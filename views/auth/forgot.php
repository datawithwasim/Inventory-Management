<h2 class="h5 mb-3">Forgot password</h2>
<form method="post" action="<?= url('forgot-password') ?>">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Your email</label>
    <input type="email" name="email" class="form-control" required autofocus></div>
  <button class="btn btn-primary w-100">Send reset link</button>
  <div class="text-center mt-3"><a href="<?= url('login') ?>">Back to sign in</a></div>
</form>
