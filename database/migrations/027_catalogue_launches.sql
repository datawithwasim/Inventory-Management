-- Catalogue launches: the catalogue team orders fabric from a warehouse for a new catalogue, the warehouse dispatches it (roll by roll) to the place
-- the catalogue team named, and when the printed books come back (in sizes) they go into stock as items of their own.
CREATE TABLE catalogue_launches (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    launch_no VARCHAR(30) NOT NULL,
    catalogue_id INT UNSIGNED NOT NULL,
    warehouse_id INT UNSIGNED NOT NULL COMMENT 'the warehouse that is asked to dispatch the fabric',
    ship_to VARCHAR(255) NULL COMMENT 'where the catalogue team wants the fabric sent',
    status ENUM('draft','ordered','partial','dispatched','received','closed','cancelled') NOT NULL DEFAULT 'draft',
    note VARCHAR(255) NULL,
    extra_cost DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'printing, binding … added to the cost of the books',
    fabric_absorbed DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'value of fabric already counted into the cost of received books',
    created_by INT UNSIGNED NULL,
    ordered_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_launch_no (tenant_id, launch_no),
    KEY idx_launch_status (tenant_id, status),
    KEY idx_launch_catalogue (tenant_id, catalogue_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (catalogue_id) REFERENCES catalogues(id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE launch_fabric (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    launch_id INT UNSIGNED NOT NULL,
    variant_id INT UNSIGNED NOT NULL,
    qty_ordered DECIMAL(14,3) NOT NULL,
    qty_issued DECIMAL(14,3) NOT NULL DEFAULT 0,
    qty_returned DECIMAL(14,3) NOT NULL DEFAULT 0 COMMENT 'wrong fabric sent back to the warehouse',
    value_issued DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'cost of the fabric issued, less what came back',
    note VARCHAR(150) NULL,
    UNIQUE KEY uq_launch_fabric (launch_id, variant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (launch_id) REFERENCES catalogue_launches(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES item_variants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE launch_sizes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    launch_id INT UNSIGNED NOT NULL,
    size_name VARCHAR(60) NOT NULL,
    qty_planned INT UNSIGNED NOT NULL DEFAULT 0,
    variant_id INT UNSIGNED NULL COMMENT 'the item for this size; made when the first copies are received',
    qty_received INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_launch_size (launch_id, size_name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (launch_id) REFERENCES catalogue_launches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE stock_docs MODIFY type ENUM('adjustment','transfer','stocktake','launch_issue','launch_return','launch_receipt') NOT NULL;
ALTER TABLE stock_docs ADD COLUMN launch_id INT UNSIGNED NULL AFTER warehouse_id, ADD KEY idx_doc_launch (launch_id);
ALTER TABLE stock_ledger MODIFY type ENUM('opening','adjustment','transfer_in','transfer_out','transfer_return','stocktake','purchase','purchase_return','sale','sales_return','launch_issue','launch_return','launch_receipt') NOT NULL;
ALTER TABLE items MODIFY item_type ENUM('fabric','linen','wallpaper','carpet','accessory','other','catalogue') NOT NULL DEFAULT 'other';
