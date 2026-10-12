-- Transfers between two warehouses become a two-step movement: dispatched (stock leaves the sender and is "in transit"),
-- then received by the other warehouse (rolls land on a rack there). Shortages are settled, never silently lost.
ALTER TABLE stock_docs MODIFY status ENUM('draft','posted','in_transit','partial','received','received_short','cancelled') NOT NULL DEFAULT 'posted',
    ADD COLUMN dispatched_at DATETIME NULL AFTER posted_at,
    ADD COLUMN expected_date DATE NULL AFTER dispatched_at,
    ADD COLUMN transport VARCHAR(100) NULL AFTER expected_date,
    ADD COLUMN vehicle_no VARCHAR(30) NULL AFTER transport,
    ADD COLUMN lr_no VARCHAR(60) NULL AFTER vehicle_no,
    ADD COLUMN received_at DATETIME NULL AFTER lr_no,
    ADD COLUMN received_by INT UNSIGNED NULL AFTER received_at;
ALTER TABLE stock_doc_lines
    ADD COLUMN qty_received DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER unit_cost,
    ADD COLUMN qty_lost DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER qty_received,
    ADD COLUMN qty_returned DECIMAL(14,3) NOT NULL DEFAULT 0 AFTER qty_lost;
ALTER TABLE stock_ledger MODIFY type ENUM('opening','adjustment','transfer_in','transfer_out','transfer_return','stocktake','purchase','purchase_return','sale','sales_return') NOT NULL;
CREATE TABLE stock_transfer_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    doc_id INT UNSIGNED NOT NULL,
    line_id INT UNSIGNED NULL,
    event ENUM('dispatch','receive','lost','returned','cancel') NOT NULL,
    qty DECIMAL(14,3) NOT NULL DEFAULT 0,
    location_id INT UNSIGNED NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_ste_doc (tenant_id, doc_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (doc_id) REFERENCES stock_docs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
UPDATE stock_docs SET status = 'received', dispatched_at = posted_at, received_at = posted_at, received_by = created_by WHERE type = 'transfer' AND to_warehouse_id IS NOT NULL AND to_warehouse_id <> warehouse_id;
UPDATE stock_doc_lines l JOIN stock_docs d ON d.id = l.doc_id SET l.qty_received = l.qty WHERE d.type = 'transfer' AND d.status = 'received';
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT role_id, 'stock.receive' FROM role_permissions WHERE permission = 'stock.transfer';
