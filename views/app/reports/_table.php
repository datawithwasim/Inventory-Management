<?php /** @var array $def @var array $rows @var array $totals */ ?>
<table class="table table-sm table-hover align-middle report-table">
  <thead class="table-light"><tr>
    <?php if (!empty($selectable)): ?><th style="width:32px"><input type="checkbox" class="form-check-input" id="pick-all" checked aria-label="Select all"></th><?php endif; ?>
    <?php foreach ($def['columns'] as $c): ?><th class="<?= in_array($c['type'], ['money', 'qty', 'int'], true) ? 'text-end' : '' ?>"><?= e($c['label']) ?></th><?php endforeach; ?>
  </tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <?php if (!empty($selectable)): ?><td><input type="checkbox" class="form-check-input pick" name="pick[]" value="<?= (int)$r['item_id'] ?>" <?= (float)$r['suggest'] > 0 ? 'checked' : 'disabled' ?>></td><?php endif; ?>
      <?php foreach ($def['columns'] as $k => $c): $num = in_array($c['type'], ['money', 'qty', 'int'], true); ?>
        <td class="<?= $num ? 'text-end text-nowrap' : '' ?>"><?= e(report_cell($c, $r[$k] ?? null)) ?></td>
      <?php endforeach; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="<?= count($def['columns']) + (!empty($selectable) ? 1 : 0) ?>" class="text-center text-muted py-4"><?= isset($def['needs']) && empty($f[$def['needs']]) ? 'Choose a ' . e($def['needs']) . ' above to see the statement.' : 'No data for these filters.' ?></td></tr><?php endif; ?>
  </tbody>
  <?php if ($totals && $rows): ?>
  <tfoot class="table-light fw-semibold"><tr>
    <?php if (!empty($selectable)): ?><td></td><?php endif; ?>
    <?php foreach ($def['columns'] as $k => $c): ?>
      <td class="<?= in_array($c['type'], ['money', 'qty', 'int'], true) ? 'text-end text-nowrap' : '' ?>"><?= $k === array_key_first($def['columns']) ? 'Total' : (isset($totals[$k]) ? e(report_cell($c, $totals[$k])) : '') ?></td>
    <?php endforeach; ?>
  </tr></tfoot>
  <?php endif; ?>
</table>
