import { Link } from 'react-router-dom';
import { fdate } from '../../lib/format';
import { useSession } from '../../lib/session';
import DocList from '../../components/DocList';

export default function GrnList() {
  const { can, term } = useSession();
  return (
    <DocList title="Goods receipts" api="/purchase/grns" queryKey="grns" empty="No goods receipts yet."
      actions={() => can('purchase.create') ? <Link className="btn btn-primary" to="/purchase/grns/create"><i className="bi bi-plus-lg" /> Receive goods (no PO)</Link> : null}
      filters={(d, set, sp) => (
        <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q: (e.currentTarget.elements.namedItem('q') as HTMLInputElement).value }); }}>
          <div className="col-md-3"><select className="form-select" value={sp.get('supplier') ?? ''} onChange={(e) => set({ supplier: e.target.value })}><option value="">All {term('supplier', true).toLowerCase()}</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
          <div className="col-md-3"><input name="q" className="form-control" placeholder="GRN no. or challan no." defaultValue={sp.get('q') ?? ''} /></div>
          <div className="col-auto"><button className="btn btn-outline-secondary">Filter</button></div>
        </form>)}
      columns={[
        { head: 'GRN', cell: (r) => <Link to={`/purchase/grns/${r.id}`}>{r.grn_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.received_date) }, { head: term('supplier'), cell: (r) => r.supplier }, { head: term('warehouse'), cell: (r) => r.warehouse },
        { head: 'PO', cell: (r) => r.po_id ? <Link to={`/purchase/orders/${r.po_id}`}>{r.po_no}</Link> : '—' }, { head: 'Lines', cell: (r) => r.line_count },
        { head: 'Bill', cell: (r) => r.bill_id ? <Link to={`/purchase/bills/${r.bill_id}`}>View bill</Link> : <span className="text-muted">not billed</span> },
      ]} />
  );
}
