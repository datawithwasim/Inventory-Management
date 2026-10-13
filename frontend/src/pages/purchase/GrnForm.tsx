import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { qty as fmtQty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import ItemSearch from '../../components/ItemSearch';

type RLine = { po_item_id: string; variant_id: string; label: string; tb: boolean; unit: string; dec: boolean; qty: string; left: string; unit_price: string; tax_rate: string; location_id: string; lot_no: string };
const blank = (): RLine => ({ po_item_id: '', variant_id: '', label: '', tb: false, unit: '', dec: true, qty: '', left: '', unit_price: '', tax_rate: '', location_id: '', lot_no: '' });

/** Receive goods: against an approved purchase order (quantities, racks, supplier lots) or straight from a supplier. Each line of a roll item becomes its own roll. */
export default function GrnForm() {
  const { id: poId } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('grn');
  const src = useQuery({ queryKey: ['grn-form', poId ?? 'new'], queryFn: async () => (await get(poId ? `/purchase/orders/${poId}/receive` : '/purchase/grns/create')) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [supplier, setSupplier] = useState('');
  const [lines, setLines] = useState<RLine[]>([blank()]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const d = src.data.data; if (!d) return;
    const v: Record<string, any> = {};
    for (const [k, f] of Object.entries(schema.data.fields)) v[k] = f.custom ? '' : '';
    v.received_date = new Date().toISOString().slice(0, 10); v.warehouse_id = String(d.po?.warehouse_id ?? d.warehouses?.[0]?.id ?? '');
    setValues(v);
    const old: any[] = d.oldLines ?? [];
    setLines(old.length ? old.map((l) => ({ ...blank(), po_item_id: String(l.po_item_id ?? ''), variant_id: String(l.variant_id), label: l.label, tb: !!l.tb, unit: l.unit ?? '', dec: l.dec !== 0, qty: String(l.qty), left: String(l.left ?? ''), unit_price: String(l.unit_price ?? ''), tax_rate: String(l.tax_rate ?? ''), location_id: l.location_id ? String(l.location_id) : '', lot_no: l.lot_no ?? '' })) : [blank()]);
  }, [schema.data, src.data, values]);

  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data.data; if (!d) return <div className="alert alert-danger">{(src.data.messages ?? []).map((m: Msg) => m.msg).join(' ') || 'This purchase order cannot receive goods right now.'}</div>;
  const po = d.po; const racks: Record<string, { id: number; code: string }[]> = d.racks ?? {};
  const myRacks = racks[String(values.warehouse_id)] ?? [];
  const set = (i: number, patch: Partial<RLine>) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const split = (i: number) => setLines((ls) => [...ls.slice(0, i + 1), { ...ls[i], qty: '', lot_no: '' }, ...ls.slice(i + 1)]);

  const save = async () => {
    setSaving(true); setMsgs([]);
    const body: Record<string, any> = { ...toPayload(schema.data!, values), supplier_id: po ? po.supplier_id : supplier, po_id: po ? po.id : '',
      lines: lines.filter((l) => l.variant_id || l.po_item_id).map((l) => ({ po_item_id: l.po_item_id, variant_id: l.variant_id, qty: l.qty, unit_price: l.unit_price, tax_rate: l.tax_rate, location_id: l.location_id, lot_no: l.lot_no })) };
    const r = await post('/purchase/grns', body);
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['grns', 'grn', 'po', 'pos', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/purchase/grns');
  };
  const rackSelect = (l: RLine, i: number) => (
    <select className="form-select form-select-sm" value={l.location_id} onChange={(e) => set(i, { location_id: e.target.value })}><option value="">No rack</option>{myRacks.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}</select>
  );
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={po ? `Receive goods · ${po.po_no}` : 'Receive goods (no purchase order)'}
        extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=grn&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(po ? `/purchase/orders/${po.id}` : '/purchase/grns')}>Cancel</button>
        <button className="btn btn-success" disabled={saving}>{saving ? 'Saving…' : 'Receive into stock'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body">
        <div className="row mb-3"><div className="col-md-4"><label className="form-label">{term('supplier')}</label>
          {po ? <input className="form-control" disabled value={po.supplier} /> : <select className="form-select" required value={supplier} onChange={(e) => setSupplier(e.target.value)}><option value="">Choose…</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>}</div></div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <p className="text-muted small mb-0">Fabric / roll-tracked items: <b>each line is one roll</b> and becomes its own roll on the rack you choose. If a delivery has 3 rolls of the same fabric, use <i>＋ split</i> to add a line per roll. Extra cost is spread over the items by value and becomes part of their stock cost; it does not change the supplier's bill.</p>
      </div></div>
      <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>Items</span>
        {!po && <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines((l) => [...l, blank()])}><i className="bi bi-plus-lg" /> Add item</button>}</div>
        <div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th style={{ minWidth: 240 }}>{term('item')}</th>{po && <th className="text-end">Still due</th>}<th style={{ width: 130 }}>Qty received</th><th style={{ width: 150 }}>{term('rack')}</th><th style={{ width: 150 }}>Supplier lot ({term('batch', true).toLowerCase()})</th>{!po && <><th style={{ width: 120 }}>Unit price</th><th style={{ width: 90 }}>Tax %</th></>}<th style={{ width: 90 }} /></tr></thead>
          <tbody>{lines.map((l, i) => (
            <tr key={i}>
              <td>{po ? <>{l.label}{l.tb && <> <span className="badge text-bg-secondary">{term('batch')}</span></>}</> : <ItemSearch value={l.variant_id} label={l.label} supplier={supplier || undefined} placeholder="Search item, SKU or barcode"
                onPick={(p) => { if (!p) { set(i, { variant_id: '', label: '' }); return; } const v = p.row; set(i, { variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), tb: !!Number(v.track_batch), unit_price: String(v.rate ?? v.cost_price ?? ''), tax_rate: String(Number(v.tax_rate ?? 0)) }); }} />}</td>
              {po && <td className="text-end">{l.left ? fmtQty(l.left) : ''} {l.unit}</td>}
              <td><div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div></td>
              <td>{rackSelect(l, i)}</td>
              <td>{l.tb ? <input className="form-control form-control-sm" maxLength={60} value={l.lot_no} onChange={(e) => set(i, { lot_no: e.target.value })} /> : null}</td>
              {!po && <><td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value })} /></td><td><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /></td></>}
              <td className="text-nowrap">{l.tb && <button type="button" className="btn btn-sm btn-outline-secondary me-1" title="Add another roll / part for this item" onClick={() => split(i)}>＋ split</button>}
                {(!po || lines.filter((x) => x.po_item_id === l.po_item_id).length > 1) && <button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={() => setLines((ls) => (ls.length > 1 ? ls.filter((_, j) => j !== i) : [blank()]))}>×</button>}</td>
            </tr>))}</tbody></table></div></div>
    </form>
  );
}
