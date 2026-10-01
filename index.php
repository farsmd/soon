<?php
// index.php — صفحه اصلی سایت (نسخه ۶)
// قالب هر بخش از دیتابیس (جدول site_templates) می‌آید؛ اینجا فقط اسکلت سند و ترتیب بخش‌ها ساخته می‌شود.
// دیگر هیچ فایل قالبی include نمی‌شود.

declare(strict_types=1);

require __DIR__ . '/config.php';

// سشن عمومی (برای CSRF فرم تماس) — باید قبل از هر خروجی شروع شود
// روی هاست اشتراکی مسیر پیش‌فرض سشن زود پاک می‌شود و فرم‌ها خطای CSRF می‌دهند؛
// سشن را داخل پوشهٔ خود سیستم نگه می‌داریم (اگر ساخته نشد، پیش‌فرض هاست می‌ماند).
if (session_status() !== PHP_SESSION_ACTIVE) {
    $cmsPubSessionDir = __DIR__ . '/sessions';
    if (!is_dir($cmsPubSessionDir)) {
        @mkdir($cmsPubSessionDir, 0700, true);
    }
    if (is_dir($cmsPubSessionDir) && is_writable($cmsPubSessionDir)) {
        ini_set('session.save_path', $cmsPubSessionDir);
    }
    ini_set('session.gc_maxlifetime', (string) session_lifetime_seconds());
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// لاگ بازدید و کلیک‌های سایت (نسخه ۸٫۲) — برای بیکن کلیک همین‌جا پاسخ داده و تمام می‌شود
track_public_request();

// پردازش ارسال فرم تماس قبل از هر خروجی (نتیجه‌اش داخل قالب «تماس» نشان داده می‌شود)
process_contact_form();

$settings = all_settings();
$sections = get_sections(true); // فقط فعال‌ها، به ترتیب

$seoTitle = (string) ($settings['seo_title'] ?? '') !== '' ? (string) $settings['seo_title'] : (string) ($settings['site_title'] ?? 'وب‌سایت من');
$seoDesc  = (string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : (string) ($settings['site_description'] ?? '');

echo skeleton_head($settings, $seoTitle, $seoDesc);

if ($sections === []) {
    // هنوز هیچ بخشی فعال نشده است
    echo '<main id="main">' . "\n";
    echo '<div class="container empty-state">'
        . '<h1>' . e($settings['site_title'] ?? 'وب‌سایت من') . '</h1>'
        . '<p>هنوز هیچ بخشی برای نمایش فعال نشده است.</p>'
        . '<p><a class="btn" href="admin.php">ورود به مدیریت و ساخت بخش‌ها</a></p>'
        . '</div>' . "\n";
    echo '</main>' . "\n";
    echo skeleton_foot();
    exit;
}

// بخش‌های هدر بیرون از <main> و فوتر بعد از آن می‌نشینند تا سند معنایی و معتبر بماند؛
// بقیه بخش‌ها دقیقاً به ترتیب پنل مدیریت داخل <main> رندر می‌شوند.
$mainOpen = false;
$mainPrinted = false;
foreach ($sections as $section) {
    $key = section_template_key($section);
    if ($key === 'header' && !$mainOpen && !$mainPrinted) {
        echo render_db_template('header', $settings, $section) . "\n";
        continue;
    }
    if ($key === 'footer') {
        if ($mainOpen) {
            echo '</main>' . "\n";
            $mainOpen = false;
        }
        echo render_db_template('footer', $settings, $section) . "\n";
        continue;
    }
    if (!$mainOpen) {
        echo '<main id="main">' . "\n";
        $mainOpen = true;
        $mainPrinted = true;
    }
    echo render_db_template($key, $settings, $section) . "\n";
}
if ($mainOpen) {
    echo '</main>' . "\n";
} elseif (!$mainPrinted) {
    // فقط هدر/فوتر فعال بوده‌اند؛ main خالی برای لینک «پرش به محتوا»
    echo '<main id="main"></main>' . "\n";
}
echo skeleton_foot();
