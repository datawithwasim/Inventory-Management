<?php
// Module => label + actions. Roles are built from these keys ("module.action").
return [
    'items'      => ['label' => 'Items',                'actions' => ['view', 'create', 'edit', 'delete']],
    'masters'    => ['label' => 'Masters (categories, brands, units, taxes)', 'actions' => ['view', 'create', 'edit', 'delete']],
    'warehouses' => ['label' => 'Warehouses',           'actions' => ['view', 'create', 'edit', 'delete']],
    'stock'      => ['label' => 'Stock & batches',      'actions' => ['view', 'adjust', 'transfer']],
    'suppliers'  => ['label' => 'Suppliers',            'actions' => ['view', 'create', 'edit', 'delete']],
    'customers'  => ['label' => 'Customers',            'actions' => ['view', 'create', 'edit', 'delete']],
    'purchase'   => ['label' => 'Purchase',             'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
    'sales'      => ['label' => 'Sales',                'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
    'pos'        => ['label' => 'POS',                  'actions' => ['use']],
    'reports'    => ['label' => 'Reports',              'actions' => ['view', 'export']],
    'users'      => ['label' => 'Users',                'actions' => ['view', 'create', 'edit', 'delete']],
    'roles'      => ['label' => 'Roles & permissions',  'actions' => ['view', 'create', 'edit', 'delete']],
    'settings'   => ['label' => 'Company settings',     'actions' => ['view', 'edit']],
];
