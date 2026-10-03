<?php $typeIcons = ['text' => 'input-cursor-text', 'textarea' => 'text-paragraph', 'email' => 'envelope', 'phone' => 'telephone', 'url' => 'link-45deg', 'number' => '123', 'decimal' => 'hash',
    'currency' => 'currency-rupee', 'percent' => 'percent', 'date' => 'calendar-date', 'datetime' => 'calendar-event', 'dropdown' => 'list-ul', 'radio' => 'ui-radios', 'multiselect' => 'ui-checks', 'checkbox' => 'check2-square']; ?>
<div class="dz" id="dz" data-start="<?= e($start) ?>">
  <header class="dz-top">
    <a class="dz-back" href="<?= url('settings/company') ?>" title="Back to settings"><i class="bi bi-arrow-left"></i></a>
    <span class="dz-title">Form designer</span><span class="dz-sep"></span>
    <select id="dzForm" class="dz-select" aria-label="Form"></select>
    <div class="dz-seg" id="dzStyle" title="Where the labels go on the form"><button type="button" data-s="top">Labels on top</button><button type="button" data-s="left">Labels on left</button></div>
    <span class="dz-grow"></span>
    <span class="dz-dirty d-none" id="dzDirty"><i class="bi bi-circle-fill"></i> Unsaved</span>
    <button type="button" class="dz-btn" id="dzPreview" title="Preview how the form will look"><i class="bi bi-eye"></i><span>Preview</span></button>
    <button type="button" class="dz-btn" id="dzUndo" disabled><i class="bi bi-arrow-counterclockwise"></i><span>Undo</span></button>
    <button type="button" class="dz-btn" id="dzReset"><i class="bi bi-eraser"></i><span>Reset form</span></button>
    <a class="dz-btn light" href="<?= url('settings/company') ?>" id="dzCancel">Cancel</a>
    <form method="post" action="<?= url('settings/formdesign') ?>" id="dzSave" class="m-0 d-flex gap-2" data-noload><?= csrf_field() ?>
      <input type="hidden" name="payload" id="dzPayload"><input type="hidden" name="current" id="dzCurrent"><input type="hidden" name="close" id="dzClose" value="">
      <button class="dz-btn primary" id="dzSaveBtn"><i class="bi bi-check2"></i><span>Save</span></button>
      <button class="dz-btn primary soft" id="dzSaveClose" type="submit">Save and close</button>
    </form>
  </header>

  <div class="dz-main">
    <aside class="dz-left">
      <div class="dz-box-title">New fields <small id="dzNoCustom" class="d-none">not available on this form</small></div>
      <div class="dz-types" id="dzTypes">
        <?php foreach ($types as $k => $l): ?><div class="dz-type" draggable="true" data-type="<?= e($k) ?>" title="Drag onto the form"><i class="bi bi-<?= e($typeIcons[$k] ?? 'input-cursor-text') ?>"></i><span><?= e($l) ?></span></div><?php endforeach; ?>
      </div>
      <button type="button" class="dz-wide" id="dzAddSection"><i class="bi bi-layout-three-columns"></i> NEW SECTION</button>
      <div class="dz-box-title mt-3">Unused fields <span class="dz-count" id="dzUnusedCount">0</span></div>
      <div class="dz-unused" id="dzUnused"></div>
      <div class="dz-help">Drag a field type onto the form to create a new field. Drag a field from <b>Unused</b> back to bring it on to the form.</div>
    </aside>

    <main class="dz-center">
      <div class="dz-paper" id="dzPaper">
        <div class="dz-paper-head"><h2 id="dzPaperTitle"></h2><span class="dz-mock-buttons"><span>Cancel</span><span class="p">Save</span></span></div>
        <div id="dzSections"></div>
        <div id="dzLines" class="dz-lines" hidden></div>
      </div>
    </main>

    <aside class="dz-right" id="dzRight" hidden>
      <div class="dz-right-head"><div class="d-flex align-items-center gap-2"><i class="bi" id="pIcon"></i><div><div class="fw-semibold" id="pName"></div><div class="small opacity-75" id="pKind"></div></div></div><button type="button" class="dz-x" id="pClose" aria-label="Close"><i class="bi bi-x-lg"></i></button></div>
      <div class="dz-right-body">
        <label class="dz-lbl" for="pLabel">Field label</label>
        <input id="pLabel" class="dz-in" maxlength="80">
        <div id="pTypeBox"><label class="dz-lbl" for="pType">Field type</label><select id="pType" class="dz-in"><?php foreach ($types as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select><div class="dz-note" id="pTypeNote"></div></div>
        <div id="pOptBox"><label class="dz-lbl" for="pOptions">Choices <span class="opacity-75">(one per line)</span></label><textarea id="pOptions" class="dz-in" rows="5"></textarea></div>
        <label class="dz-lbl" for="pHelp">Hint under the field</label>
        <input id="pHelp" class="dz-in" maxlength="140" placeholder="Optional help text">
        <div class="dz-lbl">Width</div>
        <div class="dz-seg w" id="pWidth"><?php foreach ($widths as $k => $l): ?><button type="button" data-w="<?= e((string)$k) ?>"><?= e($k === '' ? 'Auto' : ($k === '100' ? 'Full' : $k . '%')) ?></button><?php endforeach; ?></div>
        <div class="dz-row" id="pReqRow"><span>Mandatory field</span><label class="dz-sw"><input type="checkbox" id="pReq"><i></i></label></div>
        <div class="dz-row" id="pUniqRow"><span>Unique (no duplicate values)</span><label class="dz-sw"><input type="checkbox" id="pUniq"><i></i></label></div>
        <div class="dz-row" id="pListRow"><span>Show as a column in the list</span><label class="dz-sw"><input type="checkbox" id="pList"><i></i></label></div>
        <div class="dz-note" id="pCore"><i class="bi bi-lock me-1"></i>This is an essential field. It is always on the form and always filled in.</div>
        <div class="d-flex gap-2 mt-3"><button type="button" class="dz-btn" id="pUp"><i class="bi bi-arrow-up"></i> Earlier</button><button type="button" class="dz-btn" id="pDown"><i class="bi bi-arrow-down"></i> Later</button></div>
        <button type="button" class="dz-btn danger mt-3" id="pHide"><i class="bi bi-eye-slash"></i> Move to unused</button>
        <button type="button" class="dz-btn danger mt-2" id="pDelete"><i class="bi bi-trash"></i> Delete this field</button>
      </div>
    </aside>
  </div>
</div>
<div class="dz-pop" id="dzPop" hidden></div>
<script type="application/json" id="dzData"><?= json_encode(['model' => $model, 'types' => $types, 'icons' => $typeIcons], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= asset('js/form-designer.js') ?>"></script>
