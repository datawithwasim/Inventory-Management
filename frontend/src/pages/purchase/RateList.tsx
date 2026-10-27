import { useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { get, post, type Msg } from '../../lib/api';
import { fdate, moneyIn, qty } from '../../lib/format';
import { useSession } from '../../lib/session';
import { Messages, PageHead, Spinner } from '../../components/ui';

const plain = (v: any) => rtrimZeros(Number(v).toFixed(2));
const rtrimZeros = (s: string) => s.replace(/\.?0+$/, '');

export default function RateList() {
  const { can, term } = useSession(); const qc = useQueryClient();
  const [sp, setSp] = useSearchParams();
  const supplier = sp.get('supplier') ?? ''; const show = sp.get('show') === 'all' ? 'all' : 'current'; const applied = sp.get('q') ?? '';
  const [q, setQ] = useState(applied);
  const set = (k: string, v: string) => { const n = new URLSearchParams(sp); if (v) n.set(k, v); else n.delete(k); setSp(n); };
  const res = useQuery({ queryKey: ['rates', supplier, show, applied], queryFn: async () => (await get(`/purchase/rates?q=${encodeURIComponent(applied)}&supplier=${supplier}&show=${show}`)).data });
  const [msgs, setMsgs] = useState<Msg[]>([]);
  const d = res.data; const canEdit = can('suppliers.edit');
  const reload = () => qc.invalidateQueries({ queryKey: ['rates'] });
  const del = async (r: any) => { if (!confirm('Remove this rate?')) return; await post(`/suppliers/${r.supplier_id}/rates/${r.id}/delete`, { return: 'purchase/rates' }); reload(); };
  const stats: [string, number, string][] = d ? [['Current rates', d.stats.current, 'tags'], [term('supplier', true), d.stats.suppliers, 'truck'], [`${term('item', true)} with a rate`, d.stats.items, 'box-seam'], ['Earlier rates kept', d.stats.earlier, 'clock-history']] : [];
  return (
    <>
      <PageHead title="Rate lists" />
      <Messages items={msgs} />
      {d && <div className="row g-3 mb-3">{stats.map(([l, n, ic]) => (
        <div className="col-6 col-lg-3" key={l}><div className="card"><div className="card-body d-flex align-items-center gap-3"><span className="rec-avatar" style={{ width: 40, height: 40 }}><i className={`bi bi-${ic}`} /></span><div><div className="fs-4 lh-1">{n}</div><div className="small text-muted">{l}</div></div></div></div></div>))}</div>}
      <section className="card mb-3">
        <div className="card-header rl-head">
          <div><div className="rl-title"><i className="bi bi-currency-rupee" /> Supplier rate lists</div><div className="rl-sub">What every {term('supplier').toLowerCase()} charges for each {term('item').toLowerCase()} · filled in automatically on purchase orders</div></div>
          <div className="rl-tools">
            {can('reports.view') && <Link className="btn btn-outline-secondary" to="/reports/rate-comparison"><i className="bi bi-bar-chart" /><span className="d-none d-md-inline"> Compare suppliers</span></Link>}
            {canEdit && <>
              <Link className="btn btn-outline-secondary" to="/purchase/rates/exchange"><i className="bi bi-currency-exchange" /><span className="d-none d-md-inline"> Exchange rates</span></Link>
              <Link className="btn btn-outline-secondary" to={`/purchase/rates/import${supplier ? `?supplier=${supplier}` : ''}`}><i className="bi bi-upload" /><span className="d-none d-md-inline"> Import CSV</span></Link>
              <Link className="btn btn-primary" to={`/purchase/rates/create${supplier ? `?supplier=${supplier}` : ''}`}><i className="bi bi-plus-lg" /> Add rate</Link></>}
          </div>
        </div>
        <form className="rl-bar" onSubmit={(e) => { e.preventDefault(); set('q', q); }}>
          <div className="rl-search"><i className="bi bi-search" /><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search item, SKU, design or their code" aria-label="Search" /></div>
          <select className="form-select form-select-sm" style={{ maxWidth: 240 }} value={supplier} onChange={(e) => set('supplier', e.target.value)} aria-label={term('supplier')}><option value="">All {term('supplier', true).toLowerCase()}</option>{(d?.suppliers ?? []).map((s: any) => <option key={s.id} value={s.id}>{s.name}</option>)}</select>
          <div className="rec-tabs act-tabs" role="group"><button type="button" className={show === 'current' ? 'on' : ''} onClick={() => set('show', '')}>Current</button><button type="button" className={show === 'all' ? 'on' : ''} onClick={() => set('show', 'all')}>With earlier rates</button></div>
        </form>
        {res.isLoading ? <Spinner /> : d.rows.length ? (
          <div className="table-responsive"><table className="table rl-table align-middle mb-0">
            <thead><tr><th>{term('item')}</th><th>{term('supplier')}</th><th className="text-end">Roll rate</th><th className="text-end">Cut rate</th><th className="text-end">MOQ rate</th><th className="text-end d-none d-md-table-cell">Disc.</th><th className="d-none d-lg-table-cell">Valid</th><th /></tr></thead>
            <tbody>{d.rows.map((r: any) => {
              const name = r.item_name + (r.vname ? ' — ' + r.vname : '');
              const lowest = r.is_current && d.low[r.variant_id] != null && Math.abs(r.net_inr - d.low[r.variant_id]) < 0.005;
              const foreign = r.currency && r.currency !== 'INR';
              const cell = (v: number | null) => v === null ? <span className="text-muted">—</span> : <>{moneyIn(v, r.currency)}</>;
              return (
                <tr key={r.id} className={r.is_current ? '' : 'rl-old'}>
                  <td><Link className="fw-medium" to={`/items/${r.item_id}`}>{name}</Link> <span className="text-muted small">{r.sku}</span>{r.is_current ? null : <> <span className="badge text-bg-light border fw-normal">Earlier</span></>}</td>
                  <td><Link to={`/suppliers/${r.supplier_id}`}>{r.supplier_name}</Link>{r.scode ? <span className="text-muted small"> {r.scode}</span> : null}{r.sname ? <div className="small text-muted">{r.sname}</div> : null}</td>
                  <td className="text-end fw-semibold">{cell(r.nets.roll)}{lowest ? <> <span className="badge text-bg-success fw-normal" title="Cheapest current roll rate for this item">Lowest</span></> : null}{foreign && r.is_current ? <div className="small text-muted fw-normal">≈ {moneyIn(r.net_inr, 'INR')}</div> : null}</td>
                  <td className="text-end">{cell(r.nets.cut)}</td>
                  <td className="text-end">{cell(r.nets.moq)}{r.nets.moq !== null ? <div className="small text-muted">from {qty(r.min_qty)}</div> : null}</td>
                  <td className="text-end d-none d-md-table-cell">{Number(r.discount_pct) > 0 ? `${plain(r.discount_pct)}%` : <span className="text-muted">—</span>}</td>
                  <td className="small text-nowrap d-none d-lg-table-cell">{fdate(r.valid_from)} <span className="text-muted">→</span> {r.valid_to ? fdate(r.valid_to) : <span className="text-muted">open</span>}</td>
                  <td className="text-end text-nowrap">{canEdit && <>{r.is_current && <Link className="btn btn-sm btn-outline-primary" to={`/purchase/rates/${r.id}/edit?supplier=${r.supplier_id}`}>Revise</Link>} <button className="btn btn-sm btn-link text-danger px-1" title="Remove" aria-label="Remove rate" onClick={() => del(r)}><i className="bi bi-trash" /></button></>}</td>
                </tr>);
            })}</tbody></table></div>
        ) : (
          <div className="rl-empty"><i className="bi bi-currency-rupee" /><div><b>{applied || supplier ? 'Nothing matches these filters' : 'No rates yet'}</b><div className="text-muted small">{applied || supplier ? `Clear the search or choose another ${term('supplier').toLowerCase()}.` : `Add the roll, cut and MOQ rates your ${term('supplier', true).toLowerCase()} quote. The rate fills in by itself when you pick the ${term('item').toLowerCase()} on a purchase order.`}</div></div></div>
        )}
      </section>
    </>
  );
}
