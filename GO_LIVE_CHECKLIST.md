# Go-live checklist

Tick each line. Sections A–B are done on the server, C is a click-through on the live site, D–E are safety and rollback.

## A. Already verified (on the dev copy, before upload)

- [x] Every PHP file passes `php -l` (no syntax errors).
- [x] All 13 test suites pass: smoke, phase2, racks, purchase, sales, settings, reports, personalise, extras, robust, records, masters, api (≈ 700 checks).
- [x] `npm run build` type-checks and produces the same `public/ui/` that is committed (nothing left over).
- [x] Browser run-through with no console or network errors: sales (order → deliver → invoice → payment → return), stock (adjust, transfer, stock-take), reports (incl. low stock → draft POs), settings, users/roles, Super Admin (create company, open as owner, back to admin).
- [x] PHP 8.3 with `pdo_mysql, mbstring, gd, zip, fileinfo, json, openssl, curl` loaded.

## B. Server (cPanel)

Before anything: **take a backup** (Super Admin → System → Download backup, and copy the `storage/uploads/` folder).

1. [ ] **PHP version** 8.1 or higher (MultiPHP Manager). Extensions on: `pdo_mysql, mbstring, zip` (Excel export needs `zip`), `fileinfo`, `gd`.
2. [ ] **Upload** the project (see the file list in the last message, or upload the whole ZIP over the old files).
   - [ ] Keep your existing `.env` and `storage/` (do not overwrite).
   - [ ] **Do not upload** `frontend/node_modules/`, `.git/`, `tests/` (tests are not needed on the server).
   - [ ] `public/ui/` must contain the **new** `index.html` and exactly one `assets/index-*.js` (delete older `index-*.js` files there).
3. [ ] **Delete the removed POS files** if they are still on the server:
   `app/Controllers/PosController.php`, `views/app/pos/` (whole folder), `views/layouts/pos.php`, `public/assets/js/pos.js`.
4. [ ] **`.htaccess`** is the new one (it also blocks every `*.md` file). If the site is in a sub-folder, `RewriteBase` is not needed, but the folder must allow `.htaccess` (`AllowOverride All`).
5. [ ] **`storage/`** and `storage/uploads/`, `storage/logs/` are writable (755/775). Logo upload and logs depend on it.
6. [ ] **`.env`**: `APP_DEBUG=false`, DB details correct, `MAIL_FROM` set to a real address on your domain (password-reset mails use PHP `mail()`).
7. [ ] **Database updates**: sign in at `/admin/login` (or `/app/admin/login`) → **System → Run updates**.
   Expected pending list when coming from the older version: `013_supplier_products`, `014_supplier_items`, `015_remove_pos`. Afterwards the page must say "The database is up to date."
8. [ ] **HTTPS** is on for the domain (cookies carry the login; do not run the app on plain http).
9. [ ] Open `https://your-domain/app/` once with a hard refresh (Ctrl+F5) so the browser drops the old screens.

## C. Click-through on the live site (≈ 20 minutes)

Use a test company (Super Admin → Companies → New company) so real data is not touched. Delete or suspend it afterwards.

**Sign-in and shell**
- [ ] Super Admin sign-in works; Dashboard, Companies, Plans, System, Audit log all open.
- [ ] Create a company; "Open as owner" lands on the company dashboard with the support bar; "Back to admin" returns to Companies.
- [ ] Company owner signs in at `/app/login`; wrong password shows an error; sign-out works.
- [ ] Menu shows Inventory / Purchase / Sales / Insights; **no POS** anywhere (menu, "New" button, Settings → Modules, Roles).

