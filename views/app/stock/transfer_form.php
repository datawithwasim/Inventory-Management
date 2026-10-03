<form method="post" action="<?= url('stock/transfers') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3 <?= ffclass('transfer') ?>"><?= ffextras('transfer', [], []) ?>
  <div class="col-md-3"<?= ffa('transfer.warehouse_id') ?>><label class="form-label"><?= fl('transfer.warehouse_id', 'From ' . e(term('warehouse', true))) ?></label><select name="warehouse_id" id="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"<?= ffa('transfer.to_warehouse_id') ?>><label class="form-label"><?= fl('transfer.to_warehouse_id', 'To ' . e(term('warehouse', true))) ?></label><select name="to_warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('to_warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
<?php if (ff('transfer.note')): ?>  <div class="col-md-6"<?= ffa('transfer.note') ?>><label class="form-label"><?= fl('transfer.note', 'Note (optional)') ?><?= ffstar('transfer.note') ?></label><input name="note"<?= ffreq('transfer.note') ?> class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div><?php else: ?><?= ffh('transfer.note', e(old('note'))) ?><?php endif; ?>
</div>
<p class="text-muted small mt-3 mb-0">You can also move stock between <?= e(term('racks', true)) ?> <em>inside</em> one <?= e(term('warehouse', true)) ?>: choose the same <?= e(term('warehouse', true)) ?> on both sides and pick different <?= e(term('racks', true)) ?> on each line.</p></div></div>
<?php $mode = 'transfer'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Transfer stock</button> <a class="btn btn-link" href="<?= url('stock/transfers') ?>">Cancel</a>
</form>
