<?php
// modules/blog/frontend.php — توابع فرانت‌اند وبلاگ (ماژول واقعی از ۹٫۹۹٫۱۳)
declare(strict_types=1);

/** تاریخ شمسی برای نمایش */
function blog_fa_date(string $dt): string
{
    $dt = trim($dt);
    if ($dt === '') { return ''; }
    try {
        if (function_exists('jdate')) { return jdate('Y/m/d', strtotime($dt)); }
        return (new DateTime($dt))->format('Y/m/d');
    } catch (Throwable $e) { return $dt; }
}

/** دریافت یک مقاله منتشرشده با اسلاگ */
function blog_get_post(string $slug): ?array
{
    $st = db()->prepare("SELECT * FROM blog_posts WHERE slug = :s AND status = 'published' LIMIT 1");
    $st->execute([':s' => $slug]);
    $post = $st->fetch(PDO::FETCH_ASSOC);
    return $post ?: null;
}

/** فهرست مقالات منتشرشده */
function blog_get_posts(int $limit = 0): array
{
    $sql = "SELECT id, title, slug, excerpt, featured_image, published_at, created_at, template FROM blog_posts " .
        "WHERE status = 'published' ORDER BY COALESCE(NULLIF(published_at,''), created_at) DESC";
    if ($limit > 0) { $sql .= " LIMIT " . $limit; }
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/** رندر صفحه تکی مقاله با سیستم قالب */
function blog_render_single(array $post, array $settings): void
{
    $siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');
    $canonBase = site_base_url($settings);
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
    $head = str_replace('<head>', '<head>' . "\n" . '<base href="/">', $head);
    echo $head;
    echo render_db_template('header', $settings) . "\n";
    echo '<main id="main">';
    template_render('post', [
        'title' => (string) $post['title'],
        'content' => (string) ($post['content'] ?? ''),
        'image' => (string) ($post['featured_image'] ?? ''),
        'date' => blog_fa_date((string) ($post['published_at'] ?? $post['created_at'])),
        'excerpt' => (string) ($post['excerpt'] ?? ''),
    ], (string) ($post['template'] ?? ''), (string) $post['slug']);
    echo '</main>';
    echo render_db_template('footer', $settings) . "\n";
    echo skeleton_foot();
}

/** رندر فهرست مقالات */
function blog_render_list(array $settings): void
{
    $posts = blog_get_posts();
    $siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');
    $canonBase = site_base_url($settings);
    $seo = [
        'url' => $canonBase . '/blog',
        'breadcrumbs' => [
            ['name' => 'خانه', 'url' => $canonBase . '/'],
            ['name' => 'وبلاگ', 'url' => $canonBase . '/blog'],
        ],
    ];
    $title = 'وبلاگ — ' . $siteTitle;
    $head = skeleton_head($settings, $title, 'مقالات آموزشی لاینرلایت درباره نورپردازی خطی و دکوراتیو.', $seo);
    $head = str_replace('<head>', '<head>' . "\n" . '<base href="/">', $head);
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
}
