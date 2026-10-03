<div class="card mb-3"><div class="card-body">
  <div class="row">
    <div class="col-md-3"><div class="text-muted small"><?= e($heading) ?></div><strong><?= e($doc['doc_no']) ?></strong></div>
    <div class="col-md-3"><div class="text-muted small">Date</div><?= e($doc['created_at']) ?> by <?= e($doc['user_name'] ?? '—') ?></div>
    <div class="col-md-3"><div class="text-muted small"><?= $doc['to_warehouse'] ? 'From → To' : 'Warehouse' ?></div><?= e($doc['warehouse']) ?><?= $doc['to_warehouse'] ? ' → ' . e($doc['to_warehouse']) : '' ?></div>
    <div class="col-md-3"><?php if ($doc['reason']): ?><div class="text-muted small">Reason</div><?= e($reasons[$doc['reason']] ?? $doc['reason']) ?><?php endif; ?></div>
  </div>
  <?php if ($doc['note']): ?><div class="mt-2 text-muted"><?= e($doc['note']) ?></div><?php endif; ?>
</div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
  <thead><tr><th>Item</th><th>SKU</th><th>Batch</th><th><?= $doc['to_warehouse'] ? 'From rack → To rack' : 'Rack' ?></th><th class="text-end">Qty</th></tr></thead><tbody>
  <?php foreach ($lines as $l): ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['sku']) ?></td>
      <td><?= $l['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$l['batch_id']) . '">' . e($l['batch_no']) . '</a>' : '' ?></td>
      <td><?= e($l['rack'] ?? 'No rack') ?><?= $doc['to_warehouse'] ? ' → ' . e($l['to_rack'] ?? 'No rack') : '' ?></td>
      <td class="text-end <?= $signed ? ($l['qty'] < 0 ? 'text-danger' : 'text-success') : '' ?>"><?= $signed && $l['qty'] > 0 ? '+' : '' ?><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<a class="btn btn-link mt-2" href="<?= url($back) ?>">&laquo; Back</a>
