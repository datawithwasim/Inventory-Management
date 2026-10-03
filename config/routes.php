<?php
/** @var Core\Router $router */

$A = 'App\\Controllers\\';
$S = 'App\\Admin\\';

// ---- One-time installer ----
$router->get('/install', $A . 'InstallController@show');
$router->post('/install', $A . 'InstallController@run');

// ---- Company app ----
$router->get('/', $A . 'AuthController@home');
$router->get('/login', $A . 'AuthController@showLogin', ['guest']);
$router->post('/login', $A . 'AuthController@login', ['guest']);
$router->post('/logout', $A . 'AuthController@logout');
$router->get('/forgot-password', $A . 'AuthController@showForgot', ['guest']);
$router->post('/forgot-password', $A . 'AuthController@forgot', ['guest']);
$router->get('/reset-password/{token}', $A . 'AuthController@showReset', ['guest']);
$router->post('/reset-password/{token}', $A . 'AuthController@reset', ['guest']);

$router->get('/dashboard', $A . 'DashboardController@index', ['auth']);

$router->get('/profile', $A . 'ProfileController@show', ['auth']);
$router->post('/profile', $A . 'ProfileController@update', ['auth']);
$router->post('/profile/password', $A . 'ProfileController@password', ['auth']);

$router->get('/users', $A . 'UserController@index', ['auth', 'perm:users.view']);
$router->get('/users/create', $A . 'UserController@create', ['auth', 'perm:users.create']);
$router->post('/users', $A . 'UserController@store', ['auth', 'perm:users.create']);
$router->get('/users/{id}/edit', $A . 'UserController@edit', ['auth', 'perm:users.edit']);
$router->post('/users/{id}', $A . 'UserController@update', ['auth', 'perm:users.edit']);
$router->post('/users/{id}/toggle', $A . 'UserController@toggle', ['auth', 'perm:users.edit']);
$router->post('/users/{id}/delete', $A . 'UserController@destroy', ['auth', 'perm:users.delete']);

$router->get('/roles', $A . 'RoleController@index', ['auth', 'perm:roles.view']);
$router->get('/roles/create', $A . 'RoleController@create', ['auth', 'perm:roles.create']);
$router->post('/roles', $A . 'RoleController@store', ['auth', 'perm:roles.create']);
$router->get('/roles/{id}/edit', $A . 'RoleController@edit', ['auth', 'perm:roles.edit']);
$router->post('/roles/{id}', $A . 'RoleController@update', ['auth', 'perm:roles.edit']);
$router->post('/roles/{id}/delete', $A . 'RoleController@destroy', ['auth', 'perm:roles.delete']);

// ---- Masters, warehouses, items ----
$router->get('/masters/{type}', $A . 'MasterController@index', ['auth', 'perm:masters.view']);
$router->get('/masters/{type}/create', $A . 'MasterController@create', ['auth', 'perm:masters.create']);
$router->post('/masters/{type}', $A . 'MasterController@store', ['auth', 'perm:masters.create']);
$router->get('/masters/{type}/{id}/edit', $A . 'MasterController@edit', ['auth', 'perm:masters.edit']);
$router->post('/masters/{type}/{id}', $A . 'MasterController@update', ['auth', 'perm:masters.edit']);
$router->post('/masters/{type}/{id}/delete', $A . 'MasterController@destroy', ['auth', 'perm:masters.delete']);

$router->get('/warehouses', $A . 'WarehouseController@index', ['auth', 'perm:warehouses.view']);
$router->get('/warehouses/create', $A . 'WarehouseController@create', ['auth', 'perm:warehouses.create']);
$router->post('/warehouses', $A . 'WarehouseController@store', ['auth', 'perm:warehouses.create']);
$router->get('/warehouses/{id}/edit', $A . 'WarehouseController@edit', ['auth', 'perm:warehouses.edit']);
$router->post('/warehouses/{id}', $A . 'WarehouseController@update', ['auth', 'perm:warehouses.edit']);
$router->post('/warehouses/{id}/default', $A . 'WarehouseController@makeDefault', ['auth', 'perm:warehouses.edit']);
$router->post('/warehouses/{id}/delete', $A . 'WarehouseController@destroy', ['auth', 'perm:warehouses.delete']);

