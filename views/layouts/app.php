<?php
use Core\Menu;
use Core\Theme;

$me = Core\Auth::user();
$path = '/' . trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(base_path())), '/');
$menu = Menu::sidebar();
$quick = Menu::quick();
[$crumbGroup, $crumbItem] = Menu::current($menu, $path);
$logo = Core\Settings::get('company.logo') !== '' ? company_logo_url() : null;
$initial = mb_strtoupper(mb_substr($me['name'], 0, 1));
$expiry = $me['tenant_status'] === 'trial' ? $me['trial_ends_at'] : $me['subscription_ends_at'];
$sbStyle = Theme::get('appearance.sidebar');
$dashActive = $path === '/dashboard';
?>
<!doctype html>
<html lang="en">
<head>
  <?php require __DIR__ . '/head.php'; ?>
  <meta name="csrf" content="<?= e(csrf_token()) ?>">
  <meta name="base" content="<?= e(url('')) ?>">
  <title><?= e(($title ?? '') . ' · ' . $me['tenant_name']) ?></title>
</head>
<body class="app sbs-<?= e($sbStyle) ?> density-<?= e(Theme::get('appearance.density')) ?>">
<aside class="sb" id="sidebar" aria-label="Main menu">
  <a class="sb-brand" href="<?= url('dashboard') ?>">
    <?php if ($logo): ?><img src="<?= e($logo) ?>" alt="" class="sb-logo"><?php else: ?><span class="sb-mark"><?= e(mb_strtoupper(mb_substr($me['tenant_name'], 0, 1))) ?></span><?php endif; ?>
    <span class="sb-name"><?= e($me['tenant_name']) ?></span>
  </a>
  <nav class="sb-nav">
    <a class="sb-link <?= $dashActive ? 'active' : '' ?>" href="<?= url('dashboard') ?>" title="Dashboard"><i class="bi bi-<?= e($menu['dashboard']['icon']) ?>"></i><span>Dashboard</span></a>
    <?php foreach ($menu['groups'] as $key => $g):
        $open = false;
        foreach ($g['items'] as $it) if (Menu::isActive($it, $path)) $open = true; ?>
      <div class="sb-group <?= $open ? 'open' : '' ?>" data-group="<?= e($key) ?>">
        <button type="button" class="sb-group-head" aria-expanded="<?= $open ? 'true' : 'false' ?>" title="<?= e($g['label']) ?>"><i class="bi bi-<?= e($g['icon']) ?> sb-gicon"></i><span><?= e($g['label']) ?></span><i class="bi bi-chevron-down sb-caret"></i></button>
        <div class="sb-items">
          <?php foreach ($g['items'] as $it): $on = $crumbItem === $it; ?>
            <a class="sb-link <?= $on ? 'active' : '' ?>" href="<?= url(ltrim($it['href'], '/')) ?>" title="<?= e($it['label']) ?>"><i class="bi bi-<?= e($it['icon']) ?>"></i><span><?= e($it['label']) ?></span></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </nav>
  <div class="sb-foot">
    <div class="sb-plan"><span class="badge rounded-pill"><?= e($me['plan_name']) ?></span>
      <span class="small"><?= $me['tenant_status'] === 'trial' ? 'Trial' : 'Valid' ?><?= $expiry ? ' until ' . e(fdate($expiry)) : '' ?></span></div>
    <button type="button" class="sb-collapse" id="sbCollapse" title="Collapse menu"><i class="bi bi-chevron-bar-left"></i><span>Collapse</span></button>
  </div>
</aside>
<div class="sb-backdrop" id="sbBackdrop"></div>

