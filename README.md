# Home Furnishing Inventory (SaaS)

Multi-tenant inventory management for home-furnishing businesses. PHP 8.1+ and MySQL 8 / MariaDB.
Each company (tenant) has its own users, roles and data; the platform owner manages companies and plans from the Super Admin panel.

## Status
- **Phase 1 – Foundation: done** (auth, tenants, roles & permissions, Super Admin panel, plans/limits, audit log)
- **Phase 2 – Items, stock & batches: done** (masters, warehouses, items with variants and bundles, stock ledger,
  batches where 1 batch = 1 roll, adjustments / opening stock, transfers, stock-takes, CSV import/export)
- **Racks / locations: done** (racks per warehouse, stock kept and moved per rack, "Stock by rack" search)
- **Phase 3 – Purchase: done** (suppliers, requisitions, purchase orders with optional approval, goods receipts with one batch per roll and rack,
  landed cost, bills with payment status, purchase returns)
- **Phase 4 – Sales: done** (customers & groups with price lists, sales orders that reserve stock, deliveries cut from a chosen roll and rack,
  invoices with payments and advances, customer returns, set/bundle sales, POS with barcode scanning)
- **Phase 5 – Customization: done** (company profile + logo, currency / date / digit formats, document number styles, workflow rules,
  your own words for menus and screens, custom fields on items / customers / suppliers, editable print templates for invoice, receipt,
  delivery note, purchase order)
- **Phase 6 – Reports & dashboard: done** (dashboard with period filter, net sales hero, trend chart, top items, stock value, ageing, low-stock lists;
  14 reports with filters, Excel / CSV export and print-to-PDF; low-stock → draft purchase orders)
- **Phase 8 – Extras: done** (barcode labels for items and rolls, one-click database backup, security hardening). REST API, 2FA, notifications and
  multi-language/currency were intentionally left out.
- **Modern look & make-it-yours: done** — new app shell (grouped collapsible menu, global search with Ctrl+K, quick-create, light/dark mode, phone drawer),
  Bootstrap/icons/fonts now bundled locally (no CDN needed). Companies can customise: Appearance (brand colour, menu style, spacing, default theme),
  Modules & menu (switch parts off), Edit page layout (Zoho-style builder, opened from the Edit page of any form; Detail page tab picks the record summary; every module has a record page (supplier, customer, item, warehouse, rack, requisition, PO, GRN, bill, returns, sales order, delivery, invoice, stock documents) with Overview / Timeline / Notes and a Related list; migration 011) — full-screen builder for all forms: sections with 1–3 columns, drag fields, create new custom fields from a 13-type palette, hide/require, widths, hints, labels left/top, line-item columns, preview, undo; needs migration 008 — Super Admin → System → Run updates), Custom fields (now created inside the Form designer — 15 types incl. multi-select, date & time, "Unique"; migration 009; the separate Settings page is gone) on items, customers, suppliers, sales orders and
  purchase orders), Names, Print templates. Each user can customise their Dashboard widgets and list columns.

- **Purchase masters: done** — fuller Supplier master (type, GSTIN/PAN, city/state, dispatch address, bank, credit limit, lead time, transport, extra contacts),
  Item master for Fabrics / Linen / Wallpaper / Carpets / Accessories (type-aware attributes, HSN, colour & size per variant), and a **supplier-wise rate list**
  (rate, discount, min qty, validity, history, CSV import) that fills the price on purchase orders and shows last-bought price, plus 4 purchase reports (supplier purchases & outstanding, rate comparison, rate history, price paid). Needs migration 010.

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
php tests/personalise.php http://127.0.0.1:8099 # personalisation layer
php tests/robust.php http://127.0.0.1:8099 /path/to/server.log # every route with junk input
php tests/extras.php http://127.0.0.1:8099    # phase 8 (needs local `mysql -uroot` for the restore check)  (run all against a throw-away database)
```

## Layout
`core/` framework · `app/` company app · `app/Admin/` Super Admin · `views/` templates · `database/migrations/` SQL · `config/routes.php` routes.

Every tenant-owned model extends `Core\Model`, which forces `tenant_id` on every query.
