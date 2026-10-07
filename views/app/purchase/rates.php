<?php
/** @var array $rows @var array $low @var int $supplier @var string $q @var string $show @var array $stats @var array $suppliers */
$canEdit = can('suppliers.edit');
$d = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
$self = 'purchase/rates' . ($_SERVER['QUERY_STRING'] ?? '' ? '?' . preg_replace('~[^A-Za-z0-9_=&%.\-]~', '', (string)$_SERVER['QUERY_STRING']) : '');
?>
<div class="row g-3 mb-3">
  <?php foreach ([['Current rates', $stats['current'], 'tags'], [term('suppliers'), $stats['suppliers'], 'truck'], [term('items') . ' with a rate', $stats['items'], 'box-seam'], ['Earlier rates kept', $stats['earlier'], 'clock-history']] as [$l, $n, $ic]): ?>
    <div class="col-6 col-lg-3"><div class="card"><div class="card-body d-flex align-items-center gap-3"><span class="rec-avatar" style="width:40px;height:40px"><i class="bi bi-<?= e($ic) ?>"></i></span><div><div class="fs-4 lh-1"><?= (int)$n ?></div><div class="small text-muted"><?= e($l) ?></div></div></div></div></div>
  <?php endforeach; ?>
</div>
<section class="card mb-3">
  <div class="card-header rl-head">
    <div><div class="rl-title"><i class="bi bi-currency-rupee"></i> Supplier rate lists</div><div class="rl-sub">What every <?= e(term('supplier', true)) ?> charges for each <?= e(term('item', true)) ?> · filled in automatically on purchase orders</div></div>
    <div class="rl-tools">
      <?php if (can('reports.view')): ?><a class="btn btn-outline-secondary" data-page-action href="<?= url('reports/rate-comparison') ?>"><i class="bi bi-bar-chart"></i><span class="d-none d-md-inline"> Compare suppliers</span></a><?php endif; ?>
      <?php if ($canEdit): ?>
        <button class="btn btn-outline-secondary" type="button" data-page-action data-bs-toggle="modal" data-bs-target="#rlImport"><i class="bi bi-upload"></i><span class="d-none d-md-inline"> Import CSV</span></button>
        <button class="btn btn-primary" type="button" data-page-action data-rate-new data-bs-toggle="modal" data-bs-target="#rateModal"><i class="bi bi-plus-lg"></i> Add rate</button>
      <?php endif; ?>
    </div>
  </div>
  <form class="rl-bar" method="get" action="<?= url('purchase/rates') ?>">
    <div class="rl-search"><i class="bi bi-search"></i><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search item, SKU, design or their code" aria-label="Search"></div>
    <select name="supplier" class="form-select form-select-sm" style="max-width:240px" onchange="this.form.submit()" aria-label="<?= e(term('supplier')) ?>"><option value="">All <?= e(term('suppliers', true)) ?></option>
      <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select>
    <div class="rec-tabs act-tabs" role="group"><button type="submit" name="show" value="current" class="<?= $show === 'current' ? 'on' : '' ?>">Current</button><button type="submit" name="show" value="all" class="<?= $show === 'all' ? 'on' : '' ?>">With earlier rates</button></div>
  </form>
  <?php if ($rows): ?><div class="table-responsive"><table class="table rl-table align-middle mb-0">
    <thead><tr><th><?= e(term('item')) ?></th><th><?= e(term('supplier')) ?></th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net rate</th><th class="text-end d-none d-md-table-cell">Min qty</th><th class="d-none d-lg-table-cell">Valid</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $name = $r['item_name'] . ($r['vname'] ? ' — ' . $r['vname'] : ''); $lowest = $r['is_current'] && isset($low[$r['variant_id']]) && abs($r['net'] - $low[$r['variant_id']]) < 0.005; ?>
      <tr class="<?= $r['is_current'] ? '' : 'rl-old' ?>">
        <td><a class="fw-medium" href="<?= url("items/{$r['item_id']}") ?>"><?= e($name) ?></a> <span class="text-muted small"><?= e($r['sku']) ?></span><?= $r['is_current'] ? '' : ' <span class="badge text-bg-light border fw-normal">Earlier</span>' ?></td>
        <td><a href="<?= url("suppliers/{$r['supplier_id']}#rates") ?>"><?= e($r['supplier_name']) ?></a><?= $r['supplier_code'] ? ' <span class="text-muted small">' . e($r['supplier_code']) . '</span>' : '' ?></td>
        <td class="text-end"><?= e(money($r['rate'])) ?></td>
        <td class="text-end"><?= (float)$r['discount_pct'] > 0 ? e($d($r['discount_pct'])) . '%' : '<span class="text-muted">—</span>' ?></td>
        <td class="text-end fw-semibold"><?= e(money($r['net'])) ?><?= $lowest ? ' <span class="badge text-bg-success fw-normal" title="Cheapest current rate for this item">Lowest</span>' : '' ?></td>
        <td class="text-end d-none d-md-table-cell"><?= (float)$r['min_qty'] > 0 ? e(qty($r['min_qty'])) : '<span class="text-muted">—</span>' ?></td>
        <td class="small text-nowrap d-none d-lg-table-cell"><?= e(fdate($r['valid_from'])) ?> <span class="text-muted">→</span> <?= $r['valid_to'] ? e(fdate($r['valid_to'])) : '<span class="text-muted">open</span>' ?></td>
        <td class="text-end text-nowrap">
          <?php if ($canEdit): ?>
            <?php if ($r['is_current']): ?><button type="button" class="btn btn-sm btn-outline-primary" data-rate-revise data-supplier="<?= (int)$r['supplier_id'] ?>" data-variant="<?= (int)$r['variant_id'] ?>" data-name="<?= e($name . ' (' . $r['sku'] . ')') ?>" data-rate="<?= e($r['rate']) ?>" data-disc="<?= e($r['discount_pct']) ?>" data-min="<?= e($r['min_qty']) ?>" data-code="<?= e($r['supplier_code'] ?? '') ?>" data-lead="<?= e($r['lead_time_days'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#rateModal">Revise</button><?php endif; ?>
            <form class="d-inline" method="post" action="<?= url("suppliers/{$r['supplier_id']}/rates/{$r['id']}/delete") ?>" onsubmit="return confirm('Remove this rate?')"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($self) ?>"><button class="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove rate"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
  <?php if (!$rows): ?>
    <div class="rl-empty"><i class="bi bi-currency-rupee"></i><div><b><?= ($q !== '' || $supplier) ? 'Nothing matches these filters' : 'No rates yet' ?></b><div class="text-muted small"><?= ($q !== '' || $supplier) ? 'Clear the search or choose another ' . e(term('supplier', true)) . '.' : 'Add what your ' . e(term('suppliers', true)) . ' quote. The rate fills in by itself when you pick the ' . e(term('item', true)) . ' on a purchase order.' ?></div></div>
      <?php if ($canEdit && $q === '' && !$supplier): ?><button class="btn btn-primary btn-sm ms-auto" type="button" data-rate-new data-bs-toggle="modal" data-bs-target="#rateModal"><i class="bi bi-plus-lg"></i> Add the first rate</button><?php endif; ?></div>
  <?php endif; ?>
