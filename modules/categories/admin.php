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
