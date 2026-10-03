<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e(($title ?? 'Sign in') . ' · ' . config('name')) ?></title>
</head>
<body>
<div class="auth-split">
  <section class="auth-hero">
    <div class="auth-logo"><span class="sb-mark"><i class="bi bi-box-seam"></i></span><?= e(config('name')) ?></div>
    <div>
      <h2>Know exactly what you have, where it is, and what is selling.</h2>
      <ul>
        <li><i class="bi bi-check-circle-fill"></i>Roll-by-roll fabric tracking with rack locations</li>
        <li><i class="bi bi-check-circle-fill"></i>Purchase, sales, returns and counter billing</li>
        <li><i class="bi bi-check-circle-fill"></i>Reports, barcode labels and your own settings</li>
      </ul>
    </div>
    <div class="small opacity-75">&copy; <?= date('Y') ?> <?= e(config('name')) ?></div>
  </section>
  <section class="auth-pane">
    <div class="auth-card" <?= !empty($wide) ? 'style="max-width:640px"' : '' ?>>
      <div class="d-lg-none text-center mb-3"><span class="sb-mark mx-auto mb-2"><i class="bi bi-box-seam"></i></span><div class="fw-bold"><?= e(config('name')) ?></div></div>
      <div class="card"><div class="card-body p-4">
        <?php require __DIR__ . '/flash.php'; ?>
        <?= $content ?>
      </div></div>
    </div>
  </section>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
