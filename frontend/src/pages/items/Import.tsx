import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, legacyUrl, postForm, type Msg } from '../../lib/api';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

export default function ItemImport() {
  const { term } = useSession(); const nav = useNavigate(); const qc = useQueryClient();
  const src = useQuery({ queryKey: ['item-import'], queryFn: async () => (await get('/items/import')).data });
  const [msgs, setMsgs] = useState<Msg[]>([]); const [busy, setBusy] = useState(false);
  if (src.isLoading || !src.data) return <Spinner />;
  const cols: string[] = src.data.columns ?? [];
  const run = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault(); setBusy(true); setMsgs([]);
    const r = await postForm('/items/import', new FormData(e.currentTarget)); setBusy(false);
    if (!r.ok) { setMsgs(r.messages ?? []); window.scrollTo({ top: 0, behavior: 'smooth' }); return; }
    qc.invalidateQueries({ queryKey: ['items'] }); nav('/items', { state: { notice: (r.messages ?? [])[0]?.msg } });
  };
  return (
    <>
      <PageHead title="Import items" />
      <Messages items={msgs} />
      <div className="row g-3"><div className="col-lg-7"><div className="card"><div className="card-body">
        <h2 className="h6">Upload a CSV file</h2>
        <form onSubmit={run}>
          <div className="mb-3"><input type="file" name="file" accept=".csv,text/csv" className="form-control" required /></div>
          <button className="btn btn-primary" disabled={busy}>{busy ? 'Importing…' : 'Import'}</button> <Link className="btn btn-link" to="/items">Cancel</Link>
        </form>
        <p className="text-muted small mt-3 mb-0">All rows are checked first. If anything is wrong nothing is imported and you will see what to fix.</p>
      </div></div></div>
      <div className="col-lg-5"><div className="card"><div className="card-body">
        <h2 className="h6">File format</h2>
        <p className="small">One row per {term('item').toLowerCase()}. Every colour, design or size is its own {term('item').toLowerCase()} with its own name and SKU. Design, composition, width and the like are kept on the supplier item.</p>
        <p className="small mb-2"><code>{cols.join(', ')}</code></p>
        <ul className="small text-muted ps-3">
          <li>{term('rack', true)} are not part of this file: add stock and choose {term('rack', true).toLowerCase()} under Stock → Adjustments.</li>
          <li><code>unit</code> and <code>tax</code> must already exist (Masters). Categories and brands are created automatically.</li>
          <li><code>item_type</code>: fabric, linen, wallpaper, carpet, accessory or other. Blank means other.</li>
          <li><code>hsn_code</code> is the tax classification code.</li>
          <li><code>track_batch</code>: yes / no (one roll = one {term('batch').toLowerCase()}). If left blank, fabric, wallpaper and carpet are tracked by roll.</li>
          <li>Older files still import; columns this version no longer has (variant_name, colour, size, design_no…) are ignored.</li>
          <li>Blank <code>sku</code> is generated for you.</li>
          <li>From Excel: File → Save As → CSV.</li>
        </ul>
        <a className="btn btn-outline-secondary btn-sm" href={legacyUrl('/items/import/template')}><i className="bi bi-download" /> Download template</a>
      </div></div></div></div>
    </>
  );
}
