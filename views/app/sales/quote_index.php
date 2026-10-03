<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option><?php foreach (App\Models\Sales::QUOTE_STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Quotation no. or customer" value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('sales.create')): ?><a class="btn btn-primary" href="<?= url('sales/quotations/create') ?>"><i class="bi bi-plus-lg"></i> New quotation</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Quotation</th><th>Date</th><th>Customer</th><th>Valid until</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><a href="<?= url("sales/quotations/{$r['id']}") ?>"><?= e($r['quote_no']) ?></a></td><td><?= e($r['quote_date']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['valid_until'] ?? '') ?></td><td class="text-end"><?= e(money($r['total'])) ?></td><td><?= sale_badge('quote', $r['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No quotations found.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
