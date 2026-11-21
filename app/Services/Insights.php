<?php
namespace App\Services;

use App\Core\DB;

/**
 * Indicadores do Painel gerencial (aba "Visão executiva"). SÓ o admin vê: tem valores em R$.
 *
 * Definições combinadas com o Gabriel (20/11/2026):
 *  - Paciente ATIVO = conta ativa e plano em dia (vencimento de hoje em diante). É o "Ativo" da lista de pacientes.
 *  - NOVO paciente = "Paciente desde" (ou a data do cadastro, se vazio) dentro do período.
 *  - Vendido (faturamento) = valor cheio das vendas feitas no período. Recebido = o que caiu no período (líquido).
 *  - A receber = parcelas pendentes que ainda vão vencer. INADIMPLÊNCIA = parcela pendente vencida há 1 dia ou mais.
 *  - TAXA DE RENOVAÇÃO = dos planos que venceram no período, quantos renovaram (quem renovou antes do vencimento
 *    também conta). Quem renovou atrasado conta no mês em que o plano venceu.
 *  - SEM ACOMPANHAMENTO = paciente ativo sem responsável atribuído (modo babá).
 * Reais (R$) nunca são somados com dólar ou euro: o que não é real aparece à parte.
 */
final class Insights
{
    public static function periods(): array
    {
        return ['30d' => 'Últimos 30 dias', 'mes' => 'Mês atual', 'mes_ant' => 'Mês passado', 'ano' => 'Ano atual'];
    }

    public static function graphs(): array
    {
        return ['6' => '6 meses', '12' => '12 meses', 'ano' => 'Ano atual'];
    }

    public static function periodKey(?string $k): string
    {
        return isset(self::periods()[(string) $k]) ? (string) $k : '30d';
    }

    public static function graphKey(?string $k): string
    {
        return isset(self::graphs()[(string) $k]) ? (string) $k : '6';
    }

    /** Dia do mês limitado ao último dia daquele mês (ex.: 31 em abril vira 30). */
    private static function clampDay(string $monthStart, int $day): string
    {
        $last = (int) date('t', strtotime($monthStart));
        return date('Y-m-', strtotime($monthStart)) . sprintf('%02d', min($day, $last));
    }

    /** Período escolhido e o período anterior equivalente (para as setas de comparação). */
    public static function window(string $key): array
    {
        $today = today();
        $firstOfMonth = date('Y-m-01', strtotime($today));
        switch ($key) {
            case 'mes':
                $from = $firstOfMonth;
                $to = $today;
                $pFrom = date('Y-m-d', strtotime($from . ' -1 month'));
                $pTo = self::clampDay($pFrom, (int) date('j', strtotime($today)));
                break;
            case 'mes_ant':
                $from = date('Y-m-d', strtotime($firstOfMonth . ' -1 month'));
                $to = date('Y-m-d', strtotime($firstOfMonth . ' -1 day'));
                $pFrom = date('Y-m-d', strtotime($from . ' -1 month'));
                $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
                break;
            case 'ano':
                $from = date('Y-01-01', strtotime($today));
                $to = $today;
                $pFrom = (((int) date('Y', strtotime($today))) - 1) . '-01-01';
                $pTo = self::clampDay((((int) date('Y', strtotime($today))) - 1) . '-' . date('m', strtotime($today)) . '-01', (int) date('j', strtotime($today)));
                break;
            default:
                $key = '30d';
                $from = date('Y-m-d', strtotime($today . ' -29 day'));
                $to = $today;
                $pFrom = date('Y-m-d', strtotime($from . ' -30 day'));
                $pTo = date('Y-m-d', strtotime($from . ' -1 day'));
        }
        return ['key' => $key, 'label' => self::periods()[$key], 'from' => $from, 'to' => $to, 'pFrom' => $pFrom, 'pTo' => $pTo];
    }

    /** Variação percentual (null quando não dá para comparar). */
    public static function trend(float $now, float $before): ?array
    {
        if ($before <= 0) {
            return null;
        }
        $pct = ($now - $before) / $before * 100;
        $r = (int) round($pct);
        return ['pct' => abs($r), 'dir' => $r > 0 ? 'up' : ($r < 0 ? 'down' : 'flat')];
    }

