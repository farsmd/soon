<?php
// config.php — اتصال دیتابیس و توابع کمکی مشترک (نسخه ۶)
// همه فایل‌های این پروژه در یک فولدر کنار هم قرار دارند؛ عکس‌های آپلودی داخل فولدر uploads همان فولدر است.
// نسخه ۵: محتوای قالب‌ها و CSS سایت داخل دیتابیس نگهداری می‌شود؛ فقط اسکلت صفحه در کد باقی مانده است.
// نسخه ۶ (فاز ۲): مشتری‌ها، دسته‌بندی و کاتالوگ محصول با قیمت متری + آپشن + قیمت همکار و ماشین‌حساب قیمت.

declare(strict_types=1);

define('APP_VERSION', '6.0.0');
define('DB_FILE', __DIR__ . '/database.sqlite');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', 'uploads');

// قالب‌ها و CSS کارخانه‌ای در فایل جدا هستند تا هر فایل برای آپدیت گیت‌هاب کوچک بماند
require_once __DIR__ . '/defaults.php';

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

/** افزودن ستون فقط اگر وجود نداشته باشد (بدون پاک‌کردن داده‌های قبلی) */
function db_add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    $cols = [];
    foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $r) {
        $cols[] = (string) $r['name'];
    }
    if (!in_array($column, $cols, true)) {
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}

/**
 * ساخت خودکار جدول‌ها و داده‌های پیش‌فرض.
 * امن برای مهاجرت: روی دیتابیس نسخه ۱ هم بدون حذف پسورد/تنظیمات/بخش‌های قبلی اجرا می‌شود.
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

    // --- مهاجرت نسخه ۲: ستون‌های محتوای واقعی برای بخش‌ها ---
    db_add_column_if_missing($pdo, 'sections', 'heading',   'TEXT');
    db_add_column_if_missing($pdo, 'sections', 'body',      'TEXT');
    db_add_column_if_missing($pdo, 'sections', 'image',     'TEXT');
    db_add_column_if_missing($pdo, 'sections', 'link_url',  'TEXT');
    db_add_column_if_missing($pdo, 'sections', 'link_text', 'TEXT');

    // --- جدول صفحه‌ها (نسخه ۲) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pages (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            title           TEXT NOT NULL,
            slug            TEXT NOT NULL UNIQUE,
            content         TEXT,
            seo_title       TEXT,
            seo_description TEXT,
            is_active       INTEGER NOT NULL DEFAULT 1,
            sort_order      INTEGER NOT NULL DEFAULT 0,
            show_in_menu    INTEGER NOT NULL DEFAULT 0,
            created_at      TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- جدول پیام‌های فرم تماس (نسخه ۲) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS contact_messages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            contact    TEXT NOT NULL,
            message    TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- فاز ۲ (نسخه ۶): مشتری‌ها ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS customers (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name     TEXT NOT NULL,
            company       TEXT,
            mobile        TEXT NOT NULL,
            customer_type TEXT NOT NULL DEFAULT 'retail',
            city          TEXT,
            address       TEXT,
            notes         TEXT,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- فاز ۲ (نسخه ۶): دسته‌بندی محصولات (با زیردسته) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_categories (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT NOT NULL,
            slug        TEXT NOT NULL UNIQUE,
            parent_id   INTEGER,
            description TEXT,
            image       TEXT,
            sort_order  INTEGER NOT NULL DEFAULT 0,
            is_active   INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- فاز ۲ (نسخه ۶): محصولات (قیمت متری + قیمت همکار) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id                      INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id             INTEGER,
            name                    TEXT NOT NULL,
            sku                     TEXT,
            description             TEXT,
            image                   TEXT,
            price_per_meter         INTEGER NOT NULL DEFAULT 0,
            partner_price_per_meter INTEGER,
            is_active               INTEGER NOT NULL DEFAULT 1,
            sort_order              INTEGER NOT NULL DEFAULT 0,
            created_at              TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- فاز ۲ (نسخه ۶): تعریف ویژگی‌های محصول و گزینه‌هایشان ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_attributes (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            title      TEXT NOT NULL,
            attr_key   TEXT NOT NULL UNIQUE,
            input_type TEXT NOT NULL DEFAULT 'select',
            unit       TEXT,
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_attribute_options (
            id                     INTEGER PRIMARY KEY AUTOINCREMENT,
            attribute_id           INTEGER NOT NULL,
            title                  TEXT NOT NULL,
            price_delta_per_meter  INTEGER NOT NULL DEFAULT 0,
            sort_order             INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_attribute_values (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id   INTEGER NOT NULL,
            attribute_id INTEGER NOT NULL,
            option_id    INTEGER,
            num_value    REAL,
            text_value   TEXT
        )
    ");

    // --- جدول قالب‌های داخل دیتابیس (نسخه ۵) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_templates (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            template_key TEXT NOT NULL UNIQUE,
            title        TEXT NOT NULL,
            content      TEXT,
            is_system    INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // --- جدول تاریخچه ویرایش قالب و CSS (نسخه ۵) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS design_revisions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            item_type  TEXT NOT NULL,
            item_key   TEXT NOT NULL,
            content    TEXT,
            note       TEXT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // سید تنظیمات پیش‌فرض (INSERT OR IGNORE یعنی مقادیر فعلی سایت زنده دست نمی‌خورند)
    $defaults = [
        'site_title'          => 'وب‌سایت من',
        'site_description'    => 'توضیح کوتاه وب‌سایت من — این متن را از پنل مدیریت تغییر دهید.',
        'admin_password_hash' => '',
        'seo_title'           => '',
        'seo_description'     => '',
        // تنظیمات آپدیت یک‌کلیکی از گیت‌هاب (نسخه ۴)
        'update_repo'         => 'farsmd/soon',
        'update_branch'       => 'main',
        'update_zip_url'      => '',
        // قالب و CSS داخل دیتابیس + تنظیمات ظاهری (نسخه ۵)
        'site_css'            => '',
        'custom_css'          => '',
        'css_updated_at'      => '',
        'design_css_seeded'   => '',
        'primary_color'       => '#2563eb',
        'accent_color'        => '#0d9488',
        'site_font'           => 'system',
        'container_width'     => '1200',
        'border_radius'       => '12',
        'default_theme'       => 'light',
        'header_sticky'       => '1',
        // فاز ۲ (نسخه ۶): مشتری و کاتالوگ محصول
        'partner_discount_percent' => '10',
        'catalog_public'      => '1',
        'catalog_title'       => 'کاتالوگ محصولات',
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

    // سید یک صفحه نمونه (فقط وقتی هیچ صفحه‌ای وجود ندارد) تا منو و page.php از اول قابل مشاهده باشند
    $pcount = (int) $pdo->query('SELECT COUNT(*) FROM pages')->fetchColumn();
    if ($pcount === 0) {
        $ins = $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t, :s, :c, :st, :sd, 1, 10, 1)');
        $ins->execute([
            ':t'  => 'درباره ما',
            ':s'  => 'about',
            ':c'  => '<p>این یک صفحه نمونه است که از پنل مدیریت، بخش «صفحه‌ها» ساخته شده است. متن، عنوان سئو و نمایش در منو را از همان‌جا تغییر دهید.</p>',
            ':st' => 'درباره ما',
            ':sd' => 'صفحه درباره ما',
        ]);
    }

    // --- نسخه ۵: سید قالب‌ها و CSS داخل دیتابیس (فقط آیتم‌های غایب؛ داده کاربر دست نمی‌خورد) ---
    seed_design_if_needed($pdo);

    // --- فاز ۲ (نسخه ۶): سید دسته‌ها و ویژگی‌های پیش‌فرض + CSS کاتالوگ (فقط وقتی خالی/غایب است) ---
    seed_catalog_if_needed($pdo);
}

/** قالب‌بندی خوانای حجم فایل (B/KB/MB/GB) */
function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = (float) $bytes;
    $unit = 'B';
    foreach ($units as $unit) {
        $value /= 1024;
        if ($value < 1024) {
            break;
        }
    }
    return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
}

/** Quote امن نام جدول/ستون SQLite برای استفاده در کوئری‌های پویا */
function sqlite_identifier(string $name): string
{
    return '"' . str_replace('"', '""', $name) . '"';
}

/**
 * آمار دیتابیس فعال: وضعیت اتصال و سلامت، حجم و زمان تغییر فایل،
 * فهرست جدول‌های کاربر و تعداد ردیف هر جدول.
 */
function db_stats(?PDO $pdo = null): array
{
    $pdo = $pdo ?: db();
    $exists = is_file(DB_FILE);
    $size = $exists ? (int) filesize(DB_FILE) : 0;
    $modified = $exists ? (int) filemtime(DB_FILE) : null;
    $stats = [
        'connected'        => false,
        'integrity'        => '',
        'error'            => null,
        'path'             => DB_FILE,
        'name'             => basename(DB_FILE),
        'exists'           => $exists,
        'size'             => $size,
        'size_formatted'   => format_bytes($size),
        'modified'         => $modified,
        'modified_formatted' => $modified ? date('Y/m/d H:i:s', $modified) : '—',
        'tables'           => [],
        'table_count'      => 0,
        'total_rows'       => 0,
    ];

    try {
        $integrity = (string) $pdo->query('PRAGMA integrity_check')->fetchColumn();
        $stats['integrity'] = $integrity;
        $stats['connected'] = ($integrity === 'ok');
        if (!$stats['connected']) {
            $stats['error'] = 'بررسی سلامت دیتابیس نتیجه ok نداد: ' . $integrity;
        }

        $names = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($names as $name) {
            $table = (string) $name;
            $rows = null;
            try {
                $rows = (int) $pdo->query('SELECT COUNT(*) FROM ' . sqlite_identifier($table))->fetchColumn();
                $stats['total_rows'] += $rows;
            } catch (Throwable $ignored) {
                $rows = null;
            }
            $stats['tables'][] = ['name' => $table, 'rows' => $rows];
        }
        $stats['table_count'] = count($stats['tables']);
    } catch (Throwable $ex) {
        $stats['connected'] = false;
        $stats['error'] = $ex->getMessage();
    }

    return $stats;
}

/** مشخصات یک فایل برای نمایش در صفحه اتصال دیتابیس */
function database_file_info(string $path): array
{
    $size = is_file($path) ? (int) filesize($path) : 0;
    $modified = is_file($path) ? (int) filemtime($path) : null;
    $activePath = is_file(DB_FILE) ? realpath(DB_FILE) : false;
    $thisPath = is_file($path) ? realpath($path) : false;

    return [
        'name' => basename($path),
        'path' => $path,
        'size' => $size,
        'size_formatted' => format_bytes($size),
        'modified' => $modified,
        'modified_formatted' => $modified ? date('Y/m/d H:i:s', $modified) : '—',
        'is_active' => ($activePath !== false && $thisPath !== false && $activePath === $thisPath),
    ];
}

/** فایل‌های دیتابیس موجود در فولدر برنامه؛ فایل فعال اول فهرست می‌آید */
function db_files(): array
{
    $paths = [];
    foreach (['*.sqlite', '*.sqlite3', '*.db'] as $pattern) {
        foreach (glob(__DIR__ . '/' . $pattern) ?: [] as $path) {
            if (is_file($path)) {
                $paths[$path] = true;
            }
        }
    }

    $files = [];
    foreach (array_keys($paths) as $path) {
        // فایل‌های موقت ساخت بکاپ/بازیابی که در پایان درخواست پاک می‌شوند نمایش داده نمی‌شوند.
        if (preg_match('/^(backup-|restore-|database-switch-|database-upload-)/', basename($path))) {
            continue;
        }
        $files[] = database_file_info($path);
    }
    usort($files, static function (array $a, array $b): int {
        if ($a['is_active'] !== $b['is_active']) {
            return $a['is_active'] ? -1 : 1;
        }
        return strcmp((string) $a['name'], (string) $b['name']);
    });
    return $files;
}

