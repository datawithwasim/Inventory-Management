<?php /** Rate list card on the supplier page. @var array $s @var array $rates */ ?>
<div class="card mb-3" id="rates">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><span><i class="bi bi-tags me-1"></i>Rate list — what this <?= e(term('supplier', true)) ?> charges us</span>
    <?php if (can('suppliers.edit')): ?><span class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="<?= url("suppliers/{$s['id']}/rates/template") ?>"><i class="bi bi-download"></i> CSV template</a>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#rateImport"><i class="bi bi-upload"></i> Import CSV</button>
      <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#rateAdd"><i class="bi bi-plus-lg"></i> Add rate</button></span><?php endif; ?></div>
  <?php if (can('suppliers.edit')): ?>
  <div class="collapse border-bottom" id="rateImport"><form class="p-3 row g-2 align-items-end" method="post" enctype="multipart/form-data" action="<?= url("suppliers/{$s['id']}/rates/import") ?>"><?= csrf_field() ?>
    <div class="col-md-6"><label class="form-label small mb-1">CSV file (columns: sku, rate, discount_pct, min_qty, lead_time_days, supplier_code, valid_from)</label><input type="file" name="file" accept=".csv,text/csv" class="form-control form-control-sm" required></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Import rates</button></div>
    <div class="col-12 small text-muted">Only <strong>sku</strong> and <strong>rate</strong> are needed. Items are matched by SKU; a blank date means today.</div></form></div>
  <div class="collapse border-bottom" id="rateAdd"><form class="p-3 row g-2 align-items-end" method="post" action="<?= url("suppliers/{$s['id']}/rates") ?>" id="rateForm"><?= csrf_field() ?>
    <div class="col-md-5 position-relative"><label class="form-label small mb-1"><?= e(term('item')) ?> (name, SKU or barcode)</label>
      <input type="hidden" name="variant_id" id="rVariant"><input id="rSearch" class="form-control form-control-sm" autocomplete="off" placeholder="Start typing…" required>
      <div class="list-group position-absolute shadow-sm" id="rList" style="z-index:20;max-height:240px;overflow:auto;min-width:300px" hidden></div></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Rate</label><input name="rate" type="number" step="0.01" min="0" class="form-control form-control-sm" required></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Discount %</label><input name="discount_pct" type="number" step="0.01" min="0" max="100" class="form-control form-control-sm" placeholder="0"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Valid from</label><input name="valid_from" type="date" class="form-control form-control-sm" value="<?= e(date('Y-m-d')) ?>" required></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Min. qty</label><input name="min_qty" type="number" step="0.001" min="0" class="form-control form-control-sm" placeholder="0"></div>
    <div class="col-6 col-md-2"><label class="form-label small mb-1">Lead time (days)</label><input name="lead_time_days" type="number" min="0" max="365" class="form-control form-control-sm" placeholder="<?= (int)$s['lead_time_days'] ?>"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Supplier's own code</label><input name="supplier_code" maxlength="60" class="form-control form-control-sm"></div>
    <div class="col-6 col-md-3"><label class="form-label small mb-1">Valid till (optional)</label><input name="valid_to" type="date" class="form-control form-control-sm"></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">Save rate</button></div>
    <div class="col-12 small text-muted">Adding a newer rate for the same item closes the old one — the old rate stays in the history below.</div></form></div>
  <?php endif; ?>
  <div class="table-responsive"><table class="table table-sm align-middle mb-0">
    <thead><tr><th><?= e(term('item')) ?></th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net</th><th class="text-end">Min qty</th><th>Valid</th><th>Code</th><th></th></tr></thead><tbody>
    <?php foreach ($rates as $r): ?>
      <tr class="<?= $r['is_current'] ? '' : 'text-muted' ?>">
        <td><a href="<?= url("items/{$r['item_id']}") ?>"><?= e($r['item_name'] . ($r['vname'] ? ' — ' . $r['vname'] : '')) ?></a> <span class="small text-muted"><?= e($r['sku']) ?></span> <?= $r['is_current'] ? '<span class="badge text-bg-success">Current</span>' : '<span class="badge text-bg-light border">History</span>' ?></td>
        <td class="text-end"><?= e(money($r['rate'])) ?></td><td class="text-end"><?= (float)$r['discount_pct'] > 0 ? e(rtrim(rtrim(number_format((float)$r['discount_pct'], 2), '0'), '.')) . '%' : '—' ?></td>
        <td class="text-end fw-semibold"><?= e(money($r['net'])) ?></td><td class="text-end"><?= (float)$r['min_qty'] > 0 ? e(qty($r['min_qty'])) : '—' ?></td>
        <td class="small"><?= e(fdate($r['valid_from'])) ?> → <?= $r['valid_to'] ? e(fdate($r['valid_to'])) : 'open' ?></td><td class="small"><?= e($r['supplier_code'] ?? '') ?></td>
        <td class="text-end"><?php if (can('suppliers.edit')): ?><form method="post" action="<?= url("suppliers/{$s['id']}/rates/{$r['id']}/delete") ?>" onsubmit="return confirm('Remove this rate?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger" title="Remove">&times;</button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$rates): ?><tr><td colspan="8" class="text-muted">No rates yet. Add the rates this <?= e(term('supplier', true)) ?> quotes — they fill in automatically on purchase orders.</td></tr><?php endif; ?>
    </tbody></table></div>
</div>
<script>
(function () {
  var q = document.getElementById('rSearch'); if (!q) return;
  var list = document.getElementById('rList'), vid = document.getElementById('rVariant'), t = null;
  q.addEventListener('input', function () {
    vid.value = ''; clearTimeout(t); var s = q.value.trim(); if (!s) { list.hidden = true; return; }
    t = setTimeout(function () {
      fetch(<?= json_encode(url('lookup/items')) ?> + '?q=' + encodeURIComponent(s), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        list.innerHTML = '';
        rows.forEach(function (v) {
          var a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action py-1 small';
          a.textContent = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
          a.onclick = function () { vid.value = v.id; q.value = a.textContent; list.hidden = true; };
          list.appendChild(a);
        });
        if (!rows.length) { var d = document.createElement('div'); d.className = 'list-group-item small text-muted'; d.textContent = 'No items found'; list.appendChild(d); }
        list.hidden = false;
      });
    }, 200);
  });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  document.getElementById('rateForm').addEventListener('submit', function (e) { if (!vid.value) { e.preventDefault(); q.focus(); q.setCustomValidity('Pick an item from the list'); q.reportValidity(); setTimeout(function () { q.setCustomValidity(''); }, 1500); } });
})();
</script>
