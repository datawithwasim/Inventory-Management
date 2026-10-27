import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, postForm, type Msg } from '../../lib/api';
import { fdate, money, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { createPortal } from 'react-dom';
import RateForm from '../../components/RateForm';

const plain = (v: any) => rtrimZeros(Number(v).toFixed(2));
const rtrimZeros = (s: string) => s.replace(/\.?0+$/, '');

export default function RateList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const supplier = sp.get('supplier') ?? ''; const show = sp.get('show') === 'all' ? 'all' : 'current'; const applied = sp.get('q') ?? '';
  const [q, setQ] = useState(applied);
  const set = (k: string, v: string) => { const n = new URLSearchParams(sp); if (v) n.set(k, v); else n.delete(k); setSp(n); };
  const res = useQuery({ queryKey: ['rates', supplier, show, applied], queryFn: async () => (await get(`/purchase/rates?q=${encodeURIComponent(applied)}&supplier=${supplier}&show=${show}`)).data });
  const [form, setForm] = useState<null | { supplier: string; preset?: any }>(null);
  const [imp, setImp] = useState<null | { supplier: string }>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const d = res.data; const canEdit = can('suppliers.edit');
  const reload = () => { qc.invalidateQueries({ queryKey: ['rates'] }); setForm(null); setImp(null); };
  const del = async (r: any) => { if (!confirm('Remove this rate?')) return; await post(`/suppliers/${r.supplier_id}/rates/${r.id}/delete`, { return: 'purchase/rates' }); reload(); };
  const upload = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault(); if (!imp?.supplier) return;
    const fd = new FormData(e.currentTarget); setBusy(true);
    const r = await postForm(`/suppliers/${imp.supplier}/rates/import`, fd); setBusy(false);
    setMsgs(r.messages ?? []); if (r.ok) reload();
  };
  const stats: [string, number, string][] = d ? [['Current rates', d.stats.current, 'tags'], [term('supplier', true), d.stats.suppliers, 'truck'], [`${term('item', true)} with a rate`, d.stats.items, 'box-seam'], ['Earlier rates kept', d.stats.earlier, 'clock-history']] : [];
  return (
    <>
      <PageHead title="Rate lists" />
      <Messages items={msgs} />
      {d && <div className="row g-3 mb-3">{stats.map(([l, n, ic]) => (
        <div className="col-6 col-lg-3" key={l}><div className="card"><div className="card-body d-flex align-items-center gap-3"><span className="rec-avatar" style={{ width: 40, height: 40 }}><i className={`bi bi-${ic}`} /></span><div><div className="fs-4 lh-1">{n}</div><div className="small text-muted">{l}</div></div></div></div></div>))}</div>}
      <section className="card mb-3">
        <div className="card-header rl-head">
          <div><div className="rl-title"><i className="bi bi-currency-rupee" /> Supplier rate lists</div><div className="rl-sub">What every {term('supplier').toLowerCase()} charges for each {term('item').toLowerCase()} · filled in automatically on purchase orders</div></div>
          <div className="rl-tools">
            {can('reports.view') && <Link className="btn btn-outline-secondary" to="/reports/rate-comparison"><i className="bi bi-bar-chart" /><span className="d-none d-md-inline"> Compare suppliers</span></Link>}
            {canEdit && <>
              <button className="btn btn-outline-secondary" onClick={() => { setForm(null); setImp({ supplier }); }}><i className="bi bi-upload" /><span className="d-none d-md-inline"> Import CSV</span></button>
              <button className="btn btn-primary" onClick={() => { setImp(null); setForm({ supplier }); }}><i className="bi bi-plus-lg" /> Add rate</button></>}
          </div>
        </div>
        {form && createPortal(
          <>
            <div className="modal-backdrop fade show" />
            <div className="modal fade show d-block" role="dialog" aria-modal="true" aria-label="Rate" onMouseDown={(e) => { if (e.target === e.currentTarget) setForm(null); }}>
              <div className="modal-dialog modal-lg"><div className="modal-content">
                <div className="modal-header"><h2 className="modal-title h5 mb-0">{form.preset ? 'Change rate' : 'Add a rate'}</h2><button type="button" className="btn-close" aria-label="Close" onClick={() => setForm(null)} /></div>
                <div className="modal-body" style={{ overflow: 'visible' }}>
                  <div className="row g-2 mb-3"><div className="col-md-6"><label className="form-label small mb-1">{term('supplier')}</label>
                    <select className="form-select form-select-sm" value={form.supplier} disabled={!!form.preset} onChange={(e) => setForm({ supplier: e.target.value })}><option value="">Choose…</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div></div>
                  {form.supplier ? <RateForm plain key={form.supplier + (form.preset?.id ?? '')} supplierId={Number(form.supplier)} preset={form.preset} onDone={reload} /> : <div className="small text-muted">Choose the {term('supplier').toLowerCase()} first.</div>}
                </div>
              </div></div>
            </div>
          </>, document.body)}
        {imp && (
          <form className="p-3 border-bottom bg-body-tertiary" onSubmit={upload}>
            <div className="row g-2 align-items-end">
              <div className="col-md-4"><label className="form-label small mb-1">{term('supplier')}</label>
                <select className="form-select form-select-sm" value={imp.supplier} onChange={(e) => setImp({ supplier: e.target.value })} required><option value="">Choose…</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
              <div className="col-md-4"><label className="form-label small mb-1">CSV file</label><input type="file" name="file" accept=".csv,text/csv" className="form-control form-control-sm" required /></div>
              <div className="col-auto d-flex gap-2"><button className="btn btn-sm btn-primary" disabled={busy || !imp.supplier}>{busy ? 'Importing…' : 'Import'}</button>
                <a className={`btn btn-sm btn-outline-secondary ${imp.supplier ? '' : 'disabled'}`} href={imp.supplier ? legacyUrl(`/suppliers/${imp.supplier}/rates/template`) : '#'}><i className="bi bi-download" /> Template</a>
                <button type="button" className="btn btn-sm btn-link text-muted" onClick={() => setImp(null)}>Cancel</button></div>
              <div className="col-12 form-text">Columns: <code>sku, rate, discount_pct, min_qty, lead_time_days, supplier_code, supplier_name, valid_from</code>. Only <b>sku</b> and <b>rate</b> are needed.</div>
            </div>
          </form>)}
        <form className="rl-bar" onSubmit={(e) => { e.preventDefault(); set('q', q); }}>
          <div className="rl-search"><i className="bi bi-search" /><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search item, SKU, design or their code" aria-label="Search" /></div>
          <select className="form-select form-select-sm" style={{ maxWidth: 240 }} value={supplier} onChange={(e) => set('supplier', e.target.value)} aria-label={term('supplier')}><option value="">All {term('supplier', true).toLowerCase()}</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>
          <div className="rec-tabs act-tabs" role="group"><button type="button" className={show === 'current' ? 'on' : ''} onClick={() => set('show', '')}>Current</button><button type="button" className={show === 'all' ? 'on' : ''} onClick={() => set('show', 'all')}>With earlier rates</button></div>
        </form>
        {res.isLoading ? <Spinner /> : d.rows.length ? (
          <div className="table-responsive"><table className="table rl-table align-middle mb-0">
            <thead><tr><th>{term('item')}</th><th>{term('supplier')}</th><th className="text-end">Rate</th><th className="text-end">Disc.</th><th className="text-end">Net rate</th><th className="text-end d-none d-md-table-cell">Min qty</th><th className="d-none d-lg-table-cell">Valid</th><th /></tr></thead>
            <tbody>{d.rows.map((r: any) => {
              const name = r.item_name + (r.vname ? ' — ' + r.vname : '');
              const lowest = r.is_current && d.low[r.variant_id] != null && Math.abs(r.net - d.low[r.variant_id]) < 0.005;
              return (
                <tr key={r.id} className={r.is_current ? '' : 'rl-old'}>
                  <td><Link className="fw-medium" to={`/items/${r.item_id}`}>{name}</Link> <span className="text-muted small">{r.sku}</span>{r.is_current ? null : <> <span className="badge text-bg-light border fw-normal">Earlier</span></>}</td>
                  <td><Link to={`/suppliers/${r.supplier_id}`}>{r.supplier_name}</Link>{r.scode ? <span className="text-muted small"> {r.scode}</span> : null}{r.sname ? <div className="small text-muted">{r.sname}</div> : null}</td>
                  <td className="text-end">{money(r.rate)}</td>
                  <td className="text-end">{Number(r.discount_pct) > 0 ? `${plain(r.discount_pct)}%` : <span className="text-muted">—</span>}</td>
                  <td className="text-end fw-semibold">{money(r.net)}{lowest ? <> <span className="badge text-bg-success fw-normal" title="Cheapest current rate for this item">Lowest</span></> : null}</td>
                  <td className="text-end d-none d-md-table-cell">{Number(r.min_qty) > 0 ? qty(r.min_qty) : <span className="text-muted">—</span>}</td>
                  <td className="small text-nowrap d-none d-lg-table-cell">{fdate(r.valid_from)} <span className="text-muted">→</span> {r.valid_to ? fdate(r.valid_to) : <span className="text-muted">open</span>}</td>
                  <td className="text-end text-nowrap">{canEdit && <>{r.is_current && <button className="btn btn-sm btn-outline-primary" onClick={() => { setImp(null); setForm({ supplier: String(r.supplier_id), preset: { ...r, scode: r.scode, sname: r.sname } }); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>Revise</button>} <button className="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove rate" onClick={() => del(r)}><i className="bi bi-trash" /></button></>}</td>
                </tr>);
            })}</tbody></table></div>
        ) : (
          <div className="rl-empty"><i className="bi bi-currency-rupee" /><div><b>{applied || supplier ? 'Nothing matches these filters' : 'No rates yet'}</b><div className="text-muted small">{applied || supplier ? `Clear the search or choose another ${term('supplier').toLowerCase()}.` : `Add what your ${term('supplier', true).toLowerCase()} quote. The rate fills in by itself when you pick the ${term('item').toLowerCase()} on a purchase order.`}</div></div>
            {canEdit && !applied && !supplier && <button className="btn btn-primary btn-sm ms-auto" onClick={() => setForm({ supplier: '' })}><i className="bi bi-plus-lg" /> Add the first rate</button>}</div>
        )}
      </section>
    </>
  );
}
