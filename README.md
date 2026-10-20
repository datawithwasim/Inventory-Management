# Home Furnishing Inventory (SaaS)

Multi-tenant inventory management for home-furnishing businesses. PHP 8.1+ and MySQL 8 / MariaDB.
Each company (tenant) has its own users, roles and data; the platform owner manages companies and plans from the Super Admin panel.

## Status
- **Phase 1 – Foundation: done** (auth, tenants, roles & permissions, Super Admin panel, plans/limits, audit log)
- **Phase 2 – Items, stock & batches: done** (masters, warehouses, items (one item = one SKU; every colour / design / size is its own item) and bundles, stock ledger,
  batches where 1 batch = 1 roll, adjustments / opening stock, transfers, stock-takes, CSV import/export)
- **Racks / locations: done** (racks per warehouse, stock kept and moved per rack, "Stock by rack" search)
- **Phase 3 – Purchase: done** (suppliers, requisitions, purchase orders with optional approval, goods receipts with one batch per roll and rack,
  landed cost, bills with payment status, purchase returns)
- **Phase 4 – Sales: done** (customers & groups with price lists, sales orders that reserve stock, deliveries cut from a chosen roll and rack,
  invoices with payments and advances, customer returns, set/bundle sales)
- **Phase 5 – Customization: done** (company profile + logo, currency / date / digit formats, document number styles, workflow rules,
  your own words for menus and screens, custom fields on items / customers / suppliers, editable print templates for invoice,
  delivery note, purchase order)
- **Phase 6 – Reports & dashboard: done** (dashboard with period filter, net sales hero, trend chart, top items, stock value, ageing, low-stock lists;
  14 reports with filters, Excel / CSV export and print-to-PDF; low-stock → draft purchase orders)
- **Phase 8 – Extras: done** (barcode labels for items and rolls, one-click database backup, security hardening). 2FA, notifications and
  multi-language/currency were intentionally left out. (The JSON API under `/api/v1` exists only as the backend of the React screens.)
- **Modern look & make-it-yours: done** — new app shell (grouped collapsible menu, global search with Ctrl+K, quick-create, light/dark mode, phone drawer),
  Bootstrap/icons/fonts now bundled locally (no CDN needed). Companies can customise: Appearance (brand colour, menu style, spacing, default theme),
  Modules & menu (switch parts off), Edit page layout (Zoho-style builder, opened from the Edit page of any form; Detail page tab picks the record summary; every module has a record page (supplier, customer, item, warehouse, rack, requisition, PO, GRN, bill, returns, sales order, delivery, invoice, stock documents) with Overview / Timeline / Notes and a Related list; migration 011) — full-screen builder for all forms: sections with 1–3 columns, drag fields, create new custom fields from a 13-type palette, hide/require, widths, hints, labels left/top, line-item columns, preview, undo; needs migration 008 — Super Admin → System → Run updates), Custom fields (now created inside the Form designer — 15 types incl. multi-select, date & time, "Unique"; migration 009; the separate Settings page is gone) on items, customers, suppliers, sales orders and
  purchase orders), Names, Print templates. Each user can customise their Dashboard widgets and list columns.

- **Purchase masters: done** — fuller Supplier master (type, GSTIN/PAN, city/state, dispatch address, bank, credit limit, lead time, transport, extra contacts),
  Item master for Fabrics / Linen / Wallpaper / Carpets / Accessories (type, HSN; design no., composition, width, GSM, pattern and finish live on the **supplier item** the item is tagged to; migration 018), and a **supplier-wise rate list**
  (rate, discount, min qty, validity, history, CSV import) that fills the price on purchase orders and shows last-bought price, plus a Rate lists page in the Purchase menu and 4 purchase reports (supplier purchases & outstanding, rate comparison, rate history, price paid). Needs migration 010.

- **React interface: done** — every screen (dashboard, masters, items, purchase, sales, stock, reports, settings, users/roles, Super Admin) is a React app at `/app`
  (built files are committed in `public/ui/`, so the server needs no Node). **The site address opens the app**, and every old screen address (`/dashboard`, `/customers`, `/login` …) sends the browser to its twin under `/app`; including the password reset pages, customer groups, rate lists, imports, the Ctrl K search, the page layout designer and every printout (PO, invoice, delivery note, vouchers, transfer challan, report print, barcode labels). Only files stay server-made: CSV/Excel downloads, the backup download and the installer. Do **not** set `LEGACY_UI` in the live `.env` (it keeps the old HTML screens reachable for the automated tests only). See `frontend/README.md`.
