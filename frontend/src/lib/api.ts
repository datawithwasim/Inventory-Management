// One place that talks to the PHP backend (/api/v1/...). Same session cookie as the old pages, CSRF token in a header.
export type Msg = { type: string; msg: string };
export class ApiError extends Error {
  constructor(public status: number, public messages: Msg[], message: string) { super(message); }
}

const base = (document.querySelector('meta[name="app-base"]') as HTMLMetaElement | null)?.content ?? '';
let csrf = '';
export const setCsrf = (t: string) => { csrf = t; };
export const getCsrf = () => csrf;
/** Address of a legacy (PHP-rendered) page: used for screens not rebuilt in React yet. */
export const legacyUrl = (path: string) => base + (path.startsWith('/') ? path : '/' + path);
export const appBase = base + '/app';
/** Address of a screen of the app, for links that open in a new tab (printouts). */
export const appUrl = (path: string) => appBase + (path.startsWith('/') ? path : '/' + path);
/** Address of an API call, for things fetched as plain files (a barcode picture). */
export const apiUrl = (path: string) => `${base}/api/v1${path}`;

export type Reply<T = any> = { ok: boolean; data?: T; view?: string; redirect?: string; messages?: Msg[]; error?: string; [k: string]: any };

export async function api<T = any>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<Reply<T>> {
  const res = await fetch(`${base}/api/v1${path}`, {
    method, credentials: 'same-origin',
    headers: { Accept: 'application/json', ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}), ...(csrf ? { 'X-CSRF-Token': csrf } : {}) },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  let json: Reply<T> | null = null;
  try { json = await res.json(); } catch { /* not JSON */ }
  if (!json) throw new ApiError(res.status, [], 'The server sent an unexpected answer. Please try again.');
  // a refused form (422) is an answer the page shows, not a crash
  if (res.status === 401 && !/^\/(session|login|admin\/login|admin\/session|logout|admin\/logout)/.test(path)) window.dispatchEvent(new CustomEvent('inv-expired'));
  if (!res.ok && res.status !== 422) throw new ApiError(res.status, json.messages ?? [], json.error ?? 'Something went wrong.');
  return json;
}

export const get = <T = any>(path: string) => api<T>('GET', path);
export const post = <T = any>(path: string, body: unknown = {}) => api<T>('POST', path, body);

/** A form with a file in it (company logo): sent as multipart, the way a browser form does. */
export async function postForm<T = any>(path: string, form: FormData): Promise<Reply<T>> {
  const res = await fetch(`${base}/api/v1${path}`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', ...(csrf ? { 'X-CSRF-Token': csrf } : {}) }, body: form });
  let json: Reply<T> | null = null;
  try { json = await res.json(); } catch { /* not JSON */ }
  if (!json) throw new ApiError(res.status, [], 'The server sent an unexpected answer. Please try again.');
  if (!res.ok && res.status !== 422) throw new ApiError(res.status, json.messages ?? [], json.error ?? 'Something went wrong.');
  return json;
}
