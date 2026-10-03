<div class="row g-3 mb-3">
  <div class="col-lg-8"><div class="card h-100"><div class="card-body"><div class="row">
    <div class="col-md-4"><div class="text-muted small">Invoice</div><strong><?= e($i['invoice_no']) ?></strong> <?= pay_badge($status) ?></div>
    <div class="col-md-4"><div class="text-muted small">Customer</div><a href="<?= url('customers/' . (int)$i['customer_id']) ?>"><?= e($i['customer']) ?></a></div>
    <div class="col-md-4"><div class="text-muted small">Dates</div>Invoiced <?= e($i['invoice_date']) ?><br>Due <?= e($i['due_date'] ?? '—') ?></div></div>
    <div class="mt-2 small">
      <?php if ($i['delivery_id']): ?>Delivery <a href="<?= url('sales/deliveries/' . (int)$i['delivery_id']) ?>"><?= e($i['delivery_no']) ?></a><?php endif; ?>
      <?php if ($i['order_id']): ?> · Order <a href="<?= url('sales/orders/' . (int)$i['order_id']) ?>"><?= e($i['order_no']) ?></a><?php endif; ?></div>
    <?php if ($i['notes']): ?><div class="text-muted mt-1"><?= e($i['notes']) ?></div><?php endif; ?>
    <div class="mt-3 d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-primary" target="_blank" href="<?= url("sales/invoices/{$i['id']}/print") ?>"><i class="bi bi-printer"></i> Print invoice</a>
      <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= url("sales/invoices/{$i['id']}/print?receipt=1") ?>">Receipt</a>
      <?php if (can('sales.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("sales/invoices/{$i['id']}/edit") ?>">Edit details</a><?php endif; ?>
      <?php if (can('sales.create') && $i['delivery_id']): ?><a class="btn btn-sm btn-outline-danger" href="<?= url('sales/returns/create?invoice=' . (int)$i['id']) ?>">Customer return</a><?php endif; ?>
    </div></div></div></div>
  <div class="col-lg-4"><div class="card h-100"><div class="card-body"><table class="table table-sm table-borderless mb-0">
    <tr><td>Gross</td><td class="text-end"><?= e(money($i['subtotal'])) ?></td></tr>
    <?php if ((float)$i['discount_total'] > 0): ?><tr><td>Discount</td><td class="text-end">− <?= e(money($i['discount_total'])) ?></td></tr><?php endif; ?>
    <tr><td>Tax</td><td class="text-end"><?= e(money($i['tax_total'])) ?></td></tr>
    <?php if ((float)$i['delivery_charge'] > 0): ?><tr><td>Delivery</td><td class="text-end"><?= e(money($i['delivery_charge'])) ?></td></tr><?php endif; ?>
    <?php if ((float)$i['installation_charge'] > 0): ?><tr><td>Installation</td><td class="text-end"><?= e(money($i['installation_charge'])) ?></td></tr><?php endif; ?>
    <tr class="border-top"><th>Total</th><th class="text-end"><?= e(money($i['total'])) ?></th></tr>
    <?php if ((float)$i['returned_amount'] > 0): ?><tr><td>Returns credited</td><td class="text-end">− <?= e(money($i['returned_amount'])) ?></td></tr><?php endif; ?>
    <tr><td>Received</td><td class="text-end">− <?= e(money($i['paid_amount'])) ?></td></tr>
    <tr class="border-top"><th><?= $due < -0.004 ? 'Credit due to customer' : 'Balance due' ?></th><th class="text-end <?= $due > 0.004 ? 'text-danger' : 'text-success' ?>"><?= e(money(abs($due))) ?></th></tr></table></div></div></div>
</div>
<?php if ($items): ?><div class="card mb-3"><div class="table-responsive"><?php require __DIR__ . '/_delivery_rows.php'; ?></div></div><?php endif; ?>
<div class="row g-3">
  <div class="col-lg-7"><div class="card"><div class="card-header">Payments received</div><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><tbody>
    <?php foreach ($payments as $p): ?><tr><td><?= e($p['paid_on']) ?></td><td><?= e(App\Models\Purchase::METHODS[$p['method']] ?? ($p['method'] === 'advance' ? 'Advance' : $p['method'])) ?><?= $p['reference'] ? ' · ' . e($p['reference']) : '' ?></td><td class="text-muted small"><?= e($p['note'] ?? '') ?></td><td class="text-end"><?= e(money($p['amount'])) ?></td>
      <td class="text-end"><?php if (can('sales.delete')): ?><form method="post" action="<?= url("sales/invoices/{$i['id']}/payments/{$p['id']}/delete") ?>" onsubmit="return confirm('Remove this payment?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">×</button></form><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$payments): ?><tr><td class="text-muted">No payments yet.</td></tr><?php endif; ?></tbody></table></div></div>
    <?php if ($returns): ?><div class="card mt-3"><div class="card-header">Returns</div><ul class="list-group list-group-flush"><?php foreach ($returns as $r): ?><li class="list-group-item d-flex justify-content-between"><a href="<?= url("sales/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a><span><?= e($r['return_date']) ?> · <?= e(money($r['total'])) ?></span></li><?php endforeach; ?></ul></div><?php endif; ?></div>
  <?php if ($due > 0.004 && can('sales.create')): ?><div class="col-lg-5"><div class="card"><div class="card-header">Record a payment</div><div class="card-body">
    <form method="post" action="<?= url("sales/invoices/{$i['id']}/payments") ?>"><?= csrf_field() ?><div class="row g-2">
      <div class="col-6"><label class="form-label">Amount</label><input type="number" step="0.01" min="0.01" name="amount" class="form-control" value="<?= e(number_format($due, 2, '.', '')) ?>" required></div>
      <div class="col-6"><label class="form-label">Date</label><input type="date" name="paid_on" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-6"><label class="form-label">Method</label><select name="method" class="form-select"><?php foreach (App\Models\Purchase::METHODS as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label">Reference</label><input name="reference" class="form-control" maxlength="80" placeholder="UTR / cheque no."></div>
      <div class="col-12"><input name="note" class="form-control" maxlength="150" placeholder="Note (optional)"></div>
      <div class="col-12"><button class="btn btn-success w-100">Record payment</button></div></div></form></div></div></div><?php endif; ?>
</div>
