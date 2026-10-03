CREATE TABLE customer_groups (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cgroup (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    price DECIMAL(14,2) NOT NULL,
    UNIQUE KEY uq_gprice (group_id, variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES customer_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(100) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    address VARCHAR(255) NULL,
    ship_address VARCHAR(255) NULL,
    tax_no VARCHAR(40) NULL,
    credit_days INT UNSIGNED NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    is_walkin TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES customer_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_quotations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    quote_no VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    quote_date DATE NOT NULL,
    valid_until DATE NULL,
    status ENUM('draft','sent','accepted','rejected','converted') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) NULL,
    delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    installation_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    order_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quote_no (tenant_id, quote_no),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_quotation_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    quotation_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    qty DECIMAL(14,3) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (quotation_id) REFERENCES sales_quotations(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    order_no VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_date DATE NULL,
    status ENUM('draft','confirmed','partial','delivered','closed','cancelled') NOT NULL DEFAULT 'draft',
    allow_backorder TINYINT(1) NOT NULL DEFAULT 0,
    ship_to VARCHAR(255) NULL,
    notes VARCHAR(255) NULL,
    delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    installation_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    charges_billed TINYINT(1) NOT NULL DEFAULT 0,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    quotation_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    confirmed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_order_no (tenant_id, order_no),
    KEY idx_order_status (tenant_id, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    qty_ordered DECIMAL(14,3) NOT NULL,
    qty_delivered DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    KEY idx_soi_variant (tenant_id, variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES sales_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    delivery_no VARCHAR(30) NOT NULL,
    order_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    delivery_date DATE NOT NULL,
    ship_to VARCHAR(255) NULL,
    note VARCHAR(255) NULL,
    source ENUM('order','direct','pos') NOT NULL DEFAULT 'direct',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_delivery_no (tenant_id, delivery_no),
    KEY idx_delivery_order (order_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES sales_orders(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE delivery_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED NOT NULL,
    order_item_id INT UNSIGNED NULL,
    parent_id INT UNSIGNED NULL COMMENT 'set on the stock rows of a bundle / set line',
    variant_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty DECIMAL(14,3) NOT NULL,
    qty_returned DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    invoice_no VARCHAR(30) NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    delivery_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    installation_charge DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    returned_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    paid_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoice_no (tenant_id, invoice_no),
    UNIQUE KEY uq_invoice_delivery (tenant_id, delivery_id),
    KEY idx_invoice_customer (tenant_id, customer_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (delivery_id) REFERENCES deliveries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE customer_payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NULL,
    order_id INT UNSIGNED NULL COMMENT 'advance taken on an order',
    amount DECIMAL(14,2) NOT NULL,
    applied DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'advance already moved onto invoices',
    paid_on DATE NOT NULL,
    method ENUM('cash','bank','upi','card','cheque','other','advance') NOT NULL DEFAULT 'cash',
    reference VARCHAR(80) NULL,
    note VARCHAR(150) NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cpay_invoice (invoice_id),
    KEY idx_cpay_order (order_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    return_no VARCHAR(30) NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    delivery_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    reason VARCHAR(150) NULL,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_sreturn_no (tenant_id, return_no),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sales_return_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    return_id INT UNSIGNED NOT NULL,
    delivery_item_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    batch_id INT UNSIGNED NOT NULL DEFAULT 0,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    qty DECIMAL(14,3) NOT NULL,
    restock TINYINT(1) NOT NULL DEFAULT 1,
    unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (delivery_item_id) REFERENCES delivery_items(id),
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO customers (tenant_id, name, is_walkin) SELECT id, 'Walk-in customer', 1 FROM tenants;
