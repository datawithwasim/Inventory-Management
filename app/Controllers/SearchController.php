<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Auth;
use Core\Controller;
use Core\DB;

/** Global search (Ctrl K): items, customers, suppliers, documents and rolls — only what the user may see. */
final class SearchController extends Controller
{
    public function index(): void
    {
        $t = (int)Auth::tenantId();
        $q = trim((string)($_GET['q'] ?? ''));
        $groups = [];
        if (mb_strlen($q) >= 2) {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $add = function (string $title, array $rows) use (&$groups) { if ($rows) $groups[] = ['title' => $title, 'rows' => $rows]; };
            if (can('items.view')) {
                $add(term('items'), array_map(fn($r) => ['l' => $r['name'] . ($r['vname'] ? ' — ' . $r['vname'] : ''), 's' => $r['sku'], 'i' => 'tags', 'u' => url('items/' . $r['id'])], DB::all(
                    'SELECT i.id, i.name, v.name AS vname, v.sku FROM items i JOIN item_variants v ON v.item_id = i.id
                     WHERE i.tenant_id = ? AND (i.name LIKE ? OR v.sku LIKE ? OR v.barcode = ? OR v.name LIKE ?) GROUP BY i.id, v.id ORDER BY i.name LIMIT 6', [$t, $like, $like, $q, $like])));
            }
            if (can('stock.view')) {
                $add(term('rolls'), array_map(fn($r) => ['l' => $r['batch_no'] . ' · ' . $r['name'], 's' => 'Roll', 'i' => 'layers', 'u' => url('stock/batches/' . $r['id'])], DB::all(
                    'SELECT b.id, b.batch_no, i.name FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id
                     WHERE b.tenant_id = ? AND (b.batch_no LIKE ? OR b.supplier_lot LIKE ?) ORDER BY b.id DESC LIMIT 4', [$t, $like, $like])));
            }
            if (can('customers.view')) {
                $add(term('customers'), array_map(fn($r) => ['l' => $r['name'], 's' => $r['phone'] ?? '', 'i' => 'person', 'u' => url('customers/' . $r['id'])], DB::all(
                    'SELECT id, name, phone FROM customers WHERE tenant_id = ? AND (name LIKE ? OR phone LIKE ? OR email LIKE ?) ORDER BY name LIMIT 5', [$t, $like, $like, $like])));
            }
            if (can('suppliers.view')) {
                $add(term('suppliers'), array_map(fn($r) => ['l' => $r['name'], 's' => $r['phone'] ?? '', 'i' => 'truck', 'u' => url('suppliers/' . $r['id'])], DB::all(
                    'SELECT id, name, phone FROM suppliers WHERE tenant_id = ? AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT 5', [$t, $like, $like])));
            }
            if (can('sales.view')) {
                $docs = [];
                foreach ([['sales_invoices', 'invoice_no', 'sales/invoices', 'Invoice', 'receipt'], ['sales_orders', 'order_no', 'sales/orders', 'Sales order', 'bag-check']] as [$tb, $col, $route, $lbl, $icon]) {
                    foreach (DB::all("SELECT id, $col AS no FROM $tb WHERE tenant_id = ? AND $col LIKE ? ORDER BY id DESC LIMIT 3", [$t, $like]) as $r) $docs[] = ['l' => $r['no'], 's' => $lbl, 'i' => $icon, 'u' => url("$route/{$r['id']}")];
                }
                $add('Sales documents', $docs);
            }
            if (can('purchase.view')) {
                $docs = [];
                foreach ([['purchase_orders', 'po_no', 'purchase/orders', 'Purchase order', 'cart-plus'], ['purchase_bills', 'bill_no', 'purchase/bills', 'Bill', 'receipt-cutoff'],
                          ['grns', 'grn_no', 'purchase/grns', 'Goods receipt', 'box-arrow-in-down']] as [$tb, $col, $route, $lbl, $icon]) {
                    foreach (DB::all("SELECT id, $col AS no FROM $tb WHERE tenant_id = ? AND $col LIKE ? ORDER BY id DESC LIMIT 3", [$t, $like]) as $r) $docs[] = ['l' => $r['no'], 's' => $lbl, 'i' => $icon, 'u' => url("$route/{$r['id']}")];
                }
                $add('Purchase documents', $docs);
            }
        }
        header('Content-Type: application/json');
        echo json_encode(['groups' => $groups]);
        exit;
    }
}
