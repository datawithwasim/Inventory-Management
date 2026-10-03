<h2 class="h5 mb-3">Install <?= e(config('name')) ?></h2>
<ul class="list-unstyled mb-3">
  <?php foreach ($requirements as $label => $ok): ?>
    <li><i class="bi <?= $ok ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?> me-2"></i><?= e($label) ?></li>
  <?php endforeach; ?>
</ul>
<?php if ($ready): ?>
<form method="post" action="<?= url('install') ?>"><?= csrf_field() ?>
  <h3 class="h6">Database <small class="text-muted">(cPanel → MySQL Databases)</small></h3>
  <div class="row">
    <div class="col-md-8 mb-3"><label class="form-label">Host</label><input name="db_host" class="form-control" value="<?= e(old('db_host', 'localhost')) ?>" required></div>
    <div class="col-md-4 mb-3"><label class="form-label">Port</label><input name="db_port" class="form-control" value="<?= e(old('db_port', '3306')) ?>" required></div>
  </div>
  <div class="mb-3"><label class="form-label">Database name</label><input name="db_name" class="form-control" value="<?= e(old('db_name')) ?>" placeholder="cpaneluser_inventory" required></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Database user</label><input name="db_user" class="form-control" value="<?= e(old('db_user')) ?>" required></div>
    <div class="col-md-6 mb-3"><label class="form-label">Database password</label><input type="password" name="db_pass" class="form-control"></div>
  </div>
  <hr>
  <h3 class="h6">Your Super Admin account</h3>
  <div class="mb-3"><label class="form-label">App name</label><input name="app_name" class="form-control" value="<?= e(old('app_name', config('name'))) ?>" required></div>
  <div class="mb-3"><label class="form-label">Your name</label><input name="admin_name" class="form-control" value="<?= e(old('admin_name')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Email</label><input type="email" name="admin_email" class="form-control" value="<?= e(old('admin_email')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Password (min 8 characters)</label><input type="password" name="admin_password" class="form-control" minlength="8" required></div>
  <button class="btn btn-primary w-100">Install</button>
</form>
<?php endif; ?>
