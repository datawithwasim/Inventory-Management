<?php ob_start(); ?>
<div class="card mb-3" id="balance"><div class="card-body">
  <div class="text-muted small">We owe this <?= e(term('supplier', true)) ?></div><div class="fs-2 <?= $outstanding > 0.004 ? 'text-danger' : '' ?>"><?= e(money($outstanding)) ?></div>
  <div class="text-muted small">after returns and payments, across all bills</div>
  <?php if ((float)$s['credit_limit'] > 0): $over = $outstanding > (float)$s['credit_limit']; ?><div class="mt-2 small <?= $over ? 'text-danger fw-semibold' : 'text-muted' ?>"><i class="bi bi-<?= $over ? 'exclamation-triangle' : 'speedometer2' ?> me-1"></i>Credit limit <?= e(money($s['credit_limit'])) ?><?= $over ? ' — exceeded by ' . e(money($outstanding - (float)$s['credit_limit'])) : '' ?></div><?php endif; ?>
</div></div>
<?php if ($contacts): ?><div class="card mb-3" id="contacts"><div class="card-header">Contact people</div><ul class="list-group list-group-flush">
  <?php foreach ($contacts as $c): ?><li class="list-group-item"><b><?= e($c['name']) ?></b><?= $c['role'] ? ' <span class="text-muted">· ' . e($c['role']) . '</span>' : '' ?><div class="small text-muted"><?= e(implode(' · ', array_filter([$c['phone'], $c['email']]))) ?></div></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__ . '/_products.php'; ?>
<?php require __DIR__ . '/_rates.php'; ?>
<?php
$actTabs = [
  ['id' => 'orders', 'label' => 'Purchase orders', 'head' => ['Order', 'Date', 'Status', 'Total'], 'empty' => 'No purchase orders yet.', 'all' => null, 'new' => can('purchase.create') ? [url('purchase/orders/create?supplier=' . (int)$s['id']), 'New purchase order'] : null, 'rows' => array_map(fn($p) => [
      '<a class="fw-medium" href="' . url("purchase/orders/{$p['id']}") . '">' . e($p['po_no']) . '</a>', e(fdate($p['order_date'])), po_badge($p['status']), e(money($p['total']))], $pos)],
  ['id' => 'bills', 'label' => 'Bills', 'head' => ['Bill', 'Date', 'Status', 'Balance due'], 'empty' => 'No bills yet.', 'all' => null, 'rows' => array_map(fn($b) => [
      '<a class="fw-medium" href="' . url("purchase/bills/{$b['id']}") . '">' . e($b['bill_no']) . '</a>' . ($b['supplier_bill_no'] ? ' <span class="text-muted small">' . e($b['supplier_bill_no']) . '</span>' : ''), e(fdate($b['bill_date'])),
      pay_badge(App\Models\Purchase::payStatus($b)), e(money(max(0, App\Models\Purchase::outstanding($b))))], $bills)],
];
require dirname(__DIR__) . '/_activity.php';
?>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'supplier', 'row' => $s, 'name' => $s['name'], 'back' => 'suppliers', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => ($s['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span> ') . ($s['supplier_type'] ? '<span class="badge text-bg-secondary">' . e(App\Models\Purchase::SUPPLIER_TYPES[$s['supplier_type']] ?? '') . '</span>' : ''),
    'related' => ['balance' => 'What we owe', 'products' => 'Items supplied', 'rates' => 'Rate list', 'orders' => 'Purchase orders', 'bills' => 'Bills'],
    'actions' => (can('suppliers.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("suppliers/{$s['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('purchase.create') ? '<li><a class="dropdown-item" href="' . url('purchase/orders/create?supplier=' . (int)$s['id']) . '"><i class="bi bi-cart-plus me-2 text-muted"></i>New purchase order</a></li>' : '')
        . (can('suppliers.delete') ? '<li><form method="post" action="' . url("suppliers/{$s['id']}/delete") . '" onsubmit="return confirm(\'Delete this supplier?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
