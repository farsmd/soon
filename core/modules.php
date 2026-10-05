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
