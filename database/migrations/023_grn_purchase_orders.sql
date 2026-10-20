-- One goods receipt can cover several purchase orders of the same supplier (the supplier sent everything on one invoice).
-- grns.po_id keeps the first of them; this table lists all of them.
CREATE TABLE grn_pos (
    grn_id INT UNSIGNED NOT NULL,
    po_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (grn_id, po_id),
    KEY idx_grnpo_po (po_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (grn_id) REFERENCES grns(id) ON DELETE CASCADE,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO grn_pos (grn_id, po_id, tenant_id) SELECT id, po_id, tenant_id FROM grns WHERE po_id IS NOT NULL;
