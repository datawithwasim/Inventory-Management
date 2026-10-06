<?php ob_start(); ?>
<div class="card mb-3" id="balance"><div class="card-body"><div class="row">
  <div class="col-6"><div class="text-muted small">Owes us</div><div class="fs-2 <?= $owed > 0.004 ? 'text-danger' : '' ?>"><?= e(money($owed)) ?></div><div class="text-muted small">after returns and payments</div></div>
  <div class="col-6"><div class="text-muted small">Advance held</div><div class="fs-2"><?= e(money($advance)) ?></div><div class="text-muted small">taken on orders, not yet adjusted</div></div></div></div></div>
<div class="row g-3 mb-3">
  <div class="col-md-6" id="orders"><div class="card"><div class="card-header">Recent orders</div><ul class="list-group list-group-flush">
    <?php foreach ($orders as $o): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/orders/{$o['id']}") ?>"><?= e($o['order_no']) ?></a><span><?= sale_badge('order', $o['status']) ?> <?= e(money($o['total'])) ?></span></li><?php endforeach; ?>
    <?php if (!$orders): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
  <div class="col-md-6" id="invoices"><div class="card"><div class="card-header">Invoices</div><ul class="list-group list-group-flush">
    <?php foreach ($invoices as $i): $st = App\Models\Purchase::payStatus($i); ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/invoices/{$i['id']}") ?>"><?= e($i['invoice_no']) ?></a><span><?= pay_badge($st) ?> <?= e(money(App\Models\Purchase::outstanding($i))) ?> due</span></li><?php endforeach; ?>
    <?php if (!$invoices): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
</div>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'customer', 'row' => $c, 'name' => $c['name'], 'back' => 'customers', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => ($c['is_walkin'] ? '<span class="badge text-bg-info">Walk-in</span> ' : '') . ($c['is_active'] ?? 1 ? '' : '<span class="badge text-bg-dark">Inactive</span>'),
    'related' => ['balance' => 'Balance', 'orders' => 'Orders', 'invoices' => 'Invoices'],
    'actions' => (can('customers.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("customers/{$c['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('sales.create') ? '<li><a class="dropdown-item" href="' . url('sales/orders/create?customer=' . (int)$c['id']) . '"><i class="bi bi-bag-plus me-2 text-muted"></i>New order</a></li>' : '')
        . (can('customers.delete') && !$c['is_walkin'] ? '<li><form method="post" action="' . url("customers/{$c['id']}/delete") . '" onsubmit="return confirm(\'Delete this customer?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
