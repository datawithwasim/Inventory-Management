import { useEffect, useRef, useState } from 'react';
import { get } from '../lib/api';

export type Picked = { id: number; label: string; row: any };

/** Search box for our items (name, SKU, barcode). Calls onPick with the variant; typing again clears the choice. */
export default function ItemSearch({ value, label, onPick, supplier, placeholder = 'Search your items by name, SKU or barcode…', disabled = false, path = '/lookup/items', extra = '' }: {
  value: string; label: string; onPick: (p: Picked | null) => void; supplier?: string | number; placeholder?: string; disabled?: boolean; path?: string; extra?: string;
}) {
  const [text, setText] = useState(label); const [rows, setRows] = useState<any[]>([]); const [open, setOpen] = useState(false); const [active, setActive] = useState(0);
  const box = useRef<HTMLDivElement>(null); const list = useRef<HTMLDivElement>(null); const timer = useRef<number | undefined>(undefined);
  useEffect(() => { setText(label); }, [label]);
  useEffect(() => {
    const away = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', away); return () => document.removeEventListener('mousedown', away);
  }, []);
  const search = (q: string) => {
    window.clearTimeout(timer.current);
    // the purchase item list can also answer an empty box (the supplier's own items first); the other lists need something typed
    if (!q.trim() && path !== '/lookup/items') { setRows([]); setOpen(false); return; }
    timer.current = window.setTimeout(async () => {
      const r: any = await get(`${path}?q=${encodeURIComponent(q)}${supplier ? `&supplier=${supplier}` : ''}${extra}`);
      setRows(Array.isArray(r) ? r : (r.data ?? [])); setActive(0); setOpen(true);
    }, 180);
  };
  const choose = (v: any) => {
    const ours = v.product_name ? v.product_name : v.item_name + (v.name ? ' — ' + v.name : '');
    const l = v.product_name ? `${v.product_name} · ${v.item_name}` : `${ours} (${v.sku})`; setText(l); setOpen(false); onPick({ id: v.id, label: l, row: v });
  };
  // arrow keys move through the list, Enter picks the highlighted row, Escape closes it
  const keys = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!open) { search(text); return; }
      if (!rows.length) return;
      const n = (active + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length; setActive(n);
      list.current?.children[n]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && open && rows.length) { e.preventDefault(); choose(rows[active] ?? rows[0]); }
    else if (e.key === 'Escape' && open) { e.preventDefault(); setOpen(false); }
    else if (e.key === 'Tab') setOpen(false);
  };
  return (
    <div className="position-relative" ref={box}>
      <input className="form-control" autoComplete="off" disabled={disabled} placeholder={placeholder} value={text} role="combobox" aria-expanded={open} aria-autocomplete="list"
        onChange={(e) => { setText(e.target.value); if (value) onPick(null); search(e.target.value); }} onKeyDown={keys} onFocus={() => (rows.length ? setOpen(true) : !value && search(text))} />
      {open && (
        <div className="ip-list" ref={list} style={{ position: 'absolute', left: 0, right: 0, zIndex: 20 }}>
          {!rows.length && <div className="ip-msg">No items found — check the spelling or try the SKU.</div>}
          {rows.map((v, k) => {
            const ours = v.product_name ? v.product_name : v.item_name + (v.name ? ' — ' + v.name : '');
            const their = [v.sup_code, v.sup_name].filter(Boolean).join(' · ');
            const sub = [v.product_name ? [v.catalogue, v.item_name, v.sku].filter(Boolean).join(' · ') : v.sku, their ? `Their: ${their}` : '', v.available != null ? `stock ${v.available}` : ''].filter(Boolean).join(' · ');
            return (
              <div key={v.id} className={`ip-row${k === active ? ' active' : ''}`} style={k === active ? { background: 'var(--bs-primary-bg-subtle, #eef2ff)' } : undefined} onMouseEnter={() => setActive(k)} onMouseDown={(e) => { e.preventDefault(); choose(v); }}>
                <div className="ip-main"><div className="ip-title">{ours}</div><div className="ip-sub">{sub}</div></div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
