<?php
// modules/products/products.php — نقطه ورود ماژول محصولات (ماژول واقعی از ۹٫۹۹٫۱۴)
declare(strict_types=1);

$__productsDir = __DIR__;

// لود توابع فرانت‌اند (همیشه)
require_once $__productsDir . '/frontend.php';

// لود پنل ادمین (فقط در محیط ادمین)
if (defined('CMS_ADMIN_PANEL')) {
    require_once $__productsDir . '/admin.php';
}

// هوک فعال‌سازی
add_action('module_products_activate', function() {
    // جدول products در مایگریشن اصلی ساخته می‌شود
});

// هوک غیرفعال‌سازی
add_action('module_products_deactivate', function() {
    // داده‌ها حفظ می‌شوند
});

// هوک نصب: ساخت جدول‌ها
add_action('module_products_install', function() {
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER,
                name TEXT NOT NULL DEFAULT '',
                sku TEXT,
                description TEXT,
                image TEXT,
                price_per_meter INTEGER NOT NULL DEFAULT 0,
                partner_price_per_meter INTEGER,
                pricing_model TEXT NOT NULL DEFAULT 'per_meter',
                price_per_watt INTEGER NOT NULL DEFAULT 0,
                base_price INTEGER NOT NULL DEFAULT 0,
                frame_options_json TEXT,
                labor_cost_per_meter INTEGER NOT NULL DEFAULT 0,
                labor_cost_per_fixture INTEGER NOT NULL DEFAULT 0,
                order_form_config TEXT,
                seo_title TEXT,
                seo_description TEXT,
                seo_keywords TEXT,
                is_active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                prep_days INTEGER NOT NULL DEFAULT 0,
                template TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $e) { /* silent */ }
});
