/* Line editor for stock adjustments and transfers. Plain JS, no dependencies. */
(function () {
  var me = document.currentScript;
  var mode = me.dataset.mode;                 // 'adjust' | 'transfer'
  var lookupUrl = me.dataset.lookup, batchUrl = me.dataset.batches;
  var body = document.querySelector('#lineTable tbody');
  var wh = document.getElementById('warehouse_id');
  var n = 0, timer = null;

  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function hid(name, val) { var i = mk('input', { type: 'hidden', name: name }); i.value = val == null ? '' : val; return i; }

  function addLine(d) {
    d = d || {};
    var i = n++, p = 'lines[' + i + ']';
    var tr = mk('tr');
    var search = mk('input', { type: 'text', class: 'form-control form-control-sm', placeholder: 'Search item, SKU or barcode', autocomplete: 'off' });
    search.value = d.label || '';
    var list = mk('div', { class: 'list-group position-absolute shadow-sm', style: 'z-index:20;max-height:240px;overflow:auto;min-width:320px', hidden: '' });
    var vId = hid(p + '[variant_id]', d.variant_id), vLabel = hid(p + '[label]', d.label), vTb = hid(p + '[tb]', d.tb),
        vUnit = hid(p + '[unit]', d.unit), vDec = hid(p + '[dec]', d.dec);
    var tdItem = mk('td', { style: 'min-width:280px;position:relative' }, [vId, vLabel, vTb, vUnit, vDec, search, list]);

    var sel = mk('select', { name: p + '[batch_id]', class: 'form-select form-select-sm' });
    var lot = mk('input', { name: p + '[lot_no]', class: 'form-control form-control-sm mt-1', placeholder: 'Supplier lot no. (optional)', hidden: '' });
    lot.value = d.lot_no || '';
    var none = mk('span', { class: 'text-muted small', text: '—' });
    var tdBatch = mk('td', { style: 'min-width:220px' }, [sel, lot, none]);

    var qty = mk('input', { name: p + '[qty]', type: 'number', step: '0.001', class: 'form-control form-control-sm', style: 'width:110px' });
    qty.value = d.qty || '';
    if (mode === 'transfer') qty.min = '0';
    var unit = mk('span', { class: 'ms-1 text-muted small', text: d.unit || '' });
    var tdQty = mk('td', { class: 'text-nowrap' }, [qty, unit]);

    var cells = [tdItem, tdBatch, tdQty];
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

    function state() { return { tb: vTb.value === '1', dec: vDec.value === '1' }; }
    function applyStep() { qty.step = state().dec ? '0.001' : '1'; unit.textContent = vUnit.value; }
    function loadBatches(keep) {
      var s = state();
      sel.hidden = !s.tb; none.hidden = s.tb; lot.hidden = true;
      sel.disabled = !s.tb;
      if (!s.tb || !vId.value) { sel.innerHTML = ''; return; }
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
      });
    }
    function syncLot() { lot.hidden = !(mode === 'adjust' && state().tb && sel.value === 'new'); }
    sel.onchange = syncLot;
    tr.reload = function () { loadBatches(sel.value); };

    function choose(v) {
      vId.value = v.id; vLabel.value = search.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
      vTb.value = v.track_batch ? '1' : '0'; vUnit.value = v.unit; vDec.value = v.allow_decimal ? '1' : '0';
      list.hidden = true; applyStep(); loadBatches(); qty.focus();
    }
    search.oninput = function () {
      vId.value = ''; clearTimeout(timer);
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
    if (d.variant_id) { applyStep(); loadBatches(d.batch_id); if (d.lot_no) lot.hidden = false; }
    return tr;
  }

  document.getElementById('addLine').onclick = function () { addLine(); };
  if (wh) wh.onchange = function () { Array.prototype.forEach.call(body.rows, function (r) { r.reload && r.reload(); }); };
  var init = JSON.parse(document.getElementById('initialLines').textContent || '[]');
  if (init.length) init.forEach(addLine); else addLine();
})();
