<?php
namespace App\Services;

use App\Core\DB;
use App\Core\Xlsx;
use RuntimeException;

/**
 * Importação de pacientes por planilha (modelo oficial NDO).
 *
 * Fluxo: ler o arquivo -> validar TUDO (nada é salvo) -> mostrar a prévia -> só depois do "Confirmar" salvar.
 * Cada linha termina em uma destas situações:
 *   ok   = pronta para importar
 *   warn = importa, mas tem um aviso para você olhar
 *   dup  = duplicada (e-mail já cadastrado, repetido na planilha ou mesma pessoa): NÃO importa
 *   err  = tem erro: NÃO importa (corrija a planilha e envie de novo)
 *
 * As regras de pagamento são as MESMAS do cadastro de paciente (Admin > Pacientes > Novo): parcelas de 1 a 12,
 * cartão em reais cai sozinho, dólar/euro recebido exige o valor em reais, taxa de 0 a 30 etc.
 * Valores (plano, combinado, pagamentos) são só do admin: o paciente nunca vê.
 * Paciente importado NÃO ganha senha nem acesso ao app (users.app_access = 0): o acesso é liberado depois,
 * paciente por paciente, em "Gerar nova senha provisória".
 */
final class PatientImport
{
    public const MAX_ROWS = 500;           // pacientes por arquivo
    public const MAX_BYTES = 2097152;      // 2 MB
    public const SHEET = 'Pacientes';      // nome da aba que o modelo usa
    public const TEMPLATE_VERSION = '4';

    // ---------------------------------------------------------------
    // Colunas do modelo (a fonte única: o modelo .xlsx, a tela de instruções e o leitor usam esta lista)
    // ---------------------------------------------------------------

