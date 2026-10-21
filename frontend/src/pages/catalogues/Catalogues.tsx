import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { money } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';

/** Catalogues: the books the showroom sells from. */
export function CatalogueList() {
  const { can } = useSession(); const [q, setQ] = useState(''); const [applied, setApplied] = useState('');
  const res = useQuery({ queryKey: ['catalogues', applied], queryFn: async () => (await get(`/catalogues?q=${encodeURIComponent(applied)}`)).data });
  const d = res.data;
  return (
    <>
      <PageHead title="Catalogues">
        <Link className="btn btn-outline-secondary" to="/products">All products</Link>
        {can('products.create') && <Link className="btn btn-primary" to="/catalogues/create"><i className="bi bi-plus-lg" /> New catalogue</Link>}
      </PageHead>
      <p className="text-muted">A catalogue is a book the customer looks through (for example CARAVAN). A new catalogue is known by its gutt number; give it a name when it gets one. The designs in it are its products.</p>
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); setApplied(q); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search by gutt no. or name" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
      </form>
      {res.isLoading ? <Spinner /> : (
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>Gutt no.</th><th>Catalogue</th><th className="text-end">Products</th><th>Notes</th><th /></tr></thead>
          <tbody>
            {d.rows.map((r: any) => (
              <tr key={r.id} className={Number(r.is_active) ? '' : 'text-muted'}>
                <td className="text-nowrap">{r.code ?? <span className="text-muted">—</span>}</td>
                <td><Link className="fw-medium" to={`/catalogues/${r.id}`}>{r.name}</Link>{Number(r.named) ? null : <> <Badge tone="warning">name pending</Badge></>}{Number(r.is_active) ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
                <td className="text-end">{r.products}{Number(r.products) !== Number(r.active_products) ? <span className="text-muted small"> ({r.active_products} active)</span> : null}</td>
                <td className="small text-muted">{r.notes}</td>
                <td className="text-end">{can('products.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/catalogues/${r.id}/edit`}>Edit</Link>}</td>
              </tr>))}
            {!d.rows.length && <tr><td colSpan={5} className="text-muted py-4">No catalogues yet. Create one, then add its products or import them from a CSV file on the Products page.</td></tr>}
          </tbody></table></div></div>
      )}
      {d && d.loose > 0 && <p className="small text-muted mt-2">{d.loose} product(s) are not in any catalogue (ready-made goods and the like). <Link to="/products?catalogue=none">Show them</Link></p>}
    </>
  );
}

export function CatalogueDetail() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const { can } = useSession();
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const res = useQuery({ queryKey: ['catalogue', id], queryFn: async () => (await get(`/catalogues/${id}`)).data });
  if (res.isLoading) return <Spinner />;
  const d = res.data; if (!d) return <div className="alert alert-danger">Catalogue not found.</div>;
  const c = d.c; const rows: any[] = d.products;
  const remove = async () => {
    if (!confirm('Delete this catalogue?')) return;
    const r = await post(`/catalogues/${id}/delete`); if (r.ok) { qc.invalidateQueries({ queryKey: ['catalogues'] }); nav('/catalogues'); } else setMsgs(r.messages ?? []);
  };
  return (
    <>
      <PageHead title={<>{c.name} {c.code && c.code !== c.name ? <small className="text-muted fw-normal">gutt {c.code}</small> : null} {Number(c.named) ? null : <Badge tone="warning">name pending</Badge>} {Number(c.is_active) ? null : <Badge tone="dark">Inactive</Badge>}</>}>
        <Link className="btn btn-outline-secondary" to="/catalogues">All catalogues</Link>
        {can('products.edit') && <Link className="btn btn-outline-primary" to={`/catalogues/${c.id}/edit`}>Edit</Link>}
        {can('products.create') && <Link className="btn btn-primary" to={`/products/create?catalogue=${c.id}`}><i className="bi bi-plus-lg" /> Add product</Link>}
      </PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      {c.notes ? <p className="text-muted">{c.notes}</p> : null}
      <div className="card"><div className="card-header">Products <span className="act-n">{rows.length}</span></div>
        <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>Gutt no.</th><th>Product</th><th>Item</th><th className="text-end">Price</th><th /></tr></thead>
          <tbody>
            {rows.map((p) => (
              <tr key={p.id} className={Number(p.is_active) ? '' : 'text-muted'}>
                <td className="text-nowrap">{p.code ?? <span className="text-muted">—</span>}</td>
                <td className="fw-medium">{p.name}{Number(p.named) ? null : <> <Badge tone="warning">name pending</Badge></>}{Number(p.is_active) ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
                <td><Link to={`/items/${p.item_id}`}>{p.item_name}</Link> <span className="text-muted small">{p.sku}</span></td>
                <td className="text-end">{money(p.price)}</td>
                <td className="text-end">{can('products.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/products/${p.id}/edit`}>Edit</Link>}</td>
              </tr>))}
            {!rows.length && <tr><td colSpan={5} className="text-muted py-4">No products in this catalogue yet.</td></tr>}
          </tbody></table></div></div>
      {can('products.delete') && !rows.length && <button className="btn btn-link text-danger mt-2" onClick={remove}>Delete this catalogue</button>}
    </>
  );
}

export function CatalogueForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient();
  const res = useQuery({ queryKey: ['catalogue-form', id ?? 'new'], queryFn: async () => (await get(id ? `/catalogues/${id}/edit` : '/catalogues/create')).data });
  const [f, setF] = useState<null | { code: string; name: string; notes: string; is_active: boolean }>(null); const [msgs, setMsgs] = useState<Msg[]>([]);
  if (res.isLoading || !res.data) return <Spinner />;
  const row = res.data.row; const v = f ?? { code: row?.code ?? '', name: row && Number(row.named) ? row.name : '', notes: row?.notes ?? '', is_active: row ? !!Number(row.is_active) : true };
  const save = async (e: React.FormEvent) => {
    e.preventDefault(); setMsgs([]);
    const r = await post(id ? `/catalogues/${id}` : '/catalogues', { code: v.code, name: v.name, notes: v.notes, is_active: v.is_active ? 1 : 0 });
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    qc.invalidateQueries({ queryKey: ['catalogues'] }); qc.invalidateQueries({ queryKey: ['catalogue'] });
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/catalogues');
  };
  return (
    <>
      <PageHead title={row ? `Edit ${row.name}` : 'New catalogue'} />
      <div className="row"><div className="col-lg-6"><div className="card"><div className="card-body">
        <Messages items={msgs} />
        <form onSubmit={save}>
          <div className="mb-3"><label className="form-label">Gutt no.</label><input className="form-control" maxLength={40} value={v.code} onChange={(e) => setF({ ...v, code: e.target.value })} autoFocus placeholder="the number it is known by to start with" /></div>
          <div className="mb-3"><label className="form-label">Catalogue name <span className="text-muted fw-normal">(when it gets one)</span></label><input className="form-control" maxLength={120} value={v.name} onChange={(e) => setF({ ...v, name: e.target.value })} required={!v.code} placeholder={v.code ? `until named it is called ${v.code}` : 'for example CARAVAN'} /></div>
          <div className="mb-3"><label className="form-label">Notes <span className="text-muted fw-normal">(optional)</span></label><input className="form-control" maxLength={255} value={v.notes} onChange={(e) => setF({ ...v, notes: e.target.value })} /></div>
          <div className="form-check form-switch mb-3"><input className="form-check-input" type="checkbox" id="act" checked={v.is_active} onChange={(e) => setF({ ...v, is_active: e.target.checked })} /><label className="form-check-label" htmlFor="act">Active <span className="text-muted small">(an inactive catalogue and its products are not offered when selling)</span></label></div>
          <button className="btn btn-primary">Save</button> <Link className="btn btn-link" to={row ? `/catalogues/${row.id}` : '/catalogues'}>Cancel</Link>
        </form></div></div></div></div>
    </>
  );
}
