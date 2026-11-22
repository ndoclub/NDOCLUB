<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;

/**
 * Dados pessoais do cadastro do paciente (Atualização 08.1): sexo, CPF, Instagram e endereço.
 * Tudo é opcional e só o admin vê (nunca o paciente, o feed, o ranking ou o perfil público).
 * Aqui ficam as regras de limpeza e validação, usadas pelo cadastro, pela ficha e pela importação.
 */
final class PersonalData
{
    /** Colunas da tabela users que esta classe cuida (na ordem usada nos formulários). */
    public const COLUMNS = ['sex', 'cpf', 'instagram', 'addr_zip', 'addr_street', 'addr_number', 'addr_complement', 'addr_district', 'addr_city', 'addr_state', 'addr_country'];

    /** Limites de tamanho (iguais aos do banco). */
    private const MAX = ['addr_street' => 120, 'addr_number' => 20, 'addr_complement' => 80, 'addr_district' => 80, 'addr_city' => 80, 'addr_state' => 40, 'addr_country' => 60, 'addr_zip' => 12];

    /** Rótulos dos campos de texto, para as mensagens de erro. */
    private const LABELS = ['addr_street' => 'Rua', 'addr_number' => 'Número', 'addr_complement' => 'Complemento', 'addr_district' => 'Bairro', 'addr_city' => 'Cidade', 'addr_state' => 'Estado', 'addr_country' => 'País', 'addr_zip' => 'CEP'];

    public static function sexChoices(): array
    {
        return ['F' => 'Feminino', 'M' => 'Masculino'];
    }

    public static function sexLabel(?string $sex): string
    {
        return self::sexChoices()[(string) $sex] ?? 'Não informado';
    }

    /** Aceita F/M, feminino/masculino, mulher/homem (qualquer caixa). '' = não informado; false = não entendi. */
    public static function parseSex(string $raw): string|false
    {
        $s = mb_strtolower(trim($raw));
        if ($s === '') {
            return '';
        }
        if (in_array($s, ['f', 'fem', 'feminino', 'mulher', 'female'], true)) {
            return 'F';
        }
        if (in_array($s, ['m', 'masc', 'masculino', 'homem', 'male'], true)) {
            return 'M';
        }
        return false;
    }

    public static function states(): array
    {
        return ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    }

    // ---------------------------------------------------------------- CPF

    public static function cpfDigits(string $raw): string
    {
        return preg_replace('/\D+/', '', $raw) ?? '';
    }

