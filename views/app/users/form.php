<?php $action = $user ? url("users/{$user['id']}") : url('users'); ?>
<div class="card" style="max-width:560px"><div class="card-body">
<form method="post" action="<?= $action ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Name</label>
    <input name="name" class="form-control" value="<?= e(old('name', $user['name'] ?? '')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" value="<?= e(old('email', $user['email'] ?? '')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Role</label>
    <select name="role_id" class="form-select" required>
      <?php foreach ($roles as $r): ?>
        <option value="<?= (int)$r['id'] ?>" <?= (int)old('role_id', $user['role_id'] ?? 0) === (int)$r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
      <?php endforeach; ?>
    </select></div>
  <div class="mb-3"><label class="form-label"><?= $user ? 'New password (leave blank to keep)' : 'Password' ?></label>
    <input type="password" name="password" class="form-control" minlength="8" <?= $user ? '' : 'required' ?>></div>
  <button class="btn btn-primary">Save</button>
  <a class="btn btn-link" href="<?= url('users') ?>">Cancel</a>
</form></div></div>
