import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { lineTotals } from '../../lib/docs';
import { useSession } from '../../lib/session';
import { Messages, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';
import { PayBadge } from '../../components/Badges';

export default function BillDetail() {
  const { id } = useParams(); const qc = useQueryClient();
  const { can, term, session } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const q = useQuery({ queryKey: ['bill', id], queryFn: async () => (await get(`/purchase/bills/${id}`)).data });
  const [pay, setPay] = useState<{ amount: string; paid_on: string; method: string; reference: string; note: string } | null>(null);
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Bill not found.</div>;
  const b = d.b; const due: number = d.due;
  const refresh = () => ['bill', 'bills', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] }));
  const startPay = () => setPay({ amount: due > 0 ? due.toFixed(2) : '', paid_on: new Date().toISOString().slice(0, 10), method: 'cash', reference: '', note: '' });
  const submitPay = async () => { const r = await post(`/purchase/bills/${id}/payments`, pay); setMsgs(r.messages ?? []); if (r.ok) { setPay(null); refresh(); } };
  const delPay = async (pid: number) => { if (!confirm('Remove this payment?')) return; const r = await post(`/purchase/bills/${id}/payments/${pid}/delete`); setMsgs(r.messages ?? []); refresh(); };
  const methods = session?.payMethods ?? {};
  const itemRow = (l: any) => { const t = lineTotals(Number(l.qty), Number(l.unit_price), Number(l.tax_rate)); return <tr key={l.id}><td>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted">{l.sku}</small>{(l.sup_code || l.sup_name) && <div className="small text-muted">Their: {[l.sup_code, l.sup_name].filter(Boolean).join(' · ')}</div>}</td><td className="text-end">{qty(l.qty)} {l.unit}</td><td className="text-end">{money(l.unit_price)}</td><td className="text-end">{qty(l.tax_rate)}</td><td className="text-end">{money(t.total)}</td></tr>; };
  return (
    <RecordPage entity="bill" id={b.id} name={b.bill_no} back="/purchase/bills" badges={<PayBadge state={d.status} />}
      actions={<>{can('purchase.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/purchase/bills/${id}/edit`}>Edit details</Link>}{b.grn_id && can('purchase.create') && <Link className="btn btn-sm btn-outline-danger" to={`/purchase/returns/create?grn=${b.grn_id}`}>Return goods</Link>}</>}
      notice={msgs.length ? <div className="mb-3"><Messages items={msgs} onClose={() => setMsgs([])} /></div> : null}
      related={[{ id: 'totals', label: 'Amounts' }, ...(d.items.length ? [{ id: 'items', label: 'Items' }] : []), { id: 'payments', label: 'Payments' }]}>
      <div className="card mb-3" id="totals" style={{ maxWidth: 460 }}><div className="card-body"><table className="table table-sm table-borderless mb-0"><tbody>
        <tr><td>Goods</td><td className="text-end">{money(b.subtotal)}</td></tr><tr><td>Tax</td><td className="text-end">{money(b.tax_total)}</td></tr>
        {Number(b.other_charges) > 0 && <tr><td>Other charges</td><td className="text-end">{money(b.other_charges)}</td></tr>}
        <tr className="border-top"><th>Total</th><th className="text-end">{money(b.total)}</th></tr>
        {Number(b.returned_amount) > 0 && <tr><td>Returns credited</td><td className="text-end">− {money(b.returned_amount)}</td></tr>}
        <tr><td>Paid</td><td className="text-end">− {money(b.paid_amount)}</td></tr>
        <tr className="border-top"><th>{due < -0.004 ? 'Credit with ' + term('supplier').toLowerCase() : 'Balance due'}</th><th className={`text-end ${due > 0.004 ? 'text-danger' : 'text-success'}`}>{money(Math.abs(due))}</th></tr></tbody></table></div></div>
      {d.items.length > 0 && <div className="card mb-3" id="items"><div className="table-responsive"><table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th className="text-end">Qty</th><th className="text-end">Price</th><th className="text-end">Tax %</th><th className="text-end">Amount</th></tr></thead><tbody>{d.items.map(itemRow)}</tbody></table></div></div>}
      <div className="row g-3" id="payments">
        <div className="col-lg-7"><div className="card"><div className="card-header">Payments made</div><div className="table-responsive"><table className="table table-sm mb-0 align-middle"><tbody>
          {d.payments.map((p: any) => <tr key={p.id}><td>{fdate(p.paid_on)}</td><td>{methods[p.method] ?? p.method}{p.reference ? <span className="text-muted small"> · {p.reference}</span> : null}</td><td className="text-end">{money(p.amount)}</td>
            <td className="text-end">{can('purchase.delete') && <button className="btn btn-sm btn-link text-danger p-0" onClick={() => delPay(p.id)}>Remove</button>}</td></tr>)}
          {!d.payments.length && <tr><td className="text-muted">No payments yet.</td></tr>}</tbody></table></div></div>
          {d.returns.length > 0 && <div className="card mt-3"><div className="card-header">Returns</div><ul className="list-group list-group-flush">{d.returns.map((r: any) => <li className="list-group-item d-flex justify-content-between" key={r.id}><Link to={`/purchase/returns/${r.id}`}>{r.return_no}</Link><span className="text-muted">{fdate(r.return_date)} · {money(r.total)}</span></li>)}</ul></div>}</div>
        {due > 0.004 && can('purchase.create') && <div className="col-lg-5"><div className="card"><div className="card-header">Record a payment</div><div className="card-body">
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
      <span className="d-none">{legacyUrl('/')}</span>
    </RecordPage>
  );
}
