<?php
$v = fn($k, $d = '') => e(old($k, $po[$k] ?? $d));
$pre = (int)($_GET['supplier'] ?? 0);
?>
<form method="post" action="<?= $po ? url("purchase/orders/{$po['id']}") : url('purchase/orders') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3 <?= ffclass('purchase_order') ?>">
  <div class="col-md-4"<?= ffa('purchase_order.supplier_id') ?>><label class="form-label"><?= fl('purchase_order.supplier_id', e(term('supplier'))) ?></label><select name="supplier_id" class="form-select" required><option value="">Choose…</option>
    <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)old('supplier_id', $po['supplier_id'] ?? $pre) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"<?= ffa('purchase_order.warehouse_id') ?>><label class="form-label"><?= fl('purchase_order.warehouse_id', 'Deliver to') ?></label><select name="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id', $po['warehouse_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"<?= ffa('purchase_order.order_date') ?>><label class="form-label"><?= fl('purchase_order.order_date', 'Order date') ?></label><input type="date" name="order_date" class="form-control" value="<?= $v('order_date', date('Y-m-d')) ?>" required></div>
<?php if (ff('purchase_order.expected_date')): ?>  <div class="col-md-3"<?= ffa('purchase_order.expected_date') ?>><label class="form-label"><?= fl('purchase_order.expected_date', 'Expected on') ?><?= ffstar('purchase_order.expected_date') ?></label><input type="date" name="expected_date"<?= ffreq('purchase_order.expected_date') ?> class="form-control" value="<?= $v('expected_date') ?>"></div><?php else: ?><?= ffh('purchase_order.expected_date', $v('expected_date')) ?><?php endif; ?>
<?php if (ff('purchase_order.notes')): ?>  <div class="col-12"<?= ffa('purchase_order.notes') ?>><label class="form-label"><?= fl('purchase_order.notes', 'Notes (optional)') ?><?= ffstar('purchase_order.notes') ?></label><input name="notes"<?= ffreq('purchase_order.notes') ?> class="form-control" maxlength="255" value="<?= $v('notes') ?>"></div><?php else: ?><?= ffh('purchase_order.notes', $v('notes')) ?><?php endif; ?>
<?= ffextras('purchase_order', $cfFields, $cfValues) ?>
</div></div></div>
<?php $mode = 'po'; $lineEntity = 'purchase_order'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Save draft</button> <a class="btn btn-link" href="<?= $po ? url("purchase/orders/{$po['id']}") : url('purchase/orders') ?>">Cancel</a>
</form>
