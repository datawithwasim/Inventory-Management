<h2 class="h5 mb-3">Choose a new password</h2>
<form method="post" action="<?= url('reset-password/' . e($token)) ?>">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">New password</label>
    <input type="password" name="password" class="form-control" minlength="8" required autofocus></div>
  <div class="mb-3"><label class="form-label">Confirm password</label>
    <input type="password" name="password_confirm" class="form-control" minlength="8" required></div>
  <button class="btn btn-primary w-100">Update password</button>
</form>
