<?php  $v = fn($k, $d = '') => e(old($k, Core\Settings::get('company.' . $k, $d))); $logo = company_logo_url(); ?>
<div class="card" style="max-width:820px"><div class="card-body">
<form method="post" action="<?= url('settings/company') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-md-6"><label class="form-label">Company name</label><input name="name" class="form-control" maxlength="150" required value="<?= e(old('name', $tenantName)) ?>">
      <div class="form-text">Shown in the menu and on documents.</div></div>
    <div class="col-md-6"><label class="form-label">Legal / registered name</label><input name="legal_name" class="form-control" maxlength="150" value="<?= $v('legal_name') ?>"><div class="form-text">Optional. Printed instead of the company name on invoices.</div></div>
    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" maxlength="500"><?= $v('address') ?></textarea></div>
    <div class="col-md-4"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="60" value="<?= $v('phone') ?>"></div>
    <div class="col-md-4"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="190" value="<?= $v('email') ?>"></div>
    <div class="col-md-4"><label class="form-label">Website</label><input name="website" class="form-control" maxlength="190" value="<?= $v('website') ?>"></div>
    <div class="col-md-4"><label class="form-label">Tax number (GSTIN / VAT)</label><input name="tax_no" class="form-control" maxlength="60" value="<?= $v('tax_no') ?>"></div>
    <div class="col-md-8"><label class="form-label">Bank details (printed on invoices)</label><textarea name="bank" class="form-control" rows="2" maxlength="500" placeholder="Bank, account number, IFSC…"><?= $v('bank') ?></textarea></div>
    <div class="col-12"><label class="form-label">Logo</label>
      <div class="d-flex align-items-center gap-3">
        <?php if ($logo): ?><img src="<?= e($logo) ?>?v=<?= time() ?>" alt="Logo" style="max-height:70px;max-width:200px" class="border rounded p-1 bg-white">
          <div class="form-check"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rl"><label class="form-check-label" for="rl">Remove the logo</label></div><?php endif; ?>
        <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/gif,image/webp" style="max-width:340px"></div>
      <div class="form-text">PNG, JPG, GIF or WebP, up to 1 MB.</div></div>
  </div>
  <button class="btn btn-primary mt-3">Save</button>
</form></div></div>