$router->get('/locations', $A . 'LocationController@index', ['auth', 'perm:warehouses.view']);
$router->get('/locations/create', $A . 'LocationController@create', ['auth', 'perm:warehouses.create']);
$router->post('/locations', $A . 'LocationController@store', ['auth', 'perm:warehouses.create']);
$router->get('/locations/{id}/edit', $A . 'LocationController@edit', ['auth', 'perm:warehouses.edit']);
$router->post('/locations/{id}', $A . 'LocationController@update', ['auth', 'perm:warehouses.edit']);
$router->post('/locations/{id}/delete', $A . 'LocationController@destroy', ['auth', 'perm:warehouses.delete']);

$router->get('/items', $A . 'ItemController@index', ['auth', 'perm:items.view']);
$router->get('/items/create', $A . 'ItemController@create', ['auth', 'perm:items.create']);
$router->post('/items', $A . 'ItemController@store', ['auth', 'perm:items.create']);
$router->get('/items/import', $A . 'ImportController@show', ['auth', 'perm:items.create']);
$router->post('/items/import', $A . 'ImportController@run', ['auth', 'perm:items.create']);
$router->get('/items/import/template', $A . 'ImportController@template', ['auth', 'perm:items.create']);
$router->get('/items/export', $A . 'ImportController@export', ['auth', 'perm:items.view']);
$router->get('/items/{id}', $A . 'ItemController@show', ['auth', 'perm:items.view']);
$router->get('/items/{id}/edit', $A . 'ItemController@edit', ['auth', 'perm:items.edit']);
$router->post('/items/{id}', $A . 'ItemController@update', ['auth', 'perm:items.edit']);
$router->post('/items/{id}/delete', $A . 'ItemController@destroy', ['auth', 'perm:items.delete']);

// ---- Stock ----
$router->get('/stock', $A . 'StockController@index', ['auth', 'perm:stock.view']);
$router->get('/stock/ledger', $A . 'StockController@ledger', ['auth', 'perm:stock.view']);
$router->get('/stock/racks', $A . 'StockController@racks', ['auth', 'perm:stock.view']);
$router->get('/stock/placement', $A . 'StockController@placement', ['auth', 'perm:stock.view']);
$router->get('/stock/batches', $A . 'StockController@batches', ['auth', 'perm:stock.view']);
$router->get('/stock/batches/{id}', $A . 'StockController@batch', ['auth', 'perm:stock.view']);
$router->get('/stock/lookup', $A . 'StockController@lookup', ['auth', 'perm:stock.view']);
$router->get('/stock/batch-options', $A . 'StockController@batchOptions', ['auth', 'perm:stock.view']);

$router->get('/stock/adjustments', $A . 'AdjustmentController@index', ['auth', 'perm:stock.view']);
$router->get('/stock/adjustments/create', $A . 'AdjustmentController@create', ['auth', 'perm:stock.adjust']);
$router->post('/stock/adjustments', $A . 'AdjustmentController@store', ['auth', 'perm:stock.adjust']);
$router->get('/stock/adjustments/{id}', $A . 'AdjustmentController@show', ['auth', 'perm:stock.view']);

$router->get('/stock/transfers', $A . 'TransferController@index', ['auth', 'perm:stock.view']);
$router->get('/stock/transfers/create', $A . 'TransferController@create', ['auth', 'perm:stock.transfer']);
$router->post('/stock/transfers', $A . 'TransferController@store', ['auth', 'perm:stock.transfer']);
$router->get('/stock/transfers/{id}', $A . 'TransferController@show', ['auth', 'perm:stock.view']);

$router->get('/stock/takes', $A . 'StocktakeController@index', ['auth', 'perm:stock.view']);
$router->get('/stock/takes/create', $A . 'StocktakeController@create', ['auth', 'perm:stock.adjust']);
$router->post('/stock/takes', $A . 'StocktakeController@store', ['auth', 'perm:stock.adjust']);
$router->get('/stock/takes/{id}', $A . 'StocktakeController@show', ['auth', 'perm:stock.view']);
$router->post('/stock/takes/{id}', $A . 'StocktakeController@update', ['auth', 'perm:stock.adjust']);
$router->post('/stock/takes/{id}/delete', $A . 'StocktakeController@destroy', ['auth', 'perm:stock.adjust']);

