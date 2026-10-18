import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { qty as fmtQty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import ItemSearch from '../../components/ItemSearch';

type Roll = { qty: string; lot_no: string; location_id: string };
/** One item on the receipt: what the supplier billed, and the rolls (or the single quantity) that actually arrived. */
type Item = { po_item_id: string; variant_id: string; label: string; tb: boolean; unit: string; dec: boolean; billed: string; left: string; unit_price: string; tax_rate: string; rolls: Roll[]; makeN: string; makeLen: string };
const blankRoll = (rack = ''): Roll => ({ qty: '', lot_no: '', location_id: rack });
const blank = (): Item => ({ po_item_id: '', variant_id: '', label: '', tb: false, unit: '', dec: true, billed: '', left: '', unit_price: '', tax_rate: '', rolls: [blankRoll()], makeN: '', makeLen: '' });
const num = (v: string) => Number(v) || 0;
const r3 = (n: number) => Math.round(n * 1000) / 1000;

/** Receive goods: against an approved purchase order (quantities, racks, supplier lots) or straight from a supplier. Each line of a roll item becomes its own roll. */
export default function GrnForm() {
  const { id: poId } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('grn');
  const src = useQuery({ queryKey: ['grn-form', poId ?? 'new'], queryFn: async () => (await get(poId ? `/purchase/orders/${poId}/receive` : '/purchase/grns/create')) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [supplier, setSupplier] = useState('');
  const [items, setItems] = useState<Item[]>([blank()]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const d = src.data.data; if (!d) return;
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) v[k] = f.custom ? '' : '';
    v.received_date = new Date().toISOString().slice(0, 10); v.warehouse_id = String(d.po?.warehouse_id ?? d.warehouses?.[0]?.id ?? '');
    setValues(v);
    // lines that come with the form (a purchase order's items, or a refused receipt): lines of one purchase-order item belong together
    const old: any[] = d.oldLines ?? []; const grouped: Item[] = [];
    for (const l of old) {
      const poId = String(l.po_item_id ?? ''); const roll: Roll = { qty: String(l.qty ?? ''), lot_no: l.lot_no ?? '', location_id: l.location_id ? String(l.location_id) : '' };
      const g = poId ? grouped.find((x) => x.po_item_id === poId) : undefined;
      if (g) { g.rolls.push(roll); continue; }
      grouped.push({ ...blank(), po_item_id: poId, variant_id: String(l.variant_id), label: l.label, tb: !!l.tb, unit: l.unit ?? '', dec: l.dec !== 0, billed: String(l.billed_qty ?? l.left ?? l.qty ?? ''), left: String(l.left ?? ''), unit_price: String(l.unit_price ?? ''), tax_rate: String(l.tax_rate ?? ''), rolls: [roll] });
    }
    setItems(grouped.length ? grouped : [blank()]);
  }, [schema.data, src.data, values]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data.data; if (!d) return <div className="alert alert-danger">{(src.data.messages ?? []).map((m: Msg) => m.msg).join(' ') || 'This purchase order cannot receive goods right now.'}</div>;
  const po = d.po; const racks: Record<string, { id: number; code: string }[]> = d.racks ?? {};
  const myRacks = racks[String(values.warehouse_id)] ?? [];
  const setItem = (i: number, patch: Partial<Item>) => setItems((xs) => xs.map((x, k) => (k === i ? { ...x, ...patch } : x)));
  const setRoll = (i: number, r: number, patch: Partial<Roll>) => setItems((xs) => xs.map((x, k) => (k === i ? { ...x, rolls: x.rolls.map((y, m) => (m === r ? { ...y, ...patch } : y)) } : x)));
  const addRoll = (i: number) => setItems((xs) => xs.map((x, k) => (k === i ? { ...x, rolls: [...x.rolls, blankRoll(x.rolls[x.rolls.length - 1]?.location_id ?? '')] } : x)));
  const dropRoll = (i: number, r: number) => setItems((xs) => xs.map((x, k) => (k === i ? { ...x, rolls: x.rolls.length > 1 ? x.rolls.filter((_, m) => m !== r) : [blankRoll()] } : x)));
  /** "4 rolls of 50 m": replaces the rolls of this item. With only the number of rolls given, the billed quantity is shared equally. */
  const makeRolls = (i: number) => setItems((xs) => xs.map((x, k) => {
    if (k !== i) return x;
    const n = Math.max(1, Math.min(200, Math.floor(num(x.makeN)))); const len = num(x.makeLen) > 0 ? num(x.makeLen) : n > 0 && num(x.billed) > 0 ? r3(num(x.billed) / n) : 0;
    if (!n || !len) return x;
    const rack = x.rolls[0]?.location_id ?? '';
    return { ...x, rolls: Array.from({ length: n }, () => blankRoll(rack)).map((y) => ({ ...y, qty: String(len) })), makeN: '', makeLen: '' };
  }));
  const sameRack = (i: number, rack: string) => setItems((xs) => xs.map((x, k) => (k === i ? { ...x, rolls: x.rolls.map((y) => ({ ...y, location_id: rack })) } : x)));
  const received = (x: Item) => r3(x.rolls.reduce((a, r) => a + num(r.qty), 0));
  const rollCount = items.reduce((a, x) => a + (x.variant_id || x.po_item_id ? x.rolls.filter((r) => num(r.qty) > 0).length : 0), 0);

  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), supplier_id: po ? po.supplier_id : supplier, po_id: po ? po.id : '',
      lines: items.filter((x) => x.variant_id || x.po_item_id).flatMap((x) => x.rolls.filter((r) => num(r.qty) > 0 || (!x.tb && x.rolls.length === 1)).map((r, n) => ({ po_item_id: x.po_item_id, variant_id: x.variant_id, qty: r.qty, unit_price: x.unit_price, tax_rate: x.tax_rate, location_id: r.location_id, lot_no: r.lot_no, billed_qty: n === 0 ? x.billed : '' }))) };
    const r = await post('/purchase/grns', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['grns', 'grn', 'po', 'pos', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/purchase/grns');
  };
  const rackSelect = (value: string, onChange: (v: string) => void) => (
    <select className="form-select form-select-sm" value={value} onChange={(e) => onChange(e.target.value)}><option value="">No {term('rack').toLowerCase()}</option>{myRacks.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}</select>
  );
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={po ? `Receive goods · ${po.po_no}` : 'Receive goods (no purchase order)'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=grn&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(po ? `/purchase/orders/${po.id}` : '/purchase/grns')}>Cancel</button>
        <button className="btn btn-success" disabled={saving}>{saving ? 'Saving…' : `Receive into stock${rollCount ? ` · ${rollCount} ${rollCount === 1 ? 'line' : 'lines'}` : ''}`}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body">
        <div className="row mb-3"><div className="col-md-4"><label className="form-label">{term('supplier')}</label>
          {po ? <input className="form-control" disabled value={po.supplier} /> : <select className="form-select" required value={supplier} onChange={(e) => setSupplier(e.target.value)}><option value="">Choose…</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>}</div></div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <p className="text-muted small mb-0">For fabric and other roll-tracked items, write what the supplier <b>billed</b>, then list the <b>rolls that really arrived</b> (for example 4 rolls of 50 m against a bill of 200 m). Every roll becomes its own {term('batch').toLowerCase()} with its own number and sits on the {term('rack').toLowerCase()} you choose. Extra cost is spread over the items by value and becomes part of their stock cost; it does not change the supplier's bill.</p>
      </div></div>

      {items.map((x, i) => {
        const got = received(x); const billed = num(x.billed) || (po ? num(x.left) : 0); const diff = r3(got - billed);
        return (
          <div className="card mb-3" key={i}>
            <div className="card-body pb-2">
              <div className="row g-2 align-items-end">
                <div className="col-lg-5"><label className="form-label small mb-1">{term('item')} {x.tb && <span className="badge text-bg-secondary ms-1">{term('batch')}</span>}</label>
                  {po ? <div className="fw-medium">{x.label}</div> : <ItemSearch value={x.variant_id} label={x.label} supplier={supplier || undefined} placeholder="Search item, SKU or barcode"
                    onPick={(p) => { if (!p) { setItem(i, { variant_id: '', label: '' }); return; } const v = p.row; setItem(i, { variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), tb: !!Number(v.track_batch), unit_price: String(v.rate ?? v.cost_price ?? ''), tax_rate: String(Number(v.tax_rate ?? 0)) }); }} />}</div>
                <div className="col-6 col-lg-2"><label className="form-label small mb-1">{po ? 'Billed / due' : 'Billed qty'}</label>
                  <div className="input-group input-group-sm"><input type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" value={x.billed} onChange={(e) => setItem(i, { billed: e.target.value })} placeholder={po && x.left ? fmtQty(x.left) : ''} />{x.unit && <span className="input-group-text">{x.unit}</span>}</div></div>
                {!po && <><div className="col-6 col-lg-2"><label className="form-label small mb-1">Unit price</label><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={x.unit_price} onChange={(e) => setItem(i, { unit_price: e.target.value })} /></div>
                  <div className="col-6 col-lg-1"><label className="form-label small mb-1">Tax %</label><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={x.tax_rate} onChange={(e) => setItem(i, { tax_rate: e.target.value })} /></div></>}
                <div className="col text-end">{!po && <button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove item" onClick={() => setItems((xs) => (xs.length > 1 ? xs.filter((_, k) => k !== i) : [blank()]))}>Remove</button>}</div>
              </div>
            </div>
            {x.variant_id || x.po_item_id ? (
              <div className="px-3 pb-3">
                <div className="table-responsive"><table className="table table-sm align-middle mb-2">
                  <thead><tr><th style={{ width: 44 }}>{x.tb ? term('batch') : '#'}</th><th style={{ width: 160 }}>{x.tb ? 'Length received' : 'Qty received'}</th><th style={{ width: 170 }}>{term('rack')}{x.rolls.length > 1 && <> <a href="#" className="small fw-normal" onClick={(e) => { e.preventDefault(); sameRack(i, x.rolls[0].location_id); }} title={`Use the first row's ${term('rack').toLowerCase()} for all`}>same for all</a></>}</th>{x.tb && <th>Supplier roll / lot no.</th>}<th style={{ width: 44 }} /></tr></thead>
                  <tbody>{x.rolls.map((r, m) => (
                    <tr key={m}><td className="text-muted">{m + 1}</td>
                      <td><div className="input-group input-group-sm"><input type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" value={r.qty} onChange={(e) => setRoll(i, m, { qty: e.target.value })} />{x.unit && <span className="input-group-text">{x.unit}</span>}</div></td>
                      <td>{rackSelect(r.location_id, (v) => setRoll(i, m, { location_id: v }))}</td>
                      {x.tb && <td><input className="form-control form-control-sm" maxLength={60} value={r.lot_no} placeholder="optional" onChange={(e) => setRoll(i, m, { lot_no: e.target.value })} /></td>}
                      <td>{(x.tb || x.rolls.length > 1) && <button type="button" className="btn btn-sm btn-link text-danger px-1" aria-label="Remove this row" onClick={() => dropRoll(i, m)}><i className="bi bi-x-lg" /></button>}</td></tr>))}</tbody>
                </table></div>
                <div className="d-flex flex-wrap gap-2 align-items-center">
                  {x.tb && <>
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => addRoll(i)}><i className="bi bi-plus-lg" /> Add a roll</button>
                    <span className="text-muted small ms-2">or</span>
                    <div className="input-group input-group-sm" style={{ width: 'auto' }}>
                      <input type="number" min="1" max="200" className="form-control" style={{ width: 70 }} placeholder="rolls" value={x.makeN} onChange={(e) => setItem(i, { makeN: e.target.value })} aria-label="Number of rolls" />
                      <span className="input-group-text">×</span>
                      <input type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" style={{ width: 90 }} placeholder={num(x.makeN) > 0 && num(x.billed) > 0 ? `${r3(num(x.billed) / Math.floor(num(x.makeN)))} each` : 'length'} value={x.makeLen} onChange={(e) => setItem(i, { makeLen: e.target.value })} aria-label="Length of each roll" />
                      {x.unit && <span className="input-group-text">{x.unit}</span>}
                      <button type="button" className="btn btn-outline-primary" onClick={() => makeRolls(i)} disabled={!(num(x.makeN) >= 1)}>Make rolls</button>
                    </div></>}
                  <span className="ms-auto small">
                    <b>{x.rolls.filter((r) => num(r.qty) > 0).length}</b> {x.tb ? (x.rolls.filter((r) => num(r.qty) > 0).length === 1 ? 'roll' : 'rolls') : 'line'} · received <b>{fmtQty(got)} {x.unit}</b>
                    {billed > 0 && (Math.abs(diff) < 0.0005 ? <span className="badge text-bg-success ms-2">matches the bill</span> : diff < 0 ? <span className="badge text-bg-warning ms-2">short by {fmtQty(-diff)} {x.unit}</span> : <span className="badge text-bg-danger ms-2">{fmtQty(diff)} {x.unit} more than billed</span>)}
                  </span>
                </div>
              </div>
            ) : <div className="px-3 pb-3 text-muted small">Choose the {term('item').toLowerCase()} to enter its rolls.</div>}
          </div>
        );
      })}
      {!po && <button type="button" className="btn btn-outline-primary mb-4" onClick={() => setItems((xs) => [...xs, blank()])}><i className="bi bi-plus-lg" /> Add another item</button>}
    </form>
  );
}
