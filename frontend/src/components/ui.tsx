import { useEffect, useRef, useState, type ReactNode } from 'react';
import type { Msg } from '../lib/api';

/** Flash messages from the server (success / danger / warning). */
export function Messages({ items, onClose }: { items: Msg[]; onClose?: () => void }) {
  if (!items.length) return null;
  return <>{items.map((m, i) => (
    <div key={i} className={`alert alert-${m.type === 'error' ? 'danger' : m.type} d-flex justify-content-between align-items-start py-2`} role="alert">
      <span>{m.msg}</span>{onClose && <button type="button" className="btn-close" aria-label="Close" onClick={onClose} />}
    </div>
  ))}</>;
}

/** A dropdown that closes on outside click, built without Bootstrap's JS so React stays in charge of the DOM. */
export function Dropdown({ button, children, align = 'end', className = '', menuClass = '' }: { button: (open: boolean) => ReactNode; children: (close: () => void) => ReactNode; align?: 'start' | 'end'; className?: string; menuClass?: string }) {
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState<{ top: number; right: number; left: number }>({ top: 0, right: 0, left: 0 });
  const ref = useRef<HTMLDivElement>(null);
  const toggle = () => {
    if (!open && ref.current) { const r = ref.current.getBoundingClientRect(); setPos({ top: r.bottom + 6, right: window.innerWidth - r.right, left: r.left }); }
    setOpen((o) => !o);
  };
  useEffect(() => {
    if (!open) return;
    const down = (e: MouseEvent) => { if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false); };
    const key = (e: KeyboardEvent) => { if (e.key === 'Escape') setOpen(false); };
    const away = () => setOpen(false);
    document.addEventListener('mousedown', down); document.addEventListener('keydown', key); window.addEventListener('resize', away); window.addEventListener('scroll', away, true);
    return () => { document.removeEventListener('mousedown', down); document.removeEventListener('keydown', key); window.removeEventListener('resize', away); window.removeEventListener('scroll', away, true); };
  }, [open]);
  return (
    <div className={`dropdown ${className}`} ref={ref}>
      <span onClick={toggle}>{button(open)}</span>
      {open && <div className={`dropdown-menu show shadow ${align === 'end' ? 'dropdown-menu-end' : ''} ${menuClass}`} style={align === 'end' ? { position: 'fixed', top: pos.top, right: pos.right, left: 'auto', margin: 0 } : { position: 'fixed', top: pos.top, left: pos.left, margin: 0 }}>{children(() => setOpen(false))}</div>}
    </div>
  );
}

export const Spinner = () => <div className="text-center py-5"><div className="spinner-border text-primary" role="status" aria-label="Loading" /></div>;

export function PageHead({ title, children, extra }: { title: ReactNode; children?: ReactNode; extra?: ReactNode }) {
  return <div className="page-head"><div><h1>{title}{extra}</h1></div><div className="page-actions">{children}</div></div>;
}

/** Badge colours the old pages used for statuses. */
export const Badge = ({ tone = 'secondary', children }: { tone?: string; children: ReactNode }) => <span className={`badge text-bg-${tone}`}>{children}</span>;
