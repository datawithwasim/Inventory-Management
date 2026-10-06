<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Purchase;
use Core\DB;

/** All supplier rates in one place (Purchase → Rate lists). Adding, revising and removing reuse the per-supplier routes. */
final class RateListController extends PurchaseBase
{
    public function index(): void
    {
        $t = $this->tid();
        $supplier = (int)($_GET['supplier'] ?? 0);
        $q = trim((string)($_GET['q'] ?? ''));
        $show = ($_GET['show'] ?? 'current') === 'all' ? 'all' : 'current';
        $where = 'r.tenant_id = ?';
        $p = [$t];
        if ($supplier) { $where .= ' AND r.supplier_id = ?'; $p[] = $supplier; }
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ? OR r.supplier_code LIKE ? OR i.design_no LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $rows = Purchase::markCurrent(DB::all(
            "SELECT r.*, s.name AS supplier_name, v.sku, v.name AS vname, i.name AS item_name, i.id AS item_id
             FROM supplier_rates r JOIN suppliers s ON s.id = r.supplier_id JOIN item_variants v ON v.id = r.variant_id JOIN items i ON i.id = v.item_id
             WHERE $where ORDER BY i.name, v.name, r.variant_id, r.supplier_id, r.valid_from DESC, r.id DESC LIMIT 3000", $p));
        // lowest current net per variant (across the suppliers shown)
        $low = [];
        foreach ($rows as $r) if ($r['is_current'] && (!isset($low[$r['variant_id']]) || $r['net'] < $low[$r['variant_id']])) $low[$r['variant_id']] = $r['net'];
        $stats = ['current' => 0, 'earlier' => 0, 'suppliers' => [], 'items' => []];
        foreach ($rows as $r) {
            if ($r['is_current']) { $stats['current']++; $stats['suppliers'][$r['supplier_id']] = 1; $stats['items'][$r['variant_id']] = 1; } else $stats['earlier']++;
        }
        if ($show === 'current') $rows = array_values(array_filter($rows, fn($r) => $r['is_current']));
        $this->view('app/purchase/rates', ['title' => 'Rate lists', 'rows' => $rows, 'low' => $low, 'supplier' => $supplier, 'q' => $q, 'show' => $show,
            'stats' => ['current' => $stats['current'], 'earlier' => $stats['earlier'], 'suppliers' => count($stats['suppliers']), 'items' => count($stats['items'])],
            'suppliers' => DB::all('SELECT id, name FROM suppliers WHERE tenant_id = ? ORDER BY name', [$t])]);
    }
}
