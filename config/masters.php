<?php
// Simple "list of names" master data, managed by one generic screen.
// fields: [column, label, type(text|number|checkbox), required]
return [
    'categories' => [
        'title' => 'Categories', 'one' => 'Category', 'table' => 'categories', 'used_by' => 'category_id',
        'fields' => [['name', 'Name', 'text', true]],
    ],
    'brands' => [
        'title' => 'Brands', 'one' => 'Brand', 'table' => 'brands', 'used_by' => 'brand_id',
        'fields' => [['name', 'Name', 'text', true]],
    ],
    'units' => [
        'title' => 'Units', 'one' => 'Unit', 'table' => 'units', 'used_by' => 'unit_id',
        'fields' => [['name', 'Name', 'text', true], ['short_name', 'Short name', 'text', true], ['allow_decimal', 'Allow decimals (e.g. 12.5 m)', 'checkbox', false]],
    ],
    'taxes' => [
        'title' => 'Taxes', 'one' => 'Tax', 'table' => 'taxes', 'used_by' => 'tax_id',
        'fields' => [['name', 'Name', 'text', true], ['rate', 'Rate %', 'number', true]],
    ],
];
