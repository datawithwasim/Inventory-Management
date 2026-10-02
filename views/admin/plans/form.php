<?php $action = $plan ? url("admin/plans/{$plan['id']}") : url('admin/plans'); $v = fn($k, $d = '') => e(old($k, $plan[$k] ?? $d)); ?>
<div class="card" style="max-width:560px"><div class="card-body">
<form method="post" action="<?= $action ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Plan name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Max users</label><input type="number" min="0" name="max_users" class="form-control" value="<?= $v('max_users', 0) ?>" required></div>
    <div class="col-md-4 mb-3"><label class="form-label">Max items</label><input type="number" min="0" name="max_items" class="form-control" value="<?= $v('max_items', 0) ?>" required></div>
    <div class="col-md-4 mb-3"><label class="form-label">Max warehouses</label><input type="number" min="0" name="max_warehouses" class="form-control" value="<?= $v('max_warehouses', 0) ?>" required></div>
  </div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0" name="price" class="form-control" value="<?= $v('price', 0) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Trial days</label><input type="number" min="0" name="trial_days" class="form-control" value="<?= $v('trial_days', 0) ?>" required></div>
  </div>
  <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= (!$plan || $plan['is_active']) ? 'checked' : '' ?>><label class="form-check-label" for="act">Active (can be assigned to new companies)</label></div>
  <p class="text-muted small">0 = unlimited.</p>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('admin/plans') ?>">Cancel</a>
</form></div></div>
