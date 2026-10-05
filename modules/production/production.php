<?php
// modules/production/production.php — نقطه ورود ماژول تولید (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

$__prodDir = __DIR__;

require_once $__prodDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__prodDir . '/admin.php';
}

add_action('module_production_activate', function() {});
add_action('module_production_deactivate', function() {});
