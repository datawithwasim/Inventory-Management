-- Supplier rates: a roll rate, a cut rate and an MOQ rate (the existing `rate` is the roll rate, min_qty is the MOQ quantity).
-- Suppliers are Domestic, Imported or Market; imported ones quote in a foreign currency, the others in rupees.
-- Purchase orders are in the supplier's currency with the exchange rate of the day; goods received, bills and stock cost are always in rupees.
CREATE TABLE currencies (
    tenant_id INT UNSIGNED NOT NULL,
    code CHAR(3) NOT NULL,
    name VARCHAR(40) NOT NULL,
    symbol VARCHAR(8) NOT NULL DEFAULT '',
    rate_to_inr DECIMAL(14,6) NOT NULL DEFAULT 1 COMMENT 'rupees for one unit of this currency',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id, code),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE suppliers ADD COLUMN origin ENUM('domestic','imported','market') NOT NULL DEFAULT 'domestic' AFTER supplier_type, ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER origin;
UPDATE suppliers SET origin = 'imported' WHERE supplier_type = 'importer';

ALTER TABLE supplier_rates ADD COLUMN cut_rate DECIMAL(14,2) NULL AFTER rate, ADD COLUMN moq_rate DECIMAL(14,2) NULL AFTER cut_rate, ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER moq_rate;

ALTER TABLE purchase_orders ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'INR' AFTER supplier_id, ADD COLUMN exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1 AFTER currency;
ALTER TABLE purchase_order_items ADD COLUMN rate_kind ENUM('roll','cut','moq') NULL AFTER unit_price;
