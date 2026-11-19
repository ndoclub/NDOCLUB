<?php
namespace App\Core;

use PDO;

/**
 * Acesso ao banco com PDO. Todas as consultas usam parâmetros (?),
 * o que protege contra SQL injection.
 */
final class DB
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            if (($c['driver'] ?? 'mysql') === 'sqlite') {
                $pdo = new PDO('sqlite:' . $c['path']);
                $pdo->exec('PRAGMA foreign_keys = ON');
            } else {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $c['host'],
                    $c['port'] ?? 3306,
                    $c['name'],
                    $c['charset'] ?? 'utf8mb4'
                );
                $pdo = new PDO($dsn, $c['user'], $c['pass']);
            }
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$pdo = $pdo;
        }
        return self::$pdo;
    }

    /** Várias linhas. */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Uma linha (ou null). */
    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    /** Um único valor (primeira coluna da primeira linha). */
    public static function val(string $sql, array $params = [])
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    /** INSERT/UPDATE/DELETE. Retorna linhas afetadas. */
    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** INSERT que devolve o id criado. */
    public static function insert(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return (int) self::pdo()->lastInsertId();
    }

    /** Executa várias operações como uma só (tudo ou nada). */
    public static function tx(callable $fn)
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Monta "?,?,?" para cláusulas IN. */
    public static function marks(array $values): string
    {
        return implode(',', array_fill(0, count($values), '?'));
    }
}
