<?php
// page.php — نمایش یک صفحه جدا با آدرس page.php?slug=...
// صفحه‌ها از پنل مدیریت (بخش «صفحه‌ها») ساخته می‌شوند و هرکدام عنوان سئوی جدا دارند.

declare(strict_types=1);

require __DIR__ . '/config.php';

$settings = all_settings();
$slug = trim((string) ($_GET['slug'] ?? ''));
$page = ($slug !== '' && is_valid_slug($slug)) ? get_page_by_slug($slug, true) : null;

$siteTitle = (string) ($settings['site_title'] ?? 'وب‌سایت من');

if ($page === null) {
    http_response_code(404);
    $title = 'صفحه پیدا نشد — ' . $siteTitle;
    $desc  = (string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : (string) ($settings['site_description'] ?? '');
} else {
    $title = (string) ($page['seo_title'] ?? '') !== '' ? (string) $page['seo_title'] : ((string) $page['title'] . ' — ' . $siteTitle);
    $desc  = (string) ($page['seo_description'] ?? '') !== '' ? (string) $page['seo_description'] : (string) ($settings['site_description'] ?? '');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($desc) ?>">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php"><?= e($siteTitle) ?></a>
        <?= menu_html() ?>
    </div>
</header>
<main>
<?php if ($page === null): ?>
    <section class="content-section">
        <div class="container empty-state">
            <h1>صفحه پیدا نشد</h1>
            <p>صفحه‌ای با این آدرس وجود ندارد یا غیرفعال است.</p>
            <p><a class="btn" href="index.php">بازگشت به صفحه اصلی</a></p>
        </div>
    </section>
<?php else: ?>
    <section class="content-section page-content">
        <div class="container">
            <h1><?= e((string) $page['title']) ?></h1>
            <div class="page-body">
                <?= (string) ($page['content'] ?? '') ?>
            </div>
        </div>
    </section>
<?php endif; ?>
</main>
<footer class="site-footer">
    <div class="container">
        <p>© <?= e(date('Y')) ?> <?= e($siteTitle) ?> — همه حقوق محفوظ است.</p>
    </div>
</footer>
<script>
(function(){var b=document.getElementById('theme-toggle');if(!b)return;if(localStorage.getItem('cms-theme')==='dark'){document.documentElement.dataset.theme='dark';}b.addEventListener('click',function(){var d=document.documentElement.dataset.theme==='dark';document.documentElement.dataset.theme=d?'light':'dark';localStorage.setItem('cms-theme',d?'light':'dark');});})();
</script>
</body>
</html>
