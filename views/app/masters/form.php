<div class="card" style="max-width:480px"><div class="card-body">
<form method="post" action="<?= $row ? url("masters/$type/{$row['id']}") : url("masters/$type") ?>"><?= csrf_field() ?>
  <?php foreach ($def['fields'] as [$col, $label, $kind, $required]): $val = old($col, $row[$col] ?? ($kind === 'number' ? '0' : '')); ?>
    <?php if ($kind === 'checkbox'): ?>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="<?= $col ?>" value="1" id="f_<?= $col ?>" <?= $val ? 'checked' : '' ?>>
        <label class="form-check-label" for="f_<?= $col ?>"><?= e($label) ?></label></div>
    <?php else: ?>
      <div class="mb-3"><label class="form-label"><?= e($label) ?></label>
        <input name="<?= $col ?>" class="form-control" <?= $kind === 'number' ? 'type="number" step="0.01" min="0" max="999"' : '' ?> value="<?= e($val) ?>" <?= $required ? 'required' : '' ?>></div>
    <?php endif; ?>
  <?php endforeach; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url("masters/$type") ?>">Cancel</a>
</form></div></div>
