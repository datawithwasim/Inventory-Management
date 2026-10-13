import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import ItemSearch from '../../components/ItemSearch';

/** Customer groups (Retail, Dealer, Designer…) with a default discount. */
export function GroupList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const res = useQuery({ queryKey: ['customer-groups'], queryFn: async () => (await get('/customers/groups')).data });
  const del = async (id: number) => { if (!confirm('Delete this group?')) return; await post(`/customers/groups/${id}/delete`); qc.invalidateQueries({ queryKey: ['customer-groups'] }); };
  if (res.isLoading) return <Spinner />;
  const rows: any[] = res.data?.rows ?? [];
  return (
    <>
      <PageHead title="Customer groups">{can('customers.create') && <Link className="btn btn-primary" to="/customers/groups/create"><i className="bi bi-plus-lg" /> New group</Link>}</PageHead>
      <div className="text-muted mb-3">Groups such as Retail, Dealer or Designer can have a default discount and their own prices.</div>
      <div className="card"><div className="table-responsive"><table className="table table-hover mb-0"><thead><tr><th>Group</th><th className="text-end">Default discount</th><th className="text-end">Special prices</th><th className="text-end">{term('customer', true)}</th><th /></tr></thead><tbody>
        {rows.map((r) => <tr key={r.id}><td>{r.name}</td><td className="text-end">{qty(r.discount_pct)}%</td><td className="text-end">{r.prices}</td><td className="text-end">{r.customers}</td>
          <td className="text-end text-nowrap">{can('customers.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/customers/groups/${r.id}/edit`}>Edit &amp; prices</Link>} {can('customers.delete') && <button className="btn btn-sm btn-outline-danger" onClick={() => del(r.id)}>Delete</button>}</td></tr>)}
        {!rows.length && <tr><td colSpan={5} className="text-muted">No groups yet.</td></tr>}</tbody></table></div></div>
      <Link className="btn btn-link mt-2" to="/customers">&laquo; {term('customer', true)}</Link>
    </>
  );
}

export function GroupForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession();
  const res = useQuery({ queryKey: ['customer-group', id ?? 'new'], queryFn: async () => (await get(id ? `/customers/groups/${id}/edit` : '/customers/groups/create')).data });
  const [f, setF] = useState<null | { name: string; discount_pct: string }>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [pmsgs, setPmsgs] = useState<Msg[]>([]);
  const [item, setItem] = useState({ id: '', label: '' }); const [price, setPrice] = useState('');
  if (res.isLoading || !res.data) return <Spinner />;
  const row = res.data.row; const prices: any[] = res.data.prices ?? [];
  const v = f ?? { name: row?.name ?? '', discount_pct: row ? String(row.discount_pct ?? 0) : '0' };
  const reload = () => { qc.invalidateQueries({ queryKey: ['customer-group'] }); qc.invalidateQueries({ queryKey: ['customer-groups'] }); };
  const save = async (e: React.FormEvent) => {
    e.preventDefault(); setMsgs([]);
    const r = await post(row ? `/customers/groups/${row.id}` : '/customers/groups', v);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    reload(); setF(null);
    if (!row && r.redirect) nav('/' + r.redirect.replace(/^\//, ''), { replace: true }); else setMsgs(r.messages ?? [{ type: 'success', msg: 'Group updated.' }]);
  };
  const setPrc = async (e: React.FormEvent) => {
    e.preventDefault(); setPmsgs([]);
    const r = await post(`/customers/groups/${row.id}/prices`, { variant_id: item.id, price });
    if (!r.ok) { setPmsgs(r.messages ?? []); return; }
    setItem({ id: '', label: '' }); setPrice(''); reload();
  };
  const rm = async (pid: number) => { await post(`/customers/groups/${row.id}/prices/${pid}/delete`); reload(); };
  return (
    <>
      <PageHead title={row ? 'Edit group' : 'New customer group'} />
      <div className="row g-3">
        <div className="col-lg-5"><div className="card"><div className="card-body">
          <Messages items={msgs} />
          <form onSubmit={save}>
            <div className="mb-3"><label className="form-label">Group name</label><input className="form-control" maxLength={80} value={v.name} onChange={(e) => setF({ ...v, name: e.target.value })} required /></div>
            <div className="mb-3"><label className="form-label">Default discount %</label><input type="number" step="0.01" min="0" max="100" className="form-control" value={v.discount_pct} onChange={(e) => setF({ ...v, discount_pct: e.target.value })} />
              <div className="form-text">Applied automatically on the list price of {term('item', true).toLowerCase()} that have no special price for this group.</div></div>
            <button className="btn btn-primary">Save</button> <Link className="btn btn-link" to="/customers/groups">Back</Link>
          </form></div></div></div>
        {row && <div className="col-lg-7"><div className="card"><div className="card-header">Special prices for {row.name}</div>
          <div className="card-body"><Messages items={pmsgs} />
            <form onSubmit={setPrc} className="row g-2 align-items-end">
              <div className="col-md-7"><label className="form-label">{term('item')}</label><ItemSearch path="/lookup/sale-items" value={item.id} label={item.label} placeholder="Search item, SKU or barcode" onPick={(p) => setItem(p ? { id: String(p.id), label: p.label } : { id: '', label: '' })} /></div>
              <div className="col-md-3"><label className="form-label">Price</label><input type="number" step="0.01" min="0" className="form-control" value={price} onChange={(e) => setPrice(e.target.value)} required /></div>
              <div className="col-md-2"><button className="btn btn-primary w-100">Set</button></div>
            </form></div>
          <div className="table-responsive"><table className="table table-sm mb-0"><thead><tr><th>{term('item')}</th><th className="text-end">List price</th><th className="text-end">Group price</th><th /></tr></thead><tbody>
            {prices.map((p) => <tr key={p.id}><td>{p.item_name}{p.vname ? ' — ' + p.vname : ''} <small className="text-muted">{p.sku}</small></td><td className="text-end">{money(p.sale_price)}</td><td className="text-end"><strong>{money(p.price)}</strong></td>
              <td className="text-end"><button className="btn btn-sm btn-outline-danger" aria-label="Remove price" onClick={() => rm(p.id)}>×</button></td></tr>)}
            {!prices.length && <tr><td colSpan={4} className="text-muted">No special prices. The default discount applies.</td></tr>}</tbody></table></div></div></div>}
      </div>
    </>
  );
}
