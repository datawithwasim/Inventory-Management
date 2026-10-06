<?php ob_start(); ?>
<div class="card mb-3" id="stock"><div class="card-header">Stored on this <?= e(term('rack', true)) ?></div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th><?= e(term('item')) ?></th><th>SKU</th><th><?= e(term('batch')) ?></th><th class="text-end">Qty</th></tr></thead><tbody>
  <?php foreach ($stock as $r): ?><tr><td><a href="<?= url('items/' . (int)$r['item_id']) ?>"><?= e($r['item_name']) ?></a><?= $r['vname'] ? ' — ' . e($r['vname']) : '' ?></td><td><?= e($r['sku']) ?></td>
    <td><?= $r['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$r['batch_id']) . '">' . e($r['batch_no']) . '</a>' : '' ?></td><td class="text-end"><?= e(qty($r['qty'])) ?> <?= e($r['unit']) ?></td></tr><?php endforeach; ?>
  <?php if (!$stock): ?><tr><td colspan="4" class="text-muted">Nothing is stored here.</td></tr><?php endif; ?></tbody></table></div></div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'location', 'row' => $l, 'name' => $l['code'], 'back' => 'locations', 'body' => $body, 'badges' => $l['is_active'] ? '' : '<span class="badge text-bg-secondary">Inactive</span>',
    'factsTitle' => e(term('rack')) . ' details', 'related' => ['stock' => 'Stored here'],
    'facts' => [[term('warehouse'), '<a href="' . url('warehouses/' . (int)$l['warehouse_id']) . '">' . e($l['warehouse']) . '</a>'], ['Code', e($l['code'])], ['Description', e($l['description'] ?? '')]],
    'actions' => (can('warehouses.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("locations/{$l['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('warehouses.delete') ? '<li><form method="post" action="' . url("locations/{$l['id']}/delete") . '" onsubmit="return confirm(\'Delete this?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
