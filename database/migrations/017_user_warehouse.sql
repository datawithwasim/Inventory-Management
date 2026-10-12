-- Warehouse-wise users: every user can be tied to one warehouse (operator / incharge). No warehouse = head office, sees and works on all warehouses.
ALTER TABLE users ADD COLUMN warehouse_id INT UNSIGNED NULL AFTER role_id,
    ADD KEY idx_users_warehouse (warehouse_id),
    ADD CONSTRAINT fk_users_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id);
ALTER TABLE roles ADD COLUMN warehouse_bound TINYINT(1) NOT NULL DEFAULT 0 AFTER is_system;
INSERT IGNORE INTO roles (tenant_id, name, is_owner, is_system, warehouse_bound) SELECT id, 'Warehouse Incharge', 0, 1, 1 FROM tenants;
INSERT IGNORE INTO roles (tenant_id, name, is_owner, is_system, warehouse_bound) SELECT id, 'Warehouse Operator', 0, 1, 1 FROM tenants;
INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.perm FROM roles r JOIN (
    SELECT 'items.view' AS perm UNION ALL SELECT 'masters.view' UNION ALL SELECT 'warehouses.view' UNION ALL SELECT 'warehouses.create' UNION ALL SELECT 'warehouses.edit'
    UNION ALL SELECT 'stock.view' UNION ALL SELECT 'stock.adjust' UNION ALL SELECT 'stock.transfer' UNION ALL SELECT 'stock.receive'
    UNION ALL SELECT 'purchase.view' UNION ALL SELECT 'purchase.create' UNION ALL SELECT 'sales.view' UNION ALL SELECT 'reports.view') p
WHERE r.name = 'Warehouse Incharge' AND r.is_system = 1;
INSERT IGNORE INTO role_permissions (role_id, permission)
SELECT r.id, p.perm FROM roles r JOIN (
    SELECT 'items.view' AS perm UNION ALL SELECT 'warehouses.view' UNION ALL SELECT 'stock.view' UNION ALL SELECT 'stock.transfer' UNION ALL SELECT 'stock.receive') p
WHERE r.name = 'Warehouse Operator' AND r.is_system = 1;
