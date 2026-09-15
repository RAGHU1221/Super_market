<?php
/**
 * Global constants & session bootstrap
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_CASHIER', 'cashier');

// Permission matrix: which roles can access which module keys
$GLOBALS['PERMISSIONS'] = [
    'dashboard'   => [ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER],
    'billing'     => [ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER],
    'products'    => [ROLE_ADMIN, ROLE_MANAGER],
    'categories'  => [ROLE_ADMIN, ROLE_MANAGER],
    'suppliers'   => [ROLE_ADMIN, ROLE_MANAGER],
    'purchases'   => [ROLE_ADMIN, ROLE_MANAGER],
    'stock'       => [ROLE_ADMIN, ROLE_MANAGER],
    'sales_history' => [ROLE_ADMIN, ROLE_MANAGER, ROLE_CASHIER],
    'returns'     => [ROLE_ADMIN, ROLE_MANAGER],
    'staff'       => [ROLE_ADMIN],
    'reports'     => [ROLE_ADMIN, ROLE_MANAGER],
    'settings'    => [ROLE_ADMIN],
];