/**
 * اعتبارسنجی فایل دیتابیس این سیستم قبل از هر جایگزینی:
 * هدر SQLite، سلامت کامل فایل و وجود جدول settings باید تأیید شود.
 */
function validate_database_file(string $path): array
{
    if (!is_file($path)) {
        return ['ok' => false, 'error' => 'فایل دیتابیس پیدا نشد.'];
    }
    if ((int) filesize($path) <= 0) {
        return ['ok' => false, 'error' => 'فایل دیتابیس خالی است.'];
    }
    $header = @file_get_contents($path, false, null, 0, 16);
    if ($header !== "SQLite format 3\0") {
        return ['ok' => false, 'error' => 'فایل انتخاب‌شده فرمت SQLite ندارد.'];
    }

    try {
        $test = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $integrity = $test->query('PRAGMA integrity_check')->fetchAll(PDO::FETCH_COLUMN);
        if ($integrity === [] || (string) $integrity[0] !== 'ok') {
            $test = null;
            return ['ok' => false, 'error' => 'بررسی سلامت فایل دیتابیس موفق نبود؛ فایل آسیب‌دیده است.'];
        }
        $stmt = $test->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'settings'");
        $hasSettings = $stmt !== false && $stmt->fetch() !== false;
        $test = null;
        if (!$hasSettings) {
            return ['ok' => false, 'error' => 'این فایل دیتابیس این سیستم نیست؛ جدول settings در آن پیدا نشد.'];
        }
    } catch (Throwable $ex) {
        return ['ok' => false, 'error' => 'باز کردن یا اعتبارسنجی فایل دیتابیس انجام نشد: ' . $ex->getMessage()];
    }

    return ['ok' => true, 'error' => null];
}

/**
 * فعال‌کردن امن یک فایل دیتابیس دیگر به‌عنوان database.sqlite.
 * ابتدا فایل مقصد روی یک نسخه موقت اعتبارسنجی می‌شود، سپس از دیتابیس فعلی
 * نسخه امن database-backup-before-switch.sqlite ساخته و نسخه موقت جایگزین می‌شود.
 */
function activate_database_file(string $source): array
{
    if (!is_file($source)) {
        return ['ok' => false, 'error' => 'فایل دیتابیس انتخاب‌شده پیدا نشد.', 'safety_copy' => null];
    }
    if (is_file(DB_FILE) && realpath($source) === realpath(DB_FILE)) {
        return ['ok' => false, 'error' => 'این فایل همین حالا دیتابیس فعال است.', 'safety_copy' => null];
    }

    $stage = __DIR__ . '/database-switch-' . bin2hex(random_bytes(6)) . '.sqlite';
    if (!@copy($source, $stage)) {
        return ['ok' => false, 'error' => 'کپی‌کردن فایل دیتابیس برای بررسی انجام نشد.', 'safety_copy' => null];
    }

    $validation = validate_database_file($stage);
    if (!$validation['ok']) {
        @unlink($stage);
        return ['ok' => false, 'error' => (string) $validation['error'], 'safety_copy' => null];
    }

    $safety = __DIR__ . '/database-backup-before-switch.sqlite';
    if (is_file(DB_FILE) && !@copy(DB_FILE, $safety)) {
        @unlink($stage);
        return ['ok' => false, 'error' => 'ساخت نسخه امن از دیتابیس فعلی انجام نشد؛ تعویض متوقف شد.', 'safety_copy' => null];
    }

    if (!@rename($stage, DB_FILE)) {
        @unlink($stage);
        return ['ok' => false, 'error' => 'جایگزینی فایل دیتابیس انجام نشد؛ مجوز نوشتن فولدر برنامه را بررسی کنید.', 'safety_copy' => basename($safety)];
    }

    return ['ok' => true, 'error' => null, 'safety_copy' => basename($safety)];
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

/** صفحه‌ها */
function get_pages(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM pages';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_page_by_slug(string $slug, bool $onlyActive = false): ?array
{
    $sql = 'SELECT * FROM pages WHERE slug = :s';
    if ($onlyActive) {
        $sql .= ' AND is_active = 1';
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function is_valid_slug(string $slug): bool
{
    return (bool) preg_match('/^[A-Za-z0-9\-_]+$/', $slug);
}

/**
 * آیتم‌های منوی سایت: همیشه لینک خانه + صفحه‌هایی که show_in_menu دارند.
 */
function menu_items(): array
{
    $items = [['title' => 'خانه', 'url' => 'index.php']];
    // فاز ۲: لینک کاتالوگ محصولات وقتی نمایش عمومی کاتالوگ فعال است
    if (get_setting('catalog_public', '1') === '1') {
        $items[] = ['title' => get_setting('catalog_title', 'کاتالوگ محصولات'), 'url' => 'products.php'];
    }
    foreach (get_pages(true) as $p) {
        if ((int) ($p['show_in_menu'] ?? 0) === 1) {
            $items[] = [
                'title' => (string) $p['title'],
                'url'   => 'page.php?slug=' . urlencode((string) $p['slug']),
            ];
        }
    }
    return $items;
}

/**
 * HTML منوی سایت (برای پلیس‌هولدر {{menu}}) + دکمه تغییر تم.
 * در موبایل با دکمه همبرگری (aria-expanded/aria-controls) باز و بسته می‌شود؛ اسکریپتش در اسکلت صفحه است.
 */
function menu_html(string $class = 'main-nav'): string
{
    static $cache = [];
    if (isset($cache[$class])) {
        return $cache[$class];
    }
    $html = '<nav class="' . e($class) . '" aria-label="منوی اصلی">';
    $html .= '<button type="button" class="nav-toggle" aria-expanded="false" aria-controls="nav-list" aria-label="نمایش یا پنهان کردن منو"><span aria-hidden="true">☰</span></button>';
    $html .= '<div class="nav-list" id="nav-list">';
    foreach (menu_items() as $item) {
        $html .= '<a href="' . e($item['url']) . '">' . e($item['title']) . '</a>';
    }
    $html .= '<button type="button" class="theme-toggle" id="theme-toggle" title="تغییر تم روشن/تیره" aria-label="تغییر تم روشن یا تیره">🌓</button>';
    $html .= '</div></nav>';
    $cache[$class] = $html;
    return $html;
}

/** مسیر عمومی عکس یک بخش (یا رشته خالی) */
function section_image_url(array $section): string
{
    $img = trim((string) ($section['image'] ?? ''));
    if ($img === '') {
        return '';
    }
    return UPLOADS_URL . '/' . basename($img);
}

/**
 * اعتبارسنجی و ذخیره عکس آپلودی یک بخش.
 * خروجی: ['ok' => bool, 'filename' => ?string, 'error' => ?string]
 */
function handle_section_image_upload(?array $file, ?string $oldImage = null): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'filename' => $oldImage, 'error' => null];
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'filename' => null, 'error' => 'آپلود عکس انجام نشد (کد خطا: ' . (int) ($file['error'] ?? -1) . ').'];
    }
    $maxBytes = 3 * 1024 * 1024; // حدود ۳ مگابایت
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'filename' => null, 'error' => 'حجم عکس باید کمتر از ۳ مگابایت باشد.'];
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'filename' => null, 'error' => 'فرمت عکس مجاز نیست؛ فقط jpg، png، webp و gif.'];
    }
    // بررسی میم واقعی فایل
    $mime = null;
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, (string) $file['tmp_name']);
            finfo_close($finfo);
        }
    } elseif (function_exists('mime_content_type')) {
        $mime = mime_content_type((string) $file['tmp_name']);
    }
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if ($mime !== null && !in_array($mime, $allowedMime, true)) {
        return ['ok' => false, 'filename' => null, 'error' => 'فایل انتخاب‌شده عکس معتبر نیست.'];
    }
    if (!is_dir(UPLOADS_DIR)) {
        @mkdir(UPLOADS_DIR, 0775, true);
    }
    $name = 'img_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
    $dest = UPLOADS_DIR . '/' . $name;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['ok' => false, 'filename' => null, 'error' => 'ذخیره عکس روی سرور انجام نشد؛ مجوز نوشتن فولدر uploads را بررسی کنید.'];
    }
    // حذف عکس قبلی (برای تمیز ماندن فولدر)
    if ($oldImage !== null && $oldImage !== '' && $oldImage !== $name) {
        $oldPath = UPLOADS_DIR . '/' . basename($oldImage);
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }
    return ['ok' => true, 'filename' => $name, 'error' => null];
}

/** ذخیره پیام فرم تماس */
function save_contact_message(string $name, string $contact, string $message): void
{
    $stmt = db()->prepare('INSERT INTO contact_messages (name, contact, message) VALUES (:n, :c, :m)');
    $stmt->execute([':n' => $name, ':c' => $contact, ':m' => $message]);
}

function get_contact_messages(): array
{
    return db()->query('SELECT * FROM contact_messages ORDER BY id DESC')->fetchAll();
}

/** نام فایل قالب معتبر است؟ فقط template_*.php با حروف/عدد/آندرلاین (برای فایل‌های قدیمی و لیست سفید پاک‌سازی) */
function is_valid_template_file(string $file): bool
{
    return (bool) preg_match('/^template_[A-Za-z0-9_]+\.php$/', $file);
}

/** تبدیل نام فایل قالب قدیمی به کلید قالب دیتابیس: template_header.php ← header */
function template_key_from_file(string $file): string
{
    $base = basename(trim($file));
    if (preg_match('/^template_([A-Za-z0-9_]+)\.php$/', $base, $m)) {
        return $m[1];
    }
    if (preg_match('/^[A-Za-z0-9_]+$/', $base)) {
        return $base;
    }
    return 'content';
}

/** نام فایلِ متناظر با یک کلید قالب (برای ستون template_file بخش‌ها که به شکل قدیمی نگه داشته می‌شود) */
function template_file_from_key(string $key): string
{
    return 'template_' . $key . '.php';
}

/** کلید قالب یک بخش از روی ستون template_file */
function section_template_key(array $section): string
{
    return template_key_from_file((string) ($section['template_file'] ?? ''));
}

/**
 * فهرست نام‌فایل‌های قالب قابل انتخاب برای بخش‌ها (به شکل قدیمی template_x.php)،
 * ولی منبع واقعی آن قالب‌های داخل دیتابیس است — هیچ فایلی خوانده یا اجرا نمی‌شود.
 */
function available_templates(): array
{
    $names = [];
    foreach (all_templates() as $row) {
        $names[] = template_file_from_key((string) $row['template_key']);
    }
    return $names;
}

// =============================================================
// نسخه ۵: قالب‌ها و CSS داخل دیتابیس + موتور قالب امن
// فقط اسکلت سند HTML در کد می‌ماند؛ هیچ کدی از دیتابیس اجرا نمی‌شود.
// =============================================================

// تابع factory_templates() در فایل defaults.php است (بالای همین فایل require می‌شود).

/** محتوای کارخانه‌ای یک قالب (یا null) */
function factory_template_content(string $key): ?string
{
    $all = factory_templates();
    return isset($all[$key]) ? (string) $all[$key]['content'] : null;
}

