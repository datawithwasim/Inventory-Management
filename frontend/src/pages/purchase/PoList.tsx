import { Link } from 'react-router-dom';
import { useQueryClient } from '@tanstack/react-query';
import { post } from '../../lib/api';
import { fdate, money } from '../../lib/format';
import { useSession } from '../../lib/session';
import DocList from '../../components/DocList';
import { PoBadge } from '../../components/Badges';

export default function PoList() {
  const { can, term, session } = useSession(); const qc = useQueryClient();
  const toggle = async (on: boolean) => { await post('/purchase/orders/approval-setting', { po_approval: on ? 1 : '' }); qc.invalidateQueries({ queryKey: ['pos'] }); };
  return (
    <DocList title="Purchase orders" api="/purchase/orders" queryKey="pos" empty="No purchase orders found."
      actions={() => can('purchase.create') ? <Link className="btn btn-primary" to="/purchase/orders/create"><i className="bi bi-plus-lg" /> New purchase order</Link> : null}
      filters={(d, set, sp) => (
        <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q: (e.currentTarget.elements.namedItem('q') as HTMLInputElement).value }); }}>
          <div className="col-md-3"><select className="form-select" value={sp.get('status') ?? ''} onChange={(e) => set({ status: e.target.value })}><option value="">All statuses</option><option value="open">Open (not finished)</option>
            {Object.entries(session?.poStatus ?? {}).map(([k, [l]]) => <option key={k} value={k}>{l}</option>)}</select></div>
          <div className="col-md-3"><select className="form-select" value={sp.get('supplier') ?? ''} onChange={(e) => set({ supplier: e.target.value })}><option value="">All {term('supplier', true).toLowerCase()}</option>{d.suppliers.map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
          <div className="col-md-2"><input name="q" className="form-control" placeholder="PO no." defaultValue={sp.get('q') ?? ''} /></div>
          <div className="col-auto"><button className="btn btn-outline-secondary">Filter</button></div>
        </form>)}
      columns={[
        { head: 'PO', cell: (r) => <Link to={`/purchase/orders/${r.id}`}>{r.po_no}</Link> }, { head: 'Date', cell: (r) => fdate(r.order_date) }, { head: term('supplier'), cell: (r) => r.supplier },
        { head: 'Deliver to', cell: (r) => r.warehouse }, { head: 'Expected', cell: (r) => fdate(r.expected_date) }, { head: 'Total', className: 'text-end', cell: (r) => money(r.total) }, { head: 'Status', cell: (r) => <PoBadge status={r.status} /> },
      ]}
      below={(d) => can('settings.edit') ? (
        <div className="mt-3 form-check form-switch"><input className="form-check-input" type="checkbox" id="appr" checked={!!d.approval} onChange={(e) => toggle(e.target.checked)} /><label className="form-check-label" htmlFor="appr">Purchase orders need approval before goods can be received</label></div>) : null} />
  );
}
