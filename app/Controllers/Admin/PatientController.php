<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Upload;
use App\Core\View;
use App\Services\Athletes;
use App\Services\Audit;
use App\Services\Consultations;
use App\Services\Insights;
use App\Services\Levels;
use App\Services\Payments;
use App\Services\PersonalData;
use App\Services\Plans;
use App\Services\Points;
use App\Services\Ranking;
use App\Services\Responsibles;
use App\Services\Renewal;
use App\Services\Settings;
use App\Services\Streak;

final class PatientController
{
    /** Abas da ficha do paciente (?aba=). A primeira é a padrão. */
    public const ABAS = ['resumo' => 'Resumo', 'plano' => 'Plano e financeiro', 'acompanhamento' => 'Acompanhamento', 'app' => 'App do paciente'];

    /** Endereço da ficha já na aba certa (depois de salvar algo, o admin volta para onde estava). */
    public static function ficha(int $id, string $aba = 'resumo', string $ancora = ''): string
    {
        $url = '/admin/pacientes/' . $id;
        if ($aba !== 'resumo' && isset(self::ABAS[$aba])) {
            $url .= '?aba=' . $aba;
        }
        return $url . ($ancora !== '' ? '#' . $ancora : '');
    }

    /**
     * Ordenações possíveis da lista: chave => [rótulo, texto crescente, texto decrescente, sentido inicial,
     * expressão SQL, "vazio por último"]. A chave vem da URL, então só estas valem (lista fechada).
     */
    public static function sortOptions(): array
    {
        return [
            'name'     => ['Nome', 'A a Z', 'Z a A', 'asc', 'u.name', false],
            'status'   => ['Status', 'Ativos primeiro', 'Desativados primeiro', 'asc', "CASE WHEN u.status = 'inactive' THEN 3 WHEN u.renewal_date IS NULL THEN 2 WHEN u.renewal_date < ? THEN 1 ELSE 0 END", false],
            'plan'     => ['Plano', 'do mais curto ao mais longo', 'do mais longo ao mais curto', 'asc', 'pl.duration_days', true],
            'renewal'  => ['Vencimento', 'mais próximo primeiro', 'mais distante primeiro', 'asc', 'u.renewal_date', true],
            'consults' => ['Consultas feitas', 'menos primeiro', 'mais primeiro', 'asc', 'done_count', false],
            'resp'     => ['Responsável', 'A a Z', 'Z a A', 'asc', 'rs.name', true],
        ];
    }

    /** Faixas de vencimento do filtro (dias até vencer). */
    public static function dueBuckets(): array
    {
        return [
            'expired' => 'Vencidos',
            'd7'      => 'Vencem em 0 a 7 dias',
            'd15'     => 'Vencem em 8 a 15 dias',
            'd30'     => 'Vencem em 16 a 30 dias',
            'd60'     => 'Vencem em 31 a 60 dias',
            'd60p'    => 'Vencem em mais de 60 dias',
            'nodate'  => 'Sem data de vencimento',
        ];
    }

    private static function inDueBucket(?int $days, string $bucket): bool
    {
        return match ($bucket) {
            'expired' => $days !== null && $days < 0,
            'd7'      => $days !== null && $days >= 0 && $days <= 7,
            'd15'     => $days !== null && $days >= 8 && $days <= 15,
            'd30'     => $days !== null && $days >= 16 && $days <= 30,
            'd60'     => $days !== null && $days >= 31 && $days <= 60,
            'd60p'    => $days !== null && $days > 60,
            'nodate'  => $days === null,
            default   => true,
        };
    }

    /** Atalhos rápidos do alto da lista (cada um mostra a quantidade). A chave vai na URL como ?f= */
    public static function quickFilters(): array
    {
        return ['all' => 'Todos', 'active' => 'Ativos', 'expired' => 'Inativos', 'late' => 'Em atraso', 'soon' => 'Renovação próxima'];
    }

    /** Outros atalhos (dentro de "Mais filtros"). Os links antigos do Painel continuam funcionando. */
    public static function moreFilters(): array
    {
        return ['green' => 'Em dia', 'yellow' => 'Vencem logo', 'red' => 'Críticos', 'away' => 'Sumidos', 'noapp' => 'Sem acesso ao app', 'inactive' => 'Desativados',
                'athletes' => 'Atletas', 'compete' => 'Competem', 'nocoach' => 'Sem assessoria', 'incomplete' => 'Cadastro incompleto'];
    }

    /**
     * Um paciente entra no atalho? Definições:
     *  Ativos = plano em dia · Inativos = plano vencido e não renovado
     *  Em atraso = plano vencido OU parcela pendente já vencida · Renovação próxima = vence de hoje até o limite do selo amarelo.
     */
    private static function matchesQuick(string $f, array $r, int $yellowDays): bool
    {
        $active = $r['status'] === 'active';
        $isAthlete = (int) ($r['is_athlete'] ?? 0) === 1;
        return match ($f) {
            'active'     => $r['plan_status']['key'] === 'active',
            'expired'    => $r['plan_status']['key'] === 'expired',
            'late'       => $r['plan_status']['key'] === 'expired' || !empty($r['late']),
            'soon'       => $active && $r['renew']['days'] !== null && $r['renew']['days'] >= 0 && $r['renew']['days'] <= $yellowDays,
            'inactive'   => $r['status'] === 'inactive',
            'green', 'yellow', 'red' => $active && $r['renew']['seal'] === $f,
            'away'       => $active && $r['away'],
            'noapp'      => $r['noapp'],
            'incomplete' => PersonalData::incomplete($r),
            'athletes'   => $isAthlete,
            'compete'    => $isAthlete && (int) $r['competes'] === 1,
            'nocoach'    => $isAthlete && (int) $r['has_coach'] === 0,
            default      => true,
        };
    }

