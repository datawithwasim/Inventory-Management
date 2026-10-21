import { money, qty as fq } from '../lib/format';
import { saleLine } from '../lib/docs';
import ItemSearch from './ItemSearch';
import LineGrid, { gridCols, type CellDef, type GridCol } from './LineGrid';

export type SLine = { variant_id: string; product_id: string; label: string; unit: string; dec: boolean; qty: string; unit_price: string; discount_pct: string; tax_rate: string; hint: string };
export const blankSLine = (): SLine => ({ variant_id: '', product_id: '', label: '', unit: '', dec: true, qty: '', unit_price: '', discount_pct: '', tax_rate: '', hint: '' });

/** The items table of a sales order, drawn from its column design: item search (with this customer's price), qty, price, discount, tax and live totals. */
export default function SalesLines({ lines, setLines, customer, warehouse, charges, grid, term, free = false }: {
  lines: SLine[]; setLines: (l: SLine[]) => void; customer: string; warehouse: string; charges: number; grid?: GridCol[]; term: (k: string, p?: boolean) => string; free?: boolean;
}) {
  const set = (i: number, patch: Partial<SLine>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
  const calc = (l: SLine) => saleLine(Number(l.qty) || 0, Number(l.unit_price) || 0, Number(l.discount_pct) || 0, Number(l.tax_rate) || 0);
  const t = lines.reduce((a, l) => { const c = calc(l); return { gross: a.gross + c.gross, disc: a.disc + c.disc, tax: a.tax + c.tax, total: a.total + c.total }; }, { gross: 0, disc: 0, tax: 0, total: 0 });
  const cols = gridCols(grid, { item: 'Product', qty: 'Qty', price: 'Price', disc: 'Disc %', tax: 'Tax %', total: 'Total' });
  const showDisc = cols.some((c) => c.key === 'disc'); const showTax = cols.some((c) => c.key === 'tax');
  const defs: Record<string, CellDef<SLine>> = {
    item: { style: { minWidth: 260 }, cell: (l, i) => (<>
      <ItemSearch path="/lookup/sale-items" extra={`&customer=${customer || 0}&warehouse=${warehouse || 0}`} value={l.variant_id} label={l.label} placeholder="Search product, catalogue, item or SKU"
        onPick={(p) => {
          if (!p) { set(i, { variant_id: '', product_id: '', label: '', hint: '' }); return; }
          const v = p.row;
          set(i, { variant_id: String(v.id), product_id: String(v.product_id ?? ''), label: p.label, unit: v.unit, dec: !!Number(v.allow_decimal), unit_price: free ? '0' : String(v.price ?? ''), discount_pct: !free && Number(v.discount) ? String(v.discount) : '', tax_rate: !free && Number(v.tax_rate) ? String(Number(v.tax_rate)) : '',
            hint: v.available != null ? `Free stock: ${fq(v.available)} ${v.unit}${Number(v.is_bundle) ? ' (sets)' : ''}` : '' });
        }} />
      {l.hint && <div className="small text-muted mt-1">{l.hint}</div>}</>) },
    qty: { style: { width: 130 }, cell: (l, i) => <div className="input-group input-group-sm"><input type="number" step={l.dec ? '0.001' : '1'} min="0" className="form-control" value={l.qty} onChange={(e) => set(i, { qty: e.target.value })} />{l.unit && <span className="input-group-text">{l.unit}</span>}</div> },
    price: { style: { width: 120 }, cell: (l, i) => <input type="number" step="0.01" min="0" className="form-control form-control-sm" value={l.unit_price} onChange={(e) => set(i, { unit_price: e.target.value })} /> },
    disc: { style: { width: 90 }, cell: (l, i) => <input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.discount_pct} onChange={(e) => set(i, { discount_pct: e.target.value })} /> },
    tax: { style: { width: 90 }, cell: (l, i) => <input type="number" step="0.01" min="0" max="100" className="form-control form-control-sm" value={l.tax_rate} onChange={(e) => set(i, { tax_rate: e.target.value })} /> },
    total: { style: { width: 120 }, className: 'text-end', cell: (l) => money(calc(l).total) },
  };
  return (
    <LineGrid title="Products" addLabel="Add product" onAdd={() => setLines([...lines, blankSLine()])} cols={cols} defs={defs} rows={lines}
      onRemove={(i) => setLines(lines.length > 1 ? lines.filter((_, j) => j !== i) : [blankSLine()])}
      footer={<>
        <tr><td className="text-muted">Gross</td><td className="text-end">{money(t.gross)}</td></tr>
        {showDisc && <tr><td className="text-muted">Discount</td><td className="text-end">− {money(t.disc)}</td></tr>}
        {showTax && <tr><td className="text-muted">Tax</td><td className="text-end">{money(t.tax)}</td></tr>}
        <tr><td className="text-muted">Delivery &amp; installation</td><td className="text-end">{money(charges)}</td></tr>
        <tr><th>Total</th><th className="text-end">{money(t.total + charges)}</th></tr></>} />
  );
}