/** متن آغازین قالب‌های سفارشی تازه */
function custom_template_starter(): string
{
    return <<<'HTML'
<section class="custom-section">
    <div class="container">
        <h2>{{section_heading}}</h2>
        {{#if section_image}}<p class="section-image"><img src="{{section_image_url}}" alt="{{section_heading}}" loading="lazy" decoding="async"></p>{{/if}}
        {{#if section_body}}<div class="section-body">{{section_body}}</div>{{/if}}
        {{#if section_link_url}}{{#if section_link_text}}<p><a class="btn" href="{{section_link_url}}">{{section_link_text}}</a></p>{{/if}}{{/if}}
    </div>
</section>
HTML;
}

/** هش SHA1 فایل‌های پیش‌فرض نسخه ۴ (برای تشخیص فایل دست‌نخورده هنگام مهاجرت به نسخه ۵) */
function legacy_bundled_hashes(): array
{
    return [
        'template_header.php'   => '2af1955db7ba96d2aca1d93355b1b729f2cfb66d',
        'template_slider.php'   => 'be0f3ab58cc2488d1d200250889dae52527a7796',
        'template_features.php' => 'aa3c782b8680a432e893bdac0b570415501501b1',
        'template_content.php'  => '63e156b68d6ab1dc7087221fb3446d1e4535dbc9',
        'template_footer.php'   => '8b515828e6b6d272a3660a41f7159abf63b9f3a3',
        'template_contact.php'  => '42014cda483eabf4a7f121bb1798ae0b2709dbec',
        'template_single.php'   => '3bb254a4e41540dfed88ba3f5ee31db846373065',
        'style.css'             => 'a2b15723e69dd6d05d2c16964404a69aa9352ce3',
    ];
}

/** ذخیره یک نسخه قبلی در تاریخچه طراحی (قبل از هر ذخیره/بازیابی/بازنشانی) */
function archive_design_revision(PDO $pdo, string $type, string $key, string $content, string $note): void
{
    $stmt = $pdo->prepare('INSERT INTO design_revisions (item_type, item_key, content, note) VALUES (:t, :k, :c, :n)');
    $stmt->execute([':t' => $type, ':k' => $key, ':c' => $content, ':n' => $note]);
}

/**
 * سید قالب‌ها و CSS نسخه ۵ داخل دیتابیس — فقط آیتم‌های غایب.
 * قالب PHP قدیمیِ دست‌نخورده (مثل نسخه ۴) با قالب کارخانه‌ای امن جایگزین می‌شود؛
 * قالب PHP سفارشی هرگز اجرا نمی‌شود: سورسش در design_revisions آرشیو می‌شود تا گم نشود.
 * CSS قدیمی سفارشی هم به‌عنوان custom_css روی پایه تازه سوار می‌شود و آرشیو می‌گردد.
 */
function seed_design_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $readSetting = static function (string $key) use ($pdo): string {
        $st = $pdo->prepare('SELECT value FROM settings WHERE key = :k');
        $st->execute([':k' => $key]);
        $row = $st->fetch();
        return $row === false ? '' : (string) ($row['value'] ?? '');
    };
    $writeSetting = static function (string $key, string $value) use ($pdo): void {
        $st = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $st->execute([':k' => $key, ':v' => $value]);
    };

    $existing = [];
    foreach ($pdo->query('SELECT template_key FROM site_templates')->fetchAll(PDO::FETCH_COLUMN) as $k) {
        $existing[(string) $k] = true;
    }
    $hashes = legacy_bundled_hashes();
    $ins = $pdo->prepare('INSERT OR IGNORE INTO site_templates (template_key, title, content, is_system) VALUES (:k, :t, :c, :s)');

    foreach (factory_templates() as $key => $def) {
        if (isset($existing[$key])) {
            continue;
        }
        $legacyFile = __DIR__ . '/template_' . $key . '.php';
        if (is_file($legacyFile)) {
            $src = (string) file_get_contents($legacyFile);
            $bundled = (string) ($hashes['template_' . $key . '.php'] ?? '');
            if ($bundled === '' || !hash_equals($bundled, sha1($src))) {
                archive_design_revision($pdo, 'template', $key, $src, 'legacy-php-archive: template_' . $key . '.php — قالب PHP سفارشی نسخه ۴؛ هنگام مهاجرت به نسخه ۵ آرشیو شد و هرگز اجرا نمی‌شود.');
            }
        }
        $ins->execute([':k' => $key, ':t' => (string) $def['title'], ':c' => (string) $def['content'], ':s' => 1]);
        $existing[$key] = true;
    }

    // قالب‌های فایلی سفارشی نسخه‌های قبل (با کلیدهای دیگر): آرشیو سورس + ساخت قالب امن جایگزین
    foreach (glob(__DIR__ . '/template_*.php') ?: [] as $path) {
        $base = basename($path);
        if (!is_valid_template_file($base)) {
            continue;
        }
        $key = template_key_from_file($base);
        if (isset($existing[$key])) {
            continue;
        }
        $src = (string) file_get_contents($path);
        archive_design_revision($pdo, 'template', $key, $src, 'legacy-php-archive: ' . $base . ' — قالب PHP سفارشی نسخه ۴؛ هنگام مهاجرت به نسخه ۵ آرشیو شد و هرگز اجرا نمی‌شود.');
        $ins->execute([':k' => $key, ':t' => 'قالب قدیمی «' . $key . '»', ':c' => custom_template_starter(), ':s' => 0]);
        $existing[$key] = true;
    }

    // CSS: فقط یک بار در اولین اجرای نسخه ۵ سید می‌شود تا پاک‌کردن عمدی CSS توسط مدیر بعداً برنگردد.
    if ($readSetting('design_css_seeded') !== '1') {
        if (trim($readSetting('site_css')) === '') {
            $legacyCss = __DIR__ . '/style.css';
            if (is_file($legacyCss)) {
                $src = (string) file_get_contents($legacyCss);
                $writeSetting('site_css', default_site_css());
                if (!hash_equals((string) ($hashes['style.css'] ?? ''), sha1($src))) {
                    if (trim($readSetting('custom_css')) === '') {
                        $writeSetting('custom_css', $src);
                    }
                    archive_design_revision($pdo, 'css', 'custom_css', $src, 'legacy-css-archive: style.css — استایل سفارشی نسخه ۴؛ هنگام مهاجرت به نسخه ۵ در CSS سفارشی حفظ و آرشیو شد.');
                }
            } else {
                $writeSetting('site_css', default_site_css());
            }
            if ($readSetting('css_updated_at') === '') {
                $writeSetting('css_updated_at', date('Y-m-d H:i:s'));
            }
        }
        $writeSetting('design_css_seeded', '1');
    }
}

/** همه قالب‌های دیتابیس به ترتیب ساخت (کارخانه‌ای‌ها اول سید شده‌اند) */
function all_templates(): array
{
    return db()->query('SELECT * FROM site_templates ORDER BY id ASC')->fetchAll();
}

/** یک قالب دیتابیس با کلید */
function get_template_row(string $key): ?array
{
    $stmt = db()->prepare('SELECT * FROM site_templates WHERE template_key = :k');
    $stmt->execute([':k' => $key]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** محتوای فعال یک قالب: ردیف دیتابیس، وگرنه کارخانه‌ای، وگرنه قالب content */
function get_template_content(string $key): string
{
    $row = get_template_row($key);
    if ($row !== null) {
        return (string) ($row['content'] ?? '');
    }
    $factory = factory_template_content($key);
    if ($factory !== null) {
        return $factory;
    }
    return (string) (factory_template_content('content') ?? '');
}

/** ذخیره محتوای یک قالب با آرشیو نسخه قبلی در تاریخچه */
function save_template_content(string $key, string $content, string $note = 'قبل از ذخیره ویرایش قالب'): bool
{
    $row = get_template_row($key);
    if ($row === null) {
        return false;
    }
    archive_design_revision(db(), 'template', $key, (string) ($row['content'] ?? ''), $note);
    $stmt = db()->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $stmt->execute([':c' => $content, ':k' => $key]);
    return true;
}

/** ساخت قالب سفارشی تازه در دیتابیس */
function create_template(string $key, string $title): bool
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $key) || get_template_row($key) !== null) {
        return false;
    }
    $stmt = db()->prepare('INSERT INTO site_templates (template_key, title, content, is_system) VALUES (:k, :t, :c, 0)');
    $stmt->execute([':k' => $key, ':t' => $title !== '' ? $title : $key, ':c' => custom_template_starter()]);
    return true;
}

/** حذف قالب سفارشی (فقط غیرسیستمی و بلااستفاده در بخش‌ها) */
function delete_template(string $key): bool
{
    $row = get_template_row($key);
    if ($row === null || (int) ($row['is_system'] ?? 0) === 1) {
        return false;
    }
    foreach (get_sections(false) as $s) {
        if (section_template_key($s) === $key) {
            return false;
        }
    }
    archive_design_revision(db(), 'template', $key, (string) ($row['content'] ?? ''), 'قبل از حذف قالب');
    db()->prepare('DELETE FROM site_templates WHERE template_key = :k')->execute([':k' => $key]);
    return true;
}

/** بازنشانی یک قالب به محتوای کارخانه‌ای (با آرشیو نسخه فعلی) */
function reset_template_to_factory(string $key): bool
{
    $factory = factory_template_content($key);
    if ($factory === null || get_template_row($key) === null) {
        return false;
    }
    return save_template_content($key, $factory, 'قبل از بازنشانی به قالب کارخانه‌ای');
}

/** فهرست نسخه‌های قبلی یک آیتم طراحی */
function design_revisions_for(string $type, string $key, int $limit = 20): array
{
    $stmt = db()->prepare('SELECT * FROM design_revisions WHERE item_type = :t AND item_key = :k ORDER BY id DESC LIMIT ' . max(1, $limit));
    $stmt->execute([':t' => $type, ':k' => $key]);
    return $stmt->fetchAll();
}

/** نسخه‌های آرشیوشده مهاجرت از فایل‌های قدیمی (هشدار در پنل) */
function legacy_archived_revisions(string $type): array
{
    $stmt = db()->prepare('SELECT * FROM design_revisions WHERE item_type = :t AND note LIKE :n ORDER BY id DESC LIMIT 30');
    $stmt->execute([':t' => $type, ':n' => 'legacy-%']);
    return $stmt->fetchAll();
}

/** بازیابی یک نسخه قبلی از تاریخچه (نسخه فعلی هم قبلش آرشیو می‌شود) */
function restore_design_revision(int $id): bool
{
    $stmt = db()->prepare('SELECT * FROM design_revisions WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $rev = $stmt->fetch();
    if ($rev === false) {
        return false;
    }
    $type = (string) $rev['item_type'];
    $key  = (string) $rev['item_key'];
    $content = (string) ($rev['content'] ?? '');
    if ($type === 'template') {
        return save_template_content($key, $content, 'قبل از بازیابی از تاریخچه');
    }
    if ($type === 'css' || $type === 'custom_css') {
        return save_css_content($key === 'custom_css' || $type === 'custom_css' ? 'custom_css' : 'site_css', $content, 'قبل از بازیابی از تاریخچه');
    }
    return false;
}

/** ذخیره CSS اصلی یا سفارشی با آرشیو نسخه قبلی و تازه‌سازی زمان CSS */
function save_css_content(string $which, string $content, string $note = 'قبل از ذخیره ویرایش CSS'): bool
{
    if (!in_array($which, ['site_css', 'custom_css'], true)) {
        return false;
    }
    archive_design_revision(db(), 'css', $which, get_setting($which, ''), $note);
    set_setting($which, $content);
    bump_css_updated();
    return true;
}

/** بازنشانی CSS اصلی به پیش‌فرض کارخانه یا خالی‌کردن CSS سفارشی (با آرشیو نسخه فعلی) */
function reset_css_content(string $which): bool
{
    if ($which === 'site_css') {
        return save_css_content('site_css', default_site_css(), 'قبل از بازنشانی CSS به پیش‌فرض کارخانه');
    }
    if ($which === 'custom_css') {
        return save_css_content('custom_css', '', 'قبل از خالی‌کردن CSS سفارشی');
    }
    return false;
}

/** تازه‌سازی مهر زمانی CSS (برای شکستن کش style.php) */
function bump_css_updated(): void
{
    set_setting('css_updated_at', date('Y-m-d H:i:s'));
}

/**
 * فایل‌های قدیمی قابل حذف امن: فایل‌های template_*.php که کلیدشان در دیتابیس سید شده
 * و style.css وقتی CSS داخل دیتابیس نشسته است. فقط همین لیست سفید حذف می‌شود.
 */
function legacy_cleanup_candidates(): array
{
    $out = [];
    $keys = [];
    foreach (all_templates() as $row) {
        $keys[(string) $row['template_key']] = true;
    }
    foreach (glob(__DIR__ . '/template_*.php') ?: [] as $path) {
        $base = basename($path);
        if (is_valid_template_file($base) && isset($keys[template_key_from_file($base)])) {
            $out[] = $base;
        }
    }
    if (is_file(__DIR__ . '/style.css') && trim(get_setting('site_css', '')) !== '') {
        $out[] = 'style.css';
    }
    sort($out);
    return $out;
}

// ---------- موتور قالب امن ----------

/** کلیدهایی که مقدارشان HTML خامِ داخلی و مطمئن است (بدون escape) */
function tpl_raw_keys(): array
{
    return ['menu', 'section_body', 'page_content', 'contact_form', 'slider_slides',
        // فاز ۲: HTML داخلی ساخته‌شده در کد برای کاتالوگ و محصول (هرگز از ورودی کاربر ساخته نمی‌شود)
        'categories_nav', 'products_grid', 'product_specs', 'attributes_options', 'estimator'];
}

/** پارس بازگشتی توکن‌های قالب به درخت گره‌ها (متن / متغیر / شرط) */
function tpl_parse_nodes(array $tokens, int &$i, array $stopAt): array
{
    $nodes = [];
    $count = count($tokens);
    while ($i < $count) {
        $tok = (string) $tokens[$i];
        if (preg_match('/^\{\{\s*else\s*\}\}$/', $tok)) {
            if (in_array('else', $stopAt, true)) {
                return $nodes;
            }
            $i++;
            continue;
        }
        if (preg_match('/^\{\{\s*\/if\s*\}\}$/', $tok)) {
            if (in_array('/if', $stopAt, true)) {
                return $nodes;
            }
            $i++;
            continue;
        }
        if (preg_match('/^\{\{\s*#if\s+([A-Za-z0-9_]+)\s*\}\}$/', $tok, $m)) {
            $key = $m[1];
            $i++;
            $then = tpl_parse_nodes($tokens, $i, ['else', '/if']);
            $else = [];
            if ($i < $count && preg_match('/^\{\{\s*else\s*\}\}$/', (string) $tokens[$i])) {
                $i++;
                $else = tpl_parse_nodes($tokens, $i, ['/if']);
            }
            if ($i < $count && preg_match('/^\{\{\s*\/if\s*\}\}$/', (string) $tokens[$i])) {
                $i++;
            }
            $nodes[] = ['if', $key, $then, $else];
            continue;
        }
        if (preg_match('/^\{\{\s*([A-Za-z0-9_]+)\s*\}\}$/', $tok, $m)) {
            $nodes[] = ['var', $m[1]];
            $i++;
            continue;
        }
        $nodes[] = ['text', $tok];
        $i++;
    }
    return $nodes;
}

/** رندر درخت گره‌ها با context (مقادیر عادی escape می‌شوند؛ کلیدهای خام فقط داخلی‌اند) */
function tpl_render_nodes(array $nodes, array $ctx, array $rawKeys): string
{
    $out = '';
    foreach ($nodes as $node) {
        if ($node[0] === 'text') {
            $out .= $node[1];
        } elseif ($node[0] === 'var') {
            $key = $node[1];
            $val = array_key_exists($key, $ctx) ? (string) $ctx[$key] : '';
            $out .= in_array($key, $rawKeys, true) ? $val : e($val);
        } elseif ($node[0] === 'if') {
            $val = $ctx[$node[1]] ?? '';
            $truthy = !($val === '' || $val === '0' || $val === false || $val === null);
            $out .= tpl_render_nodes($truthy ? $node[2] : $node[3], $ctx, $rawKeys);
        }
    }
    return $out;
}

/**
 * رندر امن متن قالب دیتابیس: فقط {{key}} و {{#if key}}...{{else}}...{{/if}}.
 * هیچ eval/include از محتوای دیتابیس انجام نمی‌شود؛ کد PHP داخل قالب کاملاً خنثی است.
 */
function tpl_render(string $tpl, array $ctx): string
{
    $tokens = preg_split(
        '/(\{\{\s*#if\s+[A-Za-z0-9_]+\s*\}\}|\{\{\s*else\s*\}\}|\{\{\s*\/if\s*\}\}|\{\{\s*[A-Za-z0-9_]+\s*\}\})/',
        $tpl,
        -1,
        PREG_SPLIT_DELIM_CAPTURE
    );
    if ($tokens === false) {
        return '';
    }
    $i = 0;
    $nodes = tpl_parse_nodes($tokens, $i, []);
    return tpl_render_nodes($nodes, $ctx, tpl_raw_keys());
}

/**
 * ساخت context رندر یک قالب: مقادیر بخش/صفحه/سایت با پس‌روی‌های نسخه ۴
 * (تیتر خالی اسلایدر ← عنوان سایت، متن خالی ویژگی‌ها ← کارت‌های پیش‌فرض در خود قالب و...)
 */
function template_context(string $key, array $settings, ?array $section = null, ?array $page = null, array $extra = []): array
{
    $siteTitle = (string) ($settings['site_title'] ?? '');
    $siteDesc  = (string) ($settings['site_description'] ?? '');
    $heading   = trim((string) ($section['heading'] ?? ''));
    $body      = (string) ($section['body'] ?? '');
    $linkUrl   = trim((string) ($section['link_url'] ?? ''));
    $linkText  = trim((string) ($section['link_text'] ?? ''));

    if ($key === 'slider') {
        if ($heading === '') {
            $heading = $siteTitle;
        }
        if (trim($body) === '') {
            $body = $siteDesc;
        }
        if ($linkText === '') {
            $linkText = 'شروع کنید';
        }
    } elseif ($key === 'features') {
        if ($heading === '') {
            $heading = 'ویژگی‌ها';
        }
    } elseif ($key === 'content') {
        if ($heading === '') {
            $heading = 'درباره ' . $siteTitle;
        }
    } elseif ($key === 'contact') {
        if ($heading === '') {
            $heading = 'تماس با ما';
        }
    }

    $pageTitle = '';
    $pageContent = '';
    if ($page !== null) {
        $pageTitle = (string) ($page['title'] ?? '');
        $pageContent = (string) ($page['content'] ?? '');
    } elseif ($section !== null) {
        $pageTitle = $heading !== '' ? $heading : (string) ($section['title'] ?? '');
        $pageContent = $body;
    }

    $ctx = [
        'site_title'        => $siteTitle,
        'site_description'  => $siteDesc,
        'seo_title'         => (string) ($settings['seo_title'] ?? '') !== '' ? (string) $settings['seo_title'] : $siteTitle,
        'seo_description'   => (string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : $siteDesc,
        'current_year'      => date('Y'),
        'menu'              => menu_html(),
        'section_title'     => (string) ($section['title'] ?? ''),
        'section_heading'   => $heading,
        'section_body'      => $body,
        'section_image'     => $section !== null ? basename((string) ($section['image'] ?? '')) : '',
        'section_image_url' => $section !== null ? section_image_url($section) : '',
        'section_link_url'  => $linkUrl,
        'section_link_text' => $linkText,
        'page_title'        => $pageTitle,
        'page_content'      => $pageContent,
        'contact_form'      => '',
        'slider_slides'     => '',
    ];
    if ($key === 'contact' && !array_key_exists('contact_form', $extra)) {
        $ctx['contact_form'] = contact_form_html(contact_state());
    }
    if (!array_key_exists('slider_slides', $extra)) {
        $ctx['slider_slides'] = slider_slides_html();
    }
    foreach ($extra as $k => $v) {
        $ctx[$k] = $v;
    }
    return $ctx;
}

/** رندر یک قالب دیتابیس با کلید (بدون هیچ فایل یا اجرای کد) */
function render_db_template(string $key, array $settings, ?array $section = null, ?array $page = null, array $extra = []): string
{
    return tpl_render(get_template_content($key), template_context($key, $settings, $section, $page, $extra));
}

// ---------- فرم تماس (منطق در کد، ظاهر در قالب دیتابیس) ----------

/** وضعیت فرم تماس در همین درخواست (نتیجه پردازش ارسال) */
function contact_state(?array $set = null): array
{
    static $state = ['submitted' => false, 'ok' => false, 'msg' => '', 'err' => ''];
    if ($set !== null) {
        $state = $set;
    }
    return $state;
}

/**
 * پردازش ارسال فرم تماس — باید قبل از هر خروجی و بعد از شروع سشن صدا زده شود.
 * CSRF بر پایه سشن + فیلد مخفی ضدربات (honeypot) + بررسی زمان ارسال (مثل نسخه ۴).
 */
function process_contact_form(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['contact_form'] ?? '') !== '1') {
        return;
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    $tokenOk = hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
    $honeyOk = trim((string) ($_POST['website'] ?? '')) === '';
    $started = (int) ($_SESSION['contact_form_time'] ?? 0);
    $timeOk  = $started > 0 && (time() - $started) >= 2;
    $name    = trim((string) ($_POST['c_name'] ?? ''));
    $contact = trim((string) ($_POST['c_contact'] ?? ''));
    $message = trim((string) ($_POST['c_message'] ?? ''));

    if (!$tokenOk) {
        contact_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'درخواست نامعتبر است؛ صفحه را تازه کنید و دوباره تلاش کنید.']);
    } elseif (!$honeyOk || !$timeOk) {
        contact_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'ارسال انجام نشد؛ چند ثانیه صبر کنید و دوباره تلاش کنید.']);
    } elseif ($name === '' || $contact === '' || $message === '') {
        contact_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'نام، راه تماس و متن پیام را کامل کنید.']);
    } elseif (strlen($message) < 3) {
        contact_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'متن پیام خیلی کوتاه است.']);
    } else {
        save_contact_message($name, $contact, $message);
        $_SESSION['contact_form_time'] = time();
        contact_state(['submitted' => true, 'ok' => true, 'msg' => 'پیام شما ثبت شد. ممنون از تماس شما.', 'err' => '']);
    }
}