<div class="shell">
  <?php if (Core\Auth::impersonating()): ?>
    <div class="support-bar d-flex justify-content-between align-items-center">
      <span><i class="bi bi-eye me-1"></i>Super Admin support mode: you are viewing <strong><?= e($me['tenant_name']) ?></strong>.</span>
      <form method="post" action="<?= url('impersonate/stop') ?>"><?= csrf_field() ?><button class="btn btn-sm btn-dark">Back to admin</button></form>
    </div>
  <?php endif; ?>
  <header class="topbar">
    <button type="button" class="icon-btn d-lg-none" id="menuBtn" aria-label="Open menu"><i class="bi bi-list fs-4"></i></button>
    <button type="button" class="search-trigger" data-palette aria-label="Search">
      <i class="bi bi-search"></i><span class="d-none d-sm-inline">Search or jump to…</span><kbd class="d-none d-md-inline">Ctrl K</kbd>
    </button>
    <div class="topbar-actions">
      <?php if ($quick): ?>
        <div class="dropdown">
          <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-plus-lg"></i><span class="d-none d-sm-inline">New</span></button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <?php foreach ($quick as $q): ?><li><a class="dropdown-item" href="<?= url(ltrim($q['href'], '/')) ?>"><i class="bi bi-<?= e($q['icon']) ?> me-2 text-muted"></i><?= e($q['label']) ?></a></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <button type="button" class="icon-btn" id="modeBtn" aria-label="Switch light / dark" title="Light / dark"><i class="bi bi-moon-stars"></i></button>
      <div class="dropdown">
        <button class="user-btn" data-bs-toggle="dropdown" aria-expanded="false"><span class="avatar"><?= e($initial) ?></span><span class="d-none d-md-block text-start lh-sm"><span class="d-block fw-semibold small"><?= e($me['name']) ?></span><span class="d-block text-muted" style="font-size:.72rem"><?= e($me['role_name']) ?></span></span><i class="bi bi-chevron-down small d-none d-md-block"></i></button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
          <li class="dropdown-header">Account &amp; administration</li>
          <?php foreach ($menu['account'] as $it): ?>
            <li><a class="dropdown-item" href="<?= url(ltrim($it['href'], '/')) ?>"><i class="bi bi-<?= e($it['icon']) ?> me-2 text-muted"></i><?= e($it['label']) ?></a></li>
          <?php endforeach; ?>
          <li><hr class="dropdown-divider"></li>
          <li><form method="post" action="<?= url('logout') ?>"><?= csrf_field() ?><button class="dropdown-item"><i class="bi bi-box-arrow-right me-2 text-muted"></i>Sign out</button></form></li>
        </ul>
      </div>
    </div>
  </header>
  <main class="content" id="main">
    <div class="page-head">
      <div>
        <?php if ($crumbItem && $path !== '/dashboard'): ?>
          <nav class="crumbs" aria-label="Breadcrumb"><a href="<?= url('dashboard') ?>">Home</a><i class="bi bi-chevron-right"></i><span><?= e($crumbGroup) ?></span><i class="bi bi-chevron-right"></i><a href="<?= url(ltrim($crumbItem['href'], '/')) ?>"><?= e($crumbItem['label']) ?></a></nav>
        <?php endif; ?>
        <h1><?= e($title ?? '') ?></h1>
      </div>
    </div>
    <?php require __DIR__ . '/flash.php'; ?>
    <?= $content ?>
    <?= $GLOBALS['foot_html'] ?? '' ?>
  </main>
</div>

<div class="palette" id="palette" hidden>
  <div class="palette-box" role="dialog" aria-label="Search">
    <div class="palette-input"><i class="bi bi-search"></i><input id="paletteInput" type="search" placeholder="Search items, customers, documents — or type a page name" autocomplete="off"><kbd>Esc</kbd></div>
    <div class="palette-list" id="paletteList"></div>
    <div class="palette-foot"><span><kbd>↑</kbd><kbd>↓</kbd> move</span><span><kbd>Enter</kbd> open</span></div>
  </div>
</div>
<?php
$pal = [['t' => 'Page', 'l' => 'Dashboard', 'i' => $menu['dashboard']['icon'], 'u' => url('dashboard')]];
foreach ($menu['groups'] as $g) foreach ($g['items'] as $it) $pal[] = ['t' => 'Go to', 'l' => $it['label'], 'i' => $it['icon'], 'u' => url(ltrim($it['href'], '/')), 's' => $g['label']];
foreach ($menu['account'] as $it) $pal[] = ['t' => 'Go to', 'l' => $it['label'], 'i' => $it['icon'], 'u' => url(ltrim($it['href'], '/')), 's' => 'Administration'];
foreach ($quick as $q) $pal[] = ['t' => 'Create', 'l' => $q['label'], 'i' => $q['icon'], 'u' => url(ltrim($q['href'], '/'))];
?>
<script id="paletteData" type="application/json"><?= json_encode($pal, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?php require __DIR__ . '/foot.php'; ?>
</body>
</html>
