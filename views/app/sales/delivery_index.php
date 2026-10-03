<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="customer" class="form-select"><option value="">All customers</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $customer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><input name="q" class="form-control" placeholder="Delivery no." value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('sales.create')): ?><a class="btn btn-primary" href="<?= url('sales/deliveries/create') ?>"><i class="bi bi-plus-lg"></i> Deliver without an order</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Delivery</th><th>Date</th><th>Customer</th><th>From</th><th>Order</th><th>Invoice</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><a href="<?= url("sales/deliveries/{$r['id']}") ?>"><?= e($r['delivery_no']) ?></a> <?= $r['source'] === 'pos' ? '<span class="badge text-bg-light border">POS</span>' : '' ?></td><td><?= e($r['delivery_date']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['warehouse']) ?></td>
    <td><?= $r['order_id'] ? '<a href="' . url('sales/orders/' . (int)$r['order_id']) . '">' . e($r['order_no']) . '</a>' : '—' ?></td>
    <td><?= $r['invoice_id'] ? '<a href="' . url('sales/invoices/' . (int)$r['invoice_id']) . '">' . e($r['invoice_no']) . '</a>' : '<span class="text-muted">not invoiced</span>' ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No deliveries yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
