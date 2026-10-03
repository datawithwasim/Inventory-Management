<div class="d-flex justify-content-between mb-3"><div class="text-muted">Move stock between warehouses. Batches (rolls) keep their identity.</div>
  <?php if (can('stock.transfer')): ?><a class="btn btn-primary" href="<?= url('stock/transfers/create') ?>"><i class="bi bi-plus-lg"></i> New transfer</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>No.</th><th>Date</th><th>From</th><th>To</th><th>Lines</th><th>By</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("stock/transfers/{$r['id']}") ?>"><?= e($r['doc_no']) ?></a></td><td><?= e($r['created_at']) ?></td><td><?= e($r['warehouse']) ?></td><td><?= e($r['to_warehouse']) ?></td><td><?= (int)$r['line_count'] ?></td><td><?= e($r['user_name'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No transfers yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
