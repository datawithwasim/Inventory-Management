import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import RecordPage from '../../components/RecordPage';
import DetailTable, { useViewCols, type DetailDef } from '../../components/DetailTable';
import { ItemCell } from '../../components/ItemCell';

export function ReturnList() {
  const { can, term } = useSession();
  return (
    <DocList title="Purchase returns" api="/purchase/returns" queryKey="returns" empty="No returns yet."
      above={() => <div className="text-muted mb-3">Goods sent back to {term('supplier', true).toLowerCase()}.</div>}
      actions={() => can('purchase.create') ? <Link className="btn btn-primary" to="/purchase/returns/create"><i className="bi bi-plus-lg" /> New return</Link> : null}
      columns={[
        { head: 'Return', cell: (r) => <Link to={`/purchase/returns/${r.id}`}>{r.return_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.return_date) }, { head: term('supplier'), cell: (r) => r.supplier },
        { head: 'Receipt', cell: (r) => <Link to={`/purchase/grns/${r.grn_id}`}>{r.grn_no}</Link> }, { head: 'Reason', cell: (r) => r.reason ?? '' }, { head: 'Value', className: 'text-end', cell: (r) => money(r.total) },
      ]} />
  );
}

/** Step 1: pick the goods receipt. Step 2 (?grn=): say what goes back and from which rack. */
export function ReturnCreate() {
  const [sp, setSp] = useSearchParams();
  return sp.get('grn') ? <ReturnForm grn={sp.get('grn')!} /> : <ReturnPick onSearch={(q) => setSp(q ? { q } : {})} q={sp.get('q') ?? ''} />;
}

function ReturnPick({ q, onSearch }: { q: string; onSearch: (q: string) => void }) {
  const { term } = useSession(); const [text, setText] = useState(q);
  const res = useQuery({ queryKey: ['return-pick', q], queryFn: async () => (await get(`/purchase/returns/create?q=${encodeURIComponent(q)}`)).data });
  return (
    <>
      <PageHead title="New purchase return" />
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); onSearch(text); }}><div className="col-md-5"><input className="form-control" placeholder="Search goods receipt no., supplier or challan no." value={text} onChange={(e) => setText(e.target.value)} /></div><div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div></form>
      <p className="text-muted">Choose the goods receipt the {term('item', true).toLowerCase()} came in on.</p>
      {res.isLoading ? <Spinner /> : <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle"><thead><tr><th>Receipt</th><th>Date</th><th>{term('supplier')}</th><th>Challan</th><th className="text-end">Can still return</th><th /></tr></thead><tbody>
        {res.data.rows.map((r: any) => <tr key={r.id}><td>{r.grn_no}</td><td>{fdate(r.received_date)}</td><td>{r.supplier}</td><td>{r.supplier_ref ?? ''}</td><td className="text-end">{qty(r.returnable)}</td>
          <td className="text-end">{Number(r.returnable) > 0 && <Link className="btn btn-sm btn-outline-danger" to={`/purchase/returns/create?grn=${r.id}`}>Return from this</Link>}</td></tr>)}
        {!res.data.rows.length && <tr><td colSpan={6} className="text-muted">No goods receipts found.</td></tr>}</tbody></table></div></div>}
    </>
  );
}

