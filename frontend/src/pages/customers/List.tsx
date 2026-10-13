import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post } from '../../lib/api';
import { money } from '../../lib/format';
import { useSession } from '../../lib/session';
import { PageHead, Spinner } from '../../components/ui';
import ColumnPicker from '../../components/ColumnPicker';

export default function CustomerList() {
  const { can, term } = useSession();
  const qc = useQueryClient();
  const [q, setQ] = useState(''); const [group, setGroup] = useState('');
  const [applied, setApplied] = useState({ q: '', group: '' });
  const res = useQuery({ queryKey: ['customers', applied], queryFn: async () => (await get(`/customers?q=${encodeURIComponent(applied.q)}&group=${applied.group}`)).data });
  const d = res.data;
  const col = (k: string) => d?.cols?.includes(k);
  return (
    <>
      <PageHead title={term('customer', true)}>
        <Link className="btn btn-outline-secondary" to="/customers/groups">{term('customer')} groups</Link>
        {can('customers.create') && <Link className="btn btn-primary" to="/customers/create"><i className="bi bi-plus-lg" /> Add {term('customer').toLowerCase()}</Link>}
      </PageHead>
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); setApplied({ q, group }); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search name, phone, contact" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-md-3"><select className="form-select" value={group} onChange={(e) => { setGroup(e.target.value); setApplied({ q, group: e.target.value }); }}><option value="">All groups</option>{(d?.groups ?? []).map((g: any) => <option key={g.id} value={g.id}>{g.name}</option>)}</select></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
      </form>
      {res.isLoading ? <Spinner /> : (
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>{term('customer')}</th>{col('group') && <th>Group</th>}{col('phone') && <th>Phone</th>}{(d.cfList ?? []).map((f: any) => <th key={f.id}>{f.label}</th>)}{col('credit') && <th>Credit</th>}{col('owes') && <th className="text-end">Owes us</th>}
            <th className="text-end col-th"><ColumnPicker list="customers" entity="customer" onApplied={() => qc.invalidateQueries({ queryKey: ['customers'] })} /></th></tr></thead>
          <tbody>
            {d.rows.map((r: any) => (
              <tr key={r.id} className={r.is_active ? '' : 'text-muted'}>
                <td><Link to={`/customers/${r.id}`}>{r.name}</Link>{r.is_walkin ? <> <span className="badge text-bg-light border">Counter sales</span></> : null}{r.is_active ? null : <> <span className="badge text-bg-dark">Inactive</span></>}</td>
                {col('group') && <td>{r.group_name ?? ''}</td>}{col('phone') && <td>{r.phone ?? ''}</td>}
                {(d.cfList ?? []).map((f: any) => <td key={f.id}>{String(r.cf?.[f.id] ?? '')}</td>)}
                {col('credit') && <td>{r.credit_days ? `${r.credit_days} days` : 'Immediate'}</td>}
                {col('owes') && <td className={`text-end ${r.outstanding > 0.004 ? 'text-danger' : ''}`}>{money(r.outstanding)}</td>}
                <td className="text-end">{can('customers.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/customers/${r.id}/edit`}>Edit</Link>}</td>
              </tr>
            ))}
            {!d.rows.length && <tr><td colSpan={9} className="text-muted">No {term('customer', true).toLowerCase()} found.</td></tr>}
          </tbody></table></div></div>
      )}
    </>
  );
}
