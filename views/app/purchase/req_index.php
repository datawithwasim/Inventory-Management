<div class="d-flex justify-content-between mb-3"><div class="text-muted">Ask for items to be bought, then turn the request into a purchase order.</div>
  <?php if (can('purchase.create')): ?><div><a class="btn btn-outline-secondary" href="<?= url('purchase/requisitions/create?low=1') ?>"><i class="bi bi-exclamation-triangle"></i> From low stock</a>
  <a class="btn btn-primary" href="<?= url('purchase/requisitions/create') ?>"><i class="bi bi-plus-lg"></i> New requisition</a></div><?php endif; ?></div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0">
  <thead><tr><th>No.</th><th>Date</th><th>Lines</th><th>Status</th><th>PO</th><th>By</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr><td><a href="<?= url("purchase/requisitions/{$r['id']}") ?>"><?= e($r['req_no']) ?></a></td><td><?= e(substr($r['created_at'], 0, 10)) ?></td><td><?= (int)$r['line_count'] ?></td>
      <td><span class="badge text-bg-<?= ['open' => 'primary', 'converted' => 'success', 'cancelled' => 'secondary'][$r['status']] ?>"><?= e($r['status']) ?></span></td>
      <td><?= $r['po_id'] ? '<a href="' . url('purchase/orders/' . (int)$r['po_id']) . '">' . e($r['po_no']) . '</a>' : '' ?></td><td><?= e($r['user_name'] ?? '') ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No requisitions yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
<?= pager($page, $pages) ?>
