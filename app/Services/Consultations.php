<?php
namespace App\Services;

use App\Core\DB;

/**
 * Consultas presenciais realizadas. Cada plano tem uma cota (plans.consultations_included).
 * O contador "x de y" vale para o plano atual: cada consulta guarda o "cycle" (quantas renovações o
 * paciente tinha quando ela foi registrada). Ao renovar, o ciclo muda e o contador recomeça do zero;
 * as consultas antigas continuam no histórico. Só o admin vê isso.
 */
final class Consultations
{
    /** Trecho de SQL: consultas do plano atual do paciente (usa o alias "u" para users). */
    public const DONE_SQL = '(SELECT COUNT(*) FROM consultations c WHERE c.user_id = u.id
                               AND c.cycle = (SELECT COUNT(*) FROM renewals rn WHERE rn.user_id = u.id))';

    /** Ciclo atual do paciente = quantas renovações ele já teve. */
    public static function cycle(int $userId): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM renewals WHERE user_id = ?', [$userId]);
    }

    /** Consultas feitas no plano atual. */
    public static function done(int $userId): int
    {
        return (int) DB::val(
            'SELECT COUNT(*) FROM consultations WHERE user_id = ? AND cycle = ?',
            [$userId, self::cycle($userId)]
        );
    }

    /** Cota do plano (null = não definida). */
    public static function quota(?int $planId): ?int
    {
        if (!$planId) {
            return null;
        }
        $q = DB::val('SELECT consultations_included FROM plans WHERE id = ?', [$planId]);
        return ($q === null || $q === false) ? null : (int) $q;
    }

    /**
     * Resumo para a ficha e a lista: ['done' => 2, 'quota' => 4|null, 'left' => 2|null, 'full' => bool].
     */
    public static function summary(int $done, $quota): array
    {
        $quota = ($quota === null || $quota === '' || $quota === false) ? null : (int) $quota;
        return [
            'done' => $done,
            'quota' => $quota,
            'left' => $quota === null ? null : max(0, $quota - $done),
            'full' => $quota !== null && $done >= $quota,
        ];
    }

    /** Histórico do paciente (mais recentes primeiro). 'current' = é do plano atual. */
    public static function forUser(int $userId, int $limit = 30): array
    {
        $cycle = self::cycle($userId);
        $rows = DB::all(
            'SELECT id, held_on, cycle, note, created_at FROM consultations WHERE user_id = ?
              ORDER BY held_on DESC, id DESC LIMIT ' . max(1, $limit),
            [$userId]
        );
        foreach ($rows as &$r) {
            $r['current'] = (int) $r['cycle'] === $cycle;
        }
        return $rows;
    }

    /** Total de consultas já feitas na vida do paciente (todos os planos). */
    public static function total(int $userId): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM consultations WHERE user_id = ?', [$userId]);
    }

    /**
     * Registra $qty consultas (1 a 20) feitas em $heldOn (AAAA-MM-DD, não pode ser futura).
     * Retorna null se deu certo, ou o texto do erro.
     */
    public static function add(int $userId, string $heldOn, string $note, int $qty, ?int $adminId): ?string
    {
        if (!Payments::validDate($heldOn)) {
            return 'Data da consulta inválida.';
        }
        if ($heldOn > today()) {
            return 'A consulta ainda não aconteceu. Registre depois de realizada.';
        }
        if ($qty < 1 || $qty > 20) {
            return 'Quantidade inválida (use de 1 a 20).';
        }
        $note = trim($note);
        if (mb_strlen($note) > 160) {
            return 'A observação pode ter até 160 letras.';
        }
        DB::tx(function () use ($userId, $heldOn, $note, $qty, $adminId) {
            $cycle = self::cycle($userId);
            for ($i = 0; $i < $qty; $i++) {
                DB::run(
                    'INSERT INTO consultations (user_id, held_on, cycle, note, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$userId, $heldOn, $cycle, $note !== '' ? $note : null, $adminId, now()]
                );
            }
        });
        return null;
    }

    /** Apaga uma consulta (engano de registro). Retorna o paciente dono, ou null se não existe. */
    public static function remove(int $id): ?array
    {
        $row = DB::one('SELECT id, user_id, held_on FROM consultations WHERE id = ?', [$id]);
        if (!$row) {
            return null;
        }
        DB::run('DELETE FROM consultations WHERE id = ?', [$id]);
        return $row;
    }
}
