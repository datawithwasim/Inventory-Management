-- Fuller supplier master, home-furnishing item attributes, and supplier-wise rate lists.
ALTER TABLE suppliers
    ADD COLUMN supplier_type ENUM('manufacturer','trader','jobworker','importer') NULL AFTER name,
    ADD COLUMN pan VARCHAR(20) NULL AFTER tax_no,
    ADD COLUMN city VARCHAR(80) NULL AFTER address,
    ADD COLUMN state VARCHAR(80) NULL AFTER city,
    ADD COLUMN pincode VARCHAR(12) NULL AFTER state,
    ADD COLUMN ship_address VARCHAR(255) NULL AFTER pincode,
    ADD COLUMN bank_name VARCHAR(100) NULL,
    ADD COLUMN bank_account VARCHAR(40) NULL,
    ADD COLUMN bank_ifsc VARCHAR(20) NULL,
    ADD COLUMN credit_limit DECIMAL(14,2) NOT NULL DEFAULT 0,
    ADD COLUMN lead_time_days INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN transport VARCHAR(100) NULL;

CREATE TABLE supplier_contacts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    role VARCHAR(60) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    KEY idx_sc_supplier (supplier_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE items
    ADD COLUMN item_type ENUM('fabric','linen','wallpaper','carpet','accessory','other') NOT NULL DEFAULT 'other' AFTER name,
    ADD COLUMN hsn_code VARCHAR(20) NULL,
    ADD COLUMN design_no VARCHAR(60) NULL,
    ADD COLUMN composition VARCHAR(120) NULL,
    ADD COLUMN width VARCHAR(40) NULL,
    ADD COLUMN gsm VARCHAR(40) NULL,
    ADD COLUMN pattern VARCHAR(80) NULL,
    ADD COLUMN finish VARCHAR(80) NULL;

ALTER TABLE item_variants
    ADD COLUMN colour VARCHAR(60) NULL AFTER name,
    ADD COLUMN size VARCHAR(60) NULL AFTER colour;

-- What each supplier charges for each item. Rows are never overwritten: a new rate closes the old one, so history is kept.
CREATE TABLE supplier_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    rate DECIMAL(14,2) NOT NULL,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    min_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    lead_time_days INT UNSIGNED NULL,
    supplier_code VARCHAR(60) NULL COMMENT "supplier's own design / item code",
    valid_from DATE NOT NULL,
    valid_to DATE NULL,
    note VARCHAR(150) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sr_lookup (tenant_id, supplier_id, variant_id, valid_from),
    KEY idx_sr_variant (tenant_id, variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
