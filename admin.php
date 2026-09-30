<?php
// admin.php — پنل مدیریت محتوای ساده (پسورددار)
// مدیریت بخش‌ها، قالب‌های PHP، تنظیمات سایت و تغییر پسورد

declare(strict_types=1);

require __DIR__ . '/config.php';

// ---------- سشن امن نسبی ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('درخواست نامعتبر است (CSRF). لطفاً دوباره تلاش کنید.');
    }
}

function redirect_admin(string $url = 'admin.php'): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

$pdo = db();
$passwordHash = get_setting('admin_password_hash', '');

// ---------- خروج ----------
if (isset($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), true);
    }
    session_destroy();
    redirect_admin();
}

// ---------- ساخت پسورد برای اولین بار ----------
if ($passwordHash === '') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup') {
        check_csrf();
        $pw  = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($pw) < 8) {
            $error = 'پسورد باید حداقل ۸ کاراکتر باشد.';
        } elseif ($pw !== $pw2) {
            $error = 'تکرار پسورد با پسورد یکسان نیست.';
        } else {
            set_setting('admin_password_hash', password_hash($pw, PASSWORD_DEFAULT));
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            redirect_admin();
        }
    }
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>راه‌اندازی مدیریت</title>
<style><?= admin_css() ?></style>
</head>
<body>
<div class="auth-box">
    <h1>راه‌اندازی مدیریت</h1>
    <p class="muted">برای اولین بار وارد می‌شوید. یک پسورد برای پنل مدیریت بسازید.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="setup">
        <label>پسورد مدیریت
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>تکرار پسورد
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn primary block">ساخت پسورد و ورود</button>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- فرم ورود ----------
if (!is_logged_in()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        check_csrf();
        $pw = (string) ($_POST['password'] ?? '');
        if (password_verify($pw, $passwordHash)) {
            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            redirect_admin();
        }
        $error = 'پسورد اشتباه است.';
    }
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود مدیریت</title>
<style><?= admin_css() ?></style>
</head>
<body>
<div class="auth-box">
    <h1>ورود مدیریت</h1>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>پسورد
            <input type="password" name="password" required autocomplete="current-password" autofocus>
        </label>
        <button type="submit" class="btn primary block">ورود</button>
    </form>
    <p class="muted center"><a href="index.php">مشاهده سایت</a></p>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- از اینجا به بعد فقط ادمین واردشده ----------

$page  = (string) ($_GET['page'] ?? 'sections');
$error = '';
flash_pull:

