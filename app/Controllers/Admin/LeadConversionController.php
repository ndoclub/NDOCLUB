<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\LeadConversion;
use App\Services\Leads;
use App\Services\PersonalData;
use App\Services\Payments;
use App\Services\Plans;
use App\Services\Responsibles;

/**
 * Converter lead em paciente (Atualização 09.2), em duas telas:
 *  1) formulário (plano contratado, valor, pagamento, dados do paciente) -> 2) resumo para CONFIRMAR o plano contratado.
 * Só a confirmação grava. Tudo é conferido de novo na confirmação (os campos escondidos do resumo nunca são confiados).
 * Duas formas: criar um paciente novo, ou vincular a um paciente que já existe (renova o plano dele e lança a venda).
 */
final class LeadConversionController
{
    /** Campos que o resumo repete escondidos, para a confirmação conferir tudo de novo. */
    private const KEEP = [
        'modo', 'ok_dup', 'patient_id', 'name', 'email', 'phone', 'goal', 'birth_date', 'joined_on', 'renewal_date', 'plan_id', 'responsible_id',
        'plan_price', 'plan_currency', 'method', 'installments', 'card_brand', 'fee_pct', 'first_due', 'first_paid', 'received_brl',
    ];

    private function lead(int $id): array
    {
        $lead = Leads::find($id);
        if (!$lead) {
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        if ($lead['status'] === 'won') {
            flash('err', 'Este lead já foi convertido em paciente.');
            redirect('/admin/crm/' . $id);
        }
        return $lead;
    }

    // ------------------------------------------------------------------ tela 1: formulário

    public function form(int $id): void
    {
        $lead = $this->lead($id);
        $dups = Leads::duplicates((string) $lead['email'], $lead['phone'], (string) $lead['instagram'], $id);
        $modo = (string) ($_GET['modo'] ?? '');
        $mode = $modo === 'vincular' ? 'link' : ($modo === 'novo' ? 'new' : '');
        if ($mode === '' && !$dups['patient']) {
            $mode = 'new';
        }
        $this->renderForm($lead, $mode, $dups, !empty($_GET['outra']), (int) ($_GET['paciente'] ?? 0));
    }

    private function renderForm(array $lead, string $mode, array $dups, bool $outra, int $pickPatient = 0): void
    {
        $plan = null;
        $planId = (int) old('plan_id', (string) ($lead['plan_id'] ?? 0));
        if ($planId > 0) {
            $plan = DB::one('SELECT * FROM plans WHERE id = ? AND active = 1', [$planId]);
        }
        View::render('admin/crm/convert', [
            'title' => 'Converter em paciente',
            'lead' => $lead, 'mode' => $mode, 'dups' => $dups, 'outra' => $outra,
            'plans' => Plans::active(), 'plan' => $plan,
            'fees' => Payments::feeTable(),
            'respChoices' => Responsibles::choices((int) $lead['responsible_id']),
            'patients' => DB::all("SELECT id, name, email, status FROM users WHERE role = 'patient' ORDER BY name ASC"),
            'pickPatient' => $pickPatient > 0 ? $pickPatient : (int) ($dups['patient']['id'] ?? 0),
            'defaultRenewal' => date('Y-m-d', strtotime(today() . ' +' . (int) ($plan['duration_days'] ?? 30) . ' days')),
        ], 'admin');
    }

    // ------------------------------------------------------------------ leitura e conferência

    /** Lê e confere tudo. Retorna [dados, erros, duplicados]. */
    private function collect(array $lead): array
    {
        $err = [];
        $mode = input('modo') === 'vincular' ? 'link' : 'new';
        $c = ['mode' => $mode];

        // plano contratado
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $plan = $planId ? DB::one('SELECT * FROM plans WHERE id = ? AND active = 1', [$planId]) : null;
        if (!$plan) {
            $err[] = 'Escolha o plano contratado.';
        }
        $c['plan'] = $plan;
        $resp = (int) ($_POST['responsible_id'] ?? 0);
        if ($resp > 0 && !Responsibles::isActive($resp)) {
            $err[] = 'Responsável inválido ou desativado.';
            $resp = 0;
        }
        $c['responsible_id'] = $resp > 0 ? $resp : null;

        // valor contratado
        $typed = parse_money((string) ($_POST['plan_price'] ?? ''));
        $cur = currency_or_brl($_POST['plan_currency'] ?? 'BRL');
        if ($typed === false) {
            $err[] = 'Valor contratado inválido. Use números, por exemplo 450,00.';
        }
        $amount = $typed === false ? null : $typed;
        if ($amount === null && $plan && $plan['price'] !== null) {
            $amount = (float) $plan['price'];
            $cur = 'BRL';
        }
        if ($typed !== false && ($amount === null || $amount <= 0)) {
            $err[] = 'Informe o valor contratado (o plano escolhido não tem valor definido).';
        }
        $c['amount'] = (float) $amount;
        $c['currency'] = $cur;
        // valor igual ao do plano não é "valor combinado": só guarda quando é diferente (ou em dólar/euro)
        $c['store_custom'] = $typed !== false && $typed !== null && !($plan && $plan['price'] !== null && $cur === 'BRL' && abs($typed - (float) $plan['price']) < 0.005);

        // pagamento: a conversão sempre lança a venda no Financeiro
        $method = trim((string) ($_POST['method'] ?? ''));
        $c['pay'] = null;
        if ($method === '' || !isset(Payments::methods()[$method])) {
            $err[] = 'Escolha a forma de pagamento: a conversão lança a venda no Financeiro.';
        } elseif ($amount !== null && $amount > 0) {
            $parsed = Payments::parseForm($amount, $cur, 'first_paid');
            if (is_string($parsed)) {
                $err[] = $parsed;
            } else {
                $c['pay'] = $parsed;
            }
        }

        $dups = ['email' => null, 'phone' => [], 'instagram' => [], 'patient' => null];
        if ($mode === 'link') {
            $pid = (int) ($_POST['patient_id'] ?? 0);
            $p = $pid ? DB::one("SELECT id, name, email, status, renewal_date FROM users WHERE id = ? AND role = 'patient'", [$pid]) : null;
            if (!$p) {
                $err[] = 'Escolha o paciente que já existe.';
            } elseif ($p['status'] === 'inactive') {
                $err[] = 'O acesso de ' . $p['name'] . ' está desativado. Reative o paciente antes de vincular.';
            }
            $c['patient_id'] = $p ? (int) $p['id'] : 0;
            $c['patient'] = $p;
            if ($p && $plan) {
                $prev = $p['renewal_date'] ? substr($p['renewal_date'], 0, 10) : '';
                $base = ($prev !== '' && $prev > today()) ? $prev : today();
                $c['new_renewal'] = date('Y-m-d', strtotime($base . ' +' . (int) $plan['duration_days'] . ' days'));
            }
            return [$c, $err, $dups];
        }

        // paciente novo
        $name = input('name');
        $email = mb_strtolower(input('email'));
        $phone = input('phone');
        $goal = input('goal');
        $birth = input('birth_date');
        $joined = input('joined_on') ?: today();
        $renewal = input('renewal_date');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $err[] = 'Informe o nome completo do paciente.';
        }
        $emailTaken = false;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 160) {
            $err[] = 'Informe um e-mail válido: ele é o login do paciente no app.';
        } elseif ($u = DB::one('SELECT id, name, role FROM users WHERE email = ?', [$email])) {
            $emailTaken = true;
            $err[] = $u['role'] === 'patient'
                ? 'Já existe o paciente ' . $u['name'] . ' com este e-mail. Use "Vincular a paciente que já existe".'
                : 'Este e-mail já é usado por outro usuário do sistema.';
        }
        if ($phone !== '') {
            $dg = preg_replace('/\D+/', '', $phone);
            if (!preg_match('/^[\d\s()+.-]+$/', $phone) || strlen($dg) < 8 || strlen($dg) > 15) {
                $err[] = 'WhatsApp: confira o número (DDD e, se for de fora do Brasil, o código do país).';
            }
        }
        if (mb_strlen($goal) > 120) {
            $err[] = 'Objetivo: no máximo 120 letras.';
        }
        if ($birth !== '' && !(Payments::validDate($birth) && $birth <= today() && $birth >= '1900-01-01')) {
            $err[] = 'Data de nascimento inválida (use uma data do passado, a partir de 1900).';
        }
        if (!Payments::validDate($joined) || $joined > today()) {
            $err[] = '"Paciente desde" inválido (use uma data de hoje ou do passado).';
        }
        if ($renewal !== '' && !Payments::validDate($renewal)) {
            $err[] = 'Data de vencimento inválida.';
        }
        if ($renewal === '' && $plan) {
            $renewal = date('Y-m-d', strtotime(today() . ' +' . (int) $plan['duration_days'] . ' days'));
        }
        [$pd, $pdErr] = PersonalData::clean(['instagram' => (string) ($lead['instagram'] ?? '')]);
        $err = array_merge($err, $pdErr);

