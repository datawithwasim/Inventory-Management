<?php
declare(strict_types=1);

namespace Core;

/**
 * The screens now live in the app (/app/...). A browser that opens a classic screen address is sent to its twin there.
 * Downloads and exports stay what they are: files the server makes. Printouts (invoice, PO, labels …) are pages of the app.
 * LEGACY_UI=1 in .env keeps the classic screens reachable (the automated tests read them as HTML).
 */
final class Spa
{
    /** Address starts that the app shows. Keep in step with frontend/src/lib/routes.ts. */
    public const PREFIXES = ['/dashboard', '/customers', '/catalogues', '/products', '/suppliers', '/purchase/supplier-items', '/purchase/rates', '/items', '/purchase/orders', '/purchase/grns', '/purchase/bills',
        '/purchase/requisitions', '/purchase/returns', '/warehouses', '/locations', '/stock', '/sales/orders', '/sales/deliveries', '/sales/invoices', '/sales/returns', '/reports',
        '/labels', '/settings', '/users', '/roles', '/masters', '/profile', '/login', '/forgot-password', '/reset-password', '/admin'];

    /** Downloads and little lookups under those prefixes: files, not screens. (Printouts are screens of the app too.) */
    private const KEEP = '~(/(export|template|barcode|data|backup|logo|lookup|placement|batch-options)(/|$))|^/lookup/~';

    public static function redirectFor(string $path, array $query = []): ?string
    {
        if ($path === '/') return '/app/';
        foreach (self::PREFIXES as $p) {
            if ($path !== $p && !str_starts_with($path, $p . '/')) continue;
            if (preg_match(self::KEEP, $path)) return null;
            if (str_starts_with($path, '/admin/system/backup')) return null;
            return '/app' . $path;
        }
        return null;
    }
}