**Settings first (so formats are right)**
- [ ] Company profile: name, address, tax number saved; **logo upload** shows in the menu and on a printed invoice.
- [ ] Currency & dates: pick symbol/decimals/date format → amounts and dates change everywhere after Save.
- [ ] Document numbers, Names (labels), Appearance (colour changes live), Modules, Rules — each saves and shows the green message.
- [ ] Masters: add a category, brand, unit, tax. A unit used by an item cannot be deleted.
- [ ] Roles: create "Staff-like" role; Users: add a user on it, sign in as that user — a page they may not open (e.g. Add user) shows the yellow "You do not have permission to open this page" message.

**Stock set-up**
- [ ] Warehouse added; racks added as a range (A-01 … A-10).
- [ ] Item with variants, "Track by roll" on; Item without roll tracking; a Set (bundle) of two items.
- [ ] Stock adjustment (opening stock): fabric creates a roll with supplier lot and rack; non-roll item adds quantity.
- [ ] Transfer between two racks and between two warehouses; stock-take: count, save, post — differences appear in the ledger.
- [ ] Stock overview, Stock by rack, Rolls list + roll page history, Ledger filters.

**Purchase**
- [ ] Supplier with rate list and "Supplier items" (their name/code linked to our item).
- [ ] PO (with approval on/off as per your rule) → receive goods (one roll per line, rack, supplier lot) → bill → payment → status "Paid".
- [ ] Purchase return reduces stock and credits the bill; requisition → "Turn into PO".

**Sales**
- [ ] Customer; sales order with customer price/discount; Confirm reserves stock (free stock drops).
- [ ] Deliver: roll and rack suggested; fabric from two rolls shows the shade warning (or is blocked, per your rule); partial delivery leaves the order "Partly delivered".
- [ ] Invoice created; payment (partial, then full); advance taken on the order is adjusted on the invoice.
- [ ] Sales return puts goods back on a rack; "Put back" unticked does not touch stock.
- [ ] Prints open in a new tab and look right: **invoice, delivery note, purchase order, stock-out voucher** (adjustment/transfer/delivery) — check logo, address, tax number, roll numbers.

**Reports and exports**
- [ ] Dashboard figures match the Sales register / Stock summary for the same period.
- [ ] A report: Excel and CSV download open correctly; Print / PDF opens.
- [ ] Low stock → "Create draft purchase orders" makes POs per supplier.
- [ ] Super Admin → System → **Download backup** gives a `.sql` file (open it: it must contain `CREATE TABLE`).

**Limits**
- [ ] Plan limits: a plan with 1 user refuses a second user; "0 = unlimited" really is unlimited.

## D. Safety

- [ ] **Change the Super Admin password** (the seeded `ChangeMe123!` must not exist on the live site).
- [ ] `https://your-domain/.env`, `/storage/logs/mail.log`, `/config/config.php`, `/README.md`, `/GO_LIVE_CHECKLIST.md`, `/tests/` all answer **403/404**.
- [ ] `/install` is locked (`storage/installed.lock` exists) and answers **404**, not the installer.
- [ ] Test companies/users created in section C are suspended or deleted.
- [ ] A second copy of the backup is stored **off the server**.

## E. If something goes wrong

- **White page / "Unexpected answer" in the app:** check `storage/logs/` and the cPanel error log; the usual causes are PHP < 8.1, a missing extension, or `storage/` not writable.
- **Screens look old after upload:** hard refresh; confirm `public/ui/index.html` points to the same `index-*.js` that exists in `public/ui/assets/`.
- **A screen says "Opening…" and jumps to a classic page:** that screen has no React version (only the page-layout designer and print pages are PHP) — expected.
- **Roll back:** restore the old files, then restore the `.sql` backup in phpMyAdmin into an **empty** database (migrations are not reversible).

## F. Known and intentional

- Page layout designer / custom fields (Settings → "Page layout & custom fields") and all print pages are still PHP pages.
- "Walk-in customer" is kept for deliveries made without a registered customer.
- Invoices, payments and customer/supplier statements remain (basic receivable/payable tracking). Remove them only by a separate decision.
- The browser may show a favicon 404 in the console; it has no effect.
