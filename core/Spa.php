<?php
declare(strict_types=1);

namespace Core;

/**
 * The screens are the app's, at their plain addresses (/purchase/orders, not /app/purchase/orders): a browser that opens
 * one gets the app's page and the app shows the screen. Downloads and exports stay what they are: files the server makes.
 * Printouts (invoice, PO, labels …) are pages of the app. The older /app/... addresses send the browser to the plain one.
 * LEGACY_UI=1 in .env keeps the classic screens reachable (the automated tests read them as HTML).
 */
final class Spa
{
    /** Address starts that the app shows. Keep in step with frontend/src/lib/routes.ts. */
    public const PREFIXES = ['/dashboard', '/customers', '/catalogues', '/products', '/launches', '/suppliers', '/purchase/supplier-items', '/purchase/rates', '/items', '/purchase/orders', '/purchase/grns', '/purchase/bills',
        '/purchase/requisitions', '/purchase/returns', '/warehouses', '/locations', '/stock', '/sales/orders', '/sales/deliveries', '/sales/invoices', '/sales/returns', '/reports',
        '/labels', '/settings', '/users', '/roles', '/masters', '/profile', '/login', '/forgot-password', '/reset-password'];

    /** Downloads and little lookups under those prefixes: files, not screens. (Printouts are screens of the app too.) */
    private const KEEP = '~(/(export|template|barcode|data|backup|logo|lookup|placement|batch-options)(/|$))|^/lookup/~';

    /** Is this address one of the app's screens (the app's page answers it), rather than a file or a lookup? */
    public static function isScreen(string $path): bool
    {
        if ($path === '/') return true;
        foreach (self::PREFIXES as $p) {
            if ($path !== $p && !str_starts_with($path, $p . '/')) continue;
            return !preg_match(self::KEEP, $path);
        }
        return false;
    }

    /** The plain address of an older /app/... link ("/app/customers/3" -> "/customers/3"), or null when it is not one. */
    public static function plainFor(string $path): ?string
    {
        if ($path !== '/app' && !str_starts_with($path, '/app/')) return null;
        return '/' . ltrim(substr($path, 4), '/');
    }
}
