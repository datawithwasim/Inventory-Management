<?php
declare(strict_types=1);

namespace Core;

/**
 * QR Code encoder (byte mode, error correction L or M, versions 1–10: up to 213 bytes at M, 271 at L), output as inline SVG.
 * Enough for an item code, a URL or a few fields; nothing to install.
 */
final class Qr
{
    /** version => [data codewords per block group..., EC codewords per block]: [ecPerBlock, [[blocks, dataCw], ...]] */
    private const BLOCKS = [
        'L' => [1 => [7, [[1, 19]]], 2 => [10, [[1, 34]]], 3 => [15, [[1, 55]]], 4 => [20, [[1, 80]]], 5 => [26, [[1, 108]]], 6 => [18, [[2, 68]]], 7 => [20, [[2, 78]]],
            8 => [24, [[2, 97]]], 9 => [30, [[2, 116]]], 10 => [18, [[2, 68], [2, 69]]]],
        'M' => [1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]], 6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]],
            8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]]],
    ];
    private const ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    public const MAX_BYTES = ['L' => 271, 'M' => 213];

    /** @return int[][] rows of 0/1 (1 = dark), without the quiet zone */
    public static function matrix(string $text, string $ecc = 'M'): array
    {
        $ecc = $ecc === 'L' ? 'L' : 'M';
        $bytes = array_values(unpack('C*', $text) ?: []);
        $ver = 0;
        foreach (self::BLOCKS[$ecc] as $v => [$ec, $groups]) {
            $data = 0;
            foreach ($groups as [$n, $cw]) $data += $n * $cw;
            $countBits = $v < 10 ? 8 : 16;
            if (4 + $countBits + 8 * count($bytes) <= $data * 8) { $ver = $v; break; }
        }
        if (!$ver) throw new \InvalidArgumentException('Too much data for the QR code (max ' . self::MAX_BYTES[$ecc] . ' characters).');
        [$ecPer, $groups] = self::BLOCKS[$ecc][$ver];
        $dataCw = 0;
        foreach ($groups as [$n, $cw]) $dataCw += $n * $cw;

        // bit stream
        $bits = [];
        $put = function (int $val, int $len) use (&$bits) { for ($i = $len - 1; $i >= 0; $i--) $bits[] = ($val >> $i) & 1; };
        $put(0b0100, 4);
        $put(count($bytes), $ver < 10 ? 8 : 16);
        foreach ($bytes as $b) $put($b, 8);
        $cap = $dataCw * 8;
        for ($i = 0; $i < 4 && count($bits) < $cap; $i++) $bits[] = 0;
        while (count($bits) % 8) $bits[] = 0;
        $cw = [];
        foreach (array_chunk($bits, 8) as $c) $cw[] = bindec(implode('', $c));
        for ($pad = 0; count($cw) < $dataCw; $pad++) $cw[] = $pad % 2 ? 0x11 : 0xEC;

        // blocks + Reed–Solomon, interleaved
        $blocks = [];
        $pos = 0;
        foreach ($groups as [$n, $len]) {
            for ($i = 0; $i < $n; $i++) { $blocks[] = ['d' => array_slice($cw, $pos, $len), 'e' => []]; $pos += $len; }
        }
        $gen = self::generator($ecPer);
        foreach ($blocks as &$b) $b['e'] = self::remainder($b['d'], $gen);
        unset($b);
        $final = [];
        $maxLen = max(array_map(fn($b) => count($b['d']), $blocks));
        for ($i = 0; $i < $maxLen; $i++) foreach ($blocks as $b) if (isset($b['d'][$i])) $final[] = $b['d'][$i];
        for ($i = 0; $i < $ecPer; $i++) foreach ($blocks as $b) $final[] = $b['e'][$i];
        $stream = [];
        foreach ($final as $c) for ($i = 7; $i >= 0; $i--) $stream[] = ($c >> $i) & 1;

        // function patterns
        $size = 17 + 4 * $ver;
        $m = array_fill(0, $size, array_fill(0, $size, 0));
        $fn = array_fill(0, $size, array_fill(0, $size, false));
        $set = function (int $x, int $y, int $v) use (&$m, &$fn, $size) { if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) { $m[$y][$x] = $v; $fn[$y][$x] = true; } };
        for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 === 0 ? 1 : 0); $set($i, 6, $i % 2 === 0 ? 1 : 0); }
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $set($cx + $dx, $cy + $dy, ($d !== 2 && $d !== 4) ? 1 : 0);
            }
        }
        $ap = self::ALIGN[$ver];
        foreach ($ap as $i => $cy) foreach ($ap as $j => $cx) {
            $last = count($ap) - 1;
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) continue;
            for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1 ? 1 : 0);
        }
        // reserve the format and version areas (real values are written per mask)
        $setFormat = function (int $mask) use (&$m, &$fn, $size, $ecc) {
            $data = ($ecc === 'L' ? 1 : 0) << 3 | $mask;
            $rem = $data;
            for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
            $bitsF = (($data << 10) | $rem) ^ 0x5412;
            $b = fn(int $i) => ($bitsF >> $i) & 1;
            $s = function (int $x, int $y, int $v) use (&$m, &$fn) { $m[$y][$x] = $v; $fn[$y][$x] = true; };
            for ($i = 0; $i <= 5; $i++) $s(8, $i, $b($i));
            $s(8, 7, $b(6)); $s(8, 8, $b(7)); $s(7, 8, $b(8));
            for ($i = 9; $i < 15; $i++) $s(14 - $i, 8, $b($i));
            for ($i = 0; $i < 8; $i++) $s($size - 1 - $i, 8, $b($i));
            for ($i = 8; $i < 15; $i++) $s(8, $size - 15 + $i, $b($i));
            $s(8, $size - 8, 1);
        };
        $setFormat(0);
        if ($ver >= 7) {
            $rem = $ver;
            for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
            $vb = ($ver << 12) | $rem;
            for ($i = 0; $i < 18; $i++) {
                $bit = ($vb >> $i) & 1; $a = $size - 11 + $i % 3; $b2 = intdiv($i, 3);
                $set($a, $b2, $bit); $set($b2, $a, $bit);
            }
        }

        // data
        $i = 0;
        $total = count($stream);
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) $right = 5;
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
                    if (!$fn[$y][$x] && $i < $total) $m[$y][$x] = $stream[$i++];
                }
            }
        }

        // best mask
        $best = null; $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $t = $m;
            for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) {
                if ($fn[$y][$x]) continue;
                $inv = match ($mask) {
                    0 => ($x + $y) % 2 === 0, 1 => $y % 2 === 0, 2 => $x % 3 === 0, 3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, 5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0, default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($inv) $t[$y][$x] ^= 1;
            }
            // write this mask's format bits into $t
            $mm = $m; $ff = $fn; $m = $t;
            $setFormat($mask);
            $t = $m; $m = $mm; $fn = $ff;
            $score = self::penalty($t, $size);
            if ($score < $bestScore) { $bestScore = $score; $best = $t; }
        }
        return $best;
    }

    private static function penalty(array $m, int $n): int
    {
        $score = 0;
        $dark = 0;
        for ($y = 0; $y < $n; $y++) {
            for ($dir = 0; $dir < 2; $dir++) {
                $run = 1;
                $line = [];
                for ($x = 0; $x < $n; $x++) $line[] = $dir === 0 ? $m[$y][$x] : $m[$x][$y];
                for ($x = 1; $x < $n; $x++) {
                    if ($line[$x] === $line[$x - 1]) { $run++; if ($run === 5) $score += 3; elseif ($run > 5) $score++; } else $run = 1;
                }
                // 1:1:3:1:1 finder-like pattern with four light modules on a side
                $s = implode('', $line);
                $score += 40 * (substr_count($s, '10111010000') + substr_count($s, '00001011101'));
            }
            for ($x = 0; $x < $n; $x++) {
                $dark += $m[$y][$x];
                if ($x + 1 < $n && $y + 1 < $n && $m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) $score += 3;
            }
        }
        $total = $n * $n;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $score + max(0, $k) * 10;
    }

    /** Reed–Solomon over GF(256), polynomial 0x11D. */
    private static function mul(int $a, int $b): int
    {
        static $exp = null, $log = null;
        if ($exp === null) {
            $exp = []; $log = array_fill(0, 256, 0);
            $x = 1;
            for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
            for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
        }
        return ($a === 0 || $b === 0) ? 0 : $exp[$log[$a] + $log[$b]];
    }

    private static function generator(int $deg): array
    {
        $g = [1];
        $root = 1;
        for ($i = 0; $i < $deg; $i++) {
            $next = array_fill(0, count($g) + 1, 0);
            foreach ($g as $j => $c) {
                $next[$j] ^= self::mul($c, $root);
                $next[$j + 1] ^= $c;
            }
            $g = $next;
            $root = self::mul($root, 2);
        }
        return $g;   // lowest degree first, leading coefficient last (= 1)
    }

    private static function remainder(array $data, array $gen): array
    {
        $deg = count($gen) - 1;
        $r = array_fill(0, $deg, 0);
        foreach ($data as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            if ($f !== 0) for ($i = 0; $i < $deg; $i++) $r[$i] ^= self::mul($gen[$deg - 1 - $i], $f);
        }
        return $r;
    }

    public static function svg(string $text, string $ecc = 'M'): string
    {
        $m = self::matrix($text, $ecc);
        $n = count($m);
        $q = 2;                         // quiet zone (modules); the label designer adds margin around it
        $d = '';
        foreach ($m as $y => $row) {
            $x = 0;
            while ($x < $n) {
                if ($row[$x]) {
                    $s = $x;
                    while ($x < $n && $row[$x]) $x++;
                    $d .= 'M' . ($s + $q) . ' ' . ($y + $q) . 'h' . ($x - $s) . 'v1h-' . ($x - $s) . 'z';
                } else $x++;
            }
        }
        $t = $n + 2 * $q;
        return '<svg xmlns="http://www.w3.org/2000/svg" class="bc" viewBox="0 0 ' . $t . ' ' . $t . '" preserveAspectRatio="xMidYMid meet" shape-rendering="crispEdges" role="img" aria-label="QR code"><rect width="' . $t . '" height="' . $t . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></svg>';
    }
}
