<?php /** Rate list card on the supplier page. @var array $s @var array $rates */
$cur = array_values(array_filter($rates, fn($r) => $r['is_current']));
$hist = count($rates) - count($cur);
$canEdit = can('suppliers.edit');
$d = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<section class="card mb-3" id="rates">
  <div class="card-header rl-head">
    <div><div class="rl-title"><i class="bi bi-tags"></i> Rate list <span class="act-n"><?= count($cur) ?></span></div><div class="rl-sub">What this <?= e(term('supplier', true)) ?> charges us · <?= count($cur) ?> current<?= $hist ? ' · ' . $hist . ' earlier' : '' ?></div></div>
    <div class="rl-tools">
      <?php if (can('reports.view')): ?><a class="btn btn-sm btn-outline-secondary" href="<?= url('reports/rate-history?supplier=' . (int)$s['id']) ?>"><i class="bi bi-clock-history"></i><span class="d-none d-md-inline"> History report</span></a><?php endif; ?>
      <?php if ($canEdit): ?>
        <div class="dropdown"><button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-label="Import options"><i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-md-inline"> CSV</span></button>
          <ul class="dropdown-menu dropdown-menu-end shadow"><li><a class="dropdown-item" href="<?= url("suppliers/{$s['id']}/rates/template") ?>"><i class="bi bi-download me-2 text-muted"></i>Download template</a></li>
            <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#rateImportModal"><i class="bi bi-upload me-2 text-muted"></i>Import rates…</button></li></ul></div>
        <button class="btn btn-sm btn-primary" type="button" data-rate-new data-bs-toggle="modal" data-bs-target="#rateModal"><i class="bi bi-plus-lg"></i> Add rate</button>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($rates): ?>
  <div class="rl-bar">
    <div class="rl-search"><i class="bi bi-search"></i><input type="search" id="rlFilter" placeholder="Filter this list" aria-label="Filter rates"></div>
    <?php if ($hist): ?><label class="form-check form-switch m-0 small text-muted"><input class="form-check-input" type="checkbox" id="rlHist"> <span>Show earlier rates (<?= $hist ?>)</span></label><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="table-responsive"><table class="table rl-table align-middle mb-0" id="rlTable">
    <thead><tr><th><?= e(term('item')) ?></th><th class="text-end">Rate</th><th class="text-end">Disc.</th><th class="text-end">Net rate</th><th class="text-end d-none d-md-table-cell">Min qty</th><th class="d-none d-lg-table-cell">Valid</th><th class="d-none d-lg-table-cell">Code</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rates as $r): $name = $r['item_name'] . ($r['vname'] ? ' — ' . $r['vname'] : ''); ?>
      <tr class="<?= $r['is_current'] ? '' : 'rl-old' ?>" <?= $r['is_current'] ? '' : 'hidden' ?> data-search="<?= e(mb_strtolower($name . ' ' . $r['sku'] . ' ' . ($r['supplier_code'] ?? ''))) ?>">
        <td><a class="fw-medium" href="<?= url("items/{$r['item_id']}") ?>"><?= e($name) ?></a> <span class="text-muted small"><?= e($r['sku']) ?></span><?= $r['is_current'] ? '' : ' <span class="badge text-bg-light border fw-normal">Earlier</span>' ?></td>
        <td class="text-end"><?= e(money($r['rate'])) ?></td>
        <td class="text-end"><?= (float)$r['discount_pct'] > 0 ? e($d($r['discount_pct'])) . '%' : '<span class="text-muted">—</span>' ?></td>
        <td class="text-end fw-semibold"><?= e(money($r['net'])) ?></td>
        <td class="text-end d-none d-md-table-cell"><?= (float)$r['min_qty'] > 0 ? e(qty($r['min_qty'])) : '<span class="text-muted">—</span>' ?></td>
        <td class="small text-nowrap d-none d-lg-table-cell"><?= e(fdate($r['valid_from'])) ?> <span class="text-muted">→</span> <?= $r['valid_to'] ? e(fdate($r['valid_to'])) : '<span class="text-muted">open</span>' ?></td>
        <td class="small d-none d-lg-table-cell"><?= e($r['supplier_code'] ?? '') ?></td>
        <td class="text-end text-nowrap">
          <?php if ($canEdit): ?>
            <?php if ($r['is_current']): ?><button type="button" class="btn btn-sm btn-outline-primary" data-rate-revise data-variant="<?= (int)$r['variant_id'] ?>" data-name="<?= e($name . ' (' . $r['sku'] . ')') ?>" data-rate="<?= e($r['rate']) ?>" data-disc="<?= e($r['discount_pct']) ?>" data-min="<?= e($r['min_qty']) ?>" data-code="<?= e($r['supplier_code'] ?? '') ?>" data-lead="<?= e($r['lead_time_days'] ?? '') ?>" data-bs-toggle="modal" data-bs-target="#rateModal">Revise</button><?php endif; ?>
            <form class="d-inline" method="post" action="<?= url("suppliers/{$s['id']}/rates/{$r['id']}/delete") ?>" onsubmit="return confirm('Remove this rate?')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove rate"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    <tr id="rlNone" hidden><td colspan="8" class="text-muted py-3">Nothing matches.</td></tr>
    </tbody></table></div>
  <?php if (!$rates): ?>
    <div class="rl-empty"><i class="bi bi-tags"></i><div><b>No rates yet</b><div class="text-muted small">Add what this <?= e(term('supplier', true)) ?> quotes. The rate fills in by itself when you pick the <?= e(term('item', true)) ?> on a purchase order.</div></div>
      <?php if ($canEdit): ?><button class="btn btn-primary btn-sm ms-auto" type="button" data-rate-new data-bs-toggle="modal" data-bs-target="#rateModal"><i class="bi bi-plus-lg"></i> Add the first rate</button><?php endif; ?></div>
  <?php endif; ?>
