<?php
// admin_blog.php — مدیریت وبلاگ/مقالات (نسخه ۹٫۱۴)
declare(strict_types=1);

if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

function blog_post_actions(): array
{
    return ['add_blog_post', 'update_blog_post', 'delete_blog_post', 'toggle_blog_post'];
}

function blog_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
        case 'add_blog_post':
        case 'update_blog_post':
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '') { throw new RuntimeException('عنوان مقاله الزامی است.'); }
            $slug = trim((string) ($_POST['slug'] ?? ''));
            if ($slug === '') {
                $slug = blog_make_slug($title);
            }
            $slug = preg_replace('/[^a-z0-9_-]/', '-', strtolower($slug));
            $slug = trim(preg_replace('/-+/', '-', $slug), '-');
            if ($slug === '') { $slug = 'post-' . time(); }
            // یکتایی اسلاگ
            $chk = $pdo->prepare('SELECT id FROM blog_posts WHERE slug = :s AND id != :id LIMIT 1');
            $chk->execute([':s' => $slug, ':id' => $id]);
            if ($chk->fetch() !== false) { $slug .= '-' . time(); }

            $data = [
                ':t' => $title,
                ':s' => $slug,
                ':e' => trim((string) ($_POST['excerpt'] ?? '')),
                ':c' => (string) ($_POST['content'] ?? ''),
                ':f' => trim((string) ($_POST['featured_image'] ?? '')),
                ':st' => (($_POST['status'] ?? 'draft') === 'published') ? 'published' : 'draft',
                ':p' => trim((string) ($_POST['published_at'] ?? '')),
            ];
            if ($action === 'add_blog_post') {
                $pdo->prepare(
                    'INSERT INTO blog_posts (title, slug, excerpt, content, featured_image, status, published_at) ' .
                    'VALUES (:t, :s, :e, :c, :f, :st, :p)'
                )->execute($data);
                flash('ok', 'مقاله «' . $title . '» ساخته شد.');
            } else {
                if ($id <= 0) { throw new RuntimeException('مقاله نامعتبر است.'); }
                $data[':id'] = $id;
                $pdo->prepare(
                    'UPDATE blog_posts SET title=:t, slug=:s, excerpt=:e, content=:c, featured_image=:f, ' .
                    'status=:st, published_at=:p, updated_at=CURRENT_TIMESTAMP WHERE id=:id'
                )->execute($data);
                flash('ok', 'مقاله به‌روز شد.');
            }
            redirect_admin('admin.php?page=blog');
            // no break

        case 'delete_blog_post':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) { throw new RuntimeException('مقاله نامعتبر است.'); }
            $pdo->prepare('DELETE FROM blog_posts WHERE id = :id')->execute([':id' => $id]);
            flash('ok', 'مقاله حذف شد.');
            redirect_admin('admin.php?page=blog');
            // no break

        case 'toggle_blog_post':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) { throw new RuntimeException('مقاله نامعتبر است.'); }
            $pdo->prepare(
                "UPDATE blog_posts SET status = CASE WHEN status = 'published' THEN 'draft' ELSE 'published' END, " .
                "published_at = CASE WHEN status = 'draft' AND (published_at IS NULL OR published_at = '') THEN date('now') ELSE published_at END, " .
                "updated_at = CURRENT_TIMESTAMP WHERE id = :id"
            )->execute([':id' => $id]);
            flash('ok', 'وضعیت انتشار تغییر کرد.');
            redirect_admin('admin.php?page=blog');
            // no break
    }
}

function blog_make_slug(string $title): string
{
    // اسلاگ ساده از عنوان فارسی: حروف فارسی نگه داشته می‌شود
    $s = trim($title);
    $s = preg_replace('/\s+/', '-', $s);
    $s = preg_replace('/[^a-zA-Z0-9_\x{0600}-\x{06FF}-]/u', '', $s);
    return $s !== '' ? $s : 'post-' . time();
}

