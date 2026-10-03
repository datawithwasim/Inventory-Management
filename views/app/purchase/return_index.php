<div class="d-flex justify-content-between mb-3"><div class="text-muted">Goods sent back to suppliers.</div>
  <?php if (can('purchase.create')): ?><a class="btn btn-primary" href="<?= url('purchase/returns/create') ?>"><i class="bi bi-plus-lg"></i> New return</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Return</th><th>Date</th><th>Supplier</th><th>Receipt</th><th>Reason</th><th class="text-end">Value</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr><td><a href="<?= url("purchase/returns/{$r['id']}") ?>"><?= e($r['return_no']) ?></a></td><td><?= e($r['return_date']) ?></td><td><?= e($r['supplier']) ?></td>
    <td><a href="<?= url('purchase/grns/' . (int)$r['grn_id']) ?>"><?= e($r['grn_no']) ?></a></td><td><?= e($r['reason'] ?? '') ?></td><td class="text-end"><?= e(money($r['total'])) ?></td></tr><?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No returns yet.</td></tr><?php endif; ?></tbody></table></div></div>
<?= pager($page, $pages) ?>
