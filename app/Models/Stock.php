<?php
declare(strict_types=1);

namespace App\Models;

use Core\Auth;
use Core\DB;

/**
 * The only place stock changes. Every change is an immutable ledger row plus an
 * updated balance row, in one transaction. Call inside DB::transaction().
 */
final class Stock
{
    private static function tid(): int
    {
        return (int)Auth::tenantId();
    }

    public static function round(float $q): float
    {
        return round($q, 3);
    }

    /** Variant + item + unit info for this company, or null. */
    public static function variant(int $variantId): ?array
    {
        return DB::one(
            'SELECT v.*, i.name AS item_name, i.track_batch, i.is_bundle, i.unit_id, i.is_active AS item_active,
                    u.short_name AS unit, u.allow_decimal
             FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE v.tenant_id = ? AND v.id = ?', [self::tid(), $variantId]);
    }

    public static function label(array $v): string
    {
        return $v['item_name'] . ($v['name'] ? ' — ' . $v['name'] : '') . ' (' . $v['sku'] . ')';
    }

    public static function warehouse(int $id): ?array
    {
        return DB::one('SELECT * FROM warehouses WHERE tenant_id = ? AND id = ?', [self::tid(), $id]);
    }

    public static function batch(int $id): ?array
    {
        return DB::one('SELECT * FROM batches WHERE tenant_id = ? AND id = ?', [self::tid(), $id]);
    }

    public static function location(int $id): ?array
    {
        return DB::one('SELECT * FROM locations WHERE tenant_id = ? AND id = ?', [self::tid(), $id]);
    }

    /** Stock of a variant in a warehouse/batch; in one rack when $locationId is given, else across all racks. */
    public static function balance(int $variantId, int $warehouseId, int $batchId = 0, ?int $locationId = null): float
    {
        return (float)DB::val(
            'SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ?'
            . ($locationId === null ? '' : ' AND location_id = ?'),
            $locationId === null ? [self::tid(), $variantId, $warehouseId, $batchId] : [self::tid(), $variantId, $warehouseId, $batchId, $locationId]);
    }

    /** Where a variant (optionally one batch) sits in a warehouse: racks with stock, biggest first. */
    public static function placements(int $variantId, int $warehouseId, ?int $batchId = null): array
    {
        return DB::all(
            'SELECT s.location_id, COALESCE(l.code, \'\') AS code, SUM(s.qty) AS qty
             FROM stock_balances s LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
             WHERE s.tenant_id = ? AND s.variant_id = ? AND s.warehouse_id = ?' . ($batchId === null ? '' : ' AND s.batch_id = ?') . '
             GROUP BY s.location_id, l.code HAVING qty > 0.0005 ORDER BY qty DESC',
            $batchId === null ? [self::tid(), $variantId, $warehouseId] : [self::tid(), $variantId, $warehouseId, $batchId]);
    }

    public static function rackName(?string $code): string
    {
        return $code !== null && $code !== '' ? $code : 'No rack';
    }

    /** Creates a roll/thaan batch with an auto number like SKU-B0001. */
    public static function newBatch(int $variantId, float $qty, ?string $lot = null, float $cost = 0, ?string $date = null, ?string $note = null): int
    {
        $v = self::variant($variantId) ?? throw new StockException('Item not found.');
        if (!$v['track_batch']) throw new StockException($v['item_name'] . ' is not batch tracked.');
        $n = (int)DB::val('SELECT COUNT(*) FROM batches WHERE tenant_id = ? AND variant_id = ?', [self::tid(), $variantId]);
        do {
            $no = sprintf('%s-B%04d', $v['sku'], ++$n);
        } while (DB::val('SELECT 1 FROM batches WHERE tenant_id = ? AND batch_no = ?', [self::tid(), $no]));
        return DB::insert('batches', [
            'tenant_id' => self::tid(), 'variant_id' => $variantId, 'batch_no' => $no,
            'supplier_lot' => $lot !== null && $lot !== '' ? $lot : null,
            'received_date' => $date ?: date('Y-m-d'), 'received_qty' => self::round($qty),
            'unit_cost' => $cost, 'note' => $note,
        ]);
    }

    public static function move(
        int $variantId, int $warehouseId, int $batchId, float $qty, string $type,
        ?string $refType = null, ?int $refId = null, ?float $cost = null, ?string $note = null, int $locationId = 0
    ): void {
        $tid = self::tid();
        $qty = self::round($qty);
        if ($qty == 0.0) throw new StockException('Quantity cannot be zero.');

        $v = self::variant($variantId) ?? throw new StockException('Item not found.');
        if ($v['is_bundle']) throw new StockException($v['item_name'] . ' is a bundle; stock is held by its components.');
        if (!self::warehouse($warehouseId)) throw new StockException('Warehouse not found.');
        if (!$v['allow_decimal'] && floor($qty) != $qty) {
            throw new StockException(self::label($v) . ' is counted in whole ' . $v['unit'] . '; decimals are not allowed.');
        }

        $rack = null;
        if ($locationId > 0) {
            $rack = self::location($locationId);
            if (!$rack || (int)$rack['warehouse_id'] !== $warehouseId) {
                throw new StockException('That rack does not belong to the chosen warehouse.');
            }
            if (!$rack['is_active'] && $qty > 0) throw new StockException('Rack ' . $rack['code'] . ' is inactive; choose another rack.');
        } else {
            $locationId = 0;
        }

        if ($v['track_batch']) {
            $b = $batchId > 0 ? self::batch($batchId) : null;
            if (!$b || (int)$b['variant_id'] !== $variantId) {
                throw new StockException(self::label($v) . ' is batch tracked: choose a valid batch.');
            }
        } else {
            $batchId = 0;
        }

        DB::run('INSERT IGNORE INTO stock_balances (tenant_id, variant_id, warehouse_id, batch_id, location_id, qty) VALUES (?,?,?,?,?,0)',
            [$tid, $variantId, $warehouseId, $batchId, $locationId]);
        $have = (float)DB::val(
            'SELECT qty FROM stock_balances WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ? FOR UPDATE',
            [$tid, $variantId, $warehouseId, $batchId, $locationId]);
        if ($have + $qty < -0.0005) {
            $where = ($batchId ? ' in batch ' . $b['batch_no'] : '') . ' at ' . self::rackName($rack['code'] ?? null);
            $elsewhere = '';
            $total = self::balance($variantId, $warehouseId, $batchId);
            if ($total > $have + 0.0005) $elsewhere = ' (' . self::fmt($total) . ' ' . $v['unit'] . ' in this warehouse on other racks)';
            throw new StockException('Not enough stock for ' . self::label($v) . $where . ': only '
                . self::fmt($have) . ' ' . $v['unit'] . ' available' . $elsewhere . '.');
        }
        DB::run('UPDATE stock_balances SET qty = qty + ? WHERE tenant_id = ? AND variant_id = ? AND warehouse_id = ? AND batch_id = ? AND location_id = ?',
            [$qty, $tid, $variantId, $warehouseId, $batchId, $locationId]);
        DB::insert('stock_ledger', [
            'tenant_id' => $tid, 'variant_id' => $variantId, 'warehouse_id' => $warehouseId, 'batch_id' => $batchId, 'location_id' => $locationId,
            'qty_change' => $qty, 'type' => $type, 'ref_type' => $refType, 'ref_id' => $refId,
            'unit_cost' => $cost, 'note' => $note, 'user_id' => Auth::user()['id'] ?? null,
        ]);
    }

    /** Variants matching name / SKU / barcode, for pickers. Bundles excluded. */
    public static function search(string $q, int $limit = 15): array
    {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        return DB::all(
            'SELECT v.id, v.sku, v.name, i.name AS item_name, i.track_batch, u.short_name AS unit, u.allow_decimal
             FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE v.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND v.is_active = 1
               AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?)
             ORDER BY i.name, v.name LIMIT ' . (int)$limit,
            [self::tid(), $like, $like, $like, $q]);
    }