function ReturnForm({ grn }: { grn: string }) {
  const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('purchase_return');
  const src = useQuery({ queryKey: ['return-form', grn], queryFn: async () => (await get(`/purchase/returns/create?grn=${grn}`)) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [rows, setRows] = useState<Record<string, { qty: string; location_id: string }>>({});
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data?.data || values) return;
    const d = src.data.data; const v: Record<string, any> = {}; Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    v.return_date = new Date().toISOString().slice(0, 10); setValues(v);
    const r: Record<string, { qty: string; location_id: string }> = {};
    d.items.forEach((l: any) => { r[l.id] = { qty: '', location_id: String(l.placements?.[0]?.location_id ?? 0) }; });
    setRows(r);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data.data; if (!d) return <div className="alert alert-danger">Goods receipt not found.</div>;
  const g = d.g; const myRacks = d.racks?.[g.warehouse_id] ?? [];
  const save = async () => {
    setSaving(true); setMsgs([]);
    const lines: Record<string, any> = {}; Object.entries(rows).forEach(([k, v]) => { lines[k] = v; });
    const r = await post('/purchase/returns', { ...toPayload(schema.data!, values), grn_id: g.id, lines });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    ['returns', 'grn', 'bill', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={`Return goods · ${g.grn_no}`} extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=purchase_return&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav('/purchase/returns/create')}>Back</button><button className="btn btn-danger" disabled={saving}>{saving ? 'Saving…' : `Return to ${term('supplier').toLowerCase()}`}</button></PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><div className="text-muted small">{term('supplier')}</div><div className="mb-3">{g.supplier}<br /><small className="text-muted">Receipt {g.grn_no} · {g.warehouse}</small></div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} /></div></div>
      <div className="card mb-3"><div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th className="text-end">Received</th><th className="text-end">Can return</th><th>Take from {term('rack', true).toLowerCase()}</th><th style={{ width: 150 }}>Return qty</th></tr></thead><tbody>
        {d.items.map((l: any) => {
          const row = rows[l.id] ?? { qty: '', location_id: '0' };
          return <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small></td><td>{l.batch_no ?? '—'}</td><td className="text-end">{qty(l.qty)} {l.unit}</td>
            <td className="text-end">{qty(l.returnable)}<div className="small text-muted">in stock: {qty(l.on_hand)}</div></td>
            <td>{Number(l.returnable) > 0 && <><select className="form-select form-select-sm" value={row.location_id} onChange={(e) => setRows({ ...rows, [l.id]: { ...row, location_id: e.target.value } })}><option value="0">No {term('rack').toLowerCase()}</option>{myRacks.map((r: any) => <option key={r.id} value={r.id}>{r.code}</option>)}</select>
              {l.placements.length > 0 && <div className="form-text small">In stock: {l.placements.map((p: any) => `${p.code || 'No rack'} ${qty(p.qty)}`).join(' · ')}</div>}</>}</td>
            <td>{Number(l.returnable) > 0 ? <input type="number" step="0.001" min="0" max={qty(l.returnable)} className="form-control form-control-sm" placeholder="0" value={row.qty} onChange={(e) => setRows({ ...rows, [l.id]: { ...row, qty: e.target.value } })} /> : <span className="text-muted">all returned</span>}</td></tr>;
        })}</tbody></table></div></div>
    </form>
  );
}

export function ReturnDetail() {
  const { id } = useParams(); const { term } = useSession();
  const cols = useViewCols('purchase_return', { item: term('item'), batch: term('batch'), rack: term('rack'), qty: 'Qty', price: 'Price', value: 'Value' });
  const q = useQuery({ queryKey: ['return', id], queryFn: async () => (await get(`/purchase/returns/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Return not found.</div>;
  const r = d.r;
  return (
    <RecordPage entity="purchase_return" id={r.id} name={r.return_no} back="/purchase/returns" related={[{ id: 'items', label: 'Items returned' }]}>
      <DetailTable cols={cols} rows={d.items} rowKey={(l: any) => l.id} defs={{
        item: { cell: (l) => <ItemCell l={l} /> }, batch: { cell: (l) => l.batch_no ?? '—' }, rack: { cell: (l) => l.rack ?? 'No rack' },
        qty: { className: 'text-end', cell: (l) => <>{qty(l.qty)} {l.unit}</> }, price: { className: 'text-end', cell: (l) => money(l.unit_price) }, value: { className: 'text-end', cell: (l) => money(l.line_total) },
      } as Record<string, DetailDef<any>>} />
    </RecordPage>
  );
}
