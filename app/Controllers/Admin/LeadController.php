<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use App\Services\Audit;
use App\Services\Leads;
use App\Services\PersonalData;
use App\Services\Responsibles;

/** CRM / Leads (Atualização 09.1): lista, cadastro, ficha com linha do tempo e listas editáveis (origens e etapas). */
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
            'p' => isset(Leads::periods()[$g('p')]) ? $g('p') : '',
            'f' => in_array($g('f'), ['late', 'today', 'hot', 'dnc'], true) ? $g('f') : '',
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
            'sources' => DB::all('SELECT id, name, active FROM lead_sources ORDER BY active DESC, sort_order ASC, name ASC'),
            'stages' => Leads::allStages(),
            'responsibles' => Responsibles::all(),
            'sports' => DB::all('SELECT id, name FROM sports ORDER BY active DESC, name ASC'),
            'plans' => DB::all('SELECT id, name FROM plans ORDER BY active DESC, name ASC'),
        ], 'admin');
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

        // Próximo retorno: uma data, ou "em N dias". Lead "Não contatar" não recebe novo retorno.
        $nextOn = null;
        $nextType = input('next_type');
        $nextType = isset(Leads::ACTIONS[$nextType]) ? $nextType : 'followup';
        if ((int) $lead['do_not_contact'] === 0) {
            $nd = input('next_date');
            $days = input('next_days');
            if ($nd !== '') {
                if (!self::validDate($nd) || $nd < today()) {
                    $err[] = 'A data do próximo retorno precisa ser hoje ou uma data futura.';
                } else {
                    $nextOn = $nd;
                }
            } elseif ($days !== '') {
                if (!ctype_digit($days) || (int) $days < 0 || (int) $days > 365) {
                    $err[] = 'Próximo retorno em quantos dias? Digite um número de 0 a 365.';
                } else {
                    $nextOn = date('Y-m-d', strtotime(today() . ' +' . (int) $days . ' days'));
                }
            }
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

    public function stage(int $id): void
    {
        $lead = $this->open($id);
        if (Leads::moveStage($lead, (int) ($_POST['stage_id'] ?? 0), (int) Auth::id())) {
            Audit::log((int) Auth::id(), 'lead_stage', 'lead', $id, $lead['name']);
            flash('ok', 'Etapa atualizada.');
        } else {
            flash('err', 'Escolha uma etapa diferente da atual (Convertido e Perdido têm botões próprios, nas próximas atualizações).');
        }
        redirect('/admin/crm/' . $id);
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
        flash('ok', !empty($_POST['on']) ? 'Autorização registrada.' : 'Autorização removida.');
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
        View::render('admin/crm/config', ['title' => 'CRM · Configurar', 'sources' => Leads::allSources(), 'stages' => Leads::allStages()], 'admin');
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
        DB::run('UPDATE lead_stages SET name = ?, active = ?, sort_order = ? WHERE id = ?', [$name, $active, $order, $id]);
        Audit::log((int) Auth::id(), 'lead_stage_cfg', 'lead_stage', $id, $row['name'] . ' -> ' . $name . ($active ? '' : ' (desativada)'));
        flash('ok', 'Etapa salva.');
        redirect('/admin/crm/configurar');
    }
}
