<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <title><?= e($title ?? 'POS') ?> · <?= e(Core\Auth::user()['tenant_name']) ?></title>
  <style>body { background: #f4f6f9; } .pos-wrap { max-width: 1400px; margin: 0 auto; padding: 12px; }</style>
</head>
<body>
<div class="pos-wrap">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div><a class="btn btn-sm btn-outline-secondary" href="<?= url('dashboard') ?>"><i class="bi bi-arrow-left"></i> Back</a>
      <strong class="ms-2"><?= e(Core\Auth::user()['tenant_name']) ?> · Point of sale</strong></div>
    <span class="text-muted small"><?= e(Core\Auth::user()['name']) ?></span>
  </div>
  <?= $content ?>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