</section>

<?php if ($canEdit): ?>
<div class="modal fade" id="rateModal" tabindex="-1" aria-labelledby="rateModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" action="<?= url("suppliers/{$s['id']}/rates") ?>" id="rateForm" data-noload><?= csrf_field() ?>
  <div class="modal-header"><h2 class="modal-title h5" id="rateModalTitle">Add a rate</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div class="mb-3 position-relative"><label class="form-label"><?= e(term('item')) ?></label>
      <input type="hidden" name="variant_id" id="rVariant"><input id="rSearch" class="form-control" autocomplete="off" placeholder="Search by name, SKU or barcode…" required>
      <div class="list-group position-absolute shadow-sm w-100" id="rList" style="z-index:1060;max-height:220px;overflow:auto" hidden></div></div>
    <div class="row g-3">
      <div class="col-6"><label class="form-label">Rate</label><div class="input-group"><span class="input-group-text">₹</span><input name="rate" id="rRate" type="number" step="0.01" min="0" class="form-control" required></div></div>
      <div class="col-6"><label class="form-label">Discount</label><div class="input-group"><input name="discount_pct" id="rDisc" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="0"><span class="input-group-text">%</span></div></div>
      <div class="col-6"><label class="form-label">Valid from</label><input name="valid_from" type="date" class="form-control" value="<?= e(date('Y-m-d')) ?>" required></div>
      <div class="col-6"><label class="form-label">Valid till <span class="text-muted fw-normal">(optional)</span></label><input name="valid_to" type="date" class="form-control"></div>
      <div class="col-4"><label class="form-label">Min. qty</label><input name="min_qty" id="rMin" type="number" step="0.001" min="0" class="form-control" placeholder="0"></div>
      <div class="col-4"><label class="form-label">Lead time</label><div class="input-group"><input name="lead_time_days" id="rLead" type="number" min="0" max="365" class="form-control" placeholder="<?= (int)$s['lead_time_days'] ?>"><span class="input-group-text">days</span></div></div>
      <div class="col-4"><label class="form-label">Their code</label><input name="supplier_code" id="rCode" maxlength="60" class="form-control"></div>
    </div>
    <div class="form-text mt-3"><i class="bi bi-info-circle"></i> A newer rate closes the old one on its start date. The old rate stays in the list under “Show earlier rates”.</div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save rate</button></div>
  </form></div></div></div>

