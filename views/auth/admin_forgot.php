<h2 class="h5 mb-3">Super Admin: forgot password</h2>
<form method="post" action="<?= url('admin/forgot-password') ?>">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Your Super Admin email</label>
    <input type="email" name="email" class="form-control" required autofocus></div>
  <button class="btn btn-dark w-100">Send reset link</button>
  <div class="text-center mt-3"><a href="<?= url('admin/login') ?>">Back to sign in</a></div>
</form>
