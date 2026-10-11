import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import RecordPage from '../../components/RecordPage';
import { refreshSales } from './OrderPages';

export function SalesReturnList() {
  const { can, term } = useSession();
  return (
    <DocList title="Sales returns" api="/sales/returns" queryKey="sales-returns" empty="No returns yet."
      above={() => <div className="text-muted mb-3">Goods taken back from {term('customer', true).toLowerCase()}. The {term('customer').toLowerCase()} is credited on the invoice.</div>}
      actions={() => can('sales.create') ? <Link className="btn btn-primary" to="/sales/returns/create"><i className="bi bi-plus-lg" /> New return</Link> : null}
      columns={[
        { head: 'Return', cell: (r) => <Link to={`/sales/returns/${r.id}`}>{r.return_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.return_date) }, { head: term('customer'), cell: (r) => r.customer },
        { head: 'Invoice', cell: (r) => <Link to={`/sales/invoices/${r.invoice_id}`}>{r.invoice_no}</Link> }, { head: 'Reason', cell: (r) => r.reason ?? '' }, { head: 'Credit', className: 'text-end', cell: (r) => money(r.total) },
      ]} />
  );
}

/** Step 1: pick the invoice. Step 2 (?invoice=): say what comes back, and whether it goes back on a rack. */
export function SalesReturnCreate() {
  const [sp, setSp] = useSearchParams();
  return sp.get('invoice') ? <ReturnForm invoice={sp.get('invoice')!} /> : <Pick q={sp.get('q') ?? ''} onSearch={(q) => setSp(q ? { q } : {})} />;
}

function Pick({ q, onSearch }: { q: string; onSearch: (q: string) => void }) {
  const { term } = useSession(); const [text, setText] = useState(q);
  const res = useQuery({ queryKey: ['sales-return-pick', q], queryFn: async () => (await get(`/sales/returns/create?q=${encodeURIComponent(q)}`)).data });
  return (
    <>
      <PageHead title="New sales return" />
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); onSearch(text); }}><div className="col-md-5"><input className="form-control" placeholder={`Search invoice no. or ${term('customer').toLowerCase()}`} value={text} onChange={(e) => setText(e.target.value)} /></div><div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div></form>
      <p className="text-muted">Choose the invoice the goods were sold on.</p>
      {res.isLoading ? <Spinner /> : <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle"><thead><tr><th>Invoice</th><th>Date</th><th>{term('customer')}</th><th className="text-end">Total</th><th /></tr></thead><tbody>
        {res.data.rows.map((r: any) => <tr key={r.id}><td>{r.invoice_no}</td><td>{fdate(r.invoice_date)}</td><td>{r.customer}</td><td className="text-end">{money(r.total)}</td><td className="text-end"><Link className="btn btn-sm btn-outline-danger" to={`/sales/returns/create?invoice=${r.id}`}>Return from this</Link></td></tr>)}
        {!res.data.rows.length && <tr><td colSpan={5} className="text-muted">No invoices found.</td></tr>}</tbody></table></div></div>}
    </>
  );
}

type RR = { qty: string; location_id: string; restock: boolean };

