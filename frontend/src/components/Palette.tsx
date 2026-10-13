import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { get } from '../lib/api';
import { localPath } from '../lib/routes';
import { useSession } from '../lib/session';

type Row = { head?: string; l: string; s?: string; i: string; to: string };

/** Search box (Ctrl K): pages and quick-create links straight away, records from the server after two letters. */
export default function Palette({ open, onClose }: { open: boolean; onClose: () => void }) {
  const { session } = useSession(); const nav = useNavigate();
  const [q, setQ] = useState(''); const [found, setFound] = useState<Row[]>([]); const [sel, setSel] = useState(0);
  const input = useRef<HTMLInputElement>(null); const timer = useRef<number | undefined>(undefined);
  const pages = useMemo<Row[]>(() => {
    const m = session?.menu; if (!m) return [];
    const out: Row[] = [{ head: 'Page', l: m.dashboard.label, i: m.dashboard.icon, to: m.dashboard.href ?? m.dashboard.path }];
    Object.values(m.groups).forEach((g) => g.items.forEach((it) => out.push({ head: 'Go to', l: it.label, s: g.label, i: it.icon, to: it.href ?? it.path })));
    m.account.forEach((it) => out.push({ head: 'Go to', l: it.label, s: 'Administration', i: it.icon, to: it.href ?? it.path }));
    (session?.quick ?? []).forEach((it) => out.push({ head: 'Create', l: it.label, i: it.icon, to: it.href ?? it.path }));
    return out;
  }, [session]);
  useEffect(() => { if (open) { setQ(''); setFound([]); setSel(0); setTimeout(() => input.current?.focus(), 30); } }, [open]);
  useEffect(() => {
    window.clearTimeout(timer.current);
    if (q.trim().length < 2) { setFound([]); return; }
    timer.current = window.setTimeout(async () => {
      const r: any = await get(`/search?q=${encodeURIComponent(q.trim())}`);
      const out: Row[] = [];
      (r.groups ?? []).forEach((g: any) => g.rows.forEach((x: any, k: number) => out.push({ head: k === 0 ? g.title : undefined, l: x.l, s: x.s, i: x.i, to: localPath(x.u) })));
      setFound(out);
    }, 180);
  }, [q]);
  const needle = q.trim().toLowerCase();
  const matched = needle ? pages.filter((p) => p.l.toLowerCase().includes(needle)).slice(0, 6) : pages.slice(0, 8);
  const rows: Row[] = [...found, ...matched.map((p, k) => ({ ...p, head: k === 0 || matched[k - 1].head !== p.head ? p.head : undefined }))];
  useEffect(() => { setSel(0); }, [q, found.length]);
  if (!open) return null;
  const go = (r: Row) => { onClose(); nav(r.to); };
  const key = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') onClose();
    else if (e.key === 'ArrowDown') { e.preventDefault(); setSel((s) => Math.min(s + 1, rows.length - 1)); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); setSel((s) => Math.max(s - 1, 0)); }
    else if (e.key === 'Enter' && rows[sel]) { e.preventDefault(); go(rows[sel]); }
  };
  return (
    <div className="palette" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="palette-box" role="dialog" aria-label="Search">
        <div className="palette-input"><i className="bi bi-search" /><input ref={input} type="search" value={q} onChange={(e) => setQ(e.target.value)} onKeyDown={key} placeholder="Search items, customers, documents — or type a page name" autoComplete="off" /><kbd>Esc</kbd></div>
        <div className="palette-list">
          {rows.map((r, k) => (
            <div key={k}>
              {r.head && <div className="pal-head">{r.head}</div>}
              <div className={`pal-item ${k === sel ? 'sel' : ''}`} onMouseEnter={() => setSel(k)} onClick={() => go(r)}><i className={`bi bi-${r.i}`} /><span>{r.l}</span>{r.s && <span className="sub">{r.s}</span>}</div>
            </div>
          ))}
          {!rows.length && <div className="pal-empty">Nothing found{needle.length < 2 ? '' : ` for “${q}”`}.</div>}
        </div>
        <div className="palette-foot"><span><kbd>↑</kbd><kbd>↓</kbd> move</span><span><kbd>Enter</kbd> open</span></div>
      </div>
    </div>
  );
}
