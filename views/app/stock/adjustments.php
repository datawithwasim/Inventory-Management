<?php $reasons = App\Controllers\AdjustmentController::REASONS; ?>
<div class="d-flex justify-content-between mb-3"><div class="text-muted">Add or remove stock with a reason. Opening stock is entered here too.</div>
  <?php if (can('stock.adjust')): ?><a class="btn btn-primary" href="<?= url('stock/adjustments/create') ?>"><i class="bi bi-plus-lg"></i> New adjustment</a><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>No.</th><th>Date</th><th><?= e(term('warehouse')) ?></th><th>Reason</th><th>Lines</th><th>By</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("stock/adjustments/{$r['id']}") ?>"><?= e($r['doc_no']) ?></a></td><td><?= e($r['created_at']) ?></td><td><?= e($r['warehouse']) ?></td>
      <td><?= e($reasons[$r['reason']] ?? $r['reason']) ?></td><td><?= (int)$r['line_count'] ?></td><td><?= e($r['user_name'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No adjustments yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
