<?php
// index.php — صفحه اصلی سایت (نسخه ۲)
// بخش‌های فعال دیتابیس را به ترتیب sort_order لود و پشت سر هم نمایش می‌دهد.
// هر بخش با محتوای خودش (عنوان، متن، عکس، لینک) به قالب پاس داده می‌شود.

declare(strict_types=1);

require __DIR__ . '/config.php';

// سشن عمومی (برای CSRF فرم تماس) — باید قبل از هر خروجی شروع شود
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

$settings = all_settings();
$sections = get_sections(true); // فقط فعال‌ها، به ترتیب

if ($sections === []) {
    // هنوز هیچ بخشی فعال نشده است
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e((string) ($settings['seo_title'] ?? '') !== '' ? (string) $settings['seo_title'] : ($settings['site_title'] ?? 'وب‌سایت من')) ?></title>
    <meta name="description" content="<?= e((string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : ($settings['site_description'] ?? '')) ?>">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container empty-state">
        <h1><?= e($settings['site_title'] ?? 'وب‌سایت من') ?></h1>
        <p>هنوز هیچ بخشی برای نمایش فعال نشده است.</p>
        <p><a class="btn" href="admin.php">ورود به مدیریت و ساخت بخش‌ها</a></p>
    </div>
</body>
</html>
    <?php
    exit;
}

// حالت قالب تک‌صفحه: اگر تنها بخش فعال، قالب تک‌صفحه باشد آن را کامل خروجی بده
if (count($sections) === 1 && basename((string) $sections[0]['template_file']) === 'template_single.php') {
    echo render_template('template_single.php', $settings, $sections[0]);
    exit;
}

// حالت چندبخشی: هدر، اسلایدر، ...، فوتر به ترتیب کنار هم — هر بخش با محتوای خودش
foreach ($sections as $section) {
    echo render_template((string) $section['template_file'], $settings, $section);
    echo PHP_EOL;
}
