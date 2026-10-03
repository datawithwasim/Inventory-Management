<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("purchase/orders/{$po['id']}") ?>">Back</a><a class="btn btn-outline-secondary btn-sm" href="<?= url('settings/templates?doc=purchase_order') ?>">Change this layout</a></div>
<?php $docTitle = 'Purchase order'; $docNo = $po['po_no']; $docDate = $po['order_date']; $meta = !empty($tpl['show_expected']) ? ['Expected' => fdate($po['expected_date'] ?? null)] : []; require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="row mb-3"><div class="col-6"><div class="small text-muted"><?= e(term('supplier')) ?></div><strong><?= e($po['supplier']) ?></strong>
    <?php foreach (['sup_address', 'sup_phone', 'sup_tax'] as $k) if (!empty($po[$k])) echo '<div class="small">' . ($k === 'sup_tax' ? 'Tax no. ' : '') . e($po[$k]) . '</div>'; ?></div>
  <div class="col-6"><div class="small text-muted">Deliver to</div><strong><?= e($po['warehouse']) ?></strong></div></div>
<table class="table table-sm align-middle doc-table"><thead><tr><th>Item</th><th class="text-end">Qty</th><th class="text-end">Price</th><?= !empty($tpl['show_tax']) ? '<th class="text-end">Tax %</th>' : '' ?><th class="text-end">Amount</th></tr></thead><tbody>
  <?php foreach ($items as $l): [$net, , $tot] = App\Models\Purchase::line((float)$l['qty_ordered'], (float)$l['unit_price'], (float)$l['tax_rate']); ?>
    <tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td><td class="text-end"><?= e(qty($l['qty_ordered'])) ?> <?= e($l['unit']) ?></td>
      <td class="text-end"><?= e(money($l['unit_price'])) ?></td><?= !empty($tpl['show_tax']) ? '<td class="text-end">' . e(qty($l['tax_rate'])) . '</td>' : '' ?><td class="text-end"><?= e(money(!empty($tpl['show_tax']) ? $net : $tot)) ?></td></tr>
  <?php endforeach; ?></tbody></table>
<div class="row justify-content-end"><div class="col-md-5"><table class="table table-sm table-borderless">
  <tr><td>Subtotal</td><td class="text-end"><?= e(money($po['subtotal'])) ?></td></tr><tr><td>Tax</td><td class="text-end"><?= e(money($po['tax_total'])) ?></td></tr>
  <tr class="border-top"><th>Total</th><th class="text-end"><?= e(money($po['total'])) ?></th></tr></table></div></div>
<?php $notes = $po['notes']; $showBank = !empty($tpl['show_bank']); require dirname(__DIR__) . '/print/_foot.php'; ?>
