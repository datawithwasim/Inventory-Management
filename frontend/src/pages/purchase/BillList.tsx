import { Link } from 'react-router-dom';
import { fdate, money } from '../../lib/format';
import { owed, payState } from '../../lib/docs';
import { useSession } from '../../lib/session';
import DocList from '../../components/DocList';
import { PayBadge } from '../../components/Badges';

export default function BillList() {
  const { term } = useSession();
  return (
    <DocList title="Purchase bills" api="/purchase/bills" queryKey="bills" empty="No bills found. Create a bill from a goods receipt."
      filters={(d, set, sp) => (
        <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q: (e.currentTarget.elements.namedItem('q') as HTMLInputElement).value }); }}>
          <div className="col-md-3"><select className="form-select" value={sp.get('status') ?? ''} onChange={(e) => set({ status: e.target.value })}><option value="">All bills</option><option value="unpaid">Unpaid</option><option value="partial">Partly paid</option><option value="paid">Paid</option><option value="overdue">Overdue</option></select></div>
          <div className="col-md-3"><select className="form-select" value={sp.get('supplier') ?? ''} onChange={(e) => set({ supplier: e.target.value })}><option value="">All {term('supplier', true).toLowerCase()}</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="Bill no. or supplier bill no." defaultValue={sp.get('q') ?? ''} /></div>
          <div className="col-auto"><button className="btn btn-outline-secondary">Filter</button></div>
        </form>)}
      above={(d) => <div className="card mb-2"><div className="card-body py-2">Balance due on these bills: <strong className={d.totalDue > 0.004 ? 'text-danger' : ''}>{money(d.totalDue)}</strong></div></div>}
      columns={[
        { head: 'Bill', cell: (r) => <><Link to={`/purchase/bills/${r.id}`}>{r.bill_no}</Link>{r.supplier_bill_no ? <><br /><small className="text-muted">{r.supplier_bill_no}</small></> : null}</> },
        { head: 'Date', cell: (r) => fdate(r.bill_date) }, { head: term('supplier'), cell: (r) => r.supplier },
        { head: 'Due date', cell: (r) => { const late = payState(r) !== 'paid' && r.due_date && r.due_date < new Date().toISOString().slice(0, 10); return <span className={late ? 'text-danger' : ''}>{fdate(r.due_date)}{late ? ' (overdue)' : ''}</span>; } },
        { head: 'Total', className: 'text-end', cell: (r) => money(r.total) }, { head: 'Balance', className: 'text-end', cell: (r) => money(owed(r)) }, { head: 'Status', cell: (r) => <PayBadge row={r} /> },
      ]} />
  );
}
