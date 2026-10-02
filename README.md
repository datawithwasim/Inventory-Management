# Home Furnishing Inventory (SaaS)

Multi-tenant inventory management for home-furnishing businesses. PHP 8.1+ and MySQL 8 / MariaDB.
Each company (tenant) has its own users, roles and data; the platform owner manages companies and plans from the Super Admin panel.

## Status
- **Phase 1 – Foundation: done** (auth, tenants, roles & permissions, Super Admin panel, plans/limits, audit log)
- Next: Phase 2 – items, stock ledger, batches (1 batch = 1 roll)

## Setup
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
php tests/smoke.php http://127.0.0.1:8099     # run against a throw-away database
```

## Layout
`core/` framework · `app/` company app · `admin/` Super Admin · `views/` templates · `database/migrations/` SQL · `config/routes.php` routes.

Every tenant-owned model extends `Core\Model`, which forces `tenant_id` on every query.
