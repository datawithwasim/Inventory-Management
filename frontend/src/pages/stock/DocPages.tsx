import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg, appUrl } from '../../lib/api';
import { fdate, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import RecordPage from '../../components/RecordPage';
import StockLines, { blankSl, slPayload, type SL } from '../../components/StockLines';

const refresh = (qc: ReturnType<typeof useQueryClient>) => ['stock-docs', 'stock-doc', 'record', 'stock', 'stock-racks', 'stock-batches', 'stock-ledger', 'warehouse', 'location', 'item'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
const stamp = (s: string) => `${fdate(String(s).slice(0, 10))} ${String(s).slice(11, 16)}`;
const layoutLink = (form: string) => <Link className="page-layout-link" to={`/settings/formdesign?form=${form}&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link>;

function useReasons() {
  const q = useQuery({ queryKey: ['options', 'stock_reasons'], staleTime: 60_000, queryFn: async () => (await get('/options/stock_reasons')).options as { id: string; name: string }[] });
  return Object.fromEntries((q.data ?? []).map((o) => [o.id, o.name])) as Record<string, string>;
}

// ------------------------------------------------------------------ lists

export function AdjustmentList() {
  const { can, term } = useSession(); const reasons = useReasons();
  return (
    <DocList title="Stock adjustments" api="/stock/adjustments" queryKey="stock-docs" empty="No adjustments yet."
      above={() => <div className="text-muted mb-3">Add or remove stock with a reason. Opening stock is entered here too.</div>}
      actions={() => can('stock.adjust') ? <Link className="btn btn-primary" to="/stock/adjustments/create"><i className="bi bi-plus-lg" /> New adjustment</Link> : null}
      columns={[
        { head: 'No.', cell: (r) => <Link to={`/stock/adjustments/${r.id}`}>{r.doc_no}</Link> }, { head: 'Date', cell: (r) => stamp(r.created_at) }, { head: term('warehouse'), cell: (r) => r.warehouse },
        { head: 'Reason', cell: (r) => reasons[r.reason] ?? r.reason }, { head: 'Lines', cell: (r) => r.line_count }, { head: 'By', cell: (r) => r.user_name ?? '' },
      ]} />
  );
}

export function TakeList() {
  const { can, term } = useSession();
  return (
    <DocList title="Stock-takes" api="/stock/takes" queryKey="stock-docs" empty="No stock-takes yet."
      above={() => <div className="text-muted mb-3">Count a {term('warehouse').toLowerCase()} and correct the system to match.</div>}
      actions={() => can('stock.adjust') ? <Link className="btn btn-primary" to="/stock/takes/create"><i className="bi bi-plus-lg" /> Start stock-take</Link> : null}
      columns={[
        { head: 'No.', cell: (r) => <Link to={`/stock/takes/${r.id}`}>{r.doc_no}</Link> }, { head: 'Started', cell: (r) => stamp(r.created_at) }, { head: term('warehouse'), cell: (r) => r.warehouse },
        { head: 'Lines', cell: (r) => r.line_count }, { head: 'Status', cell: (r) => <Badge tone={r.status === 'posted' ? 'success' : 'warning'}>{r.status}</Badge> }, { head: 'By', cell: (r) => r.user_name ?? '' },
      ]} />
  );
}

// ------------------------------------------------------------------ adjustment / transfer forms

/** New stock adjustment (mode "adjust") or transfer (mode "transfer"): header from the designed layout, then the line editor. */
function StockDocForm({ mode }: { mode: 'adjust' | 'transfer' }) {
  const nav = useNavigate(); const qc = useQueryClient(); const { term } = useSession();
  const entity = mode === 'adjust' ? 'adjustment' : 'transfer'; const base = mode === 'adjust' ? '/stock/adjustments' : '/stock/transfers';
  const schema = useSchema(entity);
  const src = useQuery({ queryKey: ['stock-doc-form', entity], gcTime: 0, queryFn: async () => (await get(`${base}/create`)).data });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [lines, setLines] = useState<SL[]>([blankSl()]);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const v: Record<string, any> = {}; Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    const whs: any[] = src.data.warehouses ?? [];
    v.warehouse_id = String(whs[0]?.id ?? '');
    if (mode === 'transfer') v.to_warehouse_id = String(whs[1]?.id ?? whs[0]?.id ?? '');
    setValues(v);
  }, [schema.data, src.data, values, mode]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const from = String(values.warehouse_id ?? ''); const to = String(values.to_warehouse_id ?? '');
  const between = mode === 'transfer' && !!to && to !== from;
  // goods leaving for another warehouse also carry transport details; moving inside one warehouse does not
  const shown = mode === 'transfer' && !between ? { ...schema.data, sections: schema.data.sections.map((sec) => ({ ...sec, fields: sec.fields.filter((k) => !['expected_date', 'transport', 'vehicle_no', 'lr_no'].includes(k)) })) } : schema.data;
  // changing a warehouse changes which rolls / racks exist: lines are re-pointed at the new place
  const onHeader = (v: Record<string, any>) => {
    if (v.warehouse_id !== values.warehouse_id) setLines((ls) => ls.map((l) => ({ ...l, location_id: '', batch_id: l.tb && mode === 'adjust' ? 'new' : '' })));
    if (v.to_warehouse_id !== values.to_warehouse_id) setLines((ls) => ls.map((l) => ({ ...l, to_location_id: '0' })));
    setValues(v);
  };
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(base, { ...toPayload(schema.data!, values), lines: slPayload(lines) });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refresh(qc);
    nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={mode === 'adjust' ? 'New stock adjustment' : 'New stock transfer'} extra={schema.data.canDesign ? layoutLink(entity) : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(base)}>Cancel</button>
        <button className="btn btn-primary" disabled={saving}>{saving ? 'Posting…' : mode === 'adjust' ? 'Post adjustment' : between ? 'Dispatch stock' : 'Move stock'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body">
        <SchemaForm schema={shown} values={values} onChange={onHeader} />
        {between && <div className="alert alert-info py-2 small"><i className="bi bi-truck me-1" /><strong>Between two {term('warehouse', true).toLowerCase()}:</strong> the stock leaves the sender now and stays <strong>in transit</strong> until the other {term('warehouse').toLowerCase()} confirms what it received and puts it on a {term('rack').toLowerCase()}. Anything that does not arrive has to be settled.</div>}
        <p className="text-muted small mb-0">{mode === 'adjust'
          ? <>Use a positive quantity to add stock (for fabric, <strong>+ New {term('batch').toLowerCase()}</strong> creates a new roll) and a negative quantity to remove it from a chosen {term('batch').toLowerCase()}.</>
          : <>You can also move stock between {term('rack', true).toLowerCase()} <em>inside</em> one {term('warehouse').toLowerCase()}: choose the same {term('warehouse').toLowerCase()} on both sides and pick different {term('rack', true).toLowerCase()} on each line.</>}</p>
      </div></div>
      <StockLines mode={mode} lines={lines} setLines={setLines} racks={src.data.racks ?? {}} from={from} to={to} />
    </form>
  );
}
export const AdjustmentForm = () => <StockDocForm mode="adjust" />;
export const TransferForm = () => <StockDocForm mode="transfer" />;

// ------------------------------------------------------------------ adjustment / transfer detail

function StockDocDetail({ kind }: { kind: 'adjustment' | 'transfer' }) {
  const { id } = useParams(); const { term } = useSession();
  const base = kind === 'adjustment' ? '/stock/adjustments' : '/stock/transfers';
  const q = useQuery({ queryKey: ['stock-doc', kind, id], queryFn: async () => (await get(`${base}/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Not found.</div>;
  const doc = d.doc; const signed = kind === 'adjustment'; const moves = !!doc.to_warehouse;
  return (
    <RecordPage entity={kind} id={doc.id} name={doc.doc_no} back={base} badges={<Badge tone="light">{d.heading}</Badge>}
      actions={<a className="btn btn-sm btn-outline-secondary" target="_blank" rel="noreferrer" href={appUrl(`${base}/${doc.id}/voucher`)}><i className="bi bi-scissors" /> Stock-out voucher</a>}
      related={[{ id: 'lines', label: 'Items' }]}>
      <div className="card mb-3" id="lines"><div className="table-responsive"><table className="table mb-0">
        <thead><tr><th>{term('item')}</th><th>SKU</th><th>{term('batch')}</th><th>{moves ? `From ${term('rack').toLowerCase()} → To ${term('rack').toLowerCase()}` : term('rack')}</th><th className="text-end">Qty</th></tr></thead><tbody>
          {d.lines.map((l: any) => (
            <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''}</td><td>{l.sku}</td>
              <td>{l.batch_no ? <Link to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link> : ''}</td>
              <td>{l.rack ?? 'No rack'}{moves ? ` → ${l.to_rack ?? 'No rack'}` : ''}</td>
              <td className={`text-end ${signed ? (Number(l.qty) < 0 ? 'text-danger' : 'text-success') : ''}`}>{signed && Number(l.qty) > 0 ? '+' : ''}{qty(l.qty)} {l.unit}</td></tr>))}
        </tbody></table></div></div>
    </RecordPage>
  );
}
export const AdjustmentDetail = () => <StockDocDetail kind="adjustment" />;

// ------------------------------------------------------------------ stock-take

export function TakeForm() {
  const nav = useNavigate(); const qc = useQueryClient();
  const schema = useSchema('stocktake');
  const src = useQuery({ queryKey: ['take-form'], gcTime: 0, queryFn: async () => (await get('/stock/takes/create')).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  const { term } = useSession();
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const v: Record<string, any> = {}; Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    v.warehouse_id = String(src.data.warehouses?.[0]?.id ?? ''); setValues(v);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post('/stock/takes', toPayload(schema.data!, values));
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    refresh(qc); nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title="Start a stock-take" extra={schema.data.canDesign ? layoutLink('stocktake') : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav('/stock/takes')}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Starting…' : 'Start'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 620 }}><div className="card-body">
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <p className="text-muted small mb-0">A list of everything the system thinks is in the {term('warehouse').toLowerCase()} (by {term('batch').toLowerCase()} for fabric) is prepared. You enter what you actually counted, then post: differences become stock-take movements.</p>
      </div></div>
    </form>
  );
}

export function TakeDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { can, term } = useSession();
  const q = useQuery({ queryKey: ['stock-doc', 'take', id], queryFn: async () => (await get(`/stock/takes/${id}`)).data });
  const [counts, setCounts] = useState<Record<string, string> | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  useEffect(() => {
    if (q.data && !counts) setCounts(Object.fromEntries(q.data.lines.map((l: any) => [String(l.id), qty(l.qty)])));
  }, [q.data, counts]);
  if (q.isLoading || !counts) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Not found.</div>;
  const doc = d.doc; const draft = doc.status === 'draft'; const canEdit = draft && can('stock.adjust');
  const send = async (action: 'save' | 'post') => {
    if (action === 'post' && !confirm('Post this stock-take? Stock will be corrected to the counted quantities.')) return;
    setBusy(true);
    const r = await post(`/stock/takes/${id}`, { action, counts });
    setBusy(false); setMsgs(r.messages ?? []);
    if (r.ok) { refresh(qc); window.scrollTo({ top: 0, behavior: 'smooth' }); }
  };
  const remove = async () => {
    if (!confirm('Delete this draft?')) return;
    const r = await post(`/stock/takes/${id}/delete`);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    refresh(qc); nav('/stock/takes');
  };
  return (
    <RecordPage entity="stocktake" id={doc.id} name={doc.doc_no} back="/stock/takes" badges={<Badge tone={draft ? 'warning' : 'success'}>{doc.status}</Badge>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'lines', label: 'Counts' }]}>
      <div className="card mb-3" id="lines"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th>{term('rack')}</th><th className="text-end">System qty</th><th className="text-end" style={{ width: 170 }}>Counted</th><th className="text-end">Difference</th></tr></thead><tbody>
          {d.lines.map((l: any) => {
            const counted = canEdit ? Number(counts[String(l.id)]) : Number(l.qty);
            const diff = (Number.isFinite(counted) ? counted : Number(l.qty)) - Number(l.expected_qty);
            return (
              <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small></td>
                <td>{l.batch_no ? <Link to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link> : ''}</td><td>{l.rack ?? 'No rack'}</td><td className="text-end">{qty(l.expected_qty)} {l.unit}</td>
                <td className="text-end">{canEdit ? <input type="number" step="0.001" min="0" className="form-control form-control-sm text-end" value={counts[String(l.id)] ?? ''} onChange={(e) => setCounts({ ...counts, [String(l.id)]: e.target.value })} /> : <>{qty(l.qty)} {l.unit}</>}</td>
                <td className={`text-end ${Math.abs(diff) > 0.0005 ? (diff < 0 ? 'text-danger' : 'text-success') : 'text-muted'}`}>{diff > 0.0005 ? '+' : ''}{qty(diff)}</td></tr>
            );
          })}
          {!d.lines.length && <tr><td colSpan={6} className="text-muted">This {term('warehouse').toLowerCase()} has no stock. Use an adjustment to add stock.</td></tr>}
        </tbody></table></div></div>
      {canEdit && <div className="d-flex gap-2 mb-3">
        <button className="btn btn-outline-primary" disabled={busy} onClick={() => send('save')}>Save counts</button>
        <button className="btn btn-primary" disabled={busy} onClick={() => send('post')}>Post stock-take</button>
        <button className="btn btn-outline-danger ms-auto" disabled={busy} onClick={remove}>Delete draft</button></div>}
    </RecordPage>
  );
}
