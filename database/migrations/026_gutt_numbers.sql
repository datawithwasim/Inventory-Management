-- Catalogues and their designs are first known only by a gutt number; the name comes later.
-- `code` is the gutt number. `named` is 0 while the name is still just the number (shown as "name pending"), 1 once it has been given a real name.
ALTER TABLE catalogues ADD COLUMN code VARCHAR(40) NULL AFTER name, ADD COLUMN named TINYINT(1) NOT NULL DEFAULT 1 AFTER code, ADD UNIQUE KEY uq_catalogue_code (tenant_id, code);
ALTER TABLE products ADD COLUMN code VARCHAR(40) NULL AFTER name, ADD COLUMN named TINYINT(1) NOT NULL DEFAULT 1 AFTER code, ADD KEY idx_product_code (tenant_id, catalogue_id, code);