function blog_render_admin(array $d): void
{
    $posts = $d['blog_posts'] ?? [];
    $edit = $d['blog_edit'] ?? null;
    ?>
    <h1>وبلاگ / مقالات</h1>
    <p class="muted">مقالات آموزشی سایت؛ آدرس عمومی: <a href="/blog" target="_blank" dir="ltr">/blog</a></p>

    <div class="crud-toolbar">
        <button type="button" class="btn add" data-toggle-panel="blog-form-panel" aria-expanded="false">+ مقاله تازه</button>
    </div>
    <div class="crud-panel" id="blog-form-panel" <?= $edit ? '' : 'hidden' ?>>
        <h2><?= $edit ? 'ویرایش مقاله' : 'مقاله تازه' ?></h2>
        <form method="post" class="card wide">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit ? 'update_blog_post' : 'add_blog_post' ?>">
            <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <label>عنوان *
                <input type="text" name="title" required value="<?= e((string) ($edit['title'] ?? '')) ?>">
            </label>
            <div class="inline-fields">
                <label>اسلاگ (انگلیسی، خالی = خودکار)
                    <input type="text" name="slug" dir="ltr" value="<?= e((string) ($edit['slug'] ?? '')) ?>" placeholder="my-article">
                </label>
                <label>وضعیت
                    <select name="status">
                        <option value="draft" <?= (($edit['status'] ?? '') !== 'published') ? 'selected' : '' ?>>پیش‌نویس</option>
                        <option value="published" <?= (($edit['status'] ?? '') === 'published') ? 'selected' : '' ?>>منتشرشده</option>
                    </select>
                </label>
            </div>
            <label>خلاصه
                <textarea name="excerpt" rows="2"><?= e((string) ($edit['excerpt'] ?? '')) ?></textarea>
            </label>
            <label>تصویر شاخص (آدرس)
                <input type="text" name="featured_image" dir="ltr" value="<?= e((string) ($edit['featured_image'] ?? '')) ?>" placeholder="uploads/gallery/...">
            </label>
            <label>متن مقاله
                <textarea name="content" id="blog-content-editor" rows="12" dir="rtl"><?= e((string) ($edit['content'] ?? '')) ?></textarea>
            </label>
            <script src="assets/tinymce/tinymce.min.js"></script>
            <script>
            (function(){
                if (typeof tinymce === 'undefined') return;
                tinymce.init({
                    selector: '#blog-content-editor',
                    directionality: 'rtl',
                    height: 420,
                    menubar: false,
                    plugins: 'lists link image table code fullscreen',
                    toolbar: 'undo redo | blocks | bold italic underline | alignright aligncenter alignleft | bullist numlist | link image table | code fullscreen',
                    block_formats: 'پاراگراف=p;تیتر ۲=h2;تیتر ۳=h3',
                    content_style: 'body{font-family:Vazirmatn,Tahoma,sans-serif;direction:rtl;font-size:15px;line-height:2}',
                    branding: false,
                    promotion: false,
                });
            })();
            </script>
            <button type="submit" class="btn add">ذخیره</button>
            <?php if ($edit): ?><a class="btn" href="admin.php?page=blog">انصراف</a><?php endif; ?>
        </form>
    </div>

    <table>
        <thead><tr><th>عنوان</th><th>اسلاگ</th><th>وضعیت</th><th>تاریخ انتشار</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($posts as $p): ?>
            <tr>
                <td><strong><?= e((string) $p['title']) ?></strong></td>
                <td dir="ltr"><a href="/blog/<?= e((string) $p['slug']) ?>" target="_blank"><?= e((string) $p['slug']) ?></a></td>
                <td><?= $p['status'] === 'published' ? '<span style="color:#16a34a">منتشرشده</span>' : '<span class="muted">پیش‌نویس</span>' ?></td>
                <td><?= e((string) ($p['published_at'] ?? '—')) ?></td>
                <td class="row-actions">
                    <a class="btn small edit" href="admin.php?page=blog&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                    <form method="post" style="display:inline" onsubmit="return confirm('وضعیت انتشار تغییر کند؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="toggle_blog_post">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button class="btn small" type="submit"><?= $p['status'] === 'published' ? 'لغو انتشار' : 'انتشار' ?></button>
                    </form>
                    <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_blog_post">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button class="btn small danger" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($posts === []): ?>
            <tr><td colspan="5" class="muted">هنوز مقاله‌ای نیست.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    <?php
}
