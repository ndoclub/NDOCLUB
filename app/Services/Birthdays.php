<?php
namespace App\Services;

use App\Core\DB;

/**
 * Aniversariantes (só para o painel do admin). A data de nascimento nunca aparece para pacientes.
 * Entram os pacientes com plano em dia ou sem data de vencimento; quem já saiu (plano vencido) ou
 * está desativado não entra. Tudo é calculado no PHP (funciona igual no MySQL e no SQLite dos testes).
 */
final class Birthdays
{
    public const DEFAULT_MESSAGE = 'Feliz aniversário, {nome}! 🎉 Que este novo ciclo venha com muita saúde, energia e conquistas. Conte comigo e com a equipe do No Days Off Club. Um abraço!';

    /** Janelas de tempo da tela: chave => rótulo. */
    public static function windows(): array
    {
        return ['hoje' => 'Hoje', '7' => 'Próximos 7 dias', '30' => 'Próximos 30 dias', 'mes' => 'Este mês', 'prox' => 'Mês que vem'];
    }

    public static function windowKey(?string $k): string
    {
        return array_key_exists((string) $k, self::windows()) ? (string) $k : '7';
    }

    /** [início, fim] (AAAA-MM-DD) da janela. */
    public static function range(string $key, ?string $today = null): array
    {
        $today = $today ?: today();
        switch ($key) {
            case 'hoje':
                return [$today, $today];
            case '30':
                return [$today, date('Y-m-d', strtotime($today . ' +30 day'))];
            case 'mes':
                return [date('Y-m-01', strtotime($today)), date('Y-m-t', strtotime($today))];
            case 'prox':
                $first = date('Y-m-d', strtotime(date('Y-m-01', strtotime($today)) . ' +1 month'));
                return [$first, date('Y-m-t', strtotime($first))];
            default: // '7'
                return [$today, date('Y-m-d', strtotime($today . ' +7 day'))];
        }
    }

    /** Dia do aniversário em um ano. Quem nasceu em 29/02 comemora em 28/02 nos anos que não são bissextos. */
    public static function occurrence(string $birth, int $year): string
    {
        $m = (int) substr($birth, 5, 2);
        $d = (int) substr($birth, 8, 2);
        if ($m === 2 && $d === 29 && !checkdate(2, 29, $year)) {
            $d = 28;
        }
        return sprintf('%04d-%02d-%02d', $year, $m, $d);
    }

    /**
     * Aniversariantes entre duas datas (inclusive), em ordem de data e de nome.
     * Cada linha: id, name, phone, avatar, birth_date, msg_optin, date (dia do aniversário na janela),
     * days (a partir de hoje; negativo se já passou no mês), age (idade que faz).
     */
    public static function between(string $from, string $to, ?string $today = null): array
    {
        $today = $today ?: today();
        $rows = DB::all(
            "SELECT id, name, phone, avatar, birth_date, msg_optin, renewal_date
               FROM users
              WHERE role = 'patient' AND status = 'active' AND birth_date IS NOT NULL
                AND (renewal_date IS NULL OR renewal_date >= ?)",
            [$today]
        );
        $y0 = (int) substr($from, 0, 4);
        $y1 = (int) substr($to, 0, 4);
        $out = [];
        foreach ($rows as $r) {
            $birth = substr((string) $r['birth_date'], 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                continue;
            }
            for ($y = $y0; $y <= $y1; $y++) {
                $occ = self::occurrence($birth, $y);
                if ($occ >= $from && $occ <= $to) {
                    $r['date'] = $occ;
                    $r['days'] = (int) round((strtotime($occ) - strtotime($today)) / 86400);
                    $r['age'] = $y - (int) substr($birth, 0, 4);
                    $out[] = $r;
                    break;
                }
            }
        }
        usort($out, fn($a, $b) => [$a['date'], mb_strtolower($a['name'])] <=> [$b['date'], mb_strtolower($b['name'])]);
        return $out;
    }

    public static function forWindow(string $key, ?string $today = null): array
    {
        [$from, $to] = self::range($key, $today);
        return self::between($from, $to, $today);
    }

    public static function todayCount(?string $today = null): int
    {
        $today = $today ?: today();
        return count(self::between($today, $today, $today));
    }

    /** Pacientes em dia (ou sem data de vencimento) que ainda não têm data de nascimento. */
    public static function missingCount(?string $today = null): int
    {
        return (int) DB::val(
            "SELECT COUNT(*) FROM users WHERE role = 'patient' AND status = 'active' AND birth_date IS NULL AND (renewal_date IS NULL OR renewal_date >= ?)",
            [$today ?: today()]
        );
    }

    /** Texto da mensagem (editável em Configurações). {nome} vira o primeiro nome. */
    public static function message(string $name): string
    {
        $tpl = trim((string) Settings::get('birthday_message', ''));
        if ($tpl === '') {
            $tpl = self::DEFAULT_MESSAGE;
        }
        return str_replace('{nome}', first_name($name), $tpl);
    }

    /** Link do WhatsApp com a mensagem pronta (null se o telefone não serve). */
    public static function whatsappUrl(?string $phone, string $name): ?string
    {
        return wa_phone_url($phone, self::message($name));
    }
}
