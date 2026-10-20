import type { ReactNode } from 'react';
import { gridCols, type GridCol } from './LineGrid';
import { useSchema } from './RecordForm';

/** What a detail page knows how to draw for one column. */
export type DetailDef<R> = { className?: string; cell: (row: R, i: number) => ReactNode };

/** The columns of a document's detail-page items table as the company designed them (`known`: every column this page can draw, with its standard name). */
export function useViewCols(entity: string, known: Record<string, string>): GridCol[] {
  const schema = useSchema(entity);
  return gridCols(schema.data?.viewgrid, known);
}

/** The items table of a detail page, drawn from its column design; an optional totals block sits under it. */
export default function DetailTable<R>({ cols, defs, rows, rowKey, rowClass, footer, id }: {
  cols: GridCol[]; defs: Record<string, DetailDef<R>>; rows: R[]; rowKey: (r: R) => string | number; rowClass?: (r: R) => string | undefined; footer?: ReactNode; id?: string;
}) {
  return (
    <div className="card mb-3" id={id ?? 'items'}>
      <div className="table-responsive"><table className="table mb-0 align-middle">
        <thead><tr>{cols.map((c) => <th key={c.key} className={defs[c.key].className}>{c.label}</th>)}</tr></thead>
        <tbody>{rows.map((r, i) => <tr key={rowKey(r)} className={rowClass?.(r)}>{cols.map((c) => <td key={c.key} className={defs[c.key].className}>{defs[c.key].cell(r, i)}</td>)}</tr>)}</tbody>
      </table></div>
      {footer && <div className="card-footer bg-transparent d-flex justify-content-end"><table className="table table-sm table-borderless mb-0" style={{ maxWidth: 360 }}><tbody>{footer}</tbody></table></div>}
    </div>
  );
}
