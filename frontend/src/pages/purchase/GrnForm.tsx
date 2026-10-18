import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { qty as fmtQty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import ItemSearch from '../../components/ItemSearch';

/** One roll (or, for an item that is not tracked by roll, the one quantity). location_id '' means the rack chosen for the item. */
type Roll = { qty: string; lot_no: string; location_id: string };
/** One item on the receipt: what the supplier billed, and the rolls that actually arrived. */
type Item = { po_item_id: string; variant_id: string; label: string; tb: boolean; unit: string; dec: boolean; billed: string; left: string; unit_price: string; tax_rate: string; rack: string; rolls: Roll[] };
const blankRoll = (): Roll => ({ qty: '', lot_no: '', location_id: '' });
const blank = (): Item => ({ po_item_id: '', variant_id: '', label: '', tb: false, unit: '', dec: true, billed: '', left: '', unit_price: '', tax_rate: '', rack: '', rolls: [blankRoll()] });
const num = (v: string) => Number(v) || 0;
const r3 = (n: number) => Math.round(n * 1000) / 1000;
const MAX_ROLLS = 200;

/** "4x50", "50, 50, 48.5", "3x50 + 48.5" → [50, 50, 50, 48.5]; a string is the reason it cannot be read. */
function parseRolls(text: string, dec: boolean): number[] | string {
  const t = text.replace(/\s*[x×*]\s*/gi, 'x').trim(); if (!t) return [];
  const out: number[] = [];
  for (const tok of t.split(/[,;+\s]+/).filter(Boolean)) {
    const m = tok.match(/^(?:(\d+)x)?(\d+(?:\.\d+)?)$/i); if (!m) return `"${tok}" is not a length`;
    const n = m[1] ? parseInt(m[1], 10) : 1; const len = Number(m[2]);
    if (!(len > 0)) return 'a length must be more than 0';
    if (!dec && !Number.isInteger(len)) return 'whole numbers only for this item';
    if (out.length + n > MAX_ROLLS) return `up to ${MAX_ROLLS} rolls for one item`;
    for (let k = 0; k < n; k++) out.push(len);
  }
  return out;
}
/** [50, 50, 50, 48.5] → "3x50, 48.5" */
function rollsText(lengths: number[]): string {
  const parts: string[] = [];
  for (let k = 0; k < lengths.length;) { let e = k; while (e < lengths.length && lengths[e] === lengths[k]) e++; parts.push(e - k > 1 ? `${e - k}x${lengths[k]}` : String(lengths[k])); k = e; }
  return parts.join(', ');
}

/** Receive goods: against an approved purchase order (quantities, racks, supplier lots) or straight from a supplier. Each line of a roll item becomes its own roll. */
export default function GrnForm() {
  const { id: poId } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('grn');
  const src = useQuery({ queryKey: ['grn-form', poId ?? 'new'], queryFn: async () => (await get(poId ? `/purchase/orders/${poId}/receive` : '/purchase/grns/create')) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [supplier, setSupplier] = useState('');
  const [items, setItems] = useState<Item[]>([blank()]);
  const rollInputs = useRef<(HTMLInputElement | null)[]>([]);
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
  const change = (i: number, fn: (x: Item) => Item) => setItems((xs) => xs.map((x, k) => (k === i ? fn(x) : x)));
  const received = (x: Item) => r3(x.rolls.reduce((a, r) => a + num(r.qty), 0));
  const filled = (x: Item) => x.rolls.filter((r) => num(r.qty) > 0).length;
  const live = items.filter((x) => x.variant_id || x.po_item_id);
  const rollTotal = live.reduce((a, x) => a + filled(x), 0);
  const differ = live.filter((x) => { const b = num(x.billed) || (po ? num(x.left) : 0); return b > 0 && Math.abs(received(x) - b) >= 0.0005; }).length;
  /** Types of rolls as text → rolls; lot numbers and per-roll racks stay with the roll number they were on. */
  const applyText = (i: number, lengths: number[]) => change(i, (x) => ({ ...x, rolls: lengths.map((l, k) => ({ qty: String(l), lot_no: x.rolls[k]?.lot_no ?? '', location_id: x.rolls[k]?.location_id ?? '' })) }));

  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), supplier_id: po ? po.supplier_id : supplier, po_id: po ? po.id : '',
      lines: live.flatMap((x) => x.rolls.filter((r) => num(r.qty) > 0 || (!x.tb && x.rolls.length === 1)).map((r, n) => ({ po_item_id: x.po_item_id, variant_id: x.variant_id, qty: r.qty, unit_price: x.unit_price, tax_rate: x.tax_rate, location_id: r.location_id || x.rack, lot_no: r.lot_no, billed_qty: n === 0 ? x.billed : '' }))) };
    const r = await post('/purchase/grns', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['grns', 'grn', 'po', 'pos', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/purchase/grns');
  };
  const rackSelect = (value: string, onChange: (v: string) => void, first: string) => (
    <select className="form-select form-select-sm" value={value} onChange={(e) => onChange(e.target.value)}><option value="">{first}</option>{myRacks.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}</select>
  );
  const cols = 6 + (po ? 0 : 2);
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={po ? `Receive goods · ${po.po_no}` : 'Receive goods (no purchase order)'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=grn&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(po ? `/purchase/orders/${po.id}` : '/purchase/grns')}>Cancel</button>
        <button className="btn btn-success" disabled={saving}>{saving ? 'Saving…' : `Receive into stock${live.length ? ` · ${live.length} ${live.length === 1 ? 'item' : 'items'}, ${rollTotal} ${rollTotal === 1 ? 'line' : 'lines'}` : ''}`}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body">
        <div className="row mb-3"><div className="col-md-4"><label className="form-label">{term('supplier')}</label>
          {po ? <input className="form-control" disabled value={po.supplier} /> : <select className="form-select" required value={supplier} onChange={(e) => setSupplier(e.target.value)}><option value="">Choose…</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>}</div></div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <p className="text-muted small mb-0">Extra cost is spread over the items by value and becomes part of their stock cost; it does not change the supplier's bill.</p>
      </div></div>

      <div className="card mb-3">
        <div className="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"><span>Items <span className="act-n">{live.length}</span></span>
          <span className="small text-muted">Write the <b>billed</b> quantity, then the rolls that arrived: <code>4x50</code>, <code>50, 50, 48.5</code> or <code>3x50 + 48.5</code>. Each roll becomes its own {term('batch').toLowerCase()}.</span></div>
        <div style={{ overflow: 'visible' }}><table className="table table-sm align-middle mb-0">
          <thead><tr><th style={{ minWidth: 230 }}>{term('item')}</th><th style={{ width: 120 }}>{po ? 'Billed / due' : 'Billed qty'}</th><th style={{ minWidth: 200 }}>Rolls received</th><th style={{ width: 150 }}>Received</th><th style={{ width: 150 }}>{term('rack')}</th>
            {!po && <><th style={{ width: 100 }}>Unit price</th><th style={{ width: 70 }}>Tax %</th></>}<th style={{ width: 84 }} /></tr></thead>
          {items.map((x, i) => {
            const got = received(x); const billed = num(x.billed) || (po ? num(x.left) : 0); const diff = r3(got - billed); const has = !!(x.variant_id || x.po_item_id);
            return (
              <ItemRows key={i} x={x} i={i} has={has} got={got} billed={billed} diff={diff} po={!!po} cols={cols} term={term} supplier={supplier} rollInputs={rollInputs}
                change={change} applyText={applyText} rackSelect={rackSelect} onRemove={() => setItems((xs) => (xs.length > 1 ? xs.filter((_, k) => k !== i) : [blank()]))} />
            );
          })}
          <tfoot><tr><td colSpan={cols + 1} className="small text-muted">{live.length} {live.length === 1 ? 'item' : 'items'} · {rollTotal} {rollTotal === 1 ? 'line' : 'lines'} to stock{differ > 0 ? <span className="ms-2 text-warning-emphasis"><i className="bi bi-exclamation-triangle" /> {differ} {differ === 1 ? 'item differs' : 'items differ'} from the bill</span> : live.length ? <span className="ms-2 text-success"><i className="bi bi-check-circle" /> everything matches the bill</span> : null}</td></tr></tfoot>
        </table></div>
      </div>
      {!po && <button type="button" className="btn btn-outline-primary mb-4" onClick={() => setItems((xs) => [...xs, blank()])}><i className="bi bi-plus-lg" /> Add another item</button>}
    </form>
  );
}

/** One item: a single compact row (billed, rolls typed as text, received, rack, price) and, when opened, the roll-by-roll editor. */
function ItemRows({ x, i, has, got, billed, diff, po, cols, term, supplier, rollInputs, change, applyText, rackSelect, onRemove }: {
  x: Item; i: number; has: boolean; got: number; billed: number; diff: number; po: boolean; cols: number; term: (k: string, p?: boolean) => string; supplier: string; rollInputs: React.MutableRefObject<(HTMLInputElement | null)[]>;
  change: (i: number, fn: (x: Item) => Item) => void; applyText: (i: number, l: number[]) => void; rackSelect: (v: string, on: (v: string) => void, first: string) => React.ReactNode; onRemove: () => void;
}) {
  const shown = rollsText(x.rolls.map((r) => num(r.qty)).filter((n) => n > 0));
  const [draft, setDraft] = useState<string | null>(null); const [err, setErr] = useState(''); const [modal, setModal] = useState(false);
  const commit = () => {
    if (draft === null) return;
    const r = parseRolls(draft, x.dec);
    if (typeof r === 'string') { setErr(r); return; }
    setErr(''); setDraft(null);
    if (r.length) applyText(i, r); else change(i, (y) => ({ ...y, rolls: [blankRoll()] }));
  };
  const n = x.rolls.filter((r) => num(r.qty) > 0).length;
  return (
    <tbody>
      <tr>
        <td>
          {po ? <><span className="fw-medium">{x.label}</span>{x.tb && <> <span className="badge text-bg-secondary">{term('batch')}</span></>}</>
            : <ItemSearch value={x.variant_id} label={x.label} supplier={supplier || undefined} placeholder="Search item, SKU or barcode"
              onPick={(p) => { if (!p) { change(i, (y) => ({ ...y, variant_id: '', label: '' })); return; } const v = p.row; change(i, (y) => ({ ...y, variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), tb: !!Number(v.track_batch), unit_price: String(v.rate ?? v.cost_price ?? ''), tax_rate: String(Number(v.tax_rate ?? 0)) })); setTimeout(() => rollInputs.current[i]?.focus(), 60); }} />}
        </td>
        <td><div className="input-group input-group-sm"><input type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" value={x.billed} placeholder={po && x.left ? fmtQty(x.left) : ''} onChange={(e) => change(i, (y) => ({ ...y, billed: e.target.value }))} />{x.unit && <span className="input-group-text">{x.unit}</span>}</div></td>
        <td>
          {!has ? <span className="text-muted small">choose the {term('item').toLowerCase()} first</span> : x.tb ? (
            <>
              <input ref={(el) => { rollInputs.current[i] = el; }} className={`form-control form-control-sm ${err ? 'is-invalid' : ''}`} placeholder="e.g. 4x50 or 50, 50, 48.5" value={draft ?? shown} aria-label="Rolls received"
                onChange={(e) => { setDraft(e.target.value); setErr(''); }} onFocus={(e) => e.currentTarget.select()} onBlur={commit}
                onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); commit(); } else if (e.key === 'Escape') { setDraft(null); setErr(''); } }} />
              {err && <div className="invalid-feedback d-block">{err}</div>}
            </>
          ) : <div className="input-group input-group-sm"><input ref={(el) => { rollInputs.current[i] = el; }} type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" value={x.rolls[0]?.qty ?? ''} aria-label="Qty received" onChange={(e) => change(i, (y) => ({ ...y, rolls: [{ ...y.rolls[0], qty: e.target.value }] }))} />{x.unit && <span className="input-group-text">{x.unit}</span>}</div>}
        </td>
        <td className="small text-nowrap">{has && (got > 0 || billed > 0) && <>
          <b>{fmtQty(got)} {x.unit}</b>{x.tb && n > 0 && <span className="text-muted"> · {n} {n === 1 ? 'roll' : 'rolls'}</span>}
          {billed > 0 && got > 0 && <div>{Math.abs(diff) < 0.0005 ? <span className="text-success"><i className="bi bi-check-circle" /> matches</span> : diff < 0 ? <span className="badge text-bg-warning">short {fmtQty(-diff)}</span> : <span className="badge text-bg-danger">+{fmtQty(diff)} extra</span>}</div>}</>}</td>
        <td>{has ? rackSelect(x.rack, (v) => change(i, (y) => ({ ...y, rack: v })), `No ${term('rack').toLowerCase()}`) : null}</td>
        {!po && <><td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={x.unit_price} onChange={(e) => change(i, (y) => ({ ...y, unit_price: e.target.value }))} /></td>
          <td><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={x.tax_rate} onChange={(e) => change(i, (y) => ({ ...y, tax_rate: e.target.value }))} /></td></>}
        <td className="text-nowrap text-end">
          {has && x.tb && <button type="button" className="btn btn-sm btn-outline-secondary me-1" title={`Roll by roll: lengths, a different ${term('rack').toLowerCase()} for one roll, supplier roll numbers`} onClick={() => setModal(true)}><i className="bi bi-list-ol" /> Rolls</button>}
          {!po && <button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove item" onClick={onRemove}>×</button>}
        </td>
      </tr>
      {modal && has && x.tb && createPortal(
        <RollsModal x={x} i={i} got={got} billed={billed} diff={diff} term={term} change={change} rackSelect={rackSelect} onClose={() => setModal(false)} />, document.body)}
    </tbody>
  );
}

