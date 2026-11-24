<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\LeadConversion;
use App\Services\Leads;
use App\Services\Settings;
use App\Services\PersonalData;
use App\Services\Responsibles;

/** CRM / Leads (09.1 a 09.4): lista, Funil (Kanban por etapa) e Follow-up (colunas por prazo) com indicadores, cadastro, ficha com linha do tempo, responsáveis, follow-up e listas editáveis (origens e etapas). */
final class LeadController
{
    private const PER_PAGE = [10, 25, 50, 100];

    // ------------------------------------------------------------------ lista

    public function index(): void
    {
        $g = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
        $f = [
            'q' => mb_substr($g('q'), 0, 80),
            'temp' => isset(Leads::TEMPS[$g('temp')]) ? $g('temp') : '',
            'source' => ctype_digit($g('source')) ? $g('source') : '',
            'stage' => ctype_digit($g('stage')) ? $g('stage') : '',
            'sport' => ctype_digit($g('sport')) ? $g('sport') : '',
            'plan' => ctype_digit($g('plan')) ? $g('plan') : '',
            'resp' => ($g('resp') === 'none' || ctype_digit($g('resp'))) ? $g('resp') : '',
            'task' => ($g('task') === 'none' || ctype_digit($g('task'))) ? $g('task') : '',
            'p' => isset(Leads::periods()[$g('p')]) ? $g('p') : '',
            'f' => in_array($g('f'), ['late', 'today', 'tomorrow', 'week', 'later', 'sched', 'none', 'noresp', 'pot', 'hot', 'dnc', 'won'], true) ? $g('f') : '',
            'ord' => isset(Leads::sorts()[$g('ord')]) ? $g('ord') : 'new',
        ];
        $perPage = in_array((int) $g('pp'), self::PER_PAGE, true) ? (int) $g('pp') : 25;
        $page = max(1, (int) $g('pg'));
        $res = Leads::listing($f, $perPage, ($page - 1) * $perPage);
        $pages = max(1, (int) ceil($res['total'] / $perPage));
        if ($page > $pages) {
            $page = $pages;
            $res = Leads::listing($f, $perPage, ($page - 1) * $perPage);
        }
        View::render('admin/crm/index', [
            'title' => 'CRM',
            'list' => $res['rows'], 'total' => $res['total'], 'f' => $f, 'perPage' => $perPage, 'page' => $page, 'pages' => $pages,
            'offset' => ($page - 1) * $perPage,
            'counts' => Leads::counts(),
            'ind' => Leads::indicators(),
            'respChoices' => Responsibles::choices(),
            'sources' => DB::all('SELECT id, name, active FROM lead_sources ORDER BY active DESC, sort_order ASC, name ASC'),
            'stages' => Leads::allStages(),
            'responsibles' => Responsibles::all(),
            'sports' => DB::all('SELECT id, name FROM sports ORDER BY active DESC, name ASC'),
            'plans' => DB::all('SELECT id, name FROM plans ORDER BY active DESC, name ASC'),
        ], 'admin');
    }

    // ------------------------------------------------------------------ funil (Kanban por etapa)

    /** Funil (antes "Quadro"): os mesmos leads em aberto da Lista, em colunas por etapa. Arrastar um cartão muda a etapa (ver stage()). */
    public function board(): void
    {
        $g = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
        $f = [
            'q' => mb_substr($g('q'), 0, 80),
            'temp' => isset(Leads::TEMPS[$g('temp')]) ? $g('temp') : '',
            'source' => ctype_digit($g('source')) ? $g('source') : '',
            'resp' => ($g('resp') === 'none' || ctype_digit($g('resp'))) ? $g('resp') : '',
            'f' => in_array($g('f'), ['late', 'today', 'hot', 'noresp'], true) ? $g('f') : '',
        ];
        $b = Leads::board($f);
        View::render('admin/crm/board', [
            'title' => 'CRM · Funil',
            'wide' => true, // o Funil usa a largura toda da tela (o conteúdo do painel normalmente tem limite)
            'columns' => $b['columns'], 'total' => $b['total'], 'f' => $f,
            'counts' => Leads::counts(),
            'ind' => Leads::indicators(),
            'respChoices' => Responsibles::choices(),
            'sources' => DB::all('SELECT id, name, active FROM lead_sources ORDER BY active DESC, sort_order ASC, name ASC'),
            'moveStages' => Leads::stages(true),
        ], 'admin');
    }

    // ------------------------------------------------------------------ follow-up (09.4)

    /** Follow-up: os mesmos leads em colunas por PRAZO do próximo retorno (Atrasados, Hoje, Amanhã, Próximos 7 dias, Depois, Sem retorno). */
    public function followUp(): void
    {
        $g = fn(string $k) => isset($_GET[$k]) && is_string($_GET[$k]) ? trim($_GET[$k]) : '';
        $f = [
            'q' => mb_substr($g('q'), 0, 80),
            'resp' => ($g('resp') === 'none' || ctype_digit($g('resp'))) ? $g('resp') : '',
            'c' => isset(Leads::FOLLOW_CHIPS[$g('c')]) ? $g('c') : '',
        ];
        $b = Leads::followBoard($f);
        View::render('admin/crm/followup', [
            'title' => 'CRM · Follow-up',
            'wide' => true,
            'columns' => $b['columns'], 'total' => $b['total'], 'f' => $f,
            'chipCounts' => Leads::followChipCounts($f),
            'attn' => Leads::attention(),
            'respChoices' => Responsibles::choices(),
        ], 'admin');
    }

