<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\Audit;
use App\Services\ImportTemplate;
use App\Services\PatientImport;
use RuntimeException;

/**
 * Importação de pacientes por planilha (só admin).
 * Passo a passo: baixar o modelo -> preencher -> enviar -> PRÉVIA (nada é salvo) -> confirmar -> resultado.
 * A planilha enviada fica só na sessão do admin (não é guardada em disco) e a prévia é refeita a cada tela,
 * com os dados atuais do sistema.
 */
final class ImportController
{
    private const KEY = '_import';
    private const RESULT = '_import_result';
    private const TTL = 1800; // a prévia vale 30 minutos

    /** Prévia que está esperando confirmação (ou null). */
    private function pending(): ?array
    {
        $p = $_SESSION[self::KEY] ?? null;
        if (!is_array($p) || empty($p['rows']) || (time() - (int) ($p['at'] ?? 0)) > self::TTL) {
            unset($_SESSION[self::KEY]);
            return null;
        }
        return $p;
    }

    public function index(): void
    {
        $p = $this->pending();
        View::render('admin/import/index', [
            'title' => 'Importar pacientes',
            'columns' => PatientImport::columns(),
            'lists' => PatientImport::lists(),
            'pending' => $p ? ['file' => (string) $p['file'], 'count' => count($p['rows'])] : null,
            'maxRows' => PatientImport::MAX_ROWS,
        ], 'admin');
    }

