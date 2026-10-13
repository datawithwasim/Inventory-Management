-- Showroom names: a Catalogue is a book (CARAVAN), a Product is one design in it (CARAVAN-7) with its own price, and each product is sold from one Item (the stock the warehouse counts).
-- One item can be sold under several products (the same fabric in two books). A product may have no catalogue (ready-made goods).
CREATE TABLE catalogues (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(120) NOT NULL,
    notes VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_catalogue (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    catalogue_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NOT NULL COMMENT 'the item (its one SKU) this product is sold from',
    name VARCHAR(150) NOT NULL,
    price DECIMAL(14,2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product (tenant_id, name),
    KEY idx_product_variant (tenant_id, variant_id),
    KEY idx_product_catalogue (tenant_id, catalogue_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (catalogue_id) REFERENCES catalogues(id) ON DELETE RESTRICT,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sales documents remember which product the customer chose (the stock side still runs on the item).
ALTER TABLE sales_order_items ADD COLUMN product_id INT UNSIGNED NULL AFTER variant_id, ADD KEY idx_soi_product (product_id);
ALTER TABLE delivery_items ADD COLUMN product_id INT UNSIGNED NULL AFTER variant_id, ADD KEY idx_di_product (product_id);

-- Permissions: whoever could see items can see products and catalogues; whoever could change items can change them.
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'products.view' FROM role_permissions WHERE permission IN ('items.view', 'sales.view');
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'products.create' FROM role_permissions WHERE permission = 'items.create';
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'products.edit' FROM role_permissions WHERE permission = 'items.edit';
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'products.delete' FROM role_permissions WHERE permission = 'items.delete';
