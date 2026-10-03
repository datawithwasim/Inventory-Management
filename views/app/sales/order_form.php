<?php $v = fn($k, $d = '') => e(old($k, $o[$k] ?? $d)); $pre = (int)($_GET['customer'] ?? 0); ?>
<form method="post" action="<?= $o ? url("sales/orders/{$o['id']}") : url('sales/orders') ?>"><?= csrf_field() ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-4"><label class="form-label"><?= e(term('customer')) ?></label><select name="customer_id" id="customer_id" class="form-select" required>
    <?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('customer_id', $o['customer_id'] ?? $pre) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-3"><label class="form-label">Ship from</label><select name="warehouse_id" id="warehouse_id" class="form-select" required>
    <?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id', $o['warehouse_id'] ?? 0) === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><label class="form-label">Order date</label><input type="date" name="order_date" class="form-control" value="<?= $v('order_date', date('Y-m-d')) ?>" required></div>
  <div class="col-md-3"><label class="form-label">Deliver by</label><input type="date" name="expected_date" class="form-control" value="<?= $v('expected_date') ?>"></div>
  <div class="col-md-6"><label class="form-label">Delivery address</label><input name="ship_to" class="form-control" maxlength="255" value="<?= $v('ship_to') ?>"></div>
  <div class="col-md-3"><label class="form-label">Delivery charge</label><input type="number" step="0.01" min="0" name="delivery_charge" class="form-control" value="<?= $v('delivery_charge') ?>"></div>
  <div class="col-md-3"><label class="form-label">Installation charge</label><input type="number" step="0.01" min="0" name="installation_charge" class="form-control" value="<?= $v('installation_charge') ?>"></div>
  <div class="col-md-8"><label class="form-label">Notes</label><input name="notes" class="form-control" maxlength="255" value="<?= $v('notes') ?>"></div>
  <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="allow_backorder" value="1" id="bo" <?= old('allow_backorder', $o['allow_backorder'] ?? 0) ? 'checked' : '' ?>>
    <label class="form-check-label" for="bo">Take the order even if stock is short <small class="text-muted d-block">(made-to-order / back-order)</small></label></div></div>
</div><p class="text-muted small mt-3 mb-0">Confirming the order <strong>reserves</strong> the stock, so it cannot be sold to someone else before this <?= e(term('customer', true)) ?>'s delivery.</p></div></div>
<?php require __DIR__ . '/_lines.php'; ?>
<button class="btn btn-primary">Save draft</button> <a class="btn btn-link" href="<?= $o ? url("sales/orders/{$o['id']}") : url('sales/orders') ?>">Cancel</a>
</form>
