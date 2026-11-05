import { type ReactNode } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { get } from '../lib/api';
import { PageHead, Spinner } from './ui';

export type Col<R = any> = { head: ReactNode; cell: (r: R, d: any) => ReactNode; className?: string };

/** Pager like the PHP one: previous / "n / pages" / next. */
export function Pager({ page, pages, onPage, inCard = false }: { page: number; pages: number; onPage: (p: number) => void; inCard?: boolean }) {
  if (pages <= 1) return null;
  const nav = (
    <nav aria-label="Pages"><ul className={`pagination pagination-sm mb-0 ${inCard ? '' : 'mt-3'}`}>
      <li className={`page-item ${page <= 1 ? 'disabled' : ''}`}><button className="page-link" onClick={() => onPage(page - 1)}>‹</button></li>
      <li className="page-item disabled"><span className="page-link">{page} / {pages}</span></li>
      <li className={`page-item ${page >= pages ? 'disabled' : ''}`}><button className="page-link" onClick={() => onPage(page + 1)}>›</button></li></ul></nav>
  );
  return inCard ? <div className="pager-row"><span>Page {page} of {pages}</span>{nav}</div> : nav;
}

/** Status tabs over a list (All / Draft / Ordered …): they set one address-bar filter. */
export function Tabs({ tabs, value, onPick }: { tabs: [string, string][]; value: string; onPick: (v: string) => void }) {
  return <div className="seg" role="tablist">{tabs.map(([v, l]) => <button key={v || 'all'} type="button" role="tab" aria-selected={value === v} className={value === v ? 'on' : ''} onClick={() => onPick(v)}>{l}</button>)}</div>;
}

/**
 * A document list page: the server route's filters live in the address bar (?status=&supplier=&q=&page=),
 * the table is drawn from `columns`. `tabs` are the status tabs over the table (they set `tabParam`, default "status");
 * `filters` renders the other filter controls next to them and gets `set(patch)`.
 */
export default function DocList({ title, sub, api, queryKey, columns, filters, actions, empty, above, below, tabs, tabParam = 'status', tabDefault = '' }: {
  title: ReactNode; sub?: ReactNode; api: string; queryKey: string; columns: Col[]; empty: string;
  filters?: (d: any, set: (patch: Record<string, string>) => void, sp: URLSearchParams) => ReactNode; actions?: (d: any) => ReactNode; above?: (d: any) => ReactNode; below?: (d: any) => ReactNode;
  tabs?: [string, string][] | ((d: any) => [string, string][]); tabParam?: string; tabDefault?: string;
}) {
  const [sp, setSp] = useSearchParams();
  const set = (patch: Record<string, string>) => { const n = new URLSearchParams(sp); Object.entries(patch).forEach(([k, v]) => (v ? n.set(k, v) : n.delete(k))); if (!('page' in patch)) n.delete('page'); setSp(n); };
  const res = useQuery({ queryKey: [queryKey, api, sp.toString()], queryFn: async () => (await get(`${api}?${sp.toString()}`)).data });
  const d = res.data;
  const tabList = d ? (typeof tabs === 'function' ? tabs(d) : tabs ?? []) : [];
  return (
    <>
      <PageHead title={title} sub={sub}>{d && actions?.(d)}</PageHead>
      {res.isLoading ? <Spinner /> : d && (<>
        {above?.(d)}
        <div className="card list-card">
          {(tabList.length > 0 || filters) && <div className="list-bar">
            {tabList.length > 0 && <Tabs tabs={tabList} value={sp.get(tabParam) ?? tabDefault} onPick={(v) => set({ [tabParam]: v })} />}
            {filters && <div className="list-tools">{filters(d, set, sp)}</div>}
          </div>}
          <div className="table-responsive"><table className="table table-hover mb-0 align-middle">
          <thead><tr>{columns.map((c, i) => <th key={i} className={c.className}>{c.head}</th>)}</tr></thead>
          <tbody>
            {d.rows.map((r: any, ri: number) => <tr key={r.id ?? ri}>{columns.map((c, i) => <td key={i} className={c.className}>{c.cell(r, d)}</td>)}</tr>)}
            {!d.rows.length && <tr><td colSpan={columns.length}><div className="list-empty"><i className="bi bi-inbox" />{empty}</div></td></tr>}
          </tbody></table></div>
          <Pager inCard page={Number(sp.get('page') ?? d.page ?? 1)} pages={d.pages ?? 1} onPage={(p) => set({ page: String(p) })} />
        </div>
        {below?.(d)}
      </>)}
    </>
  );
}
