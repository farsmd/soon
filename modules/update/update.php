<?php
// modules/update/update.php — نقطه ورود ماژول آپدیت (ماژول واقعی از ۹٫۹۹٫۲۶)
declare(strict_types=1);
$__modDir = __DIR__;
require_once $__modDir . '/frontend.php';
if (defined('CMS_ADMIN_PANEL')) {
    require_once $__modDir . '/admin.php';
}
add_action('module_update_activate', function() {});
add_action('module_update_deactivate', function() {});
