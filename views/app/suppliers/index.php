<?php $col = fn($k) => in_array($k, $cols, true); ?>
<form class="row g-2 mb-3" method="get">
  <div class="col-md-4"><input name="q" class="form-control" placeholder="Search name, phone, contact" value="<?= e($q) ?>"></div>
  <div class="col-auto"><button class="btn btn-outline-secondary">Search</button></div>
  <div class="col text-end"><?= Core\Columns::picker('suppliers', $cols) ?><?php if (can('suppliers.create')): ?><a class="btn btn-primary" href="<?= url('suppliers/create') ?>"><i class="bi bi-plus-lg"></i> Add <?= e(term('supplier', true)) ?></a><?php endif; ?></div>
</form>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr><th><?= e(term('supplier')) ?></th><?php if ($col('contact')): ?><th>Contact</th><?php endif; ?><?php if ($col('phone')): ?><th>Phone</th><?php endif; ?><?php foreach ($cfList as $f): ?><th><?= e($f['label']) ?></th><?php endforeach; ?><?php if ($col('terms')): ?><th>Terms</th><?php endif; ?><?php if ($col('owes')): ?><th class="text-end">We owe</th><?php endif; ?><th></th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?>
    <tr class="<?= $r['is_active'] ? '' : 'text-muted' ?>"><td><a href="<?= url("suppliers/{$r['id']}") ?>"><?= e($r['name']) ?></a> <?= $r['is_active'] ? '' : '<span class="badge text-bg-dark">Inactive</span>' ?></td>
      <?php if ($col('contact')): ?><td><?= e($r['contact_person'] ?? '') ?></td><?php endif; ?><?php if ($col('phone')): ?><td><?= e($r['phone'] ?? '') ?></td><?php endif; ?><?php foreach ($cfList as $f): ?><td><?= e(App\Models\CustomFields::display($f, $r['cf'][$f['id']] ?? null)) ?></td><?php endforeach; ?><?php if ($col('terms')): ?><td><?= $r['payment_terms_days'] ? (int)$r['payment_terms_days'] . ' days' : 'Immediate' ?></td><?php endif; ?>
      <?php if ($col('owes')): ?><td class="text-end <?= $r['outstanding'] > 0.004 ? 'text-danger' : '' ?>"><?= e(money($r['outstanding'])) ?></td><?php endif; ?>
      <td class="text-end"><?php if (can('suppliers.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("suppliers/{$r['id']}/edit") ?>">Edit</a><?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="text-muted">No <?= e(term('suppliers', true)) ?> yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
