<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, phone, contact" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="group" class="form-select"><option value="">All groups</option>
    <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $group === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col text-end"><a class="btn btn-outline-secondary" href="<?= url('customers/groups') ?>">Customer groups</a>
    <?php if (can('customers.create')): ?><a class="btn btn-primary" href="<?= url('customers/create') ?>"><i class="bi bi-plus-lg"></i> Add customer</a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th>Customer</th><th>Group</th><th>Phone</th><th>Credit</th><th class="text-end">Owes us</th><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>"><td><a href="<?= url("customers/{$r['id']}") ?>"><?= e($r['name']) ?></a> <?= $r['is_walkin'] ? '<span class="badge text-bg-light border">Counter sales</span>' : '' ?><?= $r['is_active'] ? '' : ' <span class="badge text-bg-dark">Inactive</span>' ?></td>
      <td><?= e($r['group_name'] ?? '') ?></td><td><?= e($r['phone'] ?? '') ?></td><td><?= $r['credit_days'] ? (int)$r['credit_days'] . ' days' : 'Immediate' ?></td>
      <td class="text-end <?= $r['outstanding'] > 0.004 ? 'text-danger' : '' ?>"><?= e(money($r['outstanding'])) ?></td>
      <td class="text-end"><?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("customers/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="6" class="text-muted">No customers found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
