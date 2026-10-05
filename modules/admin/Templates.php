<?php
// modules/admin/Templates.php — مدیریت قالب‌های وردپرسی

declare(strict_types=1);

/** پردازش فرم‌های قالب */
function templates_handle_post(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return null;
    $action = $_POST['tpl_action'] ?? '';

    if ($action === 'save') {
        $file = trim((string) ($_POST['tpl_file'] ?? ''));
        $content = (string) ($_POST['tpl_content'] ?? '');
        $isPart = ($_POST['tpl_is_part'] ?? '') === '1';
        if ($file === '' || !preg_match('/^[a-z0-9\-]+\.php$/', $file)) {
            return 'نام فایل نامعتبر است.';
        }
        if ($isPart && !is_dir(templates_dir() . '/parts/')) {
            @mkdir(templates_dir() . '/parts/', 0755, true);
        }
        $path = $isPart ? templates_dir() . '/parts/' . $file : templates_dir() . '/' . $file;
        // بررسی سینتکس
        $tmp = tempnam(sys_get_temp_dir(), 'tplchk');
        @file_put_contents($tmp, $content);
        $out = []; $ret = 1;
        if (function_exists('exec')) {
            @exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $ret);
        } else { $ret = 0; }
        @unlink($tmp);
        if ($ret !== 0) {
            return 'خطای سینتکس: ' . implode(' ', array_slice($out, 0, 3));
        }
        if (@file_put_contents($path, $content) !== false) {
            return 'قالب ذخیره شد.';
        }
        return 'خطا در ذخیره.';
    }

    if ($action === 'add') {
        $file = trim((string) ($_POST['tpl_file'] ?? ''));
        $name = trim((string) ($_POST['tpl_name'] ?? ''));
        $type = trim((string) ($_POST['tpl_type'] ?? 'post'));
        if ($file === '' || $name === '') return 'نام فایل و نام قالب الزامی است.';
        if (!preg_match('/^[a-z0-9\-]+\.php$/', $file)) return 'نام فایل فقط حروف کوچک، عدد و خط تیره.';
        $path = templates_dir() . '/' . $file;
        if (is_file($path)) return 'این فایل قبلاً وجود دارد.';
        $content = "<?php\n/*\nTemplate Name: $name\nTemplate Type: $type\nDescription: \nAuthor: لاینرلایت\nVersion: 1.0.0\n*/\n?>\n<article>\n    <h1><?= e(\$title ?? '') ?></h1>\n    <div><?= \$content ?? '' ?></div>\n</article>\n";
        if (@file_put_contents($path, $content) !== false) {
            return 'قالب جدید ساخته شد.';
        }
        return 'خطا در ساخت قالب.';
    }

    if ($action === 'delete') {
        $file = trim((string) ($_POST['tpl_file'] ?? ''));
        if ($file === '' || !preg_match('/^[a-z0-9\-]+\.php$/', $file)) return 'نامعتبر.';
        // قالب‌های پیش‌فرض حذف نمی‌شوند
        $protected = ['single.php', 'page.php', 'single-product.php', 'category.php', 'fullwidth.php', 'home.php'];
        if (in_array($file, $protected, true)) return 'قالب پیش‌فرض قابل حذف نیست.';
        if (@unlink(templates_dir() . '/' . $file)) {
            return 'قالب حذف شد.';
        }
        return 'خطا در حذف.';
    }

    return null;
}

