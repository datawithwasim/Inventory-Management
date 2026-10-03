<form method="post" action="<?= url('stock/adjustments') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-3"<?= ffa('adjustment.warehouse_id') ?>><label class="form-label"><?= fl('adjustment.warehouse_id', e(term('warehouse'))) ?></label><select name="warehouse_id" id="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"<?= ffa('adjustment.reason') ?>><label class="form-label"><?= fl('adjustment.reason', 'Reason') ?></label><select name="reason" class="form-select" required>
    <?php foreach ($reasons as $k => $label): ?><option value="<?= e($k) ?>" <?= old('reason') === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
<?php if (ff('adjustment.note')): ?>  <div class="col-md-6"<?= ffa('adjustment.note') ?>><label class="form-label"><?= fl('adjustment.note', 'Note (optional)') ?><?= ffstar('adjustment.note') ?></label><input name="note"<?= ffreq('adjustment.note') ?> class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div><?php else: ?><?= ffh('adjustment.note', e(old('note'))) ?><?php endif; ?>
</div>
<p class="text-muted small mt-3 mb-0">Use a positive quantity to add stock (for fabric, <strong>+ New <?= e(term('batch', true)) ?></strong> creates a new roll) and a negative quantity to remove it from a chosen <?= e(term('batch', true)) ?>.</p>
</div></div>
<?php $mode = 'adjust'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Post adjustment</button> <a class="btn btn-link" href="<?= url('stock/adjustments') ?>">Cancel</a>
</form>