// پردازش فرم‌ها (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'add_section':
            case 'update_section':
                $title = trim((string) ($_POST['title'] ?? ''));
                $file  = basename((string) ($_POST['template_file'] ?? ''));
                $order = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                if ($title === '') {
                    throw new RuntimeException('عنوان بخش را وارد کنید.');
                }
                if (!in_array($file, available_templates(), true)) {
                    throw new RuntimeException('فایل قالب انتخاب‌شده معتبر نیست.');
                }
                if ($action === 'add_section') {
                    $stmt = $pdo->prepare('INSERT INTO sections (title, template_file, sort_order, is_active) VALUES (:t,:f,:o,:a)');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active]);
                    flash('ok', 'بخش جدید ساخته شد.');
                } else {
                    $id = (int) ($_POST['id'] ?? 0);
                    $stmt = $pdo->prepare('UPDATE sections SET title=:t, template_file=:f, sort_order=:o, is_active=:a WHERE id=:id');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active, ':id' => $id]);
                    flash('ok', 'بخش ویرایش شد.');
                }
                redirect_admin('admin.php?page=sections');
                // no break (redirect exits)

            case 'delete_section':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM sections WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'بخش حذف شد.');
                redirect_admin('admin.php?page=sections');
                // no break

            case 'toggle_section':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE sections SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
                redirect_admin('admin.php?page=sections');
                // no break

            case 'move_section':
                $id = (int) ($_POST['id'] ?? 0);
                $dir = (string) ($_POST['direction'] ?? '');
                move_section($pdo, $id, $dir);
                redirect_admin('admin.php?page=sections');
                // no break

            case 'create_template':
                $slug = trim((string) ($_POST['slug'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9_]+$/', $slug)) {
                    throw new RuntimeException('نام قالب فقط می‌تواند حروف انگلیسی، عدد و آندرلاین باشد.');
                }
                $file = 'template_' . $slug . '.php';
                $path = __DIR__ . '/' . $file;
                if (is_file($path)) {
                    throw new RuntimeException('قالبی با این نام از قبل وجود دارد.');
                }
                $starter = "<!-- قالب: " . e($slug) . " -->\n<section class=\"custom-section\">\n    <div class=\"container\">\n        <h2>{{site_title}}</h2>\n        <p>این متن قالب «" . e($slug) . "» است. از پنل مدیریت آن را ویرایش کنید.</p>\n    </div>\n</section>\n";
                file_put_contents($path, $starter);
                flash('ok', 'قالب «' . $file . '» ساخته شد.');
                redirect_admin('admin.php?page=templates&edit=' . urlencode($file));
                // no break

            case 'save_template':
                $file = basename((string) ($_POST['template_file'] ?? ''));
                if (!is_valid_template_file($file)) {
                    throw new RuntimeException('نام فایل قالب معتبر نیست.');
                }
                $path = __DIR__ . '/' . $file;
                if (!is_file($path)) {
                    throw new RuntimeException('فایل قالب پیدا نشد.');
                }
                file_put_contents($path, (string) ($_POST['code'] ?? ''));
                flash('ok', 'قالب «' . $file . '» ذخیره شد.');
                redirect_admin('admin.php?page=templates&edit=' . urlencode($file));
                // no break

            case 'delete_template':
                $file = basename((string) ($_POST['template_file'] ?? ''));
                if (!is_valid_template_file($file)) {
                    throw new RuntimeException('نام فایل قالب معتبر نیست.');
                }
                $used = $pdo->prepare('SELECT COUNT(*) FROM sections WHERE template_file = :f');
                $used->execute([':f' => $file]);
                if ((int) $used->fetchColumn() > 0) {
                    throw new RuntimeException('این قالب در یک یا چند بخش استفاده شده است؛ اول آن بخش‌ها را حذف یا قالبشان را عوض کنید.');
                }
                $path = __DIR__ . '/' . $file;
                if (is_file($path)) {
                    unlink($path);
                }
                flash('ok', 'قالب «' . $file . '» حذف شد.');
                redirect_admin('admin.php?page=templates');
                // no break

            case 'save_settings':
                set_setting('site_title', trim((string) ($_POST['site_title'] ?? '')));
                set_setting('site_description', trim((string) ($_POST['site_description'] ?? '')));
                flash('ok', 'تنظیمات ذخیره شد.');
                redirect_admin('admin.php?page=settings');
                // no break

            case 'change_password':
                $current = (string) ($_POST['current_password'] ?? '');
                $new     = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['new_password_confirm'] ?? '');
                if (!password_verify($current, get_setting('admin_password_hash', ''))) {
                    throw new RuntimeException('پسورد فعلی اشتباه است.');
                }
                if (strlen($new) < 8) {
                    throw new RuntimeException('پسورد جدید باید حداقل ۸ کاراکتر باشد.');
                }
                if ($new !== $confirm) {
                    throw new RuntimeException('تکرار پسورد جدید یکسان نیست.');
                }
                set_setting('admin_password_hash', password_hash($new, PASSWORD_DEFAULT));
                flash('ok', 'پسورد تغییر کرد.');
                redirect_admin('admin.php?page=settings');
                // no break
        }
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}

/** جابه‌جایی یک بخش به بالا/پایین با همسایه‌اش */
function move_section(PDO $pdo, int $id, string $direction): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM sections ORDER BY sort_order ASC, id ASC')->fetchAll();
    $idx = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) { $idx = $i; break; }
    }
    if ($idx === null) return;
    $swap = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($rows)) return;
    $a = $rows[$idx]; $b = $rows[$swap];
    $upd = $pdo->prepare('UPDATE sections SET sort_order = :o WHERE id = :id');
    $upd->execute([':o' => $b['sort_order'], ':id' => $a['id']]);
    $upd->execute([':o' => $a['sort_order'], ':id' => $b['id']]);
}

