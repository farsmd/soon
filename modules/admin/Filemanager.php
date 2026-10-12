<?php
// modules/admin/Filemanager.php — مدیریت فایل حرفه‌ای پنل (نسخه ۱۰٫۲۷)
// مرور دایرکتوری‌ها، ویرایش کد با CodeMirror، ایجاد/تغییرنام/حذف — با گاردهای امنیتی سخت‌گیرانه.

declare(strict_types=1);

if (!defined('CMS_ADMIN_PANEL')) {
    exit;
}

/** ریشه سایت */
function fm_root(): string
{
    return (string) realpath(__DIR__ . '/../..');
}

/** پسوندهای متنی قابل ویرایش */
function fm_editable_exts(): array
{
    return ['php', 'css', 'js', 'json', 'md', 'txt', 'html', 'xml', 'svg', 'yml', 'yaml'];
}

/**
 * نرمال‌سازی مسیر نسبی. برمی‌گرداند: رشته تمیزشده یا null در صورت نامعتبر بودن.
 * قوانین: بدون ..، بدون بایت null، بدون مسیر مطلق، بدون بک‌اسلش.
 */
function fm_sanitize_rel(?string $rel): ?string
{
    if ($rel === null) {
        return null;
    }
    $rel = trim(str_replace('\\', '/', $rel));
    if ($rel === '' || $rel === '/') {
        return '';
    }
    if (str_contains($rel, "\0")) {
        return null;
    }
    $rel = trim($rel, '/');
    if ($rel === '') {
        return '';
    }
    if (str_starts_with($rel, '/')) {
        return null;
    }
    $parts = explode('/', $rel);
    foreach ($parts as $p) {
        if ($p === '' || $p === '.' || $p === '..') {
            return null;
        }
    }
    return implode('/', $parts);
}

/** آیا این مسیر نسبی در بلاک‌لیست سخت است؟ */
function fm_is_blocked(string $rel): bool
{
    $r = strtolower($rel);
    // فایل‌های حساس ریشه
    if (in_array($r, ['database.sqlite', '.htaccess'], true)) {
        return true;
    }
    // انواع فایل دیتابیس
    if ((bool) preg_match('/\.sqlite(-shm|-wal|-journal)?$/', $r)) {
        return true;
    }
    // دایرکتوری‌ها و فایل‌های حساس در هر عمقی (۱۰٫۲۸: ‎.htaccess و ‎.user.ini هم ساب‌فولدری بلاک‌اند)
    foreach (explode('/', $r) as $seg) {
        if (in_array($seg, ['uploads', 'backups', '.git', '.htaccess', '.user.ini'], true)) {
            return true;
        }
    }
    return false;
}

/**
 * تبدیل مسیر نسبی به مسیر مطلق امن. null یعنی غیرمجاز.
 * ریشه‌های مجاز: روت سایت (به‌جز بلاک‌لیست).
 */
function fm_resolve(string $rel): ?string
{
    $clean = fm_sanitize_rel($rel);
    if ($clean === null) {
        return null;
    }
    if (fm_is_blocked($clean)) {
        return null;
    }
    $root = fm_root();
    if ($root === '' || !is_dir($root)) {
        return null;
    }
    $abs = $root . ($clean === '' ? '' : '/' . $clean);
    // برای مسیرهای موجود: realpath و بررسی ماندن داخل روت
    $real = realpath($abs);
    if ($real !== false) {
        $realRoot = (string) realpath($root);
        if ($real !== $realRoot && !str_starts_with($real, $realRoot . DIRECTORY_SEPARATOR)) {
            return null;
        }
        // بررسی مجدد بلاک‌لیست روی مسیر واقعی (مقابله با سیملینک)
        $relReal = ltrim(str_replace('\\', '/', substr($real, strlen($realRoot))), '/');
        if ($relReal !== '' && fm_is_blocked($relReal)) {
            return null;
        }
        return $real;
    }
    // مسیر ناموجود (برای ایجاد فایل/پوشه): والد باید موجود و مجاز باشد
    $parent = dirname($abs);
    $realParent = realpath($parent);
    if ($realParent === false) {
        return null;
    }
    $realRoot = (string) realpath($root);
    if ($realParent !== $realRoot && !str_starts_with($realParent, $realRoot . DIRECTORY_SEPARATOR)) {
        return null;
    }
    return $abs;
}

