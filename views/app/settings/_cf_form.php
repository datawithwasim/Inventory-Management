<?php /** @var array $cfFields @var array $cfValues */ if (!empty($cfFields)): ?>
<div class="card mb-3"><div class="card-header">Additional details</div><div class="card-body"><div class="row g-3">
  <?php foreach ($cfFields as $f): $name = 'cf[' . (int)$f['id'] . ']'; $val = (string)($cfValues[$f['id']] ?? ''); $req = $f['is_required'] ? 'required' : ''; ?>
    <div class="col-md-4"><label class="form-label"><?= e($f['label']) ?><?= $f['is_required'] ? ' <span class="text-danger">*</span>' : '' ?></label>
      <?php if ($f['type'] === 'checkbox'): ?>
        <div class="form-check"><input type="hidden" name="<?= $name ?>" value="0"><input class="form-check-input" type="checkbox" name="<?= $name ?>" value="1" <?= $val === '1' ? 'checked' : '' ?>><label class="form-check-label">Yes</label></div>
      <?php elseif ($f['type'] === 'dropdown'): ?>
        <select name="<?= $name ?>" class="form-select" <?= $req ?>><option value="">—</option><?php foreach ($f['choices'] as $c): ?><option <?= $val === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select>
      <?php else: ?>
        <input name="<?= $name ?>" class="form-control" type="<?= $f['type'] === 'number' ? 'number' : ($f['type'] === 'date' ? 'date' : 'text') ?>" <?= $f['type'] === 'number' ? 'step="any"' : 'maxlength="255"' ?> value="<?= e($val) ?>" <?= $req ?>>
      <?php endif; ?></div>
  <?php endforeach; ?>
</div></div></div>
<?php endif; ?>
