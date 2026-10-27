import { useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, postForm, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';
import RateForm from '../../components/RateForm';

/** Where a rate page goes back to: the screen it was opened from (a router path), else the rate lists. */
const useBack = () => {
  const [sp] = useSearchParams(); const r = sp.get('return') ?? '';
  return /^\/[A-Za-z0-9_\/-]*$/.test(r) ? r : '/purchase/rates';
};
const useSuppliers = () => useQuery({ queryKey: ['rates', 'suppliers'], queryFn: async () => (await get('/purchase/rates?supplier=-1')).data });

/** Add a rate, or revise the current one (/purchase/rates/:id/edit?supplier=…), on a page of its own. */
export function RatePage() {
  const { id } = useParams(); const [sp] = useSearchParams(); const nav = useNavigate(); const qc = useQueryClient();
  const { term } = useSession(); const back = useBack();
  const fixed = sp.get('supplier') ?? ''; const variant = sp.get('variant') ?? '';
  const [supplier, setSupplier] = useState(fixed);
  const sup = useSuppliers();
  const row = useQuery({ queryKey: ['rate-row', id, fixed], enabled: !!id, queryFn: async () => ((await get(`/purchase/rates?supplier=${fixed}&show=all`)).data.rows as any[]).find((x) => String(x.id) === String(id)) ?? null });
  const item = useQuery({ queryKey: ['rate-item', variant, supplier], enabled: !!variant && !id, queryFn: async () => { const r: any = await get(`/lookup/items?variant=${variant}${supplier ? `&supplier=${supplier}` : ''}`); const v = (Array.isArray(r) ? r : r.data ?? [])[0]; return v ? { id: String(v.id), label: `${v.item_name}${v.name ? ' — ' + v.name : ''} (${v.sku})` } : null; } });
  if (sup.isLoading || (id && row.isLoading) || (variant && !id && item.isLoading)) return <Spinner />;
  const list: any[] = sup.data?.suppliers ?? []; const preset = id ? row.data : undefined;
  if (id && !preset) return <div className="alert alert-danger">Rate not found.</div>;
  const chosen = list.find((x) => String(x.id) === String(supplier));
  const cur = chosen && chosen.origin === 'imported' && chosen.currency ? chosen.currency : 'INR';
  const done = () => { qc.invalidateQueries({ queryKey: ['rates'] }); ['supplier', 'si', 'item', 'record'].forEach((k) => qc.invalidateQueries({ queryKey: [k] })); nav(back); };
  return (
    <>
      <PageHead title={preset ? 'Revise rate' : 'Add a rate'}><Link className="btn btn-outline-secondary" to={back}>Cancel</Link></PageHead>
      <div className="card"><div className="card-body">
        <div className="row g-2 mb-3"><div className="col-md-6"><label className="form-label small mb-1">{term('supplier')}</label>
          <select className="form-select form-select-sm" value={supplier} disabled={!!preset || !!fixed} onChange={(e) => setSupplier(e.target.value)}><option value="">Choose…</option>{list.map((s: any) => <option key={s.id} value={s.id}>{s.name}{s.origin === 'imported' ? ` (${s.currency})` : ''}</option>)}</select></div></div>
        {supplier ? <RateForm plain key={supplier + (preset?.id ?? '') + (item.data?.id ?? '')} supplierId={Number(supplier)} preset={preset} initialItem={item.data ?? undefined} currency={cur} onDone={done} onCancel={() => nav(back)} /> : <div className="small text-muted">Choose the {term('supplier').toLowerCase()} first.</div>}
      </div></div>
    </>
  );
}

/** Import rates from a CSV file. */
export function RateImport() {
  const nav = useNavigate(); const qc = useQueryClient(); const [sp] = useSearchParams(); const { term } = useSession(); const back = useBack();
  const sup = useSuppliers(); const [supplier, setSupplier] = useState(sp.get('supplier') ?? '');
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (sup.isLoading) return <Spinner />;
  const upload = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault(); if (!supplier) return;
    setBusy(true); const r = await postForm(`/suppliers/${supplier}/rates/import`, new FormData(e.currentTarget)); setBusy(false);
    if (r.ok) { qc.invalidateQueries({ queryKey: ['rates'] }); nav(back); } else setMsgs(r.messages ?? []);
  };
  return (
    <>
      <PageHead title="Import rates from CSV"><Link className="btn btn-outline-secondary" to={back}>Cancel</Link></PageHead>
      <div className="card"><div className="card-body">
        <Messages items={msgs} />
        <form onSubmit={upload}><div className="row g-3">
          <div className="col-md-5"><label className="form-label">{term('supplier')}</label>
            <select className="form-select" value={supplier} onChange={(e) => setSupplier(e.target.value)} required><option value="">Choose…</option>{(sup.data?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select></div>
          <div className="col-md-5"><label className="form-label">CSV file</label><input type="file" name="file" accept=".csv,text/csv" className="form-control" required /></div>
          <div className="col-12 form-text mt-0">Columns: <code>sku, roll_rate, cut_rate, moq_rate, min_qty, discount_pct, lead_time_days, supplier_code, supplier_name, valid_from</code>. Only <b>sku</b> and <b>roll_rate</b> are needed; the rates are in the {term('supplier').toLowerCase()}&apos;s currency.</div>
          <div className="col-12 d-flex gap-2"><button className="btn btn-primary" disabled={busy || !supplier}>{busy ? 'Importing…' : 'Import'}</button>
            <a className={`btn btn-outline-secondary ${supplier ? '' : 'disabled'}`} href={supplier ? legacyUrl(`/suppliers/${supplier}/rates/template`) : '#'}><i className="bi bi-download" /> Template</a></div>
        </div></form>
      </div></div>
    </>
  );
}

/** The rupee value of each foreign currency, kept by the company; new purchase orders to imported suppliers start from these. */
export function ExchangeRates() {
  const nav = useNavigate(); const qc = useQueryClient(); const back = useBack();
  const sup = useSuppliers();
  const [rows, setRows] = useState<Record<string, { rate: string; on: boolean }> | null>(null);
  const [nw, setNw] = useState({ code: '', name: '', symbol: '', rate_to_inr: '' }); const [busy, setBusy] = useState(false); const [msgs, setMsgs] = useState<Msg[]>([]);
  if (sup.isLoading || !sup.data) return <Spinner />;
  const currencies: any[] = (sup.data.currencies ?? []).filter((c: any) => c.code !== 'INR');
  const v: Record<string, { rate: string; on: boolean }> = rows ?? Object.fromEntries(currencies.map((c) => [c.code, { rate: String(Number(c.rate_to_inr)), on: !!Number(c.is_active) }]));
  const save = async (e: React.FormEvent) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    const r = await post('/purchase/currencies', { rows: Object.entries(v).map(([code, x]) => ({ code, rate_to_inr: x.rate, is_active: x.on ? 1 : 0 })), new: nw });
    setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    qc.invalidateQueries({ queryKey: ['rates'] }); nav(back);
  };
  return (
    <>
      <PageHead title="Exchange rates"><Link className="btn btn-outline-secondary" to={back}>Cancel</Link></PageHead>
      <div className="card"><div className="card-body">
        <Messages items={msgs} />
        <p className="text-muted small">How many rupees one unit is worth. Imported suppliers are bought from in their own currency; receiving the goods books every stock cost, bill and report in rupees at the rate of the order. New orders start from these rates, and orders already made keep theirs.</p>
        <form onSubmit={save}>
          <div className="row g-3">
            {currencies.map((c) => (
              <div className="col-6 col-md-3" key={c.code}>
                <label className="form-label small mb-1">1 {c.code} <span className="text-muted">{c.name}</span></label>
                <div className="input-group"><span className="input-group-text">₹</span><input type="number" step="0.0001" min="0" className="form-control" value={v[c.code]?.rate ?? ''} onChange={(e) => setRows({ ...v, [c.code]: { ...v[c.code], rate: e.target.value } })} />
                  <span className="input-group-text" title="In use"><input type="checkbox" className="form-check-input mt-0" checked={v[c.code]?.on ?? false} onChange={(e) => setRows({ ...v, [c.code]: { ...v[c.code], on: e.target.checked } })} /></span></div>
              </div>))}
          </div>
          <h2 className="h6 mt-4">Add another currency</h2>
          <div className="row g-2">
            <div className="col-4 col-md-2"><input className="form-control" maxLength={3} placeholder="Code, e.g. CHF" value={nw.code} onChange={(e) => setNw({ ...nw, code: e.target.value.toUpperCase() })} /></div>
            <div className="col-8 col-md-3"><input className="form-control" placeholder="Name" value={nw.name} onChange={(e) => setNw({ ...nw, name: e.target.value })} /></div>
            <div className="col-4 col-md-2"><input className="form-control" maxLength={8} placeholder="Symbol" value={nw.symbol} onChange={(e) => setNw({ ...nw, symbol: e.target.value })} /></div>
            <div className="col-8 col-md-3"><div className="input-group"><span className="input-group-text">₹</span><input type="number" step="0.0001" min="0" className="form-control" placeholder="Rupees for 1" value={nw.rate_to_inr} onChange={(e) => setNw({ ...nw, rate_to_inr: e.target.value })} /></div></div>
          </div>
          <div className="mt-4 d-flex gap-2"><button className="btn btn-primary" disabled={busy}>{busy ? 'Saving…' : 'Save exchange rates'}</button><Link className="btn btn-link" to={back}>Cancel</Link></div>
        </form>
      </div></div>
    </>
  );
}
