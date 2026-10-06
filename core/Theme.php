<?php
declare(strict_types=1);

namespace Core;

/** Company look & feel: brand colour, sidebar style, density, default light/dark mode. */
final class Theme
{
    public const BRANDS = ['#4f46e5' => 'Indigo', '#2563eb' => 'Blue', '#0d9488' => 'Teal', '#16a34a' => 'Green', '#d97706' => 'Amber', '#e11d48' => 'Rose', '#9333ea' => 'Purple', '#475569' => 'Slate'];
    public const SIDEBARS = ['dark' => 'Dark', 'light' => 'Light', 'brand' => 'Brand colour'];
    public const DENSITY = ['comfortable' => 'Comfortable', 'compact' => 'Compact'];
    public const MODES = ['light' => 'Light', 'dark' => 'Dark', 'auto' => 'Match the device'];
    public const DEFAULTS = ['appearance.brand' => '#4f46e5', 'appearance.sidebar' => 'dark', 'appearance.density' => 'comfortable', 'appearance.mode' => 'light'];

    public static function get(string $key): string
    {
        $v = Auth::user() ? Settings::get($key, self::DEFAULTS[$key]) : self::DEFAULTS[$key];
        return match ($key) {
            'appearance.brand' => preg_match('/^#[0-9a-f]{6}$/i', $v) ? strtolower($v) : self::DEFAULTS[$key],
            'appearance.sidebar' => isset(self::SIDEBARS[$v]) ? $v : 'dark',
            'appearance.density' => isset(self::DENSITY[$v]) ? $v : 'comfortable',
            'appearance.mode' => isset(self::MODES[$v]) ? $v : 'light',
            default => $v,
        };
    }

    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function mix(array $a, array $b, float $t): string
    {
        return sprintf('#%02x%02x%02x', ...array_map(fn($i) => (int)round($a[$i] * (1 - $t) + $b[$i] * $t), [0, 1, 2]));
    }

    private static function lum(array $c): float
    {
        $f = fn($v) => ($v /= 255) <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        return 0.2126 * $f($c[0]) + 0.7152 * $f($c[1]) + 0.0722 * $f($c[2]);
    }

    /** Text colour (white / near-black) that reads on the given colour. */
    public static function ink(string $hex): string
    {
        return self::lum(self::rgb($hex)) > 0.42 ? '#111827' : '#ffffff';
    }

    /** <style> block with the brand variables, plus the data attributes' initial values. */
    public static function css(): string
    {
        $b = self::get('appearance.brand');
        $c = self::rgb($b);
        $vars = [
            '--brand' => $b, '--brand-rgb' => implode(',', $c), '--brand-600' => self::mix($c, [0, 0, 0], .14), '--brand-700' => self::mix($c, [0, 0, 0], .28),
            '--brand-soft' => self::mix($c, [255, 255, 255], .90), '--brand-soft-dark' => self::mix($c, [17, 24, 39], .78), '--brand-ink' => self::ink($b),
            '--brand-text' => self::lum($c) > 0.5 ? self::mix($c, [0, 0, 0], .45) : $b,
            '--sb-brand' => self::mix($c, [0, 0, 0], .30),
        ];
        $o = ':root{';
        foreach ($vars as $k => $v) $o .= "$k:$v;";
        return '<style id="brand-vars">' . $o . '}</style>';
    }

    /** Inline script (run before paint) that applies the saved light/dark choice, so pages never flash. */
    public static function bootScript(): string
    {
        $default = self::get('appearance.mode');
        return '<script>(function(){var m=null;try{m=localStorage.getItem("inv-mode")}catch(e){}'
            . 'if(!m||m==="default")m=' . json_encode($default) . ';'
            . 'if(m==="auto")m=window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches?"dark":"light";'
            . 'var d=document.documentElement;d.setAttribute("data-default-mode",' . json_encode($default) . ');d.setAttribute("data-bs-theme",m);d.setAttribute("data-theme",m);'
            . 'try{if(localStorage.getItem("inv-sb")==="1")d.classList.add("sb-collapsed")}catch(e){}})();</script>';
    }
}
