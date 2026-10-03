<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, phone, contact" value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col text-end"><?php if (can('suppliers.create')): ?><a class="btn btn-primary" href="<?= url('suppliers/create') ?>"><i class="bi bi-plus-lg"></i> Add supplier</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Supplier</th><th>Contact</th><th>Phone</th><th>Terms</th><th class="text-end">We owe</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>"><td><a href="<?= url("suppliers/{$r['id']}") ?>"><?= e($r['name']) ?></a> <?= $r['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>' ?></td>
      <td><?= e($r['contact_person'] ?? '') ?></td><td><?= e($r['phone'] ?? '') ?></td><td><?= $r['payment_terms_days'] ? (int)$r['payment_terms_days'] . ' days' : 'Immediate' ?></td>
      <td class="text-end <?= $r['outstanding'] > 0.004 ? 'text-danger' : '' ?>"><?= e(money($r['outstanding'])) ?></td>
      <td class="text-end"><?php if (can('suppliers.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("suppliers/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No suppliers yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
