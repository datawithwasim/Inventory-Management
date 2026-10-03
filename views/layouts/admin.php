<?php
$admin = Core\Auth::admin();
$GLOBALS['pendingMigrations'] = count(Core\Migrator::pending());
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$active = fn(string $p) => ($p === '/admin' ? $path === '/admin' : str_starts_with($path, $p)) ? 'active' : '';
$nav = [['/admin', 'speedometer2', 'Dashboard'], ['/admin/tenants', 'buildings', 'Companies'], ['/admin/plans', 'layers', 'Plans'], ['/admin/system', 'gear', 'System'], ['/admin/audit', 'journal-text', 'Audit log']];
?>
<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <title><?= e(($title ?? '') . ' · Super Admin') ?></title>
</head>
<body class="app sbs-dark">
<aside class="sb" id="sidebar">
  <a class="sb-brand" href="<?= url('admin') ?>"><span class="sb-mark"><i class="bi bi-shield-check"></i></span><span class="sb-name">Super Admin</span></a>
  <nav class="sb-nav">
    <?php foreach ($nav as [$p, $icon, $label]): ?>
      <a class="sb-link <?= $active($p) ?>" href="<?= url(ltrim($p, '/')) ?>" title="<?= e($label) ?>"><i class="bi bi-<?= $icon ?>"></i><span><?= e($label) ?></span>
        <?php if ($p === '/admin/system' && !empty($GLOBALS['pendingMigrations'])): ?><span class="badge text-bg-warning ms-auto"><?= (int)$GLOBALS['pendingMigrations'] ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot"><button type="button" class="sb-collapse" id="sbCollapse"><i class="bi bi-chevron-bar-left"></i><span>Collapse</span></button></div>
</aside>
<div class="sb-backdrop" id="sbBackdrop"></div>
<div class="shell">
  <header class="topbar">
    <button type="button" class="icon-btn d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
    <div class="topbar-actions">
      <button type="button" class="icon-btn" id="modeBtn" aria-label="Switch light / dark"><i class="bi bi-moon-stars"></i></button>
      <span class="text-muted small d-none d-sm-inline"><?= e($admin['name']) ?></span>
      <form method="post" action="<?= url('admin/logout') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-secondary">Sign out</button></form>
    </div>
  </header>
  <main class="content">
    <div class="page-head"><div><h1><?= e($title ?? '') ?></h1></div></div>
    <?php require __DIR__ . '/flash.php'; ?>
    <?= $content ?>
  </main>
</div>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