</section>

<?php if ($canEdit): ?>
<div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" action="" id="rateForm" data-noload><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($self) ?>">
  <div class="modal-header"><h2 class="modal-title h5" id="rateModalTitle">Add a rate</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label"><?= e(term('supplier')) ?></label><select id="rSupplier" class="form-select" required><option value="">Choose…</option>
      <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3 position-relative"><label class="form-label"><?= e(term('item')) ?></label>
      <input type="hidden" name="variant_id" id="rVariant"><input id="rSearch" class="form-control" autocomplete="off" placeholder="Search by name, SKU or barcode…" required>
      <div class="list-group position-absolute shadow-sm w-100" id="rList" style="z-index:1060;max-height:220px;overflow:auto" hidden></div></div>
    <div class="row g-3">
      <div class="col-6"><label class="form-label">Rate</label><div class="input-group"><span class="input-group-text">₹</span><input name="rate" id="rRate" type="number" step="0.01" min="0" class="form-control" required></div></div>
      <div class="col-6"><label class="form-label">Discount</label><div class="input-group"><input name="discount_pct" id="rDisc" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="0"><span class="input-group-text">%</span></div></div>
      <div class="col-6"><label class="form-label">Valid from</label><input name="valid_from" type="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-6"><label class="form-label">Valid till <span class="text-muted fw-normal">(optional)</span></label><input name="valid_to" type="date" class="form-control"></div>
      <div class="col-4"><label class="form-label">Min. qty</label><input name="min_qty" id="rMin" type="number" step="0.001" min="0" class="form-control" placeholder="0"></div>
      <div class="col-4"><label class="form-label">Lead time</label><div class="input-group"><input name="lead_time_days" id="rLead" type="number" min="0" max="365" class="form-control"><span class="input-group-text">days</span></div></div>
      <div class="col-4"><label class="form-label">Their code</label><input name="supplier_code" id="rCode" maxlength="60" class="form-control"></div>
    </div>
    <div class="form-text mt-3"><i class="bi bi-info-circle"></i> A newer rate closes the old one on its start date; the old rate stays under “With earlier rates”.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save rate</button></div>
  </form></div></div></div>

