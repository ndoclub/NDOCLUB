<?php
namespace App\Core;

/**
 * Gráficos do painel gerencial desenhados no servidor em SVG (sem biblioteca, sem JavaScript).
 * As cores ficam no app.css (classes ch-*, dn-*, sp-*). Cada ponto tem um <title> com o valor
 * (aparece ao passar o mouse) e todo gráfico tem uma tabela equivalente na tela.
 */
final class Charts
{
    private static function f(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Valor curto para eixos e rótulos: 48000 => "R$ 48 mil", 1250000 => "R$ 1,3 mi", 800 => "R$ 800". */
    public static function compact(float $v): string
    {
        $abs = abs($v);
        if ($abs >= 1000000) {
            $x = $v / 1000000;
            return 'R$ ' . number_format($x, abs($x - round($x)) < 0.05 ? 0 : 1, ',', '.') . ' mi';
        }
        if ($abs >= 1000) {
            $x = $v / 1000;
            return 'R$ ' . number_format($x, abs($x - round($x)) < 0.05 ? 0 : 1, ',', '.') . ' mil';
        }
        return 'R$ ' . number_format($v, 0, ',', '.');
    }

    /** Passo "redondo" do eixo (1, 2, 5 x 10^n) para ter uns 4 intervalos. Retorna [passo, quantidade de passos]. */
    public static function niceStep(float $max): array
    {
        if ($max <= 0) {
            return [1.0, 1];
        }
        $rough = $max / 4;
        $exp = floor(log10($rough));
        $f = $rough / (10 ** $exp);
        $nice = $f <= 1 ? 1 : ($f <= 2 ? 2 : ($f <= 5 ? 5 : 10));
        $step = $nice * (10 ** $exp);
        return [(float) $step, max(1, (int) ceil($max / $step - 1e-9))];
    }

    /** Mini gráfico de barras (último mês em destaque). */
    public static function bars(array $values, int $w = 88, int $h = 42): string
    {
        $n = count($values);
        if ($n === 0) {
            return '';
        }
        $max = max(array_map('floatval', $values));
        $slot = $w / $n;
        $bw = $slot * 0.62;
        $out = '<svg class="spark" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" aria-hidden="true" focusable="false">';
        foreach (array_values($values) as $i => $v) {
            $bh = $max > 0 ? max(2.0, ((float) $v / $max) * ($h - 2)) : 2.0;
            $x = $i * $slot + ($slot - $bw) / 2;
            $out .= '<rect class="' . ($i === $n - 1 ? 'sp-last' : 'sp-bar') . '" x="' . self::f($x) . '" y="' . self::f($h - $bh) . '" width="' . self::f($bw) . '" height="' . self::f($bh) . '" rx="2"/>';
        }
        return $out . '</svg>';
    }

    /** Anel de porcentagem (taxa de renovação). null = sem dado ("—"). */
    public static function ring(?int $pct, int $size = 64): string
    {
        $r = 26;
        $c = 2 * M_PI * $r;
        $out = '<svg class="ring" viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . ($pct === null ? 'Sem dado' : (int) $pct . ' por cento') . '">';
        $out .= '<circle class="ring-track" cx="32" cy="32" r="' . $r . '" fill="none" stroke-width="8"/>';
        if ($pct !== null && $pct > 0) {
            $len = $c * min(100, $pct) / 100;
            $out .= '<circle class="ring-fill" cx="32" cy="32" r="' . $r . '" fill="none" stroke-width="8" stroke-linecap="round" stroke-dasharray="' . self::f($len) . ' ' . self::f($c) . '" transform="rotate(-90 32 32)"/>';
        }
        return $out . '</svg>';
    }

    /**
     * Rosca. $segments = [['label' => 'Trimestral', 'value' => 55, 'cls' => 'dn-1'], ...].
     * Centro: número grande + legenda pequena.
     */
    public static function donut(array $segments, string $big, string $small, int $size = 176): string
    {
        $total = 0.0;
        foreach ($segments as $s) {
            $total += (float) $s['value'];
        }
        $r = 62;
        $c = 2 * M_PI * $r;
        $out = '<svg class="donut" viewBox="0 0 160 160" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . self::esc($big . ' ' . $small) . '">';
        $out .= '<circle class="dn-track" cx="80" cy="80" r="' . $r . '" fill="none" stroke-width="24"/>';
        if ($total > 0) {
            $gap = count($segments) > 1 ? 2.0 : 0.0;
            $offset = 0.0;
            foreach ($segments as $s) {
                $len = (float) $s['value'] / $total * $c;
                if ($len <= 0) {
                    continue;
                }
                $draw = max(0.5, $len - $gap);
                $out .= '<circle class="' . self::esc((string) $s['cls']) . '" cx="80" cy="80" r="' . $r . '" fill="none" stroke-width="24" stroke-dasharray="' . self::f($draw) . ' ' . self::f($c) . '" stroke-dashoffset="' . self::f(-$offset) . '" transform="rotate(-90 80 80)"><title>' . self::esc($s['label'] . ': ' . (int) $s['value']) . '</title></circle>';
                $offset += $len;
            }
        }
        $out .= '<text class="dn-big" x="80" y="82" text-anchor="middle">' . self::esc($big) . '</text>';
        $out .= '<text class="dn-small" x="80" y="100" text-anchor="middle">' . self::esc($small) . '</text>';
        return $out . '</svg>';
    }

    /** Colunas com o valor em cima (evolução da carteira). A última coluna fica em destaque. */
    public static function columns(array $labels, array $values, int $w = 340, int $h = 176): string
    {
        $n = count($values);
        if ($n === 0) {
            return '';
        }
        $top = 24;
        $bottom = 26;
        $plotH = $h - $top - $bottom;
        $max = max(1.0, (float) max(array_map('floatval', $values)));
        $slot = $w / $n;
        $bw = min(40.0, $slot * 0.56);
        $out = '<svg class="colchart" viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" role="img" aria-label="Pacientes em carteira por mês">';
        $out .= '<line class="ch-axis" x1="0" y1="' . ($h - $bottom) . '" x2="' . $w . '" y2="' . ($h - $bottom) . '"/>';
        foreach (array_values($values) as $i => $v) {
            $bh = max(2.0, ((float) $v / $max) * $plotH);
            $x = $i * $slot + ($slot - $bw) / 2;
            $y = $h - $bottom - $bh;
            $last = $i === $n - 1;
            $out .= '<path class="' . ($last ? 'col-last' : 'col') . '" d="M' . self::f($x) . ' ' . self::f($h - $bottom) . 'V' . self::f($y + 4) . 'Q' . self::f($x) . ' ' . self::f($y) . ' ' . self::f($x + 4) . ' ' . self::f($y) . 'H' . self::f($x + $bw - 4) . 'Q' . self::f($x + $bw) . ' ' . self::f($y) . ' ' . self::f($x + $bw) . ' ' . self::f($y + 4) . 'V' . self::f($h - $bottom) . 'Z"><title>' . self::esc(($labels[$i] ?? '') . ': ' . (int) $v . ' pacientes') . '</title></path>';
            $out .= '<text class="ch-val' . ($last ? ' ch-val-last' : '') . '" x="' . self::f($x + $bw / 2) . '" y="' . self::f($y - 6) . '" text-anchor="middle">' . (int) $v . '</text>';
            $out .= '<text class="ch-lbl" x="' . self::f($x + $bw / 2) . '" y="' . ($h - 8) . '" text-anchor="middle">' . self::esc((string) ($labels[$i] ?? '')) . '</text>';
        }
        return $out . '</svg>';
    }

    /**
     * Linhas (evolução financeira). $series = [['name' => 'Vendido', 'cls' => 'ch-s1', 'values' => [...]], ...]
     * Eixo único, começando em zero. Só o último valor de cada linha tem rótulo (à direita, afastados se forem parecidos); os demais aparecem ao passar o mouse
     * e na tabela. Retorna '' se tudo for zero.
     */
    public static function lines(array $labels, array $series, int $w = 560, int $h = 260): string
    {
        $n = count($labels);
        $max = 0.0;
        foreach ($series as $s) {
            foreach ($s['values'] as $v) {
                $max = max($max, (float) $v);
            }
        }
        if ($n === 0 || $max <= 0) {
            return '';
        }
        [$step, $steps] = self::niceStep($max);
        $top = $step * $steps;
        $L = 70;
        $R = 96; // espaço à direita para o valor do último mês
        $T = 18;
        $B = 30;
        $plotW = $w - $L - $R;
        $plotH = $h - $T - $B;
        $x = fn(int $i) => $n > 1 ? $L + $i * ($plotW / ($n - 1)) : $L + $plotW / 2;
        $y = fn(float $v) => $T + $plotH - ($v / $top) * $plotH;

        $out = '<svg class="lines" viewBox="0 0 ' . $w . ' ' . $h . '" width="100%" role="img" aria-label="Evolução financeira por mês">';
        for ($g = 0; $g <= $steps; $g++) {
            $val = $g * $step;
            $yy = self::f($y($val));
            $out .= '<line class="' . ($g === 0 ? 'ch-axis' : 'ch-grid') . '" x1="' . $L . '" y1="' . $yy . '" x2="' . ($w - $R + 4) . '" y2="' . $yy . '"/>';
            $out .= '<text class="ch-lbl" x="' . ($L - 8) . '" y="' . self::f($y($val) + 4) . '" text-anchor="end">' . ($g === 0 ? 'R$ 0' : self::esc(self::compact($val))) . '</text>';
        }
        foreach ($labels as $i => $lab) {
            $out .= '<text class="ch-lbl" x="' . self::f($x($i)) . '" y="' . ($h - 8) . '" text-anchor="middle">' . self::esc((string) $lab) . '</text>';
        }
        foreach ($series as $k => $s) {
            $pts = [];
            foreach (array_values($s['values']) as $i => $v) {
                $pts[] = self::f($x($i)) . ',' . self::f($y((float) $v));
            }
            $out .= '<polyline class="' . self::esc((string) $s['cls']) . ' ln" fill="none" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="' . implode(' ', $pts) . '"/>';
        }
        foreach ($series as $k => $s) {
            foreach (array_values($s['values']) as $i => $v) {
                $out .= '<circle class="' . self::esc((string) $s['cls']) . ' dot" cx="' . self::f($x($i)) . '" cy="' . self::f($y((float) $v)) . '" r="4.5"><title>' . self::esc(($labels[$i] ?? '') . ' · ' . $s['name'] . ': ' . 'R$ ' . number_format((float) $v, 2, ',', '.')) . '</title></circle>';
            }
        }
        // Rótulo do último ponto de cada linha, à direita do gráfico. Se os dois valores forem parecidos,
        // os rótulos são afastados na vertical para nunca ficarem um por cima do outro.
        $labs = [];
        foreach ($series as $k => $s) {
            $v = (float) ($s['values'][$n - 1] ?? 0);
            $labs[] = ['y' => $y($v) + 4, 'v' => $v];
        }
        usort($labs, fn($a, $b) => $a['y'] <=> $b['y']);
        $minGap = 18.0;
        for ($i = 1; $i < count($labs); $i++) {
            if ($labs[$i]['y'] - $labs[$i - 1]['y'] < $minGap) {
                $labs[$i]['y'] = $labs[$i - 1]['y'] + $minGap;
            }
        }
        foreach ($labs as $l) {
            $out .= '<text class="ch-val" x="' . self::f($x($n - 1) + 12) . '" y="' . self::f($l['y']) . '" text-anchor="start">' . self::esc(self::compact($l['v'])) . '</text>';
        }
        return $out . '</svg>';
    }
}
