-- Custom fields can now be a "Lookup": the value is a record of another kind (an item, a supplier, a rack …). `options` holds the kind.
ALTER TABLE custom_fields MODIFY type ENUM('text','number','date','dropdown','checkbox','textarea','email','phone','url','decimal','currency','percent','radio','multiselect','datetime','lookup') NOT NULL;
