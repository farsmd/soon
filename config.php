<?php
// config.php — اتصال دیتابیس و توابع کمکی مشترک
// همه فایل‌های این پروژه در یک فولدر کنار هم قرار دارند.

declare(strict_types=1);

define('DB_FILE', __DIR__ . '/database.sqlite');

/**
 * اتصال PDO به SQLite (فایل در همان فولدر ساخته می‌شود)
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    init_db($pdo);
    return $pdo;
}

/**
 * ساخت خودکار جدول‌ها و داده‌های پیش‌فرض
 */
function init_db(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sections (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            title         TEXT NOT NULL,
            template_file TEXT NOT NULL,
            sort_order    INTEGER NOT NULL DEFAULT 0,
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // سید تنظیمات پیش‌فرض
    $defaults = [
        'site_title'          => 'وب‌سایت من',
        'site_description'    => 'توضیح کوتاه وب‌سایت من — این متن را از پنل مدیریت تغییر دهید.',
        'admin_password_hash' => '',
    ];
    $stmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)');
    foreach ($defaults as $k => $v) {
        $stmt->execute([':key' => $k, ':value' => $v]);
    }

    // سید بخش‌های پیش‌فرض صفحه اصلی (فقط وقتی هیچ بخشی وجود ندارد)
    $count = (int) $pdo->query('SELECT COUNT(*) FROM sections')->fetchColumn();
    if ($count === 0) {
        $seed = [
            ['هدر سایت',     'template_header.php',   10],
            ['اسلایدر اصلی', 'template_slider.php',   20],
            ['ویژگی‌ها',     'template_features.php', 30],
            ['محتوای اصلی',  'template_content.php',  40],
            ['فوتر سایت',    'template_footer.php',   50],
        ];
        $ins = $pdo->prepare('INSERT INTO sections (title, template_file, sort_order, is_active) VALUES (:t, :f, :o, 1)');
        foreach ($seed as [$title, $file, $order]) {
            $ins->execute([':t' => $title, ':f' => $file, ':o' => $order]);
        }
    }
}

/** خروجی امن برای HTML */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function get_setting(string $key, string $default = ''): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE key = :key');
    $stmt->execute([':key' => $key]);
    $row = $stmt->fetch();
    return $row === false ? $default : (string) ($row['value'] ?? $default);
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (key, value) VALUES (:key, :value)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':key' => $key, ':value' => $value]);
}

/** همه تنظیمات به صورت آرایه انجمنی */
function all_settings(): array
{
    $rows = db()->query('SELECT key, value FROM settings')->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[$r['key']] = (string) $r['value'];
    }
    return $out;
}

/**
 * بخش‌های صفحه اصلی
 * @param bool $onlyActive فقط بخش‌های فعال
 */
function get_sections(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM sections';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/** نام فایل قالب معتبر است؟ فقط template_*.php با حروف/عدد/آندرلاین */
function is_valid_template_file(string $file): bool
{
    return (bool) preg_match('/^template_[A-Za-z0-9_]+\.php$/', $file);
}

/** لیست فایل‌های قالب موجود در فولدر */
function available_templates(): array
{
    $files = glob(__DIR__ . '/template_*.php') ?: [];
    $names = [];
    foreach ($files as $path) {
        $base = basename($path);
        if (is_valid_template_file($base)) {
            $names[] = $base;
        }
    }
    sort($names);
    return $names;
}

/**
 * رندر یک فایل قالب PHP و جایگزینی پلیس‌هولدرها.
 * داخل قالب این متغیرها در دسترس‌اند: $site_title ، $site_description ، $settings
 * و این پلیس‌هولدرها در خروجی جایگزین می‌شوند:
 *   {{site_title}}  {{site_description}}  {{current_year}}
 */
function render_template(string $file, array $settings): string
{
    $base = basename($file);
    if (!is_valid_template_file($base)) {
        return '';
    }
    $path = __DIR__ . '/' . $base;
    if (!is_file($path)) {
        return '<!-- قالب پیدا نشد: ' . e($base) . ' -->';
    }

    // متغیرهایی که داخل قالب‌های PHP قابل استفاده‌اند
    $site_title       = (string) ($settings['site_title'] ?? '');
    $site_description = (string) ($settings['site_description'] ?? '');

    ob_start();
    include $path;
    $output = (string) ob_get_clean();

    $replacements = [
        '{{site_title}}'       => e($site_title),
        '{{site_description}}' => e($site_description),
        '{{current_year}}'     => date('Y'),
    ];
    return strtr($output, $replacements);
}
