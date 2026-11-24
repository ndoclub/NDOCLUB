<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Leads;
use App\Services\Responsibles;

/**
 * Perdidos e reativação (Atualização 09.2).
 * Lead perdido sai das oportunidades abertas e vive só na tela "Reativação". Daqui saem as ações de
 * marcar como perdido, programar a reativação (cadência a partir de 20 dias) e reabrir o lead.
 */
final class LeadRecoveryController
{
    private const PER_PAGE = [10, 25, 50, 100];

    // ------------------------------------------------------------------ tela Reativação

    public function index(): void
    {
        $g = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
        $f = [
            'q' => mb_substr($g('q'), 0, 80),
            'reason' => ctype_digit($g('reason')) ? $g('reason') : '',
            'resp' => ($g('resp') === 'none' || ctype_digit($g('resp'))) ? $g('resp') : '',
            'f' => in_array($g('f'), ['ready', 'waiting', 'unscheduled', 'done', 'noauth', 'dnc'], true) ? $g('f') : '',
            'ord' => isset(Leads::lostSorts()[$g('ord')]) ? $g('ord') : 'next',
        ];
        $perPage = in_array((int) $g('pp'), self::PER_PAGE, true) ? (int) $g('pp') : 25;
        $page = max(1, (int) $g('pg'));
        $res = Leads::lostListing($f, $perPage, ($page - 1) * $perPage);
        $pages = max(1, (int) ceil($res['total'] / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $res = Leads::lostListing($f, $perPage, ($page - 1) * $perPage);
        }
        View::render('admin/crm/recovery', [
            'title' => 'CRM · Reativação',
            'list' => $res['rows'], 'total' => $res['total'], 'f' => $f, 'perPage' => $perPage, 'page' => $page, 'pages' => $pages,
            'offset' => ($page - 1) * $perPage,
            'counts' => Leads::lostCounts(),
            'days' => Leads::recoveryDays(),
            'reasons' => Leads::allLostReasons(),
            'responsibles' => Responsibles::all(),
            'respChoices' => Responsibles::choices(),
        ], 'admin');
    }

    // ------------------------------------------------------------------ ações na ficha

    private function lead(int $id): array
    {
        $lead = Leads::find($id);
        if (!$lead) {
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        return $lead;
    }

    /** Marcar um lead em aberto como perdido (motivo obrigatório). */
    public function lose(int $id): void
    {
        $lead = $this->lead($id);
        $back = '/admin/crm/' . $id;
        if ($lead['status'] !== 'open') {
            flash('err', $lead['status'] === 'won' ? 'Este lead já virou paciente.' : 'Este lead já está como perdido.');
            redirect($back);
        }
        $reasonId = (int) ($_POST['reason_id'] ?? 0);
        if (!$reasonId || !DB::val('SELECT id FROM lead_lost_reasons WHERE id = ? AND active = 1', [$reasonId])) {
            flash('err', 'Escolha o motivo da perda.');
            redirect($back . '#perder');
        }
        $note = mb_substr(input('note'), 0, 300) ?: null;
        $contactOk = !empty($_POST['contact_ok']);
        Leads::markLost($lead, $reasonId, $note, $contactOk, (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_lost', 'lead', $id, $lead['name']);
        $fresh = Leads::find($id);
        if ($fresh && Leads::recoveryEligible($fresh) && $fresh['next_on']) {
            flash('ok', 'Lead marcado como perdido. Reativação programada: 1º contato em ' . fmt_date($fresh['next_on']) . '.');
        } elseif ((int) $lead['do_not_contact'] === 1) {
            flash('ok', 'Lead marcado como perdido. Ele está como "Não contatar": não há reativação.');
        } else {
            flash('ok', 'Lead marcado como perdido. Ele não autorizou novos contatos, então a reativação não foi programada.');
        }
        redirect($back);
    }

    /** O lead perdido voltou a se interessar: volta para as oportunidades abertas. */
    public function reopen(int $id): void
    {
        $lead = $this->lead($id);
        $back = '/admin/crm/' . $id;
        if ($lead['status'] !== 'lost') {
            flash('err', 'Só dá para reabrir um lead perdido.');
            redirect($back);
        }
        $stage = (int) ($_POST['stage_id'] ?? 0);
        if (!Leads::reopen($lead, $stage, mb_substr(input('note'), 0, 300) ?: null, (int) Auth::id())) {
            flash('err', 'Escolha a etapa em que o lead volta.');
            redirect($back . '#reabrir');
        }
        Audit::log((int) Auth::id(), 'lead_reopen', 'lead', $id, $lead['name']);
        flash('ok', 'Lead reaberto: voltou para as oportunidades em aberto' . ((int) $lead['do_not_contact'] === 1 ? '.' : ', com retorno para hoje.'));
        redirect($back);
    }

    /** Programar a reativação (ou recomeçar depois de concluída). */
    public function start(int $id): void
    {
        $lead = $this->lead($id);
        $back = '/admin/crm/' . $id;
        if ($lead['status'] !== 'lost') {
            flash('err', 'A reativação é só para leads perdidos.');
            redirect($back);
        }
        if ((int) $lead['do_not_contact'] === 1) {
            flash('err', 'Este lead está como "Não contatar": não dá para programar reativação.');
            redirect($back);
        }
        if ((int) $lead['contact_ok'] !== 1) {
            flash('err', 'Este lead não autorizou novos contatos. Marque a autorização na ficha só se ele aceitou receber contato.');
            redirect($back);
        }
        $restart = $lead['recovery_status'] === 'done';
        $due = Leads::startRecovery($lead, (int) Auth::id(), $restart);
        Audit::log((int) Auth::id(), 'lead_recovery', 'lead', $id, $lead['name'] . ($restart ? ' (nova reativação)' : ''));
        flash('ok', ($restart ? 'Nova reativação programada' : 'Reativação programada') . ': 1º contato em ' . fmt_date((string) $due) . '.');
        redirect($back);
    }
}
