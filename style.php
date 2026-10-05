<?php
// style.php — خروجی عمومی و فقط‌خواندنی CSS سایت از دیتابیس (نسخه ۶)
// ترتیب خروجی: ایمپورت فونت (در صورت انتخاب) + CSS اصلی + متغیرهای تنظیمات ظاهری + CSS سفارشی.
// با ETag و If-None-Match پاسخ 304 می‌دهد؛ هیچ تنظیم دیگری جز CSS از این مسیر لو نمی‌رود.

declare(strict_types=1);

require __DIR__ . '/core/config.php';

$settings = all_settings();
$css = build_site_css($settings);
$etag = '"' . sha1($css . '|' . (string) ($settings['css_updated_at'] ?? '')) . '"';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');

$ifNoneMatch = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
if (strpos($ifNoneMatch, 'W/') === 0) {
    $ifNoneMatch = trim(substr($ifNoneMatch, 2));
}
if ($ifNoneMatch !== '' && ($ifNoneMatch === $etag || $ifNoneMatch === '*')) {
    http_response_code(304);
    exit;
}

echo $css;
