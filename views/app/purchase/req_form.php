<form method="post" action="<?= url('purchase/requisitions') ?>"><?= csrf_field() ?>
<div class="card mb-3" style="max-width:720px"><div class="card-body"><label class="form-label">Note (optional)</label><input name="note" class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div></div>
<?php $mode = 'req'; require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Save requisition</button> <a class="btn btn-link" href="<?= url('purchase/requisitions') ?>">Cancel</a>
</form>
