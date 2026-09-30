<?php
// config.php — اتصال دیتابیس و توابع کمکی مشترک (نسخه ۳)
// همه فایل‌های این پروژه در یک فولدر کنار هم قرار دارند؛ عکس‌های آپلودی داخل فولدر uploads همان فولدر است.

declare(strict_types=1);

define('APP_VERSION', '3.0.0');
define('DB_FILE', __DIR__ . '/database.sqlite');
define('UPLOADS_DIR', __DIR__ . '/uploads');
define('UPLOADS_URL', 'uploads');

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

    // سید تنظیمات پیش‌فرض (INSERT OR IGNORE یعنی مقادیر فعلی سایت زنده دست نمی‌خورند)
    $defaults = [
        'site_title'          => 'وب‌سایت من',
        'site_description'    => 'توضیح کوتاه وب‌سایت من — این متن را از پنل مدیریت تغییر دهید.',
        'admin_password_hash' => '',
        'seo_title'           => '',
        'seo_description'     => '',
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

/** HTML منوی سایت (برای پلیس‌هولدر {{menu}} و استفاده مستقیم در قالب‌ها) + دکمه تغییر تم */
function menu_html(string $class = 'main-nav'): string
{
    $html = '<nav class="' . e($class) . '">';
    foreach (menu_items() as $item) {
        $html .= '<a href="' . e($item['url']) . '">' . e($item['title']) . '</a>';
    }
    $html .= '<button type="button" class="theme-toggle" id="theme-toggle" title="تغییر تم روشن/تیره" aria-label="تغییر تم">🌓</button>';
    $html .= '</nav>';
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
 * داخل قالب این متغیرها در دسترس‌اند: $site_title ، $site_description ، $settings ، $section (ردیف بخش جاری، اگر باشد)
 * و این پلیس‌هولدرها در خروجی جایگزین می‌شوند:
 *   {{site_title}}  {{site_description}}  {{current_year}}  {{menu}}  {{seo_title}}  {{seo_description}}
 */
function render_template(string $file, array $settings, ?array $section = null): string
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
        '{{seo_title}}'        => e((string) ($settings['seo_title'] ?? '') !== '' ? (string) $settings['seo_title'] : $site_title),
        '{{seo_description}}'  => e((string) ($settings['seo_description'] ?? '') !== '' ? (string) $settings['seo_description'] : $site_description),
        '{{current_year}}'     => date('Y'),
        '{{menu}}'             => menu_html(),
    ];
    return strtr($output, $replacements);
}
