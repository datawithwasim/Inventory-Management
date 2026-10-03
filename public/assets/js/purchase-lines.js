/* Line editor for requisitions, purchase orders and goods receipts. Plain JS, no dependencies. */
(function () {
  var me = document.currentScript;
  var mode = me.dataset.mode;                       // 'req' | 'po' | 'grn'
  var lookupUrl = me.dataset.lookup;
  var body = document.querySelector('#lineTable tbody');
  var racks = JSON.parse((document.getElementById('rackData') || { textContent: '{}' }).textContent || '{}');
  var wh = document.getElementById('warehouse_id');
  var totalEl = document.getElementById('grandTotal');
  var n = 0, timer = null;

  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function hid(name, val) { var i = mk('input', { type: 'hidden', name: name }); i.value = val == null ? '' : val; return i; }
  function num(name, val, step, w, ph) {
    var i = mk('input', { name: name, type: 'number', step: step, min: '0', class: 'form-control form-control-sm', style: 'width:' + w + 'px' });
    if (ph) i.placeholder = ph;
    i.value = val == null ? '' : val; return i;
  }
  function fillRacks(sel, keep) {
    sel.innerHTML = '';
    sel.appendChild(mk('option', { value: '0', text: 'No rack' }));
    ((wh && racks[wh.value]) || []).forEach(function (r) { sel.appendChild(mk('option', { value: r.id, text: r.code })); });
    sel.value = String(keep || 0); if (sel.value !== String(keep || 0)) sel.value = '0';
  }

  function recalc() {
    if (!totalEl) return;
    var sum = 0;
    Array.prototype.forEach.call(body.rows, function (r) {
      var q = parseFloat(r.querySelector('[name$="[qty]"]').value) || 0, p = parseFloat(r.querySelector('[name$="[unit_price]"]').value) || 0,
          t = parseFloat(r.querySelector('[name$="[tax_rate]"]').value) || 0, tot = q * p * (1 + t / 100);
      var cell = r.querySelector('.line-total'); if (cell) cell.textContent = tot.toFixed(2);
      sum += tot;
    });
    totalEl.textContent = sum.toFixed(2);
  }

  function addLine(d) {
    d = d || {};
    var i = n++, p = 'lines[' + i + ']';
    var linked = !!d.po_item_id;                      // receiving against a PO line: item and price are fixed
    var tr = mk('tr');
    var vId = hid(p + '[variant_id]', d.variant_id), vLabel = hid(p + '[label]', d.label), vTb = hid(p + '[tb]', d.tb),
        vUnit = hid(p + '[unit]', d.unit), vDec = hid(p + '[dec]', d.dec), poItem = hid(p + '[po_item_id]', d.po_item_id);
    var search = mk('input', { type: 'text', class: 'form-control form-control-sm', placeholder: 'Search item, SKU or barcode', autocomplete: 'off' });
    search.value = d.label || '';
    var list = mk('div', { class: 'list-group position-absolute shadow-sm', style: 'z-index:20;max-height:240px;overflow:auto;min-width:320px', hidden: '' });
    var tdItem;
    if (linked) {
      search.type = 'hidden';
      var note = d.left != null ? mk('div', { class: 'form-text small', text: 'Still due: ' + parseFloat(d.left) + ' ' + (d.unit || '') }) : null;
      tdItem = mk('td', { style: 'min-width:240px' }, [vId, vLabel, vTb, vUnit, vDec, poItem, search, mk('span', { text: d.label || '' })].concat(note ? [note] : []));
    } else {
      tdItem = mk('td', { style: 'min-width:260px;position:relative' }, [vId, vLabel, vTb, vUnit, vDec, poItem, search, list]);
    }
    var cells = [tdItem];

    var qty = num(p + '[qty]', d.qty, '0.001', 110);
    var unit = mk('span', { class: 'ms-1 text-muted small', text: d.unit || '' });
    var price = num(p + '[unit_price]', d.unit_price, '0.01', 100);
    var tax = num(p + '[tax_rate]', d.tax_rate, '0.01', 80);
    tax.max = '100';
    var note = mk('input', { name: p + '[note]', class: 'form-control form-control-sm', placeholder: 'Note (optional)', maxlength: '150' }); note.value = d.note || '';
    var rack, lot, lotWrap;

    if (mode === 'grn') {
      rack = mk('select', { name: p + '[location_id]', class: 'form-select form-select-sm', style: 'min-width:120px' });
      fillRacks(rack, d.location_id);
      lot = mk('input', { name: p + '[lot_no]', class: 'form-control form-control-sm', placeholder: 'Supplier lot', style: 'width:130px' }); lot.value = d.lot_no || '';
      lotWrap = mk('td', {}, [lot, mk('div', { class: 'form-text small', text: '1 line = 1 roll' })]);
      cells.push(mk('td', {}, [rack]), lotWrap);
    }
    cells.push(mk('td', { class: 'text-nowrap' }, [qty, unit]));
    if (mode === 'po' || mode === 'grn') {
      if (linked) {
        price.readOnly = tax.readOnly = true; price.classList.add('bg-light'); tax.classList.add('bg-light');
      }
      cells.push(mk('td', {}, [price]), mk('td', {}, [tax]));
    }
    if (mode === 'po') cells.push(mk('td', { class: 'text-end line-total', text: '0.00' }));
    if (mode === 'req') cells.push(mk('td', {}, [note]));

    var actions = [];
    if (mode === 'grn' && linked) {
      var split = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary me-1', title: 'Add another roll / part for this item', text: '＋ split' });
      split.onclick = function () {
        var copy = addLine({ po_item_id: d.po_item_id, variant_id: vId.value, label: vLabel.value, tb: vTb.value, unit: vUnit.value, dec: vDec.value,
          unit_price: price.value, tax_rate: tax.value, qty: '', location_id: rack.value });
        tr.after(copy);
      };
      actions.push(split);
    }
    var rm = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: '×' });
    rm.onclick = function () { tr.remove(); recalc(); };
    actions.push(rm);
    cells.push(mk('td', { class: 'text-nowrap' }, actions));
    cells.forEach(function (c) { tr.appendChild(c); });
    body.appendChild(tr);

    function apply() {
      var tb = vTb.value === '1', dec = vDec.value === '1';
      qty.step = dec ? '0.001' : '1'; unit.textContent = vUnit.value;
      if (lotWrap) lotWrap.style.visibility = tb ? 'visible' : 'hidden';
    }
    function choose(v) {
      vId.value = v.id; vLabel.value = search.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
      vTb.value = v.track_batch ? '1' : '0'; vUnit.value = v.unit; vDec.value = v.allow_decimal ? '1' : '0';
      if (price && !price.value) price.value = parseFloat(v.cost_price) || '';
      if (tax && !tax.value) tax.value = parseFloat(v.tax_rate) || '';
      list.hidden = true; apply(); recalc(); qty.focus();
    }
    if (!linked) {
      search.oninput = function () {
        vId.value = ''; clearTimeout(timer);
        var q = search.value.trim();
        if (!q) { list.hidden = true; return; }
        timer = setTimeout(function () {
          fetch(lookupUrl + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
            list.innerHTML = '';
            rows.forEach(function (v) {
              var a = mk('button', { type: 'button', class: 'list-group-item list-group-item-action py-1 small',
                text: v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')' + (v.track_batch ? ' · roll/batch' : '') });
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
    }
    [qty, price, tax].forEach(function (el) { el.addEventListener('input', recalc); });
    tr.refillRack = function () { if (rack) fillRacks(rack, rack.value); };
    apply(); recalc();
    return tr;
  }

  document.getElementById('addLine').onclick = function () { addLine(); };
  if (wh) wh.onchange = function () { Array.prototype.forEach.call(body.rows, function (r) { r.refillRack && r.refillRack(); }); };
  var init = JSON.parse(document.getElementById('initialLines').textContent || '[]');
  if (init.length) init.forEach(addLine); else if (mode !== 'grn' || !document.getElementById('hasPo')) addLine();
})();
