<?php
/** Tabbed list card (e.g. Purchase orders | Bills). @var string $actId @var string $actTitle @var array $actTabs [id,label,head[],rows[[html..]],empty,all?(url,label)] */
$first = true;
?>
<section class="card mb-3 act" id="<?= e($actId) ?>">
  <div class="card-header act-head"><span><?= e($actTitle) ?></span>
    <div class="rec-tabs act-tabs" role="tablist">
      <?php foreach ($actTabs as $i => $t): ?><button type="button" class="<?= $i === 0 ? 'on' : '' ?>" data-act="<?= e($actId . '-' . $t['id']) ?>"><?= e($t['label']) ?> <span class="act-n"><?= count($t['rows']) ?></span></button><?php endforeach; ?>
    </div></div>
  <?php foreach ($actTabs as $i => $t): ?>
    <div class="act-pane" id="<?= e($actId . '-' . $t['id']) ?>" <?= $i === 0 ? '' : 'hidden' ?>>
      <?php if ($t['rows']): ?>
        <div class="table-responsive"><table class="table act-table align-middle mb-0"><thead><tr>
          <?php foreach ($t['head'] as $j => $h): ?><th class="<?= $j === 0 ? '' : ($j === count($t['head']) - 1 ? 'text-end' : '') ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
          <?php foreach ($t['rows'] as $row): ?><tr><?php foreach ($row as $j => $c): ?><td class="<?= $j === count($row) - 1 ? 'text-end text-nowrap' : '' ?>"><?= $c ?></td><?php endforeach; ?></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if (!empty($t['all'])): ?><div class="act-foot"><a href="<?= e($t['all'][0]) ?>"><?= e($t['all'][1]) ?> <i class="bi bi-arrow-right"></i></a></div><?php endif; ?>
      <?php else: ?><div class="act-empty"><i class="bi bi-inbox"></i><?= e($t['empty']) ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</section>
<script>
(function () {
  var box = document.getElementById(<?= json_encode($actId) ?>);
  box.querySelectorAll('[data-act]').forEach(function (b) {
    b.addEventListener('click', function () {
      box.querySelectorAll('[data-act]').forEach(function (x) { x.classList.toggle('on', x === b); });
      box.querySelectorAll('.act-pane').forEach(function (p) { p.hidden = p.id !== b.dataset.act; });
    });
  });
})();
</script>
