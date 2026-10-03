<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use App\Models\Stock;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\Settings;

/** Shared helpers for the purchase screens. */
abstract class PurchaseBase extends Controller
{
    protected function tid(): int
    {
        return (int)Auth::tenantId();
    }

    protected function bounce(string $msg, string $back): never
    {
        flash('danger', $msg);
        with_old($this->input());
        redirect($back);
    }

    protected function date(mixed $v, string $label, string $back, bool $required = true): ?string
    {
        $v = trim((string)$v);
        if ($v === '') {
            if ($required) $this->bounce("$label is required.", $back);
            return null;
        }
        $ok = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
        if (!$ok) $this->bounce("$label is not a valid date.", $back);
        return $v;
    }

    protected function text(mixed $v, int $max, string $label, string $back): ?string
    {
        $v = trim((string)$v);
        if (mb_strlen($v) > $max) $this->bounce("$label is too long (max $max characters).", $back);
        return $v === '' ? null : $v;
    }

    protected function money(mixed $v, string $label, string $back, float $max = 99999999): float
    {
        $v = trim((string)$v);
        if ($v === '') return 0.0;
        if (!is_numeric($v) || (float)$v < 0 || (float)$v > $max) $this->bounce("$label must be a number of zero or more.", $back);
        return round((float)$v, 2);
    }

    protected function activeSupplier(mixed $id, string $back): array
    {
        $s = Purchase::supplier((int)$id);
        if (!$s || !$s['is_active']) $this->bounce('Choose a valid supplier.', $back);
        return $s;
    }

    protected function activeWarehouse(mixed $id, string $back): array
    {
        $w = Stock::warehouse((int)$id);
        if (!$w || !$w['is_active']) $this->bounce('Choose a valid warehouse.', $back);
        return $w;
    }

    /** Settings → Rules: stock coming into a warehouse that has racks must be put on one. */
    protected function rackRequired(int $warehouseId, int $rackId): bool
    {
        return $rackId === 0 && Settings::bool('require_rack')
            && (bool)DB::val('SELECT 1 FROM locations WHERE tenant_id = ? AND warehouse_id = ? AND is_active = 1 LIMIT 1', [$this->tid(), $warehouseId]);
    }

    protected function suppliers(bool $onlyActive = true): array
    {
        return DB::all('SELECT id, name FROM suppliers WHERE tenant_id = ?' . ($onlyActive ? ' AND is_active = 1' : '') . ' ORDER BY name', [$this->tid()]);
    }

    protected function warehouses(): array
    {
        return DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name', [$this->tid()]);
    }

    protected function racksByWarehouse(): array
    {
        $out = [];
        foreach (DB::all('SELECT id, warehouse_id, code FROM locations WHERE tenant_id = ? AND is_active = 1 ORDER BY code', [$this->tid()]) as $r) {
            $out[$r['warehouse_id']][] = ['id' => (int)$r['id'], 'code' => $r['code']];
        }
        return $out;
    }

    /** A purchasable variant (not a bundle, active) with its item info, or null. */
    protected function buyable(int $variantId): ?array
    {
        $v = Stock::variant($variantId);
        return $v && !$v['is_bundle'] && $v['item_active'] && $v['is_active'] ? $v : null;
    }

    /** Whole-number units reject decimals. */
    protected function qtyFor(array $v, mixed $raw, string $prefix, string $back): float
    {
        $raw = trim((string)$raw);
        if ($raw === '' || !is_numeric($raw) || (float)$raw <= 0) $this->bounce("$prefix: enter a quantity above zero.", $back);
        $q = Stock::round((float)$raw);
        if (!$v['allow_decimal'] && floor($q) != $q) $this->bounce("$prefix: " . $v['item_name'] . ' is counted in whole ' . $v['unit'] . '.', $back);
        return $q;
    }

    protected function taxFor(mixed $raw, string $prefix, string $back): float
    {
        $raw = trim((string)$raw);
        if ($raw === '') return 0.0;
        if (!is_numeric($raw) || (float)$raw < 0 || (float)$raw > 100) $this->bounce("$prefix: tax must be between 0 and 100.", $back);
        return round((float)$raw, 2);
    }

    protected function postedLines(string $back, string $key = 'lines'): array
    {
        $lines = array_values(array_filter((array)($this->input()[$key] ?? []),
            fn($l) => is_array($l) && ((int)($l['variant_id'] ?? 0) > 0 || (int)($l['po_item_id'] ?? 0) > 0)));
        if (!$lines) $this->bounce('Add at least one item.', $back);
        if (count($lines) > 200) $this->bounce('Too many lines (max 200).', $back);
        return $lines;
    }

    protected function oldLines(): array
    {
        return array_values((array)($_SESSION['_old']['lines'] ?? []));
    }

    protected function pageOf(string $table, string $where, array $params, int $per = 30): array
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = (int)DB::val("SELECT COUNT(*) FROM $table WHERE $where", $params);
        return [$page, max(1, (int)ceil($total / $per)), ' LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per)];
    }
}
