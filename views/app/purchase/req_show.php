
<?php ob_start(); ?><?php if ($r['status'] === 'open' && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/requisitions/{$r['id']}/cancel") ?>" onsubmit="return confirm('Cancel this requisition?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Cancel requisition</button></form><?php endif; ?><?php $actions = ob_get_clean(); ?>
<?php ob_start(); ?>
<div class="card mb-3" id="items"><div class="table-responsive"><table class="table mb-0"><thead><tr><th><?= e(term('item')) ?></th><th>SKU</th><th class="text-end">Qty</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['sku']) ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td><?= e($l['note'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if ($r['status'] === 'open' && can('purchase.create')): ?>
<div class="card mb-3" id="convert" style="max-width:640px"><div class="card-header">Turn into a purchase order</div><div class="card-body">
  <form method="post" action="<?= url("purchase/requisitions/{$r['id']}/convert") ?>"><?= csrf_field() ?><div class="row g-2">
    <div class="col-md-5"><select name="supplier_id" class="form-select" required><option value=""><?= e(term('supplier')) ?>…</option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select name="warehouse_id" class="form-select"><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Create PO</button></div></div>
    <div class="form-text">A draft PO is created with each <?= e(term('item', true)) ?>'s default cost and tax. You can edit it before submitting.</div></form>
</div></div>
<?php endif; ?>
<?php $body = ob_get_clean();
$stc = ['open' => 'primary', 'converted' => 'success', 'cancelled' => 'secondary'][$r['status']];
$rec = ['entity' => 'requisition', 'row' => $r, 'name' => $r['req_no'], 'back' => 'purchase/requisitions', 'body' => $body, 'actions' => $actions,
    'badges' => '<span class="badge text-bg-' . $stc . '">' . e($r['status']) . '</span>', 'factsTitle' => 'Requisition details',
    'related' => array_filter(['items' => 'Items', 'convert' => ($r['status'] === 'open' && can('purchase.create')) ? 'Turn into PO' : null]),
    'facts' => ['raised' => ['Raised', e(fdate(substr((string)$r['created_at'], 0, 10)))], 'by' => ['By', e($r['user_name'] ?? '—')],
        'po' => ['Purchase order', $r['po_id'] ? '<a href="' . url('purchase/orders/' . (int)$r['po_id']) . '">' . e($r['po_no']) . '</a>' : '']]];
require dirname(__DIR__) . '/_record.php';
