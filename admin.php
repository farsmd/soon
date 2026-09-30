<?php
// admin.php — پنل مدیریت محتوای ساده (پسورددار) — نسخه ۴
// مدیریت بخش‌ها (با محتوای واقعی و آپلود عکس)، صفحه‌ها، قالب‌های PHP، پیام‌های تماس، بکاپ، اتصال دیتابیس، آپدیت یک‌کلیکی و تنظیمات

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

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) ($p['secure'] ?? false), true);
    }
    session_destroy();
}

$pdo = db();
$passwordHash = get_setting('admin_password_hash', '');

// ---------- خروج ----------
if (isset($_GET['logout'])) {
    logout_admin();
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

// پردازش فرم‌ها (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');

    // دانلود بکاپ دیتابیس (فقط ادمین واردشده، با CSRF) — خروجی فایل است و همین‌جا تمام می‌شود
    if ($action === 'download_backup') {
        $tmp = __DIR__ . '/backup-' . date('YmdHis') . '.sqlite';
        if (!@copy(DB_FILE, $tmp)) {
            $error = 'ساخت نسخه پشتیبان انجام نشد.';
        } else {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="site-backup-' . date('Ymd-His') . '.sqlite"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            @unlink($tmp);
            exit;
        }
    }

    try {
        switch ($action) {
            case 'add_section':
            case 'update_section':
                $title = trim((string) ($_POST['title'] ?? ''));
                $file  = basename((string) ($_POST['template_file'] ?? ''));
                $order = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $heading  = trim((string) ($_POST['heading'] ?? ''));
                $body     = (string) ($_POST['body'] ?? '');
                $linkUrl  = trim((string) ($_POST['link_url'] ?? ''));
                $linkText = trim((string) ($_POST['link_text'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('عنوان بخش را وارد کنید.');
                }
                if (!in_array($file, available_templates(), true)) {
                    throw new RuntimeException('فایل قالب انتخاب‌شده معتبر نیست.');
                }
                $id = (int) ($_POST['id'] ?? 0);
                $oldImage = null;
                if ($action === 'update_section' && $id > 0) {
                    $st = $pdo->prepare('SELECT image FROM sections WHERE id = :id');
                    $st->execute([':id' => $id]);
                    $oldImage = ($r = $st->fetch()) ? ($r['image'] ?? null) : null;
                }
                // حذف عکس فعلی؟
                if (isset($_POST['remove_image']) && $oldImage) {
                    $oldPath = UPLOADS_DIR . '/' . basename((string) $oldImage);
                    if (is_file($oldPath)) { @unlink($oldPath); }
                    $oldImage = null;
                }
                $up = handle_section_image_upload($_FILES['image'] ?? null, $oldImage ? (string) $oldImage : null);
                if (!$up['ok']) {
                    throw new RuntimeException((string) $up['error']);
                }
                $image = $up['filename'];
                if ($action === 'add_section') {
                    $stmt = $pdo->prepare('INSERT INTO sections (title, template_file, sort_order, is_active, heading, body, image, link_url, link_text) VALUES (:t,:f,:o,:a,:h,:b,:im,:lu,:lt)');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active, ':h' => $heading, ':b' => $body, ':im' => $image, ':lu' => $linkUrl, ':lt' => $linkText]);
                    flash('ok', 'بخش جدید ساخته شد.');
                } else {
                    $stmt = $pdo->prepare('UPDATE sections SET title=:t, template_file=:f, sort_order=:o, is_active=:a, heading=:h, body=:b, image=:im, link_url=:lu, link_text=:lt WHERE id=:id');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active, ':h' => $heading, ':b' => $body, ':im' => $image, ':lu' => $linkUrl, ':lt' => $linkText, ':id' => $id]);
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
                move_row($pdo, 'sections', $id, $dir);
                redirect_admin('admin.php?page=sections');
                // no break

            case 'add_page':
            case 'update_page':
                $ptitle = trim((string) ($_POST['title'] ?? ''));
                $slug   = trim((string) ($_POST['slug'] ?? ''));
                $content = (string) ($_POST['content'] ?? '');
                $seoT   = trim((string) ($_POST['seo_title'] ?? ''));
                $seoD   = trim((string) ($_POST['seo_description'] ?? ''));
                $porder = (int) ($_POST['sort_order'] ?? 0);
                $pactive = isset($_POST['is_active']) ? 1 : 0;
                $inMenu  = isset($_POST['show_in_menu']) ? 1 : 0;
                $pid = (int) ($_POST['id'] ?? 0);
                if ($ptitle === '') {
                    throw new RuntimeException('عنوان صفحه را وارد کنید.');
                }
                if ($slug === '' || !is_valid_slug($slug)) {
                    throw new RuntimeException('نامک (slug) فقط می‌تواند حروف انگلیسی، عدد، خط تیره و آندرلاین باشد.');
                }
                // یکتایی نامک
                $chk = $pdo->prepare('SELECT id FROM pages WHERE slug = :s AND (:id = 0 OR id != :id)');
                $chk->execute([':s' => $slug, ':id' => $pid]);
                if ($chk->fetch()) {
                    throw new RuntimeException('صفحه‌ای با این نامک از قبل وجود دارد؛ نامک دیگری انتخاب کنید.');
                }
                if ($action === 'add_page') {
                    $stmt = $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t,:s,:c,:st,:sd,:a,:o,:m)');
                    $stmt->execute([':t' => $ptitle, ':s' => $slug, ':c' => $content, ':st' => $seoT, ':sd' => $seoD, ':a' => $pactive, ':o' => $porder, ':m' => $inMenu]);
                    flash('ok', 'صفحه جدید ساخته شد.');
                } else {
                    $stmt = $pdo->prepare('UPDATE pages SET title=:t, slug=:s, content=:c, seo_title=:st, seo_description=:sd, is_active=:a, sort_order=:o, show_in_menu=:m WHERE id=:id');
                    $stmt->execute([':t' => $ptitle, ':s' => $slug, ':c' => $content, ':st' => $seoT, ':sd' => $seoD, ':a' => $pactive, ':o' => $porder, ':m' => $inMenu, ':id' => $pid]);
                    flash('ok', 'صفحه ویرایش شد.');
                }
                redirect_admin('admin.php?page=pages');
                // no break

            case 'delete_page':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM pages WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'صفحه حذف شد.');
                redirect_admin('admin.php?page=pages');
                // no break

            case 'toggle_page':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE pages SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
                redirect_admin('admin.php?page=pages');
                // no break

            case 'move_page':
                $id = (int) ($_POST['id'] ?? 0);
                $dir = (string) ($_POST['direction'] ?? '');
                move_row($pdo, 'pages', $id, $dir);
                redirect_admin('admin.php?page=pages');
                // no break

            case 'delete_message':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM contact_messages WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'پیام حذف شد.');
                redirect_admin('admin.php?page=messages');
                // no break

            case 'restore_backup':
                $f = $_FILES['backup_file'] ?? null;
                if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('فایل بکاپ را انتخاب کنید.');
                }
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['sqlite', 'sqlite3', 'db'], true)) {
                    throw new RuntimeException('فقط فایل بکاپ با پسوند sqlite یا db مجاز است.');
                }
                if ((int) ($f['size'] ?? 0) > 20 * 1024 * 1024) {
                    throw new RuntimeException('حجم فایل بکاپ بیش از حد مجاز است.');
                }
                $tmpRestore = __DIR__ . '/restore-' . date('YmdHis') . '.sqlite';
                if (!move_uploaded_file((string) $f['tmp_name'], $tmpRestore)) {
                    throw new RuntimeException('ذخیره فایل بکاپ آپلودی انجام نشد.');
                }
                // اعتبارسنجی: باید دیتابیس SQLite سالم و دارای جدول settings باشد
                $valid = false;
                try {
                    $test = new PDO('sqlite:' . $tmpRestore, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $q = $test->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
                    $valid = $q !== false && $q->fetch() !== false;
                    $test = null;
                } catch (Throwable $ignored) {
                    $valid = false;
                }
                if (!$valid) {
                    @unlink($tmpRestore);
                    throw new RuntimeException('فایل انتخاب‌شده دیتابیس SQLite معتبرِ این سیستم (دارای جدول تنظیمات) نیست؛ بازیابی انجام نشد.');
                }
                // نسخه امن از دیتابیس فعلی نگه داشته می‌شود
                @copy(DB_FILE, __DIR__ . '/database-backup-before-restore.sqlite');
                if (!@copy($tmpRestore, DB_FILE)) {
                    @unlink($tmpRestore);
                    throw new RuntimeException('جایگزینی دیتابیس انجام نشد؛ مجوز نوشتن فایل دیتابیس را بررسی کنید.');
                }
                @unlink($tmpRestore);
                // بعد از بازیابی، خروج اجباری تا با دیتابیس جدید وارد شوید
                logout_admin();
                // سشن تازه برای پیام
                session_start();
                flash('ok', 'بکاپ بازیابی شد. نسخه قبلی دیتابیس با نام database-backup-before-restore.sqlite نگه داشته شد. دوباره وارد شوید.');
                redirect_admin();
                // no break

            case 'switch_database_file':
                $selected = basename((string) ($_POST['database_file'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9._-]+\.(sqlite|sqlite3|db)$/i', $selected)) {
                    throw new RuntimeException('نام فایل دیتابیس معتبر نیست.');
                }
                $selectedPath = __DIR__ . '/' . $selected;
                if (!is_file($selectedPath)) {
                    throw new RuntimeException('فایل دیتابیس انتخاب‌شده پیدا نشد.');
                }
                if (is_file(DB_FILE) && realpath($selectedPath) === realpath(DB_FILE)) {
                    throw new RuntimeException('این فایل همین حالا دیتابیس فعال است.');
                }
                $switch = activate_database_file($selectedPath);
                if (!$switch['ok']) {
                    throw new RuntimeException((string) $switch['error']);
                }
                logout_admin();
                session_start();
                flash('ok', 'دیتابیس تغییر کرد. نسخه قبلی با نام database-backup-before-switch.sqlite نگه داشته شد. با پسورد دیتابیس جدید دوباره وارد شوید.');
                redirect_admin();
                // no break

            case 'upload_database_file':
                $f = $_FILES['database_file'] ?? null;
                if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('فایل دیتابیس را انتخاب کنید.');
                }
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['sqlite', 'db'], true)) {
                    throw new RuntimeException('فقط فایل دیتابیس با پسوند sqlite یا db مجاز است.');
                }
                if ((int) ($f['size'] ?? 0) <= 0) {
                    throw new RuntimeException('فایل دیتابیس خالی است.');
                }
                if ((int) ($f['size'] ?? 0) > 20 * 1024 * 1024) {
                    throw new RuntimeException('حجم فایل دیتابیس بیش از ۲۰ مگابایت است.');
                }
                $tmpUpload = __DIR__ . '/database-upload-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.sqlite';
                if (!move_uploaded_file((string) $f['tmp_name'], $tmpUpload)) {
                    throw new RuntimeException('ذخیره فایل دیتابیس آپلودی انجام نشد.');
                }
                $switch = activate_database_file($tmpUpload);
                @unlink($tmpUpload);
                if (!$switch['ok']) {
                    throw new RuntimeException((string) $switch['error']);
                }
                logout_admin();
                session_start();
                flash('ok', 'دیتابیس آپلودی فعال شد. نسخه قبلی با نام database-backup-before-switch.sqlite نگه داشته شد. با پسورد دیتابیس جدید دوباره وارد شوید.');
                redirect_admin();
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
                set_setting('seo_title', trim((string) ($_POST['seo_title'] ?? '')));
                set_setting('seo_description', trim((string) ($_POST['seo_description'] ?? '')));
                flash('ok', 'تنظیمات ذخیره شد.');
                redirect_admin('admin.php?page=settings');
                // no break

            case 'save_update_settings':
                $repo = trim((string) ($_POST['update_repo'] ?? ''));
                $branch = trim((string) ($_POST['update_branch'] ?? ''));
                $zipUrl = trim((string) ($_POST['update_zip_url'] ?? ''));
                if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
                    throw new RuntimeException('نام مخزن باید به شکل owner/repo باشد؛ مثل farsmd/soon.');
                }
                if (!preg_match('#^[A-Za-z0-9._/-]+$#', $branch) || strpos($branch, '..') !== false) {
                    throw new RuntimeException('نام شاخه معتبر نیست.');
                }
                if ($zipUrl !== '' && !preg_match('#^https?://#i', $zipUrl)) {
                    throw new RuntimeException('آدرس مستقیم فایل ZIP باید با http یا https شروع شود.');
                }
                set_setting('update_repo', $repo);
                set_setting('update_branch', $branch);
                set_setting('update_zip_url', $zipUrl);
                flash('ok', 'تنظیمات آپدیت ذخیره شد.');
                redirect_admin('admin.php?page=update');
                // no break

            case 'perform_update':
                $cfg = update_repo_config();
                $wantBackup = isset($_POST['backup_db']);
                $result = perform_update($cfg, $wantBackup);
                if (!$result['ok']) {
                    throw new RuntimeException((string) $result['error']);
                }
                $msg = 'آپدیت با موفقیت انجام شد. نسخه جدید: ' . (string) $result['new_version'] . '.';
                if (!empty($result['backup_file'])) {
                    $msg .= ' بکاپ دیتابیس قبل از آپدیت با نام ' . (string) $result['backup_file'] . ' در فولدر backups ذخیره شد.';
                } else {
                    $msg .= ' (بدون گرفتن بکاپ دیتابیس، طبق انتخاب شما.)';
                }
                flash('ok', $msg);
                redirect_admin('admin.php?page=update&done=1');
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

