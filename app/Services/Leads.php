<?php
namespace App\Services;

use App\Core\DB;

/**
 * CRM / Leads (Atualização 09.1 e 09.2).
 * Lead = alguém que ainda NÃO é paciente: de onde veio, em que etapa está, quando foi o último contato e o próximo.
 *
 * REGRAS:
 *  - A linha do tempo (lead_events) só ganha linhas novas. O sistema nunca edita nem apaga uma linha antiga
 *    (a única exceção é a exclusão do lead inteiro, pedido de exclusão pela LGPD, que também leva a linha do tempo).
 *  - "Não contatar" bloqueia o botão de WhatsApp e novos retornos agendados.
 *  - 09.2: os indicadores, os responsáveis, o follow-up, a reativação e a conversão usam OS MESMOS registros (leads, lead_events,
 *    lead_stages, lead_conversions). Não existe tabela paralela: tudo o que aparece nos números vem destas tabelas.
 *  - Nada aqui conversa com WhatsApp nem Instagram: toda interação é registrada à mão (source = 'manual').
 *    As colunas source e external_id da linha do tempo ficam prontas para integrações futuras.
 */
final class Leads
{
    public const TEMPS = ['hot' => 'Quente', 'warm' => 'Morno', 'cold' => 'Frio'];

    public const CHANNELS = [
        'whatsapp' => 'WhatsApp',
        'instagram' => 'Instagram (direct)',
        'phone' => 'Telefone',
        'email' => 'E-mail',
        'in_person' => 'Presencial',
        'site' => 'Site / formulário',
        'other' => 'Outro',
    ];

    public const RESULTS = [
        'no_answer' => 'Sem resposta',
        'talked' => 'Conversamos',
        'info_sent' => 'Enviei informações',
        'asked_later' => 'Pediu para retornar depois',
        'not_interested' => 'Sem interesse agora',
    ];

    public const ACTIONS = [
        'followup' => 'Retomar contato',
        'whatsapp' => 'Enviar WhatsApp',
        'call' => 'Ligar',
        'proposal' => 'Enviar proposta',
        'meeting' => 'Marcar conversa',
        'other' => 'Outra ação',
    ];

    /** Sugestão de dias até o próximo retorno, por temperatura (o time pode digitar outro valor). */
    public const SUGGEST_DAYS = ['hot' => 1, 'warm' => 3, 'cold' => 7];

    /** Reativação: cadência padrão (dias depois da perda) e o mínimo permitido (a primeira tentativa só a partir de 20 dias). */
    public const RECOVERY_DEFAULT = '20,30,45';
    public const MIN_RECOVERY_DAYS = 20;
    /** Quadro (Kanban): quantos cartões aparecem por coluna (o resto fica na Lista, com o filtro da etapa). */
    public const BOARD_LIMIT = 30;

    /** Telas iniciais possíveis do CRM (Configurar). */
    public const HOME_VIEWS = ['board' => 'Quadro (colunas por etapa)', 'list' => 'Lista (tabela)'];

    public const DEFAULT_MESSAGE = 'Olá, {nome}! Tudo bem? Aqui é do No Days Off Club. Posso te ajudar com alguma dúvida sobre o acompanhamento nutricional?';

    // ------------------------------------------------------------------ listas

    /** Origens ativas (mais a atual do lead, se foi desativada). */
    public static function sources(?int $keepId = null): array
    {
        return DB::all('SELECT * FROM lead_sources WHERE active = 1 OR id = ? ORDER BY sort_order ASC, name ASC', [(int) $keepId]);
    }

    public static function allSources(): array
    {
        return DB::all(
            'SELECT s.*, (SELECT COUNT(*) FROM leads l WHERE l.source_id = s.id) AS leads FROM lead_sources s ORDER BY s.active DESC, s.sort_order ASC, s.name ASC'
        );
    }

    /** Etapas ativas. $openOnly = só as etapas "em andamento" (sem Convertido e Perdido). */
    public static function stages(bool $openOnly = false, ?int $keepId = null): array
    {
        return DB::all(
            'SELECT * FROM lead_stages WHERE (active = 1 OR id = ?)' . ($openOnly ? " AND kind = 'open'" : '') . ' ORDER BY sort_order ASC, id ASC',
            [(int) $keepId]
        );
    }

    public static function allStages(): array
    {
        return DB::all(
            "SELECT st.*, (SELECT COUNT(*) FROM leads l WHERE l.stage_id = st.id AND l.status = 'open') AS leads FROM lead_stages st ORDER BY st.sort_order ASC, st.id ASC"
        );
    }

    /** Motivos de perda ativos (mais o atual do lead, se foi desativado). */
    public static function lostReasons(?int $keepId = null): array
    {
        return DB::all('SELECT * FROM lead_lost_reasons WHERE active = 1 OR id = ? ORDER BY sort_order ASC, id ASC', [(int) $keepId]);
    }

    public static function allLostReasons(): array
    {
        return DB::all(
            'SELECT r.*, (SELECT COUNT(*) FROM leads l WHERE l.lost_reason_id = r.id AND l.status = ?) AS leads FROM lead_lost_reasons r ORDER BY r.active DESC, r.sort_order ASC, r.id ASC',
            ['lost']
        );
    }

    public static function firstOpenStageId(): int
    {
        return (int) DB::val("SELECT id FROM lead_stages WHERE kind = 'open' AND active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1");
    }

    public static function stageName(int $id): string
    {
        return (string) DB::val('SELECT name FROM lead_stages WHERE id = ?', [$id]);
    }

    // ------------------------------------------------------------------ contatos e duplicidade

    /** Chave para achar o mesmo telefone escrito de jeitos diferentes: os últimos 8 números (ignora +55, DDD e o 9). */
    public static function phoneKey(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        return strlen($d) >= 8 ? substr($d, -8) : null;
    }

