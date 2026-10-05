<?php
// modules/customers/customers.php — نقطه ورود ماژول مشتری‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

$__custDir = __DIR__;

require_once $__custDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__custDir . '/admin.php';
}

add_action('module_customers_activate', function() {});
add_action('module_customers_deactivate', function() {});

add_action('module_customers_install', function() {
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL DEFAULT '',
                phone TEXT NOT NULL DEFAULT '',
                type TEXT NOT NULL DEFAULT 'retail',
                credit_limit INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $e) { /* silent */ }
});
