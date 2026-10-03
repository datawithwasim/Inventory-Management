<?php require __DIR__ . '/_tabs.php'; ?>
<div class="row g-3"><div class="col-lg-3"><div class="list-group">
  <?php foreach ($docs as $k => $l): ?><a class="list-group-item list-group-item-action <?= $k === $doc ? 'active' : '' ?>" href="<?= url("settings/templates?doc=$k") ?>"><?= e($l) ?></a><?php endforeach; ?></div></div>
<div class="col-lg-9"><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e($docs[$doc]) ?> layout</span>
  <?php if ($previewUrl): ?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?= e($previewUrl) ?>"><i class="bi bi-eye"></i> Preview with your latest <?= e(strtolower($docs[$doc])) ?></a><?php else: ?><span class="text-muted small">Create a <?= e(strtolower($docs[$doc])) ?> to preview it.</span><?php endif; ?></div>
<div class="card-body"><form method="post" action="<?= url('settings/templates') ?>"><?= csrf_field() ?><input type="hidden" name="doc" value="<?= e($doc) ?>">
  <div class="row g-3">
  <?php foreach ($fields as $k => [$label, $type]): $val = old($k, $values[$k] ?? ''); ?>
    <?php if ($type === 'bool'): ?>
      <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($k) ?>" value="1" id="f_<?= e($k) ?>" <?= !empty($val) ? 'checked' : '' ?>><label class="form-check-label" for="f_<?= e($k) ?>"><?= e($label) ?></label></div></div>
    <?php elseif ($type === 'color'): ?>
      <div class="col-md-6"><label class="form-label"><?= e($label) ?></label><input type="color" name="<?= e($k) ?>" class="form-control form-control-color" value="<?= e($val) ?>"></div>
    <?php elseif ($type === 'textarea'): ?>
      <div class="col-12"><label class="form-label"><?= e($label) ?></label><textarea name="<?= e($k) ?>" class="form-control" rows="3" maxlength="1000"><?= e($val) ?></textarea></div>
    <?php else: ?>
      <div class="col-md-6"><label class="form-label"><?= e($label) ?></label><input name="<?= e($k) ?>" class="form-control" maxlength="120" value="<?= e($val) ?>"></div>
    <?php endif; ?>
  <?php endforeach; ?></div>
  <button class="btn btn-primary mt-3">Save template</button>
</form></div></div>
<p class="text-muted small mt-2">Your logo, address and tax number come from <a href="<?= url('settings/company') ?>">Company profile</a>.</p></div></div>
