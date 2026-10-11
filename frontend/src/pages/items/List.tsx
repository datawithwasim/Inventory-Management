import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl } from '../../lib/api';
import { money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, PageHead, Spinner } from '../../components/ui';
import ColumnPicker from '../../components/ColumnPicker';

export default function ItemList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const applied = sp.get('q') ?? ''; const cat = sp.get('category') ?? ''; const low = sp.get('low') === '1'; const page = Number(sp.get('page') ?? 1);
  const [q, setQ] = useState(applied);
  const set = (patch: Record<string, string>) => { const n = new URLSearchParams(sp); Object.entries(patch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); if (!('page' in patch)) n.delete('page'); setSp(n); };
  const res = useQuery({ queryKey: ['items', applied, cat, low, page], queryFn: async () => (await get(`/items?q=${encodeURIComponent(applied)}&category=${cat}&low=${low ? 1 : ''}&page=${page}`)).data });
  const d = res.data; const col = (k: string) => d?.cols?.includes(k);
  return (
    <>
      <PageHead title={term('item', true)}>
        <a className="btn btn-outline-secondary" href={legacyUrl('/items/export')}><i className="bi bi-download" /> Export</a>
        {can('items.create') && <>
          <a className="btn btn-outline-secondary" href={legacyUrl('/items/import')}><i className="bi bi-upload" /> Import</a>
          {d?.limitReached ? <span className="text-warning ms-2">Item limit reached</span> : <Link className="btn btn-primary" to="/items/create"><i className="bi bi-plus-lg" /> Add {term('item').toLowerCase()}</Link>}</>}
      </PageHead>
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q }); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search name, SKU, barcode" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-md-3"><select className="form-select" value={cat} onChange={(e) => set({ category: e.target.value })}><option value="">All categories</option>{(d?.categories ?? []).map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
        <div className="col-auto form-check d-flex align-items-center ms-2"><input className="form-check-input me-2" type="checkbox" id="low" checked={low} onChange={(e) => set({ low: e.target.checked ? '1' : '' })} /><label htmlFor="low" className="form-check-label">Low stock only</label></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Filter</button></div>
      </form>
      {res.isLoading ? <Spinner /> : (<>
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>{term('item')}</th>{col('category') && <th>Category</th>}{col('brand') && <th>Brand</th>}{(d.cfList ?? []).map((f: any) => <th key={f.id}>{f.label}</th>)}{col('variants') && <th>Variants</th>}{col('price') && <th className="text-end">Sale price</th>}{col('stock') && <th className="text-end">Stock</th>}
            <th className="text-end col-th"><ColumnPicker list="items" entity="item" onApplied={() => qc.invalidateQueries({ queryKey: ['items'] })} /></th></tr></thead>
          <tbody>
            {d.rows.map((r: any) => {
              const isLow = !r.is_bundle && Number(r.reorder_level) > 0 && Number(r.stock) <= Number(r.reorder_level);
              return (
                <tr key={r.id} className={r.is_active ? '' : 'text-muted'}>
                  <td><Link to={`/items/${r.id}`}>{r.name}</Link>{r.is_bundle ? <> <Badge tone="info">Bundle</Badge></> : null}{r.track_batch ? <> <Badge>{term('batch')}</Badge></> : null}{r.is_active ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
                  {col('category') && <td>{r.category ?? ''}</td>}{col('brand') && <td>{r.brand ?? ''}</td>}
                  {(d.cfList ?? []).map((f: any) => <td key={f.id}>{String(r.cf?.[f.id] ?? '')}</td>)}
                  {col('variants') && <td>{r.variant_count}</td>}
                  {col('price') && <td className="text-end">{money(r.min_price)}{r.variant_count > 1 ? '+' : ''}</td>}
                  {col('stock') && <td className="text-end">{qty(r.stock)} {r.is_bundle ? 'sets' : r.unit} {isLow ? <Badge tone="danger">Low</Badge> : null}</td>}
                  <td className="text-end">{can('items.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/items/${r.id}/edit`}>Edit</Link>}</td>
                </tr>
              );
            })}
            {!d.rows.length && <tr><td colSpan={10} className="text-muted">No {term('item', true).toLowerCase()} found.</td></tr>}
          </tbody></table></div></div>
        <div className="d-flex justify-content-between align-items-center mt-3">
          <nav aria-label="Pages"><ul className="pagination pagination-sm mb-0">
            <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}><button className="page-link" onClick={() => set({ page: String(page - 1) })}>‹</button></li>
            <li className="page-item disabled"><span className="page-link">{d.page} / {d.pages}</span></li>
            <li className={`page-item ${page >= d.pages ? 'disabled' : ''}`}><button className="page-link" onClick={() => set({ page: String(page + 1) })}>›</button></li></ul></nav>
          <span className="text-muted small">{d.total} {term('item', true).toLowerCase()}</span>
        </div></>)}
    </>
  );
}
