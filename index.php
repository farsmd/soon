<?php
// index.php — صفحه اصلی سایت
// بخش‌های فعال دیتابیس را به ترتیب sort_order لود و پشت سر هم نمایش می‌دهد.

declare(strict_types=1);

require __DIR__ . '/config.php';

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
    <title><?= e($settings['site_title'] ?? 'وب‌سایت من') ?></title>
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
    echo render_template('template_single.php', $settings);
    exit;
}

// حالت چندبخشی: هدر، اسلایدر، ...، فوتر به ترتیب کنار هم
foreach ($sections as $section) {
    echo render_template((string) $section['template_file'], $settings);
    echo PHP_EOL;
}
