<?php
// modules/orders/orders.php — نقطه ورود ماژول سفارش‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

$__ordersDir = __DIR__;

require_once $__ordersDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__ordersDir . '/admin.php';
}

add_action('module_orders_activate', function() {});
add_action('module_orders_deactivate', function() {});

add_action('module_orders_install', function() {
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER,
                status TEXT NOT NULL DEFAULT 'new',
                total INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $e) { /* silent */ }
});
