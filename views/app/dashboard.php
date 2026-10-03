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
<div class="card"><div class="card-body">
  <h2 class="h5">Welcome, <?= e($u['name']) ?> 👋</h2>
  <p class="text-muted mb-0">Start by adding <strong>Items</strong>, then enter opening stock under <strong>Stock → Adjustments</strong>. Purchase and Sales are coming next.</p>
</div></div>