- **Transfers between warehouses are tracked:** dispatch (stock leaves the sender and is *in transit*) → the other warehouse confirms what arrived and puts it on racks → shortages are settled (lost / sent back); challan, overdue alerts, report and `stock.receive` permission (migration 016).
- **Goods receipt by rolls:** one compact row per item, so a delivery of 10–15 items fits on one screen: write the billed quantity (200 m), then the rolls that really arrived as text (`4x50`, `50, 50, 48.5`, `3x50 + 48.5`). The row shows received / matches the bill / short / extra and a footer says how many items differ. A row's Rolls button opens a pop-up with the roll-by-roll editor (length, a different rack for one roll, the supplier's roll no.; Enter on the last length adds the next roll). Each roll becomes its own batch (`ITEM-B0001` …); the receipt keeps billed against received; up to 500 roll lines per receipt (migration 022).
- **One supplier invoice for several purchase orders:** when a supplier sends the goods of 2–3 (or more) orders on a single invoice, open Goods receipts → Receive goods, choose the supplier and press Add beside each of its open orders (or start from one order and add the others). Their due items join one receipt table (each tagged with its order number), you enter the invoice number once, and the stock, the one bill and the status of every order (partial / received) follow from that one receipt. Orders of different suppliers or other warehouses cannot be mixed; items that are on no order can still be added by hand (migration 023, `grn_pos`).
- **Lookup fields:** a new custom-field type, "Lookup (pick a record)", in the page-layout designer: choose what it points to (Item, Supplier, Customer, Warehouse, Rack, Product, Catalogue, Category, Brand) and the person filling the form searches and picks a record. It shows as the record's name (linked to its page) on detail pages and lists, and is checked against the company's own records. The supplier and customer pickers on the standard forms are now searchable lookups from the same registry (`core/Lookups.php`; add a source there and it is available everywhere) (migration 024).
- **Catalogues & products (showroom names):** a catalogue is a book (CARAVAN), a product is one design in it (CARAVAN-7) with its own price, and each product is sold from one item (the stock the warehouse counts). One item can be sold under several products (the same fabric in two books); a product may have no catalogue (ready-made goods). Sales orders, deliveries, invoices and their printouts show the product name; the warehouse voucher, stock, GRN and purchase keep the item. Import the sheet `Catalogue Name, Product Name, Item Name, Price` on the Products page. Sales summary can be grouped by product or catalogue (migration 021).
- **Print templates:** each company designs its own layouts for the invoice, delivery note and purchase order (Settings → Print templates): paper size and orientation, letterhead (logo position and size, company lines, heading, rule), and ordered sections (parties, items table with its own columns and style, totals with amount in words, notes, bank details, terms, own text blocks, signature lines, footer), watermark, colours and font; several templates per document, one used for printing, switchable on the print page (migration 020, `app/Models/PrintDoc.php`). The built-in Standard layout follows the older simple options.
- **Barcode labels:** a visual label designer (drag / resize / rotate text, Code 128, EAN-13, Code 39 and QR, boxes, logo, fields from the item, supplier item and roll), saved templates, ready-made sizes and A4 sheet layouts, print from a chosen start position, per-GRN roll labels (migration 019, `core/Qr.php`, `core/Barcode.php`).
- **Warehouse-wise users:** every user works at one warehouse (or "All warehouses" = head office; the Owner is never limited). Roles *Warehouse Incharge* and *Warehouse Operator* are tied to one warehouse; such users see and act on their own warehouse only (stock, racks, ledger, rolls, documents, reports, dashboard), only the receiving warehouse can receive a transfer and only the sender can cancel it. Any custom role can be marked "Warehouse staff" (migration 017, `core/Scope.php`).
- **POS removed** — this is an inventory app; counter billing is not part of it (migration 015).
- Before going live, follow **GO_LIVE_CHECKLIST.md**.

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