    /** Baixar o modelo oficial (.xlsx). A aba Listas já vem com os planos, modalidades e responsáveis de hoje. */
    public function template(): void
    {
        try {
            $bytes = ImportTemplate::build();
        } catch (RuntimeException $e) {
            flash('err', $e->getMessage());
            redirect('/admin/importar');
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="modelo-importacao-pacientes-NDO.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store');
        echo $bytes;
        exit;
    }

    /** Recebe a planilha, lê e guarda na sessão. NÃO salva nada no banco. */
    public function upload(): void
    {
        $f = $_FILES['arquivo'] ?? null;
        $err = is_array($f) ? (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($err !== UPLOAD_ERR_OK) {
            flash('err', match ($err) {
                UPLOAD_ERR_NO_FILE => 'Escolha o arquivo da planilha antes de validar.',
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'O arquivo é grande demais (o limite é 2 MB).',
                default => 'Não consegui receber o arquivo. Tente de novo.',
            });
            redirect('/admin/importar');
        }
        if (!is_uploaded_file((string) $f['tmp_name'])) {
            flash('err', 'Arquivo inválido.');
            redirect('/admin/importar');
        }
        $orig = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '', (string) $f['name']) ?? '';
        $orig = mb_substr($orig, 0, 80);
        try {
            $table = PatientImport::readFile((string) $f['tmp_name'], $orig);
        } catch (RuntimeException $e) {
            flash('err', $e->getMessage());
            redirect('/admin/importar');
        }
        $_SESSION[self::KEY] = [
            'token' => bin2hex(random_bytes(8)),
            'file' => $orig,
            'at' => time(),
            'sheet' => $table['sheet'],
            'ignored' => $table['ignored'],
            'rows' => $table['rows'],
        ];
        unset($_SESSION[self::RESULT]);
        redirect('/admin/importar/previa');
    }

    /** Prévia: cada linha com Pronto / Aviso / Duplicado / Erro. */
    public function preview(): void
    {
        $p = $this->pending();
        if (!$p) {
            flash('err', 'Envie uma planilha para ver a prévia.');
            redirect('/admin/importar');
        }
        $val = PatientImport::validate($p['rows']);
        $only = (string) ($_GET['v'] ?? '');
        if (!in_array($only, ['ok', 'warn', 'dup', 'err', 'problems'], true)) {
            $only = '';
        }
        $list = array_values(array_filter($val['rows'], function ($r) use ($only) {
            return match ($only) {
                'ok', 'warn', 'dup', 'err' => $r['status'] === $only,
                'problems' => $r['status'] !== 'ok',
                default => true,
            };
        }));
        View::render('admin/import/preview', [
            'title' => 'Prévia da importação',
            'file' => (string) $p['file'],
            'sheet' => (string) $p['sheet'],
            'ignored' => (array) $p['ignored'],
            'summary' => $val['summary'],
            'rows' => $list,
            'only' => $only,
            'labels' => PatientImport::statusLabels(),
        ], 'admin');
    }

    /** Relatório (CSV) das linhas que não estão "Pronto", para corrigir a planilha. */
    public function problems(): void
    {
        $p = $this->pending();
        if (!$p) {
            flash('err', 'Envie uma planilha primeiro.');
            redirect('/admin/importar');
        }
        $val = PatientImport::validate($p['rows']);
        $labels = PatientImport::statusLabels();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="problemas-importacao.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // o Excel reconhece os acentos
        fputcsv($out, ['Linha na planilha', 'Situação', 'Paciente', 'E-mail', 'O que fazer / problema'], ';', '"', '');
        foreach ($val['rows'] as $r) {
            if ($r['status'] === 'ok') {
                continue;
            }
            $msgs = array_merge($r['dups'], $r['errors'], $r['warnings']);
            fputcsv($out, [
                $r['line'],
                $labels[$r['status']],
                PatientImport::csvSafe((string) $r['view']['name']),
                PatientImport::csvSafe((string) $r['view']['email']),
                PatientImport::csvSafe(implode(' | ', $msgs)),
            ], ';', '"', '');
        }
        fclose($out);
        exit;
    }

    /** Confirmou: salva só as linhas Pronto/Aviso. Duplicadas e com erro ficam de fora. */
    public function confirm(): void
    {
        $p = $this->pending();
        if (!$p) {
            flash('err', 'A prévia expirou ou já foi importada. Envie a planilha de novo.');
            redirect('/admin/importar');
        }
        if (!hash_equals((string) $p['token'], (string) ($_POST['token'] ?? ''))) {
            flash('err', 'Esta prévia não é mais a atual. Confira de novo.');
            redirect('/admin/importar/previa');
        }
        $val = PatientImport::validate($p['rows']); // refaz a validação com os dados de agora
        $s = $val['summary'];
        if ($s['importable'] === 0) {
            flash('err', 'Não há nenhuma linha pronta para importar. Corrija a planilha e envie de novo.');
            redirect('/admin/importar/previa');
        }
        if ($s['warn'] > 0 && empty($_POST['ack'])) {
            flash('err', 'Marque "Conferi os avisos" para importar as linhas com aviso.');
            redirect('/admin/importar/previa');
        }

        // Tira a prévia da sessão ANTES de salvar: um segundo clique não importa tudo de novo
        unset($_SESSION[self::KEY]);
        @set_time_limit(120);
        $res = PatientImport::commit($val, (int) Auth::id());

        $n = count($res['created']);
        Audit::log(
            (int) Auth::id(),
            'patient_import',
            'import',
            null,
            $p['file'] . ' · ' . $n . ' importado(s), ' . $s['dup'] . ' duplicado(s), ' . $s['err'] . ' com erro, ' . count($res['failed']) . ' falha(s)'
        );
        $_SESSION[self::RESULT] = [
            'file' => $p['file'],
            'at' => time(),
            'created' => $res['created'],
            'failed' => $res['failed'],
            'dup' => $s['dup'],
            'err' => $s['err'],
            'total' => $s['total'],
        ];
        redirect('/admin/importar/resultado');
    }

    public function discard(): void
    {
        unset($_SESSION[self::KEY]);
        flash('ok', 'Prévia descartada. Nada foi salvo.');
        redirect('/admin/importar');
    }

    public function result(): void
    {
        $r = $_SESSION[self::RESULT] ?? null;
        if (!is_array($r)) {
            redirect('/admin/importar');
        }
        View::render('admin/import/result', ['title' => 'Importação concluída', 'r' => $r], 'admin');
    }
}
