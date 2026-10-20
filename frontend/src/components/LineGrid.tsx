import type { CSSProperties, ReactNode } from 'react';

/** One column of an items table as the company designed it (name, shown or not), from the form's schema. */
export type GridCol = { key: string; label: string; show: boolean; locked?: boolean; def?: string };

/** What a table knows how to draw for one column: its header look and its cell. */
export type CellDef<R> = { style?: CSSProperties; className?: string; cell: (row: R, i: number) => ReactNode };

/**
 * The columns to draw: the company's order and names, only the visible ones this table can draw.
 * Until the schema has arrived (or when it carries no grid) every known column is shown under its default name.
 */
export function gridCols(grid: GridCol[] | undefined, known: Record<string, string>): GridCol[] {
  if (!grid || !grid.length) return Object.entries(known).map(([key, label]) => ({ key, label, show: true }));
  return grid.filter((c) => c.show && c.key in known);
}

/**
 * The items table of a document, drawn from its column design: header, one row per line (cells from `defs` in the designed order),
 * a remove button and an optional totals block. Used by purchase orders, sales orders, stock adjustments and transfers.
 */
export default function LineGrid<R>({ title, addLabel, onAdd, cols, defs, rows, onRemove, footer, id }: {
  title: string; addLabel: string; onAdd: () => void; cols: GridCol[]; defs: Record<string, CellDef<R>>; rows: R[]; onRemove: (i: number) => void; footer?: ReactNode; id?: string;
}) {
  return (
    <div className="card mb-3">
      <div className="card-header d-flex justify-content-between align-items-center"><span>{title}</span>
        <button type="button" className="btn btn-sm btn-outline-primary" onClick={onAdd}><i className="bi bi-plus-lg" /> {addLabel}</button></div>
      <div className="table-responsive" style={{ overflow: 'visible' }}><table className="table mb-0 align-middle" id={id ?? 'lineTable'}>
        <thead><tr>{cols.map((c) => <th key={c.key} className={defs[c.key].className} style={defs[c.key].style}>{c.label}</th>)}<th style={{ width: 44 }} /></tr></thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              {cols.map((c) => <td key={c.key} className={defs[c.key].className}>{defs[c.key].cell(r, i)}</td>)}
              <td><button type="button" className="btn btn-sm btn-outline-danger" aria-label="Remove line" onClick={() => onRemove(i)}>×</button></td>
            </tr>
          ))}
        </tbody>
      </table></div>
      {footer && <div className="card-footer bg-transparent d-flex justify-content-end"><table className="table table-sm table-borderless mb-0" style={{ maxWidth: 360 }}><tbody>{footer}</tbody></table></div>}
    </div>
  );
}
