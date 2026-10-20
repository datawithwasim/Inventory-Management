import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { Messages, Spinner } from '../../components/ui';
import { routerPath } from '../../lib/routes';

/* The page layout designer: arrange the fields of every form and detail page, add the company's own fields,
   choose the summary strip and the related lists. It edits a copy of the layout and sends the changes only on Save. */

const CHOICE: Record<string, 1> = { dropdown: 1, radio: 1, multiselect: 1 };
const UNIQ: Record<string, 1> = { text: 1, email: 1, phone: 1, url: 1, number: 1, decimal: 1, dropdown: 1, date: 1 };
const ICONS: Record<string, string> = { text: 'input-cursor-text', textarea: 'text-paragraph', email: 'envelope', phone: 'telephone', url: 'link-45deg', number: '123', decimal: 'hash',
  currency: 'currency-rupee', percent: 'percent', date: 'calendar-date', datetime: 'calendar-event', dropdown: 'list-ul', radio: 'ui-radios', multiselect: 'ui-checks', checkbox: 'check2-square', lookup: 'search' };

type Fld = any; type Form = any;
type Drag = { kind: 'tile' | 'unused'; key: string } | { kind: 'new'; type: string } | null;
type Mark = { k: 'tile'; key: string; after: boolean } | { k: 'grid'; si: number } | { k: 'unused' } | { k: 'sum' } | null;
type Pop = { x: number; y: number; items: { p: string; label: string; icon: string; danger?: boolean; on?: boolean }[]; pick: (p: string) => void } | null;

const labelOf = (x: Fld) => (x.custom ? x.label : (x.custom_label || x.label));
const spanOf = (x: Fld, cols: number) => { if (x.w) return Math.max(1, Math.round(parseInt(x.w, 10) / 100 * 12)); if (cols === 0) return x.span || 12; return cols === 3 ? 4 : 12 / cols; };
const clone = <T,>(v: T): T => JSON.parse(JSON.stringify(v));

const safeBack = (r: string | null) => { const p = r && r.startsWith('/') && !r.startsWith('//') ? routerPath(r) : ''; return p && !p.startsWith('/settings/formdesign') ? p : '/settings/company'; };

export default function FormDesigner() {
  const [sp] = useSearchParams();
  const [version, setVersion] = useState(0);
  const [start, setStart] = useState(sp.get('form') ?? '');
  const res = useQuery({ queryKey: ['formdesign', version], gcTime: 0, staleTime: 0, queryFn: async () => (await get('/settings/formdesign')).data });
  useEffect(() => { document.body.classList.add('dz-page'); return () => document.body.classList.remove('dz-page'); }, []);
  if (res.isLoading || !res.data) return <Spinner />;
  return <Inner key={version} model={res.data.model} types={res.data.types} lookups={res.data.lookups ?? {}} widths={res.data.widths} start={start} back={safeBack(sp.get('return'))}
    reload={(formKey: string) => { setStart(formKey); setVersion((v) => v + 1); }} />;
}

