<div class="row g-3 mb-3">
  <div class="col-md-5"><div class="card"><div class="card-body">
    <?php if (!empty($s['supplier_type'])): ?><span class="badge text-bg-secondary mb-2"><?= e(App\Models\Purchase::SUPPLIER_TYPES[$s['supplier_type']] ?? $s['supplier_type']) ?></span><?php endif; ?>
    <?php $place = implode(', ', array_filter([$s['address'] ?? '', $s['city'] ?? '', $s['state'] ?? '', $s['pincode'] ?? ''])); ?>
    <?php foreach (['contact_person' => 'Contact', 'phone' => 'Phone', 'email' => 'Email'] as $k => $l): if (!empty($s[$k])): ?>
      <div class="small text-muted"><?= $l ?></div><div class="mb-1"><?= e($s[$k]) ?></div>
    <?php endif; endforeach; ?>
    <?php foreach ($contacts as $c): ?><div class="small text-muted"><?= e($c['role'] ?: 'Contact') ?></div><div class="mb-1"><?= e($c['name']) ?><?= $c['phone'] ? ' · ' . e($c['phone']) : '' ?><?= $c['email'] ? ' · ' . e($c['email']) : '' ?></div><?php endforeach; ?>
    <?php if ($place): ?><div class="small text-muted">Address</div><div class="mb-1"><?= e($place) ?></div><?php endif; ?>
    <?php foreach (['ship_address' => 'Dispatch address', 'tax_no' => 'GSTIN', 'pan' => 'PAN', 'transport' => 'Preferred transport', 'notes' => 'Notes'] as $k => $l): if (!empty($s[$k])): ?>
      <div class="small text-muted"><?= $l ?></div><div class="mb-1"><?= e($s[$k]) ?></div>
    <?php endif; endforeach; ?>
    <?php if (!empty($s['bank_name']) || !empty($s['bank_account'])): ?><div class="small text-muted">Bank</div><div class="mb-1"><?= e(implode(' · ', array_filter([$s['bank_name'], $s['bank_account'], $s['bank_ifsc']]))) ?></div><?php endif; ?>
    <div class="small text-muted">Payment terms</div><div><?= $s['payment_terms_days'] ? (int)$s['payment_terms_days'] . ' days' : 'Immediate' ?><?= (int)$s['lead_time_days'] ? ' · delivers in ' . (int)$s['lead_time_days'] . ' days' : '' ?></div>
    <div class="mt-3">
      <?php if (can('suppliers.edit')): ?><a class="btn btn-sm btn-primary" href="<?= url("suppliers/{$s['id']}/edit") ?>">Edit</a><?php endif; ?>
      <?php if (can('purchase.create')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url('purchase/orders/create?supplier=' . (int)$s['id']) ?>">New purchase order</a><?php endif; ?>
      <?php if (can('suppliers.delete')): ?><form class="d-inline" method="post" action="<?= url("suppliers/{$s['id']}/delete") ?>" onsubmit="return confirm('Delete this supplier?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form><?php endif; ?>
    </div>
  </div></div></div>
  <div class="col-md-7"><div class="card"><div class="card-body">
    <div class="text-muted small">We owe this <?= e(term('supplier', true)) ?></div><div class="fs-2 <?= $outstanding > 0.004 ? 'text-danger' : '' ?>"><?= e(money($outstanding)) ?></div>
    <div class="text-muted small">after returns and payments, across all bills</div>
    <?php if ((float)$s['credit_limit'] > 0): $over = $outstanding > (float)$s['credit_limit']; ?><div class="mt-2 small <?= $over ? 'text-danger fw-semibold' : 'text-muted' ?>"><i class="bi bi-<?= $over ? 'exclamation-triangle' : 'speedometer2' ?> me-1"></i>Credit limit <?= e(money($s['credit_limit'])) ?><?= $over ? ' — exceeded by ' . e(money($outstanding - (float)$s['credit_limit'])) : '' ?></div><?php endif; ?>
  </div></div></div>
</div>
<?php require dirname(__DIR__) . '/settings/_cf_show.php'; ?>
<?php require __DIR__ . '/_rates.php'; ?>
<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-header">Recent purchase orders</div><ul class="list-group list-group-flush">
    <?php foreach ($pos as $p): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/orders/{$p['id']}") ?>"><?= e($p['po_no']) ?></a><span><?= po_badge($p['status']) ?> <?= e(money($p['total'])) ?></span></li><?php endforeach; ?>
    <?php if (!$pos): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
  <div class="col-md-6"><div class="card"><div class="card-header">Bills</div><ul class="list-group list-group-flush">
    <?php foreach ($bills as $b): $st = App\Models\Purchase::payStatus($b); ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/bills/{$b['id']}") ?>"><?= e($b['bill_no']) ?></a><span><?= pay_badge($st) ?> <?= e(money(App\Models\Purchase::outstanding($b))) ?> due</span></li><?php endforeach; ?>
    <?php if (!$bills): ?><li class="list-group-item text-muted">None yet.</li><?php endif; ?></ul></div></div>
</div>