/** لیست محتوای یک دایرکتوری: اول پوشه‌ها بعد فایل‌ها، مرتب الفبا */
function fm_list(string $dirRel): array
{
    $abs = fm_resolve($dirRel);
    if ($abs === null || !is_dir($abs)) {
        return [];
    }
    $dirs = [];
    $files = [];
    foreach (scandir($abs) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $rel = ($dirRel === '' ? $name : $dirRel . '/' . $name);
        if (fm_is_blocked($rel)) {
            continue;
        }
        $full = $abs . '/' . $name;
        $entry = [
            'name' => $name,
            'rel' => $rel,
            'is_dir' => is_dir($full),
            'size' => is_file($full) ? (int) filesize($full) : 0,
            'mtime' => (int) filemtime($full),
            'ext' => strtolower((string) pathinfo($name, PATHINFO_EXTENSION)),
        ];
        if ($entry['is_dir']) {
            $dirs[] = $entry;
        } else {
            $files[] = $entry;
        }
    }
    $cmp = static fn($a, $b) => strnatcasecmp($a['name'], $b['name']);
    usort($dirs, $cmp);
    usort($files, $cmp);
    return array_merge($dirs, $files);
}

/** خواندن فایل متنی (حداکثر ۲ مگابایت) */
function fm_read(string $rel): ?string
{
    $abs = fm_resolve($rel);
    if ($abs === null || !is_file($abs)) {
        return null;
    }
    $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, fm_editable_exts(), true)) {
        return null;
    }
    if (filesize($abs) > 2 * 1024 * 1024) {
        return null;
    }
    $c = @file_get_contents($abs);
    return is_string($c) ? $c : null;
}

/** نوشتن فایل متنی (فقط پسوند مجاز) */
function fm_write(string $rel, string $content): bool
{
    $abs = fm_resolve($rel);
    if ($abs === null || !is_file($abs)) {
        return false;
    }
    $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
    if (!in_array($ext, fm_editable_exts(), true)) {
        return false;
    }
    if (strlen($content) > 2 * 1024 * 1024) {
        return false;
    }
    return @file_put_contents($abs, $content) !== false;
}

/** اعتبارسنجی نام فایل/پوشه جدید (فقط نام، بدون مسیر) */
function fm_valid_name(?string $name): ?string
{
    if ($name === null) {
        return null;
    }
    $name = trim($name);
    if ($name === '' || strlen($name) > 120) {
        return null;
    }
    if (!preg_match('/^[A-Za-z0-9_\-\.]+$/', $name)) {
        return null;
    }
    if (in_array($name, ['.', '..'], true)) {
        return null;
    }
    return $name;
}

/** ساخت فایل خالی */
function fm_create_file(string $dirRel, string $name): bool
{
    $name = fm_valid_name($name);
    if ($name === null) {
        return false;
    }
    $rel = ($dirRel === '' ? $name : $dirRel . '/' . $name);
    $abs = fm_resolve($rel);
    if ($abs === null || file_exists($abs)) {
        return false;
    }
    return @file_put_contents($abs, '') !== false;
}

/** ساخت پوشه */
function fm_create_dir(string $dirRel, string $name): bool
{
    $name = fm_valid_name($name);
    if ($name === null) {
        return false;
    }
    $rel = ($dirRel === '' ? $name : $dirRel . '/' . $name);
    $abs = fm_resolve($rel);
    if ($abs === null || file_exists($abs)) {
        return false;
    }
    return @mkdir($abs, 0755, false);
}

/** تغییرنام (فقط داخل همان پوشه) */
function fm_rename(string $oldRel, string $newName): bool
{
    $newName = fm_valid_name($newName);
    if ($newName === null) {
        return false;
    }
    $oldAbs = fm_resolve($oldRel);
    if ($oldAbs === null || !file_exists($oldAbs)) {
        return false;
    }
    $dir = dirname($oldRel);
    $dir = ($dir === '.' || $dir === '') ? '' : $dir;
    $newRel = ($dir === '' ? $newName : $dir . '/' . $newName);
    $newAbs = fm_resolve($newRel);
    if ($newAbs === null || file_exists($newAbs)) {
        return false;
    }
    return @rename($oldAbs, $newAbs);
}

