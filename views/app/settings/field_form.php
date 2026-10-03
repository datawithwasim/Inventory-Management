<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); $type = old('type', $row['type'] ?? 'text'); ?>
<div class="card" style="max-width:620px"><div class="card-body">
<form method="post" action="<?= $row ? url("settings/custom-fields/{$row['id']}") : url('settings/custom-fields') ?>"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Belongs to</label>
      <?php if ($row): ?><input class="form-control" value="<?= e(App\Models\CustomFields::ENTITIES[$row['entity']]) ?>" disabled>
      <?php else: ?><select name="entity" class="form-select"><?php foreach (App\Models\CustomFields::ENTITIES as $k => $l): ?><option value="<?= $k ?>" <?= old('entity', $entity) === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?></div>
    <div class="col-md-6"><label class="form-label">Type</label>
      <?php if ($row): ?><input class="form-control" value="<?= e(App\Models\CustomFields::TYPES[$row['type']]) ?>" disabled>
      <?php else: ?><select name="type" id="ftype" class="form-select"><?php foreach (App\Models\CustomFields::TYPES as $k => $l): ?><option value="<?= $k ?>" <?= $type === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select><?php endif; ?></div>
    <div class="col-12"><label class="form-label">Field name</label><input name="label" class="form-control" maxlength="80" value="<?= $v('label') ?>" required placeholder="e.g. Fabric width, Dye lot, Preferred courier"></div>
    <div class="col-12" id="optBox" <?= in_array($type, App\Models\CustomFields::CHOICE_TYPES, true) ? '' : 'hidden' ?>><label class="form-label">Choices (one per line)</label><textarea name="options" class="form-control" rows="4"><?= $v('options') ?></textarea></div>
    <div class="col-md-4"><label class="form-label">Order</label><input type="number" name="sort_order" class="form-control" value="<?= $v('sort_order', 0) ?>"><div class="form-text">Smaller numbers come first.</div></div>
    <div class="col-md-8 d-flex flex-column justify-content-end">
      <div class="form-check"><input class="form-check-input" type="checkbox" name="is_required" value="1" id="rq" <?= old('is_required', $row['is_required'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="rq">Required</label></div>
      <div class="form-check"><input class="form-check-input" type="checkbox" name="show_in_list" value="1" id="sl" <?= old('show_in_list', $row['show_in_list'] ?? 0) ? 'checked' : '' ?>><label class="form-check-label" for="sl">Show as a column in the list (first 3 only)</label></div>
      <?php if ($row): ?><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="ac" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="ac">Active (untick to hide it without losing the data)</label></div><?php endif; ?></div>
  </div>
  <button class="btn btn-primary mt-3">Save</button> <a class="btn btn-link" href="<?= url('settings/custom-fields') ?>">Cancel</a>
</form></div></div>
<script>(function(){var t=document.getElementById('ftype');if(!t)return;t.onchange=function(){document.getElementById('optBox').hidden=['dropdown','radio'].indexOf(t.value)<0;};})();</script>
