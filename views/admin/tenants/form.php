<?php
$action = $tenant ? url("admin/tenants/{$tenant['id']}") : url('admin/tenants');
$v = fn($k, $d = '') => e(old($k, $tenant[$k] ?? $d));
$status = old('status', $tenant['status'] ?? 'trial');
?>
<div class="row"><div class="col-lg-7"><div class="card"><div class="card-body">
<form method="post" action="<?= $action ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Company name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Plan</label>
      <select name="plan_id" class="form-select" required>
        <?php foreach ($plans as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (int)old('plan_id', $tenant['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="col-md-6 mb-3"><label class="form-label">Status</label>
      <select name="status" class="form-select">
        <?php foreach (['trial', 'active', 'suspended'] as $s): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option><?php endforeach; ?>
      </select></div>
  </div>
  <div class="row">
    <?php if ($tenant): ?>
      <div class="col-md-6 mb-3"><label class="form-label">Trial ends</label><input type="date" name="trial_ends_at" class="form-control" value="<?= $v('trial_ends_at') ?>"></div>
    <?php endif; ?>
    <div class="col-md-6 mb-3"><label class="form-label">Subscription ends</label><input type="date" name="subscription_ends_at" class="form-control" value="<?= $v('subscription_ends_at') ?>"></div>
  </div>
  <?php if (!$tenant): ?>
    <hr><h2 class="h6">Owner account</h2>
    <div class="mb-3"><label class="form-label">Owner name</label><input name="owner_name" class="form-control" value="<?= e(old('owner_name')) ?>" required></div>
    <div class="mb-3"><label class="form-label">Owner email</label><input type="email" name="owner_email" class="form-control" value="<?= e(old('owner_email')) ?>" required></div>
    <div class="mb-3"><label class="form-label">Temporary password</label><input type="text" name="owner_password" class="form-control" minlength="8" required></div>
  <?php endif; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('admin/tenants') ?>">Cancel</a>
</form></div></div></div>
<?php if ($tenant && !empty($users)): ?>
<div class="col-lg-5"><div class="card"><div class="card-header">Users</div>
  <ul class="list-group list-group-flush">
    <?php foreach ($users as $u): ?>
      <li class="list-group-item d-flex justify-content-between"><span><?= e($u['name']) ?><br><small class="text-muted"><?= e($u['email']) ?></small></span>
      <span><span class="badge text-bg-secondary"><?= e($u['role_name']) ?></span> <?= $u['is_active'] ? '' : '<span class="badge text-bg-danger">off</span>' ?></span></li>
    <?php endforeach; ?>
  </ul></div></div>
<?php endif; ?>
</div>
