<?php require __DIR__ . '/_tabs.php';
$sym = old('symbol', Core\Settings::get('currency.symbol')); $pos = old('position', Core\Settings::get('currency.position')); $dec = old('decimals', Core\Settings::get('currency.decimals'));
$grp = old('grouping', Core\Settings::get('number.grouping')); $fmt = old('date_format', Core\Settings::get('date.format')); ?>
<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" action="<?= url('settings/preferences') ?>"><?= csrf_field() ?>
  <h2 class="h6">Currency</h2>
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Symbol</label><input name="symbol" class="form-control" maxlength="8" placeholder="₹  $  AED" value="<?= e($sym) ?>"><div class="form-text">Leave empty to show plain numbers.</div></div>
    <div class="col-md-3"><label class="form-label">Placed</label><select name="position" class="form-select"><option value="before" <?= $pos === 'before' ? 'selected' : '' ?>>Before the amount</option><option value="after" <?= $pos === 'after' ? 'selected' : '' ?>>After the amount</option></select></div>
    <div class="col-md-3"><label class="form-label">Decimals</label><select name="decimals" class="form-select"><?php foreach (['0', '1', '2', '3'] as $d): ?><option <?= (string)$dec === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Digit grouping</label><select name="grouping" class="form-select"><option value="intl" <?= $grp === 'intl' ? 'selected' : '' ?>>1,234,567.00</option><option value="indian" <?= $grp === 'indian' ? 'selected' : '' ?>>12,34,567.00 (Indian)</option></select></div>
  </div>
  <h2 class="h6">Dates</h2>
  <div class="mb-3" style="max-width:300px"><label class="form-label">Date format on screens and prints</label><select name="date_format" class="form-select">
    <?php foreach ($dateFormats as $k => $l): ?><option value="<?= e($k) ?>" <?= $fmt === $k ? 'selected' : '' ?>><?= e($l) ?> — <?= e(date($k)) ?></option><?php endforeach; ?></select></div>
  <p class="text-muted small">Example: <strong><?= e(money(1234567.5)) ?></strong> · <strong><?= e(fdate(date('Y-m-d'))) ?></strong></p>
  <button class="btn btn-primary">Save</button>
</form></div></div>
