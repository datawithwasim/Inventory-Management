<?php
$v = fn($k, $d = '') => e(old($k, $po[$k] ?? $d));
$pre = (int)($_GET['supplier'] ?? 0);
?>
<form method="post" action="<?= $po ? url("purchase/orders/{$po['id']}") : url('purchase/orders') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-4"><label class="form-label"><?= e(term('supplier')) ?></label><select name="supplier_id" class="form-select" required><option value="">Choose…</option>
    <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)old('supplier_id', $po['supplier_id'] ?? $pre) === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Deliver to</label><select name="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id', $po['warehouse_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">Order date</label><input type="date" name="order_date" class="form-control" value="<?= $v('order_date', date('Y-m-d')) ?>" required></div>
  <div class="col-md-3"><label class="form-label">Expected on</label><input type="date" name="expected_date" class="form-control" value="<?= $v('expected_date') ?>"></div>
  <div class="col-12"><label class="form-label">Notes (optional)</label><input name="notes" class="form-control" maxlength="255" value="<?= $v('notes') ?>"></div>
</div></div></div>
<?php $mode = 'po'; require __DIR__ . '/_lines.php'; ?>
<?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
<button class="btn btn-primary">Save draft</button> <a class="btn btn-link" href="<?= $po ? url("purchase/orders/{$po['id']}") : url('purchase/orders') ?>">Cancel</a>
</form>
