<div class="row g-3 mb-3">
  <div class="col-md-5"><div class="card"><div class="card-body">
    <?php foreach (['group_name' => 'Group', 'contact_person' => 'Contact', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'ship_address' => 'Delivery address', 'tax_no' => 'Tax no.', 'notes' => 'Notes'] as $k => $l): if (!empty($c[$k])): ?>
      <div class="small text-muted"><?= $l ?></div><div class="mb-1"><?= e($c[$k]) ?></div><?php endif; endforeach; ?>
    <div class="small text-muted">Credit</div><div><?= $c['credit_days'] ? (int)$c['credit_days'] . ' days' : 'Immediate' ?></div>
    <div class="mt-3">
      <?php if (can('customers.edit')): ?><a class="btn btn-sm btn-primary" href="<?= url("customers/{$c['id']}/edit") ?>">Edit</a><?php endif; ?>
      <?php if (can('sales.create')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('sales/orders/create?customer=' . (int)$c['id']) ?>">New order</a>
        <a class="btn btn-sm btn-outline-primary" href="<?= url('sales/quotations/create?customer=' . (int)$c['id']) ?>">New quotation</a><?php endif; ?>
      <?php if (can('customers.delete') && !$c['is_walkin']): ?><form class="d-inline" method="post" action="<?= url("customers/{$c['id']}/delete") ?>" onsubmit="return confirm('Delete this customer?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?>
    </div></div></div></div>
  <div class="col-md-7"><div class="card"><div class="card-body"><div class="row">
    <div class="col-6"><div class="text-muted small">Owes us</div><div class="fs-2 <?= $owed > 0.004 ? 'text-danger' : '' ?>"><?= e(money($owed)) ?></div><div class="text-muted small">after returns and payments</div></div>
    <div class="col-6"><div class="text-muted small">Advance held</div><div class="fs-2"><?= e(money($advance)) ?></div><div class="text-muted small">taken on orders, not yet adjusted</div></div></div></div></div></div>
</div>
<?php require dirname(__DIR__) . '/settings/_cf_show.php'; ?>
<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-header">Recent orders</div><ul class="list-group list-group-flush">
    <?php foreach ($orders as $o): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/orders/{$o['id']}") ?>"><?= e($o['order_no']) ?></a><span><?= sale_badge('order', $o['status']) ?> <?= e(money($o['total'])) ?></span></li><?php endforeach; ?>
    <?php if (!$orders): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header">Invoices</div><ul class="list-group list-group-flush">
    <?php foreach ($invoices as $i): $st = App\Models\Purchase::payStatus($i); ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/invoices/{$i['id']}") ?>"><?= e($i['invoice_no']) ?></a><span><?= pay_badge($st) ?> <?= e(money(App\Models\Purchase::outstanding($i))) ?> due</span></li><?php endforeach; ?>
    <?php if (!$invoices): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
</div>
