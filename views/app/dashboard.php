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
<div class="card"><div class="card-body">
  <h2 class="h5">Welcome, <?= e($u['name']) ?> 👋</h2>
  <p class="text-muted mb-0">Your workspace is ready. Items, stock, purchase and sales modules are being added next. For now you can invite your team under <strong>Users</strong> and control access under <strong>Roles</strong>.</p>
</div></div>
