<?php /** Product master of one supplier: what they supply and what THEY call it. @var array $s @var array $products */
$canEdit = can('suppliers.edit');
$d = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<section class="card mb-3" id="products">
  <div class="card-header rl-head">
    <div><div class="rl-title"><i class="bi bi-box-seam"></i> Products supplied <span class="act-n"><?= count($products) ?></span></div>
      <div class="rl-sub">The <?= e(term('supplier', true)) ?>'s own product names and codes. Purchase orders and rates are made against these.</div></div>
    <div class="rl-tools"><?php if ($canEdit): ?><button class="btn btn-sm btn-primary" type="button" data-prod-new data-bs-toggle="modal" data-bs-target="#prodModal"><i class="bi bi-plus-lg"></i> Add product</button><?php endif; ?></div>
  </div>
  <?php if ($products): ?>
  <div class="rl-bar"><div class="rl-search"><i class="bi bi-search"></i><input type="search" id="prFilter" placeholder="Search their name, code or ours" aria-label="Filter products"></div></div>
  <div class="table-responsive"><table class="table rl-table align-middle mb-0" id="prTable">
    <thead><tr><th>Their product</th><th><?= e(term('item')) ?> (ours)</th><th class="text-end">Rate</th><th class="text-end d-none d-md-table-cell">Last paid</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): $ours = $p['item_name'] . ($p['vname'] ? ' — ' . $p['vname'] : ''); ?>
      <tr data-search="<?= e(mb_strtolower(($p['supplier_name'] ?? '') . ' ' . ($p['supplier_code'] ?? '') . ' ' . $ours . ' ' . $p['sku'])) ?>" class="<?= $p['v_active'] ? '' : 'text-muted' ?>">
        <td><?php if ($p['supplier_name'] || $p['supplier_code']): ?><div class="fw-medium"><?= e($p['supplier_name'] ?: $p['supplier_code']) ?></div><?= $p['supplier_name'] && $p['supplier_code'] ? '<div class="small text-muted">Code ' . e($p['supplier_code']) . '</div>' : '' ?>
          <?php else: ?><span class="text-muted small">Same as ours</span><?php endif; ?></td>
        <td><a href="<?= url("items/{$p['item_id']}") ?>"><?= e($ours) ?></a> <span class="text-muted small"><?= e($p['sku']) ?></span></td>
        <td class="text-end"><?= $p['net'] !== null ? '<span class="fw-semibold">' . e(money($p['net'])) . '</span>' . ((float)$p['rate_row']['discount_pct'] > 0 ? '<div class="small text-muted">' . e(money($p['rate_row']['rate'])) . ' less ' . e($d($p['rate_row']['discount_pct'])) . '%</div>' : '') : '<span class="text-muted small">No rate yet</span>' ?></td>
        <td class="text-end d-none d-md-table-cell"><?= $p['last_paid'] !== null ? e(money($p['last_paid'])) . '<div class="small text-muted">' . e(fdate($p['last_on'])) . '</div>' : '<span class="text-muted">—</span>' ?></td>
        <td class="text-end text-nowrap">
          <?php if ($canEdit): ?>
            <button type="button" class="btn btn-sm btn-outline-primary" data-prod-edit data-bs-toggle="modal" data-bs-target="#prodModal" data-variant="<?= (int)$p['variant_id'] ?>" data-name="<?= e($ours . ' (' . $p['sku'] . ')') ?>" data-sname="<?= e($p['supplier_name'] ?? '') ?>" data-scode="<?= e($p['supplier_code'] ?? '') ?>" data-note="<?= e($p['note'] ?? '') ?>">Edit</button>
            <form class="d-inline" method="post" action="<?= url("suppliers/{$s['id']}/products/{$p['id']}/delete") ?>" onsubmit="return confirm('Remove this product from the list? Its rate history stays.')"><?= csrf_field() ?><button class="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove product"><i class="bi bi-trash"></i></button></form>
          <?php endif; ?>
        </td></tr>
    <?php endforeach; ?>
    <tr id="prNone" hidden><td colspan="5" class="text-muted py-3">Nothing matches.</td></tr>
    </tbody></table></div>
  <?php else: ?>
    <div class="rl-empty"><i class="bi bi-box-seam"></i><div><b>No products yet</b><div class="text-muted small">List what this <?= e(term('supplier', true)) ?> supplies, with the name and code <i>they</i> use. Then pick them by that name on a purchase order.</div></div>
      <?php if ($canEdit): ?><button class="btn btn-primary btn-sm ms-auto" type="button" data-prod-new data-bs-toggle="modal" data-bs-target="#prodModal"><i class="bi bi-plus-lg"></i> Add the first product</button><?php endif; ?></div>
  <?php endif; ?>
</section>

