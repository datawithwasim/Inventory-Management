
<?php ob_start(); ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0"><thead><tr><th><?= e(term('item')) ?></th><th>Roll</th><th>Back on</th><th class="text-end">Qty</th><th class="text-end">Credit</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['batch_no'] ?? '') ?></td>
    <td><?= $l['restock'] ? e($l['rack'] ?? 'No rack') : '<span class="badge text-bg-secondary">Not put back</span>' ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td class="text-end"><?= e(money($l['line_total'])) ?></td></tr><?php endforeach; ?>
  </tbody><tfoot><tr><th colspan="4" class="text-end">Total credit (incl. tax)</th><th class="text-end"><?= e(money($r['total'])) ?></th></tr></tfoot></table></div></div>
<?php $body = ob_get_clean();
$rec = ['entity' => 'sales_return', 'row' => $r, 'name' => $r['return_no'], 'back' => 'sales/returns', 'body' => $body, 'badges' => '', 'related' => ['items' => 'Items returned'],
    'facts' => ['customer' => [term('customer'), e($r['customer'])], 'invoice' => ['Invoice', '<a href="' . url('sales/invoices/' . (int)$r['invoice_id']) . '">' . e($r['invoice_no']) . '</a>'], 'by' => ['By', e($r['user_name'] ?? '—')], 'total' => ['Total credit (incl. tax)', e(money($r['total']))]]];
require dirname(__DIR__) . '/_record.php';
