<?php $col = fn($k) => in_array($k, $cols, true); $canCreate = can('suppliers.create'); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search their name or code, our item, SKU, supplier" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="supplier" class="form-select" onchange="this.form.submit()"><option value="">All <?= e(term('suppliers', true)) ?></option>
    <?php foreach ($suppliers as $s): ?><option value="<?= (int)$s['id'] ?>" <?= $supplier === (int)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-md-2"><select name="link" class="form-select" onchange="this.form.submit()"><option value="">Linked or not</option><option value="linked" <?= $link === 'linked' ? 'selected' : '' ?>>Linked to our item</option><option value="unlinked" <?= $link === 'unlinked' ? 'selected' : '' ?>>Not linked yet</option></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col text-end"><?= Core\Columns::picker('supplier_items', $cols) ?>
    <?php if ($canCreate): ?><a class="btn btn-outline-secondary" data-page-action href="<?= url('purchase/supplier-items/export') ?>">Export</a>
    <button type="button" class="btn btn-outline-secondary" data-page-action data-bs-toggle="modal" data-bs-target="#siImport">Import</button>
    <a class="btn btn-primary" href="<?= url('purchase/supplier-items/create' . ($supplier ? '?supplier=' . $supplier : '')) ?>"><i class="bi bi-plus-lg"></i> Add supplier item</a><?php endif; ?></div>
</form>
<?php if ($unlinked && $link !== 'unlinked'): ?><div class="alert alert-warning py-2 small"><i class="bi bi-link-45deg"></i> <?= (int)$unlinked ?> supplier item(s) are not linked to one of your items yet, so they cannot be used on a purchase order. <a href="<?= url('purchase/supplier-items?link=unlinked') ?>">Show them</a></div><?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Supplier item</th><th><?= e(term('supplier')) ?></th>
    <?php if ($col('code')): ?><th>Code</th><?php endif; ?><?php if ($col('our_item')): ?><th>Our item</th><?php endif; ?><?php if ($col('rate')): ?><th class="text-end">Current rate</th><?php endif; ?>
    <?php if ($col('moq')): ?><th class="text-end">Min order</th><?php endif; ?><?php if ($col('lead')): ?><th class="text-end">Lead time</th><?php endif; ?>
    <?php foreach ($cfList as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?><?php if ($col('preferred')): ?><th>Preferred</th><?php endif; ?><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>">
      <td><a class="fw-medium" href="<?= url("purchase/supplier-items/{$r['id']}") ?>"><?= e($r['supplier_name'] ?: $r['supplier_code']) ?></a><?= $r['is_active'] ? '' : ' <span class="badge text-bg-dark">Inactive</span>' ?></td>
      <td><a href="<?= url("suppliers/{$r['supplier_id']}") ?>"><?= e($r['supplier']) ?></a></td>
      <?php if ($col('code')): ?><td><?= e($r['supplier_code'] ?? '') ?></td><?php endif; ?>
      <?php if ($col('our_item')): ?><td><?php if ($r['variant_id']): ?><a href="<?= url("items/{$r['item_id']}") ?>"><?= e(App\Controllers\SupplierItemController::ourName($r)) ?></a> <span class="text-muted small"><?= e($r['sku']) ?></span><?php else: ?><span class="badge text-bg-warning">Not linked</span><?php endif; ?></td><?php endif; ?>
      <?php if ($col('rate')): ?><td class="text-end"><?= ($r['net'] ?? null) !== null ? e(money($r['net'])) : '<span class="text-muted">—</span>' ?></td><?php endif; ?>
      <?php if ($col('moq')): ?><td class="text-end"><?= (float)$r['min_order_qty'] > 0 ? e(qty($r['min_order_qty'])) : '<span class="text-muted">—</span>' ?></td><?php endif; ?>
      <?php if ($col('lead')): ?><td class="text-end"><?= $r['lead_time_days'] !== null ? (int)$r['lead_time_days'] . ' d' : '<span class="text-muted">—</span>' ?></td><?php endif; ?>
      <?php foreach ($cfList as $f): ?><td><?= e(App\Models\CustomFields::display($f, $r['cf'][$f['id']] ?? null)) ?></td><?php endforeach; ?>
      <?php if ($col('preferred')): ?><td><?= $r['is_preferred'] ? '<span class="badge text-bg-success">Preferred</span>' : '' ?></td><?php endif; ?>
      <td class="text-end"><?php if (can('suppliers.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("purchase/supplier-items/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="12" class="text-muted py-4">No supplier items yet. A supplier item is a product as <i>your supplier</i> names and codes it, linked to the item in your own stock.<?= $canCreate ? ' <a href="' . url('purchase/supplier-items/create') . '">Add the first one</a>.' : '' ?></td></tr><?php endif; ?>
  </tbody></table></div></div>
<?php if ($canCreate): ?>
<div class="modal fade" id="siImport" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
  <form method="post" enctype="multipart/form-data" action="<?= url('purchase/supplier-items/import') ?>"><?= csrf_field() ?>
  <div class="modal-header"><h2 class="modal-title h5">Import supplier items from a CSV file</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
    <div class="form-text mt-2">Columns: <code>supplier, supplier_item_name, supplier_item_code, sku, min_order_qty, lead_time_days, preferred, rate, discount_pct, valid_from</code>. <b>supplier</b> must match an existing supplier name; <b>sku</b> links the row to your item (leave it empty to link later); a <b>rate</b> is added when a sku is given. A row for an item already listed updates it.</div>
    <a class="btn btn-sm btn-outline-secondary mt-3" href="<?= url('purchase/supplier-items/template') ?>"><i class="bi bi-download"></i> Download the template</a></div>
  <div class="modal-footer"><button type="button" class="btn btn-link text-muted" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Import</button></div>
  </form></div></div></div>
<?php endif; ?>
