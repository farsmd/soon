<?php
// modules/settings/frontend.php — توابع تنظیمات (ماژول واقعی از ۹٫۹۹٫۲۵)
declare(strict_types=1);

/** دریافت یک تنظیم سایت */
function settings_get(string $key, $default = '') {
    try {
        $st = db()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $st->execute([$key]);
        $val = $st->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Throwable $e) {
        return $default;
    }
}