function ReturnForm({ invoice }: { invoice: string }) {
  const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const schema = useSchema('sales_return');
  const src = useQuery({ queryKey: ['sales-return-form', invoice], gcTime: 0, queryFn: async () => (await get(`/sales/returns/create?invoice=${invoice}`)) as any });
  const [values, setValues] = useState<Record<string, any> | null>(null); const [rows, setRows] = useState<Record<string, RR>>({});
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data?.data || values) return;
    const d = src.data.data; const v: Record<string, any> = {}; Object.keys(schema.data.fields).forEach((k) => (v[k] = ''));
    v.return_date = new Date().toISOString().slice(0, 10); setValues(v);
    const r: Record<string, RR> = {}; d.items.forEach((l: any) => { r[l.id] = { qty: '', location_id: String(l.location_id ?? 0), restock: true }; }); setRows(r);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const d = src.data.data; if (!d) return <div className="alert alert-danger">Invoice not found.</div>;
  const inv = d.i; const racks: { id: number; code: string }[] = d.racks?.[inv.warehouse_id] ?? [];
  const save = async () => {
    setSaving(true); setMsgs([]);
    const lines: Record<string, any> = {};
    Object.entries(rows).forEach(([k, v]) => { lines[k] = { qty: v.qty, location_id: v.location_id, restock: v.restock ? 1 : '' }; });
    const r = await post('/sales/returns', { ...toPayload(schema.data!, values), invoice_id: inv.id, lines });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    refreshSales(qc); nav('/' + String(r.redirect).replace(/^\//, ''));
  };
  const setRow = (id: string, p: Partial<RR>) => setRows((rs) => ({ ...rs, [id]: { ...rs[id], ...p } }));
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={`Return · ${inv.invoice_no}`} extra={schema.data.canDesign ? <a className="page-layout-link" href={legacyUrl(`/settings/formdesign?form=sales_return&return=${encodeURIComponent(location.pathname)}`)}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</a> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav('/sales/returns/create')}>Back</button><button className="btn btn-danger" disabled={saving}>{saving ? 'Saving…' : 'Record return'}</button></PageHead>
      <Messages items={msgs} />
      <div className="card mb-3"><div className="card-body"><div className="text-muted small">{term('customer')}</div><div className="mb-3">{inv.customer}<br /><small className="text-muted">Invoice {inv.invoice_no} · stock goes back to {inv.warehouse}</small></div>
        <SchemaForm schema={schema.data} values={values} onChange={setValues} />
        <p className="text-muted small mb-0">For a <strong>set</strong>, return the set line (for the credit) and each part you take back (to put it back on a {term('rack').toLowerCase()}). Untick "Put back" for goods that are damaged and must not return to stock.</p></div></div>
      <div className="card mb-3"><div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th>Roll ({term('batch', true).toLowerCase()})</th><th className="text-end">Sold</th><th className="text-end">Can return</th><th>Put back on {term('rack').toLowerCase()}</th><th style={{ width: 130 }}>Return qty</th></tr></thead><tbody>
          {d.items.map((l: any) => { const part = l.parent_id !== null && l.parent_id !== undefined; const row = rows[l.id]; if (!row) return null; return (
            <tr key={l.id} className={part ? 'small' : ''}>
              <td>{part ? '  ↳ ' : ''}{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small>{Number(l.is_bundle) ? <> <Badge tone="info">Set</Badge></> : null}</td>
              <td>{l.batch_no ?? ''}</td><td className="text-end">{qty(l.qty)} {l.unit}</td><td className="text-end">{qty(l.returnable)}</td>
              <td>{!Number(l.is_bundle) && l.returnable > 0 && <div className="d-flex align-items-center gap-2">
                <select className="form-select form-select-sm" style={{ width: 'auto' }} value={row.location_id} onChange={(e) => setRow(l.id, { location_id: e.target.value })}><option value="0">No {term('rack').toLowerCase()}</option>{racks.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}</select>
                <label className="small text-nowrap"><input type="checkbox" className="me-1" checked={row.restock} onChange={(e) => setRow(l.id, { restock: e.target.checked })} />Put back</label></div>}</td>
              <td>{l.returnable > 0 ? <input type="number" step="0.001" min="0" max={qty(l.returnable)} className="form-control form-control-sm" placeholder="0" value={row.qty} onChange={(e) => setRow(l.id, { qty: e.target.value })} /> : <span className="text-muted">all returned</span>}</td></tr>); })}
        </tbody></table></div></div>
    </form>
  );
}

export function SalesReturnDetail() {
  const { id } = useParams(); const { term } = useSession();
  const q = useQuery({ queryKey: ['sales-return', id], queryFn: async () => (await get(`/sales/returns/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Return not found.</div>;
  const r = d.r;
  return (
    <RecordPage entity="sales_return" id={r.id} name={r.return_no} back="/sales/returns" related={[{ id: 'items', label: 'Items returned' }]}>
      <div className="card mb-3" id="items"><div className="table-responsive"><table className="table mb-0"><thead><tr><th>{term('item')}</th><th>Roll</th><th>Back on</th><th className="text-end">Qty</th><th className="text-end">Credit</th></tr></thead><tbody>
        {d.items.map((l: any) => <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''}</td><td>{l.batch_no ?? ''}</td><td>{Number(l.restock) ? l.rack ?? 'No rack' : <Badge tone="secondary">Not put back</Badge>}</td><td className="text-end">{qty(l.qty)} {l.unit}</td><td className="text-end">{money(l.line_total)}</td></tr>)}</tbody>
        <tfoot><tr><th colSpan={4} className="text-end">Total credit (incl. tax)</th><th className="text-end">{money(r.total)}</th></tr></tfoot></table></div></div>
    </RecordPage>
  );
}