<div class="modal fade" id="rlImport" tabindex="-1" aria-labelledby="rlImportTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" enctype="multipart/form-data" action="" id="rlImportForm"><?= csrf_field() ?><input type="hidden" name="return" value="<?= e($self) ?>">
  <div class="modal-header"><h2 class="modal-title h5" id="rlImportTitle">Import rates from a CSV file</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label"><?= e(term('supplier')) ?></label><select id="iSupplier" class="form-select" required><option value="">Choose…</option>
      <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
    <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
    <div class="form-text mt-2">Columns: <code>sku, rate, discount_pct, min_qty, lead_time_days, supplier_code, valid_from</code>. Only <b>sku</b> and <b>rate</b> are needed.</div>
    <a class="btn btn-sm btn-outline-secondary mt-3 disabled" id="iTemplate" href="#"><i class="bi bi-download"></i> Download the template</a>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Import</button></div>
  </form></div></div></div>
<script>
(function () {
  var base = <?= json_encode(rtrim(url('suppliers'), '/')) ?>, lookup = <?= json_encode(url('lookup/items')) ?>;
  var form = document.getElementById('rateForm'), sup = document.getElementById('rSupplier'), q = document.getElementById('rSearch'), vid = document.getElementById('rVariant'), list = document.getElementById('rList'), t = null;
  function setAction() { form.action = sup.value ? base + '/' + sup.value + '/rates' : ''; }
  sup.addEventListener('change', setAction); setAction();
  function reset(title) { document.getElementById('rateModalTitle').textContent = title; form.reset(); vid.value = ''; q.readOnly = false; sup.disabled = false; list.hidden = true; setAction(); }
  document.querySelectorAll('[data-rate-new]').forEach(function (b) { b.addEventListener('click', function () { reset('Add a rate'); }); });
  document.querySelectorAll('[data-rate-revise]').forEach(function (b) {
    b.addEventListener('click', function () {
      reset('Revise rate'); sup.value = b.dataset.supplier; setAction(); vid.value = b.dataset.variant; q.value = b.dataset.name; q.readOnly = true;
      document.getElementById('rRate').value = b.dataset.rate; document.getElementById('rDisc').value = parseFloat(b.dataset.disc) || '';
      document.getElementById('rMin').value = parseFloat(b.dataset.min) || ''; document.getElementById('rCode').value = b.dataset.code; document.getElementById('rLead').value = b.dataset.lead;
      sup.disabled = true;   // fixed while revising; the form action already carries the supplier
    });
  });
  document.getElementById('rateModal').addEventListener('shown.bs.modal', function () { (q.readOnly ? document.getElementById('rRate') : (sup.value ? q : sup)).focus(); });
  q.addEventListener('input', function () {
    vid.value = ''; clearTimeout(t); var s = q.value.trim(); if (!s) { list.hidden = true; return; }
    t = setTimeout(function () {
      fetch(lookup + '?q=' + encodeURIComponent(s), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
        list.innerHTML = '';
        res.forEach(function (v) { var a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action py-2 small'; a.textContent = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')'; a.onclick = function () { vid.value = v.id; q.value = a.textContent; list.hidden = true; document.getElementById('rRate').focus(); }; list.appendChild(a); });
        if (!res.length) { var d = document.createElement('div'); d.className = 'list-group-item small text-muted'; d.textContent = 'No items found'; list.appendChild(d); }
        list.hidden = false;
      });
    }, 200);
  });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  form.addEventListener('submit', function (e) {
    if (!sup.value) { e.preventDefault(); sup.focus(); return; }
    if (!vid.value) { e.preventDefault(); q.focus(); q.setCustomValidity('Pick an item from the list'); q.reportValidity(); setTimeout(function () { q.setCustomValidity(''); }, 1500); }
  });
  // import
  var is = document.getElementById('iSupplier'), imp = document.getElementById('rlImportForm'), tpl = document.getElementById('iTemplate');
  function setImp() { imp.action = is.value ? base + '/' + is.value + '/rates/import' : ''; tpl.href = is.value ? base + '/' + is.value + '/rates/template' : '#'; tpl.classList.toggle('disabled', !is.value); }
  is.addEventListener('change', setImp); setImp();
  imp.addEventListener('submit', function (e) { if (!is.value) { e.preventDefault(); is.focus(); } });
})();
</script>
<?php endif; ?>
