import { type ReactNode } from 'react';

/**
 * The filter row above a DocList: put named inputs inside, tick/pick/type, press the button (selects and checkboxes apply at once).
 * `names` are the address-bar parameters it controls; empty ones are dropped from the address.
 */
export default function Filters({ names, sp, set, children, right }: { names: string[]; sp: URLSearchParams; set: (p: Record<string, string>) => void; children: ReactNode; right?: ReactNode }) {
  const apply = (form: HTMLFormElement) => {
    const f = new FormData(form); const patch: Record<string, string> = {};
    names.forEach((n) => { patch[n] = String(f.get(n) ?? ''); });
    set(patch);
  };
  return (
    <form className="row g-2 mb-3" key={sp.toString()} onSubmit={(e) => { e.preventDefault(); apply(e.currentTarget); }}
      onChange={(e) => { const t = e.target as HTMLElement; if (t.tagName === 'SELECT' || (t as HTMLInputElement).type === 'checkbox' || (t as HTMLInputElement).type === 'date') apply(e.currentTarget as HTMLFormElement); }}>
      {children}
      <div className="col-auto"><button className="btn btn-outline-secondary">Filter</button></div>
      {right && <div className="col text-end">{right}</div>}
    </form>
  );
}
