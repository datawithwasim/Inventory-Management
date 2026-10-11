import { useEffect, useState, type ReactNode } from 'react';
import { Navigate, NavLink, Outlet, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post, postForm, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

const NAV: [string, [string, string, string][]][] = [
  ['Organisation', [['company', 'Company profile', 'building'], ['preferences', 'Currency & dates', 'currency-exchange'], ['numbering', 'Document numbers', 'hash']]],
  ['Make it yours', [['appearance', 'Appearance', 'palette'], ['modules', 'Modules & menu', 'grid-1x2'], ['labels', 'Names (labels)', 'type'], ['templates', 'Print templates', 'printer']]],
  ['Rules', [['workflow', 'Rules & workflow', 'diagram-3']]],
];

/** The settings frame: grouped menu on the left, the chosen tab on the right. */
export function SettingsLayout() {
  const { can } = useSession();
  return (
    <>
      <PageHead title="Settings" />
      <div className="settings-grid">
        <nav className="settings-nav" aria-label="Settings">
          {NAV.map(([group, items]) => (
            <div key={group}><div className="settings-nav-head">{group}</div>
              {items.map(([k, label, icon]) => <NavLink key={k} to={`/settings/${k}`} className={({ isActive }) => `settings-nav-link ${isActive ? 'active' : ''}`}><i className={`bi bi-${icon}`} />{label}</NavLink>)}
            </div>
          ))}
          {can('settings.edit') && <div><div className="settings-nav-head">Forms</div>
            <a className="settings-nav-link" href={legacyUrl('/settings/formdesign')}><i className="bi bi-layout-text-window-reverse" />Page layout &amp; custom fields</a></div>}
        </nav>
        <div className="settings-body"><Outlet /></div>
      </div>
    </>
  );
}
export const SettingsHome = () => <Navigate to="/settings/company" replace />;

/** One tab's data and its Save: after a save the page data and the session (formats, names, colours, menu) are fetched again. */
function useTab(tab: string) {
  const qc = useQueryClient(); const { reload } = useSession();
  const q = useQuery({ queryKey: ['settings', tab], gcTime: 0, queryFn: async () => (await get(`/settings/${tab}`)).data });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const save = async (body: unknown, form = false) => {
    setBusy(true); setMsgs([]);
    const r = form ? await postForm(`/settings/${tab}`, body as FormData) : await post(`/settings/${tab}`, body);
    setBusy(false); setMsgs(r.messages ?? []);
    if (r.ok) { await qc.invalidateQueries({ queryKey: ['settings', tab] }); await reload(); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
    return r.ok;
  };
  return { d: q.data, loading: q.isLoading, msgs, setMsgs, busy, save };
}
const Frame = ({ msgs, onClose, children }: { msgs: Msg[]; onClose: () => void; children: ReactNode }) => <><Messages items={msgs} onClose={onClose} />{children}</>;

// ------------------------------------------------------------------ company

export function CompanyTab() {
  const t = useTab('company'); const [v, setV] = useState<Record<string, string> | null>(null); const [file, setFile] = useState<File | null>(null); const [rm, setRm] = useState(false);
  useEffect(() => { if (t.d && !v) setV(t.d.values); }, [t.d, v]);
  if (t.loading || !v) return <Spinner />;
  const set = (k: string, val: string) => setV({ ...v, [k]: val });
  const submit = async () => {
    const f = new FormData();
    ['name', 'legal_name', 'address', 'phone', 'email', 'website', 'tax_no', 'bank'].forEach((k) => f.append(k, v[k] ?? ''));
    if (file) f.append('logo', file); else if (rm) f.append('remove_logo', '1');
    if (await t.save(f, true)) { setFile(null); setRm(false); setV(null); }
  };
  const logo = t.d.values.logo as string | null;
  const inp = (k: string, label: string, col: string, extra: any = {}, help?: string) => <div className={col}><label className="form-label">{label}</label><input className="form-control" value={v[k] ?? ''} onChange={(e) => set(k, e.target.value)} {...extra} />{help && <div className="form-text">{help}</div>}</div>;
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <div className="card" style={{ maxWidth: 820 }}><div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void submit(); }}><div className="row g-3">
        {inp('name', 'Company name', 'col-md-6', { maxLength: 150, required: true }, 'Shown in the menu and on documents.')}
        {inp('legal_name', 'Legal / registered name', 'col-md-6', { maxLength: 150 }, 'Optional. Printed instead of the company name on invoices.')}
        <div className="col-12"><label className="form-label">Address</label><textarea className="form-control" rows={2} maxLength={500} value={v.address ?? ''} onChange={(e) => set('address', e.target.value)} /></div>
        {inp('phone', 'Phone', 'col-md-4', { maxLength: 60 })}{inp('email', 'Email', 'col-md-4', { type: 'email', maxLength: 190 })}{inp('website', 'Website', 'col-md-4', { maxLength: 190 })}
        {inp('tax_no', 'Tax number (GSTIN / VAT)', 'col-md-4', { maxLength: 60 })}
        <div className="col-md-8"><label className="form-label">Bank details (printed on invoices)</label><textarea className="form-control" rows={2} maxLength={500} placeholder="Bank, account number, IFSC…" value={v.bank ?? ''} onChange={(e) => set('bank', e.target.value)} /></div>
        <div className="col-12"><label className="form-label">Logo</label>
          <div className="d-flex align-items-center gap-3">
            {logo && <><img src={`${logo}?v=${t.d ? 1 : 0}`} alt="Logo" style={{ maxHeight: 70, maxWidth: 200 }} className="border rounded p-1 bg-white" />
              <div className="form-check"><input className="form-check-input" type="checkbox" id="rl" checked={rm} onChange={(e) => setRm(e.target.checked)} /><label className="form-check-label" htmlFor="rl">Remove the logo</label></div></>}
            <input type="file" className="form-control" style={{ maxWidth: 340 }} accept="image/png,image/jpeg,image/gif,image/webp" onChange={(e) => setFile(e.target.files?.[0] ?? null)} /></div>
          <div className="form-text">PNG, JPG, GIF or WebP, up to 1 MB.</div></div>
      </div><button className="btn btn-primary mt-3" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button></form></div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ preferences

export function PreferencesTab() {
  const t = useTab('preferences'); const [v, setV] = useState<Record<string, string> | null>(null);
  useEffect(() => { if (t.d && !v) setV(t.d.values); }, [t.d, v]);
  if (t.loading || !v) return <Spinner />;
  const set = (k: string, val: string) => setV({ ...v, [k]: val });
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <div className="card" style={{ maxWidth: 720 }}><div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void t.save(v); }}>
        <h2 className="h6">Currency</h2>
        <div className="row g-3 mb-3">
          <div className="col-md-3"><label className="form-label">Symbol</label><input className="form-control" maxLength={8} placeholder="₹  $  AED" value={v.symbol} onChange={(e) => set('symbol', e.target.value)} /><div className="form-text">Leave empty to show plain numbers.</div></div>
          <div className="col-md-3"><label className="form-label">Placed</label><select className="form-select" value={v.position} onChange={(e) => set('position', e.target.value)}><option value="before">Before the amount</option><option value="after">After the amount</option></select></div>
          <div className="col-md-3"><label className="form-label">Decimals</label><select className="form-select" value={v.decimals} onChange={(e) => set('decimals', e.target.value)}>{['0', '1', '2', '3'].map((x) => <option key={x}>{x}</option>)}</select></div>
          <div className="col-md-3"><label className="form-label">Digit grouping</label><select className="form-select" value={v.grouping} onChange={(e) => set('grouping', e.target.value)}><option value="intl">1,234,567.00</option><option value="indian">12,34,567.00 (Indian)</option></select></div>
        </div>
        <h2 className="h6">Dates</h2>
        <div className="mb-3" style={{ maxWidth: 300 }}><label className="form-label">Date format on screens and prints</label>
          <select className="form-select" value={v.date_format} onChange={(e) => set('date_format', e.target.value)}>{Object.entries(t.d.dateFormats as Record<string, string>).map(([k, l]) => <option key={k} value={k}>{l} — {t.d.today[k]}</option>)}</select></div>
        <button className="btn btn-primary" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button>
      </form></div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ numbering

