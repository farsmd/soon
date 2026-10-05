<?php
// modules/settings/settings.php — نقطه ورود ماژول تنظیمات (ماژول واقعی از ۹٫۹۹٫۲۵)
declare(strict_types=1);

$__modDir = __DIR__;

require_once $__modDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__modDir . '/admin.php';
}

add_action('module_settings_activate', function() {});
add_action('module_settings_deactivate', function() {});
