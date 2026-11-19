<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;

/** Entrega arquivos guardados fora da área pública, só para quem está logado. */
final class MediaController
{
    public function image(string $file): void
    {
        if (!preg_match('/^[a-f0-9]{32}\.jpg$/', $file)) {
            $this->notFound();
        }
        $path = Upload::dir('img') . '/' . $file;
        if (!is_file($path)) {
            $this->notFound();
        }
        session_write_close();
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, max-age=86400');
        readfile($path);
        exit;
    }

    public function diet(string $file): void
    {
        if (!preg_match('/^[a-f0-9]{32}\.pdf$/', $file)) {
            $this->notFound();
        }
        $diet = DB::one('SELECT user_id FROM diets WHERE pdf_file = ?', [$file]);
        $u = Auth::user();
        if (!$diet || (!Auth::isAdmin() && (int) $diet['user_id'] !== (int) $u['id'])) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
        $path = Upload::dir('pdf') . '/' . $file;
        if (!is_file($path)) {
            $this->notFound();
        }
        session_write_close();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="dieta.pdf"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo 'Arquivo não encontrado.';
        exit;
    }
}
