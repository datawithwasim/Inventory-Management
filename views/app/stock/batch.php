<?php require_once __DIR__ . '/_types.php'; $st = App\Models\Stock::batchStatus((float)$balance, (float)$b['received_qty']); ?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body">
    <div class="text-muted small">Item</div><a href="<?= url("items/{$b['item_id']}") ?>"><?= e($b['item_name']) ?><?= $b['vname'] ? ' — ' . e($b['vname']) : '' ?></a>
    <div class="text-muted small mt-2">SKU</div><?= e($b['sku']) ?>
    <div class="text-muted small mt-2">Supplier lot</div><?= e($b['supplier_lot'] ?: '—') ?>
    <div class="text-muted small mt-2">Received</div><?= e($b['received_date']) ?> · cost <?= e(money($b['unit_cost'])) ?>/<?= e($b['unit']) ?>
  </div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body">
    <div class="text-muted small">Roll size (received)</div><div class="fs-4"><?= e(qty($b['received_qty'])) ?> <?= e($b['unit']) ?></div>
    <div class="text-muted small mt-2">Balance now</div><div class="fs-4"><?= e(qty($balance)) ?> <?= e($b['unit']) ?> <span class="badge text-bg-<?= $st === 'Finished' ? 'secondary' : ($st === 'Available' ? 'success' : 'info') ?> fs-6"><?= e($st) ?></span></div>
    <div class="text-muted small mt-2">Used so far</div><?= e(qty(max(0, (float)$b['received_qty'] - (float)$balance))) ?> <?= e($b['unit']) ?>
  </div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-header">Where it is</div><ul class="list-group list-group-flush">
    <?php foreach ($where as $w): ?><li class="list-group-item d-flex justify-content-between"><?= e($w['name']) ?><strong><?= e(qty($w['qty'])) ?> <?= e($b['unit']) ?></strong></li><?php endforeach; ?>
    <?php if (!$where): ?><li class="list-group-item text-muted">No stock left.</li><?php endif; ?>
  </ul></div></div>
</div>
<div class="card"><div class="card-header">History of this batch</div>
<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>When</th><th>Type</th><th>Warehouse</th><th class="text-end">Qty</th><th>By</th><th>Note</th></tr></thead><tbody>
<?php foreach ($history as $h): ?>
  <tr><td class="text-nowrap"><?= e($h['created_at']) ?></td><td><?= e(stock_type($h['type'])) ?></td><td><?= e($h['warehouse']) ?></td>
    <td class="text-end <?= $h['qty_change'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $h['qty_change'] > 0 ? '+' : '' ?><?= e(qty($h['qty_change'])) ?></td><td><?= e($h['user_name'] ?? '') ?></td><td><?= e($h['note'] ?? '') ?></td></tr>
<?php endforeach; ?></tbody></table></div></div>