$sections  = get_sections(false);
$templates = available_templates();
$settings  = all_settings();
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editSection = null;
if ($page === 'sections' && isset($_GET['edit_id'])) {
    foreach ($sections as $s) {
        if ((int) $s['id'] === (int) $_GET['edit_id']) { $editSection = $s; break; }
    }
}
$editTemplate = null;
$editTemplateCode = '';
if ($page === 'templates' && isset($_GET['edit'])) {
    $candidate = basename((string) $_GET['edit']);
    if (is_valid_template_file($candidate) && is_file(__DIR__ . '/' . $candidate)) {
        $editTemplate = $candidate;
        $editTemplateCode = (string) file_get_contents(__DIR__ . '/' . $candidate);
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>پنل مدیریت</title>
<style><?= admin_css() ?></style>
</head>
<body>
<header class="topbar">
    <strong>پنل مدیریت — <?= e($settings['site_title'] ?? '') ?></strong>
    <nav>
        <a href="index.php" target="_blank">مشاهده سایت</a>
        <a href="admin.php?logout=1">خروج</a>
    </nav>
</header>

<div class="layout">
    <aside class="sidebar">
        <a href="admin.php?page=sections" class="<?= $page === 'sections' ? 'active' : '' ?>">بخش‌های صفحه اصلی</a>
        <a href="admin.php?page=templates" class="<?= $page === 'templates' ? 'active' : '' ?>">قالب‌ها</a>
        <a href="admin.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">تنظیمات و پسورد</a>
    </aside>

    <main class="content">
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($page === 'sections'): ?>
            <h1>بخش‌های صفحه اصلی</h1>
            <p class="muted">صفحه اصلی (index.php) بخش‌های فعال را دقیقاً به همین ترتیب لود می‌کند. برای ساخت یک صفحه تک‌قالبی، همه بخش‌ها را غیرفعال کنید و فقط یک بخش با قالب <code>template_single.php</code> فعال بگذارید.</p>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>فایل قالب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($sections as $s): ?>
                    <tr>
                        <td><?= (int) $s['sort_order'] ?></td>
                        <td><?= e($s['title']) ?></td>
                        <td><code><?= e($s['template_file']) ?></code></td>
                        <td><?= (int) $s['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" title="بالا">▲</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" title="پایین">▼</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit"><?= (int) $s['is_active'] === 1 ? 'غیرفعال' : 'فعال' ?></button></form>
                            <a class="btn small" href="admin.php?page=sections&edit_id=<?= (int) $s['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این بخش حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="danger">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sections === []): ?><tr><td colspan="5" class="muted">هنوز بخشی ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

            <h2><?= $editSection ? 'ویرایش بخش' : 'افزودن بخش جدید' ?></h2>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editSection ? 'update_section' : 'add_section' ?>">
                <?php if ($editSection): ?><input type="hidden" name="id" value="<?= (int) $editSection['id'] ?>"><?php endif; ?>
                <label>عنوان بخش
                    <input type="text" name="title" required value="<?= e($editSection['title'] ?? '') ?>" placeholder="مثلاً اسلایدر اصلی">
                </label>
                <label>فایل قالب
                    <select name="template_file" required>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= e($t) ?>" <?= ($editSection && $editSection['template_file'] === $t) ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= e($editSection['sort_order'] ?? '10') ?>">
                </label>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editSection || (int) $editSection['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
                <button type="submit" class="btn primary"><?= $editSection ? 'ذخیره ویرایش' : 'افزودن بخش' ?></button>
                <?php if ($editSection): ?><a class="btn" href="admin.php?page=sections">انصراف</a><?php endif; ?>
            </form>

        <?php elseif ($page === 'templates'): ?>
            <h1>قالب‌ها</h1>
            <p class="muted">قالب‌ها فایل‌های PHP کنار همین پنل هستند (با پیشوند <code>template_</code>). داخل قالب می‌توانید از کد PHP، متغیرهای <code>$site_title</code> و <code>$site_description</code> و پلیس‌هولدرهای <code>{{site_title}}</code> ، <code>{{site_description}}</code> و <code>{{current_year}}</code> استفاده کنید. ویرایش کد قالب یعنی اجرای آن روی سایت؛ فقط وقتی وارد پنل هستید این کار را بکنید.</p>

            <table>
                <thead><tr><th>فایل قالب</th><th>در بخش‌ها استفاده شده؟</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($templates as $t):
                    $usedIn = [];
                    foreach ($sections as $s) { if ($s['template_file'] === $t) $usedIn[] = $s['title']; }
                ?>
                    <tr>
                        <td><code><?= e($t) ?></code></td>
                        <td><?= $usedIn ? e(implode('، ', $usedIn)) : '<span class="muted">بلااستفاده</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=templates&edit=<?= urlencode($t) ?>">ویرایش کد</a>
                            <?php if (!$usedIn): ?>
                            <form method="post" class="inline" onsubmit="return confirm('این فایل قالب حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_template"><input type="hidden" name="template_file" value="<?= e($t) ?>"><button type="submit" class="danger">حذف</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>ساخت قالب جدید</h2>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_template">
                <label>نام قالب (فقط حروف انگلیسی، عدد و آندرلاین)
                    <span class="file-input">template_<input type="text" name="slug" required pattern="[A-Za-z0-9_]+" placeholder="promo">.php</span>
                </label>
                <button type="submit" class="btn primary">ساخت قالب</button>
            </form>

            <?php if ($editTemplate): ?>
                <h2>ویرایش کد: <code><?= e($editTemplate) ?></code></h2>
                <form method="post" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_file" value="<?= e($editTemplate) ?>">
                    <textarea name="code" rows="18" dir="ltr" spellcheck="false"><?= e($editTemplateCode) ?></textarea>
                    <button type="submit" class="btn primary">ذخیره قالب</button>
                    <a class="btn" href="index.php" target="_blank">مشاهده سایت</a>
                </form>
            <?php endif; ?>

        <?php else: ?>
            <h1>تنظیمات سایت</h1>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">
                <label>عنوان سایت
                    <input type="text" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>">
                </label>
                <label>توضیح سایت
                    <textarea name="site_description" rows="3"><?= e($settings['site_description'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="btn primary">ذخیره تنظیمات</button>
            </form>

            <h2>تغییر پسورد مدیریت</h2>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <label>پسورد فعلی
                    <input type="password" name="current_password" required autocomplete="current-password">
                </label>
                <label>پسورد جدید (حداقل ۸ کاراکتر)
                    <input type="password" name="new_password" required autocomplete="new-password">
                </label>
                <label>تکرار پسورد جدید
                    <input type="password" name="new_password_confirm" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn primary">تغییر پسورد</button>
            </form>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
<?php
// استایل داخلی پنل (برای اینکه admin.php مستقل بماند)
function admin_css(): string
{
    return <<<'CSS'
*{box-sizing:border-box}body{font-family:Tahoma,Arial,sans-serif;background:#f3f4f6;color:#111827;margin:0}
a{color:#2563eb;text-decoration:none}.muted{color:#6b7280;font-size:13px}.center{text-align:center}
.auth-box{max-width:380px;margin:10vh auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
label{display:block;margin:12px 0;font-size:14px}input[type=text],input[type=password],input[type=number],select,textarea{width:100%;padding:9px;margin-top:6px;border:1px solid #d1d5db;border-radius:8px;font-family:inherit}
textarea[dir=ltr]{font-family:Consolas,monospace;font-size:13px}
.btn{display:inline-block;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;cursor:pointer;font-family:inherit}
.btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.btn.small{padding:4px 10px;font-size:13px}.btn.block{width:100%}
button{padding:6px 10px;border:1px solid #d1d5db;border-radius:7px;background:#fff;cursor:pointer;font-family:inherit}
button.danger{color:#dc2626;border-color:#fecaca}
.alert{padding:10px 14px;border-radius:8px;margin:12px 0;font-size:14px}.alert.ok{background:#dcfce7}.alert.error{background:#fee2e2}
.topbar{display:flex;justify-content:space-between;align-items:center;background:#111827;color:#fff;padding:12px 18px}
.topbar a{color:#93c5fd;margin-inline-start:14px}
.layout{display:flex;min-height:calc(100vh - 49px)}
.sidebar{width:200px;background:#fff;border-inline-end:1px solid #e5e7eb;padding:14px;display:flex;flex-direction:column;gap:6px}
.sidebar a{padding:9px 12px;border-radius:8px;color:#111827}.sidebar a.active{background:#eff6ff;color:#2563eb;font-weight:bold}
.content{flex:1;padding:20px;max-width:1000px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;margin:14px 0}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px;vertical-align:top}
th{background:#f9fafb}.actions{white-space:nowrap}.inline{display:inline}
.card{background:#fff;padding:16px;border-radius:10px;margin:14px 0;max-width:640px}
.badge{padding:2px 8px;border-radius:99px;font-size:12px}.badge.ok{background:#dcfce7}.badge.off{background:#e5e7eb}
.check{display:flex;gap:8px;align-items:center}.file-input{direction:ltr;display:flex;align-items:center;gap:4px}
code{background:#f3f4f6;padding:1px 5px;border-radius:5px;direction:ltr;display:inline-block}
CSS;
}
