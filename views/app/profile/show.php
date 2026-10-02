<div class="row g-4">
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h6 mb-3">Details</h2>
    <form method="post" action="<?= url('profile') ?>"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Name</label>
        <input name="name" class="form-control" value="<?= e(old('name', $me['name'])) ?>" required></div>
      <div class="mb-3"><label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e(old('email', $me['email'])) ?>" required></div>
      <button class="btn btn-primary">Save</button>
    </form>
  </div></div></div>
  <div class="col-lg-6"><div class="card"><div class="card-body">
    <h2 class="h6 mb-3">Change password</h2>
    <form method="post" action="<?= url('profile/password') ?>"><?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Current password</label>
        <input type="password" name="current_password" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">New password</label>
        <input type="password" name="password" class="form-control" minlength="8" required></div>
      <div class="mb-3"><label class="form-label">Confirm new password</label>
        <input type="password" name="password_confirm" class="form-control" minlength="8" required></div>
      <button class="btn btn-primary">Change password</button>
    </form>
  </div></div></div>
</div>
