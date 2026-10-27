import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, postForm, type Msg } from '../../lib/api';
import { fdate, moneyIn, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import { createPortal } from 'react-dom';
import RateForm from '../../components/RateForm';

const plain = (v: any) => rtrimZeros(Number(v).toFixed(2));
const rtrimZeros = (s: string) => s.replace(/\.?0+$/, '');

/** The rupee value of each foreign currency, kept by the company; new purchase orders to imported suppliers start from these. */
function ExchangeRates({ currencies, onSaved }: { currencies: any[]; onSaved: (m: Msg[]) => void }) {
  const [rows, setRows] = useState<Record<string, { rate: string; on: boolean }>>(() => Object.fromEntries(currencies.filter((c) => c.code !== 'INR').map((c) => [c.code, { rate: String(Number(c.rate_to_inr)), on: !!Number(c.is_active) }])));
  const [nw, setNw] = useState({ code: '', name: '', symbol: '', rate_to_inr: '' }); const [busy, setBusy] = useState(false);
  const save = async () => {
    setBusy(true);
    const r = await post('/purchase/currencies', { rows: Object.entries(rows).map(([code, v]) => ({ code, rate_to_inr: v.rate, is_active: v.on ? 1 : 0 })), new: nw });
    setBusy(false); onSaved(r.ok ? [{ type: 'success', msg: 'Exchange rates saved. New purchase orders use them; orders already made keep the rate they were made at.' }] : r.messages ?? []);
    if (r.ok) setNw({ code: '', name: '', symbol: '', rate_to_inr: '' });
  };
  return (
    <div className="p-3 border-bottom bg-body-tertiary">
      <div className="small text-muted mb-2">How many rupees one unit is worth. Used for imported suppliers: their orders are made in their currency and every stock cost, bill and report is in rupees at this rate.</div>
      <div className="row g-2 align-items-end">
        {currencies.filter((c) => c.code !== 'INR').map((c) => (
          <div className="col-6 col-md-3" key={c.code}>
            <label className="form-label small mb-1 d-flex justify-content-between"><span>1 {c.code} <span className="text-muted">{c.name}</span></span></label>
            <div className="input-group input-group-sm"><span className="input-group-text">₹</span><input type="number" step="0.0001" min="0" className="form-control" value={rows[c.code]?.rate ?? ''} onChange={(e) => setRows({ ...rows, [c.code]: { ...rows[c.code], rate: e.target.value } })} />
              <span className="input-group-text"><input type="checkbox" className="form-check-input mt-0" title="In use" checked={rows[c.code]?.on ?? false} onChange={(e) => setRows({ ...rows, [c.code]: { ...rows[c.code], on: e.target.checked } })} /></span></div>
          </div>))}
        <div className="col-12"><div className="small text-muted mt-1">Add another currency</div></div>
        <div className="col-4 col-md-2"><input className="form-control form-control-sm" maxLength={3} placeholder="Code, e.g. CHF" value={nw.code} onChange={(e) => setNw({ ...nw, code: e.target.value.toUpperCase() })} /></div>
        <div className="col-8 col-md-3"><input className="form-control form-control-sm" placeholder="Name" value={nw.name} onChange={(e) => setNw({ ...nw, name: e.target.value })} /></div>
        <div className="col-4 col-md-2"><input className="form-control form-control-sm" maxLength={8} placeholder="Symbol" value={nw.symbol} onChange={(e) => setNw({ ...nw, symbol: e.target.value })} /></div>
        <div className="col-8 col-md-3"><div className="input-group input-group-sm"><span className="input-group-text">₹</span><input type="number" step="0.0001" min="0" className="form-control" placeholder="Rupees for 1" value={nw.rate_to_inr} onChange={(e) => setNw({ ...nw, rate_to_inr: e.target.value })} /></div></div>
        <div className="col-12 d-flex gap-2"><button type="button" className="btn btn-sm btn-primary" onClick={save} disabled={busy}>{busy ? 'Saving…' : 'Save exchange rates'}</button></div>
      </div>
    </div>
  );
}

export default function RateList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const supplier = sp.get('supplier') ?? ''; const show = sp.get('show') === 'all' ? 'all' : 'current'; const applied = sp.get('q') ?? '';
  const [q, setQ] = useState(applied);
  const set = (k: string, v: string) => { const n = new URLSearchParams(sp); if (v) n.set(k, v); else n.delete(k); setSp(n); };
  const res = useQuery({ queryKey: ['rates', supplier, show, applied], queryFn: async () => (await get(`/purchase/rates?q=${encodeURIComponent(applied)}&supplier=${supplier}&show=${show}`)).data });
  const [form, setForm] = useState<null | { supplier: string; preset?: any }>(null);
  const [imp, setImp] = useState<null | { supplier: string }>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false); const [fx, setFx] = useState(false);
  const d = res.data; const canEdit = can('suppliers.edit');
  const curOf = (sid: string) => { const x = (d?.suppliers ?? []).find((y: any) => String(y.id) === String(sid)); return x && x.origin === 'imported' && x.currency ? x.currency : 'INR'; };
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
              <button className="btn btn-outline-secondary" onClick={() => setFx(!fx)}><i className="bi bi-currency-exchange" /><span className="d-none d-md-inline"> Exchange rates</span></button>
              <button className="btn btn-outline-secondary" onClick={() => { setForm(null); setImp({ supplier }); }}><i className="bi bi-upload" /><span className="d-none d-md-inline"> Import CSV</span></button>
              <button className="btn btn-primary" onClick={() => { setImp(null); setForm({ supplier }); }}><i className="bi bi-plus-lg" /> Add rate</button></>}
          </div>
        </div>
        {fx && canEdit && d && <ExchangeRates currencies={d.currencies} onSaved={(m) => { setMsgs(m); qc.invalidateQueries({ queryKey: ['rates'] }); }} />}
        {form && createPortal(
          <>
            <div className="modal-backdrop fade show" />
            <div className="modal fade show d-block" role="dialog" aria-modal="true" aria-label="Rate" onMouseDown={(e) => { if (e.target === e.currentTarget) setForm(null); }}>
              <div className="modal-dialog modal-lg"><div className="modal-content">
                <div className="modal-header"><h2 className="modal-title h5 mb-0">{form.preset ? 'Change rate' : 'Add a rate'}</h2><button type="button" className="btn-close" aria-label="Close" onClick={() => setForm(null)} /></div>
                <div className="modal-body" style={{ overflow: 'visible' }}>
                  <div className="row g-2 mb-3"><div className="col-md-6"><label className="form-label small mb-1">{term('supplier')}</label>
                    <select className="form-select form-select-sm" value={form.supplier} disabled={!!form.preset} onChange={(e) => setForm({ supplier: e.target.value })}><option value="">Choose…</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div></div>
                  {form.supplier ? <RateForm plain key={form.supplier + (form.preset?.id ?? '')} supplierId={Number(form.supplier)} preset={form.preset} currency={curOf(form.supplier)} onDone={reload} /> : <div className="small text-muted">Choose the {term('supplier').toLowerCase()} first.</div>}
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
              <div className="col-12 form-text">Columns: <code>sku, roll_rate, cut_rate, moq_rate, min_qty, discount_pct, lead_time_days, supplier_code, supplier_name, valid_from</code>. Only <b>sku</b> and <b>roll_rate</b> are needed; rates are in the {term('supplier').toLowerCase()}&apos;s currency.</div>
            </div>
          </form>)}
        <form className="rl-bar" onSubmit={(e) => { e.preventDefault(); set('q', q); }}>
          <div className="rl-search"><i className="bi bi-search" /><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search item, SKU, design or their code" aria-label="Search" /></div>
          <select className="form-select form-select-sm" style={{ maxWidth: 240 }} value={supplier} onChange={(e) => set('supplier', e.target.value)} aria-label={term('supplier')}><option value="">All {term('supplier', true).toLowerCase()}</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>
          <div className="rec-tabs act-tabs" role="group"><button type="button" className={show === 'current' ? 'on' : ''} onClick={() => set('show', '')}>Current</button><button type="button" className={show === 'all' ? 'on' : ''} onClick={() => set('show', 'all')}>With earlier rates</button></div>
        </form>
        {res.isLoading ? <Spinner /> : d.rows.length ? (
          <div className="table-responsive"><table className="table rl-table align-middle mb-0">
            <thead><tr><th>{term('item')}</th><th>{term('supplier')}</th><th className="text-end">Roll rate</th><th className="text-end">Cut rate</th><th className="text-end">MOQ rate</th><th className="text-end d-none d-md-table-cell">Disc.</th><th className="d-none d-lg-table-cell">Valid</th><th /></tr></thead>
            <tbody>{d.rows.map((r: any) => {
              const name = r.item_name + (r.vname ? ' — ' + r.vname : '');
              const lowest = r.is_current && d.low[r.variant_id] != null && Math.abs(r.net_inr - d.low[r.variant_id]) < 0.005;
              const foreign = r.currency && r.currency !== 'INR';
              const cell = (v: number | null) => v === null ? <span className="text-muted">—</span> : <>{moneyIn(v, r.currency)}</>;
              return (
                <tr key={r.id} className={r.is_current ? '' : 'rl-old'}>
                  <td><Link className="fw-medium" to={`/items/${r.item_id}`}>{name}</Link> <span className="text-muted small">{r.sku}</span>{r.is_current ? null : <> <span className="badge text-bg-light border fw-normal">Earlier</span></>}</td>
                  <td><Link to={`/suppliers/${r.supplier_id}`}>{r.supplier_name}</Link>{r.scode ? <span className="text-muted small"> {r.scode}</span> : null}{r.sname ? <div className="small text-muted">{r.sname}</div> : null}</td>
                  <td className="text-end fw-semibold">{cell(r.nets.roll)}{lowest ? <> <span className="badge text-bg-success fw-normal" title="Cheapest current roll rate for this item">Lowest</span></> : null}{foreign && r.is_current ? <div className="small text-muted fw-normal">≈ {moneyIn(r.net_inr, 'INR')}</div> : null}</td>
                  <td className="text-end">{cell(r.nets.cut)}</td>
                  <td className="text-end">{cell(r.nets.moq)}{r.nets.moq !== null ? <div className="small text-muted">from {qty(r.min_qty)}</div> : null}</td>
                  <td className="text-end d-none d-md-table-cell">{Number(r.discount_pct) > 0 ? `${plain(r.discount_pct)}%` : <span className="text-muted">—</span>}</td>
                  <td className="small text-nowrap d-none d-lg-table-cell">{fdate(r.valid_from)} <span className="text-muted">→</span> {r.valid_to ? fdate(r.valid_to) : <span className="text-muted">open</span>}</td>
                  <td className="text-end text-nowrap">{canEdit && <>{r.is_current && <button className="btn btn-sm btn-outline-primary" onClick={() => { setImp(null); setForm({ supplier: String(r.supplier_id), preset: { ...r, scode: r.scode, sname: r.sname } }); window.scrollTo({ top: 0, behavior: 'smooth' }); }}>Revise</button>} <button className="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove rate" onClick={() => del(r)}><i className="bi bi-trash" /></button></>}</td>
                </tr>);
            })}</tbody></table></div>
        ) : (
          <div className="rl-empty"><i className="bi bi-currency-rupee" /><div><b>{applied || supplier ? 'Nothing matches these filters' : 'No rates yet'}</b><div className="text-muted small">{applied || supplier ? `Clear the search or choose another ${term('supplier').toLowerCase()}.` : `Add the roll, cut and MOQ rates your ${term('supplier', true).toLowerCase()} quote. The rate fills in by itself when you pick the ${term('item').toLowerCase()} on a purchase order.`}</div></div></div>
        )}
      </section>
    </>
  );
}