    /** Batches of a variant with their balance (in one warehouse, or all). */
    public static function batchesFor(int $variantId, ?int $warehouseId, bool $onlyInStock = true): array
    {
        $sql = 'SELECT b.id, b.batch_no, b.supplier_lot, b.received_date, b.received_qty,
                       COALESCE(SUM(s.qty), 0) AS balance
                FROM batches b LEFT JOIN stock_balances s
                  ON s.batch_id = b.id AND s.tenant_id = b.tenant_id' . ($warehouseId ? ' AND s.warehouse_id = ?' : '') . '
                WHERE b.tenant_id = ? AND b.variant_id = ?
                GROUP BY b.id' . ($onlyInStock ? ' HAVING balance > 0' : '') . ' ORDER BY b.received_date, b.id';
        $params = $warehouseId ? [$warehouseId, self::tid(), $variantId] : [self::tid(), $variantId];
        return DB::all($sql, $params);
    }

    public static function batchStatus(float $balance, float $received): string
    {
        if ($balance <= 0.0005) return 'Finished';
        return $balance < $received - 0.0005 ? 'Partially used' : 'Available';
    }

    /** How many sets of a bundle can be made from the components in stock. */
    public static function bundleAvailable(int $itemId): int
    {
        $rows = DB::all(
            'SELECT bc.qty, COALESCE((SELECT SUM(s.qty) FROM stock_balances s
                    WHERE s.tenant_id = bc.tenant_id AND s.variant_id = bc.component_variant_id), 0) AS have
             FROM bundle_components bc WHERE bc.tenant_id = ? AND bc.bundle_item_id = ?', [self::tid(), $itemId]);
        if (!$rows) return 0;
        return (int)min(array_map(fn($r) => floor((float)$r['have'] / max((float)$r['qty'], 0.001)), $rows));
    }

    public static function fmt(float|string|null $q): string
    {
        $s = number_format((float)$q, 3, '.', '');
        return rtrim(rtrim($s, '0'), '.') ?: '0';
    }
}
