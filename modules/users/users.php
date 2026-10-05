<?php
// modules/users/users.php — نقطه ورود ماژول کاربران (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

$__usersDir = __DIR__;

require_once $__usersDir . '/frontend.php';

if (defined('CMS_ADMIN_PANEL')) {
    require_once $__usersDir . '/admin.php';
}

add_action('module_users_activate', function() {});
add_action('module_users_deactivate', function() {});
