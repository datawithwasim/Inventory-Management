/* Line editor for quotations and sales orders. Plain JS, no dependencies. */
(function () {
  var me = document.currentScript;
  var lookupUrl = me.dataset.lookup;
  var body = document.querySelector('#lineTable tbody');
  var customer = document.getElementById('customer_id');
  var warehouse = document.getElementById('warehouse_id');
  var n = 0, timer = null;

  function mk(tag, props, kids) {
    var e = document.createElement(tag);
    Object.keys(props || {}).forEach(function (k) { if (k === 'text') e.textContent = props[k]; else e.setAttribute(k, props[k]); });
    (kids || []).forEach(function (c) { e.appendChild(c); });
    return e;
  }
  function hid(name, val) { var i = mk('input', { type: 'hidden', name: name }); i.value = val == null ? '' : val; return i; }
  function num(name, val, step, w, max) {
    var i = mk('input', { name: name, type: 'number', step: step, min: '0', class: 'form-control form-control-sm', style: 'width:' + w + 'px' });
    if (max) i.max = max;
    i.value = val == null ? '' : val; return i;
  }
  function f(x) { return parseFloat(x) || 0; }
  function r2(x) { return Math.round(x * 100) / 100; }

  function recalc() {
    var gross = 0, disc = 0, tax = 0;
    Array.prototype.forEach.call(body.rows, function (r) {
      var q = f(r.querySelector('[name$="[qty]"]').value), p = f(r.querySelector('[name$="[unit_price]"]').value),
          d = f(r.querySelector('[name$="[discount_pct]"]').value), t = f(r.querySelector('[name$="[tax_rate]"]').value);
      var g = r2(q * p), dd = r2(g * d / 100), net = r2(g - dd), x = r2(net * t / 100);
      var cell = r.querySelector('.line-total'); if (cell) cell.textContent = (net + x).toFixed(2);
      gross += g; disc += dd; tax += x;
    });
    var ch = f((document.querySelector('[name=delivery_charge]') || {}).value) + f((document.querySelector('[name=installation_charge]') || {}).value);
    var set = function (id, v) { var e = document.getElementById(id); if (e) e.textContent = v.toFixed(2); };
    set('tSub', gross); set('tDisc', disc); set('tTax', tax); set('tCharges', ch); set('tTotal', gross - disc + tax + ch);
  }

  function addLine(d) {
    d = d || {};
    var i = n++, p = 'lines[' + i + ']';
    var tr = mk('tr');
    var vId = hid(p + '[variant_id]', d.variant_id), vLabel = hid(p + '[label]', d.label), vUnit = hid(p + '[unit]', d.unit), vDec = hid(p + '[dec]', d.dec);
    var search = mk('input', { type: 'text', class: 'form-control form-control-sm', placeholder: 'Search item, SKU or barcode', autocomplete: 'off' });
    search.value = d.label || '';
    var hint = mk('div', { class: 'form-text small' });
    var list = mk('div', { class: 'list-group position-absolute shadow-sm', style: 'z-index:20;max-height:260px;overflow:auto;min-width:360px', hidden: '' });
    var tdItem = mk('td', { style: 'min-width:270px;position:relative' }, [vId, vLabel, vUnit, vDec, search, hint, list]);
    var qty = num(p + '[qty]', d.qty, '0.001', 100);
    var unit = mk('span', { class: 'ms-1 text-muted small', text: d.unit || '' });
    var price = num(p + '[unit_price]', d.unit_price, '0.01', 100);
    var disc = num(p + '[discount_pct]', d.discount_pct, '0.01', 70, '100');
    var tax = num(p + '[tax_rate]', d.tax_rate, '0.01', 70, '100');
    var total = mk('td', { class: 'text-end line-total', text: '0.00' });
    var rm = mk('button', { type: 'button', class: 'btn btn-sm btn-outline-danger', text: '×' });
    rm.onclick = function () { tr.remove(); recalc(); };
    [tdItem, mk('td', { class: 'text-nowrap' }, [qty, unit]), mk('td', {}, [price]), mk('td', {}, [disc]), mk('td', {}, [tax]), total, mk('td', {}, [rm])].forEach(function (c) { tr.appendChild(c); });
    body.appendChild(tr);

    function choose(v) {
      vId.value = v.id; vLabel.value = search.value = v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ')';
      vUnit.value = v.unit; vDec.value = v.allow_decimal ? '1' : '0'; unit.textContent = v.unit;
      qty.step = v.allow_decimal ? '0.001' : '1';
      price.value = v.price; disc.value = v.discount || ''; tax.value = v.tax_rate || '';
      hint.textContent = (v.is_bundle ? 'Set · ' : '') + (v.available != null ? 'Free stock: ' + parseFloat(v.available) + ' ' + v.unit : '');
      list.hidden = true; recalc(); qty.focus();
    }
    search.oninput = function () {
      vId.value = ''; hint.textContent = ''; clearTimeout(timer);
      var q = search.value.trim();
      if (!q) { list.hidden = true; return; }
      timer = setTimeout(function () {
        var url = lookupUrl + '?q=' + encodeURIComponent(q) + '&customer=' + encodeURIComponent(customer ? customer.value : 0) + '&warehouse=' + encodeURIComponent(warehouse ? warehouse.value : 0);
        fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (rows) {
          list.innerHTML = '';
          rows.forEach(function (v) {
            var a = mk('button', { type: 'button', class: 'list-group-item list-group-item-action py-1 small',
              text: v.item_name + (v.name ? ' — ' + v.name : '') + ' (' + v.sku + ') · ' + v.price.toFixed(2) + (v.available != null ? ' · free ' + parseFloat(v.available) : '') + (v.is_bundle ? ' · set' : '') });
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
    [qty, price, disc, tax].forEach(function (el) { el.addEventListener('input', recalc); });
    if (d.dec === 0 || d.dec === '0') qty.step = '1';
    recalc();
    return tr;
  }

  document.getElementById('addLine').onclick = function () { addLine(); };
  ['delivery_charge', 'installation_charge'].forEach(function (nme) { var e = document.querySelector('[name=' + nme + ']'); if (e) e.addEventListener('input', recalc); });
  var init = JSON.parse(document.getElementById('initialLines').textContent || '[]');
  if (init.length) init.forEach(addLine); else addLine();
})();