<?php if ($canEdit): ?>
<div class="modal fade" id="prodModal" tabindex="-1" aria-labelledby="prodModalTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" action="<?= url("suppliers/{$s['id']}/products") ?>" id="prodForm" data-noload><?= csrf_field() ?><input type="hidden" name="edit" id="pEdit" value="">
  <div class="modal-header"><h2 class="modal-title h5" id="prodModalTitle">Add a product</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <div class="mb-3 position-relative"><label class="form-label">Our <?= e(strtolower(term('item'))) ?></label>
      <input type="hidden" name="variant_id" id="pVariant"><input id="pSearch" class="form-control" autocomplete="off" placeholder="Search by name, SKU or barcode…" required>
      <div class="list-group position-absolute shadow-sm w-100" id="pList" style="z-index:1060;max-height:220px;overflow:auto" hidden></div>
      <div class="form-text">Not in your items yet? Add it under Inventory → Items first.</div></div>
    <div class="row g-3">
      <div class="col-8"><label class="form-label">Their product name</label><input name="supplier_name" id="pSName" maxlength="150" class="form-control" placeholder="What the <?= e(term('supplier', true)) ?> calls it"></div>
      <div class="col-4"><label class="form-label">Their code</label><input name="supplier_code" id="pSCode" maxlength="60" class="form-control"></div>
      <div class="col-12"><label class="form-label">Note <span class="text-muted fw-normal">(optional)</span></label><input name="note" id="pNote" maxlength="150" class="form-control"></div>
    </div>
    <div class="rl-modal-sep"><span>Rate <span class="text-muted fw-normal">(optional — leave empty to add the rate later)</span></span></div>
    <div class="row g-3">
      <div class="col-6"><label class="form-label">Rate</label><div class="input-group"><span class="input-group-text">₹</span><input name="rate" type="number" step="0.01" min="0" class="form-control"></div></div>
      <div class="col-6"><label class="form-label">Discount</label><div class="input-group"><input name="discount_pct" type="number" step="0.01" min="0" max="100" class="form-control" placeholder="0"><span class="input-group-text">%</span></div></div>
      <div class="col-4"><label class="form-label">Min. qty</label><input name="min_qty" type="number" step="0.001" min="0" class="form-control" placeholder="0"></div>
      <div class="col-4"><label class="form-label">Lead time</label><div class="input-group"><input name="lead_time_days" type="number" min="0" max="365" class="form-control"><span class="input-group-text">days</span></div></div>
      <div class="col-4"><label class="form-label">Valid from</label><input name="valid_from" type="date" class="form-control" value="<?= e(date('Y-m-d')) ?>"></div>
    </div>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save product</button></div>
  </form></div></div></div>
<?php endif; ?>
<script>
(function () {
  var rows = document.querySelectorAll('#prTable tbody tr[data-search]'), none = document.getElementById('prNone'), f = document.getElementById('prFilter');
  if (f) f.addEventListener('input', function () { var q = f.value.trim().toLowerCase(), n = 0; rows.forEach(function (r) { var ok = !q || r.dataset.search.indexOf(q) > -1; r.hidden = !ok; if (ok) n++; }); none.hidden = n > 0; });
  var q = document.getElementById('pSearch'); if (!q) return;
  var list = document.getElementById('pList'), vid = document.getElementById('pVariant'), t = null, form = document.getElementById('prodForm'), modal = document.getElementById('prodModal');
  function reset(title, edit) { document.getElementById('prodModalTitle').textContent = title; form.reset(); vid.value = ''; q.readOnly = edit; list.hidden = true; document.getElementById('pEdit').value = edit ? '1' : ''; }
  document.querySelectorAll('[data-prod-new]').forEach(function (b) { b.addEventListener('click', function () { reset('Add a product', false); }); });
  document.querySelectorAll('[data-prod-edit]').forEach(function (b) {
    b.addEventListener('click', function () {
      reset('Edit product', true); vid.value = b.dataset.variant; q.value = b.dataset.name;
      document.getElementById('pSName').value = b.dataset.sname; document.getElementById('pSCode').value = b.dataset.scode; document.getElementById('pNote').value = b.dataset.note;
    });
  });
  modal.addEventListener('shown.bs.modal', function () { (q.readOnly ? document.getElementById('pSName') : q).focus(); });
  q.addEventListener('input', function () {
    vid.value = ''; clearTimeout(t); var s = q.value.trim(); if (!s) { list.hidden = true; return; }
    t = setTimeout(function () {
      fetch(<?= json_encode(url('lookup/items')) ?> + '?q=' + encodeURIComponent(s), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
        list.innerHTML = '';
        res.forEach(function (v) {
          var a = document.createElement('button'); a.type = 'button'; a.className = 'list-group-item list-group-item-action py-2 small';
          a.textContent = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
          a.onclick = function () { vid.value = v.id; q.value = a.textContent; list.hidden = true; document.getElementById('pSName').focus(); };
          list.appendChild(a);
        });
        if (!res.length) { var d = document.createElement('div'); d.className = 'list-group-item small text-muted'; d.textContent = 'No items found'; list.appendChild(d); }
        list.hidden = false;
      });
    }, 180);
  });
  form.addEventListener('submit', function (e) { if (!vid.value) { e.preventDefault(); q.focus(); q.setCustomValidity('Pick an item from the list'); q.reportValidity(); q.setCustomValidity(''); } });
  document.addEventListener('mousedown', function (e) { if (!list.contains(e.target) && e.target !== q) list.hidden = true; });
})();
</script>
