-- Supplier-wise product master: what each supplier supplies and what THEY call it (their product name / code).
-- Rates and purchase orders are made against these.
CREATE TABLE supplier_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    supplier_name VARCHAR(150) NULL COMMENT "what the supplier calls this product",
    supplier_code VARCHAR(60) NULL COMMENT "supplier's own design / item code",
    note VARCHAR(150) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sp (tenant_id, supplier_id, variant_id),
    KEY idx_sp_code (tenant_id, supplier_id, supplier_code),
    KEY idx_sp_variant (tenant_id, variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO supplier_products (tenant_id, supplier_id, variant_id, supplier_code)
SELECT r.tenant_id, r.supplier_id, r.variant_id, NULLIF(SUBSTRING_INDEX(GROUP_CONCAT(r.supplier_code ORDER BY r.valid_from DESC, r.id DESC SEPARATOR '|'), '|', 1), '')
FROM supplier_rates r GROUP BY r.tenant_id, r.supplier_id, r.variant_id;
