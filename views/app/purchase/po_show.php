<?php $st = $po['status']; $canReceive = in_array($st, ['approved', 'partial'], true) && $remaining > 0; ?>
<?php ob_start(); ?>
  <a class="btn btn-outline-secondary" target="_blank" href="<?= url("purchase/orders/{$po['id']}/print") ?>"><i class="bi bi-printer"></i> Print</a>
  <?php if ($st === 'draft' && can('purchase.edit')): ?><a class="btn btn-outline-primary" href="<?= url("purchase/orders/{$po['id']}/edit") ?>">Edit</a><?php endif; ?>
  <?php if ($st === 'draft' && can('purchase.create')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/submit") ?>"><?= csrf_field() ?><button class="btn btn-primary"><?= $approval ? 'Submit for approval' : 'Confirm order' ?></button></form><?php endif; ?>
  <?php if ($st === 'pending_approval' && can('purchase.approve')): ?>
    <form method="post" action="<?= url("purchase/orders/{$po['id']}/approve") ?>"><?= csrf_field() ?><button class="btn btn-success">Approve</button></form>
    <form method="post" action="<?= url("purchase/orders/{$po['id']}/reject") ?>"><?= csrf_field() ?><button class="btn btn-outline-secondary">Send back to draft</button></form>
  <?php endif; ?>
  <?php if ($canReceive && can('purchase.create')): ?><a class="btn btn-success" href="<?= url("purchase/orders/{$po['id']}/receive") ?>"><i class="bi bi-box-arrow-in-down"></i> Receive goods</a><?php endif; ?>
  <?php if ($st === 'partial' && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/close") ?>" onsubmit="return confirm('Close this order? Nothing more can be received against it.')"><?= csrf_field() ?><button class="btn btn-outline-dark">Close order</button></form><?php endif; ?>
  <?php if (in_array($st, ['draft', 'pending_approval', 'approved'], true) && !$grns && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/cancel") ?>" onsubmit="return confirm('Cancel this purchase order?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Cancel order</button></form><?php endif; ?>
  <?php if ($st === 'draft' && can('purchase.delete')): ?><form method="post" action="<?= url("purchase/orders/{$po['id']}/delete") ?>" onsubmit="return confirm('Delete this draft?')"><?= csrf_field() ?><button class="btn btn-outline-danger">Delete draft</button></form><?php endif; ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><th class="text-end">Ordered</th><th class="text-end">Received</th><th class="text-end">Still due</th><th class="text-end">Price</th><th class="text-end">Tax %</th><th class="text-end">Total</th></tr></thead><tbody>
  <?php foreach ($items as $l): [, , $tot] = App\Models\Purchase::line((float)$l['qty_ordered'], (float)$l['unit_price'], (float)$l['tax_rate']); $due = max(0, (float)$l['qty_ordered'] - (float)$l['qty_received']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td class="text-end"><?= e(qty($l['qty_ordered'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(qty($l['qty_received'])) ?></td><td class="text-end"><?= e(qty($due)) ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(qty($l['tax_rate'])) ?></td><td class="text-end"><?= e(money($tot)) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="6" class="text-end text-muted">Subtotal</td><td class="text-end"><?= e(money($po['subtotal'])) ?></td></tr>
    <tr><td colspan="6" class="text-end text-muted">Tax</td><td class="text-end"><?= e(money($po['tax_total'])) ?></td></tr>
    <tr><th colspan="6" class="text-end">Total</th><th class="text-end"><?= e(money($po['total'])) ?></th></tr></tfoot></table></div></div>
<?php if ($grns): ?><div class="card mb-3" id="grns"><div class="card-header">Goods received against this order</div><ul class="list-group list-group-flush">
  <?php foreach ($grns as $g): ?><li class="list-group-item"><a href="<?= url("purchase/grns/{$g['id']}") ?>"><?= e($g['grn_no']) ?></a> <span class="text-muted">· <?= e(fdate($g['received_date'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php $body = ob_get_clean();
$rec = ['entity' => 'purchase_order', 'row' => $po, 'name' => $po['po_no'], 'back' => 'purchase/orders', 'cfValues' => $cfValues ?? [], 'body' => $body, 'actions' => $actions, 'badges' => po_badge($st),
    'related' => array_filter(['items' => 'Items', 'grns' => $grns ? 'Goods received' : null]),
    'facts' => ['created_by' => ['Created by', e($po['created_name'] ?? '—')], 'approved_by' => ['Approved by', $po['approved_name'] ? e($po['approved_name']) . ' on ' . e(substr((string)$po['approved_at'], 0, 10)) : ''], 'total' => ['Total', e(money($po['total']))]]];
require dirname(__DIR__) . '/_record.php';
