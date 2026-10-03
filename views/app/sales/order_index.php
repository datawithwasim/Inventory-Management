<form class="row g-2 mb-3" method="get">
  <div class="col-md-3"><select name="status" class="form-select"><option value="">All statuses</option><option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open (to deliver)</option>
    <?php foreach (App\Models\Sales::ORDER_STATUS as $k => [$l]): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><select name="customer" class="form-select"><option value="">All customers</option><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $customer === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><input name="q" class="form-control" placeholder="Order no." value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end"><?php if (can('sales.create')): ?><a class="btn btn-primary" href="<?= url('sales/orders/create') ?>"><i class="bi bi-plus-lg"></i> New sales order</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Ship from</th><th>Deliver by</th><th class="text-end">Total</th><th>Status</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><a href="<?= url("sales/orders/{$r['id']}") ?>"><?= e($r['order_no']) ?></a></td><td><?= e($r['order_date']) ?></td><td><?= e($r['customer']) ?></td><td><?= e($r['warehouse']) ?></td><td><?= e($r['expected_date'] ?? '') ?></td><td class="text-end"><?= e(money($r['total'])) ?></td><td><?= sale_badge('order', $r['status']) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="text-muted">No orders found.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
