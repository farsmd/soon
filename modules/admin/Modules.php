<?php
// modules/admin/Modules.php — مدیریت ماژول‌ها (فعال/غیرفعال، ویرایش، افزودن)

declare(strict_types=1);

/** فهرست ماژول‌های پیش‌فرض سیستم */
function modules_default_list(): array
{
    return [
        ['key' => 'dashboard', 'name' => 'داشبورد', 'description' => 'نمای کلی وضعیت کسب‌وکار', 'category' => 'اصلی', 'icon' => 'dashboard', 'is_core' => 1, 'sort' => 10],
        ['key' => 'pages', 'name' => 'صفحه‌ها', 'description' => 'مدیریت صفحات سایت', 'category' => 'محتوا', 'icon' => 'file', 'is_core' => 1, 'sort' => 20],
        ['key' => 'blog', 'name' => 'مقالات', 'description' => 'مدیریت مقالات و بلاگ', 'category' => 'محتوا', 'icon' => 'article', 'sort' => 30],
        ['key' => 'products', 'name' => 'محصولات', 'description' => 'کاتالوگ محصولات', 'category' => 'فروش', 'icon' => 'box', 'sort' => 40],
        ['key' => 'categories', 'name' => 'دسته‌بندی‌ها', 'description' => 'دسته‌بندی محصولات', 'category' => 'فروش', 'icon' => 'folder', 'sort' => 50],
        ['key' => 'customers', 'name' => 'مشتری‌ها', 'description' => 'مدیریت مشتریان و همکاران', 'category' => 'فروش', 'icon' => 'users', 'sort' => 60],
        ['key' => 'orders', 'name' => 'سفارش‌ها', 'description' => 'ثبت و پیگیری سفارش‌ها', 'category' => 'فروش', 'icon' => 'cart', 'sort' => 70],
        ['key' => 'materials', 'name' => 'مواد اولیه و انبار', 'description' => 'مدیریت موجودی مواد', 'category' => 'انبار', 'icon' => 'warehouse', 'sort' => 80],
        ['key' => 'production', 'name' => 'تولید', 'description' => 'مدیریت فرآیند تولید', 'category' => 'تولید', 'icon' => 'factory', 'sort' => 90],
        ['key' => 'finance', 'name' => 'مالی', 'description' => 'داشبورد مالی و فاکتورها', 'category' => 'مالی', 'icon' => 'wallet', 'sort' => 100],
        ['key' => 'employees', 'name' => 'پرسنل', 'description' => 'مدیریت کارکنان', 'category' => 'منابع انسانی', 'icon' => 'id', 'sort' => 110],
        ['key' => 'payroll', 'name' => 'حقوق و دستمزد', 'description' => 'محاسبه حقوق و فیش', 'category' => 'منابع انسانی', 'icon' => 'money', 'sort' => 120],
        ['key' => 'reports', 'name' => 'گزارش‌ها', 'description' => 'گزارش‌های تحلیلی', 'category' => 'گزارش', 'icon' => 'chart', 'sort' => 130],
        ['key' => 'notifications', 'name' => 'اعلان‌ها', 'description' => 'سیستم اعلان‌ها', 'category' => 'سیستم', 'icon' => 'bell', 'sort' => 140],
        ['key' => 'settings', 'name' => 'تنظیمات', 'description' => 'تنظیمات سایت و سیستم', 'category' => 'سیستم', 'icon' => 'gear', 'is_core' => 1, 'sort' => 150],
        ['key' => 'users', 'name' => 'کاربران و نقش‌ها', 'description' => 'مدیریت کاربران پنل', 'category' => 'سیستم', 'icon' => 'shield', 'is_core' => 1, 'sort' => 160],
        ['key' => 'update', 'name' => 'آپدیت سیستم', 'description' => 'به‌روزرسانی یک‌کلیکی', 'category' => 'سیستم', 'icon' => 'refresh', 'is_core' => 1, 'sort' => 170],
        ['key' => 'modules', 'name' => 'مدیریت ماژول‌ها', 'description' => 'فعال‌سازی و مدیریت ماژول‌ها', 'category' => 'سیستم', 'icon' => 'puzzle', 'is_core' => 1, 'sort' => 180],
    ];
}

