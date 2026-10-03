-- Personal preferences (dashboard layout, list columns, ...) and custom fields on documents.
CREATE TABLE user_prefs (
    user_id INT UNSIGNED NOT NULL,
    pkey VARCHAR(60) NOT NULL,
    pvalue TEXT NOT NULL,
    PRIMARY KEY (user_id, pkey),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE custom_fields MODIFY entity ENUM('item','customer','supplier','quotation','sales_order','purchase_order') NOT NULL;
