<?php
namespace App\Services;

use App\Core\DB;

/**
 * CRM / Leads (Atualização 09.1).
 * Lead = alguém que ainda NÃO é paciente: de onde veio, em que etapa está, quando foi o último contato e o próximo.
 *
 * REGRAS:
 *  - A linha do tempo (lead_events) só ganha linhas novas. O sistema nunca edita nem apaga uma linha antiga
 *    (a única exceção é a exclusão do lead inteiro, pedido de exclusão pela LGPD, que também leva a linha do tempo).
 *  - "Não contatar" bloqueia o botão de WhatsApp e novos retornos agendados.
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
                $nextTxt = (self::ACTIONS[$c['next_type']] ?? 'Retorno') . ' em ' . fmt_date($c['next_on']);
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
        $w = ["l.status = 'open'"];
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
                    sp.name AS sport_name, pl.name AS plan_name
               FROM leads l
               JOIN lead_stages st ON st.id = l.stage_id
               LEFT JOIN lead_sources s ON s.id = l.source_id
               LEFT JOIN responsibles r ON r.id = l.responsible_id
               LEFT JOIN sports sp ON sp.id = l.sport_id
               LEFT JOIN plans pl ON pl.id = l.plan_id
              WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset,
            $p
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** Números do topo da lista. */
    public static function counts(): array
    {
        $t = today();
        return [
            'open' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'open'"),
            'new_month' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE entered_on >= ?", [substr($t, 0, 8) . '01']),
            'hot' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'open' AND temperature = 'hot'"),
            'late' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'open' AND next_on IS NOT NULL AND next_on < ?", [$t]),
            'today' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'open' AND next_on = ?", [$t]),
            'dnc' => (int) DB::val("SELECT COUNT(*) FROM leads WHERE status = 'open' AND do_not_contact = 1"),
        ];
    }

    public static function find(int $id): ?array
    {
        return DB::one(
            'SELECT l.*, s.name AS source_name, s.kind AS source_kind, st.name AS stage_name, st.kind AS stage_kind,
                    r.name AS responsible_name, nr.name AS next_responsible_name, sp.name AS sport_name, pl.name AS plan_name,
                    ub.name AS referred_name, cb.name AS created_name
               FROM leads l
               JOIN lead_stages st ON st.id = l.stage_id
               LEFT JOIN lead_sources s ON s.id = l.source_id
               LEFT JOIN responsibles r ON r.id = l.responsible_id
               LEFT JOIN responsibles nr ON nr.id = l.next_responsible_id
               LEFT JOIN sports sp ON sp.id = l.sport_id
               LEFT JOIN plans pl ON pl.id = l.plan_id
               LEFT JOIN users ub ON ub.id = l.referred_by
               LEFT JOIN users cb ON cb.id = l.created_by
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

    /** Mensagem de WhatsApp pronta (editável em Configurações). {nome} vira o primeiro nome. */
    public static function message(string $name): string
    {
        $tpl = trim((string) Settings::get('lead_message', ''));
        if ($tpl === '') {
            $tpl = self::DEFAULT_MESSAGE;
        }
        return str_replace('{nome}', first_name($name), $tpl);
    }

    /** Link do WhatsApp (null se não há telefone válido ou se o lead está como "Não contatar"). */
    public static function waUrl(array $lead): ?string
    {
        if ((int) ($lead['do_not_contact'] ?? 0) === 1) {
            return null;
        }
        return wa_phone_url($lead['phone'] ?? '', self::message((string) $lead['name']));
    }
}
