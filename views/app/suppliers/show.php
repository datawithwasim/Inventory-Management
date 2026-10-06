<?php ob_start(); ?>
<div class="card mb-3" id="balance"><div class="card-body">
  <div class="text-muted small">We owe this <?= e(term('supplier', true)) ?></div><div class="fs-2 <?= $outstanding > 0.004 ? 'text-danger' : '' ?>"><?= e(money($outstanding)) ?></div>
  <div class="text-muted small">after returns and payments, across all bills</div>
  <?php if ((float)$s['credit_limit'] > 0): $over = $outstanding > (float)$s['credit_limit']; ?><div class="mt-2 small <?= $over ? 'text-danger fw-semibold' : 'text-muted' ?>"><i class="bi bi-<?= $over ? 'exclamation-triangle' : 'speedometer2' ?> me-1"></i>Credit limit <?= e(money($s['credit_limit'])) ?><?= $over ? ' — exceeded by ' . e(money($outstanding - (float)$s['credit_limit'])) : '' ?></div><?php endif; ?>
</div></div>
<?php if ($contacts): ?><div class="card mb-3" id="contacts"><div class="card-header">Contact people</div><ul class="list-group list-group-flush">
  <?php foreach ($contacts as $c): ?><li class="list-group-item"><b><?= e($c['name']) ?></b><?= $c['role'] ? ' <span class="text-muted">· ' . e($c['role']) . '</span>' : '' ?><div class="small text-muted"><?= e(implode(' · ', array_filter([$c['phone'], $c['email']]))) ?></div></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php require __DIR__ . '/_rates.php'; ?>
<div class="row g-3 mb-3">
  <div class="col-md-6" id="orders"><div class="card"><div class="card-header">Recent purchase orders</div><ul class="list-group list-group-flush">
    <?php foreach ($pos as $p): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/orders/{$p['id']}") ?>"><?= e($p['po_no']) ?></a><span><?= po_badge($p['status']) ?> <?= e(money($p['total'])) ?></span></li><?php endforeach; ?>
    <?php if (!$pos): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
  <div class="col-md-6" id="bills"><div class="card"><div class="card-header">Bills</div><ul class="list-group list-group-flush">
    <?php foreach ($bills as $b): $st = App\Models\Purchase::payStatus($b); ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/bills/{$b['id']}") ?>"><?= e($b['bill_no']) ?></a><span><?= pay_badge($st) ?> <?= e(money(App\Models\Purchase::outstanding($b))) ?> due</span></li><?php endforeach; ?>
    <?php if (!$bills): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
</div>
<?php
$body = ob_get_clean();
$rec = ['entity' => 'supplier', 'row' => $s, 'name' => $s['name'], 'back' => 'suppliers', 'cfValues' => $cfValues, 'body' => $body,
    'badges' => ($s['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span> ') . ($s['supplier_type'] ? '<span class="badge text-bg-secondary">' . e(App\Models\Purchase::SUPPLIER_TYPES[$s['supplier_type']] ?? '') . '</span>' : ''),
    'related' => ['balance' => 'What we owe', 'rates' => 'Rate list', 'orders' => 'Purchase orders', 'bills' => 'Bills'],
    'actions' => (can('suppliers.edit') ? '<a class="btn btn-sm btn-primary" href="' . url("suppliers/{$s['id']}/edit") . '">Edit</a> ' : ''),
    'menu' => (can('purchase.create') ? '<li><a class="dropdown-item" href="' . url('purchase/orders/create?supplier=' . (int)$s['id']) . '"><i class="bi bi-cart-plus me-2 text-muted"></i>New purchase order</a></li>' : '')
        . (can('suppliers.delete') ? '<li><form method="post" action="' . url("suppliers/{$s['id']}/delete") . '" onsubmit="return confirm(\'Delete this supplier?\')">' . csrf_field() . '<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>' : '')];
require dirname(__DIR__) . '/_record.php';
