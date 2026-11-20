<?php
namespace App\Core;

use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Leitor simples de planilhas Excel (.xlsx), sem bibliotecas externas.
 * Um .xlsx é um ZIP com arquivos XML. Aqui lemos só o que a importação precisa: o texto/número de cada célula.
 *
 *  - Datas: células formatadas como data viram "AAAA-MM-DD".
 *  - Números: devolvidos sem "ruído" de ponto flutuante (0,1 + 0,2 não vira 0.30000000000000004).
 *  - Fórmulas: vale o último resultado guardado pelo Excel.
 *  - Não executa macros nem abre links externos. XML com DOCTYPE/ENTITY é recusado (proteção contra XXE).
 *
 * Qualquer problema (arquivo corrompido, grande demais, protegido por senha) vira RuntimeException
 * com mensagem em português, pronta para mostrar ao usuário.
 */
final class Xlsx
{
    private const MAX_XML_BYTES = 25 * 1024 * 1024;   // cada XML descompactado
    private const MAX_TOTAL_BYTES = 80 * 1024 * 1024; // soma de tudo descompactado (proteção contra "zip bomb")

    private ZipArchive $zip;
    private bool $date1904 = false;

    private function __construct(ZipArchive $zip)
    {
        $this->zip = $zip;
    }

    /** Abre o arquivo e confere se parece mesmo um .xlsx. */
    public static function open(string $path): self
    {
        $head = @file_get_contents($path, false, null, 0, 8);
        if ($head === false || strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0) {
            // D0CF11E0 = formato antigo (.xls) ou arquivo protegido por senha
            throw new RuntimeException('Este arquivo é do Excel antigo (.xls) ou está protegido por senha. Abra no Excel e use "Salvar como" > Pasta de Trabalho do Excel (.xlsx), sem senha.');
        }
        if (strncmp((string) $head, 'PK', 2) !== 0) {
            throw new RuntimeException('Este arquivo não é uma planilha Excel (.xlsx) válida.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Não consegui abrir a planilha. O arquivo pode estar corrompido: salve de novo como .xlsx e tente outra vez.');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $st = $zip->statIndex($i);
            $total += (int) ($st['size'] ?? 0);
            if ($total > self::MAX_TOTAL_BYTES) {
                $zip->close();
                throw new RuntimeException('A planilha é grande demais para importar. Envie só os pacientes (sem imagens ou abas extras).');
            }
        }
        if ($zip->locateName('xl/workbook.xml') === false) {
            $zip->close();
            throw new RuntimeException('Este arquivo não é uma planilha Excel (.xlsx) válida.');
        }
        return new self($zip);
    }

    public function __destruct()
    {
        @$this->zip->close();
    }

    /** XML de dentro do ZIP (como texto), já conferido. */
    private function xml(string $name): ?string
    {
        $idx = $this->zip->locateName($name);
        if ($idx === false) {
            return null;
        }
        $st = $this->zip->statIndex($idx);
        if ((int) ($st['size'] ?? 0) > self::MAX_XML_BYTES) {
            throw new RuntimeException('A planilha é grande demais para importar. Envie só os pacientes (sem imagens ou abas extras).');
        }
        $s = $this->zip->getFromIndex($idx);
        if ($s === false) {
            throw new RuntimeException('Não consegui ler a planilha. O arquivo pode estar corrompido.');
        }
        if (stripos($s, '<!DOCTYPE') !== false || stripos($s, '<!ENTITY') !== false) {
            throw new RuntimeException('A planilha tem conteúdo não permitido e foi recusada por segurança.');
        }
        return $s;
    }

    private static function reader(string $xml): XMLReader
    {
        $r = new XMLReader();
        if (!@$r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Não consegui ler a planilha. O arquivo pode estar corrompido.');
        }
        return $r;
    }

