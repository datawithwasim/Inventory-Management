// Screens already rebuilt in React. Every other menu entry still opens the PHP page, so the app can move over module by module.
export const REACT_PREFIXES = ['/dashboard', '/customers', '/suppliers', '/purchase/supplier-items', '/purchase/rates', '/items', '/purchase/orders', '/purchase/grns', '/purchase/bills', '/purchase/requisitions', '/purchase/returns', '/warehouses', '/locations', '/stock', '/sales/orders', '/sales/deliveries', '/sales/invoices', '/sales/returns', '/reports', '/labels', '/settings', '/users', '/roles', '/masters', '/profile'];
export const isReact = (path: string) => REACT_PREFIXES.some((p) => path === p || path.startsWith(p + '/'));

/** A PHP address (with the site's folder in front) as an in-app path, e.g. "/site/customers/3" -> "/customers/3". */
export const localPath = (url: string) => {
  const base = (document.querySelector('meta[name="app-base"]') as HTMLMetaElement | null)?.content ?? '';
  const p = url.replace(/^https?:\/\/[^/]+/, '');
  return base && p.startsWith(base) ? p.slice(base.length) || '/' : p;
};

/** The browser address as a router path: no site folder and no /app in front ("/site/app/customers/3" -> "/customers/3"). */
export const routerPath = (url: string) => localPath(url).replace(/^\/app(?=\/|$)/, '') || '/';

/** Files, printouts and little lookups under those addresses stay server-made: they are not screens. */
const FILES = /\/(export|template|barcode|data|backup|logo|lookup)(\/|$)/;
/** Is this address one of the app's screens (so a plain link to it should open inside the app)? */
export const isScreen = (path: string, _search = '') => isReact(path) && !FILES.test(path);
