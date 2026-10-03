<?php $mode = old('mode', 'single'); ?>
<div class="card" style="max-width:560px"><div class="card-body">
<form method="post" action="<?= $row ? url("locations/{$row['id']}") : url('locations') ?>"><?= csrf_field() ?>
  <?php if ($row): ?>
    <p class="text-muted">Warehouse: <strong><?= e($row['warehouse']) ?></strong></p>
    <div class="mb-3"><label class="form-label">Rack code</label><input name="code" class="form-control" maxlength="30" value="<?= e(old('code', $row['code'])) ?>" required></div>
    <div class="mb-3"><label class="form-label">Description (optional)</label><input name="description" class="form-control" maxlength="150" value="<?= e(old('description', $row['description'] ?? '')) ?>"></div>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active (new stock can be put here)</label></div>
  <?php else: ?>
    <div class="mb-3"><label class="form-label">Warehouse</label><select name="warehouse_id" class="form-select" required>
      <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <div class="btn-group mb-3" role="group">
      <input type="radio" class="btn-check" name="mode" value="single" id="m1" <?= $mode === 'single' ? 'checked' : '' ?>><label class="btn btn-outline-primary" for="m1">One rack</label>
      <input type="radio" class="btn-check" name="mode" value="range" id="m2" <?= $mode === 'range' ? 'checked' : '' ?>><label class="btn btn-outline-primary" for="m2">Many racks (a range)</label>
    </div>
    <div id="single"><div class="mb-3"><label class="form-label">Rack code</label><input name="code" class="form-control" maxlength="30" placeholder="e.g. A-01" value="<?= e(old('code')) ?>"></div></div>
    <div id="range" hidden>
      <div class="row">
        <div class="col-md-4 mb-3"><label class="form-label">Prefix</label><input name="prefix" class="form-control" placeholder="A-" value="<?= e(old('prefix')) ?>"></div>
        <div class="col-md-3 mb-3"><label class="form-label">From</label><input name="from" type="number" min="0" class="form-control" value="<?= e(old('from', 1)) ?>"></div>
        <div class="col-md-3 mb-3"><label class="form-label">To</label><input name="to" type="number" min="0" class="form-control" value="<?= e(old('to', 10)) ?>"></div>
        <div class="col-md-2 mb-3"><label class="form-label">Digits</label><input name="pad" type="number" min="0" max="6" class="form-control" value="<?= e(old('pad', 2)) ?>"></div>
      </div>
      <p class="text-muted small">Prefix <code>A-</code>, from 1 to 10 with 2 digits creates A-01 … A-10 (max 200 at a time).</p>
    </div>
    <div class="mb-3"><label class="form-label">Description (optional)</label><input name="description" class="form-control" maxlength="150" value="<?= e(old('description')) ?>"></div>
    <script>
      (function () {
        function sync() { var r = document.getElementById('m2').checked; document.getElementById('range').hidden = !r; document.getElementById('single').hidden = r; }
        document.getElementById('m1').onchange = document.getElementById('m2').onchange = sync; sync();
      })();
    </script>
  <?php endif; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('locations') ?>">Cancel</a>
</form></div></div>
