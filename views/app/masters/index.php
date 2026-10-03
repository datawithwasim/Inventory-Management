<?php require __DIR__ . '/_tabs.php'; ?>
<div class="d-flex justify-content-between mb-3">
  <div class="text-muted"><?= count($rows) ?> <?= e(strtolower($def['title'])) ?></div>
  <?php if (can('masters.create')): ?><a class="btn btn-primary" href="<?= url("masters/$type/create") ?>"><i class="bi bi-plus-lg"></i> Add <?= e(strtolower($def['one'])) ?></a><?php endif; ?>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0 align-middle">
  <thead><tr>
    <?php foreach ($def['fields'] as [$col, $label]): ?><th><?= e($type === 'units' && $col === 'allow_decimal' ? 'Decimals' : $label) ?></th><?php endforeach; ?>
    <th>Used by <?= e(term('items', true)) ?></th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <?php foreach ($def['fields'] as [$col, $label, $kind]): ?>
        <td><?= $kind === 'checkbox' ? ($r[$col] ? 'Yes' : 'No') : e($r[$col]) ?></td>
      <?php endforeach; ?>
      <td><?= (int)$r['used'] ?></td>
      <td class="text-end text-nowrap">
        <?php if (can('masters.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= url("masters/$type/{$r['id']}/edit") ?>">Edit</a><?php endif; ?>
        <?php if (can('masters.delete')): ?>
          <form class="d-inline" method="post" action="<?= url("masters/$type/{$r['id']}/delete") ?>" onsubmit="return confirm('Delete?')"><?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="9" class="text-muted">Nothing here yet.</td></tr><?php endif; ?>
  </tbody></table></div></div>
