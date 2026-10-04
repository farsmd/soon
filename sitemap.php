<?php
// sitemap.php — نقشه سایت XML خودکار (نسخه ۸٫۱۰٫۰)
// شامل: صفحه اصلی، صفحه‌ها، محصولات فعال

declare(strict_types=1);

require __DIR__ . '/config.php';

$settings = all_settings();

// آدرس پایه
$baseUrl = rtrim((string) ($settings['site_url'] ?? ''), '/');
if ($baseUrl === '') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $baseUrl = $host !== '' ? $proto . '://' . $host . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/') : '';
}

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$urls = [];

// بررسی فعال بودن
if (($settings['sitemap_enabled'] ?? '1') !== '1') {
    header('HTTP/1.1 404 Not Found');
    echo 'Sitemap disabled';
    exit;
}

// صفحه اصلی
$homeFreq = $settings['sitemap_home_freq'] ?? 'daily';
$homePrio = $settings['sitemap_home_priority'] ?? '1.0';
if ($baseUrl !== '') {
    $urls[] = ['loc' => $baseUrl . '/', 'changefreq' => $homeFreq, 'priority' => $homePrio];
}

// صفحه‌ها
try {
    $pages = db()->query("SELECT slug, updated_at FROM pages WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    foreach ($pages as $p) {
        $slug = (string) ($p['slug'] ?? '');
        if ($slug !== '' && $baseUrl !== '') {
            $urls[] = [
                'loc' => $baseUrl . '/' . urlencode($slug),
                'changefreq' => $settings['sitemap_pages_freq'] ?? 'weekly',
                'priority' => $settings['sitemap_pages_priority'] ?? '0.8',
            ];
        }
    }
} catch (Throwable $e) {
    // ignore
}

// محصولات فعال
try {
    $products = db()->query("SELECT id, updated_at FROM products WHERE is_active = 1 ORDER BY sort_order ASC")->fetchAll();
    foreach ($products as $pr) {
        if ($baseUrl !== '') {
            $urls[] = [
                'loc' => $baseUrl . '/products?id=' . (int) $pr['id'],
                'changefreq' => $settings['sitemap_products_freq'] ?? 'weekly',
                'priority' => $settings['sitemap_products_priority'] ?? '0.7',
            ];
        }
    }
} catch (Throwable $e) {
    // ignore
}

// صفحه محصولات
if ($baseUrl !== '') {
    $urls[] = ['loc' => $baseUrl . '/products', 'changefreq' => 'weekly', 'priority' => '0.9'];
}

// صفحه پیگیری سفارش
if ($baseUrl !== '') {
    $urls[] = ['loc' => $baseUrl . '/track', 'changefreq' => 'monthly', 'priority' => '0.6'];
}

// وبلاگ: فهرست + مقالات منتشرشده (۹٫۱۵٫۲)
if ($baseUrl !== '') {
    $urls[] = ['loc' => $baseUrl . '/blog', 'changefreq' => 'daily', 'priority' => '0.8'];
}
try {
    $posts = db()->query("SELECT slug FROM blog_posts WHERE status = 'published' ORDER BY published_at DESC")->fetchAll();
    foreach ($posts as $bp) {
        $bslug = (string) ($bp['slug'] ?? '');
        if ($bslug !== '' && $baseUrl !== '') {
            $urls[] = [
                'loc' => $baseUrl . '/blog/' . urlencode($bslug),
                'changefreq' => 'weekly',
                'priority' => '0.7',
            ];
        }
    }
} catch (Throwable $e) {
    // ignore
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