/** Pop-up for one item's rolls: length, rack and supplier roll number of each roll. Changes apply straight away; Done closes it. */
function RollsModal({ x, i, got, billed, diff, term, change, rackSelect, onClose }: {
  x: Item; i: number; got: number; billed: number; diff: number; term: (k: string, p?: boolean) => string; change: (i: number, fn: (x: Item) => Item) => void;
  rackSelect: (v: string, on: (v: string) => void, first: string) => React.ReactNode; onClose: () => void;
}) {
  const box = useRef<HTMLDivElement>(null);
  const [text, setText] = useState<string | null>(null); const [err, setErr] = useState('');
  useEffect(() => {
    const k = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', k); document.body.classList.add('modal-open');
    box.current?.querySelector<HTMLInputElement>('input[data-rl]')?.focus();
    return () => { document.removeEventListener('keydown', k); document.body.classList.remove('modal-open'); };
  }, [onClose]);
  const setRoll = (m: number, patch: Partial<Roll>) => change(i, (y) => ({ ...y, rolls: y.rolls.map((z, k) => (k === m ? { ...z, ...patch } : z)) }));
  const addRoll = () => { change(i, (y) => ({ ...y, rolls: [...y.rolls, blankRoll()] })); setTimeout(() => { const all = box.current?.querySelectorAll<HTMLInputElement>('input[data-rl]'); all?.[all.length - 1]?.focus(); }, 30); };
  const shown = rollsText(x.rolls.map((r) => num(r.qty)).filter((n) => n > 0));
  const commit = () => {
    if (text === null) return; const r = parseRolls(text, x.dec);
    if (typeof r === 'string') { setErr(r); return; }
    setErr(''); setText(null);
    change(i, (y) => ({ ...y, rolls: r.length ? r.map((l, k) => ({ qty: String(l), lot_no: y.rolls[k]?.lot_no ?? '', location_id: y.rolls[k]?.location_id ?? '' })) : [blankRoll()] }));
  };
  const n = x.rolls.filter((r) => num(r.qty) > 0).length;
  return (
    <>
      <div className="modal-backdrop fade show" />
      <div className="modal fade show d-block" role="dialog" aria-modal="true" aria-label="Rolls" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
        <div className="modal-dialog modal-lg modal-dialog-scrollable" ref={box}><div className="modal-content">
          <div className="modal-header"><div><h2 className="modal-title h5 mb-0">Rolls · {x.label}</h2>
            <div className="small text-muted">{billed > 0 ? <>Billed {fmtQty(billed)} {x.unit} · </> : null}received <b>{fmtQty(got)} {x.unit}</b> in {n} {n === 1 ? 'roll' : 'rolls'}
              {billed > 0 && got > 0 && (Math.abs(diff) < 0.0005 ? <span className="badge text-bg-success ms-2">matches the bill</span> : diff < 0 ? <span className="badge text-bg-warning ms-2">short by {fmtQty(-diff)} {x.unit}</span> : <span className="badge text-bg-danger ms-2">{fmtQty(diff)} {x.unit} more than billed</span>)}</div></div>
            <button type="button" className="btn-close" aria-label="Close" onClick={onClose} /></div>
          <div className="modal-body">
            <label className="form-label small mb-1">Type them quickly <span className="text-muted fw-normal">(for example 4x50 or 50, 50, 48.5; lot numbers and racks below stay with their roll)</span></label>
            <input className={`form-control form-control-sm mb-3 ${err ? 'is-invalid' : ''}`} style={{ maxWidth: 360 }} value={text ?? shown} placeholder="4x50" onChange={(e) => { setText(e.target.value); setErr(''); }} onBlur={commit}
              onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); commit(); } }} />
            {err && <div className="invalid-feedback d-block mb-2">{err}</div>}
            <table className="table table-sm align-middle mb-2">
              <thead><tr><th style={{ width: 40 }}>#</th><th style={{ width: 170 }}>Length</th><th style={{ width: 200 }}>{term('rack')}</th><th>Supplier roll / lot no.</th><th style={{ width: 40 }} /></tr></thead>
              <tbody>{x.rolls.map((r, m) => (
                <tr key={m}><td className="text-muted">{m + 1}</td>
                  <td><div className="input-group input-group-sm"><input data-rl type="number" step={x.dec ? '0.001' : '1'} min="0" className="form-control" value={r.qty} onChange={(e) => setRoll(m, { qty: e.target.value })}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); if (m === x.rolls.length - 1 && num(r.qty) > 0) addRoll(); else (e.currentTarget.closest('tr')?.nextElementSibling?.querySelector('input[data-rl]') as HTMLInputElement | null)?.focus(); } }} />{x.unit && <span className="input-group-text">{x.unit}</span>}</div></td>
                  <td>{rackSelect(r.location_id, (v) => setRoll(m, { location_id: v }), 'Same as the item')}</td>
                  <td><input className="form-control form-control-sm" maxLength={60} value={r.lot_no} placeholder="optional" onChange={(e) => setRoll(m, { lot_no: e.target.value })} /></td>
                  <td><button type="button" className="btn btn-sm btn-link text-danger px-1" aria-label="Remove this roll" onClick={() => change(i, (y) => ({ ...y, rolls: y.rolls.length > 1 ? y.rolls.filter((_, k) => k !== m) : [blankRoll()] }))}><i className="bi bi-x-lg" /></button></td></tr>))}</tbody>
            </table>
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={addRoll}><i className="bi bi-plus-lg" /> Add a roll</button>
            <span className="text-muted small ms-2">Enter on the last length adds the next roll.</span>
          </div>
          <div className="modal-footer"><button type="button" className="btn btn-primary" onClick={onClose}>Done</button></div>
        </div></div>
      </div>
    </>
  );
}
