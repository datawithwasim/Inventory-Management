<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); ?>
<div class="card" style="max-width:760px"><div class="card-body">
<form method="post" action="<?= $row ? url("customers/{$row['id']}") : url('customers') ?>"><?= csrf_field() ?>
  <div class="row">
    <div class="col-md-8 mb-3"><label class="form-label"><?= e(term('customer')) ?> name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150" <?= !empty($row['is_walkin']) ? 'readonly' : '' ?>></div>
    <?php if (ff('customer.group_id')): ?><div class="col-md-4 mb-3"><label class="form-label">Group<?= ffstar('customer.group_id') ?></label><select name="group_id" class="form-select"<?= ffreq('customer.group_id') ?>><option value="">None (retail)</option>
      <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)old('group_id', $row['group_id'] ?? 0) === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?><?= (float)$g['discount_pct'] > 0 ? ' (' . e(qty($g['discount_pct'])) . '% off)' : '' ?></option><?php endforeach; ?></select></div><?php endif; ?>
    <?php if (ff('customer.contact_person')): ?><div class="col-md-4 mb-3"><label class="form-label">Contact person<?= ffstar('customer.contact_person') ?></label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"<?= ffreq('customer.contact_person') ?>></div><?php endif; ?>
    <div class="col-md-4 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <?php if (ff('customer.email')): ?><div class="col-md-4 mb-3"><label class="form-label">Email<?= ffstar('customer.email') ?></label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"<?= ffreq('customer.email') ?>></div><?php endif; ?>
  </div>
  <?php if (ff('customer.address')): ?><div class="mb-3"><label class="form-label">Billing address<?= ffstar('customer.address') ?></label><input name="address" class="form-control" value="<?= $v('address') ?>"<?= ffreq('customer.address') ?>></div><?php endif; ?>
  <?php if (ff('customer.ship_address')): ?><div class="mb-3"><label class="form-label">Delivery address (if different)<?= ffstar('customer.ship_address') ?></label><input name="ship_address" class="form-control" value="<?= $v('ship_address') ?>"<?= ffreq('customer.ship_address') ?>></div><?php endif; ?>
  <div class="row">
    <?php if (ff('customer.tax_no')): ?><div class="col-md-4 mb-3"><label class="form-label">Tax number (GSTIN / VAT)<?= ffstar('customer.tax_no') ?></label><input name="tax_no" class="form-control" value="<?= $v('tax_no') ?>"<?= ffreq('customer.tax_no') ?>></div><?php endif; ?>
    <?php if (ff('customer.credit_days')): ?><div class="col-md-3 mb-3"><label class="form-label">Credit days<?= ffstar('customer.credit_days') ?></label><input type="number" min="0" max="365" name="credit_days" class="form-control" value="<?= $v('credit_days', 0) ?>"<?= ffreq('customer.credit_days') ?>></div><?php endif; ?>
    <?php if (ff('customer.notes')): ?><div class="col-md-5 mb-3"><label class="form-label">Notes<?= ffstar('customer.notes') ?></label><input name="notes" class="form-control" value="<?= $v('notes') ?>"<?= ffreq('customer.notes') ?>></div><?php endif; ?>
  </div>
  <?php if ($row && !$row['is_walkin']): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div><?php endif; ?>
  <?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('customers') ?>">Cancel</a>
</form></div></div>
