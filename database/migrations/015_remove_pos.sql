-- POS (counter billing) is removed from the product: this is an inventory management app.
UPDATE deliveries SET source = 'direct' WHERE source = 'pos';
ALTER TABLE deliveries MODIFY source ENUM('order','direct') NOT NULL DEFAULT 'direct';
DELETE FROM role_permissions WHERE permission = 'pos.use';
DELETE FROM tenant_settings WHERE skey = 'template.receipt';
