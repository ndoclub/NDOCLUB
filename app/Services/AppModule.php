<?php
namespace App\Services;

use App\Core\DB;

/**
 * Módulo "App do paciente" no painel (Atualização 09.5).
 * Reúne os números do uso do app que antes ficavam espalhados pelo Painel e pela Visão executiva.
 * As definições são as mesmas dos filtros da lista de pacientes (Pacientes > ?f=away e ?f=noapp):
 *  - sumido = paciente ativo, com acesso liberado, que não abre o app há 7 dias ou mais (ou nunca abriu);
 *  - sem acesso = paciente que veio de importação e ainda não recebeu a senha provisória (app_access = 0).
 * Nada aqui grava dado: só lê.
 */
final class AppModule
{
    /** O que está esperando a equipe no app: resgates pendentes e denúncias abertas (vira o número vermelho do menu). */
    public static function pending(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = ['redemptions' => 0, 'reports' => 0, 'total' => 0];
        try {
            $cache['redemptions'] = (int) DB::val("SELECT COUNT(*) FROM redemptions WHERE status = 'pending'");
            $cache['reports'] = (int) DB::val("SELECT COUNT(*) FROM reports WHERE status = 'open'");
            $cache['total'] = $cache['redemptions'] + $cache['reports'];
        } catch (\Throwable $e) {
            // sem as tabelas do app: o menu simplesmente não mostra o número
        }
        return $cache;
    }

    /** Números da página "Visão geral" do módulo. */
    public static function overview(): array
    {
        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 day'));
        $today = today();
        $yellow = (int) Settings::get('warn_yellow_days', 15);
        $c = fn(string $sql, array $p = []) => (int) DB::val($sql, $p);
        $base = "role = 'patient' AND status = 'active'";
        $p = self::pending();
        return [
            'withAccess' => $c("SELECT COUNT(*) FROM users WHERE $base AND app_access = 1"),
            'noAccess' => $c("SELECT COUNT(*) FROM users WHERE $base AND app_access = 0"),
            'active7' => $c("SELECT COUNT(*) FROM users WHERE $base AND app_access = 1 AND last_seen_at >= ?", [$weekAgo]),
            'away' => $c("SELECT COUNT(*) FROM users WHERE $base AND app_access = 1 AND (last_seen_at IS NULL OR last_seen_at < ?)", [$weekAgo]),
            // renovação em risco = vence dentro da janela do selo amarelo E está sumido do app
            'risk' => $c(
                "SELECT COUNT(*) FROM users WHERE $base AND app_access = 1 AND renewal_date >= ? AND renewal_date <= ? AND (last_seen_at IS NULL OR last_seen_at < ?)",
                [$today, date('Y-m-d', strtotime($today . ' +' . $yellow . ' day')), $weekAgo]
            ),
            'checkins' => $c('SELECT COUNT(*) FROM checkins WHERE checkin_date = ?', [$today]),
            'redemptions' => $p['redemptions'],
            'reports' => $p['reports'],
            'yellow' => $yellow,
        ];
    }
}
