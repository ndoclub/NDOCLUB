<?php
namespace App\Core;

/**
 * Upload seguro de imagens (fotos) e PDFs (dieta).
 * - Tudo é salvo FORA da área pública (pasta storage/) e entregue por /media/...
 *   só para quem estiver logado.
 * - Imagens são recriadas (re-encodadas) em JPEG: isso remove dados ocultos
 *   como localização (EXIF) e qualquer código escondido no arquivo.
 */
final class Upload
{
    public static function dir(string $kind): string
    {
        return ROOT . '/storage/' . ($kind === 'pdf' ? 'private' : 'uploads');
    }

    /** Devolve o nome do arquivo salvo, ou null (e $error preenchido se houve problema). */
    public static function image(?array $file, int $maxSide, ?string &$error = null, string $kind = 'img'): ?string
    {
        $error = null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Não foi possível enviar a imagem. Tente uma foto menor.';
            return null;
        }
        $maxBytes = (int) config('upload.max_image_mb', 5) * 1024 * 1024;
        if ($file['size'] > $maxBytes) {
            $error = 'A imagem passa de ' . (int) config('upload.max_image_mb', 5) . ' MB. Escolha uma foto menor.';
            return null;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'Envio inválido.';
            return null;
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $error = 'Use uma imagem JPG, PNG ou WEBP.';
            return null;
        }
        if ($info[0] * $info[1] > 40000000) {
            $error = 'Imagem grande demais.';
            return null;
        }

        $name = bin2hex(random_bytes(16)) . '.jpg';
        $dest = self::dir($kind) . '/' . $name;

        if (!function_exists('imagecreatefromjpeg')) {
            // Sem a extensão GD: guarda o arquivo como veio (menos seguro, mas funciona).
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $error = 'Não foi possível salvar a imagem.';
                return null;
            }
            return $name;
        }

        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
            default        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file['tmp_name']) : false,
        };
        if (!$src) {
            $error = 'Não foi possível ler a imagem.';
            return null;
        }

        // Corrige a rotação de fotos tiradas no celular
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($file['tmp_name']);
            $orientation = $exif['Orientation'] ?? 1;
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $rot = imagerotate($src, $angle, 0);
                if ($rot) {
                    $src = $rot;
                }
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, $maxSide / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $saved = imagejpeg($dst, $dest, 85);
        imagedestroy($src);
        imagedestroy($dst);

        if (!$saved) {
            $error = 'Não foi possível salvar a imagem.';
            return null;
        }
        return $name;
    }

    /** PDF da dieta (somente admin envia). */
    public static function pdf(?array $file, ?string &$error = null): ?string
    {
        $error = null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Não foi possível enviar o PDF.';
            return null;
        }
        if ($file['size'] > (int) config('upload.max_pdf_mb', 10) * 1024 * 1024) {
            $error = 'O PDF passa de ' . (int) config('upload.max_pdf_mb', 10) . ' MB.';
            return null;
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $error = 'Envio inválido.';
            return null;
        }
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        $mime = function_exists('mime_content_type') ? mime_content_type($file['tmp_name']) : 'application/pdf';
        if ($head !== '%PDF-' || $mime !== 'application/pdf') {
            $error = 'O arquivo precisa ser um PDF.';
            return null;
        }
        $name = bin2hex(random_bytes(16)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], self::dir('pdf') . '/' . $name)) {
            $error = 'Não foi possível salvar o PDF.';
            return null;
        }
        return $name;
    }

    /**
     * Comprovante de pagamento (foto ou PDF). Vai para a pasta PRIVADA (storage/private) e só o admin
     * abre, pela rota /admin/parcelas/{id}/comprovante. Foto é recriada em JPEG (tira dados ocultos).
     */
    public static function proof(?array $file, ?string &$error = null): ?string
    {
        $error = null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'Não foi possível enviar o comprovante. Tente um arquivo menor.';
            return null;
        }
        $head = is_uploaded_file($file['tmp_name']) ? (string) file_get_contents($file['tmp_name'], false, null, 0, 5) : '';
        if ($head === '%PDF-') {
            return self::pdf($file, $error);
        }
        $name = self::image($file, 1600, $error, 'pdf');
        if ($name === null && $error !== null && str_starts_with($error, 'Use uma imagem')) {
            $error = 'O comprovante precisa ser uma foto (JPG, PNG ou WEBP) ou um PDF.';
        }
        return $name;
    }

    /** Apaga um comprovante da pasta privada (foto ou PDF). */
    public static function deleteProof(?string $name): void
    {
        self::delete($name, 'pdf');
    }

    public static function delete(?string $name, string $kind = 'img'): void
    {
        if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|pdf)$/', $name)) {
            $path = self::dir($kind) . '/' . $name;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
