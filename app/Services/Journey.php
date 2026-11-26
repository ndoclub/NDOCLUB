<?php
namespace App\Services;

use App\Core\DB;

/**
 * Jornada do paciente, parte 1: Renovação e Saída (Atualização 11).
 *
 * A Jornada é uma lista de TAREFAS que o sistema gera sozinho a partir das datas do paciente
 * (vencimento, plano, consultas) e das respostas que o time registra. Cada tarefa tem gatilho, prazo e
 * responsável; tarefa que passa do prazo aparece como atrasada. O sistema NUNCA manda mensagem:
 * só lembra quem precisa agir.
 *
 * - "Ciclo" é o mesmo das consultas: quantas renovações o paciente já teve. Renovou = ciclo novo = tarefas novas.
 * - journey_tasks.status: pending (a fazer), done (feita), skipped (deixou de valer sozinha), prior (anterior ao sistema).
 * - A linha do tempo (journey_events) nunca é editada nem apagada: correção = nova linha.
 * - Só o admin vê. Nada daqui vai para o app do paciente.
 */
final class Journey
{
    /** Tipos de jornada do plano. Smart é igual ao Premium (o intervalo entre consultas sai de duração ÷ consultas). */
    public const TYPES = [
        'premium'   => 'Premium (consultas e dossiê)',
        'smart'     => 'Smart (consultas a cada 60 dias)',
        'escalavel' => 'Escalável (sem consultas)',
        'mensal'    => 'Mensal (1 consulta, sem dossiê)',
    ];

    /** Resposta dada na última consulta. */
    public const ANSWERS = [
        'renova'         => 'Vai renovar',
        'data_combinada' => 'Data combinada',
        'vai_pensar'     => 'Vai pensar',
        'nao_renova'     => 'Não renova',
    ];

    /** Motivos de saída (quem sai da carteira). */
    public const EXIT_REASONS = [
        'preco'      => 'Preço',
        'tempo'      => 'Falta de tempo',
        'outro_prof' => 'Outro profissional',
        'esfriou'    => 'Esfriou',
        'pausa'      => 'Pausa (volta depois)',
        'outro'      => 'Outro',
    ];

    /** Tarefas: código => [título, dica, quem faz (chave dos ajustes)]. */
    public const TASKS = [
        'dossie'            => ['Dossiê pronto (Gamma)', 'Antes e depois, gordura perdida e evolução no período. Fica pronto antes da última consulta.', 'dossie'],
        'ultima_consulta'   => ['Última consulta: apresentar o dossiê e registrar a resposta', 'O Gabriel pergunta se o paciente vai renovar ou subir de plano. Registre a resposta na ficha (aba Jornada).', 'ultima'],
        'contato_renovacao' => ['Contato de renovação', 'Falar com o paciente sobre a renovação (gerar o link e confirmar o pagamento).', 'contato'],
        'atualizar_plano'   => ['Registrar a renovação (plano, valor e datas)', 'Quando o pagamento cair, use "Renovar" na ficha (aba Plano e financeiro). Esta tarefa fecha sozinha.', 'contato'],
        'sair_grupo'        => ['Remover o paciente do grupo, sair e apagar o grupo', 'O plano venceu sem renovar. O paciente fica em "Vencidos" até o time decidir.', 'suporte'],
        'recontato_pausa'   => ['Recontato da pausa', 'O paciente pediu uma pausa. Ver se quer voltar.', 'contato'],
        'mensal_msg'        => ['Mensagem do Mensal: perguntar se quer entrar em um plano', 'O plano mensal está acabando e o paciente ainda não fechou um plano.', 'contato'],
    ];

    /** Tarefas que o sistema fecha sozinho (não têm botão "Concluir"). */
    public const AUTO_DONE = ['ultima_consulta', 'atualizar_plano'];

    /** Só nascem tarefas de prazo próximo (dias à frente), para a lista não encher de coisa distante. */
    public const HORIZON_DAYS = 30;

    // ------------------------------------------------------------------ ajustes

    /** Ajustes da Jornada (com os valores do Gabriel como padrão). */
    public static function cfg(): array
    {
        $n = fn(string $k, int $d, int $min, int $max): int => max($min, min($max, (int) Settings::get($k, $d)));
        $who = fn(string $k, string $d): string => trim((string) Settings::get($k, $d)) ?: $d;
        return [
            'start'    => (string) Settings::get('journey_start_on', today()),
            'window'   => $n('journey_window', 75, 15, 365),
            'd_dossie' => $n('journey_days_dossie', 7, 0, 60),
            'd_contato' => $n('journey_days_contact', 15, 0, 90),
            'd_pausa'  => $n('journey_days_pause', 60, 1, 365),
            'raise_min' => $n('journey_raise_min', 10, 0, 100),
            'raise_max' => $n('journey_raise_max', 25, 0, 100),
            'who' => [
                'dossie'  => $who('journey_who_dossie', 'Kauanny'),
                'ultima'  => $who('journey_who_ultima', 'Gabriel'),
                'contato' => $who('journey_who_contact', 'Isabela'),
                'suporte' => $who('journey_who_support', 'Suporte'),
            ],
        ];
    }

