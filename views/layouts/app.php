<?php
$me = Core\Auth::user();
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$active = fn(string $p) => str_starts_with($path, $p) ? 'active' : '';
?>
<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e(($title ?? '') . ' · ' . $me['tenant_name']) ?></title>
</head>
<body>
<nav class="sidebar">
  <a class="brand" href="<?= url('dashboard') ?>"><i class="bi bi-box-seam me-2"></i><?= e($me['tenant_name']) ?></a>
  <ul class="nav flex-column">
    <li><a class="nav-link <?= $active('/dashboard') ?>" href="<?= url('dashboard') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    <li class="nav-heading">Inventory</li>
    <li><span class="nav-link disabled"><i class="bi bi-tags me-2"></i>Items <small>(soon)</small></span></li>
    <li><span class="nav-link disabled"><i class="bi bi-boxes me-2"></i>Stock <small>(soon)</small></span></li>
    <li><span class="nav-link disabled"><i class="bi bi-cart-plus me-2"></i>Purchase <small>(soon)</small></span></li>
    <li><span class="nav-link disabled"><i class="bi bi-receipt me-2"></i>Sales <small>(soon)</small></span></li>
    <li class="nav-heading">Administration</li>
    <?php if (can('users.view')): ?>
      <li><a class="nav-link <?= $active('/users') ?>" href="<?= url('users') ?>"><i class="bi bi-people me-2"></i>Users</a></li>
    <?php endif; ?>
    <?php if (can('roles.view')): ?>
      <li><a class="nav-link <?= $active('/roles') ?>" href="<?= url('roles') ?>"><i class="bi bi-shield-lock me-2"></i>Roles</a></li>
    <?php endif; ?>
    <li><a class="nav-link <?= $active('/profile') ?>" href="<?= url('profile') ?>"><i class="bi bi-person-circle me-2"></i>My profile</a></li>
  </ul>
</nav>
<div class="main">
  <?php if (Core\Auth::impersonating()): ?>
    <div class="bg-warning px-3 py-2 d-flex justify-content-between align-items-center">
      <span><i class="bi bi-eye me-1"></i>Super Admin support mode: you are viewing <strong><?= e($me['tenant_name']) ?></strong>.</span>
      <form method="post" action="<?= url('impersonate/stop') ?>"><?= csrf_field() ?>
        <button class="btn btn-sm btn-dark">Back to admin</button></form>
    </div>
  <?php endif; ?>
  <div class="topbar px-4 py-2 d-flex justify-content-between align-items-center">
    <h1 class="h5 mb-0"><?= e($title ?? '') ?></h1>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small"><?= e($me['name']) ?> · <?= e($me['role_name']) ?></span>
      <form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?>
        <button class="btn btn-sm btn-outline-secondary">Sign out</button></form>
    </div>
  </div>
  <main class="p-4">
    <?php require __DIR__ . '/flash.php'; ?>
    <?= $content ?>
  </main>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
