CREATE TABLE tenant_settings (
    tenant_id INT UNSIGNED NOT NULL,
    skey VARCHAR(60) NOT NULL,
    svalue VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (tenant_id, skey),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    tax_no VARCHAR(40) NULL,
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_supplier (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_requisitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    req_no VARCHAR(30) NOT NULL,
    status ENUM('open','converted','cancelled') NOT NULL DEFAULT 'open',
    note VARCHAR(255) NULL,
    po_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_req_no (tenant_id, req_no),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_requisition_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    requisition_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    note VARCHAR(150) NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (requisition_id) REFERENCES purchase_requisitions(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    po_no VARCHAR(30) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    status ENUM('draft','pending_approval','approved','partial','received','closed','cancelled') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_po_no (tenant_id, po_no),
    KEY idx_po_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    po_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    qty_ordered DECIMAL(14,3) NOT NULL,
    qty_received DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    grn_no VARCHAR(30) NOT NULL,
    po_id INT UNSIGNED NULL,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    received_date DATE NOT NULL,
    supplier_ref VARCHAR(60) NULL COMMENT 'supplier challan / invoice number',
    extra_cost DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'freight, duty etc. spread into item cost',
    extra_cost_note VARCHAR(150) NULL,
    note VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_grn_no (tenant_id, grn_no),
    KEY idx_grn_po (po_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE grn_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    grn_id INT UNSIGNED NOT NULL,
    po_item_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty DECIMAL(14,3) NOT NULL,
    qty_returned DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    landed_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
    supplier_lot VARCHAR(60) NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (grn_id) REFERENCES grns(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_bills (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    bill_no VARCHAR(30) NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    grn_id INT UNSIGNED NULL,
    supplier_bill_no VARCHAR(60) NULL,
    bill_date DATE NOT NULL,
    due_date DATE NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    other_charges DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    returned_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_bill_no (tenant_id, bill_no),
    UNIQUE KEY uq_bill_grn (tenant_id, grn_id),
    KEY idx_bill_supplier (tenant_id, supplier_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (grn_id) REFERENCES grns(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE supplier_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    bill_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14,2) NOT NULL,
    paid_on DATE NOT NULL,
    method ENUM('cash','bank','upi','card','cheque','other') NOT NULL DEFAULT 'cash',
    reference VARCHAR(80) NULL,
    note VARCHAR(150) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pay_bill (bill_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (bill_id) REFERENCES purchase_bills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    return_no VARCHAR(30) NOT NULL,
    grn_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    bill_id INT UNSIGNED NULL,
    return_date DATE NOT NULL,
    reason VARCHAR(150) NULL,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_return_no (tenant_id, return_no),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (grn_id) REFERENCES grns(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE purchase_return_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    return_id INT UNSIGNED NOT NULL,
    grn_item_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (return_id) REFERENCES purchase_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (grn_item_id) REFERENCES grn_items(id),
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