export function NumberingTab() {
  const t = useTab('numbering'); const [num, setNum] = useState<Record<string, { prefix: string; pad: string; year: boolean }> | null>(null);
  useEffect(() => { if (t.d && !num) setNum(Object.fromEntries(Object.entries(t.d.docs as Record<string, any>).map(([k, x]) => [k, { prefix: x.cfg.prefix, pad: String(x.cfg.pad), year: !!x.cfg.year }]))); }, [t.d, num]);
  if (t.loading || !num) return <Spinner />;
  const set = (k: string, p: Partial<{ prefix: string; pad: string; year: boolean }>) => setNum({ ...num, [k]: { ...num[k], ...p } });
  const body = { num: Object.fromEntries(Object.entries(num).map(([k, x]) => [k, { prefix: x.prefix, pad: x.pad, year: x.year ? 1 : '' }])) };
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <p className="text-muted">Choose how your document numbers look. The number keeps counting; changing the prefix only changes how new documents are named.</p>
      <form onSubmit={(e) => { e.preventDefault(); void t.save(body).then((ok) => ok && setNum(null)); }}>
        <div className="card"><div className="table-responsive"><table className="table mb-0 align-middle">
          <thead><tr><th>Document</th><th style={{ width: 140 }}>Prefix</th><th style={{ width: 120 }}>Digits</th><th style={{ width: 160 }}>Add the year</th><th>Next number looks like</th></tr></thead><tbody>
            {Object.entries(t.d.docs as Record<string, any>).map(([k, x]) => (
              <tr key={k}><td>{x.label}</td>
                <td><input className="form-control form-control-sm text-uppercase" maxLength={10} required value={num[k].prefix} onChange={(e) => set(k, { prefix: e.target.value })} /></td>
                <td><input type="number" min={3} max={8} className="form-control form-control-sm" value={num[k].pad} onChange={(e) => set(k, { pad: e.target.value })} /></td>
                <td><div className="form-check"><input className="form-check-input" type="checkbox" checked={num[k].year} onChange={(e) => set(k, { year: e.target.checked })} /><label className="form-check-label small">restart every year</label></div></td>
                <td><code>{x.sample}</code></td></tr>))}
          </tbody></table></div></div>
        <button className="btn btn-primary mt-3" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button></form>
    </Frame>
  );
}

