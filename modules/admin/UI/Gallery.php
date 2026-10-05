<?php
/**
 * نسخه ۹ — رابط کاربری مدیریت گالری
 * این فایل از admin.php وقتی $page === 'gallery' است include می‌شود.
 * متغیرهای موردنیاز: $pdo
 */
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }

$galleryFigs = gallery_get_figures($pdo);
$editIdx = isset($_GET['edit_idx']) ? (int) $_GET['edit_idx'] : -1;
?>
<h1>مدیریت گالری</h1>
<p class="muted">عکس‌های صفحه <a href="/gallery" target="_blank" rel="noopener">گالری پروژه‌ها</a> را از اینجا مدیریت کنید. عکس‌ها در پوشه <code>uploads/gallery/</code> ذخیره می‌شوند و با آپدیت سیستم پاک نمی‌شوند.</p>

<div class="crud-toolbar">
    <button type="button" class="btn add" data-toggle-panel="gallery-upload-panel" aria-expanded="false">+ افزودن عکس جدید</button>
</div>

<div class="crud-panel" id="gallery-upload-panel" hidden>
    <h2>آپلود عکس جدید</h2>
    <form method="post" enctype="multipart/form-data" class="card wide" id="galleryUploadForm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="gallery_upload">
        <div class="dropzone" id="galleryDropzone">
            <input type="file" name="gallery_image" id="galleryFileInput" accept="image/jpeg,image/png,image/webp" required hidden>
            <div class="dz-inner">
                <div class="dz-icon">📷</div>
                <p><strong>عکس را اینجا رها کنید</strong> یا <span class="dz-browse">کلیک کنید و انتخاب کنید</span></p>
                <p class="muted" style="font-size:12px">JPG، PNG یا WebP — حداکثر ۸ مگابایت</p>
                <div class="dz-preview" id="dzPreview" hidden><img id="dzImg" alt=""><span id="dzName"></span></div>
            </div>
        </div>
        <label>کپشن (عنوان عکس)
            <input type="text" name="caption" placeholder="مثلاً نور خطی آشپزخانه">
        </label>
        <label>توضیحات تکمیلی <span class="muted">(اختیاری — زیر کپشن نمایش داده می‌شود)</span>
            <textarea name="description" rows="2" placeholder="توضیح کوتاه درباره این پروژه..."></textarea>
        </label>
        <button type="submit" class="btn add">آپلود و افزودن به گالری</button>
    </form>
</div>

<?php if ($editIdx >= 0 && isset($galleryFigs[$editIdx])): ?>
<div class="crud-panel" data-open="1">
    <h2>ویرایش عکس <?= $editIdx + 1 ?></h2>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="gallery_update">
        <input type="hidden" name="index" value="<?= $editIdx ?>">
        <div style="display:flex;gap:16px;align-items:start;flex-wrap:wrap;margin-bottom:12px">
            <img src="<?= e($galleryFigs[$editIdx]['src']) ?>" alt="" style="width:180px;height:120px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb">
            <div class="muted" style="font-size:12px"><code><?= e($galleryFigs[$editIdx]['src']) ?></code></div>
        </div>
        <label>کپشن (عنوان عکس)
            <input type="text" name="caption" value="<?= e($galleryFigs[$editIdx]['caption']) ?>" placeholder="مثلاً نور خطی آشپزخانه">
        </label>
        <label>توضیحات تکمیلی
            <textarea name="description" rows="3" placeholder="توضیح کوتاه درباره این پروژه..."><?= e($galleryFigs[$editIdx]['desc']) ?></textarea>
        </label>
        <button type="submit" class="btn edit">ذخیره ویرایش</button>
        <a class="btn" href="admin.php?page=gallery">انصراف</a>
    </form>
</div>
<?php endif; ?>

<h2>عکس‌های گالری (<?= count($galleryFigs) ?>)</h2>
<?php if ($galleryFigs === []): ?>
    <p class="muted">هنوز عکسی در گالری نیست. با دکمه «افزودن عکس جدید» شروع کنید.</p>
<?php else: ?>
<div class="gallery-grid">
    <?php foreach ($galleryFigs as $i => $fig): ?>
    <div class="gallery-card">
        <img src="<?= e($fig['src']) ?>" alt="<?= e($fig['alt']) ?>" loading="lazy">
        <div class="gallery-card-body">
            <strong><?= e($fig['caption'] !== '' ? $fig['caption'] : 'بدون کپشن') ?></strong>
            <?php if ($fig['desc'] !== ''): ?><p class="muted"><?= e(mb_substr($fig['desc'], 0, 80)) ?><?= mb_strlen($fig['desc']) > 80 ? '…' : '' ?></p><?php endif; ?>
            <div class="actions">
                <a class="btn small edit" href="admin.php?page=gallery&edit_idx=<?= $i ?>">ویرایش</a>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="gallery_move"><input type="hidden" name="index" value="<?= $i ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small" <?= $i === 0 ? 'disabled' : '' ?>>↑</button></form>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="gallery_move"><input type="hidden" name="index" value="<?= $i ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small" <?= $i === count($galleryFigs) - 1 ? 'disabled' : '' ?>>↓</button></form>
                <form method="post" class="inline" onsubmit="return confirm('این عکس از گالری حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="gallery_delete"><input type="hidden" name="index" value="<?= $i ?>"><label class="check small" style="display:inline-flex;align-items:center;gap:4px;font-size:11px"><input type="checkbox" name="delete_file" value="1"> حذف فایل</label> <button type="submit" class="btn small danger-btn">حذف</button></form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
(function(){
    var dz = document.getElementById('galleryDropzone');
    var fi = document.getElementById('galleryFileInput');
    if(!dz || !fi){ return; }
    dz.addEventListener('click', function(){ fi.click(); });
    ['dragenter','dragover'].forEach(function(ev){ dz.addEventListener(ev, function(e){ e.preventDefault(); dz.classList.add('dz-over'); }); });
    ['dragleave','drop'].forEach(function(ev){ dz.addEventListener(ev, function(e){ e.preventDefault(); dz.classList.remove('dz-over'); }); });
    dz.addEventListener('drop', function(e){
        var files = e.dataTransfer.files;
        if(files && files.length){ fi.files = files; showPreview(files[0]); }
    });
    fi.addEventListener('change', function(){ if(fi.files.length){ showPreview(fi.files[0]); } });
    function showPreview(file){
        var pv = document.getElementById('dzPreview');
        var img = document.getElementById('dzImg');
        var nm = document.getElementById('dzName');
        if(pv && img){
            img.src = URL.createObjectURL(file);
            if(nm){ nm.textContent = file.name; }
            pv.hidden = false;
        }
    }
})();
</script>
<style>
.dropzone{border:2px dashed #cbd5e1;border-radius:14px;padding:32px 20px;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;margin-bottom:14px}
.dropzone:hover,.dropzone.dz-over{border-color:#2563eb;background:#eff6ff}
.dz-icon{font-size:40px;margin-bottom:8px}
.dz-browse{color:#2563eb;text-decoration:underline}
.dz-preview{margin-top:12px;display:flex;align-items:center;gap:10px;justify-content:center}
.dz-preview img{width:80px;height:60px;object-fit:cover;border-radius:8px}
.gallery-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:12px}
.gallery-card{border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;background:#fff}
.gallery-card img{width:100%;height:150px;object-fit:cover;display:block}
.gallery-card-body{padding:10px 12px}
.gallery-card-body strong{font-size:13px;display:block;margin-bottom:4px}
.gallery-card-body .actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;align-items:center}
.gallery-card-body .actions .btn.small{padding:4px 10px;font-size:12px}
</style>
