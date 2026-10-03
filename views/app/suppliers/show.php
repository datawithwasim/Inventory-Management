<div class="row g-3 mb-3">
  <div class="col-md-5"><div class="card"><div class="card-body">
    <?php foreach (['contact_person' => 'Contact', 'phone' => 'Phone', 'email' => 'Email', 'address' => 'Address', 'tax_no' => 'Tax no.', 'notes' => 'Notes'] as $k => $l): if (!empty($s[$k])): ?>
      <div class="small text-muted"><?= $l ?></div><div class="mb-1"><?= e($s[$k]) ?></div>
    <?php endif; endforeach; ?>
    <div class="small text-muted">Payment terms</div><div><?= $s['payment_terms_days'] ? (int)$s['payment_terms_days'] . ' days' : 'Immediate' ?></div>
    <div class="mt-3">
      <?php if (can('suppliers.edit')): ?><a class="btn btn-sm btn-primary" href="<?= url("suppliers/{$s['id']}/edit") ?>">Edit</a><?php endif; ?>
      <?php if (can('purchase.create')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('purchase/orders/create?supplier=' . (int)$s['id']) ?>">New purchase order</a><?php endif; ?>
      <?php if (can('suppliers.delete')): ?><form class="d-inline" method="post" action="<?= url("suppliers/{$s['id']}/delete") ?>" onsubmit="return confirm('Delete this supplier?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?>
    </div>
  </div></div></div>
  <div class="col-md-7"><div class="card"><div class="card-body">
    <div class="text-muted small">We owe this supplier</div><div class="fs-2 <?= $outstanding > 0.004 ? 'text-danger' : '' ?>"><?= e(money($outstanding)) ?></div>
    <div class="text-muted small">after returns and payments, across all bills</div>
  </div></div></div>
</div>
<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-header">Recent purchase orders</div><ul class="list-group list-group-flush">
    <?php foreach ($pos as $p): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/orders/{$p['id']}") ?>"><?= e($p['po_no']) ?></a><span><?= po_badge($p['status']) ?> <?= e(money($p['total'])) ?></span></li><?php endforeach; ?>
    <?php if (!$pos): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header">Bills</div><ul class="list-group list-group-flush">
    <?php foreach ($bills as $b): $st = App\Models\Purchase::payStatus($b); ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/bills/{$b['id']}") ?>"><?= e($b['bill_no']) ?></a><span><?= pay_badge($st) ?> <?= e(money(App\Models\Purchase::outstanding($b))) ?> due</span></li><?php endforeach; ?>
    <?php if (!$bills): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
</div>
