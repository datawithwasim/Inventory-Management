<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); ?>
<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" action="<?= $row ? url("suppliers/{$row['id']}") : url('suppliers') ?>"><?= csrf_field() ?>
  <div class="ffgrid">
  <div class="mb-3"<?= ffa('supplier.name') ?>><label class="form-label"><?= fl('supplier.name', e(term('supplier')) . ' name') ?></label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150"></div>
  <div class="row">
    <?php if (ff('supplier.contact_person')): ?><div class="col-md-6 mb-3"<?= ffa('supplier.contact_person') ?>><label class="form-label"><?= fl('supplier.contact_person', 'Contact person') ?><?= ffstar('supplier.contact_person') ?></label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"<?= ffreq('supplier.contact_person') ?>></div><?php endif; ?>
    <div class="col-md-6 mb-3"<?= ffa('supplier.phone') ?>><label class="form-label"><?= fl('supplier.phone', 'Phone') ?></label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <?php if (ff('supplier.email')): ?><div class="col-md-6 mb-3"<?= ffa('supplier.email') ?>><label class="form-label"><?= fl('supplier.email', 'Email') ?><?= ffstar('supplier.email') ?></label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"<?= ffreq('supplier.email') ?>></div><?php endif; ?>
    <?php if (ff('supplier.tax_no')): ?><div class="col-md-6 mb-3"<?= ffa('supplier.tax_no') ?>><label class="form-label"><?= fl('supplier.tax_no', 'Tax number (GSTIN / VAT)') ?><?= ffstar('supplier.tax_no') ?></label><input name="tax_no" class="form-control" value="<?= $v('tax_no') ?>"<?= ffreq('supplier.tax_no') ?>></div><?php endif; ?>
  </div>
  <?php if (ff('supplier.address')): ?><div class="mb-3"<?= ffa('supplier.address') ?>><label class="form-label"><?= fl('supplier.address', 'Address') ?><?= ffstar('supplier.address') ?></label><input name="address" class="form-control" value="<?= $v('address') ?>"<?= ffreq('supplier.address') ?>></div><?php endif; ?>
  <div class="row">
    <?php if (ff('supplier.payment_terms_days')): ?><div class="col-md-4 mb-3"<?= ffa('supplier.payment_terms_days') ?>><label class="form-label"><?= fl('supplier.payment_terms_days', 'Payment terms (days)') ?><?= ffstar('supplier.payment_terms_days') ?></label><input type="number" min="0" max="365" name="payment_terms_days" class="form-control" value="<?= $v('payment_terms_days', 0) ?>"<?= ffreq('supplier.payment_terms_days') ?>></div><?php endif; ?>
    <?php if (ff('supplier.notes')): ?><div class="col-md-8 mb-3"<?= ffa('supplier.notes') ?>><label class="form-label"><?= fl('supplier.notes', 'Notes') ?><?= ffstar('supplier.notes') ?></label><input name="notes" class="form-control" value="<?= $v('notes') ?>"<?= ffreq('supplier.notes') ?>></div><?php endif; ?>
  </div>
  </div>
  <?php if ($row): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div><?php endif; ?>
  <?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('suppliers') ?>">Cancel</a>
</form></div></div>
