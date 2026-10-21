import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, postForm, type Msg } from '../../lib/api';
import { money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import ItemSearch from '../../components/ItemSearch';

/** Products: the names and prices the showroom sells by. Each is sold from one item. */
export function ProductList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const applied = sp.get('q') ?? ''; const cat = sp.get('catalogue') ?? ''; const state = sp.get('state') ?? ''; const page = Number(sp.get('page') ?? 1);
  const [q, setQ] = useState(applied);
  const set = (patch: Record<string, string>) => { const n = new URLSearchParams(sp); Object.entries(patch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); if (!('page' in patch)) n.delete('page'); setSp(n); };
  const res = useQuery({ queryKey: ['products', applied, cat, state, page], queryFn: async () => (await get(`/products?q=${encodeURIComponent(applied)}&catalogue=${cat}&state=${state}&page=${page}`)).data });
  const [importing, setImporting] = useState(false); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const upload = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault(); setBusy(true);
    const r = await postForm('/products/import', new FormData(e.currentTarget)); setBusy(false);
    setMsgs(r.messages ?? []); if (r.ok) { setImporting(false); qc.invalidateQueries({ queryKey: ['products'] }); qc.invalidateQueries({ queryKey: ['catalogues'] }); }
  };
  const d = res.data;
  return (
    <>
      <PageHead title="Products">
        <a className="btn btn-outline-secondary" href={legacyUrl('/products/export')}><i className="bi bi-download" /> Export</a>
        {can('products.create') && <><button className="btn btn-outline-secondary" onClick={() => setImporting((x) => !x)}><i className="bi bi-upload" /> Import</button>
          <Link className="btn btn-primary" to="/products/create"><i className="bi bi-plus-lg" /> New product</Link></>}
      </PageHead>
      <Messages items={msgs} onClose={() => setMsgs([])} />
      {importing && (
        <form className="card card-body mb-3" onSubmit={upload}>
          <div className="row g-2 align-items-end">
            <div className="col-md-5"><label className="form-label small mb-1">CSV file</label><input type="file" name="file" accept=".csv,text/csv" className="form-control form-control-sm" required /></div>
            <div className="col-auto d-flex gap-2"><button className="btn btn-sm btn-primary" disabled={busy}>{busy ? 'Importing…' : 'Import'}</button>
              <a className="btn btn-sm btn-outline-secondary" href={legacyUrl('/products/template')}><i className="bi bi-download" /> Template</a>
              <button type="button" className="btn btn-sm btn-link text-muted" onClick={() => setImporting(false)}>Cancel</button></div>
            <div className="col-12 form-text">Columns: <code>Catalogue Name, Product Name, Item Name, Price, Gutt No</code>. Catalogue, Price and Gutt No are optional; a row with a Gutt No and no Product Name is called "catalogue-number" until it is named. A catalogue is created if it does not exist. The {term('item').toLowerCase()} must already exist under Items (by its name). A product with a name that already exists is updated. All rows are checked first; if anything is wrong nothing is imported.</div>
          </div>
        </form>)}
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set({ q }); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search gutt no., product, catalogue, item or SKU" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-md-3"><select className="form-select" value={cat} onChange={(e) => set({ catalogue: e.target.value })}><option value="">All catalogues</option><option value="none">No catalogue</option>{(d?.catalogues ?? []).map((c: any) => <option key={c.id} value={c.id}>{c.name}</option>)}</select></div>
        <div className="col-md-2"><select className="form-select" value={state} onChange={(e) => set({ state: e.target.value })}><option value="">Active and inactive</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
      </form>
      {res.isLoading ? <Spinner /> : (
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>Gutt no.</th><th>Product</th><th>Catalogue</th><th>{term('item')}</th><th className="text-end">Price</th><th className="text-end">Stock</th><th /></tr></thead>
          <tbody>
            {d.rows.map((r: any) => (
              <tr key={r.id} className={Number(r.is_active) ? '' : 'text-muted'}>
                <td className="text-nowrap">{r.code ?? <span className="text-muted">—</span>}</td>
                <td className="fw-medium">{r.name}{Number(r.named) ? null : <> <Badge tone="warning">name pending</Badge></>}{Number(r.is_active) ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
                <td>{r.catalogue_id ? <Link to={`/catalogues/${r.catalogue_id}`}>{r.catalogue}</Link> : <span className="text-muted">—</span>}</td>
                <td><Link to={`/items/${r.item_id}`}>{r.item_name}</Link> <span className="text-muted small">{r.sku}</span></td>
                <td className="text-end">{money(r.price)}</td>
                <td className="text-end">{qty(r.stock)} {r.unit}</td>
                <td className="text-end">{can('products.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/products/${r.id}/edit`}>Edit</Link>}</td>
              </tr>))}
            {!d.rows.length && <tr><td colSpan={7} className="text-muted py-4">No products found. A product is the name the showroom sells by (for example CARAVAN-7) and it is sold from one {term('item').toLowerCase()}.</td></tr>}
          </tbody></table></div></div>
      )}
      {d && d.pages > 1 && <div className="d-flex gap-2 align-items-center mt-3"><button className="btn btn-sm btn-outline-secondary" disabled={page <= 1} onClick={() => set({ page: String(page - 1) })}>« Previous</button><span className="small text-muted">Page {page} of {d.pages} · {d.total} products</span><button className="btn btn-sm btn-outline-secondary" disabled={page >= d.pages} onClick={() => set({ page: String(page + 1) })}>Next »</button></div>}
    </>
  );
}

export function ProductForm() {
  const { id } = useParams(); const nav = useNavigate(); const qc = useQueryClient(); const [sp] = useSearchParams(); const { term } = useSession();
  const res = useQuery({ queryKey: ['product-form', id ?? 'new', sp.get('catalogue') ?? ''], gcTime: 0, queryFn: async () => (await get(id ? `/products/${id}/edit` : `/products/create?catalogue=${sp.get('catalogue') ?? ''}`)).data });
  type F = { code: string; name: string; catalogue_id: string; price: string; is_active: boolean; item: { id: string; label: string } };
  const [f, setF] = useState<F | null>(null); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (res.isLoading || !res.data) return <Spinner />;
  const d = res.data; const row = d.row;
  const v: F = f ?? { code: row?.code ?? '', name: row && Number(row.named) ? row.name : '', catalogue_id: String(row?.catalogue_id ?? d.preset?.catalogue_id ?? '') === '0' ? '' : String(row?.catalogue_id ?? d.preset?.catalogue_id ?? ''), price: row ? String(row.price) : '',
    is_active: row ? !!Number(row.is_active) : true, item: d.item ? { id: String(d.item.id), label: d.item.label } : { id: '', label: '' } };
  const put = (patch: Partial<F>) => setF({ ...v, ...patch });
  const save = async (another: boolean) => {
    setBusy(true); setMsgs([]);
    const r = await post(id ? `/products/${id}` : '/products', { code: v.code, name: v.name, catalogue_id: v.catalogue_id, variant_id: v.item.id, price: v.price, is_active: v.is_active ? 1 : 0, add_another: another ? 1 : '' });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['products'] }); qc.invalidateQueries({ queryKey: ['catalogues'] }); qc.invalidateQueries({ queryKey: ['catalogue'] });
    if (another) { setF({ code: '', name: '', catalogue_id: v.catalogue_id, price: v.price, is_active: true, item: { id: '', label: '' } }); return; }
    nav(r.redirect ? '/' + r.redirect.replace(/^\//, '') : '/products');
  };
  const remove = async () => { if (!confirm('Delete this product?')) return; const r = await post(`/products/${id}/delete`); if (r.ok) { qc.invalidateQueries({ queryKey: ['products'] }); nav(row.catalogue_id ? `/catalogues/${row.catalogue_id}` : '/products'); } else setMsgs(r.messages ?? []); };
  return (
    <>
      <PageHead title={row ? `Edit ${row.name}` : 'New product'} />
      <div className="row"><div className="col-lg-7"><div className="card"><div className="card-body">
        <Messages items={msgs} />
        <form onSubmit={(e) => { e.preventDefault(); void save(false); }}>
          <div className="row g-3">
            <div className="col-md-3"><label className="form-label">Gutt no.</label><input className="form-control" maxLength={40} value={v.code} onChange={(e) => put({ code: e.target.value })} autoFocus placeholder="for example 7" /></div>
            <div className="col-md-5"><label className="form-label">Product name <span className="text-muted fw-normal">(when it gets one)</span></label><input className="form-control" maxLength={150} value={v.name} onChange={(e) => put({ name: e.target.value })} placeholder={v.code ? 'until named: catalogue-number' : 'for example CARAVAN-7'} required={!v.code} /></div>
            <div className="col-md-4"><label className="form-label">Catalogue <span className="text-muted fw-normal">(optional)</span></label>
              <select className="form-select" value={v.catalogue_id} onChange={(e) => put({ catalogue_id: e.target.value })}><option value="">No catalogue</option>{d.catalogues.map((c: any) => <option key={c.id} value={c.id}>{c.name}{c.code && c.code !== c.name ? ` · ${c.code}` : ''}{Number(c.is_active) ? '' : ' (inactive)'}</option>)}</select></div>
            <div className="col-12"><label className="form-label">{term('item')} it is sold from</label>
              <ItemSearch path="/lookup/sale-items" extra="&mode=item" value={v.item.id} label={v.item.label} placeholder="Search the item by name or SKU" onPick={(p) => put({ item: p ? { id: String(p.id), label: p.label } : { id: '', label: '' }, price: p && !v.price ? String(p.row.price ?? '') : v.price })} />
              <div className="form-text">The stock, rolls and purchasing stay on the {term('item').toLowerCase()}. Several products can be sold from the same {term('item').toLowerCase()}.</div></div>
            <div className="col-md-4"><label className="form-label">Price</label><input type="number" step="0.01" min="0" className="form-control" value={v.price} onChange={(e) => put({ price: e.target.value })} required /></div>
            <div className="col-md-8 d-flex align-items-end"><div className="form-check form-switch"><input className="form-check-input" type="checkbox" id="pact" checked={v.is_active} onChange={(e) => put({ is_active: e.target.checked })} /><label className="form-check-label" htmlFor="pact">Active <span className="text-muted small">(inactive products are not offered when selling)</span></label></div></div>
          </div>
          <div className="mt-4 d-flex gap-2 flex-wrap">
            <button className="btn btn-primary" disabled={busy}>Save</button>
            {!row && <button type="button" className="btn btn-outline-primary" disabled={busy} onClick={() => save(true)}>Save and add another</button>}
            <Link className="btn btn-link" to={v.catalogue_id ? `/catalogues/${v.catalogue_id}` : '/products'}>Cancel</Link>
            {row && <button type="button" className="btn btn-link text-danger ms-auto" onClick={remove}>Delete</button>}
          </div>
        </form></div></div></div></div>
    </>
  );
}