    public function index(): void
    {
        // Parâmetros da URL: só texto simples (?f[]=x e parecidos viram vazio, sem aviso nem filtro estranho)
        $get = fn(string $k, string $d = ''): string => is_string($_GET[$k] ?? null) ? $_GET[$k] : $d;
        $q = trim($get('q'));
        $filter = $get('f', 'all');
        if (!isset(self::quickFilters()[$filter]) && !isset(self::moreFilters()[$filter])) {
            $filter = 'all';
        }
        $perOptions = [10, 25, 50, 100];
        $perPage = (int) $get('pp', '25');
        if (!in_array($perPage, $perOptions, true)) {
            $perPage = 25;
        }
        $page = max(1, (int) $get('pg', '1'));
        $sportId = (int) $get('s');
        $planFilter = (int) $get('plan');
        $stFilter = $get('st');
        $dueFilter = $get('v');
        $respFilter = $get('r');
        $consFilter = $get('c');
        if (!in_array($stFilter, ['active', 'expired', 'disabled', 'nodate'], true)) {
            $stFilter = '';
        }
        if (!isset(self::dueBuckets()[$dueFilter])) {
            $dueFilter = '';
        }
        if ($respFilter !== 'none') {
            $respFilter = ctype_digit($respFilter) && (int) $respFilter > 0 ? (string) (int) $respFilter : '';
        }
        if (!in_array($consFilter, ['left', 'full', 'zero', 'noquota'], true)) {
            $consFilter = '';
        }

        // Ordenação: "chave_sentido" (ex.: renewal_asc). Chave desconhecida volta para nome A a Z.
        $sorts = self::sortOptions();
        $ord = $get('ord', 'name_asc');
        [$sortKey, $sortDir] = array_pad(explode('_', $ord, 2), 2, 'asc');
        if (!isset($sorts[$sortKey])) {
            $sortKey = 'name';
        }
        $sortDir = $sortDir === 'desc' ? 'desc' : 'asc';
        $ord = $sortKey . '_' . $sortDir;

        $sql = "SELECT u.id, u.name, u.email, u.phone, u.avatar, u.status, u.renewal_date, u.last_seen_at, u.responsible_id, u.app_access,
                       u.cpf, u.addr_street, u.addr_city,
                       pl.name AS plan_name, COALESCE(bl.price, pl.price) AS plan_value,
                       CASE WHEN bl.price IS NOT NULL THEN bl.currency ELSE 'BRL' END AS plan_currency,
                       pl.consultations_included AS quota,
                       rs.name AS responsible_name, rs.active AS responsible_active,
                       ap.is_athlete, ap.competes, ap.has_coach, ap.coach_name, ap.event_name, ap.event_date,
                       " . Consultations::DONE_SQL . " AS done_count
                  FROM users u
                  LEFT JOIN plans pl ON pl.id = u.plan_id
                  LEFT JOIN patient_billing bl ON bl.user_id = u.id
                  LEFT JOIN athlete_profiles ap ON ap.user_id = u.id
                  LEFT JOIN responsibles rs ON rs.id = u.responsible_id
                 WHERE u.role = 'patient'";
        $params = [];
        if ($sportId > 0) {
            $sql .= ' AND ap.is_athlete = 1 AND EXISTS (SELECT 1 FROM user_sports us WHERE us.user_id = u.id AND us.sport_id = ?)';
            $params[] = $sportId;
        }
        if ($planFilter > 0) {
            $sql .= ' AND u.plan_id = ?';
            $params[] = $planFilter;
        }
        if ($respFilter === 'none') {
            $sql .= ' AND u.responsible_id IS NULL';
        } elseif ($respFilter !== '') {
            $sql .= ' AND u.responsible_id = ?';
            $params[] = (int) $respFilter;
        }
        if ($q !== '') {
            // Nome e e-mail; e, se tiver 3 números ou mais, também CPF e WhatsApp (só os dígitos)
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            if (strlen($digits) >= 3) {
                $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?
                          OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(u.phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '') LIKE ?)";
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $digits . '%';
                $params[] = '%' . $digits . '%';
            } else {
                $sql .= ' AND (u.name LIKE ? OR u.email LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }
        // ORDER BY só usa expressões da lista fechada acima (nada vem direto da URL)
        [, , , , $expr, $nullsLast] = $sorts[$sortKey];
        $sql .= ' ORDER BY ' . ($nullsLast ? '(' . $expr . ' IS NULL) ASC, ' : '') . $expr . ' ' . strtoupper($sortDir) . ', u.name ASC';
        if ($sortKey === 'status') {
            $params[] = today();
        }

        $weekAgo = date('Y-m-d H:i:s', strtotime('-7 day'));
        Payments::syncCards();
        $lateIds = array_fill_keys(array_map('intval', array_column(DB::all(
            "SELECT DISTINCT p.user_id AS id FROM payment_installments i JOIN payments p ON p.id = i.payment_id WHERE i.status = 'pending' AND i.due_on < ?",
            [today()]
        ), 'id')), true);
        $yellowDays = (int) Settings::get('warn_yellow_days', 15);

        // 1) Filtros de fora dos atalhos (status, vencimento, consultas). Dá a base para contar os atalhos.
        $base = [];
        foreach (DB::all($sql, $params) as $r) {
            $r['renew'] = Renewal::info($r['renewal_date']);
            $r['noapp'] = (int) $r['app_access'] === 0; // importado, acesso ainda não liberado
            $r['away'] = !$r['noapp'] && (empty($r['last_seen_at']) || $r['last_seen_at'] < $weekAgo);
            $r['plan_status'] = Renewal::planStatus((string) $r['status'], $r['renewal_date']);
            $r['cons'] = Consultations::summary((int) $r['done_count'], $r['quota']);
            $r['late'] = isset($lateIds[(int) $r['id']]);
            if ($stFilter !== '' && $r['plan_status']['key'] !== $stFilter) {
                continue;
            }
            // Faixas de vencimento só olham contas ativas (conta desativada não precisa de plano de ação)
            if ($dueFilter !== '' && ($r['status'] !== 'active' || !self::inDueBucket($r['renew']['days'], $dueFilter))) {
                continue;
            }
            if ($consFilter === 'left' && !($r['cons']['quota'] !== null && !$r['cons']['full'])) {
                continue;
            }
            if ($consFilter === 'full' && !$r['cons']['full']) {
                continue;
            }
            if ($consFilter === 'zero' && $r['cons']['done'] !== 0) {
                continue;
            }
            if ($consFilter === 'noquota' && $r['cons']['quota'] !== null) {
                continue;
            }
            $base[] = $r;
        }

        // 2) Quantos pacientes cada atalho mostraria (com a busca e os outros filtros já aplicados)
        $counts = array_fill_keys(array_keys(self::quickFilters()), 0);
        foreach ($base as $r) {
            foreach ($counts as $k => $n) {
                if (self::matchesQuick($k, $r, $yellowDays)) {
                    $counts[$k]++;
                }
            }
        }

        // 3) O atalho escolhido
        $list = array_values(array_filter($base, fn($r) => self::matchesQuick($filter, $r, $yellowDays)));

        // 4) Paginação
        $total = count($list);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $shown = array_slice($list, $offset, $perPage);

        // 5) Indicadores do alto (mês atual contra o mês passado até o mesmo dia; mesmas definições do Painel)
        $ins = Insights::build('mes', '6');
        $soon = 0;
        $soon7 = 0;
        foreach (DB::all("SELECT renewal_date FROM users WHERE role = 'patient' AND status = 'active' AND renewal_date IS NOT NULL") as $x) {
            $dd = (int) days_until($x['renewal_date']);
            if ($dd >= 0 && $dd <= $yellowDays) {
                $soon++;
            }
            if ($dd >= 0 && $dd <= 7) {
                $soon7++;
            }
        }
        View::render('admin/patients/index', [
            'title' => 'Pacientes', 'list' => $shown, 'q' => $q, 'filter' => $filter,
            'cards' => ['ins' => $ins, 'soon' => $soon, 'soon7' => $soon7, 'yellow' => $yellowDays],
            'counts' => $counts, 'quick' => self::quickFilters(), 'more' => self::moreFilters(),
            'total' => $total, 'page' => $page, 'pages' => $pages, 'perPage' => $perPage, 'perOptions' => $perOptions, 'offset' => $offset,
            'sportId' => $sportId, 'sports' => Athletes::sports(false),
            'planFilter' => $planFilter, 'planOptions' => Plans::withCounts(),
            'stFilter' => $stFilter, 'dueFilter' => $dueFilter, 'respFilter' => $respFilter, 'consFilter' => $consFilter,
            'dueBuckets' => self::dueBuckets(),
            'sorts' => $sorts, 'ord' => $ord, 'sortKey' => $sortKey, 'sortDir' => $sortDir,
            'responsibles' => Responsibles::all(), 'respChoices' => Responsibles::choices(),
            'sportNames' => Athletes::namesFor(array_column($shown, 'id')),
        ], 'admin');
    }

    /** Define o responsável de vários pacientes de uma vez (marcados na lista). */
    public function assign(): void
    {
        $back = safe_back((string) ($_POST['back'] ?? ''), '/admin/pacientes');
        $ids = $_POST['ids'] ?? [];
        $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0))) : [];
        $raw = (string) ($_POST['responsible_id'] ?? '');
        if (!$ids) {
            flash('err', 'Marque pelo menos um paciente na lista.');
            redirect($back);
        }
        if ($raw === '' || !ctype_digit($raw)) {
            flash('err', 'Escolha o responsável (ou "Sem responsável").');
            redirect($back);
        }
        $rid = (int) $raw;
        if ($rid > 0 && !Responsibles::isActive($rid)) {
            flash('err', 'Esse responsável não existe ou está desativado.');
            redirect($back);
        }
        $n = Responsibles::assign($ids, $rid);
        $label = $rid > 0 ? (string) DB::val('SELECT name FROM responsibles WHERE id = ?', [$rid]) : 'sem responsável';
        Audit::log((int) Auth::id(), 'patient_assign', 'responsible', $rid ?: null, count($ids) . ' paciente(s) -> ' . $label);
        flash('ok', $rid > 0
            ? count($ids) . (count($ids) === 1 ? ' paciente agora é de ' : ' pacientes agora são de ') . $label . '.'
            : 'Responsável removido de ' . count($ids) . (count($ids) === 1 ? ' paciente.' : ' pacientes.'));
        redirect($back);
    }

    /** Registra consulta(s) presencial(is) já realizada(s). */
    public function addConsultation(int $id): void
    {
        $this->find($id);
        $err = Consultations::add(
            $id,
            input('held_on') ?: today(),
            input('note'),
            (int) ($_POST['qty'] ?? 1) ?: 1,
            (int) Auth::id()
        );
        if ($err !== null) {
            flash('err', $err);
            redirect(self::ficha($id, 'acompanhamento', 'consultas'));
        }
        $qty = max(1, (int) ($_POST['qty'] ?? 1));
        Audit::log((int) Auth::id(), 'consultation_add', 'user', $id, $qty . ' consulta(s) em ' . (input('held_on') ?: today()));
        flash('ok', $qty === 1 ? 'Consulta registrada.' : $qty . ' consultas registradas.');
        redirect(self::ficha($id, 'acompanhamento', 'consultas'));
    }

    /** Apaga uma consulta registrada por engano. */
    public function removeConsultation(int $id): void
    {
        $row = Consultations::remove($id);
        if (!$row) {
            flash('err', 'Consulta não encontrada.');
            redirect('/admin/pacientes');
        }
        Audit::log((int) Auth::id(), 'consultation_delete', 'user', (int) $row['user_id'], 'consulta de ' . $row['held_on']);
        flash('ok', 'Consulta apagada.');
        redirect(self::ficha((int) $row['user_id'], 'acompanhamento', 'consultas'));
    }

    public function create(): void
    {
        View::render('admin/patients/create', [
            'title' => 'Novo paciente',
            'password' => temp_password(),
            'defaultRenewal' => date('Y-m-d', strtotime('+30 day')),
            'plans' => Plans::active(),
            'fees' => Payments::feeTable(),
            'respChoices' => Responsibles::choices(),
        ], 'admin');
    }

    /** Data de nascimento válida: existe no calendário, não é futura e não é anterior a 1900. */
    private static function validBirth(string $d): bool
    {
        return Payments::validDate($d) && $d <= today() && $d >= '1900-01-01';
    }

    /** Lê do formulário os dados pessoais (sexo, CPF, Instagram, endereço). Devolve [valores limpos, erros]. */
    private static function readPersonalData(): array
    {
        $in = [];
        foreach (PersonalData::COLUMNS as $k) {
            $in[$k] = input($k);
        }
        return PersonalData::clean($in);
    }

    public function store(): void
    {
        $name = input('name');
        $email = mb_strtolower(input('email'));
        $phone = input('phone');
        $goal = input('goal');
        $renewal = input('renewal_date');
        $joined = input('joined_on') ?: today();
        $birth = input('birth_date');
        $optin = !empty($_POST['msg_optin']);
        [$pd, $pdErrors] = self::readPersonalData();
        $password = input('password');
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $custom = parse_money((string) ($_POST['plan_price'] ?? ''));
        $customCur = currency_or_brl($_POST['plan_currency'] ?? 'BRL');
        $plan = $planId ? DB::one('SELECT * FROM plans WHERE id = ? AND active = 1', [$planId]) : null;
        $respId = (int) ($_POST['responsible_id'] ?? 0);

        // Pagamento (opcional): "method" ausente ou "none" = só cadastrar. Vazio = esqueceu de escolher.
        $method = array_key_exists('method', $_POST) ? trim((string) $_POST['method']) : 'none';
        $register = $method !== 'none';

        $errors = [];
        if ($planId && !$plan) {
            $errors[] = 'Plano inválido.';
        }
        if ($respId > 0 && !Responsibles::isActive($respId)) {
            $errors[] = 'Responsável inválido ou desativado.';
        }
        if ($custom === false) {
            $errors[] = 'Valor vendido inválido. Use números, por exemplo 450,00.';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        } elseif (DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$email])) {
            $errors[] = 'Já existe um usuário com este e-mail.';
        }
        if ($renewal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal)) {
            $errors[] = 'Data de renovação inválida.';
        }
        if (!Payments::validDate($joined) || $joined > today()) {
            $errors[] = '"Paciente desde" inválido (use uma data de hoje ou do passado).';
        }
        if ($birth !== '' && !self::validBirth($birth)) {
            $errors[] = 'Data de nascimento inválida (use uma data do passado, a partir de 1900).';
        }
        $errors = array_merge($errors, $pdErrors);
        if ($pd['cpf'] !== '' && ($owner = PersonalData::cpfOwner($pd['cpf']))) {
            $errors[] = 'Este CPF já está cadastrado para ' . $owner['name'] . '.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'A senha provisória precisa ter pelo menos 8 caracteres.';
        }

        // Valor da venda: o que foi digitado, ou o valor do plano
        $amount = $custom === false ? null : $custom;
        $payCur = $customCur;
        if ($amount === null && $plan && $plan['price'] !== null) {
            $amount = (float) $plan['price'];
            $payCur = 'BRL';
        }
        $pay = null;
        if ($register) {
            if ($method === '' || !isset(Payments::methods()[$method])) {
                $errors[] = 'Escolha a forma de pagamento (ou "Só cadastrar, sem pagamento agora").';
            } elseif ($custom !== false) {
                if ($amount === null || $amount <= 0) {
                    $errors[] = 'Informe o valor vendido para registrar o pagamento.';
                } else {
                    $parsed = $this->parsePayment($amount, $payCur, 'first_paid');
                    if (is_string($parsed)) {
                        $errors[] = $parsed;
                    } else {
                        $pay = $parsed;
                    }
                }
            }
        }
        if ($errors) {
            keep_old();
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/novo');
        }

        if ($renewal === '' && $plan) {
            $renewal = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' day'));
        }
        // Valor igual ao do plano não é "valor combinado": só guarda quando é diferente (ou em dólar/euro)
        $storeCustom = $custom !== null;
        if ($storeCustom && $plan && $plan['price'] !== null && $customCur === 'BRL' && abs($custom - (float) $plan['price']) < 0.005) {
            $storeCustom = false;
        }
        $id = DB::tx(function () use ($pd, $name, $email, $password, $phone, $goal, $renewal, $joined, $birth, $optin, $plan, $custom, $customCur, $storeCustom, $pay, $amount, $payCur, $respId) {
            $id = DB::insert(
                "INSERT INTO users (role, name, email, password_hash, phone, goal, status, renewal_date, joined_on, birth_date, msg_optin, msg_optin_at, plan_id, responsible_id, must_change_password, created_at)
                 VALUES ('patient', ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null, $goal ?: null, $renewal ?: null, $joined, $birth !== '' ? $birth : null, $optin ? 1 : 0, $optin ? now() : null, $plan ? (int) $plan['id'] : null, $respId > 0 ? $respId : null, now()]
            );
            DB::run('UPDATE users SET ' . implode(' = ?, ', PersonalData::COLUMNS) . ' = ? WHERE id = ?', array_merge(PersonalData::dbValues($pd), [$id]));
            if ($storeCustom) {
                Plans::setCustomPrice($id, $custom, $customCur, 'manual');
            }
            if ($pay !== null && $amount !== null) {
                Payments::create(
                    $id,
                    $plan ? (int) $plan['id'] : null,
                    null,
                    $amount,
                    $payCur,
                    $pay['method'],
                    $pay['installments'],
                    today(),
                    $pay['first_due'],
                    $pay['paid_count'],
                    $pay['received_brl'],
                    (int) Auth::id(),
                    $pay['brand'],
                    $pay['fee_pct']
                );
            }
            return $id;
        });
        Audit::log((int) Auth::id(), 'patient_create', 'user', $id, $name . ($pay ? ' · ' . money((float) $amount, 2, $payCur) . ' em ' . $pay['installments'] . 'x (' . Payments::methodLabel($pay['method']) . ')' : ''));
        $_SESSION['_creds'] = ['name' => $name, 'email' => $email, 'password' => $password];
        flash('ok', 'Paciente criado.' . ($pay ? ' Pagamento e parcelas registrados em Financeiro.' : '') . ' Envie o acesso pelo WhatsApp (mensagem pronta abaixo).');
        redirect('/admin/pacientes/' . $id);
    }

    public function show(int $id): void
    {
        $p = DB::one("SELECT * FROM users WHERE id = ? AND role = 'patient'", [$id]);
        if (!$p) {
            flash('err', 'Paciente não encontrado.');
            redirect('/admin/pacientes');
        }

        $creds = $_SESSION['_creds'] ?? null;
        unset($_SESSION['_creds']);
        $welcome = null;
        if ($creds) {
            $welcome = "Oi, " . first_name($creds['name']) . "! Seu acesso ao No Days Off Club está pronto.\n\n"
                . "Link: " . app_url() . "\nE-mail: " . $creds['email'] . "\nSenha provisória: " . $creds['password'] . "\n\n"
                . "No primeiro acesso você escolhe a sua senha. Bora!";
        }

        $life = Points::lifetime($id);
        $plansList = Plans::active();
        if (!empty($p['plan_id']) && !in_array((int) $p['plan_id'], array_map(fn($x) => (int) $x['id'], $plansList), true)) {
            $cur = DB::one('SELECT * FROM plans WHERE id = ?', [(int) $p['plan_id']]);
            if ($cur) {
                $plansList[] = $cur;
            }
        }
        $planRow = !empty($p['plan_id']) ? DB::one('SELECT * FROM plans WHERE id = ?', [(int) $p['plan_id']]) : null;
        Payments::syncCards();
        $payments = Payments::forUser($id);
        $priceInfo = Plans::priceInfoFor($id);
        $suggest = Plans::renewalSuggestion($id);
        $lastMethod = $payments ? (string) $payments[0]['method'] : 'pix';
        $consSummary = Consultations::summary(Consultations::done($id), $planRow['consultations_included'] ?? null);
        $aba = is_string($_GET['aba'] ?? null) ? $_GET['aba'] : 'resumo';
        if (!isset(self::ABAS[$aba])) {
            $aba = 'resumo';
        }
        View::render('admin/patients/show', [
            'aba' => $aba,
            'consSummary' => $consSummary,
            'consultations' => Consultations::forUser($id),
            'consTotal' => Consultations::total($id),
            'respChoices' => Responsibles::choices(!empty($p['responsible_id']) ? (int) $p['responsible_id'] : null),
            'respName' => !empty($p['responsible_id']) ? DB::val('SELECT name FROM responsibles WHERE id = ?', [(int) $p['responsible_id']]) : null,
            'planStatus' => Renewal::planStatus((string) $p['status'], $p['renewal_date']),
            'plans' => $plansList,
            'planRow' => $planRow,
            'customPrice' => Plans::customPrice($id),
            'priceInfo' => $priceInfo,
            'suggest' => $suggest,
            'payments' => $payments,
            'fees' => Payments::feeTable(),
            'lastMethod' => $lastMethod,
            'renewChoices' => Plans::renewalChoices(),
            'athlete' => Athletes::profile($id),
            'athleteSportIds' => Athletes::sportIds($id),
            'sportChoices' => Athletes::choicesFor($id),
            'title' => $p['name'],
            'p' => $p,
            'welcome' => $welcome,
            'renew' => Renewal::info($p['renewal_date']),
            'renewals' => DB::all(
                'SELECT r.*, pl.name AS plan_name FROM renewals r LEFT JOIN plans pl ON pl.id = r.plan_id
                  WHERE r.user_id = ? ORDER BY r.created_at DESC, r.id DESC LIMIT 8',
                [$id]
            ),
            'balance' => Points::balance($id),
            'monthPts' => Points::monthTotal($id),
            'level' => Levels::forPoints($life),
            'rank' => Ranking::positionOf($id),
            'streak' => Streak::current($id),
            'ledger' => array_map(function ($r) {
                $r['label'] = Points::label($r['rule_key']);
                return $r;
            }, DB::all('SELECT * FROM points_ledger WHERE user_id = ? ORDER BY created_at DESC, id DESC LIMIT 12', [$id])),
            'rules' => DB::all('SELECT rkey, label, points FROM point_rules ORDER BY sort_order'),
            'diet' => DB::one('SELECT * FROM diets WHERE user_id = ?', [$id]),
            'workouts' => DB::all('SELECT id, title, weekday, active FROM workouts WHERE user_id = ? ORDER BY sort_order, id', [$id]),
            'measures' => DB::all('SELECT * FROM measurements WHERE user_id = ? ORDER BY measured_on DESC LIMIT 6', [$id]),
            'lastCheckin' => DB::val('SELECT MAX(checkin_date) FROM checkins WHERE user_id = ?', [$id]),
            'workoutsMonth' => (int) DB::val('SELECT COUNT(*) FROM workout_logs WHERE user_id = ? AND done_on >= ?', [$id, date('Y-m-01')]),
        ], 'admin');
    }

    public function update(int $id): void
    {
        $p = $this->find($id);
        $name = input('name');
        $email = mb_strtolower(input('email'));
        $phone = input('phone');
        $goal = input('goal');
        $status = input('status') === 'inactive' ? 'inactive' : 'active';
        $renewal = input('renewal_date');
        $joined = input('joined_on');
        $birth = input('birth_date');
        $optin = !empty($_POST['msg_optin']);
        [$pd, $pdErrors] = self::readPersonalData();
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $respId = (int) ($_POST['responsible_id'] ?? 0);
        $custom = parse_money((string) ($_POST['plan_price'] ?? ''));
        $customCur = currency_or_brl($_POST['plan_currency'] ?? 'BRL');

        $errors = [];
        if ($respId > 0 && $respId !== (int) ($p['responsible_id'] ?? 0) && !Responsibles::isActive($respId)) {
            $errors[] = 'Responsável inválido ou desativado.';
        }
        if ($planId && !DB::one('SELECT id FROM plans WHERE id = ? AND (active = 1 OR id = ?)', [$planId, (int) ($p['plan_id'] ?? 0)])) {
            $errors[] = 'Plano inválido.';
        }
        if ($custom === false) {
            $errors[] = 'Valor combinado inválido. Use números, por exemplo 450,00.';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'Informe o nome.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail inválido.';
        } elseif (DB::val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [$email, $id])) {
            $errors[] = 'Este e-mail já é de outro usuário.';
        }
        if ($renewal !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $renewal)) {
            $errors[] = 'Data de renovação inválida.';
        }
        if ($joined !== '' && (!Payments::validDate($joined) || $joined > today())) {
            $errors[] = '"Paciente desde" inválido (use uma data de hoje ou do passado).';
        }
        if ($birth !== '' && !self::validBirth($birth)) {
            $errors[] = 'Data de nascimento inválida (use uma data do passado, a partir de 1900).';
        }
        $errors = array_merge($errors, $pdErrors);
        if ($pd['cpf'] !== '' && ($owner = PersonalData::cpfOwner($pd['cpf'], $id))) {
            $errors[] = 'Este CPF já está cadastrado para ' . $owner['name'] . '.';
        }
        if ($errors) {
            foreach ($errors as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/' . $id);
        }
        // Trocou de plano? O valor antigo "mantido até renovar" era do plano anterior: não vira valor combinado.
        if ($planId !== (int) ($p['plan_id'] ?? 0)) {
            $info = Plans::priceInfoFor($id);
            if ($info['source'] === 'reajuste' && $custom !== null && abs($custom - (float) $info['amount']) < 0.005 && $customCur === 'BRL') {
                $custom = null;
            }
        }
        DB::run(
            'UPDATE users SET name = ?, email = ?, phone = ?, goal = ?, status = ?, renewal_date = ?, joined_on = ?, birth_date = ?, plan_id = ?, responsible_id = ? WHERE id = ?',
            [$name, $email, $phone ?: null, $goal ?: null, $status, $renewal ?: null, $joined !== '' ? $joined : null, $birth !== '' ? $birth : null, $planId ?: null, $respId > 0 ? $respId : null, $id]
        );
        // Só mexe nos dados pessoais se o formulário os enviou (uma aba antiga, aberta antes da atualização, não apaga nada)
        $sentPd = false;
        foreach (PersonalData::COLUMNS as $k) {
            $sentPd = $sentPd || array_key_exists($k, $_POST);
        }
        if ($sentPd) {
            DB::run('UPDATE users SET ' . implode(' = ?, ', PersonalData::COLUMNS) . ' = ? WHERE id = ?', array_merge(PersonalData::dbValues($pd), [$id]));
        }
        // Autorização de mensagens: guarda quando foi marcada (e não muda a data se já estava marcada)
        if ($optin && !(int) ($p['msg_optin'] ?? 0)) {
            DB::run('UPDATE users SET msg_optin = 1, msg_optin_at = ? WHERE id = ?', [now(), $id]);
        } elseif (!$optin && (int) ($p['msg_optin'] ?? 0)) {
            DB::run('UPDATE users SET msg_optin = 0, msg_optin_at = NULL WHERE id = ?', [$id]);
        }
        Plans::setCustomPrice($id, $custom, $customCur);
        Audit::log((int) Auth::id(), 'patient_update', 'user', $id, $p['name']);
        flash('ok', 'Dados salvos.');
        redirect('/admin/pacientes/' . $id);
    }

    public function renew(int $id): void
    {
        $p = $this->find($id);
        $days = (int) ($_POST['days'] ?? 0);
        if (!in_array($days, Plans::renewalChoices(), true)) {
            flash('err', 'Escolha um período de renovação.');
            redirect(self::ficha($id, 'plano'));
        }
        $amount = parse_money((string) ($_POST['amount'] ?? ''));
        if ($amount === false) {
            flash('err', 'Valor da renovação inválido. Use números, por exemplo 450,00.');
            redirect(self::ficha($id, 'plano'));
        }
        $cur = currency_or_brl($_POST['currency'] ?? 'BRL');

        // Pagamento (opcional): só é registrado se houver valor. Tudo é conferido ANTES de renovar.
        $pay = null;
        if ($amount !== null) {
            $parsed = $this->parsePayment($amount, $cur, 'first_paid');
            if (is_string($parsed)) {
                flash('err', $parsed);
                redirect(self::ficha($id, 'plano'));
            }
            $pay = $parsed;
        }

        $new = Renewal::renew($id, $days, (int) Auth::id(), $amount, !empty($p['plan_id']) ? (int) $p['plan_id'] : null, $pay, $cur);
        $detail = '+' . $days . ' dias, até ' . $new;
        if ($amount !== null) {
            $detail .= ', ' . money($amount, 2, $cur);
            if ($pay) {
                $detail .= ' (' . Payments::methodLabel($pay['method']) . ', ' . $pay['installments'] . 'x)';
            }
        }
        Audit::log((int) Auth::id(), 'renewal', 'user', $id, $detail);
        flash('ok', 'Renovado até ' . fmt_date($new) . '. O bônus de pontos foi lançado.' . ($pay ? ' Pagamento e parcelas registrados em Financeiro.' : ''));
        redirect(self::ficha($id, 'plano'));
    }

    /** Lê e confere os campos de pagamento do formulário (regra única em Payments::parseForm). */
    private function parsePayment(float $total, string $cur, string $paidKey): array|string
    {
        return Payments::parseForm($total, $cur, $paidKey);
    }

    /** Registrar um pagamento avulso (contratos que já existiam antes do sistema, por exemplo). */
    public function addPayment(int $id): void
    {
        $p = $this->find($id);
        $total = parse_money((string) ($_POST['total'] ?? ''));
        $cur = currency_or_brl($_POST['currency'] ?? 'BRL');
        $soldOn = trim((string) ($_POST['sold_on'] ?? ''));
        if ($soldOn === '') {
            $soldOn = today();
        }
        if ($total === false || $total === null || $total <= 0) {
            flash('err', 'Informe o valor total do pagamento. Use números, por exemplo 1200,00.');
            redirect(self::ficha($id, 'plano'));
        }
        if (!Payments::validDate($soldOn) || $soldOn > today()) {
            flash('err', 'Data da venda inválida (não pode ser no futuro).');
            redirect(self::ficha($id, 'plano'));
        }
        $parsed = $this->parsePayment($total, $cur, 'paid_count');
        if (is_string($parsed)) {
            flash('err', $parsed);
            redirect(self::ficha($id, 'plano'));
        }
        DB::tx(function () use ($id, $p, $total, $cur, $soldOn, $parsed) {
            Payments::create(
                $id,
                !empty($p['plan_id']) ? (int) $p['plan_id'] : null,
                null,
                $total,
                $cur,
                $parsed['method'],
                $parsed['installments'],
                $soldOn,
                $parsed['first_due'],
                $parsed['paid_count'],
                $parsed['received_brl'],
                (int) Auth::id(),
                $parsed['brand'],
                $parsed['fee_pct']
            );
        });
        Audit::log((int) Auth::id(), 'payment_add', 'user', $id, money($total, 2, $cur) . ' em ' . $parsed['installments'] . 'x (' . Payments::methodLabel($parsed['method']) . ')');
        flash('ok', 'Pagamento registrado com ' . $parsed['installments'] . ($parsed['installments'] === 1 ? ' parcela.' : ' parcelas.'));
        redirect(self::ficha($id, 'plano'));
    }

    public function points(int $id): void
    {
        $p = $this->find($id);
        $type = input('type');
        $note = input('note');

        if ($type === 'referral') {
            $rule = Points::rule('referral');
            if (!$rule || (int) $rule['active'] !== 1 || (int) $rule['points'] <= 0) {
                flash('err', 'A regra de indicação está desativada.');
                redirect(self::ficha($id, 'app'));
            }
            Points::add($id, 'referral', (int) $rule['points'], true, null, null, $note ?: 'Indicação que virou paciente', (int) Auth::id());
            Audit::log((int) Auth::id(), 'points_referral', 'user', $id, '+' . $rule['points']);
            flash('ok', '+' . $rule['points'] . ' pontos pela indicação.');
        } else {
            $points = (int) ($_POST['points'] ?? 0);
            if ($points === 0 || abs($points) > 5000 || $note === '') {
                flash('err', 'Informe os pontos (de -5000 a 5000, diferente de 0) e o motivo.');
                redirect(self::ficha($id, 'app'));
            }
            $counts = !empty($_POST['counts_ranking']) && $points > 0;
            Points::add($id, 'manual', $points, $counts, null, null, $note, (int) Auth::id());
            Audit::log((int) Auth::id(), 'points_manual', 'user', $id, $points . ' pts: ' . $note);
            flash('ok', 'Ajuste lançado no extrato.');
        }
        redirect(self::ficha($id, 'app'));
    }

    public function resetPassword(int $id): void
    {
        $p = $this->find($id);
        $pass = temp_password();
        $wasBlocked = (int) ($p['app_access'] ?? 1) === 0;
        // Gerar a senha provisória também libera o acesso de quem veio de importação (app_access = 1)
        DB::run('UPDATE users SET password_hash = ?, must_change_password = 1, app_access = 1 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
        DB::run('DELETE FROM login_attempts WHERE email = ?', [$p['email']]);
        Audit::log((int) Auth::id(), $wasBlocked ? 'access_release' : 'password_reset', 'user', $id, $p['name']);
        $_SESSION['_creds'] = ['name' => $p['name'], 'email' => $p['email'], 'password' => $pass];
        flash('ok', ($wasBlocked ? 'Acesso liberado! ' : '') . 'Senha provisória gerada. Envie pelo WhatsApp (mensagem pronta no alto da página).');
        redirect(self::ficha($id, 'app'));
    }

    public function diet(int $id): void
    {
        $this->find($id);
        $title = input('title') ?: 'Plano alimentar';
        $link = input('link_url');
        $notes = input('notes');

        if ($link !== '' && !preg_match('#^https?://#i', $link)) {
            flash('err', 'O link precisa começar com http:// ou https://');
            redirect(self::ficha($id, 'acompanhamento'));
        }
        $current = DB::one('SELECT * FROM diets WHERE user_id = ?', [$id]);
        $pdf = Upload::pdf($_FILES['pdf'] ?? null, $err);
        if ($err) {
            flash('err', $err);
            redirect(self::ficha($id, 'acompanhamento'));
        }
        $pdfFile = $current['pdf_file'] ?? null;
        if ($pdf) {
            Upload::delete($pdfFile, 'pdf');
            $pdfFile = $pdf;
        }
        if ($current) {
            DB::run('UPDATE diets SET title = ?, link_url = ?, pdf_file = ?, notes = ?, updated_at = ? WHERE user_id = ?', [$title, $link ?: null, $pdfFile, $notes ?: null, now(), $id]);
        } else {
            DB::run('INSERT INTO diets (user_id, title, link_url, pdf_file, notes, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, $title, $link ?: null, $pdfFile, $notes ?: null, now()]);
        }
        Audit::log((int) Auth::id(), 'diet_update', 'user', $id);
        flash('ok', 'Dieta atualizada.');
        redirect(self::ficha($id, 'acompanhamento'));
    }

    public function removeDietPdf(int $id): void
    {
        $this->find($id);
        $current = DB::one('SELECT pdf_file FROM diets WHERE user_id = ?', [$id]);
        if ($current && $current['pdf_file']) {
            Upload::delete($current['pdf_file'], 'pdf');
            DB::run('UPDATE diets SET pdf_file = NULL, updated_at = ? WHERE user_id = ?', [now(), $id]);
        }
        flash('ok', 'PDF removido.');
        redirect(self::ficha($id, 'acompanhamento'));
    }

    /** Perfil esportivo (atleta) preenchido pelo admin. A escolha de "mostrar no perfil" é do paciente e não muda aqui. */
    public function sport(int $id): void
    {
        $this->find($id);
        $parsed = Athletes::parse($id);
        if ($parsed['errors']) {
            foreach ($parsed['errors'] as $e) {
                flash('err', $e);
            }
            redirect('/admin/pacientes/' . $id);
        }
        Athletes::save($id, $parsed, null);
        $d = $parsed['data'];
        Audit::log((int) Auth::id(), 'athlete_update', 'user', $id, $d['is_athlete'] ? count($parsed['sports']) . ' modalidade(s)' . ($d['competes'] ? ', compete' : '') : 'não é atleta');
        flash('ok', 'Perfil esportivo salvo.');
        redirect('/admin/pacientes/' . $id);
    }

    /** Exclusão definitiva (direito de exclusão da LGPD): apaga o paciente e todos os dados dele. */
    public function delete(int $id): void
    {
        $p = $this->find($id);
        if (empty($_POST['confirm'])) {
            flash('err', 'Marque a confirmação para excluir.');
            redirect('/admin/pacientes/' . $id);
        }
        foreach (DB::all('SELECT image FROM posts WHERE user_id = ? AND image IS NOT NULL', [$id]) as $row) {
            Upload::delete($row['image']);
        }
        Upload::delete($p['avatar']);
        $d = DB::one('SELECT pdf_file FROM diets WHERE user_id = ?', [$id]);
        if ($d) {
            Upload::delete($d['pdf_file'], 'pdf');
        }
        foreach (DB::all('SELECT i.proof_file FROM payment_installments i JOIN payments p ON p.id = i.payment_id WHERE p.user_id = ? AND i.proof_file IS NOT NULL', [$id]) as $row) {
            Upload::deleteProof($row['proof_file']);
        }
        DB::run("DELETE FROM reports WHERE target_type = 'post' AND target_id IN (SELECT id FROM posts WHERE user_id = ?)", [$id]);
        DB::run("DELETE FROM reports WHERE target_type = 'comment' AND target_id IN (SELECT id FROM comments WHERE user_id = ?)", [$id]);
        DB::run('DELETE FROM redemptions WHERE user_id = ?', [$id]);
        DB::run('DELETE FROM users WHERE id = ?', [$id]);
        Audit::log((int) Auth::id(), 'patient_delete', 'user', $id, $p['name']);
        flash('ok', 'Paciente e todos os dados dele foram excluídos.');
        redirect('/admin/pacientes');
    }

    private function find(int $id): array
    {
        $p = DB::one("SELECT * FROM users WHERE id = ? AND role = 'patient'", [$id]);
        if (!$p) {
            flash('err', 'Paciente não encontrado.');
            redirect('/admin/pacientes');
        }
        return $p;
    }
}