// ---- Purchase ----
$router->get('/lookup/items', $A . 'LookupController@items', ['auth']);

$router->get('/suppliers', $A . 'SupplierController@index', ['auth', 'perm:suppliers.view']);
$router->get('/suppliers/create', $A . 'SupplierController@create', ['auth', 'perm:suppliers.create']);
$router->post('/suppliers', $A . 'SupplierController@store', ['auth', 'perm:suppliers.create']);
$router->get('/suppliers/{id}', $A . 'SupplierController@show', ['auth', 'perm:suppliers.view']);
$router->get('/suppliers/{id}/edit', $A . 'SupplierController@edit', ['auth', 'perm:suppliers.edit']);
$router->post('/suppliers/{id}', $A . 'SupplierController@update', ['auth', 'perm:suppliers.edit']);
$router->post('/suppliers/{id}/delete', $A . 'SupplierController@destroy', ['auth', 'perm:suppliers.delete']);

$router->get('/purchase/requisitions', $A . 'RequisitionController@index', ['auth', 'perm:purchase.view']);
$router->get('/purchase/requisitions/create', $A . 'RequisitionController@create', ['auth', 'perm:purchase.create']);
$router->post('/purchase/requisitions', $A . 'RequisitionController@store', ['auth', 'perm:purchase.create']);
$router->get('/purchase/requisitions/{id}', $A . 'RequisitionController@show', ['auth', 'perm:purchase.view']);
$router->post('/purchase/requisitions/{id}/convert', $A . 'RequisitionController@convert', ['auth', 'perm:purchase.create']);
$router->post('/purchase/requisitions/{id}/cancel', $A . 'RequisitionController@cancel', ['auth', 'perm:purchase.edit']);

$router->get('/purchase/orders', $A . 'PurchaseOrderController@index', ['auth', 'perm:purchase.view']);
$router->post('/purchase/orders/approval-setting', $A . 'PurchaseOrderController@setApproval', ['auth', 'perm:settings.edit']);
$router->get('/purchase/orders/create', $A . 'PurchaseOrderController@create', ['auth', 'perm:purchase.create']);
$router->post('/purchase/orders', $A . 'PurchaseOrderController@store', ['auth', 'perm:purchase.create']);
$router->get('/purchase/orders/{id}', $A . 'PurchaseOrderController@show', ['auth', 'perm:purchase.view']);
$router->get('/purchase/orders/{id}/print', $A . 'PurchaseOrderController@print', ['auth', 'perm:purchase.view']);
$router->get('/purchase/orders/{id}/edit', $A . 'PurchaseOrderController@edit', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/orders/{id}', $A . 'PurchaseOrderController@update', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/orders/{id}/submit', $A . 'PurchaseOrderController@submit', ['auth', 'perm:purchase.create']);
$router->post('/purchase/orders/{id}/approve', $A . 'PurchaseOrderController@approve', ['auth', 'perm:purchase.approve']);
$router->post('/purchase/orders/{id}/reject', $A . 'PurchaseOrderController@reject', ['auth', 'perm:purchase.approve']);
$router->post('/purchase/orders/{id}/cancel', $A . 'PurchaseOrderController@cancel', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/orders/{id}/close', $A . 'PurchaseOrderController@close', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/orders/{id}/delete', $A . 'PurchaseOrderController@destroy', ['auth', 'perm:purchase.delete']);
$router->get('/purchase/orders/{id}/receive', $A . 'GrnController@createFromPo', ['auth', 'perm:purchase.create']);

$router->get('/purchase/grns', $A . 'GrnController@index', ['auth', 'perm:purchase.view']);
$router->get('/purchase/grns/create', $A . 'GrnController@create', ['auth', 'perm:purchase.create']);
$router->post('/purchase/grns', $A . 'GrnController@store', ['auth', 'perm:purchase.create']);
$router->get('/purchase/grns/{id}', $A . 'GrnController@show', ['auth', 'perm:purchase.view']);
$router->post('/purchase/grns/{id}/bill', $A . 'BillController@createFromGrn', ['auth', 'perm:purchase.create']);

