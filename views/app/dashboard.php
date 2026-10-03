<?php
use App\Controllers\DashboardController;
use App\Models\Charts;
use App\Models\Metrics;

$m = fn($v) => money($v);
$ranges = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'mtd' => 'This month', 'ytd' => 'This year'];
$canReports = can('reports.view') && Core\Modules::enabled('reports');
$ageRows = function (array $vals) {
    $r = [];
    foreach (Metrics::AGEING as $i => $name) $r[] = [$name, $vals[$i], money($vals[$i]), $i + 1, null];
    return $r;
};
$buf = function (callable $f): string { ob_start(); $f(); return (string)ob_get_clean(); };

/** key => HTML. Order and visibility come from the user's own layout. */
$render = [];
$render['kpi'] = $buf(function () use ($m, $p, $salesNow, $salesPrev, $buyNow, $buyPrev, $buySeries, $receivable, $payable, $ordersOpen, $lowCount, $stockValue, $rolls, $canReports) { ?>
<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100"><div class="card-body d-flex flex-column justify-content-center">
      <div class="text-muted small">Net sales · <?= e($p['label']) ?></div>
      <div class="hero"><?= e($m($salesNow)) ?></div>
      <div class="small"><?= Charts::delta($salesNow, $salesPrev, true) ?> <span class="text-muted">vs <?= e($m($salesPrev)) ?> before</span></div>
      <div class="text-muted small mt-1">Invoices minus returns</div>
    </div></div>
  </div>
  <div class="col-lg-7">
    <div class="row g-3 h-100">
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl">Purchases</div><div class="big"><?= e($m($buyNow)) ?></div>
        <?= Charts::delta($buyNow, $buyPrev, null) ?><?= Charts::spark($buySeries) ?>
      </div></div></div>
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl"><?= e(term('customers')) ?> owe us</div>
        <div class="big"><a class="text-reset text-decoration-none" href="<?= url('sales/invoices?status=unpaid') ?>"><?= e($m($receivable)) ?></a></div>
      </div></div></div>
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl">We owe <?= e(term('suppliers', true)) ?></div>
        <div class="big"><a class="text-reset text-decoration-none" href="<?= url('purchase/bills?status=unpaid') ?>"><?= e($m($payable)) ?></a></div>
      </div></div></div>
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl">Orders to deliver</div>
        <div class="big"><a class="text-reset text-decoration-none" href="<?= url('sales/orders?status=open') ?>"><?= (int)$ordersOpen ?></a></div>
      </div></div></div>
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl">Low-stock <?= e(term('items', true)) ?></div>
        <div class="big <?= $lowCount ? 'text-danger' : '' ?>"><a class="text-reset text-decoration-none" href="<?= url($canReports ? 'reports/low-stock' : 'stock?low=1') ?>"><?= (int)$lowCount ?></a></div>
      </div></div></div>
      <div class="col-6 col-md-4"><div class="card h-100 stat"><div class="card-body">
        <div class="lbl">Stock value · <?= (int)$rolls ?> rolls</div>
        <div class="big"><?= e($m($stockValue)) ?></div>
      </div></div></div>
    </div>
  </div>
</div>
<?php });