// ------------------------------------------------------------------ appearance

const hex2rgb = (h: string) => [1, 3, 5].map((i) => parseInt(h.substr(i, 2), 16));
const mix = (a: number[], b: number[], t: number) => '#' + [0, 1, 2].map((i) => ('0' + Math.round(a[i] * (1 - t) + b[i] * t).toString(16)).slice(-2)).join('');
const lum = (c: number[]) => { const f = (v: number) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); }; return 0.2126 * f(c[0]) + 0.7152 * f(c[1]) + 0.0722 * f(c[2]); };
/** Paints a brand colour onto the page straight away (the same maths as the PHP appearance page). */
function previewBrand(h: string) {
  if (!/^#[0-9a-f]{6}$/i.test(h)) return;
  const c = hex2rgb(h); const s = document.documentElement.style;
  s.setProperty('--brand', h); s.setProperty('--brand-rgb', c.join(',')); s.setProperty('--brand-600', mix(c, [0, 0, 0], 0.14)); s.setProperty('--brand-700', mix(c, [0, 0, 0], 0.28));
  s.setProperty('--brand-soft', mix(c, [255, 255, 255], 0.9)); s.setProperty('--brand-soft-dark', mix(c, [17, 24, 39], 0.78));
  s.setProperty('--brand-ink', lum(c) > 0.42 ? '#111827' : '#ffffff'); s.setProperty('--brand-text', lum(c) > 0.5 ? mix(c, [0, 0, 0], 0.45) : h); s.setProperty('--sb-brand', mix(c, [0, 0, 0], 0.3));
}

export function AppearanceTab() {
  const t = useTab('appearance'); const [v, setV] = useState<Record<string, string> | null>(null);
  useEffect(() => { if (t.d && !v) setV(t.d.values); }, [t.d, v]);
  if (t.loading || !v) return <Spinner />;
  const brands = t.d.brands as Record<string, string>;
  const pick = (k: string, val: string) => {
    setV({ ...v, [k]: val });
    if (k === 'brand') previewBrand(val);
    if (k === 'sidebar') document.body.className = document.body.className.replace(/sbs-\w+/, `sbs-${val}`);
    if (k === 'density') document.body.className = document.body.className.replace(/density-\w+/, `density-${val}`);
  };
  const options = (key: string, title: string, list: Record<string, string>, col: string, hint?: (k: string) => string) => (<>
    <h2 className="h6">{title}</h2>
    <div className="row g-2 mb-4">{Object.entries(list).map(([k, l]) => <div className={col} key={k}><label className="w-100" onClick={() => pick(key, k)}><input className="d-none" type="radio" readOnly checked={v[key] === k} /><span className="opt-card"><strong>{l}</strong>{hint && <span className="d-block small text-muted">{hint(k)}</span>}</span></label></div>)}</div></>);
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <div className="card"><div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void t.save({ brand: v.brand, sidebar: v.sidebar, density: v.density, mode: v.mode }); }}>
        <h2 className="h6">Brand colour</h2>
        <p className="text-muted small">Buttons, links, highlights and charts use this colour. You see the change straight away; it is saved when you press Save.</p>
        <div className="d-flex flex-wrap align-items-center gap-3 mb-4">
          {Object.entries(brands).map(([hex, name]) => <label className="position-relative" title={name} key={hex}><input className="swatch-radio" type="radio" readOnly checked={v.brand === hex} onClick={() => pick('brand', hex)} /><span className="swatch" style={{ background: hex }} onClick={() => pick('brand', hex)} /></label>)}
          <div className="d-flex align-items-center gap-2 ms-2"><span className="small text-muted">Custom</span><input type="color" className="form-control form-control-color" title="Pick any colour" value={v.brand} onChange={(e) => pick('brand', e.target.value)} /></div>
        </div>
        {options('sidebar', 'Menu style', t.d.sidebars, 'col-sm-4')}
        {options('density', 'Spacing', t.d.densities, 'col-sm-6', (k) => (k === 'compact' ? 'Tighter rows — more on the screen' : 'Roomy and easy on the eyes'))}
        <h2 className="h6">Default theme</h2><p className="text-muted small">Everyone can still switch between light and dark with the moon button at the top.</p>
        <div className="row g-2 mb-4">{Object.entries(t.d.modes as Record<string, string>).map(([k, l]) => <div className="col-sm-4" key={k}><label className="w-100" onClick={() => setV({ ...v, mode: k })}><input className="d-none" type="radio" readOnly checked={v.mode === k} /><span className="opt-card"><strong>{l}</strong></span></label></div>)}</div>
        <button className="btn btn-primary" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save appearance'}</button>
      </form></div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ modules

