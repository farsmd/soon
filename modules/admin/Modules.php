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
        if ($key !== '' && module_set_enabled($key, $enable)) {
            return $enable ? 'ماژول فعال شد.' : 'ماژول غیرفعال شد.';
        }
        return 'تغییر وضعیت انجام نشد.';
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

/** رندر صفحه مدیریت ماژول‌ها */
function modules_render(): void
{
    modules_seed();
    $msg = modules_handle_post();
    $modules = modules_all();
    $editId = (int) ($_GET['edit'] ?? 0);
    $editModule = null;
    if ($editId > 0) {
        foreach ($modules as $m) { if ((int) $m['id'] === $editId) { $editModule = $m; break; } }
    }
    ?>
    <div class="page-head">
        <h1>مدیریت ماژول‌ها</h1>
        <p class="muted">ماژول‌ها را فعال/غیرفعال کنید، ویرایش کنید یا ماژول جدید بسازید.</p>
    </div>
    <?php if ($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>

    <div class="card">
        <div class="card-head">
            <h2>فهرست ماژول‌ها (<?= count($modules) ?>)</h2>
            <button type="button" class="btn btn-success" onclick="document.getElementById('addModuleForm').style.display = document.getElementById('addModuleForm').style.display === 'none' ? 'block' : 'none'">+ افزودن ماژول</button>
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

        <?php if ($editModule): ?>
        <form method="post" class="form-grid" style="background:#f8fafc;padding:16px;border-radius:8px;margin-bottom:16px">
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
        <?php endif; ?>

        <div class="table-wrap"><table class="table">
            <thead><tr><th>نام</th><th>کلید</th><th>دسته</th><th>نسخه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($modules as $m): ?>
                <tr>
                    <td><strong><?= e($m['name']) ?></strong><br><small class="muted"><?= e($m['description']) ?></small></td>
                    <td dir="ltr"><code><?= e($m['module_key']) ?></code><?= (int) $m['is_core'] ? ' <span class="badge">هسته</span>' : '' ?></td>
                    <td><?= e($m['category']) ?></td>
                    <td dir="ltr"><?= e($m['version']) ?></td>
                    <td>
                        <?php if ((int) $m['is_core']): ?>
                            <span class="badge badge-success">فعال</span>
                        <?php else: ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="mod_action" value="toggle">
                                <input type="hidden" name="module_key" value="<?= e($m['module_key']) ?>">
                                <input type="hidden" name="enable" value="<?= (int) $m['enabled'] ? '0' : '1' ?>">
                                <button type="submit" class="btn btn-sm <?= (int) $m['enabled'] ? 'btn-success' : 'btn-secondary' ?>">
                                    <?= (int) $m['enabled'] ? 'فعال' : 'غیرفعال' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="admin.php?page=modules&edit=<?= (int) $m['id'] ?>" class="btn btn-sm btn-warning">ویرایش</a>
                        <?php if (!(int) $m['is_core']): ?>
                        <form method="post" style="display:inline" onsubmit="return confirm('حذف شود؟')">
                            <input type="hidden" name="mod_action" value="delete">
                            <input type="hidden" name="module_id" value="<?= (int) $m['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">حذف</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php
}
