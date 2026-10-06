<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

final class WarehouseController extends Controller
{
    private function load(string $id): array
    {
        return DB::one('SELECT * FROM warehouses WHERE tenant_id = ? AND id = ?', [Auth::tenantId(), (int)$id]) ?? $this->notFound();
    }

    private function limitReached(): bool
    {
        $max = (int)Auth::user()['max_warehouses'];
        return $max > 0 && (int)DB::val('SELECT COUNT(*) FROM warehouses WHERE tenant_id = ?', [Auth::tenantId()]) >= $max;
    }

    public function index(): void
    {
        $rows = DB::all(
            'SELECT w.*, COALESCE((SELECT SUM(s.qty) FROM stock_balances s WHERE s.tenant_id = w.tenant_id AND s.warehouse_id = w.id), 0) AS total_qty
             FROM warehouses w WHERE w.tenant_id = ? ORDER BY w.is_default DESC, w.name', [Auth::tenantId()]);
        $this->view('app/warehouses/index', ['title' => term('warehouses'), 'rows' => $rows, 'limitReached' => $this->limitReached()]);
    }

    public function show(string $id): void
    {
        $w = $this->load($id);
        $t = (int)Auth::tenantId();
        $this->view('app/warehouses/show', [
            'title' => $w['name'], 'w' => $w,
            'racks' => DB::all('SELECT l.id, l.code, l.description, l.is_active, COALESCE((SELECT SUM(s.qty) FROM stock_balances s WHERE s.location_id = l.id), 0) AS qty
                                FROM locations l WHERE l.tenant_id = ? AND l.warehouse_id = ? ORDER BY l.code LIMIT 200', [$t, $w['id']]),
            'stock' => DB::all('SELECT i.id AS item_id, i.name AS item_name, v.name AS vname, v.sku, u.short_name AS unit, SUM(s.qty) AS qty FROM stock_balances s
                                JOIN item_variants v ON v.id = s.variant_id JOIN items i ON i.id = v.item_id JOIN units u ON u.id = i.unit_id
                                WHERE s.tenant_id = ? AND s.warehouse_id = ? GROUP BY v.id, i.id, i.name, v.name, v.sku, u.short_name HAVING qty > 0.0005 ORDER BY qty DESC LIMIT 25', [$t, $w['id']]),
            'totalQty' => (float)DB::val('SELECT COALESCE(SUM(qty),0) FROM stock_balances WHERE tenant_id = ? AND warehouse_id = ?', [$t, $w['id']]),
            'skuCount' => (int)DB::val('SELECT COUNT(DISTINCT variant_id) FROM stock_balances WHERE tenant_id = ? AND warehouse_id = ? AND qty > 0.0005', [$t, $w['id']]),
        ]);
    }

    public function create(): void
    {
        if ($this->limitReached()) {
            flash('warning', "Your plan's warehouse limit is reached.");
            redirect('warehouses');
        }
        $this->view('app/warehouses/form', ['title' => 'New warehouse', 'row' => null]);
    }

    private function fields(string $back, ?int $exceptId): array
    {
        $this->enforceFields('warehouse', $back);
        $d = $this->validate(['name' => 'required|max:100', 'code' => 'required|max:20', 'address' => 'max:255'], $back);
        $name = trim($d['name']);
        if (DB::val('SELECT 1 FROM warehouses WHERE tenant_id = ? AND name = ? AND id <> ?', [Auth::tenantId(), $name, $exceptId ?? 0])) {
            flash('danger', 'A warehouse with that name already exists.');
            with_old($d);
            redirect($back);
        }
        return ['name' => $name, 'code' => strtoupper(trim($d['code'])), 'address' => trim($d['address'] ?? '') ?: null,
            'is_active' => empty($d['is_active']) ? 0 : 1];
    }

    public function store(): void
    {
        if ($this->limitReached()) redirect('warehouses');
        $data = $this->fields('warehouses/create', null);
        $data['is_active'] = 1;
        $id = DB::insert('warehouses', ['tenant_id' => Auth::tenantId()] + $data);
        Audit::log('warehouse_create', 'warehouse', $id, $data['name']);
        flash('success', 'Warehouse added.');
        redirect('warehouses');
    }

    public function edit(string $id): void
    {
        $this->view('app/warehouses/form', ['title' => 'Edit warehouse', 'row' => $this->load($id)]);
    }

    public function update(string $id): void
    {
        $row = $this->load($id);
        $data = $this->fields("warehouses/$id/edit", (int)$row['id']);
        if ($row['is_default']) $data['is_active'] = 1;
        DB::run('UPDATE warehouses SET name=?, code=?, address=?, is_active=? WHERE tenant_id = ? AND id = ?',
            [...array_values($data), Auth::tenantId(), $row['id']]);
        Audit::log('warehouse_update', 'warehouse', (int)$row['id']);
        flash('success', 'Warehouse updated.');
        redirect('warehouses');
    }

    public function makeDefault(string $id): void
    {
        $row = $this->load($id);
        DB::transaction(function () use ($row) {
            DB::run('UPDATE warehouses SET is_default = 0 WHERE tenant_id = ?', [Auth::tenantId()]);
            DB::run('UPDATE warehouses SET is_default = 1, is_active = 1 WHERE tenant_id = ? AND id = ?', [Auth::tenantId(), $row['id']]);
        });
        flash('success', $row['name'] . ' is now the default warehouse.');
        redirect('warehouses');
    }

    public function destroy(string $id): void
    {
        $row = $this->load($id);
        $used = (int)DB::val('SELECT COUNT(*) FROM stock_ledger WHERE tenant_id = ? AND warehouse_id = ?', [Auth::tenantId(), $row['id']]);
        if ($row['is_default'] || $used > 0) {
            flash('danger', $row['is_default'] ? 'The default warehouse cannot be deleted.' : 'This warehouse has stock history. Mark it inactive instead.');
            redirect('warehouses');
        }
        DB::run('DELETE FROM warehouses WHERE tenant_id = ? AND id = ?', [Auth::tenantId(), $row['id']]);
        Audit::log('warehouse_delete', 'warehouse', (int)$row['id'], $row['name']);
        flash('success', 'Warehouse deleted.');
        redirect('warehouses');
    }
}
