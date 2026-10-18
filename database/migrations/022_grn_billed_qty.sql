-- Goods receipt: what the supplier's bill / challan said for an item (e.g. 200 m) can differ from the rolls that actually arrived (4 x 50 m).
-- It is kept on the first roll line of each item, so the receipt can show billed against received.
ALTER TABLE grn_items ADD COLUMN billed_qty DECIMAL(14,3) NULL AFTER qty;
