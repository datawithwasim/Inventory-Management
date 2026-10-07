<?php
// Sidebar, quick-create menu and search shortcuts. 'perm' = any-of; 'module' = can be switched off in Settings → Modules.
// 'exact' = highlight only on the exact path. Labels are callables so the company's own words (Settings → Names) apply.
return [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'speedometer2', 'path' => '/dashboard'],
    'groups' => [
        'inventory' => ['label' => 'Inventory', 'icon' => 'boxes', 'items' => [
            ['label' => fn() => term('items'), 'icon' => 'tags', 'path' => '/items', 'perm' => ['items.view']],
            ['label' => fn() => 'Stock', 'icon' => 'box-seam', 'path' => '/stock', 'perm' => ['stock.view'], 'exact' => true],
            ['label' => fn() => 'Stock by ' . term('rack', true), 'icon' => 'geo-alt', 'path' => '/stock/racks', 'perm' => ['stock.view']],
            ['label' => fn() => term('rolls'), 'icon' => 'layers', 'path' => '/stock/batches', 'perm' => ['stock.view']],
            ['label' => fn() => 'Adjustments', 'icon' => 'sliders', 'path' => '/stock/adjustments', 'perm' => ['stock.view']],
            ['label' => fn() => 'Transfers', 'icon' => 'arrow-left-right', 'path' => '/stock/transfers', 'perm' => ['stock.view'], 'module' => 'transfers'],
            ['label' => fn() => 'Stock-takes', 'icon' => 'clipboard-check', 'path' => '/stock/takes', 'perm' => ['stock.view'], 'module' => 'takes'],
            ['label' => fn() => 'Stock ledger', 'icon' => 'journal-text', 'path' => '/stock/ledger', 'perm' => ['stock.view']],
            ['label' => fn() => 'Barcode labels', 'icon' => 'upc', 'path' => '/labels', 'perm' => ['stock.view'], 'module' => 'labels'],
            ['label' => fn() => term('warehouses'), 'icon' => 'building', 'path' => '/warehouses', 'perm' => ['warehouses.view']],
            ['label' => fn() => term('racks') . ' / locations', 'icon' => 'grid-3x3-gap', 'path' => '/locations', 'perm' => ['warehouses.view']],
            ['label' => fn() => 'Masters', 'icon' => 'list-check', 'path' => '/masters', 'href' => '/masters/categories', 'perm' => ['masters.view']],
        ]],
        'purchase' => ['label' => 'Purchase', 'icon' => 'cart3', 'items' => [
            ['label' => fn() => term('suppliers'), 'icon' => 'truck', 'path' => '/suppliers', 'perm' => ['suppliers.view']],
            ['label' => fn() => 'Supplier items', 'icon' => 'box-seam', 'path' => '/purchase/supplier-items', 'perm' => ['suppliers.view']],
            ['label' => fn() => 'Rate lists', 'icon' => 'currency-rupee', 'path' => '/purchase/rates', 'perm' => ['suppliers.view']],
            ['label' => fn() => 'Requisitions', 'icon' => 'card-checklist', 'path' => '/purchase/requisitions', 'perm' => ['purchase.view'], 'module' => 'requisitions'],
            ['label' => fn() => 'Purchase orders', 'icon' => 'cart-plus', 'path' => '/purchase/orders', 'perm' => ['purchase.view']],
            ['label' => fn() => 'Goods receipts', 'icon' => 'box-arrow-in-down', 'path' => '/purchase/grns', 'perm' => ['purchase.view']],
            ['label' => fn() => 'Bills & payments', 'icon' => 'receipt-cutoff', 'path' => '/purchase/bills', 'perm' => ['purchase.view']],
            ['label' => fn() => 'Purchase returns', 'icon' => 'arrow-return-left', 'path' => '/purchase/returns', 'perm' => ['purchase.view'], 'module' => 'purchase_returns'],
        ]],
        'sales' => ['label' => 'Sales', 'icon' => 'bag-check', 'items' => [
            ['label' => fn() => 'POS (counter)', 'icon' => 'upc-scan', 'path' => '/pos', 'perm' => ['pos.use'], 'module' => 'pos'],
            ['label' => fn() => term('customers'), 'icon' => 'person-lines-fill', 'path' => '/customers', 'perm' => ['customers.view']],
            ['label' => fn() => 'Sales orders', 'icon' => 'bag-check', 'path' => '/sales/orders', 'perm' => ['sales.view']],
            ['label' => fn() => 'Deliveries', 'icon' => 'truck-flatbed', 'path' => '/sales/deliveries', 'perm' => ['sales.view']],
            ['label' => fn() => 'Invoices & payments', 'icon' => 'receipt', 'path' => '/sales/invoices', 'perm' => ['sales.view']],
            ['label' => fn() => 'Sales returns', 'icon' => 'arrow-counterclockwise', 'path' => '/sales/returns', 'perm' => ['sales.view'], 'module' => 'sales_returns'],
        ]],
        'insights' => ['label' => 'Insights', 'icon' => 'bar-chart-line', 'items' => [
            ['label' => fn() => 'Reports', 'icon' => 'bar-chart-line', 'path' => '/reports', 'perm' => ['reports.view'], 'module' => 'reports'],
        ]],
    ],
    // Shown in the profile dropdown (top right), not in the sidebar.
    'account' => [
            ['label' => fn() => 'Users', 'icon' => 'people', 'path' => '/users', 'perm' => ['users.view']],
            ['label' => fn() => 'Roles', 'icon' => 'shield-lock', 'path' => '/roles', 'perm' => ['roles.view']],
            ['label' => fn() => 'Settings', 'icon' => 'sliders2', 'path' => '/settings', 'perm' => ['settings.view']],
            ['label' => fn() => 'My profile', 'icon' => 'person-circle', 'path' => '/profile'],
    ],
    'quick' => [
        ['label' => fn() => 'New ' . term('item', true), 'icon' => 'tags', 'path' => '/items/create', 'perm' => ['items.create']],
        ['label' => fn() => 'New ' . term('customer', true), 'icon' => 'person-plus', 'path' => '/customers/create', 'perm' => ['customers.create']],
        ['label' => fn() => 'New ' . term('supplier', true), 'icon' => 'truck', 'path' => '/suppliers/create', 'perm' => ['suppliers.create']],
        ['label' => fn() => 'New sales order', 'icon' => 'bag-plus', 'path' => '/sales/orders/create', 'perm' => ['sales.create']],
        ['label' => fn() => 'New purchase order', 'icon' => 'cart-plus', 'path' => '/purchase/orders/create', 'perm' => ['purchase.create']],
        ['label' => fn() => 'Stock adjustment', 'icon' => 'sliders', 'path' => '/stock/adjustments/create', 'perm' => ['stock.adjust']],
        ['label' => fn() => 'Open POS', 'icon' => 'upc-scan', 'path' => '/pos', 'perm' => ['pos.use'], 'module' => 'pos'],
    ],
];
