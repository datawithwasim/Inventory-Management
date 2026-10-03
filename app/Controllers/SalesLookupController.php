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

    private function shape(array $v, int $customerId, int $warehouseId): array
    {
        [$price, $disc] = Sales::priceFor((int)$v['id'], $customerId);
        $out = ['id' => (int)$v['id'], 'sku' => $v['sku'], 'name' => $v['name'], 'item_name' => $v['item_name'], 'unit' => $v['unit'],
            'allow_decimal' => (int)$v['allow_decimal'], 'track_batch' => (int)$v['track_batch'], 'is_bundle' => (int)$v['is_bundle'],
            'price' => $price, 'discount' => $disc, 'tax_rate' => (float)$v['tax_rate'], 'cost_price' => (float)$v['cost_price']];
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

    /** Search by name / SKU / barcode. */
    public function items(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        if ($q === '') $this->json([]);
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        $rows = DB::all(self::SELECT . ' AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?) ORDER BY i.name, v.name LIMIT 15', [Auth::tenantId(), $like, $like, $like, $q]);
        $c = (int)($_GET['customer'] ?? 0);
        $w = (int)($_GET['warehouse'] ?? 0);
        $this->json(array_map(fn($v) => $this->shape($v, $c, $w), $rows));
    }

    /** One variant by id or exact barcode / SKU (barcode scanners). */
    public function one(): void
    {
        $code = trim((string)($_GET['code'] ?? ''));
        $id = (int)($_GET['id'] ?? 0);
        $c = (int)($_GET['customer'] ?? 0);
        $w = (int)($_GET['warehouse'] ?? 0);
        $v = $id
            ? DB::one(self::SELECT . ' AND v.id = ?', [Auth::tenantId(), $id])
            : DB::one(self::SELECT . ' AND (v.barcode = ? OR v.sku = ?) LIMIT 1', [Auth::tenantId(), $code, $code]);
        $this->json($v ? $this->shape($v, $c, $w) : null);
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