export function ModulesTab() {
  const t = useTab('modules'); const [on, setOn] = useState<Set<string> | null>(null);
  useEffect(() => { if (t.d && !on) setOn(new Set(Object.keys(t.d.modules).filter((k) => !(t.d.off as string[]).includes(k)))); }, [t.d, on]);
  if (t.loading || !on) return <Spinner />;
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <div className="card"><div className="card-body">
        <p className="text-muted">Switch off the parts you do not use. They disappear from the menu and the search, and their pages stop opening. Nothing is deleted — switch a part back on any time and everything is still there.</p>
        <form onSubmit={(e) => { e.preventDefault(); void t.save({ on: [...on] }); }}>
          <div className="list-group list-group-flush mb-3">
            {Object.entries(t.d.modules as Record<string, [string, string]>).map(([k, [label, desc]]) => (
              <label className="list-group-item d-flex align-items-center justify-content-between gap-3 px-0" key={k}>
                <span><span className="fw-semibold">{label}</span><span className="d-block small text-muted">{desc}</span></span>
                <span className="form-check form-switch m-0"><input className="form-check-input" type="checkbox" checked={on.has(k)} onChange={(e) => { const n = new Set(on); e.target.checked ? n.add(k) : n.delete(k); setOn(n); }} /></span>
              </label>))}
          </div>
          <button className="btn btn-primary" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button></form>
      </div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ labels

export function LabelsTab() {
  const t = useTab('labels'); const [v, setV] = useState<Record<string, [string, string]> | null>(null);
  useEffect(() => {
    if (t.d && !v) { const saved = (Array.isArray(t.d.saved) ? {} : t.d.saved) as Record<string, [string, string]>; setV(Object.fromEntries(Object.keys(t.d.terms).map((k) => [k, saved[k] ?? ['', '']]))); }
  }, [t.d, v]);
  if (t.loading || !v) return <Spinner />;
  const set = (k: string, i: 0 | 1, val: string) => setV({ ...v, [k]: i === 0 ? [val, v[k][1]] : [v[k][0], val] });
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <p className="text-muted">Use your own words. For example call suppliers "Vendors" or racks "Bins". Screens, menus and headings follow; leave a box empty to use the standard word.</p>
      <div className="card" style={{ maxWidth: 640 }}><div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void t.save({ label: v }).then((ok) => ok && setV(null)); }}>
        <table className="table table-sm align-middle"><thead><tr><th>Standard word</th><th>Your word (one)</th><th>Your word (many)</th></tr></thead><tbody>
          {Object.entries(t.d.terms as Record<string, [string, string]>).map(([k, [one, many]]) => (
            <tr key={k}><td>{one} / {many}</td>
              <td><input className="form-control form-control-sm" maxLength={30} placeholder={one} value={v[k][0]} onChange={(e) => set(k, 0, e.target.value)} /></td>
              <td><input className="form-control form-control-sm" maxLength={30} placeholder={many} value={v[k][1]} onChange={(e) => set(k, 1, e.target.value)} /></td></tr>))}
        </tbody></table>
        <button className="btn btn-primary" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button></form></div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ workflow

