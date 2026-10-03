CREATE TABLE locations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL,
    description VARCHAR(150) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_location (tenant_id, warehouse_id, code),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stock_balances ADD COLUMN location_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = no rack assigned' AFTER batch_id, DROP PRIMARY KEY, ADD PRIMARY KEY (tenant_id, variant_id, warehouse_id, batch_id, location_id);

ALTER TABLE stock_ledger ADD COLUMN location_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER batch_id;

ALTER TABLE stock_doc_lines ADD COLUMN location_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER batch_id, ADD COLUMN to_location_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER location_id;
