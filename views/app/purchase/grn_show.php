<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Goods receipt</div><strong><?= e($g['grn_no']) ?></strong></div>
  <div class="col-md-3"><div class="text-muted small"><?= e(term('supplier')) ?></div><a href="<?= url('suppliers/' . (int)$g['supplier_id']) ?>"><?= e($g['supplier']) ?></a><?= $g['supplier_ref'] ? '<br><small class="text-muted">Challan ' . e($g['supplier_ref']) . '</small>' : '' ?></div>
  <div class="col-md-3"><div class="text-muted small">Received into</div><?= e($g['warehouse']) ?><br><small class="text-muted"><?= e(fdate($g['received_date'])) ?> by <?= e($g['user_name'] ?? '—') ?></small></div>
  <div class="col-md-3"><div class="text-muted small">Purchase order</div><?= $g['po_id'] ? '<a href="' . url('purchase/orders/' . (int)$g['po_id']) . '">' . e($g['po_no']) . '</a>' : '—' ?></div>
</div>
<?php if ($g['note']): ?><div class="text-muted mt-2"><?= e($g['note']) ?></div><?php endif; ?>
<?php if ((float)$g['extra_cost'] > 0): ?><div class="small mt-2">Extra cost <?= e(money($g['extra_cost'])) ?><?= $g['extra_cost_note'] ? ' (' . e($g['extra_cost_note']) . ')' : '' ?> spread into <?= e(term('item', true)) ?> cost.</div><?php endif; ?>
<div class="mt-3 d-flex gap-2">
  <?php if ($g['bill_id']): ?><a class="btn btn-outline-primary" href="<?= url('purchase/bills/' . (int)$g['bill_id']) ?>">Bill <?= e($g['bill_no']) ?></a>
  <?php elseif (can('purchase.create')): ?><form method="post" action="<?= url("purchase/grns/{$g['id']}/bill") ?>"><?= csrf_field() ?><button class="btn btn-primary">Create bill</button></form><?php endif; ?>
  <?php if (can('purchase.create')): ?><a class="btn btn-outline-danger" href="<?= url('purchase/returns/create?grn=' . (int)$g['id']) ?>">Return goods</a><?php endif; ?>
</div></div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('batch')) ?> (roll)</th><th><?= e(term('rack')) ?></th><th class="text-end">Qty</th><th class="text-end">Returned</th><th class="text-end">Price</th><th class="text-end">Stock cost</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td><?= $l['batch_no'] ? '<a href="' . url('stock/batches/' . (int)$l['batch_id']) . '">' . e($l['batch_no']) . '</a>' . ($l['supplier_lot'] ? '<br><small class="text-muted">lot ' . e($l['supplier_lot']) . '</small>' : '') : '—' ?></td>
      <td><?= e($l['rack'] ?? 'No rack') ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= (float)$l['qty_returned'] > 0 ? e(qty($l['qty_returned'])) : '' ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(number_format((float)$l['landed_unit_cost'], 2)) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div>
<?php if ($returns): ?><div class="card"><div class="card-header">Returns</div><ul class="list-group list-group-flush">
  <?php foreach ($returns as $r): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a><span><?= e(fdate($r['return_date'])) ?> · <?= e(money($r['total'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
