<?php
namespace App\Services;

use App\Core\DB;

/** Registro de ações importantes feitas pelo administrador. */
final class Audit
{
    public static function log(?int $adminId, string $action, ?string $targetType = null, ?int $targetId = null, ?string $detail = null): void
    {
        DB::run(
            'INSERT INTO audit_log (admin_id, action, target_type, target_id, detail, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$adminId, $action, $targetType, $targetId, $detail !== null ? mb_substr($detail, 0, 300) : null, now()]
        );
    }
}
