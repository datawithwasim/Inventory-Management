import { money, qty as fq } from '../lib/format';
import { saleLine } from '../lib/docs';
import ItemSearch from './ItemSearch';

export type SLine = { variant_id: string; label: string; unit: string; dec: boolean; qty: string; unit_price: string; discount_pct: string; tax_rate: string; hint: string };
export const blankSLine = (): SLine => ({ variant_id: '', label: '', unit: '', dec: true, qty: '', unit_price: '', discount_pct: '', tax_rate: '', hint: '' });

/** The items table of a sales order: item search (with this customer's price), qty, price, discount, tax and live totals. */
export default function SalesLines({ lines, setLines, customer, warehouse, charges, showDisc = true, showTax = true, term }: {
  lines: SLine[]; setLines: (l: SLine[]) => void; customer: string; warehouse: string; charges: number; showDisc?: boolean; showTax?: boolean; term: (k: string, p?: boolean) => string;
}) {
  const set = (i: number, patch: Partial<SLine>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const calc = (l: SLine) => saleLine(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.discount_pct) || 0, Number(l.tax_rate) || 0);
  const t = lines.reduce((a, l) => { const c = calc(l); return { gross: a.gross + c.gross, disc: a.disc + c.disc, tax: a.tax + c.tax, total: a.total + c.total }; }, { gross: 0, disc: 0, tax: 0, total: 0 });
  const cols = 3 + (showDisc ? 1 : 0) + (showTax ? 1 : 0);
  return (
    <div className="card mb-3">
      <div className="card-header d-flex justify-content-between align-items-center"><span>{term('item', true)}</span>
        <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setLines([...lines, blankSLine()])}><i className="bi bi-plus-lg" /> Add {term('item').toLowerCase()}</button></div>
      <div className="table-responsive" style={{ overflow: 'visible' }}><table className="table mb-0 align-middle" id="lineTable">
        <thead><tr><th style={{ minWidth: 260 }}>{term('item')}</th><th style={{ width: 130 }}>Qty</th><th style={{ width: 120 }}>Price</th>{showDisc && <th style={{ width: 90 }}>Disc %</th>}{showTax && <th style={{ width: 90 }}>Tax %</th>}<th className="text-end" style={{ width: 120 }}>Total</th><th style={{ width: 44 }} /></tr></thead>
        <tbody>
          {lines.map((l, i) => (
            <tr key={i}>
              <td><ItemSearch path="/lookup/sale-items" extra={`&customer=${customer || 0}&warehouse=${warehouse || 0}`} value={l.variant_id} label={l.label} placeholder="Search item, SKU or barcode"
                onPick={(p) => {
                  if (!p) { set(i, { variant_id: '', label: '', hint: '' }); return; }
                  const v = p.row;
                  set(i, { variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), unit_price: String(v.price ?? ''), discount_pct: Number(v.discount) ? String(v.discount) : '', tax_rate: Number(v.tax_rate) ? String(Number(v.tax_rate)) : '',
                    hint: v.available != null ? `Free stock: ${fq(v.available)} ${v.unit}${Number(v.is_bundle) ? ' (sets)' : ''}` : '' });
                }} />
                {l.hint && <div className="small text-muted mt-1">{l.hint}</div>}</td>
              <td><div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div></td>
              <td><input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value })} /></td>
              {showDisc && <td><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.discount_pct} onChange={(e) => set(i, { discount_pct: e.target.value })} /></td>}
              {showTax && <td><input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /></td>}
              <td className="text-end">{money(calc(l).total)}</td>
              <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={() => setLines(lines.length > 1 ? lines.filter((_, j) => j !== i) : [blankSLine()])}>×</button></td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr><td colSpan={cols} className="text-end text-muted">Gross</td><td className="text-end">{money(t.gross)}</td><td /></tr>
          {showDisc && <tr><td colSpan={cols} className="text-end text-muted">Discount</td><td className="text-end">− {money(t.disc)}</td><td /></tr>}
          {showTax && <tr><td colSpan={cols} className="text-end text-muted">Tax</td><td className="text-end">{money(t.tax)}</td><td /></tr>}
          <tr><td colSpan={cols} className="text-end text-muted">Delivery &amp; installation</td><td className="text-end">{money(charges)}</td><td /></tr>
          <tr><th colSpan={cols} className="text-end">Total</th><th className="text-end">{money(t.total + charges)}</th><td /></tr>
        </tfoot>
      </table></div>
    </div>
  );
}
