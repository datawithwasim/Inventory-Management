<?php
$shown = array_filter($cfFields ?? [], fn($f) => ($cfValues[$f['id']] ?? '') !== '');
if ($shown): ?>
<div class="card mb-3"><div class="card-header">Additional details</div><div class="card-body"><div class="row g-3">
  <?php foreach ($shown as $f): ?><div class="col-md-4"><div class="small text-muted"><?= e($f['label']) ?></div><div><?= e(App\Models\CustomFields::display($f, $cfValues[$f['id']])) ?></div></div><?php endforeach; ?>
</div></div></div>
<?php endif; ?>
