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
