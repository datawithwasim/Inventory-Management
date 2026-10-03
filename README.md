# Home Furnishing Inventory (SaaS)

Multi-tenant inventory management for home-furnishing businesses. PHP 8.1+ and MySQL 8 / MariaDB.
Each company (tenant) has its own users, roles and data; the platform owner manages companies and plans from the Super Admin panel.

## Status
- **Phase 1 – Foundation: done** (auth, tenants, roles & permissions, Super Admin panel, plans/limits, audit log)
- **Phase 2 – Items, stock & batches: done** (masters, warehouses, items with variants and bundles, stock ledger,
  batches where 1 batch = 1 roll/thaan, adjustments / opening stock, transfers, stock-takes, CSV import/export)
- **Racks / locations: done** (racks per warehouse, stock kept and moved per rack, "Stock by rack" search)
- **Phase 3 – Purchase: done** (suppliers, requisitions, purchase orders with optional approval, goods receipts with one batch per roll and rack,
  landed cost, bills with payment status, purchase returns)
- **Phase 4 – Sales: done** (customers & groups with price lists, quotations, sales orders that reserve stock, deliveries cut from a chosen roll and rack,
  invoices with payments and advances, customer returns, set/bundle sales, POS with barcode scanning)
- **Phase 5 – Customization: done** (company profile + logo, currency / date / digit formats, document number styles, workflow rules,
  your own words for menus and screens, custom fields on items / customers / suppliers, editable print templates for invoice, receipt,
  delivery note, purchase order and quotation)
- **Phase 6 – Reports & dashboard: done** (dashboard with period filter, net sales hero, trend chart, top items, stock value, ageing, low-stock lists;
  14 reports with filters, Excel / CSV export and print-to-PDF; low-stock → draft purchase orders)
- **Phase 8 – Extras: done** (barcode labels for items and rolls, one-click database backup, security hardening). REST API, 2FA, notifications and
  multi-language/currency were intentionally left out.

### Updating an installed copy (cPanel)
Upload the new files over the old ones (keep your `.env`), then sign in as Super Admin → **System → Run updates**.

## Deploy on cPanel (no terminal needed)
1. **PHP version:** cPanel → *MultiPHP Manager* → select PHP 8.1 or higher for your domain.
2. **Database:** cPanel → *MySQL Databases* → create a database and a user, and add the user to the database with *All Privileges*. Note the full names (they carry your cPanel username prefix).
3. **Upload:** download this branch as a ZIP, then upload and extract it in `public_html` (or in a sub-folder such as `public_html/inventory`). The files (`index.php`, `.htaccess`, `core/`, `app/` …) must sit directly in that folder. The bundled `.htaccess` hides code, config and `.env` from the web.
4. **Install:** open `https://your-domain/install`, enter the database details and your Super Admin login. The installer creates the tables, writes `.env` and locks itself.
5. Sign in at `/admin/login`, create your first company and give it a plan.

Better, if cPanel lets you: point the domain's document root at the `public/` folder (then nothing but `public/` is web-visible).
The folder `storage/` must be writable (company logos are stored in `storage/uploads/`, outside the web folder).
Password-reset emails use PHP `mail()` on the installer-generated `.env`; if sending fails they are logged to `storage/logs/mail.log`.

## Setup (command line / local development)
```bash
cp .env.example .env            # set DB_* and the first Super Admin credentials
php database/migrate.php        # create tables
php database/seed.php           # default plans + first Super Admin
php -S 127.0.0.1:8000 -t public public/index.php
```
- Company login: `/login`  ·  Super Admin: `/admin/login`
- Production: point the web server document root at `public/` (an Apache `.htaccess` is included).
- Password-reset emails are written to `storage/logs/mail.log` until an SMTP driver is added.
- Change the seeded Super Admin password after first login.

## Tests
```bash
php -S 127.0.0.1:8099 -t public public/index.php &
php tests/smoke.php http://127.0.0.1:8099     # phase 1
php tests/phase2.php http://127.0.0.1:8099    # phase 2
php tests/racks.php http://127.0.0.1:8099     # racks / locations
php tests/purchase.php http://127.0.0.1:8099  # phase 3
php tests/sales.php http://127.0.0.1:8099     # phase 4
php tests/settings.php http://127.0.0.1:8099  # phase 5
php tests/reports.php http://127.0.0.1:8099   # phase 6
php tests/extras.php http://127.0.0.1:8099    # phase 8 (needs local `mysql -uroot` for the restore check)  (run all against a throw-away database)
```

## Layout
`core/` framework · `app/` company app · `app/Admin/` Super Admin · `views/` templates · `database/migrations/` SQL · `config/routes.php` routes.

Every tenant-owned model extends `Core\Model`, which forces `tenant_id` on every query.
