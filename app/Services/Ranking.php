<?php
namespace App\Services;

use App\Core\DB;

/** Ranking MENSAL: soma dos ganhos do mês. Zera sozinho a cada mês. */
final class Ranking
{
    /** @return array{0:string,1:string} início (inclusive) e fim (exclusive) do mês */
    public static function bounds(?string $ym = null): array
    {
        if (!$ym || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
            $ym = date('Y-m');
        }
        $start = $ym . '-01 00:00:00';
        $end = date('Y-m-d 00:00:00', strtotime($ym . '-01 +1 month'));
        return [$start, $end];
    }

    public static function normalize(?string $ym): string
    {
        return ($ym && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) ? $ym : date('Y-m');
    }

    /**
     * Lista completa do mês, já com a posição (empates dividem a posição).
     * Retorna cada item com: id, name, avatar, pts, pos.
     */
    public static function month(?string $ym = null): array
    {
        [$start, $end] = self::bounds($ym);
        $rows = DB::all(
            "SELECT u.id, u.name, u.avatar, COALESCE(SUM(l.points), 0) AS pts
               FROM users u
               LEFT JOIN points_ledger l
                 ON l.user_id = u.id AND l.counts_ranking = 1 AND l.created_at >= ? AND l.created_at < ?
              WHERE u.role = 'patient' AND u.status = 'active' AND u.app_access = 1
              GROUP BY u.id, u.name, u.avatar
              ORDER BY pts DESC, u.name ASC",
            [$start, $end]
        );
        $pos = 0;
        $prev = null;
        foreach ($rows as $i => &$r) {
            $r['pts'] = (int) $r['pts'];
            if ($prev === null || $r['pts'] !== $prev) {
                $pos = $i + 1;
            }
            $r['pos'] = $pos;
            $prev = $r['pts'];
        }
        unset($r);
        return $rows;
    }

    public static function positionOf(int $userId, ?string $ym = null): ?array
    {
        foreach (self::month($ym) as $r) {
            if ((int) $r['id'] === $userId) {
                return $r;
            }
        }
        return null;
    }

    /** Meses que já têm pontos registrados (para escolher no seletor). */
    public static function availableMonths(): array
    {
        $first = DB::val('SELECT MIN(created_at) FROM points_ledger');
        $months = [];
        $cursor = new \DateTimeImmutable(date('Y-m-01'));
        $limit = $first ? new \DateTimeImmutable(date('Y-m-01', strtotime($first))) : $cursor;
        for ($i = 0; $i < 24 && $cursor >= $limit; $i++) {
            $months[] = $cursor->format('Y-m');
            $cursor = $cursor->modify('-1 month');
        }
        return $months ?: [date('Y-m')];
    }
}
