<?php /** The supplier's items (their catalogue) on the supplier page. The master lives under Purchase → Supplier items. @var array $s @var array $products */
$canAdd = can('suppliers.create');
$d = fn($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<section class="card mb-3" id="products">
  <div class="card-header rl-head">
    <div><div class="rl-title"><i class="bi bi-box-seam"></i> Items supplied <span class="act-n"><?= count($products) ?></span></div>
      <div class="rl-sub">What this <?= e(term('supplier', true)) ?> sells, named and coded the way <i>they</i> do, and which of your items each one is.</div></div>
    <div class="rl-tools"><a class="btn btn-sm btn-outline-secondary" href="<?= url('purchase/supplier-items?supplier=' . (int)$s['id']) ?>"><i class="bi bi-box-arrow-up-right"></i><span class="d-none d-md-inline"> Open in Supplier items</span></a>
      <?php if ($canAdd): ?><a class="btn btn-sm btn-primary" href="<?= url('purchase/supplier-items/create?supplier=' . (int)$s['id']) ?>"><i class="bi bi-plus-lg"></i> Add item</a><?php endif; ?></div>
  </div>
  <?php if ($products): ?>
  <div class="table-responsive"><table class="table rl-table align-middle mb-0">
    <thead><tr><th>Their item</th><th><?= e(term('item')) ?> (ours)</th><th class="text-end">Rate</th><th class="text-end d-none d-md-table-cell">Last paid</th></tr></thead><tbody>
    <?php foreach ($products as $p): $ours = $p['variant_id'] ? $p['item_name'] . ($p['vname'] ? ' — ' . $p['vname'] : '') : ''; ?>
      <tr class="<?= $p['is_active'] ? '' : 'text-muted' ?>">
        <td><a class="fw-medium" href="<?= url("purchase/supplier-items/{$p['id']}") ?>"><?= e($p['supplier_name'] ?: $p['supplier_code']) ?></a><?= $p['supplier_name'] && $p['supplier_code'] ? '<div class="small text-muted">Code ' . e($p['supplier_code']) . '</div>' : '' ?><?= $p['is_preferred'] ? ' <span class="badge text-bg-success">Preferred</span>' : '' ?></td>
        <td><?php if ($p['variant_id']): ?><a href="<?= url("items/{$p['item_id']}") ?>"><?= e($ours) ?></a> <span class="text-muted small"><?= e($p['sku']) ?></span><?php else: ?><span class="badge text-bg-warning">Not linked</span><?php endif; ?></td>
        <td class="text-end"><?= $p['net'] !== null ? '<span class="fw-semibold">' . e(money($p['net'])) . '</span>' . ((float)$p['rate_row']['discount_pct'] > 0 ? '<div class="small text-muted">' . e(money($p['rate_row']['rate'])) . ' less ' . e($d($p['rate_row']['discount_pct'])) . '%</div>' : '') : '<span class="text-muted small">No rate yet</span>' ?></td>
        <td class="text-end d-none d-md-table-cell"><?= $p['last_paid'] !== null ? e(money($p['last_paid'])) . '<div class="small text-muted">' . e(fdate($p['last_on'])) . '</div>' : '<span class="text-muted">—</span>' ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
  <?php else: ?>
    <div class="rl-empty"><i class="bi bi-box-seam"></i><div><b>No items yet</b><div class="text-muted small">List what this <?= e(term('supplier', true)) ?> supplies, with the name and code <i>they</i> use, and link each to your own item.</div></div>
      <?php if ($canAdd): ?><a class="btn btn-primary btn-sm ms-auto" href="<?= url('purchase/supplier-items/create?supplier=' . (int)$s['id']) ?>"><i class="bi bi-plus-lg"></i> Add the first item</a><?php endif; ?></div>
  <?php endif; ?>
</section>
