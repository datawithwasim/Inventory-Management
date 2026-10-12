<?php /** Transfer challan: goes with the goods. @var array $doc @var array $lines @var array $from @var array $to @var array $tpl */
$tpl['title'] = ''; $tpl['signature'] = 0; $tpl['terms'] = '';
$docTitle = 'Transfer challan'; $docNo = $doc['doc_no']; $docDate = substr((string)($doc['dispatched_at'] ?: $doc['created_at']), 0, 10);
$meta = ['Expected' => $doc['expected_date'] ? fdate($doc['expected_date']) : null];
$between = (int)$doc['to_warehouse_id'] !== (int)$doc['warehouse_id'];
?>
<div class="no-print mb-3 d-flex gap-2"><button class="btn btn-primary btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
  <a class="btn btn-outline-secondary btn-sm" href="<?= url("stock/transfers/{$doc['id']}") ?>">Back</a></div>
<style>
  .chl-table th, .chl-table td { vertical-align: middle; } .chl-table td { height: 40px; } .chl-blank { min-width: 90px; border: 1px solid #aaa !important; }
  .chl-sign { border-top: 1px solid #333; padding-top: 4px; font-size: .8rem; margin-top: 46px; } @media print { .chl-table tr { page-break-inside: avoid; } }
</style>
<?php require dirname(__DIR__) . '/print/_head.php'; ?>
<div class="row g-3 mb-3">
  <div class="col-6"><div class="small text-muted">From</div><strong><?= e($from['name']) ?></strong><?php if (!empty($from['address'])): ?><div class="small"><?= nl2br(e($from['address'])) ?></div><?php endif; ?></div>
  <div class="col-6"><div class="small text-muted">To</div><strong><?= e($to['name']) ?></strong><?php if (!empty($to['address'])): ?><div class="small"><?= nl2br(e($to['address'])) ?></div><?php endif; ?></div>
</div>
<?php if ($between && ($doc['transport'] || $doc['vehicle_no'] || $doc['lr_no'])): ?>
<div class="row g-3 mb-3 small">
  <?php foreach (['Transporter' => $doc['transport'], 'Vehicle no.' => $doc['vehicle_no'], 'LR / challan no.' => $doc['lr_no']] as $k => $v): if (!$v) continue; ?>
    <div class="col-4"><span class="text-muted"><?= e($k) ?></span><br><strong><?= e($v) ?></strong></div><?php endforeach; ?>
</div>
<?php endif; ?>
<table class="table table-sm doc-table chl-table">
  <thead><tr><th style="width:34px">#</th><th>Item</th><th>Roll</th><th>Packed from rack</th><th class="text-end">Qty sent</th><th class="chl-blank-h">Qty received</th><th>Put on rack</th><th>Remark</th></tr></thead>
  <tbody>
  <?php $n = 0; foreach ($lines as $l): $n++; ?>
    <tr><td><?= $n ?></td><td><?= e($l['item_name']) ?><?= $l['vname'] ? ' — ' . e($l['vname']) : '' ?> <small class="text-muted"><?= e($l['sku']) ?></small></td>
      <td class="fw-semibold"><?= $l['batch_no'] ? e($l['batch_no']) : '<span class="text-muted fw-normal">—</span>' ?></td>
      <td><?= e($l['rack'] ?? '—') ?></td><td class="text-end fw-bold"><?= e(qty($l['qty'])) ?> <small class="fw-normal"><?= e($l['unit']) ?></small></td>
      <td class="chl-blank"></td><td class="chl-blank"></td><td class="chl-blank"></td></tr>
  <?php endforeach; ?></tbody>
  <tfoot><tr><td colspan="4" class="text-end small text-muted">Lines: <?= count($lines) ?></td><td class="text-end fw-semibold"><?= count(array_unique(array_column($lines, 'unit'))) === 1 ? e(qty(array_sum(array_column($lines, 'qty')))) . ' ' . e($lines[0]['unit']) : '' ?></td><td colspan="3"></td></tr></tfoot>
</table>
<div class="small text-muted">Receiver: check every roll against this challan, write what you actually received and the rack it was put on. Report any difference the same day.</div>
<div class="row mt-4"><div class="col-3"><div class="chl-sign">Sent by</div></div><div class="col-3"><div class="chl-sign">Driver / transporter</div></div><div class="col-3"><div class="chl-sign">Received by</div></div><div class="col-3"><div class="chl-sign">Date &amp; time received</div></div></div>
<?php $notes = $doc['note']; require dirname(__DIR__) . '/print/_foot.php'; ?>
