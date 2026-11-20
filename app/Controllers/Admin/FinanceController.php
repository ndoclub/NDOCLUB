<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Upload;
use App\Core\View;
use App\Services\Audit;
use App\Services\Payments;

/**
 * Financeiro (só admin): vendido no mês, o que já caiu, o que falta cair, Pix para cobrar e taxas do cartão.
 * O paciente nunca acessa nem vê nada daqui.
 */
final class FinanceController
{
    public function index(): void
    {
        Payments::syncCards(); // cartão em R$ que já venceu cai sozinho
        $ym = Payments::normalizeMonth($_GET['m'] ?? null);
        $filter = (string) ($_GET['f'] ?? 'month');
        if (!in_array($filter, ['month', 'pending', 'received', 'late'], true)) {
            $filter = 'month';
        }
        $first = $ym . '-01';
        $mn = month_name((int) date('n', strtotime($first)));
        View::render('admin/finance/index', [
            'title' => 'Financeiro',
            'ym' => $ym,
            'filter' => $filter,
            'monthLabel' => mb_strtoupper(mb_substr($mn, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($mn, 1, null, 'UTF-8') . ' de ' . date('Y', strtotime($first)),
            'prevYm' => date('Y-m', strtotime($first . ' -1 month')),
            'nextYm' => date('Y-m', strtotime($first . ' +1 month')),
            'isCurrent' => $ym === date('Y-m'),
            'summary' => Payments::monthSummary($ym),
            'attention' => Payments::attention(),
            'receivables' => Payments::receivables(12),
            'rows' => Payments::installments($ym, $filter),
        ], 'admin');
    }

    /** Lê o comprovante enviado (opcional). Retorna o nome do arquivo, null (sem arquivo) ou false (erro já avisado). */
    private function readProof(string $back): string|false|null
    {
        $file = $_FILES['proof'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $name = Upload::proof($file, $err);
        if ($err) {
            flash('err', $err);
            redirect($back);
        }
        return $name;
    }

    /** "Recebi": marca a parcela como recebida (em USD/EUR, informa quanto caiu em reais) e guarda o comprovante, se enviado. */
    public function receive(int $id): void
    {
        $inst = Payments::installment($id);
        $back = safe_back($_POST['back'] ?? null, '/admin/financeiro');
        if (!$inst) {
            flash('err', 'Parcela não encontrada.');
            redirect($back);
        }
        $brl = parse_money((string) ($_POST['received_brl'] ?? ''));
        if ($brl === false) {
            flash('err', 'Valor em reais inválido. Use números, por exemplo 4950,00.');
            redirect($back);
        }
        $proof = $this->readProof($back);
        $err = Payments::receive($id, trim((string) ($_POST['received_on'] ?? '')) ?: null, $brl);
        if ($err) {
            if (is_string($proof)) {
                Upload::deleteProof($proof);
            }
            flash('err', $err);
            redirect($back);
        }
        if (is_string($proof)) {
            Payments::setProof($id, $proof);
        }
        Audit::log((int) Auth::id(), 'installment_received', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number'] . ' · ' . money((float) $inst['amount'], 2, $inst['currency']) . ($brl !== null && $inst['currency'] !== 'BRL' ? ' → ' . money($brl) : '') . (is_string($proof) ? ' · com comprovante' : ''));
        flash('ok', 'Parcela marcada como recebida' . (is_string($proof) ? ', com o comprovante.' : '.'));
        redirect($back);
    }

    /** Anexa (ou troca) o comprovante de uma parcela. */
    public function attach(int $id): void
    {
        $inst = Payments::installment($id);
        $back = safe_back($_POST['back'] ?? null, '/admin/financeiro');
        if (!$inst) {
            flash('err', 'Parcela não encontrada.');
            redirect($back);
        }
        $proof = $this->readProof($back);
        if (!is_string($proof)) {
            flash('err', 'Escolha a foto ou o PDF do comprovante.');
            redirect($back);
        }
        Payments::setProof($id, $proof);
        Audit::log((int) Auth::id(), 'installment_proof', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number']);
        flash('ok', 'Comprovante anexado.');
        redirect($back);
    }

    /** Abre o comprovante (só admin; o arquivo fica fora da área pública). */
    public function proof(int $id): void
    {
        $inst = Payments::installment($id);
        $file = $inst['proof_file'] ?? null;
        if (!$file || !preg_match('/^[a-f0-9]{32}\.(jpg|pdf)$/', (string) $file)) {
            http_response_code(404);
            echo 'Comprovante não encontrado.';
            exit;
        }
        $path = Upload::dir('pdf') . '/' . $file;
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Comprovante não encontrado.';
            exit;
        }
        $pdf = str_ends_with($file, '.pdf');
        session_write_close();
        header('Content-Type: ' . ($pdf ? 'application/pdf' : 'image/jpeg'));
        header('Content-Disposition: inline; filename="comprovante.' . ($pdf ? 'pdf' : 'jpg') . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /** Desfaz um "recebi" marcado por engano, ou reativa uma parcela cancelada. */
    public function undo(int $id): void
    {
        $inst = Payments::installment($id);
        $back = safe_back($_POST['back'] ?? null, '/admin/financeiro');
        if (!$inst) {
            flash('err', 'Parcela não encontrada.');
            redirect($back);
        }
        $err = Payments::undo($id);
        if ($err) {
            flash('err', $err);
            redirect($back);
        }
        Audit::log((int) Auth::id(), 'installment_undo', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number'] . ' voltou para pendente');
        flash('ok', 'Parcela voltou para pendente.');
        redirect($back);
    }

    /** Cancela uma parcela (estorno ou paciente que parou de pagar). */
    public function cancel(int $id): void
    {
        $inst = Payments::installment($id);
        $back = safe_back($_POST['back'] ?? null, '/admin/financeiro');
        if (!$inst) {
            flash('err', 'Parcela não encontrada.');
            redirect($back);
        }
        Payments::cancel($id);
        Audit::log((int) Auth::id(), 'installment_cancel', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number'] . ' cancelada');
        flash('ok', 'Parcela cancelada. Ela não entra mais nas somas. Para voltar atrás, use "Reativar" na ficha do paciente.');
        redirect($back);
    }

    /** Tela das taxas do cartão (bandeira x faixa de parcelas). */
    public function fees(): void
    {
        View::render('admin/finance/fees', [
            'title' => 'Taxas do cartão',
            'table' => Payments::feeTable(),
        ], 'admin');
    }

    public function saveFees(): void
    {
        $input = is_array($_POST['fee'] ?? null) ? $_POST['fee'] : [];
        $err = Payments::saveFees($input);
        if ($err) {
            flash('err', $err);
            redirect('/admin/financeiro/taxas');
        }
        Audit::log((int) Auth::id(), 'fees_update', 'setting', 0, 'taxas do cartão');
        flash('ok', 'Taxas salvas. Valem para as próximas vendas no cartão (as já registradas não mudam).');
        redirect('/admin/financeiro/taxas');
    }
}
