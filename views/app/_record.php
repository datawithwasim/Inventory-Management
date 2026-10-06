<?php
/**
 * Zoho-style record page: header, left "Related list", Overview / Timeline tabs, notes.
 * @var array $rec entity, row, name, back, badges (html), actions (html: buttons), menu (html: dropdown items), related [anchor => label], body (html), cfValues
 */
$GLOBALS['rec_shell'] = true;
$E = $rec['entity'];
$row = $rec['row'];
$notes = Core\RecordView::notes($E, (int)$row['id']);
$canNote = isset(Core\RecordView::META[$E]) && can(Core\RecordView::META[$E][0]);
$related = ['recOverview' => 'Overview'] + ($rec['related'] ?? []) + ['notes' => 'Notes (' . count($notes) . ')'];
$layoutUrl = url('settings/formdesign?form=' . $E . '&return=' . rawurlencode($_SERVER['REQUEST_URI'] ?? ''));
?>
<div class="rec">
  <div class="rec-top">
    <a class="rec-back" href="<?= url($rec['back']) ?>" title="Back to the list"><i class="bi bi-arrow-left"></i></a>
    <div class="rec-avatar"><?= e(mb_strtoupper(mb_substr(trim((string)$rec['name']), 0, 1))) ?></div>
    <div class="rec-title"><h1><?= e($rec['name']) ?></h1><div class="rec-badges"><?= $rec['badges'] ?? '' ?></div></div>
    <div class="rec-actions">
      <?= $rec['actions'] ?? '' ?>
      <?php if (!empty($rec['menu']) || can('settings.edit')): ?>
        <div class="dropdown d-inline-block">
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots"></i></button>
          <ul class="dropdown-menu dropdown-menu-end shadow">
            <?= $rec['menu'] ?? '' ?>
            <?php if (can('settings.edit')): ?><li><a class="dropdown-item" href="<?= e($layoutUrl) ?>"><i class="bi bi-layout-text-window me-2 text-muted"></i>Edit page layout</a></li><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php require __DIR__ . '/../layouts/flash.php'; ?>
  <div class="rec-layout">
    <aside class="rec-side"><div class="rec-side-h">Related list</div>
      <?php foreach ($related as $id => $label): ?><a href="#<?= e($id) ?>" data-rel="<?= e($id) ?>"><?= e($label) ?></a><?php endforeach; ?>
    </aside>
    <div class="rec-main">
      <div class="rec-tabs" role="tablist"><button type="button" class="on" data-tab="recOverview">Overview</button><button type="button" data-tab="recTimeline">Timeline</button></div>
      <div id="recOverview" class="rec-panel">
        <?= Core\RecordView::summary($E, $row, $rec['cfValues'] ?? []) ?>
        <?= Core\RecordView::sections($E, $row, $rec['cfValues'] ?? []) ?>
        <?= $rec['body'] ?? '' ?>
        <section class="card mb-3" id="notes"><div class="card-header">Notes</div><div class="card-body">
          <?php if ($canNote): ?><form method="post" action="<?= url("records/$E/{$row['id']}/notes") ?>" class="mb-3"><?= csrf_field() ?>
            <textarea name="body" class="form-control" rows="2" maxlength="1500" placeholder="Add a note…" required></textarea>
            <button class="btn btn-sm btn-primary mt-2">Add note</button></form><?php endif; ?>
          <?php foreach ($notes as $n): ?>
            <div class="rec-note"><div><?= nl2br(e($n['body'])) ?></div>
              <div class="small text-muted d-flex justify-content-between"><span><?= e($n['user_name'] ?: 'Someone') ?> · <?= e(fdate(substr((string)$n['created_at'], 0, 10))) ?> <?= e(substr((string)$n['created_at'], 11, 5)) ?></span>
                <?php if ($canNote && ((int)$n['created_by'] === (int)(Core\Auth::user()['id'] ?? 0) || can('settings.edit'))): ?><form method="post" action="<?= url("records/notes/{$n['id']}/delete") ?>" onsubmit="return confirm('Remove this note?')"><?= csrf_field() ?><button class="btn btn-link btn-sm p-0 text-danger">Delete</button></form><?php endif; ?></div></div>
          <?php endforeach; ?>
          <?php if (!$notes): ?><div class="text-muted small">No notes yet.</div><?php endif; ?>
        </div></section>
      </div>
      <div id="recTimeline" class="rec-panel" hidden><div class="card"><div class="card-body"><?= Core\RecordView::timeline($E, (int)$row['id'], $row['created_at'] ?? null) ?></div></div></div>
    </div>
  </div>
</div>
<script>
(function () {
  var tabs = document.querySelectorAll('.rec-tabs button'), panels = document.querySelectorAll('.rec-panel');
  function show(id) { tabs.forEach(function (t) { t.classList.toggle('on', t.dataset.tab === id); }); panels.forEach(function (p) { p.hidden = p.id !== id; }); }
  tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.tab); }); });
  document.querySelectorAll('.rec-side a').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault(); show('recOverview');
      var el = document.getElementById(a.dataset.rel); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      document.querySelectorAll('.rec-side a').forEach(function (x) { x.classList.toggle('on', x === a); });
    });
  });
  if (location.hash && document.getElementById(location.hash.slice(1))) setTimeout(function () { document.getElementById(location.hash.slice(1)).scrollIntoView(); }, 50);
})();
</script>
