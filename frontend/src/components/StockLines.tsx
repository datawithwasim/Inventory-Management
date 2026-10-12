import { useEffect, useState } from 'react';
import { get } from '../lib/api';
import { useSession } from '../lib/session';
import ItemSearch from './ItemSearch';

export type SL = {
  variant_id: string; label: string; unit: string; dec: boolean; tb: boolean;
  batch_id: string; lot_no: string; location_id: string; to_location_id: string; qty: string; unit_cost: string;
};
export const blankSl = (): SL => ({ variant_id: '', label: '', unit: '', dec: true, tb: false, batch_id: '', lot_no: '', location_id: '', to_location_id: '0', qty: '', unit_cost: '' });
export type Racks = Record<string, { id: number; code: string }[]>;

function Row({ l, mode, racks, from, to, between, patch, remove }: { l: SL; mode: 'adjust' | 'transfer'; racks: Racks; from: string; to: string; between: boolean; patch: (p: Partial<SL>) => void; remove: () => void }) {
  const { term } = useSession();
  const [batches, setBatches] = useState<any[]>([]);
  const [place, setPlace] = useState<any[] | null>(null);

  // the rolls of this item in the chosen warehouse
  useEffect(() => {
    if (!l.variant_id || !l.tb) { setBatches([]); return; }
    let live = true;
    get(`/stock/batch-options?variant=${l.variant_id}&warehouse=${from}${mode === 'transfer' ? '&in_stock=1' : ''}`).then((r: any) => { if (live) setBatches(Array.isArray(r) ? r : []); });
    return () => { live = false; };
  }, [l.variant_id, l.tb, from, mode]);

  // where that stock sits now: shown as a hint, and the fullest rack is pre-picked until the user chooses one
  useEffect(() => {
    if (!l.variant_id || (l.tb && !/^\d+$/.test(l.batch_id))) { setPlace(null); if (l.location_id === '') patch({ location_id: '0' }); return; }
    let live = true;
    get(`/stock/placement?variant=${l.variant_id}&warehouse=${from}${l.tb ? `&batch=${l.batch_id}` : ''}`).then((r: any) => {
      if (!live) return;
      const rows = Array.isArray(r) ? r : [];
      setPlace(rows);
      if (l.location_id === '') patch({ location_id: rows.length ? String(rows[0].location_id) : '0' });
    });
    return () => { live = false; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [l.variant_id, l.tb, l.batch_id, from]);

  const fromRacks = racks[from] ?? []; const toRacks = racks[to] ?? [];
  const rackSel = (value: string, onChange: (v: string) => void, list: { id: number; code: string }[]) => (
    <select className="form-select form-select-sm" value={value || '0'} onChange={(e) => onChange(e.target.value)}>
      <option value="0">No {term('rack').toLowerCase()}</option>{list.map((r) => <option key={r.id} value={r.id}>{r.code}</option>)}
    </select>
  );
  return (
    <tr>
      <td style={{ minWidth: 260 }}>
        <ItemSearch path="/stock/lookup" value={l.variant_id} label={l.label} placeholder="Search item, SKU or barcode"
          onPick={(p) => patch(p
            ? { variant_id: String(p.row.id), label: p.label, unit: p.row.unit ?? '', dec: !!Number(p.row.allow_decimal), tb: !!Number(p.row.track_batch), batch_id: p.row.track_batch && mode === 'adjust' ? 'new' : '', location_id: '' }
            : { variant_id: '', label: '', tb: false, batch_id: '', location_id: '' })} />
      </td>
      <td style={{ minWidth: 210 }}>
        {l.tb ? (<>
          <select className="form-select form-select-sm" value={l.batch_id} onChange={(e) => patch({ batch_id: e.target.value, location_id: '' })}>
            {mode === 'adjust' ? <option value="new">+ New {term('batch').toLowerCase()}</option> : <option value="">{batches.length ? `Choose ${term('batch').toLowerCase()}…` : `No ${term('batch', true).toLowerCase()} in stock here`}</option>}
            {batches.map((b) => <option key={b.id} value={b.id}>{b.batch_no}{b.supplier_lot ? ` · lot ${b.supplier_lot}` : ''} · balance {parseFloat(b.balance)}</option>)}
          </select>
          {mode === 'adjust' && l.batch_id === 'new' && <input className="form-control form-control-sm mt-1" placeholder="Supplier lot no. (optional)" maxLength={60} value={l.lot_no} onChange={(e) => patch({ lot_no: e.target.value })} />}
        </>) : <span className="text-muted small">—</span>}
      </td>
      <td style={{ minWidth: 150 }}>
        {rackSel(l.location_id, (v) => patch({ location_id: v }), fromRacks)}
        {place && <div className="form-text small">{place.length ? 'In stock: ' + place.map((r) => `${r.code || 'No rack'} ${parseFloat(r.qty)}`).join(' · ') : 'Nothing in stock here'}</div>}
      </td>
      {mode === 'transfer' && !between && <td style={{ minWidth: 150 }}>{rackSel(l.to_location_id, (v) => patch({ to_location_id: v }), toRacks)}</td>}
      <td className="text-nowrap">
        <input type="number" className="form-control form-control-sm d-inline-block" style={{ width: 110 }} step={l.dec ? '0.001' : '1'} min={mode === 'transfer' ? '0' : undefined} value={l.qty} onChange={(e) => patch({ qty: e.target.value })} />
        <span className="ms-1 text-muted small">{l.unit}</span>
      </td>
      {mode === 'adjust' && <td><input type="number" className="form-control form-control-sm" style={{ width: 100 }} step="0.01" min="0" placeholder="default" value={l.unit_cost} onChange={(e) => patch({ unit_cost: e.target.value })} /></td>}
      <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={remove}>×</button></td>
    </tr>
  );
}

/** The lines of a stock adjustment or transfer: item, roll (batch), rack(s) and quantity. */
export default function StockLines({ mode, lines, setLines, racks, from, to = '' }: { mode: 'adjust' | 'transfer'; lines: SL[]; setLines: (f: (ls: SL[]) => SL[]) => void; racks: Racks; from: string; to?: string }) {
  // between two warehouses the receiving side picks the rack when the goods arrive
  const between = mode === 'transfer' && !!to && to !== from;
  const { term } = useSession();
  const patch = (i: number) => (p: Partial<SL>) => setLines((ls) => ls.map((l, j) => (j === i ? { ...l, ...p } : l)));
  return (
    <div className="card mb-3"><div className="card-header d-flex justify-content-between align-items-center"><span>{term('item', true)}</span>
      <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines((ls) => [...ls, blankSl()])}><i className="bi bi-plus-lg" /> Add line</button></div>
      <div className="table-responsive" style={{ overflow: 'visible' }}><table className="table mb-0 align-middle">
        <thead><tr><th>{term('item')}</th><th>{term('batch')}</th>{mode === 'adjust' ? <th>{term('rack')}</th> : between ? <th>Take from {term('rack').toLowerCase()}</th> : <><th>From {term('rack').toLowerCase()}</th><th>To {term('rack').toLowerCase()}</th></>}<th>{mode === 'adjust' ? 'Qty change (− to remove)' : 'Qty to move'}</th>{mode === 'adjust' && <th>Cost / unit</th>}<th /></tr></thead>
        <tbody>{lines.map((l, i) => <Row key={i} l={l} mode={mode} racks={racks} from={from} to={to} between={between} patch={patch(i)} remove={() => setLines((ls) => (ls.length > 1 ? ls.filter((_, j) => j !== i) : [blankSl()]))} />)}</tbody>
      </table></div></div>
  );
}

/** What gets posted for the lines (the PHP controllers read `lines[n][...]`). */
export const slPayload = (lines: SL[]) => lines.filter((l) => l.variant_id).map((l) => ({
  variant_id: l.variant_id, batch_id: l.tb ? l.batch_id : '', lot_no: l.lot_no, location_id: l.location_id || '0', to_location_id: l.to_location_id || '0', qty: l.qty, unit_cost: l.unit_cost,
}));
