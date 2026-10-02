<h2 class="h5 mb-3">Super Admin sign in</h2>
<form method="post" action="<?= url('admin/login') ?>">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required autofocus></div>
  <div class="mb-3"><label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required></div>
  <button class="btn btn-dark w-100">Sign in</button>
</form>
