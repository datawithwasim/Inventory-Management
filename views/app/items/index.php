<?php $col = fn($k) => in_array($k, $cols, true); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, SKU, barcode" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="category" class="form-select"><option value="">All categories</option>
    <?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $cat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto form-check d-flex align-items-center ms-2"><input class="form-check-input me-2" type="checkbox" name="low" value="1" id="low" <?= $low ? 'checked' : '' ?>><label for="low" class="form-check-label">Low stock only</label></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Filter</button></div>
  <div class="col text-end">
    <?= Core\Columns::picker('items', $cols) ?>
    <a class="btn btn-outline-secondary" href="<?= url('items/export') ?>"><i class="bi bi-download"></i> Export</a>
    <?php if (can('items.create')): ?>
      <a class="btn btn-outline-secondary" href="<?= url('items/import') ?>"><i class="bi bi-upload"></i> Import</a>
      <?= $limitReached ? '<span class="text-warning ms-2">Item limit reached</span>' : '<a class="btn btn-primary" href="' . url('items/create') . '"><i class="bi bi-plus-lg"></i> Add item</a>' ?>
    <?php endif; ?>
  </div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('item')) ?></th><?php if ($col('category')): ?><th>Category</th><?php endif; ?><?php if ($col('brand')): ?><th>Brand</th><?php endif; ?><?php foreach ($cfList as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?><?php if ($col('variants')): ?><th>Variants</th><?php endif; ?><?php if ($col('price')): ?><th class="text-end">Sale price</th><?php endif; ?><?php if ($col('stock')): ?><th class="text-end">Stock</th><?php endif; ?><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): $isLow = !$r['is_bundle'] && $r['reorder_level'] > 0 && $r['stock'] <= $r['reorder_level']; ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>">
      <td><a href="<?= url("items/{$r['id']}") ?>"><?= e($r['name']) ?></a>
        <?= $r['is_bundle'] ? '<span class="badge text-bg-info">Bundle</span>' : '' ?>
        <?= $r['track_batch'] ? '<span class="badge text-bg-secondary">Batch</span>' : '' ?>
        <?= $r['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>' ?></td>
      <?php if ($col('category')): ?><td><?= e($r['category'] ?? '') ?></td><?php endif; ?><?php if ($col('brand')): ?><td><?= e($r['brand'] ?? '') ?></td><?php endif; ?><?php foreach ($cfList as $f): ?><td><?= e(App\Models\CustomFields::display($f, $r['cf'][$f['id']] ?? null)) ?></td><?php endforeach; ?>
      <?php if ($col('variants')): ?><td><?= (int)$r['variant_count'] ?></td><?php endif; ?>
      <?php if ($col('price')): ?><td class="text-end"><?= e(money($r['min_price'])) ?><?= $r['variant_count'] > 1 ? '+' : '' ?></td><?php endif; ?>
      <?php if ($col('stock')): ?><td class="text-end"><?= e(qty($r['stock'])) ?> <?= $r['is_bundle'] ? 'sets' : e($r['unit']) ?> <?= $isLow ? '<span class="badge text-bg-danger">Low</span>' : '' ?></td><?php endif; ?>
      <td class="text-end"><?php if (can('items.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("items/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="10" class="text-muted">No <?= e(term('items', true)) ?> found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<div class="d-flex justify-content-between align-items-center"><?= pager($page, $pages) ?><span class="text-muted small mt-3"><?= (int)$total ?> <?= e(term('item', true)) ?>(s)</span></div>
