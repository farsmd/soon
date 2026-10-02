<?php
/**
 * نسخه ۹ — رابط کاربری صفحه‌ساز ویژوال (بلوک‌های صفحه)
 * این فایل از admin.php در نمای ویرایش صفحه include می‌شود.
 * متغیرهای موردنیاز: $pdo، $editPage، $pageBlocks، $editBlock
 */
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }

$blockTypes = page_block_types();
$editBlockSettings = [];
if ($editBlock !== null) {
    $editBlockSettings = json_decode((string) ($editBlock['settings'] ?? '{}'), true) ?: [];
}
?>
<div id="blocks" style="margin-top:32px">
    <h2>صفحه‌ساز ویژوال <span class="muted" style="font-size:13px;font-weight:normal">— بلوک‌ها به ترتیب زیر صفحه نمایش داده می‌شوند</span></h2>
    <p class="muted">با بلوک‌ها می‌توانید صفحه را بدون کدنویسی بسازید: متن، تصویر، گالری، ویترین محصولات، دکمه اقدام، کارت ویژگی، جداکننده و ویدیو.</p>

    <div class="crud-toolbar">
        <button type="button" class="btn add" data-toggle-panel="block-form-panel" aria-expanded="false">+ افزودن بلوک جدید</button>
    </div>

    <div class="crud-panel" id="block-form-panel" <?= $editBlock !== null ? 'data-open="1"' : 'hidden' ?>>
        <h3><?= $editBlock ? 'ویرایش بلوک: ' . e($blockTypes[$editBlock['block_type']] ?? '') : 'افزودن بلوک جدید' ?></h3>
        <form method="post" class="card wide">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editBlock ? 'update_block' : 'add_block' ?>">
            <input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>">
            <?php if ($editBlock): ?><input type="hidden" name="block_id" value="<?= (int) $editBlock['id'] ?>"><?php endif; ?>
            <label>نوع بلوک
                <select name="block_type" id="blockTypeSelect" required>
                    <?php foreach ($blockTypes as $bk => $bt): ?>
                    <option value="<?= e($bk) ?>" <?= ($editBlock['block_type'] ?? 'text') === $bk ? 'selected' : '' ?>><?= e($bt) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>عنوان بلوک <span class="muted">(اختیاری — بالای بلوک نمایش داده می‌شود)</span>
                <input type="text" name="block_title" value="<?= e($editBlock['title'] ?? '') ?>" placeholder="مثلاً چرا لاینرلایت؟">
            </label>
            <div id="blockContentWrap">
                <label>محتوا
                    <textarea name="block_content" rows="6" id="blockContentField" placeholder="متن یا محتوای بلوک..."><?= e($editBlock['content'] ?? '') ?></textarea>
                </label>
                <p class="muted" id="blockContentHint" style="font-size:12px"></p>
            </div>
            <div id="blockSettingsWrap">
                <label>متن جایگزین عکس (alt)
                    <input type="text" name="setting_alt" value="<?= e($editBlockSettings['alt'] ?? '') ?>">
                </label>
                <label>کپشن زیر عکس
                    <input type="text" name="setting_caption" value="<?= e($editBlockSettings['caption'] ?? '') ?>">
                </label>
                <label>متن دکمه (برای CTA)
                    <input type="text" name="setting_btn_text" value="<?= e($editBlockSettings['btn_text'] ?? 'شروع کنید') ?>">
                </label>
                <label>لینک دکمه (برای CTA)
                    <input type="text" name="setting_btn_url" value="<?= e($editBlockSettings['btn_url'] ?? 'products.php') ?>" dir="ltr">
                </label>
            </div>
            <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editBlock || (int) $editBlock['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
            <button type="submit" class="btn <?= $editBlock ? 'edit' : 'add' ?>"><?= $editBlock ? 'ذخیره ویرایش' : 'افزودن بلوک' ?></button>
            <?php if ($editBlock): ?><a class="btn" href="admin.php?page=pages&edit_id=<?= (int) $editPage['id'] ?>#blocks">انصراف</a><?php endif; ?>
        </form>
    </div>

    <script>
    (function(){
        var hints = {
            text: 'متن بلوک — می‌توانید HTML ساده هم بنویسید.',
            image: 'آدرس کامل عکس را وارد کنید (مثلاً uploads/gallery/gallery-01.jpg).',
            gallery: 'هر عکس در یک خط جدا — آدرس کامل هر عکس.',
            products: 'این بلوک خودکار ویترین محصولات فعال را نمایش می‌دهد؛ نیازی به محتوا نیست.',
            cta: 'متن توضیح کوتاه بنویسید؛ متن و لینک دکمه را در فیلدهای پایین تنظیم کنید.',
            features: 'هر ویژگی در یک خط — فرمت: عنوان | توضیح',
            divider: 'نیازی به محتوا نیست؛ یک خط جداکننده نمایش می‌دهد.',
            video: 'آدرس ویدیو (آپارات، یوتیوب یا فایل mp4).'
        };
        var sel = document.getElementById('blockTypeSelect');
        var hint = document.getElementById('blockContentHint');
        function upd(){ if(sel && hint){ hint.textContent = hints[sel.value] || ''; } }
        if(sel){ sel.addEventListener('change', upd); upd(); }
    })();
    </script>

    <?php if ($pageBlocks === []): ?>
        <p class="muted">هنوز بلوکی برای این صفحه ساخته نشده است.</p>
    <?php else: ?>
    <table>
        <thead><tr><th>ترتیب</th><th>نوع</th><th>عنوان</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($pageBlocks as $b): ?>
            <tr>
                <td><?= (int) $b['sort_order'] ?></td>
                <td><span class="badge"><?= e($blockTypes[$b['block_type']] ?? $b['block_type']) ?></span></td>
                <td><?= e($b['title'] !== '' ? $b['title'] : '—') ?></td>
                <td><?= (int) $b['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                <td class="actions">
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_block"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small">↑</button></form>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_block"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small">↓</button></form>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_block"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>"><button type="submit" class="btn small warn"><?= (int) $b['is_active'] === 1 ? 'غیرفعال' : 'فعال' ?></button></form>
                    <a class="btn small edit" href="admin.php?page=pages&edit_id=<?= (int) $editPage['id'] ?>&edit_block=<?= (int) $b['id'] ?>#blocks">ویرایش</a>
                    <form method="post" class="inline" onsubmit="return confirm('این بلوک حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_block"><input type="hidden" name="block_id" value="<?= (int) $b['id'] ?>"><input type="hidden" name="page_id" value="<?= (int) $editPage['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p><a class="btn" href="page.php?slug=<?= urlencode((string) $editPage['slug']) ?>" target="_blank" rel="noopener">👁 پیش‌نمایش صفحه</a></p>
</div>