$router->get('/purchase/bills', $A . 'BillController@index', ['auth', 'perm:purchase.view']);
$router->get('/purchase/bills/{id}', $A . 'BillController@show', ['auth', 'perm:purchase.view']);
$router->get('/purchase/bills/{id}/edit', $A . 'BillController@edit', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/bills/{id}', $A . 'BillController@update', ['auth', 'perm:purchase.edit']);
$router->post('/purchase/bills/{id}/payments', $A . 'BillController@pay', ['auth', 'perm:purchase.create']);
$router->post('/purchase/bills/{id}/payments/{payId}/delete', $A . 'BillController@deletePayment', ['auth', 'perm:purchase.delete']);

$router->get('/purchase/returns', $A . 'PurchaseReturnController@index', ['auth', 'perm:purchase.view']);
$router->get('/purchase/returns/create', $A . 'PurchaseReturnController@create', ['auth', 'perm:purchase.create']);
$router->post('/purchase/returns', $A . 'PurchaseReturnController@store', ['auth', 'perm:purchase.create']);
$router->get('/purchase/returns/{id}', $A . 'PurchaseReturnController@show', ['auth', 'perm:purchase.view']);

// ---- Sales ----
$router->get('/lookup/sale-items', $A . 'SalesLookupController@items', ['auth']);
$router->get('/lookup/sale-item', $A . 'SalesLookupController@one', ['auth']);
$router->get('/lookup/stock', $A . 'SalesLookupController@stock', ['auth']);

$router->get('/customers', $A . 'CustomerController@index', ['auth', 'perm:customers.view']);
$router->get('/customers/create', $A . 'CustomerController@create', ['auth', 'perm:customers.create']);
$router->post('/customers', $A . 'CustomerController@store', ['auth', 'perm:customers.create']);
$router->get('/customers/groups', $A . 'CustomerGroupController@index', ['auth', 'perm:customers.view']);
$router->get('/customers/groups/create', $A . 'CustomerGroupController@create', ['auth', 'perm:customers.create']);
$router->post('/customers/groups', $A . 'CustomerGroupController@store', ['auth', 'perm:customers.create']);
$router->get('/customers/groups/{id}/edit', $A . 'CustomerGroupController@edit', ['auth', 'perm:customers.edit']);
$router->post('/customers/groups/{id}', $A . 'CustomerGroupController@update', ['auth', 'perm:customers.edit']);
$router->post('/customers/groups/{id}/prices', $A . 'CustomerGroupController@setPrice', ['auth', 'perm:customers.edit']);
$router->post('/customers/groups/{id}/prices/{priceId}/delete', $A . 'CustomerGroupController@deletePrice', ['auth', 'perm:customers.edit']);
$router->post('/customers/groups/{id}/delete', $A . 'CustomerGroupController@destroy', ['auth', 'perm:customers.delete']);
$router->get('/customers/{id}', $A . 'CustomerController@show', ['auth', 'perm:customers.view']);
$router->get('/customers/{id}/edit', $A . 'CustomerController@edit', ['auth', 'perm:customers.edit']);
$router->post('/customers/{id}', $A . 'CustomerController@update', ['auth', 'perm:customers.edit']);
$router->post('/customers/{id}/delete', $A . 'CustomerController@destroy', ['auth', 'perm:customers.delete']);

$router->get('/sales/quotations', $A . 'QuotationController@index', ['auth', 'perm:sales.view']);
$router->get('/sales/quotations/create', $A . 'QuotationController@create', ['auth', 'perm:sales.create']);
$router->post('/sales/quotations', $A . 'QuotationController@store', ['auth', 'perm:sales.create']);
$router->get('/sales/quotations/{id}', $A . 'QuotationController@show', ['auth', 'perm:sales.view']);
$router->get('/sales/quotations/{id}/print', $A . 'QuotationController@print', ['auth', 'perm:sales.view']);
$router->get('/sales/quotations/{id}/edit', $A . 'QuotationController@edit', ['auth', 'perm:sales.edit']);
$router->post('/sales/quotations/{id}', $A . 'QuotationController@update', ['auth', 'perm:sales.edit']);
$router->post('/sales/quotations/{id}/send', $A . 'QuotationController@send', ['auth', 'perm:sales.edit']);
$router->post('/sales/quotations/{id}/accept', $A . 'QuotationController@accept', ['auth', 'perm:sales.edit']);
$router->post('/sales/quotations/{id}/reject', $A . 'QuotationController@reject', ['auth', 'perm:sales.edit']);
$router->post('/sales/quotations/{id}/convert', $A . 'QuotationController@convert', ['auth', 'perm:sales.create']);
$router->post('/sales/quotations/{id}/delete', $A . 'QuotationController@destroy', ['auth', 'perm:sales.delete']);

$router->get('/sales/orders', $A . 'SalesOrderController@index', ['auth', 'perm:sales.view']);
$router->get('/sales/orders/create', $A . 'SalesOrderController@create', ['auth', 'perm:sales.create']);
$router->post('/sales/orders', $A . 'SalesOrderController@store', ['auth', 'perm:sales.create']);
$router->get('/sales/orders/{id}', $A . 'SalesOrderController@show', ['auth', 'perm:sales.view']);
$router->get('/sales/orders/{id}/edit', $A . 'SalesOrderController@edit', ['auth', 'perm:sales.edit']);
$router->post('/sales/orders/{id}', $A . 'SalesOrderController@update', ['auth', 'perm:sales.edit']);
$router->post('/sales/orders/{id}/confirm', $A . 'SalesOrderController@confirm', ['auth', 'perm:sales.create']);
$router->post('/sales/orders/{id}/cancel', $A . 'SalesOrderController@cancel', ['auth', 'perm:sales.edit']);
$router->post('/sales/orders/{id}/close', $A . 'SalesOrderController@close', ['auth', 'perm:sales.edit']);
$router->post('/sales/orders/{id}/delete', $A . 'SalesOrderController@destroy', ['auth', 'perm:sales.delete']);
$router->post('/sales/orders/{id}/advance', $A . 'SalesOrderController@advance', ['auth', 'perm:sales.create']);
$router->post('/sales/orders/{id}/advance/{payId}/refund', $A . 'SalesOrderController@refundAdvance', ['auth', 'perm:sales.edit']);
$router->get('/sales/orders/{id}/deliver', $A . 'DeliveryController@createFromOrder', ['auth', 'perm:sales.create']);

$router->get('/sales/deliveries', $A . 'DeliveryController@index', ['auth', 'perm:sales.view']);
$router->get('/sales/deliveries/create', $A . 'DeliveryController@create', ['auth', 'perm:sales.create']);
$router->post('/sales/deliveries', $A . 'DeliveryController@store', ['auth', 'perm:sales.create']);
$router->get('/sales/deliveries/{id}', $A . 'DeliveryController@show', ['auth', 'perm:sales.view']);
$router->get('/sales/deliveries/{id}/print', $A . 'DeliveryController@print', ['auth', 'perm:sales.view']);
$router->post('/sales/deliveries/{id}/invoice', $A . 'DeliveryController@invoice', ['auth', 'perm:sales.create']);

$router->get('/sales/invoices', $A . 'InvoiceController@index', ['auth', 'perm:sales.view']);
$router->get('/sales/invoices/{id}', $A . 'InvoiceController@show', ['auth', 'perm:sales.view']);
$router->get('/sales/invoices/{id}/print', $A . 'InvoiceController@print', ['auth', 'perm:sales.view']);
$router->get('/sales/invoices/{id}/edit', $A . 'InvoiceController@edit', ['auth', 'perm:sales.edit']);
$router->post('/sales/invoices/{id}', $A . 'InvoiceController@update', ['auth', 'perm:sales.edit']);
$router->post('/sales/invoices/{id}/payments', $A . 'InvoiceController@pay', ['auth', 'perm:sales.create']);
$router->post('/sales/invoices/{id}/payments/{payId}/delete', $A . 'InvoiceController@deletePayment', ['auth', 'perm:sales.delete']);

$router->get('/sales/returns', $A . 'SalesReturnController@index', ['auth', 'perm:sales.view']);
$router->get('/sales/returns/create', $A . 'SalesReturnController@create', ['auth', 'perm:sales.create']);
$router->post('/sales/returns', $A . 'SalesReturnController@store', ['auth', 'perm:sales.create']);
$router->get('/sales/returns/{id}', $A . 'SalesReturnController@show', ['auth', 'perm:sales.view']);

$router->get('/pos', $A . 'PosController@index', ['auth', 'perm:pos.use']);
$router->post('/pos/checkout', $A . 'PosController@checkout', ['auth', 'perm:pos.use']);

// ---- Settings ----
$router->get('/settings', $A . 'SettingsController@index', ['auth', 'perm:settings.view']);
$router->get('/settings/custom-fields', $A . 'CustomFieldController@index', ['auth', 'perm:settings.view']);
$router->get('/settings/custom-fields/create', $A . 'CustomFieldController@create', ['auth', 'perm:settings.edit']);
$router->post('/settings/custom-fields', $A . 'CustomFieldController@store', ['auth', 'perm:settings.edit']);
$router->get('/settings/custom-fields/{id}/edit', $A . 'CustomFieldController@edit', ['auth', 'perm:settings.edit']);
$router->post('/settings/custom-fields/{id}', $A . 'CustomFieldController@update', ['auth', 'perm:settings.edit']);
$router->post('/settings/custom-fields/{id}/delete', $A . 'CustomFieldController@destroy', ['auth', 'perm:settings.edit']);
$router->get('/settings/{tab}', $A . 'SettingsController@show', ['auth', 'perm:settings.view']);
$router->post('/settings/{tab}', $A . 'SettingsController@save', ['auth', 'perm:settings.edit']);
$router->get('/company/logo', $A . 'SettingsController@logo', ['auth']);

$router->post('/impersonate/stop', $S . 'TenantController@stopImpersonating');

// ---- Super Admin panel ----
$router->get('/admin/login', $S . 'AuthController@showLogin', ['admin_guest']);
$router->post('/admin/login', $S . 'AuthController@login', ['admin_guest']);
$router->post('/admin/logout', $S . 'AuthController@logout', ['admin']);

$router->get('/admin', $S . 'DashboardController@index', ['admin']);

$router->get('/admin/tenants', $S . 'TenantController@index', ['admin']);
$router->get('/admin/tenants/create', $S . 'TenantController@create', ['admin']);
$router->post('/admin/tenants', $S . 'TenantController@store', ['admin']);
$router->get('/admin/tenants/{id}/edit', $S . 'TenantController@edit', ['admin']);
$router->post('/admin/tenants/{id}', $S . 'TenantController@update', ['admin']);
$router->post('/admin/tenants/{id}/status', $S . 'TenantController@status', ['admin']);
$router->post('/admin/tenants/{id}/impersonate', $S . 'TenantController@impersonate', ['admin']);

$router->get('/admin/plans', $S . 'PlanController@index', ['admin']);
$router->get('/admin/plans/create', $S . 'PlanController@create', ['admin']);
$router->post('/admin/plans', $S . 'PlanController@store', ['admin']);
$router->get('/admin/plans/{id}/edit', $S . 'PlanController@edit', ['admin']);
$router->post('/admin/plans/{id}', $S . 'PlanController@update', ['admin']);

$router->get('/admin/audit', $S . 'DashboardController@audit', ['admin']);

$router->get('/admin/system', $S . 'SystemController@index', ['admin']);
$router->post('/admin/system/migrate', $S . 'SystemController@migrate', ['admin']);

// ---- Reports ----
$router->get('/reports', $A . 'ReportController@index', ['auth', 'perm:reports.view']);
$router->post('/reports/low-stock/create-pos', $A . 'ReportController@createPos', ['auth', 'perm:reports.view', 'perm:purchase.create']);
$router->get('/reports/{slug}/export/{format}', $A . 'ReportController@export', ['auth', 'perm:reports.export']);
$router->get('/reports/{slug}', $A . 'ReportController@show', ['auth', 'perm:reports.view']);
