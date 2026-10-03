<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Sales return</div><strong><?= e($r['return_no']) ?></strong></div>
  <div class="col-md-3"><div class="text-muted small"><?= e(term('customer')) ?></div><?= e($r['customer']) ?></div>
  <div class="col-md-3"><div class="text-muted small">Invoice</div><a href="<?= url('sales/invoices/' . (int)$r['invoice_id']) ?>"><?= e($r['invoice_no']) ?></a></div>
  <div class="col-md-3"><div class="text-muted small">Date</div><?= e(fdate($r['return_date'])) ?> by <?= e($r['user_name'] ?? '—') ?></div></div>
  <?php if ($r['reason']): ?><div class="text-muted mt-2"><?= e($r['reason']) ?></div><?php endif; ?></div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th><?= e(term('item')) ?></th><th>Roll</th><th>Back on</th><th class="text-end">Qty</th><th class="text-end">Credit</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['batch_no'] ?? '') ?></td>
    <td><?= $l['restock'] ? e($l['rack'] ?? 'No rack') : '<span class="badge text-bg-secondary">Not put back</span>' ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['line_total'])) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="4" class="text-end">Total credit (incl. tax)</th><th class="text-end"><?= e(money($r['total'])) ?></th></tr></tfoot></table></div></div>
