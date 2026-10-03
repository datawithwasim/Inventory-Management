<form class="row g-2 mb-3" method="get"><div class="col-md-5"><input name="q" class="form-control" placeholder="Search invoice no. or customer" value="<?= e($q) ?>"></div><div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div></form>
<p class="text-muted">Choose the invoice the goods were sold on.</p>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Invoice</th><th>Date</th><th><?= e(term('customer')) ?></th><th class="text-end">Total</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><?= e($r['invoice_no']) ?></td><td><?= e(fdate($r['invoice_date'])) ?></td><td><?= e($r['customer']) ?></td><td class="text-end"><?= e(money($r['total'])) ?></td>
    <td class="text-end"><a class="btn btn-sm btn-outline-danger" href="<?= url('sales/returns/create?invoice=' . (int)$r['id']) ?>">Return from this</a></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="5" class="text-muted">No invoices found.</td></tr><?php endif; ?></tbody></table></div></div>
