/* Delivery editor: pick the roll (batch) and rack each line is taken from. Plain JS, no dependencies. */
(function () {
  var me = document.currentScript;
  var stockUrl = me.dataset.stock, lookupUrl = me.dataset.lookup, fixedOrder = me.dataset.order === '1';
  var body = document.querySelector('#lineTable tbody');
  var wh = document.getElementById('warehouse_id');
  var customer = document.getElementById('customer_id');
  var n = 0, timer = null;

  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function hid(name, val) { var i = mk('input', { type: 'hidden', name: name }); i.value = val == null ? '' : val; return i; }
  function num(name, val, step, w, ro) {
    var i = mk('input', { name: name, type: 'number', step: step, min: '0', class: 'form-control form-control-sm' + (ro ? ' bg-light' : ''), style: 'width:' + w + 'px' });
    i.value = val == null ? '' : val; if (ro) i.readOnly = true; return i;
  }
  var fmt = function (x) { return String(parseFloat(x)); };

  function shadeCheck() {
    var byVariant = {};
    Array.prototype.forEach.call(body.rows, function (r) {
      if (!r.getBatch) return;
      var v = r.getVariant(), b = r.getBatch();
      if (!v || !b) return;
      (byVariant[v] = byVariant[v] || {})[b] = 1;
    });
    Array.prototype.forEach.call(body.rows, function (r) {
      if (!r.shade) return;
      var v = r.getVariant();
      r.shade.hidden = !(v && byVariant[v] && Object.keys(byVariant[v]).length > 1);
    });
  }

  function addLine(d) {
    d = d || {};
    var i = n++, p = 'lines[' + i + ']';
    var linked = !!d.order_item_id, bundle = !!d.bundle || d.bundle === '1';
    var tr = mk('tr');
    var vId = hid(p + '[variant_id]', d.variant_id), vLabel = hid(p + '[label]', d.label), vTb = hid(p + '[tb]', d.tb), vUnit = hid(p + '[unit]', d.unit),
        vDec = hid(p + '[dec]', d.dec), vBun = hid(p + '[bundle]', d.bundle ? 1 : 0), oi = hid(p + '[order_item_id]', d.order_item_id);
    var search = mk('input', { type: 'text', class: 'form-control form-control-sm', placeholder: 'Search item, SKU or barcode', autocomplete: 'off' });
    search.value = d.label || '';
    var info = mk('div', { class: 'form-text small' });
    var shade = mk('div', { class: 'small text-warning', text: '⚠ Cut from different rolls – the shade may differ.', hidden: '' });
    var list = mk('div', { class: 'ip-list', hidden: '' });
    var tdItem;
    if (linked) {
      search.type = 'hidden';
      var note = (d.left != null ? 'Still due: ' + fmt(d.left) + ' ' + (d.unit || '') : '');
      info.textContent = note;
      tdItem = mk('td', { style: 'min-width:240px' }, [vId, vLabel, vTb, vUnit, vDec, vBun, oi, search, mk('span', { text: d.label || '' }), info, shade]);
    } else {
      tdItem = mk('td', { style: 'min-width:260px;position:relative' }, [vId, vLabel, vTb, vUnit, vDec, vBun, oi, search, info, shade, list]);
    }
    if (d.warn) info.appendChild(mk('div', { class: 'text-danger', text: d.warn }));

    var batch = mk('select', { name: p + '[batch_id]', class: 'form-select form-select-sm', style: 'min-width:200px' });
    var rack = mk('select', { name: p + '[location_id]', class: 'form-select form-select-sm', style: 'min-width:130px' });
    var auto = mk('span', { class: 'small text-muted', text: 'Parts are picked automatically', hidden: '' });
    var tdStock = mk('td', { colspan: '1', style: 'min-width:210px' }, [batch, auto]);
    var tdRack = mk('td', {}, [rack]);
    var qty = num(p + '[qty]', d.qty, '0.001', 100);
    var unit = mk('span', { class: 'ms-1 text-muted small', text: d.unit || '' });
    var price = num(p + '[unit_price]', d.unit_price, '0.01', 90, linked), disc = num(p + '[discount_pct]', d.discount_pct, '0.01', 65, linked), tax = num(p + '[tax_rate]', d.tax_rate, '0.01', 65, linked);
    var acts = [];
    if (linked && !bundle) {
      var split = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary me-1', title: 'Take part of it from another roll / rack', text: '＋ split' });
      split.onclick = function () {
        tr.after(addLine({ order_item_id: oi.value, variant_id: vId.value, label: vLabel.value, tb: vTb.value, unit: vUnit.value, dec: vDec.value, bundle: 0,
          unit_price: price.value, discount_pct: disc.value, tax_rate: tax.value, qty: '', batch_id: 0, location_id: 0 }));
      };
      acts.push(split);
    }
    var rm = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: '×' });
    rm.onclick = function () { tr.remove(); shadeCheck(); };
    acts.push(rm);
    [tdItem, tdStock, tdRack, mk('td', { class: 'text-nowrap' }, [qty, unit]), mk('td', {}, [price]), mk('td', {}, [disc]), mk('td', {}, [tax]), mk('td', { class: 'text-nowrap' }, acts)].forEach(function (c) { tr.appendChild(c); });
    body.appendChild(tr);
    tr.shade = shade;
    tr.getVariant = function () { return vId.value; };
    tr.getBatch = function () { return vTb.value === '1' ? batch.value : ''; };

    var cache = null;
    function racksFor(batchId) {
      if (!cache) return [];
      if (cache.tb) { var b = cache.batches.filter(function (x) { return String(x.id) === String(batchId); })[0]; return b ? b.racks : []; }
      return cache.racks;
    }
    function fillRacks(keep) {
      var rows = racksFor(batch.value);
      rack.innerHTML = '';
      rows.forEach(function (r) { rack.appendChild(mk('option', { value: r.location_id, text: (r.code || 'No rack') + ' · ' + fmt(r.qty) })); });
      if (!rows.length) rack.appendChild(mk('option', { value: '0', text: 'No stock here' }));
      if (keep != null && rows.some(function (r) { return String(r.location_id) === String(keep); })) rack.value = String(keep);
    }
    function load(keepBatch, keepRack) {
      var tb = vTb.value === '1', isBundle = vBun.value === '1';
      batch.hidden = !tb || isBundle; auto.hidden = !isBundle; tdRack.style.visibility = isBundle ? 'hidden' : 'visible';
      if (!vId.value || isBundle) { shadeCheck(); return; }
      var wid = wh ? wh.value : me.dataset.warehouse;
      fetch(stockUrl + '?variant=' + encodeURIComponent(vId.value) + '&warehouse=' + encodeURIComponent(wid), { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (data) {
        cache = data;
        if (data.tb) {
          batch.innerHTML = '';
          batch.appendChild(mk('option', { value: '0', text: data.batches.length ? 'Choose the roll…' : 'No rolls in stock here' }));
          data.batches.forEach(function (b) { batch.appendChild(mk('option', { value: b.id, text: b.batch_no + (b.lot ? ' · lot ' + b.lot : '') + ' · ' + fmt(b.balance) + ' left' })); });
          if (keepBatch) batch.value = String(keepBatch);
        }
        fillRacks(keepRack);
        shadeCheck();
      });
    }
    batch.onchange = function () { fillRacks(); shadeCheck(); };
    tr.reload = function () { load(batch.value, rack.value); };

    function choose(v) {
      vId.value = v.id; vLabel.value = search.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
      vTb.value = v.track_batch ? '1' : '0'; vUnit.value = v.unit; vDec.value = v.allow_decimal ? '1' : '0'; vBun.value = v.is_bundle ? '1' : '0';
      unit.textContent = v.unit; qty.step = v.allow_decimal ? '0.001' : '1';
      price.value = v.price; disc.value = v.discount || ''; tax.value = v.tax_rate || '';
      info.textContent = v.available != null ? 'Free stock: ' + fmt(v.available) + ' ' + v.unit : '';
      list.hidden = true; load(); qty.focus();
    }
    if (!linked) {
      ItemPicker.attach({ input: search, list: list, host: tr,
        url: function (q) { return lookupUrl + '?q=' + encodeURIComponent(q) + '&customer=' + encodeURIComponent(customer ? customer.value : 0) + '&warehouse=' + encodeURIComponent(wh ? wh.value : 0); },
        onType: function () { vId.value = ''; },
        render: function (v) { return { title: v.item_name + (v.name ? ' — ' + v.name : ''), sub: v.sku + (v.available != null ? ' · free ' + fmt(v.available) + ' ' + v.unit : '') + (v.is_bundle ? ' · set' : ''), right: v.price.toFixed(2) }; },
        pick: choose });
    }
    if (d.multi) shade.hidden = false;
    if (d.variant_id) load(d.batch_id, d.location_id);
    return tr;
  }

  document.getElementById('addLine').onclick = function () { addLine(); };
  if (wh && !fixedOrder) wh.onchange = function () { Array.prototype.forEach.call(body.rows, function (r) { r.reload && r.reload(); }); };
  var init = JSON.parse(document.getElementById('initialLines').textContent || '[]');
  if (init.length) init.forEach(addLine); else if (!fixedOrder) addLine();
})();
