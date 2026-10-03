<div class="fdz" id="fdz">
  <div class="fdz-bar">
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <label class="small text-muted m-0" for="fdzForm">Form</label>
      <select id="fdzForm" class="form-select form-select-sm" style="width:auto;min-width:230px"></select>
      <span class="badge text-bg-primary d-none" id="fdzCustom">customised</span>
    </div>
    <div class="d-flex align-items-center gap-2">
      <span class="small text-warning-emphasis d-none" id="fdzDirty"><i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>Unsaved changes</span>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="fdzUndo" disabled><i class="bi bi-arrow-counterclockwise"></i> Undo</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="fdzReset"><i class="bi bi-eraser"></i> Reset this form</button>
      <form method="post" action="<?= url('settings/formdesign') ?>" id="fdzSave" class="m-0" data-noload><?= csrf_field() ?><input type="hidden" name="payload" id="fdzPayload">
        <button class="btn btn-sm btn-primary"><i class="bi bi-check2"></i> Save all forms</button></form>
    </div>
  </div>

  <div class="fdz-body">
    <div class="fdz-stage">
      <div class="fdz-hint"><i class="bi bi-info-circle me-1"></i>Drag a field to move it. Click a field to edit it. Use the width buttons to make it narrower or wider.</div>
      <div class="fdz-paper">
        <div class="fdz-paper-title" id="fdzTitle"></div>
        <div class="fdz-canvas" id="fdzCanvas" aria-label="Form layout"></div>
        <div class="fdz-fake-actions"><span class="btn btn-primary btn-sm disabled">Save</span> <span class="text-muted small ms-2">Cancel</span></div>
      </div>
      <div class="fdz-tray" id="fdzTrayWrap" hidden>
        <div class="fdz-tray-title"><i class="bi bi-eye-slash me-1"></i>Hidden fields <span class="text-muted fw-normal">— not shown on the form. Click + to bring one back.</span></div>
        <div id="fdzTray" class="d-flex flex-wrap gap-2"></div>
      </div>
    </div>

    <aside class="fdz-panel" id="fdzPanel">
      <div class="fdz-empty" id="fdzEmpty"><i class="bi bi-hand-index-thumb"></i><div>Select a field on the form to change its name, width, hint or whether it is required.</div></div>
      <div id="fdzProps" hidden>
        <div class="fdz-props-head"><i class="bi" id="pIcon"></i><div><div class="fw-semibold" id="pName"></div><div class="small text-muted" id="pType"></div></div></div>
        <label class="form-label mt-3" for="pLabel">Label on the form</label>
        <input class="form-control form-control-sm" id="pLabel" maxlength="60">
        <label class="form-label mt-3" for="pHelp">Hint under the field</label>
        <input class="form-control form-control-sm" id="pHelp" maxlength="140" placeholder="Optional, e.g. as printed on the GST certificate">
        <div class="form-label mt-3">Width</div>
        <div class="fdz-seg" id="pWidth" role="group">
          <?php foreach ($widths as $k => $l): ?><button type="button" data-w="<?= e((string)$k) ?>"><?= e($k === '' ? 'Auto' : ($k === '100' ? 'Full' : $k . '%')) ?></button><?php endforeach; ?>
        </div>
        <div id="pOptional">
          <div class="fdz-switch mt-3"><span>Show on the form</span><span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="pShow"></span></div>
          <div class="fdz-switch"><span>Must be filled in</span><span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="pReq"></span></div>
        </div>
        <div class="small text-muted mt-3" id="pCore" hidden><i class="bi bi-lock me-1"></i>This is an essential field: it always shows and cannot be removed.</div>
        <div class="d-flex gap-2 mt-4" id="pMove">
          <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="pUp"><i class="bi bi-arrow-up"></i> Earlier</button>
          <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="pDown"><i class="bi bi-arrow-down"></i> Later</button>
        </div>
      </div>
    </aside>
  </div>
</div>
<script type="application/json" id="fdzData"><?= json_encode($model, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<script src="<?= asset('js/form-designer.js') ?>"></script>