/** HTML داخلی فرم تماس (با پیام نتیجه) — به‌صورت پلیس‌هولدر خام {{contact_form}} در قالب نشست می‌کند */
function contact_form_html(array $state): string
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        if (empty($state['submitted'])) {
            $_SESSION['contact_form_time'] = time();
        }
    }
    $html = '';
    if ((string) ($state['msg'] ?? '') !== '') {
        $html .= '<div class="alert ok" role="status">' . e($state['msg']) . '</div>';
    }
    if ((string) ($state['err'] ?? '') !== '') {
        $html .= '<div class="alert error" role="alert">' . e($state['err']) . '</div>';
    }
    $action = (string) ($_SERVER['PHP_SELF'] ?? 'index.php');
    $html .= '<form method="post" class="contact-form" action="' . e($action) . '#contact">';
    $html .= '<input type="hidden" name="contact_form" value="1">';
    $html .= '<input type="hidden" name="csrf" value="' . e((string) ($_SESSION['csrf'] ?? '')) . '">';
    $html .= '<div class="hp-field" aria-hidden="true"><label>وب‌سایت<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
    $html .= '<label>نام شما<input type="text" name="c_name" required maxlength="120" autocomplete="name"></label>';
    $html .= '<label>ایمیل یا شماره تماس<input type="text" name="c_contact" required maxlength="120" autocomplete="email"></label>';
    $html .= '<label>پیام<textarea name="c_message" rows="5" required maxlength="4000"></textarea></label>';
    $html .= '<button type="submit" class="btn">ارسال پیام</button>';
    $html .= '</form>';
    return $html;
}

