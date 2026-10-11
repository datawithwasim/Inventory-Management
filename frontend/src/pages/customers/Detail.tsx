import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get, legacyUrl, post } from '../../lib/api';
import { fdate, money } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Spinner } from '../../components/ui';
import RecordPage from '../../components/RecordPage';

const saleTone: Record<string, string> = { draft: 'secondary', confirmed: 'primary', partial: 'info', delivered: 'success', invoiced: 'success', closed: 'dark', cancelled: 'danger' };

export default function CustomerDetail() {
  const { id } = useParams(); const nav = useNavigate();
  const { can, term } = useSession();
  const q = useQuery({ queryKey: ['customer', id], queryFn: async () => (await get(`/customers/${id}`)).data });
  if (q.isLoading) return <Spinner />;
  const d = q.data; if (!d) return <div className="alert alert-danger">Customer not found.</div>;
  const c = d.c;
  const remove = async () => {
    if (!confirm('Delete this customer?')) return;
    const r = await post(`/customers/${id}/delete`);
    if (r.ok) nav('/customers'); else alert(r.messages?.[0]?.msg ?? 'Could not delete.');
  };
  const owed = (i: any) => Math.max(0, Number(i.total) - Number(i.returned_amount) - Number(i.paid_amount));
  return (
    <RecordPage entity="customer" id={c.id} name={c.name} back="/customers"
      badges={<>{c.is_walkin ? <Badge tone="info">Walk-in</Badge> : null}{c.is_active ? null : <Badge tone="dark">Inactive</Badge>}</>}
      actions={can('customers.edit') ? <Link className="btn btn-sm btn-primary" to={`/customers/${c.id}/edit`}>Edit</Link> : null}
      menu={(close) => <>
        {can('sales.create') && <li><a className="dropdown-item" href={legacyUrl(`/sales/orders/create?customer=${c.id}`)}><i className="bi bi-bag-plus me-2 text-muted" />New order</a></li>}
        {can('customers.delete') && !c.is_walkin && <li><button className="dropdown-item text-danger" onClick={() => { close(); void remove(); }}><i className="bi bi-trash me-2" />Delete</button></li>}
      </>}
      related={[{ id: 'balance', label: 'Balance' }, { id: 'orders', label: 'Orders' }, { id: 'invoices', label: 'Invoices' }]}>
      <div className="card mb-3" id="balance"><div className="card-body"><div className="row">
        <div className="col-6"><div className="text-muted small">Owes us</div><div className={`fs-2 ${d.owed > 0.004 ? 'text-danger' : ''}`}>{money(d.owed)}</div><div className="text-muted small">after returns and payments</div></div>
        <div className="col-6"><div className="text-muted small">Advance held</div><div className="fs-2">{money(d.advance)}</div><div className="text-muted small">taken on orders, not yet adjusted</div></div></div></div></div>
      <section className="card mb-3" id="orders"><div className="card-header d-flex justify-content-between align-items-center"><span>Orders <span className="act-n">{d.orders.length}</span></span>
        {can('sales.create') && <a className="btn btn-sm btn-outline-primary" href={legacyUrl(`/sales/orders/create?customer=${c.id}`)}><i className="bi bi-plus-lg" /> New order</a>}</div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th className="text-end">Total</th></tr></thead><tbody>
          {d.orders.map((o: any) => <tr key={o.id}><td><a className="fw-medium" href={legacyUrl(`/sales/orders/${o.id}`)}>{o.order_no}</a></td><td>{fdate(o.order_date)}</td><td><Badge tone={saleTone[o.status] ?? 'secondary'}>{o.status}</Badge></td><td className="text-end">{money(o.total)}</td></tr>)}
          {!d.orders.length && <tr><td colSpan={4} className="text-muted">No orders yet.</td></tr>}</tbody></table></div></section>
      <section className="card mb-3" id="invoices"><div className="card-header">Invoices <span className="act-n">{d.invoices.length}</span></div>
        <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>Invoice</th><th>Date</th><th className="text-end">Balance due</th></tr></thead><tbody>
          {d.invoices.map((i: any) => <tr key={i.id}><td><a className="fw-medium" href={legacyUrl(`/sales/invoices/${i.id}`)}>{i.invoice_no}</a></td><td>{fdate(i.invoice_date)}</td><td className="text-end">{owed(i) > 0.004 ? <span className="text-danger">{money(owed(i))}</span> : <Badge tone="success">Paid</Badge>}</td></tr>)}
          {!d.invoices.length && <tr><td colSpan={3} className="text-muted">No invoices yet.</td></tr>}</tbody></table></div></section>
      <span className="d-none">{term('customer')}</span>
    </RecordPage>
  );
}
