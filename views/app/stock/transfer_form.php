<form method="post" action="<?= url('stock/transfers') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-3"><label class="form-label">From <?= e(term('warehouse', true)) ?></label><select name="warehouse_id" id="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">To <?= e(term('warehouse', true)) ?></label><select name="to_warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('to_warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-6"><label class="form-label">Note (optional)</label><input name="note" class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div>
</div>
<p class="text-muted small mt-3 mb-0">You can also move stock between <?= e(term('racks', true)) ?> <em>inside</em> one <?= e(term('warehouse', true)) ?>: choose the same <?= e(term('warehouse', true)) ?> on both sides and pick different <?= e(term('racks', true)) ?> on each line.</p></div></div>
<?php $mode = 'transfer'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Transfer stock</button> <a class="btn btn-link" href="<?= url('stock/transfers') ?>">Cancel</a>
</form>
