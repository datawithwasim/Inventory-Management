-- The fabric a catalogue launch needs is asked from the warehouse the way every order is: as a sales order (addressed to the catalogue department,
-- with the place the fabric must be sent as its delivery address). The warehouse delivers it from its sales orders; the launch follows those orders.
-- launch_fabric (the separate fabric order of the first version) is no longer used.
ALTER TABLE sales_orders ADD COLUMN launch_id INT UNSIGNED NULL AFTER warehouse_id, ADD KEY idx_so_launch (launch_id);
