<div class="row g-3"><div class="col-lg-5"><div class="card"><div class="card-body">
<form method="post" action="<?= $row ? url("customers/groups/{$row['id']}") : url('customers/groups') ?>"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label">Group name</label><input name="name" class="form-control" maxlength="80" value="<?= e(old('name', $row['name'] ?? '')) ?>" required></div>
  <div class="mb-3"><label class="form-label">Default discount %</label><input type="number" step="0.01" min="0" max="100" name="discount_pct" class="form-control" value="<?= e(old('discount_pct', $row['discount_pct'] ?? 0)) ?>">
    <div class="form-text">Applied automatically on the list price of items that have no special price for this group.</div></div>
  <button class="btn btn-primary">Save</button> <a class="btn btn-link" href="<?= url('customers/groups') ?>">Back</a>
</form></div></div></div>
<?php if ($row): ?>
<div class="col-lg-7"><div class="card"><div class="card-header">Special prices for <?= e($row['name']) ?></div>
  <div class="card-body">
    <form method="post" action="<?= url("customers/groups/{$row['id']}/prices") ?>" class="row g-2 align-items-end"><?= csrf_field() ?>
      <input type="hidden" name="variant_id" id="gpVariant">
      <div class="col-md-7 position-relative"><label class="form-label">Item</label><input id="gpSearch" class="form-control" placeholder="Search item, SKU or barcode" autocomplete="off">
        <div id="gpList" class="list-group position-absolute shadow-sm" style="z-index:20;min-width:320px" hidden></div></div>
      <div class="col-md-3"><label class="form-label">Price</label><input type="number" step="0.01" min="0" name="price" class="form-control" required></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Set</button></div>
    </form></div>
  <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Item</th><th class="text-end">List price</th><th class="text-end">Group price</th><th></th></tr></thead><tbody>
    <?php foreach ($prices as $p): ?><tr><td><?= e($p['item_name']) ?><?= $p['vname'] ? ' — ' . e($p['vname']) : '' ?> <small class="text-muted"><?= e($p['sku']) ?></small></td><td class="text-end"><?= e(money($p['sale_price'])) ?></td><td class="text-end"><strong><?= e(money($p['price'])) ?></strong></td>
      <td class="text-end"><form method="post" action="<?= url("customers/groups/{$row['id']}/prices/{$p['id']}/delete") ?>"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">×</button></form></td></tr><?php endforeach; ?>
    <?php if (!$prices): ?><tr><td colspan="4" class="text-muted">No special prices. The default discount applies.</td></tr><?php endif; ?></tbody></table></div></div></div>
<script>
(function () {
  var s = document.getElementById('gpSearch'), l = document.getElementById('gpList'), v = document.getElementById('gpVariant'), t = null;
  s.oninput = function () {
    v.value = ''; clearTimeout(t); var q = s.value.trim(); if (!q) { l.hidden = true; return; }
    t = setTimeout(function () {
      fetch('<?= url('lookup/sale-items') ?>?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        l.innerHTML = '';
        rows.forEach(function (x) {
          var b = document.createElement('button'); b.type = 'button'; b.className = 'list-group-item list-group-item-action py-1 small';
          b.textContent = x.item_name + (x.name ? ' — ' + x.name : '') + ' (' + x.sku + ') · list ' + x.price.toFixed(2);
          b.onclick = function () { v.value = x.id; s.value = b.textContent; l.hidden = true; };
          l.appendChild(b);
        });
        l.hidden = !rows.length;
      });
    }, 200);
  };
  s.onkeydown = function (e) { if (e.key === 'Enter') e.preventDefault(); };
})();
</script>
<?php endif; ?></div>
