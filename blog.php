<?php
/**
 * وبلاگ/مقالات — entry point (ماژول واقعی از ۹٫۹۹٫۱۳)
 * منطق اصلی در modules/blog/ است و خودکار لود می‌شود.
 * آدرس تمیز: /blog و /blog/<slug>
 */
declare(strict_types=1);

require __DIR__ . '/core/config.php';
cms_session_start();

if (!module_is_enabled('blog')) {
    http_response_code(404);
    exit('وبلاگ غیرفعال است.');
}

$settings = all_settings();
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug !== '') {
    $post = blog_get_post($slug);
    if ($post === null) {
        http_response_code(404);
        $siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');
        $title = 'یافت نشد — ' . $siteTitle;
        echo skeleton_head($settings, $title, '', ['url' => site_base_url($settings) . '/blog']);
        echo render_db_template('header', $settings) . "\n";
        echo '<main id="main"><div class="page-body"><h1>مقاله یافت نشد</h1><p><a class="btn" href="' . e(pretty_url('blog.php')) . '">بازگشت به وبلاگ</a></p></div></main>';
        echo render_db_template('footer', $settings) . "\n";
        echo skeleton_foot();
        exit;
    }
    blog_render_single($post, $settings);
    exit;
}

blog_render_list($settings);