/** جابه‌جایی یک ردیف (بخش یا صفحه) به بالا/پایین با همسایه‌اش */
function move_row(PDO $pdo, string $table, int $id, string $direction): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM ' . $table . ' ORDER BY sort_order ASC, id ASC')->fetchAll();
    $idx = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) { $idx = $i; break; }
    }
    if ($idx === null) return;
    $swap = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($rows)) return;
    $a = $rows[$idx]; $b = $rows[$swap];
    $upd = $pdo->prepare('UPDATE ' . $table . ' SET sort_order = :o WHERE id = :id');
    $upd->execute([':o' => $b['sort_order'], ':id' => $a['id']]);
    $upd->execute([':o' => $a['sort_order'], ':id' => $b['id']]);
}

$sections  = get_sections(false);
$pages     = get_pages(false);
$messages  = get_contact_messages();
$templates = available_templates();
$settings  = all_settings();
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$databaseConnected = false;
try {
    $databaseConnected = ((string) $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok');
} catch (Throwable $ignored) {
    $databaseConnected = false;
}
$dbStats = null;
$dbFiles = [];
if ($page === 'database') {
    $dbStats = db_stats($pdo);
    $databaseConnected = (bool) ($dbStats['connected'] ?? false);
    $dbFiles = db_files();
}

$updateCfg = update_repo_config();
$updateInfo = null;
if ($page === 'update' && (isset($_GET['check']) || isset($_GET['done']))) {
    $updateInfo = update_check($updateCfg);
}

$editSection = null;
if ($page === 'sections' && isset($_GET['edit_id'])) {
    foreach ($sections as $s) {
        if ((int) $s['id'] === (int) $_GET['edit_id']) { $editSection = $s; break; }
    }
}
$editPage = null;
if ($page === 'pages' && isset($_GET['edit_id'])) {
    foreach ($pages as $p) {
        if ((int) $p['id'] === (int) $_GET['edit_id']) { $editPage = $p; break; }
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
        <a href="admin.php?page=pages" class="<?= $page === 'pages' ? 'active' : '' ?>">صفحه‌ها</a>
        <a href="admin.php?page=templates" class="<?= $page === 'templates' ? 'active' : '' ?>">قالب‌ها</a>
        <a href="admin.php?page=messages" class="<?= $page === 'messages' ? 'active' : '' ?>">پیام‌های تماس<?php if ($messages !== []): ?> (<?= count($messages) ?>)<?php endif; ?></a>
        <a href="admin.php?page=tools" class="<?= $page === 'tools' ? 'active' : '' ?>">ابزار و بکاپ</a>
        <a href="admin.php?page=database" class="<?= $page === 'database' ? 'active' : '' ?>"><?php if ($databaseConnected): ?><span class="status-dot" title="دیتابیس متصل است"></span><?php endif; ?>اتصال دیتابیس</a>
        <a href="admin.php?page=update" class="<?= $page === 'update' ? 'active' : '' ?>">آپدیت</a>
        <a href="admin.php?page=settings" class="<?= $page === 'settings' ? 'active' : '' ?>">تنظیمات و پسورد</a>
        <div class="sidebar-version">نسخه برنامه: <span dir="ltr"><?= e(APP_VERSION) ?></span></div>
    </aside>

    <main class="content">
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($page === 'sections'): ?>
            <h1>بخش‌های صفحه اصلی</h1>
            <p class="muted">صفحه اصلی (index.php) بخش‌های فعال را دقیقاً به همین ترتیب لود می‌کند. هر بخش محتوای خودش (تیتر، متن، عکس، لینک) را دارد. برای فرم تماس، یک بخش با قالب <code>template_contact.php</code> بسازید. برای ساخت یک صفحه تک‌قالبی، همه بخش‌ها را غیرفعال کنید و فقط یک بخش با قالب <code>template_single.php</code> فعال بگذارید.</p>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>فایل قالب</th><th>عکس</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($sections as $s): ?>
                    <tr>
                        <td><?= (int) $s['sort_order'] ?></td>
                        <td><?= e($s['title']) ?></td>
                        <td><code><?= e($s['template_file']) ?></code></td>
                        <td><?= !empty($s['image']) ? 'دارد' : '<span class="muted">—</span>' ?></td>
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
                <?php if ($sections === []): ?><tr><td colspan="6" class="muted">هنوز بخشی ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

            <h2><?= $editSection ? 'ویرایش بخش' : 'افزودن بخش جدید' ?></h2>
            <form method="post" class="card wide" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editSection ? 'update_section' : 'add_section' ?>">
                <?php if ($editSection): ?><input type="hidden" name="id" value="<?= (int) $editSection['id'] ?>"><?php endif; ?>
                <label>عنوان بخش (داخلی، برای مدیریت)
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
                <label>تیتر نمایشی بخش
                    <input type="text" name="heading" value="<?= e($editSection['heading'] ?? '') ?>" placeholder="اگر خالی باشد، عنوان پیش‌فرض قالب نشان داده می‌شود">
                </label>
                <label>متن بخش <span class="muted">(HTML ساده مجاز است؛ فقط مدیر سایت این را می‌نویسد)</span>
                    <textarea name="body" rows="6"><?= e($editSection['body'] ?? '') ?></textarea>
                </label>
                <label>عکس بخش <span class="muted">(jpg/png/webp/gif، حداکثر ۳ مگابایت؛ در اسلایدر پس‌زمینه می‌شود)</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                </label>
                <?php if ($editSection && !empty($editSection['image'])): ?>
                    <p class="muted">عکس فعلی: <code><?= e($editSection['image']) ?></code></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>آدرس لینک دکمه (اختیاری)
                    <input type="text" name="link_url" value="<?= e($editSection['link_url'] ?? '') ?>" placeholder="https://example.com یا page.php?slug=about" dir="ltr">
                </label>
                <label>متن دکمه لینک
                    <input type="text" name="link_text" value="<?= e($editSection['link_text'] ?? '') ?>" placeholder="مثلاً اطلاعات بیشتر">
                </label>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editSection || (int) $editSection['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
                <button type="submit" class="btn primary"><?= $editSection ? 'ذخیره ویرایش' : 'افزودن بخش' ?></button>
                <?php if ($editSection): ?><a class="btn" href="admin.php?page=sections">انصراف</a><?php endif; ?>
            </form>

        <?php elseif ($page === 'pages'): ?>
            <h1>صفحه‌ها</h1>
            <p class="muted">هر صفحه آدرس جدا دارد: <code>page.php?slug=نامک</code>. اگر «نمایش در منو» فعال باشد، لینکش خودکار به منوی سایت (هدر و قالب تک‌صفحه) اضافه می‌شود. لینک «خانه» همیشه اول منو است.</p>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>نامک (slug)</th><th>در منو</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td><?= (int) $p['sort_order'] ?></td>
                        <td><?= e($p['title']) ?></td>
                        <td><code><?= e($p['slug']) ?></code><br><a href="page.php?slug=<?= urlencode((string) $p['slug']) ?>" target="_blank">مشاهده</a></td>
                        <td><?= (int) $p['show_in_menu'] === 1 ? 'بله' : '<span class="muted">خیر</span>' ?></td>
                        <td><?= (int) $p['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" title="بالا">▲</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" title="پایین">▼</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit"><?= (int) $p['is_active'] === 1 ? 'غیرفعال' : 'فعال' ?></button></form>
                            <a class="btn small" href="admin.php?page=pages&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این صفحه حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="danger">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pages === []): ?><tr><td colspan="6" class="muted">هنوز صفحه‌ای ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

            <h2><?= $editPage ? 'ویرایش صفحه' : 'ساخت صفحه جدید' ?></h2>
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
                <label>محتوای صفحه <span class="muted">(HTML ساده مجاز است)</span>
                    <textarea name="content" rows="10"><?= e($editPage['content'] ?? '') ?></textarea>
                </label>
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
                <button type="submit" class="btn primary"><?= $editPage ? 'ذخیره ویرایش' : 'ساخت صفحه' ?></button>
                <?php if ($editPage): ?><a class="btn" href="admin.php?page=pages">انصراف</a><?php endif; ?>
            </form>

        <?php elseif ($page === 'templates'): ?>
            <h1>قالب‌ها</h1>
            <p class="muted">قالب‌ها فایل‌های PHP کنار همین پنل هستند (با پیشوند <code>template_</code>). داخل قالب می‌توانید از کد PHP، متغیرهای <code>$site_title</code> و <code>$site_description</code> و <code>$section</code> (محتوای بخش جاری) و پلیس‌هولدرهای <code>{{site_title}}</code> ، <code>{{site_description}}</code> ، <code>{{current_year}}</code> ، <code>{{menu}}</code> ، <code>{{seo_title}}</code> و <code>{{seo_description}}</code> استفاده کنید. ویرایش کد قالب یعنی اجرای آن روی سایت؛ فقط وقتی وارد پنل هستید این کار را بکنید.</p>

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
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_template">
                    <input type="hidden" name="template_file" value="<?= e($editTemplate) ?>">
                    <textarea name="code" rows="18" dir="ltr" spellcheck="false"><?= e($editTemplateCode) ?></textarea>
                    <button type="submit" class="btn primary">ذخیره قالب</button>
                    <a class="btn" href="index.php" target="_blank">مشاهده سایت</a>
                </form>
            <?php endif; ?>

        <?php elseif ($page === 'messages'): ?>
            <h1>پیام‌های تماس</h1>
            <p class="muted">پیام‌هایی که از فرم تماس سایت (بخش با قالب <code>template_contact.php</code>) فرستاده شده‌اند.</p>
            <table>
                <thead><tr><th>تاریخ</th><th>نام</th><th>راه تماس</th><th>پیام</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?= e($m['created_at']) ?></td>
                        <td><?= e($m['name']) ?></td>
                        <td><?= e($m['contact']) ?></td>
                        <td><?= nl2br(e($m['message'])) ?></td>
                        <td><form method="post" class="inline" onsubmit="return confirm('این پیام حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="danger">حذف</button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($messages === []): ?><tr><td colspan="5" class="muted">هنوز پیامی ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'tools'): ?>
            <h1>ابزار و بکاپ</h1>

            <h2>دانلود بکاپ دیتابیس</h2>
            <p class="muted">یک کپی از فایل <code>database.sqlite</code> (شامل تنظیمات، بخش‌ها، صفحه‌ها و پیام‌ها) دانلود می‌شود. آن را جای امن نگه دارید.</p>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="download_backup">
                <button type="submit" class="btn primary">دانلود بکاپ دیتابیس</button>
            </form>

            <h2>بازیابی بکاپ</h2>
            <p class="muted">فایل بکاپ (sqlite/db) را آپلود کنید. قبل از جایگزینی، از دیتابیس فعلی یک نسخه امن با نام <code>database-backup-before-restore.sqlite</code> نگه داشته می‌شود و بعد از بازیابی باید دوباره وارد شوید. فایل باید دیتابیس همین سیستم (دارای جدول تنظیمات) باشد.</p>
            <form method="post" class="card" enctype="multipart/form-data" onsubmit="return confirm('دیتابیس فعلی با این بکاپ جایگزین شود؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore_backup">
                <label>فایل بکاپ
                    <input type="file" name="backup_file" required accept=".sqlite,.sqlite3,.db">
                </label>
                <button type="submit" class="btn danger-btn">بازیابی بکاپ</button>
            </form>

        <?php elseif ($page === 'database'): ?>
            <h1>اتصال دیتابیس</h1>
            <p class="muted">از این صفحه می‌توانید وضعیت اتصال دیتابیس SQLite فعال را ببینید، بین فایل‌های دیتابیس موجود جابه‌جا شوید یا یک فایل دیتابیس دیگر آپلود کنید. قبل از هر تعویض، فایل تازه کامل اعتبارسنجی می‌شود و از دیتابیس فعلی نسخه امن نگه داشته می‌شود.</p>

            <?php if ($dbStats): ?>
            <section class="card wide">
                <h2>وضعیت اتصال</h2>
                <p>
                    <?php if ($dbStats['connected']): ?>
                        <span class="status-pill ok"><span class="status-dot"></span>متصل است</span>
                    <?php else: ?>
                        <span class="status-pill error">خطا در اتصال</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($dbStats['error'])): ?><div class="alert error"><?= e($dbStats['error']) ?></div><?php endif; ?>
                <table>
                    <tbody>
                        <tr><th>فایل فعال</th><td><code><?= e($dbStats['name']) ?></code></td></tr>
                        <tr><th>نسخه برنامه</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>مسیر فایل</th><td><code><?= e($dbStats['path']) ?></code></td></tr>
                        <tr><th>حجم فایل</th><td><?= e($dbStats['size_formatted']) ?></td></tr>
                        <tr><th>آخرین تغییر</th><td><?= e($dbStats['modified_formatted']) ?></td></tr>
                    </tbody>
                </table>
                <div class="stat-grid">
                    <div class="stat-card"><span>تعداد جدول‌ها</span><strong><?= (int) $dbStats['table_count'] ?></strong></div>
                    <div class="stat-card"><span>مجموع ردیف‌ها</span><strong><?= (int) $dbStats['total_rows'] ?></strong></div>
                </div>
            </section>

            <section class="card wide">
                <h2>جدول‌های دیتابیس</h2>
                <p class="muted">فهرست جدول‌های کاربر و تعداد ردیف فعلی هرکدام. جدول‌های داخلی SQLite در این فهرست نشان داده نمی‌شوند.</p>
                <table>
                    <thead><tr><th>نام جدول</th><th>تعداد ردیف</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbStats['tables'] as $table): ?>
                        <tr>
                            <td><code><?= e($table['name']) ?></code></td>
                            <td><?= $table['rows'] === null ? 'خطا در شمارش' : (int) $table['rows'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dbStats['tables'] === []): ?><tr><td colspan="2" class="muted">جدولی پیدا نشد.</td></tr><?php endif; ?>
                    <tr><th>جمع کل</th><th><?= (int) $dbStats['total_rows'] ?> ردیف در <?= (int) $dbStats['table_count'] ?> جدول</th></tr>
                    </tbody>
                </table>
            </section>
            <?php else: ?>
                <div class="alert error">دریافت وضعیت دیتابیس انجام نشد.</div>
            <?php endif; ?>

            <section class="card wide">
                <h2>انتخاب فایل دیتابیس موجود</h2>
                <p class="muted">فایل‌های <code>sqlite</code>، <code>sqlite3</code> و <code>db</code> موجود در فولدر برنامه فهرست شده‌اند. برای فعال‌کردن یک فایل دیگر، روی «اتصال به این فایل» کلیک کنید. قبل از تعویض، از دیتابیس فعلی فایل <code>database-backup-before-switch.sqlite</code> ساخته می‌شود و بعد از تعویض باید با پسورد دیتابیس جدید دوباره وارد شوید.</p>
                <table>
                    <thead><tr><th>نام فایل</th><th>وضعیت</th><th>حجم</th><th>آخرین تغییر</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbFiles as $file): ?>
                        <tr>
                            <td><code><?= e($file['name']) ?></code></td>
                            <td><?php if ($file['is_active']): ?><span class="badge ok">فعال</span><?php else: ?><span class="badge off">غیرفعال</span><?php endif; ?></td>
                            <td><?= e($file['size_formatted']) ?></td>
                            <td><?= e($file['modified_formatted']) ?></td>
                            <td>
                                <?php if ($file['is_active']): ?>
                                    <button type="button" disabled>فایل فعال فعلی</button>
                                <?php else: ?>
                                    <form method="post" class="inline" onsubmit="return confirm('دیتابیس فعال با این فایل جایگزین شود؟ قبل از تعویض نسخه امن ساخته می‌شود و باید دوباره وارد شوید.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="switch_database_file">
                                        <input type="hidden" name="database_file" value="<?= e($file['name']) ?>">
                                        <button type="submit" class="btn primary small">اتصال به این فایل</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dbFiles === []): ?><tr><td colspan="5" class="muted">فایل دیتابیسی در فولدر برنامه پیدا نشد.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="card wide">
                <h2>آپلود فایل دیتابیس</h2>
                <p class="muted">یک فایل <code>sqlite</code> یا <code>db</code> (حداکثر ۲۰ مگابایت) انتخاب کنید. فایل باید دیتابیس سالم همین سیستم و دارای جدول <code>settings</code> باشد. اگر اعتبارسنجی موفق نباشد، دیتابیس فعلی دست‌نخورده باقی می‌ماند.</p>
                <form method="post" enctype="multipart/form-data" onsubmit="return confirm('این فایل به‌عنوان دیتابیس فعال جایگزین شود؟ قبل از تعویض نسخه امن ساخته می‌شود و باید دوباره وارد شوید.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_database_file">
                    <label>فایل دیتابیس
                        <input type="file" name="database_file" required accept=".sqlite,.db">
                    </label>
                    <button type="submit" class="btn danger-btn">آپلود و اتصال به دیتابیس</button>
                </form>
            </section>

            <section class="card wide">
                <h2>نکته مهم درباره بکاپ و پسورد</h2>
                <p class="muted">بکاپی که از «ابزار و بکاپ» دانلود می‌کنید روی دستگاه شما ذخیره می‌شود. نسخه‌های امن قبل از بازیابی یا تعویض دیتابیس، با نام‌های <code>database-backup-before-restore.sqlite</code> و <code>database-backup-before-switch.sqlite</code> در همان فولدر برنامه نگه داشته می‌شوند. همه تنظیمات، صفحه‌ها، بخش‌ها، پیام‌ها و هش پسورد مدیریت داخل فایل <code>database.sqlite</code> است؛ بنابراین بعد از اتصال به یک دیتابیس دیگر، ورود با پسورد ذخیره‌شده در همان دیتابیس جدید انجام می‌شود.</p>
            </section>

        <?php elseif ($page === 'update'): ?>
            <h1>آپدیت سیستم</h1>
            <p class="muted">از این صفحه می‌توانید ببینید نسخه تازه‌ای از سیستم در مخزن گیت‌هاب منتشر شده یا نه و با یک کلیک سایت را آپدیت کنید. آپدیت فقط فایل‌های کد را جایگزین می‌کند؛ فایل <code>database.sqlite</code>، تنظیمات داخل آن، عکس‌های آپلودی شما در <code>uploads</code> و فولدر <code>backups</code> هرگز دست نمی‌خورند و هیچ فایل محلی‌ای پاک نمی‌شود. بعد از آپدیت، مهاجرت دیتابیس در اولین بازدید به‌صورت خودکار اجرا می‌شود و پسورد و تنظیمات فعلی حفظ می‌شوند.</p>

            <section class="card wide">
                <h2>وضعیت نسخه</h2>
                <table>
                    <tbody>
                        <tr><th>نسخه نصب‌شده فعلی</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>مخزن گیت‌هاب</th><td><code><?= e($updateCfg['repo']) ?></code></td></tr>
                        <tr><th>شاخه</th><td><code><?= e($updateCfg['branch']) ?></code></td></tr>
                        <?php if (!empty($updateCfg['zip_url'])): ?>
                        <tr><th>آدرس مستقیم فایل آپدیت</th><td><code><?= e($updateCfg['zip_url']) ?></code></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p><a class="btn" href="admin.php?page=update&check=1">بررسی نسخه تازه در گیت‌هاب</a></p>

                <?php if ($updateInfo !== null): ?>
                    <?php if (!empty($updateInfo['error'])): ?>
                        <div class="alert error"><?= e($updateInfo['error']) ?></div>
                    <?php elseif (!empty($updateInfo['checked'])): ?>
                        <?php if (!empty($updateInfo['update_available'])): ?>
                            <p><span class="status-pill ok">آپدیت تازه موجود است</span></p>
                            <p>آخرین نسخه در مخزن: <strong dir="ltr"><?= e((string) $updateInfo['latest']) ?></strong> — نسخه فعلی شما: <span dir="ltr"><?= e(APP_VERSION) ?></span></p>
                        <?php else: ?>
                            <p><span class="status-pill ok">سیستم به‌روز است</span></p>
                            <p class="muted">آخرین نسخه مخزن (<span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span>) با نسخه فعلی شما یکی است.</p>
                        <?php endif; ?>

                        <?php if (!empty($updateInfo['commits'])): ?>
                            <h3>آخرین تغییرات مخزن</h3>
                            <table>
                                <thead><tr><th>توضیح تغییر</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                <?php foreach ($updateInfo['commits'] as $c): ?>
                                    <tr><td><?= e($c['message']) ?></td><td><?= e($c['date']) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">برای دیدن آخرین نسخه و فهرست تغییرات، روی «بررسی نسخه تازه در گیت‌هاب» کلیک کنید.</p>
                <?php endif; ?>
            </section>

            <section class="card wide">
                <h2>آپدیت با یک کلیک</h2>
                <p class="muted">قبل از جایگزینی فایل‌ها، در صورت تیک خوردن گزینه زیر، از دیتابیس فعلی در فولدر <code>backups</code> بکاپ گرفته می‌شود. اگر گرفتن بکاپ ناموفق باشد، آپدیت اصلاً شروع نمی‌شود. اگر بکاپ نمی‌خواهید، تیک را بردارید؛ در این صورت آپدیت بدون بکاپ انجام می‌شود.</p>
                <form method="post" onsubmit="return confirm('فایل‌های کد سایت با نسخه تازه جایگزین شوند؟ دیتابیس و فایل‌های آپلودی شما دست نمی‌خورند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="perform_update">
                    <label class="check">
                        <input type="checkbox" name="backup_db" value="1" checked>
                        گرفتن بکاپ از دیتابیس قبل از آپدیت
                    </label>
                    <?php if ($updateInfo !== null && !empty($updateInfo['update_available'])): ?>
                        <button type="submit" class="btn primary">آپدیت به نسخه <span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span></button>
                    <?php else: ?>
                        <button type="submit" class="btn primary" disabled>اول نسخه تازه را بررسی کنید</button>
                        <p class="muted">دکمه آپدیت فقط وقتی فعال می‌شود که نسخه تازه‌تری در مخزن پیدا شود.</p>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card wide">
                <h2>تنظیمات مخزن آپدیت</h2>
                <p class="muted">مخزن و شاخه‌ای که آپدیت‌ها از آن خوانده می‌شوند. به‌صورت پیش‌فرض مخزن رسمی همین سیستم است. «آدرس مستقیم فایل ZIP آپدیت» یک گزینه پیشرفته است و معمولاً باید خالی بماند؛ اگر پر شود، دانلود و بررسی نسخه از همان آدرس انجام می‌شود (مثلاً برای میرور یا تست).</p>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_update_settings">
                    <label>مخزن گیت‌هاب (owner/repo)
                        <input type="text" name="update_repo" dir="ltr" value="<?= e($updateCfg['repo']) ?>" required>
                    </label>
                    <label>شاخه
                        <input type="text" name="update_branch" dir="ltr" value="<?= e($updateCfg['branch']) ?>" required>
                    </label>
                    <label>آدرس مستقیم فایل ZIP آپدیت (پیشرفته — معمولاً خالی)
                        <input type="text" name="update_zip_url" dir="ltr" value="<?= e($updateCfg['zip_url']) ?>" placeholder="https://example.com/update.zip">
                    </label>
                    <button type="submit" class="btn primary">ذخیره تنظیمات آپدیت</button>
                </form>
            </section>

        <?php else: ?>
            <h1>تنظیمات سایت</h1>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">
                <label>عنوان سایت
                    <input type="text" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>">
                </label>
                <label>توضیح سایت
                    <textarea name="site_description" rows="3"><?= e($settings['site_description'] ?? '') ?></textarea>
                </label>
                <label>عنوان سئوی صفحه اصلی (اگر خالی باشد عنوان سایت استفاده می‌شود)
                    <input type="text" name="seo_title" value="<?= e($settings['seo_title'] ?? '') ?>">
                </label>
                <label>توضیح سئوی صفحه اصلی (meta description)
                    <textarea name="seo_description" rows="2"><?= e($settings['seo_description'] ?? '') ?></textarea>
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
label{display:block;margin:12px 0;font-size:14px}input[type=text],input[type=password],input[type=number],select,textarea,input[type=file]{width:100%;padding:9px;margin-top:6px;border:1px solid #d1d5db;border-radius:8px;font-family:inherit}
textarea[dir=ltr]{font-family:Consolas,monospace;font-size:13px}
.btn{display:inline-block;padding:8px 14px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;cursor:pointer;font-family:inherit}
.btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.btn.small{padding:4px 10px;font-size:13px}.btn.block{width:100%}
.btn.danger-btn{background:#dc2626;border-color:#dc2626;color:#fff}
button{padding:6px 10px;border:1px solid #d1d5db;border-radius:7px;background:#fff;cursor:pointer;font-family:inherit}
button.danger{color:#dc2626;border-color:#fecaca}
.alert{padding:10px 14px;border-radius:8px;margin:12px 0;font-size:14px}.alert.ok{background:#dcfce7}.alert.error{background:#fee2e2}
.topbar{display:flex;justify-content:space-between;align-items:center;background:#111827;color:#fff;padding:12px 18px}
.topbar a{color:#93c5fd;margin-inline-start:14px}
.layout{display:flex;min-height:calc(100vh - 49px)}
.sidebar{width:210px;background:#fff;border-inline-end:1px solid #e5e7eb;padding:14px;display:flex;flex-direction:column;gap:6px}
.sidebar a{padding:9px 12px;border-radius:8px;color:#111827}.sidebar a.active{background:#eff6ff;color:#2563eb;font-weight:bold}
.sidebar-version{margin-top:auto;padding:10px 12px 4px;border-top:1px solid #e5e7eb;color:#9ca3af;font-size:12px}
.status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px #dcfce7;margin-inline-end:7px;vertical-align:middle}
.status-pill{display:inline-flex;align-items:center;padding:5px 11px;border-radius:99px;font-size:13px;font-weight:bold}.status-pill.ok{background:#dcfce7;color:#166534}.status-pill.error{background:#fee2e2;color:#991b1b}.status-pill .status-dot{box-shadow:none;margin-inline-end:6px}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:14px}.stat-card{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px}.stat-card span{display:block;color:#6b7280;font-size:13px;margin-bottom:6px}.stat-card strong{font-size:24px;color:#111827}
.content{flex:1;padding:20px;max-width:1050px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;margin:14px 0}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px;vertical-align:top}
th{background:#f9fafb}.actions{white-space:nowrap}.inline{display:inline}
.card{background:#fff;padding:16px;border-radius:10px;margin:14px 0;max-width:640px}
.card.wide{max-width:820px}
.badge{padding:2px 8px;border-radius:99px;font-size:12px}.badge.ok{background:#dcfce7}.badge.off{background:#e5e7eb}
.check{display:flex;gap:8px;align-items:center}.file-input{direction:ltr;display:flex;align-items:center;gap:4px}
code{background:#f3f4f6;padding:1px 5px;border-radius:5px;direction:ltr;display:inline-block}
CSS;
}