    /** Diferença em pontos percentuais (para a taxa de renovação). */
    public static function trendPoints(?int $now, ?int $before): ?array
    {
        if ($now === null || $before === null) {
            return null;
        }
        $d = $now - $before;
        return ['pct' => abs($d), 'dir' => $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat')];
    }

    // ---------------------------------------------------------------
    // Pacientes: ativos, novos e crescimento
    // ---------------------------------------------------------------

    /** Todos os pacientes, só com o que os indicadores precisam. */
    private static function patients(): array
    {
        return DB::all(
            "SELECT u.id, u.status, u.renewal_date, u.responsible_id, u.plan_id,
                    COALESCE(u.joined_on, SUBSTR(u.created_at, 1, 10)) AS since
               FROM users u WHERE u.role = 'patient'"
        );
    }

    /** Quantos pacientes estavam ativos num dia (conta ativa, já tinha começado e o plano ainda valia). */
    private static function activeAt(array $pats, string $day): int
    {
        $n = 0;
        foreach ($pats as $p) {
            if ($p['status'] === 'active' && $p['renewal_date'] !== null && substr((string) $p['since'], 0, 10) <= $day && substr((string) $p['renewal_date'], 0, 10) >= $day) {
                $n++;
            }
        }
        return $n;
    }

    private static function newBetween(array $pats, string $from, string $to): int
    {
        $n = 0;
        foreach ($pats as $p) {
            $s = substr((string) $p['since'], 0, 10);
            if ($s >= $from && $s <= $to) {
                $n++;
            }
        }
        return $n;
    }

    // ---------------------------------------------------------------
    // Renovação: dos planos que venceram, quantos renovaram
    // ---------------------------------------------------------------

    /**
     * Todos os vencimentos que já aconteceram: ['user|data' => ['user' => id, 'date' => AAAA-MM-DD, 'renewed' => bool]].
     * Cada renovação guarda o vencimento que ela renovou (previous_date); o vencimento atual do paciente,
     * se já passou, é um plano que venceu e ainda não renovou.
     */
    private static function dueEvents(array $pats): array
    {
        $today = today();
        $ev = [];
        foreach (DB::all("SELECT r.user_id, r.previous_date FROM renewals r JOIN users u ON u.id = r.user_id WHERE u.role = 'patient' AND r.previous_date IS NOT NULL") as $r) {
            $d = substr((string) $r['previous_date'], 0, 10);
            if ($d <= $today) {
                $ev[$r['user_id'] . '|' . $d] = ['user' => (int) $r['user_id'], 'date' => $d, 'renewed' => true];
            }
        }
        foreach ($pats as $p) {
            if ($p['renewal_date'] === null) {
                continue;
            }
            $d = substr((string) $p['renewal_date'], 0, 10);
            $k = $p['id'] . '|' . $d;
            if ($d <= $today && !isset($ev[$k])) {
                $ev[$k] = ['user' => (int) $p['id'], 'date' => $d, 'renewed' => false];
            }
        }
        return $ev;
    }

    /**
     * Taxa de renovação de um período. $only = ids de pacientes (para a tabela da equipe), ou null para todos.
     * Retorna due (planos que venceram), renewed (quantos renovaram) e pct (null se nada venceu).
     */
    private static function renewalRate(array $events, string $from, string $to, ?array $only = null): array
    {
        $due = 0;
        $renewed = 0;
        foreach ($events as $e) {
            if ($e['date'] < $from || $e['date'] > $to) {
                continue;
            }
            if ($only !== null && !isset($only[$e['user']])) {
                continue;
            }
            $due++;
            $renewed += $e['renewed'] ? 1 : 0;
        }
        return ['due' => $due, 'renewed' => $renewed, 'pct' => $due > 0 ? (int) round($renewed / $due * 100) : null];
    }

    // ---------------------------------------------------------------
    // Financeiro
    // ---------------------------------------------------------------

    /** [moeda => total] das vendas feitas entre duas datas (inclusive). */
    private static function sold(string $from, string $to): array
    {
        $out = [];
        foreach (DB::all('SELECT currency, SUM(total) AS t FROM payments WHERE sold_on >= ? AND sold_on <= ? GROUP BY currency', [$from, $to]) as $r) {
            $out[$r['currency']] = (float) $r['t'];
        }
        return $out;
    }

    /** O que caiu em R$ entre duas datas (líquido; dólar/euro pelo valor creditado). */
    private static function received(string $from, string $to): float
    {
        return (float) DB::val(
            "SELECT COALESCE(SUM(received_brl), 0) FROM payment_installments WHERE status = 'received' AND received_on >= ? AND received_on <= ?",
            [$from, $to]
        );
    }

    /** Parcelas pendentes. $late = true: vencidas (inadimplência); false: ainda a vencer. */
    private static function open(bool $late): array
    {
        $cmp = $late ? 'i.due_on < ?' : 'i.due_on >= ?';
        // Em reais vale o líquido (depois da taxa do cartão); em dólar/euro, o valor da parcela
        $net = "CASE WHEN i.currency = 'BRL' THEN COALESCE(i.net_amount, i.amount) ELSE i.amount END";
        $by = [];
        $n = 0;
        foreach (DB::all("SELECT i.currency, COUNT(*) AS n, SUM($net) AS t FROM payment_installments i WHERE i.status = 'pending' AND $cmp GROUP BY i.currency", [today()]) as $r) {
            $by[$r['currency']] = (float) $r['t'];
            $n += (int) $r['n'];
        }
        $patients = (int) DB::val(
            "SELECT COUNT(DISTINCT p.user_id) FROM payment_installments i JOIN payments p ON p.id = i.payment_id WHERE i.status = 'pending' AND $cmp",
            [today()]
        );
        return ['by' => $by, 'count' => $n, 'patients' => $patients];
    }

    // ---------------------------------------------------------------
    // Série mensal (gráficos e mini gráficos)
    // ---------------------------------------------------------------

    /** Os últimos 12 meses (até o atual): ym, rótulo, vendido e recebido em R$, ativos no fim do mês e novos. */
    private static function monthly(array $pats): array
    {
        $today = today();
        $first = date('Y-m-d', strtotime(date('Y-m-01', strtotime($today)) . ' -11 month'));
        $soldBy = [];
        foreach (DB::all("SELECT SUBSTR(sold_on, 1, 7) AS ym, SUM(total) AS t FROM payments WHERE currency = 'BRL' AND sold_on >= ? AND sold_on <= ? GROUP BY SUBSTR(sold_on, 1, 7)", [$first, $today]) as $r) {
            $soldBy[$r['ym']] = (float) $r['t'];
        }
        $recvBy = [];
        foreach (DB::all("SELECT SUBSTR(received_on, 1, 7) AS ym, SUM(received_brl) AS t FROM payment_installments WHERE status = 'received' AND received_on >= ? AND received_on <= ? GROUP BY SUBSTR(received_on, 1, 7)", [$first, $today]) as $r) {
            $recvBy[$r['ym']] = (float) $r['t'];
        }
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $start = date('Y-m-d', strtotime($first . ' +' . $i . ' month'));
            $ym = substr($start, 0, 7);
            $end = min(date('Y-m-t', strtotime($start)), $today);
            $out[] = [
                'ym' => $ym,
                'label' => mb_strtolower(mb_substr(month_name((int) date('n', strtotime($start))), 0, 3, 'UTF-8'), 'UTF-8'),
                'sold' => $soldBy[$ym] ?? 0.0,
                'received' => $recvBy[$ym] ?? 0.0,
                'active' => self::activeAt($pats, $end),
                'new' => self::newBetween($pats, $start, $end),
                'partial' => $ym === substr($today, 0, 7),
            ];
        }
        return $out;
    }

