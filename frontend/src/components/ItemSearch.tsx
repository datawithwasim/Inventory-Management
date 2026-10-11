import { useEffect, useRef, useState } from 'react';
import { get } from '../lib/api';

export type Picked = { id: number; label: string; row: any };

/** Search box for our items (name, SKU, barcode). Calls onPick with the variant; typing again clears the choice. */
export default function ItemSearch({ value, label, onPick, supplier, placeholder = 'Search your items by name, SKU or barcode…', disabled = false, path = '/lookup/items', extra = '' }: {
  value: string; label: string; onPick: (p: Picked | null) => void; supplier?: string | number; placeholder?: string; disabled?: boolean; path?: string; extra?: string;
}) {
  const [text, setText] = useState(label); const [rows, setRows] = useState<any[]>([]); const [open, setOpen] = useState(false);
  const box = useRef<HTMLDivElement>(null); const timer = useRef<number | undefined>(undefined);
  useEffect(() => { setText(label); }, [label]);
  useEffect(() => {
    const away = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', away); return () => document.removeEventListener('mousedown', away);
  }, []);
  const search = (q: string) => {
    window.clearTimeout(timer.current);
    if (!q.trim()) { setRows([]); setOpen(false); return; }
    timer.current = window.setTimeout(async () => {
      const r: any = await get(`${path}?q=${encodeURIComponent(q)}${supplier ? `&supplier=${supplier}` : ''}${extra}`);
      setRows(Array.isArray(r) ? r : (r.data ?? [])); setOpen(true);
    }, 180);
  };
  return (
    <div className="position-relative" ref={box}>
      <input className="form-control" autoComplete="off" disabled={disabled} placeholder={placeholder} value={text}
        onChange={(e) => { setText(e.target.value); if (value) onPick(null); search(e.target.value); }} onFocus={() => rows.length && setOpen(true)} />
      {open && (
        <div className="ip-list" style={{ position: 'absolute', left: 0, right: 0, zIndex: 20 }}>
          {!rows.length && <div className="ip-msg">No items found — check the spelling or try the SKU.</div>}
          {rows.map((v) => {
            const ours = v.item_name + (v.name ? ' — ' + v.name : '');
            return (
              <div key={v.id} className="ip-row" onMouseDown={(e) => { e.preventDefault(); const l = `${ours} (${v.sku})`; setText(l); setOpen(false); onPick({ id: v.id, label: l, row: v }); }}>
                <div className="ip-main"><div className="ip-title">{ours}</div><div className="ip-sub">{v.sku}</div></div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
