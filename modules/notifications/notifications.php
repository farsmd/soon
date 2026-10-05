<?php
// modules/notifications/notifications.php — نقطه ورود ماژول اعلان‌ها (ماژول واقعی از ۹٫۹۹٫۲۲)
declare(strict_types=1);

$__modDir = __DIR__;

require_once $__modDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__modDir . '/admin.php';
}

add_action('module_notifications_activate', function() {});
add_action('module_notifications_deactivate', function() {});