    // ---------------------------------------------------------------
    // Carteira e equipe
    // ---------------------------------------------------------------

    /** Pacientes ativos hoje por plano. Cada item: name, count, pct. Planos sem ninguém não entram. */
    private static function byPlan(array $activeIds): array
    {
        $plans = DB::all('SELECT id, name, duration_days FROM plans ORDER BY duration_days ASC, id ASC');
        $count = [];
        foreach ($activeIds as $p) {
            $k = $p['plan_id'] === null ? 0 : (int) $p['plan_id'];
            $count[$k] = ($count[$k] ?? 0) + 1;
        }
        $total = count($activeIds);
        $out = [];
        foreach ($plans as $pl) {
            $n = $count[(int) $pl['id']] ?? 0;
            if ($n > 0) {
                $out[] = ['name' => (string) $pl['name'], 'count' => $n, 'pct' => $total ? (int) round($n / $total * 100) : 0, 'none' => false];
            }
        }
        if (!empty($count[0])) {
            $out[] = ['name' => 'Sem plano', 'count' => $count[0], 'pct' => $total ? (int) round($count[0] / $total * 100) : 0, 'none' => true];
        }
        return $out;
    }

    /** Modalidades dos atletas ativos: name, count, pct (sobre os atletas). */
    private static function bySport(): array
    {
        $today = today();
        $athletes = (int) DB::val(
            "SELECT COUNT(*) FROM athlete_profiles ap JOIN users u ON u.id = ap.user_id
              WHERE ap.is_athlete = 1 AND u.role = 'patient' AND u.status = 'active' AND u.renewal_date >= ?",
            [$today]
        );
        $rows = DB::all(
            "SELECT s.name, COUNT(*) AS n FROM user_sports us
               JOIN sports s ON s.id = us.sport_id
               JOIN athlete_profiles ap ON ap.user_id = us.user_id AND ap.is_athlete = 1
               JOIN users u ON u.id = us.user_id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date >= ?
              GROUP BY s.id, s.name ORDER BY n DESC, s.name ASC",
            [$today]
        );
        $list = [];
        foreach ($rows as $r) {
            $list[] = ['name' => (string) $r['name'], 'count' => (int) $r['n'], 'pct' => $athletes ? (int) round($r['n'] / $athletes * 100) : 0];
        }
        return ['athletes' => $athletes, 'list' => $list];
    }

