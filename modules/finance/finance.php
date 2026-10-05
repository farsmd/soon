<?php
// modules/finance/finance.php — نقطه ورود ماژول مالی (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

$__finDir = __DIR__;

require_once $__finDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__finDir . '/admin.php';
}

add_action('module_finance_activate', function() {});
add_action('module_finance_deactivate', function() {});
