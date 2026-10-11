# New interface (React)

The app is being rebuilt screen by screen in React. The PHP side stays: it keeps the database, the rules and the permissions,
and now also answers JSON. Screens not rebuilt yet open the old PHP page, so nothing stops working during the move.

## How it fits together

| | |
|---|---|
| **Pages** | `/app/...` — one PHP route (`SpaController`) serves the built React page for every address under it |
| **Built files** | `public/ui/` (committed, so cPanel needs no Node) |
| **Data** | `/api/v1/<any existing route>` — the *same controller* as the page, answering JSON (`core/Api.php`) |
| **Sign-in** | same session cookie; `POST`s send the `X-CSRF-Token` header (token comes from `GET /api/v1/session`) |
| **Forms** | `GET /api/v1/schema/<entity>` gives the company's designed layout (sections, fields, own labels, custom fields); `SchemaForm` draws it |
| **Record pages** | `GET /api/v1/record/<entity>/<id>` gives the designed summary + sections, timeline and notes |
| **Pick lists** | `GET /api/v1/options/<source>` |

### JSON mode in one paragraph
A request under `/api/v1/` runs the normal route. A controller's `view(...)` data is returned as JSON instead of HTML; a `redirect(...)`
after a write becomes `{ok, redirect, messages}` (422 when the form was refused); errors are JSON with the right status. So every write
keeps its existing validation, audit and stock rules — React never re-implements them. Keys named like password / token / secret / hash
never leave the server.

## Build

```
cd frontend
npm install
npm run dev      # http://localhost:5173 , proxies /api and /assets to the PHP server on :8099
npm run build    # type-checks, then writes public/ui/
```
Upload `public/ui/` to the server after a build.

## Adding a module (what Customers did)
1. `src/lib/routes.ts` — add its path prefix to `REACT_PREFIXES` (menu links then use React).
2. `src/pages/<module>/List|Detail|Form.tsx` — list from `GET /<route>`, detail = `RecordPage` + the module's own cards, form = `SchemaForm`.
3. Register the routes in `src/App.tsx`.
4. If the module is not in `FormDesign::FIELDS` yet, nothing else is needed; if its fields need a special input, add them to `FormDesign::INPUT`.

## Status

| Module | State |
|---|---|
| Sign-in, shell (menu, profile, theme), dashboard | done (dashboard is a simplified first version: no charts yet) |
| Customers (list with column picker, record page, form from the layout designer) | done |
| Suppliers (list, record page with contacts, items supplied, rate list add / revise / remove, orders, bills; form with contact people) | done |
| Supplier items (list with filters + column picker, record page with rates, form with the our-item search) | done (CSV import stays on the PHP page for now) |
| Items (list, record page with variants / rolls / supplier rates, form with variants and bundle components) | done |
| Purchase: orders (list, approval switch, workflow buttons, form with the items table), goods receipts (against an order or direct, per-roll lines with racks and supplier lots), bills (payments), requisitions (incl. from low stock, turn into PO), returns (pick receipt, choose qty and rack) | done |
| Warehouses (list with make-default / delete, record page with racks and what is stored, form), racks / locations (list, record page, add one or a range, edit) | done |
| Stock: overview (rack split, reserved, value, low stock), stock by rack, rolls list + roll page with history, stock ledger | done |
| Stock documents: adjustments and transfers (line editor: item search, roll, rack with in-stock hint, new roll with supplier lot; stock-out voucher link), stock-takes (start, enter counts, save, post, delete draft) | done |
| Sales: orders (list, form with customer prices / discount / tax and live totals, confirm & reserve, advance, close, cancel), deliveries (against an order with the roll and rack suggested per line and shade warning, split lines, or direct; creates the invoice), invoices (payments, edit details), sales returns (pick invoice, put back on a rack or not) | done (delivery note / invoice / receipt printing stays on the PHP print pages) |
| Everything else (reports, settings, POS, admin) | still the PHP pages |
| Layout designer, print pages (invoice, voucher), POS counter | stay PHP for now |

Suggested order (remaining): Reports → Settings → POS → Super admin.
