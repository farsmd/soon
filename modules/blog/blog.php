<?php
// modules/blog/blog.php — نقطه ورود ماژول وبلاگ (ماژول واقعی از ۹٫۹۹٫۱۳)
declare(strict_types=1);

$__blogDir = __DIR__;

// لود توابع فرانت‌اند (همیشه)
require_once $__blogDir . '/frontend.php';

// لود پنل ادمین (فقط در محیط ادمین)
if (defined('CMS_ADMIN_PANEL')) {
    require_once $__blogDir . '/admin.php';
}

// هوک فعال‌سازی
add_action('module_blog_activate', function() {
    // جدول blog_posts در مایگریشن اصلی ساخته می‌شود
});

// هوک غیرفعال‌سازی
add_action('module_blog_deactivate', function() {
    // داده‌ها حفظ می‌شوند
});

// هوک نصب: ساخت جدول
add_action('module_blog_install', function() {
    try {
        db()->exec("
            CREATE TABLE IF NOT EXISTS blog_posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL DEFAULT '',
                slug TEXT NOT NULL DEFAULT '',
                excerpt TEXT NOT NULL DEFAULT '',
                content TEXT NOT NULL DEFAULT '',
                featured_image TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'published',
                published_at TEXT NOT NULL DEFAULT '',
                template TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } catch (Throwable $e) { /* silent */ }
});
