<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class LookupController extends Controller
{
    /** Item picker for purchase forms: name / SKU / barcode, with default cost and tax. */
    public function items(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $variant = (int)($_GET['variant'] ?? 0);
        $supplier = (int)($_GET['supplier'] ?? 0);
        $rows = [];
        $sel = 'SELECT v.id, v.sku, v.name, v.cost_price, i.name AS item_name, i.track_batch, u.short_name AS unit, u.allow_decimal, COALESCE(x.rate, 0) AS tax_rate
                FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id LEFT JOIN taxes x ON x.id = i.tax_id
                WHERE v.tenant_id = ? AND i.is_bundle = 0 AND i.is_active = 1 AND v.is_active = 1';
        if ($variant > 0) {
            $rows = DB::all($sel . ' AND v.id = ?', [Auth::tenantId(), $variant]);
        } elseif ($q === '') {
            // with a supplier chosen, its own catalogue comes first
            $first = $supplier > 0 ? 'EXISTS (SELECT 1 FROM supplier_items sp WHERE sp.tenant_id = v.tenant_id AND sp.supplier_id = ' . $supplier . ' AND sp.variant_id = v.id AND sp.is_active = 1) DESC, ' : '';
            $rows = DB::all($sel . ' ORDER BY ' . $first . 'i.name, v.name LIMIT 15', [Auth::tenantId()]);
        } else {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            // the supplier's own product name / code finds the item too
            $theirs = $supplier > 0 ? ' OR EXISTS (SELECT 1 FROM supplier_items sp WHERE sp.tenant_id = v.tenant_id AND sp.supplier_id = ' . $supplier . ' AND sp.variant_id = v.id AND sp.is_active = 1 AND (sp.supplier_name LIKE ? OR sp.supplier_code LIKE ?))' : '';
            $args = [Auth::tenantId(), $like, $like, $like, $q];
            if ($supplier > 0) array_push($args, $like, $like);
            $rows = DB::all($sel . ' AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?' . $theirs . ') ORDER BY i.name, v.name LIMIT 15', $args);
        }
        if ($supplier > 0 && $rows && DB::val('SELECT 1 FROM suppliers WHERE tenant_id = ? AND id = ?', [Auth::tenantId(), $supplier])) {
            foreach ($rows as &$r) {
                $prod = Purchase::productOf($supplier, (int)$r['id']);
                if ($prod) { $r['sup_name'] = (string)($prod['supplier_name'] ?? ''); $r['sup_code'] = (string)($prod['supplier_code'] ?? ''); }
                $cur = Purchase::currentRate($supplier, (int)$r['id']);
                if ($cur) {
                    $r['rate'] = Purchase::netRate($cur);
                    $r['rate_note'] = 'Supplier rate ' . number_format((float)$cur['rate'], 2) . ((float)$cur['discount_pct'] > 0 ? ' less ' . (float)$cur['discount_pct'] . '%' : '')
                        . ((float)$cur['min_qty'] > 0 ? ' · min ' . (float)$cur['min_qty'] : '');
                }
                $last = Purchase::lastPaid($supplier, (int)$r['id']);
                if ($last) $r['last_paid'] = (float)$last['unit_price'];
                if ($last) $r['last_note'] = 'Last bought at ' . number_format((float)$last['unit_price'], 2) . ' on ' . fdate($last['received_date']);
            }
            unset($r);
        }
        header('Content-Type: application/json');
        echo json_encode($rows);
        exit;
    }
}
