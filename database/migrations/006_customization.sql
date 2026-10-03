ALTER TABLE tenant_settings MODIFY svalue TEXT NOT NULL;

CREATE TABLE custom_fields (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity ENUM('item','customer','supplier') NOT NULL,
    label VARCHAR(80) NOT NULL,
    type ENUM('text','number','date','dropdown','checkbox') NOT NULL,
    options TEXT NULL COMMENT 'one choice per line, for dropdowns',
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    show_in_list TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cfield (tenant_id, entity, label),
    KEY idx_cfield_entity (tenant_id, entity, is_active, sort_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE custom_field_values (
    field_id INT UNSIGNED NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (field_id, entity_id),
    KEY idx_cfv_entity (tenant_id, entity_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES custom_fields(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