    /**
     * Equipe: por responsável, quantos pacientes ativos, % das consultas da cota que já foram feitas e a taxa
     * de renovação do período. A última linha ("Sem responsável") é o "sem acompanhamento".
     */
    private static function team(array $pats, array $activePats, array $events, array $w): array
    {
        $today = today();
        $quotaRows = DB::all(
            'SELECT u.id, u.responsible_id, pl.consultations_included AS quota, ' . Consultations::DONE_SQL . " AS done
               FROM users u LEFT JOIN plans pl ON pl.id = u.plan_id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date >= ?",
            [$today]
        );
        $groups = [];
        foreach ($activePats as $p) {
            $k = $p['responsible_id'] === null ? 0 : (int) $p['responsible_id'];
            $groups[$k]['ids'][(int) $p['id']] = true;
        }
        $doneBy = [];
        $quotaBy = [];
        foreach ($quotaRows as $r) {
            if ($r['quota'] === null) {
                continue;
            }
            $k = $r['responsible_id'] === null ? 0 : (int) $r['responsible_id'];
            $doneBy[$k] = ($doneBy[$k] ?? 0) + min((int) $r['done'], (int) $r['quota']);
            $quotaBy[$k] = ($quotaBy[$k] ?? 0) + (int) $r['quota'];
        }
        // Renovação: o vencimento conta para o responsável ATUAL do paciente, mesmo que o paciente já não esteja ativo
        $byResp = [];
        foreach ($pats as $p) {
            $byResp[$p['responsible_id'] === null ? 0 : (int) $p['responsible_id']][(int) $p['id']] = true;
        }
        $rows = [];
        foreach (DB::all('SELECT id, name, active FROM responsibles ORDER BY name ASC') as $r) {
            $id = (int) $r['id'];
            $n = count($groups[$id]['ids'] ?? []);
            if ((int) $r['active'] !== 1 && $n === 0) {
                continue; // desativada e sem pacientes: não ocupa espaço
            }
            $rate = self::renewalRate($events, $w['from'], $w['to'], $byResp[$id] ?? []);
            $rows[] = [
                'id' => $id, 'name' => (string) $r['name'], 'active' => (int) $r['active'] === 1, 'patients' => $n,
                'cons' => isset($quotaBy[$id]) && $quotaBy[$id] > 0 ? (int) round($doneBy[$id] / $quotaBy[$id] * 100) : null,
                'renewal' => $rate['pct'], 'due' => $rate['due'],
            ];
        }
        $none = count($groups[0]['ids'] ?? []);
        return ['rows' => $rows, 'noResponsible' => $none];
    }

    // ---------------------------------------------------------------
    // Painel completo
    // ---------------------------------------------------------------