function Inner({ model, types, lookups, widths, start, back, reload }: { model: Form[]; types: Record<string, string>; lookups: Record<string, string>; widths: Record<string, string>; start: string; back: string; reload: (k: string) => void }) {
  const nav = useNavigate();
  const initial = useRef<string>('');
  const initialByKey = useRef<Record<string, string>>({});
  const [data, setData] = useState<Form[]>(() => {
    const d = clone(model); d.forEach((f: Form) => { f.deleted = f.deleted || []; f.resetFlag = false; });
    initial.current = JSON.stringify(d); d.forEach((f: Form) => { initialByKey.current[f.key] = JSON.stringify(f); });
    return d;
  });
  const [cur, setCur] = useState(() => Math.max(0, model.findIndex((f) => f.key === start)));
  const [sel, setSel] = useState<string | null>(null);
  const [preview, setPreview] = useState(false);
  const [mark, setMark] = useState<Mark>(null);
  const [pop, setPop] = useState<Pop>(null);
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const [saving, setSaving] = useState(false);
  const hist = useRef<string[]>([initial.current]);
  const [, bump] = useState(0);
  const drag = useRef<Drag>(null);
  const newSeq = useRef(0);
  const typedTag = useRef<string | null>(null);

  const F: Form = data[cur];
  const dirty = JSON.stringify(data) !== initial.current;
  const canUndo = hist.current.length > 1;

  /** Change a copy of the layout, then keep it and remember the step for Undo. */
  const edit = (fn: (d: Form[], f: Form) => void, o: { keepReset?: boolean; tag?: string } = {}) => {
    const d = clone(data); const f = d[cur];
    fn(d, f);
    if (!o.keepReset) f.resetFlag = false;
    const now = JSON.stringify(d); const h = hist.current;
    if (o.tag) { if (typedTag.current === o.tag && h.length > 1) h[h.length - 1] = now; else if (h[h.length - 1] !== now) h.push(now); typedTag.current = o.tag; }
    else { typedTag.current = null; if (h[h.length - 1] !== now) h.push(now); }
    if (h.length > 80) h.shift();
    setData(d); bump((n) => n + 1);
  };
  const undo = () => {
    const h = hist.current; if (h.length < 2) return;
    h.pop(); const d = JSON.parse(h[h.length - 1]); setData(d); typedTag.current = null;
    if (sel && !d[cur].fields[sel]) setSel(null);
    bump((n) => n + 1);
  };
  const undoRef = useRef(undo); undoRef.current = undo;
  useEffect(() => {
    const k = (e: KeyboardEvent) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !/input|textarea|select/i.test((e.target as HTMLElement).tagName)) { e.preventDefault(); undoRef.current(); }
      if (e.key === 'Escape') { setPop(null); setPreview(false); }
    };
    const away = (e: MouseEvent) => { if (!(e.target as HTMLElement).closest('.dz-pop,[data-pop]')) setPop(null); };
    const warn = (e: BeforeUnloadEvent) => { if (JSON.stringify(dataRef.current) !== initial.current) { e.preventDefault(); e.returnValue = ''; } };
    document.addEventListener('keydown', k); document.addEventListener('mousedown', away); window.addEventListener('beforeunload', warn);
    return () => { document.removeEventListener('keydown', k); document.removeEventListener('mousedown', away); window.removeEventListener('beforeunload', warn); };
  }, []);
  const dataRef = useRef(data); dataRef.current = data;

  const fld = (key: string): Fld => F.fields[key];
  const locate = (f: Form, key: string): [number, number] | null => { for (let i = 0; i < f.sections.length; i++) { const j = f.sections[i].fields.indexOf(key); if (j > -1) return [i, j]; } return null; };
  const detach = (f: Form, key: string) => { const p = locate(f, key); if (p) f.sections[p[0]].fields.splice(p[1], 1); };
  const insertAt = (f: Form, si: number, ref: string | null, after: boolean, key: string) => {
    const s = f.sections[si]; let j = ref ? s.fields.indexOf(ref) : s.fields.length;
    if (j < 0) j = s.fields.length; else if (after) j++;
    s.fields.splice(j, 0, key);
  };
  const hide = (f: Form, key: string) => { const x = f.fields[key]; x.show = false; x.req = false; detach(f, key); if (f.summary) f.summary = f.summary.filter((k: string) => k !== key); };
  const moveField = (key: string, dir: number) => edit((_, f) => {
    const p = locate(f, key); if (!p) return; const [si, idx] = p; const j = idx + dir;
    if (j < 0) { if (si === 0) return; f.sections[si].fields.splice(idx, 1); f.sections[si - 1].fields.push(key); }
    else if (j >= f.sections[si].fields.length) { if (si === f.sections.length - 1) return; f.sections[si].fields.splice(idx, 1); f.sections[si + 1].fields.unshift(key); }
    else { f.sections[si].fields.splice(idx, 1); f.sections[si].fields.splice(j, 0, key); }
  });
  const toggleSummary = (key: string) => {
    const sum: string[] = F.summary || [];
    if (!sum.includes(key) && sum.length >= 4) { alert('The summary strip holds up to four fields.'); return; }
    edit((_, f) => { const s: string[] = f.summary || (f.summary = []); const i = s.indexOf(key); if (i > -1) s.splice(i, 1); else s.push(key); });
  };
  const deleteField = (key: string) => {
    const x = fld(key); if (!confirm(`Delete the field "${x.label}"? Everything typed into it on existing records is deleted too.`)) return;
    edit((_, f) => { detach(f, key); if (!f.fields[key].isNew) f.deleted.push(key); delete f.fields[key]; if (f.summary) f.summary = f.summary.filter((k: string) => k !== key); });
    if (sel === key) setSel(null);
  };
  const drop = (si: number, ref: string | null, after: boolean) => {
    const dr = drag.current; drag.current = null; setMark(null); if (!dr) return;
    if (dr.kind === 'tile' && dr.key === ref) return;
    if (dr.kind === 'new' && !F.customFields) return;
    let picked = ''; const nt = dr.kind === 'new' ? dr.type : '';
    edit((_, f) => {
      let k: string;
      if (dr.kind === 'tile') { k = dr.key; detach(f, k); }
      else if (dr.kind === 'unused') { k = dr.key; f.fields[k].show = true; }
      else {
        k = 'cf:new' + (++newSeq.current);
        f.fields[k] = { key: k, label: types[nt] || 'New field', kind: ICONS[nt] || 'input-cursor-text', optional: true, custom: true, isNew: true, type: nt, options: CHOICE[nt] ? 'Option 1\nOption 2' : nt === 'lookup' ? 'item' : '', show: true, req: false, inList: false, w: '', help: '', span: 4 };
      }
      insertAt(f, si, ref, after, k); picked = k;
    });
    setSel(picked);
  };

  const openPop = (e: React.MouseEvent, items: NonNullable<Pop>['items'], pick: (p: string) => void) => {
    const r = (e.currentTarget as HTMLElement).getBoundingClientRect();
    setPop({ x: Math.max(8, Math.min(window.innerWidth - 210, r.right - 200)), y: r.bottom + 6, items, pick });
  };

  const x: Fld | null = sel ? F.fields[sel] ?? null : null;
  const unused = Object.keys(F.fields).filter((k) => !F.fields[k].show);
  const rel: any[] = F.related || [];
  const sum: string[] = F.summary || [];

  /* ---------- save ---------- */
  const save = async (close: boolean) => {
    setSaving(true); setMsgs([]);
    const out: Record<string, any> = {};
    data.forEach((f: Form) => {
      if (JSON.stringify(f) === initialByKey.current[f.key]) return;
      const o: any = { style: f.style, lines: f.lines, deleted: f.deleted, sections: f.sections.map((s: any) => ({ title: s.title, cols: s.cols, fields: s.fields.slice() })), fields: {} };
      if (f.detail) { o.summary = (f.summary || []).slice(); o.related = (f.related || []).map((r: any) => ({ id: r.id, show: r.show })); }
      if (f.resetFlag) o.reset = true;
      Object.keys(f.fields).forEach((k) => {
        const y = f.fields[k];
        o.fields[k] = { w: y.w, help: y.help, show: y.show, req: y.req };
        if (y.custom) { Object.assign(o.fields[k], { name: y.label, type: y.type, options: y.options, inList: y.inList, unique: !!y.unique }); if (y.isNew) o.fields[k].isNew = true; }
        else o.fields[k].custom_label = y.custom_label;
      });
      out[f.key] = o;
    });
    const r = await post('/settings/formdesign', { payload: JSON.stringify(out), current: F.key, close: '', return: '' });
    setSaving(false);
    if (!r.ok) { setMsgs(r.messages ?? []); return; }
    initial.current = JSON.stringify(data); // saved: leaving does not need a warning
    if (close) nav(back); else reload(F.key);
  };
  const leave = (e: React.MouseEvent) => { if (dirty && !confirm('You have unsaved changes. Leave without saving?')) e.preventDefault(); };

  const resetForm = () => {
    if (!confirm(`Put "${F.title}" back to the standard design? Your custom fields stay. (Nothing is saved until you press Save.)`)) return;
    edit((_, f) => {
      const std: string[] = f.stdOrder.slice(); const cfs = Object.keys(f.fields).filter((k) => f.fields[k].custom);
      Object.keys(f.fields).forEach((k) => { const y = f.fields[k]; y.w = ''; y.help = ''; if (!y.custom) { y.custom_label = ''; y.show = true; y.req = false; } else y.show = true; });
      f.sections = [{ id: 's1', title: '', cols: 0, fields: f.layout ? std : [] }];
      if (cfs.length) f.sections.push({ id: 's2', title: 'Additional details', cols: 0, fields: cfs });
      const sysK: string[] = f.sysOrder || []; sysK.forEach((k) => { f.fields[k].show = true; f.fields[k].custom_label = ''; });
      if (sysK.length) f.sections.push({ id: 'sys', title: 'Record details', cols: 2, fields: sysK.slice() });
      if (f.detail && f.summaryDefault) f.summary = f.summaryDefault.slice();
      if (f.related && f.relatedDefault) { const by: Record<string, any> = {}; f.related.forEach((r: any) => { by[r.id] = r; r.show = true; }); f.related = f.relatedDefault.map((id: string) => by[id]).filter(Boolean); }
      if (!f.layout) f.sections[0].fields = Object.keys(f.fields).filter((k) => !f.fields[k].custom);
      f.style = 'top'; f.lines = { disc: true, tax: true }; f.resetFlag = true;
    }, { keepReset: true });
    setSel(null);
  };

  const groups = useMemo(() => {
    const g: Record<string, { i: number; t: string }[]> = {};
    data.forEach((f: Form, i: number) => { (g[f.group] ||= []).push({ i, t: f.title }); });
    return g;
  }, [data.length]); // eslint-disable-line react-hooks/exhaustive-deps

  const tile = (k: string, s: any) => {
    const y = fld(k); if (!y) return null;
    const pinned = F.detail && sum.includes(k);
    const m = mark && mark.k === 'tile' && mark.key === k ? (mark.after ? ' drop-after' : ' drop-before') : '';
    return (
      <div key={k} style={{ ['--span' as any]: spanOf(y, s.cols) }} data-key={k} tabIndex={0}
        className={`dz-tile${k === sel ? ' sel' : ''}${y.req ? ' req' : ''}${y.sys ? ' sys' : ''}${F.layout ? '' : ' fixed'}${m}`}
        draggable={F.layout && !preview}
        onDragStart={(e) => { if (preview || !F.layout) { e.preventDefault(); return; } drag.current = { kind: 'tile', key: k }; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', k); } catch { /* ignore */ } }}
        onDragEnd={() => { drag.current = null; setMark(null); }}
        onClick={() => { if (preview) { setSel(k); setPreview(false); } else setSel(k); }}>
        <i className="bi bi-grip-vertical dz-grip" /><span className="dz-ico"><i className={`bi bi-${y.kind}`} /></span>
        <span className="dz-name">{labelOf(y)}{y.req && <b className="dz-star"> *</b>}{y.custom && <em className="dz-cf">custom</em>}{y.sys && <em className="dz-cf dz-sysb">detail page only</em>}{pinned && <i className="bi bi-pin-angle-fill dz-pin" title="Shown in the summary strip" />}</span>
        <button type="button" className="dz-more" data-pop title="More" onClick={(e) => {
          e.stopPropagation();
          openPop(e, [
            { p: 'edit', label: 'Edit properties', icon: 'pencil' },
            ...(y.optional && y.type !== 'checkbox' ? [{ p: 'req', label: y.req ? 'Make optional' : 'Make mandatory', icon: 'asterisk' }] : []),
            ...(F.detail ? [{ p: 'sum', label: sum.includes(k) ? 'Remove from summary strip' : 'Show in summary strip', icon: 'pin-angle' }] : []),
            { p: 'up', label: 'Move earlier', icon: 'arrow-up' }, { p: 'down', label: 'Move later', icon: 'arrow-down' },
            ...(y.optional ? [{ p: 'hide', label: 'Move to unused', icon: 'eye-slash' }] : []),
            ...(y.custom ? [{ p: 'del', label: 'Delete field', icon: 'trash', danger: true }] : []),
          ], (p) => {
            if (p === 'edit') setSel(k);
            if (p === 'sum') toggleSummary(k);
            if (p === 'req') edit((_, f) => { f.fields[k].req = !f.fields[k].req; });
            if (p === 'up') moveField(k, -1);
            if (p === 'down') moveField(k, 1);
            if (p === 'hide') { edit((_, f) => hide(f, k)); if (sel === k) setSel(null); }
            if (p === 'del') deleteField(k);
          });
        }}><i className="bi bi-three-dots" /></button>
        <span className="dz-faux">{y.help ? <small>{y.help}</small> : null}</span>
      </div>
    );
  };

  const setProp = (fn: (y: Fld) => void, tag?: string) => { if (!sel) return; edit((_, f) => fn(f.fields[sel]), tag ? { tag: sel + ':' + tag } : {}); };

  return (
    <div className="dz" onDragEnd={() => { drag.current = null; setMark(null); }}>
      <header className="dz-top">
        <Link className="dz-back" to={back} title="Back" onClick={leave}><i className="bi bi-arrow-left" /></Link>
        <span className="dz-title">Edit page layout</span><span className="dz-sep" />
        <select className="dz-select" aria-label="Form" value={cur} onChange={(e) => { setCur(Number(e.target.value)); setSel(null); }}>
          {Object.entries(groups).map(([g, list]) => <optgroup key={g} label={g}>{list.map((o) => <option key={o.i} value={o.i}>{o.t}</option>)}</optgroup>)}
        </select>
        <div className="dz-seg" title="Where the labels go on the form">
          <button type="button" className={F.style === 'top' ? 'on' : ''} onClick={() => edit((_, f) => { f.style = 'top'; })}>Labels on top</button>
          <button type="button" className={F.style === 'left' ? 'on' : ''} onClick={() => edit((_, f) => { f.style = 'left'; })}>Labels on left</button>
        </div>
        <span className="dz-grow" />
        {dirty && <span className="dz-dirty"><i className="bi bi-circle-fill" /> Unsaved</span>}
        <button type="button" className={`dz-btn${preview ? ' on' : ''}`} title="Preview how the form will look" onClick={() => setPreview((p) => !p)}><i className="bi bi-eye" /><span>Preview</span></button>
        <button type="button" className="dz-btn" disabled={!canUndo} onClick={undo}><i className="bi bi-arrow-counterclockwise" /><span>Undo</span></button>
        <button type="button" className="dz-btn" onClick={resetForm}><i className="bi bi-eraser" /><span>Reset form</span></button>
        <Link className="dz-btn light" to={back} onClick={leave}>Cancel</Link>
        <button type="button" className="dz-btn primary" disabled={saving} onClick={() => save(false)}><i className="bi bi-check2" /><span>{saving ? 'Saving…' : 'Save'}</span></button>
        <button type="button" className="dz-btn primary soft" disabled={saving} onClick={() => save(true)}>Save and close</button>
      </header>

      <div className="dz-main">
        <aside className="dz-left">
          <div className="dz-box-title">New fields {!F.customFields && <small>not available on this form</small>}</div>
          <div className={`dz-types${F.customFields ? '' : ' off'}`}>
            {Object.entries(types).map(([k, l]) => (
              <div key={k} className="dz-type" draggable title="Drag onto the form"
                onDragStart={(e) => { if (!F.customFields) { e.preventDefault(); return; } drag.current = { kind: 'new', type: k }; e.dataTransfer.effectAllowed = 'copy'; try { e.dataTransfer.setData('text/plain', k); } catch { /* ignore */ } }}>
                <i className={`bi bi-${ICONS[k] ?? 'input-cursor-text'}`} /><span>{l}</span></div>
            ))}
          </div>
          <button type="button" className="dz-wide" onClick={() => { edit((_, f) => { f.sections.push({ id: 'n' + (++newSeq.current), title: 'New section', cols: 2, fields: [] }); }); setTimeout(() => { const i = document.querySelectorAll<HTMLInputElement>('.dz-sec-title'); const last = i[i.length - 1]; last?.focus(); last?.select(); }, 30); }}><i className="bi bi-layout-three-columns" /> NEW SECTION</button>
          <div className="dz-box-title mt-3">Unused fields <span className="dz-count">{unused.length}</span></div>
          <div className={`dz-unused${mark?.k === 'unused' ? ' drop-into' : ''}`}
            onDragOver={(e) => { const d = drag.current; if (d && d.kind === 'tile' && fld(d.key).optional) { e.preventDefault(); setMark({ k: 'unused' }); } }}
            onDragLeave={() => setMark(null)}
            onDrop={(e) => { const d = drag.current; setMark(null); if (d && d.kind === 'tile' && fld(d.key).optional) { e.preventDefault(); drag.current = null; edit((_, f) => hide(f, d.key)); if (sel === d.key) setSel(null); } }}>
            {unused.length ? unused.map((k) => { const y = F.fields[k]; return (
              <div key={k} className="dz-un" draggable onDragStart={(e) => { drag.current = { kind: 'unused', key: k }; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', k); } catch { /* ignore */ } }}>
                <i className={`bi bi-${y.kind}`} /><span>{labelOf(y)}</span>
                <button type="button" title="Put back on the form" onClick={() => { edit((_, f) => { f.fields[k].show = true; insertAt(f, f.sections.length - 1, null, false, k); }); setSel(k); }}><i className="bi bi-plus-lg" /></button>
              </div>); }) : <div className="dz-un-empty">Nothing here. Fields you remove from the form wait here.</div>}
          </div>
          <div className="dz-help">Drag a field type onto the form to create a new field. Drag a field from <b>Unused</b> back to bring it on to the form.</div>
        </aside>

        <main className="dz-center">
          <Messages items={msgs} />
          <div className={`dz-paper${preview ? ' preview' : ''}${F.style === 'left' ? ' left' : ''}`}>
            <div className="dz-paper-head"><h2>{F.title.replace(/ form$/, '')} — {preview ? 'preview' : 'layout'}</h2><span className="dz-mock-buttons"><span>Cancel</span><span className="p">Save</span></span></div>

            {F.detail && (
              <div className={`dz-sumbar${mark?.k === 'sum' ? ' drop-into' : ''}`}
                onDragOver={(e) => { if (drag.current?.kind === 'tile') { e.preventDefault(); setMark({ k: 'sum' }); } }} onDragLeave={() => setMark(null)}
                onDrop={(e) => { setMark(null); const d = drag.current; if (!d || d.kind !== 'tile') return; e.preventDefault(); drag.current = null; if (!sum.includes(d.key)) toggleSummary(d.key); }}>
                <div className="dz-sum-head"><b>Summary strip</b><span>Key fields shown at the top of the detail page · up to 4 · not on the edit form</span>
                  {!preview && sum.length < 4 && <button type="button" data-pop onClick={(e) => {
                    const opts: string[] = []; F.sections.forEach((s: any) => s.fields.forEach((k: string) => { if (fld(k) && !sum.includes(k)) opts.push(k); }));
                    if (!opts.length) return;
                    openPop(e, opts.map((k) => ({ p: k, label: labelOf(fld(k)), icon: fld(k).kind })), (k) => toggleSummary(k));
                  }}><i className="bi bi-plus-lg" /> Add field</button>}
                </div>
                <div className="dz-sum-row">
                  {sum.map((k) => { const y = fld(k); if (!y) return null; return (
                    <div key={k} className="dz-sum-chip"><span>{labelOf(y)}</span><b>—</b>{!preview && <button type="button" title="Remove from the strip" onClick={() => toggleSummary(k)}><i className="bi bi-x" /></button>}</div>); })}
                  {!sum.length && <em className="text-muted small">Empty — the page opens straight on the sections. Drag a field here or use Add field.</em>}
                </div>
              </div>
            )}

            <div>
              {F.sections.map((s: any, si: number) => (
                <section key={si} className="dz-sec"
                  onDragOver={(e) => { if (!drag.current) return; e.preventDefault(); const t = (e.target as HTMLElement).closest('.dz-tile') as HTMLElement | null;
                    if (t && !(drag.current.kind === 'tile' && t.dataset.key === drag.current.key)) { const r = t.getBoundingClientRect(); setMark({ k: 'tile', key: t.dataset.key!, after: e.clientX - r.left > r.width / 2 }); }
                    else if (!t) setMark({ k: 'grid', si }); }}
                  onDrop={(e) => { if (!drag.current) return; e.preventDefault(); const t = (e.target as HTMLElement).closest('.dz-tile') as HTMLElement | null;
                    if (t) { const r = t.getBoundingClientRect(); drop(si, t.dataset.key!, e.clientX - r.left > r.width / 2); } else drop(si, null, false); }}>
                  <div className="dz-sec-head">
                    {preview ? <div className="dz-sec-name">{s.title}</div> :
                      <input className="dz-sec-title" value={s.title} maxLength={60} placeholder={si === 0 ? F.title.replace(/ form$/, '').replace(/\s*\(.*?\)/g, '') + ' information' : 'More details'}
                        onChange={(e) => edit((_, f) => { f.sections[si].title = e.target.value; }, { tag: 'sec' + si })} />}
                    <div className="dz-sec-tools">
                      <button type="button" data-pop title="Columns in this section" onClick={(e) => openPop(e, [[0, 'Original widths'], [1, '1 column'], [2, '2 columns'], [3, '3 columns']].map(([n, l]) => ({ p: String(n), label: String(l), icon: 'layout-three-columns', on: s.cols === n })), (p) => edit((_, f) => { f.sections[si].cols = Number(p); }))}><i className="bi bi-layout-three-columns" /> {s.cols ? s.cols + ' col' : 'Original'}</button>
                      <button type="button" title="Move section up" onClick={() => si > 0 && edit((_, f) => { [f.sections[si - 1], f.sections[si]] = [f.sections[si], f.sections[si - 1]]; })}><i className="bi bi-arrow-up" /></button>
                      <button type="button" title="Move section down" onClick={() => si < F.sections.length - 1 && edit((_, f) => { [f.sections[si + 1], f.sections[si]] = [f.sections[si], f.sections[si + 1]]; })}><i className="bi bi-arrow-down" /></button>
                      <button type="button" title="Delete section" onClick={() => {
                        if (F.sections.length === 1) { alert('A form needs at least one section.'); return; }
                        edit((_, f) => { const dst = f.sections[si === 0 ? 1 : si - 1]; f.sections[si].fields.forEach((k: string) => dst.fields.push(k)); f.sections.splice(si, 1); });
                      }}><i className="bi bi-trash" /></button>
                    </div>
                  </div>
                  <div className={`dz-grid${mark?.k === 'grid' && mark.si === si ? ' drop-into' : ''}`}>
                    {s.fields.map((k: string) => tile(k, s))}
                    {!s.fields.length && <div className="dz-drop-empty">Drop fields here</div>}
                  </div>
                </section>
              ))}
            </div>

            {F.lineForm && (
              <div className="dz-lines">
                <div className="dz-sec-head"><div className="dz-sec-name">Line items</div><div className="dz-sec-tools"><span className="dz-hint">Choose the columns of the items table</span></div></div>
                <div className="dz-table">
                  {['Item', 'Qty', 'Price', 'Disc %', 'Tax %', 'Total'].map((c) => {
                    const k = c === 'Disc %' ? 'disc' : c === 'Tax %' ? 'tax' : null; const on = !k || F.lines[k];
                    return <div key={c} className={`dz-col${on ? '' : ' off'}${k ? ' tog' : ''}`} onClick={() => k && edit((_, f) => { f.lines[k] = !f.lines[k]; })}>{c}{k && <i className={`bi bi-${on ? 'eye' : 'eye-slash'}`} />}</div>;
                  })}
                </div>
              </div>
            )}

            {F.detail && rel.length > 0 && (
              <div className="dz-lines dz-rel">
                <div className="dz-sec-head"><div className="dz-sec-name">Related lists</div><div className="dz-sec-tools"><span className="dz-hint">Cards shown below the sections on the detail page: change their order or hide them</span></div></div>
                {rel.map((r, i) => (
                  <div key={r.id} className={`dz-relrow${r.show ? '' : ' off'}`}><i className="bi bi-list-ul" /><span>{r.label}</span>
                    {!preview && <>
                      <button type="button" title="Move up" disabled={i === 0} onClick={() => edit((_, f) => { [f.related[i - 1], f.related[i]] = [f.related[i], f.related[i - 1]]; })}><i className="bi bi-arrow-up" /></button>
                      <button type="button" title="Move down" disabled={i === rel.length - 1} onClick={() => edit((_, f) => { [f.related[i + 1], f.related[i]] = [f.related[i], f.related[i + 1]]; })}><i className="bi bi-arrow-down" /></button>
                      {r.lock ? <span className="dz-lock" title="Needed to work with this record"><i className="bi bi-lock" /></span>
                        : <button type="button" title={r.show ? 'Hide' : 'Show'} onClick={() => edit((_, f) => { f.related[i].show = !f.related[i].show; })}><i className={`bi bi-${r.show ? 'eye' : 'eye-slash'}`} /></button>}
                    </>}
                  </div>))}
              </div>
            )}
          </div>
        </main>

        {x && (
          <aside className="dz-right">
            <div className="dz-right-head"><div className="d-flex align-items-center gap-2"><i className={`bi bi-${x.kind}`} /><div><div className="fw-semibold">{labelOf(x)}</div>
              <div className="small opacity-75">{x.sys ? 'Record detail · detail page only' : x.custom ? 'Custom field · ' + (types[x.type] || x.type) : (x.optional ? 'Standard field (optional)' : 'Standard field (essential)')}</div></div></div>
              <button type="button" className="dz-x" aria-label="Close" onClick={() => setSel(null)}><i className="bi bi-x-lg" /></button></div>
            <div className="dz-right-body">
              <label className="dz-lbl" htmlFor="pLabel">Field label</label>
              <input id="pLabel" className="dz-in" maxLength={80} placeholder={x.custom ? 'Field name' : x.label} value={x.custom ? x.label : x.custom_label}
                onChange={(e) => setProp((y) => { if (y.custom) y.label = e.target.value; else y.custom_label = e.target.value === y.label ? '' : e.target.value; }, 'label')} />
              {x.custom && <div><label className="dz-lbl" htmlFor="pType">Field type</label>
                <select id="pType" className="dz-in" value={x.type || 'text'} disabled={!x.isNew} onChange={(e) => setProp((y) => { y.type = e.target.value; y.kind = ICONS[e.target.value] || y.kind; if (CHOICE[y.type] && !y.options) y.options = 'Option 1\nOption 2'; if (y.type === 'lookup' && !lookups[y.options || '']) y.options = 'item'; if (y.type === 'checkbox') y.req = false; })}>
                  {Object.entries(types).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
                <div className="dz-note">{x.isNew ? '' : 'The type of a saved field cannot be changed. Delete it and add a new one if needed.'}</div></div>}
              {x.custom && x.type === 'lookup' && <div><label className="dz-lbl" htmlFor="pSource">Pick from</label>
                <select id="pSource" className="dz-in" value={x.options || 'item'} disabled={!x.isNew} onChange={(e) => setProp((y) => { y.options = e.target.value; }, 'options')}>
                  {Object.entries(lookups).map(([k, l]) => <option key={k} value={k}>{l}</option>)}</select>
                <div className="dz-note">The person filling the form searches these records and picks one. {x.isNew ? '' : 'It cannot be changed after saving.'}</div></div>}
              {x.custom && CHOICE[x.type] && <div><label className="dz-lbl" htmlFor="pOptions">Choices <span className="opacity-75">(one per line)</span></label>
                <textarea id="pOptions" className="dz-in" rows={5} value={x.options || ''} onChange={(e) => setProp((y) => { y.options = e.target.value; }, 'options')} /></div>}
              {!x.sys && <>
                <label className="dz-lbl" htmlFor="pHelp">Hint under the field</label>
                <input id="pHelp" className="dz-in" maxLength={140} placeholder="Optional help text" value={x.help || ''} onChange={(e) => setProp((y) => { y.help = e.target.value; }, 'help')} />
                <div className="dz-lbl">Width</div>
                <div className="dz-seg w">{Object.entries(widths).map(([k]) => <button type="button" key={k} className={(x.w || '') === k ? 'on' : ''} onClick={() => setProp((y) => { y.w = k; })}>{k === '' ? 'Auto' : k === '100' ? 'Full' : k + '%'}</button>)}</div>
              </>}
              {x.optional && x.type !== 'checkbox' && <div className="dz-row"><span>Mandatory field</span><label className="dz-sw"><input type="checkbox" checked={!!x.req} onChange={(e) => setProp((y) => { y.req = e.target.checked; })} /><i /></label></div>}
              {x.custom && UNIQ[x.type] && <div className="dz-row"><span>Unique (no duplicate values)</span><label className="dz-sw"><input type="checkbox" checked={!!x.unique} onChange={(e) => setProp((y) => { y.unique = e.target.checked; })} /><i /></label></div>}
              {x.custom && <div className="dz-row"><span>Show as a column in the list</span><label className="dz-sw"><input type="checkbox" checked={!!x.inList} onChange={(e) => setProp((y) => { y.inList = e.target.checked; })} /><i /></label></div>}
              {!x.optional && <div className="dz-note"><i className="bi bi-lock me-1" />This is an essential field. It is always on the form and always filled in.</div>}
              <div className="d-flex gap-2 mt-3"><button type="button" className="dz-btn" onClick={() => moveField(sel!, -1)}><i className="bi bi-arrow-up" /> Earlier</button><button type="button" className="dz-btn" onClick={() => moveField(sel!, 1)}><i className="bi bi-arrow-down" /> Later</button></div>
              {x.optional && <button type="button" className="dz-btn danger mt-3" onClick={() => { const k = sel!; edit((_, f) => hide(f, k)); setSel(null); }}><i className="bi bi-eye-slash" /> Move to unused</button>}
              {x.custom && <button type="button" className="dz-btn danger mt-2" onClick={() => deleteField(sel!)}><i className="bi bi-trash" /> Delete this field</button>}
            </div>
          </aside>
        )}
      </div>

      {pop && (
        <div className="dz-pop" style={{ top: pop.y + window.scrollY, left: pop.x }}>
          {pop.items.map((it) => <button key={it.p} className={`${it.danger ? 'danger' : ''}${it.on ? ' on' : ''}`} onClick={() => { const f = pop.pick; setPop(null); f(it.p); }}><i className={`bi bi-${it.icon}`} /> {it.label}</button>)}
        </div>
      )}
    </div>
  );
}