// ---------- اسلایدهای ساخته‌شده از بخش‌های عکس‌دار ----------

/** اسلایدهای کاروسل از بخش‌های فعال عکس‌دار (به‌جز هدر/فوتر) برای پلیس‌هولدر {{slider_slides}} */
function slider_slides_html(): string
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $slides = [];
    foreach (get_sections(true) as $s) {
        $k = section_template_key($s);
        if ($k === 'header' || $k === 'footer') {
            continue;
        }
        $imgUrl = section_image_url($s);
        if ($imgUrl === '') {
            continue;
        }
        $heading = trim((string) ($s['heading'] ?? '')) !== '' ? (string) $s['heading'] : (string) ($s['title'] ?? '');
        $slide  = '<figure class="slide' . ($slides === [] ? ' is-active' : '') . '">';
        $slide .= '<img src="' . e($imgUrl) . '" alt="' . e($heading) . '" loading="lazy" decoding="async">';
        $slide .= '<figcaption class="slide-caption">';
        if ($heading !== '') {
            $slide .= '<h3>' . e($heading) . '</h3>';
        }
        if (trim((string) ($s['body'] ?? '')) !== '') {
            $slide .= '<div class="slide-text">' . (string) $s['body'] . '</div>';
        }
        $linkUrl = trim((string) ($s['link_url'] ?? ''));
        $linkText = trim((string) ($s['link_text'] ?? ''));
        if ($linkUrl !== '' && $linkText !== '') {
            $slide .= '<a class="btn btn-light" href="' . e($linkUrl) . '">' . e($linkText) . '</a>';
        }
        $slide .= '</figcaption></figure>';
        $slides[] = $slide;
    }
    if ($slides === []) {
        $cache = '';
        return $cache;
    }
    $html = '<div class="slides" data-slider>' . implode('', $slides);
    if (count($slides) > 1) {
        $html .= '<button type="button" class="slide-btn slide-prev" data-slide-prev aria-label="اسلاید قبلی">‹</button>';
        $html .= '<button type="button" class="slide-btn slide-next" data-slide-next aria-label="اسلاید بعدی">›</button>';
        $html .= '<div class="slide-dots">';
        foreach ($slides as $idx => $unused) {
            $html .= '<button type="button" class="slide-dot' . ($idx === 0 ? ' is-active' : '') . '" data-slide-dot="' . $idx . '" aria-label="اسلاید ' . ($idx + 1) . '"' . ($idx === 0 ? ' aria-current="true"' : '') . '></button>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    $cache = $html;
    return $cache;
}

// ---------- تنظیمات ظاهری و CSS ----------

/** اعتبارسنجی رنگ هگز (#rgb یا #rrggbb) با مقدار جایگزین امن */
function valid_hex_color($value, string $default): string
{
    $v = trim((string) $value);
    return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v) ? strtolower($v) : $default;
}

/** روشن/تیره‌کردن یک رنگ هگز به نسبت -۱ تا ۱ */
function shade_hex_color(string $hex, float $percent): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return '#1d4ed8';
    }
    $out = '#';
    for ($i = 0; $i < 6; $i += 2) {
        $c = hexdec(substr($hex, $i, 2));
        $c = $percent < 0 ? (int) round($c * (1 + $percent)) : (int) round($c + (255 - $c) * $percent);
        $out .= str_pad(dechex(max(0, min(255, $c))), 2, '0', STR_PAD_LEFT);
    }
    return $out;
}

/** تنظیمات ظاهری اعتبارسنجی‌شده از روی تنظیمات خام دیتابیس */
function validated_visual_settings(array $settings): array
{
    $fontStacks = [
        'system'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Tahoma, Arial, sans-serif',
        'tahoma'    => 'Tahoma, "Segoe UI", Arial, sans-serif',
        'vazirmatn' => '"Vazirmatn", Tahoma, "Segoe UI", Arial, sans-serif',
    ];
    $font = (string) ($settings['site_font'] ?? 'system');
    if (!isset($fontStacks[$font])) {
        $font = 'system';
    }
    $theme = (string) ($settings['default_theme'] ?? 'light');
    if (!in_array($theme, ['light', 'dark', 'system'], true)) {
        $theme = 'light';
    }
    $width = (int) ($settings['container_width'] ?? 1200);
    $width = max(880, min(1600, $width > 0 ? $width : 1200));
    $radius = (int) ($settings['border_radius'] ?? 12);
    $radius = max(0, min(32, $radius));
    return [
        'primary_color'   => valid_hex_color($settings['primary_color'] ?? '', '#2563eb'),
        'accent_color'    => valid_hex_color($settings['accent_color'] ?? '', '#0d9488'),
        'site_font'       => $font,
        'font_stack'      => $fontStacks[$font],
        'container_width' => $width,
        'border_radius'   => $radius,
        'default_theme'   => $theme,
        'header_sticky'   => (string) ($settings['header_sticky'] ?? '1') !== '0',
    ];
}

/** بلوک متغیرهای CSS ساخته‌شده از تنظیمات ظاهری پنل (بعد از CSS اصلی می‌نشیند تا برنده شود) */
function visual_css_vars(array $settings): string
{
    $v = validated_visual_settings($settings);
    $css = ":root{\n"
        . '--primary:' . $v['primary_color'] . ";\n"
        . '--primary-dark:' . shade_hex_color($v['primary_color'], -0.18) . ";\n"
        . '--accent:' . $v['accent_color'] . ";\n"
        . '--accent-dark:' . shade_hex_color($v['accent_color'], -0.18) . ";\n"
        . '--font-family:' . $v['font_stack'] . ";\n"
        . '--container-width:' . $v['container_width'] . "px;\n"
        . '--radius:' . $v['border_radius'] . "px;\n"
        . "}\n";
    if (!$v['header_sticky']) {
        $css .= ".site-header{position:static;}\n";
    }
    return $css;
}

/** ساخت خروجی نهایی style.php: ایمپورت فونت (در صورت انتخاب) + CSS اصلی + متغیرهای ظاهری + CSS سفارشی */
function build_site_css(array $settings): string
{
    $v = validated_visual_settings($settings);
    $parts = [];
    if ($v['site_font'] === 'vazirmatn') {
        $parts[] = "@import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');";
    }
    $siteCss = (string) ($settings['site_css'] ?? '');
    if (trim($siteCss) === '') {
        $siteCss = default_site_css();
    }
    $parts[] = $siteCss;
    $parts[] = "/* ===== تنظیمات ظاهری (از پنل مدیریت) ===== */\n" . visual_css_vars($settings);
    $custom = trim((string) ($settings['custom_css'] ?? ''));
    if ($custom !== '') {
        $parts[] = "/* ===== CSS سفارشی (از پنل مدیریت) ===== */\n" . $custom;
    }
    return implode("\n", $parts) . "\n";
}

/** نسخه کش CSS برای پارامتر ?v= در لینک استایل و ETag */
function site_css_version(array $settings): string
{
    return substr(sha1(build_site_css($settings) . '|' . (string) ($settings['css_updated_at'] ?? '')), 0, 10);
}

// ---------- اسکلت صفحه (تنها بخش فایلیِ ظاهر سایت) ----------

/** سربرگ سند: doctype، head با سئو، لینک style.php و اسکریپت بدون فلشِ تم + بازشدن body و لینک پرش */
function skeleton_head(array $settings, string $title, string $description): string
{
    $visual = validated_visual_settings($settings);
    $themeJson = json_encode($visual['default_theme']);
    $out  = "<!DOCTYPE html>\n";
    $out .= "<html lang=\"fa\" dir=\"rtl\">\n<head>\n";
    $out .= '<meta charset="UTF-8">' . "\n";
    $out .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    $out .= '<title>' . e($title) . '</title>' . "\n";
    $out .= '<meta name="description" content="' . e($description) . '">' . "\n";
    $out .= '<meta name="theme-color" content="' . e($visual['primary_color']) . '">' . "\n";
    $out .= '<link rel="stylesheet" href="style.php?v=' . e(site_css_version($settings)) . '">' . "\n";
    $out .= '<script>(function(){try{var t=localStorage.getItem(\'cms-theme\');if(t!==\'dark\'&&t!==\'light\'){t=' . $themeJson . ';if(t===\'system\'){t=(window.matchMedia&&window.matchMedia(\'(prefers-color-scheme: dark)\').matches)?\'dark\':\'light\';}}if(t===\'dark\'){document.documentElement.setAttribute(\'data-theme\',\'dark\');}}catch(e){}})();</script>' . "\n";
    $out .= "</head>\n<body>\n";
    $out .= '<a class="skip-link" href="#main">پرش به محتوای اصلی</a>' . "\n";
    return $out;
}

/** پایان سند: اسکریپت سبک منوی موبایل، تغییر تم و اسلایدر + بستن body و html */
function skeleton_foot(): string
{
    return <<<'HTML'
<script>
(function(){
    var navToggle=document.querySelector('.nav-toggle');
    if(navToggle){
        var nav=navToggle.parentNode;
        var list=nav?nav.querySelector('.nav-list'):null;
        function closeNav(){if(list){list.classList.remove('open');}navToggle.setAttribute('aria-expanded','false');}
        navToggle.addEventListener('click',function(){
            var open=list?list.classList.toggle('open'):false;
            navToggle.setAttribute('aria-expanded',open?'true':'false');
        });
        if(list){list.addEventListener('click',function(ev){if(ev.target&&ev.target.tagName==='A'){closeNav();}});}
        document.addEventListener('keydown',function(ev){if(ev.key==='Escape'&&list&&list.classList.contains('open')){closeNav();navToggle.focus();}});
    }
    var themeBtn=document.getElementById('theme-toggle');
    if(themeBtn){
        themeBtn.addEventListener('click',function(){
            var dark=document.documentElement.getAttribute('data-theme')==='dark';
            if(dark){document.documentElement.removeAttribute('data-theme');}else{document.documentElement.setAttribute('data-theme','dark');}
            try{localStorage.setItem('cms-theme',dark?'light':'dark');}catch(e){}
        });
    }
    var reduceMotion=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var sliders=document.querySelectorAll('[data-slider]');
    Array.prototype.forEach.call(sliders,function(root){
        var slides=root.querySelectorAll('.slide');
        if(!slides.length){return;}
        var dots=root.querySelectorAll('.slide-dot');
        var idx=0,timer=null;
        function show(n){
            idx=((n%slides.length)+slides.length)%slides.length;
            Array.prototype.forEach.call(slides,function(s,i){s.classList.toggle('is-active',i===idx);});
            Array.prototype.forEach.call(dots,function(d,i){d.classList.toggle('is-active',i===idx);if(i===idx){d.setAttribute('aria-current','true');}else{d.removeAttribute('aria-current');}});
        }
        function stop(){if(timer){clearInterval(timer);timer=null;}}
        function start(){stop();if(reduceMotion||slides.length<2){return;}timer=setInterval(function(){show(idx+1);},5000);}
        var prev=root.querySelector('[data-slide-prev]'),next=root.querySelector('[data-slide-next]');
        if(prev){prev.addEventListener('click',function(){show(idx-1);start();});}
        if(next){next.addEventListener('click',function(){show(idx+1);start();});}
        Array.prototype.forEach.call(dots,function(d,i){d.addEventListener('click',function(){show(i);start();});});
        root.addEventListener('mouseenter',stop);
        root.addEventListener('mouseleave',start);
        root.addEventListener('focusin',stop);
        root.addEventListener('focusout',start);
        var startX=null;
        root.addEventListener('touchstart',function(ev){startX=ev.changedTouches[0].clientX;},{passive:true});
        root.addEventListener('touchend',function(ev){
            if(startX===null){return;}
            var dx=ev.changedTouches[0].clientX-startX;
            if(Math.abs(dx)>40){show(dx<0?idx+1:idx-1);start();}
            startX=null;
        },{passive:true});
        show(0);
        start();
    });
})();
</script>
</body>
</html>
HTML;
}

