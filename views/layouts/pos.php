<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <title><?= e($title ?? 'POS') ?> · <?= e(Core\Auth::user()['tenant_name']) ?></title>
  <style>.pos-wrap { max-width: 1500px; margin: 0 auto; padding: 14px; }</style>
</head>
<body class="app density-<?= e(Core\Theme::get('appearance.density')) ?>">
<div class="pos-wrap">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3"><a class="btn btn-sm btn-outline-secondary" href="<?= url('dashboard') ?>"><i class="bi bi-arrow-left"></i> Back</a>
      <span class="fw-semibold"><i class="bi bi-upc-scan me-1 text-primary"></i><?= e(Core\Auth::user()['tenant_name']) ?> · Point of sale</span></div>
    <div class="d-flex align-items-center gap-2"><span class="text-muted small"><?= e(Core\Auth::user()['name']) ?></span>
      <button type="button" class="icon-btn" id="modeBtn" aria-label="Switch light / dark"><i class="bi bi-moon-stars"></i></button></div>
  </div>
  <?= $content ?>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
