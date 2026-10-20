import { useEffect, useRef, useState } from 'react';
import { get } from '../lib/api';

type Row = { id: number | string; label: string; sub?: string };

/**
 * A "Lookup" field: search a kind of record (supplier, customer, rack, product …) by what you type and pick one.
 * `value` is the id; its name is fetched when the form opens on a saved value. Typing again clears the choice.
 */
export default function LookupInput({ source, value, onChange, disabled = false, required = false, placeholder }: {
  source: string; value: string; onChange: (id: string, label: string) => void; disabled?: boolean; required?: boolean; placeholder?: string;
}) {
  const [text, setText] = useState(''); const [rows, setRows] = useState<Row[]>([]); const [open, setOpen] = useState(false); const [empty, setEmpty] = useState(false);
  const box = useRef<HTMLDivElement>(null); const timer = useRef<number | undefined>(undefined); const shown = useRef('');
  // the name of the saved / chosen record
  useEffect(() => {
    if (!value) { if (shown.current) { shown.current = ''; setText(''); } return; }
    let live = true;
    get(`/lookup/find/${source}?id=${encodeURIComponent(value)}`).then((r: any) => { const row: Row | undefined = r?.rows?.[0]; if (live && row && row.label !== shown.current) { shown.current = row.label; setText(row.label); } });
    return () => { live = false; };
  }, [value, source]);
  useEffect(() => {
    const away = (e: MouseEvent) => { if (box.current && !box.current.contains(e.target as Node)) setOpen(false); };
    document.addEventListener('mousedown', away); return () => document.removeEventListener('mousedown', away);
  }, []);
  const search = (q: string) => {
    window.clearTimeout(timer.current);
    timer.current = window.setTimeout(async () => {
      const r: any = await get(`/lookup/find/${source}?q=${encodeURIComponent(q)}`);
      setRows(r?.rows ?? []); setEmpty(!(r?.rows ?? []).length); setOpen(true);
    }, 150);
  };
  return (
    <div className="position-relative" ref={box}>
      <input className="form-control" autoComplete="off" disabled={disabled} required={required && !value} placeholder={placeholder ?? 'Type to search…'} value={text}
        onChange={(e) => { setText(e.target.value); shown.current = ''; if (value) onChange('', ''); search(e.target.value); }} onFocus={() => search(value ? '' : text)} />
      {open && (
        <div className="ip-list" style={{ position: 'absolute', left: 0, right: 0, zIndex: 20 }}>
          {empty && <div className="ip-msg">Nothing found — check the spelling.</div>}
          {rows.map((r) => (
            <div key={r.id} className="ip-row" onMouseDown={(e) => { e.preventDefault(); shown.current = r.label; setText(r.label); setOpen(false); onChange(String(r.id), r.label); }}>
              <div className="ip-main"><div className="ip-title">{r.label}</div>{r.sub ? <div className="ip-sub">{r.sub}</div> : null}</div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
