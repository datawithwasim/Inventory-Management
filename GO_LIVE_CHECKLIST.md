# Go-live checklist

Tick each line. Sections A–B are done on the server, C is a click-through on the live site, D–E are safety and rollback.

## A. Already verified (on the dev copy, before upload)

- [x] Every PHP file passes `php -l` (no syntax errors).
- [x] All 13 test suites pass: smoke, phase2, racks, purchase, sales, settings, reports, personalise, extras, robust, records, masters, api (≈ 780 checks).
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
   Expected pending list when coming from the older version: `013_supplier_products`, `014_supplier_items`, `015_remove_pos`, `016_transfer_in_transit`, `017_user_warehouse`, `018_one_item_one_sku`, `019_label_templates`, `020_print_templates`, `021_catalogues_products`, `022_grn_billed_qty`, `023_grn_purchase_orders`. Afterwards the page must say "The database is up to date."
8. [ ] **HTTPS** is on for the domain (cookies carry the login; do not run the app on plain http).
9. [ ] Open `https://your-domain/` (it should land on `/app/`) once with a hard refresh (Ctrl+F5), and check `.env` does **not** contain `LEGACY_UI=1`.

## C. Click-through on the live site (≈ 20 minutes)

Use a test company (Super Admin → Companies → New company) so real data is not touched. Delete or suspend it afterwards.

**Sign-in and shell**
- [ ] Super Admin sign-in works; **"Forgot password?"** on the admin login mails a reset link (check the spam folder; if no mail arrives, the link is in `storage/logs/mail.log`, or run `php database/reset-admin.php <email> '<new password>'` from the cPanel Terminal); Dashboard, Companies, Plans, System, Audit log all open.
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
- [ ] **One item = one SKU** (no variants): every colour / design / size is its own item (e.g. "Royal Velvet Wine 54in", "Royal Velvet Teal 54in"). Item form: name, type, category, unit, tax, SKU, barcode, sale price, roll tracking. A second SKU on one item is refused. Then tag it with a **Supplier item**, which carries the supplier's name/code, design no., composition, width, GSM, pattern, finish, rate, minimum order and lead time (the item page shows them). Also: an item without roll tracking; a Set (bundle) of two items. Import/Export CSV is one row per item.
- [ ] Stock adjustment (opening stock): fabric creates a roll with supplier lot and rack; non-roll item adds quantity.
- [ ] Transfer between two racks of ONE warehouse: instant ("Moved").
- [ ] Transfer between TWO warehouses (transport, vehicle, LR no., expected date): status **In transit**; the sender's stock drops, the receiver's does not rise yet; Stock page shows the quantity under "In transit"; the roll shows "In transit" (not "Finished").
- [ ] Challan prints (logo, both warehouse addresses, transport, blank "Qty received / Put on rack" columns, signatures).
- [ ] Receiver (a user with the **Receive** permission) opens the transfer → Receive goods → enters what arrived and the rack → stock appears on that rack, status "Partly received" or "Received". Receive the rest in a second go.
- [ ] Shortage: settle the missing quantity as "lost" and as "send back" → status "Received (shortage)"; History lists every step with who did it.
- [ ] Cancel a fresh dispatch → every roll is back on the rack it left.
- [ ] **Warehouse-wise users** (Settings → Users): adding a user asks "Works at" — a warehouse, or "All warehouses (head office)". The roles **Warehouse Incharge** and **Warehouse Operator** must be tied to one warehouse (head office is refused). Existing users keep working as head office until you edit them.
- [ ] Sign in as an operator of warehouse B: Stock, Stock by rack, Ledger, Rolls, Racks and Warehouses show **only B**; the Dashboard shows only B's stock and transfers (no sales / purchase / money figures); Reports lists only warehouse reports and the warehouse filter is fixed to B.
- [ ] Transfer A → B: the operator of **A** sees it but cannot receive it; the operator of **B** can receive it but cannot cancel it; an operator of a third warehouse C does not see it at all (404). Settling a shortage needs "Adjust" and either side.
- [ ] An operator of B cannot add stock, send stock or receive goods for warehouse A; cannot add or delete warehouses or change the default.
- [ ] A warehouse that still has people cannot be deleted or made inactive.
- [ ] A dispatcher without the Receive permission cannot receive; a receiver cannot dispatch or settle (Settings → Roles → Stock: View / Adjust / Transfer / **Receive**).
- [ ] Reports → "Transfers: in transit & shortages"; overdue transfers (past expected date) show in red.
- [ ] **Barcode labels** (Inventory → Barcode labels): pick items or rolls, choose a ready-made template, set copies, Print (margins "None", scale 100%). Print one test label first and **scan it** with your scanner.
- [ ] **Label designer** (Design a label): drag, resize and rotate text, barcodes (Code 128, EAN-13, Code 39), QR codes, boxes/lines and your logo; insert fields (item name, SKU, price, roll no., supplier details, rack, dates…); shrink-to-fit text; sheet layouts (A4 3 × 8, Avery-style, roll printers, 2-across); Undo/Redo; live preview with a real item or roll; Save, Save as new, Make default. Sheets: start part-way through a used sheet.
- [ ] GRN page → "Roll labels" prints a label per received roll; Item page → "Print label".
- [ ] A user who can only print labels cannot design / edit / delete templates (Roles → "Barcode labels": View / Create / Edit / Delete).
- [ ] Stock-take: count, save, post — differences appear in the ledger.
- [ ] Stock overview, Stock by rack, Rolls list + roll page history, Ledger filters.

**Purchase**
- [ ] Supplier with rate list and "Supplier items" (their name/code linked to our item).
- [ ] Item ↔ supplier items: an item a supplier is linked to cannot be deleted (it says why); Low stock → "Create draft purchase orders" picks the **preferred** supplier, its current rate and never less than its minimum order; "Turn into PO" on a requisition prices from the chosen supplier's rate list.
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

- **Super Admin forgot the password and mail does not work:** `php database/reset-admin.php admin@yourdomain.com 'NewPassword123'` (cPanel Terminal / SSH), or in phpMyAdmin set `super_admins.password_hash` to a hash made by `php -r "echo password_hash('NewPassword123', PASSWORD_DEFAULT);"`.
- **White page / "Unexpected answer" in the app:** check `storage/logs/` and the cPanel error log; the usual causes are PHP < 8.1, a missing extension, or `storage/` not writable.
- **Screens look old after upload:** hard refresh; confirm `public/ui/index.html` points to the same `index-*.js` that exists in `public/ui/assets/`.
- **An address shows "Page not found" inside the app:** there is no screen at that address. Old PHP screen addresses are redirected to `/app/...` automatically; downloads (CSV / Excel, backup) stay server files by design.
- **The old HTML screens still open (no redirect):** `.env` contains `LEGACY_UI=1`. Remove that line on the live site.
- **Roll back:** restore the old files, then restore the `.sql` backup in phpMyAdmin into an **empty** database (migrations are not reversible).

## F. Known and intentional

- Every screen and every printout (PO, invoice, delivery note, vouchers, transfer challan, report print, barcode labels) is a page of the app. Only downloads (CSV / Excel, backup) and the installer are made by the server.
- "Walk-in customer" is kept for deliveries made without a registered customer.
- A user belongs to one warehouse (or none = head office). Company-wide screens (sales / purchase registers, invoices money figures, low-stock list) are hidden from warehouse-tied users; the Owner is never limited.
- Invoices, payments and customer/supplier statements remain (basic receivable/payable tracking). Remove them only by a separate decision.
- The browser may show a favicon 404 in the console; it has no effect.
