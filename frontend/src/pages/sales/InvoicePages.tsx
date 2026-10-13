import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg, appUrl } from '../../lib/api';
import { fdate, money } from '../../lib/format';
import { owed, payState } from '../../lib/docs';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { SchemaForm, toPayload, useSchema } from '../../components/RecordForm';
import DocList from '../../components/DocList';
import Filters from '../../components/Filters';
import RecordPage from '../../components/RecordPage';
import DeliveryRows from '../../components/DeliveryRows';
import { PayBadge } from '../../components/Badges';
import { refreshSales } from './OrderPages';

export function InvoiceList() {
  const { term } = useSession();
  const today = new Date().toISOString().slice(0, 10);
  return (
    <DocList title="Sales invoices" api="/sales/invoices" queryKey="invoices" empty="No invoices found."
      filters={(d, set, sp) => (
        <Filters names={['status', 'customer', 'q']} sp={sp} set={set}>
          <div className="col-md-2"><select name="status" className="form-select" defaultValue={sp.get('status') ?? ''}><option value="">All invoices</option><option value="unpaid">Unpaid</option><option value="partial">Partly paid</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select></div>
          <div className="col-md-3"><select name="customer" className="form-select" defaultValue={sp.get('customer') ?? ''}><option value="">All {term('customer', true).toLowerCase()}</option>{d.customers.map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="Invoice no." defaultValue={sp.get('q') ?? ''} /></div>
        </Filters>)}
      above={(d) => <div className="card mb-2"><div className="card-body py-2">Balance due on these invoices: <strong className={d.totalDue > 0.004 ? 'text-danger' : ''}>{money(d.totalDue)}</strong></div></div>}
      columns={[
        { head: 'Invoice', cell: (r) => <Link to={`/sales/invoices/${r.id}`}>{r.invoice_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.invoice_date) }, { head: term('customer'), cell: (r) => r.customer },
        { head: 'Due date', cell: (r) => { const late = payState(r) !== 'paid' && r.due_date && r.due_date < today; return <span className={late ? 'text-danger' : ''}>{fdate(r.due_date)}{late ? ' (overdue)' : ''}</span>; } },
        { head: 'Total', className: 'text-end', cell: (r) => money(r.total) }, { head: 'Balance', className: 'text-end', cell: (r) => money(owed(r)) }, { head: 'Status', cell: (r) => <PayBadge row={r} /> },
      ]} />
  );
}

export function InvoiceDetail() {
  const { id } = useParams(); const qc = useQueryClient();
  const { can, term, session } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['invoice', id], queryFn: async () => (await get(`/sales/invoices/${id}`)).data });
  const [pay, setPay] = useState<{ amount: string; paid_on: string; method: string; reference: string; note: string } | null>(null);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Invoice not found.</div>;
  const i = d.i; const due: number = d.due; const methods = session?.payMethods ?? {};
  const startPay = () => setPay({ amount: due > 0 ? due.toFixed(2) : '', paid_on: new Date().toISOString().slice(0, 10), method: 'cash', reference: '', note: '' });
  const submitPay = async () => { const r = await post(`/sales/invoices/${id}/payments`, pay); setMsgs(r.messages ?? []); if (r.ok) { setPay(null); refreshSales(qc); } };
  const delPay = async (pid: number) => { if (!confirm('Remove this payment?')) return; const r = await post(`/sales/invoices/${id}/payments/${pid}/delete`); setMsgs(r.messages ?? []); refreshSales(qc); };
  return (
    <RecordPage entity="invoice" id={i.id} name={i.invoice_no} back="/sales/invoices" badges={<PayBadge state={d.status} />}
      actions={<>
        <a className="btn btn-sm btn-primary" target="_blank" rel="noreferrer" href={appUrl(`/sales/invoices/${i.id}/print`)}><i className="bi bi-printer" /> Print invoice</a>
        {can('sales.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/sales/invoices/${i.id}/edit`}>Edit details</Link>}
        {can('sales.create') && i.delivery_id && <Link className="btn btn-sm btn-outline-danger" to={`/sales/returns/create?invoice=${i.id}`}>{term('customer')} return</Link>}
      </>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'totals', label: 'Amounts' }, ...(d.items.length ? [{ id: 'items', label: 'Items' }] : []), { id: 'payments', label: 'Payments' }]}>
      <div className="card mb-3" id="totals" style={{ maxWidth: 460 }}><div className="card-body"><table className="table table-sm table-borderless mb-0"><tbody>
        <tr><td>Gross</td><td className="text-end">{money(i.subtotal)}</td></tr>
        {Number(i.discount_total) > 0 && <tr><td>Discount</td><td className="text-end">− {money(i.discount_total)}</td></tr>}
        <tr><td>Tax</td><td className="text-end">{money(i.tax_total)}</td></tr>
        {Number(i.delivery_charge) > 0 && <tr><td>Delivery</td><td className="text-end">{money(i.delivery_charge)}</td></tr>}
        {Number(i.installation_charge) > 0 && <tr><td>Installation</td><td className="text-end">{money(i.installation_charge)}</td></tr>}
        <tr className="border-top"><th>Total</th><th className="text-end">{money(i.total)}</th></tr>
        {Number(i.returned_amount) > 0 && <tr><td>Returns credited</td><td className="text-end">− {money(i.returned_amount)}</td></tr>}
        <tr><td>Received</td><td className="text-end">− {money(i.paid_amount)}</td></tr>
        <tr className="border-top"><th>{due < -0.004 ? 'Credit due to customer' : 'Balance due'}</th><th className={`text-end ${due > 0.004 ? 'text-danger' : 'text-success'}`}>{money(Math.abs(due))}</th></tr></tbody></table></div></div>
      {d.items.length > 0 && <div className="card mb-3" id="items"><div className="table-responsive"><DeliveryRows items={d.items} /></div></div>}
      <div className="row g-3" id="payments">
        <div className="col-lg-7"><div className="card"><div className="card-header">Payments received</div><div className="table-responsive"><table className="table table-sm mb-0 align-middle"><tbody>
          {d.payments.map((p: any) => <tr key={p.id}><td>{fdate(p.paid_on)}</td><td>{methods[p.method] ?? (p.method === 'advance' ? 'Advance' : p.method)}{p.reference ? <span className="text-muted small"> · {p.reference}</span> : null}</td><td className="text-muted small">{p.note ?? ''}</td><td className="text-end">{money(p.amount)}</td>
            <td className="text-end">{can('sales.delete') && <button className="btn btn-sm btn-link text-danger p-0" onClick={() => delPay(p.id)}>Remove</button>}</td></tr>)}
          {!d.payments.length && <tr><td className="text-muted">No payments yet.</td></tr>}</tbody></table></div></div>
          {d.returns.length > 0 && <div className="card mt-3"><div className="card-header">Returns</div><ul className="list-group list-group-flush">{d.returns.map((r: any) => <li className="list-group-item d-flex justify-content-between" key={r.id}><Link to={`/sales/returns/${r.id}`}>{r.return_no}</Link><span>{fdate(r.return_date)} · {money(r.total)}</span></li>)}</ul></div>}</div>
        {due > 0.004 && can('sales.create') && <div className="col-lg-5"><div className="card"><div className="card-header">Record a payment</div><div className="card-body">
          {!pay ? <button className="btn btn-success w-100" onClick={startPay}>Record payment</button> : (
            <div className="row g-2">
              <div className="col-6"><label className="form-label">Amount</label><input type="number" step="0.01" min="0.01" className="form-control" value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} /></div>
              <div className="col-6"><label className="form-label">Date</label><input type="date" className="form-control" value={pay.paid_on} onChange={(e) => setPay({ ...pay, paid_on: e.target.value })} /></div>
              <div className="col-6"><label className="form-label">Method</label><select className="form-select" value={pay.method} onChange={(e) => setPay({ ...pay, method: e.target.value })}>{Object.entries(methods).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select></div>
              <div className="col-6"><label className="form-label">Reference</label><input className="form-control" maxLength={80} placeholder="UTR / cheque no." value={pay.reference} onChange={(e) => setPay({ ...pay, reference: e.target.value })} /></div>
              <div className="col-12"><input className="form-control" maxLength={150} placeholder="Note (optional)" value={pay.note} onChange={(e) => setPay({ ...pay, note: e.target.value })} /></div>
              <div className="col-12 d-flex gap-2"><button className="btn btn-success flex-grow-1" onClick={submitPay}>Save payment</button><button className="btn btn-outline-secondary" onClick={() => setPay(null)}>Cancel</button></div>
            </div>)}</div></div></div>}
      </div>
    </RecordPage>
  );
}

