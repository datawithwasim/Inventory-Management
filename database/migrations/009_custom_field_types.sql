-- Multi-select and date & time custom fields, and an optional "Unique" rule.
ALTER TABLE custom_fields MODIFY type ENUM('text','number','date','dropdown','checkbox','textarea','email','phone','url','decimal','currency','percent','radio','multiselect','datetime') NOT NULL;
ALTER TABLE custom_fields ADD COLUMN is_unique TINYINT(1) NOT NULL DEFAULT 0 AFTER is_required;