/** حذف بازگشتی فایل یا پوشه */
function fm_delete_recursive(string $abs): bool
{
    if (is_link($abs)) {
        return @unlink($abs);
    }
    if (is_file($abs)) {
        return @unlink($abs);
    }
    if (!is_dir($abs)) {
        return false;
    }
    foreach (scandir($abs) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (!fm_delete_recursive($abs . '/' . $name)) {
            return false;
        }
    }
    return @rmdir($abs);
}

function fm_delete(string $rel): bool
{
    if ($rel === '') {
        return false; // هرگز روت
    }
    $abs = fm_resolve($rel);
    if ($abs === null || !file_exists($abs)) {
        return false;
    }
    return fm_delete_recursive($abs);
}

/** حجم خوانا */
function fm_human_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return round($bytes / (1024 * 1024), 2) . ' MB';
}

/** حالت CodeMirror بر اساس پسوند */
function fm_cm_mode(string $ext): string
{
    return match ($ext) {
        'php' => 'php',
        'css' => 'css',
        'js' => 'javascript',
        'json' => 'javascript',
        'xml', 'svg' => 'xml',
        'html', 'md' => 'htmlmixed',
        default => 'htmlmixed',
    };
}

/** آیکون SVG برای فایل/پوشه (بدون ایموجی) */
function fm_icon(string $name, bool $isDir, string $ext): string
{
    if ($isDir) {
        $p = '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>';
        $c = '#f59e0b';
    } else {
        $p = match ($ext) {
            'php' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><text x="12" y="17" text-anchor="middle" font-size="7" fill="currentColor">php</text>',
            'css' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><text x="12" y="17" text-anchor="middle" font-size="7" fill="currentColor">css</text>',
            'js' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><text x="12" y="17" text-anchor="middle" font-size="7" fill="currentColor">js</text>',
            default => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8"/>',
        };
        $c = match ($ext) {
            'php' => '#777bb3',
            'css' => '#2965f1',
            'js' => '#f0db4f',
            'json' => '#f0db4f',
            'md', 'txt' => '#64748b',
            'html', 'xml', 'svg' => '#e34c26',
            default => '#94a3b8',
        };
    }
    return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="' . $c . '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** بردکرامب دایرکتوری جاری */
function fm_breadcrumb(string $dirRel): string
{
    $h = '<a href="admin.php?page=filemanager" class="fm-crumb">' . fm_icon('root', true, '') . '<span>روت</span></a>';
    if ($dirRel === '') {
        return $h;
    }
    $acc = '';
    foreach (explode('/', $dirRel) as $seg) {
        $acc = ($acc === '' ? $seg : $acc . '/' . $seg);
        $h .= '<span class="fm-crumb-sep">/</span><a href="admin.php?page=filemanager&amp;dir=' . urlencode($acc) . '" class="fm-crumb"><span>' . e($seg) . '</span></a>';
    }
    return $h;
}

/** رندر صفحه مدیریت فایل */
function filemanager_render_admin(): void
{
    $dirRel = fm_sanitize_rel((string) ($_GET['dir'] ?? ''));
    if ($dirRel === null) {
        $dirRel = '';
    }
    $editRel = fm_sanitize_rel((string) ($_GET['edit'] ?? ''));
    if ($editRel === null) {
        $editRel = '';
    }
    $entries = fm_list($dirRel);
    $editContent = null;
    $editExt = '';
    $editErr = '';
    if ($editRel !== '') {
        $editExt = strtolower((string) pathinfo($editRel, PATHINFO_EXTENSION));
        if (!in_array($editExt, fm_editable_exts(), true)) {
            $editErr = 'این نوع فایل قابل ویرایش نیست.';
        } else {
            $editContent = fm_read($editRel);
            if ($editContent === null) {
                $editErr = 'فایل یافت نشد یا خوانده نشد.';
            }
        }
    }
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    ?>
    <h1>مدیریت فایل</h1>
    <div class="alert" style="background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;padding:12px 16px;margin:12px 0;font-size:14px">
        <strong>توجه:</strong> ویرایش‌ها فقط روی فایل‌های <strong>هاست</strong> اعمال می‌شود و مخزن GitHub را به‌روز نمی‌کند —
        با اجرای بعدی آپدیتر، تغییرات هاست با نسخه گیت‌هاب <strong>رونویسی</strong> می‌شود. این ابزار برای هات‌فیکس و بررسی سریع است، نه توسعه اصلی.
        دیتابیس، آپلودها، بکاپ‌ها و <span dir="ltr">.htaccess</span> قفل هستند و نمایش داده نمی‌شوند.
    </div>
    <?php if (is_array($flash)): ?>
        <div class="alert <?= $flash['type'] === 'ok' ? 'ok' : 'error' ?>"><?= e((string) $flash['message']) ?></div>
    <?php endif; ?>

    <div class="fm-toolbar" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0">
        <nav class="fm-crumbs" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;flex:1;min-width:200px"><?= fm_breadcrumb($dirRel) ?></nav>
        <button type="button" class="btn small" data-toggle-panel="fm-newfile-panel">+ فایل تازه</button>
        <button type="button" class="btn small" data-toggle-panel="fm-newdir-panel">+ پوشه تازه</button>
    </div>

    <div class="crud-panel" id="fm-newfile-panel" hidden>
        <h3>فایل تازه در این پوشه</h3>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="fm_create_file">
            <input type="hidden" name="fm_dir" value="<?= e($dirRel) ?>">
            <label style="margin:0">نام فایل <input type="text" name="fm_name" dir="ltr" placeholder="example.php" required pattern="[A-Za-z0-9_\-\.]+" style="width:220px"></label>
            <button type="submit" class="btn add small">ایجاد</button>
        </form>
    </div>
    <div class="crud-panel" id="fm-newdir-panel" hidden>
        <h3>پوشه تازه در این پوشه</h3>
        <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="fm_create_dir">
            <input type="hidden" name="fm_dir" value="<?= e($dirRel) ?>">
            <label style="margin:0">نام پوشه <input type="text" name="fm_name" dir="ltr" placeholder="mydir" required pattern="[A-Za-z0-9_\-\.]+" style="width:220px"></label>
            <button type="submit" class="btn add small">ایجاد</button>
        </form>
    </div>

    <div class="fm-layout" style="display:grid;grid-template-columns:minmax(280px,380px) 1fr;gap:16px;align-items:start">
        <div class="card" style="padding:0;overflow:hidden;max-width:100%">
            <div style="padding:10px 14px;border-bottom:1px solid #e2e8f0;font-weight:bold;font-size:14px"><?= count($entries) ?> مورد</div>
            <ul class="fm-list" style="list-style:none;margin:0;padding:0;max-height:60vh;overflow:auto">
                <?php if ($dirRel !== ''): ?>
                <li>
                    <a href="admin.php?page=filemanager&amp;dir=<?= urlencode((string) preg_replace('#/[^/]+$#', '', $dirRel)) ?>" class="fm-row" style="color:#64748b">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#64748b" stroke-width="1.8" stroke-linecap="round"><path d="M19 12H5m7-7-7 7 7 7"/></svg>
                        <span>.. (بالا)</span>
                    </a>
                </li>
                <?php endif; ?>
                <?php foreach ($entries as $en): ?>
                <li>
                    <?php if ($en['is_dir']): ?>
                    <a href="admin.php?page=filemanager&amp;dir=<?= urlencode($en['rel']) ?>" class="fm-row">
                        <?= fm_icon($en['name'], true, '') ?>
                        <span class="fm-name"><?= e($en['name']) ?></span>
                        <span class="fm-meta muted">پوشه</span>
                        <span class="fm-ops">
                            <button type="button" class="btn small" onclick="fmRename('<?= e($en['rel']) ?>','<?= e($en['name']) ?>')">تغییرنام</button>
                        </span>
                    </a>
                    <?php else: ?>
                    <div class="fm-row<?= $editRel === $en['rel'] ? ' fm-active' : '' ?>">
                        <?= fm_icon($en['name'], false, $en['ext']) ?>
                        <span class="fm-name" dir="ltr"><?= e($en['name']) ?></span>
                        <span class="fm-meta muted"><?= fm_human_size($en['size']) ?> · <?= date('Y/m/d H:i', $en['mtime']) ?></span>
                        <span class="fm-ops">
                            <?php if (in_array($en['ext'], fm_editable_exts(), true)): ?>
                            <a class="btn small edit" href="admin.php?page=filemanager&amp;dir=<?= urlencode($dirRel) ?>&amp;edit=<?= urlencode($en['rel']) ?>">ویرایش</a>
                            <?php endif; ?>
                            <button type="button" class="btn small" onclick="fmRename('<?= e($en['rel']) ?>','<?= e($en['name']) ?>')">تغییرنام</button>
                            <button type="button" class="btn small danger" onclick="fmDelete('<?= e($en['rel']) ?>','<?= e($en['name']) ?>')">حذف</button>
                        </span>
                    </div>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
                <?php if ($entries === []): ?>
                <li class="muted" style="padding:16px">این پوشه خالی است.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="card" style="max-width:100%">
            <?php if ($editErr !== ''): ?>
                <div class="alert error"><?= e($editErr) ?></div>
            <?php elseif ($editContent !== null): ?>
                <h3 style="margin-top:0">ویرایش: <span dir="ltr"><?= e($editRel) ?></span></h3>
                <form method="post" id="fm-edit-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="fm_save">
                    <input type="hidden" name="fm_path" value="<?= e($editRel) ?>">
                    <input type="hidden" name="fm_dir" value="<?= e($dirRel) ?>">
                    <textarea name="content" class="code-editor" data-mode="<?= fm_cm_mode($editExt) ?>" dir="ltr" style="min-height:420px"><?= e($editContent) ?></textarea>
                    <p style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
                        <button type="submit" class="btn add">ذخیره (Ctrl+S)</button>
                        <a class="btn" href="admin.php?page=filemanager&amp;dir=<?= urlencode($dirRel) ?>">بستن ویرایشگر</a>
                    </p>
                </form>
                <script>
                document.getElementById('fm-edit-form').addEventListener('keydown', function(ev) {
                    if ((ev.ctrlKey || ev.metaKey) && (ev.key === 's' || ev.key === 'S')) {
                        ev.preventDefault();
                        this.requestSubmit();
                    }
                });
                </script>
            <?php else: ?>
                <p class="muted" style="margin:8px 0">برای ویرایش، روی «ویرایش» کنار یک فایل متنی بزنید. فایل‌های باینری و قفل‌شده قابل ویرایش نیستند.</p>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" id="fm-rename-form" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="fm_rename">
        <input type="hidden" name="fm_path" id="fm-rename-path" value="">
        <input type="hidden" name="fm_name" id="fm-rename-name" value="">
        <input type="hidden" name="fm_dir" value="<?= e($dirRel) ?>">
    </form>
    <form method="post" id="fm-delete-form" style="display:none" onsubmit="return confirm('حذف قطعی شود؟ این عمل قابل بازگشت نیست.')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="fm_delete">
        <input type="hidden" name="fm_path" id="fm-delete-path" value="">
        <input type="hidden" name="fm_dir" value="<?= e($dirRel) ?>">
    </form>
    <script>
    function fmRename(rel, oldName) {
        var n = prompt('نام تازه:', oldName);
        if (n === null) return;
        n = n.trim();
        if (!n || n === oldName) return;
        document.getElementById('fm-rename-path').value = rel;
        document.getElementById('fm-rename-name').value = n;
        document.getElementById('fm-rename-form').requestSubmit();
    }
    function fmDelete(rel, name) {
        if (!confirm('«' + name + '» حذف شود؟' + (rel.indexOf('.') === -1 ? '\n(پوشه و همه محتوایش حذف می‌شود)' : ''))) return;
        document.getElementById('fm-delete-path').value = rel;
        document.getElementById('fm-delete-form').requestSubmit();
    }
    </script>
    <style>
    .fm-row{display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit}
    a.fm-row:hover{background:#f8fafc}
    .fm-row.fm-active{background:#eff6ff}
    .fm-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:14px}
    .fm-meta{font-size:11px;white-space:nowrap}
    .fm-ops{display:flex;gap:4px}
    .fm-crumb{display:inline-flex;align-items:center;gap:4px;font-size:14px}
    .fm-crumb-sep{color:#94a3b8}
    @media (max-width:900px){.fm-layout{grid-template-columns:1fr !important}}
    </style>
    <?php
}
