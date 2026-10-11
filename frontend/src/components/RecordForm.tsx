import { Fragment, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { get } from '../lib/api';
import ItemSearch from './ItemSearch';

export type Field = {
  key: string; label: string; type: string; required: boolean; help?: string; w?: string; custom?: boolean; sys?: boolean; cfId?: number; choices?: string[]; source?: string;
};
export type Section = { id: string; title: string; cols: number; fields: string[] };
export type Schema = { entity: string; title: string; style: string; sections: Section[]; fields: Record<string, Field>; canDesign: boolean };

/** Pick-list contents, cached across forms. */
function useOptions(source?: string) {
  return useQuery({ queryKey: ['options', source], enabled: !!source, staleTime: 60_000, queryFn: async () => (await get(`/options/${source}`)).options as { id: string | number; name: string }[] });
}

function Select({ field, value, onChange, disabled }: { field: Field; value: string; onChange: (v: string) => void; disabled?: boolean }) {
  const o = useOptions(field.source);
  return (
    <select className="form-select" value={value} required={field.required} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
      <option value="">{field.required ? 'Choose…' : '—'}</option>
      {(o.data ?? []).map((x) => <option key={x.id} value={String(x.id)}>{x.name}</option>)}
    </select>
  );
}

/** One input, by the field's type. Values travel as strings, the way the PHP forms post them. */
function Input({ field, value, onChange, label, onPatch, locked }: { field: Field; value: string | string[]; onChange: (v: any) => void; label?: string; onPatch?: (patch: Record<string, any>) => void; locked?: boolean }) {
  const v = Array.isArray(value) ? '' : value ?? '';
  const common = { className: 'form-control', value: v, required: field.required, onChange: (e: any) => onChange(e.target.value) };
  switch (field.type) {
    case 'textarea': return <textarea {...common} rows={2} />;
    case 'email': return <input type="email" {...common} />;
    case 'phone': return <input type="tel" {...common} />;
    case 'url': return <input type="url" {...common} />;
    case 'date': return <input type="date" {...common} />;
    case 'datetime': return <input type="datetime-local" {...common} value={v.replace(' ', 'T')} />;
    case 'number': return <input type="number" step="1" {...common} />;
    case 'decimal': case 'currency': case 'money': return <input type="number" step="0.01" {...common} />;
    case 'percent': return <input type="number" step="0.01" min="0" max="100" {...common} />;
    case 'select': return <Select field={field} value={v} onChange={onChange} disabled={locked} />;
    case 'variant': return <ItemSearch value={v} label={label ?? ''} disabled={locked} onPick={(p) => onPatch?.({ [field.key]: p ? String(p.id) : '', [field.key + ':label']: p ? p.label : '' })} />;
    case 'dropdown': return <select className="form-select" value={v} required={field.required} onChange={(e) => onChange(e.target.value)}><option value="">—</option>{(field.choices ?? []).map((c) => <option key={c}>{c}</option>)}</select>;
    case 'radio': return <div>{(field.choices ?? []).map((c) => <div className="form-check form-check-inline" key={c}><input className="form-check-input" type="radio" checked={v === c} onChange={() => onChange(c)} id={`${field.key}-${c}`} /><label className="form-check-label" htmlFor={`${field.key}-${c}`}>{c}</label></div>)}</div>;
    case 'multiselect': { const sel = Array.isArray(value) ? value : []; return <div>{(field.choices ?? []).map((c) => <div className="form-check" key={c}><input className="form-check-input" type="checkbox" checked={sel.includes(c)} id={`${field.key}-${c}`} onChange={(e) => onChange(e.target.checked ? [...sel, c] : sel.filter((x) => x !== c))} /><label className="form-check-label" htmlFor={`${field.key}-${c}`}>{c}</label></div>)}</div>; }
    case 'checkbox': return <div className="form-check"><input className="form-check-input" type="checkbox" checked={v === '1'} id={`f-${field.key}`} onChange={(e) => onChange(e.target.checked ? '1' : '0')} /><label className="form-check-label" htmlFor={`f-${field.key}`}>Yes</label></div>;
    default: return <input type="text" {...common} />;
  }
}

const widthOf = (f: Field, cols: number) => (f.w ? Number(f.w) : cols === 3 ? 33.3333 : 100 / (cols || 2));

/**
 * The designed form of an entity: sections, columns, widths, own labels and fields, from /schema/<entity>.
 * `values` holds standard fields by key and custom fields as `cf:<id>`; `onChange` gets the whole map.
 */
export function SchemaForm({ schema, values, onChange, locked = [] }: { schema: Schema; values: Record<string, any>; onChange: (v: Record<string, any>) => void; locked?: string[] }) {
  const set = (k: string, v: any) => onChange({ ...values, [k]: v });
  return (
    <div className={`ffgrid ${schema.style === 'left' ? 'ff-left' : ''}`}>
      {schema.sections.map((s) => {
        const fields = s.fields.map((k) => schema.fields[k]).filter((f) => f && !f.sys);
        if (!fields.length) return null;
        return (
          <Fragment key={s.id}>
            {s.title && <div className="ff-section"><span>{s.title}</span></div>}
            {fields.map((f) => (
              <div key={f.key} className="mb-3" style={{ flex: `0 0 ${widthOf(f, s.cols)}%`, maxWidth: `${widthOf(f, s.cols)}%` }}>
                <label className="form-label">{f.label}{f.required && <span className="text-danger"> *</span>}</label>
                <Input field={f} value={values[f.key]} onChange={(v) => set(f.key, v)} label={values[f.key + ':label']} onPatch={(patch) => onChange({ ...values, ...patch })} locked={locked.includes(f.key)} />
                {f.help && <div className="form-text">{f.help}</div>}
              </div>
            ))}
          </Fragment>
        );
      })}
    </div>
  );
}

/** Loads a schema and keeps form state; `initial` seeds the values once the data is there. */
export function useSchema(entity: string) {
  return useQuery({ queryKey: ['schema', entity], staleTime: 30_000, queryFn: async () => (await get(`/schema/${entity}`)).schema as Schema });
}

export function useFormState(initial: Record<string, any> | null) {
  const [values, setValues] = useState<Record<string, any>>({});
  const [seeded, setSeeded] = useState(false);
  useEffect(() => { if (initial && !seeded) { setValues(initial); setSeeded(true); } }, [initial, seeded]);
  return { values, setValues, ready: seeded };
}

/** Standard field keys → posted names; custom fields travel as cf[<id>]. */
export function toPayload(schema: Schema, values: Record<string, any>): Record<string, any> {
  const out: Record<string, any> = { cf: {} };
  for (const k of Object.keys(schema.fields)) {
    const f = schema.fields[k];
    if (f.sys) continue;
    if (f.custom && f.cfId) out.cf[f.cfId] = values[k] ?? '';
    else out[k] = values[k] ?? '';
  }
  return out;
}

export function FormActions({ saving, cancel, children }: { saving: boolean; cancel: () => void; children?: ReactNode }) {
  return <div className="d-flex gap-2">{children}<button type="button" className="btn btn-outline-secondary" onClick={cancel}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving…' : 'Save'}</button></div>;
}

export function submitHandler(fn: () => Promise<void>) { return (e: FormEvent) => { e.preventDefault(); void fn(); }; }
export const useMemoValues = useMemo;
