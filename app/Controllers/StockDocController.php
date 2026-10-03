<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Stock;
use Core\Auth;
use Core\Controller;
use Core\DB;

/** Shared helpers for stock documents (adjustments, transfers, stock-takes). */
abstract class StockDocController extends Controller
{
    protected function tid(): int
    {
        return (int)Auth::tenantId();
    }

    protected function activeWarehouses(): array
    {
        return DB::all('SELECT id, name FROM warehouses WHERE tenant_id = ? AND is_active = 1 ORDER BY is_default DESC, name', [$this->tid()]);
    }

    protected function bounce(string $msg, string $back): never
    {
        flash('danger', $msg);
        with_old($this->input());
        redirect($back);
    }

    protected function activeWarehouse(mixed $id, string $back, string $what = 'warehouse'): array
    {
        $w = Stock::warehouse((int)$id);
        if (!$w || !$w['is_active']) $this->bounce("Choose a valid $what.", $back);
        return $w;
    }

    /** Posted lines that have an item chosen. Rows are numbered from 1 for error messages. */
    protected function postedLines(string $back): array
    {
        $lines = array_values(array_filter((array)($this->input()['lines'] ?? []),
            fn($l) => is_array($l) && (int)($l['variant_id'] ?? 0) > 0));
        if (!$lines) $this->bounce('Add at least one item line.', $back);
        if (count($lines) > 200) $this->bounce('Too many lines (max 200).', $back);
        return $lines;
    }

    protected function loadDoc(string $id, string $type): array
    {
        return DB::one(
            'SELECT d.*, w.name AS warehouse, w2.name AS to_warehouse, u.name AS user_name
             FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN warehouses w2 ON w2.id = d.to_warehouse_id
             LEFT JOIN users u ON u.id = d.created_by WHERE d.tenant_id = ? AND d.id = ? AND d.type = ?',
            [$this->tid(), (int)$id, $type]) ?? $this->notFound();
    }

    protected function docLines(int $docId): array
    {
        return DB::all(
            'SELECT l.*, v.sku, v.name AS vname, i.name AS item_name, u.short_name AS unit, b.batch_no,
                    r1.code AS rack, r2.code AS to_rack
             FROM stock_doc_lines l JOIN item_variants v ON v.id = l.variant_id JOIN items i ON i.id = v.item_id
             JOIN units u ON u.id = i.unit_id LEFT JOIN batches b ON b.id = l.batch_id AND l.batch_id > 0
             LEFT JOIN locations r1 ON r1.id = l.location_id AND l.location_id > 0
             LEFT JOIN locations r2 ON r2.id = l.to_location_id AND l.to_location_id > 0
             WHERE l.tenant_id = ? AND l.doc_id = ? ORDER BY l.id', [$this->tid(), $docId]);
    }

    protected function docList(string $type, string $title, string $view): void
    {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = (int)DB::val('SELECT COUNT(*) FROM stock_docs WHERE tenant_id = ? AND type = ?', [$this->tid(), $type]);
        $rows = DB::all(
            'SELECT d.*, w.name AS warehouse, w2.name AS to_warehouse, u.name AS user_name,
                    (SELECT COUNT(*) FROM stock_doc_lines l WHERE l.doc_id = d.id) AS line_count
             FROM stock_docs d JOIN warehouses w ON w.id = d.warehouse_id LEFT JOIN warehouses w2 ON w2.id = d.to_warehouse_id
             LEFT JOIN users u ON u.id = d.created_by WHERE d.tenant_id = ? AND d.type = ? ORDER BY d.id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30),
            [$this->tid(), $type]);
        $this->view($view, ['title' => $title, 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int)ceil($total / 30))]);
    }

    /** Racks per warehouse for the line editor: {warehouseId: [{id, code}]}. */
    protected function racksByWarehouse(): array
    {
        $out = [];
        foreach (DB::all('SELECT id, warehouse_id, code FROM locations WHERE tenant_id = ? AND is_active = 1 ORDER BY code', [$this->tid()]) as $r) {
            $out[$r['warehouse_id']][] = ['id' => (int)$r['id'], 'code' => $r['code']];
        }
        return $out;
    }

    /** Old posted lines (after a failed save) so the editor can rebuild them. */
    protected function oldLines(): array
    {
        return array_values((array)($_SESSION['_old']['lines'] ?? []));
    }
}
