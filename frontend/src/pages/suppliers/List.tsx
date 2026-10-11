import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get } from '../../lib/api';
import { money } from '../../lib/format';
import { useSession } from '../../lib/session';
import { PageHead, Spinner } from '../../components/ui';
import ColumnPicker from '../../components/ColumnPicker';

export default function SupplierList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [q, setQ] = useState(''); const [applied, setApplied] = useState('');
  const res = useQuery({ queryKey: ['suppliers', applied], queryFn: async () => (await get(`/suppliers?q=${encodeURIComponent(applied)}`)).data });
  const d = res.data; const col = (k: string) => d?.cols?.includes(k);
  return (
    <>
      <PageHead title={term('supplier', true)}>{can('suppliers.create') && <Link className="btn btn-primary" to="/suppliers/create"><i className="bi bi-plus-lg" /> Add {term('supplier').toLowerCase()}</Link>}</PageHead>
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); setApplied(q); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search name, phone, contact" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
      </form>
      {res.isLoading ? <Spinner /> : (
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>{term('supplier')}</th>{col('contact') && <th>Contact</th>}{col('phone') && <th>Phone</th>}{(d.cfList ?? []).map((f: any) => <th key={f.id}>{f.label}</th>)}{col('terms') && <th>Terms</th>}{col('owes') && <th className="text-end">We owe</th>}
            <th className="text-end col-th"><ColumnPicker list="suppliers" entity="supplier" onApplied={() => qc.invalidateQueries({ queryKey: ['suppliers'] })} /></th></tr></thead>
          <tbody>
            {d.rows.map((r: any) => (
              <tr key={r.id} className={r.is_active ? '' : 'text-muted'}>
                <td><Link to={`/suppliers/${r.id}`}>{r.name}</Link>{r.is_active ? null : <> <span className="badge text-bg-dark">Inactive</span></>}</td>
                {col('contact') && <td>{r.contact_person ?? ''}</td>}{col('phone') && <td>{r.phone ?? ''}</td>}
                {(d.cfList ?? []).map((f: any) => <td key={f.id}>{String(r.cf?.[f.id] ?? '')}</td>)}
                {col('terms') && <td>{r.payment_terms_days ? `${r.payment_terms_days} days` : 'Immediate'}</td>}
                {col('owes') && <td className={`text-end ${r.outstanding > 0.004 ? 'text-danger' : ''}`}>{money(r.outstanding)}</td>}
                <td className="text-end">{can('suppliers.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/suppliers/${r.id}/edit`}>Edit</Link>}</td>
              </tr>
            ))}
            {!d.rows.length && <tr><td colSpan={9} className="text-muted">No {term('supplier', true).toLowerCase()} found.</td></tr>}
          </tbody></table></div></div>
      )}
    </>
  );
}
