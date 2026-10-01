<?php
// config.php — اتصال دیتابیس و توابع کمکی مشترک (نسخه ۶)
// همه فایل‌های این پروژه در یک فولدر کنار هم قرار دارند؛ عکس‌های آپلودی داخل فولدر uploads همان فولدر است.
// نسخه ۵: محتوای قالب‌ها و CSS سایت داخل دیتابیس نگهداری می‌شود؛ فقط اسکلت صفحه در کد باقی مانده است.
// نسخه ۶ (فاز ۲): مشتری‌ها، دسته‌بندی و کاتالوگ محصول با قیمت متری + آپشن + قیمت همکار و ماشین‌حساب قیمت.
// نسخه ۷ (فاز ۲٫۵): مواد اولیه، انبار کارگاه، فرمول ساخت محصول (BOM) و بهای تمام‌شده بر پایه «آخرین قیمت خرید».
// نسخه ۸ (فاز ۳): سفارش‌ها (همکار/مشتری) با ردیف‌های طول‌دار، تخفیف پلکانی متراژ، سیم و درپوش، پیش‌فاکتور چاپی، وضعیت‌ها و انبار پرتی.

declare(strict_types=1);

define('APP_VERSION', '8.2.1');
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
    $pdo->exec('PRAGMA busy_timeout = 5000');
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

    // --- فاز ۲٫۵ (نسخه ۷): مواد اولیه و انبار کارگاه (یک انبار؛ ستون انبار ندارد تا بعداً قابل توسعه باشد) ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS materials (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            name                TEXT NOT NULL,
            unit                TEXT NOT NULL DEFAULT 'عدد',
            stock_qty           REAL NOT NULL DEFAULT 0,
            last_price          INTEGER NOT NULL DEFAULT 0,
            low_stock_threshold REAL NOT NULL DEFAULT 0,
            notes               TEXT,
            is_active           INTEGER NOT NULL DEFAULT 1,
            created_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at          TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stock_movements (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id   INTEGER NOT NULL,
            move_type     TEXT NOT NULL DEFAULT 'in',
            qty           REAL NOT NULL DEFAULT 0,
            unit_price    INTEGER,
            reason        TEXT,
            ref_type      TEXT,
            ref_id        INTEGER,
            balance_after REAL NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_stock_movements_material ON stock_movements (material_id, id)");

    // --- فاز ۲٫۵ (نسخه ۷): فرمول ساخت محصول (BOM) — مصرف ماده به‌ازای هر متر یا هر چراغ ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS product_materials (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id  INTEGER NOT NULL,
            material_id INTEGER NOT NULL,
            qty         REAL NOT NULL DEFAULT 0,
            basis       TEXT NOT NULL DEFAULT 'per_meter',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            UNIQUE (product_id, material_id, basis)
        )
    ");

    // --- فاز ۳ (نسخه ۸): سفارش‌ها، پلکان تخفیف، وضعیت‌ها و انبار پرتی ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS price_tiers (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            title            TEXT NOT NULL,
            min_meters       REAL NOT NULL DEFAULT 0,
            max_meters       REAL,
            discount_percent REAL NOT NULL DEFAULT 0,
            applies_to       TEXT NOT NULL DEFAULT 'partner',
            is_active        INTEGER NOT NULL DEFAULT 1,
            sort_order       INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_statuses (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            status_key TEXT NOT NULL UNIQUE,
            title      TEXT NOT NULL,
            color      TEXT NOT NULL DEFAULT '#6b7280',
            is_active  INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            order_no         INTEGER NOT NULL UNIQUE,
            customer_id      INTEGER NOT NULL,
            customer_type    TEXT NOT NULL DEFAULT 'retail',
            source           TEXT NOT NULL DEFAULT 'admin',
            status           TEXT NOT NULL DEFAULT 'new',
            subtotal         INTEGER NOT NULL DEFAULT 0,
            discount_percent REAL NOT NULL DEFAULT 0,
            discount_amount  INTEGER NOT NULL DEFAULT 0,
            total            INTEGER NOT NULL DEFAULT 0,
            total_meters     REAL NOT NULL DEFAULT 0,
            total_fixtures   INTEGER NOT NULL DEFAULT 0,
            prep_days        INTEGER NOT NULL DEFAULT 0,
            notes            TEXT,
            created_by       TEXT,
            created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_customer ON orders (customer_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders (status, id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_items (
            id                  INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id            INTEGER NOT NULL,
            product_id          INTEGER NOT NULL,
            product_name        TEXT NOT NULL DEFAULT '',
            length_cm           REAL NOT NULL DEFAULT 0,
            qty                 INTEGER NOT NULL DEFAULT 1,
            billable_m          REAL NOT NULL DEFAULT 0,
            unit_price_per_m    REAL NOT NULL DEFAULT 0,
            options_json        TEXT,
            options_extra_per_m REAL NOT NULL DEFAULT 0,
            wire_length_cm      REAL NOT NULL DEFAULT 0,
            wire_steps          INTEGER NOT NULL DEFAULT 0,
            wire_extra_total    INTEGER NOT NULL DEFAULT 0,
            has_endcap          INTEGER NOT NULL DEFAULT 0,
            note                TEXT,
            line_subtotal       INTEGER NOT NULL DEFAULT 0,
            line_total          INTEGER NOT NULL DEFAULT 0,
            sort_order          INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items (order_id, id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS order_status_history (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id    INTEGER NOT NULL,
            from_status TEXT,
            to_status   TEXT NOT NULL,
            note        TEXT,
            created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_order_status_history_order ON order_status_history (order_id, id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS material_remnants (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            material_id INTEGER NOT NULL,
            length_cm   REAL NOT NULL DEFAULT 0,
            qty         INTEGER NOT NULL DEFAULT 1,
            source      TEXT NOT NULL DEFAULT 'manual',
            note        TEXT,
            created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_material_remnants_material ON material_remnants (material_id, id)");
    // ستون‌های تازه فاز ۳ روی جدول‌های قدیمی (ارتقای خودکار، بدون حذف داده)
    db_add_column_if_missing($pdo, 'products', 'prep_days', 'INTEGER NOT NULL DEFAULT 0');
    db_add_column_if_missing($pdo, 'product_materials', 'apply_condition', "TEXT NOT NULL DEFAULT 'always'");
    // ستون و تنظیمات نسخه ۸٫۲ (توضیح هر ردیف سفارش + سقف طول سیم)
    db_add_column_if_missing($pdo, 'order_items', 'note', 'TEXT');
    if (get_setting('wire_max_cm', '') === '') {
        set_setting('wire_max_cm', '100');
    }
    // جدول‌های لاگ (نسخه ۸٫۲): بازدید سایت و فعالیت مدیریت + تنظیمات نشست و لاگ
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS visit_logs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at  TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            kind        TEXT NOT NULL DEFAULT 'visit',
            ip          TEXT NOT NULL DEFAULT '',
            user_agent  TEXT NOT NULL DEFAULT '',
            referer     TEXT NOT NULL DEFAULT '',
            path        TEXT NOT NULL DEFAULT '',
            target      TEXT NOT NULL DEFAULT '',
            session_key TEXT NOT NULL DEFAULT ''
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_visit_logs_id ON visit_logs (id DESC)");
    // جدول نشست‌ها (نسخه ۸٫۲٫۱): نشست به‌جای فایل روی دیسک، داخل همین دیتابیس ذخیره می‌شود
    // تا پاک‌سازی دوره‌ای و قفلِ مسیر نشستِ هاست‌های اشتراکی نتواند نشست مدیر را بکشد.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS app_sessions (
            id         TEXT PRIMARY KEY,
            data       TEXT NOT NULL DEFAULT '',
            expires_at INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip         TEXT NOT NULL DEFAULT '',
            action     TEXT NOT NULL DEFAULT '',
            page       TEXT NOT NULL DEFAULT '',
            detail     TEXT NOT NULL DEFAULT '',
            ok         INTEGER NOT NULL DEFAULT 1
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_admin_logs_id ON admin_logs (id DESC)");
    foreach ([
        'session_lifetime_hours' => '168',
        'visit_log_enabled'      => '1',
        'admin_log_enabled'      => '1',
        'log_retention_days'     => '90',
        'log_skip_bots'          => '1',
    ] as $logKey => $logDef) {
        if (get_setting($logKey, '') === '') {
            set_setting($logKey, $logDef);
        }
    }
    if (get_setting('order_line_note', '') === '') {
        set_setting('order_line_note', '1');
    }

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

    // --- فاز ۲٫۵ (نسخه ۷): سید مواد اولیه نمونه (فقط یک بار و فقط وقتی جدول مواد خالی است) ---
    seed_inventory_if_needed($pdo);
    seed_order_rules_if_needed($pdo);
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
    $html = <<<'HTML'
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
    // اسکریپت ردیابی کلیک بازدیدکننده (فقط وقتی لاگ بازدید فعال است)
    $track = tracking_script_html();
    if ($track !== '') {
        $html = str_replace('</body>', $track . "\n</body>", $html);
    }
    return $html;
}

// ---------- نشست، لاگ بازدید و لاگ مدیریت (نسخه ۸٫۲) ----------

/** عمر نشست (ثانیه) از تنظیم پنل؛ پیش‌فرض ۱۶۸ ساعت (یک هفته)، بین ۱ ساعت تا ۳۰ روز. */
function session_lifetime_seconds(): int
{
    try {
        $h = (int) get_setting('session_lifetime_hours', '168');
    } catch (Throwable $ignored) {
        $h = 168;
    }
    if ($h < 1) { $h = 1; }
    if ($h > 720) { $h = 720; }
    return $h * 3600;
}

// نگهدارندهٔ نشست دیتابیسی و شروع نشست (نسخه ۸٫۲٫۱) در session_handler.php است.
require_once __DIR__ . '/session_handler.php';

/** آی‌پی واقعی بازدیدکننده (با احترام به هدر پراکسی هاست‌های اشتراکی). */
function client_ip(): string
{
    $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** تشخیص ساده ربات از روی User-Agent (برای لاگ بازدید تمیزتر). */
function ua_is_bot(string $ua): bool
{
    if ($ua === '') { return true; }
    return (bool) preg_match('/bot|crawler|spider|crawl|slurp|headless|curl|wget|python-requests|scrapy|semrush|ahrefs/i', $ua);
}

/** پاک‌سازی دوره‌ای لاگ‌های قدیمی‌تر از مهلت نگهداری تنظیم‌شده (گاهی، نه هر درخواست). */
function prune_logs_maybe(): void
{
    if (random_int(1, 50) !== 1) { return; }
    try {
        $days = (int) get_setting('log_retention_days', '90');
        if ($days < 1) { $days = 1; }
        if ($days > 3650) { $days = 3650; }
        $cut = "datetime('now', '-" . $days . " days')";
        db()->exec("DELETE FROM visit_logs WHERE created_at < $cut");
        db()->exec("DELETE FROM admin_logs WHERE created_at < $cut");
    } catch (Throwable $ignored) {
    }
}

/**
 * ردیابی درخواست‌های صفحات عمومی: بازدید صفحه (GET) و کلیک‌ها (بیکن JS).
 * در ابتدای index.php و page.php و products.php صدا زده می‌شود؛ برای بیکن کلیک
 * پاسخ 204 می‌دهد و همان‌جا تمام می‌شود تا رندر صفحه انجام نشود.
 */
function track_public_request(): void
{
    try {
        if (get_setting('visit_log_enabled', '1') !== '1') { return; }
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
        if (get_setting('log_skip_bots', '1') === '1' && ua_is_bot($ua)) { return; }
        $sessionKey = '';
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $sessionKey = substr(md5(session_id()), 0, 10);
        }
        if ($method === 'POST' && isset($_POST['track_click'])) {
            // بیکن کلیک: فقط از خود سایت قبول می‌شود
            $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
            if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
                http_response_code(204);
                exit;
            }
            $path = substr((string) ($_POST['p'] ?? ($_SERVER['REQUEST_URI'] ?? '')), 0, 300);
            $target = substr(trim((string) ($_POST['t'] ?? '')), 0, 200);
            db()->prepare("INSERT INTO visit_logs (kind, ip, user_agent, referer, path, target, session_key) VALUES ('click', ?, ?, ?, ?, ?, ?)")
                ->execute([client_ip(), $ua, substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500), $path, $target, $sessionKey]);
            prune_logs_maybe();
            http_response_code(204);
            exit;
        }
        if ($method === 'GET') {
            db()->prepare("INSERT INTO visit_logs (kind, ip, user_agent, referer, path, target, session_key) VALUES ('visit', ?, ?, ?, ?, '', ?)")
                ->execute([client_ip(), $ua, substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500), substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 300), $sessionKey]);
            prune_logs_maybe();
        }
    } catch (Throwable $ignored) {
        // لاگ نباید هیچ‌وقت سایت را از کار بیندازد
    }
}

/** اسکریپت سبک ردیابی کلیک روی لینک‌ها و دکمه‌های صفحات عمومی. */
function tracking_script_html(): string
{
    try {
        if (get_setting('visit_log_enabled', '1') !== '1') { return ''; }
    } catch (Throwable $ignored) {
        return '';
    }
    return <<<'JS'
<script>
(function(){
    document.addEventListener('click',function(ev){
        var el=ev.target;
        while(el&&el!==document.body&&!(el.tagName==='A'||el.tagName==='BUTTON'||(el.getAttribute&&el.getAttribute('role')==='button'))){el=el.parentElement;}
        if(!el||el===document.body){return;}
        var label='';
        if(el.tagName==='A'){label=(el.textContent||'').trim().replace(/\s+/g,' ').slice(0,60);var href=el.getAttribute('href')||'';if(href){label=(label?label+' ← ':'')+href.slice(0,90);}}
        else{label=((el.textContent||el.value||'').trim().replace(/\s+/g,' ').slice(0,60))||(el.getAttribute('aria-label')||'دکمه');}
        if(!label){return;}
        try{
            var body=new URLSearchParams();body.set('track_click','1');body.set('t',label);body.set('p',location.pathname+location.search);
            if(navigator.sendBeacon){navigator.sendBeacon(location.pathname,body);}
            else{fetch(location.pathname,{method:'POST',body:body,keepalive:true}).catch(function(){});}
        }catch(e){}
    },true);
})();
</script>
JS;
}

/** ثبت یک رویداد در لاگ فعالیت مدیریت (ورود، خروج، اکشن‌ها)؛ هرگز جریان پنل را مختل نمی‌کند. */
function log_admin_event(string $action, string $detail = '', bool $ok = true, string $page = ''): void
{
    try {
        if (get_setting('admin_log_enabled', '1') !== '1') { return; }
        if ($page === '') { $page = (string) ($_GET['page'] ?? ''); }
        db()->prepare('INSERT INTO admin_logs (ip, action, page, detail, ok) VALUES (?, ?, ?, ?, ?)')
            ->execute([client_ip(), substr($action, 0, 60), substr($page, 0, 60), substr($detail, 0, 300), $ok ? 1 : 0]);
        prune_logs_maybe();
    } catch (Throwable $ignored) {
    }
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

// سازنده‌های HTML کاتالوگ (catalog_* / product_*_html) در فایل defaults.php هستند تا config.php کوچک بماند.

// =============================================================
// فاز ۲٫۵ (نسخه ۷): مواد اولیه، انبار کارگاه و بهای تمام‌شده محصول
// مبنای قیمت ماده «آخرین قیمت خرید» است: هر ورودِ خرید، قیمت جاری ماده را به‌روز می‌کند.
// هر ردیف فرمول ساخت (BOM) یا «به‌ازای هر متر» است (اعشاری مجاز؛ مثلاً چسب ۰٫۰۲ بسته در متر)
// یا «به‌ازای هر چراغ» (مثل درایور و درپوش که برای هر چراغ/ردیف سفارش یک بار مصرف می‌شوند).
// =============================================================

/** قالب‌بندی مقدار موجودی/مصرف بدون صفرهای اضافی (مثل ۰٫۰۲) */
function format_qty(float $qty): string
{
    $q = round($qty, 4);
    if (floor($q) == $q) {
        return number_format($q, 0, '.', ',');
    }
    return rtrim(rtrim(number_format($q, 4, '.', ','), '0'), '.');
}

function get_materials(bool $onlyActive = false): array
{
    $sql = 'SELECT * FROM materials' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY name ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function get_material(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM materials WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** مواد فعالی که موجودی‌شان به حد هشدار رسیده یا از آن رد شده (فقط وقتی آستانه تعریف شده باشد) */
function low_stock_materials(): array
{
    return db()->query('SELECT * FROM materials WHERE is_active = 1 AND low_stock_threshold > 0 AND stock_qty <= low_stock_threshold ORDER BY stock_qty ASC, name ASC')->fetchAll();
}

/** ارزش کل موجودی انبار با آخرین قیمت خرید هر ماده */
function inventory_total_value(): float
{
    return (float) db()->query('SELECT COALESCE(SUM(stock_qty * last_price), 0) FROM materials')->fetchColumn();
}

/**
 * ثبت گردش انبار و به‌روزرسانی اتمیک موجودی (داخل تراکنش؛ یا همه ثبت می‌شود یا هیچ‌کدام).
 *  - in: خرید/ورود — qty مقدار ورودی (> ۰)؛ اگر unit_price داده شود «آخرین قیمت خرید» ماده هم به‌روز می‌شود.
 *  - out: مصرف/خروج — qty مقدار خروجی (> ۰)؛ موجودی کافی لازم است، وگرنه خطا برمی‌گردد و هیچ حرکتی ثبت نمی‌شود.
 *  - adjust: اصلاح موجودی — qty یعنی «موجودی هدف نهایی» و اختلاف به‌صورت حرکت امضادار ثبت می‌شود.
 * قیمت واحد هر حرکت هم ذخیره می‌شود تا بعداً امکان میانگین‌گیری باشد.
 * خروجی: ['ok' => bool, 'error' => ?string, 'movement_id' => ?int, 'balance_after' => ?float]
 */
function apply_stock_movement(PDO $pdo, int $materialId, string $type, float $qty, ?int $unitPrice = null, string $reason = '', string $refType = '', ?int $refId = null): array
{
    $err = static fn (string $m): array => ['ok' => false, 'error' => $m, 'movement_id' => null, 'balance_after' => null];
    if (!in_array($type, ['in', 'out', 'adjust'], true)) {
        return $err('نوع گردش انبار معتبر نیست.');
    }
    if (!is_finite($qty)) {
        return $err('مقدار گردش معتبر نیست.');
    }
    $stmt = $pdo->prepare('SELECT * FROM materials WHERE id = :id');
    $stmt->execute([':id' => $materialId]);
    $mat = $stmt->fetch();
    if ($mat === false) {
        return $err('ماده اولیه پیدا نشد.');
    }
    $stock     = (float) $mat['stock_qty'];
    $lastPrice = (int) $mat['last_price'];
    $movePrice = $unitPrice ?? $lastPrice;

    if ($type === 'in') {
        if ($qty <= 0) {
            return $err('مقدار ورود باید بزرگ‌تر از صفر باشد.');
        }
        $delta = $qty;
    } elseif ($type === 'out') {
        if ($qty <= 0) {
            return $err('مقدار خروج باید بزرگ‌تر از صفر باشد.');
        }
        if ($qty > $stock + 1e-9) {
            return $err('موجودی کافی نیست؛ موجودی فعلی «' . (string) $mat['name'] . '» ' . format_qty($stock) . ' ' . (string) $mat['unit'] . ' است.');
        }
        $delta = -$qty;
    } else { // adjust — qty موجودی هدف است
        if ($qty < 0) {
            return $err('موجودی هدف نمی‌تواند منفی باشد.');
        }
        $delta = $qty - $stock;
    }
    $delta = round($delta, 6);
    $balanceAfter = round($stock + $delta, 6);
    if ($balanceAfter < 0 && $balanceAfter > -1e-9) {
        $balanceAfter = 0.0;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO stock_movements (material_id, move_type, qty, unit_price, reason, ref_type, ref_id, balance_after) VALUES (:m, :t, :q, :p, :r, :rt, :ri, :b)')
            ->execute([
                ':m'  => $materialId,
                ':t'  => $type,
                ':q'  => $delta,
                ':p'  => $movePrice,
                ':r'  => $reason !== '' ? $reason : null,
                ':rt' => $refType !== '' ? $refType : null,
                ':ri' => $refId,
                ':b'  => $balanceAfter,
            ]);
        $movementId = (int) $pdo->lastInsertId();
        if ($type === 'in' && $unitPrice !== null) {
            // مبنای سیستم: آخرین قیمت خرید
            $pdo->prepare('UPDATE materials SET stock_qty = :s, last_price = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':s' => $balanceAfter, ':p' => $unitPrice, ':id' => $materialId]);
        } else {
            $pdo->prepare('UPDATE materials SET stock_qty = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':s' => $balanceAfter, ':id' => $materialId]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return $err('ثبت گردش انبار انجام نشد: ' . $ex->getMessage());
    }
    return ['ok' => true, 'error' => null, 'movement_id' => $movementId, 'balance_after' => $balanceAfter];
}

/** ردیف‌های فرمول ساخت (BOM) یک محصول همراه مشخصات و قیمت جاری هر ماده */
function product_bom_lines(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT pm.*, m.name AS material_name, m.unit AS material_unit, m.last_price AS material_price, m.stock_qty AS material_stock, m.is_active AS material_active
         FROM product_materials pm
         JOIN materials m ON m.id = pm.material_id
         WHERE pm.product_id = :p
         ORDER BY pm.sort_order ASC, pm.id ASC'
    );
    $stmt->execute([':p' => $productId]);
    return $stmt->fetchAll();
}

/**
 * بهای مواد محصول با آخرین قیمت خرید مواد:
 * ['per_meter' => int, 'per_fixture' => int, 'lines' => [material_id, name, unit, qty, basis, unit_price, line_cost]]
 * بهای «هر چراغ» جدا گزارش می‌شود و عمداً داخل بهای متری قاطی نمی‌شود.
 */
function product_material_cost(int $productId): array
{
    $perMeter = 0.0;
    $perFixture = 0.0;
    $lines = [];
    foreach (product_bom_lines($productId) as $l) {
        $unitPrice = (int) $l['material_price'];
        $q = (float) $l['qty'];
        $lineCost = $q * $unitPrice;
        if ((string) $l['basis'] === 'per_fixture') {
            $perFixture += $lineCost;
        } else {
            $perMeter += $lineCost;
        }
        $lines[] = [
            'material_id' => (int) $l['material_id'],
            'name'        => (string) $l['material_name'],
            'unit'        => (string) $l['material_unit'],
            'qty'         => $q,
            'basis'       => (string) $l['basis'],
            'unit_price'  => $unitPrice,
            'line_cost'   => $lineCost,
        ];
    }
    return ['per_meter' => (int) round($perMeter), 'per_fixture' => (int) round($perFixture), 'lines' => $lines];
}

/**
 * برآورد نیاز مواد برای ساخت یک سفارش از محصول: طول (متر) × مصرف متری + تعداد چراغ × مصرف هر چراغ.
 * هر ردیف: material_id, name, unit, needed, stock, shortage (کمبود = max(۰، نیاز − موجودی))
 */
function product_required_materials(int $productId, float $lengthMeters, int $fixtures = 1): array
{
    if (!is_finite($lengthMeters) || $lengthMeters < 0) {
        $lengthMeters = 0.0;
    }
    if ($fixtures < 0) {
        $fixtures = 0;
    }
    $agg = [];
    foreach (product_bom_lines($productId) as $l) {
        $mid = (int) $l['material_id'];
        if (!isset($agg[$mid])) {
            $agg[$mid] = [
                'material_id' => $mid,
                'name'        => (string) $l['material_name'],
                'unit'        => (string) $l['material_unit'],
                'needed'      => 0.0,
                'stock'       => (float) $l['material_stock'],
            ];
        }
        $agg[$mid]['needed'] += ((string) $l['basis'] === 'per_fixture')
            ? (float) $l['qty'] * $fixtures
            : (float) $l['qty'] * $lengthMeters;
    }
    $out = [];
    foreach ($agg as $r) {
        $r['needed']   = round($r['needed'], 6);
        $r['shortage'] = round(max(0.0, $r['needed'] - $r['stock']), 6);
        $out[] = $r;
    }
    return $out;
}

/**
 * حاشیه سود متری محصول در برابر بهای مواد هر متر (مشتری و همکار).
 * بهای ثابت هر چراغ جدا برگردانده می‌شود تا در تحلیل سفارش (به‌ازای هر ردیف) لحاظ شود.
 */
function product_margin(int $productId): array
{
    $product = get_product($productId);
    $cost = product_material_cost($productId);
    $retail  = $product !== null ? product_base_price_per_meter($product, false) : 0.0;
    $partner = $product !== null ? product_base_price_per_meter($product, true) : 0.0;
    $costPerMeter = (float) $cost['per_meter'];
    $calc = static function (float $price) use ($costPerMeter): array {
        $margin = $price - $costPerMeter;
        return [$margin, $price > 0 ? ($margin / $price) * 100 : 0.0];
    };
    [$retailMargin, $retailPct] = $calc($retail);
    [$partnerMargin, $partnerPct] = $calc($partner);
    return [
        'retail_price'          => $retail,
        'partner_price'         => $partner,
        'cost_per_meter'        => $cost['per_meter'],
        'cost_per_fixture'      => $cost['per_fixture'],
        'retail_margin'         => $retailMargin,
        'retail_margin_percent' => $retailPct,
        'partner_margin'        => $partnerMargin,
        'partner_margin_percent' => $partnerPct,
    ];
}

/** سید مواد اولیه نمونه نسخه ۷ — فقط یک بار (با پرچم تنظیمات) و فقط وقتی جدول مواد خالی است؛ هرگز داده کاربر بازنویسی نمی‌شود */
function seed_inventory_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $flag = $pdo->query("SELECT value FROM settings WHERE key = 'inventory_seeded_v7'")->fetchColumn();
    if ($flag !== false && (string) $flag !== '') {
        return;
    }
    $count = (int) $pdo->query('SELECT COUNT(*) FROM materials')->fetchColumn();
    if ($count === 0) {
        $ins = $pdo->prepare('INSERT INTO materials (name, unit, stock_qty, last_price, low_stock_threshold, notes, is_active) VALUES (:n, :u, 0, 0, 0, :notes, 1)');
        $seed = [
            ['پروفیل آلومینیوم', 'متر'],
            ['نوار LED', 'متر'],
            ['درایور', 'عدد'],
            ['درپوش', 'عدد'],
            ['چسب حرارتی', 'بسته'],
        ];
        foreach ($seed as [$name, $unit]) {
            $ins->execute([':n' => $name, ':u' => $unit, ':notes' => 'ماده نمونه نسخه ۷ — قیمت خرید و موجودی واقعی را از صفحه «مواد اولیه» ثبت کنید.']);
        }
    }
    $pdo->prepare("INSERT INTO settings (key, value) VALUES ('inventory_seeded_v7', '1') ON CONFLICT(key) DO UPDATE SET value = '1'")->execute();
}

// توابع آپدیت یک‌کلیکی گیت‌هاب (backups_dir و update_* و perform_update) در فایل admin_catalog.php هستند؛
// فقط پنل مدیریت از آن‌ها استفاده می‌کند تا config.php کوچک بماند.

// ---------- فاز ۳ (نسخه ۸): سفارش‌ها، پلکان تخفیف، وضعیت‌ها و انبار پرتی ----------
// همه قواعد تجاری (پلکان‌ها، حداقل‌ها، گام سیم، درصد بیعانه و ...) در دیتابیس و از
// پنل «قوانین قیمت‌گذاری» قابل‌ویرایش‌اند؛ در کد هیچ عدد تجاری ثابتی نیست.

/** خواندن یک تنظیم سفارش با مقدار پیش‌فرض */
function order_setting(string $key, $default = '')
{
    return get_setting($key, (string) $default);
}

/** پلکان‌های تخفیف فعال/غیرفعال به ترتیب */
function price_tiers(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM price_tiers';
    if ($onlyActive) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= ' ORDER BY sort_order ASC, min_meters ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/** وضعیت‌های سفارش به ترتیب */
function order_statuses(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM order_statuses';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/** عنوان فارسی یک وضعیت سفارش */
function order_status_title(string $key): string
{
    foreach (order_statuses(false) as $s) {
        if ((string) $s['status_key'] === $key) {
            return (string) $s['title'];
        }
    }
    return $key;
}

/** رنگ یک وضعیت سفارش */
function order_status_color(string $key): string
{
    foreach (order_statuses(false) as $s) {
        if ((string) $s['status_key'] === $key) {
            return (string) ($s['color'] ?? '#6b7280');
        }
    }
    return '#6b7280';
}

/** طول قابل‌صورتحساب هر چراغ (متر) — کمتر از حداقل، همان حداقل حساب می‌شود */
function billable_length_m(float $lengthCm): float
{
    $m = round($lengthCm, 1) / 100.0;
    $min = (float) order_setting('min_billable_m', 0.5);
    return max($m, $min);
}

/** درصد تخفیف پلکانی برای متراژ کل سفارش.
 * قانون مرزها (طبق مثال‌های تأییدشده: 17.9→پلکان ۹–۱۸، 18→پلکان ۱۸–۳۰، 300→پلکان ۱۲۰–۳۰۰، 300.1→پلکان بالای ۳۰۰):
 * کف بازه شامل است، سقف بازه هم شامل است؛ در مرز مشترک دو پلکان، پلکان بالاتر (کف بزرگ‌تر) اعمال می‌شود؛
 * پلکان بدون سقف فقط «بالای» کف خودش اعمال می‌شود (نه روی خود عدد کف).
 */
function tier_for(float $totalMeters, bool $isPartner): float
{
    $best = 0.0;
    $bestMin = -1.0;
    foreach (price_tiers(true) as $t) {
        if ((string) ($t['applies_to'] ?? 'partner') === 'partner' && !$isPartner) {
            continue;
        }
        $min = (float) ($t['min_meters'] ?? 0);
        $max = ($t['max_meters'] ?? null) === null ? null : (float) $t['max_meters'];
        $hit = $totalMeters >= $min && ($max === null ? $totalMeters > $min : $totalMeters <= $max);
        if ($hit && $min >= $bestMin) {
            $bestMin = $min;
            $best = (float) ($t['discount_percent'] ?? 0);
        }
    }
    return $best;
}

/**
 * محاسبه کامل یک سفارش.
 * هر ردیف: ['product_id'=>int, 'length_cm'=>float, 'qty'=>int, 'wire_length_cm'=>float, 'has_endcap'=>bool, 'options'=>[attrId=>optionId]]
 * قرارداد گردکردن (مستند): هر ردیف به نزدیک‌ترین تومان گرد می‌شود، جمع ردیف‌ها = جمع جزء،
 * تخفیف پلکانی روی جمع ردیف‌ها گرد می‌شود، مبلغ نهایی = جمع − تخفیف + اضافه سیم.
 */
function compute_order_totals(array $lines, bool $isPartner): array
{
    $wireStepPrice = (int) order_setting('wire_price_per_step', 0);
    $wireStepCm = (int) order_setting('wire_step_cm', 5);
    $out = [];
    $totalMeters = 0.0;
    $subtotal = 0;
    $wireExtraTotal = 0;
    $fixtures = 0;
    foreach ($lines as $ln) {
        $product = get_product((int) ($ln['product_id'] ?? 0));
        if ($product === null) {
            continue;
        }
        $lengthCm = round((float) ($ln['length_cm'] ?? 0), 1);
        $qty = max(1, (int) ($ln['qty'] ?? 1));
        $billableM = billable_length_m($lengthCm);
        $optIds = [];
        foreach ((array) ($ln['options'] ?? []) as $oid) {
            $oid = (int) $oid;
            if ($oid > 0) {
                $optIds[] = $oid;
            }
        }
        $unit = product_unit_price($product, $optIds, $isPartner);
        $wireCm = (float) ($ln['wire_length_cm'] ?? order_setting('wire_default_cm', 20));
        $wireDefaultCm = (float) order_setting('wire_default_cm', 20);
        // فقط گام‌های بالاتر از طول پیش‌فرض سیم هزینه دارند (سیم استاندارد داخل قیمت پایه است)
        $wireSteps = $wireStepCm > 0 ? max(0, (int) round(($wireCm - $wireDefaultCm) / $wireStepCm)) : 0;
        $wireExtra = $wireSteps * $wireStepPrice * $qty;
        $lineSubtotal = (int) round($billableM * $unit * $qty);
        $lineTotal = $lineSubtotal + $wireExtra;
        $out[] = [
            'product_id' => (int) $product['id'],
            'length_cm' => $lengthCm,
            'qty' => $qty,
            'billable_m' => $billableM,
            'unit_price_per_m' => $unit,
            'options_extra_per_m' => $unit - product_base_price_per_meter($product, $isPartner),
            'wire_length_cm' => $wireCm,
            'wire_steps' => $wireSteps,
            'wire_extra_total' => $wireExtra,
            'has_endcap' => !empty($ln['has_endcap']),
            'note' => trim((string) ($ln['note'] ?? '')),
            'line_subtotal' => $lineSubtotal,
            'line_total' => $lineTotal,
        ];
        $totalMeters += $billableM * $qty;
        $subtotal += $lineSubtotal;
        $wireExtraTotal += $wireExtra;
        $fixtures += $qty;
    }
    $discountPercent = tier_for($totalMeters, $isPartner);
    $discountAmount = (int) round($subtotal * $discountPercent / 100);
    $total = $subtotal - $discountAmount + $wireExtraTotal;
    return [
        'lines' => $out,
        'total_meters' => round($totalMeters, 4),
        'total_fixtures' => $fixtures,
        'subtotal' => $subtotal,
        'discount_percent' => $discountPercent,
        'discount_amount' => $discountAmount,
        'wire_extra_total' => $wireExtraTotal,
        'total' => $total,
    ];
}

/**
 * نیاز مواد یک سفارش (با طول واقعی، نه طول صورتحسابی).
 * هر ردیف سفارش: ['product_id'=>int, 'length_cm'=>float, 'qty'=>int, 'has_endcap'=>bool]
 * مواد با شرط endcap فقط وقتی شمرده می‌شوند که ردیف درپوش داشته باشد.
 */
function order_required_materials(array $orderLines): array
{
    $acc = [];
    foreach ($orderLines as $ln) {
        $pid = (int) ($ln['product_id'] ?? 0);
        $qty = max(1, (int) ($ln['qty'] ?? 1));
        $lengthM = round((float) ($ln['length_cm'] ?? 0), 1) / 100.0;
        $hasEndcap = !empty($ln['has_endcap']);
        foreach (product_bom_lines($pid) as $bom) {
            if (($bom['apply_condition'] ?? 'always') === 'endcap' && !$hasEndcap) {
                continue;
            }
            $mid = (int) $bom['material_id'];
            $need = $bom['basis'] === 'per_fixture' ? (float) $bom['qty'] * $qty : (float) $bom['qty'] * $lengthM * $qty;
            if (!isset($acc[$mid])) {
                $mat = get_material($mid);
                $acc[$mid] = [
                    'material_id' => $mid,
                    'name' => (string) ($mat['name'] ?? ('#' . $mid)),
                    'unit' => (string) ($mat['unit'] ?? ''),
                    'needed' => 0.0,
                    'stock' => (float) ($mat['stock_qty'] ?? 0),
                ];
            }
            $acc[$mid]['needed'] += $need;
        }
    }
    foreach ($acc as &$r) {
        $r['shortage'] = max(0.0, (float) $r['needed'] - (float) $r['stock']);
    }
    unset($r);
    return array_values($acc);
}

/** سید قواعد فاز ۳ — فقط یک بار (با پرچم تنظیمات)؛ هرگز داده کاربر بازنویسی نمی‌شود */
function seed_order_rules_if_needed(PDO $pdo): void
{
    if (get_setting('seeded_order_rules_v8', '') === '1') {
        return;
    }
    // پلکان‌های تخفیف همکار (۶ پلکان پیش‌فرض، کاملاً قابل‌ویرایش از پنل)
    if ((int) $pdo->query('SELECT COUNT(*) FROM price_tiers')->fetchColumn() === 0) {
        $tiers = [
            ['۹ تا ۱۸ متر', 9, 18, 3],
            ['۱۸ تا ۳۰ متر', 18, 30, 5],
            ['۳۰ تا ۶۰ متر', 30, 60, 7],
            ['۶۰ تا ۱۲۰ متر', 60, 120, 9],
            ['۱۲۰ تا ۳۰۰ متر', 120, 300, 12],
            ['بالای ۳۰۰ متر', 300, null, 15],
        ];
        $ins = $pdo->prepare('INSERT INTO price_tiers (title, min_meters, max_meters, discount_percent, applies_to, is_active, sort_order) VALUES (:t, :min, :max, :pct, :ap, 1, :s)');
        $s = 0;
        foreach ($tiers as $tr) {
            $s += 10;
            $ins->execute([':t' => $tr[0], ':min' => $tr[1], ':max' => $tr[2], ':pct' => $tr[3], ':ap' => 'partner', ':s' => $s]);
        }
    }
    // وضعیت‌های سفارش
    if ((int) $pdo->query('SELECT COUNT(*) FROM order_statuses')->fetchColumn() === 0) {
        $statuses = [
            ['new', 'جدید', '#2563eb'],
            ['confirmed', 'تأییدشده', '#0891b2'],
            ['in_production', 'در حال تولید', '#d97706'],
            ['ready', 'آماده ارسال', '#7c3aed'],
            ['shipped', 'ارسال‌شده', '#059669'],
            ['delivered', 'تحویل‌شده', '#16a34a'],
            ['cancelled', 'لغوشده', '#dc2626'],
        ];
        $ins = $pdo->prepare('INSERT INTO order_statuses (status_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, 1, :s)');
        $s = 0;
        foreach ($statuses as $st) {
            $s += 10;
            $ins->execute([':k' => $st[0], ':t' => $st[1], ':c' => $st[2], ':s' => $s]);
        }
    }
    // ویژگی «رنگ پروفیل» (سفید/مشکی) — فقط اگر هنوز ساخته نشده باشد (ارتقای نصب‌های قدیمی)
    $hasProfileColor = (int) $pdo->query("SELECT COUNT(*) FROM product_attributes WHERE attr_key = 'profile_color' OR title = 'رنگ پروفیل'")->fetchColumn();
    if ($hasProfileColor === 0) {
        $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM product_attributes')->fetchColumn();
        $pdo->prepare("INSERT INTO product_attributes (title, attr_key, input_type, unit, sort_order, is_active) VALUES ('رنگ پروفیل', 'profile_color', 'select', '', :o, 1)")
            ->execute([':o' => $maxSort + 10]);
        $pcId = (int) $pdo->lastInsertId();
        $insO = $pdo->prepare('INSERT INTO product_attribute_options (attribute_id, title, price_delta_per_meter, sort_order) VALUES (:a, :t, 0, :o)');
        $insO->execute([':a' => $pcId, ':t' => 'سفید', ':o' => 10]);
        $insO->execute([':a' => $pcId, ':t' => 'مشکی', ':o' => 20]);
    }
    // تنظیمات عددی/متنی پیش‌فرض فاز ۳ (فقط اگر کاربر چیزی ست نکرده باشد)
    $defaults = [
        'partner_min_bars' => '3',
        'bar_length_m' => '3',
        'min_billable_m' => '0.5',
        'wire_default_cm' => '20',
        'wire_step_cm' => '5',
        'wire_price_per_step' => '0',
        'wire_max_cm' => '100',
        'order_line_note' => '1',
        'remnant_min_cm' => '20',
        'default_prep_days' => '3',
        'deposit_percent' => '50',
        'orders_public' => '1',
        'enforce_min_partner' => 'warn',
        'next_order_no' => '1001',
        'payment_terms' => '',
        'warranty_text' => '',
        'qc_text' => '',
    ];
    foreach ($defaults as $k => $v) {
        if (get_setting($k, '') === '') {
            set_setting($k, $v);
        }
    }
    set_setting('seeded_order_rules_v8', '1');
}
