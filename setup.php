<?php
// setup.php — ویزارد راه‌اندازی اولیه برای بیزینس تازه (نسخه ۹٫۸)
// نصب تمیز: اطلاعات کسب‌وکار، برندینگ پایه و حساب مدیر را می‌گیرد و پرچم setup_completed را می‌زند.

declare(strict_types=1);

require __DIR__ . '/core/config.php';

cms_session_start();

// اگر راه‌اندازی قبلاً انجام شده، به پنل برو
if (get_setting('setup_completed', '') === '1') {
    header('Location: admin.php');
    exit;
}

$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $postedStep = (int) ($_POST['step'] ?? 1);

    if ($postedStep === 2) {
        $name = trim((string) ($_POST['business_name'] ?? ''));
        if ($name === '') {
            $error = 'نام کسب‌وکار را وارد کنید.';
        } else {
            set_setting('site_title', $name);
            set_setting('site_description', trim((string) ($_POST['business_desc'] ?? '')));
            set_setting('contact_phone', trim((string) ($_POST['contact_phone'] ?? '')));
            set_setting('contact_email', trim((string) ($_POST['contact_email'] ?? '')));
            set_setting('contact_whatsapp', trim((string) ($_POST['contact_whatsapp'] ?? '')));
            // لوگو
            if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
                $ext = strtolower(pathinfo((string) $_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) {
                    $dest = __DIR__ . '/uploads/logo.' . $ext;
                    @mkdir(__DIR__ . '/uploads', 0755, true);
                    // لوگوهای قبلی را پاک کن
                    foreach (glob(__DIR__ . '/uploads/logo.*') as $old) { @unlink($old); }
                    if (@move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) {
                        set_setting('site_logo', 'uploads/logo.' . $ext);
                    }
                }
            }
            header('Location: setup.php?step=3');
            exit;
        }
        $step = 2;
    } elseif ($postedStep === 3) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $pw = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password2'] ?? '');
        if ($username === '' || strlen($username) < 3) {
            $error = 'نام کاربری باید حداقل ۳ کاراکتر باشد.';
        } elseif (strlen($pw) < 8) {
            $error = 'پسورد باید حداقل ۸ کاراکتر باشد.';
        } elseif ($pw !== $pw2) {
            $error = 'تکرار پسورد با پسورد یکی نیست.';
        } else {
            $pdo = db();
            $exists = (int) $pdo->query("SELECT COUNT(*) FROM admin_users WHERE username = " . $pdo->quote($username))->fetchColumn();
            if ($exists > 0) {
                $error = 'این نام کاربری قبلاً استفاده شده است.';
            } else {
                $hash = password_hash($pw, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO admin_users (username, pass_hash, display_name, role_key, is_active) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$username, $hash, $username, 'owner', 1]);
                $_SESSION['setup_admin'] = $username;
                header('Location: setup.php?step=4');
                exit;
            }
        }
        $step = 3;
    } elseif ($postedStep === 4) {
        set_setting('setup_completed', '1');
        // ورود خودکار مدیر ساخته‌شده
        if (!empty($_SESSION['setup_admin'])) {
            $_SESSION['admin_user'] = $_SESSION['setup_admin'];
            $_SESSION['admin_role'] = 'owner';
            unset($_SESSION['setup_admin']);
        }
        header('Location: admin.php');
        exit;
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>راه‌اندازی سیستم — قدم <?= $step ?> از ۴</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Tahoma,"Segoe UI",Arial,sans-serif;background:#07090d;color:#f2ede1;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.setup-card{background:#0e131b;border:1px solid #1f2836;border-radius:18px;padding:36px;max-width:520px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.4)}
.setup-card h1{font-size:24px;margin-bottom:8px;color:#e8c66a}
.setup-card .subtitle{color:#9d937e;margin-bottom:24px;line-height:1.8}
.steps{display:flex;gap:8px;margin-bottom:28px}
.step-dot{flex:1;height:6px;border-radius:3px;background:#1f2836}
.step-dot.done{background:#c9a227}
.step-dot.current{background:#e8c66a}
label{display:block;margin-bottom:16px;font-weight:600}
label input{width:100%;margin-top:6px;padding:12px;border:1px solid #1f2836;border-radius:10px;background:#0b0e14;color:#f2ede1;font-family:inherit;font-size:15px}
.hint{font-size:12px;color:#9d937e;font-weight:400;margin-top:4px}
.btn{display:inline-block;background:#c9a227;color:#07090d;border:none;border-radius:10px;padding:14px 28px;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
.btn:hover{background:#e8c66a}
.alert{background:#fee2e2;color:#b91c1c;border-radius:10px;padding:12px;margin-bottom:16px}
.success-icon{font-size:64px;text-align:center;margin-bottom:16px}
.back-link{display:block;text-align:center;margin-top:16px;color:#9d937e;text-decoration:none}
</style>
</head>
<body>
<div class="setup-card">
    <div class="steps">
        <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="step-dot <?= $i < $step ? 'done' : ($i === $step ? 'current' : '') ?>"></div>
        <?php endfor; ?>
    </div>

    <?php if ($error !== ''): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($step === 1): ?>
        <h1>👋 خوش آمدید</h1>
        <p class="subtitle">این ویزارد در چند قدم ساده، سیستم مدیریت کسب‌وکار شما را راه‌اندازی می‌کند: اطلاعات کسب‌وکار، حساب مدیر و تنظیمات پایه.</p>
        <a href="setup.php?step=2" class="btn" style="text-align:center;text-decoration:none">شروع راه‌اندازی</a>

    <?php elseif ($step === 2): ?>
        <h1>🏢 اطلاعات کسب‌وکار</h1>
        <p class="subtitle">نام و اطلاعات تماس کسب‌وکار شما در سایت و فاکتورها نمایش داده می‌شود.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf']) ?>">
            <input type="hidden" name="step" value="2">
            <label>نام کسب‌وکار *
                <input type="text" name="business_name" required maxlength="120" value="<?= htmlspecialchars((string) get_setting('site_title', '')) ?>">
            </label>
            <label>توضیح کوتاه
                <input type="text" name="business_desc" maxlength="300" value="<?= htmlspecialchars((string) get_setting('site_description', '')) ?>">
            </label>
            <label>شماره تماس
                <input type="text" name="contact_phone" dir="ltr" maxlength="20" value="<?= htmlspecialchars((string) get_setting('contact_phone', '')) ?>">
            </label>
            <label>ایمیل
                <input type="text" name="contact_email" dir="ltr" maxlength="120" value="<?= htmlspecialchars((string) get_setting('contact_email', '')) ?>">
            </label>
            <label>لوگو (اختیاری)
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp">
                <span class="hint">فرمت‌های مجاز: PNG, JPG, SVG, WebP</span>
            </label>
            <button type="submit" class="btn">ادامه</button>
        </form>

    <?php elseif ($step === 3): ?>
        <h1>👤 حساب مدیر</h1>
        <p class="subtitle">حساب مدیر کل سیستم را بسازید. با این حساب وارد پنل مدیریت می‌شوید.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf']) ?>">
            <input type="hidden" name="step" value="3">
            <label>نام کاربری *
                <input type="text" name="username" required minlength="3" maxlength="50" dir="ltr" autocomplete="username">
            </label>
            <label>پسورد *
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <span class="hint">حداقل ۸ کاراکتر</span>
            </label>
            <label>تکرار پسورد *
                <input type="password" name="password2" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="btn">ادامه</button>
        </form>
        <a href="setup.php?step=2" class="back-link">→ بازگشت به قدم قبل</a>

    <?php elseif ($step === 4): ?>
        <div class="success-icon">🎉</div>
        <h1 style="text-align:center">تبریک! سیستم آماده است</h1>
        <p class="subtitle" style="text-align:center">کسب‌وکار «<?= htmlspecialchars((string) get_setting('site_title', '')) ?>» با موفقیت راه‌اندازی شد.<br>حالا وارد پنل مدیریت شوید و شروع کنید.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars((string) $_SESSION['csrf']) ?>">
            <input type="hidden" name="step" value="4">
            <button type="submit" class="btn">ورود به پنل مدیریت</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
