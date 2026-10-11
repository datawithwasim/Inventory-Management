<?php /** Stock-out voucher for the cutter. @var array $v @var array $lines @var array $tpl */
$transfer = $v['kind'] === 'transfer';
$docTitle = 'Stock-out voucher'; $docNo = $v['no']; $docDate = $v['date']; $meta = $v['meta'];
?>
<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url($v['back']) ?>">Back</a></div>
<style>
  .vch-table th, .vch-table td { vertical-align: middle; }
  .vch-qty { font-size: 1.15rem; font-weight: 700; white-space: nowrap; }
  .vch-table td { height: 46px; } .vch-blank { min-width: 100px; border: 1px solid #aaa !important; }
  .vch-rack { font-size: 1.05rem; font-weight: 700; white-space: nowrap; }
  .vch-sign { border-top: 1px solid #333; padding-top: 4px; font-size: .8rem; margin-top: 46px; }
  @media print { .vch-table tr { page-break-inside: avoid; } }
</style>
<?php require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="d-flex justify-content-between mb-3"><div><div class="small text-muted"><?= $v['kind'] === 'delivery' ? 'Issued for' : 'Purpose' ?></div><strong><?= e($v['for']) ?></strong></div>
  <div class="text-end"><div class="small text-muted">Take stock from</div><strong><?= e($v['warehouse']) ?></strong></div></div>
<table class="table table-sm doc-table vch-table">
  <thead><tr><th style="width:34px">#</th><th>Rack</th><th>Item</th><th>Roll</th><th class="text-end">Roll length before</th><th class="text-end">Issue qty</th><?php if ($transfer): ?><th>To rack</th><?php endif; ?><th class="vch-blank-h">Actual cut</th><th>Left on roll</th></tr></thead>
  <tbody>
  <?php $n = 0; $prev = null; foreach ($lines as $l): $n++; ?>
    <tr>
      <td><?= $n ?></td>
      <td class="vch-rack"><?= $l['rack'] !== '' ? e($l['rack']) : '<span class="text-muted fw-normal">No rack</span>' ?></td>
      <td><?= e($l['item']) ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td class="fw-semibold"><?= $l['batch'] !== '' ? e($l['batch']) : '<span class="text-muted fw-normal">—</span>' ?></td>
      <td class="text-end"><?= $l['before'] !== null ? e(qty($l['before'])) . ' ' . e($l['unit']) : '<span class="text-muted">—</span>' ?></td>
      <td class="text-end vch-qty"><?= e(qty($l['qty'])) ?> <small class="fw-normal"><?= e($l['unit']) ?></small></td>
      <?php if ($transfer): ?><td class="vch-rack"><?= e($l['to_rack'] !== '' ? $l['to_rack'] : '—') ?></td><?php endif; ?>
      <td class="vch-blank"></td><td class="vch-blank"></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="<?= $transfer ? 6 : 5 ?>" class="text-end small text-muted">Lines: <?= count($lines) ?></td><td class="text-end fw-semibold"><?= count(array_unique(array_column($lines, 'unit'))) === 1 ? e(qty(array_sum(array_column($lines, 'qty')))) . ' ' . e($lines[0]['unit']) : '' ?></td><td colspan="<?= $transfer ? 3 : 2 ?>"></td></tr></tfoot>
</table>
<div class="small text-muted">Take each line from the rack and roll shown. Write the length actually cut and what is left on the roll.</div>
<div class="row mt-4"><div class="col-4"><div class="vch-sign">Issued by (warehouse)<?= !empty($v['by']) ? ' — ' . e($v['by']) : '' ?></div></div><div class="col-4"><div class="vch-sign">Received by (cutter)</div></div><div class="col-4"><div class="vch-sign">Checked by</div></div></div>
<?php $notes = $v['note']; require dirname(__DIR__) . '/print/_foot.php'; ?>