// ---------- CSS پایه کارخانه‌ای ----------

// تابع default_site_css() در فایل defaults.php است (بالای همین فایل require می‌شود).

/** نشانگر بلوک CSS کاتالوگ داخل site_css (برای جلوگیری از افزودن تکراری هنگام مهاجرت) */
function catalog_css_marker(): string
{
    return '/* === کاتالوگ محصولات (نسخه ۶) === */';
}

// تابع catalog_css_block() در فایل defaults.php است (بالای همین فایل require می‌شود).

/** سید داده‌های پایه فاز ۲ و افزودن CSS کاتالوگ به سایت‌های قدیمی — فقط آیتم‌های غایب؛ هرگز داده کاربر بازنویسی نمی‌شود */
function seed_catalog_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    // دسته‌بندی‌های پیش‌فرض (فقط وقتی هیچ دسته‌ای وجود ندارد)
    $catCount = (int) $pdo->query('SELECT COUNT(*) FROM product_categories')->fetchColumn();
    if ($catCount === 0) {
        $ins = $pdo->prepare('INSERT INTO product_categories (title, slug, sort_order, is_active) VALUES (:t, :s, :o, 1)');
        $seedCats = [
            ['چراغ خطی کمد',     'wardrobe-linear', 10],
            ['چراغ خطی کابینت',  'cabinet-linear',  20],
            ['چراغ خطی کلوزت',   'closet-linear',   30],
            ['چراغ دکوراتیو',    'decorative',      40],
        ];
        foreach ($seedCats as [$t, $s, $o]) {
            $ins->execute([':t' => $t, ':s' => $s, ':o' => $o]);
        }
    }

    // ویژگی‌های پیش‌فرض و گزینه‌هایشان (فقط وقتی هیچ ویژگی‌ای وجود ندارد)
    $attrCount = (int) $pdo->query('SELECT COUNT(*) FROM product_attributes')->fetchColumn();
    if ($attrCount === 0) {
        $insA = $pdo->prepare('INSERT INTO product_attributes (title, attr_key, input_type, unit, sort_order, is_active) VALUES (:t, :k, :ty, :u, :o, 1)');
        $insO = $pdo->prepare('INSERT INTO product_attribute_options (attribute_id, title, price_delta_per_meter, sort_order) VALUES (:a, :t, 0, :o)');
        $insA->execute([':t' => 'رنگ نور', ':k' => 'light_color', ':ty' => 'select', ':u' => '', ':o' => 10]);
        $lightId = (int) $pdo->lastInsertId();
        foreach (['آفتابی ۳۰۰۰K', 'طبیعی ۴۰۰۰K', 'مهتابی ۶۵۰۰K'] as $i => $title) {
            $insO->execute([':a' => $lightId, ':t' => $title, ':o' => ($i + 1) * 10]);
        }
        $insA->execute([':t' => 'سنسور', ':k' => 'sensor', ':ty' => 'select', ':u' => '', ':o' => 20]);
        $sensorId = (int) $pdo->lastInsertId();
        foreach (['بدون سنسور', 'سنسور لمسی', 'سنسور حرکتی'] as $i => $title) {
            $insO->execute([':a' => $sensorId, ':t' => $title, ':o' => ($i + 1) * 10]);
        }
        $insA->execute([':t' => 'جنس پروفیل', ':k' => 'profile_material', ':ty' => 'text', ':u' => '', ':o' => 30]);
    }

    // CSS کاتالوگ برای سایت‌های نسخه ۵: فقط اگر نشانگرش نیست، یک بار به انتهای CSS اصلی افزوده می‌شود (CSS کاربر پاک نمی‌شود)
    $st = $pdo->prepare('SELECT value FROM settings WHERE key = :k');
    $st->execute([':k' => 'site_css']);
    $row = $st->fetch();
    $siteCss = $row === false ? '' : (string) ($row['value'] ?? '');
    if ($siteCss !== '' && strpos($siteCss, catalog_css_marker()) === false) {
        $upd = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        $upd->execute([':k' => 'site_css', ':v' => rtrim($siteCss) . "\n\n" . catalog_css_block() . "\n"]);
        $upd->execute([':k' => 'css_updated_at', ':v' => date('Y-m-d H:i:s')]);
    }
}

// ---------- مشتری‌ها ----------

/** انواع مشتری: کلید ذخیره‌شده => برچسب فارسی */
function customer_types(): array
{
    return [
        'partner' => 'همکار',
        'retail'  => 'مشتری',
        'company' => 'شرکت',
    ];
}

function customer_type_label(string $key): string
{
    $types = customer_types();
    return $types[$key] ?? $types['retail'];
}

