<?php
// 404.php — هندلر ۴۰۴ سرور (۹٫۹۹٫۳۳)
// برای آدرس‌هایی که به rewrite گیر نمی‌کنند (مثل price.html قدیمی).
// در .htaccess: ErrorDocument 404 /404.php

declare(strict_types=1);

require __DIR__ . '/core/config.php';

cms_session_start();
process_contact_form();
process_partner_form();
track_public_request();

$settings = all_settings();
$siteTitle = (string) ($settings['site_title'] ?? 'وب‌سایت من');

http_response_code(404);
$title = 'صفحه پیدا نشد — ' . $siteTitle;
$desc = (string) ($settings['seo_description'] ?? '') !== '' ? (string) ($settings['seo_description']) : (string) ($settings['site_description'] ?? '');

echo skeleton_head($settings, $title, $desc);
echo render_db_template('header', $settings) . "\n";
echo '<main id="main">' . "\n" . notfound_auto_redirect_html() . "\n" . '</main>' . "\n";
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();
