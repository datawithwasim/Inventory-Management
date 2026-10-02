<?php
/** @var Core\Router $router */

$A = 'App\\Controllers\\';
$S = 'Admin\\Controllers\\';

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
