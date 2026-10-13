<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\DB;

/** Catalogues: the books the showroom sells from (CARAVAN …). The designs in a book are Products. */
final class CatalogueController extends SalesBase
{
    private function load(string $id): array
    {
        return DB::one('SELECT * FROM catalogues WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$id]) ?? $this->notFound();
    }

    public function index(): void
    {
        $q = trim((string)($_GET['q'] ?? ''));
        $where = 'c.tenant_id = ?'; $p = [$this->tid()];
        if ($q !== '') { $where .= ' AND c.name LIKE ?'; $p[] = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%'; }
        $this->view('app/catalogues/index', ['title' => 'Catalogues', 'q' => $q, 'rows' => DB::all(
            "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.catalogue_id = c.id) AS products, (SELECT COUNT(*) FROM products p WHERE p.catalogue_id = c.id AND p.is_active = 1) AS active_products
             FROM catalogues c WHERE $where ORDER BY c.name", $p),
            'loose' => (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ? AND catalogue_id IS NULL', [$this->tid()])]);
    }

    public function show(string $id): void
    {
        $c = $this->load($id);
        $this->view('app/catalogues/show', ['title' => $c['name'], 'c' => $c, 'products' => DB::all(
            'SELECT p.*, v.sku, i.name AS item_name, i.id AS item_id FROM products p JOIN item_variants v ON v.id = p.variant_id JOIN items i ON i.id = v.item_id
             WHERE p.tenant_id = ? AND p.catalogue_id = ? ORDER BY LENGTH(p.name), p.name', [$this->tid(), $c['id']])]);
    }

    public function create(): void
    {
        $this->view('app/catalogues/form', ['title' => 'New catalogue', 'row' => null]);
    }

    public function edit(string $id): void
    {
        $c = $this->load($id);
        $this->view('app/catalogues/form', ['title' => 'Edit ' . $c['name'], 'row' => $c]);
    }

    private function fields(string $back, ?int $exceptId): array
    {
        $d = $this->input();
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) $this->bounce('Catalogue name is required (max 120 characters).', $back);
        if (DB::val('SELECT 1 FROM catalogues WHERE tenant_id = ? AND name = ? AND id <> ?', [$this->tid(), $name, $exceptId ?? 0])) $this->bounce('A catalogue with that name already exists.', $back);
        return ['name' => $name, 'notes' => $this->text($d['notes'] ?? '', 255, 'Notes', $back), 'is_active' => array_key_exists('is_active', $d) && empty($d['is_active']) ? 0 : 1];
    }

    public function store(): void
    {
        $data = $this->fields('catalogues/create', null);
        $id = DB::insert('catalogues', ['tenant_id' => $this->tid()] + $data);
        Audit::log('catalogue_create', 'catalogue', $id, $data['name']);
        flash('success', 'Catalogue created. Add its products now, or import them from a CSV file.');
        redirect("catalogues/$id");
    }

    public function update(string $id): void
    {
        $c = $this->load($id);
        $data = $this->fields("catalogues/{$c['id']}/edit", (int)$c['id']);
        DB::run('UPDATE catalogues SET name = ?, notes = ?, is_active = ? WHERE tenant_id = ? AND id = ?', [$data['name'], $data['notes'], $data['is_active'], $this->tid(), $c['id']]);
        Audit::log('catalogue_update', 'catalogue', (int)$c['id']);
        flash('success', 'Catalogue updated.');
        redirect("catalogues/{$c['id']}");
    }

    public function destroy(string $id): void
    {
        $c = $this->load($id);
        $n = (int)DB::val('SELECT COUNT(*) FROM products WHERE tenant_id = ? AND catalogue_id = ?', [$this->tid(), $c['id']]);
        if ($n > 0) $this->bounce("This catalogue still has $n product(s). Delete or move them first, or mark the catalogue inactive.", "catalogues/{$c['id']}");
        DB::run('DELETE FROM catalogues WHERE tenant_id = ? AND id = ?', [$this->tid(), $c['id']]);
        Audit::log('catalogue_delete', 'catalogue', (int)$c['id'], $c['name']);
        flash('success', 'Catalogue deleted.');
        redirect('catalogues');
    }
}
