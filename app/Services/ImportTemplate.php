<?php
namespace App\Services;

use App\Core\Xlsx;
use RuntimeException;
use ZipArchive;

/**
 * Modelo oficial de importação (.xlsx) para download.
 * O arquivo-base fica em app/Resources/. A cada download, a aba "Listas" é reescrita com os planos,
 * modalidades e responsáveis ATIVOS de hoje, para as listas suspensas do Excel sempre baterem com o sistema.
 * O resto do arquivo (instruções, cabeçalhos, exemplos fictícios) vem pronto, sem nenhum dado de paciente.
 */
final class ImportTemplate
{
    private const MAX_LIST = 60; // linhas 2 a 61 da aba Listas

    public static function path(): string
    {
        return ROOT . '/app/Resources/modelo-importacao-pacientes.xlsx';
    }

    /** Conteúdo do .xlsx pronto para enviar ao navegador. */
    public static function build(): string
    {
        $src = self::path();
        if (!is_file($src)) {
            throw new RuntimeException('O arquivo do modelo não foi encontrado no servidor (app/Resources/modelo-importacao-pacientes.xlsx).');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ndo');
        if ($tmp === false || !copy($src, $tmp)) {
            throw new RuntimeException('Não consegui preparar o modelo. Tente de novo.');
        }
        try {
            // descobre qual arquivo XML é a aba "Listas"
            $x = Xlsx::open($tmp);
            $listPath = null;
            foreach ($x->sheets() as $s) {
                if ($s['name'] === 'Listas') {
                    $listPath = $s['path'];
                }
            }
            unset($x);
            if ($listPath === null) {
                throw new RuntimeException('O modelo está sem a aba Listas.');
            }

            $zip = new ZipArchive();
            if ($zip->open($tmp) !== true) {
                throw new RuntimeException('Não consegui abrir o modelo.');
            }
            $xml = $zip->getFromName($listPath);
            if ($xml === false) {
                $zip->close();
                throw new RuntimeException('O modelo está incompleto.');
            }
            $zip->addFromString($listPath, self::fillLists($xml, PatientImport::lists()));
            $zip->close();

            $bytes = file_get_contents($tmp);
            if ($bytes === false) {
                throw new RuntimeException('Não consegui ler o modelo preparado.');
            }
            return $bytes;
        } finally {
            @unlink($tmp);
        }
    }

    /** Troca as linhas 2 em diante da aba Listas pelos valores de hoje (mantém a linha 1, com os cabeçalhos). */
    private static function fillLists(string $xml, array $lists): string
    {
        if (!preg_match('#<row\b[^>]*\br="1"[^>]*>.*?</row>#s', $xml, $head)) {
            throw new RuntimeException('O modelo está com a aba Listas fora do padrão.');
        }
        $cols = [
            'A' => $lists['plans'], 'B' => $lists['sports'], 'C' => $lists['responsibles'],
            'D' => $lists['methods'], 'E' => $lists['currencies'], 'F' => $lists['brands'],
        ];
        $rows = '';
        $maxRow = 1;
        for ($i = 0; $i < self::MAX_LIST; $i++) {
            $cells = '';
            foreach ($cols as $letter => $items) {
                if (isset($items[$i])) {
                    $v = htmlspecialchars((string) $items[$i], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                    $cells .= '<c r="' . $letter . ($i + 2) . '" t="inlineStr"><is><t xml:space="preserve">' . $v . '</t></is></c>';
                }
            }
            if ($cells !== '') {
                $rows .= '<row r="' . ($i + 2) . '">' . $cells . '</row>';
                $maxRow = $i + 2;
            }
        }
        $xml = preg_replace('#<sheetData>.*</sheetData>#s', '<sheetData>' . $head[0] . $rows . '</sheetData>', $xml, 1, $n);
        if (!$n) {
            throw new RuntimeException('O modelo está com a aba Listas fora do padrão.');
        }
        return preg_replace('#<dimension ref="[^"]*"\s*/>#', '<dimension ref="A1:F' . $maxRow . '"/>', $xml, 1) ?? $xml;
    }
}