/** seed کردن ماژول‌های پیش‌فرض (فقط اگر جدول خالی است) */
function modules_seed(): void
{
    try {
        $count = (int) db()->query("SELECT COUNT(*) FROM modules")->fetchColumn();
        if ($count > 0) return;
        $now = time();
        $stmt = db()->prepare("INSERT INTO modules (module_key, name, description, version, enabled, is_core, category, icon, sort_order, created_at, updated_at) VALUES (?, ?, ?, '1.0.0', 1, ?, ?, ?, ?, ?, ?)");
        foreach (modules_default_list() as $m) {
            $stmt->execute([$m['key'], $m['name'], $m['description'], $m['is_core'] ?? 0, $m['category'], $m['icon'], $m['sort'], $now, $now]);
        }
    } catch (Throwable $e) { /* silent */ }
}

/** دریافت همه ماژول‌ها */
function modules_all(bool $onlyEnabled = false): array
{
    try {
        $sql = "SELECT * FROM modules" . ($onlyEnabled ? " WHERE enabled = 1" : "") . " ORDER BY sort_order ASC, name ASC";
        return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** دریافت یک ماژول با کلید */
function module_get(string $key): ?array
{
    try {
        $stmt = db()->prepare("SELECT * FROM modules WHERE module_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** آیا ماژول فعال است؟ (اگر ماژول ثبت نشده، پیش‌فرض فعال فرض می‌شود) */
function module_is_enabled(string $key): bool
{
    $m = module_get($key);
    if ($m === null) return true;
    return (int) $m['enabled'] === 1;
}

/** تغییر وضعیت فعال/غیرفعال */
function module_set_enabled(string $key, bool $enabled): bool
{
    try {
        $m = module_get($key);
        if ($m === null) return false;
        if ((int) $m['is_core'] === 1 && !$enabled) return false; // ماژول هسته غیرفعال نمی‌شود
        $stmt = db()->prepare("UPDATE modules SET enabled = ?, updated_at = ? WHERE module_key = ?");
        return $stmt->execute([$enabled ? 1 : 0, time(), $key]);
    } catch (Throwable $e) { return false; }
}

/** افزودن ماژول جدید */
function module_add(array $data): ?int
{
    try {
        $stmt = db()->prepare("INSERT INTO modules (module_key, name, description, version, enabled, is_core, category, icon, file_path, sort_order, created_at, updated_at) VALUES (?, ?, ?, ?, 1, 0, ?, ?, ?, ?, ?, ?)");
        $now = time();
        $stmt->execute([
            $data['key'], $data['name'], $data['description'] ?? '',
            $data['version'] ?? '1.0.0', $data['category'] ?? '',
            $data['icon'] ?? '', $data['file_path'] ?? '',
            (int) ($data['sort'] ?? 999), $now, $now,
        ]);
        return (int) db()->lastInsertId();
    } catch (Throwable $e) { return null; }
}

/** ویرایش ماژول */
function module_update(int $id, array $data): bool
{
    try {
        $stmt = db()->prepare("UPDATE modules SET name = ?, description = ?, version = ?, category = ?, icon = ?, file_path = ?, sort_order = ?, updated_at = ? WHERE id = ?");
        return $stmt->execute([
            $data['name'], $data['description'] ?? '', $data['version'] ?? '1.0.0',
            $data['category'] ?? '', $data['icon'] ?? '', $data['file_path'] ?? '',
            (int) ($data['sort'] ?? 999), time(), $id,
        ]);
    } catch (Throwable $e) { return false; }
}

/** حذف ماژول (فقط غیرهسته) */
function module_delete(int $id): bool
{
    try {
        $stmt = db()->prepare("SELECT is_core FROM modules WHERE id = ?");
        $stmt->execute([$id]);
        if ((int) $stmt->fetchColumn() === 1) return false;
        return db()->prepare("DELETE FROM modules WHERE id = ?")->execute([$id]);
    } catch (Throwable $e) { return false; }
}

/** پردازش فرم‌های مدیریت ماژول */
function modules_handle_post(): ?string
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return null;
    $action = $_POST['mod_action'] ?? '';

    if ($action === 'toggle') {
        $key = trim((string) ($_POST['module_key'] ?? ''));
        $enable = ($_POST['enable'] ?? '') === '1';
        if ($key === '') return 'کلید نامعتبر است.';
        if ($enable) {
            return module_activate($key) ? 'ماژول فعال شد.' : 'فعال‌سازی انجام نشد.';
        } else {
            return module_deactivate($key) ? 'ماژول غیرفعال شد.' : 'غیرفعال‌سازی انجام نشد (ماژول هسته؟).';
        }
    }
    if ($action === 'add') {
        $key = trim((string) ($_POST['module_key'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($key === '' || $name === '') return 'کلید و نام ماژول الزامی است.';
        if (!preg_match('/^[a-z0-9_]+$/', $key)) return 'کلید فقط حروف کوچک انگلیسی، عدد و آندرلاین.';
        if (module_get($key) !== null) return 'این کلید قبلاً ثبت شده است.';
        $id = module_add([
            'key' => $key, 'name' => $name,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'version' => trim((string) ($_POST['version'] ?? '1.0.0')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'icon' => trim((string) ($_POST['icon'] ?? '')),
            'sort' => (int) ($_POST['sort'] ?? 999),
        ]);
        return $id ? 'ماژول جدید اضافه شد.' : 'خطا در افزودن ماژول.';
    }
    if ($action === 'edit') {
        $id = (int) ($_POST['module_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id <= 0 || $name === '') return 'اطلاعات ناقص است.';
        $ok = module_update($id, [
            'name' => $name,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'version' => trim((string) ($_POST['version'] ?? '1.0.0')),
            'category' => trim((string) ($_POST['category'] ?? '')),
            'icon' => trim((string) ($_POST['icon'] ?? '')),
            'sort' => (int) ($_POST['sort'] ?? 999),
        ]);
        return $ok ? 'ماژول ویرایش شد.' : 'خطا در ویرایش.';
    }
    if ($action === 'delete') {
        $id = (int) ($_POST['module_id'] ?? 0);
        if ($id <= 0) return 'اطلاعات ناقص است.';
        return module_delete($id) ? 'ماژول حذف شد.' : 'حذف انجام نشد (ماژول هسته قابل حذف نیست).';
    }
    return null;
}

/** رندر صفحه مدیریت ماژول‌ها (سبک وردپرس) */
function modules_render(): void
{
    modules_seed();
    modules_sync(); // همگام‌سازی نسخه‌ها از مانیفست
    $msg = modules_handle_post();
    $modules = modules_all();
    $discovered = modules_discover();
    $editId = (int) ($_GET['edit'] ?? 0);
    $editModule = null;
    if ($editId > 0) {
        foreach ($modules as $m) { if ((int) $m['id'] === $editId) { $editModule = $m; break; } }
    }
    // گروه‌بندی بر اساس دسته
    $grouped = [];
    foreach ($modules as $m) {
        $cat = $m['category'] ?: 'سایر';
        $grouped[$cat][] = $m;
    }
    ?>
    <div class="page-head">
        <h1>مدیریت ماژول‌ها</h1>
        <p class="muted">ماژول‌ها را فعال/غیرفعال کنید، ویرایش کنید یا ماژول جدید بسازید. نسخه هر ماژول با آپدیت سیستم به‌روز می‌شود.</p>
    </div>
    <?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

    <style>
    .mod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:16px}
    .mod-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:18px;position:relative;transition:box-shadow .2s}
    .mod-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08)}
    .mod-card.disabled{opacity:.65;background:#f8fafc}
    .mod-card-head{display:flex;align-items:flex-start;gap:12px;margin-bottom:10px}
    .mod-icon{width:44px;height:44px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
    .mod-card h3{margin:0;font-size:15px}
    .mod-card .mod-key{font-size:11px;color:#94a3b8;direction:ltr;display:inline-block}
    .mod-desc{font-size:13px;color:#64748b;margin:8px 0;line-height:1.7}
    .mod-meta{display:flex;gap:8px;align-items:center;font-size:12px;color:#64748b;margin-bottom:12px;flex-wrap:wrap}
    .mod-ver{background:#f1f5f9;padding:2px 8px;border-radius:6px;direction:ltr}
    .mod-update{color:#d97706;font-weight:700}
    .mod-actions{display:flex;gap:8px;flex-wrap:wrap}
    .mod-cat-title{font-size:16px;font-weight:700;margin:24px 0 4px;color:#334155;border-bottom:2px solid #e2e8f0;padding-bottom:8px}
    </style>

    <div class="card">
        <div class="card-head">
            <h2>افزودن ماژول جدید</h2>
            <button type="button" class="btn btn-success" onclick="document.getElementById('addModuleForm').style.display = document.getElementById('addModuleForm').style.display === 'none' ? 'block' : 'none'">+ افزودن</button>
        </div>
        <form id="addModuleForm" method="post" style="display:none" class="form-grid">
            <input type="hidden" name="mod_action" value="add">
            <label>کلید (انگلیسی) <input type="text" name="module_key" required pattern="[a-z0-9_]+" dir="ltr"></label>
            <label>نام <input type="text" name="name" required></label>
            <label>توضیحات <input type="text" name="description"></label>
            <label>نسخه <input type="text" name="version" value="1.0.0" dir="ltr"></label>
            <label>دسته‌بندی <input type="text" name="category"></label>
            <label>ترتیب <input type="number" name="sort" value="999"></label>
            <div><button type="submit" class="btn btn-success">ثبت ماژول</button></div>
        </form>
    </div>

    <?php if ($editModule): ?>
    <div class="card" style="margin-top:16px">
        <form method="post" class="form-grid">
            <input type="hidden" name="mod_action" value="edit">
            <input type="hidden" name="module_id" value="<?= (int) $editModule['id'] ?>">
            <h3>ویرایش: <?= e($editModule['name']) ?></h3>
            <label>نام <input type="text" name="name" value="<?= e($editModule['name']) ?>" required></label>
            <label>توضیحات <input type="text" name="description" value="<?= e($editModule['description']) ?>"></label>
            <label>نسخه <input type="text" name="version" value="<?= e($editModule['version']) ?>" dir="ltr"></label>
            <label>دسته‌بندی <input type="text" name="category" value="<?= e($editModule['category']) ?>"></label>
            <label>آیکون <input type="text" name="icon" value="<?= e($editModule['icon']) ?>" dir="ltr"></label>
            <label>ترتیب <input type="number" name="sort" value="<?= (int) $editModule['sort_order'] ?>"></label>
            <div>
                <button type="submit" class="btn btn-warning">ذخیره</button>
                <a href="admin.php?page=modules" class="btn">انصراف</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php foreach ($grouped as $cat => $catModules): ?>
        <div class="mod-cat-title"><?= e($cat) ?> (<?= count($catModules) ?>)</div>
        <div class="mod-grid">
        <?php foreach ($catModules as $m):
            $key = (string) $m['module_key'];
            $manifest = $discovered[$key] ?? null;
            $manifestVer = $manifest['version'] ?? null;
            $hasUpdate = $manifestVer && version_compare($manifestVer, (string) $m['version'], '>');
            $isEnabled = (int) $m['enabled'] === 1;
            $isCore = (int) $m['is_core'] === 1;
        ?>
            <div class="mod-card <?= $isEnabled ? '' : 'disabled' ?>">
                <div class="mod-card-head">
                    <div class="mod-icon">🧩</div>
                    <div>
                        <h3><?= e($m['name']) ?></h3>
                        <span class="mod-key"><?= e($key) ?></span>
                        <?php if ($isCore): ?><span class="badge">هسته</span><?php endif; ?>
                    </div>
                </div>
                <?php if ($m['description']): ?><div class="mod-desc"><?= e($m['description']) ?></div><?php endif; ?>
                <div class="mod-meta">
                    <span class="mod-ver">v<?= e($m['version']) ?></span>
                    <?php if ($hasUpdate): ?><span class="mod-update">نسخه <?= e($manifestVer) ?> موجود!</span><?php endif; ?>
                    <?php if (!$isEnabled): ?><span class="badge badge-secondary">غیرفعال</span><?php endif; ?>
                </div>
                <div class="mod-actions">
                    <?php if (!$isCore): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="mod_action" value="toggle">
                        <input type="hidden" name="module_key" value="<?= e($key) ?>">
                        <input type="hidden" name="enable" value="<?= $isEnabled ? '0' : '1' ?>">
                        <button type="submit" class="btn btn-sm <?= $isEnabled ? 'btn-secondary' : 'btn-success' ?>">
                            <?= $isEnabled ? 'غیرفعال کردن' : 'فعال کردن' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                    <a href="admin.php?page=modules&edit=<?= (int) $m['id'] ?>" class="btn btn-sm btn-warning">ویرایش</a>
                    <?php if (!$isCore): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                        <input type="hidden" name="mod_action" value="delete">
                        <input type="hidden" name="module_id" value="<?= (int) $m['id'] ?>">
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
