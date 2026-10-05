<?php
// core/modules.php — هسته سیستم ماژول وردپرسی (نسخه ۹٫۹۹٫۹)
// هر ماژول یک پوشه در modules/ با فایل module.json است.

declare(strict_types=1);

/** مسیر پوشه ماژول‌ها */
function modules_dir(): string
{
    return (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/modules';
}

// ---------- سیستم هوک (اکشن/فیلتر مثل وردپرس) ----------

$GLOBALS['__cms_actions'] = $GLOBALS['__cms_actions'] ?? [];
$GLOBALS['__cms_filters'] = $GLOBALS['__cms_filters'] ?? [];

function add_action(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__cms_actions'][$hook][$priority][] = $callback;
}

function do_action(string $hook, ...$args): void
{
    if (empty($GLOBALS['__cms_actions'][$hook])) return;
    ksort($GLOBALS['__cms_actions'][$hook]);
    foreach ($GLOBALS['__cms_actions'][$hook] as $cbs) {
        foreach ($cbs as $cb) { $cb(...$args); }
    }
}

function add_filter(string $hook, callable $callback, int $priority = 10): void
{
    $GLOBALS['__cms_filters'][$hook][$priority][] = $callback;
}

function apply_filters(string $hook, $value, ...$args)
{
    if (empty($GLOBALS['__cms_filters'][$hook])) return $value;
    ksort($GLOBALS['__cms_filters'][$hook]);
    foreach ($GLOBALS['__cms_filters'][$hook] as $cbs) {
        foreach ($cbs as $cb) { $value = $cb($value, ...$args); }
    }
    return $value;
}

// ---------- کشف و خواندن ماژول‌ها ----------

/** خواندن مانیفست یک ماژول */
function module_manifest(string $key): ?array
{
    $json = modules_dir() . '/' . $key . '/module.json';
    if (!is_file($json)) return null;
    $data = json_decode((string) file_get_contents($json), true);
    if (!is_array($data)) return null;
    $data['key'] = $key;
    return $data;
}

/** کشف همه ماژول‌های موجود در پوشه modules */
function modules_discover(): array
{
    $dir = modules_dir();
    $found = [];
    if (!is_dir($dir)) return $found;
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if ($entry === 'admin' || $entry === 'site') continue; // پوشه‌های سیستمی قدیم
        $full = $dir . '/' . $entry;
        if (!is_dir($full)) continue;
        $manifest = module_manifest($entry);
        if ($manifest !== null) {
            $found[$entry] = $manifest;
        }
    }
    return $found;
}

/** همگام‌سازی ماژول‌های کشف‌شده با دیتابیس */
function modules_sync(): void
{
    try {
        $discovered = modules_discover();
        $now = time();
        foreach ($discovered as $key => $m) {
            $existing = module_get($key);
            if ($existing === null) {
                // ماژول جدید
                module_add([
                    'key' => $key,
                    'name' => $m['name'] ?? $key,
                    'description' => $m['description'] ?? '',
                    'version' => $m['version'] ?? '1.0.0',
                    'category' => $m['category'] ?? '',
                    'icon' => $m['icon'] ?? '',
                ]);
            } else {
                // به‌روزرسانی نسخه اگر در مانیفست جدیدتر است
                $manifestVer = $m['version'] ?? '1.0.0';
                if (version_compare($manifestVer, (string) $existing['version'], '>')) {
                    db()->prepare("UPDATE modules SET version = ?, name = ?, description = ?, updated_at = ? WHERE module_key = ?")
                        ->execute([$manifestVer, $m['name'] ?? $existing['name'], $m['description'] ?? '', $now, $key]);
                }
            }
        }
    } catch (Throwable $e) { /* silent */ }
}

// ---------- چرخه حیات ماژول ----------

/**
 * لود فایل‌های ادمین ماژول‌های فعال (۹٫۹۹٫۲۳)
 * در admin.php بعد از تعریف CMS_ADMIN_PANEL صدا زده می‌شود.
 * فقط ماژول‌های فعال لود می‌شوند — غیرفعال‌سازی واقعاً کار می‌کند.
 */
function cms_load_active_module_admins(): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        foreach (modules_all(true) as $m) {
            $key = (string) $m['module_key'];
            if (!preg_match('/^[a-z0-9_]+$/', $key)) continue;
            $adminFile = modules_dir() . '/' . $key . '/admin.php';
            if (is_file($adminFile)) {
                try {
                    require_once $adminFile;
                } catch (Throwable $e) { /* ماژول خراب نباید پنل را بخواباند */ }
            }
        }
    } catch (Throwable $e) { /* silent */ }
}

/** آیا ماژول فعال است؟ (۹٫۹۹٫۲۳) */
function module_is_active(string $key): bool
{
    try {
        $st = db()->prepare("SELECT is_enabled FROM modules WHERE module_key = ? LIMIT 1");
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            // اگر در دیتابیس نیست، پیش‌فرض فعال است (سازگاری)
            return true;
        }
        return (int) ($row['is_enabled'] ?? 1) === 1;
    } catch (Throwable $e) {
        return true;
    }
}

/** فعال‌سازی ماژول (اجرای هوک activate) */
function module_activate(string $key): bool
{
    $manifest = module_manifest($key);
    if ($manifest === null) return false;

    // اجرای فایل اصلی ماژول برای ثبت هوک‌ها
    $mainFile = modules_dir() . '/' . $key . '/' . $key . '.php';
    if (is_file($mainFile)) {
        require_once $mainFile;
    }

    do_action("module_{$key}_activate");
    do_action('module_activate', $key);
    return module_set_enabled($key, true);
}

