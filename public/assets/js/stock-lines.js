/* Line editor for stock adjustments and transfers. Plain JS, no dependencies. */
(function () {
  var me = document.currentScript;
  var mode = me.dataset.mode;                 // 'adjust' | 'transfer'
  var lookupUrl = me.dataset.lookup, batchUrl = me.dataset.batches, placeUrl = me.dataset.placement;
  var body = document.querySelector('#lineTable tbody');
  var wh = document.getElementById('warehouse_id');                      // adjust: the warehouse; transfer: "from"
  var toWh = document.querySelector('select[name=to_warehouse_id]');     // transfer only
  var racks = JSON.parse(document.getElementById('rackData').textContent || '{}');
  var n = 0, timer = null;

  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function hid(name, val) { var i = mk('input', { type: 'hidden', name: name }); i.value = val == null ? '' : val; return i; }
  function fillRacks(sel, whId, keep) {
    var cur = keep != null ? String(keep) : sel.value;
    sel.innerHTML = '';
    sel.appendChild(mk('option', { value: '0', text: 'No rack' }));
    (racks[whId] || []).forEach(function (r) { sel.appendChild(mk('option', { value: r.id, text: r.code })); });
    sel.value = cur; if (sel.value !== cur) sel.value = '0';
  }

  function addLine(d) {
    d = d || {};
    var restored = d.location_id !== undefined;       // a row coming back after an error keeps its rack choice
    var i = n++, p = 'lines[' + i + ']';
    var tr = mk('tr');
    var search = mk('input', { type: 'text', class: 'form-control form-control-sm', placeholder: 'Search item, SKU or barcode', autocomplete: 'off' });
    search.value = d.label || '';
    var list = mk('div', { class: 'list-group position-absolute shadow-sm', style: 'z-index:20;max-height:240px;overflow:auto;min-width:320px', hidden: '' });
    var vId = hid(p + '[variant_id]', d.variant_id), vLabel = hid(p + '[label]', d.label), vTb = hid(p + '[tb]', d.tb),
        vUnit = hid(p + '[unit]', d.unit), vDec = hid(p + '[dec]', d.dec);
    var tdItem = mk('td', { style: 'min-width:260px;position:relative' }, [vId, vLabel, vTb, vUnit, vDec, search, list]);

    var sel = mk('select', { name: p + '[batch_id]', class: 'form-select form-select-sm' });
    var lot = mk('input', { name: p + '[lot_no]', class: 'form-control form-control-sm mt-1', placeholder: 'Supplier lot no. (optional)', hidden: '' });
    lot.value = d.lot_no || '';
    var none = mk('span', { class: 'text-muted small', text: '—' });
    var tdBatch = mk('td', { style: 'min-width:210px' }, [sel, lot, none]);

    var rackFrom = mk('select', { name: p + '[location_id]', class: 'form-select form-select-sm' });
    var hint = mk('div', { class: 'form-text small' });
    var tdRack = mk('td', { style: 'min-width:150px' }, [rackFrom, hint]);
    var rackTo = null, tdRackTo = null;
    if (mode === 'transfer') {
      rackTo = mk('select', { name: p + '[to_location_id]', class: 'form-select form-select-sm' });
      tdRackTo = mk('td', { style: 'min-width:150px' }, [rackTo]);
    }

    var qty = mk('input', { name: p + '[qty]', type: 'number', step: '0.001', class: 'form-control form-control-sm', style: 'width:110px' });
    qty.value = d.qty || '';
    if (mode === 'transfer') qty.min = '0';
    var unit = mk('span', { class: 'ms-1 text-muted small', text: d.unit || '' });
    var tdQty = mk('td', { class: 'text-nowrap' }, [qty, unit]);

    var cells = [tdItem, tdBatch, tdRack];
    if (tdRackTo) cells.push(tdRackTo);
    cells.push(tdQty);
    if (mode === 'adjust') {
      var cost = mk('input', { name: p + '[unit_cost]', type: 'number', step: '0.01', min: '0', class: 'form-control form-control-sm', style: 'width:100px', placeholder: 'default' });
      cost.value = d.unit_cost || '';
      cells.push(mk('td', {}, [cost]));
    }
    var rm = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: '×' });
    rm.onclick = function () { tr.remove(); };
    cells.push(mk('td', {}, [rm]));
    cells.forEach(function (c) { tr.appendChild(c); });
    body.appendChild(tr);

    fillRacks(rackFrom, wh.value, d.location_id || 0);
    if (rackTo) fillRacks(rackTo, toWh.value, d.to_location_id || 0);

    function state() { return { tb: vTb.value === '1', dec: vDec.value === '1' }; }
    function applyStep() { qty.step = state().dec ? '0.001' : '1'; unit.textContent = vUnit.value; }

    // Where is this stock right now? Show it, and (unless the user already chose) pre-pick the fullest rack.
    function placement(autoPick) {
      hint.textContent = '';
      if (!vId.value) return;
      var s = state(), b = sel.value;
      if (s.tb && !/^\d+$/.test(b)) return;                      // new batch: nothing stored yet
      var url = placeUrl + '?variant=' + encodeURIComponent(vId.value) + '&warehouse=' + encodeURIComponent(wh.value) + (s.tb ? '&batch=' + b : '');
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        if (!rows.length) { hint.textContent = 'Nothing in stock here'; return; }
        hint.textContent = 'In stock: ' + rows.map(function (r) { return (r.code || 'No rack') + ' ' + parseFloat(r.qty); }).join(' · ');
        if (autoPick) { rackFrom.value = String(rows[0].location_id); if (rackFrom.value !== String(rows[0].location_id)) rackFrom.value = '0'; }
      });
    }

    function loadBatches(keep, autoPick) {
      var s = state();
      sel.hidden = !s.tb; none.hidden = s.tb; lot.hidden = true;
      sel.disabled = !s.tb;
      if (!s.tb || !vId.value) { sel.innerHTML = ''; placement(autoPick); return; }
      var url = batchUrl + '?variant=' + encodeURIComponent(vId.value) + '&warehouse=' + encodeURIComponent(wh.value) + (mode === 'transfer' ? '&in_stock=1' : '');
      fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
        sel.innerHTML = '';
        if (mode === 'adjust') sel.appendChild(mk('option', { value: 'new', text: '+ New batch (new roll)' }));
        else sel.appendChild(mk('option', { value: '', text: rows.length ? 'Choose batch…' : 'No batches in stock here' }));
        rows.forEach(function (b) {
          var t = b.batch_no + (b.supplier_lot ? ' · lot ' + b.supplier_lot : '') + ' · balance ' + parseFloat(b.balance);
          sel.appendChild(mk('option', { value: b.id, text: t }));
        });
        if (keep) sel.value = keep;
        syncLot();
        placement(autoPick);
      });
    }
    function syncLot() { lot.hidden = !(mode === 'adjust' && state().tb && sel.value === 'new'); }
    sel.onchange = function () { syncLot(); placement(true); };
    tr.reloadFrom = function () { fillRacks(rackFrom, wh.value); loadBatches(sel.value, true); };
    tr.reloadTo = function () { if (rackTo) fillRacks(rackTo, toWh.value); };

    function choose(v) {
      vId.value = v.id; vLabel.value = search.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
      vTb.value = v.track_batch ? '1' : '0'; vUnit.value = v.unit; vDec.value = v.allow_decimal ? '1' : '0';
      list.hidden = true; applyStep(); loadBatches(null, true); qty.focus();
    }
    search.oninput = function () {
      vId.value = ''; hint.textContent = ''; clearTimeout(timer);
      var q = search.value.trim();
      if (!q) { list.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(lookupUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
          list.innerHTML = '';
          rows.forEach(function (v) {
            var a = mk('button', { type: 'button', class: 'list-group-item list-group-item-action py-1 small',
              text: v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')' + (v.track_batch ? ' · batch' : '') });
            a.onclick = function () { choose(v); };
            list.appendChild(a);
          });
          if (!rows.length) list.appendChild(mk('div', { class: 'list-group-item small text-muted', text: 'No items found' }));
          list.hidden = false;
        });
      }, 200);
    };
    search.onkeydown = function (e) { if (e.key === 'Enter') e.preventDefault(); };
    document.addEventListener('click', function (e) { if (!tr.contains(e.target)) list.hidden = true; });
    if (d.variant_id) { applyStep(); loadBatches(d.batch_id, !restored); if (d.lot_no) lot.hidden = false; }
    return tr;
  }

  document.getElementById('addLine').onclick = function () { addLine(); };
  wh.onchange = function () { Array.prototype.forEach.call(body.rows, function (r) { r.reloadFrom && r.reloadFrom(); }); };
  if (toWh) toWh.onchange = function () { Array.prototype.forEach.call(body.rows, function (r) { r.reloadTo && r.reloadTo(); }); };
  var init = JSON.parse(document.getElementById('initialLines').textContent || '[]');
  if (init.length) init.forEach(addLine); else addLine();
})();
