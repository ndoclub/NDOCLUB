<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\View;
use App\Services\Audit;
use App\Services\Payments;

/**
 * Financeiro (só admin): faturamento do mês, recebíveis (parcelas) e conferência do que caiu.
 * O paciente nunca acessa nem vê nada daqui.
 */
final class FinanceController
{
    public function index(): void
    {
        $ym = Payments::normalizeMonth($_GET['m'] ?? null);
        $filter = (string) ($_GET['f'] ?? 'month');
        if (!in_array($filter, ['month', 'pending', 'received', 'late'], true)) {
            $filter = 'month';
        }
        $first = $ym . '-01';
        View::render('admin/finance/index', [
            'title' => 'Financeiro',
            'ym' => $ym,
            'filter' => $filter,
            'monthLabel' => mb_strtoupper(mb_substr(month_name((int) date('n', strtotime($first))), 0, 1, 'UTF-8'), 'UTF-8') . mb_substr(month_name((int) date('n', strtotime($first))), 1, null, 'UTF-8') . ' de ' . date('Y', strtotime($first)),
            'prevYm' => date('Y-m', strtotime($first . ' -1 month')),
            'nextYm' => date('Y-m', strtotime($first . ' +1 month')),
            'isCurrent' => $ym === date('Y-m'),
            'summary' => Payments::monthSummary($ym),
            'late' => Payments::late(),
            'receivables' => Payments::receivables(12),
            'rows' => Payments::installments($ym, $filter),
        ], 'admin');
    }

    /** "Recebi": marca a parcela como recebida (em USD/EUR, informa quanto caiu em reais). */
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
        $err = Payments::receive($id, trim((string) ($_POST['received_on'] ?? '')) ?: null, $brl);
        if ($err) {
            flash('err', $err);
            redirect($back);
        }
        Audit::log((int) Auth::id(), 'installment_received', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number'] . ' · ' . money((float) $inst['amount'], 2, $inst['currency']) . ($brl !== null && $inst['currency'] !== 'BRL' ? ' → ' . money($brl) : ''));
        flash('ok', 'Parcela marcada como recebida.');
        redirect($back);
    }

    /** Desfaz um "recebi" marcado por engano. */
    public function undo(int $id): void
    {
        $inst = Payments::installment($id);
        $back = safe_back($_POST['back'] ?? null, '/admin/financeiro');
        if (!$inst) {
            flash('err', 'Parcela não encontrada.');
            redirect($back);
        }
        Payments::undo($id);
        Audit::log((int) Auth::id(), 'installment_undo', 'user', (int) $inst['user_id'], 'parcela ' . $inst['number'] . ' voltou para pendente');
        flash('ok', 'Parcela voltou para pendente.');
        redirect($back);
    }
}