export function WorkflowTab() {
  const t = useTab('workflow'); const [v, setV] = useState<Record<string, string> | null>(null);
  useEffect(() => { if (t.d && !v) setV(t.d.values); }, [t.d, v]);
  if (t.loading || !v) return <Spinner />;
  const sw = (k: string, title: string, help: string) => (
    <div className="form-check form-switch mb-3"><input className="form-check-input" type="checkbox" id={k} checked={v[k] === '1'} onChange={(e) => setV({ ...v, [k]: e.target.checked ? '1' : '' })} />
      <label className="form-check-label" htmlFor={k}><strong>{title}</strong><span className="d-block text-muted small">{help}</span></label></div>);
  return (
    <Frame msgs={t.msgs} onClose={() => t.setMsgs([])}>
      <div className="card" style={{ maxWidth: 820 }}><div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void t.save(v); }}>
        {sw('po_approval', 'Purchase orders need approval', 'A person with the "approve" permission must approve a purchase order before goods can be received on it.')}
        {sw('negative_stock', 'Allow selling more than the stock shows (negative stock)', 'For ordinary items only. Fabric rolls (batch-tracked) can never go below zero, because a roll physically cannot.')}
        {sw('require_rack', 'Always choose a rack when stock comes in', 'On goods receipts and stock additions, for warehouses that have racks set up.')}
        <div className="mb-3" style={{ maxWidth: 420 }}><label className="form-label"><strong>When one item is cut from more than one roll (shade may differ)</strong></label>
          <select className="form-select" value={v.shade_rule === 'block' ? 'block' : 'warn'} onChange={(e) => setV({ ...v, shade_rule: e.target.value })}><option value="warn">Warn, but allow</option><option value="block">Do not allow</option></select></div>
        <div className="mb-3" style={{ maxWidth: 300 }}><label className="form-label"><strong>Highest discount staff can give (%)</strong></label>
          <input type="number" step="0.01" min="0" max="100" className="form-control" value={v.max_discount ?? ''} onChange={(e) => setV({ ...v, max_discount: e.target.value })} />
          <div className="form-text">0 means no limit. People with the sales "approve" permission (and owners) can go above it.</div></div>
        <button className="btn btn-primary" disabled={t.busy}>{t.busy ? 'Saving…' : 'Save'}</button>
      </form></div></div>
    </Frame>
  );
}