    public static function typeOf($t): string
    {
        return is_string($t) && isset(self::TYPES[$t]) ? $t : 'premium';
    }

    public static function addDays(string $date, int $n): string
    {
        return (new \DateTimeImmutable(substr($date, 0, 10)))->modify(($n >= 0 ? '+' : '') . $n . ' day')->format('Y-m-d');
    }

    /** Intervalo entre consultas = duração ÷ consultas (Trimestral 90÷2 = 45; Smart 180÷3 = 60). null = plano sem consultas. */
    public static function interval(int $duration, ?int $cota): ?int
    {
        return ($cota !== null && $cota >= 1 && $duration > 0) ? max(1, intdiv($duration, $cota)) : null;
    }

    /** Dia estimado da última consulta: começo do ciclo + (consultas − 1) × intervalo. Trimestral = dia 45; Anual = dia 315. */
    public static function estimateLast(string $renewal, int $duration, int $cota): string
    {
        $start = self::addDays($renewal, -$duration);
        return self::addDays($start, ($cota - 1) * (int) self::interval($duration, $cota));
    }

    /**
     * Data da última consulta do plano atual e de onde ela vem:
     * marcada (alguém registrou o dia), feita (a consulta de número "cota" já foi registrada) ou estimada.
     */
    public static function lastConsult(?array $cyc, ?string $renewal, int $duration, ?int $cota, array $heldDates): array
    {
        if ($cyc && !empty($cyc['last_consult_on'])) {
            return ['date' => substr((string) $cyc['last_consult_on'], 0, 10), 'source' => 'marcada'];
        }
        if ($cota !== null && $cota >= 1) {
            if (count($heldDates) >= $cota) {
                return ['date' => substr((string) $heldDates[$cota - 1], 0, 10), 'source' => 'feita'];
            }
            if ($renewal) {
                return ['date' => self::estimateLast(substr($renewal, 0, 10), $duration, $cota), 'source' => 'estimada'];
            }
        }
        return ['date' => null, 'source' => null];
    }

    // ------------------------------------------------------------------ geração das tarefas

    /** Atualiza as tarefas de todos os pacientes (no máximo a cada 10 minutos, a menos que $force). */
    public static function sync(bool $force = false): void
    {
        if (!$force) {
            $last = (int) Settings::get('journey_synced_at', 0);
            if (time() - $last < 600) {
                return;
            }
        }
        Settings::set('journey_synced_at', (string) time());
        self::reconcile(null);
    }

    /** Atualiza as tarefas de um paciente (depois de renovar, mudar plano, registrar consulta etc.). */
    public static function syncUser(int $userId): void
    {
        self::reconcile($userId);
    }

    /** Lê o que a conciliação precisa: pacientes, ciclos, tarefas e consultas feitas. */
    private static function load(?int $only): array
    {
        $where = "u.role = 'patient'" . ($only ? ' AND u.id = ?' : '');
        $p = $only ? [$only] : [];
        $users = DB::all(
            "SELECT u.id, u.name, u.status, u.renewal_date, u.plan_id, u.exit_reason, u.exited_on, u.recontact_on,
                    pl.name AS plan_name, pl.duration_days AS dur, pl.consultations_included AS cota, pl.journey_type AS jtype,
                    (SELECT COUNT(*) FROM renewals rn WHERE rn.user_id = u.id) AS cyc
               FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id WHERE $where ORDER BY u.id",
            $p
        );
        $cycles = $tasks = $held = [];
        foreach (DB::all('SELECT * FROM journey_cycles' . ($only ? ' WHERE user_id = ?' : ''), $p) as $r) {
            $cycles[(int) $r['user_id']][(int) $r['cycle']] = $r;
        }
        foreach (DB::all('SELECT * FROM journey_tasks' . ($only ? ' WHERE user_id = ?' : '') . ' ORDER BY id', $p) as $r) {
            $tasks[(int) $r['user_id']][] = $r;
        }
        foreach (DB::all('SELECT user_id, cycle, held_on FROM consultations' . ($only ? ' WHERE user_id = ?' : '') . ' ORDER BY user_id, cycle, held_on, id', $p) as $r) {
            $held[(int) $r['user_id']][(int) $r['cycle']][] = (string) $r['held_on'];
        }
        return [$users, $cycles, $tasks, $held];
    }

    private static function reconcile(?int $only): void
    {
        $cfg = self::cfg();
        [$users, $cycles, $tasks, $held] = self::load($only);
        foreach ($users as $u) {
            $id = (int) $u['id'];
            self::reconcileUser($u, $cfg, $tasks[$id] ?? [], $cycles[$id] ?? [], $held[$id] ?? []);
        }
    }