    /**
     * Procura quem já está cadastrado com o mesmo e-mail, telefone ou Instagram.
     * Retorna ['email' => lead|null, 'phone' => [leads], 'instagram' => [leads], 'patient' => paciente|null].
     */
    public static function duplicates(string $email, ?string $phone, string $instagram, int $exceptId = 0): array
    {
        $out = ['email' => null, 'phone' => [], 'instagram' => [], 'patient' => null];
        $sel = 'SELECT l.id, l.name, l.phone, l.email, l.status FROM leads l WHERE l.id <> ? AND ';
        if ($email !== '') {
            $out['email'] = DB::one($sel . 'LOWER(l.email) = ? LIMIT 1', [$exceptId, mb_strtolower($email)]);
        }
        $key = self::phoneKey($phone);
        if ($key !== null) {
            $out['phone'] = DB::all($sel . 'l.phone_key = ? LIMIT 3', [$exceptId, $key]);
        }
        if ($instagram !== '') {
            $out['instagram'] = DB::all($sel . 'LOWER(l.instagram) = ? LIMIT 3', [$exceptId, mb_strtolower($instagram)]);
        }
        // Já é paciente? (mesmo e-mail ou mesmo telefone)
        if ($email !== '') {
            $out['patient'] = DB::one("SELECT id, name, email FROM users WHERE role = 'patient' AND LOWER(email) = ? LIMIT 1", [mb_strtolower($email)]);
        }
        if (!$out['patient'] && $key !== null) {
            foreach (DB::all("SELECT id, name, email, phone FROM users WHERE role = 'patient' AND phone IS NOT NULL AND phone <> ''") as $u) {
                if (self::phoneKey($u['phone']) === $key) {
                    $out['patient'] = $u;
                    break;
                }
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------ linha do tempo (só ganha linhas)

    /** Acrescenta uma linha à linha do tempo. Nunca existe método para editar ou apagar. */
    public static function addEvent(int $leadId, string $kind, array $f = [], ?int $by = null): int
    {
        return DB::insert(
            'INSERT INTO lead_events (lead_id, kind, happened_at, by_user_id, channel, summary, result, notes, next_action, from_value, to_value, source, external_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $leadId, $kind, $f['happened_at'] ?? now(), $by,
                $f['channel'] ?? null, self::cut($f['summary'] ?? null, 600), $f['result'] ?? null, self::cut($f['notes'] ?? null, 600),
                self::cut($f['next_action'] ?? null, 160), self::cut($f['from'] ?? null, 80), self::cut($f['to'] ?? null, 80),
                $f['source'] ?? 'manual', $f['external_id'] ?? null, now(),
            ]
        );
    }

    private static function cut(?string $s, int $n): ?string
    {
        return $s === null || $s === '' ? null : mb_substr($s, 0, $n);
    }

    public static function timeline(int $leadId): array
    {
        return DB::all(
            'SELECT e.*, u.name AS by_name FROM lead_events e LEFT JOIN users u ON u.id = e.by_user_id WHERE e.lead_id = ? ORDER BY e.happened_at DESC, e.id DESC',
            [$leadId]
        );
    }

    // ------------------------------------------------------------------ cadastro e mudanças

    /** Cria o lead e a primeira linha da linha do tempo. $d já vem validado pelo controller. */
    public static function create(array $d, int $by): int
    {
        return DB::tx(function () use ($d, $by) {
            $id = DB::insert(
                'INSERT INTO leads (name, phone, phone_key, email, instagram, sport_id, goal, plan_id, source_id, channel, referred_by, responsible_id,
                                    temperature, stage_id, status, entered_on, notes, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $d['name'], $d['phone'], self::phoneKey($d['phone']), $d['email'], $d['instagram'], $d['sport_id'], $d['goal'], $d['plan_id'],
                    $d['source_id'], $d['channel'], $d['referred_by'], $d['responsible_id'], $d['temperature'], $d['stage_id'], 'open',
                    $d['entered_on'], $d['notes'], $by, $by, now(), now(),
                ]
            );
            $src = (string) DB::val('SELECT name FROM lead_sources WHERE id = ?', [$d['source_id']]);
            self::addEvent($id, 'created', [
                'summary' => 'Lead cadastrado' . ($src !== '' ? ' · origem: ' . $src : ''),
                'channel' => $d['channel'],
                'to' => self::stageName((int) $d['stage_id']),
            ], $by);
            return $id;
        });
    }

    /** Atualiza os dados cadastrais. Registra na linha do tempo QUAIS campos mudaram (não os valores). */
    public static function updateData(array $old, array $d, int $by): void
    {
        $labels = [
            'name' => 'nome', 'phone' => 'telefone', 'email' => 'e-mail', 'instagram' => 'Instagram', 'sport_id' => 'modalidade',
            'goal' => 'objetivo', 'plan_id' => 'plano de interesse', 'source_id' => 'origem', 'channel' => 'canal',
            'referred_by' => 'quem indicou', 'responsible_id' => 'responsável', 'entered_on' => 'data de entrada', 'notes' => 'observações',
        ];
        $changed = [];
        foreach ($labels as $k => $label) {
            if ((string) ($old[$k] ?? '') !== (string) ($d[$k] ?? '')) {
                $changed[] = $label;
            }
        }
        DB::tx(function () use ($old, $d, $by, $changed) {
            DB::run(
                'UPDATE leads SET name = ?, phone = ?, phone_key = ?, email = ?, instagram = ?, sport_id = ?, goal = ?, plan_id = ?, source_id = ?, channel = ?,
                        referred_by = ?, responsible_id = ?, entered_on = ?, notes = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                [
                    $d['name'], $d['phone'], self::phoneKey($d['phone']), $d['email'], $d['instagram'], $d['sport_id'], $d['goal'], $d['plan_id'],
                    $d['source_id'], $d['channel'], $d['referred_by'], $d['responsible_id'], $d['entered_on'], $d['notes'], $by, now(), (int) $old['id'],
                ]
            );
            if ($changed) {
                self::addEvent((int) $old['id'], 'data', ['summary' => 'Dados atualizados: ' . implode(', ', $changed)], $by);
            }
        });
    }

    /**
     * Registra um contato feito com o lead (ligação, mensagem, conversa) e, se pedido, agenda o próximo retorno.
     * $c: happened_at, channel, result, summary, notes, stage_id (0 = não mudar), next_on, next_type, next_responsible_id
     */
    public static function logContact(array $lead, array $c, int $by): void
    {
        DB::tx(function () use ($lead, $c, $by) {
            $id = (int) $lead['id'];
            $nextTxt = null;
            if ($c['next_on']) {
                $nextTxt = (self::ACTIONS[$c['next_type']] ?? 'Retorno') . ' em ' . fmt_date($c['next_on']) . self::taskOwnerText($c['next_responsible_id'] ?? null);
            }
            self::addEvent($id, 'contact', [
                'happened_at' => $c['happened_at'], 'channel' => $c['channel'], 'summary' => $c['summary'], 'result' => $c['result'],
                'notes' => $c['notes'], 'next_action' => $nextTxt,
            ], $by);
            $last = $lead['last_contact_at'] && $lead['last_contact_at'] > $c['happened_at'] ? $lead['last_contact_at'] : $c['happened_at'];
            DB::run(
                'UPDATE leads SET last_contact_at = ?, next_on = ?, next_type = ?, next_responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                [$last, $c['next_on'], $c['next_on'] ? $c['next_type'] : null, $c['next_on'] ? $c['next_responsible_id'] : null, $by, now(), $id]
            );
            if ($c['stage_id'] > 0 && $c['stage_id'] !== (int) $lead['stage_id']) {
                self::moveStage($lead, $c['stage_id'], $by);
            }
        });
    }

    /** Muda a etapa (só entre as etapas "em andamento") e registra na linha do tempo. */
    public static function moveStage(array $lead, int $stageId, int $by): bool
    {
        $to = DB::one("SELECT id, name FROM lead_stages WHERE id = ? AND kind = 'open'", [$stageId]);
        if (!$to || (int) $lead['stage_id'] === $stageId) {
            return false;
        }
        DB::run('UPDATE leads SET stage_id = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$stageId, $by, now(), (int) $lead['id']]);
        self::addEvent((int) $lead['id'], 'stage', ['from' => self::stageName((int) $lead['stage_id']), 'to' => $to['name']], $by);
        return true;
    }

    public static function setTemperature(array $lead, string $temp, int $by): bool
    {
        if (!isset(self::TEMPS[$temp]) || $lead['temperature'] === $temp) {
            return false;
        }
        DB::run('UPDATE leads SET temperature = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$temp, $by, now(), (int) $lead['id']]);
        self::addEvent((int) $lead['id'], 'temperature', ['from' => self::TEMPS[$lead['temperature']] ?? $lead['temperature'], 'to' => self::TEMPS[$temp]], $by);
        return true;
    }

    /** Autorização para novos contatos (caixinha marcada pelo time quando o lead aceitou). */
    public static function setConsent(array $lead, bool $on, int $by): void
    {
        if ((int) $lead['contact_ok'] === ($on ? 1 : 0)) {
            return;
        }
        DB::run('UPDATE leads SET contact_ok = ?, contact_ok_at = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$on ? 1 : 0, $on ? now() : null, $by, now(), (int) $lead['id']]);
        if (!$on && ($lead['status'] ?? '') === 'lost') {
            // sem autorização não há reativação: a tentativa agendada é cancelada
            DB::run('UPDATE leads SET next_on = NULL, next_type = NULL, next_responsible_id = NULL, recovery_status = NULL WHERE id = ?', [(int) $lead['id']]);
        }
        self::addEvent((int) $lead['id'], 'consent', ['summary' => $on ? 'Autorizou receber novos contatos' : 'Autorização para novos contatos removida'], $by);
    }

    /** "Não contatar": tira o retorno agendado, remove a autorização e passa a bloquear o WhatsApp e novos retornos. */
    public static function setDoNotContact(array $lead, bool $on, int $by): void
    {
        if ((int) $lead['do_not_contact'] === ($on ? 1 : 0)) {
            return;
        }
        DB::tx(function () use ($lead, $on, $by) {
            if ($on) {
                DB::run(
                    'UPDATE leads SET do_not_contact = 1, do_not_contact_at = ?, contact_ok = 0, contact_ok_at = NULL, next_on = NULL, next_type = NULL, next_responsible_id = NULL, updated_by = ?, updated_at = ? WHERE id = ?',
                    [now(), $by, now(), (int) $lead['id']]
                );
            } else {
                DB::run('UPDATE leads SET do_not_contact = 0, do_not_contact_at = NULL, updated_by = ?, updated_at = ? WHERE id = ?', [$by, now(), (int) $lead['id']]);
            }
            self::addEvent((int) $lead['id'], 'dnc', ['summary' => $on ? 'Marcado como "Não contatar": retorno agendado cancelado' : 'Liberado para contato novamente'], $by);
        });
    }

    /** " · tarefa de Gabriel" (ou vazio) para as linhas da linha do tempo. */
    private static function taskOwnerText(?int $respId): string
    {
        if (!$respId) {
            return '';
        }
        $n = (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$respId]);
        return $n !== '' ? ' · tarefa de ' . $n : '';
    }

    /**
     * Define o responsável de vários leads de uma vez (0 = tirar o responsável). Vale para leads em aberto e perdidos
     * (quem cuida da reativação também precisa de dono). Registra na linha do tempo só de quem realmente mudou.
     * Retorna quantos leads mudaram.
     */
    public static function assign(array $leadIds, int $responsibleId, int $by): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $leadIds), fn($i) => $i > 0)));
        if (!$ids) {
            return 0;
        }
        $to = $responsibleId > 0 ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$responsibleId]) : 'Sem responsável';
        return DB::tx(function () use ($ids, $responsibleId, $by, $to) {
            $n = 0;
            foreach ($ids as $id) {
                $l = DB::one("SELECT id, responsible_id FROM leads WHERE id = ? AND status IN ('open', 'lost')", [$id]);
                if (!$l) {
                    continue;
                }
                $cur = $l['responsible_id'] !== null ? (int) $l['responsible_id'] : 0;
                if ($cur === $responsibleId) {
                    continue;
                }
                $from = $cur > 0 ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$cur]) : 'Sem responsável';
                DB::run('UPDATE leads SET responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$responsibleId > 0 ? $responsibleId : null, $by, now(), $id]);
                self::addEvent($id, 'assign', ['from' => $from, 'to' => $to], $by);
                $n++;
            }
            return $n;
        });
    }

    /**
     * Agenda (ou remove) o próximo retorno SEM registrar um contato: só a data, o tipo de ação e quem faz a tarefa.
     * $nextOn = null remove o retorno. Vale para leads em aberto e perdidos (nos perdidos é a tentativa de reativação).
     */
    public static function schedule(array $lead, ?string $nextOn, string $type, ?int $respId, ?string $note, int $by): void
    {
        DB::tx(function () use ($lead, $nextOn, $type, $respId, $note, $by) {
            $id = (int) $lead['id'];
            if ($nextOn === null) {
                DB::run('UPDATE leads SET next_on = NULL, next_type = NULL, next_responsible_id = NULL, updated_by = ?, updated_at = ? WHERE id = ?', [$by, now(), $id]);
                self::addEvent($id, 'schedule', ['summary' => 'Retorno removido: o lead ficou sem retorno programado', 'notes' => $note], $by);
                return;
            }
            $type = isset(self::ACTIONS[$type]) ? $type : 'followup';
            DB::run('UPDATE leads SET next_on = ?, next_type = ?, next_responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?', [$nextOn, $type, $respId, $by, now(), $id]);
            self::addEvent($id, 'schedule', [
                'summary' => 'Retorno agendado para ' . fmt_date($nextOn) . self::taskOwnerText($respId),
                'notes' => $note,
                'next_action' => self::ACTIONS[$type] . ' em ' . fmt_date($nextOn),
            ], $by);
        });
    }

    // ------------------------------------------------------------------ perdidos e reativação (09.2)
    //
    // Lead perdido sai da lista de oportunidades abertas (status = 'lost') e passa a viver só na tela "Reativação".
    // Elegível para recuperação = perdido + autorizou novos contatos + não está como "Não contatar".
    // Cadência: dias depois da perda (Configurar, mínimo 20, padrão 20, 30 e 45). A tentativa agendada usa o mesmo campo next_on.
    // recovery_status: 'pending' (cadência em andamento), 'done' (cadência concluída ou encerrada à mão), 'recovered' (voltou ao funil ou virou paciente).

    /** Dias da cadência de reativação (Configurar). Valores inválidos ou abaixo do mínimo são ignorados. */
    public static function recoveryDays(): array
    {
        $days = [];
        foreach (explode(',', (string) Settings::get('lead_recovery_days', self::RECOVERY_DEFAULT)) as $p) {
            $p = trim($p);
            if (ctype_digit($p) && (int) $p >= self::MIN_RECOVERY_DAYS && (int) $p <= 365) {
                $days[] = (int) $p;
            }
        }
        $days = array_values(array_unique($days));
        sort($days);
        return $days ?: array_map('intval', explode(',', self::RECOVERY_DEFAULT));
    }

    /** Perdido, autorizou novos contatos e não está como "Não contatar". */
    public static function recoveryEligible(array $lead): bool
    {
        return ($lead['status'] ?? '') === 'lost' && (int) ($lead['do_not_contact'] ?? 0) === 0 && (int) ($lead['contact_ok'] ?? 0) === 1;
    }

    /**
     * Data (AAAA-MM-DD) da tentativa de número $step (0 = a primeira). A primeira é "dias da cadência" depois da perda;
     * as seguintes respeitam o intervalo entre os dias da cadência a partir da tentativa anterior ($lastAttempt).
     * Nunca devolve uma data passada. Null = a cadência acabou.
     */
    public static function recoveryDue(array $lead, int $step, ?string $lastAttempt = null): ?string
    {
        $days = self::recoveryDays();
        if ($step < 0 || $step >= count($days)) {
            return null;
        }
        if ($step === 0 || $lastAttempt === null) {
            $from = substr((string) ($lead['lost_at'] ?: now()), 0, 10);
            $due = date('Y-m-d', strtotime($from . ' +' . $days[$step] . ' days'));
        } else {
            $due = date('Y-m-d', strtotime(substr($lastAttempt, 0, 10) . ' +' . ($days[$step] - $days[$step - 1]) . ' days'));
        }
        return max($due, today());
    }

    /**
     * Situação de um lead perdido na reativação. key: dnc, noauth, done, ready (pronto para contato), waiting (aguardando a data),
     * unscheduled (elegível, sem tentativa programada). Devolve também label e step/steps (tentativa N de M).
     */
    public static function recoveryState(array $lead): array
    {
        $steps = count(self::recoveryDays());
        $step = (int) ($lead['recovery_step'] ?? 0);
        $base = ['step' => min($step + 1, $steps), 'steps' => $steps];
        if ((int) ($lead['do_not_contact'] ?? 0) === 1) {
            return ['key' => 'dnc', 'label' => 'Não contatar'] + $base;
        }
        if ((int) ($lead['contact_ok'] ?? 0) !== 1) {
            return ['key' => 'noauth', 'label' => 'Sem autorização para novos contatos'] + $base;
        }
        if (($lead['recovery_status'] ?? '') === 'done') {
            return ['key' => 'done', 'label' => 'Reativação concluída'] + $base;
        }
        if (!empty($lead['next_on'])) {
            return ($lead['next_on'] <= today()
                ? ['key' => 'ready', 'label' => 'Pronto para contato']
                : ['key' => 'waiting', 'label' => 'Aguardando a data']) + $base;
        }
        return ['key' => 'unscheduled', 'label' => 'Sem reativação programada'] + $base;
    }

    /** Marca o lead como perdido (motivo obrigatório) e, se elegível, já programa a 1ª tentativa de reativação. */
    public static function markLost(array $lead, int $reasonId, ?string $note, bool $contactOk, int $by): void
    {
        DB::tx(function () use ($lead, $reasonId, $note, $contactOk, $by) {
            $id = (int) $lead['id'];
            $lostStage = (int) DB::val("SELECT id FROM lead_stages WHERE kind = 'lost' ORDER BY sort_order ASC, id ASC LIMIT 1");
            $reason = (string) DB::val('SELECT name FROM lead_lost_reasons WHERE id = ?', [$reasonId]);
            DB::run(
                "UPDATE leads SET status = 'lost', stage_id = ?, lost_reason_id = ?, lost_note = ?, lost_at = ?, next_on = NULL, next_type = NULL, next_responsible_id = NULL,
                        recovery_status = NULL, recovery_step = 0, updated_by = ?, updated_at = ? WHERE id = ?",
                [$lostStage, $reasonId, self::cut($note, 300), now(), $by, now(), $id]
            );
            self::addEvent($id, 'lost', [
                'summary' => 'Motivo: ' . $reason,
                'notes' => $note, 'from' => self::stageName((int) $lead['stage_id']), 'to' => self::stageName($lostStage),
            ], $by);
            if ((int) $lead['do_not_contact'] === 0) {
                self::setConsent($lead, $contactOk, $by);
            }
            $fresh = self::find($id);
            if ($fresh && self::recoveryEligible($fresh)) {
                self::startRecovery($fresh, $by, false);
            }
        });
    }

    /** Programa a reativação (1ª tentativa). $restart = recomeça a cadência do zero depois de concluída. */
    public static function startRecovery(array $lead, int $by, bool $restart): ?string
    {
        if (!self::recoveryEligible($lead)) {
            return null;
        }
        $id = (int) $lead['id'];
        if ($restart) {
            // recomeçar conta os dias a partir de hoje (a perda já ficou para trás)
            $lead['lost_at'] = now();
        }
        $due = self::recoveryDue($lead, 0);
        $steps = count(self::recoveryDays());
        DB::run(
            "UPDATE leads SET recovery_status = 'pending', recovery_step = 0, next_on = ?, next_type = 'followup', next_responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?",
            [$due, $lead['responsible_id'] ?: null, $by, now(), $id]
        );
        self::addEvent($id, 'recovery', [
            'summary' => ($restart ? 'Nova reativação programada' : 'Reativação programada') . ': 1ª tentativa em ' . fmt_date($due) . ' (1 de ' . $steps . ')',
            'next_action' => 'Retomar contato em ' . fmt_date($due),
        ], $by);
        return $due;
    }

    /**
     * Registra uma tentativa de reativação (contato com um lead perdido).
     * $decision: 'cadence' = segue a cadência (agenda a próxima ou conclui) · 'stop' = encerra a reativação deste lead.
     * Lead não elegível (sem autorização ou "Não contatar") só ganha o registro do contato, sem nova tentativa.
     * $c: happened_at, channel, result, summary, notes
     */
    public static function logRecoveryAttempt(array $lead, array $c, string $decision, int $by): void
    {
        DB::tx(function () use ($lead, $c, $decision, $by) {
            $id = (int) $lead['id'];
            $days = self::recoveryDays();
            $elig = self::recoveryEligible($lead);
            $status = $lead['recovery_status'];
            $step = (int) $lead['recovery_step'];
            $nextOn = null;
            $summaryNext = null;
            if ($elig && $decision === 'stop') {
                $status = 'done';
                $summaryNext = 'Reativação encerrada por decisão da equipe';
            } elseif ($elig) {
                $step++;
                if ($step >= count($days)) {
                    $status = 'done';
                    $summaryNext = 'Cadência de reativação concluída (' . count($days) . ' tentativas)';
                } else {
                    $status = 'pending';
                    $nextOn = self::recoveryDue($lead, $step, $c['happened_at']);
                    $summaryNext = 'Próxima tentativa em ' . fmt_date($nextOn) . ' (' . ($step + 1) . ' de ' . count($days) . ')';
                }
            }
            self::addEvent($id, 'contact', [
                'happened_at' => $c['happened_at'], 'channel' => $c['channel'], 'summary' => $c['summary'], 'result' => $c['result'],
                'notes' => $c['notes'], 'next_action' => $summaryNext,
            ], $by);
            $last = $lead['last_contact_at'] && $lead['last_contact_at'] > $c['happened_at'] ? $lead['last_contact_at'] : $c['happened_at'];
            DB::run(
                'UPDATE leads SET last_contact_at = ?, recovery_status = ?, recovery_step = ?, next_on = ?, next_type = ?, next_responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?',
                [$last, $status, $step, $nextOn, $nextOn ? 'followup' : null, $nextOn ? ($lead['next_responsible_id'] ?: $lead['responsible_id'] ?: null) : null, $by, now(), $id]
            );
        });
    }

    /** O lead perdido voltou a se interessar: volta para as oportunidades abertas, na etapa escolhida, com retorno para hoje. */
    public static function reopen(array $lead, int $stageId, ?string $note, int $by): bool
    {
        $to = DB::one("SELECT id, name FROM lead_stages WHERE id = ? AND kind = 'open' AND active = 1", [$stageId]);
        if (!$to || ($lead['status'] ?? '') !== 'lost') {
            return false;
        }
        DB::tx(function () use ($lead, $to, $note, $by) {
            $id = (int) $lead['id'];
            $dnc = (int) $lead['do_not_contact'] === 1;
            DB::run(
                "UPDATE leads SET status = 'open', stage_id = ?, lost_reason_id = NULL, lost_note = NULL, lost_at = NULL, recovery_status = 'recovered', recovery_step = 0,
                        cycle = cycle + 1, next_on = ?, next_type = ?, next_responsible_id = ?, updated_by = ?, updated_at = ? WHERE id = ?",
                [(int) $to['id'], $dnc ? null : today(), $dnc ? null : 'followup', $dnc ? null : ($lead['responsible_id'] ?: null), $by, now(), $id]
            );
            self::addEvent($id, 'reopen', [
                'summary' => 'Reaberto: voltou às oportunidades em aberto' . ($dnc ? '' : ' (retorno para hoje)'),
                'notes' => $note, 'from' => self::stageName((int) $lead['stage_id']), 'to' => $to['name'],
            ], $by);
        });
        return true;
    }

    /** Filtros e WHERE da tela Reativação (só leads perdidos). */
    private static function lostWhere(array $f, array &$p): string
    {
        $w = ["l.status = 'lost'"];
        if (($f['q'] ?? '') !== '') {
            $q = $f['q'];
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $parts = ["l.name LIKE ? ESCAPE '!'", "l.email LIKE ? ESCAPE '!'", "l.instagram LIKE ? ESCAPE '!'", "l.phone LIKE ? ESCAPE '!'"];
            array_push($p, $like, $like, $like, $like);
            $key = self::phoneKey($q);
            if ($key !== null && preg_match('/^[\d\s()+.-]+$/', $q)) {
                $parts[] = 'l.phone_key = ?';
                $p[] = $key;
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        if (($f['reason'] ?? '') !== '') {
            $w[] = 'l.lost_reason_id = ?';
            $p[] = (int) $f['reason'];
        }
        if (($f['resp'] ?? '') === 'none') {
            $w[] = 'l.responsible_id IS NULL';
        } elseif (($f['resp'] ?? '') !== '') {
            $w[] = 'l.responsible_id = ?';
            $p[] = (int) $f['resp'];
        }
        $elig = 'l.do_not_contact = 0 AND l.contact_ok = 1';
        $t = today();
        switch ($f['f'] ?? '') {
            case 'ready':
                $w[] = "$elig AND (l.recovery_status IS NULL OR l.recovery_status <> 'done') AND l.next_on IS NOT NULL AND l.next_on <= ?";
                $p[] = $t;
                break;
            case 'waiting':
                $w[] = "$elig AND (l.recovery_status IS NULL OR l.recovery_status <> 'done') AND l.next_on > ?";
                $p[] = $t;
                break;
            case 'unscheduled':
                $w[] = "$elig AND (l.recovery_status IS NULL OR l.recovery_status <> 'done') AND l.next_on IS NULL";
                break;
            case 'done':
                $w[] = "$elig AND l.recovery_status = 'done'";
                break;
            case 'noauth':
                $w[] = 'l.do_not_contact = 0 AND l.contact_ok = 0';
                break;
            case 'dnc':
                $w[] = 'l.do_not_contact = 1';
                break;
        }
        return implode(' AND ', $w);
    }

    public static function lostSorts(): array
    {
        return ['next' => 'Mais urgentes', 'lost' => 'Perdidos há mais tempo', 'lostnew' => 'Perdidos há menos tempo', 'name' => 'Nome (A a Z)'];
    }

    /** Uma página dos leads perdidos + o total. */
    public static function lostListing(array $f, int $limit, int $offset): array
    {
        $p = [];
        $where = self::lostWhere($f, $p);
        $order = match ($f['ord'] ?? 'next') {
            'lost' => 'l.lost_at ASC, l.id ASC',
            'lostnew' => 'l.lost_at DESC, l.id DESC',
            'name' => 'l.name ASC, l.id DESC',
            default => '(l.next_on IS NULL) ASC, l.next_on ASC, l.lost_at ASC, l.id ASC',
        };
        $total = (int) DB::val('SELECT COUNT(*) FROM leads l WHERE ' . $where, $p);
        $rows = DB::all(
            'SELECT l.*, lr.name AS reason_name, r.name AS responsible_name, r.active AS responsible_active, nr.name AS next_responsible_name,
                    s.name AS source_name, sp.name AS sport_name, pl.name AS plan_name
               FROM leads l
               LEFT JOIN lead_lost_reasons lr ON lr.id = l.lost_reason_id
               LEFT JOIN responsibles r ON r.id = l.responsible_id
               LEFT JOIN responsibles nr ON nr.id = l.next_responsible_id
               LEFT JOIN lead_sources s ON s.id = l.source_id
               LEFT JOIN sports sp ON sp.id = l.sport_id
               LEFT JOIN plans pl ON pl.id = l.plan_id
              WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Números da tela Reativação (cada lead perdido cai em UMA situação: ready, waiting, unscheduled, done, noauth ou dnc). */
    public static function lostCounts(): array
    {
        $c = [];
        foreach (['all' => '', 'ready' => 'ready', 'waiting' => 'waiting', 'unscheduled' => 'unscheduled', 'done' => 'done', 'noauth' => 'noauth', 'dnc' => 'dnc'] as $k => $f) {
            $p = [];
            $where = self::lostWhere(['f' => $f], $p);
            $c[$k] = (int) DB::val('SELECT COUNT(*) FROM leads l WHERE ' . $where, $p);
        }
        $c['eligible'] = $c['ready'] + $c['waiting'] + $c['unscheduled'];
        $c['recovered'] = (int) DB::val("SELECT COUNT(*) FROM leads WHERE status <> 'lost' AND recovery_status = 'recovered'");
        return $c;
    }

    // ------------------------------------------------------------------ lista

    public static function periods(): array
    {
        return ['' => 'Qualquer data de entrada', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', 'month' => 'Este mês'];
    }

    public static function sorts(): array
    {
        return [
            'new' => 'Mais recentes primeiro',
            'next' => 'Próximo retorno (mais urgente)',
            'last' => 'Último contato (mais antigo primeiro)',
            'name' => 'Nome (A a Z)',
        ];
    }

    /** Monta o WHERE da lista a partir dos filtros. */
    private static function where(array $f, array &$p): string
    {
        // Convertidos têm a sua própria lista (atalho "Convertidos"); as demais telas só mostram oportunidades em aberto.
        $w = [($f['f'] ?? '') === 'won' ? "l.status = 'won'" : "l.status = 'open'"];
        if (($f['q'] ?? '') !== '') {
            $q = $f['q'];
            // "!" é o caractere de escape do LIKE (funciona igual no MySQL e no SQLite)
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q) . '%';
            $parts = ["l.name LIKE ? ESCAPE '!'", "l.email LIKE ? ESCAPE '!'", "l.instagram LIKE ? ESCAPE '!'", "l.phone LIKE ? ESCAPE '!'"];
            array_push($p, $like, $like, $like, $like);
            $key = self::phoneKey($q);
            if ($key !== null && preg_match('/^[\d\s()+.-]+$/', $q)) {
                $parts[] = 'l.phone_key = ?';
                $p[] = $key;
            }
            $w[] = '(' . implode(' OR ', $parts) . ')';
        }
        foreach (['temp' => 'l.temperature', 'source' => 'l.source_id', 'stage' => 'l.stage_id', 'sport' => 'l.sport_id', 'plan' => 'l.plan_id'] as $k => $col) {
            if (($f[$k] ?? '') !== '') {
                $w[] = $col . ' = ?';
                $p[] = $f[$k];
            }
        }
        if (($f['resp'] ?? '') === 'none') {
            $w[] = 'l.responsible_id IS NULL';
        } elseif (($f['resp'] ?? '') !== '') {
            $w[] = 'l.responsible_id = ?';
            $p[] = (int) $f['resp'];
        }
        if (($f['task'] ?? '') !== '') {
            $w[] = 'l.next_on IS NOT NULL AND l.next_responsible_id = ?';
            $p[] = (int) $f['task'];
        }
        $t = today();
        if (($f['p'] ?? '') === '7d') {
            $w[] = 'l.entered_on >= ?';
            $p[] = date('Y-m-d', strtotime($t . ' -7 days'));
        } elseif (($f['p'] ?? '') === '30d') {
            $w[] = 'l.entered_on >= ?';
            $p[] = date('Y-m-d', strtotime($t . ' -30 days'));
        } elseif (($f['p'] ?? '') === 'month') {
            $w[] = 'l.entered_on >= ?';
            $p[] = substr($t, 0, 8) . '01';
        }
        if (($f['f'] ?? '') === 'late') {
            $w[] = 'l.next_on IS NOT NULL AND l.next_on < ?';
            $p[] = $t;
        } elseif (($f['f'] ?? '') === 'today') {
            $w[] = 'l.next_on = ?';
            $p[] = $t;
        } elseif (($f['f'] ?? '') === 'sched') {
            $w[] = 'l.next_on > ?';
            $p[] = $t;
        } elseif (($f['f'] ?? '') === 'none') {
            $w[] = 'l.next_on IS NULL AND l.do_not_contact = 0';
        } elseif (($f['f'] ?? '') === 'pot') {
            $w[] = 'l.stage_id IN (SELECT id FROM lead_stages WHERE potential = 1)';
        } elseif (($f['f'] ?? '') === 'noresp') {
            $w[] = 'l.responsible_id IS NULL';
        } elseif (($f['f'] ?? '') === 'hot') {
            $w[] = "l.temperature = 'hot'";
        } elseif (($f['f'] ?? '') === 'dnc') {
            $w[] = 'l.do_not_contact = 1';
        }
        return implode(' AND ', $w);
    }

    /** Uma página da lista + o total. */
    public static function listing(array $f, int $limit, int $offset): array
    {
        $p = [];
        $where = self::where($f, $p);
        $order = match ($f['ord'] ?? 'new') {
            'next' => '(l.next_on IS NULL) ASC, l.next_on ASC, l.id DESC',
            'last' => '(l.last_contact_at IS NULL) DESC, l.last_contact_at ASC, l.id DESC',
            'name' => 'l.name ASC, l.id DESC',
            default => 'l.entered_on DESC, l.id DESC',
        };
        $total = (int) DB::val('SELECT COUNT(*) FROM leads l WHERE ' . $where, $p);
        $rows = DB::all(
            'SELECT l.*, s.name AS source_name, st.name AS stage_name, r.name AS responsible_name, r.active AS responsible_active,
                    nr.name AS next_responsible_name, sp.name AS sport_name, pl.name AS plan_name
               FROM leads l
               JOIN lead_stages st ON st.id = l.stage_id
               LEFT JOIN lead_sources s ON s.id = l.source_id
               LEFT JOIN responsibles r ON r.id = l.responsible_id
               LEFT JOIN responsibles nr ON nr.id = l.next_responsible_id
               LEFT JOIN sports sp ON sp.id = l.sport_id
               LEFT JOIN plans pl ON pl.id = l.plan_id
              WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Tela inicial do CRM escolhida em Configurar: 'board' (Quadro, padrão) ou 'list' (Lista). */
    public static function homeView(): string
    {
        return Settings::get('crm_default_view', 'board') === 'list' ? 'list' : 'board';
    }

    /** Caminho (sem o prefixo da instalação) da tela inicial do CRM; é para onde o item "CRM" do menu aponta. */
    public static function homeUrl(): string
    {
        return self::homeView() === 'list' ? '/admin/crm' : '/admin/crm/quadro';
    }

    /**
     * Quadro (Kanban): os MESMOS leads em aberto da Lista, agrupados por etapa (09.3). Usa o mesmo WHERE da Lista,
     * então busca, temperatura, origem, responsável e atalhos valem igual nas duas telas.
     * Uma coluna por etapa em andamento (ativa, ou desativada mas ainda com leads, para ninguém sumir do quadro).
     * Dentro da coluna: retorno mais urgente primeiro (atrasados no topo), depois sem retorno. Cada coluna mostra no máximo BOARD_LIMIT cartões;
     * 'total' é sempre o número real de leads da coluna. Nas etapas que contam na receita potencial, 'potential' soma o plano de interesse.
     */
    public static function board(array $f): array
    {
        $f['f'] = in_array($f['f'] ?? '', ['late', 'today', 'hot', 'noresp'], true) ? $f['f'] : '';
        $p = [];
        $where = self::where($f, $p);
        $cols = [];
        $all = 0;
        foreach (DB::all(
            "SELECT st.*, (SELECT COUNT(*) FROM leads x WHERE x.stage_id = st.id AND x.status = 'open') AS open_leads
               FROM lead_stages st WHERE st.kind = 'open' ORDER BY st.sort_order ASC, st.id ASC"
        ) as $st) {
            if (!(int) $st['active'] && (int) $st['open_leads'] === 0) {
                continue;
            }
            $pp = array_merge($p, [(int) $st['id']]);
            $sum = DB::one(
                'SELECT COUNT(*) AS n, COALESCE(SUM(pl.price), 0) AS total, SUM(CASE WHEN pl.id IS NULL OR pl.price IS NULL THEN 1 ELSE 0 END) AS unpriced
                   FROM leads l LEFT JOIN plans pl ON pl.id = l.plan_id WHERE ' . $where . ' AND l.stage_id = ?',
                $pp
            ) ?: ['n' => 0, 'total' => 0, 'unpriced' => 0];
            $cards = DB::all(
                'SELECT l.*, s.name AS source_name, r.name AS responsible_name, nr.name AS next_responsible_name, sp.name AS sport_name, pl.name AS plan_name, pl.price AS plan_price
                   FROM leads l
                   LEFT JOIN lead_sources s ON s.id = l.source_id
                   LEFT JOIN responsibles r ON r.id = l.responsible_id
                   LEFT JOIN responsibles nr ON nr.id = l.next_responsible_id
                   LEFT JOIN sports sp ON sp.id = l.sport_id
                   LEFT JOIN plans pl ON pl.id = l.plan_id
                  WHERE ' . $where . ' AND l.stage_id = ?
                  ORDER BY (l.next_on IS NULL) ASC, l.next_on ASC, l.id DESC LIMIT ' . self::BOARD_LIMIT,
                $pp
            );
            $n = (int) $sum['n'];
            $all += $n;
            $cols[] = [
                'stage' => $st, 'total' => $n, 'cards' => $cards, 'more' => max(0, $n - count($cards)),
                'potential' => (float) $sum['total'], 'unpriced' => (int) ($sum['unpriced'] ?? 0),
            ];
        }
        return ['columns' => $cols, 'total' => $all];
    }

    /** Números dos atalhos e do topo da lista (só oportunidades em aberto, mais os convertidos). */
    public static function counts(): array
    {
        $t = today();
        $open = "status = 'open'";
        return [
            'open' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open"),
            'new_month' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE entered_on >= ?", [substr($t, 0, 8) . '01']),
            'hot' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND temperature = 'hot'"),
            'late' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND next_on IS NOT NULL AND next_on < ?", [$t]),
            'today' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND next_on = ?", [$t]),
            'sched' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND next_on > ?", [$t]),
            'none' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND next_on IS NULL AND do_not_contact = 0"),
            'noresp' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND responsible_id IS NULL"),
            'dnc' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE $open AND do_not_contact = 1"),
            'won' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'won'"),
        ];
    }

    /**
     * Indicadores comerciais do mês (09.2). Tudo calculado na hora a partir de leads, lead_stages, plans e lead_conversions.
     *  - converted:  leads que viraram paciente neste mês (pela data da conversão).
     *  - rate:       taxa de conversão = dos leads que ENTRARAM neste mês, quantos já viraram paciente (null se ninguém entrou).
     *  - potential:  soma do valor do plano de interesse dos leads em aberto nas etapas marcadas como "conta na receita potencial".
     *                Lead sem plano de interesse (ou plano sem valor) não entra na soma: aparece em 'unpriced'.
     *  - contracted: soma do valor contratado nas conversões do mês, separado por moeda (sem converter câmbio).
     */
    public static function indicators(): array
    {
        $m0 = substr(today(), 0, 8) . '01';
        $entered = (int) DB::val('SELECT COUNT(*) FROM leads WHERE entered_on >= ?', [$m0]);
        $cohortWon = (int) DB::val("SELECT COUNT(*) FROM leads WHERE entered_on >= ? AND status = 'won'", [$m0]);
        $pot = DB::one(
            "SELECT COUNT(*) AS n, COALESCE(SUM(p.price), 0) AS total, SUM(CASE WHEN p.id IS NULL OR p.price IS NULL THEN 1 ELSE 0 END) AS unpriced
               FROM leads l JOIN lead_stages st ON st.id = l.stage_id AND st.potential = 1 LEFT JOIN plans p ON p.id = l.plan_id
              WHERE l.status = 'open'"
        ) ?: ['n' => 0, 'total' => 0, 'unpriced' => 0];
        $byCur = [];
        $contracts = 0;
        foreach (DB::all('SELECT currency, SUM(contracted_value) AS total, COUNT(*) AS n FROM lead_conversions WHERE contracted_on >= ? GROUP BY currency', [$m0]) as $r) {
            $byCur[currency_or_brl($r['currency'])] = (float) $r['total'];
            $contracts += (int) $r['n'];
        }
        return [
            'converted' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'won' AND converted_at >= ?", [$m0 . ' 00:00:00']),
            'entered' => $entered,
            'cohort_won' => $cohortWon,
            'rate' => $entered > 0 ? round($cohortWon * 100 / $entered, 1) : null,
            'potential' => (float) $pot['total'],
            'potential_n' => (int) $pot['n'],
            'unpriced' => (int) ($pot['unpriced'] ?? 0),
            'contracted' => $byCur,
            'contracts' => $contracts,
        ];
    }

    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT l.*, s.name AS source_name, s.kind AS source_kind, st.name AS stage_name, st.kind AS stage_kind,
                    r.name AS responsible_name, nr.name AS next_responsible_name, sp.name AS sport_name, pl.name AS plan_name,
                    ub.name AS referred_name, cb.name AS created_name, lr.name AS lost_reason_name, pt.name AS patient_name
               FROM leads l
               JOIN lead_stages st ON st.id = l.stage_id
               LEFT JOIN lead_sources s ON s.id = l.source_id
               LEFT JOIN responsibles r ON r.id = l.responsible_id
               LEFT JOIN responsibles nr ON nr.id = l.next_responsible_id
               LEFT JOIN sports sp ON sp.id = l.sport_id
               LEFT JOIN plans pl ON pl.id = l.plan_id
               LEFT JOIN users ub ON ub.id = l.referred_by
               LEFT JOIN users cb ON cb.id = l.created_by
               LEFT JOIN lead_lost_reasons lr ON lr.id = l.lost_reason_id
               LEFT JOIN users pt ON pt.id = l.patient_id
              WHERE l.id = ?',
            [$id]
        );
    }

    // ------------------------------------------------------------------ apresentação

    /** "hoje", "ontem" ou "há N dias" (por dia do calendário, não por 24 horas) para um dia/hora já passado. */
    public static function agoText(?string $dt): string
    {
        $d = $dt ? days_until(substr($dt, 0, 10)) : null;
        if ($d === null) {
            return '';
        }
        $n = -$d;
        if ($n <= 0) {
            return 'hoje';
        }
        return $n === 1 ? 'ontem' : 'há ' . $n . ' dias';
    }

    /** Texto e cor do "próximo retorno". cls: late (vermelho), today (vinho), soon (neutro) ou '' (sem retorno). */
    public static function nextInfo(?string $date): array
    {
        $d = days_until($date);
        if ($d === null) {
            return ['text' => 'sem retorno agendado', 'cls' => '', 'days' => null];
        }
        if ($d < 0) {
            return ['text' => 'atrasado ' . abs($d) . ($d === -1 ? ' dia' : ' dias'), 'cls' => 'late', 'days' => $d];
        }
        if ($d === 0) {
            return ['text' => 'hoje', 'cls' => 'today', 'days' => 0];
        }
        return ['text' => $d === 1 ? 'amanhã' : 'em ' . $d . ' dias', 'cls' => 'soon', 'days' => $d];
    }

    /**
     * Estado visual do follow-up de um lead: late (atrasado), today (hoje), scheduled (agendado), none (sem retorno programado)
     * ou dnc (não contatar). Devolve também o texto de nextInfo() (text, cls, days).
     */
    public static function followUp(array $lead): array
    {
        if ((int) ($lead['do_not_contact'] ?? 0) === 1) {
            return ['state' => 'dnc', 'label' => 'Não contatar', 'text' => '', 'cls' => '', 'days' => null];
        }
        $nx = self::nextInfo($lead['next_on'] ?? null);
        $state = $nx['days'] === null ? 'none' : ($nx['days'] < 0 ? 'late' : ($nx['days'] === 0 ? 'today' : 'scheduled'));
        $labels = ['none' => 'Sem retorno programado', 'late' => 'Retorno atrasado', 'today' => 'Retorno hoje', 'scheduled' => 'Próximo retorno agendado'];
        return ['state' => $state, 'label' => $labels[$state]] + $nx;
    }

    /** Mensagem de WhatsApp pronta (editável em Configurações). {nome} vira o primeiro nome. */
    public static function message(string $name): string
    {
        $tpl = trim((string) Settings::get('lead_message', ''));
        if ($tpl === '') {
            $tpl = self::DEFAULT_MESSAGE;
        }
        return str_replace('{nome}', first_name($name), $tpl);
    }

    /** Link do WhatsApp (null se não há telefone válido, se está como "Não contatar" ou se é um lead perdido sem autorização para novos contatos). */
    public static function waUrl(array $lead): ?string
    {
        if ((int) ($lead['do_not_contact'] ?? 0) === 1) {
            return null;
        }
        // lead perdido que não autorizou novos contatos: sem mensagem pronta de abordagem (se ele escrever, a equipe responde à mão)
        if (($lead['status'] ?? '') === 'lost' && (int) ($lead['contact_ok'] ?? 0) !== 1) {
            return null;
        }
        return wa_phone_url($lead['phone'] ?? '', self::message((string) $lead['name']));
    }
}