// ------------------------------------------------------------------ print templates

export function TemplatesTab() {
  const [sp, setSp] = useSearchParams(); const doc = sp.get('doc') ?? 'invoice';
  const qc = useQueryClient(); const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  const q = useQuery({ queryKey: ['settings', 'templates', doc], gcTime: 0, queryFn: async () => (await get(`/settings/templates?doc=${doc}`)).data });
  const [v, setV] = useState<Record<string, any> | null>(null);
  useEffect(() => { setV(null); }, [doc]);
  useEffect(() => { if (q.data && !v) setV(q.data.values); }, [q.data, v]);
  if (q.isLoading || !v) return <Spinner />;
  const d = q.data;
  const save = async () => {
    setBusy(true); setMsgs([]);
    const r = await post('/settings/templates', { ...v, doc });
    setBusy(false); setMsgs(r.messages ?? []); if (r.ok) qc.invalidateQueries({ queryKey: ['settings', 'templates'] });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };
  return (
    <Frame msgs={msgs} onClose={() => setMsgs([])}>
      <div className="row g-3"><div className="col-lg-3"><div className="list-group">
        {Object.entries(d.docs as Record<string, string>).map(([k, l]) => <button type="button" key={k} className={`list-group-item list-group-item-action ${k === doc ? 'active' : ''}`} onClick={() => setSp({ doc: k })}>{l}</button>)}</div></div>
      <div className="col-lg-9"><div className="card"><div className="card-header d-flex justify-content-between align-items-center"><span>{d.docs[doc]} layout</span>
        {d.previewUrl ? <a className="btn btn-sm btn-outline-primary" target="_blank" rel="noreferrer" href={d.previewUrl}><i className="bi bi-eye" /> Preview with your latest {String(d.docs[doc]).toLowerCase()}</a> : <span className="text-muted small">Create a {String(d.docs[doc]).toLowerCase()} to preview it.</span>}</div>
        <div className="card-body"><form onSubmit={(e) => { e.preventDefault(); void save(); }}><div className="row g-3">
          {Object.entries(d.fields as Record<string, [string, string, any]>).map(([k, [label, type]]) => type === 'bool'
            ? <div className="col-md-6" key={k}><div className="form-check"><input className="form-check-input" type="checkbox" id={`f_${k}`} checked={!!Number(v[k])} onChange={(e) => setV({ ...v, [k]: e.target.checked ? 1 : 0 })} /><label className="form-check-label" htmlFor={`f_${k}`}>{label}</label></div></div>
            : type === 'color' ? <div className="col-md-6" key={k}><label className="form-label">{label}</label><input type="color" className="form-control form-control-color" value={v[k] ?? '#1f2937'} onChange={(e) => setV({ ...v, [k]: e.target.value })} /></div>
            : type === 'textarea' ? <div className="col-12" key={k}><label className="form-label">{label}</label><textarea className="form-control" rows={3} maxLength={1000} value={v[k] ?? ''} onChange={(e) => setV({ ...v, [k]: e.target.value })} /></div>
            : <div className="col-md-6" key={k}><label className="form-label">{label}</label><input className="form-control" maxLength={120} value={v[k] ?? ''} onChange={(e) => setV({ ...v, [k]: e.target.value })} /></div>)}
        </div><button className="btn btn-primary mt-3" disabled={busy}>{busy ? 'Saving…' : 'Save template'}</button></form></div></div>
      <p className="text-muted small mt-2">Your logo, address and tax number come from <NavLink to="/settings/company">Company profile</NavLink>.</p></div></div>
    </Frame>
  );
}