    public static function build(string $period, string $graph): array
    {
        $today = today();
        $w = self::window($period);
        $pats = self::patients();
        $activePats = array_values(array_filter(
            $pats,
            fn($p) => $p['status'] === 'active' && $p['renewal_date'] !== null && substr((string) $p['renewal_date'], 0, 10) >= $today
        ));
        $events = self::dueEvents($pats);
        $months = self::monthly($pats);

        // Meses do gráfico grande e dos mini gráficos (6 últimos)
        $thisYear = date('Y');
        $chart = match ($graph) {
            '12' => $months,
            'ano' => array_values(array_filter($months, fn($m) => str_starts_with($m['ym'], $thisYear))),
            default => array_slice($months, -6),
        };
        $last6 = array_slice($months, -6);

        $soldNow = self::sold($w['from'], $w['to']);
        $soldPrev = self::sold($w['pFrom'], $w['pTo']);
        $recvNow = self::received($w['from'], $w['to']);
        $recvPrev = self::received($w['pFrom'], $w['pTo']);
        $activeNow = self::activeAt($pats, $w['to']);
        $activePrev = self::activeAt($pats, $w['pTo']);
        $newNow = self::newBetween($pats, $w['from'], $w['to']);
        $newPrev = self::newBetween($pats, $w['pFrom'], $w['pTo']);
        $rateNow = self::renewalRate($events, $w['from'], $w['to']);
        $ratePrev = self::renewalRate($events, $w['pFrom'], $w['pTo']);
        $openNext = self::open(false);
        $openLate = self::open(true);
        $yearSold = self::sold(date('Y-01-01'), $today);

        // Crescimento: primeiro e último mês dos 6 do gráfico
        $g0 = $last6[0]['active'] ?? 0;
        $g1 = $last6[count($last6) - 1]['active'] ?? 0;
        $growth = $g0 > 0 ? (int) round(($g1 - $g0) / $g0 * 100) : null;

        // Alertas (mesmas definições dos filtros da lista de pacientes, para o número bater com o clique)
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 day'));
        $yellow = (int) Settings::get('warn_yellow_days', 15);
        $c = fn(string $sql, array $p = []) => (int) DB::val($sql, $p);
        $alerts = [
            'd7' => $c("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date >= ? AND renewal_date <= ?", [$today, date('Y-m-d', strtotime($today . ' +7 day'))]),
            'expired' => $c("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL AND renewal_date < ?", [$today]),
            'away' => $c("SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND app_access = 1 AND (last_seen_at IS NULL OR last_seen_at < ?)", [$weekAgo]),
            'risk' => $c(
                "SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND app_access = 1 AND renewal_date >= ? AND renewal_date <= ? AND (last_seen_at IS NULL OR last_seen_at < ?)",
                [$today, date('Y-m-d', strtotime($today . ' +' . $yellow . ' day')), $weekAgo]
            ),
            'late' => $openLate['count'],
            'reports' => $c("SELECT COUNT(*) FROM reports WHERE status = 'open'"),
            'redemptions' => $c("SELECT COUNT(*) FROM redemptions WHERE status = 'pending'"),
        ];
        $events1 = Athletes::upcoming(1);

        $team = self::team($pats, $activePats, $events, $w);
        $alerts['noresp'] = $team['noResponsible'];
        $alerts['birthdays'] = Birthdays::todayCount($today);

        return [
            'window' => $w,
            'graph' => $graph,
            'cards' => [
                'active' => ['value' => $activeNow, 'trend' => self::trend($activeNow, $activePrev), 'spark' => array_column($last6, 'active')],
                'sold' => ['by' => $soldNow, 'brl' => (float) ($soldNow['BRL'] ?? 0), 'trend' => self::trend((float) ($soldNow['BRL'] ?? 0), (float) ($soldPrev['BRL'] ?? 0)), 'spark' => array_column($last6, 'sold')],
                'received' => ['value' => $recvNow, 'trend' => self::trend($recvNow, $recvPrev), 'spark' => array_column($last6, 'received')],
                'open' => $openNext,
                'new' => ['value' => $newNow, 'trend' => self::trend($newNow, $newPrev), 'spark' => array_column($last6, 'new')],
                'renewal' => ['rate' => $rateNow, 'trend' => self::trendPoints($rateNow['pct'], $ratePrev['pct'])],
            ],
            'finance' => ['sold' => $soldNow, 'yearSold' => $yearSold, 'received' => $recvNow, 'open' => $openNext, 'late' => $openLate],
            'chart' => $chart,
            'plans' => self::byPlan($activePats),
            'activeTotal' => count($activePats),
            'sports' => self::bySport(),
            'growth' => ['months' => $last6, 'pct' => $growth, 'newPeriod' => $newNow],
            'team' => $team,
            'alerts' => $alerts,
            'nextEvent' => $events1[0] ?? null,
            'yellow' => $yellow,
        ];
    }
}
