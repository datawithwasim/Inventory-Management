<?php $st = $po['status']; $canReceive = in_array($st, ['approved', 'partial'], true) && $remaining > 0; ?>
<div class="card mb-3"><div class="card-body"><div class="row">
  <div class="col-md-3"><div class="text-muted small">Purchase order</div><strong><?= e($po['po_no']) ?></strong> <?= po_badge($st) ?></div>
  <div class="col-md-3"><div class="text-muted small">Supplier</div><a href="<?= url('suppliers/' . (int)$po['supplier_id']) ?>"><?= e($po['supplier']) ?></a></div>
  <div class="col-md-3"><div class="text-muted small">Deliver to</div><?= e($po['warehouse']) ?></div>
  <div class="col-md-3"><div class="text-muted small">Dates</div>Ordered <?= e($po['order_date']) ?><?= $po['expected_date'] ? '<br>Expected ' . e($po['expected_date']) : '' ?></div>
</div>
<?php if ($po['notes']): ?><div class="text-muted mt-2"><?= e($po['notes']) ?></div><?php endif; ?>
<div class="small text-muted mt-2">Created by <?= e($po['created_name'] ?? '—') ?><?= $po['approved_name'] ? ' · approved by ' . e($po['approved_name']) . ' on ' . e(substr((string)$po['approved_at'], 0, 10)) : '' ?></div>
<div class="mt-3 d-flex flex-wrap gap-2">
  <?php if ($st === 'draft' && can('purchase.edit')): ?><a class="btn btn-outline-primary" href="<?= url("purchase/orders/{$po['id']}/edit") ?>">Edit</a><?php endif; ?>
  <?php if ($st === 'draft' && can('purchase.create')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/submit") ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= $approval ? 'Submit for approval' : 'Confirm order' ?></button></form><?php endif; ?>
  <?php if ($st === 'pending_approval' && can('purchase.approve')): ?>
    <form method="post" action="<?= url("purchase/orders/{$po['id']}/approve") ?>"><?= csrf_field() ?><button class="btn btn-success">Approve</button></form>
    <form method="post" action="<?= url("purchase/orders/{$po['id']}/reject") ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary">Send back to draft</button></form>
  <?php endif; ?>
  <?php if ($canReceive && can('purchase.create')): ?><a class="btn btn-success" href="<?= url("purchase/orders/{$po['id']}/receive") ?>"><i class="bi bi-box-arrow-in-down"></i> Receive goods</a><?php endif; ?>
  <?php if ($st === 'partial' && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/close") ?>" onsubmit="return confirm('Close this order? Nothing more can be received against it.')"><?= csrf_field() ?><button class="btn btn-outline-dark">Close order</button></form><?php endif; ?>
  <?php if (in_array($st, ['draft', 'pending_approval', 'approved'], true) && !$grns && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/cancel") ?>" onsubmit="return confirm('Cancel this purchase order?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Cancel order</button></form><?php endif; ?>
  <?php if ($st === 'draft' && can('purchase.delete')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/delete") ?>" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete draft</button></form><?php endif; ?>
</div></div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th>Item</th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Still due</th><th class="text-end">Price</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr></thead><tbody>
  <?php foreach ($items as $l): [, , $tot] = App\Models\Purchase::line((float)$l['qty_ordered'], (float)$l['unit_price'], (float)$l['tax_rate']); $due = max(0, (float)$l['qty_ordered'] - (float)$l['qty_received']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td class="text-end"><?= e(qty($l['qty_ordered'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(qty($l['qty_received'])) ?></td><td class="text-end"><?= e(qty($due)) ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(qty($l['tax_rate'])) ?></td><td class="text-end"><?= e(money($tot)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="6" class="text-end text-muted">Subtotal</td><td class="text-end"><?= e(money($po['subtotal'])) ?></td></tr>
    <tr><td colspan="6" class="text-end text-muted">Tax</td><td class="text-end"><?= e(money($po['tax_total'])) ?></td></tr>
    <tr><th colspan="6" class="text-end">Total</th><th class="text-end"><?= e(money($po['total'])) ?></th></tr></tfoot></table></div></div>
<?php if ($grns): ?><div class="card"><div class="card-header">Goods received against this order</div><ul class="list-group list-group-flush">
  <?php foreach ($grns as $g): ?><li class="list-group-item"><a href="<?= url("purchase/grns/{$g['id']}") ?>"><?= e($g['grn_no']) ?></a> <span class="text-muted">· <?= e($g['received_date']) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
