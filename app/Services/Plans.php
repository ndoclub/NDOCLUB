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
        $v = DB::val(
            'SELECT COALESCE(b.price, p.price) FROM users u
               LEFT JOIN plans p ON p.id = u.plan_id
               LEFT JOIN patient_billing b ON b.user_id = u.id
              WHERE u.id = ?',
            [$userId]
        );
        return ($v === null || $v === false) ? null : (float) $v;
    }

    /** Valor combinado só deste paciente (admin), ou null. */
    public static function customPrice(int $userId): ?float
    {
        $v = DB::val('SELECT price FROM patient_billing WHERE user_id = ?', [$userId]);
        return ($v === null || $v === false) ? null : (float) $v;
    }

    /** Grava (ou apaga, se $price for null) o valor combinado do paciente. */
    public static function setCustomPrice(int $userId, ?float $price): void
    {
        $exists = (int) DB::val('SELECT COUNT(*) FROM patient_billing WHERE user_id = ?', [$userId]) > 0;
        if ($price === null) {
            DB::run('DELETE FROM patient_billing WHERE user_id = ?', [$userId]);
        } elseif ($exists) {
            DB::run('UPDATE patient_billing SET price = ? WHERE user_id = ?', [$price, $userId]);
        } else {
            DB::run('INSERT INTO patient_billing (user_id, price) VALUES (?, ?)', [$userId, $price]);
        }
    }

    /**
     * Total a renovar nos próximos $days dias (inclui quem já venceu), só pacientes ativos.
     * Retorna ['total' => float, 'count' => int, 'missing' => pacientes sem valor definido].
     */
    public static function dueWithin(int $days): array
    {
        $limit = date('Y-m-d', strtotime('+' . $days . ' day'));
        $row = DB::one(
            "SELECT COUNT(*) AS n,
                    COALESCE(SUM(COALESCE(b.price, p.price)), 0) AS total,
                    SUM(CASE WHEN COALESCE(b.price, p.price) IS NULL THEN 1 ELSE 0 END) AS missing
               FROM users u
               LEFT JOIN plans p ON p.id = u.plan_id
               LEFT JOIN patient_billing b ON b.user_id = u.id
              WHERE u.role = 'patient' AND u.status = 'active' AND u.renewal_date IS NOT NULL AND u.renewal_date <= ?",
            [$limit]
        );
        return ['total' => (float) ($row['total'] ?? 0), 'count' => (int) ($row['n'] ?? 0), 'missing' => (int) ($row['missing'] ?? 0)];
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