/** Edit an invoice's dates, charges and note (the goods and prices stay as delivered). */
export function InvoiceForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const schema = useSchema('invoice');
  const src = useQuery({ queryKey: ['invoice-form', id], gcTime: 0, queryFn: async () => (await get(`/sales/invoices/${id}/edit`)).data });
  const [values, setValues] = useState<Record<string, any> | null>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [saving, setSaving] = useState(false);
  useEffect(() => {
    if (!schema.data || !src.data || values) return;
    const inv = src.data.i; const v: Record<string, any> = {};
    Object.keys(schema.data.fields).forEach((k) => (v[k] = inv[k] ?? ''));
    setValues(v);
  }, [schema.data, src.data, values]);
  if (schema.isLoading || src.isLoading || !values || !schema.data) return <Spinner />;
  const inv = src.data.i;
  const save = async () => {
    setSaving(true); setMsgs([]);
    const r = await post(`/sales/invoices/${id}`, toPayload(schema.data!, values));
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    refreshSales(qc); nav(`/sales/invoices/${id}`);
  };
  return (
    <form onSubmit={(e) => { e.preventDefault(); void save(); }}>
      <PageHead title={`Edit ${inv.invoice_no}`} extra={schema.data.canDesign ? <Link className="page-layout-link" to={`/settings/formdesign?form=invoice&return=${encodeURIComponent(location.pathname)}`}><i className="bi bi-layout-text-window-reverse" /> Edit page layout</Link> : null}>
        <button type="button" className="btn btn-outline-secondary" onClick={() => nav(`/sales/invoices/${id}`)}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button>
      </PageHead>
      <Messages items={msgs} />
      <div className="card" style={{ maxWidth: 720 }}><div className="card-body"><SchemaForm schema={schema.data} values={values} onChange={setValues} /></div></div>
    </form>
  );
}
