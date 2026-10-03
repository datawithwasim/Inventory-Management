<?php $st = $q['status']; ?>
<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Quotation</div><strong><?= e($q['quote_no']) ?></strong> <?= sale_badge('quote', $st) ?></div>
  <div class="col-md-3"><div class="text-muted small">Customer</div><a href="<?= url('customers/' . (int)$q['customer_id']) ?>"><?= e($q['customer']) ?></a></div>
  <div class="col-md-3"><div class="text-muted small">Date</div><?= e($q['quote_date']) ?></div>
  <div class="col-md-3"><div class="text-muted small">Valid until</div><?= e($q['valid_until'] ?? '—') ?></div></div>
  <?php if ($q['notes']): ?><div class="text-muted mt-2"><?= e($q['notes']) ?></div><?php endif; ?>
  <?php if ($q['order_id']): ?><div class="mt-2">Order <a href="<?= url('sales/orders/' . (int)$q['order_id']) ?>"><?= e($q['order_no']) ?></a></div><?php endif; ?>
  <div class="mt-3 d-flex flex-wrap gap-2">
    <?php if (in_array($st, ['draft', 'sent'], true) && can('sales.edit')): ?><a class="btn btn-outline-primary" href="<?= url("sales/quotations/{$q['id']}/edit") ?>">Edit</a><?php endif; ?>
    <?php if ($st === 'draft' && can('sales.edit')): ?><form method="post" action="<?= url("sales/quotations/{$q['id']}/send") ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary">Mark as sent</button></form><?php endif; ?>
    <?php if (in_array($st, ['draft', 'sent'], true) && can('sales.edit')): ?><form method="post" action="<?= url("sales/quotations/{$q['id']}/accept") ?>"><?= csrf_field() ?><button class="btn btn-outline-success">Customer accepted</button></form><?php endif; ?>
    <?php if (in_array($st, ['draft', 'sent', 'accepted'], true) && can('sales.edit')): ?><form method="post" action="<?= url("sales/quotations/{$q['id']}/reject") ?>"><?= csrf_field() ?><button class="btn btn-outline-danger">Rejected</button></form><?php endif; ?>
    <?php if ($st === 'draft' && can('sales.delete')): ?><form method="post" action="<?= url("sales/quotations/{$q['id']}/delete") ?>" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete</button></form><?php endif; ?>
  </div>
  <?php if (in_array($st, ['draft', 'sent', 'accepted'], true) && can('sales.create')): ?>
    <form class="row g-2 mt-2" method="post" action="<?= url("sales/quotations/{$q['id']}/convert") ?>" style="max-width:520px"><?= csrf_field() ?>
      <div class="col-7"><select name="warehouse_id" class="form-select"><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
      <div class="col-5"><button class="btn btn-success w-100">Create sales order</button></div></form><?php endif; ?>
</div></div>
<?php $doc = $q; $qtyKey = 'qty'; require __DIR__ . '/_doc_lines.php'; ?>
