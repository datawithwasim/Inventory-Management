<?php
declare(strict_types=1);

namespace Core;

/** Parts of the app a company can switch off (Settings → Modules). Hidden from the menu and blocked by URL. */
final class Modules
{
    public const ALL = [
        'requisitions'     => ['Purchase requisitions', 'Internal "we need to buy this" requests that become purchase orders.', ['/purchase/requisitions']],
        'purchase_returns' => ['Purchase returns', 'Send goods back to a supplier.', ['/purchase/returns']],
        'quotations'       => ['Quotations', 'Price quotes to customers before an order.', ['/sales/quotations']],
        'sales_returns'    => ['Sales returns', 'Take goods back from a customer.', ['/sales/returns']],
        'pos'              => ['POS (counter sale)', 'Fast barcode billing at the shop counter.', ['/pos']],
        'transfers'        => ['Stock transfers', 'Move stock between warehouses.', ['/stock/transfers']],
        'takes'            => ['Stock-takes', 'Physical stock counting and corrections.', ['/stock/takes']],
        'labels'           => ['Barcode labels', 'Print item and roll labels.', ['/labels']],
        'reports'          => ['Reports', 'The reports section.', ['/reports']],
    ];

    private static ?array $off = null;

    public static function off(): array
    {
        if (self::$off === null) {
            $v = json_decode(Settings::get('modules.off', '[]'), true);
            self::$off = is_array($v) ? array_values(array_intersect($v, array_keys(self::ALL))) : [];
        }
        return self::$off;
    }

    public static function enabled(?string $key): bool
    {
        return $key === null || !in_array($key, self::off(), true);
    }

    public static function save(array $off): void
    {
        $off = array_values(array_intersect($off, array_keys(self::ALL)));
        Settings::set('modules.off', json_encode($off));
        self::$off = $off;
    }

    /** Which switched-off module (if any) owns this URL path? */
    public static function blocked(string $path): ?string
    {
        foreach (self::off() as $key) {
            foreach (self::ALL[$key][2] as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) return $key;
            }
        }
        return null;
    }
}
