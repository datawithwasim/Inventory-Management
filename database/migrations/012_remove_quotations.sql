-- Quotations are removed from the product: orders are created directly.
ALTER TABLE sales_orders DROP COLUMN quotation_id;
DROP TABLE IF EXISTS sales_quotation_items;
DROP TABLE IF EXISTS sales_quotations;
DELETE v FROM custom_field_values v JOIN custom_fields f ON f.id = v.field_id WHERE f.entity = 'quotation';
DELETE FROM custom_fields WHERE entity = 'quotation';
ALTER TABLE custom_fields MODIFY entity ENUM('item','customer','supplier','sales_order','purchase_order') NOT NULL;
DELETE FROM tenant_settings WHERE skey LIKE '%quotation%';
