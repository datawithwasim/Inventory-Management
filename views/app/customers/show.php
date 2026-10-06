<?php ob_start(); ?>
<div class="card mb-3" id="balance"><div class="card-body"><div class="row">
  <div class="col-6"><div class="text-muted small">Owes us</div><div class="fs-2 <?= $owed > 0.004 ? 'text-danger' : '' ?>"><?= e(money($owed)) ?></div><div class="text-muted small">after returns and payments</div></div>
  <div class="col-6"><div class="text-muted small">Advance held</div><div class="fs-2"><?= e(money($advance)) ?></div><div class="text-muted small">taken on orders, not yet adjusted</div></div></div></div></div>
<?php
$actId = 'orders'; $actTitle = 'Sales activity';
$actTabs = [
  ['id' => 'orders', 'label' => 'Orders', 'head' => ['Order', 'Date', 'Status', 'Total'], 'empty' => 'No orders yet.', 'all' => null, 'rows' => array_map(fn($o) => [
      '<a class="fw-medium" href="' . url("sales/orders/{$o['id']}") . '">' . e($o['order_no']) . '</a>', e(fdate($o['order_date'])), sale_badge('order', $o['status']), e(money($o['total']))], $orders)],
  ['id' => 'invoices', 'label' => 'Invoices', 'head' => ['Invoice', 'Date', 'Status', 'Balance due'], 'empty' => 'No invoices yet.', 'all' => null, 'rows' => array_map(fn($i) => [
      '<a class="fw-medium" href="' . url("sales/invoices/{$i['id']}") . '">' . e($i['invoice_no']) . '</a>', e(fdate($i['invoice_date'])), pay_badge(App\Models\Purchase::payStatus($i)), e(money(max(0, App\Models\Purchase::outstanding($i))))], $invoices)],
];
require dirname(__DIR__) . '/_activity.php';
?>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'customer', 'row' => $c, 'name' => $c['name'], 'back' => 'customers', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => ($c['is_walkin'] ? '<span class="badge text-bg-info">Walk-in</span> ' : '') . ($c['is_active'] ?? 1 ? '' : '<span class="badge text-bg-dark">Inactive</span>'),
    'related' => ['balance' => 'Balance', 'orders' => 'Sales activity'],
    'actions' => (can('customers.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("customers/{$c['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('sales.create') ? '<li><a class="dropdown-item" href="' . url('sales/orders/create?customer=' . (int)$c['id']) . '"><i class="bi bi-bag-plus me-2 text-muted"></i>New order</a></li>' : '')
        . (can('customers.delete') && !$c['is_walkin'] ? '<li><form method="post" action="' . url("customers/{$c['id']}/delete") . '" onsubmit="return confirm(\'Delete this customer?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
