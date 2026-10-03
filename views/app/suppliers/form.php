<?php $v = fn($k, $d = '') => e(old($k, $row[$k] ?? $d)); ?>
<div class="card" style="max-width:720px"><div class="card-body">
<form method="post" action="<?= $row ? url("suppliers/{$row['id']}") : url('suppliers') ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Supplier name</label><input name="name" class="form-control" value="<?= $v('name') ?>" required maxlength="150"></div>
  <div class="row">
    <div class="col-md-6 mb-3"><label class="form-label">Contact person</label><input name="contact_person" class="form-control" value="<?= $v('contact_person') ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?= $v('phone') ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= $v('email') ?>"></div>
    <div class="col-md-6 mb-3"><label class="form-label">Tax number (GSTIN / VAT)</label><input name="tax_no" class="form-control" value="<?= $v('tax_no') ?>"></div>
  </div>
  <div class="mb-3"><label class="form-label">Address</label><input name="address" class="form-control" value="<?= $v('address') ?>"></div>
  <div class="row">
    <div class="col-md-4 mb-3"><label class="form-label">Payment terms (days)</label><input type="number" min="0" max="365" name="payment_terms_days" class="form-control" value="<?= $v('payment_terms_days', 0) ?>"></div>
    <div class="col-md-8 mb-3"><label class="form-label">Notes</label><input name="notes" class="form-control" value="<?= $v('notes') ?>"></div>
  </div>
  <?php if ($row): ?><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" <?= $row['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="act">Active</label></div><?php endif; ?>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('suppliers') ?>">Cancel</a>
</form></div></div>
