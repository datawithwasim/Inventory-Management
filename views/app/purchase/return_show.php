<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Purchase return</div><strong><?= e($r['return_no']) ?></strong></div>
  <div class="col-md-3"><div class="text-muted small">Supplier</div><?= e($r['supplier']) ?></div>
  <div class="col-md-3"><div class="text-muted small">Receipt / bill</div><a href="<?= url('purchase/grns/' . (int)$r['grn_id']) ?>"><?= e($r['grn_no']) ?></a><?= $r['bill_id'] ? ' · <a href="' . url('purchase/bills/' . (int)$r['bill_id']) . '">' . e($r['bill_no']) . '</a>' : '' ?></div>
  <div class="col-md-3"><div class="text-muted small">Date</div><?= e($r['return_date']) ?> by <?= e($r['user_name'] ?? '—') ?></div></div>
  <?php if ($r['reason']): ?><div class="text-muted mt-2"><?= e($r['reason']) ?></div><?php endif; ?></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Item</th><th>Batch</th><th>Rack</th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Value</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['batch_no'] ?? '—') ?></td><td><?= e($l['rack'] ?? 'No rack') ?></td>
    <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(money($l['line_total'])) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="5" class="text-end">Total (incl. tax)</th><th class="text-end"><?= e(money($r['total'])) ?></th></tr></tfoot></table></div></div>
