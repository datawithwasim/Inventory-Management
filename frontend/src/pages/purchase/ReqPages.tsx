import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import RecordPage from '../../components/RecordPage';
import DetailTable, { useViewCols, type DetailDef } from '../../components/DetailTable';
import { ItemCell } from '../../components/ItemCell';
import ItemSearch from '../../components/ItemSearch';

const reqTone: Record<string, string> = { open: 'primary', converted: 'success', cancelled: 'secondary' };

export function ReqList() {
  const { can, term } = useSession();
  return (
    <DocList title="Purchase requisitions" api="/purchase/requisitions" queryKey="reqs" empty="No requisitions yet."
      above={() => <div className="text-muted mb-3">Ask for {term('item', true).toLowerCase()} to be bought, then turn the request into a purchase order.</div>}
      actions={() => can('purchase.create') ? <><Link className="btn btn-outline-secondary" to="/purchase/requisitions/create?low=1"><i className="bi bi-exclamation-triangle" /> From low stock</Link><Link className="btn btn-primary" to="/purchase/requisitions/create"><i className="bi bi-plus-lg" /> New requisition</Link></> : null}
      columns={[
        { head: 'No.', cell: (r) => <Link to={`/purchase/requisitions/${r.id}`}>{r.req_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.created_at) }, { head: 'Lines', cell: (r) => r.line_count },
        { head: 'Status', cell: (r) => <Badge tone={reqTone[r.status]}>{r.status}</Badge> }, { head: 'PO', cell: (r) => r.po_id ? <Link to={`/purchase/orders/${r.po_id}`}>{r.po_no}</Link> : '' }, { head: 'By', cell: (r) => r.user_name ?? '' },
      ]} />
  );
}

type RL = { variant_id: string; label: string; unit: string; dec: boolean; qty: string; note: string };
const blankRl = (): RL => ({ variant_id: '', label: '', unit: '', dec: true, qty: '', note: '' });

export function ReqForm() {
  const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('requisition');
  const low = sp.get('low') ? '?low=1' : '';
  const src = useQuery({ queryKey: ['req-form', low], queryFn: async () => (await get(`/purchase/requisitions/create${low}`)) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [lines, setLines] = useState<RL[]>([blankRl()]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const v: Record<string, any> = {}; Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    setValues(v);
    const old: any[] = src.data.data?.oldLines ?? [];
    if (old.length) setLines(old.map((l) => ({ variant_id: String(l.variant_id), label: l.label, unit: l.unit ?? '', dec: l.dec !== 0, qty: String(l.qty), note: l.note ?? '' })));
    if (src.data.messages?.length) setMsgs(src.data.messages);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const set = (i: number, patch: Partial<RL>) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post('/purchase/requisitions', { ...toPayload(schema.data!, values), lines: lines.filter((l) => l.variant_id).map((l) => ({ variant_id: l.variant_id, qty: l.qty, note: l.note })) });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    ['reqs', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
    nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title="New requisition" extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=requisition&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav('/purchase/requisitions')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save requisition'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card mb-3" style={{ maxWidth: 720 }}><div className="card-body"><SchemaForm schema={schema.data} values={values} onChange={setValues} /></div></div>
      <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>{term('item', true)}</span><button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines((l) => [...l, blankRl()])}><i className="bi bi-plus-lg" /> Add {term('item').toLowerCase()}</button></div>
        <div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th style={{ minWidth: 260 }}>{term('item')}</th><th style={{ width: 150 }}>Qty</th><th>Note</th><th style={{ width: 44 }} /></tr></thead><tbody>
          {lines.map((l, i) => <tr key={i}>
            <td><ItemSearch value={l.variant_id} label={l.label} onPick={(p) => set(i, p ? { variant_id: String(p.row.id), label: p.label, unit: p.row.unit, dec: !!Number(p.row.allow_decimal) } : { variant_id: '', label: '' })} /></td>
            <td><div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div></td>
            <td><input className="form-control form-control-sm" maxLength={150} value={l.note} onChange={(e) => set(i, { note: e.target.value })} /></td>
            <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={() => setLines((ls) => (ls.length > 1 ? ls.filter((_, j) => j !== i) : [blankRl()]))}>×</button></td></tr>)}</tbody></table></div></div>
    </form>
  );
}

export function ReqDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]); const [conv, setConv] = useState({ supplier_id: '', warehouse_id: '' });
  const cols = useViewCols('requisition', { item: term('item'), qty: 'Qty', note: 'Note' });
  const q = useQuery({ queryKey: ['req', id], queryFn: async () => (await get(`/purchase/requisitions/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Requisition not found.</div>;
  const r = d.r;
  const refresh = () => ['req', 'reqs', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
  const cancel = async () => { if (!confirm('Cancel this requisition?')) return; const res = await post(`/purchase/requisitions/${id}/cancel`); setMsgs(res.messages ?? []); refresh(); };
  const convert = async () => { const res = await post(`/purchase/requisitions/${id}/convert`, { supplier_id: conv.supplier_id, warehouse_id: conv.warehouse_id || d.warehouses[0]?.id }); setMsgs(res.messages ?? []); refresh(); if (res.ok) nav('/' + String(res.redirect).replace(/^\//, '')); };
  return (
    <RecordPage entity="requisition" id={r.id} name={r.req_no} back="/purchase/requisitions" badges={<Badge tone={reqTone[r.status]}>{r.status}</Badge>}
      actions={r.status === 'open' && can('purchase.edit') ? <button className="btn btn-sm btn-outline-danger" onClick={cancel}>Cancel requisition</button> : null}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'items', label: 'Items' }, ...(r.status === 'open' && can('purchase.create') ? [{ id: 'convert', label: 'Turn into PO' }] : [])]}>
      <DetailTable cols={cols} rows={d.items} rowKey={(l: any) => l.id} defs={{
        item: { cell: (l) => <ItemCell l={l} /> }, qty: { className: 'text-end', cell: (l) => <>{qty(l.qty)} {l.unit}</> }, note: { cell: (l) => l.note ?? '' },
      } as Record<string, DetailDef<any>>} />
      {r.status === 'open' && can('purchase.create') && <div className="card mb-3" id="convert" style={{ maxWidth: 640 }}><div className="card-header">Turn into a purchase order</div><div className="card-body"><div className="row g-2">
        <div className="col-md-5"><select className="form-select" value={conv.supplier_id} onChange={(e) => setConv({ ...conv, supplier_id: e.target.value })}><option value="">{term('supplier')}…</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
        <div className="col-md-4"><select className="form-select" value={conv.warehouse_id || d.warehouses[0]?.id} onChange={(e) => setConv({ ...conv, warehouse_id: e.target.value })}>{d.warehouses.map((w: any) => <option key={w.id} value={w.id}>{w.name}</option>)}</select></div>
        <div className="col-md-3"><button className="btn btn-primary w-100" disabled={!conv.supplier_id} onClick={convert}>Create PO</button></div></div>
        <div className="form-text mt-2">A draft PO is created with each {term('item').toLowerCase()}'s default cost and tax. You can edit it before submitting.</div></div></div>}
    </RecordPage>
  );
}
