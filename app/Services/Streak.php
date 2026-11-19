<?php
namespace App\Services;

use App\Core\DB;

/** Sequência de dias seguidos com check-in ("No Days Off"). */
final class Streak
{
    private static function dates(int $userId, int $limit = 400): array
    {
        $rows = DB::all('SELECT checkin_date FROM checkins WHERE user_id = ? ORDER BY checkin_date DESC LIMIT ' . (int) $limit, [$userId]);
        $set = [];
        foreach ($rows as $r) {
            $set[substr((string) $r['checkin_date'], 0, 10)] = true;
        }
        return $set;
    }

    /** Sequência atual. Se hoje ainda não fez check-in, a de ontem continua valendo. */
    public static function current(int $userId): int
    {
        $set = self::dates($userId);
        $d = new \DateTimeImmutable('today');
        if (!isset($set[$d->format('Y-m-d')])) {
            $d = $d->modify('-1 day');
        }
        $n = 0;
        while (isset($set[$d->format('Y-m-d')])) {
            $n++;
            $d = $d->modify('-1 day');
        }
        return $n;
    }

    /** Últimos 7 dias (do mais antigo até hoje). */
    public static function week(int $userId): array
    {
        $set = self::dates($userId, 30);
        $labels = [1 => 'S', 'T', 'Q', 'Q', 'S', 'S', 'D'];
        $out = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = new \DateTimeImmutable("today -$i day");
            $out[] = [
                'date' => $d->format('Y-m-d'),
                'label' => $labels[(int) $d->format('N')],
                'done' => isset($set[$d->format('Y-m-d')]),
                'today' => $i === 0,
            ];
        }
        return $out;
    }

    public static function doneToday(int $userId): bool
    {
        return (bool) DB::val('SELECT COUNT(*) FROM checkins WHERE user_id = ? AND checkin_date = ?', [$userId, today()]);
    }
}
