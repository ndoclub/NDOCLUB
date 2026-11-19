<?php
namespace App\Services;

use App\Core\DB;

/**
 * Pontos. TODA movimentação passa por aqui e fica gravada no extrato
 * (tabela points_ledger). O saldo é sempre a soma do extrato.
 *
 *  - Saldo     = soma de tudo (ganhos - resgates). Serve para a loja.
 *  - Ranking   = soma só dos ganhos do mês (counts_ranking = 1). Zera todo mês.
 *  - Nível     = soma dos ganhos de toda a vida. Nunca cai.
 */
final class Points
{
    private const SYSTEM_LABELS = [
        'manual'   => 'Ajuste manual',
        'redeem'   => 'Resgate na loja',
        'refund'   => 'Estorno de resgate',
        'reversal' => 'Pontos removidos pela moderação',
    ];

    public static function rule(string $key): ?array
    {
        return DB::one('SELECT * FROM point_rules WHERE rkey = ?', [$key]);
    }

    public static function label(string $ruleKey): string
    {
        if (isset(self::SYSTEM_LABELS[$ruleKey])) {
            return self::SYSTEM_LABELS[$ruleKey];
        }
        $rule = self::rule($ruleKey);
        return $rule ? $rule['label'] : $ruleKey;
    }

    /**
     * Dá pontos por uma regra, respeitando:
     *  - regra ativa;
     *  - limite diário (anti-abuso);
     *  - 1 vez por item (ex.: curtir o mesmo post não paga duas vezes).
     * Devolve quantos pontos foram dados (0 se não deu).
     */
    public static function award(int $userId, string $ruleKey, ?string $refType = null, ?int $refId = null, ?string $note = null): int
    {
        $rule = self::rule($ruleKey);
        if (!$rule || (int) $rule['active'] !== 1 || (int) $rule['points'] <= 0) {
            return 0;
        }

        if ($refType !== null && $refId !== null) {
            $dup = DB::val(
                'SELECT COUNT(*) FROM points_ledger WHERE user_id = ? AND rule_key = ? AND ref_type = ? AND ref_id = ?',
                [$userId, $ruleKey, $refType, $refId]
            );
            if ($dup) {
                return 0;
            }
        }

        $limit = (int) $rule['daily_limit'];
        if ($limit > 0) {
            $start = date('Y-m-d 00:00:00');
            $end = date('Y-m-d 00:00:00', strtotime('+1 day'));
            $count = (int) DB::val(
                'SELECT COUNT(*) FROM points_ledger WHERE user_id = ? AND rule_key = ? AND created_at >= ? AND created_at < ?',
                [$userId, $ruleKey, $start, $end]
            );
            if ($count >= $limit) {
                return 0;
            }
        }

        try {
            self::add($userId, $ruleKey, (int) $rule['points'], true, $refType, $refId, $note, null);
        } catch (\PDOException $e) {
            // Corrida entre duas requisições: a chave única impede o ponto duplicado.
            return 0;
        }
        return (int) $rule['points'];
    }

    /** Lançamento direto no extrato (sem checar regra). */
    public static function add(int $userId, string $ruleKey, int $points, bool $countsRanking, ?string $refType, ?int $refId, ?string $note, ?int $createdBy): int
    {
        return DB::insert(
            'INSERT INTO points_ledger (user_id, rule_key, points, counts_ranking, ref_type, ref_id, note, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$userId, $ruleKey, $points, $countsRanking ? 1 : 0, $refType, $refId, $note !== null ? mb_substr($note, 0, 200) : null, $createdBy, now()]
        );
    }

    /** Remove os pontos que um conteúdo moderado tinha rendido. */
    public static function reverseFor(string $refType, int $refId, string $ruleKey, ?int $adminId): void
    {
        $rows = DB::all(
            'SELECT user_id, points FROM points_ledger WHERE rule_key = ? AND ref_type = ? AND ref_id = ? AND points > 0',
            [$ruleKey, $refType, $refId]
        );
        foreach ($rows as $r) {
            $already = DB::val(
                'SELECT COUNT(*) FROM points_ledger WHERE user_id = ? AND rule_key = ? AND ref_type = ? AND ref_id = ?',
                [$r['user_id'], 'reversal', $refType . '_' . $ruleKey, $refId]
            );
            if (!$already) {
                self::add((int) $r['user_id'], 'reversal', -(int) $r['points'], true, $refType . '_' . $ruleKey, $refId, 'Conteúdo removido pela moderação', $adminId);
            }
        }
    }

    public static function balance(int $userId): int
    {
        return (int) DB::val('SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE user_id = ?', [$userId]);
    }

    /** Pontos ganhos desde sempre (base do nível). */
    public static function lifetime(int $userId): int
    {
        return max(0, (int) DB::val('SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE user_id = ? AND counts_ranking = 1', [$userId]));
    }

    public static function monthTotal(int $userId, ?string $ym = null): int
    {
        [$start, $end] = Ranking::bounds($ym);
        return (int) DB::val(
            'SELECT COALESCE(SUM(points), 0) FROM points_ledger WHERE user_id = ? AND counts_ranking = 1 AND created_at >= ? AND created_at < ?',
            [$userId, $start, $end]
        );
    }
}
