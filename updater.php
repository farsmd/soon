<?php
/**
 * آپدیتر مستقل لاینرلایت — نسخه ۹٫۹۹٫۰
 *
 * این فایل کاملاً خودکفاست و به config.php یا admin.php وابسته نیست.
 * اگر کل سایت خوابید، از آدرس linerlight.ir/updater.php می‌توانید سیستم را برگردانید.
 *
 * امنیت: فقط ادمین لاگین‌کرده (سشن) دسترسی دارد.
 */

// سشن را با همان نام پیش‌فرض PHP شروع کن
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// بررسی لاگین ادمین
if (empty($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>دسترسی محدود</title></head><body style="font-family:Tahoma;text-align:center;padding:60px"><h1>دسترسی محدود است</h1><p>ابتدا از طریق <a href="admin.php">پنل مدیریت</a> وارد شوید.</p></body></html>');
}

define('UPDATER_VERSION', '9.99.0');
define('GITHUB_REPO', 'farsmd/soon');
define('GITHUB_BRANCH', 'main');

function updater_e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function updater_current_version(): string {
    // خواندن نسخه از config.php یا core/config.php (هر کدام موجود بود)
    foreach ([__DIR__ . '/config.php', __DIR__ . '/core/config.php'] as $f) {
        if (is_file($f)) {
            $code = file_get_contents($f);
            if ($code !== false && preg_match("/define\s*\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $code, $m)) {
                return $m[1];
            }
        }
    }
    return 'نامشخص';
}

function updater_is_protected(string $rel): bool {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') return true;
    if ($rel === 'database.sqlite') return true;
    if (preg_match('/\.(sqlite|sqlite3|db)$/i', $rel)) return true;
    if (strpos($rel, 'backups/') === 0 || $rel === 'backups') return true;
    if (strpos($rel, '.git') === 0 || $rel === '.git') return true;
    if ($rel === 'updater.php') return true; // خود آپدیتر را بازنویسی نکن
    if (strpos($rel, 'uploads/') === 0) {
        return !in_array($rel, ['uploads/.htaccess', 'uploads/index.html'], true);
    }
    return false;
}

function updater_rmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) { @rmdir($item->getPathname()); }
        else { @unlink($item->getPathname()); }
    }
    @rmdir($dir);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$message = '';
$error = '';

if ($action === 'do_update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!class_exists('ZipArchive')) {
        $error = 'افزونه ZipArchive روی سرور فعال نیست.';
    } else {
        $work = sys_get_temp_dir() . '/linerlight-update-' . getmypid();
        @mkdir($work, 0775, true);

        // بکاپ دیتابیس
        $backupFile = null;
        if (!empty($_POST['backup']) && is_file(__DIR__ . '/database.sqlite')) {
            $bdir = __DIR__ . '/backups';
            if (!is_dir($bdir)) @mkdir($bdir, 0775, true);
            $backupFile = 'database-backup-before-update-' . date('Ymd-His') . '.sqlite';
            @copy(__DIR__ . '/database.sqlite', $bdir . '/' . $backupFile);
        }

        // دانلود ZIP
        $zipUrl = 'https://codeload.github.com/' . GITHUB_REPO . '/zip/refs/heads/' . GITHUB_BRANCH;
        $zipPath = $work . '/update.zip';
        $ch = curl_init($zipUrl);
        $fp = fopen($zipPath, 'w');
        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Linerlight-Updater/1.0',
        ]);
        $ok = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $httpCode !== 200 || !is_file($zipPath)) {
            $error = 'دانلود فایل آپدیت انجام نشد (کد: ' . $httpCode . ').';
        } else {
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $error = 'فایل ZIP معتبر نیست.';
            } else {
                $extractDir = $work . '/extract';
                @mkdir($extractDir, 0775, true);
                $zip->extractTo($extractDir);
                $zip->close();

                // پیدا کردن ریشه (soon-main/)
                $srcRoot = $extractDir;
                $entries = array_values(array_filter(scandir($extractDir) ?: [], fn($x) => $x !== '.' && $x !== '..'));
                if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
                    $srcRoot = $extractDir . '/' . $entries[0];
                }

                // کپی فایل‌ها (با ساخت پوشه)
                $base = __DIR__;
                $copied = 0;
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($it as $item) {
                    $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($srcRoot))), '/');
                    if ($rel === '' || updater_is_protected($rel)) continue;
                    $dest = $base . '/' . $rel;
                    if ($item->isDir()) {
                        if (!is_dir($dest)) @mkdir($dest, 0775, true);
                    } else {
                        $destDir = dirname($dest);
                        if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
                        if (@copy($item->getPathname(), $dest)) $copied++;
                    }
                }

                $newVer = updater_current_version();
                $message = 'آپدیت انجام شد. ' . $copied . ' فایل کپی شد. نسخه فعلی: ' . $newVer;
                if ($backupFile) $message .= ' (بکاپ: ' . $backupFile . ')';
            }
        }
        updater_rmdir($work);
    }
}

$currentVersion = updater_current_version();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>آپدیتر مستقل لاینرلایت</title>
<style>
*{box-sizing:border-box}body{font-family:Tahoma,Arial,sans-serif;background:#f3f4f6;color:#111827;margin:0;padding:20px}
.box{max-width:600px;margin:40px auto;background:#fff;padding:32px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
h1{margin:0 0 8px;font-size:22px}.muted{color:#6b7280;font-size:14px}
.ok{background:#d1fae5;color:#065f46;padding:12px;border-radius:8px;margin:16px 0}
.err{background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;margin:16px 0}
.btn{display:inline-block;padding:12px 28px;background:#2563eb;color:#fff;border:0;border-radius:8px;font-size:15px;cursor:pointer;font-family:inherit}
.btn:hover{background:#1d4ed8}
.check{margin:16px 0}label{display:flex;align-items:center;gap:8px;font-size:14px}
.ver{background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:12px;margin:16px 0;font-size:14px}
</style>
</head>
<body>
<div class="box">
    <h1>آپدیتر مستقل لاینرلایت</h1>
    <p class="muted">این آپدیتر بدون وابستگی به فایل‌های اصلی کار می‌کند — حتی اگر سایت خوابیده باشد.</p>

    <div class="ver">
        نسخه فعلی نصب‌شده: <strong><?= updater_e($currentVersion) ?></strong><br>
        مخزن: <strong><?= updater_e(GITHUB_REPO) ?></strong> (شاخه <?= updater_e(GITHUB_BRANCH) ?>)
    </div>

    <?php if ($message !== ''): ?><div class="ok"><?= updater_e($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="err"><?= updater_e($error) ?></div><?php endif; ?>

    <form method="post" onsubmit="return confirm('آپدیت به آخرین نسخه انجام شود؟')">
        <input type="hidden" name="action" value="do_update">
        <div class="check">
            <label><input type="checkbox" name="backup" value="1" checked> قبل از آپدیت از دیتابیس بکاپ بگیر</label>
        </div>
        <button type="submit" class="btn">شروع آپدیت یک‌کلیکی</button>
    </form>

    <p class="muted" style="margin-top:24px">
        فایل‌های محافظت‌شده (دیتابیس، آپلودها، بکاپ‌ها) دست نمی‌خورند.<br>
        <a href="admin.php">بازگشت به پنل مدیریت</a>
    </p>
</div>
</body>
</html>