    /**
     * Reagendar o próximo retorno de um lead pelo Follow-up (arrastar para Hoje/Amanhã, ou o seletor "Reagendar para…" do cartão).
     * Só troca a DATA: o tipo da ação e quem faz a tarefa continuam como estavam. Entra na linha do tempo como qualquer agendamento.
     *  - days: 0 (hoje), 1 (amanhã), 3, 7, 15 ou 30 dias a partir de hoje  ·  date: Y-m-d (hoje ou futura)
     * Com o cabeçalho de fetch a resposta é JSON; sem ele, aviso + volta para a tela de origem.
     */
    public function reschedule(int $id): void
    {
        $ajax = is_ajax();
        $lead = Leads::find($id);
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/crm/follow-up');
        if (!str_starts_with($back, '/admin/crm')) {
            $back = '/admin/crm/follow-up';
        }
        $fail = function (string $msg, int $code = 422) use ($ajax, $back): void {
            if ($ajax) {
                json_out(['ok' => false, 'error' => $msg], $code);
            }
            flash('err', $msg);
            redirect($back);
        };
        if (!$lead) {
            $fail('Lead não encontrado.', 404);
        }
        if (!in_array($lead['status'], ['open', 'lost'], true)) {
            $fail('Este lead já virou paciente: não há mais retorno a agendar.');
        }
        if ((int) $lead['do_not_contact'] === 1) {
            $fail('Este lead está como "Não contatar": não dá para agendar retorno.');
        }
        if ($lead['status'] === 'lost' && !Leads::recoveryEligible($lead)) {
            $fail('Este lead perdido não autorizou novos contatos: não dá para agendar tentativa.');
        }
        $days = trim((string) ($_POST['days'] ?? ''));
        $date = trim((string) ($_POST['date'] ?? ''));
        if ($days === 'date' && !$ajax) {
            redirect('/admin/crm/' . $id . '#retorno'); // "Outra data…": a ficha tem o campo de data
        }
        if ($days !== '' && ctype_digit($days) && isset(Leads::RESCHEDULE[(int) $days])) {
            $nextOn = date('Y-m-d', strtotime(today() . ' +' . (int) $days . ' days'));
        } elseif ($date !== '' && self::validDate($date) && $date >= today()) {
            $nextOn = $date;
        } else {
            $fail('Escolha para quando reagendar (hoje, amanhã ou uma data futura).');
        }
        if ($lead['next_on'] === $nextOn) {
            $fail('O retorno já está marcado para ' . fmt_date($nextOn) . '.');
        }
        $type = isset(Leads::ACTIONS[(string) $lead['next_type']]) ? (string) $lead['next_type'] : 'followup';
        $was = $lead['next_on'] ? 'antes: ' . fmt_date($lead['next_on']) : 'antes: sem retorno';
        Leads::schedule($lead, $nextOn, $type, $lead['next_responsible_id'] ? (int) $lead['next_responsible_id'] : null, 'Reagendado no Follow-up (' . $was . ').', (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_schedule', 'lead', $id, $lead['name'] . ' -> ' . $nextOn . ' (Follow-up)');
        $msg = first_name($lead['name']) . ': retorno reagendado para ' . fmt_date($nextOn) . '.';
        if ($ajax) {
            $fu = Leads::followUp(['next_on' => $nextOn, 'do_not_contact' => 0]);
            json_out([
                'ok' => true, 'message' => $msg, 'bucket' => Leads::bucketOf($nextOn), 'state' => $fu['state'],
                'text' => $fu['state'] === 'today' ? 'Retorno hoje' : 'Retorno ' . $fu['text'], 'date' => substr(fmt_date($nextOn), 0, 5),
            ]);
        }
        flash('ok', $msg);
        redirect($back);
    }

    // ------------------------------------------------------------------ cadastro e edição

    public function form(?int $id = null): void
    {
        $lead = $id ? Leads::find($id) : null;
        if ($id && !$lead) {
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        $this->renderForm($lead, null);
    }

    private function renderForm(?array $lead, ?array $dups): void
    {
        View::render('admin/crm/form', [
            'title' => $lead ? 'Editar lead' : 'Novo lead',
            'lead' => $lead, 'dups' => $dups,
            'sources' => Leads::sources($lead ? (int) $lead['source_id'] : null),
            'stages' => Leads::stages(true),
            'responsibles' => Responsibles::choices($lead ? (int) $lead['responsible_id'] : null),
            'sports' => DB::all('SELECT id, name, active FROM sports WHERE active = 1' . ($lead && $lead['sport_id'] ? ' OR id = ' . (int) $lead['sport_id'] : '') . ' ORDER BY name ASC'),
            'plans' => DB::all('SELECT id, name, active FROM plans WHERE active = 1' . ($lead && $lead['plan_id'] ? ' OR id = ' . (int) $lead['plan_id'] : '') . ' ORDER BY name ASC'),
            'patients' => DB::all("SELECT id, name FROM users WHERE role = 'patient' ORDER BY name ASC"),
        ], 'admin');
    }

    public function store(): void
    {
        [$d, $errors] = $this->collect(null);
        if ($errors) {
            $this->fail($errors, '/admin/crm/novo');
        }
        $dups = Leads::duplicates((string) $d['email'], $d['phone'], (string) $d['instagram']);
        if ($this->mustStop($dups)) {
            $GLOBALS['NDO_OLD'] = $_POST;
            $this->renderForm(null, $dups);
            return;
        }
        $id = Leads::create($d, (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_create', 'lead', $id, $d['name']);
        flash('ok', 'Lead cadastrado. Registre o primeiro contato quando falar com ele.');
        redirect('/admin/crm/' . $id);
    }

    public function update(int $id): void
    {
        $lead = Leads::find($id);
        if (!$lead) {
            redirect('/admin/crm');
        }
        [$d, $errors] = $this->collect($lead);
        if ($errors) {
            $this->fail($errors, '/admin/crm/' . $id . '/editar');
        }
        $dups = Leads::duplicates((string) $d['email'], $d['phone'], (string) $d['instagram'], $id);
        if ($this->mustStop($dups)) {
            $GLOBALS['NDO_OLD'] = $_POST;
            $this->renderForm($lead, $dups);
            return;
        }
        Leads::updateData($lead, $d, (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_update', 'lead', $id, $d['name']);
        flash('ok', 'Dados do lead salvos.');
        redirect('/admin/crm/' . $id);
    }

    /** E-mail repetido sempre bloqueia. Telefone, Instagram ou "já é paciente" bloqueiam até a pessoa confirmar que é outra pessoa. */
    private function mustStop(array $dups): bool
    {
        if ($dups['email'] !== null) {
            return true;
        }
        $soft = $dups['phone'] || $dups['instagram'] || $dups['patient'];
        return $soft && empty($_POST['ok_dup']);
    }

    private function fail(array $errors, string $back): void
    {
        keep_old();
        foreach ($errors as $e) {
            flash('err', $e);
        }
        redirect($back);
    }

    /**
     * Lê o próximo retorno do formulário: uma data, ou "em N dias" (0 = hoje). Devolve Y-m-d ou null (nada preenchido).
     * Erros de preenchimento vão para $err.
     */
    private function readNextDate(array &$err): ?string
    {
        $nd = input('next_date');
        $days = input('next_days');
        if ($nd !== '') {
            if (!self::validDate($nd) || $nd < today()) {
                $err[] = 'A data do próximo retorno precisa ser hoje ou uma data futura.';
                return null;
            }
            return $nd;
        }
        if ($days !== '') {
            if (!ctype_digit($days) || (int) $days < 0 || (int) $days > 365) {
                $err[] = 'Próximo retorno em quantos dias? Digite um número de 0 a 365.';
                return null;
            }
            return date('Y-m-d', strtotime(today() . ' +' . (int) $days . ' days'));
        }
        return null;
    }

    private static function validDate(string $d): bool
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $d);
        return $dt !== false && $dt->format('Y-m-d') === $d;
    }

    /** Lê e valida o formulário de cadastro/edição. Retorna [dados, erros]. */
    private function collect(?array $cur): array
    {
        $err = [];
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $err[] = 'Informe o nome do lead (2 a 120 letras).';
        }
        $phone = input('phone');
        if ($phone !== '') {
            $dg = preg_replace('/\D+/', '', $phone);
            if (!preg_match('/^[\d\s()+.-]+$/', $phone) || strlen($dg) < 8 || strlen($dg) > 15) {
                $err[] = 'WhatsApp ou telefone: confira o número (DDD e, se for de fora do Brasil, o código do país).';
            }
        }
        $email = mb_strtolower(input('email'));
        if ($email !== '' && (mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            $err[] = 'E-mail inválido.';
        }
        $ig = PersonalData::instagram(input('instagram'));
        if ($ig === false) {
            $err[] = 'Instagram: digite @usuario ou cole o link do perfil.';
            $ig = '';
        }
        if ($phone === '' && $email === '' && $ig === '') {
            $err[] = 'Informe pelo menos um contato: WhatsApp, e-mail ou Instagram.';
        }
        $sourceId = (int) ($_POST['source_id'] ?? 0);
        if (!DB::val('SELECT id FROM lead_sources WHERE id = ? AND (active = 1 OR id = ?)', [$sourceId, (int) ($cur['source_id'] ?? 0)])) {
            $err[] = 'Escolha a origem do lead.';
        }
        $channel = input('channel');
        $channel = isset(Leads::CHANNELS[$channel]) ? $channel : null;
        $exists = fn(string $sql, int $v) => $v > 0 && DB::val($sql, [$v]) ? $v : null;
        $sport = $exists('SELECT id FROM sports WHERE id = ?', (int) ($_POST['sport_id'] ?? 0));
        $plan = $exists('SELECT id FROM plans WHERE id = ?', (int) ($_POST['plan_id'] ?? 0));
        $ref = $exists("SELECT id FROM users WHERE id = ? AND role = 'patient'", (int) ($_POST['referred_by'] ?? 0));
        $resp = (int) ($_POST['responsible_id'] ?? 0);
        $resp = $resp > 0 && Responsibles::exists($resp) && (Responsibles::isActive($resp) || $resp === (int) ($cur['responsible_id'] ?? 0)) ? $resp : null;
        $entered = input('entered_on', today());
        if ($entered === '') {
            $entered = today();
        }
        if (!self::validDate($entered) || $entered > today()) {
            $err[] = 'Data de entrada inválida (não pode ser no futuro).';
        }
        $temp = input('temperature');
        $temp = isset(Leads::TEMPS[$temp]) ? $temp : 'warm';
        $stage = (int) ($_POST['stage_id'] ?? 0);
        if (!$cur) {
            $okStage = $stage > 0 && DB::val("SELECT id FROM lead_stages WHERE id = ? AND kind = 'open' AND active = 1", [$stage]);
            $stage = $okStage ? $stage : Leads::firstOpenStageId();
        }
        $goal = mb_substr(input('goal'), 0, 160);
        $notes = mb_substr(input('notes'), 0, 1000);

        return [[
            'name' => $name, 'phone' => $phone !== '' ? mb_substr($phone, 0, 30) : null, 'email' => $email !== '' ? $email : null,
            'instagram' => $ig !== '' ? $ig : null, 'sport_id' => $sport, 'goal' => $goal !== '' ? $goal : null, 'plan_id' => $plan,
            'source_id' => $sourceId, 'channel' => $channel, 'referred_by' => $ref, 'responsible_id' => $resp,
            'temperature' => $temp, 'stage_id' => $stage, 'entered_on' => $entered, 'notes' => $notes !== '' ? $notes : null,
        ], $err];
    }

    // ------------------------------------------------------------------ ficha

    public function show(int $id): void
    {
        $lead = Leads::find($id);
        if (!$lead) {
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        View::render('admin/crm/show', [
            'title' => $lead['name'],
            'lead' => $lead,
            'events' => Leads::timeline($id),
            'stages' => Leads::stages(true, (int) $lead['stage_id']),
            'responsibles' => Responsibles::choices((int) $lead['responsible_id']),
            'respTask' => Responsibles::choices((int) $lead['next_responsible_id']),
            'reasons' => Leads::lostReasons(),
            'openStages' => Leads::stages(true),
            'rec' => $lead['status'] === 'lost' ? Leads::recoveryState($lead) : null,
            'recDays' => Leads::recoveryDays(),
            'conv' => $lead['status'] === 'won' ? LeadConversion::forLead($id) : null,
            'fu' => Leads::followUp($lead),
            'next' => Leads::nextInfo($lead['next_on']),
            'wa' => Leads::waUrl($lead),
        ], 'admin');
    }

    private function open(int $id): array
    {
        $lead = Leads::find($id);
        if (!$lead) {
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        return $lead;
    }

    /** Registrar um contato (e, se quiser, agendar o próximo retorno e mudar a etapa). */
    public function contact(int $id): void
    {
        $lead = $this->open($id);
        $back = '/admin/crm/' . $id;
        $err = [];

        $date = input('happened_date', today());
        $time = input('happened_time', date('H:i'));
        if ($date === '') {
            $date = today();
        }
        if ($time === '') {
            $time = date('H:i');
        }
        if (!self::validDate($date) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $err[] = 'Data ou hora do contato inválida.';
            $at = now();
        } else {
            $at = $date . ' ' . $time . ':00';
            if ($at > date('Y-m-d H:i:s', strtotime('+5 minutes'))) {
                $err[] = 'O contato não pode estar no futuro. Para marcar um retorno, use "Próximo retorno".';
            }
        }
        $summary = input('summary');
        if (mb_strlen($summary) < 3) {
            $err[] = 'Escreva um resumo do contato (o que foi falado ou combinado).';
        }
        $channel = input('channel');
        $channel = isset(Leads::CHANNELS[$channel]) ? $channel : null;
        $result = input('result');
        $result = isset(Leads::RESULTS[$result]) ? $result : null;
        $stage = (int) ($_POST['stage_id'] ?? 0);

        if ($lead['status'] === 'won') {
            flash('err', 'Este lead já virou paciente: o acompanhamento agora é feito na ficha do paciente.');
            redirect($back);
        }
        if ($lead['status'] === 'lost') {
            // lead perdido: o contato é uma tentativa de reativação (segue a cadência ou encerra)
            if ($err) {
                $this->fail($err, $back);
            }
            Leads::logRecoveryAttempt($lead, [
                'happened_at' => $at, 'channel' => $channel, 'result' => $result, 'summary' => $summary, 'notes' => mb_substr(input('notes'), 0, 600) ?: null,
            ], input('after') === 'stop' ? 'stop' : 'cadence', (int) Auth::id());
            Audit::log((int) Auth::id(), 'lead_contact', 'lead', $id, $lead['name'] . ' (reativação)');
            $fresh = Leads::find($id);
            flash('ok', 'Tentativa de reativação registrada' . ($fresh && $fresh['next_on'] ? '. Próxima tentativa em ' . fmt_date($fresh['next_on']) . '.' : '.'));
            redirect($back);
        }

        // Próximo retorno: uma data, ou "em N dias". Lead "Não contatar" não recebe novo retorno.
        $nextOn = null;
        $nextType = input('next_type');
        $nextType = isset(Leads::ACTIONS[$nextType]) ? $nextType : 'followup';
        if ((int) $lead['do_not_contact'] === 0) {
            $nextOn = $this->readNextDate($err);
        }
        $nextResp = (int) ($_POST['next_responsible_id'] ?? 0);
        $nextResp = $nextResp > 0 && Responsibles::exists($nextResp) ? $nextResp : ($lead['responsible_id'] ? (int) $lead['responsible_id'] : null);

        if ($err) {
            $this->fail($err, $back);
        }
        Leads::logContact($lead, [
            'happened_at' => $at, 'channel' => $channel, 'result' => $result, 'summary' => $summary,
            'notes' => mb_substr(input('notes'), 0, 600) ?: null, 'stage_id' => $stage,
            'next_on' => $nextOn, 'next_type' => $nextType, 'next_responsible_id' => $nextResp,
        ], (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_contact', 'lead', $id, $lead['name']);
        flash('ok', 'Contato registrado' . ($nextOn ? '. Próximo retorno em ' . fmt_date($nextOn) . '.' : '. Atenção: este lead está sem próximo retorno agendado.'));
        redirect($back);
    }

    /** Agendar (ou remover) o próximo retorno sem registrar contato, escolhendo quem faz a tarefa. */
    public function schedule(int $id): void
    {
        $lead = $this->open($id);
        $back = '/admin/crm/' . $id . '#retorno';
        if (!in_array($lead['status'], ['open', 'lost'], true)) {
            flash('err', 'Este lead já virou paciente: não há mais retorno a agendar.');
            redirect('/admin/crm/' . $id);
        }
        if ($lead['status'] === 'lost' && !Leads::recoveryEligible($lead) && (int) $lead['do_not_contact'] === 0) {
            flash('err', 'Este lead perdido não autorizou novos contatos: não dá para agendar tentativa. Marque a autorização na ficha só se ele aceitou.');
            redirect($back);
        }
        $note = mb_substr(input('note'), 0, 600) ?: null;
        if (!empty($_POST['clear'])) {
            if ($lead['next_on'] === null) {
                flash('err', 'Este lead já está sem retorno programado.');
                redirect($back);
            }
            Leads::schedule($lead, null, 'followup', null, $note, (int) Auth::id());
            Audit::log((int) Auth::id(), 'lead_schedule', 'lead', $id, $lead['name'] . ' (retorno removido)');
            flash('ok', 'Retorno removido. Atenção: este lead está sem retorno programado.');
            redirect($back);
        }
        if ((int) $lead['do_not_contact'] === 1) {
            flash('err', 'Este lead está como "Não contatar": não dá para agendar retorno. Libere o contato antes.');
            redirect($back);
        }
        $err = [];
        $nextOn = $this->readNextDate($err);
        if (!$err && $nextOn === null) {
            // dias e data em branco: mantém o retorno que já existe e só troca a ação ou quem faz a tarefa
            if ($lead['next_on'] !== null) {
                $nextOn = (string) $lead['next_on'];
            } else {
                $err[] = 'Informe em quantos dias ou escolha a data do retorno.';
            }
        }
        $type = input('next_type');
        $type = isset(Leads::ACTIONS[$type]) ? $type : 'followup';
        $rid = (int) ($_POST['next_responsible_id'] ?? 0);
        $resp = null;
        if ($rid > 0) {
            if (Responsibles::exists($rid) && (Responsibles::isActive($rid) || $rid === (int) $lead['next_responsible_id'])) {
                $resp = $rid;
            } else {
                $err[] = 'Esse responsável não existe ou está desativado.';
            }
        }
        if ($err) {
            $this->fail($err, $back);
        }
        Leads::schedule($lead, $nextOn, $type, $resp, $note, (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_schedule', 'lead', $id, $lead['name'] . ' -> ' . $nextOn);
        $who = $resp ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$resp]) : '';
        flash('ok', 'Retorno agendado para ' . fmt_date($nextOn) . ($who !== '' ? ', tarefa de ' . $who : ', sem responsável pela tarefa') . '.');
        redirect($back);
    }

    /** Lê e valida o responsável escolhido (0 = sem responsável). Retorna null e avisa se for inválido. */
    private function readResponsible(string $back): ?int
    {
        $raw = (string) ($_POST['responsible_id'] ?? '');
        if ($raw === '' || !ctype_digit($raw)) {
            flash('err', 'Escolha o responsável (ou "Sem responsável").');
            redirect($back);
        }
        $rid = (int) $raw;
        if ($rid > 0 && !Responsibles::isActive($rid)) {
            flash('err', 'Esse responsável não existe ou está desativado. Cadastre ou ative em Responsáveis.');
            redirect($back);
        }
        return $rid;
    }

    /** Atribuir o responsável de UM lead (ficha). */
    public function assignOne(int $id): void
    {
        $lead = $this->open($id);
        $back = '/admin/crm/' . $id;
        if (!in_array($lead['status'], ['open', 'lost'], true)) {
            flash('err', 'Este lead já virou paciente: o responsável agora é o do paciente.');
            redirect($back);
        }
        $rid = $this->readResponsible($back);
        $n = Leads::assign([$id], (int) $rid, (int) Auth::id());
        if ($n > 0) {
            $label = $rid > 0 ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$rid]) : 'sem responsável';
            Audit::log((int) Auth::id(), 'lead_assign', 'lead', $id, $lead['name'] . ' -> ' . $label);
            flash('ok', $rid > 0 ? 'Responsável: ' . $label . '.' : 'Responsável removido.');
        } else {
            flash('err', 'Esse já é o responsável atual.');
        }
        redirect($back);
    }

    /** Atribuir o responsável de VÁRIOS leads de uma vez (lista). */
    public function assignMany(): void
    {
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/crm');
        $ids = $_POST['ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0))) : [];
        if (!$ids) {
            flash('err', 'Marque pelo menos um lead na lista.');
            redirect($back);
        }
        $rid = (int) $this->readResponsible($back);
        $n = Leads::assign($ids, $rid, (int) Auth::id());
        $label = $rid > 0 ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$rid]) : 'sem responsável';
        Audit::log((int) Auth::id(), 'lead_assign', 'responsible', $rid ?: null, count($ids) . ' lead(s) -> ' . $label);
        if ($n === 0) {
            flash('err', 'Nenhuma mudança: os leads marcados já estavam com ' . ($rid > 0 ? $label : 'esse status') . '.');
        } elseif ($rid > 0) {
            flash('ok', $n . ($n === 1 ? ' lead agora é de ' : ' leads agora são de ') . $label . '.');
        } else {
            flash('ok', 'Responsável removido de ' . $n . ($n === 1 ? ' lead.' : ' leads.'));
        }
        redirect($back);
    }

    /**
     * Muda a etapa de um lead em aberto. Serve a ficha (formulário), o cartão do Funil (seletor "Mover para…") e o arrastar e soltar (fetch).
     *  - stage_id numérico: etapa em andamento do funil (a mudança vai para a linha do tempo e para a auditoria).
     *  - stage_id "won" / "lost": não move nada; leva ao fluxo próprio ("Converter em paciente" ou "Marcar como perdido").
     * Com o cabeçalho de fetch (is_ajax) a resposta é JSON; sem ele, aviso + volta para a tela de origem.
     */
    public function stage(int $id): void
    {
        $ajax = is_ajax();
        $lead = Leads::find($id);
        if (!$lead) {
            if ($ajax) {
                json_out(['ok' => false, 'error' => 'Lead não encontrado.'], 404);
            }
            flash('err', 'Lead não encontrado.');
            redirect('/admin/crm');
        }
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/crm/' . $id);
        if (!str_starts_with($back, '/admin/crm')) {
            $back = '/admin/crm/' . $id;
        }
        $fail = function (string $msg) use ($ajax, $back): void {
            if ($ajax) {
                json_out(['ok' => false, 'error' => $msg], 422);
            }
            flash('err', $msg);
            redirect($back);
        };
        if ($lead['status'] !== 'open') {
            $fail('Só dá para mudar a etapa de leads em aberto.');
        }
        $to = trim((string) ($_POST['stage_id'] ?? ''));
        if ($to === 'won' || $to === 'lost') {
            $next = $to === 'won' ? '/admin/crm/' . $id . '/converter' : '/admin/crm/' . $id . '?perder=1#perder';
            if ($ajax) {
                json_out(['ok' => true, 'redirect' => url($next)]);
            }
            redirect($next);
        }
        $stageId = ctype_digit($to) ? (int) $to : 0;
        if ($stageId > 0 && $stageId === (int) $lead['stage_id']) {
            $fail('O lead já está nessa etapa.');
        }
        if (!Leads::moveStage($lead, $stageId, (int) Auth::id())) {
            $final = $stageId > 0 && (int) DB::val("SELECT COUNT(*) FROM lead_stages WHERE id = ? AND kind <> 'open'", [$stageId]) > 0;
            $fail($final ? 'Convertido e Perdido têm fluxo próprio: use "Converter em paciente" ou "Marcar como perdido".' : 'Escolha uma etapa válida do funil.');
        }
        Audit::log((int) Auth::id(), 'lead_stage', 'lead', $id, $lead['name']);
        $stageName = Leads::stageName($stageId);
        if ($ajax) {
            json_out(['ok' => true, 'stage' => $stageName, 'message' => first_name($lead['name']) . ' foi para ' . $stageName . '.']);
        }
        flash('ok', 'Etapa atualizada.');
        redirect($back);
    }

    public function temperature(int $id): void
    {
        $lead = $this->open($id);
        if (Leads::setTemperature($lead, input('temperature'), (int) Auth::id())) {
            Audit::log((int) Auth::id(), 'lead_temperature', 'lead', $id, $lead['name']);
            flash('ok', 'Temperatura atualizada.');
        }
        redirect('/admin/crm/' . $id);
    }

    public function consent(int $id): void
    {
        $lead = $this->open($id);
        if ((int) $lead['do_not_contact'] === 1) {
            flash('err', 'Este lead está como "Não contatar". Libere o contato antes de marcar a autorização.');
            redirect('/admin/crm/' . $id);
        }
        Leads::setConsent($lead, !empty($_POST['on']), (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_consent', 'lead', $id, $lead['name'] . (!empty($_POST['on']) ? ' (autorizou)' : ' (removida)'));
        flash('ok', !empty($_POST['on'])
            ? 'Autorização registrada.' . ($lead['status'] === 'lost' ? ' Agora este lead é elegível: use "Programar reativação".' : '')
            : 'Autorização removida.' . ($lead['status'] === 'lost' ? ' A reativação deste lead foi cancelada.' : ''));
        redirect('/admin/crm/' . $id);
    }

    public function doNotContact(int $id): void
    {
        $lead = $this->open($id);
        $on = !empty($_POST['on']);
        Leads::setDoNotContact($lead, $on, (int) Auth::id());
        Audit::log((int) Auth::id(), 'lead_dnc', 'lead', $id, $lead['name'] . ($on ? ' (não contatar)' : ' (liberado)'));
        flash('ok', $on ? 'Marcado como "Não contatar". O retorno agendado foi cancelado.' : 'Contato liberado novamente.');
        redirect('/admin/crm/' . $id);
    }

    /** Exclusão definitiva do lead e da linha do tempo dele (pedido de exclusão pela LGPD). */
    public function destroy(int $id): void
    {
        $lead = $this->open($id);
        if (empty($_POST['confirm'])) {
            flash('err', 'Marque a caixinha para confirmar a exclusão.');
            redirect('/admin/crm/' . $id);
        }
        DB::run('DELETE FROM leads WHERE id = ?', [$id]);
        Audit::log((int) Auth::id(), 'lead_delete', 'lead', $id, $lead['name']);
        flash('ok', 'Lead excluído, com toda a linha do tempo.');
        redirect('/admin/crm');
    }

    // ------------------------------------------------------------------ configurar origens e etapas

    public function config(): void
    {
        View::render('admin/crm/config', [
            'title' => 'CRM · Configurar', 'sources' => Leads::allSources(), 'stages' => Leads::allStages(),
            'reasons' => Leads::allLostReasons(), 'recDays' => Leads::recoveryDays(), 'minDays' => Leads::MIN_RECOVERY_DAYS,
            'homeView' => Leads::homeView(),
        ], 'admin');
    }

    public function addSource(): void
    {
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $this->fail(['Informe o nome da origem (2 a 60 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_sources WHERE LOWER(name) = ?', [mb_strtolower($name)])) {
            $this->fail(['Já existe uma origem com esse nome.'], '/admin/crm/configurar');
        }
        $next = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lead_sources');
        $id = DB::insert("INSERT INTO lead_sources (name, kind, sort_order, active) VALUES (?, 'other', ?, 1)", [$name, $next]);
        Audit::log((int) Auth::id(), 'lead_source', 'lead_source', $id, 'nova: ' . $name);
        flash('ok', 'Origem criada.');
        redirect('/admin/crm/configurar');
    }

    public function saveSource(int $id): void
    {
        $row = DB::one('SELECT * FROM lead_sources WHERE id = ?', [$id]);
        if (!$row) {
            redirect('/admin/crm/configurar');
        }
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $this->fail(['Informe o nome da origem (2 a 60 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_sources WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), $id])) {
            $this->fail(['Já existe uma origem com esse nome.'], '/admin/crm/configurar');
        }
        $active = empty($_POST['active']) ? 0 : 1;
        DB::run('UPDATE lead_sources SET name = ?, active = ? WHERE id = ?', [$name, $active, $id]);
        Audit::log((int) Auth::id(), 'lead_source', 'lead_source', $id, $row['name'] . ' -> ' . $name . ($active ? '' : ' (desativada)'));
        flash('ok', 'Origem salva.');
        redirect('/admin/crm/configurar');
    }

    public function addStage(): void
    {
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $this->fail(['Informe o nome da etapa (2 a 60 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_stages WHERE LOWER(name) = ?', [mb_strtolower($name)])) {
            $this->fail(['Já existe uma etapa com esse nome.'], '/admin/crm/configurar');
        }
        // Entra antes de "Convertido" e "Perdido": as etapas finais continuam no fim do funil
        $last = (int) DB::val("SELECT COALESCE(MAX(sort_order), 0) FROM lead_stages WHERE kind = 'open'");
        DB::tx(function () use ($name, $last) {
            DB::run("UPDATE lead_stages SET sort_order = sort_order + 1 WHERE kind <> 'open' AND sort_order > ?", [$last]);
            $id = DB::insert("INSERT INTO lead_stages (name, kind, sort_order, active) VALUES (?, 'open', ?, 1)", [$name, $last + 1]);
            Audit::log((int) Auth::id(), 'lead_stage_cfg', 'lead_stage', $id, 'nova: ' . $name);
        });
        flash('ok', 'Etapa criada, antes de "Convertido".');
        redirect('/admin/crm/configurar');
    }

    public function saveStage(int $id): void
    {
        $row = DB::one('SELECT * FROM lead_stages WHERE id = ?', [$id]);
        if (!$row) {
            redirect('/admin/crm/configurar');
        }
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $this->fail(['Informe o nome da etapa (2 a 60 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_stages WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), $id])) {
            $this->fail(['Já existe uma etapa com esse nome.'], '/admin/crm/configurar');
        }
        $active = empty($_POST['active']) ? 0 : 1;
        if ($row['kind'] !== 'open') {
            $active = 1; // Convertido e Perdido são fixas
        }
        if (!$active && (int) DB::val("SELECT COUNT(*) FROM leads WHERE stage_id = ? AND status = 'open'", [$id]) > 0) {
            $this->fail(['Há leads nesta etapa. Mova-os para outra etapa antes de desativar.'], '/admin/crm/configurar');
        }
        $order = (int) ($_POST['sort_order'] ?? $row['sort_order']);
        if ($row['kind'] === 'open') {
            // as etapas em andamento ficam sempre antes de Convertido e Perdido
            $maxOpen = (int) DB::val("SELECT MIN(sort_order) FROM lead_stages WHERE kind <> 'open'") - 1;
            $order = max(1, min(max(1, $maxOpen), $order));
        } else {
            $order = (int) $row['sort_order'];
        }
        $potential = $row['kind'] === 'open' && !empty($_POST['potential']) ? 1 : 0;
        DB::run('UPDATE lead_stages SET name = ?, active = ?, sort_order = ?, potential = ? WHERE id = ?', [$name, $active, $order, $potential, $id]);
        Audit::log((int) Auth::id(), 'lead_stage_cfg', 'lead_stage', $id, $row['name'] . ' -> ' . $name . ($active ? '' : ' (desativada)'));
        flash('ok', 'Etapa salva.');
        redirect('/admin/crm/configurar');
    }

    // ------------------------------------------------------------------ motivos de perda e cadência da reativação (09.2)

    public function addReason(): void
    {
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $this->fail(['Informe o motivo de perda (2 a 80 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_lost_reasons WHERE LOWER(name) = ?', [mb_strtolower($name)])) {
            $this->fail(['Já existe um motivo com esse nome.'], '/admin/crm/configurar');
        }
        $next = (int) DB::val('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM lead_lost_reasons');
        $id = DB::insert('INSERT INTO lead_lost_reasons (name, sort_order, active) VALUES (?, ?, 1)', [$name, $next]);
        Audit::log((int) Auth::id(), 'lead_reason', 'lead_reason', $id, 'novo: ' . $name);
        flash('ok', 'Motivo de perda criado.');
        redirect('/admin/crm/configurar');
    }

    public function saveReason(int $id): void
    {
        $row = DB::one('SELECT * FROM lead_lost_reasons WHERE id = ?', [$id]);
        if (!$row) {
            redirect('/admin/crm/configurar');
        }
        $name = input('name');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $this->fail(['Informe o motivo de perda (2 a 80 letras).'], '/admin/crm/configurar');
        }
        if (DB::val('SELECT id FROM lead_lost_reasons WHERE LOWER(name) = ? AND id <> ?', [mb_strtolower($name), $id])) {
            $this->fail(['Já existe um motivo com esse nome.'], '/admin/crm/configurar');
        }
        $active = empty($_POST['active']) ? 0 : 1;
        if (!$active && (int) DB::val('SELECT COUNT(*) FROM lead_lost_reasons WHERE active = 1 AND id <> ?', [$id]) === 0) {
            $this->fail(['Precisa sobrar pelo menos um motivo de perda ativo.'], '/admin/crm/configurar');
        }
        DB::run('UPDATE lead_lost_reasons SET name = ?, active = ? WHERE id = ?', [$name, $active, $id]);
        Audit::log((int) Auth::id(), 'lead_reason', 'lead_reason', $id, $row['name'] . ' -> ' . $name . ($active ? '' : ' (desativado)'));
        flash('ok', 'Motivo salvo.');
        redirect('/admin/crm/configurar');
    }

    /** Dias da cadência de reativação: de 1 a 5 números, cada um de 20 a 365, separados por vírgula. */
    /** Tela que abre quando se clica em "CRM" no menu: Funil (padrão), Follow-up ou Lista. É só uma preferência de abertura; todas continuam nas abas. */
    public function saveView(): void
    {
        $v = input('view');
        if (!isset(Leads::HOME_VIEWS[$v])) {
            $this->fail(['Escolha Funil, Follow-up ou Lista.'], '/admin/crm/configurar');
        }
        Settings::set('crm_default_view', $v);
        $names = ['list' => 'a Lista', 'follow' => 'o Follow-up', 'board' => 'o Funil'];
        Audit::log((int) Auth::id(), 'lead_view', 'setting', null, ucfirst(substr($names[$v], 2)));
        flash('ok', 'Pronto: o item "CRM" do menu agora abre ' . $names[$v] . '.');
        redirect('/admin/crm/configurar');
    }

    public function saveCadence(): void
    {
        $raw = input('days');
        $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $min = Leads::MIN_RECOVERY_DAYS;
        $days = [];
        foreach ($parts as $p) {
            if (!ctype_digit($p) || (int) $p < $min || (int) $p > 365) {
                $this->fail(['Cadência: use números de ' . $min . ' a 365, separados por vírgula (ex.: 20, 30, 45). O primeiro contato só pode acontecer a partir de ' . $min . ' dias.'], '/admin/crm/configurar');
            }
            $days[] = (int) $p;
        }
        $days = array_values(array_unique($days));
        sort($days);
        if (!$days || count($days) > 5) {
            $this->fail(['Cadência: informe de 1 a 5 prazos (ex.: 20, 30, 45).'], '/admin/crm/configurar');
        }
        Settings::set('lead_recovery_days', implode(',', $days));
        Audit::log((int) Auth::id(), 'lead_cadence', 'setting', null, implode(', ', $days) . ' dias');
        flash('ok', 'Cadência salva: ' . implode(', ', $days) . ' dias depois da perda. Vale para as próximas tentativas.');
        redirect('/admin/crm/configurar');
    }
}