/** غیرفعال‌سازی ماژول (اجرای هوک deactivate) */
function module_deactivate(string $key): bool
{
    $m = module_get($key);
    if ($m === null || (int) $m['is_core'] === 1) return false;

    do_action("module_{$key}_deactivate");
    do_action('module_deactivate', $key);
    return module_set_enabled($key, false);
}

/** نصب ماژول (ساخت جدول‌ها) */
function module_install(string $key): bool
{
    $mainFile = modules_dir() . '/' . $key . '/' . $key . '.php';
    if (is_file($mainFile)) {
        require_once $mainFile;
    }
    do_action("module_{$key}_install");
    return true;
}

/** حذف نصب ماژول */
function module_uninstall(string $key): bool
{
    do_action("module_{$key}_uninstall");
    return true;
}

// ---------- بارگذاری ماژول‌های فعال ----------

/** لود فایل‌های اصلی همه ماژول‌های فعال */
function modules_load_active(): void
{
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;

    modules_sync();

    foreach (modules_all(true) as $m) {
        $key = (string) $m['module_key'];
        $mainFile = modules_dir() . '/' . $key . '/' . $key . '.php';
        if (is_file($mainFile)) {
            try {
                require_once $mainFile;
            } catch (Throwable $e) { /* ماژول خراب نباید سایت را بخواباند */ }
        }
    }
    do_action('modules_loaded');
}

/** دریافت رجیستری ماژول‌ها از گیت‌هاب */
function modules_fetch_registry(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $url = 'https://raw.githubusercontent.com/farsmd/soon/main/modules-registry.json?t=' . time();
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'user_agent' => 'LinerlightCMS/1.0']]);
    $json = @file_get_contents($url, false, $ctx);
    if ($json === false) return null;
    $data = json_decode($json, true);
    $cache = is_array($data) ? $data : null;
    return $cache;
}

/** بررسی آپدیت برای همه ماژول‌های نصب‌شده */
function modules_check_updates(): array
{
    $registry = modules_fetch_registry();
    if ($registry === null || empty($registry['modules'])) {
        return ['ok' => false, 'error' => 'دریافت رجیستری انجام نشد.', 'updates' => []];
    }
    $updates = [];
    foreach (modules_all() as $m) {
        $key = (string) $m['module_key'];
        $remote = $registry['modules'][$key] ?? null;
        if ($remote === null) continue;
        $localVer = (string) $m['version'];
        $remoteVer = (string) ($remote['version'] ?? '0');
        if (version_compare($remoteVer, $localVer, '>')) {
            $updates[$key] = [
                'name' => $m['name'],
                'local' => $localVer,
                'remote' => $remoteVer,
                'description' => $remote['description'] ?? '',
            ];
        }
    }
    return ['ok' => true, 'updates' => $updates, 'checked_at' => time()];
}

/** به‌روزرسانی تکی یک ماژول از گیت‌هاب */
function module_update_from_github(string $key): bool
{
    if (!preg_match('/^[a-z0-9_]+$/', $key)) return false;
    try {
        $modDir = modules_dir() . '/' . $key;
        if (!is_dir($modDir)) return false;

        $ctx = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => 'LinerlightCMS/1.0']]);
        $base = 'https://raw.githubusercontent.com/farsmd/soon/main/modules/' . $key . '/';

        // دانلود module.json جدید
        $jsonContent = @file_get_contents($base . 'module.json?t=' . time(), false, $ctx);
        if ($jsonContent === false) return false;
        $manifest = json_decode($jsonContent, true);
        if (!is_array($manifest)) return false;

        // فایل‌های ماژول — چندفایلی (۹٫۹۹٫۱۹)
        // فایل اصلی اجباری است، بقیه اختیاری (اگر روی گیت‌هاب نباشند رد می‌شوند)
        $files = [];
        $mainContent = @file_get_contents($base . $key . '.php?t=' . time(), false, $ctx);
        if ($mainContent === false) return false;
        $files[$key . '.php'] = $mainContent;

        foreach (['admin.php', 'frontend.php'] as $extra) {
            $c = @file_get_contents($base . $extra . '?t=' . time(), false, $ctx);
            if ($c !== false && strlen($c) > 0) {
                $files[$extra] = $c;
            }
        }
        $files['module.json'] = $jsonContent;

        // بکاپ کامل پوشه ماژول (همه فایل‌ها، نه فقط ۲ تا)
        $backupDir = $modDir . '/backup-' . date('Ymd-His');
        @mkdir($backupDir, 0775, true);
        foreach (scandir($modDir) ?: [] as $f) {
            if ($f === '.' || $f === '..' || str_starts_with($f, 'backup-')) continue;
            $srcPath = $modDir . '/' . $f;
            if (is_file($srcPath)) {
                @copy($srcPath, $backupDir . '/' . $f);
            }
        }
        // پاک‌سازی بکاپ‌های قدیمی — فقط ۳ تای آخر نگه داشته می‌شود
        $backups = [];
        foreach (scandir($modDir) ?: [] as $f) {
            if (str_starts_with($f, 'backup-') && is_dir($modDir . '/' . $f)) {
                $backups[] = $f;
            }
        }
        sort($backups);
        while (count($backups) > 3) {
            $old = array_shift($backups);
            module_rrmdir($modDir . '/' . $old);
        }

        // جایگزینی همه فایل‌های دانلودشده
        foreach ($files as $f => $content) {
            @file_put_contents($modDir . '/' . $f, $content);
        }

        // به‌روزرسانی دیتابیس
        modules_sync();

        do_action("module_{$key}_updated");
        return true;
    } catch (Throwable $e) { return false; }
}

/** حذف بازگشتی پوشه */
function module_rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = $dir . '/' . $f;
        if (is_dir($p)) module_rrmdir($p);
        else @unlink($p);
    }
    @rmdir($dir);
}
