
<?php ob_start(); ?><?php if (can('purchase.edit')): ?><a class="btn btn-outline-primary" href="<?= url("purchase/bills/{$b['id']}/edit") ?>">Edit details</a><?php endif; ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="totals" style="max-width:460px"><div class="card-body">
    <table class="table table-sm table-borderless mb-0">
      <tr><td>Goods</td><td class="text-end"><?= e(money($b['subtotal'])) ?></td></tr><tr><td>Tax</td><td class="text-end"><?= e(money($b['tax_total'])) ?></td></tr>
      <?php if ((float)$b['other_charges'] > 0): ?><tr><td>Other charges</td><td class="text-end"><?= e(money($b['other_charges'])) ?></td></tr><?php endif; ?>
      <tr class="border-top"><th>Total</th><th class="text-end"><?= e(money($b['total'])) ?></th></tr>
      <?php if ((float)$b['returned_amount'] > 0): ?><tr><td>Returned goods</td><td class="text-end">− <?= e(money($b['returned_amount'])) ?></td></tr><?php endif; ?>
      <tr><td>Paid</td><td class="text-end">− <?= e(money($b['paid_amount'])) ?></td></tr>
      <tr class="border-top"><th><?= $due < -0.004 ? 'Credit with supplier' : 'Balance due' ?></th><th class="text-end <?= $due > 0.004 ? 'text-danger' : 'text-success' ?>"><?= e(money(abs($due))) ?></th></tr>
    </table></div></div></div>
<?php if ($items): ?><div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0"><thead><tr><th><?= e(term('item')) ?></th><th class="text-end">Qty</th><th class="text-end">Price</th><th class="text-end">Tax %</th><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $l): [, , $tot] = App\Models\Purchase::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><td class="text-end"><?= e(qty($l['tax_rate'])) ?></td><td class="text-end"><?= e(money($tot)) ?></td></tr>
  <?php endforeach; ?></tbody></table></div></div><?php endif; ?>
<div class="row g-3" id="payments">
  <div class="col-lg-7"><div class="card"><div class="card-header">Payments</div><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>
    <?php foreach ($payments as $p): ?><tr><td><?= e(fdate($p['paid_on'])) ?></td><td><?= e(App\Models\Purchase::METHODS[$p['method']] ?? $p['method']) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></td>
      <td class="text-muted small"><?= e($p['note'] ?? '') ?></td><td class="text-end"><?= e(money($p['amount'])) ?></td>
      <td class="text-end"><?php if (can('purchase.delete')): ?><form method="post" action="<?= url("purchase/bills/{$b['id']}/payments/{$p['id']}/delete") ?>" onsubmit="return confirm('Remove this payment?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">×</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td class="text-muted">No payments yet.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php if ($returns): ?><div class="card mt-3"><div class="card-header">Returns against this bill</div><ul class="list-group list-group-flush"><?php foreach ($returns as $r): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("purchase/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a><span><?= e(fdate($r['return_date'])) ?> · <?= e(money($r['total'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?></div>
  <?php if ($due > 0.004 && can('purchase.create')): ?>
  <div class="col-lg-5"><div class="card"><div class="card-header">Record a payment</div><div class="card-body">
    <form method="post" action="<?= url("purchase/bills/{$b['id']}/payments") ?>"><?= csrf_field() ?><div class="row g-2">
      <div class="col-6"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e(number_format($due, 2, '.', '')) ?>" required></div>
      <div class="col-6"><label class="form-label">Date</label><input type="date" name="paid_on" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-6"><label class="form-label">Method</label><select name="method" class="form-select"><?php foreach (App\Models\Purchase::METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label">Reference</label><input name="reference" class="form-control" maxlength="80" placeholder="UTR / cheque no."></div>
      <div class="col-12"><input name="note" class="form-control" maxlength="150" placeholder="Note (optional)"></div>
      <div class="col-12"><button class="btn btn-success w-100">Record payment</button></div></div></form></div></div></div>
  <?php endif; ?>
</div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'bill', 'row' => $b, 'name' => $b['bill_no'], 'back' => 'purchase/bills', 'body' => $body, 'actions' => $actions, 'badges' => pay_badge($status),
    'related' => array_filter(['totals' => 'Amounts', 'items' => $items ? 'Items' : null, 'payments' => 'Payments']),
    'facts' => [[term('supplier'), '<a href="' . url('suppliers/' . (int)$b['supplier_id']) . '">' . e($b['supplier']) . '</a>'],
        ['Goods receipt', $b['grn_id'] ? '<a href="' . url('purchase/grns/' . (int)$b['grn_id']) . '">' . e($b['grn_no']) . '</a>' : ''], ['Balance due', e(money(max(0, $due)))]]];
require dirname(__DIR__) . '/_record.php';
