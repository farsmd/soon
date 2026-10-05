<?php
/**
 * وبلاگ/مقالات (نسخه ۹٫۱۴)
 * آدرس تمیز: /blog و /blog/<slug>
 */
declare(strict_types=1);

require __DIR__ . '/core/config.php';
cms_session_start();

$settings = all_settings();
$siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');
$slug = trim((string) ($_GET['slug'] ?? ''));
$pdo = db();

$canonBase = site_base_url($settings);

if ($slug !== '') {
    // صفحه تکی مقاله
    $st = $pdo->prepare("SELECT * FROM blog_posts WHERE slug = :s AND status = 'published' LIMIT 1");
    $st->execute([':s' => $slug]);
    $post = $st->fetch(PDO::FETCH_ASSOC);
    if ($post === false) {
        http_response_code(404);
        $title = 'یافت نشد — ' . $siteTitle;
        echo skeleton_head($settings, $title, '', ['url' => $canonBase . '/blog']);
        echo render_db_template('header', $settings) . "\n";
        echo '<main id="main"><div class="page-body"><h1>مقاله یافت نشد</h1><p><a class="btn" href="' . e(pretty_url('blog.php')) . '">بازگشت به وبلاگ</a></p></div></main>';
        echo render_db_template('footer', $settings) . "\n";
        echo skeleton_foot();
        exit;
    }
    $seo = [
        'url' => $canonBase . '/blog/' . $post['slug'],
        'description' => (string) ($post['excerpt'] ?? ''),
        'image' => (string) ($post['featured_image'] ?? ''),
        'type' => 'article',
        'breadcrumbs' => [
            ['name' => 'خانه', 'url' => $canonBase . '/'],
            ['name' => 'وبلاگ', 'url' => $canonBase . '/blog'],
            ['name' => (string) $post['title'], 'url' => $canonBase . '/blog/' . $post['slug']],
        ],
    ];
    $title = (string) $post['title'] . ' — ' . $siteTitle;
    $head = skeleton_head($settings, $title, (string) ($post['excerpt'] ?? ''), $seo);
    $head = str_replace('<head>', '<head>\n<base href="/">', $head);
    echo $head;
    echo render_db_template('header', $settings) . "\n";
    ?>
    <main id="main">
    <div class="page-body blog-post">
        <nav class="breadcrumbs"><a href="/">خانه</a> / <a href="<?= e(pretty_url('blog.php')) ?>">وبلاگ</a> / <?= e((string) $post['title']) ?></nav>
        <h1><?= e((string) $post['title']) ?></h1>
        <p class="muted"><?= e(blog_fa_date((string) ($post['published_at'] ?? $post['created_at']))) ?></p>
        <?php if (trim((string) $post['featured_image']) !== ''): ?>
            <img class="blog-featured" src="/<?= ltrim(e((string) $post['featured_image']), '/') ?>" alt="<?= e((string) $post['title']) ?>">
        <?php endif; ?>
        <div class="blog-content"><?= $post['content'] ?></div>
        <p><a class="btn" href="<?= e(pretty_url('blog.php')) ?>">← همه مقالات</a></p>
    </div>
    </main>
    <?php
    echo render_db_template('footer', $settings) . "\n";
    echo skeleton_foot();
    exit;
}

// فهرست مقالات
$posts = $pdo->query(
    "SELECT id, title, slug, excerpt, featured_image, published_at, created_at FROM blog_posts " .
    "WHERE status = 'published' ORDER BY COALESCE(NULLIF(published_at,''), created_at) DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$seo = [
    'url' => $canonBase . '/blog',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => $canonBase . '/'],
        ['name' => 'وبلاگ', 'url' => $canonBase . '/blog'],
    ],
];
$title = 'وبلاگ — ' . $siteTitle;
$head = skeleton_head($settings, $title, 'مقالات آموزشی لاینرلایت درباره نورپردازی خطی و دکوراتیو.', $seo);
$head = str_replace('<head>', '<head>\n<base href="/">', $head);
echo $head;
echo render_db_template('header', $settings) . "\n";
?>
<main id="main">
<div class="page-body blog-list">
    <h1>وبلاگ</h1>
    <p class="muted">مقالات آموزشی درباره نورپردازی، چراغ‌های خطی و ایده‌های دکوراتیو.</p>
    <?php if ($posts === []): ?>
        <p class="muted">هنوز مقاله‌ای منتشر نشده است.</p>
    <?php else: ?>
    <div class="blog-grid">
        <?php foreach ($posts as $p): ?>
        <a class="blog-card" href="<?= e($canonBase . '/blog/' . $p['slug']) ?>">
            <div class="blog-thumb">
            <?php if (trim((string) $p['featured_image']) !== ''): ?>
                <img src="<?= e((string) $p['featured_image']) ?>" alt="<?= e((string) $p['title']) ?>" loading="lazy">
            <?php endif; ?>
            </div>
            <div class="blog-card-body">
                <h2><?= e((string) $p['title']) ?></h2>
                <?php if (trim((string) $p['excerpt']) !== ''): ?><p><?= e((string) $p['excerpt']) ?></p><?php endif; ?>
                <div class="blog-card-meta">
                    <span><?= e(blog_fa_date((string) ($p['published_at'] ?? $p['created_at']))) ?></span>
                    <span class="read-more">خواندن ←</span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</main>
<?php
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();

function blog_fa_date(string $dt): string
{
    $dt = trim($dt);
    if ($dt === '') { return ''; }
    try {
        if (function_exists('jdate')) { return jdate('Y/m/d', strtotime($dt)); }
        return (new DateTime($dt))->format('Y/m/d');
    } catch (Throwable $e) { return $dt; }
}
