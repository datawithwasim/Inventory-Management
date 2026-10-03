<div class="card mb-3"><div class="card-body d-flex justify-content-between">
  <div><strong><?= e($r['req_no']) ?></strong> · <?= e(substr($r['created_at'], 0, 10)) ?> · by <?= e($r['user_name'] ?? '—') ?>
    <span class="badge text-bg-<?= ['open' => 'primary', 'converted' => 'success', 'cancelled' => 'secondary'][$r['status']] ?> ms-2"><?= e($r['status']) ?></span>
    <?php if ($r['note']): ?><div class="text-muted"><?= e($r['note']) ?></div><?php endif; ?>
    <?php if ($r['po_id']): ?><div>Turned into <a href="<?= url('purchase/orders/' . (int)$r['po_id']) ?>"><?= e($r['po_no']) ?></a></div><?php endif; ?></div>
  <?php if ($r['status'] === 'open' && can('purchase.edit')): ?><form method="post" action="<?= url("purchase/requisitions/{$r['id']}/cancel") ?>" onsubmit="return confirm('Cancel this requisition?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Cancel requisition</button></form><?php endif; ?>
</div></div>
<div class="card mb-3"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Item</th><th>SKU</th><th class="text-end">Qty</th><th>Note</th></tr></thead><tbody>
  <?php foreach ($items as $l): ?><tr><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?></td><td><?= e($l['sku']) ?></td><td class="text-end"><?= e(qty($l['qty'])) ?> <?= e($l['unit']) ?></td><td><?= e($l['note'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if ($r['status'] === 'open' && can('purchase.create')): ?>
<div class="card" style="max-width:640px"><div class="card-header">Turn into a purchase order</div><div class="card-body">
  <form method="post" action="<?= url("purchase/requisitions/{$r['id']}/convert") ?>"><?= csrf_field() ?><div class="row g-2">
    <div class="col-md-5"><select name="supplier_id" class="form-select" required><option value="">Supplier…</option><?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select name="warehouse_id" class="form-select"><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><button class="btn btn-primary w-100">Create PO</button></div></div>
    <div class="form-text">A draft PO is created with each item's default cost and tax. You can edit it before submitting.</div></form>
</div></div>
<?php endif; ?>
