<div class="d-flex justify-content-between mb-3"><div class="text-muted">Count a warehouse and correct the system to match.</div>
  <?php if (can('stock.adjust')): ?><a class="btn btn-primary" href="<?= url('stock/takes/create') ?>"><i class="bi bi-plus-lg"></i> Start stock-take</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>No.</th><th>Started</th><th>Warehouse</th><th>Lines</th><th>Status</th><th>By</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("stock/takes/{$r['id']}") ?>"><?= e($r['doc_no']) ?></a></td><td><?= e($r['created_at']) ?></td><td><?= e($r['warehouse']) ?></td><td><?= (int)$r['line_count'] ?></td>
      <td><span class="badge text-bg-<?= $r['status'] === 'posted' ? 'success' : 'warning' ?>"><?= e($r['status']) ?></span></td><td><?= e($r['user_name'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No stock-takes yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
