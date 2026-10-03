<form method="post" action="<?= url('stock/transfers') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-3"><label class="form-label">From warehouse</label><select name="warehouse_id" id="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">To warehouse</label><select name="to_warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('to_warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">Note (optional)</label><input name="note" class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div>
</div></div></div>
<?php $mode = 'transfer'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Transfer stock</button> <a class="btn btn-link" href="<?= url('stock/transfers') ?>">Cancel</a>
</form>