    /** O que cada paciente DEVERIA ter de tarefa agora: código => [prazo, só nasce perto do prazo?]. */
    public static function desired(array $u, array $cfg, ?array $cyc, array $heldInCycle): array
    {
        $out = [];
        $R = !empty($u['renewal_date']) ? substr((string) $u['renewal_date'], 0, 10) : null;
        if (!$R || empty($u['plan_id'])) {
            return $out;
        }
        $type = self::typeOf($u['jtype'] ?? null);
        $dur = (int) $u['dur'];
        $cota = ($u['cota'] === null || $u['cota'] === '') ? null : (int) $u['cota'];
        $answer = $cyc['answer'] ?? null;
        $withConsults = in_array($type, ['premium', 'smart'], true) && $cota !== null && $cota >= 1;

        if ($withConsults && $answer === null) {
            $lc = self::lastConsult($cyc, $R, $dur, $cota, $heldInCycle)['date'];
            if ($lc) {
                $out['dossie'] = [self::addDays($lc, -$cfg['d_dossie']), true];
                $out['ultima_consulta'] = [$lc, true];
            }
        }
        if ($answer === 'data_combinada' && !empty($cyc['agreed_on'])) {
            $out['contato_renovacao'] = [substr((string) $cyc['agreed_on'], 0, 10), false];
        } elseif ($answer === 'vai_pensar' && !empty($cyc['next_consult_on'])) {
            $out['contato_renovacao'] = [self::addDays(substr((string) $cyc['next_consult_on'], 0, 10), -$cfg['d_contato']), false];
        } elseif ($answer === null) {
            if ($type === 'mensal') {
                $out['mensal_msg'] = [self::addDays($R, -$cfg['d_contato']), true];
            } elseif ($type === 'escalavel' || !$withConsults) {
                // sem consultas para marcar o dossiê (Escalável, ou plano sem a cota de consultas preenchida): contato 15 dias antes do fim
                $out['contato_renovacao'] = [self::addDays($R, -$cfg['d_contato']), true];
            }
        }
        if ($answer === 'renova') {
            $out['atualizar_plano'] = [!empty($cyc['answer_on']) ? substr((string) $cyc['answer_on'], 0, 10) : today(), false];
        }
        if ($R < today()) {
            $out['sair_grupo'] = [self::addDays($R, 1), false];
        }
        return $out;
    }

    private static function reconcileUser(array $u, array $cfg, array $mine, array $cycRows, array $heldByCycle): void
    {
        $uid = (int) $u['id'];
        $cycle = (int) $u['cyc'];
        $today = today();
        $cur = [];
        foreach ($mine as $t) {
            $tc = (int) $t['cycle'];
            if ($tc === $cycle) {
                $cur[$t['code']] = $t;
            } elseif ($t['status'] === 'pending') {
                // ciclo antigo: renovou (a "atualizar o plano" fica feita) ou o ciclo deixou de existir
                if ($tc < $cycle && $t['code'] === 'atualizar_plano') {
                    self::setStatus((int) $t['id'], 'done', 'Renovação registrada.');
                } else {
                    self::setStatus((int) $t['id'], 'skipped', $tc < $cycle ? 'O plano foi renovado: não vale mais.' : 'Deixou de valer.');
                }
            }
        }

        // Paciente inativo: nada pendente, só o recontato da pausa
        if ($u['status'] === 'inactive') {
            foreach ($cur as $code => $t) {
                if ($t['status'] === 'pending' && $code !== 'recontato_pausa') {
                    self::setStatus((int) $t['id'], 'skipped', 'Paciente inativo.');
                }
            }
            $pause = $u['exit_reason'] === 'pausa' && !empty($u['recontact_on']);
            $t = $cur['recontato_pausa'] ?? null;
            if ($pause && !$t) {
                self::insertTask($uid, $cycle, 'recontato_pausa', substr((string) $u['recontact_on'], 0, 10), 'pending', $cfg);
            } elseif ($pause && $t && $t['status'] === 'pending' && substr((string) $t['due_on'], 0, 10) !== substr((string) $u['recontact_on'], 0, 10)) {
                DB::run('UPDATE journey_tasks SET due_on = ?, updated_at = ? WHERE id = ?', [substr((string) $u['recontact_on'], 0, 10), now(), (int) $t['id']]);
            } elseif (!$pause && $t && $t['status'] === 'pending') {
                self::setStatus((int) $t['id'], 'skipped', 'Deixou de valer.');
            }
            return;
        }

        $cyc = $cycRows[$cycle] ?? null;
        $want = self::desired($u, $cfg, $cyc, $heldByCycle[$cycle] ?? []);
        $horizon = self::addDays($today, self::HORIZON_DAYS);

        foreach ($want as $code => [$due, $gated]) {
            $who = $cfg['who'][self::TASKS[$code][2]] ?? null;
            $t = $cur[$code] ?? null;
            if (!$t) {
                if ($gated && $due > $horizon) {
                    continue; // ainda longe: nasce mais perto do prazo
                }
                self::insertTask($uid, $cycle, $code, $due, $due < $cfg['start'] ? 'prior' : 'pending', $cfg);
            } elseif ($t['status'] === 'pending' || $t['status'] === 'skipped') {
                // pendente: acompanha mudança de data e de responsável; "deixou de valer" volta se voltou a valer
                $same = substr((string) $t['due_on'], 0, 10) === $due && (string) $t['assignee'] === (string) $who && $t['status'] === 'pending';
                if (!$same) {
                    DB::run(
                        'UPDATE journey_tasks SET due_on = ?, assignee = ?, status = ?, note = ?, updated_at = ? WHERE id = ?',
                        [$due, $who, 'pending', $t['status'] === 'skipped' ? null : $t['note'], now(), (int) $t['id']]
                    );
                }
            }
        }
        foreach ($cur as $code => $t) {
            if ($t['status'] === 'pending' && !isset($want[$code])) {
                self::setStatus((int) $t['id'], 'skipped', 'Deixou de valer.');
            }
        }
    }

