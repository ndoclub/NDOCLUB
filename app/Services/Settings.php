<?php
namespace App\Services;

use App\Core\DB;

/** Configurações gerais guardadas na tabela "settings". */
final class Settings
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT skey, svalue FROM settings') as $row) {
                self::$cache[$row['skey']] = $row['svalue'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        return array_key_exists($key, $all) && $all[$key] !== null ? $all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        $exists = DB::val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
        if ($exists) {
            DB::run('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
        } else {
            DB::run('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
        }
        self::$cache = null;
    }
}
