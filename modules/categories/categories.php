<?php
// modules/categories/categories.php — نقطه ورود ماژول دسته‌بندی‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

$__catDir = __DIR__;

require_once $__catDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__catDir . '/admin.php';
}

add_action('module_categories_activate', function() {});
add_action('module_categories_deactivate', function() {});

add_action('module_categories_install', function() {
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS product_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER,
                title TEXT NOT NULL DEFAULT '',
                slug TEXT NOT NULL DEFAULT '',
                description TEXT,
                image TEXT,
                sort_order INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $e) { /* silent */ }
});
