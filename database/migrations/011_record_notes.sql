-- Notes on a record (item / customer / supplier), shown on its detail page.
CREATE TABLE record_notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity VARCHAR(30) NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    body VARCHAR(1500) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_note_rec (tenant_id, entity, entity_id, id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
