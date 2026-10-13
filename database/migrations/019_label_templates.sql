-- Barcode label designer: each company can design its own label templates (size, sheet layout and the elements placed on the label).
CREATE TABLE label_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    for_type ENUM('items','rolls','any') NOT NULL DEFAULT 'any',
    width_mm DECIMAL(6,2) NOT NULL,
    height_mm DECIMAL(6,2) NOT NULL,
    page TEXT NOT NULL COMMENT 'JSON: roll / sheet, paper, columns, rows, margins, gaps, outline',
    elements MEDIUMTEXT NOT NULL COMMENT 'JSON: the text, barcode, QR, box and image elements on the label',
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_label_tpl (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Labels get a permission of their own: everyone who could see stock can print labels; whoever can edit items can design templates.
INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'labels.view' FROM role_permissions WHERE permission = 'stock.view';

INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'labels.create' FROM role_permissions WHERE permission = 'items.edit';

INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'labels.edit' FROM role_permissions WHERE permission = 'items.edit';

INSERT IGNORE INTO role_permissions (role_id, permission) SELECT DISTINCT role_id, 'labels.delete' FROM role_permissions WHERE permission = 'items.edit';
