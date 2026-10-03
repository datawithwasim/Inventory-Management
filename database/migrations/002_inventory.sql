CREATE TABLE counters (
    tenant_id INT UNSIGNED NOT NULL,
    prefix VARCHAR(10) NOT NULL,
    next_no INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (tenant_id, prefix),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brand (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE units (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    short_name VARCHAR(10) NOT NULL,
    allow_decimal TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_unit (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE taxes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tax (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE warehouses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL,
    address VARCHAR(255) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_wh (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(190) NOT NULL,
    category_id INT UNSIGNED NULL,
    brand_id INT UNSIGNED NULL,
    unit_id INT UNSIGNED NOT NULL,
    tax_id INT UNSIGNED NULL,
    description TEXT NULL,
    location VARCHAR(100) NULL COMMENT 'rack / bin label',
    track_batch TINYINT(1) NOT NULL DEFAULT 0,
    is_bundle TINYINT(1) NOT NULL DEFAULT 0,
    reorder_level DECIMAL(14,3) NOT NULL DEFAULT 0,
    reorder_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_items_tenant (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FOREIGN KEY (unit_id) REFERENCES units(id),
    FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE item_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    item_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NULL COMMENT 'e.g. Grey / 3-seater',
    sku VARCHAR(60) NOT NULL,
    barcode VARCHAR(60) NULL,
    cost_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    sale_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_sku (tenant_id, sku),
    UNIQUE KEY uq_barcode (tenant_id, barcode),
    KEY idx_variant_item (item_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bundle_components (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    bundle_item_id INT UNSIGNED NOT NULL,
    component_variant_id INT UNSIGNED NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (bundle_item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (component_variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE batches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    batch_no VARCHAR(60) NOT NULL,
    supplier_lot VARCHAR(60) NULL,
    received_date DATE NOT NULL,
    received_qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_batch_no (tenant_id, batch_no),
    KEY idx_batch_variant (variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_balances (
    tenant_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = item is not batch tracked',
    qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    PRIMARY KEY (tenant_id, variant_id, warehouse_id, batch_id),
    KEY idx_bal_batch (batch_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_ledger (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty_change DECIMAL(14,3) NOT NULL,
    type ENUM('opening','adjustment','transfer_in','transfer_out','stocktake','purchase','purchase_return','sale','sales_return') NOT NULL,
    ref_type VARCHAR(30) NULL,
    ref_id INT UNSIGNED NULL,
    unit_cost DECIMAL(14,2) NULL,
    note VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ledger_variant (tenant_id, variant_id, created_at),
    KEY idx_ledger_batch (tenant_id, batch_id),
    KEY idx_ledger_date (tenant_id, created_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_docs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    type ENUM('adjustment','transfer','stocktake') NOT NULL,
    doc_no VARCHAR(30) NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    to_warehouse_id INT UNSIGNED NULL,
    reason VARCHAR(30) NULL,
    note VARCHAR(255) NULL,
    status ENUM('draft','posted') NOT NULL DEFAULT 'posted',
    created_by INT UNSIGNED NULL,
    posted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_doc_no (tenant_id, doc_no),
    KEY idx_doc_type (tenant_id, type, id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_doc_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    doc_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty DECIMAL(14,3) NOT NULL COMMENT 'adjustment: signed change, transfer: qty moved, stocktake: counted qty',
    expected_qty DECIMAL(14,3) NULL,
    unit_cost DECIMAL(14,2) NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (doc_id) REFERENCES stock_docs(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO units (tenant_id, name, short_name, allow_decimal)
SELECT t.id, u.n, u.s, u.d FROM tenants t
JOIN (SELECT 'Piece' AS n, 'pcs' AS s, 0 AS d UNION ALL SELECT 'Meter', 'm', 1 UNION ALL SELECT 'Yard', 'yd', 1
      UNION ALL SELECT 'Set', 'set', 0 UNION ALL SELECT 'Box', 'box', 0 UNION ALL SELECT 'Kg', 'kg', 1) u;

INSERT INTO warehouses (tenant_id, name, code, is_default) SELECT id, 'Main Warehouse', 'MAIN', 1 FROM tenants;
