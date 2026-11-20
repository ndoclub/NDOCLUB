<?php
namespace App\Services;

use App\Core\DB;

/**
 * Responsáveis pelos pacientes ("modo babá": cada estagiária cuida de alguns pacientes).
 * Por enquanto eles NÃO têm login: é só uma etiqueta que o admin define e usa para filtrar a lista.
 * Não são apagados, só desativados. O paciente nunca vê isso.
 */
final class Responsibles
{
    /** Todos, com quantos pacientes ativos cada um tem. */
    public static function withCounts(): array
    {
        return DB::all(
            "SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.responsible_id = r.id AND u.role = 'patient' AND u.status = 'active') AS patients
               FROM responsibles r ORDER BY r.active DESC, r.name ASC"
        );
    }

    /** Ativos (para escolher). $keepId = mantém também o responsável atual do paciente, mesmo se desativado. */
    public static function choices(?int $keepId = null): array
    {
        return DB::all(
            'SELECT id, name, active FROM responsibles WHERE active = 1 OR id = ? ORDER BY active DESC, name ASC',
            [(int) $keepId]
        );
    }

    /** Todos (para o filtro da lista, inclusive desativados). */
    public static function all(): array
    {
        return DB::all('SELECT id, name, active FROM responsibles ORDER BY active DESC, name ASC');
    }

    /** O id é de um responsável existente? */
    public static function exists(int $id): bool
    {
        return $id > 0 && (bool) DB::val('SELECT id FROM responsibles WHERE id = ?', [$id]);
    }

    /** Responsável ativo (só ele pode receber pacientes novos). */
    public static function isActive(int $id): bool
    {
        return $id > 0 && (bool) DB::val('SELECT id FROM responsibles WHERE id = ? AND active = 1', [$id]);
    }

    /** Define o responsável de vários pacientes de uma vez (0 = tirar o responsável). Retorna quantos mudaram. */
    public static function assign(array $userIds, int $responsibleId): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($i) => $i > 0)));
        if (!$ids) {
            return 0;
        }
        return DB::tx(function () use ($ids, $responsibleId) {
            $n = 0;
            foreach ($ids as $id) {
                $n += DB::run(
                    "UPDATE users SET responsible_id = ? WHERE id = ? AND role = 'patient'",
                    [$responsibleId > 0 ? $responsibleId : null, $id]
                );
            }
            return $n;
        });
    }
}