        // duplicidade: outro paciente com o mesmo telefone/Instagram só passa se a equipe confirmar que é outra pessoa
        $dups = Leads::duplicates($email, $phone !== '' ? $phone : null, (string) ($lead['instagram'] ?? ''), (int) $lead['id']);
        if (!$emailTaken && $dups['patient'] && empty($_POST['ok_dup'])) {
            $err[] = 'Já existe o paciente ' . $dups['patient']['name'] . ' com os mesmos dados de contato. Vincule a ele, ou confirme que é outra pessoa.';
        }
        $c += ['name' => $name, 'email' => $email, 'phone' => $phone !== '' ? mb_substr($phone, 0, 30) : null, 'goal' => $goal !== '' ? $goal : null,
            'birth' => $birth !== '' ? $birth : null, 'joined_on' => $joined, 'renewal_date' => $renewal !== '' ? $renewal : null, 'pd' => $pd];
        return [$c, $err, $dups];
    }

    // ------------------------------------------------------------------ tela 2: resumo e confirmação

    public function submit(int $id): void
    {
        $lead = $this->lead($id);
        $mode = input('modo') === 'vincular' ? 'vincular' : 'novo';
        $back = '/admin/crm/' . $id . '/converter?modo=' . $mode . (!empty($_POST['ok_dup']) ? '&outra=1' : '');

        if (!empty($_POST['back'])) {
            $GLOBALS['NDO_OLD'] = $_POST;
            $this->renderForm($lead, $mode === 'vincular' ? 'link' : 'new', Leads::duplicates((string) $lead['email'], $lead['phone'], (string) $lead['instagram'], $id), !empty($_POST['ok_dup']));
            return;
        }

        [$c, $errors, $dups] = $this->collect($lead);
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect($back);
        }

        if (empty($_POST['confirm'])) {
            $this->renderReview($lead, $c, null);
            return;
        }
        if (empty($_POST['confirm_plan'])) {
            $this->renderReview($lead, $c, 'Marque a caixinha para confirmar o plano contratado e o valor.');
            return;
        }

        $by = (int) Auth::id();
        $c['password'] = temp_password();
        try {
            $res = LeadConversion::convert($lead, $c, $by);
        } catch (\RuntimeException $e) {
            flash('err', $e->getMessage());
            redirect('/admin/crm/' . $id);
        }
        $uid = (int) $res['patient_id'];
        $plan = $c['plan'];
        $detail = $plan['name'] . ' · ' . money($c['amount'], 2, $c['currency']) . ' · ' . LeadConversion::conditionText($c['pay']);
        Audit::log($by, 'lead_convert', 'lead', $id, $lead['name'] . ' -> paciente #' . $uid . ' · ' . $detail);
        if ($c['mode'] === 'new') {
            Audit::log($by, 'patient_create', 'user', $uid, $c['name'] . ' · via CRM · ' . $detail);
            $_SESSION['_creds'] = ['name' => $c['name'], 'email' => $c['email'], 'password' => $c['password']];
            flash('ok', 'Lead convertido em paciente. A venda e as parcelas já estão no Financeiro. Envie o acesso pelo WhatsApp (mensagem pronta abaixo).');
        } else {
            Audit::log($by, 'renewal', 'user', $uid, '+' . (int) $plan['duration_days'] . ' dias, até ' . $c['new_renewal'] . ', ' . money($c['amount'], 2, $c['currency']) . ' · via CRM');
            flash('ok', 'Lead vinculado ao paciente que já existia: plano renovado até ' . fmt_date($c['new_renewal']) . ' e venda lançada no Financeiro.');
        }
        redirect(PatientController::ficha($uid, $c['mode'] === 'new' ? 'resumo' : 'plano'));
    }

    private function renderReview(array $lead, array $c, ?string $problem): void
    {
        $keep = [];
        foreach (self::KEEP as $k) {
            if (isset($_POST[$k]) && is_string($_POST[$k])) {
                $keep[$k] = $_POST[$k];
            }
        }
        View::render('admin/crm/convert-review', [
            'title' => 'Confirmar conversão',
            'lead' => $lead, 'c' => $c, 'keep' => $keep, 'problem' => $problem,
            'respName' => $c['responsible_id'] ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$c['responsible_id']]) : '',
        ], 'admin');
    }
}