    /**
     * chave => [rótulo no cabeçalho, grupo, obrigatória (true/false/'cond'), apelidos aceitos, o que preencher, regra, exemplo]
     * Os apelidos são comparados sem acento, sem maiúsculas e sem pontuação.
     */
    public static function columns(): array
    {
        return [
            'name' => ['Nome completo *', 'Dados do paciente', true,
                ['nome', 'nome completo', 'paciente', 'nome do paciente'],
                'Nome e sobrenome do paciente.',
                'De 2 a 120 letras.',
                'Ana Exemplo Silva'],
            'email' => ['E-mail *', 'Dados do paciente', true,
                ['email', 'e mail', 'e-mail do paciente', 'correio eletronico'],
                'E-mail do paciente. É ele que identifica o cadastro, por isso não pode repetir.',
                'E-mail válido. Se já existir no sistema (ou aparecer duas vezes na planilha), a linha é pulada como duplicada.',
                'ana.exemplo@exemplo.invalid'],
            'phone' => ['WhatsApp', 'Dados do paciente', false,
                ['whatsapp', 'whats', 'telefone', 'celular', 'fone', 'whatsapp com ddd'],
                'WhatsApp com DDD, do jeito que preferir.',
                'De 10 a 13 números (DDD + número; o 55 é opcional). Fora disso vira aviso. Vazio é aceito.',
                '(11) 99999-0001'],
            'goal' => ['Objetivo', 'Dados do paciente', false,
                ['objetivo', 'meta'],
                'Objetivo do paciente, em poucas palavras.',
                'Até 120 letras.',
                'Emagrecimento'],
            'birth' => ['Data de nascimento', 'Dados do paciente', false,
                ['data de nascimento', 'nascimento', 'aniversario', 'data de aniversario', 'dt nascimento', 'data nasc'],
                'Dia em que o paciente nasceu. Usada só para o aviso de aniversariantes no painel (só o admin vê).',
                'Data no formato dd/mm/aaaa, do passado e a partir de 1900. Vazio é aceito. A autorização para receber mensagens NÃO vem da planilha: marque na ficha de cada paciente.',
                '15/08/1990'],
            'sex' => ['Sexo', 'Dados do paciente', false,
                ['sexo', 'genero', 'sexo do paciente'],
                'Feminino ou Masculino. Alimenta o gráfico da carteira no painel (só o admin vê).',
                'Feminino, Masculino (também aceita F, M, Mulher ou Homem). Vazio = não informado.',
                'Feminino'],
            'cpf' => ['CPF', 'Dados do paciente', false,
                ['cpf', 'cpf do paciente'],
                'CPF do paciente, usado para emitir recibo e nota fiscal (só o admin vê).',
                'Com ou sem pontos e traço. Precisa ser um CPF válido (11 dígitos). Se já existir no sistema (ou repetir na planilha), a linha é pulada como duplicada.',
                '529.982.247-25'],
            'instagram' => ['Instagram', 'Dados do paciente', false,
                ['instagram', 'insta', 'perfil do instagram', 'arroba'],
                'Perfil do paciente no Instagram.',
                '@usuario ou o link do perfil. Letras, números, ponto e sublinhado (até 30).',
                '@ana.exemplo'],
            'addr_zip' => ['CEP', 'Endereço', false,
                ['cep', 'codigo postal', 'zip'],
                'CEP do endereço.',
                'No Brasil, 8 números (com ou sem traço). Fora do Brasil, texto livre.',
                '01310-100'],
            'addr_street' => ['Rua', 'Endereço', false,
                ['rua', 'endereco', 'logradouro', 'avenida', 'rua avenida'],
                'Rua ou avenida.',
                'Até 120 letras.',
                'Av. Exemplo'],
            'addr_number' => ['Número', 'Endereço', false,
                ['numero', 'numero da casa', 'num'],
                'Número do endereço.',
                'Até 20 caracteres.',
                '100'],
            'addr_complement' => ['Complemento', 'Endereço', false,
                ['complemento', 'compl'],
                'Apartamento, bloco etc.',
                'Até 80 letras.',
                'Apto 12'],
            'addr_district' => ['Bairro', 'Endereço', false,
                ['bairro'],
                'Bairro.',
                'Até 80 letras.',
                'Centro'],
            'addr_city' => ['Cidade', 'Endereço', false,
                ['cidade', 'municipio'],
                'Cidade.',
                'Até 80 letras.',
                'São Paulo'],
            'addr_state' => ['Estado', 'Endereço', false,
                ['estado', 'uf', 'estado uf'],
                'Estado (sigla).',
                'No Brasil, a sigla (SP, RJ, MG...). Fora do Brasil, texto livre.',
                'SP'],
            'addr_country' => ['País', 'Endereço', false,
                ['pais', 'country'],
                'País do endereço.',
                'Vazio = Brasil. Para quem mora fora, escreva o país.',
                ''],
            'plan' => ['Plano', 'Plano e acompanhamento', false,
                ['plano', 'nome do plano'],
                'Nome do plano, igual ao de Admin > Planos (veja a aba Listas).',
                'Tem que existir e estar ativo. Vazio = paciente sem plano.',
                'Trimestral'],
            'renewal' => ['Vencimento do plano', 'Plano e acompanhamento', false,
                ['vencimento do plano', 'vencimento', 'renovacao', 'data de renovacao', 'proxima renovacao', 'data de vencimento', 'vence em'],
                'Dia em que o plano termina (próxima renovação).',
                'Data no formato dd/mm/aaaa. Se tiver plano e esta coluna ficar vazia, o paciente fica "sem data" (aviso). Plano já vencido também gera aviso.',
                '20/02/2027'],
            'joined' => ['Paciente desde', 'Plano e acompanhamento', false,
                ['paciente desde', 'data de entrada', 'entrada', 'inicio do acompanhamento', 'data de inicio', 'inicio', 'cliente desde'],
                'Dia em que o paciente começou o acompanhamento. O painel usa esta data para contar novos pacientes e o crescimento da carteira.',
                'Data no formato dd/mm/aaaa, de hoje ou do passado. Vazio = data da importação (então ele conta como paciente novo hoje: preencha nos antigos).',
                '20/11/2026'],
            'responsible' => ['Responsável', 'Plano e acompanhamento', false,
                ['responsavel', 'estagiaria', 'estagiario', 'responsavel pelo paciente'],
                'Estagiária responsável pelo paciente (veja a aba Listas).',
                'Tem que estar cadastrada e ativa em Admin > Responsáveis. Vazio = sem responsável.',
                'Estagiária A'],
            'sports' => ['Modalidades', 'Perfil esportivo', false,
                ['modalidades', 'modalidade', 'esportes', 'esporte'],
                'Modalidades do paciente, separadas por ponto e vírgula.',
                'Cada uma tem que existir em Admin > Modalidades (veja a aba Listas). Preencher marca o paciente como atleta.',
                'Corrida; Hyrox'],
            'coach' => ['Assessoria', 'Perfil esportivo', false,
                ['assessoria', 'assessoria esportiva', 'treinador', 'coach'],
                'Nome da assessoria esportiva, se tiver.',
                'Só para atletas (precisa de Modalidades). Até 80 letras. "Não" = sem assessoria.',
                'Equipe Exemplo'],
            'event' => ['Prova', 'Perfil esportivo', false,
                ['prova', 'nome da prova', 'proxima prova', 'competicao'],
                'Nome da prova que o paciente vai disputar.',
                'Só para atletas (precisa de Modalidades). Até 100 letras.',
                'Meia Maratona Exemplo'],
            'event_date' => ['Data da prova', 'Perfil esportivo', false,
                ['data da prova', 'data prova', 'dia da prova'],
                'Dia da prova.',
                'Data no formato dd/mm/aaaa. Só para atletas.',
                '15/03/2027'],
            'price' => ['Valor combinado', 'Financeiro (só o admin vê)', false,
                ['valor combinado', 'valor vendido', 'valor', 'valor do contrato', 'valor total', 'preco'],
                'Valor TOTAL vendido ao paciente. Preencha só se for diferente do valor do plano; vazio = usa o valor do plano.',
                'Número maior que zero, como 2400 ou 2.400,50.',
                '2400'],
            'currency' => ['Moeda', 'Financeiro (só o admin vê)', false,
                ['moeda'],
                'Moeda do valor.',
                'BRL (R$), USD (US$) ou EUR (€). Vazio = BRL.',
                'BRL'],
            'method' => ['Forma de pagamento', 'Financeiro (só o admin vê)', false,
                ['forma de pagamento', 'forma pagamento', 'pagamento', 'forma de pgto', 'meio de pagamento'],
                'Como o paciente pagou. VAZIO = só cadastra o paciente, sem registrar pagamento.',
                'Cartão de crédito, Pix, Wise, Dinheiro ou Outro. Se preencher, a Data da venda passa a ser obrigatória.',
                'Pix'],
            'sold_on' => ['Data da venda', 'Financeiro (só o admin vê)', 'cond',
                ['data da venda', 'venda', 'data de venda', 'vendido em', 'data do pagamento'],
                'Dia em que a venda foi fechada (entra no faturamento desse mês).',
                'Obrigatória quando há Forma de pagamento. Não pode ser data futura.',
                '10/11/2026'],
            'installments' => ['Parcelas', 'Financeiro (só o admin vê)', false,
                ['parcelas', 'numero de parcelas', 'n de parcelas', 'qtd parcelas', 'quantidade de parcelas'],
                'Em quantas vezes foi pago.',
                'De 1 a 12. Vazio = 1 (à vista).',
                '1'],
            'first_due' => ['Data da 1ª parcela', 'Financeiro (só o admin vê)', false,
                ['data da 1 parcela', 'data da 1a parcela', 'data da primeira parcela', 'vencimento da 1 parcela', 'primeira parcela', 'vencimento da primeira parcela', '1 parcela'],
                'Vencimento da primeira parcela. As outras vencem de mês em mês.',
                'Data dd/mm/aaaa. Vazio = mesma data da venda.',
                '10/11/2026'],
            'paid' => ['Parcelas já recebidas', 'Financeiro (só o admin vê)', false,
                ['parcelas ja recebidas', 'parcelas recebidas', 'ja recebido', 'parcelas pagas', 'recebidas'],
                'Quantas parcelas já caíram na conta.',
                'De 0 até o total de parcelas. Vazio = 0. Cartão em reais cai sozinho no vencimento (a coluna é ignorada, com aviso).',
                '1'],
            'received_brl' => ['Valor recebido em R$', 'Financeiro (só o admin vê)', false,
                ['valor recebido em r', 'valor recebido em reais', 'recebido em r', 'valor recebido', 'valor em reais'],
                'Só para dólar/euro já recebido: quanto caiu na conta, em reais.',
                'Obrigatório quando a Moeda é USD/EUR e há parcela já recebida.',
                ''],
            'brand' => ['Bandeira do cartão', 'Financeiro (só o admin vê)', false,
                ['bandeira do cartao', 'bandeira'],
                'Bandeira do cartão de crédito.',
                'Visa, Mastercard, Elo, American Express, Hipercard ou Outra. Só vale para cartão em reais.',
                ''],
            'fee' => ['Taxa do cartão (%)', 'Financeiro (só o admin vê)', false,
                ['taxa do cartao', 'taxa do cartao %', 'taxa', 'taxa %', 'taxa da maquininha'],
                'Taxa da maquininha, em %.',
                'De 0 a 30, como 2,5. Vazio = usa a tabela de Financeiro > Taxas do cartão. Só vale para cartão em reais.',
                ''],
            'consultations' => ['Consultas já feitas', 'Consultas presenciais', false,
                ['consultas ja feitas', 'consultas feitas', 'consultas realizadas', 'consultas', 'consultas ja realizadas'],
                'Quantas consultas presenciais o paciente já fez no plano atual.',
                'De 0 a 20. Vazio = 0. Entram com a data da importação (a data real não é conhecida) e a observação "Importado da planilha".',
                '1'],
        ];
    }

    /** Rótulos dos cabeçalhos do modelo, na ordem. */
    public static function headers(): array
    {
        return array_values(array_map(fn($c) => $c[0], self::columns()));
    }

    // ---------------------------------------------------------------
    // Texto: limpeza e comparação
    // ---------------------------------------------------------------

