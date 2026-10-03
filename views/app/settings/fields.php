<?php require __DIR__ . '/_tabs.php'; ?>
<div class="d-flex justify-content-between mb-3"><div class="text-muted">Add your own fields to items, customers and suppliers. They appear on the forms and detail pages.</div>
  <a class="btn btn-primary" href="<?= url('settings/custom-fields/create') ?>"><i class="bi bi-plus-lg"></i> New field</a></div>
<?php foreach (App\Models\CustomFields::ENTITIES as $e => $title): ?>
  <div class="card mb-3"><div class="card-header"><?= e($title) ?></div><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
    <thead><tr><th>Field</th><th>Type</th><th>Required</th><th>In list</th><th>Order</th><th>Filled in</th><th></th></tr></thead><tbody>
    <?php foreach ($by[$e] as $f): ?><tr class="<?= $f['is_active'] ? '' : 'text-muted' ?>"><td><?= e($f['label']) ?> <?= $f['is_active'] ? '' : '<span class="badge text-bg-secondary">Hidden</span>' ?></td>
      <td><?= e(App\Models\CustomFields::TYPES[$f['type']]) ?><?= $f['type'] === 'dropdown' ? '<div class="small text-muted">' . e(str_replace("\n", ' · ', $f['options'])) . '</div>' : '' ?></td>
      <td><?= $f['is_required'] ? 'Yes' : '' ?></td><td><?= $f['show_in_list'] ? 'Yes' : '' ?></td><td><?= (int)$f['sort_order'] ?></td><td><?= (int)$f['used'] ?></td>
      <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="<?= url("settings/custom-fields/{$f['id']}/edit") ?>">Edit</a>
        <form class="d-inline" method="post" action="<?= url("settings/custom-fields/{$f['id']}/delete") ?>" onsubmit="return confirm('Delete this field and everything entered in it?')"><?= csrf_field() ?><button class="btn btn-sm btn-outline-danger">Delete</button></form></td></tr><?php endforeach; ?>
    <?php if (!$by[$e]): ?><tr><td colspan="7" class="text-muted">No custom fields for <?= e(strtolower($title)) ?>.</td></tr><?php endif; ?></tbody></table></div></div>
<?php endforeach; ?>
