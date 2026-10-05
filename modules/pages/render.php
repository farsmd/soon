<?php
// modules/pages/render.php — قالب مدیریت صفحه‌ها (استخراج از admin.php در ۹٫۹۹٫۲۵)
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
global $pdo, $page, $pages, $editPage, $pageBlocks, $editBlock;
?>
            <h1>صفحه‌ها</h1>
            <p class="muted">هر صفحه آدرس جدا دارد: <code>page.php?slug=نامک</code>. اگر «نمایش در منو» فعال باشد، لینکش خودکار به منوی سایت (هدر و قالب تک‌صفحه) اضافه می‌شود. لینک «خانه» همیشه اول منو است.</p>

            <?php if ($editPage === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="page-form-panel" aria-expanded="false">+ ساخت صفحه جدید</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="page-form-panel" <?= $editPage !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editPage ? 'ویرایش صفحه: ' . e($editPage['title'] ?? '') : 'ساخت صفحه جدید' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editPage ? 'update_page' : 'add_page' ?>">
                <?php if ($editPage): ?><input type="hidden" name="id" value="<?= (int) $editPage['id'] ?>"><?php endif; ?>
                <label>عنوان صفحه
                    <input type="text" name="title" required value="<?= e($editPage['title'] ?? '') ?>" placeholder="مثلاً درباره ما">
                </label>
                <label>نامک (slug) — فقط حروف انگلیسی، عدد، خط تیره و آندرلاین
                    <span class="file-input">page.php?slug=<input type="text" name="slug" required pattern="[A-Za-z0-9\-_]+" value="<?= e($editPage['slug'] ?? '') ?>" placeholder="about"></span>
                </label>
                <label>محتوای صفحه
                    <div class="editor-tabs">
                        <button type="button" class="btn small active" data-etab="visual">ویرایشگر بصری</button>
                        <button type="button" class="btn small" data-etab="code">کد HTML</button>
                    </div>
                    <div id="gjs-wrap" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <div id="gjs" style="height:500px"></div>
                    </div>
                    <textarea name="content" id="page-content-code" rows="10" class="code-editor" data-mode="htmlmixed" dir="ltr" style="display:none"><?= e($editPage['content'] ?? '') ?></textarea>
                </label>
                <link rel="stylesheet" href="assets/grapes.min.css">
                <script src="assets/grapes.min.js"></script>
                <script>
                (function(){
                    if (typeof grapesjs === 'undefined') return;
                    var codeArea = document.getElementById('page-content-code');
                    var gjsWrap = document.getElementById('gjs-wrap');
                    var editor = grapesjs.init({
                        container: '#gjs',
                        fromElement: false,
                        height: '600px',
                        width: 'auto',
                        storageManager: false,
                        deviceManager: {
                            devices: [
                                { name: 'دسکتاپ', width: '' },
                                { name: 'تبلت', width: '768px', widthMedia: '992px' },
                                { name: 'موبایل', width: '375px', widthMedia: '768px' },
                            ]
                        },
                        panels: {
                            defaults: [
                                { id: 'basic-actions', el: '.gjs-pn-basic', buttons: [
                                    { id: 'undo', className: 'fa fa-undo', command: 'core:undo', attributes: { title: 'برگردان' } },
                                    { id: 'redo', className: 'fa fa-repeat', command: 'core:redo', attributes: { title: 'جلوبر' } },
                                    { id: 'fullscreen', className: 'fa fa-expand', command: 'gjs-fullscreen', attributes: { title: 'تمام‌صفحه' } },
                                    { id: 'preview-devices', className: '', buttons: [
                                        { id: 'device-desktop', className: 'fa fa-desktop', command: 'set-device-desktop', attributes: { title: 'دسکتاپ' } },
                                        { id: 'device-tablet', className: 'fa fa-tablet', command: 'set-device-tablet', attributes: { title: 'تبلت' } },
                                        { id: 'device-mobile', className: 'fa fa-mobile', command: 'set-device-mobile', attributes: { title: 'موبایل' } },
                                    ]},
                                ]},
                                { id: 'panel-blocks', el: '.gjs-pn-blocks', buttons: [{ id: 'show-blocks', active: true, label: 'بلوک‌ها', command: 'show-blocks' }] },
                                { id: 'panel-layers', el: '.gjs-pn-layers', buttons: [{ id: 'show-layers', label: 'لایه‌ها', command: 'show-layers' }] },
                                { id: 'panel-styles', el: '.gjs-pn-styles', buttons: [{ id: 'show-styles', label: 'استایل', command: 'show-styles' }] },
                            ]
                        },
                        blockManager: {
                            appendTo: '#gjs-blocks',
                            blocks: [
                                { id: 'hero', label: 'هیرو', category: 'سکشن', content: '<section style="padding:80px 20px;text-align:center;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff"><h1 style="font-size:36px;margin-bottom:16px">تیتر اصلی</h1><p style="font-size:18px;opacity:.8;margin-bottom:24px">توضیح کوتاه</p><a href="#" style="display:inline-block;padding:14px 36px;background:#d4a017;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">دکمه</a></section>' },
                                { id: 'features', label: 'ویژگی‌ها', category: 'سکشن', content: '<section style="padding:60px 20px"><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1000px;margin:0 auto"><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">⭐</div><h3>ویژگی ۱</h3><p>توضیح</p></div><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">🚀</div><h3>ویژگی ۲</h3><p>توضیح</p></div><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">💡</div><h3>ویژگی ۳</h3><p>توضیح</p></div></div></section>' },
                                { id: 'cta', label: 'دعوت به اقدام', category: 'سکشن', content: '<section style="padding:60px 20px;background:#fef9c3;text-align:center"><h2>آماده‌اید؟</h2><p>همین حالا شروع کنید</p><a href="#" style="display:inline-block;padding:14px 36px;background:#16a34a;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;margin-top:16px">شروع</a></section>' },
                                { id: 'faq', label: 'سؤالات متداول', category: 'سکشن', content: '<section style="padding:60px 20px;max-width:800px;margin:0 auto"><h2 style="text-align:center;margin-bottom:32px">سؤالات متداول</h2><details style="margin-bottom:12px;padding:16px;border:1px solid #e5e7eb;border-radius:10px"><summary style="font-weight:700;cursor:pointer">سؤال ۱؟</summary><p style="margin-top:8px">پاسخ...</p></details><details style="margin-bottom:12px;padding:16px;border:1px solid #e5e7eb;border-radius:10px"><summary style="font-weight:700;cursor:pointer">سؤال ۲؟</summary><p style="margin-top:8px">پاسخ...</p></details></section>' },
                                { id: 'text', label: 'متن', category: 'پایه', content: '<p>متن خود را اینجا بنویسید</p>' },
                                { id: 'heading', label: 'تیتر', category: 'پایه', content: '<h2>تیتر</h2>' },
                                { id: 'image', label: 'عکس', category: 'پایه', content: { type: 'image' } },
                                { id: 'columns-2', label: 'دو ستون', category: 'چیدمان', content: '<div style="display:flex;gap:16px"><div style="flex:1"><p>ستون ۱</p></div><div style="flex:1"><p>ستون ۲</p></div></div>' },
                                { id: 'columns-3', label: 'سه ستون', category: 'چیدمان', content: '<div style="display:flex;gap:16px"><div style="flex:1"><p>ستون ۱</p></div><div style="flex:1"><p>ستون ۲</p></div><div style="flex:1"><p>ستون ۳</p></div></div>' },
                                { id: 'button', label: 'دکمه', category: 'پایه', content: '<a href="#" style="display:inline-block;padding:12px 28px;background:#d4a017;color:#fff;border-radius:10px;text-decoration:none">دکمه</a>' },
                                { id: 'divider', label: 'جداکننده', category: 'پایه', content: '<hr>' },
                                { id: 'video', label: 'ویدیو', category: 'رسانه', content: '<video controls style="width:100%;border-radius:12px"><source src="" type="video/mp4"></video>' },
                                { id: 'map', label: 'نقشه', category: 'رسانه', content: '<iframe src="https://www.google.com/maps/embed?pb=..." style="width:100%;height:300px;border:0;border-radius:12px" loading="lazy"></iframe>' },
                            ]
                        },
                        styleManager: {
                            appendTo: '.gjs-pn-styles',
                            sectors: [
                                { name: 'ابعاد', open: false, properties: ['width', 'height', 'max-width', 'padding', 'margin'] },
                                { name: 'تایپوگرافی', open: false, properties: ['font-family', 'font-size', 'font-weight', 'color', 'line-height', 'text-align'] },
                                { name: 'پس‌زمینه', open: false, properties: ['background-color', 'background'] },
                                { name: 'کادر', open: false, properties: ['border', 'border-radius'] },
                            ]
                        },
                        layerManager: { appendTo: '.gjs-pn-layers' },
                    });
                    // دستور تمام‌صفحه
                    editor.Commands.add('gjs-fullscreen', {
                        run: function(ed) { gjsWrap.classList.add('gjs-fullscreen'); ed.refresh(); },
                        stop: function(ed) { gjsWrap.classList.remove('gjs-fullscreen'); ed.refresh(); },
                    });
                    // دستورات دستگاه
                    ['desktop', 'tablet', 'mobile'].forEach(function(d){
                        editor.Commands.add('set-device-' + d, { run: function(ed){ ed.setDevice(d === 'desktop' ? 'دسکتاپ' : d === 'tablet' ? 'تبلت' : 'موبایل'); } });
                    });
                    // نمایش پنل‌ها
                    editor.Commands.add('show-blocks', { run: function(ed){ document.getElementById('gjs-blocks').style.display = ''; document.querySelector('.gjs-pn-layers').style.display = 'none'; document.querySelector('.gjs-pn-styles').style.display = 'none'; }, stop: function(){} });
                    editor.Commands.add('show-layers', { run: function(){ document.getElementById('gjs-blocks').style.display = 'none'; document.querySelector('.gjs-pn-layers').style.display = ''; document.querySelector('.gjs-pn-styles').style.display = 'none'; }, stop: function(){} });
                    editor.Commands.add('show-styles', { run: function(){ document.getElementById('gjs-blocks').style.display = 'none'; document.querySelector('.gjs-pn-layers').style.display = 'none'; document.querySelector('.gjs-pn-styles').style.display = ''; }, stop: function(){} });
                    // بارگذاری محتوای فعلی
                    editor.setComponents(codeArea.value || '<p>محتوای صفحه...</p>');
                    // تب‌ها
                    var tabs = document.querySelectorAll('[data-etab]');
                    var gjsWrap = document.getElementById('gjs-wrap');
                    tabs.forEach(function(btn){
                        btn.addEventListener('click', function(){
                            tabs.forEach(function(b){ b.classList.remove('active'); });
                            btn.classList.add('active');
                            if (btn.dataset.etab === 'visual') {
                                gjsWrap.style.display = '';
                                codeArea.style.display = 'none';
                                editor.setComponents(codeArea.value);
                            } else {
                                codeArea.value = editor.getHtml();
                                gjsWrap.style.display = 'none';
                                codeArea.style.display = '';
                            }
                        });
                    });
                    // هنگام ارسال فرم، HTML را به textarea برگردان
                    codeArea.closest('form').addEventListener('submit', function(){
                        if (gjsWrap.style.display !== 'none') {
                            codeArea.value = editor.getHtml();
                        }
                    });
                })();
                </script>
                <style>
                .editor-tabs{display:flex;gap:8px;margin-bottom:10px}
                .editor-tabs .btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
                #gjs-blocks{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
                #gjs-wrap.gjs-fullscreen{position:fixed;inset:0;z-index:9999;background:#fff}
                #gjs-wrap.gjs-fullscreen #gjs{height:100vh!important}
                .gjs-pn-panel{display:flex;gap:4px;padding:6px;background:#f8fafc;border-bottom:1px solid #e5e7eb}
                .gjs-pn-btn{padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:12px}
                .gjs-pn-btn.gjs-pn-active{background:#2563eb;color:#fff;border-color:#2563eb}
                .gjs-pn-layers,.gjs-pn-styles{display:none;max-height:400px;overflow:auto;padding:10px;background:#f8fafc}
                </style>
                <div class="gjs-pn-panel gjs-pn-basic"></div>
                <div class="gjs-pn-panel gjs-pn-blocks"></div>
                <div class="gjs-pn-panel gjs-pn-layers"></div>
                <div class="gjs-pn-panel gjs-pn-styles"></div>
                <label>عنوان سئو (SEO) — اگر خالی باشد عنوان صفحه استفاده می‌شود
                    <input type="text" name="seo_title" value="<?= e($editPage['seo_title'] ?? '') ?>">
                </label>
                <label>توضیح سئو (meta description)
                    <textarea name="seo_description" rows="2"><?= e($editPage['seo_description'] ?? '') ?></textarea>
                </label>
                <label>ترتیب در منو/لیست
                    <input type="number" name="sort_order" value="<?= e($editPage['sort_order'] ?? '10') ?>">
                </label>
                <label class="check"><input type="checkbox" name="show_in_menu" value="1" <?= ($editPage && (int) $editPage['show_in_menu'] === 1) ? 'checked' : '' ?>> نمایش در منوی سایت</label>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editPage || (int) $editPage['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
                <button type="submit" class="btn <?= $editPage ? 'edit' : 'add' ?>"><?= $editPage ? 'ذخیره ویرایش' : 'ساخت صفحه' ?></button>
                <?php if ($editPage): ?><a class="btn" href="admin.php?page=pages">انصراف</a><?php endif; ?>
            </form>
            </div>

            <?php if ($editPage !== null): ?>
                <?php include __DIR__ . '/admin_blocks_ui.php'; ?>
            <?php endif; ?>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>نامک (slug)</th><th>در منو</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td><?= (int) $p['sort_order'] ?></td>
                        <td><?= e($p['title']) ?></td>
                        <td><code><?= e($p['slug']) ?></code><br><a href="/<?= urlencode((string) $p['slug']) ?>" target="_blank">مشاهده</a></td>
                        <td><?= (int) $p['show_in_menu'] === 1 ? 'بله' : '<span class="muted">خیر</span>' ?></td>
                        <td><?= (int) $p['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small">↑ بالا</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small">↓ پایین</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn small warn"><?= (int) $p['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form>
                            <?php if ((string) ($p['slug'] ?? '') === 'gallery'): ?>
                            <a class="btn small add" href="admin.php?page=gallery">🖼 مدیریت گالری</a>
                            <?php else: ?>
                            <a class="btn small edit" href="admin.php?page=pages&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                            <?php endif; ?>
                            <form method="post" class="inline" onsubmit="return confirm('این صفحه حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pages === []): ?><tr><td colspan="6" class="muted">هنوز صفحه‌ای ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>
