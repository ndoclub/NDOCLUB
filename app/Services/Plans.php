<?php
namespace App\Services;

use App\Core\DB;

/**
 * Planos do acompanhamento (Trimestral, Semestral, Anual...).
 * IMPORTANTE: valores em R$ são só do painel admin. Para o app do paciente use
 * apenas name() (nunca devolva price/value para telas de paciente).
 */
final class Plans
{
    /** Nome do plano (seguro para o paciente ver). */
    public static function name(?int $planId): ?string
    {
        if (!$planId) {
            return null;
        }
        $n = DB::val('SELECT name FROM plans WHERE id = ?', [$planId]);
        return $n !== null && $n !== false ? (string) $n : null;
    }

    /** Planos ativos (admin). */
    public static function active(): array
    {
        return DB::all('SELECT * FROM plans WHERE active = 1 ORDER BY duration_days ASC, id ASC');
    }

    /** Todos os planos com a quantidade de pacientes ativos em cada um (admin). */
    public static function withCounts(): array
    {
        return DB::all(
            "SELECT p.*, (SELECT COUNT(*) FROM users u WHERE u.plan_id = p.id AND u.role = 'patient' AND u.status = 'active') AS patients
               FROM plans p ORDER BY p.active DESC, p.duration_days ASC, p.id ASC"
        );
    }

    /** Valor que vale para o paciente: o combinado, ou o do plano. null = sem valor definido (admin). */
    public static function priceFor(int $userId): ?float
    {
        return self::priceInfoFor($userId)['amount'];
    }

    /**
     * Valor e moeda do paciente (admin):
     *  amount   = valor combinado do paciente, ou o do plano (null = sem valor definido)
     *  currency = BRL, USD ou EUR (o valor do plano é sempre em R$)
     *  source   = 'manual' (combinado), 'reajuste' (valor antigo mantido até renovar) ou 'plan' (valor do plano)
     *  planPrice = valor atual do plano (R$), mesmo quando o paciente tem valor próprio
     */
    public static function priceInfoFor(int $userId): array
    {
        $r = DB::one(
            'SELECT b.price AS custom, b.currency AS bcur, b.source AS bsrc, p.price AS plan_price
               FROM users u
               LEFT JOIN plans p ON p.id = u.plan_id
               LEFT JOIN patient_billing b ON b.user_id = u.id
              WHERE u.id = ?',
            [$userId]
        );
        $planPrice = ($r && $r['plan_price'] !== null) ? (float) $r['plan_price'] : null;
        if ($r && $r['custom'] !== null) {
            return ['amount' => (float) $r['custom'], 'currency' => currency_or_brl($r['bcur']), 'source' => $r['bsrc'] === 'reajuste' ? 'reajuste' : 'manual', 'planPrice' => $planPrice];
        }
        return ['amount' => $planPrice, 'currency' => 'BRL', 'source' => 'plan', 'planPrice' => $planPrice];
    }

    /**
     * Valor sugerido na próxima renovação: o valor combinado do paciente; se ele só estava mantendo
     * o valor antigo (reajuste), o novo valor do plano.
     */
    public static function renewalSuggestion(int $userId): array
    {
        $i = self::priceInfoFor($userId);
        if ($i['source'] === 'reajuste') {
            return ['amount' => $i['planPrice'], 'currency' => 'BRL'];
        }
        return ['amount' => $i['amount'], 'currency' => $i['currency']];
    }

    /** Valor combinado só deste paciente (admin), ou null. */
    public static function customPrice(int $userId): ?float
    {
        $v = DB::val('SELECT price FROM patient_billing WHERE user_id = ?', [$userId]);
        return ($v === null || $v === false) ? null : (float) $v;
    }

    /**
     * Grava (ou apaga, se $price for null) o valor combinado do paciente.
     * $source null = mantém a origem se valor e moeda não mudaram; se mudaram, vira 'manual'.
     */
    public static function setCustomPrice(int $userId, ?float $price, string $currency = 'BRL', ?string $source = null): void
    {
        $currency = currency_or_brl($currency);
        $row = DB::one('SELECT price, currency, source FROM patient_billing WHERE user_id = ?', [$userId]);
        if ($price === null) {
            DB::run('DELETE FROM patient_billing WHERE user_id = ?', [$userId]);
            return;
        }
        if ($row) {
            if ($source === null) {
                $same = abs((float) $row['price'] - $price) < 0.005 && $row['currency'] === $currency;
                $source = $same ? ($row['source'] === 'reajuste' ? 'reajuste' : 'manual') : 'manual';
            }
            DB::run('UPDATE patient_billing SET price = ?, currency = ?, source = ? WHERE user_id = ?', [$price, $currency, $source, $userId]);
        } else {
            DB::run('INSERT INTO patient_billing (user_id, price, currency, source) VALUES (?, ?, ?, ?)', [$userId, $price, $currency, $source ?? 'manual']);
        }
    }

