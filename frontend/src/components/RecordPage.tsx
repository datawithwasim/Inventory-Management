import { useState, type ReactNode } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, post } from '../lib/api';
import { isReact, localPath } from '../lib/routes';
import { fdate } from '../lib/format';
import { useSession } from '../lib/session';
import { Dropdown, Messages, Spinner } from './ui';

type Item = { key: string; label: string; text: string; href: string | null; links?: { text: string; href: string }[] };
type RecordData = { summary: Item[]; sections: { title: string; cols: number; items: Item[] }[] };

function One({ text, href }: { text: string; href: string }) {
  const to = localPath(href);
  return isReact(to) ? <Link to={to}>{text}</Link> : <a href={href}>{text}</a>;
}

function Value({ it }: { it: Item }) {
  if (!it.text) return <i className="text-muted">—</i>;
  if (it.links) return <>{it.links.map((l, k) => <span key={l.href}>{k > 0 && ', '}<One text={l.text} href={l.href} /></span>)}</>;
  if (!it.href) return <>{it.text}</>;
  const to = localPath(it.href);
  return isReact(to) ? <Link to={to}>{it.text}</Link> : <a href={it.href}>{it.text}</a>;
}

/**
 * The Zoho-style record page shell: title row with actions, "Related list" menu, Overview / Timeline tabs,
 * the designed summary strip and sections (from the layout designer), module cards, and notes.
 */
export default function RecordPage({ entity, id, name, back, badges, actions, menu, related, children, notice }: {
  entity: string; id: string | number; name: string; back: string; badges?: ReactNode; actions?: ReactNode; menu?: (close: () => void) => ReactNode;
  related: { id: string; label: string }[]; children?: ReactNode; notice?: ReactNode;
}) {
  const { can } = useSession();
  const nav = useNavigate(); const qc = useQueryClient();
  const [tab, setTab] = useState<'overview' | 'timeline'>('overview');
  const [note, setNote] = useState(''); const [err, setErr] = useState('');
  const q = useQuery({ queryKey: ['record', entity, String(id)], queryFn: async () => (await get(`/record/${entity}/${id}`)) as any });
  if (q.isLoading) return <Spinner />;
  const rec: RecordData | undefined = q.data?.record;
  if (!rec) return <div className="alert alert-danger">Could not load this record.</div>;
  const notes: any[] = q.data.notes ?? []; const timeline: any[] = q.data.timeline ?? [];
  const addNote = async () => {
    if (!note.trim()) return;
    const r = await post(`/records/${entity}/${id}/notes`, { body: note });
    if (r.ok) { setNote(''); setErr(''); qc.invalidateQueries({ queryKey: ['record', entity, String(id)] }); } else setErr(r.messages?.[0]?.msg ?? 'Could not add the note.');
  };
  const delNote = async (nid: number) => { if (!confirm('Remove this note?')) return; await post(`/records/notes/${nid}/delete`); qc.invalidateQueries({ queryKey: ['record', entity, String(id)] }); };
  const scrollTo = (rid: string) => { setTab('overview'); setTimeout(() => document.getElementById(rid)?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 0); };
  const sideItems = [{ id: 'recOverview', label: 'Overview' }, ...related, { id: 'notes', label: `Notes (${notes.length})` }];

  return (
    <div className="rec">
      <div className="rec-top">
        <button className="rec-back" title="Back to the list" onClick={() => nav(back)}><i className="bi bi-arrow-left" /></button>
        <div className="rec-avatar">{name.trim().slice(0, 1).toUpperCase()}</div>
        <div className="rec-title"><h1>{name}</h1><div className="rec-badges">{badges}</div></div>
        <div className="rec-actions">
          {actions}
          {menu && (
            <Dropdown button={() => <button className="btn btn-sm btn-outline-secondary" aria-label="More"><i className="bi bi-three-dots" /></button>}>
              {(close) => <ul className="list-unstyled m-0">{menu(close)}</ul>}
            </Dropdown>
          )}
        </div>
      </div>
      {notice}
      <div className="rec-layout">
        <aside className="rec-side"><div className="rec-side-h">Related list</div>
          {sideItems.map((s) => <a key={s.id} href={`#${s.id}`} onClick={(e) => { e.preventDefault(); scrollTo(s.id); }}>{s.label}</a>)}
        </aside>
        <div className="rec-main">
          <div className="rec-tabs" role="tablist"><button type="button" className={tab === 'overview' ? 'on' : ''} onClick={() => setTab('overview')}>Overview</button><button type="button" className={tab === 'timeline' ? 'on' : ''} onClick={() => setTab('timeline')}>Timeline</button></div>
          {tab === 'overview' && (
            <div id="recOverview" className="rec-panel">
              {rec.summary.length > 0 && <div className="card rec-summary"><div className="card-body">{rec.summary.map((it) => <div className="rec-kv" key={it.key}><span>{it.label}</span><b><Value it={it} /></b></div>)}</div></div>}
              {rec.sections.map((s, i) => (
                <section className="card rec-sec" key={i}><div className="card-header">{s.title}</div>
                  <div className="card-body"><div className={`rec-grid c${s.cols}`}>{s.items.map((it) => <div className="rec-kv" key={it.key}><span>{it.label}</span><b><Value it={it} /></b></div>)}</div></div></section>
              ))}
              {children}
              <section className="card mb-3" id="notes"><div className="card-header">Notes</div><div className="card-body">
                {q.data.canNote && <div className="mb-3">
                  <Messages items={err ? [{ type: 'danger', msg: err }] : []} />
                  <textarea className="form-control" rows={2} maxLength={1500} placeholder="Add a note…" value={note} onChange={(e) => setNote(e.target.value)} />
                  <button className="btn btn-sm btn-primary mt-2" onClick={addNote}>Add note</button></div>}
                {notes.map((n) => (
                  <div className="rec-note" key={n.id}><div style={{ whiteSpace: 'pre-wrap' }}>{n.body}</div>
                    <div className="small text-muted d-flex justify-content-between"><span>{n.user_name || 'Someone'} · {fdate(String(n.created_at).slice(0, 10))} {String(n.created_at).slice(11, 16)}</span>
                      {q.data.canNote && <button className="btn btn-link btn-sm p-0 text-danger" onClick={() => delNote(n.id)}>Delete</button>}</div></div>
                ))}
                {!notes.length && <div className="text-muted small">No notes yet.</div>}
              </div></section>
            </div>
          )}
          {tab === 'timeline' && (
            <div id="recTimeline" className="rec-panel"><div className="card"><div className="card-body">
              <ul className="rec-tl">
                {timeline.map((t, i) => <li key={i}><span className="dot" /><div><b>{t.who}</b> {t.verb}{t.details && <span className="text-muted"> · {t.details}</span>}<div className="small text-muted">{fdate(String(t.at).slice(0, 10))} {String(t.at).slice(11, 16)}</div></div></li>)}
                {!timeline.length && <li><span className="dot" /><div className="text-muted">No activity recorded yet.</div></li>}
              </ul></div></div></div>
          )}
        </div>
      </div>
    </div>
  );
}

export { Link };
