<form method="post" action="<?= url('sales/deliveries') ?>"><?= csrf_field() ?>
<?php if ($order): ?><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><?php endif; ?>
<div class="card mb-3"><div class="card-body"><div class="row g-3">
  <div class="col-md-4"><label class="form-label"><?= e(term('customer')) ?></label>
    <?php if ($order): ?><input class="form-control" value="<?= e($order['customer']) ?>" disabled><input type="hidden" id="customer_id" value="<?= (int)$order['customer_id'] ?>">
    <?php else: ?><select name="customer_id" id="customer_id" class="form-select" required><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)old('customer_id') === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
  <div class="col-md-3"><label class="form-label">Take stock from</label>
    <?php if ($order): ?><input class="form-control" value="<?php foreach ($warehouses as $w) if ((int)$w['id'] === (int)$order['warehouse_id']) echo e($w['name']); ?>" disabled>
    <?php else: ?><select name="warehouse_id" id="warehouse_id" class="form-select" required><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>" <?= (int)old('warehouse_id') === (int)$w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
  <div class="col-md-2"><label class="form-label">Delivery date</label><input type="date" name="delivery_date" class="form-control" value="<?= e(old('delivery_date', date('Y-m-d'))) ?>" required></div>
<?php if (ff('delivery.ship_to')): ?>  <div class="col-md-3"><label class="form-label">Deliver to<?= ffstar('delivery.ship_to') ?></label><input name="ship_to"<?= ffreq('delivery.ship_to') ?> class="form-control" maxlength="255" value="<?= e(old('ship_to', $order['ship_to'] ?? '')) ?>"></div><?php else: ?><?= ffh('delivery.ship_to', e(old('ship_to', $order['ship_to'] ?? ''))) ?><?php endif; ?>
<?php if (ff('delivery.note')): ?>  <div class="col-md-8"><label class="form-label">Note<?= ffstar('delivery.note') ?></label><input name="note"<?= ffreq('delivery.note') ?> class="form-control" maxlength="255" value="<?= e(old('note')) ?>"></div><?php else: ?><?= ffh('delivery.note', e(old('note'))) ?><?php endif; ?>
  <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="make_invoice" value="1" id="mk" <?= !isset($_SESSION['_old']) || old('make_invoice') ? 'checked' : '' ?>><label class="form-check-label" for="mk">Create the invoice now</label></div></div>
</div>
<p class="text-muted small mt-3 mb-0">Fabric: pick the <strong>roll</strong> each length is cut from. The oldest roll that can cover the quantity is suggested; if the quantity needs more than one roll you will see a shade warning. Set the quantity to 0 on lines you are not delivering now.</p></div></div>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><span><?= e(term('items')) ?></span>
  <?php if (!$order): ?><button type="button" class="btn btn-sm btn-outline-primary" id="addLine"><i class="bi bi-plus-lg"></i> Add <?= e(term('item', true)) ?></button><?php else: ?><button type="button" id="addLine" hidden></button><?php endif; ?></div>
<div class="table-responsive" style="overflow:visible"><table class="table mb-0 align-middle lines-wide" id="lineTable">
  <thead><tr><th><?= e(term('item')) ?></th><th>Roll (<?= e(term('batch', true)) ?>)</th><th><?= e(term('rack')) ?></th><th>Qty</th><th>Price</th><th>Disc %</th><th>Tax %</th><th></th></tr></thead><tbody></tbody></table></div></div>
<script type="application/json" id="initialLines"><?= json_encode($oldLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= asset('js/delivery-lines.js') ?>" data-stock="<?= url('lookup/stock') ?>" data-lookup="<?= url('lookup/sale-items') ?>" data-order="<?= $order ? 1 : 0 ?>" data-warehouse="<?= (int)($order['warehouse_id'] ?? 0) ?>"></script>
<button class="btn btn-success">Deliver &amp; take stock out</button> <a class="btn btn-link" href="<?= $order ? url("sales/orders/{$order['id']}") : url('sales/deliveries') ?>">Cancel</a>
</form>
