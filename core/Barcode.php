<?php
declare(strict_types=1);

namespace Core;

/** One entry point for every symbology a label can use; each returns an inline SVG that stretches to its box. */
final class Barcode
{
    public const SYMBOLS = [
        'code128' => 'Code 128 (any text — best for most scanners)',
        'ean13' => 'EAN-13 (12 or 13 digits)',
        'code39' => 'Code 39 (capitals, digits, - . space $ / + %)',
        'qr' => 'QR code (any text, a link, several fields)',
    ];

    /** Cleans the text for a symbology; null when it cannot be encoded at all. */
    public static function prepare(string $sym, string $text): ?string
    {
        $text = trim($text);
        if ($text === '') return null;
        switch ($sym) {
            case 'ean13':
                $d = preg_replace('/\D/', '', $text) ?? '';
                if (strlen($d) === 12) return $d . self::ean13Check($d);
                if (strlen($d) === 13 && self::ean13Check(substr($d, 0, 12)) === (int)$d[12]) return $d;
                return null;
            case 'code39':
                $t = strtoupper($text);
                return preg_match('/^[0-9A-Z\-. $\/+%]+$/', $t) ? $t : null;
            case 'qr':
                return strlen($text) <= Qr::MAX_BYTES['L'] ? $text : null;
            default:
                $t = Code128::clean($text);
                return $t !== '' ? $t : null;
        }
    }

    /** SVG for the symbology, or a small "cannot encode" note when the value does not fit it. */
    public static function svg(string $sym, string $text, int $height = 40, string $ecc = 'M'): string
    {
        $v = self::prepare($sym, $text);
        if ($v === null) return self::invalid($sym);
        return match ($sym) {
            'ean13' => self::ean13($v, $height),
            'code39' => self::code39($v, $height),
            'qr' => Qr::svg($v, strlen($v) > Qr::MAX_BYTES['M'] ? 'L' : $ecc),
            default => Code128::svg($v, $height),
        };
    }

    private static function invalid(string $sym): string
    {
        $why = $sym === 'ean13' ? 'Not a valid EAN-13' : ($sym === 'code39' ? 'Not valid for Code 39' : 'Cannot encode');
        return '<svg xmlns="http://www.w3.org/2000/svg" class="bc bc-bad" viewBox="0 0 120 20" preserveAspectRatio="none"><rect width="120" height="20" fill="#fff" stroke="#c00" stroke-dasharray="3 2"/><text x="60" y="13" font-size="8" fill="#c00" text-anchor="middle" font-family="Arial">' . htmlspecialchars($why) . '</text></svg>';
    }

    private static function ean13Check(string $d12): int
    {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) $sum += (int)$d12[$i] * ($i % 2 ? 3 : 1);
        return (10 - $sum % 10) % 10;
    }

    private static function bars(string $modules, int $height, string $label): string
    {
        // $modules: a string of 0 / 1, one char per module width
        $x = 10; $out = ''; $n = strlen($modules); $i = 0;
        while ($i < $n) {
            if ($modules[$i] === '1') {
                $s = $i;
                while ($i < $n && $modules[$i] === '1') $i++;
                $out .= '<rect x="' . ($x + $s) . '" y="0" width="' . ($i - $s) . '" height="' . $height . '"/>';
            } else $i++;
        }
        $total = $n + 20;
        return '<svg xmlns="http://www.w3.org/2000/svg" class="bc" viewBox="0 0 ' . $total . ' ' . $height . '" preserveAspectRatio="none" shape-rendering="crispEdges" role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES) . '"><g fill="#000">' . $out . '</g></svg>';
    }

    private static function ean13(string $d, int $height): string
    {
        $L = ['0001101', '0011001', '0010011', '0111101', '0100011', '0110001', '0101111', '0111011', '0110111', '0001011'];
        $G = ['0100111', '0110011', '0011011', '0100001', '0011101', '0111001', '0000101', '0010001', '0001001', '0010111'];
        $R = ['1110010', '1100110', '1101100', '1000010', '1011100', '1001110', '1010000', '1000100', '1001000', '1110100'];
        $par = ['LLLLLL', 'LLGLGG', 'LLGGLG', 'LLGGGL', 'LGLLGG', 'LGGLLG', 'LGGGLL', 'LGLGLG', 'LGLGGL', 'LGGLGL'][(int)$d[0]];
        $m = '101';
        for ($i = 0; $i < 6; $i++) $m .= $par[$i] === 'L' ? $L[(int)$d[$i + 1]] : $G[(int)$d[$i + 1]];
        $m .= '01010';
        for ($i = 7; $i < 13; $i++) $m .= $R[(int)$d[$i]];
        $m .= '101';
        return self::bars($m, $height, 'EAN-13 ' . $d);
    }

    private static function code39(string $text, int $height): string
    {
        static $P = ['0' => 'nnnwwnwnn', '1' => 'wnnwnnnnw', '2' => 'nnwwnnnnw', '3' => 'wnwwnnnnn', '4' => 'nnnwwnnnw', '5' => 'wnnwwnnnn', '6' => 'nnwwwnnnn', '7' => 'nnnwnnwnw', '8' => 'wnnwnnwnn', '9' => 'nnwwnnwnn',
            'A' => 'wnnnnwnnw', 'B' => 'nnwnnwnnw', 'C' => 'wnwnnwnnn', 'D' => 'nnnnwwnnw', 'E' => 'wnnnwwnnn', 'F' => 'nnwnwwnnn', 'G' => 'nnnnnwwnw', 'H' => 'wnnnnwwnn', 'I' => 'nnwnnwwnn', 'J' => 'nnnnwwwnn',
            'K' => 'wnnnnnnww', 'L' => 'nnwnnnnww', 'M' => 'wnwnnnnwn', 'N' => 'nnnnwnnww', 'O' => 'wnnnwnnwn', 'P' => 'nnwnwnnwn', 'Q' => 'nnnnnnwww', 'R' => 'wnnnnnwwn', 'S' => 'nnwnnnwwn', 'T' => 'nnnnwnwwn',
            'U' => 'wwnnnnnnw', 'V' => 'nwwnnnnnw', 'W' => 'wwwnnnnnn', 'X' => 'nwnnwnnnw', 'Y' => 'wwnnwnnnn', 'Z' => 'nwwnwnnnn', '-' => 'nwnnnnwnw', '.' => 'wwnnnnwnn', ' ' => 'nwwnnnwnn',
            '*' => 'nwnnwnwnn', '$' => 'nwnwnwnnn', '/' => 'nwnwnnnwn', '+' => 'nwnnnwnwn', '%' => 'nnnwnwnwn'];
        $m = '';
        foreach (str_split('*' . $text . '*') as $k => $ch) {
            if ($k > 0) $m .= '0';                       // narrow gap between characters
            foreach (str_split($P[$ch]) as $i => $e) $m .= str_repeat($i % 2 === 0 ? '1' : '0', $e === 'w' ? 3 : 1);
        }
        return self::bars($m, $height, 'Code 39 ' . $text);
    }
}
