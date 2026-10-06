<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Sales;
use App\Models\Stock;
use Core\Settings;
use Core\DB;

/** Helpers shared by the sales screens (builds on the purchase helpers: dates, text, money, quantities). */
abstract class SalesBase extends PurchaseBase
{
    /** A sellable variant: any active item, including sets (bundles). */
    protected function sellable(int $variantId): ?array
    {
        $v = Stock::variant($variantId);
        return $v && $v['item_active'] && $v['is_active'] ? $v : null;
    }

    protected function activeCustomer(mixed $id, string $back): array
    {
        $c = Sales::customer((int)$id);
        if (!$c || !$c['is_active']) $this->bounce('Choose a valid customer.', $back);
        return $c;
    }

    protected function customers(bool $onlyActive = true): array
    {
        return DB::all('SELECT id, name, is_walkin FROM customers WHERE tenant_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY is_walkin DESC, name', [$this->tid()]);
    }

    protected function percent(mixed $raw, string $label, string $back): float
    {
        $raw = trim((string)$raw);
        if ($raw === '') return 0.0;
        if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100) $this->bounce("$label must be between 0 and 100.", $back);
        return round((float)$raw, 2);
    }

    /** The company's discount limit (Settings → Rules); people who can approve sales are exempt. */
    public static function discountLimitExceeded(float $disc): ?float
    {
        $max = Settings::float('max_discount');
        return $max > 0 && $disc > $max + 0.0001 && !can('sales.approve') ? $max : null;
    }

    protected function checkDiscount(float $disc, string $prefix, string $back): void
    {
        if (($max = self::discountLimitExceeded($disc)) !== null) $this->bounce("$prefix: a discount above " . qty($max) . '% needs someone with approval rights.', $back);
    }

    /** Priced lines for orders. */
    protected function collectSaleLines(array $raw, string $back): array
    {
        $out = [];
        $seen = [];
        foreach ($raw as $n => $l) {
            $prefix = 'Line ' . ($n + 1);
            $v = $this->sellable((int)($l['variant_id'] ?? 0)) ?? $this->bounce("$prefix: choose a valid item.", $back);
            if (isset($seen[$v['id']])) $this->bounce("$prefix: " . $v['item_name'] . ' is listed twice. Combine the quantities.', $back);
            $seen[$v['id']] = 1;
            $disc = $this->percent($l['discount_pct'] ?? '', "$prefix discount", $back);
            $this->checkDiscount($disc, $prefix, $back);
            $out[] = ['variant_id' => (int)$v['id'], 'qty' => $this->qtyFor($v, $l['qty'] ?? '', $prefix, $back),
                'price' => $this->money($l['unit_price'] ?? '', "$prefix price", $back), 'disc' => $disc,
                'tax' => $this->taxFor($l['tax_rate'] ?? '', $prefix, $back)];
        }
        return $out;
    }

    protected function saleLabel(array $l): string
    {
        return $l['item_name'] . ($l['vname'] ? ' — ' . $l['vname'] : '') . ' (' . $l['sku'] . ')';
    }
}
