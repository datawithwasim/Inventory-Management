<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e(($title ?? 'Sign in') . ' · ' . config('name')) ?></title>
</head>
<body>
<div class="auth-wrap p-3">
  <div class="auth-card">
    <div class="text-center mb-4">
      <i class="bi bi-box-seam fs-1 text-primary"></i>
      <h1 class="h4 mt-2"><?= e(config('name')) ?></h1>
    </div>
    <div class="card shadow-sm"><div class="card-body p-4">
      <?php require __DIR__ . '/flash.php'; ?>
      <?= $content ?>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
