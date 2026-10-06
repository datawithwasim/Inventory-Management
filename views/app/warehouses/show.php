<?php ob_start(); ?>
<div class="row g-3 mb-3" id="stats">
  <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Total stock</div><div class="fs-3"><?= e(qty($totalQty)) ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small">Different items</div><div class="fs-3"><?= (int)$skuCount ?></div></div></div></div>
  <div class="col-6 col-md-3"><div class="card"><div class="card-body"><div class="text-muted small"><?= e(term('racks')) ?></div><div class="fs-3"><?= count($racks) ?></div></div></div></div>
</div>
<div class="card mb-3" id="racks"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(term('racks')) ?> / locations</span>
  <?php if (can('warehouses.create')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('locations/create') ?>">Add <?= e(term('rack', true)) ?></a><?php endif; ?></div>
  <div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>Code</th><th>Description</th><th class="text-end">Stock</th><th></th></tr></thead><tbody>
  <?php foreach ($racks as $r): ?><tr><td><a href="<?= url('locations/' . (int)$r['id']) ?>"><strong><?= e($r['code']) ?></strong></a></td><td><?= e($r['description'] ?? '') ?></td><td class="text-end"><?= e(qty($r['qty'])) ?></td>
    <td><?= $r['is_active'] ? '' : '<span class="badge text-bg-secondary">Inactive</span>' ?></td></tr><?php endforeach; ?>
  <?php if (!$racks): ?><tr><td colspan="4" class="text-muted">No <?= e(term('racks', true)) ?> yet.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card mb-3" id="stock"><div class="card-header">What is stored here (top 25 by quantity)</div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th><?= e(term('item')) ?></th><th>SKU</th><th class="text-end">Qty</th></tr></thead><tbody>
  <?php foreach ($stock as $r): ?><tr><td><a href="<?= url('items/' . (int)$r['item_id']) ?>"><?= e($r['item_name']) ?></a><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?></td><td><?= e($r['sku']) ?></td><td class="text-end"><?= e(qty($r['qty'])) ?> <?= e($r['unit']) ?></td></tr><?php endforeach; ?>
  <?php if (!$stock): ?><tr><td colspan="3" class="text-muted">Nothing in stock here.</td></tr><?php endif; ?></tbody></table></div></div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'warehouse', 'row' => $w, 'name' => $w['name'], 'back' => 'warehouses', 'body' => $body,
    'badges' => ($w['is_default'] ? '<span class="badge text-bg-primary">Default</span> ' : '') . ($w['is_active'] ? '' : '<span class="badge text-bg-secondary">Inactive</span>'),
    'related' => ['stats' => 'Overview figures', 'racks' => term('racks'), 'stock' => 'Stock here'],
    'actions' => (can('warehouses.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("warehouses/{$w['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('warehouses.edit') && !$w['is_default'] ? '<li><form method="post" action="' . url("warehouses/{$w['id']}/default") . '">' . csrf_field() . '<button class="dropdown-item"><i class="bi bi-star me-2 text-muted"></i>Make default</button></form></li>' : '')
        . (can('warehouses.delete') && !$w['is_default'] ? '<li><form method="post" action="' . url("warehouses/{$w['id']}/delete") . '" onsubmit="return confirm(\'Delete this warehouse?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
