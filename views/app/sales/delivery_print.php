<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("sales/deliveries/{$d['id']}") ?>">Back</a><a class="btn btn-outline-secondary btn-sm" href="<?= url('settings/templates?doc=delivery_note') ?>">Change this layout</a></div>
<?php $docTitle = 'Delivery note'; $docNo = $d['delivery_no']; $docDate = $d['delivery_date']; $meta = ['Order' => $d['order_no'] ?? null, 'Invoice' => $d['invoice_no'] ?? null]; require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="mb-3"><div class="small text-muted">Delivered to</div><strong><?= e($d['customer']) ?></strong>
  <?php if ($d['cust_phone']): ?><div><?= e($d['cust_phone']) ?></div><?php endif; ?>
  <?php if (!empty($tpl['show_ship_to'])): ?><div><?= e($d['ship_to'] ?: ($d['cust_address'] ?? '')) ?></div><?php endif; ?></div>
<table class="table table-sm align-middle doc-table"><thead><tr><th>Item</th><?= !empty($tpl['show_batch']) ? '<th>Roll</th>' : '' ?><?= !empty($tpl['show_rack']) ? '<th>Rack</th>' : '' ?><th class="text-end">Qty</th><?= !empty($tpl['show_prices']) ? '<th class="text-end">Amount</th>' : '' ?></tr></thead><tbody>
  <?php foreach ($items as $l): $part = $l['parent_id'] !== null; [, , , , $tot] = App\Models\Sales::line((float)$l['qty'], (float)$l['unit_price'], (float)$l['discount_pct'], (float)$l['tax_rate']); ?>
    <tr class="<?= $part ? 'small text-muted' : '' ?>"><td><?= $part ? '&nbsp;&nbsp;↳ ' : '' ?><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td>
      <?= !empty($tpl['show_batch']) ? '<td>' . e($l['batch_no'] ?? '') . '</td>' : '' ?><?= !empty($tpl['show_rack']) ? '<td>' . ($l['is_bundle'] ? '' : e($l['rack'] ?? '')) . '</td>' : '' ?>
      <td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><?= !empty($tpl['show_prices']) ? '<td class="text-end">' . ($part ? '' : e(money($tot))) . '</td>' : '' ?></tr>
  <?php endforeach; ?></tbody></table>
<?php $notes = $d['note']; require dirname(__DIR__) . '/print/_foot.php'; ?>