/** رندر صفحه مدیریت قالب‌ها */
function templates_render(): void
{
    $msg = templates_handle_post();
    $templates = templates_discover();
    $newPart = isset($_GET['new_part']);
    $editFile = trim((string) ($_GET['edit'] ?? ''));
    $editPart = trim((string) ($_GET['edit_part'] ?? ''));
    $editContent = '';
    $editMeta = null;
    $editIsPart = false;
    if ($editPart !== '' && preg_match('/^[a-z0-9\-]+\.php$/', $editPart)) {
        $path = templates_dir() . '/parts/' . $editPart;
        if (is_file($path)) {
            $editContent = (string) file_get_contents($path);
            $editMeta = template_parse_header($path);
            $editFile = $editPart;
            $editIsPart = true;
        }
    } elseif ($editFile !== '' && preg_match('/^[a-z0-9\-]+\.php$/', $editFile)) {
        $path = templates_dir() . '/' . $editFile;
        if (is_file($path)) {
            $editContent = (string) file_get_contents($path);
            $editMeta = template_parse_header($path);
        }
    }
    if ($newPart) {
        $editFile = '';
        $editIsPart = true;
        $editContent = "<?php\n/*\nTemplate Name: بخش جدید\nTemplate Type: part\nDescription: \nAuthor: لاینرلایت\nVersion: 1.0.0\n*/\n?>\n<!-- کد HTML/PHP بخش را اینجا بنویسید -->\n";
        $editMeta = ['name' => 'بخش جدید', 'type' => 'part'];
    }
    // گروه‌بندی بر اساس نوع
    $grouped = ['post' => [], 'page' => [], 'product' => [], 'category' => [], 'home' => []];
    foreach ($templates as $file => $t) {
        foreach ($t['types'] as $type) {
            if (isset($grouped[$type])) $grouped[$type][$file] = $t;
        }
    }
    $typeNames = ['post' => 'مقالات', 'page' => 'صفحه‌ها', 'product' => 'محصولات', 'category' => 'دسته‌بندی‌ها', 'home' => 'صفحه اصلی'];
    // بخش‌های مشترک (هدر/فوتر)
    $parts = [];
    $partsDir = templates_dir() . '/parts';
    if (is_dir($partsDir)) {
        foreach (scandir($partsDir) ?: [] as $pf) {
            if (!str_ends_with($pf, '.php')) continue;
            $meta = template_parse_header($partsDir . '/' . $pf);
            if ($meta) {
                $partKey = basename($pf, '.php');
                $meta['customized'] = template_part_is_customized($partKey);
                $parts[$pf] = $meta;
            }
        }
    }
    ?>
    <div class="page-head">
        <h1>مدیریت قالب‌ها</h1>
        <p class="muted">قالب‌های نمایشی مثل وردپرس — برای مقالات، صفحه‌ها، محصولات و دسته‌بندی‌ها.</p>
    </div>
    <?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

    <style>
    .tpl-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-top:12px}
    .tpl-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px}
    .tpl-card h3{margin:0 0 4px;font-size:15px}
    .tpl-card .tpl-file{font-size:11px;color:#94a3b8;direction:ltr;display:block}
    .tpl-card p{font-size:13px;color:#64748b;margin:8px 0}
    .tpl-card .actions{display:flex;gap:8px;margin-top:10px}
    .tpl-type-title{font-size:16px;font-weight:700;margin:20px 0 4px;color:#334155;border-bottom:2px solid #e2e8f0;padding-bottom:6px}
    </style>

    <div class="card">
        <div class="card-head">
            <h2>افزودن قالب جدید</h2>
            <button type="button" class="btn btn-success" onclick="document.getElementById('addTplForm').style.display=document.getElementById('addTplForm').style.display==='none'?'block':'none'">+ افزودن</button>
        </div>
        <form id="addTplForm" method="post" style="display:none" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="tpl_action" value="add">
            <label>نام فایل (انگلیسی) <input type="text" name="tpl_file" required pattern="[a-z0-9\-]+\.php" dir="ltr" placeholder="my-template.php"></label>
            <label>نام قالب <input type="text" name="tpl_name" required></label>
            <label>نوع
                <select name="tpl_type">
                    <option value="post">مقاله</option>
                    <option value="page">صفحه</option>
                    <option value="product">محصول</option>
                    <option value="category">دسته‌بندی</option>
                    <option value="post,page,product">همه</option>
                </select>
            </label>
            <div><button type="submit" class="btn btn-success">ساخت قالب</button></div>
        </form>
    </div>

    <?php if (($editFile && $editContent !== '') || $newPart): ?>
    <div class="card" style="margin-top:16px">
        <h2>ویرایش: <?= e($editMeta['name'] ?? $editFile) ?> <code dir="ltr"><?= e($editFile) ?></code></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="tpl_action" value="save">
            <?php if ($newPart): ?>
            <div class="form-group" style="margin-bottom:12px;">
                <label>نام فایل (انگلیسی، مثل sidebar)</label>
                <input type="text" name="tpl_file" class="form-control" dir="ltr" placeholder="sidebar" required pattern="[a-z0-9\-]+">
            </div>
            <?php else: ?>
            <input type="hidden" name="tpl_file" value="<?= e($editFile) ?>">
            <?php endif; ?>
            <?php if ($editIsPart): ?><input type="hidden" name="tpl_is_part" value="1"><?php endif; ?>
            <textarea name="tpl_content" class="code-editor" data-mode="php" rows="25" dir="ltr" style="text-align:left"><?= e($editContent) ?></textarea>
            <div style="margin-top:12px">
                <button type="submit" class="btn btn-warning">ذخیره قالب</button>
                <a href="admin.php?page=templates" class="btn">انصراف</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="tpl-type-title">بخش‌های مشترک (هدر/فوتر)</div>
    <div style="margin-bottom:12px;">
        <a href="admin.php?page=templates&new_part=1" class="btn btn-sm btn-success">افزودن بخش جدید</a>
    </div>
    <?php if (!empty($parts)): ?>
        <div class="tpl-grid">
        <?php foreach ($parts as $file => $t): ?>
            <div class="tpl-card">
                <h3><?= e($t['name']) ?></h3>
                <span class="tpl-file">parts/<?= e($file) ?></span>
                <?php if ($t['description']): ?><p><?= e($t['description']) ?></p><?php endif; ?>
                <p><?= !empty($t['customized']) ? '<span class="badge badge-success">سفارشی‌شده</span>' : '<span class="badge">پیش‌فرض دیتابیس</span>' ?></p>
                <div class="actions">
                    <a href="admin.php?page=templates&edit_part=<?= e($file) ?>" class="btn btn-sm btn-warning">ویرایش</a>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($grouped as $type => $tpls): if (empty($tpls)) continue; ?>
        <div class="tpl-type-title"><?= e($typeNames[$type]) ?> (<?= count($tpls) ?>)</div>
        <div class="tpl-grid">
        <?php foreach ($tpls as $file => $t): ?>
            <div class="tpl-card">
                <h3><?= e($t['name']) ?></h3>
                <span class="tpl-file"><?= e($file) ?></span>
                <?php if ($t['description']): ?><p><?= e($t['description']) ?></p><?php endif; ?>
                <div class="actions">
                    <a href="admin.php?page=templates&edit=<?= e($file) ?>" class="btn btn-sm btn-warning">ویرایش</a>
                    <?php if (!in_array($file, ['single.php','page.php','single-product.php','category.php','fullwidth.php'], true)): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="tpl_action" value="delete">
                        <input type="hidden" name="tpl_file" value="<?= e($file) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php
}