$render['trend'] = $buf(function () use ($m, $p, $labels, $salesSeries, $buySeries, $salesNow, $buyNow) {
    $trendTable = Charts::table(['Date', 'Sales', 'Purchases'], array_map(null, $labels, array_map($m, $salesSeries), array_map($m, $buySeries)));
    echo Charts::card('Sales vs purchases', Charts::line($labels, ['Sales' => $salesSeries, 'Purchases' => $buySeries], $m,
        'Sales ' . $m($salesNow) . ' and purchases ' . $m($buyNow) . ' over ' . $p['label']), $trendTable, $p['label']);
});
$render['top_items'] = $buf(function () use ($m, $p, $topItems) {
    $rows = array_map(fn($r) => [$r['name'], (float)$r['value'], $m($r['value'])], $topItems);
    echo Charts::card('Top ' . term('items', true) . ' by sales', Charts::hbar($rows, 'No sales in this period.'),
        Charts::table([term('item'), 'Sales'], array_map(fn($r) => [$r[0], $r[2]], $rows)), $p['label']);
});
$render['stock_cat'] = $buf(function () use ($m, $byCategory) {
    $rows = array_map(fn($r) => [$r['name'], (float)$r['value'], $m($r['value'])], $byCategory);
    echo Charts::card('Stock value by category', Charts::hbar($rows, 'No stock yet.'),
        Charts::table(['Category', 'Value'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Right now');
});
$render['age_rec'] = $buf(function () use ($ageRows, $ageRec) {
    $rows = $ageRows($ageRec);
    echo Charts::card('Money to collect, by age', Charts::hbar($rows), Charts::table(['Age', 'Amount'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Unpaid invoices by days overdue');
});
$render['age_pay'] = $buf(function () use ($ageRows, $agePay) {
    $rows = $ageRows($agePay);
    echo Charts::card('Money to pay, by age', Charts::hbar($rows), Charts::table(['Age', 'Amount'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Unpaid bills by days overdue');
});
$render['low'] = $buf(function () use ($low, $canReports) { ?>
  <div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h6">Running low</h2><?php if ($canReports): ?><a class="small" href="<?= url('reports/low-stock') ?>">Reorder</a><?php endif; ?></div>
    <?php if (!$low): ?><p class="text-muted small mb-0">Everything is above its reorder level.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($low as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><?= e($r['name']) ?></span>
          <span class="<?= (float)$r['stock'] <= 0 ? 'text-danger' : '' ?>"><?= e(qty($r['stock'])) ?> / <?= e(qty($r['reorder_level'])) ?> <?= e($r['unit']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div>
<?php });
$render['overdue'] = $buf(function () use ($m, $overdue) { ?>
  <div class="card h-100"><div class="card-body">
    <h2 class="h6">Overdue invoices</h2>
    <?php if (!$overdue): ?><p class="text-muted small mb-0">Nothing overdue.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($overdue as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><a href="<?= url('sales/invoices/' . $r['id']) ?>"><?= e($r['invoice_no']) ?></a> · <?= e($r['customer']) ?></span>
          <span><?= e($m($r['due'])) ?> <span class="text-muted">· <?= (int)$r['od'] ?>d</span></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div>
<?php });
$render['orders'] = $buf(function () use ($orders) { ?>
  <div class="card h-100"><div class="card-body">
    <h2 class="h6">Orders waiting for delivery</h2>
    <?php if (!$orders): ?><p class="text-muted small mb-0">No open orders.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($orders as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><a href="<?= url('sales/orders/' . $r['id']) ?>"><?= e($r['order_no']) ?></a> · <?= e($r['customer']) ?></span>
          <span class="text-muted"><?= $r['expected_date'] ? e(fdate($r['expected_date'])) : '—' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div>
<?php });
$spans = ['kpi' => 12, 'trend' => 12, 'top_items' => 6, 'stock_cat' => 6, 'age_rec' => 6, 'age_pay' => 6, 'low' => 4, 'overdue' => 4, 'orders' => 4];
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <div class="btn-group btn-group-sm" role="group" aria-label="Period">
    <?php foreach ($ranges as $k => $lbl): ?>
      <a class="btn btn-outline-secondary <?= $p['key'] === (string)$k ? 'active' : '' ?>" href="?range=<?= e($k) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="text-muted small"><?= e(fdate($p['from'])) ?> – <?= e(fdate($p['to'])) ?> · compared with the <?= (int)$p['days'] ?> days before</span>
  <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="offcanvas" data-bs-target="#dashCustomize"><i class="bi bi-layout-text-window-reverse me-1"></i>Customize</button>
</div>

<div class="dash-grid">
  <?php foreach ($layout['order'] as $key): if (in_array($key, $layout['hidden'], true) || !isset($render[$key])) continue; ?>
    <section class="dash-w span-<?= (int)$spans[$key] ?>" data-widget="<?= e($key) ?>"><?= $render[$key] ?></section>
  <?php endforeach; ?>
  <?php if (count($layout['hidden']) >= count($layout['order'])): ?>
    <div class="empty-state span-12"><i class="bi bi-layout-text-window-reverse"></i>Everything is hidden. Use <strong>Customize</strong> to add widgets back.</div>
  <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="dashCustomize" aria-labelledby="dashCustomizeLabel">
  <form method="post" action="<?= url('dashboard/layout') ?>" class="d-flex flex-column h-100"><?= csrf_field() ?>
    <div class="offcanvas-header"><h2 class="h5 mb-0" id="dashCustomizeLabel">Customize dashboard</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
    <div class="offcanvas-body">
      <p class="text-muted small">Show or hide widgets and move them up or down. This only changes <em>your</em> dashboard.</p>
      <div id="widgetList">
        <?php foreach ($layout['order'] as $key): ?>
          <div class="widget-row" data-key="<?= e($key) ?>">
            <input type="hidden" name="order[]" value="<?= e($key) ?>">
            <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="show[]" value="<?= e($key) ?>" id="w-<?= e($key) ?>" <?= in_array($key, $layout['hidden'], true) ? '' : 'checked' ?>></span>
            <label class="flex-grow-1 m-0" for="w-<?= e($key) ?>"><?= e(DashboardController::WIDGETS[$key]) ?></label>
            <button type="button" class="icon-btn w-up" aria-label="Move up"><i class="bi bi-arrow-up"></i></button>
            <button type="button" class="icon-btn w-down" aria-label="Move down"><i class="bi bi-arrow-down"></i></button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="offcanvas-footer p-3 border-top d-flex gap-2">
      <button class="btn btn-primary flex-grow-1">Save</button>
      <button class="btn btn-outline-secondary" name="reset" value="1" formnovalidate>Reset</button>
    </div>
  </form>
</div>
<script>
document.getElementById('widgetList').addEventListener('click', function (e) {
  var b = e.target.closest('.w-up, .w-down'); if (!b) return;
  var row = b.closest('.widget-row');
  if (b.classList.contains('w-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
  if (b.classList.contains('w-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
});
</script>
<div class="text-muted small mt-4">
  Plan <strong><?= e($u['plan_name']) ?></strong> · Users <?= (int)$userCount ?>/<?= (int)$u['max_users'] === 0 ? 'Unlimited' : (int)$u['max_users'] ?> · Roles <?= (int)$roleCount ?>
</div>
