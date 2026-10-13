import { Link } from 'react-router-dom';
import { money, qty } from '../lib/format';
import { saleLine, lineName } from '../lib/docs';
import { useSession } from '../lib/session';
import { Badge } from './ui';

/** Delivered lines with their roll and rack; the parts of a set are indented under it (shared by the delivery and invoice pages). */
export default function DeliveryRows({ items }: { items: any[] }) {
  const { term } = useSession();
  return (
    <table className="table mb-0 align-middle"><thead><tr><th>{term('item')}</th><th>{term('batch')}</th><th>{term('rack')}</th><th className="text-end">Qty</th><th className="text-end">Price</th><th className="text-end">Total</th></tr></thead><tbody>
      {items.map((l) => {
        const part = l.parent_id !== null && l.parent_id !== undefined; const c = saleLine(Number(l.qty), Number(l.unit_price), Number(l.discount_pct), Number(l.tax_rate));
        return (
          <tr key={l.id} className={part ? 'text-muted small' : ''}>
            <td>{part ? '  ↳ ' : ''}{lineName(l)}{l.product_name ? <div className="small text-muted">Item: {l.item_name}</div> : null} <small className="text-muted">{l.sku}</small>{Number(l.is_bundle) ? <> <Badge tone="info">Set</Badge></> : null}</td>
            <td>{l.batch_no ? <Link to={`/stock/batches/${l.batch_id}`}>{l.batch_no}</Link> : ''}</td><td>{Number(l.is_bundle) ? '' : l.rack ?? 'No rack'}</td>
            <td className="text-end">{qty(l.qty)} {l.unit}{Number(l.qty_returned) > 0 && <div className="small text-danger">returned {qty(l.qty_returned)}</div>}</td>
            <td className="text-end">{part ? '' : <>{money(l.unit_price)}{Number(l.discount_pct) ? <div className="small text-muted">−{qty(l.discount_pct)}%</div> : null}</>}</td>
            <td className="text-end">{part ? '' : money(c.total)}</td></tr>
        );
      })}
    </tbody></table>
  );
}
