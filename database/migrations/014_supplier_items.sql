-- Supplier items become a master of their own: the supplier's catalogue entry, optionally linked to one of our item variants.
-- One of our items can have many supplier items (many suppliers); a supplier item can wait unlinked until it is mapped.
RENAME TABLE supplier_products TO supplier_items;

ALTER TABLE supplier_items
    MODIFY variant_id INT UNSIGNED NULL,
    ADD COLUMN min_order_qty DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER supplier_code,
    ADD COLUMN lead_time_days INT UNSIGNED NULL AFTER min_order_qty,
    ADD COLUMN is_preferred TINYINT(1) NOT NULL DEFAULT 0 AFTER lead_time_days,
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER is_preferred,
    ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE custom_fields MODIFY entity ENUM('item','customer','supplier','sales_order','purchase_order','supplier_item') NOT NULL;
