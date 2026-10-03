<div class="d-flex justify-content-between mb-3"><div class="text-muted">Goods taken back from <?= e(term('customers', true)) ?>. The <?= e(term('customer', true)) ?> is credited on the invoice.</div>
  <?php if (can('sales.create')): ?><a class="btn btn-primary" href="<?= url('sales/returns/create') ?>"><i class="bi bi-plus-lg"></i> New return</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Return</th><th>Date</th><th><?= e(term('customer')) ?></th><th>Invoice</th><th>Reason</th><th class="text-end">Credit</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><a href="<?= url("sales/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a></td><td><?= e(fdate($r['return_date'])) ?></td><td><?= e($r['customer']) ?></td><td><a href="<?= url('sales/invoices/' . (int)$r['invoice_id']) ?>"><?= e($r['invoice_no']) ?></a></td><td><?= e($r['reason'] ?? '') ?></td><td class="text-end"><?= e(money($r['total'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No returns yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
