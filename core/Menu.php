<?php
declare(strict_types=1);

namespace Core;

/** Builds the sidebar / quick-create / search shortcuts for the signed-in user from config/menu.php. */
final class Menu
{
    private static function allowed(array $it): bool
    {
        if (!Modules::enabled($it['module'] ?? null)) return false;
        $perm = $it['perm'] ?? [];
        if (!$perm) return true;
        foreach ($perm as $p) if (Auth::can($p)) return true;
        return false;
    }

    private static function norm(array $it): array
    {
        return ['label' => $it['label'] instanceof \Closure ? ($it['label'])() : $it['label'], 'icon' => $it['icon'], 'path' => $it['path'], 'href' => $it['href'] ?? $it['path'], 'exact' => !empty($it['exact'])];
    }

    /** @return array{dashboard: array, groups: array} */
    public static function sidebar(): array
    {
        $cfg = config('menu');
        $groups = [];
        foreach ($cfg['groups'] as $key => $g) {
            $items = array_map([self::class, 'norm'], array_values(array_filter($g['items'], [self::class, 'allowed'])));
            if ($items) $groups[$key] = ['label' => $g['label'], 'icon' => $g['icon'], 'items' => $items];
        }
        return ['dashboard' => self::norm($cfg['dashboard']), 'groups' => $groups];
    }

    public static function quick(): array
    {
        return array_map([self::class, 'norm'], array_values(array_filter(config('menu')['quick'], [self::class, 'allowed'])));
    }

    public static function isActive(array $it, string $path): bool
    {
        return $it['exact'] ? $path === $it['path'] : ($path === $it['path'] || str_starts_with($path, $it['path'] . '/'));
    }

    /** The most specific menu entry for a path: [group label|null, item|null]. */
    public static function current(array $menu, string $path): array
    {
        $best = [null, null];
        $len = -1;
        foreach ($menu['groups'] as $g) {
            foreach ($g['items'] as $it) {
                if (self::isActive($it, $path) && strlen($it['path']) > $len) { $best = [$g['label'], $it]; $len = strlen($it['path']); }
            }
        }
        return $best;
    }
}
