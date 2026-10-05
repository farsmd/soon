<?php
// modules/pages/admin.php — مدیریت صفحه‌ها (استخراج از admin.php در ۹٫۹۹٫۲۵)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }

/** هندلر صفحه مدیریت صفحه‌ها */
function pages_handle_page(): void
{
    global $pdo, $page, $pages, $editPage, $pageBlocks, $editBlock;

        foreach ($pages as $p) {
            if ((int) $p['id'] === (int) $_GET['edit_id']) { $editPage = $p; break; }
        }
        // نسخه ۹٫۰٫۲: ویرایش صفحه گالری مستقیم به مدیریت گالری می‌رود — بدون دست‌زدن به کد
        if ($editPage !== null && (string) ($editPage['slug'] ?? '') === 'gallery' && !isset($_GET['raw'])) {
            redirect_admin('admin.php?page=gallery');
        }
        if ($editPage !== null) {
            $pageBlocks = get_page_blocks((int) $editPage['id'], false);
            if (isset($_GET['edit_block'])) {
                foreach ($pageBlocks as $b) {
                    if ((int) $b['id'] === (int) $_GET['edit_block']) { $editBlock = $b; break; }
                }
            }
        }
}

/** رندر صفحه مدیریت صفحه‌ها */
function pages_render_page(): void
{
    global $pdo, $page, $pages, $editPage, $pageBlocks, $editBlock;
    include __DIR__ . '/render.php';
}
