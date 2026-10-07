<?php
// page.php — نمایش یک صفحه جدا با آدرس page.php?slug=... (نسخه ۶)
// متن صفحه با قالب دیتابیسی «single» و پلیس‌هولدرهای {{page_title}} و {{page_content}} ساخته می‌شود.

declare(strict_types=1);

require __DIR__ . '/core/config.php';

// لاگ بازدید و کلیک‌های سایت (نسخه ۸٫۲)
cms_session_start();
process_contact_form();
process_partner_form();
track_public_request();

$settings = all_settings();
$slug = trim((string) ($_GET['slug'] ?? ''));
$page = ($slug !== '' && is_valid_slug($slug)) ? get_page_by_slug($slug, true) : null;

$siteTitle = (string) ($settings['site_title'] ?? 'وب‌سایت من');

if ($page === null) {
    http_response_code(404);
    $title = 'صفحه پیدا نشد — ' . $siteTitle;
    $desc  = (string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : (string) ($settings['site_description'] ?? '');
    $viewPage = [
        'title'   => 'صفحه پیدا نشد',
        'content' => notfound_auto_redirect_html(),
    ];
} else {
    $title = (string) ($page['seo_title'] ?? '') !== '' ? (string) $page['seo_title'] : ((string) $page['title'] . ' — ' . $siteTitle);
    $desc  = (string) ($page['seo_description'] ?? '') !== '' ? (string) $page['seo_description'] : (string) ($settings['site_description'] ?? '');
    $viewPage = $page;
}

$canonBase = site_base_url($settings);
$pageSeo = [];
if ($page !== null && $canonBase !== '') {
    $pageSeo['url'] = $canonBase . '/' . $slug;
    $pageSeo['breadcrumbs'] = [
        ['name' => 'خانه', 'url' => $canonBase . '/'],
        ['name' => (string) $page['title'], 'url' => $canonBase . '/' . $slug],
    ];
}
echo skeleton_head($settings, $title, $desc, $pageSeo);
echo render_db_template('header', $settings) . "\n";
echo '<main id="main">' . "\n";
$tplKey = ($page !== null && ($page['slug'] ?? '') === 'contact') ? 'contact_page' : 'single';
echo render_db_template($tplKey, $settings, null, $viewPage) . "\n";
// نسخه ۹: بلوک‌های صفحه‌ساز ویژوال
if ($page !== null) {
    echo render_page_blocks((int) $page['id']) . "\n";
}
echo '</main>' . "\n";
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();
