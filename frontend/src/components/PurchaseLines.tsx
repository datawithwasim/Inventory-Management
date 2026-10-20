import { money, qty as fmtQty } from '../lib/format';
import { lineTotals } from '../lib/docs';
import ItemSearch from './ItemSearch';
import LineGrid, { gridCols, type CellDef, type GridCol } from './LineGrid';

export type Line = { variant_id: string; label: string; unit: string; dec: boolean; qty: string; unit_price: string; tax_rate: string; hint?: string };
export const blankLine = (): Line => ({ variant_id: '', label: '', unit: '', dec: true, qty: '', unit_price: '', tax_rate: '', hint: '' });

/** The items table of a purchase order, drawn from its column design: search an item, qty, price, tax, line total. */
export default function PurchaseLines({ lines, setLines, supplier, grid, term }: { lines: Line[]; setLines: (l: Line[]) => void; supplier?: string; grid?: GridCol[]; term: (k: string) => string }) {
  const set = (i: number, patch: Partial<Line>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const calc = (l: Line) => lineTotals(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.tax_rate) || 0);
  const totals = lines.reduce((a, l) => { const t = calc(l); return { net: a.net + t.net, tax: a.tax + t.tax }; }, { net: 0, tax: 0 });
  const cols = gridCols(grid, { item: term('item'), qty: 'Qty', price: 'Unit price', tax: 'Tax %', total: 'Line total' });
  const hasTax = cols.some((c) => c.key === 'tax');
  const defs: Record<string, CellDef<Line>> = {
    item: { style: { minWidth: 260 }, cell: (l, i) => (<>
      <ItemSearch value={l.variant_id} label={l.label} supplier={supplier} placeholder="Search item, SKU or barcode"
        onPick={(p) => {
          if (!p) { set(i, { variant_id: '', label: '', hint: '' }); return; }
          const v = p.row; const hint = [v.sup_code || v.sup_name ? 'Their: ' + [v.sup_code, v.sup_name].filter(Boolean).join(' · ') : '', v.rate_note, v.last_note].filter(Boolean).join(' · ');
          set(i, { variant_id: String(v.id), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), unit_price: String(v.rate ?? v.cost_price ?? ''), tax_rate: String(Number(v.tax_rate ?? 0)), hint });
        }} />
      {l.hint && <div className="small text-muted mt-1">{l.hint}</div>}</>) },
    qty: { style: { width: 130 }, cell: (l, i) => <div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div> },
    price: { style: { width: 130 }, cell: (l, i) => <input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value })} /> },
    tax: { style: { width: 100 }, cell: (l, i) => <input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /> },
    total: { style: { width: 130 }, className: 'text-end', cell: (l) => money(calc(l).total) },
  };
  return (
    <LineGrid title="Items" addLabel="Add item" onAdd={() => setLines([...lines, blankLine()])} cols={cols} defs={defs} rows={lines}
      onRemove={(i) => setLines(lines.length > 1 ? lines.filter((_, j) => j !== i) : [blankLine()])}
      footer={<>
        <tr><td className="text-muted">Subtotal</td><td className="text-end">{money(totals.net)}</td></tr>
        {hasTax && <tr><td className="text-muted">Tax</td><td className="text-end">{money(totals.tax)}</td></tr>}
        <tr><th>Total</th><th className="text-end">{money(totals.net + totals.tax)}</th></tr></>} />
  );
}
export { fmtQty };
