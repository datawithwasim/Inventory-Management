import { Link } from 'react-router-dom';
import { money, qty } from '../lib/format';
import { saleLine, lineName } from '../lib/docs';
import { useSession } from '../lib/session';
import { Badge } from './ui';
import DetailTable, { useViewCols, type DetailDef } from './DetailTable';

/** Delivered lines with their roll and rack, drawn from the column design of the delivery or invoice; the parts of a set are indented under it. */
export default function DeliveryRows({ items, entity, id }: { items: any[]; entity: 'delivery' | 'invoice'; id?: string }) {
  const { term } = useSession();
  const cols = useViewCols(entity, { item: term('item'), batch: term('batch'), rack: term('rack'), qty: 'Qty', price: 'Price', total: 'Total' });
  const part = (l: any) => l.parent_id !== null && l.parent_id !== undefined;
  const calc = (l: any) => saleLine(Number(l.qty), Number(l.unit_price), Number(l.discount_pct), Number(l.tax_rate));
  const defs: Record<string, DetailDef<any>> = {
    item: { cell: (l) => <>{part(l) ? '  ↳ ' : ''}{lineName(l)}{l.product_name ? <div className="small text-muted">Item: {l.item_name}</div> : null} <small className="text-muted">{l.sku}</small>{Number(l.is_bundle) ? <> <Badge tone="info">Set</Badge></> : null}</> },
    batch: { cell: (l) => l.batch_no ? <Link to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link> : '' },
    rack: { cell: (l) => Number(l.is_bundle) ? '' : l.rack ?? 'No rack' },
    qty: { className: 'text-end', cell: (l) => <>{qty(l.qty)} {l.unit}{Number(l.qty_returned) > 0 && <div className="small text-danger">returned {qty(l.qty_returned)}</div>}</> },
    price: { className: 'text-end', cell: (l) => part(l) ? '' : <>{money(l.unit_price)}{Number(l.discount_pct) ? <div className="small text-muted">−{qty(l.discount_pct)}%</div> : null}</> },
    total: { className: 'text-end', cell: (l) => part(l) ? '' : money(calc(l).total) },
  };
  return <DetailTable id={id} cols={cols} rows={items} rowKey={(l: any) => l.id} rowClass={(l) => part(l) ? 'text-muted small' : undefined} defs={defs} />;
}