    private static function insertTask(int $userId, int $cycle, string $code, string $due, string $status, array $cfg): void
    {
        $who = $cfg['who'][self::TASKS[$code][2]] ?? null;
        try {
            DB::run(
                'INSERT INTO journey_tasks (user_id, cycle, code, assignee, status, due_on, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $cycle, $code, $who, $status, $due, $status === 'prior' ? 'Anterior ao sistema.' : null, now(), now()]
            );
        } catch (\Throwable $e) {
            // duas telas gerando ao mesmo tempo: a chave única já garantiu uma só tarefa
        }
    }

    private static function setStatus(int $taskId, string $status, ?string $note, ?int $by = null): void
    {
        DB::run(
            'UPDATE journey_tasks SET status = ?, note = ?, done_at = ?, done_by = ?, updated_at = ? WHERE id = ?',
            [$status, $note, $status === 'done' ? now() : null, $status === 'done' ? $by : null, now(), $taskId]
        );
    }

    // ------------------------------------------------------------------ linha do tempo

    public static function log(int $userId, ?int $cycle, ?string $code, string $kind, string $summary, ?int $by = null): void
    {
        DB::run(
            'INSERT INTO journey_events (user_id, cycle, code, kind, summary, by_user_id, happened_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $cycle, $code, $kind, mb_substr($summary, 0, 255), $by, now()]
        );
    }

    // ------------------------------------------------------------------ ações do time

    /** Conclui uma tarefa. Retorna ['ok' => texto] ou ['err' => texto]. */
    public static function complete(int $taskId, ?int $by, string $note = ''): array
    {
        $t = DB::one('SELECT t.*, u.name FROM journey_tasks t JOIN users u ON u.id = t.user_id WHERE t.id = ?', [$taskId]);
        if (!$t) {
            return ['err' => 'Tarefa não encontrada.'];
        }
        if ($t['status'] !== 'pending') {
            return ['err' => 'Esta tarefa já não está pendente.'];
        }
        if ($t['code'] === 'ultima_consulta') {
            return ['err' => 'Para fechar a última consulta, registre a resposta do paciente na ficha (aba Jornada).'];
        }
        if ($t['code'] === 'atualizar_plano') {
            return ['err' => 'Esta tarefa fecha sozinha quando você usa "Renovar" na ficha do paciente.'];
        }
        $note = mb_substr(trim($note), 0, 255);
        self::setStatus($taskId, 'done', $note !== '' ? $note : null, $by);
        self::log((int) $t['user_id'], (int) $t['cycle'], $t['code'], 'tarefa', 'Concluída: ' . (self::TASKS[$t['code']][0] ?? $t['code']) . ($note !== '' ? ' (' . $note . ')' : ''), $by);
        return ['ok' => 'Tarefa concluída: ' . $t['name'] . '.', 'user_id' => (int) $t['user_id']];
    }

