<?php
// modules/categories/admin.php — مدیریت دسته‌بندی‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

function categories_render_admin(array $d): void
{
    extract($d);
    ?>            <h1>دسته‌بندی‌های محصولات</h1>
            <p class="muted">دسته‌ها می‌توانند زیردسته داشته باشند. دسته‌ای که محصول یا زیردسته دارد حذف نمی‌شود. پیش‌نمایش زنده کاتالوگ: <a href="/products" target="_blank">/products</a></p>

            <?php if ($editCategory === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="category-form-panel" aria-expanded="false">+ افزودن دسته‌بندی</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="category-form-panel" <?= $editCategory !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editCategory !== null ? 'ویرایش دسته‌بندی: ' . e($editCategory['title'] ?? '') : 'دسته‌بندی تازه' ?></h2>
            <form method="post" enctype="multipart/form-data" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editCategory !== null ? 'update_category' : 'add_category' ?>">
                <?php if ($editCategory !== null): ?><input type="hidden" name="id" value="<?= (int) $editCategory['id'] ?>"><?php endif; ?>
                <label>عنوان دسته *
                    <input type="text" name="title" required value="<?= e($editCategory['title'] ?? '') ?>">
                </label>
                <label>نامک (slug) — خالی بماند خودکار ساخته می‌شود
                    <input type="text" name="slug" dir="ltr" value="<?= e($editCategory['slug'] ?? '') ?>" placeholder="wardrobe-linear">
                </label>
                <label>دسته والد
                    <select name="parent_id">
                        <option value="0">— بدون والد (دسته اصلی) —</option>
                        <?php foreach ($categoriesList as $cc): ?>
                            <?php if ($editCategory !== null && (int) $cc['id'] === (int) $editCategory['id']) { continue; } ?>
                            <option value="<?= (int) $cc['id'] ?>" <?= ($editCategory['parent_id'] ?? null) !== null && (int) $editCategory['parent_id'] === (int) $cc['id'] ? 'selected' : '' ?>><?= e($cc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>توضیح
                    <textarea name="description" rows="2"><?= e($editCategory['description'] ?? '') ?></textarea>
                </label>
                <label>عکس دسته
                    <input type="file" name="image" accept="image/*">
                </label>
                <?php if ($editCategory !== null && !empty($editCategory['image'])): ?>
                    <p><img src="<?= e(UPLOADS_URL . '/' . basename((string) $editCategory['image'])) ?>" alt="" style="max-width:180px;border-radius:8px"></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editCategory['sort_order'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editCategory['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (نمایش در کاتالوگ)
                </label>
                <button type="submit" class="btn <?= $editCategory !== null ? 'edit' : 'add' ?>"><?= $editCategory !== null ? 'ذخیره تغییرات' : 'ساخت دسته' ?></button>
                <?php if ($editCategory !== null): ?><a class="btn" href="admin.php?page=categories">انصراف</a><?php endif; ?>
            </form>
            </div>

            <h2>فهرست دسته‌ها (<?= count($categoriesList) ?>)</h2>
            <?php if ($categoriesList === []): ?>
                <div class="card wide"><p class="muted">هنوز دسته‌ای ساخته نشده است.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>عنوان</th><th>نامک</th><th>والد</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php
                $catTitles = [];
                foreach ($categoriesList as $cc) { $catTitles[(int) $cc['id']] = (string) $cc['title']; }
                foreach ($categoriesList as $cc):
                ?>
                    <tr>
                        <td><?= !empty($cc['parent_id']) ? '↳ ' : '' ?><?= e($cc['title']) ?></td>
                        <td><span dir="ltr"><?= e($cc['slug']) ?></span></td>
                        <td><?= !empty($cc['parent_id']) ? e($catTitles[(int) $cc['parent_id']] ?? '—') : '—' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑ بالا</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓ پایین</button>
                            </form>
                            <?= (int) $cc['sort_order'] ?>
                        </td>
                        <td><?= $cc['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small edit" href="admin.php?page=categories&edit_id=<?= (int) $cc['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این دسته حذف شود؟ (اگر محصول یا زیردسته داشته باشد حذف نمی‌شود)')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «محصولات» */

/**
 * هندلرهای POST دسته‌بندی‌ها — منتقل شده از Catalog.php (۹٫۹۹٫۱۸)
 */
function categories_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
            case 'add_category':
            case 'update_category':
                $catId = (int) ($_POST['id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('عنوان دسته را وارد کنید.');
                }
                $slug = trim((string) ($_POST['slug'] ?? ''));
                if ($slug === '') {
                    // نامک خودکار یکتا برای دسته‌های فارسی
                    $base = 'cat';
                    $n = 0;
                    do {
                        $try = $n === 0 ? $base . '-' . $catId : $base . '-' . $catId . '-' . $n;
                        if ($catId === 0) {
                            $try = $base . '-' . bin2hex(random_bytes(3)) . ($n === 0 ? '' : '-' . $n);
                        }
                        $n++;
                    } while (category_slug_exists($try, $catId));
                    $slug = $try;
                }
                if (category_slug_exists($slug, $catId)) {
                    throw new RuntimeException('این نامک (slug) قبلاً برای دسته دیگری ثبت شده است.');
                }
                $parentId = (int) ($_POST['parent_id'] ?? 0);
                if ($parentId <= 0 || $parentId === $catId || get_category($parentId) === null) {
                    $parentId = null;
                }
                $old = $catId > 0 ? get_category($catId) : null;
                $image = (string) ($old['image'] ?? '');
                if (!empty($_POST['remove_image']) && $image !== '') {
                    $oldPath = UPLOADS_DIR . '/' . basename($image);
                    if (is_file($oldPath)) { @unlink($oldPath); }
                    $image = '';
                }
                $up = handle_section_image_upload($_FILES['image'] ?? null, $image !== '' ? $image : null);
                if (!$up['ok']) {
                    throw new RuntimeException((string) $up['error']);
                }
                $image = (string) ($up['filename'] ?? '');
                $data = [
                    ':title' => $title,
                    ':slug'  => $slug,
                    ':parent' => $parentId,
                    ':desc'  => trim((string) ($_POST['description'] ?? '')) ?: null,
                    ':image' => $image ?: null,
                    ':sort'  => (int) ($_POST['sort_order'] ?? 0),
                    ':active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($action === 'update_category' && $catId > 0) {
                    $data[':id'] = $catId;
                    $pdo->prepare('UPDATE product_categories SET title = :title, slug = :slug, parent_id = :parent, description = :desc, image = :image, sort_order = :sort, is_active = :active WHERE id = :id')->execute($data);
                    flash('ok', 'دسته‌بندی به‌روزرسانی شد.');
                } else {
                    $pdo->prepare('INSERT INTO product_categories (title, slug, parent_id, description, image, sort_order, is_active) VALUES (:title, :slug, :parent, :desc, :image, :sort, :active)')->execute($data);
                    flash('ok', 'دسته‌بندی جدید ساخته شد.');
                }
                redirect_admin('admin.php?page=categories');
                // no break

            case 'delete_category':
                $catId = (int) ($_POST['id'] ?? 0);
                $subCount = (int) $pdo->query('SELECT COUNT(*) FROM product_categories WHERE parent_id = ' . $catId)->fetchColumn();
                $prodCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE category_id = ' . $catId)->fetchColumn();
                if ($subCount > 0 || $prodCount > 0) {
                    $parts = [];
                    if ($prodCount > 0) { $parts[] = $prodCount . ' محصول'; }
                    if ($subCount > 0) { $parts[] = $subCount . ' زیردسته'; }
                    throw new RuntimeException('این دسته قابل حذف نیست چون ' . implode(' و ', $parts) . ' دارد. اول آن‌ها را جابه‌جا یا حذف کنید.');
                }
                $cat = get_category($catId);
                $pdo->prepare('DELETE FROM product_categories WHERE id = :id')->execute([':id' => $catId]);
                if ($cat !== null && !empty($cat['image'])) {
                    @unlink(UPLOADS_DIR . '/' . basename((string) $cat['image']));
                }
                flash('ok', 'دسته‌بندی حذف شد.');
                redirect_admin('admin.php?page=categories');
                // no break

            case 'move_category':
                move_row($pdo, 'product_categories', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
                redirect_admin('admin.php?page=categories');
                // no break
    }
}