    /** Texto do jeito que veio, sem espaços estranhos, caracteres invisíveis e quebras. */
    public static function clean(string $s): string
    {
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        $s = str_replace(["\xC2\xA0", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D", "\xEF\xBB\xBF"], [' ', '', '', '', ''], $s);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /** Para comparar: minúsculas, sem acento, só letras e números separados por um espaço. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ª' => 'a', 'º' => 'o', '€' => 'eur',
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;
        return trim($s);
    }

    /** Só os números do telefone, sem o 55 do país. null se tiver poucos dígitos para comparar. */
    public static function phoneKey(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($d) >= 12 && str_starts_with($d, '55')) {
            $d = substr($d, 2);
        }
        return strlen($d) >= 10 ? $d : null;
    }

    // ---------------------------------------------------------------
    // Leitura do arquivo
    // ---------------------------------------------------------------

    /**
     * Lê a planilha (.xlsx ou .csv) e devolve ['sheet' => nome, 'ignored' => [colunas desconhecidas], 'rows' => [['n' => linha, 'raw' => [chave => texto]]]].
     * Problemas do arquivo (formato, tamanho, cabeçalho) viram RuntimeException com mensagem em português.
     */
    public static function readFile(string $path, string $originalName): array
    {
        $size = @filesize($path);
        if ($size === false || $size === 0) {
            throw new RuntimeException('O arquivo está vazio.');
        }
        if ($size > self::MAX_BYTES) {
            throw new RuntimeException('O arquivo tem mais de 2 MB. Envie só a aba de pacientes (até ' . self::MAX_ROWS . ' por vez).');
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $sheetName = 'CSV';
        if ($ext === 'xlsx') {
            $x = Xlsx::open($path);
            $sheets = $x->sheets();
            if (!$sheets) {
                throw new RuntimeException('A planilha não tem nenhuma aba.');
            }
            $pick = null;
            foreach ($sheets as $s) {
                if (self::norm($s['name']) === self::norm(self::SHEET)) {
                    $pick = $s;
                    break;
                }
            }
            if ($pick === null) {
                $visible = array_values(array_filter($sheets, fn($s) => empty($s['hidden'])));
                if (count($visible) === 1) {
                    $pick = $visible[0];
                } else {
                    throw new RuntimeException('Não encontrei a aba "' . self::SHEET . '". Use o modelo oficial (Importar > Baixar modelo) e preencha a aba Pacientes.');
                }
            }
            $sheetName = $pick['name'];
            $rows = $x->rows($pick['path'], 20000);
        } elseif ($ext === 'csv' || $ext === 'txt') {
            $rows = self::csvRows($path);
        } elseif ($ext === 'xls' || $ext === 'xlsm' || $ext === 'xlsb' || $ext === 'ods') {
            throw new RuntimeException('Envie o arquivo como Pasta de Trabalho do Excel (.xlsx) ou CSV. Em "Salvar como", escolha essas opções.');
        } else {
            throw new RuntimeException('Formato não aceito. Envie o modelo preenchido (.xlsx) ou um arquivo .csv.');
        }
        $table = self::mapRows($rows);
        $table['sheet'] = $sheetName;
        return $table;
    }

    /** CSV (vírgula, ponto e vírgula ou tab; UTF-8 ou Windows-1252). Retorna o mesmo formato do leitor de xlsx. */
    private static function csvRows(string $path): array
    {
        $s = (string) file_get_contents($path);
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        $first = '';
        foreach (preg_split('/\R/u', $s) ?: [] as $ln) {
            if (trim($ln) !== '') {
                $first = $ln;
                break;
            }
        }
        $counts = [';' => substr_count($first, ';'), ',' => substr_count($first, ','), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delim = array_key_first($counts);
        if ($counts[$delim] === 0) {
            $delim = ';';
        }
        $h = fopen('php://temp', 'r+');
        fwrite($h, $s);
        rewind($h);
        $rows = [];
        $n = 0;
        while (($row = fgetcsv($h, 0, $delim, '"', '')) !== false) {
            $n++;
            if ($n > 20000) {
                break;
            }
            if ($row === [null]) {
                continue;
            }
            $cells = [];
            foreach ($row as $i => $v) {
                $cells[$i] = (string) $v;
            }
            if (trim(implode('', $cells)) === '') {
                continue;
            }
            $rows[] = ['n' => $n, 'cells' => $cells];
        }
        fclose($h);
        return $rows;
    }

    /** Acha a linha de cabeçalho, liga cada coluna a uma chave e monta as linhas de pacientes. */
    private static function mapRows(array $rows): array
    {
        $aliasMap = [];
        foreach (self::columns() as $key => $c) {
            $aliasMap[self::norm($c[0])] = $key;
            foreach ($c[3] as $a) {
                $aliasMap[self::norm($a)] = $key;
            }
        }
        $best = null;
        $bestScore = 0;
        $scan = array_slice($rows, 0, 15, true);
        foreach ($scan as $idx => $row) {
            $keys = [];
            foreach ($row['cells'] as $v) {
                $k = $aliasMap[self::norm(self::clean((string) $v))] ?? null;
                if ($k !== null) {
                    $keys[$k] = true;
                }
            }
            $score = count($keys) + (isset($keys['name']) ? 2 : 0) + (isset($keys['email']) ? 2 : 0);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $idx;
            }
        }
        if ($best === null) {
            throw new RuntimeException('Não encontrei a linha de cabeçalhos. A primeira linha da aba Pacientes precisa ter, no mínimo, "Nome completo" e "E-mail". Use o modelo oficial.');
        }
        $colKey = [];
        $ignored = [];
        $seen = [];
        foreach ($rows[$best]['cells'] as $i => $v) {
            $label = self::clean((string) $v);
            if ($label === '') {
                continue;
            }
            $k = $aliasMap[self::norm($label)] ?? null;
            if ($k === null) {
                $ignored[] = $label;
                continue;
            }
            if (isset($seen[$k])) {
                throw new RuntimeException('A coluna "' . $label . '" aparece mais de uma vez no cabeçalho. Deixe só uma.');
            }
            $seen[$k] = true;
            $colKey[(int) $i] = $k;
        }
        $missing = [];
        foreach (['name' => 'Nome completo', 'email' => 'E-mail'] as $k => $lbl) {
            if (!isset($seen[$k])) {
                $missing[] = $lbl;
            }
        }
        if ($missing) {
            throw new RuntimeException('Faltam colunas obrigatórias no cabeçalho: ' . implode(' e ', $missing) . '. Use o modelo oficial.');
        }

        $out = [];
        foreach ($rows as $idx => $row) {
            if ($idx <= $best) {
                continue;
            }
            $raw = [];
            $any = false;
            foreach ($colKey as $i => $k) {
                $v = self::clean((string) ($row['cells'][$i] ?? ''));
                $raw[$k] = $v;
                if ($v !== '') {
                    $any = true;
                }
            }
            if (!$any) {
                continue;
            }
            $out[] = ['n' => (int) $row['n'], 'raw' => $raw];
        }
        if (count($out) > self::MAX_ROWS) {
            throw new RuntimeException('A planilha tem ' . count($out) . ' pacientes e o limite é ' . self::MAX_ROWS . ' por vez. Divida em mais de um arquivo.');
        }
        if (!$out) {
            throw new RuntimeException('Não encontrei nenhum paciente abaixo do cabeçalho. Preencha a aba Pacientes e envie de novo.');
        }
        return ['ignored' => $ignored, 'rows' => $out];
    }

    // ---------------------------------------------------------------
    // Conversões (datas, dinheiro, listas)
    // ---------------------------------------------------------------

    /** "20/11/2026", "20-11-26", "2026-11-20" -> "2026-11-20". Vazio = null. Inválido = false. */
    public static function parseDate(string $s, bool $birth = false): string|false|null
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/', $s, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4}|\d{2})$/', $s, $m)) {
            [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 100) {
                // Data de nascimento com 2 números no ano (15/08/90): se passa do ano atual, é 19xx
                $y += ($birth && $y > (int) date('y')) ? 1900 : 2000;
            }
        } else {
            return false;
        }
        if ($y < ($birth ? 1900 : 2000) || $y > 2100 || !checkdate($mo, $d, $y)) {
            return false;
        }
        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /** Dinheiro: "2400", "2.400,50", "R$ 2.400,50", "US$ 450". Vazio = null. Inválido (ou <= 0 ou grande demais) = false. */
    public static function parseMoney(string $s): float|false|null
    {
        $s = trim(str_ireplace(['US$', 'R$', '€', 'USD', 'EUR', 'BRL', '$', ' '], '', $s));
        if ($s === '') {
            return null;
        }
        if (str_contains($s, ',') && str_contains($s, '.') && strrpos($s, '.') > strrpos($s, ',')) {
            $s = str_replace(',', '', $s);                 // 1,234.56 (formato americano)
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s)); // 1.234,56
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);                 // 2.400 = dois mil e quatrocentos
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $s)) {
            return false;
        }
        $v = round((float) $s, 2);
        return ($v <= 0 || $v > 99999.99) ? false : $v;
    }

    /** Número inteiro ("6", "6x", "6 x"). Vazio = null. Inválido = false. */
    private static function parseInt(string $s): int|false|null
    {
        $s = trim(mb_strtolower($s));
        if ($s === '') {
            return null;
        }
        $s = preg_replace('/\s*x$/', '', $s) ?? $s;
        return preg_match('/^\d{1,3}$/', $s) ? (int) $s : false;
    }

    /** Divide "Corrida; Hyrox, Natação" em itens. */
    private static function splitList(string $s): array
    {
        $parts = preg_split('/[;,|\n]+/u', $s) ?: [];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p !== '') {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function methodAliases(): array
    {
        return [
            'cartao' => 'cartao', 'cartao de credito' => 'cartao', 'cartao credito' => 'cartao', 'credito' => 'cartao', 'cc' => 'cartao',
            'pix' => 'pix', 'pix parcelado' => 'pix', 'pix a vista' => 'pix',
            'wise' => 'wise', 'wise exterior' => 'wise',
            'dinheiro' => 'dinheiro', 'especie' => 'dinheiro',
            'outro' => 'outro', 'outros' => 'outro',
        ];
    }

    private static function brandAliases(): array
    {
        return [
            'visa' => 'visa', 'mastercard' => 'mastercard', 'master' => 'mastercard', 'master card' => 'mastercard',
            'elo' => 'elo', 'amex' => 'amex', 'american express' => 'amex', 'hipercard' => 'hipercard', 'hiper' => 'hipercard',
            'outra' => 'outra', 'outra bandeira' => 'outra', 'outro' => 'outra',
        ];
    }

    private static function currencyAliases(): array
    {
        return [
            'brl' => 'BRL', 'r' => 'BRL', 'rs' => 'BRL', 'real' => 'BRL', 'reais' => 'BRL',
            'usd' => 'USD', 'us' => 'USD', 'dolar' => 'USD', 'dolares' => 'USD', 'dollar' => 'USD', 'dolar americano' => 'USD',
            'eur' => 'EUR', 'euro' => 'EUR', 'euros' => 'EUR',
        ];
    }

    // ---------------------------------------------------------------
    // Validação (não salva nada)
    // ---------------------------------------------------------------

    /** Tudo o que existe hoje no sistema e que a planilha pode citar (planos, modalidades, responsáveis, cadastros). */
    private static function lookups(): array
    {
        $plans = [];
        foreach (DB::all('SELECT id, name, price, active, duration_days, consultations_included FROM plans ORDER BY duration_days ASC, id ASC') as $p) {
            $plans[self::norm((string) $p['name'])][] = $p;
        }
        $sports = [];
        foreach (DB::all('SELECT id, name, active FROM sports ORDER BY name ASC') as $s) {
            $sports[self::norm((string) $s['name'])] = $s;
        }
        $resp = [];
        foreach (DB::all('SELECT id, name, active FROM responsibles ORDER BY name ASC') as $r) {
            $resp[self::norm((string) $r['name'])] = $r;
        }
        $emails = [];
        foreach (DB::all('SELECT id, role, name, email FROM users') as $u) {
            $emails[mb_strtolower((string) $u['email'])] = $u;
        }
        $names = [];
        $phones = [];
        foreach (DB::all("SELECT id, name, phone FROM users WHERE role = 'patient'") as $u) {
            $names[self::norm((string) $u['name'])][] = $u;
            $k = self::phoneKey($u['phone']);
            if ($k !== null) {
                $phones[$k][] = $u;
            }
        }
        $cpfs = [];
        foreach (DB::all("SELECT name, cpf FROM users WHERE cpf IS NOT NULL AND cpf <> ''") as $u) {
            $cpfs[(string) $u['cpf']] = (string) $u['name'];
        }
        $fees = Payments::feeTable();
        return compact('plans', 'sports', 'resp', 'emails', 'names', 'phones', 'cpfs', 'fees');
    }

    /**
     * Valida todas as linhas. Entrada: [['n' => linha, 'raw' => [...]], ...].
     * Saída: ['rows' => [...], 'summary' => ['total','ok','warn','dup','err','importable']].
     */
    public static function validate(array $rows): array
    {
        $L = self::lookups();
        $out = [];
        $seenEmail = [];
        $seenName = [];
        $seenPhone = [];
        $seenCpf = [];
        $summary = ['total' => 0, 'ok' => 0, 'warn' => 0, 'dup' => 0, 'err' => 0, 'importable' => 0];

        foreach ($rows as $row) {
            $r = self::validateRow((int) $row['n'], $row['raw'], $L, $seenEmail, $seenName, $seenPhone, $seenCpf);
            $out[] = $r;
            $summary['total']++;
            $summary[$r['status']]++;
            if ($r['status'] === 'ok' || $r['status'] === 'warn') {
                $summary['importable']++;
            }
        }
        return ['rows' => $out, 'summary' => $summary];
    }

    private static function validateRow(int $line, array $raw, array $L, array &$seenEmail, array &$seenName, array &$seenPhone, array &$seenCpf): array
    {
        $errors = [];
        $warns = [];
        $dups = [];
        $g = fn(string $k): string => (string) ($raw[$k] ?? '');
        $today = today();
        $maxFuture = date('Y-m-d', strtotime('+5 years'));

        // ---- Nome
        $name = $g('name');
        if (mb_strlen($name) < 2) {
            $errors[] = 'Nome: informe o nome do paciente.';
        } elseif (mb_strlen($name) > 120) {
            $errors[] = 'Nome: use até 120 letras.';
        }

        // ---- E-mail
        $email = mb_strtolower($g('email'));
        $emailOk = false;
        if ($email === '') {
            $errors[] = 'E-mail: informe o e-mail do paciente.';
        } elseif (mb_strlen($email) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail: "' . $g('email') . '" não parece um e-mail válido.';
        } elseif (str_ends_with($email, '.invalid')) {
            $errors[] = 'E-mail: este é um e-mail de EXEMPLO do modelo. Troque pelo e-mail real do paciente.';
        } else {
            $emailOk = true;
        }
        if ($emailOk) {
            if (isset($L['emails'][$email])) {
                $u = $L['emails'][$email];
                $dups[] = 'Já existe um cadastro com este e-mail' . ($u['role'] === 'patient' ? ' (' . $u['name'] . ')' : '') . '.';
            }
            if (isset($seenEmail[$email])) {
                $dups[] = 'Mesmo e-mail da linha ' . $seenEmail[$email] . ' da planilha.';
            } else {
                $seenEmail[$email] = $line;
            }
        }

        // ---- WhatsApp
        $phone = $g('phone');
        $phoneKey = null;
        if ($phone !== '') {
            if (mb_strlen($phone) > 30) {
                $errors[] = 'WhatsApp: use até 30 caracteres.';
            } else {
                $digits = preg_replace('/\D+/', '', $phone) ?? '';
                $phoneKey = self::phoneKey($phone);
                if (strlen($digits) < 10 || strlen($digits) > 13) {
                    $warns[] = 'WhatsApp: "' . $phone . '" tem ' . strlen($digits) . ' números (o esperado é de 10 a 13, com DDD). Confira.';
                }
            }
        }

        // ---- Objetivo
        $goal = $g('goal');
        if (mb_strlen($goal) > 120) {
            $errors[] = 'Objetivo: use até 120 letras.';
        }

        // ---- Sexo, CPF, Instagram e endereço (as mesmas regras do cadastro manual)
        $pdIn = [];
        foreach (PersonalData::COLUMNS as $k) {
            $pdIn[$k] = $g($k);
        }
        [$pd, $pdErrors] = PersonalData::clean($pdIn);
        foreach ($pdErrors as $pe) {
            $errors[] = $pe;
        }
        if ($pd['cpf'] !== '') {
            if (isset($L['cpfs'][$pd['cpf']])) {
                $dups[] = 'Já existe um cadastro com este CPF (' . $L['cpfs'][$pd['cpf']] . ').';
            }
            if (isset($seenCpf[$pd['cpf']])) {
                $dups[] = 'Mesmo CPF da linha ' . $seenCpf[$pd['cpf']] . ' da planilha.';
            } else {
                $seenCpf[$pd['cpf']] = $line;
            }
        }

        // ---- Possível mesma pessoa (nome e WhatsApp)
        $nameKey = self::norm($name);
        if ($nameKey !== '' && mb_strlen($name) >= 2) {
            $sameName = $L['names'][$nameKey] ?? [];
            $samePhone = $phoneKey !== null ? ($L['phones'][$phoneKey] ?? []) : [];
            $both = [];
            foreach ($sameName as $u) {
                foreach ($samePhone as $v) {
                    if ((int) $u['id'] === (int) $v['id']) {
                        $both[] = $u;
                    }
                }
            }
            $fileBoth = isset($seenName[$nameKey]) && $phoneKey !== null && isset($seenPhone[$phoneKey]) && $seenName[$nameKey] === $seenPhone[$phoneKey];
            if ($both) {
                $dups[] = 'Parece ser o mesmo paciente já cadastrado (' . $both[0]['name'] . '): mesmo nome e mesmo WhatsApp.';
            } elseif ($fileBoth) {
                $dups[] = 'Parece ser o mesmo paciente da linha ' . $seenName[$nameKey] . ' (mesmo nome e mesmo WhatsApp).';
            } else {
                if ($sameName) {
                    $warns[] = 'Já existe um paciente com este nome (' . $sameName[0]['name'] . ', e-mail diferente). Confira se não é a mesma pessoa.';
                } elseif (isset($seenName[$nameKey])) {
                    $warns[] = 'Mesmo nome da linha ' . $seenName[$nameKey] . ' (e-mail diferente). Confira se não é a mesma pessoa.';
                }
                if ($samePhone) {
                    $warns[] = 'Este WhatsApp já é de ' . $samePhone[0]['name'] . '. Confira.';
                } elseif ($phoneKey !== null && isset($seenPhone[$phoneKey])) {
                    $warns[] = 'Mesmo WhatsApp da linha ' . $seenPhone[$phoneKey] . '. Confira.';
                }
            }
            $seenName[$nameKey] ??= $line;
        }
        if ($phoneKey !== null) {
            $seenPhone[$phoneKey] ??= $line;
        }

        // ---- Plano
        $plan = null;
        $planTxt = $g('plan');
        if ($planTxt !== '') {
            $found = $L['plans'][self::norm($planTxt)] ?? null;
            $active = $found ? array_values(array_filter($found, fn($p) => (int) $p['active'] === 1)) : [];
            if ($active) {
                $plan = $active[0];
            } elseif ($found) {
                $errors[] = 'Plano: "' . $planTxt . '" está desativado. Escolha um plano ativo.';
            } else {
                $names = [];
                foreach ($L['plans'] as $list) {
                    foreach ($list as $p) {
                        if ((int) $p['active'] === 1) {
                            $names[] = $p['name'];
                        }
                    }
                }
                $errors[] = 'Plano: "' . $planTxt . '" não existe. Planos ativos: ' . ($names ? implode(', ', $names) : 'nenhum cadastrado ainda (cadastre em Admin > Planos)') . '.';
            }
        }

        // ---- Vencimento
        $renewal = self::parseDate($g('renewal'));
        if ($renewal === false) {
            $errors[] = 'Vencimento do plano: "' . $g('renewal') . '" não é uma data válida. Use dd/mm/aaaa (ex.: 20/02/2027).';
            $renewal = null;
        } elseif ($renewal !== null && $renewal > $maxFuture) {
            $errors[] = 'Vencimento do plano: ' . fmt_date($renewal) . ' está longe demais (mais de 5 anos). Confira o ano.';
            $renewal = null;
        }
        if ($renewal === null && $plan !== null && $g('renewal') === '') {
            $warns[] = 'Plano sem Vencimento: o paciente ficará como "sem data" (você pode preencher depois na ficha).';
        }
        if ($renewal !== null && $renewal < $today) {
            $warns[] = 'Plano já vencido em ' . fmt_date($renewal) . ': o paciente entra como Vencido (plano vencido).';
        }

        // ---- Paciente desde (início do acompanhamento; usado pelo painel gerencial)
        $birth = self::parseDate($g('birth'), true);
        if ($birth === false) {
            $errors[] = 'Data de nascimento: "' . $g('birth') . '" não é uma data válida. Use dd/mm/aaaa (ex.: 15/08/1990).';
            $birth = null;
        } elseif ($birth !== null && ($birth > $today || $birth < '1900-01-01')) {
            $errors[] = 'Data de nascimento: ' . fmt_date($birth) . ' não é possível (use uma data do passado, a partir de 1900).';
            $birth = null;
        }
        $joined = self::parseDate($g('joined'));
        if ($joined === false) {
            $errors[] = 'Paciente desde: "' . $g('joined') . '" não é uma data válida. Use dd/mm/aaaa (ex.: 15/08/2026).';
            $joined = null;
        } elseif ($joined !== null && $joined > $today) {
            $errors[] = 'Paciente desde: ' . fmt_date($joined) . ' é uma data futura. Use o dia em que o paciente começou (hoje ou antes).';
            $joined = null;
        }

        // ---- Responsável
        $respId = null;
        $respName = '';
        $respTxt = $g('responsible');
        if ($respTxt !== '') {
            $rs = $L['resp'][self::norm($respTxt)] ?? null;
            if (!$rs) {
                $list = [];
                foreach ($L['resp'] as $x) {
                    if ((int) $x['active'] === 1) {
                        $list[] = $x['name'];
                    }
                }
                $errors[] = 'Responsável: "' . $respTxt . '" não está cadastrada. ' . ($list ? 'Responsáveis ativos: ' . implode(', ', $list) . '.' : 'Cadastre em Admin > Responsáveis (ou deixe em branco).');
            } elseif ((int) $rs['active'] !== 1) {
                $errors[] = 'Responsável: "' . $rs['name'] . '" está desativada. Escolha outra ou reative em Admin > Responsáveis.';
            } else {
                $respId = (int) $rs['id'];
                $respName = (string) $rs['name'];
            }
        }

        // ---- Perfil esportivo
        $sportIds = [];
        $sportNames = [];
        $sportsTxt = $g('sports');
        if ($sportsTxt !== '') {
            foreach (self::splitList($sportsTxt) as $item) {
                $sp = $L['sports'][self::norm($item)] ?? null;
                if (!$sp) {
                    $list = [];
                    foreach ($L['sports'] as $x) {
                        if ((int) $x['active'] === 1) {
                            $list[] = $x['name'];
                        }
                    }
                    $errors[] = 'Modalidades: "' . $item . '" não existe. Modalidades ativas: ' . ($list ? implode(', ', $list) : 'nenhuma cadastrada (cadastre em Admin > Modalidades)') . '.';
                } elseif ((int) $sp['active'] !== 1) {
                    $errors[] = 'Modalidades: "' . $sp['name'] . '" está desativada.';
                } else {
                    $sportIds[(int) $sp['id']] = (int) $sp['id'];
                    $sportNames[(int) $sp['id']] = (string) $sp['name'];
                }
            }
        }
        $isAthlete = $sportsTxt !== '' && $sportIds !== [];
        $coachTxt = $g('coach');
        $hasCoach = 0;
        $coachName = null;
        if ($coachTxt !== '') {
            $c = self::norm($coachTxt);
            if (in_array($c, ['nao', 'n', 'sem', 'nenhuma', 'nenhum', 'sem assessoria', 'nao tem', 'nao possui'], true)) {
                $hasCoach = 0;
            } elseif (mb_strlen($coachTxt) > 80) {
                $errors[] = 'Assessoria: use até 80 letras.';
            } else {
                $hasCoach = 1;
                $coachName = in_array($c, ['sim', 's', 'tem'], true) ? null : $coachTxt;
                if (!$isAthlete && $sportsTxt === '') {
                    $errors[] = 'Assessoria: só vale para atletas. Preencha as Modalidades (ou apague a assessoria).';
                }
            }
        }
        $eventName = $g('event');
        if (mb_strlen($eventName) > 100) {
            $errors[] = 'Prova: use até 100 letras.';
        }
        $eventDate = self::parseDate($g('event_date'));
        if ($eventDate === false) {
            $errors[] = 'Data da prova: "' . $g('event_date') . '" não é uma data válida. Use dd/mm/aaaa.';
            $eventDate = null;
        }
        $competes = ($eventName !== '' || $eventDate !== null) ? 1 : 0;
        if ($competes && $sportsTxt === '') {
            $errors[] = 'Prova: só vale para atletas. Preencha as Modalidades (ou apague a prova).';
        }
        if ($eventDate !== null && $eventDate < $today) {
            $warns[] = 'Data da prova (' . fmt_date($eventDate) . ') já passou.';
        }
        if ($isAthlete === false) {
            $coachName = null;
        }

        // ---- Valor combinado e moeda
        $customRaw = $g('price');
        $custom = null;
        if ($customRaw !== '') {
            $custom = self::parseMoney($customRaw);
            if ($custom === false) {
                $errors[] = 'Valor combinado: "' . $customRaw . '" não é um valor válido. Use um número maior que zero, como 2400 ou 2.400,50.';
                $custom = null;
            }
        }
        $cur = 'BRL';
        $curTxt = $g('currency');
        if ($curTxt !== '') {
            $c = self::currencyAliases()[self::norm($curTxt)] ?? null;
            if ($c === null) {
                $errors[] = 'Moeda: "' . $curTxt . '" não é aceita. Use BRL (R$), USD (US$) ou EUR (€).';
            } else {
                $cur = $c;
            }
            if ($custom === null && $customRaw === '') {
                $warns[] = 'Moeda informada sem Valor combinado: será ignorada.';
            }
        }
        // Valor igual ao do plano (em reais) não é "valor combinado": não guarda duas vezes
        $storeCustom = $custom !== null;
        if ($storeCustom && $plan && $plan['price'] !== null && $cur === 'BRL' && abs($custom - (float) $plan['price']) < 0.005) {
            $storeCustom = false;
        }

        // ---- Pagamento
        $payCols = ['sold_on', 'installments', 'first_due', 'paid', 'received_brl', 'brand', 'fee'];
        $methodTxt = $g('method');
        $pay = null;
        $amount = null;
        $payCur = $cur;
        if ($methodTxt === '') {
            $filled = [];
            foreach ($payCols as $k) {
                if ($g($k) !== '') {
                    $filled[] = self::columns()[$k][0];
                }
            }
            if ($filled) {
                $errors[] = 'Pagamento: há dados de pagamento (' . implode(', ', $filled) . ') mas a Forma de pagamento está vazia. Preencha a forma ou apague essas colunas.';
            }
        } else {
            $method = self::methodAliases()[self::norm($methodTxt)] ?? null;
            if ($method === null) {
                $errors[] = 'Forma de pagamento: "' . $methodTxt . '" não é aceita. Use: Cartão de crédito, Pix, Wise, Dinheiro ou Outro.';
            }
            // valor da venda: o combinado, ou o do plano
            if ($custom !== null) {
                $amount = $custom;
                $payCur = $cur;
            } elseif ($plan && $plan['price'] !== null) {
                $amount = (float) $plan['price'];
                $payCur = 'BRL';
            }
            if ($amount === null && !($customRaw !== '')) {
                $errors[] = 'Pagamento: não há valor para registrar. Preencha o Valor combinado' . ($planTxt !== '' ? ' (o plano "' . $planTxt . '" não tem valor cadastrado).' : ' ou escolha um Plano com valor.');
            }

            $soldOn = self::parseDate($g('sold_on'));
            if ($soldOn === null) {
                $errors[] = 'Data da venda: obrigatória quando há Forma de pagamento (define em que mês entra no faturamento).';
            } elseif ($soldOn === false) {
                $errors[] = 'Data da venda: "' . $g('sold_on') . '" não é uma data válida. Use dd/mm/aaaa.';
                $soldOn = null;
            } elseif ($soldOn > $today) {
                $errors[] = 'Data da venda: ' . fmt_date($soldOn) . ' é no futuro. A venda precisa já ter acontecido.';
                $soldOn = null;
            }

            $n = 1;
            if ($g('installments') !== '') {
                $ni = self::parseInt(str_replace(['à vista', 'a vista', 'avista'], '1', mb_strtolower($g('installments'))));
                if ($ni === false || $ni === null || $ni < 1 || $ni > 12) {
                    $errors[] = 'Parcelas: "' . $g('installments') . '" não vale. Use um número de 1 a 12.';
                } else {
                    $n = $ni;
                }
            }

            $firstDue = self::parseDate($g('first_due'));
            if ($firstDue === false) {
                $errors[] = 'Data da 1ª parcela: "' . $g('first_due') . '" não é uma data válida. Use dd/mm/aaaa.';
                $firstDue = null;
            } elseif ($firstDue === null) {
                $firstDue = $soldOn;
            } elseif ($firstDue > $maxFuture) {
                $errors[] = 'Data da 1ª parcela: ' . fmt_date($firstDue) . ' está longe demais (mais de 5 anos). Confira o ano.';
                $firstDue = null;
            } elseif ($soldOn !== null && $firstDue < $soldOn) {
                $warns[] = 'A 1ª parcela (' . fmt_date($firstDue) . ') vence antes da data da venda (' . fmt_date($soldOn) . '). Confira.';
            }

            $auto = $method !== null && Payments::isAuto($method, $payCur);
            $paid = 0;
            $paidTxt = $g('paid');
            if ($paidTxt !== '') {
                $pn = self::norm($paidTxt);
                if (in_array($pn, ['todas', 'todas as parcelas', 'tudo', 'quitado', 'quitada', 'pago', 'paga'], true)) {
                    $paid = $n;
                } elseif (in_array($pn, ['nenhuma', 'nenhum', 'nao'], true)) {
                    $paid = 0;
                } else {
                    $pi = self::parseInt($paidTxt);
                    if ($pi === false || $pi === null) {
                        $errors[] = 'Parcelas já recebidas: "' . $paidTxt . '" não vale. Use um número de 0 até ' . $n . '.';
                    } elseif ($pi > $n) {
                        $errors[] = 'Parcelas já recebidas: ' . $pi . ' é mais que o total de parcelas (' . $n . ').';
                    } else {
                        $paid = $pi;
                    }
                }
            }
            if ($auto && $paid > 0) {
                $warns[] = 'Parcelas já recebidas: cartão em reais cai sozinho no vencimento, então esta coluna será ignorada.';
                $paid = 0;
            }

            $receivedBrl = null;
            $recTxt = $g('received_brl');
            if ($payCur !== 'BRL' && $paid > 0) {
                $receivedBrl = self::parseMoney($recTxt);
                if ($receivedBrl === false || $receivedBrl === null) {
                    $errors[] = 'Valor recebido em R$: para ' . currencies()[$payCur][1] . ' já recebido, informe quanto caiu em reais (número maior que zero).';
                    $receivedBrl = null;
                }
            } elseif ($recTxt !== '') {
                $warns[] = 'Valor recebido em R$ será ignorado (só vale para dólar/euro com parcela já recebida).';
            }

            $brand = null;
            $fee = null;
            $brandTxt = $g('brand');
            $feeTxt = $g('fee');
            if ($auto) {
                if ($brandTxt !== '') {
                    $brand = self::brandAliases()[self::norm($brandTxt)] ?? null;
                    if ($brand === null) {
                        $errors[] = 'Bandeira do cartão: "' . $brandTxt . '" não é aceita. Use: Visa, Mastercard, Elo, American Express, Hipercard ou Outra.';
                    }
                }
                if ($feeTxt !== '') {
                    $fee = Payments::parsePct($feeTxt);
                    if ($fee === false) {
                        $errors[] = 'Taxa do cartão: "' . $feeTxt . '" não vale. Use um número de 0 a 30, como 2,5.';
                        $fee = null;
                    }
                }
                if ($brand === null && $fee === null && $brandTxt === '' && $feeTxt === '') {
                    $warns[] = 'Cartão sem bandeira nem taxa: o valor líquido ficará igual ao bruto (sem descontar a taxa da maquininha).';
                } elseif ($fee === null && $brand !== null && $feeTxt === '') {
                    $tbl = $L['fees'][$brand][Payments::bandFor($n)] ?? null;
                    if ($tbl === null) {
                        $warns[] = 'Bandeira ' . Payments::brandLabel($brand) . ' sem taxa cadastrada em Financeiro > Taxas do cartão para ' . Payments::bands()[Payments::bandFor($n)] . ': o líquido ficará igual ao bruto.';
                    }
                }
            } elseif ($brandTxt !== '' || $feeTxt !== '') {
                $warns[] = 'Bandeira e taxa do cartão serão ignoradas (só valem para cartão de crédito em reais).';
            }

            if ($method !== null && $amount !== null && $soldOn !== null && $firstDue !== null && !$errors) {
                $pay = [
                    'method' => $method, 'installments' => $n, 'sold_on' => $soldOn, 'first_due' => $firstDue,
                    'paid_count' => $paid, 'received_brl' => $receivedBrl, 'brand' => $brand, 'fee_pct' => $fee,
                ];
            }
        }

        // ---- Consultas já feitas
        $consults = 0;
        $consTxt = $g('consultations');
        if ($consTxt !== '') {
            $ci = self::parseInt($consTxt);
            if ($ci === false || $ci === null || $ci > 20) {
                $errors[] = 'Consultas já feitas: "' . $consTxt . '" não vale. Use um número de 0 a 20.';
            } else {
                $consults = $ci;
                $quota = $plan['consultations_included'] ?? null;
                if ($plan !== null && $quota !== null && $consults > (int) $quota) {
                    $warns[] = 'Consultas já feitas (' . $consults . ') passam da cota do plano (' . (int) $quota . ').';
                }
            }
        }

        $status = $dups ? 'dup' : ($errors ? 'err' : ($warns ? 'warn' : 'ok'));
        $payText = '';
        if ($methodTxt !== '' && $amount !== null) {
            $payText = money($amount, 2, $payCur) . ' · ' . Payments::methodText($pay['method'] ?? (self::methodAliases()[self::norm($methodTxt)] ?? 'outro'), $pay['installments'] ?? 1);
        }
        return [
            'line' => $line,
            'status' => $status,
            'errors' => $errors,
            'warnings' => $warns,
            'dups' => $dups,
            'view' => [
                'name' => $name, 'email' => $g('email'), 'phone' => $phone,
                'plan' => $plan ? (string) $plan['name'] : $planTxt, 'renewal' => $renewal, 'joined' => $joined, 'responsible' => $respName !== '' ? $respName : $respTxt,
                'sports' => implode(', ', $sportNames), 'pay' => $payText,
                'price' => $custom !== null ? money($custom, 2, $cur) : '', 'consultations' => $consults,
            ],
            'data' => $status === 'ok' || $status === 'warn' ? [
                'name' => $name, 'email' => $email, 'phone' => $phone !== '' ? $phone : null, 'goal' => $goal !== '' ? $goal : null,
                'plan_id' => $plan ? (int) $plan['id'] : null, 'renewal' => $renewal, 'joined' => $joined, 'birth' => $birth, 'pd' => $pd, 'responsible_id' => $respId,
                'sport_ids' => array_values($sportIds), 'has_coach' => $hasCoach, 'coach_name' => $coachName,
                'competes' => $competes, 'event_name' => $eventName !== '' ? $eventName : null, 'event_date' => $eventDate,
                'custom' => $custom, 'custom_cur' => $cur, 'store_custom' => $storeCustom,
                'amount' => $amount, 'pay_cur' => $payCur, 'pay' => $pay, 'consultations' => $consults,
            ] : null,
        ];
    }

    // ---------------------------------------------------------------
    // Salvar (só depois do "Confirmar")
    // ---------------------------------------------------------------

    /**
     * Importa as linhas ok/warn de um resultado de validate(). Cada paciente é salvo numa transação própria:
     * se algo falhar numa linha, só ela fica de fora (nada fica pela metade).
     * Retorna ['created' => [['id','name','line']], 'failed' => [['line','name','reason']], 'skipped' => n].
     */
    public static function commit(array $validated, int $adminId): array
    {
        $created = [];
        $failed = [];
        $skipped = 0;
        foreach ($validated['rows'] as $r) {
            if ($r['status'] !== 'ok' && $r['status'] !== 'warn') {
                $skipped++;
                continue;
            }
            $d = $r['data'];
            try {
                // confere de novo, na hora de salvar (alguém pode ter cadastrado o mesmo e-mail nesse meio tempo)
                if (DB::val('SELECT COUNT(*) FROM users WHERE email = ?', [$d['email']])) {
                    $failed[] = ['line' => $r['line'], 'name' => $d['name'], 'reason' => 'O e-mail já foi cadastrado enquanto você conferia a prévia.'];
                    continue;
                }
                $cpf = (string) ($d['pd']['cpf'] ?? '');
                if ($cpf !== '' && DB::val('SELECT COUNT(*) FROM users WHERE cpf = ?', [$cpf])) {
                    $failed[] = ['line' => $r['line'], 'name' => $d['name'], 'reason' => 'O CPF já foi cadastrado enquanto você conferia a prévia.'];
                    continue;
                }
                $id = DB::tx(function () use ($d, $adminId) {
                    // Sem senha e sem acesso: a senha aqui é aleatória e ninguém conhece. O acesso é liberado depois, na ficha.
                    $id = DB::insert(
                        "INSERT INTO users (role, name, email, password_hash, phone, goal, status, renewal_date, joined_on, birth_date, plan_id, responsible_id, must_change_password, app_access, created_at)
                         VALUES ('patient', ?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, 1, 0, ?)",
                        [$d['name'], $d['email'], password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $d['phone'], $d['goal'],
                            $d['renewal'], $d['joined'] ?? today(), $d['birth'] ?? null, $d['plan_id'], $d['responsible_id'], now()]
                    );
                    DB::run('UPDATE users SET ' . implode(' = ?, ', PersonalData::COLUMNS) . ' = ? WHERE id = ?', array_merge(PersonalData::dbValues($d['pd']), [$id]));
                    if ($d['store_custom'] && $d['custom'] !== null) {
                        Plans::setCustomPrice($id, $d['custom'], $d['custom_cur'], 'manual');
                    }
                    if ($d['sport_ids']) {
                        DB::run(
                            'INSERT INTO athlete_profiles (user_id, is_athlete, has_coach, coach_name, competes, event_name, event_date, show_public, updated_at)
                             VALUES (?, 1, ?, ?, ?, ?, ?, 0, ?)',
                            [$id, $d['has_coach'], $d['coach_name'], $d['competes'], $d['event_name'], $d['event_date'], now()]
                        );
                        foreach ($d['sport_ids'] as $sid) {
                            DB::run('INSERT INTO user_sports (user_id, sport_id) VALUES (?, ?)', [$id, (int) $sid]);
                        }
                    }
                    if ($d['pay'] !== null && $d['amount'] !== null) {
                        $p = $d['pay'];
                        Payments::create(
                            $id, $d['plan_id'], null, (float) $d['amount'], $d['pay_cur'], $p['method'], $p['installments'],
                            $p['sold_on'], $p['first_due'], $p['paid_count'], $p['received_brl'], $adminId, $p['brand'], $p['fee_pct']
                        );
                    }
                    for ($i = 0; $i < (int) $d['consultations']; $i++) {
                        DB::run(
                            'INSERT INTO consultations (user_id, held_on, cycle, note, created_by, created_at) VALUES (?, ?, 0, ?, ?, ?)',
                            [$id, today(), 'Importado da planilha (data real não informada)', $adminId, now()]
                        );
                    }
                    return $id;
                });
                $created[] = ['id' => $id, 'name' => $d['name'], 'line' => $r['line']];
            } catch (\Throwable $e) {
                error_log('Importação de pacientes, linha ' . $r['line'] . ': ' . $e->getMessage());
                $failed[] = ['line' => $r['line'], 'name' => $d['name'], 'reason' => 'Não foi possível salvar esta linha (nada foi gravado para ela). Tente de novo.'];
            }
        }
        return ['created' => $created, 'failed' => $failed, 'skipped' => $skipped];
    }

    // ---------------------------------------------------------------
    // Apoio para as telas
    // ---------------------------------------------------------------

    public static function statusLabels(): array
    {
        return ['ok' => 'Pronto', 'warn' => 'Aviso', 'dup' => 'Duplicado', 'err' => 'Erro'];
    }

    /** Valores aceitos hoje (para a tela de instruções e para a aba Listas do modelo). */
    public static function lists(): array
    {
        return [
            'plans' => array_column(DB::all('SELECT name FROM plans WHERE active = 1 ORDER BY duration_days ASC, id ASC'), 'name'),
            'sports' => array_column(DB::all('SELECT name FROM sports WHERE active = 1 ORDER BY name ASC'), 'name'),
            'responsibles' => array_column(DB::all('SELECT name FROM responsibles WHERE active = 1 ORDER BY name ASC'), 'name'),
            'methods' => array_values(Payments::methods()),
            'currencies' => ['BRL', 'USD', 'EUR'],
            'brands' => array_values(Payments::brands()),
        ];
    }

    /** Linha de texto segura para CSV (evita que o Excel execute "=...", "+...", "-...", "@..."). */
    public static function csvSafe(string $s): string
    {
        return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
    }
}
