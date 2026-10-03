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
    <?php if (can('items.view')): ?><li><a class="nav-link <?= $active('/items') ?>" href="<?= url('items') ?>"><i class="bi bi-tags me-2"></i><?= e(term('items')) ?></a></li><?php endif; ?>
    <?php if (can('stock.view')): ?>
      <li><a class="nav-link <?= $path === '/stock' ? 'active' : '' ?>" href="<?= url('stock') ?>"><i class="bi bi-boxes me-2"></i>Stock</a></li>
      <li><a class="nav-link <?= $active('/stock/racks') ?>" href="<?= url('stock/racks') ?>"><i class="bi bi-geo-alt me-2"></i>Stock by <?= e(term('rack', true)) ?></a></li>
      <li><a class="nav-link <?= $active('/stock/batches') ?>" href="<?= url('stock/batches') ?>"><i class="bi bi-layers me-2"></i><?= e(term('batches')) ?> (rolls)</a></li>
      <li><a class="nav-link <?= $active('/stock/adjustments') ?>" href="<?= url('stock/adjustments') ?>"><i class="bi bi-sliders me-2"></i>Adjustments</a></li>
      <li><a class="nav-link <?= $active('/stock/transfers') ?>" href="<?= url('stock/transfers') ?>"><i class="bi bi-arrow-left-right me-2"></i>Transfers</a></li>
      <li><a class="nav-link <?= $active('/stock/takes') ?>" href="<?= url('stock/takes') ?>"><i class="bi bi-clipboard-check me-2"></i>Stock-takes</a></li>
      <li><a class="nav-link <?= $active('/labels') ?>" href="<?= url('labels') ?>"><i class="bi bi-upc me-2"></i>Barcode labels</a></li>
      <li><a class="nav-link <?= $active('/stock/ledger') ?>" href="<?= url('stock/ledger') ?>"><i class="bi bi-journal-text me-2"></i>Stock ledger</a></li>
    <?php endif; ?>
    <?php if (can('warehouses.view')): ?>
      <li><a class="nav-link <?= $active('/warehouses') ?>" href="<?= url('warehouses') ?>"><i class="bi bi-building me-2"></i><?= e(term('warehouses')) ?></a></li>
      <li><a class="nav-link <?= $active('/locations') ?>" href="<?= url('locations') ?>"><i class="bi bi-grid-3x3-gap me-2"></i><?= e(term('racks')) ?> / locations</a></li>
    <?php endif; ?>
    <?php if (can('masters.view')): ?><li><a class="nav-link <?= $active('/masters') ?>" href="<?= url('masters/categories') ?>"><i class="bi bi-list-check me-2"></i>Masters</a></li><?php endif; ?>
    <?php if (can('purchase.view') || can('suppliers.view')): ?>
      <li class="nav-heading">Purchase</li>
      <?php if (can('suppliers.view')): ?><li><a class="nav-link <?= $active('/suppliers') ?>" href="<?= url('suppliers') ?>"><i class="bi bi-truck me-2"></i><?= e(term('suppliers')) ?></a></li><?php endif; ?>
      <?php if (can('purchase.view')): ?>
        <li><a class="nav-link <?= $active('/purchase/requisitions') ?>" href="<?= url('purchase/requisitions') ?>"><i class="bi bi-card-checklist me-2"></i>Requisitions</a></li>
        <li><a class="nav-link <?= $active('/purchase/orders') ?>" href="<?= url('purchase/orders') ?>"><i class="bi bi-cart-plus me-2"></i>Purchase orders</a></li>
        <li><a class="nav-link <?= $active('/purchase/grns') ?>" href="<?= url('purchase/grns') ?>"><i class="bi bi-box-arrow-in-down me-2"></i>Goods receipts</a></li>
        <li><a class="nav-link <?= $active('/purchase/bills') ?>" href="<?= url('purchase/bills') ?>"><i class="bi bi-receipt-cutoff me-2"></i>Bills &amp; payments</a></li>
        <li><a class="nav-link <?= $active('/purchase/returns') ?>" href="<?= url('purchase/returns') ?>"><i class="bi bi-arrow-return-left me-2"></i>Returns</a></li>
      <?php endif; ?>
    <?php endif; ?>
    <li class="nav-heading">Sales</li>
    <?php if (can('sales.view') || can('customers.view') || can('pos.use')): ?>
      <?php if (can('pos.use')): ?><li><a class="nav-link <?= $active('/pos') ?>" href="<?= url('pos') ?>"><i class="bi bi-upc-scan me-2"></i>POS (counter)</a></li><?php endif; ?>
      <?php if (can('customers.view')): ?><li><a class="nav-link <?= $active('/customers') ?>" href="<?= url('customers') ?>"><i class="bi bi-person-lines-fill me-2"></i><?= e(term('customers')) ?></a></li><?php endif; ?>
      <?php if (can('sales.view')): ?>
        <li><a class="nav-link <?= $active('/sales/quotations') ?>" href="<?= url('sales/quotations') ?>"><i class="bi bi-file-earmark-text me-2"></i>Quotations</a></li>
        <li><a class="nav-link <?= $active('/sales/orders') ?>" href="<?= url('sales/orders') ?>"><i class="bi bi-bag-check me-2"></i>Sales orders</a></li>
        <li><a class="nav-link <?= $active('/sales/deliveries') ?>" href="<?= url('sales/deliveries') ?>"><i class="bi bi-truck me-2"></i>Deliveries</a></li>
        <li><a class="nav-link <?= $active('/sales/invoices') ?>" href="<?= url('sales/invoices') ?>"><i class="bi bi-receipt me-2"></i>Invoices &amp; payments</a></li>
        <li><a class="nav-link <?= $active('/sales/returns') ?>" href="<?= url('sales/returns') ?>"><i class="bi bi-arrow-counterclockwise me-2"></i>Sales returns</a></li>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (can('reports.view')): ?>
      <li class="nav-heading">Insights</li>
      <li><a class="nav-link <?= $active('/reports') ?>" href="<?= url('reports') ?>"><i class="bi bi-bar-chart-line me-2"></i>Reports</a></li>
    <?php endif; ?>
    <li class="nav-heading">Administration</li>
    <?php if (can('users.view')): ?>
      <li><a class="nav-link <?= $active('/users') ?>" href="<?= url('users') ?>"><i class="bi bi-people me-2"></i>Users</a></li>
    <?php endif; ?>
    <?php if (can('roles.view')): ?>
      <li><a class="nav-link <?= $active('/roles') ?>" href="<?= url('roles') ?>"><i class="bi bi-shield-lock me-2"></i>Roles</a></li>
    <?php endif; ?>
    <?php if (can('settings.view')): ?><li><a class="nav-link <?= $active('/settings') ?>" href="<?= url('settings') ?>"><i class="bi bi-sliders2 me-2"></i>Settings</a></li><?php endif; ?>
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