<div class="modal fade" id="rateImportModal" tabindex="-1" aria-labelledby="rateImportTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" enctype="multipart/form-data" action="<?= url("suppliers/{$s['id']}/rates/import") ?>"><?= csrf_field() ?>
  <div class="modal-header"><h2 class="modal-title h5" id="rateImportTitle">Import rates from a CSV file</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
    <div class="form-text mt-2">Columns: <code>sku, rate, discount_pct, min_qty, lead_time_days, supplier_code, valid_from</code>. Only <b>sku</b> and <b>rate</b> are needed; a blank date means today. Rows are matched by SKU.</div>
    <a class="btn btn-sm btn-outline-secondary mt-3" href="<?= url("suppliers/{$s['id']}/rates/template") ?>"><i class="bi bi-download"></i> Download the template</a>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Import</button></div>
  </form></div></div></div>
<?php endif; ?>
<script>
(function () {
  var rows = document.querySelectorAll('#rlTable tbody tr[data-search]'), none = document.getElementById('rlNone'), f = document.getElementById('rlFilter'), h = document.getElementById('rlHist');
  function apply() {
    var q = f ? f.value.trim().toLowerCase() : '', showOld = h && h.checked, n = 0;
    rows.forEach(function (r) { var ok = (!r.classList.contains('rl-old') || showOld) && (!q || r.dataset.search.indexOf(q) > -1); r.hidden = !ok; if (ok) n++; });
    if (none) none.hidden = n > 0 || !rows.length;
  }
  if (f) f.addEventListener('input', apply); if (h) h.addEventListener('change', apply);
  var q = document.getElementById('rSearch'); if (!q) return;
  var list = document.getElementById('rList'), vid = document.getElementById('rVariant'), t = null, modal = document.getElementById('rateModal');
  function reset(title) { document.getElementById('rateModalTitle').textContent = title; document.getElementById('rateForm').reset(); vid.value = ''; q.readOnly = false; list.hidden = true; }
  document.querySelectorAll('[data-rate-new]').forEach(function (b) { b.addEventListener('click', function () { reset('Add a rate'); }); });
  document.querySelectorAll('[data-rate-revise]').forEach(function (b) {
    b.addEventListener('click', function () {
      reset('Revise rate'); vid.value = b.dataset.variant; q.value = b.dataset.name; q.readOnly = true;
      document.getElementById('rRate').value = b.dataset.rate; document.getElementById('rDisc').value = parseFloat(b.dataset.disc) || '';
      document.getElementById('rMin').value = parseFloat(b.dataset.min) || ''; document.getElementById('rCode').value = b.dataset.code; document.getElementById('rLead').value = b.dataset.lead;
    });
  });
  modal.addEventListener('shown.bs.modal', function () { (q.readOnly ? document.getElementById('rRate') : q).focus(); });
  q.addEventListener('input', function () {
    vid.value = ''; clearTimeout(t); var s = q.value.trim(); if (!s) { list.hidden = true; return; }
    t = setTimeout(function () {
      fetch(<?= json_encode(url('lookup/items')) ?> + '?q=' + encodeURIComponent(s), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
        list.innerHTML = '';
        res.forEach(function (v) {
          var a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action py-2 small';
          a.textContent = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
          a.onclick = function () { vid.value = v.id; q.value = a.textContent; list.hidden = true; document.getElementById('rRate').focus(); };
          list.appendChild(a);
        });
        if (!res.length) { var d = document.createElement('div'); d.className = 'list-group-item small text-muted'; d.textContent = 'No items found'; list.appendChild(d); }
        list.hidden = false;
      });
    }, 200);
  });
  q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
  document.getElementById('rateForm').addEventListener('submit', function (e) { if (!vid.value) { e.preventDefault(); q.focus(); q.setCustomValidity('Pick an item from the list'); q.reportValidity(); setTimeout(function () { q.setCustomValidity(''); }, 1500); } });
})();
</script>
