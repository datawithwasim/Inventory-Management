<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); ?>
<div class="card" style="max-width:760px"><div class="card-body">
<form method="post" action="<?= $row ? url("customers/{$row['id']}") : url('customers') ?>"><?= csrf_field() ?>
  <div class="row">
    <div class="col-md-8 mb-3"><label class="form-label"><?= e(term('customer')) ?> name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150" <?= !empty($row['is_walkin']) ? 'readonly' : '' ?>></div>
    <div class="col-md-4 mb-3"><label class="form-label">Group</label><select name="group_id" class="form-select"><option value="">None (retail)</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)old('group_id', $row['group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?><?= (float)$g['discount_pct'] > 0 ? ' (' . e(qty($g['discount_pct'])) . '% off)' : '' ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4 mb-3"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"></div>
    <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <div class="col-md-4 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
  </div>
  <div class="mb-3"><label class="form-label">Billing address</label><input name="address" class="form-control" value="<?= $v('address') ?>"></div>
  <div class="mb-3"><label class="form-label">Delivery address (if different)</label><input name="ship_address" class="form-control" value="<?= $v('ship_address') ?>"></div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Tax number (GSTIN / VAT)</label><input name="tax_no" class="form-control" value="<?= $v('tax_no') ?>"></div>
    <div class="col-md-3 mb-3"><label class="form-label">Credit days</label><input type="number" min="0" max="365" name="credit_days" class="form-control" value="<?= $v('credit_days', 0) ?>"></div>
    <div class="col-md-5 mb-3"><label class="form-label">Notes</label><input name="notes" class="form-control" value="<?= $v('notes') ?>"></div>
  </div>
  <?php if ($row && !$row['is_walkin']): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div><?php endif; ?>
  <?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('customers') ?>">Cancel</a>
</form></div></div>
