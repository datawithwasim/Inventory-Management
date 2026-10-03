-- More custom field types for the visual form designer.
ALTER TABLE custom_fields MODIFY type ENUM('text','number','date','dropdown','checkbox','textarea','email','phone','url','decimal','currency','percent','radio') NOT NULL;
