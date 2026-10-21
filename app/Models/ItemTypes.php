<?php
declare(strict_types=1);

namespace App\Models;

/** Product types of a home-furnishing business. */
final class ItemTypes
{
    public const LABELS = ['fabric' => 'Fabric', 'linen' => 'Linen (bedsheets, towels…)', 'wallpaper' => 'Wallpaper', 'carpet' => 'Carpet / rug', 'accessory' => 'Accessory (rods, rings, lining…)', 'other' => 'Other', 'catalogue' => 'Catalogue (printed book)'];
    /** Types that arrive in rolls and are normally tracked roll by roll. */
    public const ROLL_TYPES = ['fabric', 'wallpaper', 'carpet'];
}
