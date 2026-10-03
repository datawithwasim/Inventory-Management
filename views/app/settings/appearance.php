<?php
$brand = old('brand_custom', '') ?: old('brand', Core\Theme::get('appearance.brand'));
$brand = strtolower((string)$brand);
$sb = old('sidebar', Core\Theme::get('appearance.sidebar'));
$den = old('density', Core\Theme::get('appearance.density'));
$mode = old('mode', Core\Theme::get('appearance.mode'));
$isPreset = isset($brands[$brand]);
?>
<div class="card"><div class="card-body">
<form method="post" action="<?= url('settings/appearance') ?>" id="appearanceForm"><?= csrf_field() ?>
  <h2 class="h6">Brand colour</h2>
  <p class="text-muted small">Buttons, links, highlights and charts use this colour. You see the change straight away; it is saved when you press Save.</p>
  <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <?php foreach ($brands as $hex => $name): ?>
      <label class="position-relative" title="<?= e($name) ?>"><input class="swatch-radio" type="radio" name="brand" value="<?= e($hex) ?>" <?= $brand === $hex ? 'checked' : '' ?>><span class="swatch" style="background:<?= e($hex) ?>"></span></label>
    <?php endforeach; ?>
    <div class="d-flex align-items-center gap-2 ms-2"><span class="small text-muted">Custom</span>
      <input type="color" id="brandPicker" class="form-control form-control-color" value="<?= e($brand) ?>" title="Pick any colour">
      <input type="hidden" name="brand_custom" id="brandCustom" value="<?= $isPreset ? '' : e($brand) ?>"></div>
  </div>

  <h2 class="h6">Menu style</h2>
  <div class="row g-2 mb-4">
    <?php foreach ($sidebars as $k => $l): ?><div class="col-sm-4"><label class="w-100"><input class="d-none" type="radio" name="sidebar" value="<?= e($k) ?>" <?= $sb === $k ? 'checked' : '' ?>><span class="opt-card"><strong><?= e($l) ?></strong></span></label></div><?php endforeach; ?>
  </div>

  <h2 class="h6">Spacing</h2>
  <div class="row g-2 mb-4">
    <?php foreach ($densities as $k => $l): ?><div class="col-sm-6"><label class="w-100"><input class="d-none" type="radio" name="density" value="<?= e($k) ?>" <?= $den === $k ? 'checked' : '' ?>><span class="opt-card"><strong><?= e($l) ?></strong><span class="d-block small text-muted"><?= $k === 'compact' ? 'Tighter rows — more on the screen' : 'Roomy and easy on the eyes' ?></span></span></label></div><?php endforeach; ?>
  </div>

  <h2 class="h6">Default theme</h2>
  <p class="text-muted small">Everyone can still switch between light and dark with the moon button at the top.</p>
  <div class="row g-2 mb-4">
    <?php foreach ($modes as $k => $l): ?><div class="col-sm-4"><label class="w-100"><input class="d-none" type="radio" name="mode" value="<?= e($k) ?>" <?= $mode === $k ? 'checked' : '' ?>><span class="opt-card"><strong><?= e($l) ?></strong></span></label></div><?php endforeach; ?>
  </div>
  <button class="btn btn-primary">Save appearance</button>
</form></div></div>
<script>
(function () {
  var f = document.getElementById('appearanceForm'), root = document.documentElement, body = document.body;
  function hex2rgb(h) { return [1, 3, 5].map(function (i) { return parseInt(h.substr(i, 2), 16); }); }
  function mix(a, b, t) { return '#' + [0, 1, 2].map(function (i) { return ('0' + Math.round(a[i] * (1 - t) + b[i] * t).toString(16)).slice(-2); }).join(''); }
  function lum(c) { var f = function (v) { v /= 255; return v <= .03928 ? v / 12.92 : Math.pow((v + .055) / 1.055, 2.4); }; return .2126 * f(c[0]) + .7152 * f(c[1]) + .0722 * f(c[2]); }
  function applyBrand(h) {
    var c = hex2rgb(h), s = root.style;
    s.setProperty('--brand', h); s.setProperty('--brand-rgb', c.join(',')); s.setProperty('--brand-600', mix(c, [0, 0, 0], .14)); s.setProperty('--brand-700', mix(c, [0, 0, 0], .28));
    s.setProperty('--brand-soft', mix(c, [255, 255, 255], .9)); s.setProperty('--brand-soft-dark', mix(c, [17, 24, 39], .78));
    s.setProperty('--brand-ink', lum(c) > .42 ? '#111827' : '#ffffff'); s.setProperty('--brand-text', lum(c) > .5 ? mix(c, [0, 0, 0], .45) : h); s.setProperty('--sb-brand', mix(c, [0, 0, 0], .3));
  }
  f.querySelectorAll('input[name=brand]').forEach(function (r) { r.addEventListener('change', function () { document.getElementById('brandCustom').value = ''; document.getElementById('brandPicker').value = r.value; applyBrand(r.value); }); });
  document.getElementById('brandPicker').addEventListener('input', function () { f.querySelectorAll('input[name=brand]').forEach(function (r) { r.checked = false; }); document.getElementById('brandCustom').value = this.value; applyBrand(this.value); });
  f.querySelectorAll('input[name=sidebar]').forEach(function (r) { r.addEventListener('change', function () { body.className = body.className.replace(/sbs-\w+/, 'sbs-' + r.value); }); });
  f.querySelectorAll('input[name=density]').forEach(function (r) { r.addEventListener('change', function () { body.className = body.className.replace(/density-\w+/, 'density-' + r.value); }); });
})();
</script>
