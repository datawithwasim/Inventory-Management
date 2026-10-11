// Screens already rebuilt in React. Every other menu entry still opens the PHP page, so the app can move over module by module.
export const REACT_PREFIXES = ['/dashboard', '/customers', '/suppliers', '/purchase/supplier-items', '/items', '/purchase/orders', '/purchase/grns', '/purchase/bills', '/purchase/requisitions', '/purchase/returns', '/warehouses', '/locations', '/stock'];
export const isReact = (path: string) => REACT_PREFIXES.some((p) => path === p || path.startsWith(p + '/'));

/** A PHP address (with the site's folder in front) as an in-app path, e.g. "/site/customers/3" -> "/customers/3". */
export const localPath = (url: string) => {
  const base = (document.querySelector('meta[name="app-base"]') as HTMLMetaElement | null)?.content ?? '';
  const p = url.replace(/^https?:\/\/[^/]+/, '');
  return base && p.startsWith(base) ? p.slice(base.length) || '/' : p;
};
