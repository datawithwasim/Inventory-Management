<div class="card" style="max-width:520px"><div class="card-body">
<form method="post" action="<?= url('stock/takes') ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Warehouse to count</label><select name="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="mb-3"><label class="form-label">Note (optional)</label><input name="note" class="form-control" maxlength="255"></div>
  <p class="text-muted small">A list of everything the system thinks is in the warehouse (by batch for fabric) is prepared. You enter what you actually counted, then post: differences become stock-take movements.</p>
  <button class="btn btn-primary">Start</button> <a class="btn btn-link" href="<?= url('stock/takes') ?>">Cancel</a>
</form></div></div>