/** فهرست مشتری‌ها با جستجو (نام/شرکت/موبایل) و فیلتر نوع */
function get_customers(string $search = '', string $type = ''): array
{
    $sql = 'SELECT * FROM customers WHERE 1=1';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (full_name LIKE :s OR company LIKE :s OR mobile LIKE :s)';
        $params[':s'] = '%' . $search . '%';
    }
    if ($type !== '' && array_key_exists($type, customer_types())) {
        $sql .= ' AND customer_type = :t';
        $params[':t'] = $type;
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_customer(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM customers WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** مشتری دیگری با همین موبایل هست؟ (برای هشدار تکراری؛ $excludeId رکورد در حال ویرایش) */
function customer_mobile_exists(string $mobile, int $excludeId = 0): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM customers WHERE mobile = :m AND id != :x');
    $stmt->execute([':m' => $mobile, ':x' => $excludeId]);
    return (int) $stmt->fetchColumn() > 0;
}

// ---------- دسته‌بندی و محصول ----------

/** همه دسته‌ها به ترتیب (والدها و زیردسته‌ها با هم، بر اساس ترتیب و شناسه) */
function get_categories(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM product_categories';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_category(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM product_categories WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** شناسه‌های یک دسته + همه زیردسته‌هایش (برای فیلتر کاتالوگ) */
function category_ids_with_children(int $id): array
{
    $ids = [$id];
    $stmt = db()->prepare('SELECT id FROM product_categories WHERE parent_id = :p');
    $queue = [$id];
    while ($queue !== []) {
        $cur = array_shift($queue);
        $stmt->execute([':p' => $cur]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $child) {
            $cid = (int) $child;
            if (!in_array($cid, $ids, true)) {
                $ids[] = $cid;
                $queue[] = $cid;
            }
        }
    }
    return $ids;
}

/** دسته دیگری با همین نامک (slug) هست؟ */
function category_slug_exists(string $slug, int $excludeId = 0): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM product_categories WHERE slug = :s AND id != :x');
    $stmt->execute([':s' => $slug, ':x' => $excludeId]);
    return (int) $stmt->fetchColumn() > 0;
}

function get_products(bool $onlyActive = false, ?int $categoryId = null): array
{
    $sql = 'SELECT p.*, c.title AS category_title FROM products p LEFT JOIN product_categories c ON c.id = p.category_id WHERE 1=1';
    $params = [];
    if ($onlyActive) {
        $sql .= ' AND p.is_active = 1';
    }
    if ($categoryId !== null) {
        $ids = category_ids_with_children($categoryId);
        $marks = [];
        foreach ($ids as $i => $cid) {
            $marks[] = ':c' . $i;
            $params[':c' . $i] = $cid;
        }
        $sql .= ' AND p.category_id IN (' . implode(',', $marks) . ')';
    }
    $sql .= ' ORDER BY p.sort_order ASC, p.id ASC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function get_product(int $id): ?array
{
    $stmt = db()->prepare('SELECT p.*, c.title AS category_title FROM products p LEFT JOIN product_categories c ON c.id = p.category_id WHERE p.id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** مسیر عمومی عکس محصول/دسته (یا رشته خالی) */
function uploaded_image_url($filename): string
{
    $img = trim((string) ($filename ?? ''));
    if ($img === '') {
        return '';
    }
    return UPLOADS_URL . '/' . basename($img);
}

// ---------- ویژگی‌های محصول ----------

function get_attributes(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM product_attributes';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_attribute(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM product_attributes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function get_attribute_options(int $attributeId): array
{
    $stmt = db()->prepare('SELECT * FROM product_attribute_options WHERE attribute_id = :a ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':a' => $attributeId]);
    return $stmt->fetchAll();
}

function get_attribute_option(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM product_attribute_options WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** مقادیر ویژگی‌های یک محصول (ردیف‌های product_attribute_values) */
function get_product_attribute_values(int $productId): array
{
    $stmt = db()->prepare('SELECT * FROM product_attribute_values WHERE product_id = :p ORDER BY id ASC');
    $stmt->execute([':p' => $productId]);
    return $stmt->fetchAll();
}

/**
 * ویژگی‌هایی که محصول ارائه می‌کند، ساخت‌یافته برای نمایش/برآورد:
 * [ ['attribute'=>row, 'options'=>[optionRow...], 'default_option_id'=>?int, 'num_value'=>?float, 'text_value'=>?string], ... ]
 * فقط ویژگی‌های فعال؛ برای select فقط وقتی ردیف مقدار با option_id معتبر دارد، ارائه‌شده حساب می‌شود.
 */
function product_offered_attributes(int $productId): array
{
    $values = [];
    foreach (get_product_attribute_values($productId) as $v) {
        $values[(int) $v['attribute_id']] = $v;
    }
    $out = [];
    foreach (get_attributes(true) as $attr) {
        $aid = (int) $attr['id'];
        if (!isset($values[$aid])) {
            continue;
        }
        $v = $values[$aid];
        $type = (string) $attr['input_type'];
        if ($type === 'select') {
            $options = get_attribute_options($aid);
            $optIds = array_map(static fn ($o) => (int) $o['id'], $options);
            $defId = isset($v['option_id']) ? (int) $v['option_id'] : 0;
            if ($defId <= 0 || !in_array($defId, $optIds, true)) {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => $options, 'default_option_id' => $defId, 'num_value' => null, 'text_value' => null];
        } elseif ($type === 'number') {
            if ($v['num_value'] === null || $v['num_value'] === '') {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => [], 'default_option_id' => null, 'num_value' => (float) $v['num_value'], 'text_value' => null];
        } else {
            $tv = trim((string) ($v['text_value'] ?? ''));
            if ($tv === '') {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => [], 'default_option_id' => null, 'num_value' => null, 'text_value' => $tv];
        }
    }
    return $out;
}

// ---------- موتور قیمت (متری + آپشن + همکار) ----------

/** قالب‌بندی مبلغ تومان با جداکننده هزارگان */
function format_price($amount): string
{
    $n = (float) $amount;
    if (floor($n) == $n) {
        return number_format($n, 0, '.', ',');
    }
    return number_format($n, 2, '.', ',');
}

/** درصد تخفیف همکار از تنظیمات (۰ تا ۹۰) */
function partner_discount_percent(): float
{
    $d = (float) get_setting('partner_discount_percent', '10');
    if ($d < 0) {
        return 0.0;
    }
    return $d > 90 ? 90.0 : $d;
}

/** قیمت پایه متری: مشتری عادی یا همکار (قیمت صریح همکار، وگرنه قیمت منهای درصد همکار) */
function product_base_price_per_meter(array $product, bool $isPartner): float
{
    $price = (float) ($product['price_per_meter'] ?? 0);
    if (!$isPartner) {
        return $price;
    }
    $partner = $product['partner_price_per_meter'] ?? null;
    if ($partner !== null && $partner !== '' && (float) $partner > 0) {
        return (float) $partner;
    }
    return $price * (1 - partner_discount_percent() / 100);
}

/**
 * قیمت واحد متری با آپشن‌های انتخابی: پایه + مجموع اختلاف قیمت متری گزینه‌ها.
 * $selectedOptionIds: آرایه‌ای از شناسه گزینه‌ها (یا مقادیر رشته‌ای فرم‌ها).
 */
function product_unit_price(array $product, array $selectedOptionIds = [], bool $isPartner = false): float
{
    $unit = product_base_price_per_meter($product, $isPartner);
    foreach ($selectedOptionIds as $oid) {
        $opt = get_attribute_option((int) $oid);
        if ($opt !== null) {
            $unit += (float) ($opt['price_delta_per_meter'] ?? 0);
        }
    }
    return $unit;
}

/** برآورد قیمت کل برای طول مشخص (متر، اعشاری مجاز؛ طول نامعتبر = صفر) */
function estimate_price(array $product, float $lengthM, array $selectedOptionIds = [], bool $isPartner = false): float
{
    if (!is_finite($lengthM) || $lengthM <= 0) {
        return 0.0;
    }
    return product_unit_price($product, $selectedOptionIds, $isPartner) * $lengthM;
}

// ---------- سازنده‌های HTML کاتالوگ (خروجی خام داخلیِ مطمئن؛ ورودی‌ها escape می‌شوند) ----------

/** ناو دسته‌های کاتالوگ (چیپ‌ها) با حالت فعال */
function catalog_categories_nav_html(?int $activeCategoryId): string
{
    $cats = get_categories(true);
    $html = '<nav class="catalog-nav" aria-label="دسته‌بندی محصولات">';
    $html .= '<a href="products.php"' . ($activeCategoryId === null ? ' class="active"' : '') . '>همه محصولات</a>';
    $idsWithProducts = [];
    foreach (get_products(true) as $p) {
        if (!empty($p['category_id'])) {
            $idsWithProducts[(int) $p['category_id']] = true;
        }
    }
    foreach ($cats as $c) {
        $cid = (int) $c['id'];
        if (!isset($idsWithProducts[$cid]) && $activeCategoryId !== $cid) {
            $has = false;
            foreach (category_ids_with_children($cid) as $sub) {
                if (isset($idsWithProducts[$sub])) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                continue;
            }
        }
        $indent = !empty($c['parent_id']) ? ' style="margin-inline-start:10px"' : '';
        $html .= '<a href="products.php?cat=' . $cid . '"' . ($activeCategoryId === $cid ? ' class="active"' : '') . $indent . '>' . e($c['title']) . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

/** شبکه کارت‌های محصول برای صفحه فهرست کاتالوگ */
function catalog_products_grid_html(array $products): string
{
    if ($products === []) {
        return '<div class="empty-state"><p>هنوز محصولی در این بخش ثبت نشده است.</p></div>';
    }
    $html = '<div class="products-grid">';
    foreach ($products as $p) {
        $pid = (int) $p['id'];
        $img = uploaded_image_url($p['image'] ?? '');
        $html .= '<article class="product-card">';
        if ($img !== '') {
            $html .= '<a href="products.php?id=' . $pid . '"><img src="' . e($img) . '" alt="' . e($p['name']) . '" loading="lazy" decoding="async"></a>';
        }
        $html .= '<div class="product-card-body">';
        if (!empty($p['category_title'])) {
            $html .= '<span class="cat">' . e($p['category_title']) . '</span>';
        }
        $html .= '<h3><a href="products.php?id=' . $pid . '">' . e($p['name']) . '</a></h3>';
        $html .= '<p class="price">قیمت متری: <strong>' . e(format_price($p['price_per_meter'] ?? 0)) . '</strong> تومان</p>';
        $html .= '<a class="btn small" href="products.php?id=' . $pid . '">مشاهده و برآورد قیمت</a>';
        $html .= '</div></article>';
    }
    $html .= '</div>';
    return $html;
}

/** جدول مشخصات محصول از ویژگی‌های ارائه‌شده (گزینه پیش‌فرض / مقدار عددی / متن) */
function product_specs_html(array $product): string
{
    $offered = product_offered_attributes((int) $product['id']);
    if ($offered === []) {
        return '';
    }
    $html = '<table class="spec-table"><tbody>';
    foreach ($offered as $item) {
        $attr = $item['attribute'];
        $html .= '<tr><th>' . e($attr['title']) . '</th><td>';
        if ($item['default_option_id'] !== null) {
            $label = '';
            foreach ($item['options'] as $o) {
                if ((int) $o['id'] === $item['default_option_id']) {
                    $label = (string) $o['title'];
                    break;
                }
            }
            $html .= e($label);
        } elseif ($item['num_value'] !== null) {
            $html .= e(format_price($item['num_value'])) . (!empty($attr['unit']) ? ' ' . e($attr['unit']) : '');
        } else {
            $html .= e((string) $item['text_value']);
        }
        $html .= '</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** انتخاب‌های آپشن برای برآوردگر (select برای هر ویژگی انتخابی ارائه‌شده) */
function product_options_selects_html(array $product): string
{
    $offered = product_offered_attributes((int) $product['id']);
    $html = '';
    foreach ($offered as $item) {
        if ($item['default_option_id'] === null) {
            continue;
        }
        $attr = $item['attribute'];
        $html .= '<div class="field"><label for="est-opt-' . (int) $attr['id'] . '">' . e($attr['title']) . '</label>';
        $html .= '<select id="est-opt-' . (int) $attr['id'] . '" class="est-option">';
        foreach ($item['options'] as $o) {
            $delta = (float) ($o['price_delta_per_meter'] ?? 0);
            $html .= '<option value="' . (int) $o['id'] . '" data-delta="' . $delta . '"'
                . ((int) $o['id'] === $item['default_option_id'] ? ' selected' : '') . '>'
                . e($o['title'])
                . ($delta != 0.0 ? ' (' . ($delta > 0 ? '+' : '') . e(format_price($delta)) . ' تومان/متر)' : '')
                . '</option>';
        }
        $html .= '</select></div>';
    }
    return $html;
}

/** بلوک برآوردگر قیمت صفحه محصول: طول (متر) + آپشن‌ها → برآورد زنده مشتری و همکار */
function product_estimator_html(array $product): string
{
    $retailBase  = product_base_price_per_meter($product, false);
    $partnerBase = product_base_price_per_meter($product, true);
    $html = '<div class="estimator" id="estimator" data-retail-base="' . (float) $retailBase . '" data-partner-base="' . (float) $partnerBase . '">';
    $html .= '<h2>برآورد قیمت</h2>';
    $html .= '<div class="field"><label for="est-length">طول (متر)</label>';
    $html .= '<input type="number" id="est-length" min="0.1" step="0.1" value="1" inputmode="decimal"></div>';
    $html .= product_options_selects_html($product);
    $html .= '<p class="estimator-result">برآورد مشتری: <strong id="est-retail">' . e(format_price($retailBase)) . '</strong> تومان</p>';
    if ($partnerBase != $retailBase) {
        $html .= '<p class="partner-line">برآورد همکار: <strong id="est-partner">' . e(format_price($partnerBase)) . '</strong> تومان</p>';
    }
    $html .= '<p><a class="btn" href="index.php#contact">برای ثبت سفارش و مشاوره با ما در تماس باشید</a></p>';
    $html .= '<script>(function(){var box=document.getElementById("estimator");if(!box)return;'
        . 'var len=document.getElementById("est-length"),r=document.getElementById("est-retail"),p=document.getElementById("est-partner");'
        . 'function fmt(n){return Math.round(n).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g,",");}'
        . 'function calc(){var L=parseFloat(len.value)||0;if(L<0)L=0;var d=0;'
        . 'box.querySelectorAll(".est-option").forEach(function(s){var o=s.options[s.selectedIndex];d+=o?parseFloat(o.getAttribute("data-delta")||"0"):0;});'
        . 'var rb=parseFloat(box.getAttribute("data-retail-base")||"0"),pb=parseFloat(box.getAttribute("data-partner-base")||"0");'
        . 'if(r)r.textContent=fmt((rb+d)*L);if(p)p.textContent=fmt((pb+d)*L);}'
        . 'len.addEventListener("input",calc);box.querySelectorAll(".est-option").forEach(function(s){s.addEventListener("change",calc);});calc();})();</script>';
    $html .= '</div>';
    return $html;
}

// =============================================================
// آپدیت یک‌کلیکی از گیت‌هاب (نسخه ۴)
// =============================================================

/** فولدر بکاپ‌های قبل از آپدیت (محافظت‌شده از وب)؛ در صورت نیاز ساخته می‌شود */
function backups_dir(): string
{
    return __DIR__ . '/backups';
}

/** ساخت فولدر backups با .htaccess محافظ؛ خروجی: مسیر یا null در صورت خطا */
function ensure_backups_dir(): ?string
{
    $dir = backups_dir();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            return null;
        }
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    $idx = $dir . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }
    return $dir;
}

/** تنظیمات مخزن آپدیت (با اعتبارسنجی سبک و مقدار پیش‌فرض امن) */
function update_repo_config(): array
{
    $repo   = trim(get_setting('update_repo', 'farsmd/soon'));
    $branch = trim(get_setting('update_branch', 'main'));
    $zipUrl = trim(get_setting('update_zip_url', ''));
    if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
        $repo = 'farsmd/soon';
    }
    if (!preg_match('#^[A-Za-z0-9._/-]+$#', $branch) || strpos($branch, '..') !== false) {
        $branch = 'main';
    }
    return ['repo' => $repo, 'branch' => $branch, 'zip_url' => $zipUrl];
}

/** آدرس دانلود ZIP نسخه تازه (از تنظیم مستقیم یا ساخته‌شده از مخزن و شاخه) */
function update_zip_download_url(array $cfg): string
{
    if (($cfg['zip_url'] ?? '') !== '') {
        return (string) $cfg['zip_url'];
    }
    return 'https://codeload.github.com/' . $cfg['repo'] . '/zip/refs/heads/' . $cfg['branch'];
}

/**
 * دریافت محتوای یک آدرس اینترنتی با cURL (اگر بود) یا file_get_contents.
 * خروجی: ['ok' => bool, 'body' => ?string, 'error' => ?string]
 */
function http_fetch(string $url, int $timeout = 15): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'LinerLight-CMS-Updater/' . APP_VERSION,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = (string) curl_error($ch);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return ['ok' => true, 'body' => (string) $body, 'error' => null];
            }
            return ['ok' => false, 'body' => null, 'error' => $err !== '' ? $err : ('HTTP ' . $code)];
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header'        => "User-Agent: LinerLight-CMS-Updater/" . APP_VERSION . "\r\nAccept: */*\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'body' => null, 'error' => 'دریافت پاسخ از سرور انجام نشد.'];
    }
    return ['ok' => true, 'body' => (string) $body, 'error' => null];
}

/**
 * دانلود یک فایل (مثل ZIP آپدیت) روی دیسک با cURL یا stream.
 * خروجی: ['ok' => bool, 'error' => ?string]
 */
function http_download(string $url, string $dest, int $timeout = 120): array
{
    if (function_exists('curl_init')) {
        $fp = @fopen($dest, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'error' => 'ساخت فایل موقت برای دانلود انجام نشد.'];
        }
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'LinerLight-CMS-Updater/' . APP_VERSION,
            ]);
            $ok   = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = (string) curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if ($ok && $code >= 200 && $code < 300 && is_file($dest) && (int) filesize($dest) > 0) {
                return ['ok' => true, 'error' => null];
            }
            @unlink($dest);
            return ['ok' => false, 'error' => $err !== '' ? $err : ('HTTP ' . $code)];
        }
        fclose($fp);
    }
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header'        => "User-Agent: LinerLight-CMS-Updater/" . APP_VERSION . "\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        return ['ok' => false, 'error' => 'دانلود فایل آپدیت انجام نشد.'];
    }
    if (@file_put_contents($dest, $body) === false) {
        return ['ok' => false, 'error' => 'ذخیره فایل آپدیت روی سرور انجام نشد.'];
    }
    return ['ok' => true, 'error' => null];
}

