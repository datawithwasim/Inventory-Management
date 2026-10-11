import { type ReactNode } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get } from '../lib/api';
import { PageHead, Spinner } from './ui';

export type Col<R = any> = { head: ReactNode; cell: (r: R, d: any) => ReactNode; className?: string };

/** Pager like the PHP one: previous / "n / pages" / next. */
export function Pager({ page, pages, onPage }: { page: number; pages: number; onPage: (p: number) => void }) {
  if (pages <= 1) return null;
  return (
    <nav aria-label="Pages"><ul className="pagination pagination-sm mb-0 mt-3">
      <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}><button className="page-link" onClick={() => onPage(page - 1)}>‹</button></li>
      <li className="page-item disabled"><span className="page-link">{page} / {pages}</span></li>
      <li className={`page-item ${page >= pages ? 'disabled' : ''}`}><button className="page-link" onClick={() => onPage(page + 1)}>›</button></li></ul></nav>
  );
}

/**
 * A document list page: the server route's filters live in the address bar (?status=&supplier=&q=&page=),
 * the table is drawn from `columns`. `filters` renders the filter controls and gets `set(patch)`.
 */
export default function DocList({ title, api, queryKey, columns, filters, actions, empty, above, below }: {
  title: ReactNode; api: string; queryKey: string; columns: Col[]; empty: string;
  filters?: (d: any, set: (patch: Record<string, string>) => void, sp: URLSearchParams) => ReactNode; actions?: (d: any) => ReactNode; above?: (d: any) => ReactNode; below?: (d: any) => ReactNode;
}) {
  const [sp, setSp] = useSearchParams();
  const set = (patch: Record<string, string>) => { const n = new URLSearchParams(sp); Object.entries(patch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); if (!('page' in patch)) n.delete('page'); setSp(n); };
  const res = useQuery({ queryKey: [queryKey, sp.toString()], queryFn: async () => (await get(`${api}?${sp.toString()}`)).data });
  const d = res.data;
  return (
    <>
      <PageHead title={title}>{d && actions?.(d)}</PageHead>
      {d && filters?.(d, set, sp)}
      {res.isLoading ? <Spinner /> : d && (<>
        {above?.(d)}
        <div className="card"><div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr>{columns.map((c, i) => <th key={i} className={c.className}>{c.head}</th>)}</tr></thead>
          <tbody>
            {d.rows.map((r: any, ri: number) => <tr key={r.id ?? ri}>{columns.map((c, i) => <td key={i} className={c.className}>{c.cell(r, d)}</td>)}</tr>)}
            {!d.rows.length && <tr><td colSpan={columns.length} className="text-muted">{empty}</td></tr>}
          </tbody></table></div></div>
        <Pager page={Number(sp.get('page') ?? d.page ?? 1)} pages={d.pages ?? 1} onPage={(p) => set({ page: String(p) })} />
        {below?.(d)}
      </>)}
    </>
  );
}
