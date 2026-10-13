-- Print templates: a company can design several layouts for each printed document (invoice, delivery note, purchase order) and choose one as the default.
CREATE TABLE print_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    doc VARCHAR(30) NOT NULL,
    name VARCHAR(80) NOT NULL,
    config MEDIUMTEXT NOT NULL COMMENT 'JSON: page, header, and the ordered sections of the document',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_print_tpl (tenant_id, doc),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
