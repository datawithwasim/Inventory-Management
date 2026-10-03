<?php $v = fn($k, $d = '') => e(old($k, $q[$k] ?? $d)); $pre = (int)($_GET['customer'] ?? 0); ?>
<form method="post" action="<?= $q ? url("sales/quotations/{$q['id']}") : url('sales/quotations') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-5"><label class="form-label">Customer</label><select name="customer_id" id="customer_id" class="form-select" required>
    <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('customer_id', $q['customer_id'] ?? $pre) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="quote_date" class="form-control" value="<?= $v('quote_date', date('Y-m-d')) ?>" required></div>
  <div class="col-md-4"><label class="form-label">Valid until</label><input type="date" name="valid_until" class="form-control" value="<?= $v('valid_until', date('Y-m-d', strtotime('+15 days'))) ?>"></div>
  <div class="col-md-3"><label class="form-label">Delivery charge</label><input type="number" step="0.01" min="0" name="delivery_charge" class="form-control" value="<?= $v('delivery_charge') ?>"></div>
  <div class="col-md-3"><label class="form-label">Installation charge</label><input type="number" step="0.01" min="0" name="installation_charge" class="form-control" value="<?= $v('installation_charge') ?>"></div>
  <div class="col-md-6"><label class="form-label">Notes / terms</label><input name="notes" class="form-control" maxlength="255" value="<?= $v('notes') ?>"></div>
</div></div></div>
<?php require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Save quotation</button> <a class="btn btn-link" href="<?= $q ? url("sales/quotations/{$q['id']}") : url('sales/quotations') ?>">Cancel</a>
</form>
