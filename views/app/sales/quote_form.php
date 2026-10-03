<?php $v = fn($k, $d = '') => e(old($k, $q[$k] ?? $d)); $pre = (int)($_GET['customer'] ?? 0); ?>
<form method="post" action="<?= $q ? url("sales/quotations/{$q['id']}") : url('sales/quotations') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-5"><label class="form-label"><?= e(term('customer')) ?></label><select name="customer_id" id="customer_id" class="form-select" required>
    <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('customer_id', $q['customer_id'] ?? $pre) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="quote_date" class="form-control" value="<?= $v('quote_date', date('Y-m-d')) ?>" required></div>
<?php if (ff('quotation.valid_until')): ?>  <div class="col-md-4"><label class="form-label">Valid until<?= ffstar('quotation.valid_until') ?></label><input type="date" name="valid_until"<?= ffreq('quotation.valid_until') ?> class="form-control" value="<?= $v('valid_until', date('Y-m-d', strtotime('+15 days'))) ?>"></div><?php else: ?><?= ffh('quotation.valid_until', $v('valid_until', date('Y-m-d', strtotime('+15 days')))) ?><?php endif; ?>
<?php if (ff('quotation.delivery_charge')): ?>  <div class="col-md-3"><label class="form-label">Delivery charge<?= ffstar('quotation.delivery_charge') ?></label><input type="number" step="0.01" min="0" name="delivery_charge"<?= ffreq('quotation.delivery_charge') ?> class="form-control" value="<?= $v('delivery_charge') ?>"></div><?php else: ?><?= ffh('quotation.delivery_charge', $v('delivery_charge')) ?><?php endif; ?>
<?php if (ff('quotation.installation_charge')): ?>  <div class="col-md-3"><label class="form-label">Installation charge<?= ffstar('quotation.installation_charge') ?></label><input type="number" step="0.01" min="0" name="installation_charge"<?= ffreq('quotation.installation_charge') ?> class="form-control" value="<?= $v('installation_charge') ?>"></div><?php else: ?><?= ffh('quotation.installation_charge', $v('installation_charge')) ?><?php endif; ?>
<?php if (ff('quotation.notes')): ?>  <div class="col-md-6"><label class="form-label">Notes / terms<?= ffstar('quotation.notes') ?></label><input name="notes"<?= ffreq('quotation.notes') ?> class="form-control" maxlength="255" value="<?= $v('notes') ?>"></div><?php else: ?><?= ffh('quotation.notes', $v('notes')) ?><?php endif; ?>
</div></div></div>
<?php require __DIR__ . '/_lines.php'; ?>
<?php require dirname(__DIR__) . '/settings/_cf_form.php'; ?>
<button class="btn btn-primary">Save quotation</button> <a class="btn btn-link" href="<?= $q ? url("sales/quotations/{$q['id']}") : url('sales/quotations') ?>">Cancel</a>
</form>
