<?php
namespace App\Services;

use App\Core\DB;

/**
 * Jornada do paciente: Renovação e Saída (Atualização 11) e Entrada do paciente novo (Atualização 12).
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
 *
 * Quem recebe cada tarefa (Atualização 12), sem escala fixa:
 *  - tarefa "de função" (grupo, consulta, plano...): o nome definido em Ajustes (Lara, Gabriel, Isabela...);
 *  - tarefa "de cuidado" (confirmar acesso e, depois, o Modo Babá): o Responsável da ficha do paciente; sem ele,
 *    o plantão da semana; sem plantão, fica "sem responsável" e aparece para todos (nunca some);
 *  - qualquer pessoa pode passar uma tarefa (ou várias) para outra; a tarefa passada fica "travada" nessa pessoa.
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
        // Entrada (paciente novo ou que voltou)
        'entrada_grupo'    => ['Criar o grupo VIP no WhatsApp e mandar as boas-vindas', 'Nome padrão: ACOMPANHAMENTO PREMIUM – Nome do paciente. Membros: paciente, número comercial e número de suporte. Nas boas-vindas: o que acontece até a consulta, como funciona o suporte e o horário de atendimento.', 'grupo'],
        'entrada_welts'    => ['Cadastrar no Welts (check-in)', 'Enquanto o check-in não migra para a plataforma.', 'grupo'],
        'entrada_atleta'   => ['Cadastro de atleta', 'Modalidade, assessoria, próximas provas e treinos longos (aba Resumo da ficha, parte "Perfil esportivo").', 'atleta'],
        'entrada_agendar'  => ['Agendar a 1ª consulta', 'Agende junto com o paciente (ou o Gabriel, se a venda fechou em call, ou o próprio paciente). Informe o dia aqui: a tarefa fecha sozinha.', 'contato'],
        'entrada_consulta' => ['1ª consulta', 'Avaliação, estratégia e suplementação; o paciente sai com a próxima consulta marcada. Fecha sozinha quando a consulta é registrada na ficha (aba Acompanhamento, parte Consultas).', 'consulta'],
        'entrada_plano'    => ['Plano montado e aprovado', 'Lara ou Nívia montam; o Gabriel verifica e aprova. Prazo: 1 dia útil depois da consulta.', 'plano'],
        'entrada_treino'   => ['Treino montado (plano com treino)', 'O Gabriel monta em até 2 dias úteis depois da consulta (fim de semana não conta).', 'consulta'],
        'entrada_form'     => ['Formulário do paciente (Escalável)', 'Enviar o formulário e receber de volta. Informe o dia em que chegou: a tarefa fecha sozinha.', 'plano'],
        'entrada_dieta'    => ['Dieta montada (Escalável)', 'Liberada em até 2 dias úteis depois do formulário.', 'plano'],
        'entrada_liberar'  => ['Liberar o plano no WebDiet e avisar no grupo', 'Mandar também as orientações de suplementação e os materiais. O dia da liberação calcula a confirmação D+1 (e, depois, o Modo Babá e o 1º check-in).', 'consulta'],
        'entrada_acesso'   => ['Confirmar o acesso (D+1)', 'Perguntar se conseguiu acessar, abrir e ver o plano e se ficou alguma dúvida. Sem resposta em 24h, novo contato. Não conseguiu acessar: resolver no mesmo dia.', 'cuidador'],
    ];

    /** As tarefas da Entrada, na ordem em que aparecem no checklist. */
    public const ENTRY_CODES = ['entrada_grupo', 'entrada_welts', 'entrada_atleta', 'entrada_agendar', 'entrada_consulta', 'entrada_plano', 'entrada_treino', 'entrada_form', 'entrada_dieta', 'entrada_liberar', 'entrada_acesso'];

    /** Tarefas da Entrada que o sistema fecha e reabre sozinho, conforme o dado (dia marcado, consulta registrada). */
    public const ENTRY_AUTO = ['entrada_agendar', 'entrada_consulta', 'entrada_form'];

    /** Tarefas que o sistema fecha sozinho (não têm botão "Concluir"). */
    public const AUTO_DONE = ['ultima_consulta', 'atualizar_plano', 'entrada_agendar', 'entrada_consulta', 'entrada_form'];

    /** Como a Entrada foi aberta. */
    public const ENTRY_KINDS = ['novo' => 'paciente novo', 'reativado' => 'paciente que voltou', 'manual' => 'aberta pela equipe'];

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
            'd_entry'  => $n('journey_days_entry', 1, 0, 7),
            'd_agenda' => $n('journey_days_schedule', 2, 0, 14),
            'athlete_on' => (string) Settings::get('journey_athlete_on', '1') !== '0',
            'duty'     => trim((string) Settings::get('journey_duty', '')),
            'duty_on'  => trim((string) Settings::get('journey_duty_on', '')),
            'who' => [
                'dossie'  => $who('journey_who_dossie', 'Kauanny'),
                'ultima'  => $who('journey_who_ultima', 'Gabriel'),
                'contato' => $who('journey_who_contact', 'Isabela'),
                'suporte' => $who('journey_who_support', 'Suporte'),
                'grupo'   => $who('journey_who_group', 'Lara'),
                'atleta'  => $who('journey_who_athlete', 'Nívia'),
                'consulta' => $who('journey_who_consult', 'Gabriel'),
                'plano'   => $who('journey_who_plan', 'Lara'),
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

    /** Soma dias úteis (segunda a sexta; feriado não conta como folga). Com 0 dia, fim de semana vira a próxima segunda. */
    public static function addBusinessDays(string $date, int $n): string
    {
        $d = new \DateTimeImmutable(substr($date, 0, 10));
        for ($left = max(0, $n); $left > 0;) {
            $d = $d->modify('+1 day');
            if ((int) $d->format('N') < 6) {
                $left--;
            }
        }
        while ((int) $d->format('N') >= 6) {
            $d = $d->modify('+1 day');
        }
        return $d->format('Y-m-d');
    }

    /**
     * Quem recebe a tarefa (nome) ou null = sem responsável.
     * Tarefa "de função": nome dos Ajustes. Tarefa "de cuidado": Responsável da ficha, senão o plantão, senão ninguém.
     * "Atletas com a Nívia" desligado: o cadastro de atleta também vira tarefa "de cuidado".
     */
    public static function whoFor(string $code, array $u, array $cfg): ?string
    {
        $key = self::TASKS[$code][2] ?? 'contato';
        if ($key === 'atleta' && !$cfg['athlete_on']) {
            $key = 'cuidador';
        }
        if ($key === 'cuidador') {
            $r = trim((string) ($u['resp_name'] ?? ''));
            if ($r !== '') {
                return $r;
            }
            return $cfg['duty'] !== '' ? $cfg['duty'] : null;
        }
        return $cfg['who'][$key] ?? null;
    }

    /** Equipe: quem pode receber tarefa. Responsáveis ativos + nomes dos Ajustes + quem já tem tarefa aberta. */
    public static function team(): array
    {
        $out = [];
        $add = function (string $n) use (&$out): void {
            $n = trim($n);
            if ($n !== '' && !in_array(mb_strtolower($n), array_map('mb_strtolower', $out), true)) {
                $out[] = $n;
            }
        };
        foreach (Responsibles::choices() as $r) {
            if ((int) $r['active'] === 1) {
                $add((string) $r['name']);
            }
        }
        $cfg = self::cfg();
        foreach ($cfg['who'] as $w) {
            $add((string) $w);
        }
        $add($cfg['duty']);
        try {
            foreach (DB::all("SELECT DISTINCT assignee FROM journey_tasks WHERE status = 'pending' AND assignee IS NOT NULL AND assignee <> '' ORDER BY assignee") as $r) {
                $add((string) $r['assignee']);
            }
        } catch (\Throwable $e) {
            // sem a tabela ainda: só os nomes dos Ajustes
        }
        return $out;
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

    /**
     * Atualiza as tarefas de todos os pacientes (no máximo a cada 10 minutos, a menos que $force).
     * Nunca derruba a tela: se o SQL da Jornada ainda não foi importado, só não gera tarefa (a página da Jornada avisa).
     */
    public static function sync(bool $force = false): void
    {
        if (!$force) {
            $last = (int) Settings::get('journey_synced_at', 0);
            if (time() - $last < 600) {
                return;
            }
        }
        try {
            Settings::set('journey_synced_at', (string) time());
            self::reconcile(null);
        } catch (\PDOException $e) {
            error_log('[Jornada] sync: ' . $e->getMessage());
        }
    }

    /** Atualiza as tarefas de um paciente (depois de renovar, mudar plano, registrar consulta etc.). */
    public static function syncUser(int $userId): void
    {
        try {
            self::reconcile($userId);
        } catch (\PDOException $e) {
            error_log('[Jornada] syncUser: ' . $e->getMessage());
        }
    }

    /** Lê o que a conciliação precisa: pacientes, ciclos, tarefas e consultas feitas. */
    private static function load(?int $only): array
    {
        $where = "u.role = 'patient'" . ($only ? ' AND u.id = ?' : '');
        $p = $only ? [$only] : [];
        $users = DB::all(
            "SELECT u.id, u.name, u.status, u.renewal_date, u.plan_id, u.exit_reason, u.exited_on, u.recontact_on,
                    u.responsible_id, CASE WHEN rs.active = 1 THEN rs.name ELSE NULL END AS resp_name,
                    pl.name AS plan_name, pl.duration_days AS dur, pl.consultations_included AS cota, pl.journey_type AS jtype,
                    pl.with_training AS with_training,
                    (SELECT 1 FROM athlete_profiles ap WHERE ap.user_id = u.id AND ap.is_athlete = 1) AS is_athlete,
                    (SELECT COUNT(*) FROM renewals rn WHERE rn.user_id = u.id) AS cyc
               FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id LEFT JOIN responsibles rs ON rs.id = u.responsible_id
              WHERE $where ORDER BY u.id",
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

    /**
     * Tarefas da Entrada que o paciente deveria ter agora (só se a Entrada foi aberta neste ciclo).
     * Retorna ['want' => código => [prazo, false, true], 'done' => código => nota].
     *  - want: tarefas abertas, com o prazo (24h = 1 dia; consulta, plano e treino em dias úteis);
     *  - done: tarefas que os dados já resolveram (dia da consulta marcado, consulta registrada, formulário recebido).
     * $heldDates = dias das consultas feitas neste ciclo (em ordem). $doneOn = código => dia em que a tarefa foi concluída
     * (encadeia: plano aprovado -> liberar no mesmo dia útil -> confirmação D+1).
     */
    public static function entryWant(array $u, array $cfg, array $cyc, array $heldDates, array $doneOn): array
    {
        $want = [];
        $done = [];
        if (empty($cyc['entry_on'])) {
            return ['want' => $want, 'done' => $done];
        }
        $E = substr((string) $cyc['entry_on'], 0, 10);
        $type = self::typeOf($u['jtype'] ?? null);
        $d1 = self::addDays($E, $cfg['d_entry']);
        $add = function (string $code, string $due) use (&$want): void {
            $want[$code] = [$due, false, true];
        };
        $add('entrada_grupo', $d1);
        $add('entrada_welts', $d1);
        if (!empty($u['is_athlete'])) {
            $add('entrada_atleta', $d1);
        }

        $approval = 'entrada_plano';
        if ($type === 'escalavel') {
            $approval = 'entrada_dieta';
            if (!empty($cyc['form_on'])) {
                $f = substr((string) $cyc['form_on'], 0, 10);
                $done['entrada_form'] = 'Formulário recebido em ' . fmt_date($f) . '.';
                $add('entrada_dieta', self::addBusinessDays($f, 2));
            } else {
                $add('entrada_form', self::addDays($E, $cfg['d_agenda']));
            }
        } else {
            $first = null;
            foreach ($heldDates as $h) {
                if (substr((string) $h, 0, 10) >= $E) {
                    $first = substr((string) $h, 0, 10);
                    break;
                }
            }
            $sched = !empty($cyc['first_consult_on']) ? substr((string) $cyc['first_consult_on'], 0, 10) : null;
            if ($first !== null) {
                $done['entrada_agendar'] = $sched ? 'Marcada para ' . fmt_date($sched) . '.' : 'A consulta já foi feita.';
                $done['entrada_consulta'] = 'Consulta registrada em ' . fmt_date($first) . '.';
                $add('entrada_plano', self::addBusinessDays($first, 1));
                if (!empty($u['with_training'])) {
                    $add('entrada_treino', self::addBusinessDays($first, 2));
                }
            } elseif ($sched !== null) {
                $done['entrada_agendar'] = 'Marcada para ' . fmt_date($sched) . '.';
                $add('entrada_consulta', $sched);
            } else {
                $add('entrada_agendar', self::addDays($E, $cfg['d_agenda']));
            }
        }
        if (isset($doneOn[$approval])) {
            $add('entrada_liberar', self::addBusinessDays($doneOn[$approval], 0));
        }
        if (isset($doneOn['entrada_liberar'])) {
            $add('entrada_acesso', self::addDays($doneOn['entrada_liberar'], 1));
        }
        return ['want' => $want, 'done' => $done];
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
                self::insertTask($uid, $cycle, 'recontato_pausa', substr((string) $u['recontact_on'], 0, 10), 'pending', self::whoFor('recontato_pausa', $u, $cfg));
            } elseif ($pause && $t && $t['status'] === 'pending' && substr((string) $t['due_on'], 0, 10) !== substr((string) $u['recontact_on'], 0, 10)) {
                DB::run('UPDATE journey_tasks SET due_on = ?, updated_at = ? WHERE id = ?', [substr((string) $u['recontact_on'], 0, 10), now(), (int) $t['id']]);
            } elseif (!$pause && $t && $t['status'] === 'pending') {
                self::setStatus((int) $t['id'], 'skipped', 'Deixou de valer.');
            }
            return;
        }

        $cyc = $cycRows[$cycle] ?? null;
        $held = $heldByCycle[$cycle] ?? [];
        $want = self::desired($u, $cfg, $cyc, $held);

        // Entrada (só quem teve a Entrada aberta neste ciclo)
        $entryDone = [];
        if ($cyc && !empty($cyc['entry_on'])) {
            $doneOn = [];
            foreach ($cur as $code => $t) {
                if ($t['status'] === 'done' && !empty($t['done_at'])) {
                    $doneOn[$code] = substr((string) $t['done_at'], 0, 10);
                }
            }
            $e = self::entryWant($u, $cfg, $cyc, $held, $doneOn);
            $want = array_merge($want, $e['want']);
            $entryDone = $e['done'];
        }
        $horizon = self::addDays($today, self::HORIZON_DAYS);

        foreach ($want as $code => $w) {
            [$due, $gated] = [$w[0], $w[1]];
            $noPrior = !empty($w[2]);
            $who = self::whoFor($code, $u, $cfg);
            $t = $cur[$code] ?? null;
            if (!$t) {
                if ($gated && $due > $horizon) {
                    continue; // ainda longe: nasce mais perto do prazo
                }
                self::insertTask($uid, $cycle, $code, $due, (!$noPrior && $due < $cfg['start']) ? 'prior' : 'pending', $who);
            } elseif ($t['status'] === 'pending' || $t['status'] === 'skipped') {
                // pendente: acompanha mudança de data e de responsável (menos se alguém passou a tarefa para outra pessoa);
                // "deixou de valer" volta se voltou a valer
                $manual = (int) ($t['assignee_manual'] ?? 0) === 1;
                $whoNow = $manual ? (string) $t['assignee'] : (string) $who;
                $same = substr((string) $t['due_on'], 0, 10) === $due && (string) $t['assignee'] === $whoNow && $t['status'] === 'pending';
                if (!$same) {
                    DB::run(
                        'UPDATE journey_tasks SET due_on = ?, assignee = ?, status = ?, note = ?, updated_at = ? WHERE id = ?',
                        [$due, $whoNow !== '' ? $whoNow : null, 'pending', $t['status'] === 'skipped' ? null : $t['note'], now(), (int) $t['id']]
                    );
                }
            } elseif ($t['status'] === 'done' && in_array($code, self::ENTRY_AUTO, true) && !isset($entryDone[$code])) {
                // fechou sozinha, mas o dado mudou (apagaram o dia marcado ou a consulta): volta a ficar aberta
                DB::run("UPDATE journey_tasks SET status = 'pending', due_on = ?, note = NULL, done_at = NULL, done_by = NULL, updated_at = ? WHERE id = ?", [$due, now(), (int) $t['id']]);
                self::log($uid, $cycle, $code, 'tarefa', 'Reaberta sozinha: ' . (self::TASKS[$code][0] ?? $code) . ' (o dado mudou).');
            }
        }
        foreach ($cur as $code => $t) {
            if ($t['status'] === 'pending' && !isset($want[$code]) && !isset($entryDone[$code])) {
                self::setStatus((int) $t['id'], 'skipped', 'Deixou de valer.');
            } elseif ($t['status'] === 'done' && empty($t['done_by']) && in_array($code, self::ENTRY_AUTO, true) && !isset($want[$code]) && !isset($entryDone[$code])) {
                // fechou sozinha e o dado que a fechou sumiu (consulta apagada): deixa de valer, e volta se o dado voltar
                self::setStatus((int) $t['id'], 'skipped', 'Deixou de valer.');
                self::log($uid, $cycle, $code, 'tarefa', 'Reaberta sozinha: ' . (self::TASKS[$code][0] ?? $code) . ' (o dado mudou).');
            }
        }
        // Tarefas da Entrada que o dado já resolveu: ficam "feitas" sozinhas (aparecem marcadas no checklist)
        foreach ($entryDone as $code => $note) {
            $t = $cur[$code] ?? null;
            if (!$t) {
                $due = $want[$code][0] ?? substr((string) $cyc['entry_on'], 0, 10);
                self::insertTask($uid, $cycle, $code, $due, 'done', self::whoFor($code, $u, $cfg), $note);
            } elseif ($t['status'] === 'pending' || $t['status'] === 'skipped') {
                self::setStatus((int) $t['id'], 'done', $note);
                self::log($uid, $cycle, $code, 'tarefa', 'Fechou sozinha: ' . (self::TASKS[$code][0] ?? $code) . ' (' . rtrim($note, '.') . ').');
            }
        }
    }

    private static function insertTask(int $userId, int $cycle, string $code, string $due, string $status, ?string $who, ?string $note = null): void
    {
        $note = $note ?? ($status === 'prior' ? 'Anterior ao sistema.' : null);
        try {
            DB::run(
                'INSERT INTO journey_tasks (user_id, cycle, code, assignee, status, due_on, done_at, note, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$userId, $cycle, $code, $who, $status, $due, $status === 'done' ? now() : null, $note, now(), now()]
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
        if ($t['code'] === 'entrada_agendar') {
            return ['err' => 'Para fechar, informe o dia da 1ª consulta (campo na própria tarefa ou na ficha, aba Jornada). Ela fecha sozinha.'];
        }
        if ($t['code'] === 'entrada_form') {
            return ['err' => 'Para fechar, informe o dia em que o formulário chegou (campo na própria tarefa ou na ficha, aba Jornada). Ela fecha sozinha.'];
        }
        if ($t['code'] === 'entrada_consulta') {
            return ['err' => 'Esta tarefa fecha sozinha quando a consulta é registrada na ficha (aba Acompanhamento, parte Consultas).'];
        }
        $note = mb_substr(trim($note), 0, 255);
        self::setStatus($taskId, 'done', $note !== '' ? $note : null, $by);
        self::log((int) $t['user_id'], (int) $t['cycle'], $t['code'], 'tarefa', 'Concluída: ' . (self::TASKS[$t['code']][0] ?? $t['code']) . ($note !== '' ? ' (' . $note . ')' : ''), $by);
        $extra = '';
        if ($t['code'] === 'entrada_liberar') {
            $extra = ' A confirmação D+1 foi marcada para ' . fmt_date(self::addDays(today(), 1)) . '.';
        } elseif ($t['code'] === 'entrada_acesso') {
            self::log((int) $t['user_id'], (int) $t['cycle'], null, 'entrada', 'Entrada concluída: acesso confirmado.', $by);
            $extra = ' A Entrada deste paciente está completa.';
        }
        self::syncUser((int) $t['user_id']); // encadeia a próxima etapa da Entrada
        return ['ok' => 'Tarefa concluída: ' . $t['name'] . '.' . $extra, 'user_id' => (int) $t['user_id']];
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
        self::syncUser((int) $t['user_id']);
        return ['ok' => 'Tarefa reaberta.', 'user_id' => (int) $t['user_id']];
    }

    /** Dados do paciente que as ações precisam (só paciente com plano e vencimento). */
    private static function patientRow(int $userId): ?array
    {
        return DB::one(
            "SELECT u.id, u.name, u.status, u.renewal_date, u.plan_id, u.exit_reason, u.responsible_id,
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
                if ((int) ($contact['assignee_manual'] ?? 0) === 1) {
                    $who = (string) $contact['assignee']; // alguém passou esta tarefa para outra pessoa: continua com ela
                }
                DB::run("UPDATE journey_tasks SET status = 'pending', due_on = ?, assignee = ?, note = NULL, done_at = NULL, done_by = NULL, updated_at = ? WHERE id = ?", [$due, $who, now(), (int) $contact['id']]);
            } else {
                self::insertTask($userId, $cycle, 'contato_renovacao', $due, 'pending', $who);
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
            self::openEntry($userId, $by, 'reativado'); // quem volta faz a entrada de novo (grupo novo)
            return;
        }
        self::syncUser($userId);
    }

    // ------------------------------------------------------------------ Entrada

    /** Abre a Entrada do paciente neste ciclo (paciente novo, que voltou, ou aberta pela equipe). Só uma vez por ciclo. */
    public static function openEntry(int $userId, ?int $by, string $kind = 'novo'): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        $kind = isset(self::ENTRY_KINDS[$kind]) ? $kind : 'manual';
        $cycle = (int) $u['cyc'];
        $cyc = DB::one('SELECT * FROM journey_cycles WHERE user_id = ? AND cycle = ?', [$userId, $cycle]);
        if ($cyc && !empty($cyc['entry_on'])) {
            return ['err' => 'A Entrada deste paciente já foi aberta neste plano.'];
        }
        try {
            self::upsertCycle($userId, $cycle, ['entry_on' => today(), 'entry_kind' => $kind]);
        } catch (\PDOException $e) {
            error_log('[Jornada] openEntry: ' . $e->getMessage());
            return ['err' => 'Não foi possível abrir a Entrada. Importe o arquivo database/atualizacao_12_entrada.sql no phpMyAdmin e tente de novo.'];
        }
        self::log($userId, $cycle, null, 'entrada', 'Entrada aberta (' . self::ENTRY_KINDS[$kind] . ').', $by);
        self::syncUser($userId);
        return ['ok' => 'Entrada aberta: as tarefas de ' . $u['name'] . ' foram criadas (grupo, Welts, 1ª consulta e as etapas seguintes).'];
    }

    /**
     * Marca (ou limpa) o dia da 1ª consulta ($kind = 'consulta') ou do formulário recebido (Escalável, $kind = 'form').
     * Fecha a tarefa de agendar e cria a seguinte.
     */
    public static function setEntryDate(int $userId, string $kind, string $date, ?int $by): array
    {
        $u = self::patientRow($userId);
        if (!$u) {
            return ['err' => 'Paciente não encontrado.'];
        }
        $cycle = (int) $u['cyc'];
        $cyc = DB::one('SELECT * FROM journey_cycles WHERE user_id = ? AND cycle = ?', [$userId, $cycle]);
        if (!$cyc || empty($cyc['entry_on'])) {
            return ['err' => 'A Entrada deste paciente ainda não foi aberta.'];
        }
        if (!in_array($kind, ['consulta', 'form'], true)) {
            return ['err' => 'Escolha o que está marcando.'];
        }
        $E = substr((string) $cyc['entry_on'], 0, 10);
        if ($date !== '') {
            if (!Payments::validDate($date)) {
                return ['err' => 'Data inválida.'];
            }
            if ($kind === 'consulta' && ($date < self::addDays($E, -30) || $date > self::addDays(today(), 400))) {
                return ['err' => 'Informe o dia da consulta (de hoje em diante, ou um dia recente que ainda falta registrar).'];
            }
            if ($kind === 'form' && ($date > today() || $date < self::addDays($E, -30))) {
                return ['err' => 'Informe o dia em que o formulário chegou (hoje ou um dia que já passou).'];
            }
        }
        $col = $kind === 'consulta' ? 'first_consult_on' : 'form_on';
        self::upsertCycle($userId, $cycle, [$col => $date !== '' ? $date : null]);
        $label = $kind === 'consulta' ? '1ª consulta' : 'Formulário recebido';
        self::log($userId, $cycle, null, 'consulta', $date !== '' ? ($kind === 'consulta' ? '1ª consulta marcada para ' . fmt_date($date) . '.' : 'Formulário recebido em ' . fmt_date($date) . '.') : $label . ': dia limpo.', $by);
        self::syncUser($userId);
        return ['ok' => $date !== '' ? ($kind === 'consulta' ? '1ª consulta marcada para ' . fmt_date($date) . '.' : 'Formulário registrado em ' . fmt_date($date) . '.') : $label . ': dia limpo, a tarefa voltou a ficar aberta.'];
    }

    // ------------------------------------------------------------------ troca de responsável

    /** O nome é de alguém da equipe? (evita nome inventado em formulário adulterado) */
    private static function validTeamName(string $name): bool
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 40) {
            return false;
        }
        foreach (self::team() as $n) {
            if (mb_strtolower($n) === mb_strtolower($name)) {
                return true;
            }
        }
        return false;
    }

    /** Nome certo como está na equipe (maiúsculas e acentos do cadastro). */
    private static function teamName(string $name): string
    {
        foreach (self::team() as $n) {
            if (mb_strtolower($n) === mb_strtolower(trim($name))) {
                return $n;
            }
        }
        return trim($name);
    }

    /**
     * Passa tarefas pendentes para outra pessoa. $to = nome da equipe, ou '__auto' (volta ao responsável padrão: o sistema decide de novo).
     * Retorna ['ok' => texto, 'n' => quantas] ou ['err' => texto].
     */
    private static function moveTasks(array $rows, string $to, ?int $by): array
    {
        $auto = $to === '__auto';
        if (!$auto && !self::validTeamName($to)) {
            return ['err' => 'Escolha uma pessoa da equipe.'];
        }
        $to = $auto ? '' : self::teamName($to);
        $n = 0;
        $users = [];
        foreach ($rows as $t) {
            if ($t['status'] !== 'pending') {
                continue;
            }
            $from = trim((string) $t['assignee']);
            if (!$auto && mb_strtolower($from) === mb_strtolower($to) && (int) $t['assignee_manual'] === 1) {
                continue;
            }
            if ($auto) {
                DB::run('UPDATE journey_tasks SET assignee_manual = 0, updated_at = ? WHERE id = ?', [now(), (int) $t['id']]);
            } else {
                DB::run('UPDATE journey_tasks SET assignee = ?, assignee_manual = 1, updated_at = ? WHERE id = ?', [$to, now(), (int) $t['id']]);
                self::log((int) $t['user_id'], (int) $t['cycle'], $t['code'], 'tarefa', 'Passada de ' . ($from !== '' ? $from : 'sem responsável') . ' para ' . $to . ': ' . (self::TASKS[$t['code']][0] ?? $t['code']), $by);
            }
            $users[(int) $t['user_id']] = true;
            $n++;
        }
        if ($auto) {
            foreach (array_keys($users) as $uid) {
                self::syncUser($uid); // recalcula o responsável padrão
            }
        }
        if ($n === 0) {
            return ['err' => 'Nenhuma tarefa pendente para passar.'];
        }
        $what = $n === 1 ? '1 tarefa' : $n . ' tarefas';
        return ['ok' => $auto ? $what . ($n === 1 ? ' voltou' : ' voltaram') . ' ao responsável padrão.' : $what . ($n === 1 ? ' passou' : ' passaram') . ' para ' . $to . '.', 'n' => $n];
    }

    /** Passa UMA tarefa para outra pessoa (ou '__auto' = voltar ao responsável padrão). */
    public static function reassign(int $taskId, string $to, ?int $by): array
    {
        $t = DB::one('SELECT * FROM journey_tasks WHERE id = ?', [$taskId]);
        if (!$t) {
            return ['err' => 'Tarefa não encontrada.'];
        }
        if ($t['status'] !== 'pending') {
            return ['err' => 'Só dá para passar uma tarefa que ainda está pendente.'];
        }
        return self::moveTasks([$t], $to, $by);
    }

    /** Passa VÁRIAS tarefas escolhidas (caixinhas na lista) para outra pessoa. */
    public static function reassignMany(array $taskIds, string $to, ?int $by): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $taskIds), fn($i) => $i > 0)));
        if (!$ids) {
            return ['err' => 'Marque as tarefas que quer passar.'];
        }
        $rows = DB::all('SELECT * FROM journey_tasks WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ")  AND status = 'pending'", $ids);
        return self::moveTasks($rows, $to, $by);
    }

    /**
     * "Passar as tarefas de X para Y" (folga, férias, saída): todas as pendentes de X (ou só as atrasadas).
     * $from = nome, ou '__none' para as que estão sem responsável.
     */
    public static function passAll(string $from, string $to, bool $onlyLate, ?int $by): array
    {
        $from = trim($from);
        if ($from !== '__none' && $from === '') {
            return ['err' => 'Escolha de quem são as tarefas.'];
        }
        if ($from !== '__none' && mb_strtolower($from) === mb_strtolower(trim($to))) {
            return ['err' => 'Escolha duas pessoas diferentes.'];
        }
        $sql = "SELECT t.* FROM journey_tasks t JOIN users u ON u.id = t.user_id WHERE t.status = 'pending' AND u.role = 'patient'";
        $p = [];
        if ($from === '__none') {
            $sql .= " AND (t.assignee IS NULL OR t.assignee = '')";
        } else {
            $sql .= ' AND t.assignee = ?';
            $p[] = $from;
        }
        if ($onlyLate) {
            $sql .= ' AND t.due_on < ?';
            $p[] = today();
        }
        return self::moveTasks(DB::all($sql, $p), $to, $by);
    }

    /** Plantão da semana: quem recebe as tarefas "de cuidado" dos pacientes sem Responsável. Vazio = ninguém (tarefa sem responsável). */
    public static function setDuty(string $name, ?int $by): array
    {
        $name = trim($name);
        if ($name !== '' && !self::validTeamName($name)) {
            return ['err' => 'Escolha uma pessoa da equipe para o plantão.'];
        }
        $name = $name !== '' ? self::teamName($name) : '';
        Settings::set('journey_duty', $name);
        Settings::set('journey_duty_on', $name !== '' ? today() : '');
        self::sync(true); // as tarefas de cuidado sem Responsável seguem o plantão
        return ['ok' => $name !== '' ? 'Plantão desta semana: ' . $name . '.' : 'Plantão limpo: as tarefas de cuidado sem Responsável ficam sem dono (visíveis para todos).'];
    }

    /** Responsável renomeado em Cadastros: as tarefas abertas e os Ajustes acompanham o nome novo. */
    public static function renamed(string $old, string $new): void
    {
        $old = trim($old);
        $new = trim($new);
        if ($old === '' || $new === '' || $old === $new) {
            return;
        }
        try {
            DB::run('UPDATE journey_tasks SET assignee = ? WHERE assignee = ?', [$new, $old]);
            foreach (['journey_who_dossie', 'journey_who_ultima', 'journey_who_contact', 'journey_who_support', 'journey_who_group', 'journey_who_athlete', 'journey_who_consult', 'journey_who_plan', 'journey_duty'] as $k) {
                if (trim((string) Settings::get($k, '')) === $old) {
                    Settings::set($k, $new);
                }
            }
        } catch (\Throwable $e) {
            error_log('[Jornada] renamed: ' . $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ telas

    /** Números do Painel e do selo do menu: tarefas atrasadas e de hoje. Nunca derruba a tela (tabelas ainda não importadas = zeros). */
    public static function attention(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = ['late' => 0, 'today' => 0, 'due' => 0, 'byWho' => []];
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
            foreach (self::workload() as $who => $w) {
                if ($w['late'] > 0) {
                    $cache['byWho'][$who] = $w['late'];
                }
            }
            arsort($cache['byWho']);
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

    /** Carga de cada pessoa: [nome ou '__none' => ['n' => pendentes, 'late' => atrasadas]]. Quem esquece fica visível. */
    public static function workload(): array
    {
        $out = [];
        foreach (DB::all(
            "SELECT COALESCE(NULLIF(t.assignee, ''), '__none') AS who, COUNT(*) AS n, SUM(CASE WHEN t.due_on < ? THEN 1 ELSE 0 END) AS late
               FROM journey_tasks t JOIN users u ON u.id = t.user_id
              WHERE t.status = 'pending' AND u.role = 'patient' GROUP BY 1 ORDER BY 1",
            [today()]
        ) as $r) {
            $out[$r['who']] = ['n' => (int) $r['n'], 'late' => (int) $r['late']];
        }
        return $out;
    }

    /**
     * Aba "Entrada": pacientes com a Entrada aberta e ainda não concluída (acesso não confirmado), do mais antigo ao mais novo.
     * Cada linha: progresso (feitas de total), próxima tarefa e se alguma está atrasada.
     */
    public static function entries(): array
    {
        [$users, $cycles, $tasks] = self::load(null);
        $rows = [];
        foreach ($users as $u) {
            if ($u['status'] !== 'active') {
                continue;
            }
            $id = (int) $u['id'];
            $cycle = (int) $u['cyc'];
            $cyc = $cycles[$id][$cycle] ?? null;
            if (!$cyc || empty($cyc['entry_on'])) {
                continue;
            }
            $mine = [];
            foreach ($tasks[$id] ?? [] as $t) {
                if ((int) $t['cycle'] === $cycle && in_array($t['code'], self::ENTRY_CODES, true)) {
                    $mine[$t['code']] = $t;
                }
            }
            if (isset($mine['entrada_acesso']) && $mine['entrada_acesso']['status'] === 'done') {
                continue; // Entrada completa
            }
            $done = 0;
            $total = 0;
            $next = null;
            $late = 0;
            foreach ($mine as $t) {
                if ($t['status'] === 'done') {
                    $done++;
                    $total++;
                } elseif ($t['status'] === 'pending') {
                    $total++;
                    if (substr((string) $t['due_on'], 0, 10) < today()) {
                        $late++;
                    }
                    if ($next === null || (string) $t['due_on'] < (string) $next['due_on']) {
                        $next = $t;
                    }
                }
            }
            $E = substr((string) $cyc['entry_on'], 0, 10);
            $rows[] = [
                'id' => $id, 'name' => $u['name'], 'plan' => $u['plan_name'], 'since' => $E, 'kind' => $cyc['entry_kind'],
                'days' => max(0, -1 * (int) days_until($E)), 'done' => $done, 'total' => $total, 'next' => $next, 'late' => $late,
                'resp' => $u['resp_name'],
            ];
        }
        usort($rows, fn($a, $b) => [$a['since'], $a['name']] <=> [$b['since'], $b['name']]);
        return $rows;
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
        $allTasks = DB::all('SELECT * FROM journey_tasks WHERE user_id = ? AND cycle = ? ORDER BY due_on, id', [$userId, $cycle]);
        $tasks = [];
        $entryByCode = [];
        foreach ($allTasks as $t) {
            if (in_array($t['code'], self::ENTRY_CODES, true)) {
                $entryByCode[$t['code']] = $t;
            } else {
                $tasks[] = $t;
            }
        }
        $entryTasks = [];
        foreach (self::ENTRY_CODES as $code) {
            if (isset($entryByCode[$code])) {
                $entryTasks[] = $entryByCode[$code];
            }
        }
        $entryDone = count(array_filter($entryTasks, fn($t) => $t['status'] === 'done'));
        $entryTotal = count(array_filter($entryTasks, fn($t) => in_array($t['status'], ['done', 'pending'], true)));
        $respRow = $u && !empty($u['responsible_id']) ? DB::one('SELECT name, active FROM responsibles WHERE id = ?', [(int) $u['responsible_id']]) : null;
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
            'reajuste' => $reajuste, 'heldCount' => count($held), 'cota' => $cota, 'team' => self::team(),
            'entry' => [
                'open' => !empty($cyc['entry_on']), 'on' => $cyc['entry_on'] ?? null, 'kind' => $cyc['entry_kind'] ?? null,
                'first_consult_on' => $cyc['first_consult_on'] ?? null, 'form_on' => $cyc['form_on'] ?? null,
                'tasks' => $entryTasks, 'done' => $entryDone, 'total' => $entryTotal,
                'finished' => isset($entryByCode['entrada_acesso']) && $entryByCode['entrada_acesso']['status'] === 'done',
                'escalavel' => $type === 'escalavel', 'resp' => ($respRow && (int) $respRow['active'] === 1) ? $respRow['name'] : null,
                'respOff' => ($respRow && (int) $respRow['active'] !== 1) ? $respRow['name'] : null,
            ],
        ];
    }
}