    /** Observação livre na linha do tempo do paciente (só o admin vê; pode ter dado de saúde). */
    public static function addNote(int $userId, string $text, ?int $by): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        $text = trim($text);
        if (mb_strlen($text) < 2) {
            return ['err' => 'Escreva a observação.'];
        }
        self::log($userId, (int) $u['cyc'], null, 'nota', 'Observação: ' . $text, $by);
        return ['ok' => 'Observação registrada na linha do tempo.'];
    }

    /** Desfaz uma conclusão (erro de clique). Fica na linha do tempo. */
    public static function reopen(int $taskId, ?int $by): array
    {
        $t = DB::one('SELECT t.*, u.name FROM journey_tasks t JOIN users u ON u.id = t.user_id WHERE t.id = ?', [$taskId]);
        if (!$t || $t['status'] !== 'done') {
            return ['err' => 'Só dá para reabrir uma tarefa concluída.'];
        }
        if (in_array($t['code'], self::AUTO_DONE, true)) {
            return ['err' => 'Esta tarefa se fecha sozinha (resposta registrada ou renovação). Para mudar, registre a resposta de novo na ficha.'];
        }
        DB::run("UPDATE journey_tasks SET status = 'pending', note = NULL, done_at = NULL, done_by = NULL, updated_at = ? WHERE id = ?", [now(), $taskId]);
        self::log((int) $t['user_id'], (int) $t['cycle'], $t['code'], 'tarefa', 'Reaberta: ' . (self::TASKS[$t['code']][0] ?? $t['code']), $by);
        return ['ok' => 'Tarefa reaberta.', 'user_id' => (int) $t['user_id']];
    }

    /** Dados do paciente que as ações precisam (só paciente com plano e vencimento). */
    private static function patientRow(int $userId): ?array
    {
        return DB::one(
            "SELECT u.id, u.name, u.status, u.renewal_date, u.plan_id, u.exit_reason,
                    pl.duration_days AS dur, pl.consultations_included AS cota, pl.journey_type AS jtype,
                    (SELECT COUNT(*) FROM renewals rn WHERE rn.user_id = u.id) AS cyc
               FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id WHERE u.id = ? AND u.role = 'patient'",
            [$userId]
        );
    }

    private static function upsertCycle(int $userId, int $cycle, array $fields): void
    {
        $row = DB::one('SELECT id FROM journey_cycles WHERE user_id = ? AND cycle = ?', [$userId, $cycle]);
        if ($row) {
            $set = [];
            $vals = [];
            foreach ($fields as $k => $v) {
                $set[] = $k . ' = ?';
                $vals[] = $v;
            }
            $set[] = 'updated_at = ?';
            $vals[] = now();
            $vals[] = (int) $row['id'];
            DB::run('UPDATE journey_cycles SET ' . implode(', ', $set) . ' WHERE id = ?', $vals);
        } else {
            $cols = array_merge(['user_id', 'cycle'], array_keys($fields), ['updated_at']);
            $vals = array_merge([$userId, $cycle], array_values($fields), [now()]);
            DB::run('INSERT INTO journey_cycles (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);
        }
    }

    /** Define (ou limpa) o dia da última consulta do plano. */
    public static function setLastConsult(int $userId, string $date, ?int $by): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        if ($date !== '' && (!Payments::validDate($date) || $date < self::addDays(today(), -400) || $date > self::addDays(today(), 800))) {
            return ['err' => 'Data da última consulta inválida.'];
        }
        $cycle = (int) $u['cyc'];
        self::upsertCycle($userId, $cycle, ['last_consult_on' => $date !== '' ? $date : null]);
        self::log($userId, $cycle, null, 'consulta', $date !== '' ? 'Última consulta do plano marcada para ' . fmt_date($date) . '.' : 'Dia da última consulta limpo (volta a ser estimado).', $by);
        self::syncUser($userId);
        return ['ok' => $date !== '' ? 'Última consulta marcada para ' . fmt_date($date) . '.' : 'Dia da última consulta limpo.'];
    }

    /**
     * Registra a resposta do paciente na última consulta e acerta as tarefas:
     * renova -> "registrar a renovação"; data combinada -> contato nesse dia; vai pensar -> contato 15 dias antes da consulta seguinte;
     * não renova -> nenhuma (ele segue no acompanhamento até o fim do plano).
     */
    public static function recordAnswer(int $userId, string $answer, string $agreedOn, string $nextConsult, ?int $by): array
    {
        $u = self::patientRow($userId);
        if (!$u || empty($u['plan_id']) || empty($u['renewal_date'])) {
            return ['err' => 'O paciente precisa de plano e data de vencimento para registrar a resposta.'];
        }
        if ($u['status'] !== 'active') {
            return ['err' => 'Este paciente está inativo.'];
        }
        if (!isset(self::ANSWERS[$answer])) {
            return ['err' => 'Escolha a resposta do paciente.'];
        }
        $cfg = self::cfg();
        $max = self::addDays(today(), 400);
        if ($answer === 'data_combinada') {
            if (!Payments::validDate($agreedOn) || $agreedOn < today() || $agreedOn > $max) {
                return ['err' => 'Informe a data combinada (hoje ou uma data futura).'];
            }
        } else {
            $agreedOn = '';
        }
        if ($answer === 'vai_pensar') {
            if (!Payments::validDate($nextConsult) || $nextConsult < today() || $nextConsult > $max) {
                return ['err' => 'Informe o dia da consulta seguinte (hoje ou uma data futura).'];
            }
        } else {
            $nextConsult = '';
        }
        $cycle = (int) $u['cyc'];
        self::upsertCycle($userId, $cycle, [
            'answer' => $answer, 'answer_on' => today(),
            'agreed_on' => $agreedOn !== '' ? $agreedOn : null, 'next_consult_on' => $nextConsult !== '' ? $nextConsult : null,
        ]);
        // A última consulta cumpriu o papel; o dossiê perdeu o sentido
        $cur = DB::all('SELECT * FROM journey_tasks WHERE user_id = ? AND cycle = ?', [$userId, $cycle]);
        $by_code = [];
        foreach ($cur as $t) {
            $by_code[$t['code']] = $t;
        }
        if (isset($by_code['ultima_consulta']) && in_array($by_code['ultima_consulta']['status'], ['pending', 'prior'], true)) {
            self::setStatus((int) $by_code['ultima_consulta']['id'], 'done', 'Resposta registrada: ' . self::ANSWERS[$answer] . '.', $by);
        }
        // Mudou de ideia? A tarefa de contato (já feita ou não) volta com a data nova; as que não valem mais fecham
        $contact = $by_code['contato_renovacao'] ?? null;
        if (in_array($answer, ['data_combinada', 'vai_pensar'], true)) {
            $due = $answer === 'data_combinada' ? $agreedOn : self::addDays($nextConsult, -$cfg['d_contato']);
            $who = $cfg['who']['contato'];
            if ($contact) {
                DB::run("UPDATE journey_tasks SET status = 'pending', due_on = ?, assignee = ?, note = NULL, done_at = NULL, done_by = NULL, updated_at = ? WHERE id = ?", [$due, $who, now(), (int) $contact['id']]);
            } else {
                self::insertTask($userId, $cycle, 'contato_renovacao', $due, 'pending', $cfg);
            }
        } elseif ($contact && $contact['status'] === 'pending') {
            self::setStatus((int) $contact['id'], 'skipped', 'Deixou de valer.');
        }
        $label = self::ANSWERS[$answer] . ($agreedOn !== '' ? ' (' . fmt_date($agreedOn) . ')' : '') . ($nextConsult !== '' ? ' (consulta seguinte em ' . fmt_date($nextConsult) . ')' : '');
        self::log($userId, $cycle, 'ultima_consulta', 'resposta', 'Resposta registrada: ' . $label . '.', $by);
        self::syncUser($userId);
        $msg = 'Resposta registrada: ' . $label . '.';
        if ($answer === 'nao_renova') {
            $msg .= ' O paciente segue no acompanhamento até ' . fmt_date(substr((string) $u['renewal_date'], 0, 10)) . '.';
        }
        return ['ok' => $msg];
    }

    /**
     * Tira o paciente da carteira com motivo. A conta fica inativa (não consegue entrar no app) e ele vai para a base de reativação.
     * Pausa: o sistema cria o recontato para daqui a alguns dias (padrão 60).
     */
    public static function inactivate(int $userId, string $reason, string $note, ?int $by): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        if (!isset(self::EXIT_REASONS[$reason])) {
            return ['err' => 'Escolha o motivo da saída.'];
        }
        $cfg = self::cfg();
        $note = mb_substr(trim($note), 0, 255);
        $recontact = $reason === 'pausa' ? self::addDays(today(), $cfg['d_pausa']) : null;
        DB::run(
            "UPDATE users SET status = 'inactive', exit_reason = ?, exited_on = ?, exit_note = ?, recontact_on = ? WHERE id = ?",
            [$reason, today(), $note !== '' ? $note : null, $recontact, $userId]
        );
        $cycle = (int) $u['cyc'];
        self::log($userId, $cycle, null, 'saida', 'Inativado. Motivo: ' . self::EXIT_REASONS[$reason] . ($note !== '' ? ' (' . $note . ')' : '') . ($recontact ? '. Recontato em ' . fmt_date($recontact) . '.' : '.'), $by);
        self::syncUser($userId);
        return ['ok' => $u['name'] . ' foi para os Inativos (motivo: ' . self::EXIT_REASONS[$reason] . ').' . ($recontact ? ' Recontato marcado para ' . fmt_date($recontact) . '.' : '')];
    }

    /** O paciente voltou: conta ativa de novo e motivo de saída limpo. O histórico da saída fica na linha do tempo. */
    public static function reactivate(int $userId, ?int $by, string $why = 'Voltou para a carteira.', bool $statusAlreadyActive = false): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        // $statusAlreadyActive: o cadastro acabou de gravar "ativo" e só falta limpar o motivo de saída
        $stillExited = $statusAlreadyActive && !empty($u['exit_reason']);
        if ($u['status'] !== 'inactive' && !$stillExited) {
            return ['err' => 'Este paciente já está ativo.'];
        }
        DB::run("UPDATE users SET status = 'active', exit_reason = NULL, exited_on = NULL, exit_note = NULL, recontact_on = NULL WHERE id = ?", [$userId]);
        $cycle = (int) $u['cyc'];
        $pend = DB::one("SELECT id FROM journey_tasks WHERE user_id = ? AND cycle = ? AND code = 'recontato_pausa' AND status = 'pending'", [$userId, $cycle]);
        if ($pend) {
            self::setStatus((int) $pend['id'], 'done', 'Voltou.', $by);
        }
        self::log($userId, $cycle, null, 'volta', $why, $by);
        self::syncUser($userId);
        return ['ok' => $u['name'] . ' voltou para os pacientes ativos.'];
    }

    /** Chamado logo depois de uma renovação: registra na linha do tempo e reativa quem tinha saído. */
    public static function onRenewed(int $userId, ?int $by, string $newDate): void
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return;
        }
        // o ciclo já é o novo (a renovação já gravou a linha em "renewals")
        self::log($userId, (int) $u['cyc'], null, 'renovou', 'Renovou até ' . fmt_date($newDate) . '. Novo ciclo do plano.', $by);
        if ($u['status'] === 'inactive' && !empty($u['exit_reason'])) {
            self::reactivate($userId, $by, 'Renovou o plano e voltou para a carteira.');
            return;
        }
        self::syncUser($userId);
    }

    // ------------------------------------------------------------------ telas

    /** Números do Painel e do selo do menu: tarefas atrasadas e de hoje. Nunca derruba a tela (tabelas ainda não importadas = zeros). */
    public static function attention(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = ['late' => 0, 'today' => 0, 'due' => 0];
        try {
            self::sync(false);
            $t = today();
            $r = DB::one(
                "SELECT SUM(CASE WHEN due_on < ? THEN 1 ELSE 0 END) AS late, SUM(CASE WHEN due_on = ? THEN 1 ELSE 0 END) AS today
                   FROM journey_tasks WHERE status = 'pending' AND due_on IS NOT NULL",
                [$t, $t]
            ) ?: [];
            $cache['late'] = (int) ($r['late'] ?? 0);
            $cache['today'] = (int) ($r['today'] ?? 0);
            $cache['due'] = $cache['late'] + $cache['today'];
        } catch (\Throwable $e) {
            // sem as tabelas da Jornada (SQL 11 não importado): sem alerta
        }
        return $cache;
    }

    /** Tarefas pendentes com dados do paciente, na ordem do prazo. $who vazio = de todos. */
    public static function pending(string $who = ''): array
    {
        $sql = "SELECT t.*, u.name AS patient, u.phone, pl.name AS plan_name
                  FROM journey_tasks t JOIN users u ON u.id = t.user_id LEFT JOIN plans pl ON pl.id = u.plan_id
                 WHERE t.status = 'pending' AND u.role = 'patient'";
        $p = [];
        if ($who === '__none') {
            $sql .= " AND (t.assignee IS NULL OR t.assignee = '')";
        } elseif ($who !== '') {
            $sql .= ' AND t.assignee = ?';
            $p[] = $who;
        }
        return DB::all($sql . ' ORDER BY t.due_on ASC, u.name ASC, t.id ASC', $p);
    }

    /** Quantas tarefas pendentes cada responsável tem (para os atalhos). */
    public static function pendingByWho(): array
    {
        $out = [];
        foreach (DB::all("SELECT COALESCE(NULLIF(t.assignee, ''), '__none') AS who, COUNT(*) AS n FROM journey_tasks t JOIN users u ON u.id = t.user_id WHERE t.status = 'pending' AND u.role = 'patient' GROUP BY 1 ORDER BY 1") as $r) {
            $out[$r['who']] = (int) $r['n'];
        }
        return $out;
    }

    /** Em que ponto o paciente está na renovação (texto curto para a lista). */
    public static function stageText(?array $cyc, array $pendingCodes, bool $withConsults, ?string $lastDate = null): string
    {
        $answer = $cyc['answer'] ?? null;
        if ($answer === 'renova') {
            return 'Vai renovar: falta registrar a renovação';
        }
        if ($answer === 'data_combinada') {
            return 'Data combinada: ' . fmt_date($cyc['agreed_on'] ?? null);
        }
        if ($answer === 'vai_pensar') {
            return 'Vai pensar: contato antes de ' . fmt_date($cyc['next_consult_on'] ?? null);
        }
        if ($answer === 'nao_renova') {
            return 'Não renova (segue até o fim do plano)';
        }
        if (in_array('dossie', $pendingCodes, true)) {
            return 'Dossiê a preparar';
        }
        if (in_array('ultima_consulta', $pendingCodes, true)) {
            return 'Última consulta: registrar a resposta';
        }
        if (in_array('contato_renovacao', $pendingCodes, true) || in_array('mensal_msg', $pendingCodes, true)) {
            return 'Contato de renovação';
        }
        if ($withConsults) {
            return ($lastDate !== null && $lastDate < today()) ? 'Última consulta já passou: registrar a resposta' : 'Aguardando a última consulta';
        }
        return 'Aguardando o contato de renovação';
    }

    /**
     * Aba "Renovações": quem vence dentro da janela (ou já tem tarefa de renovação aberta) e em que ponto está.
     * Não inclui quem já venceu (vai para "Vencidos") nem quem saiu.
     */
    public static function renewals(): array
    {
        $cfg = self::cfg();
        $limit = self::addDays(today(), $cfg['window']);
        [$users, $cycles, $tasks, $held] = self::load(null);
        $rows = [];
        foreach ($users as $u) {
            if ($u['status'] !== 'active' || empty($u['renewal_date']) || empty($u['plan_id'])) {
                continue;
            }
            $R = substr((string) $u['renewal_date'], 0, 10);
            if ($R < today()) {
                continue;
            }
            $id = (int) $u['id'];
            $cycle = (int) $u['cyc'];
            $cyc = $cycles[$id][$cycle] ?? null;
            $pend = [];
            foreach ($tasks[$id] ?? [] as $t) {
                if ((int) $t['cycle'] === $cycle && $t['status'] === 'pending') {
                    $pend[$t['code']] = $t;
                }
            }
            $renewalTask = array_intersect_key($pend, array_flip(['dossie', 'ultima_consulta', 'contato_renovacao', 'atualizar_plano', 'mensal_msg']));
            if ($R > $limit && !$renewalTask && empty($cyc['answer'])) {
                continue;
            }
            $type = self::typeOf($u['jtype']);
            $cota = ($u['cota'] === null || $u['cota'] === '') ? null : (int) $u['cota'];
            $withConsults = in_array($type, ['premium', 'smart'], true) && $cota !== null && $cota >= 1;
            $lc = $withConsults ? self::lastConsult($cyc, $R, (int) $u['dur'], $cota, $held[$id][$cycle] ?? []) : ['date' => null, 'source' => null];
            $next = null;
            foreach ($renewalTask as $t) {
                if ($next === null || (string) $t['due_on'] < (string) $next['due_on']) {
                    $next = $t;
                }
            }
            $rows[] = [
                'id' => $id, 'name' => $u['name'], 'plan' => $u['plan_name'], 'type' => $type, 'renewal' => $R,
                'days' => (int) days_until($R), 'last' => $lc, 'answer' => $cyc['answer'] ?? null, 'cyc' => $cyc,
                'stage' => self::stageText($cyc, array_keys($pend), $withConsults, $lc['date']), 'next' => $next,
            ];
        }
        usort($rows, fn($a, $b) => [$a['renewal'], $a['name']] <=> [$b['renewal'], $b['name']]);
        return $rows;
    }

    /** Aba "Vencidos": plano venceu e ninguém decidiu ainda (nem renovou, nem inativou). */
    public static function expired(): array
    {
        $rows = DB::all(
            "SELECT u.id, u.name, u.renewal_date, u.phone, pl.name AS plan_name, u.plan_id,
                    (SELECT COUNT(*) FROM renewals rn WHERE rn.user_id = u.id) AS cyc
               FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date IS NOT NULL AND u.renewal_date < ?
              ORDER BY u.renewal_date DESC, u.name ASC",
            [today()]
        );
        $tasks = [];
        foreach (DB::all("SELECT user_id, cycle, code, status FROM journey_tasks WHERE code = 'sair_grupo'") as $t) {
            $tasks[(int) $t['user_id'] . ':' . (int) $t['cycle']] = $t['status'];
        }
        $answers = [];
        foreach (DB::all('SELECT user_id, cycle, answer FROM journey_cycles WHERE answer IS NOT NULL') as $c) {
            $answers[(int) $c['user_id'] . ':' . (int) $c['cycle']] = $c['answer'];
        }
        foreach ($rows as &$r) {
            $k = (int) $r['id'] . ':' . (int) $r['cyc'];
            $r['group'] = $tasks[$k] ?? null; // pending, done, prior ou null
            $r['answer'] = $answers[$k] ?? null;
            $r['days'] = (int) days_until(substr((string) $r['renewal_date'], 0, 10));
        }
        unset($r);
        return $rows;
    }

    /** Tudo da Jornada de UM paciente (aba da ficha). */
    public static function forPatient(int $userId): array
    {
        $u = self::patientRow($userId);
        $cfg = self::cfg();
        $cycle = (int) ($u['cyc'] ?? 0);
        $cyc = DB::one('SELECT * FROM journey_cycles WHERE user_id = ? AND cycle = ?', [$userId, $cycle]);
        $held = array_map(fn($r) => (string) $r['held_on'], DB::all('SELECT held_on FROM consultations WHERE user_id = ? AND cycle = ? ORDER BY held_on, id', [$userId, $cycle]));
        $type = self::typeOf($u['jtype'] ?? null);
        $cota = ($u && $u['cota'] !== null && $u['cota'] !== '') ? (int) $u['cota'] : null;
        $withConsults = in_array($type, ['premium', 'smart'], true) && $cota !== null && $cota >= 1;
        $R = $u && !empty($u['renewal_date']) ? substr((string) $u['renewal_date'], 0, 10) : null;
        $lc = ($withConsults && $u) ? self::lastConsult($cyc, $R, (int) $u['dur'], $cota, $held) : ['date' => null, 'source' => null];
        $tasks = DB::all('SELECT * FROM journey_tasks WHERE user_id = ? AND cycle = ? ORDER BY due_on, id', [$userId, $cycle]);
        $done = 0;
        $total = 0;
        foreach ($tasks as $t) {
            if ($t['status'] === 'done') {
                $done++;
                $total++;
            } elseif ($t['status'] === 'pending') {
                $total++;
            }
        }
        $price = Plans::priceInfoFor($userId);
        $reajuste = null;
        if ($price['amount'] !== null && $price['planPrice'] !== null && $price['currency'] === 'BRL') {
            $diff = round((float) $price['planPrice'] - (float) $price['amount'], 2);
            $reajuste = [
                'now' => (float) $price['amount'], 'table' => (float) $price['planPrice'], 'diff' => $diff,
                'min' => round((float) $price['amount'] * (1 + $cfg['raise_min'] / 100), 2),
                'max' => round((float) $price['amount'] * (1 + $cfg['raise_max'] / 100), 2),
            ];
        }
        return [
            'u' => $u, 'cfg' => $cfg, 'cycle' => $cycle, 'cyc' => $cyc, 'type' => $type, 'typeLabel' => self::TYPES[$type],
            'withConsults' => $withConsults, 'last' => $lc, 'interval' => $u ? self::interval((int) $u['dur'], $cota) : null,
            'tasks' => $tasks, 'done' => $done, 'total' => $total,
            'events' => DB::all('SELECT * FROM journey_events WHERE user_id = ? ORDER BY happened_at DESC, id DESC LIMIT 40', [$userId]),
            'reajuste' => $reajuste, 'heldCount' => count($held), 'cota' => $cota,
        ];
    }
}
