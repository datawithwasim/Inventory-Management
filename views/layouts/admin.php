<?php
$admin = Core\Auth::admin();
$GLOBALS['pendingMigrations'] = count(Core\Migrator::pending());
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$active = fn(string $p) => ($p === '/admin' ? $path === '/admin' : str_starts_with($path, $p)) ? 'active' : '';
?>
<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e(($title ?? '') . ' · Super Admin') ?></title>
</head>
<body>
<nav class="sidebar" style="background:#111827">
  <a class="brand" href="<?= url('admin') ?>"><i class="bi bi-shield-check me-2"></i>Super Admin</a>
  <ul class="nav flex-column">
    <li><a class="nav-link <?= $active('/admin') ?>" href="<?= url('admin') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a></li>
    <li><a class="nav-link <?= $active('/admin/tenants') ?>" href="<?= url('admin/tenants') ?>"><i class="bi bi-buildings me-2"></i>Companies</a></li>
    <li><a class="nav-link <?= $active('/admin/plans') ?>" href="<?= url('admin/plans') ?>"><i class="bi bi-layers me-2"></i>Plans</a></li>
    <li><a class="nav-link <?= $active('/admin/system') ?>" href="<?= url('admin/system') ?>"><i class="bi bi-gear me-2"></i>System<?php if (!empty($GLOBALS['pendingMigrations'])): ?> <span class="badge text-bg-warning"><?= (int)$GLOBALS['pendingMigrations'] ?></span><?php endif; ?></a></li>
    <li><a class="nav-link <?= $active('/admin/audit') ?>" href="<?= url('admin/audit') ?>"><i class="bi bi-journal-text me-2"></i>Audit log</a></li>
  </ul>
</nav>
<div class="main">
  <div class="topbar px-4 py-2 d-flex justify-content-between align-items-center">
    <h1 class="h5 mb-0"><?= e($title ?? '') ?></h1>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted small"><?= e($admin['name']) ?></span>
      <form method="post" action="<?= url('admin/logout') ?>"><?= csrf_field() ?>
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
