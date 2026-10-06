<?php
/** One full-width card per related list (Purchase orders, Bills …), like the Rate list card.
 *  @var array $actTabs [id (also the card's anchor), label, head[], rows[[html..]], empty, all?[url,label]] */
foreach ($actTabs as $t): ?>
<section class="card mb-3" id="<?= e($t['id']) ?>">
  <div class="card-header act-head"><span><?= e($t['label']) ?> <span class="act-n"><?= count($t['rows']) ?></span></span>
    <?php if (!empty($t['new'])): ?><a class="btn btn-sm btn-outline-primary" href="<?= e($t['new'][0]) ?>"><i class="bi bi-plus-lg"></i> <?= e($t['new'][1]) ?></a><?php endif; ?></div>
  <?php if ($t['rows']): ?>
    <div class="table-responsive"><table class="table act-table align-middle mb-0"><thead><tr>
      <?php foreach ($t['head'] as $j => $h): ?><th class="<?= $j === count($t['head']) - 1 ? 'text-end' : '' ?>"><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($t['rows'] as $row): ?><tr><?php foreach ($row as $j => $c): ?><td class="<?= $j === count($row) - 1 ? 'text-end text-nowrap' : '' ?>"><?= $c ?></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if (!empty($t['all'])): ?><div class="act-foot"><a href="<?= e($t['all'][0]) ?>"><?= e($t['all'][1]) ?> <i class="bi bi-arrow-right"></i></a></div><?php endif; ?>
  <?php else: ?><div class="act-empty"><i class="bi bi-inbox"></i><?= e($t['empty']) ?></div><?php endif; ?>
</section>
<?php endforeach; ?>
