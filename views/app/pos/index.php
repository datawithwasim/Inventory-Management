<div id="msg" class="alert alert-danger d-none"></div>
<div class="row g-3">
  <div class="col-lg-8">
    <div class="card mb-3"><div class="card-body position-relative">
      <input id="scan" class="form-control form-control-lg" placeholder="Scan barcode or type item name / SKU, then Enter" autocomplete="off">
      <div id="results" class="list-group position-absolute shadow" style="z-index:30;left:16px;right:16px;max-height:300px;overflow:auto" hidden></div>
    </div></div>
    <div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
      <thead><tr><th>Item</th><th style="width:110px">Qty</th><th style="width:110px">Price</th><th style="width:85px">Disc %</th><th class="text-end">Total</th><th></th></tr></thead><tbody id="cart"></tbody></table></div></div>
  </div>
  <div class="col-lg-4"><div class="card"><div class="card-body">
    <div class="mb-2"><label class="form-label small mb-0">Customer</label><select id="customer" class="form-select"><?php foreach ($customers as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$walkIn ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label small mb-0">Take stock from</label><select id="wh" class="form-select"><?php foreach ($warehouses as $w): ?><option value="<?= (int)$w['id'] ?>"><?= e($w['name']) ?></option><?php endforeach; ?></select></div>
    <table class="table table-sm table-borderless mb-2"><tr><td>Discount</td><td class="text-end" id="tDisc">0.00</td></tr><tr><td>Tax</td><td class="text-end" id="tTax">0.00</td></tr>
      <tr class="border-top"><th class="fs-5">Total</th><th class="text-end fs-4" id="tTotal">0.00</th></tr></table>
    <div class="row g-2 mb-2"><div class="col-6"><label class="form-label small mb-0">Method</label><select id="method" class="form-select"><?php foreach ($methods as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?></select></div>
      <div class="col-6"><label class="form-label small mb-0">Received</label><input id="amount" type="number" step="0.01" min="0" class="form-control text-end"></div>
      <div class="col-12"><input id="reference" class="form-control form-control-sm" placeholder="Reference (UPI / card slip)" maxlength="80"></div></div>
    <div id="change" class="text-end text-muted mb-3">&nbsp;</div>
    <button id="pay" class="btn btn-success btn-lg w-100" disabled>Complete sale (F9)</button>
    <button id="clear" type="button" class="btn btn-link w-100 text-danger mt-1">Clear cart</button>
  </div></div></div>
</div>
<script src="<?= asset('js/pos.js') ?>" data-one="<?= url('lookup/sale-item') ?>" data-search="<?= url('lookup/sale-items') ?>" data-checkout="<?= url('pos/checkout') ?>"></script>
