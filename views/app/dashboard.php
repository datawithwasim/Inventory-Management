<?php
$expiry = $u['tenant_status'] === 'trial' ? $u['trial_ends_at'] : $u['subscription_ends_at'];
$limit = fn($n) => (int)$n === 0 ? 'Unlimited' : $n;
?>
<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="text-muted small">Users</div>
    <div class="fs-3"><?= (int)$userCount ?> <small class="text-muted fs-6">/ <?= e($limit($u['max_users'])) ?></small></div>
  </div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="text-muted small">Roles</div><div class="fs-3"><?= (int)$roleCount ?></div>
  </div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="text-muted small">Plan</div><div class="fs-3"><?= e($u['plan_name']) ?></div>
  </div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body">
    <div class="text-muted small"><?= $u['tenant_status'] === 'trial' ? 'Trial ends' : 'Valid until' ?></div>
    <div class="fs-3"><?= $expiry ? e($expiry) : '—' ?></div>
  </div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md"><div class="card"><div class="card-body"><div class="text-muted small">Items</div><div class="fs-3"><a class="text-decoration-none" href="<?= url('items') ?>"><?= (int)$stats['items'] ?></a></div></div></div></div>
  <div class="col-6 col-md"><div class="card"><div class="card-body"><div class="text-muted small">Warehouses</div><div class="fs-3"><?= (int)$stats['warehouses'] ?></div></div></div></div>
  <div class="col-6 col-md"><div class="card"><div class="card-body"><div class="text-muted small">Rolls in stock</div><div class="fs-3"><a class="text-decoration-none" href="<?= url('stock/batches') ?>"><?= (int)$stats['rolls'] ?></a></div></div></div></div>
  <div class="col-6 col-md"><div class="card"><div class="card-body"><div class="text-muted small">Low stock items</div><div class="fs-3 <?= $stats['low'] ? 'text-danger' : '' ?>"><a class="text-decoration-none text-reset" href="<?= url('stock?low=1') ?>"><?= (int)$stats['low'] ?></a></div></div></div></div>
  <div class="col-6 col-md"><div class="card"><div class="card-body"><div class="text-muted small">Stock value</div><div class="fs-3"><?= e(money($stats['value'])) ?></div></div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Open purchase orders</div><div class="fs-3"><a class="text-decoration-none" href="<?= url('purchase/orders?status=open') ?>"><?= (int)$stats['open_pos'] ?></a></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">We owe suppliers</div><div class="fs-3 <?= $stats['owed'] > 0.004 ? 'text-danger' : '' ?>"><a class="text-decoration-none text-reset" href="<?= url('purchase/bills?status=unpaid') ?>"><?= e(money($stats['owed'])) ?></a></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Overdue bills</div><div class="fs-3 <?= $stats['overdue'] ? 'text-danger' : '' ?>"><a class="text-decoration-none text-reset" href="<?= url('purchase/bills?status=overdue') ?>"><?= (int)$stats['overdue'] ?></a></div></div></div></div>
</div>
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Sales today</div><div class="fs-3"><a class="text-decoration-none text-reset" href="<?= url('sales/invoices') ?>"><?= e(money($stats['sales_today'])) ?></a></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Orders to deliver</div><div class="fs-3"><a class="text-decoration-none" href="<?= url('sales/orders?status=open') ?>"><?= (int)$stats['orders_open'] ?></a></div></div></div></div>
  <div class="col-6 col-md-4"><div class="card"><div class="card-body"><div class="text-muted small">Customers owe us</div><div class="fs-3 <?= $stats['receivable'] > 0.004 ? 'text-danger' : '' ?>"><a class="text-decoration-none text-reset" href="<?= url('sales/invoices?status=unpaid') ?>"><?= e(money($stats['receivable'])) ?></a></div></div></div></div>
</div>
<div class="card"><div class="card-body">
  <h2 class="h5">Welcome, <?= e($u['name']) ?> 👋</h2>
  <p class="text-muted mb-0">Start by adding <strong>Items</strong>, then enter opening stock under <strong>Stock → Adjustments</strong>. Then buy with <strong>Purchase → Purchase orders</strong> and receive goods onto racks. Sell from <strong>POS</strong> at the counter or through <strong>Quotation → Order → Delivery → Invoice</strong>.</p>
</div></div>
