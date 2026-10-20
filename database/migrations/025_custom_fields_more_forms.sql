-- Custom fields on more forms: goods receipts, bills, deliveries, invoices, returns, requisitions, stock documents and warehouses.
ALTER TABLE custom_fields MODIFY entity ENUM('item','customer','supplier','sales_order','purchase_order','supplier_item','grn','bill','delivery','invoice','purchase_return','sales_return','requisition','adjustment','transfer','stocktake','warehouse') NOT NULL;
