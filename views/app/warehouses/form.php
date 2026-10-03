<div class="card" style="max-width:520px"><div class="card-body">
<form method="post" action="<?= $row ? url("warehouses/{$row['id']}") : url('warehouses') ?>"><?= csrf_field() ?>
  <div class="ffgrid">
  <div class="mb-3"<?= ffa('warehouse.name') ?>><label class="form-label"><?= fl('warehouse.name', 'Name') ?></label><input name="name" class="form-control" value="<?= e(old('name', $row['name'] ?? '')) ?>" required></div>
  <div class="mb-3"<?= ffa('warehouse.code') ?>><label class="form-label"><?= fl('warehouse.code', 'Code') ?></label><input name="code" class="form-control" maxlength="20" value="<?= e(old('code', $row['code'] ?? '')) ?>" required></div>
<?php if (ff('warehouse.address')): ?>  <div class="mb-3"<?= ffa('warehouse.address') ?>><label class="form-label"><?= fl('warehouse.address', 'Address') ?><?= ffstar('warehouse.address') ?></label><input name="address"<?= ffreq('warehouse.address') ?> class="form-control" value="<?= e(old('address', $row['address'] ?? '')) ?>"></div><?php else: ?><?= ffh('warehouse.address', e(old('address', $row['address'] ?? ''))) ?><?php endif; ?>
  </div>
  <?php if ($row && !$row['is_default']): ?>
    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active (can receive and issue stock)</label></div>
  <?php endif; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('warehouses') ?>">Cancel</a>
</form></div></div>
