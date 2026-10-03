<?php
declare(strict_types=1);

namespace Core;

/** Code 128 (set B) barcode as inline SVG — prints crisply and scans with any normal scanner. */
final class Code128
{
    private const PATTERNS = ['212222','222122','222221','121223','121322','131222','122213','122312','132212','221213','221312','231212','112232','122132','122231','113222','123122','123221','223211','221132','221231','213212','223112','312131','311222','321122','321221','312212','322112','322211','212123','212321','232121','111323','131123','131321','112313','132113','132311','211313','231113','231311','112133','112331','132131','113123','113321','133121','313121','211331','231131','213113','213311','213131','311123','311321','331121','312113','312311','332111','314111','221411','431111','111224','111422','121124','121421','141122','141221','112214','112412','122114','122411','142112','142211','241211','221114','413111','241112','134111','111242','121142','121241','114212','124112','124211','411212','421112','421211','212141','214121','412121','111143','111341','131141','114113','114311','411113','411311','113141','114131','311141','411131','211412','211214','211232','2331112'];

    public static function clean(string $text): string
    {
        return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
    }

    public static function svg(string $text, int $height = 40): string
    {
        $text = self::clean($text);
        if ($text === '') return '';
        $codes = [104];
        $sum = 104;
        foreach (str_split($text) as $i => $ch) {
            $v = ord($ch) - 32;
            $codes[] = $v;
            $sum += $v * ($i + 1);
        }
        $codes[] = $sum % 103;
        $codes[] = 106;
        $x = 10; // quiet zone
        $bars = '';
        foreach ($codes as $c) {
            $p = self::PATTERNS[$c];
            foreach (str_split($p) as $i => $w) {
                if ($i % 2 === 0) $bars .= '<rect x="' . $x . '" y="0" width="' . $w . '" height="' . $height . '"/>';
                $x += (int)$w;
            }
        }
        $total = $x + 10;
        return '<svg xmlns="http://www.w3.org/2000/svg" class="bc" viewBox="0 0 ' . $total . ' ' . $height . '" preserveAspectRatio="none" role="img" aria-label="Barcode ' . htmlspecialchars($text, ENT_QUOTES) . '"><g fill="#000">' . $bars . '</g></svg>';
    }
}