/** استخراج مقدار APP_VERSION از متن config.php */
function parse_app_version(string $configCode): ?string
{
    if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $configCode, $m)) {
        return $m[1];
    }
    if (preg_match('/define\(\s*"APP_VERSION"\s*,\s*"([^"]+)"\s*\)/', $configCode, $m)) {
        return $m[1];
    }
    return null;
}

/** خواندن متن یک فایل از داخل ZIP (با نام نسبی مثل config.php) */
function zip_read_entry(ZipArchive $zip, string $relative): ?string
{
    // ابتدا خود نام و سپس جستجو در زیرفولدر ریشه (مثل soon-main/config.php)
    $candidates = [$relative];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $suffix = '/' . $relative;
        if (substr($name, -strlen($suffix)) === $suffix) {
            $candidates[] = $name;
        }
    }
    foreach (array_unique($candidates) as $name) {
        $data = $zip->getFromName($name);
        if ($data !== false) {
            return (string) $data;
        }
    }
    return null;
}

/** تشخیص پیشوند فولدر ریشه داخل ZIP (مثل soon-main/) یا رشته خالی */
function zip_root_prefix(ZipArchive $zip): string
{
    $roots = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name === '' ) {
            continue;
        }
        $parts = explode('/', $name);
        if (count($parts) > 1 && $parts[0] !== '') {
            $roots[$parts[0]] = true;
        } else {
            // فایل در ریشه ZIP است؛ پیشوندی در کار نیست
            return '';
        }
    }
    if (count($roots) === 1) {
        return array_key_first($roots) . '/';
    }
    return '';
}

/**
 * بررسی وجود نسخه تازه‌تر.
 * خروجی: current, latest (?string), update_available (bool), commits (array), error (?string), checked (bool)
 */
function update_check(array $cfg): array
{
    $result = [
        'current'          => APP_VERSION,
        'latest'           => null,
        'update_available' => false,
        'commits'          => [],
        'error'            => null,
        'checked'          => false,
    ];

    if (($cfg['zip_url'] ?? '') !== '') {
        // حالت آدرس مستقیم ZIP: خود فایل دانلود و نسخه داخلش خوانده می‌شود.
        $dir = update_temp_dir();
        if ($dir === null) {
            $result['error'] = 'ساخت فولدر موقت برای بررسی آپدیت انجام نشد.';
            return $result;
        }
        $zipPath = $dir . '/check.zip';
        try {
            $dl = http_download((string) $cfg['zip_url'], $zipPath, 60);
            if (!$dl['ok']) {
                $result['error'] = 'دانلود فایل آپدیت برای بررسی انجام نشد: ' . (string) $dl['error'];
                return $result;
            }
            if (!class_exists('ZipArchive')) {
                $result['error'] = 'افزونه ZipArchive روی این سرور فعال نیست.';
                return $result;
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $result['error'] = 'فایل دانلودشده ZIP معتبر نیست.';
                return $result;
            }
            $code = zip_read_entry($zip, 'config.php');
            $zip->close();
            if ($code === null) {
                $result['error'] = 'فایل config.php داخل فایل آپدیت پیدا نشد.';
                return $result;
            }
            $latest = parse_app_version($code);
            if ($latest === null) {
                $result['error'] = 'نسخه برنامه داخل فایل آپدیت پیدا نشد.';
                return $result;
            }
            $result['latest'] = $latest;
            $result['checked'] = true;
            $result['update_available'] = version_compare($latest, APP_VERSION, '>');
            return $result;
        } finally {
            update_remove_dir($dir);
        }
    }

    // حالت مخزن گیت‌هاب: خواندن config.php خام از شاخه
    $rawUrl = 'https://raw.githubusercontent.com/' . $cfg['repo'] . '/' . $cfg['branch'] . '/config.php';
    $res = http_fetch($rawUrl, 15);
    if (!$res['ok']) {
        $result['error'] = 'بررسی نسخه تازه انجام نشد (مشکل شبکه یا دسترسی به گیت‌هاب): ' . (string) $res['error'];
        return $result;
    }
    $latest = parse_app_version((string) $res['body']);
    if ($latest === null) {
        $result['error'] = 'نسخه برنامه در فایل config.php مخزن پیدا نشد.';
        return $result;
    }
    $result['latest'] = $latest;
    $result['checked'] = true;
    $result['update_available'] = version_compare($latest, APP_VERSION, '>');

    // فهرست آخرین کامیت‌ها برای نمایش تغییرات (اختیاری؛ خطایش بی‌صدا نادیده گرفته می‌شود)
    $apiUrl = 'https://api.github.com/repos/' . $cfg['repo'] . '/commits?per_page=5&sha=' . rawurlencode($cfg['branch']);
    $commitsRes = http_fetch($apiUrl, 15);
    if ($commitsRes['ok']) {
        $decoded = json_decode((string) $commitsRes['body'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $msg  = trim((string) ($c['commit']['message'] ?? ''));
                $date = (string) ($c['commit']['author']['date'] ?? ($c['commit']['committer']['date'] ?? ''));
                $firstLine = $msg === '' ? '' : (string) strtok($msg, "\n");
                $result['commits'][] = [
                    'message' => $firstLine,
                    'date'    => $date !== '' ? date('Y/m/d H:i', (int) strtotime($date)) : '',
                ];
            }
        }
    }
    return $result;
}

/** ساخت یک فولدر موقت محافظت‌شده برای کارهای آپدیت */
function update_temp_dir(): ?string
{
    $base = ensure_backups_dir();
    if ($base === null) {
        $base = sys_get_temp_dir();
    }
    $dir = $base . '/.tmp-update-' . bin2hex(random_bytes(6));
    if (!@mkdir($dir, 0775, true)) {
        return null;
    }
    return $dir;
}

/** حذف بازگشتی یک فولدر (برای پاک‌سازی موقت‌ها) */
function update_remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/** آیا این مسیر نسبی در آپدیت محافظت می‌شود و نباید دست بخورد؟ */
function update_is_protected(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') {
        return true;
    }
    // فایل دیتابیس و هر فایل دیتابیس دیگر (.sqlite/.db)
    if ($rel === 'database.sqlite') {
        return true;
    }
    if (preg_match('/\.(sqlite|sqlite3|db)$/i', $rel)) {
        return true;
    }
    // فولدر بکاپ‌ها و متعلقات گیت
    if (strpos($rel, 'backups/') === 0 || $rel === 'backups') {
        return true;
    }
    if (strpos($rel, '.git') === 0 || $rel === '.git') {
        return true;
    }
    // فایل‌های کاربر در uploads (فقط فایل‌های سیستمی آن فولدر آپدیت می‌شوند)
    if (strpos($rel, 'uploads/') === 0) {
        return !in_array($rel, ['uploads/.htaccess', 'uploads/index.html'], true);
    }
    return false;
}

/**
 * اجرای آپدیت یک‌کلیکی.
 * خروجی: ['ok' => bool, 'error' => ?string, 'new_version' => ?string, 'backup_file' => ?string]
 */
function perform_update(array $cfg, bool $backupDb): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'افزونه ZipArchive روی این سرور فعال نیست؛ آپدیت خودکار ممکن نیست. PHP را با افزونه zip فعال کنید یا آپدیت را دستی انجام دهید.', 'new_version' => null, 'backup_file' => null];
    }

    $work = update_temp_dir();
    if ($work === null) {
        return ['ok' => false, 'error' => 'ساخت فولدر موقت برای آپدیت انجام نشد؛ مجوز نوشتن فولدر برنامه را بررسی کنید.', 'new_version' => null, 'backup_file' => null];
    }

    $backupFile = null;
    try {
        // الف) بکاپ دیتابیس قبل از آپدیت (در صورت تیک خوردن)
        if ($backupDb) {
            $bdir = ensure_backups_dir();
            if ($bdir === null) {
                return ['ok' => false, 'error' => 'ساخت فولدر بکاپ انجام نشد؛ آپدیت متوقف شد تا دیتابیس بدون بکاپ دست نخورد.', 'new_version' => null, 'backup_file' => null];
            }
            if (!is_file(DB_FILE)) {
                return ['ok' => false, 'error' => 'فایل دیتابیس پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => null];
            }
            $backupFile = 'database-backup-before-update-' . date('Ymd-His') . '.sqlite';
            if (!@copy(DB_FILE, $bdir . '/' . $backupFile)) {
                return ['ok' => false, 'error' => 'گرفتن بکاپ از دیتابیس انجام نشد؛ آپدیت متوقف شد. مجوز نوشتن فولدر backups را بررسی کنید.', 'new_version' => null, 'backup_file' => null];
            }
        }

        // ب) دانلود ZIP نسخه تازه
        $zipUrl  = update_zip_download_url($cfg);
        $zipPath = $work . '/update.zip';
        $dl = http_download($zipUrl, $zipPath, 120);
        if (!$dl['ok']) {
            return ['ok' => false, 'error' => 'دانلود فایل آپدیت از گیت‌هاب انجام نشد: ' . (string) $dl['error'], 'new_version' => null, 'backup_file' => $backupFile];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'فایل دانلودشده ZIP معتبر نیست.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $configCode = zip_read_entry($zip, 'config.php');
        if ($configCode === null) {
            $zip->close();
            return ['ok' => false, 'error' => 'فایل config.php داخل فایل آپدیت پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $newVersion = parse_app_version($configCode);
        if ($newVersion === null) {
            $zip->close();
            return ['ok' => false, 'error' => 'نسخه برنامه داخل فایل آپدیت پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        if (!version_compare($newVersion, APP_VERSION, '>')) {
            $zip->close();
            return ['ok' => false, 'error' => 'فایل آپدیت نسخه ' . $newVersion . ' را دارد که از نسخه فعلی (' . APP_VERSION . ') تازه‌تر نیست؛ آپدیت انجام نشد.', 'new_version' => null, 'backup_file' => $backupFile];
        }

        $extractDir = $work . '/extract';
        if (!@mkdir($extractDir, 0775, true) || !$zip->extractTo($extractDir)) {
            $zip->close();
            return ['ok' => false, 'error' => 'باز کردن فایل آپدیت روی سرور انجام نشد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $zip->close();

        // تشخیص فولدر ریشه داخل ZIP (مثل soon-main/)
        $srcRoot = $extractDir;
        $entries = array_values(array_filter(scandir($extractDir) ?: [], static fn($x) => $x !== '.' && $x !== '..'));
        if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
            $srcRoot = $extractDir . '/' . $entries[0];
        }
        if (!is_file($srcRoot . '/config.php')) {
            return ['ok' => false, 'error' => 'ساختار فایل آپدیت درست نیست (config.php پیدا نشد)؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }

        // ج) کپی فایل‌های تازه روی برنامه — بدون حذف هیچ فایل محلی و بدون دست‌زدن به فایل‌های محافظت‌شده
        $base = __DIR__;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            $rel  = ltrim(str_replace('\\', '/', substr($path, strlen($srcRoot))), '/');
            if ($rel === '' || update_is_protected($rel)) {
                continue;
            }
            $dest = $base . '/' . $rel;
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
                continue;
            }
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            if (!@copy($path, $dest)) {
                return ['ok' => false, 'error' => 'کپی فایل «' . $rel . '» انجام نشد؛ مجوز نوشتن فایل‌های برنامه را بررسی کنید.', 'new_version' => null, 'backup_file' => $backupFile];
            }
        }

        // د) تازه‌سازی کش آپکد در صورت وجود
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return ['ok' => true, 'error' => null, 'new_version' => $newVersion, 'backup_file' => $backupFile];
    } finally {
        // هـ) پاک‌سازی فایل‌های موقت در همه مسیرها
        update_remove_dir($work);
    }
}
