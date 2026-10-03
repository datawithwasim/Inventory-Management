<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Code128;
use Core\Controller;
use Core\DB;
use Core\Settings;

/** Printable barcode labels for items (SKU/barcode + price) and for rolls (batch number + rack). */
final class LabelController extends Controller
{
    private const SIZES = ['50x25' => '50 × 25 mm (roll printer)', '38x25' => '38 × 25 mm (small)', '65x33' => '65 × 33 mm (A4 sheet, 3 × 8)'];

    public function index(): void
    {
        $t = (int)Auth::tenantId();
        $type = ($_GET['type'] ?? 'items') === 'rolls' ? 'rolls' : 'items';
        $q = trim((string)($_GET['q'] ?? ''));
        $like = '%' . $q . '%';
        if ($type === 'rolls') {
            $rows = DB::all(
                'SELECT b.id, b.batch_no AS code, i.name AS item, v.name AS variant, COALESCE(SUM(s.qty),0) AS bal, u.short_name AS unit
                 FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 LEFT JOIN stock_balances s ON s.batch_id = b.id AND s.tenant_id = b.tenant_id
                 WHERE b.tenant_id = ? AND (? = \'\' OR i.name LIKE ? OR b.batch_no LIKE ? OR v.sku LIKE ?)
                 GROUP BY b.id HAVING bal > 0.0005 ORDER BY b.id DESC LIMIT 200', [$t, $q, $like, $like, $like]);
        } else {
            $rows = DB::all(
                'SELECT v.id, COALESCE(NULLIF(v.barcode, \'\'), v.sku) AS code, i.name AS item, v.name AS variant, v.sale_price
                 FROM item_variants v JOIN items i ON i.id = v.item_id
                 WHERE v.tenant_id = ? AND i.is_bundle = 0 AND v.is_active = 1 AND (? = \'\' OR i.name LIKE ? OR v.sku LIKE ? OR v.barcode LIKE ?)
                 ORDER BY i.name, v.name LIMIT 200', [$t, $q, $like, $like, $like]);
        }
        $this->view('app/labels/index', ['title' => 'Barcode labels', 'type' => $type, 'q' => $q, 'rows' => $rows, 'sizes' => self::SIZES]);
    }

    public function print(): void
    {
        $t = (int)Auth::tenantId();
        $type = ($_GET['type'] ?? 'items') === 'rolls' ? 'rolls' : 'items';
        $size = isset(self::SIZES[$_GET['size'] ?? '']) ? $_GET['size'] : '50x25';
        $want = [];
        foreach ((array)($_GET['n'] ?? []) as $id => $n) {
            $n = max(0, min(200, (int)$n));
            if ($n > 0 && (int)$id > 0) $want[(int)$id] = $n;
        }
        $want = array_slice($want, 0, 200, true);
        $labels = [];
        foreach ($want as $id => $copies) {
            if ($type === 'rolls') {
                $r = DB::one(
                    'SELECT b.batch_no, b.received_date, i.name AS item, v.name AS variant, u.short_name AS unit,
                            (SELECT COALESCE(SUM(s.qty),0) FROM stock_balances s WHERE s.batch_id = b.id) AS bal,
                            (SELECT GROUP_CONCAT(DISTINCT l.code ORDER BY l.code SEPARATOR \', \') FROM stock_balances s JOIN locations l ON l.id = s.location_id
                              WHERE s.batch_id = b.id AND s.qty > 0.0005 AND s.location_id > 0) AS racks
                     FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                     WHERE b.tenant_id = ? AND b.id = ?', [$t, $id]);
                if (!$r) continue;
                $l = ['code' => $r['batch_no'], 'title' => $r['item'] . ($r['variant'] ? ' — ' . $r['variant'] : ''),
                    'line' => 'Roll ' . qty($r['bal']) . ' ' . $r['unit'] . ($r['racks'] ? ' · Rack ' . $r['racks'] : ''), 'small' => 'Received ' . fdate($r['received_date'])];
            } else {
                $r = DB::one(
                    'SELECT COALESCE(NULLIF(v.barcode, \'\'), v.sku) AS code, v.sku, v.sale_price, i.name AS item, v.name AS variant
                     FROM item_variants v JOIN items i ON i.id = v.item_id WHERE v.tenant_id = ? AND v.id = ?', [$t, $id]);
                if (!$r) continue;
                $l = ['code' => $r['code'], 'title' => $r['item'] . ($r['variant'] ? ' — ' . $r['variant'] : ''), 'line' => money($r['sale_price']), 'small' => $r['sku']];
            }
            $l['svg'] = Code128::svg((string)$l['code'], 40);
            for ($i = 0; $i < $copies; $i++) $labels[] = $l;
        }
        $labels = array_slice($labels, 0, 1000);
        $this->view('app/labels/print', ['title' => 'Labels', 'labels' => $labels, 'size' => $size, 'company' => Settings::get('company.name', '')], 'layouts/print');
    }
}
