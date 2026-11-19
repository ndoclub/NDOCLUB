<?php
namespace App\Services;

/** Níveis Bronze/Prata/Ouro/Black. Baseados nos pontos ganhos de toda a vida: nunca caem. */
final class Levels
{
    public static function forPoints(int $lifetime): array
    {
        $tiers = [
            ['key' => 'bronze', 'name' => 'Bronze', 'at' => 0],
            ['key' => 'prata',  'name' => 'Prata',  'at' => (int) Settings::get('level_prata', 500)],
            ['key' => 'ouro',   'name' => 'Ouro',   'at' => (int) Settings::get('level_ouro', 1500)],
            ['key' => 'black',  'name' => 'Black',  'at' => (int) Settings::get('level_black', 4000)],
        ];
        $current = $tiers[0];
        $next = null;
        foreach ($tiers as $i => $t) {
            if ($lifetime >= $t['at']) {
                $current = $t;
                $next = $tiers[$i + 1] ?? null;
            }
        }
        $progress = 100;
        if ($next) {
            $span = max(1, $next['at'] - $current['at']);
            $progress = (int) max(0, min(100, round(($lifetime - $current['at']) / $span * 100)));
        }
        return [
            'key' => $current['key'],
            'name' => $current['name'],
            'next_name' => $next['name'] ?? null,
            'next_at' => $next['at'] ?? null,
            'missing' => $next ? max(0, $next['at'] - $lifetime) : 0,
            'progress' => $progress,
        ];
    }
}