    /** CPF com 11 números, não todos iguais e com os dois dígitos verificadores certos. */
    public static function validCpf(string $digits): bool
    {
        if (!preg_match('/^\d{11}$/', $digits) || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $digits[$i] * ($t + 1 - $i);
            }
            if ((int) $digits[$t] !== (($sum * 10) % 11) % 10) {
                return false;
            }
        }
        return true;
    }

    /** 000.000.000-00 (se não tiver 11 números, devolve como veio). */
    public static function fmtCpf(?string $cpf): string
    {
        $d = self::cpfDigits((string) $cpf);
        if (strlen($d) !== 11) {
            return (string) $cpf;
        }
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }

    /** Outro paciente já tem este CPF? Devolve a linha (id, name) ou null. */
    public static function cpfOwner(string $digits, int $exceptId = 0): ?array
    {
        if ($digits === '') {
            return null;
        }
        return DB::one("SELECT id, name FROM users WHERE cpf = ? AND id <> ? LIMIT 1", [$digits, $exceptId]);
    }

    // ---------------------------------------------------------------- Instagram

    /**
     * Aceita "@usuario", "usuario" ou o link do perfil. Devolve só o usuário em minúsculas,
     * '' se vazio, ou false se não parece um usuário válido (letras, números, ponto e sublinhado, até 30).
     */
    public static function instagram(string $raw): string|false
    {
        $s = trim($raw);
        if ($s === '') {
            return '';
        }
        if (preg_match('~^(?:https?://)?(?:www\.)?(?:instagram\.com|instagr\.am)/([^/?#\s]*)~i', $s, $m)) {
            $s = $m[1];
        }
        $s = strtolower(ltrim($s, '@'));
        if (!preg_match('/^[a-z0-9._]{1,30}$/', $s) || in_array($s, ['p', 'reel', 'reels', 'stories', 'explore', 'tv', 'accounts'], true)) {
            return false;
        }
        return $s;
    }

    public static function instagramUrl(string $user): string
    {
        return 'https://www.instagram.com/' . rawurlencode($user) . '/';
    }

    // ---------------------------------------------------------------- Endereço

    /** Em branco ou "Brasil" (com ou sem acento) = endereço no Brasil. */
    public static function isBrazil(?string $country): bool
    {
        $c = mb_strtolower(trim((string) $country));
        return in_array($c, ['', 'brasil', 'brazil', 'br'], true);
    }

    public static function fmtZip(?string $zip): string
    {
        $z = (string) $zip;
        return preg_match('/^\d{8}$/', $z) ? substr($z, 0, 5) . '-' . substr($z, 5) : $z;
    }

    /**
     * Confere e limpa os campos (vindos do formulário OU da planilha). Recebe os valores crus pelas chaves de COLUMNS.
     * Devolve [valores limpos (texto, '' = vazio), lista de erros em português].
     * No Brasil: CEP precisa ter 8 números e o estado ser uma sigla (SP, RJ...). Fora do Brasil, os dois são texto livre.
     */
    public static function clean(array $in): array
    {
        $errors = [];
        $v = [];
        foreach (self::COLUMNS as $k) {
            $v[$k] = trim((string) ($in[$k] ?? ''));
        }

        // Sexo
        $sex = self::parseSex($v['sex']);
        if ($sex === false) {
            $errors[] = 'Sexo inválido. Escolha Feminino ou Masculino.';
            $sex = '';
        }
        $v['sex'] = $sex;

        // CPF
        $v['cpf'] = self::cpfDigits($v['cpf']);
        if ($v['cpf'] !== '' && !self::validCpf($v['cpf'])) {
            $errors[] = 'CPF inválido. Confira os números (são 11 dígitos).';
            $v['cpf'] = '';
        }

        // Instagram
        $ig = self::instagram($v['instagram']);
        if ($ig === false) {
            $errors[] = 'Instagram inválido. Use o @ do perfil (letras, números, ponto e sublinhado, até 30).';
            $ig = '';
        }
        $v['instagram'] = $ig;

        // Endereço
        $brazil = self::isBrazil($v['addr_country']);
        foreach (['addr_street', 'addr_number', 'addr_complement', 'addr_district', 'addr_city', 'addr_state', 'addr_country'] as $k) {
            if (mb_strlen($v[$k]) > self::MAX[$k]) {
                $errors[] = self::LABELS[$k] . ': no máximo ' . self::MAX[$k] . ' letras.';
                $v[$k] = mb_substr($v[$k], 0, self::MAX[$k]);
            }
        }
        if ($brazil) {
            $v['addr_country'] = ''; // em branco = Brasil
            $zip = preg_replace('/\D+/', '', $v['addr_zip']) ?? '';
            if ($zip !== '' && strlen($zip) !== 8) {
                $errors[] = 'CEP inválido. No Brasil são 8 números (ex.: 01310-100).';
                $zip = '';
            }
            $v['addr_zip'] = $zip;
            $uf = strtoupper($v['addr_state']);
            if ($uf !== '' && !in_array($uf, self::states(), true)) {
                $errors[] = 'Estado inválido. Use a sigla (SP, RJ, MG...).';
                $uf = '';
            }
            $v['addr_state'] = $uf;
        } else {
            if (mb_strlen($v['addr_zip']) > self::MAX['addr_zip']) {
                $errors[] = 'CEP: no máximo 12 caracteres.';
                $v['addr_zip'] = mb_substr($v['addr_zip'], 0, 12);
            }
        }
        return [$v, $errors];
    }

    /** Há algum dado de endereço preenchido? */
    public static function hasAddress(array $row): bool
    {
        foreach (['addr_zip', 'addr_street', 'addr_number', 'addr_complement', 'addr_district', 'addr_city', 'addr_state', 'addr_country'] as $k) {
            if (trim((string) ($row[$k] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** Falta CPF ou falta o endereço (rua e cidade)? Serve ao filtro "Cadastro incompleto". */
    public static function incomplete(array $row): bool
    {
        return trim((string) ($row['cpf'] ?? '')) === ''
            || trim((string) ($row['addr_street'] ?? '')) === ''
            || trim((string) ($row['addr_city'] ?? '')) === '';
    }

    /** Valores limpos -> valores para o banco ('' vira NULL), na ordem de COLUMNS. */
    public static function dbValues(array $clean): array
    {
        $out = [];
        foreach (self::COLUMNS as $k) {
            $v = (string) ($clean[$k] ?? '');
            $out[] = $v === '' ? null : $v;
        }
        return $out;
    }
}
