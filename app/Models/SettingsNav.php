<?php
declare(strict_types=1);

namespace App\Models;

/** The Settings menu: grouped, like a SaaS admin console. */
final class SettingsNav
{
    public const GROUPS = [
        'Organisation' => [
            'company' => ['Company profile', 'building', 'settings/company'],
            'preferences' => ['Currency & dates', 'currency-exchange', 'settings/preferences'],
            'numbering' => ['Document numbers', 'hash', 'settings/numbering'],
        ],
        'Make it yours' => [
            'appearance' => ['Appearance', 'palette', 'settings/appearance'],
            'modules' => ['Modules & menu', 'grid-1x2', 'settings/modules'],
            'labels' => ['Names (labels)', 'type', 'settings/labels'],
            'templates' => ['Print templates', 'printer', 'settings/templates'],
        ],
        'Rules' => [
            'workflow' => ['Rules & workflow', 'diagram-3', 'settings/workflow'],
        ],
    ];

    public static function valid(string $tab): bool
    {
        foreach (self::GROUPS as $g) if (isset($g[$tab])) return true;
        return false;
    }
}
