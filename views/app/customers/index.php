<?php $col = fn($k) => in_array($k, $cols, true); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, phone, contact" value="<?= e($q) ?>"></div>
  <div class="col-md-3"><select name="group" class="form-select"><option value="">All groups</option>
    <?php foreach ($groups as $g): ?><option value="<?= (int)$g['id'] ?>" <?= $group === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['name']) ?></option><?php endforeach; ?></select></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col text-end"><?= Core\Columns::picker('customers', $cols) ?><a class="btn btn-outline-secondary" data-page-action href="<?= url('customers/groups') ?>"><?= e(term('customer')) ?> groups</a>
    <?php if (can('customers.create')): ?><a class="btn btn-primary" href="<?= url('customers/create') ?>"><i class="bi bi-plus-lg"></i> Add <?= e(term('customer', true)) ?></a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('customer')) ?></th><?php if ($col('group')): ?><th>Group</th><?php endif; ?><?php if ($col('phone')): ?><th>Phone</th><?php endif; ?><?php foreach ($cfList as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?><?php if ($col('credit')): ?><th>Credit</th><?php endif; ?><?php if ($col('owes')): ?><th class="text-end">Owes us</th><?php endif; ?><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>"><td><a href="<?= url("customers/{$r['id']}") ?>"><?= e($r['name']) ?></a> <?= $r['is_walkin'] ? '<span class="badge text-bg-light border">Counter sales</span>' : '' ?><?= $r['is_active'] ? '' : ' <span class="badge text-bg-dark">Inactive</span>' ?></td>
      <?php if ($col('group')): ?><td><?= e($r['group_name'] ?? '') ?></td><?php endif; ?><?php if ($col('phone')): ?><td><?= e($r['phone'] ?? '') ?></td><?php endif; ?><?php foreach ($cfList as $f): ?><td><?= e(App\Models\CustomFields::display($f, $r['cf'][$f['id']] ?? null)) ?></td><?php endforeach; ?><?php if ($col('credit')): ?><td><?= $r['credit_days'] ? (int)$r['credit_days'] . ' days' : 'Immediate' ?></td><?php endif; ?>
      <?php if ($col('owes')): ?><td class="text-end <?= $r['outstanding'] > 0.004 ? 'text-danger' : '' ?>"><?= e(money($r['outstanding'])) ?></td><?php endif; ?>
      <td class="text-end"><?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("customers/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="text-muted">No <?= e(term('customers', true)) ?> found.</td></tr><?php endif; ?>
  </tbody></table></div></div>
