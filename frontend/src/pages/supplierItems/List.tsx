import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, postForm, type Msg } from '../../lib/api';
import { money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Badge, Messages, PageHead, Spinner } from '../../components/ui';
import ColumnPicker from '../../components/ColumnPicker';

export default function SupplierItemList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const supplier = sp.get('supplier') ?? ''; const link = sp.get('link') ?? ''; const applied = sp.get('q') ?? '';
  const [q, setQ] = useState(applied);
  const set = (k: string, v: string) => { const n = new URLSearchParams(sp); if (v) n.set(k, v); else n.delete(k); setSp(n); };
  const res = useQuery({ queryKey: ['supplier-items', supplier, link, applied], queryFn: async () => (await get(`/purchase/supplier-items?q=${encodeURIComponent(applied)}&supplier=${supplier}&link=${link}`)).data });
  const [importing, setImporting] = useState(false); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const upload = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault(); setBusy(true);
    const r = await postForm('/purchase/supplier-items/import', new FormData(e.currentTarget)); setBusy(false);
    setMsgs(r.messages ?? []); if (r.ok) { setImporting(false); qc.invalidateQueries({ queryKey: ['supplier-items'] }); }
  };
  const d = res.data; const col = (k: string) => d?.cols?.includes(k);
  return (
    <>
      <PageHead title="Supplier items">
        {can('suppliers.create') && <><a className="btn btn-outline-secondary" href={legacyUrl('/purchase/supplier-items/export')}>Export</a>
          <button className="btn btn-outline-secondary" onClick={() => setImporting((x) => !x)}>Import</button>
          <Link className="btn btn-primary" to={`/purchase/supplier-items/create${supplier ? `?supplier=${supplier}` : ''}`}><i className="bi bi-plus-lg" /> Add supplier item</Link></>}
      </PageHead>
      <Messages items={msgs} />
      {importing && (
        <form className="card card-body mb-3" onSubmit={upload}>
          <div className="row g-2 align-items-end">
            <div className="col-md-5"><label className="form-label small mb-1">CSV file</label><input type="file" name="file" accept=".csv,text/csv" className="form-control form-control-sm" required /></div>
            <div className="col-auto d-flex gap-2"><button className="btn btn-sm btn-primary" disabled={busy}>{busy ? 'Importing…' : 'Import'}</button>
              <a className="btn btn-sm btn-outline-secondary" href={legacyUrl('/purchase/supplier-items/template')}><i className="bi bi-download" /> Template</a>
              <button type="button" className="btn btn-sm btn-link text-muted" onClick={() => setImporting(false)}>Cancel</button></div>
            <div className="col-12 form-text">One row per supplier item: <code>supplier, supplier_item_name, supplier_item_code, sku, min_order_qty, lead_time_days, preferred, rate, discount_pct, valid_from, design_no, composition, width, gsm, pattern, finish</code>. Supplier names must match exactly; an existing row (same SKU or code) is updated.</div>
          </div>
        </form>)}
      <form className="row g-2 mb-3" onSubmit={(e) => { e.preventDefault(); set('q', q); }}>
        <div className="col-md-4"><input className="form-control" placeholder="Search their name or code, our item, SKU, supplier" value={q} onChange={(e) => setQ(e.target.value)} /></div>
        <div className="col-md-3"><select className="form-select" value={supplier} onChange={(e) => set('supplier', e.target.value)}><option value="">All {term('supplier', true).toLowerCase()}</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
        <div className="col-md-2"><select className="form-select" value={link} onChange={(e) => set('link', e.target.value)}><option value="">Linked or not</option><option value="linked">Linked to our item</option><option value="unlinked">Not linked yet</option></select></div>
        <div className="col-auto"><button className="btn btn-outline-secondary">Search</button></div>
      </form>
      {d?.unlinked > 0 && link !== 'unlinked' && <div className="alert alert-warning py-2 small"><i className="bi bi-link-45deg" /> {d.unlinked} supplier item(s) are not linked to one of your items yet, so they cannot be used on a purchase order. <a href="#" onClick={(e) => { e.preventDefault(); set('link', 'unlinked'); }}>Show them</a></div>}
      {res.isLoading ? <Spinner /> : (
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr><th>Supplier item</th><th>{term('supplier')}</th>{col('code') && <th>Code</th>}{col('details') && <th>Design &amp; details</th>}{col('our_item') && <th>Our item</th>}{col('rate') && <th className="text-end">Current rate</th>}{col('moq') && <th className="text-end">Min order</th>}{col('lead') && <th className="text-end">Lead time</th>}
            {(d.cfList ?? []).map((f: any) => <th key={f.id}>{f.label}</th>)}{col('preferred') && <th>Preferred</th>}
            <th className="text-end col-th"><ColumnPicker list="supplier_items" entity="supplier_item" onApplied={() => qc.invalidateQueries({ queryKey: ['supplier-items'] })} /></th></tr></thead>
          <tbody>
            {d.rows.map((r: any) => (
              <tr key={r.id} className={r.is_active ? '' : 'text-muted'}>
                <td><Link className="fw-medium" to={`/purchase/supplier-items/${r.id}`}>{r.supplier_name || r.supplier_code || 'Same as ours'}</Link>{r.is_active ? null : <> <Badge tone="dark">Inactive</Badge></>}</td>
                <td><Link to={`/suppliers/${r.supplier_id}`}>{r.supplier}</Link></td>
                {col('code') && <td>{r.supplier_code ?? ''}</td>}
                {col('details') && <td className="small text-muted">{[r.design_no && `Design ${r.design_no}`, r.composition, r.width && `Width ${r.width}`, r.gsm && `${r.gsm} GSM`, r.pattern, r.finish].filter(Boolean).join(' · ')}</td>}
                {col('our_item') && <td>{r.variant_id ? <><Link to={`/items/${r.item_id}`}>{r.item_name}{r.vname ? ` — ${r.vname}` : ''}</Link> <span className="text-muted small">{r.sku}</span></> : <Badge tone="warning">Not linked</Badge>}</td>}
                {col('rate') && <td className="text-end">{r.net != null ? money(r.net) : <span className="text-muted">—</span>}</td>}
                {col('moq') && <td className="text-end">{Number(r.min_order_qty) > 0 ? qty(r.min_order_qty) : <span className="text-muted">—</span>}</td>}
                {col('lead') && <td className="text-end">{r.lead_time_days != null ? `${r.lead_time_days} d` : <span className="text-muted">—</span>}</td>}
                {(d.cfList ?? []).map((f: any) => <td key={f.id}>{String(r.cf?.[f.id] ?? '')}</td>)}
                {col('preferred') && <td>{r.is_preferred ? <Badge tone="success">Preferred</Badge> : null}</td>}
                <td className="text-end">{can('suppliers.edit') && <Link className="btn btn-sm btn-outline-primary" to={`/purchase/supplier-items/${r.id}/edit`}>Edit</Link>}</td>
              </tr>
            ))}
            {!d.rows.length && <tr><td colSpan={12} className="text-muted py-4">No supplier items yet. A supplier item is a product as <i>your supplier</i> names and codes it, linked to the item in your own stock.</td></tr>}
          </tbody></table></div></div>
      )}
    </>
  );
}
