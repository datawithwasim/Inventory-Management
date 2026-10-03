<form method="post" action="<?= url('purchase/requisitions') ?>"><?= csrf_field() ?>
<?php if (ff('requisition.note')): ?><div class="card mb-3" style="max-width:720px"><div class="card-body"><label class="form-label">Note<?= ffstar('requisition.note') ?></label><input name="note"<?= ffreq('requisition.note') ?> class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div></div><?php else: ?><?= ffh('requisition.note', old('note')) ?><?php endif; ?>
<?php $mode = 'req'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Save requisition</button> <a class="btn btn-link" href="<?= url('purchase/requisitions') ?>">Cancel</a>
</form>
