<h2 class="h5 mb-3">Sign in</h2>
<form method="post" action="<?= url('login') ?>">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Email</label>
    <input type="email" name="email" class="form-control" value="<?= e(old('email')) ?>" required autofocus></div>
  <div class="mb-3"><label class="form-label">Password</label>
    <input type="password" name="password" class="form-control" required></div>
  <button class="btn btn-primary w-100">Sign in</button>
  <div class="text-center mt-3"><a href="<?= url('forgot-password') ?>">Forgot password?</a></div>
</form>
