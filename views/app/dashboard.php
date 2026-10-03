<?php
use App\Models\Charts;
use App\Models\Metrics;

$expiry = $u['tenant_status'] === 'trial' ? $u['trial_ends_at'] : $u['subscription_ends_at'];
$limit = fn($n) => (int)$n === 0 ? 'Unlimited' : $n;
$m = fn($v) => money($v);
$ranges = ['7' => '7 days', '30' => '30 days', '90' => '90 days', 'mtd' => 'This month', 'ytd' => 'This year'];
$canReports = can('reports.view');

$ageRows = function (array $vals, string $href) {
    $r = [];
    foreach (Metrics::AGEING as $i => $name) $r[] = [$name, $vals[$i], money($vals[$i]), $i + 1, null];
    return $r;
};
?>
<form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Period">
  <div class="btn-group btn-group-sm" role="group">
    <?php foreach ($ranges as $k => $lbl): ?>
      <a class="btn btn-outline-secondary <?= $p['key'] === (string)$k ? 'active' : '' ?>" href="?range=<?= e($k) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
  <span class="text-muted small"><?= e(fdate($p['from'])) ?> – <?= e(fdate($p['to'])) ?> · compared with the <?= (int)$p['days'] ?> days before</span>
</form>

<div class="row g-3 mb-3">
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

<div class="row g-3 mb-3">
  <div class="col-12">
    <?php
    $trendTable = Charts::table(['Date', 'Sales', 'Purchases'], array_map(null, $labels, array_map($m, $salesSeries), array_map($m, $buySeries)));
    echo Charts::card('Sales vs purchases', Charts::line($labels, ['Sales' => $salesSeries, 'Purchases' => $buySeries], $m,
        'Sales ' . $m($salesNow) . ' and purchases ' . $m($buyNow) . ' over ' . $p['label']), $trendTable, $p['label']);
    ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <?php
    $rows = array_map(fn($r) => [$r['name'], (float)$r['value'], $m($r['value'])], $topItems);
    echo Charts::card('Top ' . term('items', true) . ' by sales', Charts::hbar($rows, 'No sales in this period.'),
        Charts::table([term('item'), 'Sales'], array_map(fn($r) => [$r[0], $r[2]], $rows)), $p['label']);
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    $rows = array_map(fn($r) => [$r['name'], (float)$r['value'], $m($r['value'])], $byCategory);
    echo Charts::card('Stock value by category', Charts::hbar($rows, 'No stock yet.'),
        Charts::table(['Category', 'Value'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Right now');
    ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <?php
    $rows = $ageRows($ageRec, '');
    echo Charts::card('Money to collect, by age', Charts::hbar($rows), Charts::table(['Age', 'Amount'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Unpaid invoices by days overdue');
    ?>
  </div>
  <div class="col-lg-6">
    <?php
    $rows = $ageRows($agePay, '');
    echo Charts::card('Money to pay, by age', Charts::hbar($rows), Charts::table(['Age', 'Amount'], array_map(fn($r) => [$r[0], $r[2]], $rows)), 'Unpaid bills by days overdue');
    ?>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h6">Running low</h2><?php if ($canReports): ?><a class="small" href="<?= url('reports/low-stock') ?>">Reorder</a><?php endif; ?></div>
    <?php if (!$low): ?><p class="text-muted small mb-0">Everything is above its reorder level.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($low as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><?= e($r['name']) ?></span>
          <span class="<?= (float)$r['stock'] <= 0 ? 'text-danger' : '' ?>"><?= e(qty($r['stock'])) ?> / <?= e(qty($r['reorder_level'])) ?> <?= e($r['unit']) ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <h2 class="h6">Overdue invoices</h2>
    <?php if (!$overdue): ?><p class="text-muted small mb-0">Nothing overdue.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($overdue as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><a href="<?= url('sales/invoices/' . $r['id']) ?>"><?= e($r['invoice_no']) ?></a> · <?= e($r['customer']) ?></span>
          <span><?= e($m($r['due'])) ?> <span class="text-muted">· <?= (int)$r['od'] ?>d</span></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body">
    <h2 class="h6">Orders waiting for delivery</h2>
    <?php if (!$orders): ?><p class="text-muted small mb-0">No open orders.</p><?php endif; ?>
    <ul class="list-unstyled small mb-0">
      <?php foreach ($orders as $r): ?>
        <li class="d-flex justify-content-between py-1 border-bottom"><span><a href="<?= url('sales/orders/' . $r['id']) ?>"><?= e($r['order_no']) ?></a> · <?= e($r['customer']) ?></span>
          <span class="text-muted"><?= $r['expected_date'] ? e(fdate($r['expected_date'])) : '—' ?></span></li>
      <?php endforeach; ?>
    </ul>
  </div></div></div>
</div>

<div class="text-muted small">
  Plan <strong><?= e($u['plan_name']) ?></strong> · Users <?= (int)$userCount ?>/<?= e($limit($u['max_users'])) ?> · Roles <?= (int)$roleCount ?> ·
  <?= $u['tenant_status'] === 'trial' ? 'Trial ends' : 'Valid until' ?> <?= $expiry ? e($expiry) : '—' ?>
</div>
