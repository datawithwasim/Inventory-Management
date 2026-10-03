<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class StockController extends Controller
{
    private const PER_PAGE = 50;
    public const TYPES = [
        'opening' => 'Opening stock', 'adjustment' => 'Adjustment', 'transfer_in' => 'Transfer in', 'transfer_out' => 'Transfer out',
        'stocktake' => 'Stock-take', 'purchase' => 'Purchase', 'purchase_return' => 'Purchase return', 'sale' => 'Sale', 'sales_return' => 'Sales return',
    ];

    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function warehouses(): array
    {
        return DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? ORDER BY is_default DESC, name', [$this->tid()]);
    }

    /* ---------- balances ---------- */

    public function index(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $wh = (int)($_GET['warehouse'] ?? 0);
        $low = !empty($_GET['low']);
        $page = max(1, (int)($_GET['page'] ?? 1));

        $join = 'LEFT JOIN stock_balances s ON s.variant_id = v.id AND s.tenant_id = v.tenant_id' . ($wh ? ' AND s.warehouse_id = ?' : '');
        $params = $wh ? [$wh, $t] : [$t];
        $where = 'v.tenant_id = ? AND i.is_bundle = 0 AND v.is_active = 1 AND i.is_active = 1';
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (i.name LIKE ? OR v.name LIKE ? OR v.sku LIKE ? OR v.barcode = ?)';
            array_push($params, $like, $like, $like, $q);
        }
        $itemTotal = '(SELECT COALESCE(SUM(s2.qty),0) FROM stock_balances s2 JOIN item_variants v2 ON v2.id = s2.variant_id WHERE v2.item_id = i.id)';
        if ($low) $where .= " AND i.reorder_level > 0 AND $itemTotal <= i.reorder_level";

        $base = "FROM item_variants v JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id $join
                 LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0 WHERE $where";
        $total = (int)DB::val("SELECT COUNT(DISTINCT v.id) $base", $params);
        $rows = DB::all(
            "SELECT v.id, v.sku, v.name AS vname, i.id AS item_id, i.name, i.track_batch, i.reorder_level, u.short_name AS unit,
                    COALESCE(SUM(s.qty),0) AS qty,
                    COUNT(DISTINCT CASE WHEN s.batch_id > 0 AND s.qty > 0 THEN s.batch_id END) AS batches,
                    COALESCE(SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)),0) AS value,
                    $itemTotal AS item_total
             $base GROUP BY v.id, i.id, u.id ORDER BY i.name, v.name LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        $this->attachRacks($rows, $wh);
        $this->attachReserved($rows, $wh);
        $totalValue = (float)DB::val(
            'SELECT COALESCE(SUM(s.qty * COALESCE(NULLIF(b.unit_cost,0), v.cost_price)),0)
             FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0
             WHERE s.tenant_id = ?' . ($wh ? ' AND s.warehouse_id = ?' : ''), $wh ? [$t, $wh] : [$t]);

        $this->view('app/stock/index', [
            'title' => 'Stock', 'rows' => $rows, 'q' => $q, 'wh' => $wh, 'low' => $low, 'warehouses' => $this->warehouses(),
            'page' => $page, 'pages' => max(1, (int)ceil($total / self::PER_PAGE)), 'totalValue' => $totalValue,
        ]);
    }

    /** Adds a short "where is it" list (rack: qty) to each variant row. */
    private function attachRacks(array &$rows, int $wh): void
    {
        if (!$rows) return;
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $params = [$this->tid(), ...$ids];
        $sql = "SELECT s.variant_id, w.name AS warehouse, COALESCE(l.code, '') AS code, SUM(s.qty) AS qty
                FROM stock_balances s JOIN warehouses w ON w.id = s.warehouse_id LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
                WHERE s.tenant_id = ? AND s.variant_id IN ($in)" . ($wh ? ' AND s.warehouse_id = ?' : '') . '
                GROUP BY s.variant_id, w.id, w.name, s.location_id, l.code HAVING qty > 0.0005 ORDER BY qty DESC';
        if ($wh) $params[] = $wh;
        $by = [];
        foreach (DB::all($sql, $params) as $p) $by[$p['variant_id']][] = $p;
        foreach ($rows as &$r) $r['racks'] = $by[$r['id']] ?? [];
    }

    /** Stock promised to confirmed sales orders (and parts of sets), per variant. */
    private function attachReserved(array &$rows, int $wh): void
    {
        if (!$rows) return;
        $ids = array_map(fn($r) => (int)$r['id'], $rows);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $open = "o.tenant_id = ? AND o.status IN ('confirmed','partial') AND i.qty_ordered > i.qty_delivered" . ($wh ? ' AND o.warehouse_id = ?' : '');
        $base = $wh ? [$this->tid(), $wh] : [$this->tid()];
        $map = [];
        foreach (DB::all("SELECT i.variant_id AS v, SUM(i.qty_ordered - i.qty_delivered) AS q FROM sales_order_items i JOIN sales_orders o ON o.id = i.order_id
                          WHERE $open AND i.variant_id IN ($in) GROUP BY i.variant_id", [...$base, ...$ids]) as $r) $map[$r['v']] = ($map[$r['v']] ?? 0) + (float)$r['q'];
        foreach (DB::all("SELECT bc.component_variant_id AS v, SUM((i.qty_ordered - i.qty_delivered) * bc.qty) AS q FROM sales_order_items i JOIN sales_orders o ON o.id = i.order_id
                          JOIN item_variants bv ON bv.id = i.variant_id JOIN bundle_components bc ON bc.bundle_item_id = bv.item_id
                          WHERE $open AND bc.component_variant_id IN ($in) GROUP BY bc.component_variant_id", [...$base, ...$ids]) as $r) $map[$r['v']] = ($map[$r['v']] ?? 0) + (float)$r['q'];
        foreach ($rows as &$r) $r['reserved'] = $map[$r['id']] ?? 0.0;
    }

    /* ---------- ledger ---------- */

    public function ledger(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $type = (string)($_GET['type'] ?? '');
        $wh = (int)($_GET['warehouse'] ?? 0);
        $from = (string)($_GET['from'] ?? '');
        $to = (string)($_GET['to'] ?? '');
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = 'l.tenant_id = ?';
        $params = [$t];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ? OR b.batch_no LIKE ? OR rk.code LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (isset(self::TYPES[$type])) { $where .= ' AND l.type = ?'; $params[] = $type; }
        if ($wh) { $where .= ' AND l.warehouse_id = ?'; $params[] = $wh; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $where .= ' AND l.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $where .= ' AND l.created_at <= ?'; $params[] = $to . ' 23:59:59'; }

        $base = "FROM stock_ledger l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id
                 JOIN warehouses w ON w.id = l.warehouse_id LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0
                 LEFT JOIN locations rk ON rk.id = l.location_id AND l.location_id > 0
                 LEFT JOIN users u ON u.id = l.user_id WHERE $where";
        $total = (int)DB::val("SELECT COUNT(*) $base", $params);
        $rows = DB::all(
            "SELECT l.*, i.name AS item_name, v.name AS vname, v.sku, w.name AS warehouse, b.batch_no, rk.code AS rack, u.name AS user_name
             $base ORDER BY l.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);

        $this->view('app/stock/ledger', [
            'title' => 'Stock ledger', 'rows' => $rows, 'q' => $q, 'type' => $type, 'wh' => $wh, 'from' => $from, 'to' => $to,
            'warehouses' => $this->warehouses(), 'types' => self::TYPES,
            'page' => $page, 'pages' => max(1, (int)ceil($total / self::PER_PAGE)),
        ]);
    }

    /* ---------- batches (1 batch = 1 roll) ---------- */

    public function batches(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $status = (string)($_GET['status'] ?? 'active');
        $page = max(1, (int)($_GET['page'] ?? 1));

        $where = 'b.tenant_id = ?';
        $params = [$t];
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (b.batch_no LIKE ? OR b.supplier_lot LIKE ? OR i.name LIKE ? OR v.sku LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        $having = match ($status) {
            'finished' => 'HAVING balance <= 0.0005',
            'all' => '',
            default => 'HAVING balance > 0.0005',
        };
        $base = "FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 LEFT JOIN stock_balances s ON s.batch_id = b.id AND s.tenant_id = b.tenant_id WHERE $where GROUP BY b.id $having";
        $total = (int)DB::val("SELECT COUNT(*) FROM (SELECT b.id, COALESCE(SUM(s.qty),0) AS balance $base) x", $params);
        $rows = DB::all(
            "SELECT b.*, i.name AS item_name, v.name AS vname, v.sku, u.short_name AS unit, COALESCE(SUM(s.qty),0) AS balance
             $base ORDER BY b.id DESC LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);

        $this->view('app/stock/batches', [
            'title' => term('rolls') . ' (rolls)', 'rows' => $rows, 'q' => $q, 'status' => $status,
            'page' => $page, 'pages' => max(1, (int)ceil($total / self::PER_PAGE)),
        ]);
    }

    public function batch(string $id): void
    {
        $t = $this->tid();
        $b = DB::one(
            'SELECT b.*, i.name AS item_name, i.id AS item_id, v.name AS vname, v.sku, u.short_name AS unit
             FROM batches b JOIN item_variants v ON v.id = b.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
             WHERE b.tenant_id = ? AND b.id = ?', [$t, (int)$id]) ?? $this->notFound();
        $where = DB::all(
            'SELECT w.name, COALESCE(l.code, \'\') AS rack, s.qty FROM stock_balances s JOIN warehouses w ON w.id = s.warehouse_id
             LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
             WHERE s.tenant_id = ? AND s.batch_id = ? AND s.qty <> 0 ORDER BY w.name, l.code', [$t, $b['id']]);
        $history = DB::all(
            'SELECT l.*, w.name AS warehouse, rk.code AS rack, u.name AS user_name FROM stock_ledger l JOIN warehouses w ON w.id = l.warehouse_id
             LEFT JOIN locations rk ON rk.id = l.location_id AND l.location_id > 0
             LEFT JOIN users u ON u.id = l.user_id WHERE l.tenant_id = ? AND l.batch_id = ? ORDER BY l.id', [$t, $b['id']]);
        $this->view('app/stock/batch', [
            'title' => 'Batch ' . $b['batch_no'], 'b' => $b, 'where' => $where, 'history' => $history,
            'balance' => array_sum(array_column($where, 'qty')), 'types' => self::TYPES,
        ]);
    }

    /* ---------- stock by rack ---------- */

    public function racks(): void
    {
        $t = $this->tid();
        $q = trim((string)($_GET['q'] ?? ''));
        $wh = (int)($_GET['warehouse'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $where = 's.tenant_id = ? AND s.qty > 0.0005';
        $params = [$t];
        if ($wh) { $where .= ' AND s.warehouse_id = ?'; $params[] = $wh; }
        if ($q === '-') {
            $where .= ' AND s.location_id = 0';
        } elseif ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where .= ' AND (l.code LIKE ? OR l.description LIKE ? OR i.name LIKE ? OR v.sku LIKE ? OR v.name LIKE ? OR b.batch_no LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $base = "FROM stock_balances s JOIN item_variants v ON v.id = s.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                 JOIN warehouses w ON w.id = s.warehouse_id LEFT JOIN locations l ON l.id = s.location_id AND s.location_id > 0
                 LEFT JOIN batches b ON b.id = s.batch_id AND s.batch_id > 0 WHERE $where";
        $total = (int)DB::val("SELECT COUNT(*) $base", $params);
        $rows = DB::all(
            "SELECT s.qty, w.name AS warehouse, l.code AS rack, l.description AS rack_desc, i.id AS item_id, i.name AS item_name, v.name AS vname, v.sku,
                    b.batch_no, s.batch_id, u.short_name AS unit
             $base ORDER BY w.name, (s.location_id = 0), l.code, i.name, v.name LIMIT " . self::PER_PAGE . ' OFFSET ' . (($page - 1) * self::PER_PAGE), $params);
        $this->view('app/stock/racks', [
            'title' => 'Stock by rack', 'rows' => $rows, 'q' => $q, 'wh' => $wh, 'warehouses' => $this->warehouses(),
            'page' => $page, 'pages' => max(1, (int)ceil($total / self::PER_PAGE)),
        ]);
    }

    /* ---------- JSON helpers for the line editor ---------- */

    /** Racks holding a variant (optionally one batch) in a warehouse, biggest first. */
    public function placement(): void
    {
        $variant = (int)($_GET['variant'] ?? 0);
        $wh = (int)($_GET['warehouse'] ?? 0);
        if (!Stock::variant($variant) || !Stock::warehouse($wh)) $this->json([]);
        $batch = isset($_GET['batch']) && ctype_digit((string)$_GET['batch']) ? (int)$_GET['batch'] : null;
        $this->json(Stock::placements($variant, $wh, $batch));
    }


    private function json(mixed $data): never
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function lookup(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $this->json($q === '' ? [] : Stock::search($q));
    }

    public function batchOptions(): void
    {
        $variant = (int)($_GET['variant'] ?? 0);
        if (!Stock::variant($variant)) $this->json([]);
        $wh = (int)($_GET['warehouse'] ?? 0) ?: null;
        $this->json(Stock::batchesFor($variant, $wh, !empty($_GET['in_stock'])));
    }
}
