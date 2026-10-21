<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Sales;
use App\Models\Stock;
use Core\Auth;
use Core\Controller;
use Core\DB;

/** JSON helpers for the sales screens (item picker with customer prices, stock by roll / rack). */
final class SalesLookupController extends Controller
{
    private function json(mixed $d): never
    {
        header('Content-Type: application/json');
        echo json_encode($d);
        exit;
    }

    /** One pick-list row: the item (stock side) and, when it is a product, the product (showroom side) with its own price. */
    private function shape(array $v, int $customerId, int $warehouseId): array
    {
        $isProduct = isset($v['product_id']);
        [$price, $disc] = Sales::priceFor((int)$v['id'], $customerId, $isProduct ? (float)$v['product_price'] : null);
        $out = ['id' => (int)$v['id'], 'sku' => $v['sku'], 'name' => $v['name'], 'item_name' => $v['item_name'], 'unit' => $v['unit'],
            'allow_decimal' => (int)$v['allow_decimal'], 'track_batch' => (int)$v['track_batch'], 'is_bundle' => (int)$v['is_bundle'],
            'price' => $price, 'discount' => $disc, 'tax_rate' => (float)$v['tax_rate'], 'cost_price' => (float)$v['cost_price']];
        if ($isProduct) $out += ['product_id' => (int)$v['product_id'], 'product_name' => $v['product_name'], 'catalogue' => $v['catalogue']];
        if ($warehouseId) {
            $out['available'] = $v['is_bundle']
                ? (float)Stock::bundleAvailable((int)$v['item_id'])
                : round(Sales::available((int)$v['id'], $warehouseId), 3);
        }
        return $out;
    }

    private const SELECT = 'SELECT v.id, v.sku, v.name, v.cost_price, v.item_id, i.name AS item_name, i.track_batch, i.is_bundle, u.short_name AS unit, u.allow_decimal, COALESCE(x.rate, 0) AS tax_rate
        FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN taxes x ON x.id = i.tax_id
        WHERE v.tenant_id = ? AND i.is_active = 1 AND v.is_active = 1';

    /**
     * What the showroom sells: search by product name ("caravan 7" finds CARAVAN-7), catalogue, item name, SKU or barcode.
     * Products come first; an item that has no product yet can still be sold as the item itself. ?mode=item gives plain items only (price lists use it).
     */
    public function items(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') $this->json([]);
        $t = (int)Auth::tenantId();
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $c = (int)($_GET['customer'] ?? 0);
        $w = (int)($_GET['warehouse'] ?? 0);
        $rows = [];
        if (($_GET['mode'] ?? '') !== 'item') {
            $dash = preg_replace('/\s+/', '-', $q);
            $likeDash = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $dash) . '%';
            $rows = DB::all('SELECT p.id AS product_id, p.name AS product_name, p.price AS product_price, c.name AS catalogue, v.id, v.sku, v.name, v.cost_price, v.item_id, i.name AS item_name, i.track_batch, i.is_bundle,
                    u.short_name AS unit, u.allow_decimal, COALESCE(x.rate, 0) AS tax_rate
                FROM products p LEFT JOIN catalogues c ON c.id = p.catalogue_id JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN taxes x ON x.id = i.tax_id
                WHERE p.tenant_id = ? AND p.is_active = 1 AND (p.catalogue_id IS NULL OR c.is_active = 1) AND i.is_active = 1 AND v.is_active = 1
                  AND (p.name LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR c.name LIKE ? OR i.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?)
                ORDER BY (p.name = ?) DESC, (p.name = ?) DESC, (p.name LIKE ?) DESC, LENGTH(p.name), p.name LIMIT 15',
                [$t, $like, $likeDash, $like, $like, $like, $like, $q, $dash, $q, $likeDash . '', ]);
        }
        $room = 15 - count($rows);
        if ($room > 0) {
            $bare = ($_GET['mode'] ?? '') === 'item' ? '' : ' AND NOT EXISTS (SELECT 1 FROM products p WHERE p.tenant_id = v.tenant_id AND p.variant_id = v.id)';
            $rows = array_merge($rows, DB::all(self::SELECT . $bare . ' AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?) ORDER BY i.name, v.name LIMIT ' . $room, [$t, $like, $like, $like, $q]));
        }
        $this->json(array_map(fn($v) => $this->shape($v, $c, $w), $rows));
    }

    /** Rolls and racks that hold a variant in a warehouse. */
    public function stock(): void
    {
        $variant = (int)($_GET['variant'] ?? 0);
        $wh = (int)($_GET['warehouse'] ?? 0);
        $v = Stock::variant($variant);
        if (!$v || !Stock::warehouse($wh)) $this->json(['tb' => false, 'batches' => [], 'racks' => []]);
        if (!$v['track_batch']) $this->json(['tb' => false, 'batches' => [], 'racks' => Stock::placements($variant, $wh, 0)]);
        $out = [];
        foreach (Stock::batchesFor($variant, $wh, true) as $b) {
            $out[] = ['id' => (int)$b['id'], 'batch_no' => $b['batch_no'], 'lot' => $b['supplier_lot'], 'balance' => (float)$b['balance'], 'racks' => Stock::placements($variant, $wh, (int)$b['id'])];
        }
        $this->json(['tb' => true, 'batches' => $out, 'racks' => []]);
    }
}