    /** Depois de renovar, o paciente passa para o valor atual do plano (se estava só mantendo o valor antigo). */
    public static function clearReajuste(int $userId): void
    {
        DB::run("DELETE FROM patient_billing WHERE user_id = ? AND source = 'reajuste'", [$userId]);
    }

    /**
     * Reajuste seguro: quando o valor de um plano muda, quem já está no plano mantém o valor antigo
     * até a próxima renovação. Guarda o valor antigo para os pacientes ATIVOS desse plano que ainda
     * não têm valor combinado. Retorna quantos pacientes foram protegidos.
     */
    public static function protectOnPriceChange(int $planId, ?float $oldPrice, ?float $newPrice): int
    {
        if ($oldPrice === null || $newPrice === null || abs($oldPrice - $newPrice) < 0.005) {
            return 0;
        }
        $ids = DB::all(
            "SELECT u.id FROM users u LEFT JOIN patient_billing b ON b.user_id = u.id
              WHERE u.plan_id = ? AND u.role = 'patient' AND u.status = 'active' AND b.user_id IS NULL",
            [$planId]
        );
        return DB::tx(function () use ($ids, $oldPrice) {
            foreach ($ids as $row) {
                DB::run(
                    "INSERT INTO patient_billing (user_id, price, currency, source) VALUES (?, ?, 'BRL', 'reajuste')",
                    [(int) $row['id'], $oldPrice]
                );
            }
            return count($ids);
        });
    }

    /**
     * Total a renovar nos próximos $days dias (inclui quem já venceu), só pacientes ativos.
     * O valor previsto é o que será cobrado na renovação (valor combinado, ou o valor atual do plano).
     * Retorna:
     *   total   = soma em R$ (compatível com o painel)
     *   by      = soma por moeda [BRL => .., USD => .., EUR => ..]
     *   count   = quantos pacientes
     *   missing = quantos sem valor definido
     */
    public static function dueWithin(int $days): array
    {
        $limit = date('Y-m-d', strtotime('+' . $days . ' day'));
        $rows = DB::all(
            "SELECT CASE WHEN b.price IS NOT NULL AND b.source <> 'reajuste' THEN b.currency ELSE 'BRL' END AS cur,
                    CASE WHEN b.price IS NOT NULL AND b.source <> 'reajuste' THEN b.price ELSE p.price END AS val
               FROM users u
               LEFT JOIN plans p ON p.id = u.plan_id
               LEFT JOIN patient_billing b ON b.user_id = u.id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date IS NOT NULL AND u.renewal_date <= ?",
            [$limit]
        );
        $by = [];
        $missing = 0;
        foreach ($rows as $r) {
            if ($r['val'] === null) {
                $missing++;
                continue;
            }
            $by[$r['cur']] = ($by[$r['cur']] ?? 0) + (float) $r['val'];
        }
        return ['total' => (float) ($by['BRL'] ?? 0), 'by' => $by, 'count' => count($rows), 'missing' => $missing];
    }

    /**
     * Valor aproximado por mês de um plano (preço ÷ meses de duração). Quem pensa em "3, 6 ou 12 meses"
     * vê a conta redonda (90 dias = 3 meses, 365 dias = 12 meses); durações quebradas usam meses de 30,4 dias.
     */
    public static function perMonth(?float $price, int $durationDays): ?float
    {
        if ($price === null || $durationDays <= 0) {
            return null;
        }
        $months = $durationDays / 30.4375;
        if (abs($months - round($months)) < 0.1 && round($months) >= 1) {
            $months = (float) round($months);
        }
        return round($price / $months, 2);
    }

    /** Durações aceitas na renovação: as padrão + as dos planos ativos. */
    public static function renewalChoices(): array
    {
        $days = [30, 60, 90, 180, 365];
        foreach (self::active() as $p) {
            $days[] = (int) $p['duration_days'];
        }
        $days = array_values(array_unique($days));
        sort($days);
        return $days;
    }
}