    /** Abas da planilha, na ordem: [['name' => 'Pacientes', 'path' => 'xl/worksheets/sheet2.xml'], ...] */
    public function sheets(): array
    {
        $wb = $this->xml('xl/workbook.xml');
        if ($wb === null) {
            return [];
        }
        $rels = [];
        $relXml = $this->xml('xl/_rels/workbook.xml.rels');
        if ($relXml !== null) {
            $r = self::reader($relXml);
            while ($r->read()) {
                if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'Relationship') {
                    $target = (string) $r->getAttribute('Target');
                    $target = ltrim($target, '/');
                    $rels[(string) $r->getAttribute('Id')] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
            $r->close();
        }
        $out = [];
        $r = self::reader($wb);
        while ($r->read()) {
            if ($r->nodeType !== XMLReader::ELEMENT) {
                continue;
            }
            if ($r->localName === 'workbookPr') {
                $d = (string) $r->getAttribute('date1904');
                $this->date1904 = ($d === '1' || strtolower($d) === 'true');
            } elseif ($r->localName === 'sheet') {
                $rid = (string) $r->getAttributeNs('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
                if ($rid === '') {
                    $rid = (string) $r->getAttribute('r:id');
                }
                $state = (string) $r->getAttribute('state');
                if (isset($rels[$rid]) && $state !== 'veryHidden') {
                    $out[] = ['name' => (string) $r->getAttribute('name'), 'path' => $rels[$rid], 'hidden' => $state === 'hidden'];
                }
            }
        }
        $r->close();
        return $out;
    }

    /** Textos compartilhados (a maioria dos textos da planilha fica aqui, e a célula só guarda o número). */
    private function sharedStrings(): array
    {
        $xml = $this->xml('xl/sharedStrings.xml');
        if ($xml === null) {
            return [];
        }
        $out = [];
        $r = self::reader($xml);
        $inSi = false;
        $cur = '';
        $skip = 0; // dentro de <rPh> (furigana): não faz parte do texto
        $inT = false;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->localName === 'si') {
                    $inSi = true;
                    $cur = '';
                    if ($r->isEmptyElement) {
                        $out[] = '';
                        $inSi = false;
                    }
                } elseif ($inSi && $r->localName === 'rPh') {
                    $skip++;
                } elseif ($inSi && $r->localName === 't' && $skip === 0) {
                    $inT = !$r->isEmptyElement;
                }
            } elseif ($r->nodeType === XMLReader::TEXT || $r->nodeType === XMLReader::SIGNIFICANT_WHITESPACE || $r->nodeType === XMLReader::CDATA) {
                if ($inSi && $inT && $skip === 0) {
                    $cur .= $r->value;
                }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT) {
                if ($r->localName === 't') {
                    $inT = false;
                } elseif ($r->localName === 'rPh') {
                    $skip = max(0, $skip - 1);
                } elseif ($r->localName === 'si') {
                    $out[] = $cur;
                    $inSi = false;
                }
            }
        }
        $r->close();
        return $out;
    }

    /** Quais estilos de célula (índice "s") são datas. Retorna [indice => true]. */
    private function dateStyles(): array
    {
        $xml = $this->xml('xl/styles.xml');
        if ($xml === null) {
            return [];
        }
        $custom = [];
        $xfs = [];
        $r = self::reader($xml);
        $inCellXfs = false;
        while ($r->read()) {
            if ($r->nodeType === XMLReader::ELEMENT) {
                if ($r->localName === 'numFmt') {
                    $custom[(int) $r->getAttribute('numFmtId')] = (string) $r->getAttribute('formatCode');
                } elseif ($r->localName === 'cellXfs') {
                    $inCellXfs = true;
                } elseif ($inCellXfs && $r->localName === 'xf') {
                    $xfs[] = (int) $r->getAttribute('numFmtId');
                }
            } elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'cellXfs') {
                $inCellXfs = false;
            }
        }
        $r->close();
        $builtinDates = array_merge(range(14, 22), range(27, 36), range(45, 47), range(50, 58));
        $out = [];
        foreach ($xfs as $i => $fmtId) {
            if (isset($custom[$fmtId])) {
                $out[$i] = self::isDateFormat($custom[$fmtId]);
            } else {
                $out[$i] = in_array($fmtId, $builtinDates, true);
            }
        }
        return $out;
    }

    /** O código de formato (ex.: "dd/mm/yyyy", "0.00", "[$-416]d mmm yy") representa uma data? */
    private static function isDateFormat(string $code): bool
    {
        $code = preg_replace('/"[^"]*"/', '', $code);       // textos entre aspas
        $code = preg_replace('/\[[^\]]*\]/', '', (string) $code); // [Red], [$-416]...
        $code = preg_replace('/\\\\./', '', (string) $code);  // caractere escapado
        $code = preg_replace('/_./', '', (string) $code);     // espaçamento
        $code = str_ireplace('General', '', (string) $code);
        return (bool) preg_match('/[dmyhs]/i', (string) $code);
    }

    /** Número de série do Excel -> "AAAA-MM-DD". */
    private function serialToDate(float $serial): ?string
    {
        if ($serial < 1 || $serial > 2958465) { // 1 = 01/01/1900, 2958465 = 31/12/9999
            return null;
        }
        $days = (int) floor($serial);
        if ($this->date1904) {
            $base = new \DateTimeImmutable('1904-01-01 00:00:00', new \DateTimeZone('UTC'));
            return $base->modify('+' . $days . ' days')->format('Y-m-d');
        }
        // No sistema 1900 o Excel acha que 1900 foi bissexto (série 60 = "29/02/1900", que não existe)
        $base = new \DateTimeImmutable('1899-12-30 00:00:00', new \DateTimeZone('UTC'));
        if ($days < 60) {
            $base = new \DateTimeImmutable('1899-12-31 00:00:00', new \DateTimeZone('UTC'));
        }
        return $base->modify('+' . $days . ' days')->format('Y-m-d');
    }

    /** Número como texto limpo ("2400", "2400.5", "11999998888"), sem ruído de ponto flutuante. */
    private static function cleanNumber(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || !is_numeric($raw)) {
            return $raw;
        }
        $f = (float) $raw;
        if (abs($f) >= 1e15) {
            return $raw;
        }
        $s = sprintf('%.15g', $f);
        if (str_contains($s, 'e') || str_contains($s, 'E')) {
            $s = rtrim(rtrim(number_format($f, 10, '.', ''), '0'), '.');
        }
        return $s === '-0' ? '0' : $s;
    }

    /**
     * Linhas da aba. Retorna [['n' => numeroDaLinhaNoExcel, 'cells' => [0 => 'texto', 1 => '...']], ...]
     * Linhas totalmente vazias são puladas. Para na linha $maxRows (conta linhas lidas, não só as com dados).
     * $cols = no máximo quantas colunas ler.
     */
    public function rows(string $sheetPath, int $maxRows = 5000, int $cols = 60): array
    {
        $xml = $this->xml($sheetPath);
        if ($xml === null) {
            throw new RuntimeException('Não encontrei a aba dentro da planilha.');
        }
        $shared = $this->sharedStrings();
        $dateStyle = $this->dateStyles();
        $r = self::reader($xml);

        $rows = [];
        $count = 0;
        $rowNum = 0;
        $cells = [];
        $col = 0;
        $type = '';
        $style = 0;
        $val = null;
        $inline = '';
        $inCell = false;
        $inV = false;
        $inIs = false;
        $inT = false;
        $inF = false;
        $skipPh = 0;

        while ($r->read()) {
            $nt = $r->nodeType;
            if ($nt === XMLReader::ELEMENT) {
                $name = $r->localName;
                if ($name === 'row') {
                    $rowNum = (int) $r->getAttribute('r') ?: $rowNum + 1;
                    $cells = [];
                    $count++;
                    if ($count > $maxRows) {
                        break;
                    }
                    if ($r->isEmptyElement) {
                        continue;
                    }
                } elseif ($name === 'c') {
                    $ref = (string) $r->getAttribute('r');
                    $col = $ref !== '' ? self::colIndex($ref) : (count($cells) ? max(array_keys($cells)) + 1 : 0);
                    $type = (string) $r->getAttribute('t');
                    $style = (int) $r->getAttribute('s');
                    $val = null;
                    $inline = '';
                    $inCell = true;
                    if ($r->isEmptyElement) {
                        $inCell = false;
                    }
                } elseif ($inCell && $name === 'v') {
                    $inV = !$r->isEmptyElement;
                    $val = '';
                } elseif ($inCell && $name === 'f') {
                    $inF = !$r->isEmptyElement;
                } elseif ($inCell && $name === 'is') {
                    $inIs = true;
                } elseif ($inIs && $name === 'rPh') {
                    $skipPh++;
                } elseif ($inIs && $name === 't' && $skipPh === 0) {
                    $inT = !$r->isEmptyElement;
                }
            } elseif ($nt === XMLReader::TEXT || $nt === XMLReader::SIGNIFICANT_WHITESPACE || $nt === XMLReader::CDATA) {
                if ($inV) {
                    $val .= $r->value;
                } elseif ($inT && $inIs) {
                    $inline .= $r->value;
                }
            } elseif ($nt === XMLReader::END_ELEMENT) {
                $name = $r->localName;
                if ($name === 'v') {
                    $inV = false;
                } elseif ($name === 'f') {
                    $inF = false;
                } elseif ($name === 't') {
                    $inT = false;
                } elseif ($name === 'rPh') {
                    $skipPh = max(0, $skipPh - 1);
                } elseif ($name === 'is') {
                    $inIs = false;
                } elseif ($name === 'c') {
                    $inCell = false;
                    if ($col < $cols) {
                        $text = $this->cellText($type, $style, $val, $inline, $shared, $dateStyle);
                        if ($text !== '') {
                            $cells[$col] = $text;
                        }
                    }
                } elseif ($name === 'row') {
                    if ($cells) {
                        $max = max(array_keys($cells));
                        $line = [];
                        for ($i = 0; $i <= $max; $i++) {
                            $line[$i] = $cells[$i] ?? '';
                        }
                        $rows[] = ['n' => $rowNum, 'cells' => $line];
                    }
                }
            }
        }
        $r->close();
        return $rows;
    }

    private function cellText(string $type, int $style, ?string $val, string $inline, array $shared, array $dateStyle): string
    {
        switch ($type) {
            case 's':
                return $val !== null && $val !== '' && isset($shared[(int) $val]) ? $shared[(int) $val] : '';
            case 'inlineStr':
                return $inline;
            case 'str':
                return (string) $val;
            case 'b':
                return $val === '1' ? '1' : '0';
            case 'e':
                return '#ERRO';
            case 'd': // data ISO (raro)
                return substr((string) $val, 0, 10);
            default: // número (ou vazio)
                if ($val === null || $val === '') {
                    return '';
                }
                if (!empty($dateStyle[$style]) && is_numeric($val)) {
                    $d = $this->serialToDate((float) $val);
                    return $d ?? self::cleanNumber($val);
                }
                return self::cleanNumber($val);
        }
    }

    /** "C5" -> 2 (índice da coluna, começando em 0). */
    private static function colIndex(string $ref): int
    {
        $n = 0;
        $len = strlen($ref);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($ref[$i]);
            if ($c >= 65 && $c <= 90) {
                $n = $n * 26 + ($c - 64);
            } elseif ($c >= 97 && $c <= 122) {
                $n = $n * 26 + ($c - 96);
            } else {
                break;
            }
        }
        return max(0, $n - 1);
    }
}
