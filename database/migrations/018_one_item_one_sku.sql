-- One item = one SKU. In home furnishing every colour / design / size is an item of its own, and the item is tagged to the
-- supplier item that carries the details (design no., composition, width, GSM, pattern, finish, their code, rate, minimum order).
-- Behind the scenes an item still has exactly one row in item_variants (stock, documents and rates point at it).

-- 1. Items that had several variants become several items (the first variant stays on the original item).
ALTER TABLE items ADD COLUMN src_variant_id INT UNSIGNED NULL, ADD COLUMN src_item_id INT UNSIGNED NULL;

INSERT INTO items (tenant_id, name, item_type, category_id, brand_id, unit_id, tax_id, description, location, track_batch, is_bundle, reorder_level, reorder_qty, is_active,
                   hsn_code, design_no, composition, width, gsm, pattern, finish, src_variant_id, src_item_id)
SELECT i.tenant_id, CONCAT(i.name, ' - ', COALESCE(NULLIF(TRIM(v.name), ''), NULLIF(TRIM(CONCAT_WS(' ', v.colour, v.size)), ''), v.sku)),
       i.item_type, i.category_id, i.brand_id, i.unit_id, i.tax_id, i.description, i.location, i.track_batch, i.is_bundle, i.reorder_level, i.reorder_qty, v.is_active,
       i.hsn_code, i.design_no, i.composition, i.width, i.gsm, i.pattern, i.finish, v.id, i.id
FROM item_variants v JOIN items i ON i.id = v.item_id
WHERE v.id <> (SELECT MIN(v2.id) FROM item_variants v2 WHERE v2.item_id = v.item_id);

UPDATE item_variants v JOIN items i ON i.src_variant_id = v.id SET v.item_id = i.id;

INSERT INTO custom_field_values (field_id, entity_id, tenant_id, value)
SELECT x.field_id, i.id, x.tenant_id, x.value
FROM items i JOIN custom_field_values x ON x.tenant_id = i.tenant_id AND x.entity_id = i.src_item_id
JOIN custom_fields f ON f.id = x.field_id AND f.entity = 'item'
WHERE i.src_variant_id IS NOT NULL;

ALTER TABLE items DROP COLUMN src_variant_id, DROP COLUMN src_item_id;

-- 2. The variant's own name / colour / size now belong in the item name.
UPDATE items i JOIN item_variants v ON v.item_id = i.id
SET i.name = CONCAT(i.name, ' - ', COALESCE(NULLIF(TRIM(v.name), ''), NULLIF(TRIM(CONCAT_WS(' ', v.colour, v.size)), '')))
WHERE i.name NOT LIKE CONCAT('% - ', COALESCE(NULLIF(TRIM(v.name), ''), NULLIF(TRIM(CONCAT_WS(' ', v.colour, v.size)), ''), '#none#'))
  AND COALESCE(NULLIF(TRIM(v.name), ''), NULLIF(TRIM(CONCAT_WS(' ', v.colour, v.size)), '')) IS NOT NULL;

UPDATE item_variants SET name = NULL, colour = NULL, size = NULL;

-- 3. The descriptive details move onto the supplier item.
ALTER TABLE supplier_items
    ADD COLUMN design_no VARCHAR(60) NULL AFTER supplier_code,
    ADD COLUMN composition VARCHAR(120) NULL AFTER design_no,
    ADD COLUMN width VARCHAR(40) NULL AFTER composition,
    ADD COLUMN gsm VARCHAR(40) NULL AFTER width,
    ADD COLUMN pattern VARCHAR(80) NULL AFTER gsm,
    ADD COLUMN finish VARCHAR(80) NULL AFTER pattern;

UPDATE supplier_items s JOIN item_variants v ON v.id = s.variant_id JOIN items i ON i.id = v.item_id
SET s.design_no = i.design_no, s.composition = i.composition, s.width = i.width, s.gsm = i.gsm, s.pattern = i.pattern, s.finish = i.finish;

ALTER TABLE items DROP COLUMN design_no, DROP COLUMN composition, DROP COLUMN width, DROP COLUMN gsm, DROP COLUMN pattern, DROP COLUMN finish;
