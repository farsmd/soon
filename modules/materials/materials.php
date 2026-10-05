<?php
// modules/materials/materials.php — نقطه ورود ماژول مواد اولیه (ماژول واقعی از ۹٫۹۹٫۲۲)
declare(strict_types=1);

$__modDir = __DIR__;

require_once $__modDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__modDir . '/admin.php';
}

add_action('module_materials_activate', function() {});
add_action('module_materials_deactivate', function() {});
