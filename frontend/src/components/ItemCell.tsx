/** An item in a table cell: its name, with the SKU beside it. Anything else about the line (supplier's name, order, billed quantity) has its own column. */
export function ItemCell({ l }: { l: any }) {
  return <>{l.item_name}{l.vname ? ` — ${l.vname}` : ''} <small className="text-muted text-nowrap">{l.sku}</small></>;
}

/** The supplier's own code / name for the item (shown in its own column, only when some line has one). */
export const theirText = (l: any): string => [l.sup_code, l.sup_name].filter(Boolean).join(' · ');
export const anyTheirs = (rows: any[]): boolean => rows.some((l) => theirText(l) !== '');
