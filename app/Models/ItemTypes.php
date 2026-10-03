<?php
declare(strict_types=1);

namespace App\Models;

/** Product types of a home-furnishing business, and which descriptive fields make sense for each. */
final class ItemTypes
{
    public const LABELS = ['fabric' => 'Fabric', 'linen' => 'Linen (bedsheets, towels…)', 'wallpaper' => 'Wallpaper', 'carpet' => 'Carpet / rug', 'accessory' => 'Accessory (rods, rings, lining…)', 'other' => 'Other'];
    /** Types that arrive in rolls and are normally tracked roll by roll. */
    public const ROLL_TYPES = ['fabric', 'wallpaper', 'carpet'];
    /** Which attribute fields to show for each type. */
    public const ATTRS = [
        'fabric' => ['design_no', 'composition', 'width', 'gsm', 'pattern', 'finish'],
        'linen' => ['design_no', 'composition', 'gsm', 'pattern', 'finish'],
        'wallpaper' => ['design_no', 'composition', 'width', 'pattern', 'finish'],
        'carpet' => ['design_no', 'composition', 'gsm', 'pattern', 'finish'],
        'accessory' => ['design_no', 'composition', 'finish'],
        'other' => ['design_no', 'composition', 'width', 'gsm', 'pattern', 'finish'],
    ];
    /** Wording that suits each type better than the generic label: [type][field] => label. */
    public const WORDING = [
        'linen' => ['gsm' => 'Thread count / GSM'], 'carpet' => ['gsm' => 'Pile weight / GSM', 'composition' => 'Material', 'finish' => 'Backing / pile type'],
        'wallpaper' => ['composition' => 'Material', 'width' => 'Roll width', 'finish' => 'Finish (matt, textured…)'], 'fabric' => ['width' => 'Fabric width', 'finish' => 'Finish / treatment'],
    ];
}
