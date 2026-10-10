<?php
// config.php — اتصال دیتابیس و توابع کمکی مشترک (نسخه ۶)
// همه فایل‌های این پروژه در یک فولدر کنار هم قرار دارند؛ عکس‌های آپلودی داخل فولدر uploads همان فولدر است.
// نسخه ۵: محتوای قالب‌ها و CSS سایت داخل دیتابیس نگهداری می‌شود؛ فقط اسکلت صفحه در کد باقی مانده است.
// نسخه ۶ (فاز ۲): مشتری‌ها، دسته‌بندی و کاتالوگ محصول با قیمت متری + آپشن + قیمت همکار و ماشین‌حساب قیمت.
// نسخه ۷ (فاز ۲٫۵): مواد اولیه، انبار کارگاه، فرمول ساخت محصول (BOM) و بهای تمام‌شده بر پایه «آخرین قیمت خرید».
// نسخه ۸ (فاز ۳): سفارش‌ها (همکار/مشتری) با ردیف‌های طول‌دار، تخفیف پلکانی متراژ، سیم و درپوش، پیش‌فاکتور چاپی، وضعیت‌ها و انبار پرتی.

declare(strict_types=1);

define('APP_VERSION', '9.99.42');
// روت برنامه (یک سطح بالاتر از core/)
define('APP_ROOT', dirname(__DIR__));
define('DB_FILE', APP_ROOT . '/database.sqlite');
define('UPLOADS_DIR', APP_ROOT . '/uploads');
define('UPLOADS_URL', 'uploads');

// قالب‌ها و CSS کارخانه‌ای در فایل جدا هستند تا هر فایل برای آپدیت گیت‌هاب کوچک بماند
require_once __DIR__ . '/defaults.php';

/**
 * اتصال PDO به SQLite (فایل در همان فولدر ساخته می‌شود)
 */

/** مایگریشن ستون template برای سیستم قالب (۹٫۹۹٫۱۲) */
function migrate_template_columns(): void
{
    try {
        $tables = ['blog_posts', 'pages', 'products', 'product_categories'];
        foreach ($tables as $t) {
            $cols = [];
            $stmt = db()->query("PRAGMA table_info($t)");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
                $cols[] = $c['name'];
            }
            if (!in_array('template', $cols, true)) {
                db()->exec("ALTER TABLE $t ADD COLUMN template TEXT NOT NULL DEFAULT ''");
            }
        }
    } catch (Throwable $e) { /* silent */ }
}

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
    migrate_template_columns();
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
            updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            credit_limit INTEGER NOT NULL DEFAULT 0
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
            seo_title               TEXT,
            seo_description         TEXT,
            seo_keywords            TEXT,
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

    // --- فاز ۴ (نسخه ۸٫۳): تولید — برگه تولید، مراحل کارگاه، مصرف مواد و لیست برش ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_stages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            stage_key  TEXT NOT NULL UNIQUE,
            title      TEXT NOT NULL,
            color      TEXT NOT NULL DEFAULT '#6b7280',
            is_active  INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_orders (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            production_no INTEGER NOT NULL UNIQUE,
            order_id      INTEGER NOT NULL,
            stage_key     TEXT NOT NULL DEFAULT 'queued',
            state         TEXT NOT NULL DEFAULT 'open',
            responsible   TEXT,
            notes         TEXT,
            plan_json     TEXT,
            started_at    TEXT,
            finished_at   TEXT,
            cancelled_at  TEXT,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_production_orders_order ON production_orders (order_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_production_orders_state ON production_orders (state, id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_stage_history (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            production_id INTEGER NOT NULL,
            from_stage    TEXT,
            to_stage      TEXT NOT NULL,
            responsible   TEXT,
            note          TEXT,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_production_history ON production_stage_history (production_id, id)");
    // مصرف ثبت‌شده هر برگه تولید تا لغو تولید دقیقاً همان مصرف را برگرداند:
    // stock_out = خروج از موجودی اصلی (qty به واحد ماده)، remnant_use = مصرف یک تکه پرت،
    // remnant_new = پرت تازه‌ای که از برش برگشته (remnant_id آن نگه داشته می‌شود).
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS production_consumptions (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            production_id INTEGER NOT NULL,
            material_id   INTEGER NOT NULL,
            kind          TEXT NOT NULL DEFAULT 'stock_out',
            qty           REAL NOT NULL DEFAULT 0,
            movement_id   INTEGER,
            remnant_id    INTEGER,
            detail        TEXT,
            reversed      INTEGER NOT NULL DEFAULT 0,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_production_consumptions ON production_consumptions (production_id, id)");

    // --- فاز ۵ (نسخه ۸٫۴): مالی — فاکتور، دریافتی‌ها، هزینه‌ها و سود سفارش ---
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payment_methods (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            method_key TEXT NOT NULL UNIQUE,
            title      TEXT NOT NULL,
            is_active  INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id   INTEGER NOT NULL,
            customer_id INTEGER NOT NULL,
            amount     INTEGER NOT NULL DEFAULT 0,
            method_key TEXT NOT NULL DEFAULT 'cash',
            kind       TEXT NOT NULL DEFAULT 'receipt',
            note       TEXT,
            paid_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_order ON payments (order_id, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_payments_customer ON payments (customer_id, id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL,
            endpoint   TEXT NOT NULL UNIQUE,
            p256dh     TEXT NOT NULL,
            auth       TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_push_user ON push_subscriptions (user_id)");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS invoices (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            invoice_no  INTEGER NOT NULL UNIQUE,
            order_id    INTEGER NOT NULL UNIQUE,
            vat_applied INTEGER NOT NULL DEFAULT 0,
            vat_percent REAL NOT NULL DEFAULT 0,
            subtotal    INTEGER NOT NULL DEFAULT 0,
            discount    INTEGER NOT NULL DEFAULT 0,
            vat_amount  INTEGER NOT NULL DEFAULT 0,
            total       INTEGER NOT NULL DEFAULT 0,
            note        TEXT,
            issued_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_categories (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            cat_key    TEXT NOT NULL UNIQUE,
            title      TEXT NOT NULL,
            color      TEXT NOT NULL DEFAULT '#6b7280',
            is_active  INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0
        )
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expenses (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            cat_key      TEXT NOT NULL DEFAULT 'other',
            title        TEXT NOT NULL,
            amount       INTEGER NOT NULL DEFAULT 0,
            order_id     INTEGER,
            status       TEXT NOT NULL DEFAULT 'confirmed',
            source       TEXT NOT NULL DEFAULT 'manual',
            source_id    INTEGER,
            expense_date TEXT,
            note         TEXT,
            created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            confirmed_at TEXT
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_status ON expenses (status, id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_expenses_order ON expenses (order_id, id)");
    // طول واحد تازه (شاخه/رول) بر حسب سانتی‌متر برای مواد برش‌خور؛ ۰ یعنی بدون برش
    db_add_column_if_missing($pdo, 'materials', 'cut_unit_cm', 'REAL NOT NULL DEFAULT 0');
    // نام واحد تازه برای لیست برش (شاخه، رول، بسته…) — ۸٫۵٫۰؛ کاملاً از پنل قابل‌ویرایش
    db_add_column_if_missing($pdo, 'materials', 'cut_unit_label', "TEXT NOT NULL DEFAULT 'واحد'");
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
        CREATE TABLE IF NOT EXISTS modules (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            module_key  TEXT NOT NULL UNIQUE,
            name        TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            version     TEXT NOT NULL DEFAULT '1.0.0',
            enabled     INTEGER NOT NULL DEFAULT 1,
            is_core     INTEGER NOT NULL DEFAULT 0,
            category    TEXT NOT NULL DEFAULT '',
            icon        TEXT NOT NULL DEFAULT '',
            file_path   TEXT NOT NULL DEFAULT '',
            sort_order  INTEGER NOT NULL DEFAULT 0,
            created_at  INTEGER NOT NULL DEFAULT 0,
            updated_at  INTEGER NOT NULL DEFAULT 0
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
    // --- کاربران و نقش‌های پنل (نسخه ۸٫۷٫۰): ورود چندکاربره به‌جای تک‌پسورد ---
    // جدول همیشه ساخته می‌شود؛ اگر خالی باشد و هش پسورد قدیمی مدیریت موجود باشد،
    // کاربر مالک «admin» با همان هش ساخته می‌شود تا بعد از ارتقا هیچ‌کس بیرون نماند.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            username      TEXT NOT NULL UNIQUE,
            pass_hash     TEXT NOT NULL DEFAULT '',
            display_name  TEXT NOT NULL DEFAULT '',
            role_key      TEXT NOT NULL DEFAULT 'owner',
            is_active     INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            last_login_at TEXT NOT NULL DEFAULT ''
        )
    ");
    try {
        $adminUserCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($adminUserCount === 0) {
            $legacyAdminHash = trim((string) get_setting('admin_password_hash', ''));
            if ($legacyAdminHash !== '') {
                $pdo->prepare("INSERT INTO admin_users (username, pass_hash, display_name, role_key, is_active) VALUES ('admin', ?, 'مدیر', 'owner', 1)")
                    ->execute([$legacyAdminHash]);
            }
        }
    } catch (Throwable $ignored) {
    }
    foreach ([
        'session_lifetime_hours' => '168',
        'visit_log_enabled'      => '1',
        'admin_log_enabled'      => '1',
        'log_retention_days'     => '90',
        'log_timezone'           => 'Asia/Tehran',
        'cut_kerf_mm'            => '5',
        'site_theme'             => 'cinematic',
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
        'seo_keywords'        => 'چراغ خطی, نور خطی, لاینرلایت, نورپردازی کمد, نورپردازی کابینت',
        'site_url'            => 'https://linerlight.ir',
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
        // فاز ۵ (نسخه ۸٫۴): مالی — شماره فاکتور بعدی و درصد مالیات بر ارزش افزوده
        'next_invoice_no'     => '1',
        'vat_percent'         => '10',
        // اطلاعات تماس (نسخه ۹٫۶): نمایش در صفحه تماس با ما
        'contact_phone'       => '+989366121221',
        'contact_email'       => 'info@linerlight.ir',
        'contact_whatsapp'    => '',
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

    // --- نسخه ۸٫۸٫۱: صفحه «گالری پروژه‌ها» (فقط اگر با همین اسلاگ وجود نداشته باشد) ---
    $galleryExists = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE slug = 'gallery'")->fetchColumn();
    if ($galleryExists === 0) {
        $galleryHtml = <<<'GALLERYHTML'
<p>نمونه‌ای از پروژه‌های اجراشده با چراغ‌های خطی لاینرلایت؛ از نورپردازی راه‌پله و فضای اداری تا ویترین فروشگاه و سالن‌های مسکونی.</p>
<style>
.ll-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:14px;margin:18px 0}
.ll-gallery figure{margin:0;border-radius:12px;overflow:hidden;background:#f3f4f6;cursor:zoom-in;position:relative;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.ll-gallery img{width:100%;height:230px;object-fit:cover;display:block;transition:transform .35s ease}
.ll-gallery figure:hover img{transform:scale(1.05)}
.ll-gallery figcaption{padding:10px 12px;font-size:13px;color:#374151;background:#fff}
.ll-lightbox{position:fixed;inset:0;background:rgba(0,0,0,.88);display:none;align-items:center;justify-content:center;z-index:9999;flex-direction:column}
.ll-lightbox.open{display:flex}
.ll-lightbox img{max-width:92vw;max-height:82vh;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.ll-lightbox .ll-cap{color:#e5e7eb;margin-top:12px;font-size:14px}
.ll-lightbox .ll-close{position:absolute;top:18px;left:22px;color:#fff;font-size:34px;cursor:pointer;background:none;border:0;line-height:1}
.ll-lightbox .ll-nav{position:absolute;top:50%;transform:translateY(-50%);color:#fff;font-size:40px;cursor:pointer;background:rgba(255,255,255,.12);border:0;border-radius:50%;width:52px;height:52px;line-height:1}
.ll-lightbox .ll-prev{right:16px}
.ll-lightbox .ll-next{left:16px}
</style>
<div class="ll-gallery" id="llGallery">
<figure><img loading="lazy" src="uploads/gallery/gallery-01.jpg" alt="نور خطی راه‌پله"><figcaption>نور خطی راه‌پله</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-02.jpg" alt="چراغ خطی آویز فروشگاهی"><figcaption>چراغ خطی آویز فروشگاهی</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-03.jpg" alt="نور خطی سقفی فضای اداری"><figcaption>نور خطی سقفی فضای اداری</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-04.jpg" alt="نور مخفی ویترین فروشگاه"><figcaption>نور مخفی ویترین فروشگاه</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-05.jpg" alt="چراغ خطی سقفی سالن"><figcaption>چراغ خطی سقفی سالن</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-06.jpg" alt="ترکیب چراغ خطی و ریلی"><figcaption>ترکیب چراغ خطی و ریلی</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-07.jpg" alt="نور مخفی سقفی"><figcaption>نور مخفی سقفی</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-08.jpg" alt="چراغ ریلی و خطی راهرو"><figcaption>چراغ ریلی و خطی راهرو</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-09.jpg" alt="نور خطی فضای کار"><figcaption>نور خطی فضای کار</figcaption></figure>
<figure><img loading="lazy" src="uploads/gallery/gallery-10.jpg" alt="چراغ خطی آویز سالن جلسات"><figcaption>چراغ خطی آویز سالن جلسات</figcaption></figure>
</div>
<div class="ll-lightbox" id="llLightbox" role="dialog" aria-label="نمایش بزرگ عکس">
<button class="ll-close" id="llClose" aria-label="بستن">×</button>
<button class="ll-nav ll-prev" id="llPrev" aria-label="قبلی">‹</button>
<img id="llImg" src="" alt="">
<button class="ll-nav ll-next" id="llNext" aria-label="بعدی">›</button>
<div class="ll-cap" id="llCap"></div>
</div>
<script>
(function(){
var g=document.getElementById('llGallery');if(!g)return;
var figs=Array.prototype.slice.call(g.querySelectorAll('figure'));
var lb=document.getElementById('llLightbox'),im=document.getElementById('llImg'),cap=document.getElementById('llCap'),idx=0;
function show(i){idx=(i+figs.length)%figs.length;var f=figs[idx],p=f.querySelector('img');im.src=p.src;im.alt=p.alt;cap.textContent=f.querySelector('figcaption').textContent;lb.classList.add('open');document.body.style.overflow='hidden';}
function hide(){lb.classList.remove('open');document.body.style.overflow='';}
figs.forEach(function(f,i){f.addEventListener('click',function(){show(i);});});
document.getElementById('llClose').addEventListener('click',hide);
document.getElementById('llPrev').addEventListener('click',function(e){e.stopPropagation();show(idx-1);});
document.getElementById('llNext').addEventListener('click',function(e){e.stopPropagation();show(idx+1);});
lb.addEventListener('click',function(e){if(e.target===lb)hide();});
document.addEventListener('keydown',function(e){if(!lb.classList.contains('open'))return;if(e.key==='Escape')hide();if(e.key==='ArrowLeft')show(idx+1);if(e.key==='ArrowRight')show(idx-1);});
})();
</script>
GALLERYHTML;
        $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t,:s,:c,:st,:sd,1,20,1)')
            ->execute([
                ':t'  => 'گالری پروژه‌ها',
                ':s'  => 'gallery',
                ':c'  => $galleryHtml,
                ':st' => 'گالری پروژه‌ها | لاینرلایت',
                ':sd' => 'نمونه پروژه‌های اجراشده با چراغ‌های خطی لاینرلایت؛ نورپردازی راه‌پله، فضای اداری، فروشگاه و مسکونی.',
            ]);
    }

    // --- نسخه ۹٫۶: صفحه «تماس با ما» (فقط اگر با همین اسلاگ وجود نداشته باشد) ---
    $contactPageExists = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE slug = 'contact'")->fetchColumn();
    if ($contactPageExists === 0) {
        $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t,:s,:c,:st,:sd,1,40,1)')
            ->execute([
                ':t'  => 'تماس با ما',
                ':s'  => 'contact',
                ':c'  => '',
                ':st' => 'تماس با ما | لاینرلایت',
                ':sd' => 'راه‌های ارتباط با لاینرلایت؛ تلفن، واتساپ و ایمیل. برای مشاوره و ثبت سفارش با ما در تماس باشید.',
            ]);
    }

    // --- نسخه ۹٫۱: صفحه «همکاری با ما» (فقط اگر با همین اسلاگ وجود نداشته باشد) ---
    $partnerPageExists = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE slug = 'partner'")->fetchColumn();
    if ($partnerPageExists === 0) {
        $partnerHtml = <<<'PARTNERHTML'
<p>اگر در زمینه نورپردازی، دکوراسیون، کابینت‌سازی یا برق فعالیت می‌کنید، می‌توانید به‌عنوان همکار لاینرلایت ثبت‌نام کنید و از تخفیف همکار بهره‌مند شوید. فرم زیر را پر کنید تا با شما تماس بگیریم.</p>
{{partner_form}}
PARTNERHTML;
        $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t,:s,:c,:st,:sd,1,30,1)')
            ->execute([
                ':t'  => 'همکاری با ما',
                ':s'  => 'partner',
                ':c'  => $partnerHtml,
                ':st' => 'همکاری با ما | لاینرلایت',
                ':sd' => 'ثبت‌نام همکاران لاینرلایت؛ فعالان نورپردازی، دکوراسیون و کابینت‌سازی از تخفیف همکار بهره‌مند شوند.',
            ]);
    }

    // --- نسخه ۵: سید قالب‌ها و CSS داخل دیتابیس (فقط آیتم‌های غایب؛ داده کاربر دست نمی‌خورد) ---
    seed_design_if_needed($pdo);

    // --- فاز ۲ (نسخه ۶): سید دسته‌ها و ویژگی‌های پیش‌فرض + CSS کاتالوگ (فقط وقتی خالی/غایب است) ---
    // نسخه ۸٫۹٫۲: محافظت‌شده — خطا در سید هرگز نباید سایت را از کار بیندازد
    try {
        seed_catalog_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('catalog seed failed: ' . $e->getMessage());
    }

    // --- فاز ۲٫۵ (نسخه ۷): سید مواد اولیه نمونه (فقط یک بار و فقط وقتی جدول مواد خالی است) ---
    seed_inventory_if_needed($pdo);
    seed_order_rules_if_needed($pdo);
    seed_production_if_needed($pdo);
    seed_finance_if_needed($pdo);

    // --- نسخه ۸٫۲٫۳: افزودن استایل تازه فرم ثبت سفارش به CSS دیتابیس (یک بار؛ نسخه قبلی آرشیو می‌شود) ---
    seed_order_form_css_v823_if_needed($pdo);

    // --- نسخه ۸٫۹٫۰: تم سینمایی لاینرلایت (یک بار؛ قالب‌ها و CSS قبلی آرشیو می‌شوند) ---
    // نسخه ۸٫۹٫۱: محافظت‌شده — خطا در مهاجرت هرگز نباید سایت را از کار بیندازد
    try {
        seed_cinematic_theme_v890_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('cinematic theme migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۸٫۹٫۱: لوگوی تصویری در هدر ---
    try {
        seed_header_logo_v891_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('header logo migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۸٫۹٫۳: اصلاح مسیر لوگو/فاوآیکون ---
    try {
        seed_logo_path_v893_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('logo path migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۸٫۹٫۴: بهبود نوار متحرک ---
    try {
        seed_marquee_v894_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('marquee migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۸٫۱۰٫۰: سئوی حرفه‌ای ---
    try {
        seed_product_seo_v810_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('product seo migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹: ویترین محصولات، گالری، صفحه‌ساز ---
    try {
        seed_v9_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('v9 migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۰٫۳: رفع CSS ویترین ---
    try {
        seed_v903_css_fix_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('v903 css fix failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۰٫۴: تم روشن/تیره ---
    try {
        seed_v904_theme_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('v904 theme failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۰٫۵: به‌روزرسانی CSS ---
    try {
        seed_v905_css_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('v905 css failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: به‌روزرسانی CSS ---
    try {
        seed_v91_css_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('v91 css failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: جدول درخواست‌های همکاری ---
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS partner_requests (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                manager_name     TEXT NOT NULL,
                business_name    TEXT NOT NULL,
                field_of_activity TEXT NOT NULL,
                phone            TEXT NOT NULL,
                email            TEXT,
                address          TEXT,
                status           TEXT NOT NULL DEFAULT 'new',
                created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_partner_requests_status ON partner_requests (status, created_at)");
    } catch (Throwable $e) {
        error_log('partner_requests table failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: جدول تصاویر محصول ---
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS product_images (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                image      TEXT NOT NULL,
                caption    TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_product_images_product ON product_images (product_id, sort_order)");
    } catch (Throwable $e) {
        error_log('product_images table failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: ستون تنظیمات فرم سفارش محصول ---
    try {
        $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('order_form_config', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN order_form_config TEXT");
        }
    } catch (Throwable $e) {
        error_log('order_form_config column failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: جدول فیلدهای فرم سفارش ---
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS order_form_fields (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                owner_type   TEXT NOT NULL DEFAULT 'product',
                owner_id     INTEGER NOT NULL DEFAULT 0,
                field_type   TEXT NOT NULL DEFAULT 'text',
                label        TEXT NOT NULL,
                options_json TEXT,
                placeholder  TEXT NOT NULL DEFAULT '',
                help_text    TEXT NOT NULL DEFAULT '',
                is_required  INTEGER NOT NULL DEFAULT 0,
                sort_order   INTEGER NOT NULL DEFAULT 0,
                is_active    INTEGER NOT NULL DEFAULT 1
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_off_owner ON order_form_fields (owner_type, owner_id, sort_order)");
    } catch (Throwable $e) {
        error_log('order_form_fields table failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: ستون مقادیر فیلدهای سفارشی در آیتم‌های سفارش ---
    try {
        $cols = $pdo->query("PRAGMA table_info(order_items)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('custom_fields_json', $cols, true)) {
            $pdo->exec("ALTER TABLE order_items ADD COLUMN custom_fields_json TEXT");
        }
    } catch (Throwable $e) {
        error_log('custom_fields_json column failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱٫۱۵: تنظیمات نقشه سایت ---
    try {
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_enabled', '1')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_home_freq', 'daily')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_home_priority', '1.0')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_pages_freq', 'weekly')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_pages_priority', '0.8')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_products_freq', 'weekly')");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('sitemap_products_priority', '0.7')");
    } catch (Throwable $e) {
        error_log('sitemap settings failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱٫۱۲: تنظیم واحد طول (میلی‌متر) ---
    try {
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('length_unit', 'mm')");
    } catch (Throwable $e) {
        error_log('length_unit setting failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱٫۱۰: ستون‌های قیمت‌گذاری کاستم (پر وات + قاب) ---
    try {
        $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('pricing_model', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN pricing_model TEXT NOT NULL DEFAULT 'per_meter'");
        }
        if (!in_array('price_per_watt', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN price_per_watt INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('frame_options_json', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN frame_options_json TEXT");
        }
        if (!in_array('base_price', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN base_price INTEGER NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        error_log('custom pricing columns failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱: ستون‌های هزینه تولید در جدول محصولات ---
    try {
        $cols = $pdo->query("PRAGMA table_info(products)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('labor_cost_per_meter', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN labor_cost_per_meter REAL NOT NULL DEFAULT 0");
        }
        if (!in_array('labor_cost_per_fixture', $cols, true)) {
            $pdo->exec("ALTER TABLE products ADD COLUMN labor_cost_per_fixture REAL NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {
        error_log('labor cost columns failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۲٫۰: ستون‌های تشخیص ربات و ادمین در لاگ بازدید ---
    try {
        $cols = $pdo->query("PRAGMA table_info(visit_logs)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('is_bot', $cols, true)) {
            $pdo->exec("ALTER TABLE visit_logs ADD COLUMN is_bot INTEGER NOT NULL DEFAULT 0");
            // پر کردن برای رکوردهای قدیمی بر اساس user_agent
            $pdo->exec("UPDATE visit_logs SET is_bot = 1 WHERE lower(user_agent) LIKE '%bot%' OR lower(user_agent) LIKE '%crawl%' OR lower(user_agent) LIKE '%spider%' OR lower(user_agent) LIKE '%slurp%' OR user_agent = '' OR lower(user_agent) LIKE '%headless%' OR lower(user_agent) LIKE '%curl%' OR lower(user_agent) LIKE '%wget%' OR lower(user_agent) LIKE '%python-requests%' OR lower(user_agent) LIKE '%scrapy%' OR lower(user_agent) LIKE '%semrush%' OR lower(user_agent) LIKE '%ahrefs%'");
        }
        if (!in_array('admin_user', $cols, true)) {
            $pdo->exec("ALTER TABLE visit_logs ADD COLUMN admin_user TEXT NOT NULL DEFAULT ''");
        }
    } catch (Throwable $e) {
        error_log('visit_logs bot/admin columns failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۸٫۲: ستون‌های کشور/دستگاه/مرورگر در لاگ بازدید ---
    try {
        $cols = $pdo->query("PRAGMA table_info(visit_logs)")->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach ([
            'country_code' => "ALTER TABLE visit_logs ADD COLUMN country_code TEXT NOT NULL DEFAULT ''",
            'country_name' => "ALTER TABLE visit_logs ADD COLUMN country_name TEXT NOT NULL DEFAULT ''",
            'device_type'  => "ALTER TABLE visit_logs ADD COLUMN device_type TEXT NOT NULL DEFAULT ''",
            'browser_name' => "ALTER TABLE visit_logs ADD COLUMN browser_name TEXT NOT NULL DEFAULT ''",
        ] as $col => $sql) {
            if (!in_array($col, $cols, true)) {
                $pdo->exec($sql);
            }
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ip_country_cache (
            ip TEXT PRIMARY KEY,
            country_code TEXT NOT NULL DEFAULT '',
            country_name TEXT NOT NULL DEFAULT '',
            cached_at INTEGER NOT NULL DEFAULT 0
        )");
    } catch (Throwable $e) {
        error_log('visit_logs geo columns failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۰: جدول اعلان‌های مدیریتی ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL DEFAULT '',
            title TEXT NOT NULL DEFAULT '',
            message TEXT NOT NULL DEFAULT '',
            link TEXT NOT NULL DEFAULT '',
            is_read INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Throwable $e) {
        error_log('notifications table failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۸: پرچم راه‌اندازی برای نصب‌های موجود ---
    try {
        $setupDone = $pdo->query("SELECT value FROM settings WHERE key = 'setup_completed'")->fetchColumn();
        if ($setupDone === false) {
            $userCount = 0;
            try { $userCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(); } catch (Throwable $ignored) {}
            if ($userCount > 0) {
                $pdo->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES ('setup_completed', '1')")->execute();
            }
        }
    } catch (Throwable $e) {
        error_log('setup_completed migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۷: جدول‌های منابع انسانی و تجهیزات ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS employees (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            position TEXT NOT NULL DEFAULT '',
            mobile TEXT NOT NULL DEFAULT '',
            national_id TEXT NOT NULL DEFAULT '',
            hire_date TEXT NOT NULL DEFAULT '',
            base_salary INTEGER NOT NULL DEFAULT 0,
            employment_type TEXT NOT NULL DEFAULT 'full_time',
            status TEXT NOT NULL DEFAULT 'active',
            notes TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS salary_payments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id INTEGER NOT NULL REFERENCES employees(id),
            pay_month TEXT NOT NULL,
            base_amount INTEGER NOT NULL DEFAULT 0,
            bonus INTEGER NOT NULL DEFAULT 0,
            deduction INTEGER NOT NULL DEFAULT 0,
            net_amount INTEGER NOT NULL DEFAULT 0,
            paid_date TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS equipment (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT '',
            purchase_date TEXT NOT NULL DEFAULT '',
            purchase_price INTEGER NOT NULL DEFAULT 0,
            useful_life_years REAL NOT NULL DEFAULT 5,
            status TEXT NOT NULL DEFAULT 'active',
            location TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE TABLE IF NOT EXISTS equipment_maintenance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            equipment_id INTEGER NOT NULL REFERENCES equipment(id),
            maint_date TEXT NOT NULL,
            maint_type TEXT NOT NULL DEFAULT 'repair',
            cost INTEGER NOT NULL DEFAULT 0,
            description TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
    } catch (Throwable $e) {
        error_log('hr/assets tables migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۴٫۲: یکسان‌سازی خالص فیش‌های قدیمی با فرمول کامل ---
    // خالص ذخیره‌شده قدیمی با فرمول ساده (پایه+پاداش−کسورات) بود؛ حالا با فرمول
    // کامل (مزایا − بیمه − مالیات) بازمحاسبه می‌شود تا با داخل فیش یکی باشد.
    try {
        if (get_setting('payslip_recalc_9142', '0') !== '1') {
            $bonK = (int) get_setting('wage_bon_kargari', '2200000');
            $hous = (int) get_setting('wage_housing', '3000000');
            $marr = (int) get_setting('wage_marriage', '500000');
            $chAl = (int) get_setting('wage_child_allowance', '1662555');
            $seni = (int) get_setting('wage_seniority', '500000');
            $insP = (int) get_setting('wage_insurance_worker', '7');
            $taxT = (int) get_setting('wage_tax_threshold', '24000000');
            $rows = $pdo->query(
                'SELECT sp.id, sp.base_amount, sp.bonus, sp.deduction, e.children_count, e.hire_date ' .
                'FROM salary_payments sp JOIN employees e ON e.id = sp.employee_id'
            )->fetchAll(PDO::FETCH_ASSOC);
            $upd = $pdo->prepare('UPDATE salary_payments SET net_amount = :n WHERE id = :id');
            foreach ($rows as $r) {
                $chTotal = max(0, (int) ($r['children_count'] ?? 0)) * $chAl;
                // سنوات: حداقل ۱ سال سابقه
                $senAmt = 0;
                $hd = trim((string) ($r['hire_date'] ?? ''));
                if ($hd !== '') {
                    try {
                        $d1 = new DateTime($hd); $d2 = new DateTime();
                        if ($d1 <= $d2 && $d1->diff($d2)->y >= 1) { $senAmt = $seni; }
                    } catch (Throwable $ignored) {}
                }
                $earn = (int)$r['base_amount'] + $bonK + $hous + $marr + $chTotal + $senAmt + (int)$r['bonus'];
                $ins = (int) round($earn * $insP / 100);
                $tax = (int) round(max(0, $earn - $taxT) * 0.10);
                $net = $earn - $ins - $tax - (int)$r['deduction'];
                $upd->execute([':n' => $net, ':id' => $r['id']]);
            }
            set_setting('payslip_recalc_9142', '1');
        }
    } catch (Throwable $e) {
        error_log('payslip recalc failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۴: جدول وبلاگ/مقالات ---
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS blog_posts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            excerpt TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL DEFAULT '',
            featured_image TEXT NOT NULL DEFAULT '',
            status TEXT NOT NULL DEFAULT 'draft',
            published_at TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blog_slug ON blog_posts (slug)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_blog_status ON blog_posts (status, published_at)");
    } catch (Throwable $e) {
        error_log('blog table migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۴: نرخ‌های رسمی حقوق ۱۴۰۵ (مصوب شورای عالی کار، ۲۴ اسفند ۱۴۰۴) ---
    // همه پارامترها از پنل (حقوق و دستمزد ← پارامترها) قابل تغییرند؛ اگر شورای عالی کار
    // تا پایان مهر ترمیمی تصویب کند، فقط کافی است اعداد را در پنل عوض کنید.
    try {
        $wage1405 = [
            'wage_min_daily'       => '554185',    // حداقل مزد روزانه (تومان)
            'wage_min_monthly'     => '16625550',  // حداقل مزد ماهانه ۳۰ روزه (تومان)
            'wage_bon_kargari'     => '2200000',   // بن کارگری (بدون تغییر نسبت به ۱۴۰۴)
            'wage_housing'         => '3000000',   // حق مسکن (از ۹۰۰ هزار افزایش یافت)
            'wage_marriage'        => '500000',    // حق تأهل
            'wage_child_allowance' => '1662555',   // حق اولاد هر فرزند (۱۰٪ حداقل مزد)
            'wage_seniority'       => '500000',    // پایه سنوات ماهانه (۱+ سال سابقه)
            'wage_insurance_worker'=> '7',         // بیمه سهم کارگر (٪)
            'wage_insurance_employer' => '23',     // بیمه سهم کارفرما (٪)
            'wage_tax_threshold'   => '24000000',  // سقف معافیت مالیاتی (تأیید نشده برای ۱۴۰۵)
        ];
        $wstmt = $pdo->prepare('INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        foreach ($wage1405 as $k => $v) {
            // فقط اگر مقدار فعلی همان پیش‌فرض قدیمی است یا کلید تازه است، به‌روز کن؛
            // اگر کاربر خودش در پنل تغییر داده، دست نزن.
            $cur = $pdo->query("SELECT value FROM settings WHERE key = " . $pdo->quote($k))->fetchColumn();
            if ($cur === false) {
                $wstmt->execute([':k' => $k, ':v' => $v]);
            }
        }
        // به‌روزرسانی مقادیر قدیمی ۱۴۰۴ به ۱۴۰۵ فقط اگر کاربر دست‌کاری نکرده باشد
        $oldDefaults = ['wage_housing' => '900000', 'wage_child_allowance' => '1250000'];
        $ustmt = $pdo->prepare('UPDATE settings SET value = :v WHERE key = :k AND value = :old');
        foreach ($oldDefaults as $k => $oldV) {
            $ustmt->execute([':v' => $wage1405[$k], ':k' => $k, ':old' => $oldV]);
        }
    } catch (Throwable $e) {
        error_log('wage 1405 migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۹: تعداد فرزندان پرسنل + پارامترهای حقوق وزارت‌کاری ---
    try {
        $ecols = $pdo->query("PRAGMA table_info(employees)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('children_count', $ecols, true)) {
            $pdo->exec("ALTER TABLE employees ADD COLUMN children_count INTEGER NOT NULL DEFAULT 0");
        }
        $wageDefaults = [
            'wage_bon_kargari'     => '2200000',   // بن کارگری (کمک‌هزینه اقلام مصرفی)
            'wage_housing'         => '900000',    // حق مسکن
            'wage_child_allowance' => '1250000',   // حق اولاد برای هر فرزند
            'wage_tax_threshold'   => '24000000',  // سقف معافیت مالیاتی حقوق ماهانه
        ];
        $wstmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (:k, :v)');
        foreach ($wageDefaults as $k => $v) {
            $wstmt->execute([':k' => $k, ':v' => $v]);
        }
    } catch (Throwable $e) {
        error_log('wage params migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۸٫۱۰٫۱: رفع اسکریپت reveal ---
    try {
        seed_reveal_fix_v8101_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('reveal fix migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۱: کارت QR در صفحه تماس + استایل آن (یک بار) ---
    try {
        seed_contact_qr_v911_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('contact qr migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۲٫۳: حذف ایمپورت CDN فونت از CSS دیتابیس (فونت محلی شد؛ یک بار) ---
    try {
        seed_local_font_v9123_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('local font migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۳: آدرس‌های تمیز سئودوست در قالب‌های دیتابیس (یک بار) ---
    try {
        seed_pretty_urls_v9130_if_needed($pdo);
    } catch (Throwable $e) {
        error_log('pretty urls migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۳٫۱: کاشی شدن گالری پروژه‌ها (یک بار) ---
    try {
        seed_gallery_tiles_v9131_if_needed();
    } catch (Throwable $e) {
        error_log('gallery tiles migration failed: ' . $e->getMessage());
    }
    // --- نسخه ۹٫۱۵: تم‌های جدید (یک بار) ---
    try { seed_themes_v9150_if_needed();
seed_themes_v9240_if_needed();
migrate_customer_credit_limit_if_needed();
seed_vapid_keys_if_needed();
cleanup_legacy_flat_files_if_needed(); } catch (Throwable $e) {}
    // --- نسخه ۹٫۱۴: استایل پیگیری سفارش (یک بار) ---
    try { seed_track_css_v9140_if_needed(); } catch (Throwable $e) {}
    // --- نسخه ۹٫۱۳٫۳: استایل لایت‌باکس گالری (یک بار) ---
    try {
        seed_gallery_lightbox_css_v9133_if_needed();
    } catch (Throwable $e) {
        error_log('gallery lightbox css migration failed: ' . $e->getMessage());
    }
}

/**
 * نسخه ۹٫۱۳ — تبدیل لینک‌های قدیمی (products.php و page.php?slug=) به آدرس تمیز در قالب‌ها (فقط یک بار).
 * جایگزینی جراحی انجام می‌شود تا شخصی‌سازی‌های کاربر حفظ شود.
 */
function seed_pretty_urls_v9130_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('pretty_urls_9130', '') === '1') {
        return;
    }
    $rows = $pdo->query("SELECT template_key, content FROM site_templates")->fetchAll(PDO::FETCH_ASSOC);
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    foreach ($rows as $r) {
        $tk = (string) ($r['template_key'] ?? '');
        $content = (string) ($r['content'] ?? '');
        if ($content === '') {
            continue;
        }
        $new = $content;
        // page.php?slug=X → /X (اول، چون خاص‌تر است)
        $new = preg_replace('/href="page\.php\?slug=([A-Za-z0-9_-]+)"/', 'href="/$1"', $new);
        $new = str_replace('href="products.php?', 'href="/products?', $new);
        $new = str_replace('href="products.php"', 'href="/products"', $new);
        $new = str_replace('href="order.php"', 'href="/order"', $new);
        $new = str_replace('href="card.php"', 'href="/card"', $new);
        $new = str_replace('href="index.php"', 'href="/"', $new);
        // action فرم‌ها هم
        $new = str_replace('action="products.php?', 'action="/products?', $new);
        if ($new !== $content) {
            archive_design_revision($pdo, 'template', $tk, $content, 'template-archive: قالب «' . $tk . '» قبل از تمیزسازی آدرس‌ها (نسخه ۹٫۱۳).');
            $up->execute([':c' => $new, ':k' => $tk]);
        }
    }
    set_setting('pretty_urls_9130', '1');
}

/**
 * نسخه ۹٫۱۲٫۳ — حذف ایمپورت CDN خارجی فونت وزیرمتن از CSS دیتابیس (فقط یک بار).
 * فونت از این پس به‌صورت محلی از assets/fonts سرو می‌شود.
 */
function seed_local_font_v9123_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('local_font_9123', '') === '1') {
        return;
    }
    $liveCss = (string) get_setting('site_css', '');
    if ($liveCss !== '' && strpos($liveCss, 'cdn.jsdelivr.net') !== false) {
        $cdnImport = "@import url('https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');";
        $liveCss = str_replace($cdnImport, '', $liveCss);
        set_setting('site_css', $liveCss);
    }
    set_setting('local_font_9123', '1');
}

/**
 * نسخه ۹٫۱۱ — افزودن کارت QR به قالب «تماس با ما» و استایل آن به CSS (فقط یک بار).
 */
function seed_contact_qr_v911_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('contact_qr_911', '') === '1') {
        return;
    }
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'contact_page']);
    $old = $st->fetchColumn();
    if ($old !== false && trim((string) $old) !== '') {
        archive_design_revision($pdo, 'template', 'contact_page', (string) $old, 'template-archive: قالب «تماس با ما» قبل از افزودن کارت QR (نسخه ۹٫۱۱).');
    }
    $tpls = factory_templates();
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $up->execute([':c' => $tpls['contact_page']['content'], ':k' => 'contact_page']);
    // استایل کارت QR به CSS دیتابیس اضافه شود
    $css = (string) get_setting('site_css', '');
    if ($css !== '' && strpos($css, '.contact-qr-img') === false) {
        $css .= "\n.contact-qr-card{flex-wrap:wrap}\n.contact-qr-img{width:110px;height:110px;border-radius:12px;background:#fff;padding:6px;flex-shrink:0}\n.contact-qr-hint{font-size:12px !important;font-weight:400 !important}\n";
        set_setting('site_css', $css);
    }
    set_setting('contact_qr_911', '1');
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

    $stage = APP_ROOT . '/database-switch-' . bin2hex(random_bytes(6)) . '.sqlite';
    if (!@copy($source, $stage)) {
        return ['ok' => false, 'error' => 'کپی‌کردن فایل دیتابیس برای بررسی انجام نشد.', 'safety_copy' => null];
    }

    $validation = validate_database_file($stage);
    if (!$validation['ok']) {
        @unlink($stage);
        return ['ok' => false, 'error' => (string) $validation['error'], 'safety_copy' => null];
    }

    $safety = APP_ROOT . '/database-backup-before-switch.sqlite';
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
    $rows = db()->query("SELECT key, value FROM settings WHERE key NOT IN ('site_css','custom_css')")->fetchAll();
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

/**
 * آدرس پایه سایت (برای canonical و JSON-LD) — نسخه ۹٫۱۳
 */
function site_base_url(?array $settings = null): string
{
    static $base = null;
    $useCache = $settings === null;
    if ($useCache && $base !== null) {
        return $base;
    }
    if ($settings === null) {
        try {
            $settings = all_settings();
        } catch (Throwable $ignored) {
            $settings = [];
        }
    }
    $out = rtrim((string) ($settings['site_url'] ?? ''), '/');
    if ($out === '') {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $out = $host !== '' ? $proto . '://' . $host : '';
    }
    if ($useCache) {
        $base = $out;
    }
    return $out;
}

/**
 * تبدیل آدرس‌های داخلی قدیمی به نسخه تمیز و سئودوست (نسخه ۹٫۱۳):
 * products.php → /products ، page.php?slug=X → /X ، order.php → /order ، card.php → /card ، index.php → /
 * کوئری‌استرینگ و فرگمنت حفظ می‌شوند؛ آدرس‌های خارجی و نامرتبط دست‌نخورده برمی‌گردند.
 */
function pretty_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || $url[0] === '#') {
        return $url;
    }
    if (preg_match('#^(https?://|mailto:|tel:|ftp:|//)#i', $url)) {
        return $url;
    }
    $frag = '';
    if (($p = strpos($url, '#')) !== false) {
        $frag = substr($url, $p);
        $url = substr($url, 0, $p);
    }
    $query = '';
    if (($p = strpos($url, '?')) !== false) {
        $query = substr($url, $p + 1);
        $url = substr($url, 0, $p);
    }
    $map = [
        'products.php' => '/products',
        'order.php'    => '/order',
        'card.php'     => '/card',
        'index.php'    => '/',
    ];
    if (isset($map[$url])) {
        $out = $map[$url];
        if ($query !== '') {
            $out .= '?' . $query;
        }
        return $out . $frag;
    }
    if ($url === 'page.php') {
        parse_str($query, $qs);
        $slug = (string) ($qs['slug'] ?? '');
        if ($slug !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $slug)) {
            unset($qs['slug']);
            $rest = http_build_query($qs);
            $out = '/' . $slug;
            if ($rest !== '') {
                $out .= '?' . $rest;
            }
            return $out . $frag;
        }
    }
    // نامرتبط: بازسازی عینی آدرس ورودی
    $out = $url;
    if ($query !== '') {
        $out .= '?' . $query;
    }
    return $out . $frag;
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
    $items = [['title' => 'خانه', 'url' => pretty_url('index.php')]];
    // فاز ۲: لینک کاتالوگ محصولات وقتی نمایش عمومی کاتالوگ فعال است
    if (get_setting('catalog_public', '1') === '1') {
        $items[] = ['title' => get_setting('catalog_title', 'کاتالوگ محصولات'), 'url' => pretty_url('products.php')];
    }
    foreach (get_pages(true) as $p) {
        if ((int) ($p['show_in_menu'] ?? 0) === 1) {
            $items[] = [
                'title' => (string) $p['title'],
                'url'   => pretty_url('page.php?slug=' . urlencode((string) $p['slug'])),
            ];
        }
    }
    // وبلاگ/مقالات (۹٫۱۷)
    $items[] = ['title' => 'مقالات', 'url' => pretty_url('blog.php')];
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
    // انتخاب تم فقط از پنل مدیریت (تنظیمات ظاهری ← تم پیش‌فرض)
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
    notify_admins('new_message', 'پیام تماس جدید', 'از: ' . $name . ' — ' . mb_substr($message, 0, 120), 'admin.php?page=messages');
}

function get_contact_messages(): array
{
    return db()->query('SELECT * FROM contact_messages ORDER BY id DESC')->fetchAll();
}

/** ثبت اعلان برای صندوق مشترک مدیران */
function notify_admins(string $type, string $title, string $message, string $link = ''): void
{
    try {
        db()->prepare('INSERT INTO notifications (type, title, message, link) VALUES (:t, :ti, :m, :l)')
            ->execute([
                ':t'  => mb_substr($type, 0, 32),
                ':ti' => mb_substr($title, 0, 200),
                ':m'  => mb_substr($message, 0, 500),
                ':l'  => mb_substr($link, 0, 300),
            ]);
        // پوش نوتیفیکیشن به همه ادمین‌ها (۹٫۲۵)
        try {
            $uids = db()->query('SELECT DISTINCT user_id FROM push_subscriptions')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($uids as $uid) {
                send_push_to_user((int) $uid, $title, $message, $link !== '' ? $link : 'admin.php?page=notifications');
            }
        } catch (Throwable $e) {}
    } catch (Throwable $e) {
        error_log('notify_admins failed: ' . $e->getMessage());
    }
}

/** تعداد اعلان‌های خوانده‌نشده */
function unread_notification_count(): int
{
    try {
        return (int) db()->query('SELECT COUNT(*) FROM notifications WHERE is_read = 0')->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
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
        'categories_nav', 'products_grid', 'product_specs', 'attributes_options', 'estimator',
        // نسخه ۹: ویترین محصولات صفحه اصلی
        'products_showcase'];
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
    // پردازش پلیس‌هولدر فرم همکار داخل محتوای صفحه (نسخه ۹٫۱٫۱)
    if (strpos($pageContent, '{{partner_form}}') !== false) {
        $pageContent = str_replace('{{partner_form}}', partner_form_html(partner_form_state()), $pageContent);
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
        'section_link_url'  => pretty_url((string) $linkUrl),
        'section_link_text' => $linkText,
        'page_title'        => $pageTitle,
        'page_content'      => $pageContent,
        'contact_form'      => '',
        'contact_phone'     => (string) ($settings['contact_phone'] ?? ''),
        'contact_email'     => (string) ($settings['contact_email'] ?? ''),
        'contact_whatsapp'  => (string) ($settings['contact_whatsapp'] ?? ($settings['contact_phone'] ?? '')),
        'contact_whatsapp_digits' => preg_replace('/[^0-9]/', '', (string) ($settings['contact_whatsapp'] ?? ($settings['contact_phone'] ?? ''))),
        'partner_form'      => '',
        'product_gallery'   => '',
        'slider_slides'     => '',
        'products_showcase' => '',
    ];
    if ($key === 'slider') {
        $ctx['products_showcase'] = products_showcase_html();
    }
    if (($key === 'contact' || $key === 'contact_page') && !array_key_exists('contact_form', $extra)) {
        $ctx['contact_form'] = contact_form_html(contact_state());
    }
    if (!array_key_exists('partner_form', $extra)) {
        $ctx['partner_form'] = partner_form_html(partner_form_state());
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
    if (!defined('CMS_SESSION_STARTED')) {
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
    if (defined('CMS_SESSION_STARTED')) {
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
    // REQUEST_URI کوئری‌استرینگ (مثل ?slug=contact) را حفظ می‌کند؛ PHP_SELF آن را می‌انداخت و فرم به ۴۰۴ پست می‌شد
    $action = (string) ($_SERVER['REQUEST_URI'] ?? 'index.php');
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
    if (!in_array($theme, ['light', 'dark', 'white', 'glass', 'smoke', 'ocean', 'forest', 'sunset', 'royal', 'mono', 'system'], true)) {
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
        // فونت وزیرمتن به‌صورت محلی از هاست سرو می‌شود (بدون CDN خارجی)
        $parts[] = "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('assets/fonts/Vazirmatn-Regular.woff2') format('woff2');}"
            . "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('assets/fonts/Vazirmatn-Medium.woff2') format('woff2');}"
            . "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('assets/fonts/Vazirmatn-Bold.woff2') format('woff2');}"
            . "@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:800;font-display:swap;src:url('assets/fonts/Vazirmatn-ExtraBold.woff2') format('woff2');}";
    }
    $siteCss = (string) get_setting('site_css', '');
    if (trim($siteCss) === '') {
        $siteCss = default_site_css();
    }
    $parts[] = $siteCss;
    $parts[] = "/* ===== تنظیمات ظاهری (از پنل مدیریت) ===== */\n" . visual_css_vars($settings);
    $custom = trim((string) get_setting('custom_css', ''));
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
/**
 * سربرگ سند با سئوی حرفه‌ای (نسخه ۸٫۱۰٫۰).
 * $seo آرایه اختیاری: url, image, type (website/article/product), keywords, jsonld (آرایه یا رشته JSON آماده)
 */
function skeleton_head(array $settings, string $title, string $description, array $seo = []): string
{
    $visual = validated_visual_settings($settings);
    $themeJson = json_encode($visual['default_theme']);
    $siteTitle = (string) ($settings['site_title'] ?? 'وب‌سایت من');
    // آدرس پایه سایت برای canonical و OG
    $baseUrl = rtrim((string) ($settings['site_url'] ?? ''), '/');
    if ($baseUrl === '') {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $baseUrl = $host !== '' ? $proto . '://' . $host . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/') : '';
    }
    $pageUrl = (string) ($seo['url'] ?? '');
    if ($pageUrl === '' && $baseUrl !== '') {
        $pageUrl = $baseUrl . '/';
    }
    $ogImage = (string) ($seo['image'] ?? '');
    if ($ogImage !== '' && strpos($ogImage, 'http') !== 0 && $baseUrl !== '') {
        $ogImage = $baseUrl . '/' . ltrim($ogImage, '/');
    }
    // تصویر پیش‌فرض OG: لوگو
    if ($ogImage === '' && $baseUrl !== '') {
        $ogImage = $baseUrl . '/uploads/gallery/logo.png';
    }
    $ogType = (string) ($seo['type'] ?? 'website');
    $keywords = trim((string) ($seo['keywords'] ?? ''));

    $out  = "<!DOCTYPE html>\n";
    $defTheme = (string) ($visual['default_theme'] ?? 'dark');
    $htmlThemeAttr = ($defTheme !== '' && $defTheme !== 'dark' && $defTheme !== 'system') ? ' data-theme="' . e($defTheme) . '"' : '';
    $out .= "<html lang=\"fa\" dir=\"rtl\"{$htmlThemeAttr}>\n<head>\n";
    $out .= '<meta charset="UTF-8">' . "\n";
    $out .= '<meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
    $out .= '<title>' . e($title) . '</title>' . "\n";
    $out .= '<meta name="description" content="' . e($description) . '">' . "\n";
    if ($keywords !== '') {
        $out .= '<meta name="keywords" content="' . e($keywords) . '">' . "\n";
    }
    $out .= '<meta name="robots" content="index, follow, max-image-preview:large">' . "\n";
    $out .= '<meta name="google-site-verification" content="mZGtQ4nFfc-3ObotiNUeKpw7Cdu2LoJ3DRKNz3slbtE">' . "\n";
    $out .= '<meta name="theme-color" content="' . e($visual['primary_color']) . '">' . "\n";
    // Canonical
    if ($pageUrl !== '') {
        $out .= '<link rel="canonical" href="' . e($pageUrl) . '">' . "\n";
    }
    // Open Graph
    $out .= '<meta property="og:locale" content="fa_IR">' . "\n";
    $out .= '<meta property="og:site_name" content="' . e($siteTitle) . '">' . "\n";
    $out .= '<meta property="og:type" content="' . e($ogType) . '">' . "\n";
    $out .= '<meta property="og:title" content="' . e($title) . '">' . "\n";
    $out .= '<meta property="og:description" content="' . e($description) . '">' . "\n";
    if ($pageUrl !== '') {
        $out .= '<meta property="og:url" content="' . e($pageUrl) . '">' . "\n";
    }
    if ($ogImage !== '') {
        $out .= '<meta property="og:image" content="' . e($ogImage) . '">' . "\n";
        $out .= '<meta property="og:image:alt" content="' . e($title) . '">' . "\n";
    }
    // Twitter Card
    $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $out .= '<meta name="twitter:title" content="' . e($title) . '">' . "\n";
    $out .= '<meta name="twitter:description" content="' . e($description) . '">' . "\n";
    if ($ogImage !== '') {
        $out .= '<meta name="twitter:image" content="' . e($ogImage) . '">' . "\n";
    }
    // JSON-LD پیش‌فرض: Organization + WebSite
    $jsonLd = $seo['jsonld'] ?? null;
    if ($jsonLd === null && $baseUrl !== '') {
        $jsonLd = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $baseUrl . '/#organization',
                    'name' => $siteTitle,
                    'url' => $baseUrl . '/',
                    'logo' => $baseUrl . '/uploads/gallery/logo.png',
                    'description' => $description,
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $baseUrl . '/#website',
                    'url' => $baseUrl . '/',
                    'name' => $siteTitle,
                    'publisher' => ['@id' => $baseUrl . '/#organization'],
                    'inLanguage' => 'fa-IR',
                ],
            ],
        ];
    }
    // BreadcrumbList برای sitelinks (نسخه ۹٫۱۳): از seo['breadcrumbs'] به شکل [['name'=>..,'url'=>..],...]
    $crumbs = $seo['breadcrumbs'] ?? null;
    if (is_array($crumbs) && $crumbs !== [] && $baseUrl !== '') {
        $items = [];
        $pos = 1;
        foreach ($crumbs as $c) {
            $cname = trim((string) ($c['name'] ?? ''));
            $curl = trim((string) ($c['url'] ?? ''));
            if ($cname === '') {
                continue;
            }
            if ($curl !== '' && strpos($curl, 'http') !== 0) {
                $curl = $baseUrl . '/' . ltrim($curl, '/');
            }
            $item = ['@type' => 'ListItem', 'position' => $pos, 'name' => $cname];
            if ($curl !== '') {
                $item['item'] = $curl;
            }
            $items[] = $item;
            $pos++;
        }
        if ($items !== []) {
            $crumbLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
            $crumbStr = json_encode($crumbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($crumbStr !== false && $crumbStr !== '') {
                $out .= '<script type="application/ld+json">' . $crumbStr . '</script>' . "\n";
            }
        }
    }
    if ($jsonLd !== null) {
        $jsonStr = is_string($jsonLd) ? $jsonLd : json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonStr !== false && $jsonStr !== '') {
            $out .= '<script type="application/ld+json">' . $jsonStr . '</script>' . "\n";
        }
    }
    $out .= '<link rel="stylesheet" href="style.php?v=' . e(site_css_version($settings)) . '">' . "\n";
    $favIcon = (string) ($settings['favicon_path'] ?? '');
    if ($favIcon === '') { $favIcon = 'uploads/gallery/favicon.png'; }
    $out .= '<link rel="icon" href="' . e($favIcon) . '">' . "\n";
    $out .= '<link rel="apple-touch-icon" href="' . e($favIcon) . '">' . "\n";
$out .= '<link rel="manifest" href="manifest.webmanifest">' . "\n";
$out .= '<meta name="theme-color" content="#0f172a">' . "\n";
$out .= '<meta name="mobile-web-app-capable" content="yes">' . "\n";
$out .= '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
$out .= '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">' . "\n";
$out .= '<meta name="apple-mobile-web-app-title" content="لاینرلایت">' . "\n";
    $out .= '<script>(function(){try{var t=localStorage.getItem(\'cms-theme\');if(t&&t!==\'dark\'){document.documentElement.setAttribute(\'data-theme\',t);}}catch(e){}})();</script>' . "\n";
    $out .= "</head>\n<body>\n";
    $out .= '<a class="skip-link" href="#main">پرش به محتوای اصلی</a>' . "\n";
    return $out;
}

/** پایان سند: اسکریپت سبک منوی موبایل، تغییر تم و اسلایدر + بستن body و html */

/** محتوای ۴۰۴ هوشمند (۹٫۹۹٫۳۰) — شمارش معکوس و هدایت خودکار به صفحه اصلی (با حفظ کد ۴۰۴) */
function notfound_auto_redirect_html(): string
{
    $home = e(pretty_url('index.php'));
    $homeJs = json_encode(pretty_url('index.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return '<div class="ll-404">'
        . '<div class="code" dir="ltr">404</div>'
        . '<h1>صفحه پیدا نشد</h1>'
        . '<p>صفحه‌ای با این آدرس وجود ندارد یا غیرفعال است.</p>'
        . '<p>تا <span class="count" id="ll404count">۵</span> ثانیه دیگر به صفحه اصلی هدایت می‌شوید…</p>'
        . '<p><a class="btn" href="' . $home . '">بازگشت به صفحه اصلی</a></p>'
        . '</div>'
        . '<script>(function(){var n=5,el=document.getElementById("ll404count"),fa="۰۱۲۳۴۵۶۷۸۹";'
        . 'var t=setInterval(function(){n--;if(n<=0){clearInterval(t);window.location.href=' . $homeJs . ';return;}'
        . 'if(el){el.textContent=fa[n]||n;}},1000);})();</script>';
}

/** تب‌بار شناور موبایل (۹٫۹۹٫۳۰) — خانه، ثبت سفارش، درباره ما، تماس با ما */
function tabbar_html(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = strtolower(trim((string) parse_url($uri, PHP_URL_PATH), '/'));
    // نگاشت مسیر به تب فعال
    $active = '';
    if ($path === '' || $path === 'index.php') $active = 'home';
    elseif (str_starts_with($path, 'products') || str_starts_with($path, 'order')) $active = 'order';
    elseif (str_starts_with($path, 'about')) $active = 'about';
    elseif (str_starts_with($path, 'contact')) $active = 'contact';

    $svg = function (string $body): string {
        return '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $body . '</g></svg>';
    };
    $items = [
        ['key' => 'home',    'url' => '/',         'label' => 'خانه',
         'icon' => $svg('<path d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z"/>')],
        ['key' => 'order',   'url' => '/products', 'label' => 'ثبت سفارش',
         'icon' => $svg('<path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><path d="M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/><path d="M9 14l2 2 4-4"/>')],
        ['key' => 'about',   'url' => '/about',    'label' => 'درباره ما',
         'icon' => $svg('<path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>')],
        ['key' => 'contact', 'url' => '/contact',  'label' => 'تماس با ما',
         'icon' => $svg('<path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.18 2 2 0 014.11 2h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/>')],
    ];
    $out = '<nav class="ll-tabbar" aria-label="ناوبری موبایل">';
    foreach ($items as $it) {
        $on = ($active === $it['key']) ? ' on' : '';
        $aria = ($active === $it['key']) ? ' aria-current="page"' : '';
        $out .= '<a href="' . e($it['url']) . '" class="tab' . $on . '"' . $aria . '>' . $it['icon'] . '<span>' . e($it['label']) . '</span></a>';
    }
    $out .= '</nav>';
    return $out;
}

/** آیا محتوای فوتر همان نسخه کارخانه‌ای قبل از ۹٫۹۹٫۳۰ است؟ */
function design_footer_is_legacy(string $content): bool
{
    $norm = (string) preg_replace('/\s+/', '', $content);
    return str_contains($norm, 'سفارشوکاتالوگ')
        && str_contains($norm, '/catalog/linerlight-catalog.pdf')
        && !str_contains($norm, 'همکاریباما');
}

/** همگام‌سازی طراحی ۹٫۹۹٫۳۰ — یک‌بار و امن؛ سفارشی‌سازی کاربر دست نمی‌خورد */
function design_sync_9930(): void
{
    try {
        $cur = (int) get_setting('design_version', '0');
        if ($cur >= 9930) return;

        // فوتر: فقط اگر دقیقاً نسخه کارخانه‌ای قبلی است
        $row = get_template_row('footer');
        if ($row !== null && design_footer_is_legacy((string) ($row['content'] ?? ''))) {
            $all = factory_templates();
            $new = (string) ($all['footer']['content'] ?? '');
            if ($new !== '') {
                save_template_content('footer', $new, 'همگام‌سازی خودکار طراحی ۹٫۹۹٫۳۰');
            }
        }
        // اگر ردیفی در دیتابیس نیست، پیش‌فرض کارخانه‌ای جدید خودکار اعمال می‌شود.

        set_setting('design_version', '9930');
    } catch (Throwable $e) { /* silent — طراحی نباید سایت را بخواباند */ }
}

function design_sync_9935(): void
{
    try {
        $cur = (int) get_setting('design_version', '0');
        if ($cur >= 9935) return;

        // هدر: اضافه کردن width/height به لوگو برای رفع اخطار CLS پیج‌اسپید
        $row = get_template_row('header');
        if ($row !== null) {
            $content = (string) ($row['content'] ?? '');
            if (strpos($content, 'logo-img') !== false && strpos($content, 'width="159"') === false) {
                $newContent = str_replace(
                    '<img class="logo-img" src="uploads/gallery/logo.png"',
                    '<img class="logo-img" src="uploads/gallery/logo.png" width="159" height="160"',
                    $content
                );
                if ($newContent !== $content) {
                    save_template_content('header', $newContent, 'همگام‌سازی خودکار طراحی ۹٫۹۹٫۳۵ (ابعاد لوگو)');
                }
            }
        }

        set_setting('design_version', '9935');
    } catch (Throwable $e) { /* silent — طراحی نباید سایت را بخواباند */ }
}

function skeleton_foot(): string
{
    design_sync_9930();
    design_sync_9935();
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
    // تم سایت فقط از تنظیمات پیش‌فرض پنل اعمال می‌شود
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
        $html = str_replace('</body>', $track . "\n<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
  });
}
</script>
</body>", $html);
    }
    // تب‌بار شناور موبایل (۹٫۹۹٫۳۰) — قبل از بستن body
    $html = str_replace('</body>', tabbar_html() . "\n</body>", $html);
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

// نگهدارندهٔ نشست دیتابیسی و شروع نشست (نسخه ۸٫۲٫۲: نشست خودترمیم با کوکی امضاشده) در session_handler.php است.
require_once __DIR__ . '/session_handler.php';
require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/templates.php';

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

/** تبدیل کد ۲ حرفی کشور به ایموجی پرچم (Regional Indicator Symbols) — بدون نیاز به mbstring. */
function country_flag_emoji(string $code): string
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^[A-Z]{2}$/', $code)) { return ''; }
    $flag = '';
    for ($i = 0; $i < 2; $i++) {
        $cp = 0x1F1E6 + ord($code[$i]) - 65; // Regional Indicator A = U+1F1E6
        // تبدیل codepoint به UTF-8 دستی
        $flag .= chr(0xF0 | ($cp >> 18)) . chr(0x80 | (($cp >> 12) & 0x3F)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
    }
    return $flag;
}

/** تشخیص نوع دستگاه و نام مرورگر از User-Agent — برچسب‌های فارسی. */
function detect_device_browser(string $ua): array
{
    $l = strtolower($ua);
    // دستگاه
    $device = 'دسکتاپ';
    $deviceKey = 'desktop';
    if (strpos($l, 'ipad') !== false || (strpos($l, 'tablet') !== false && strpos($l, 'mobile') === false)) {
        $device = 'تبلت'; $deviceKey = 'tablet';
    } elseif (preg_match('/mobile|iphone|ipod|android.*mobile|blackberry|iemobile|opera mini|windows phone/i', $ua)) {
        $device = 'موبایل'; $deviceKey = 'mobile';
    } elseif (strpos($l, 'android') !== false) {
        $device = 'تبلت'; $deviceKey = 'tablet';
    }
    // مرورگر (ترتیب مهم است: اج و اپرا و سامسونگ قبل از کروم چک شوند)
    $browser = 'سایر';
    $browserKey = 'other';
    if (preg_match('/edg\/|edge\//i', $ua)) { $browser = 'اج'; $browserKey = 'edge'; }
    elseif (preg_match('/opr\/|opera/i', $ua)) { $browser = 'اپرا'; $browserKey = 'opera'; }
    elseif (strpos($l, 'samsungbrowser') !== false) { $browser = 'سامسونگ'; $browserKey = 'samsung'; }
    elseif (strpos($l, 'firefox') !== false || strpos($l, 'fxios') !== false) { $browser = 'فایرفاکس'; $browserKey = 'firefox'; }
    elseif (strpos($l, 'crios') !== false || strpos($l, 'chrome') !== false) { $browser = 'کروم'; $browserKey = 'chrome'; }
    elseif (strpos($l, 'safari') !== false) { $browser = 'سافاری'; $browserKey = 'safari'; }
    elseif (strpos($l, 'msie') !== false || strpos($l, 'trident') !== false) { $browser = 'اینترنت اکسپلورر'; $browserKey = 'ie'; }
    return ['device' => $device, 'device_key' => $deviceKey, 'browser' => $browser, 'browser_key' => $browserKey];
}

/** آیا آی‌پی خصوصی/محلی است؟ */
function ip_is_private(string $ip): bool
{
    if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') { return true; }
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
}

/**
 * تشخیص کشور از روی آی‌پی با کش دیتابیسی.
 * برمی‌گرداند: ['code' => 'IR', 'name' => 'ایران'] — در خطا کد خالی.
 */
/** خواندن کشور فقط از کش — هیچ تماس شبکه‌ای؛ امن برای مسیر داغ لود صفحه. */
function detect_country_from_ip_cached(string $ip): array
{
    $out = ['hit' => false, 'code' => '', 'name' => ''];
    $ip = trim($ip);
    if ($ip === '' || ip_is_private($ip)) {
        $out['hit'] = true;
        $out['name'] = 'داخلی';
        return $out;
    }
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT country_code, country_name, cached_at FROM ip_country_cache WHERE ip = ? LIMIT 1');
        $st->execute([$ip]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row !== false && (int) $row['cached_at'] > time() - 30 * 86400) {
            $out['hit'] = true;
            $out['code'] = (string) $row['country_code'];
            $out['name'] = (string) $row['country_name'];
        }
    } catch (Throwable $ignored) {
    }
    return $out;
}

/** حل کشور یک IP با تماس API + ذخیره در کش — فقط در shutdown (غیرهم‌زمان) یا بک‌فیل ادمین صدا بزنید. */
function resolve_country_for_ip(string $ip): array
{
    $ip = trim($ip);
    if ($ip === '' || ip_is_private($ip)) {
        return ['code' => '', 'name' => 'داخلی'];
    }
    $code = ''; $name = '';
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true]]);
        $json = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=status,country,countryCode', false, $ctx);
        if ($json !== false) {
            $data = json_decode($json, true);
            if (is_array($data) && ($data['status'] ?? '') === 'success') {
                $code = strtoupper(trim((string) ($data['countryCode'] ?? '')));
                $nameEn = trim((string) ($data['country'] ?? ''));
                if (preg_match('/^[A-Z]{2}$/', $code)) {
                    $name = country_name_fa($code, $nameEn);
                } else {
                    $code = '';
                }
            }
        }
        db()->prepare('INSERT OR REPLACE INTO ip_country_cache (ip, country_code, country_name, cached_at) VALUES (?,?,?,?)')
            ->execute([$ip, $code, $name, time()]);
    } catch (Throwable $ignored) {
    }
    return ['code' => $code, 'name' => $name];
}

function detect_country_from_ip(string $ip): array
{
    $ip = trim($ip);
    if ($ip === '' || ip_is_private($ip)) {
        return ['code' => '', 'name' => 'داخلی'];
    }
    $cached = detect_country_from_ip_cached($ip);
    if (!empty($cached['hit'])) {
        return ['code' => $cached['code'], 'name' => $cached['name']];
    }
    return resolve_country_for_ip($ip);
}

/** زمان‌بندی حل کشور بازدید بعد از ارسال پاسخ — صفحه هرگز منتظر API نمی‌ماند. */
function schedule_geo_resolve(int $logId, string $ip): void
{
    if ($logId <= 0 || $ip === '' || ip_is_private(trim($ip))) {
        return;
    }
    register_shutdown_function(function () use ($logId, $ip) {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            } else {
                while (ob_get_level() > 0) {
                    @ob_end_flush();
                }
                @flush();
            }
            $geo = resolve_country_for_ip($ip);
            if ($geo['code'] !== '' || $geo['name'] !== '') {
                db()->prepare('UPDATE visit_logs SET country_code = ?, country_name = ? WHERE id = ?')
                    ->execute([(string) $geo['code'], (string) $geo['name'], $logId]);
            }
        } catch (Throwable $ignored) {
        }
    });
}

/** نام فارسی کشور از روی کد — برای کدهای پرتکرار؛ بقیه همان نام انگلیسی. */
function country_name_fa(string $code, string $fallback = ''): string
{
    static $map = [
        'IR' => 'ایران', 'US' => 'آمریکا', 'DE' => 'آلمان', 'GB' => 'بریتانیا',
        'FR' => 'فرانسه', 'NL' => 'هلند', 'CA' => 'کانادا', 'AU' => 'استرالیا',
        'TR' => 'ترکیه', 'AE' => 'امارات', 'SA' => 'عربستان', 'IQ' => 'عراق',
        'AF' => 'افغانستان', 'PK' => 'پاکستان', 'IN' => 'هند', 'CN' => 'چین',
        'RU' => 'روسیه', 'UA' => 'اوکراین', 'SE' => 'سوئد', 'CH' => 'سوئیس',
        'IT' => 'ایتالیا', 'ES' => 'اسپانیا', 'JP' => 'ژاپن', 'KR' => 'کره جنوبی',
        'BR' => 'برزیل', 'EG' => 'مصر', 'QA' => 'قطر', 'KW' => 'کویت',
        'OM' => 'عمان', 'BH' => 'بحرین', 'JO' => 'اردن', 'LB' => 'لبنان',
        'SY' => 'سوریه', 'YE' => 'یمن', 'AZ' => 'آذربایجان', 'AM' => 'ارمنستان',
        'GE' => 'گرجستان', 'KZ' => 'قزاقستان', 'UZ' => 'ازبکستان', 'TM' => 'ترکمنستان',
    ];
    return $map[$code] ?? ($fallback !== '' ? $fallback : $code);
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
    // لاگ بعد از ارسال پاسخ (۹٫۲۴): اول صفحه به کاربر می‌رسد، بعد INSERT انجام می‌شود
    if (function_exists('fastcgi_finish_request')) {
        register_shutdown_function(function () {
            try { fastcgi_finish_request(); } catch (Throwable $e) {}
            track_public_request_now();
        });
        return;
    }
    track_public_request_now();
}

function track_public_request_now(): void
{
    try {
        if (get_setting('visit_log_enabled', '1') !== '1') { return; }
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300);
        // ربات‌ها هم ثبت می‌شوند (is_bot=1) تا با فیلتر جدا قابل مشاهده باشند
        $isBot = ua_is_bot($ua) ? 1 : 0;
        // اگر بازدیدکننده با نشست ادمین وارد فرانت‌اند شده، نام کاربری‌اش ثبت می‌شود
        $adminUser = '';
        if (isset($_SESSION) && !empty($_SESSION['admin_logged_in']) && !empty($_SESSION['admin_username'])) {
            $adminUser = substr((string) $_SESSION['admin_username'], 0, 60);
        }
        $sessionKey = '';
        if (defined('CMS_SESSION_STARTED') && ($GLOBALS['CMS_SID'] ?? '') !== '') {
            $sessionKey = substr(md5((string) $GLOBALS['CMS_SID']), 0, 10);
        }
        // تشخیص دستگاه/مرورگر سریع و محلی است؛ کشور فقط از کش خوانده می‌شود
        // (آی‌پی‌های تازه بعد از ارسال صفحه، غیرهم‌زمان حل می‌شوند تا لود کند نشود)
        $dbInfo = detect_device_browser($ua);
        $clientIp = client_ip();
        $geoCached = detect_country_from_ip_cached($clientIp);
        $geoHit = !empty($geoCached['hit']);
        $countryCode = (string) ($geoCached['code'] ?? '');
        $countryName = (string) ($geoCached['name'] ?? '');
        $deviceType = (string) ($dbInfo['device'] ?? '');
        $browserName = (string) ($dbInfo['browser'] ?? '');
        if ($method === 'POST' && isset($_POST['track_click'])) {
            // بیکن کلیک: فقط از خود سایت قبول می‌شود
            $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
            if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== ($_SERVER['HTTP_HOST'] ?? '')) {
                http_response_code(204);
                exit;
            }
            $path = substr((string) ($_POST['p'] ?? ($_SERVER['REQUEST_URI'] ?? '')), 0, 300);
            $target = substr(trim((string) ($_POST['t'] ?? '')), 0, 200);
            db()->prepare("INSERT INTO visit_logs (kind, ip, user_agent, referer, path, target, session_key, is_bot, admin_user, country_code, country_name, device_type, browser_name) VALUES ('click', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$clientIp, $ua, substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500), $path, $target, $sessionKey, $isBot, $adminUser, $countryCode, $countryName, $deviceType, $browserName]);
            if (!$geoHit) { schedule_geo_resolve((int) db()->lastInsertId(), $clientIp); }
            prune_logs_maybe();
            http_response_code(204);
            exit;
        }
        if ($method === 'GET') {
            db()->prepare("INSERT INTO visit_logs (kind, ip, user_agent, referer, path, target, session_key, is_bot, admin_user, country_code, country_name, device_type, browser_name) VALUES ('visit', ?, ?, ?, ?, '', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$clientIp, $ua, substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500), substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 300), $sessionKey, $isBot, $adminUser, $countryCode, $countryName, $deviceType, $browserName]);
            if (!$geoHit) { schedule_geo_resolve((int) db()->lastInsertId(), $clientIp); }
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
    // نسخه ۸٫۹٫۲: تابع catalog_css_block() در کد نیست؛ بدون function_exists صدا زده نشود تا fatal ندهد.
    $st = $pdo->prepare('SELECT value FROM settings WHERE key = :k');
    $st->execute([':k' => 'site_css']);
    $row = $st->fetch();
    $siteCss = $row === false ? '' : (string) ($row['value'] ?? '');
    if ($siteCss !== '' && strpos($siteCss, catalog_css_marker()) === false && function_exists('catalog_css_block')) {
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
    // فاز ۵ (نسخه ۸٫۴): هر خرید واقعی مواد (ورود با قیمت خرید) به‌صورت «ثبت اولیه»
    // در هزینه‌ها می‌نشیند و فقط با تأیید مدیر به هزینه قطعی تبدیل می‌شود.
    // برگشت‌های تولید (refType=production) خرید نیستند و عمداً از این قاعده بیرون‌اند.
    if ($type === 'in' && $unitPrice !== null && $unitPrice > 0 && $refType !== 'production') {
        try {
            $expenseAmount = (int) round($qty * $unitPrice);
            if ($expenseAmount > 0) {
                $pdo->prepare("INSERT INTO expenses (cat_key, title, amount, status, source, source_id, expense_date, note) VALUES ('materials', :t, :a, 'pending', 'material_purchase', :sid, :d, :n)")
                    ->execute([
                        ':t' => 'خرید مواد اولیه: ' . (string) $mat['name'],
                        ':a' => $expenseAmount,
                        ':sid' => $movementId,
                        ':d' => date('Y-m-d'),
                        ':n' => $reason !== '' ? $reason : null,
                    ]);
            }
        } catch (Throwable $ignored) {
            // خطای مالی هرگز نباید ثبت گردش انبار را خراب کند
        }
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
    // هزینه تولید (نسخه ۹٫۱): دستمزد/سربار به‌ازای هر متر و هر چراغ
    $prod = get_product($productId);
    $laborMeter = (float) ($prod['labor_cost_per_meter'] ?? 0);
    $laborFixture = (float) ($prod['labor_cost_per_fixture'] ?? 0);
    if ($laborMeter > 0) {
        $lines[] = [
            'material_id' => 0,
            'name'        => 'هزینه تولید (هر متر)',
            'unit'        => '',
            'qty'         => 1,
            'basis'       => 'per_meter',
            'unit_price'  => (int) round($laborMeter),
            'line_cost'   => $laborMeter,
            'is_labor'    => true,
        ];
        $perMeter += $laborMeter;
    }
    if ($laborFixture > 0) {
        $lines[] = [
            'material_id' => 0,
            'name'        => 'هزینه تولید (هر چراغ)',
            'unit'        => '',
            'qty'         => 1,
            'basis'       => 'per_fixture',
            'unit_price'  => (int) round($laborFixture),
            'line_cost'   => $laborFixture,
            'is_labor'    => true,
        ];
        $perFixture += $laborFixture;
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

/** سید فاز ۴ (نسخه ۸٫۳): مراحل پیش‌فرض تولید + شماره برگه — فقط یک بار؛ داده کاربر دست نمی‌خورد */
function seed_production_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('production_seeded_v83', '') === '1') {
        return;
    }
    // مراحل کارگاه (کاملاً قابل‌ویرایش از صفحه «مراحل تولید»)
    if ((int) $pdo->query('SELECT COUNT(*) FROM production_stages')->fetchColumn() === 0) {
        $stages = [
            ['queued', 'در صف تولید', '#6b7280'],
            ['cutting', 'برش', '#d97706'],
            ['assembly', 'مونتاژ', '#2563eb'],
            ['testing', 'تست و کنترل کیفیت', '#7c3aed'],
            ['packaging', 'بسته‌بندی', '#0891b2'],
            ['done', 'آماده تحویل', '#16a34a'],
        ];
        $ins = $pdo->prepare('INSERT INTO production_stages (stage_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, 1, :s)');
        $s = 0;
        foreach ($stages as $st) {
            $s += 10;
            $ins->execute([':k' => $st[0], ':t' => $st[1], ':c' => $st[2], ':s' => $s]);
        }
    }
    if (get_setting('next_production_no', '') === '') {
        set_setting('next_production_no', '1');
    }
    set_setting('production_seeded_v83', '1');
}

/** سید فاز ۵ (نسخه ۸٫۴): روش‌های پرداخت و دسته‌های هزینه — فقط یک بار، بدون بازنویسی داده کاربر */
function seed_finance_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('finance_seeded_v84', '') === '1') {
        return;
    }
    // روش‌های پرداخت (قابل‌ویرایش از صفحه «قوانین مالی»)
    if ((int) $pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn() === 0) {
        $methods = [
            ['cash', 'نقدی'],
            ['card', 'کارت'],
            ['transfer', 'حواله'],
            ['check', 'چک'],
        ];
        $ins = $pdo->prepare('INSERT INTO payment_methods (method_key, title, is_active, sort_order) VALUES (:k, :t, 1, :s)');
        $s = 0;
        foreach ($methods as $m) {
            $s += 10;
            $ins->execute([':k' => $m[0], ':t' => $m[1], ':s' => $s]);
        }
    }
    // دسته‌بندی هزینه‌ها (قابل‌ویرایش از صفحه «قوانین مالی»)
    if ((int) $pdo->query('SELECT COUNT(*) FROM expense_categories')->fetchColumn() === 0) {
        $cats = [
            ['materials', 'خرید مواد اولیه', '#2563eb'],
            ['rent', 'اجاره', '#7c3aed'],
            ['salary', 'حقوق', '#0891b2'],
            ['transport', 'حمل‌ونقل', '#d97706'],
            ['other', 'سایر', '#6b7280'],
        ];
        $ins = $pdo->prepare('INSERT INTO expense_categories (cat_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, 1, :s)');
        $s = 0;
        foreach ($cats as $c) {
            $s += 10;
            $ins->execute([':k' => $c[0], ':t' => $c[1], ':c' => $c[2], ':s' => $s]);
        }
    }
    if (get_setting('next_invoice_no', '') === '') {
        set_setting('next_invoice_no', '1');
    }
    set_setting('finance_seeded_v84', '1');
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
        $qty = max(1, (int) ($ln['qty'] ?? 1));
        $isPerWatt = (string) ($product['pricing_model'] ?? 'per_meter') === 'per_watt';
        if ($isPerWatt) {
            // قیمت‌گذاری سفارشی بر اساس وات (چراغ رشد گیاه): هر وات × قیمت هر وات × تعداد
            $watt = max(0, (int) ($ln['watt'] ?? 0));
            $pricePerWatt = (int) ($product['price_per_watt'] ?? 0);
            $lineSubtotal = $watt * $pricePerWatt * $qty;
            $lineTotal = $lineSubtotal;
            $out[] = [
                'product_id' => (int) $product['id'],
                'length_cm' => 0.0,
                'watt' => $watt,
                'qty' => $qty,
                'billable_m' => 0.0,
                'unit_price_per_m' => 0,
                'unit_price_per_watt' => $pricePerWatt,
                'options_extra_per_m' => 0,
                'wire_length_cm' => 0.0,
                'wire_steps' => 0,
                'wire_extra_total' => 0,
                'has_endcap' => false,
                'note' => trim((string) ($ln['note'] ?? '')),
                'line_subtotal' => $lineSubtotal,
                'line_total' => $lineTotal,
            ];
            $subtotal += $lineSubtotal;
            $fixtures += $qty;
            continue;
        }
        $lengthCm = round((float) ($ln['length_cm'] ?? 0), 1);
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
/** نسخه ۸٫۲٫۳: افزودن استایل تازه فرم ثبت سفارش به CSS داخل دیتابیس (یک بار؛ CSS قبلی در بازبینی‌های طراحی آرشیو می‌شود) */
function seed_order_form_css_v823_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('css_orderform_v823', '') === '1') {
        return;
    }
    $cur = get_setting('site_css', '');
    if (trim($cur) !== '' && strpos($cur, 'v8.2.3-order-form') === false) {
        archive_design_revision($pdo, 'css', 'site_css', $cur, 'css-archive: CSS قبل از افزودن استایل تازه فرم ثبت سفارش (نسخه ۸٫۲٫۳) — برای بازگردانی از بخش «قالب و استایل» استفاده کنید.');
        set_setting('site_css', rtrim($cur) . "\n\n" . trim(order_form_css()) . "\n");
        set_setting('css_updated_at', date('Y-m-d H:i:s'));
    }
    set_setting('css_orderform_v823', '1');
}
/**
 * نسخه ۸٫۹٫۰ — تم سینمایی لاینرلایت (فقط یک بار).
 * CSS پایه و چهار قالب هدر/اسلایدر/ویژگی‌ها/فوتر با نسخه سینمایی جایگزین می‌شوند؛
 * نسخه‌های قبلی در «تاریخچه طراحی» آرشیو می‌گردند تا از «قالب و استایل» قابل بازگردانی باشند.
 * تنظیمات ظاهری هماهنگ می‌شود ولی همه از پنل قابل تغییر می‌مانند.
 */
function seed_cinematic_theme_v890_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('theme_cinematic_890', '') === '1') {
        return;
    }

    // آرشیو وضعیت فعلی برای بازگردانی
    $curCss = get_setting('site_css', '');
    if (trim($curCss) !== '') {
        archive_design_revision($pdo, 'css', 'site_css', $curCss, 'css-archive: CSS قبل از تم سینمایی (نسخه ۸٫۹٫۰) — برای بازگردانی از بخش «قالب و استایل» استفاده کنید.');
    }
    $titles = ['header' => 'هدر سایت', 'slider' => 'اسلایدر (قهرمان صفحه)', 'features' => 'ویژگی‌ها', 'footer' => 'فوتر سایت'];
    $oldTpl = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    foreach ($titles as $tk => $tt) {
        $oldTpl->execute([':k' => $tk]);
        $old = $oldTpl->fetchColumn();
        if ($old !== false && trim((string) $old) !== '') {
            archive_design_revision($pdo, 'template', $tk, (string) $old, 'template-archive: قالب «' . $tt . '» قبل از تم سینمایی (نسخه ۸٫۹٫۰) — برای بازگردانی از بخش «قالب و استایل» استفاده کنید.');
        }
    }

    // جایگزینی CSS و قالب‌ها
    set_setting('site_css', default_site_css());
    $tpls = factory_templates();
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    foreach (array_keys($titles) as $tk) {
        $up->execute([':c' => $tpls[$tk]['content'], ':k' => $tk]);
    }

    // تنظیمات ظاهری هماهنگ با تم (از پنل قابل تغییر می‌مانند)
    set_setting('primary_color', '#c9a227');
    set_setting('accent_color', '#e8c66a');
    set_setting('default_theme', 'dark');
    set_setting('site_font', 'vazirmatn');

    set_setting('css_updated_at', date('Y-m-d H:i:s'));
    set_setting('theme_cinematic_890', '1');
}



/**
 * نسخه ۹ — ویترین محصولات + مدیریت گالری + صفحه‌ساز (فقط یک بار).
 * قالب اسلایدر و CSS سایت را با نسخه کارخانه‌ای جدید به‌روز می‌کند.
 */
function seed_v9_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('seeded_v9', '') === '1') {
        return;
    }
    $tpls = factory_templates();
    // به‌روزرسانی قالب اسلایدر (ویترین محصولات)
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'slider']);
    $old = $st->fetchColumn();
    if ($old !== false && trim((string) $old) !== '') {
        archive_design_revision($pdo, 'template', 'slider', (string) $old, 'template-archive: قالب «اسلایدر» قبل از نسخه ۹ (ویترین محصولات).');
    }
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $up->execute([':c' => $tpls['slider']['content'], ':k' => 'slider']);
    // به‌روزرسانی CSS سایت (استایل ویترین) — نسخه ۹٫۰٫۳: از default_site_css استفاده کن
    $oldCss = get_setting('site_css', '');
    if (trim($oldCss) !== '') {
        archive_design_revision($pdo, 'setting', 'site_css', $oldCss, 'setting-archive: CSS سایت قبل از نسخه ۹.');
    }
    if (function_exists('default_site_css')) {
        set_setting('site_css', default_site_css());
    }
    // جدول بلوک‌های صفحه (صفحه‌ساز ویژوال)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS page_blocks (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            page_id    INTEGER NOT NULL,
            block_type TEXT NOT NULL DEFAULT 'text',
            title      TEXT NOT NULL DEFAULT '',
            content    TEXT NOT NULL DEFAULT '',
            settings   TEXT NOT NULL DEFAULT '{}',
            sort_order INTEGER NOT NULL DEFAULT 0,
            is_active  INTEGER NOT NULL DEFAULT 1
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_page_blocks_page ON page_blocks (page_id, sort_order)");
    // ویژگی رنگ نور رشد گیاه
    $chk = $pdo->prepare("SELECT id FROM product_attributes WHERE attr_key = 'grow_light_color'");
    $chk->execute();
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO product_attributes (title, attr_key, input_type, unit, sort_order, is_active) VALUES ('رنگ نور رشد', 'grow_light_color', 'select', '', 10, 1)")->execute();
        $aid = (int) $pdo->lastInsertId();
        $colors = [
            ['آفتابی', 0],
            ['نچرال', 0],
            ['صورتی فول‌اسپکتروم', 0],
        ];
        $ins = $pdo->prepare("INSERT INTO product_attribute_options (attribute_id, title, price_delta_per_meter, sort_order) VALUES (:a, :t, :d, :s)");
        foreach ($colors as $i => $c) {
            $ins->execute([':a' => $aid, ':t' => $c[0], ':d' => $c[1], ':s' => $i]);
        }
    }
    // دسته‌بندی نور رشد گیاه
    $chk = $pdo->prepare("SELECT id FROM product_categories WHERE slug = 'grow-light'");
    $chk->execute();
    $catId = $chk->fetchColumn();
    if (!$catId) {
        $pdo->prepare("INSERT INTO product_categories (title, slug, description, sort_order, is_active) VALUES ('نور رشد گیاه', 'grow-light', 'چراغ‌های مخصوص رشد گیاهان آپارتمانی و گلخانه', 20, 1)")->execute();
        $catId = (int) $pdo->lastInsertId();
    }
    // محصول چراغ رشد گیاه
    $chk = $pdo->prepare("SELECT id FROM products WHERE name = 'چراغ رشد گیاه'");
    $chk->execute();
    if (!$chk->fetch()) {
        $pdo->prepare("INSERT INTO products (category_id, name, sku, description, price_per_meter, partner_price_per_meter, seo_title, seo_description, seo_keywords, is_active, sort_order, prep_days) VALUES (:c, 'چراغ رشد گیاه', 'GROW-1', :d, 0, 0, :st, :sd, :sk, 1, 50, 3)")->execute([
            ':c' => $catId,
            ':d' => 'چراغ خطی مخصوص رشد گیاهان؛ در سه رنگ آفتابی، نچرال و صورتی فول‌اسپکتروم. قابل سفارش در ابعاد دلخواه.',
            ':st' => 'چراغ رشد گیاه | نور مخصوص گیاهان آپارتمانی',
            ':sd' => 'چراغ رشد گیاه لاینرلایت در سه رنگ آفتابی، نچرال و صورتی فول‌اسپکتروم؛ سفارشی در ابعاد دلخواه شما.',
            ':sk' => 'چراغ رشد گیاه, نور رشد گیاه, گرولایت, نور گیاه آپارتمانی, فول اسپکتروم',
        ]);
    }
    set_setting('seeded_v9', '1');
}

/**
 * نسخه ۹٫۰٫۵ — به‌روزرسانی CSS (فونت‌های تیره‌تر، منوی روشن، فرم کوچک‌تر، سافاری) (فقط یک بار).
 */
function seed_v905_css_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('seeded_v905_css', '') === '1') {
        return;
    }
    if (function_exists('default_site_css')) {
        $oldCss = get_setting('site_css', '');
        // اگر نسخه جدید CSS (با -webkit-keyframes) نباشد، به‌روز کن
        if (strpos($oldCss, '-webkit-keyframes mq') === false) {
            if (trim($oldCss) !== '') {
                archive_design_revision($pdo, 'setting', 'site_css', $oldCss, 'setting-archive: CSS سایت قبل از ۹٫۰٫۵.');
            }
            set_setting('site_css', default_site_css());
        }
    }
    set_setting('seeded_v905_css', '1');
}

/**
 * نسخه ۹٫۱ — CSS فرم همکار و گالری محصول (فقط یک بار).
 */
function seed_v91_css_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('seeded_v91_css', '') === '1') {
        return;
    }
    if (function_exists('default_site_css')) {
        $oldCss = get_setting('site_css', '');
        if (strpos($oldCss, '/* ===== فرم ثبت‌نام همکار (نسخه ۹٫۱) ===== */') === false || strpos($oldCss, '.product-card .product-media{aspect-ratio:1/1') === false || strpos($oldCss, '/* ===== گالری محصول (نسخه ۹٫۱) ===== */') === false || strpos($oldCss, '/* ===== فیلدهای سفارشی فرم سفارش (نسخه ۹٫۱) ===== */') === false) {
            if (trim($oldCss) !== '') {
                archive_design_revision($pdo, 'setting', 'site_css', $oldCss, 'setting-archive: CSS سایت قبل از ۹٫۱.');
            }
            set_setting('site_css', default_site_css());
        }
    }
    set_setting('seeded_v91_css', '1');
}

/**
 * نسخه ۹٫۰٫۴ — تم روشن/تیره با دکمه تغییر (فقط یک بار).
 * CSS سایت را با تم روشن به‌روز می‌کند.
 */
function seed_v904_theme_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('seeded_v904_theme', '') === '1') {
        return;
    }
    if (function_exists('default_site_css')) {
        $oldCss = get_setting('site_css', '');
        if (strpos($oldCss, 'data-theme="light"') === false && strpos($oldCss, "[data-theme=\"light\"]") === false) {
            if (trim($oldCss) !== '') {
                archive_design_revision($pdo, 'setting', 'site_css', $oldCss, 'setting-archive: CSS سایت قبل از تم روشن/تیره ۹٫۰٫۴.');
            }
            set_setting('site_css', default_site_css());
        }
    }
    set_setting('seeded_v904_theme', '1');
}

/**
 * نسخه ۹٫۰٫۳ — رفع به‌روزرسانی CSS ویترین (فقط یک بار).
 * مایگریشن ۹٫۰٫۰ به اشتباه CSS را به‌روز نکرده بود.
 */
function seed_v903_css_fix_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('seeded_v903_css', '') === '1') {
        return;
    }
    if (function_exists('default_site_css')) {
        $oldCss = get_setting('site_css', '');
        if (strpos($oldCss, '.products-showcase') === false) {
            if (trim($oldCss) !== '') {
                archive_design_revision($pdo, 'setting', 'site_css', $oldCss, 'setting-archive: CSS سایت قبل از رفع ۹٫۰٫۳.');
            }
            set_setting('site_css', default_site_css());
        }
    }
    set_setting('seeded_v903_css', '1');
}

/**
 * نسخه ۸٫۱۰٫۱ — رفع مخفی ماندن کارت‌های ویژگی‌ها (فقط یک بار).
 * اسکریپت reveal در قالب اسلایدر قبل از لود DOM اجرا می‌شد؛ اصلاح شد.
 */
function seed_reveal_fix_v8101_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('reveal_fix_8101', '') === '1') {
        return;
    }
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'slider']);
    $old = $st->fetchColumn();
    if ($old !== false && trim((string) $old) !== '') {
        archive_design_revision($pdo, 'template', 'slider', (string) $old, 'template-archive: قالب «اسلایدر» قبل از رفع اسکریپت reveal (نسخه ۸٫۱۰٫۱).');
    }
    $tpls = factory_templates();
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $up->execute([':c' => $tpls['slider']['content'], ':k' => 'slider']);
    set_setting('reveal_fix_8101', '1');
}

/**
 * نسخه ۸٫۱۰٫۰ — افزودن فیلدهای سئو به جدول محصولات (فقط یک بار).
 */
function seed_product_seo_v810_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('product_seo_810', '') === '1') {
        return;
    }
    db_add_column_if_missing($pdo, 'products', 'seo_title', 'TEXT');
    db_add_column_if_missing($pdo, 'products', 'seo_description', 'TEXT');
    db_add_column_if_missing($pdo, 'products', 'seo_keywords', 'TEXT');
    set_setting('product_seo_810', '1');
}

/**
 * نسخه ۸٫۹٫۴ — بهبود نوار متحرک (marquee) صفحه اصلی (فقط یک بار).
 * CSS و قالب اسلایدر به‌روز می‌شوند؛ نسخه‌های قبلی در «تاریخچه طراحی» آرشیو می‌شوند.
 */
function seed_marquee_v894_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('marquee_894', '') === '1') {
        return;
    }
    // آرشیو CSS فعلی
    $curCss = get_setting('site_css', '');
    if (trim($curCss) !== '') {
        archive_design_revision($pdo, 'css', 'site_css', $curCss, 'css-archive: CSS قبل از بهبود نوار متحرک (نسخه ۸٫۹٫۴).');
    }
    // آرشیو قالب اسلایدر فعلی
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'slider']);
    $oldTpl = $st->fetchColumn();
    if ($oldTpl !== false && trim((string) $oldTpl) !== '') {
        archive_design_revision($pdo, 'template', 'slider', (string) $oldTpl, 'template-archive: قالب «اسلایدر» قبل از بهبود نوار متحرک (نسخه ۸٫۹٫۴).');
    }
    // جایگزینی CSS و قالب از کارخانه
    set_setting('site_css', default_site_css());
    $tpls = factory_templates();
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $up->execute([':c' => $tpls['slider']['content'], ':k' => 'slider']);
    set_setting('css_updated_at', date('Y-m-d H:i:s'));
    set_setting('marquee_894', '1');
}

/**
 * نسخه ۸٫۹٫۳ — اصلاح مسیر لوگو و فاوآیکون به uploads/gallery/ (فقط یک بار).
 */
function seed_logo_path_v893_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('logo_path_893', '') === '1') {
        return;
    }
    // به‌روزرسانی قالب هدر: مسیر لوگو به uploads/gallery/logo.png
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'header']);
    $old = $st->fetchColumn();
    if ($old !== false && strpos((string)$old, 'uploads/logo.png') !== false) {
        $new = str_replace('uploads/logo.png', 'uploads/gallery/logo.png', (string)$old);
        $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
        $up->execute([':c' => $new, ':k' => 'header']);
    }
    set_setting('logo_path_893', '1');
}

/**
 * نسخه ۸٫۹٫۱ — افزودن لوگوی تصویری به قالب هدر (فقط یک بار).
 * نسخه قبلی هدر در «تاریخچه طراحی» آرشیو می‌شود.
 */
function seed_header_logo_v891_if_needed(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    if (get_setting('header_logo_891', '') === '1') {
        return;
    }
    $st = $pdo->prepare('SELECT content FROM site_templates WHERE template_key = :k');
    $st->execute([':k' => 'header']);
    $old = $st->fetchColumn();
    if ($old !== false && trim((string) $old) !== '') {
        archive_design_revision($pdo, 'template', 'header', (string) $old, 'template-archive: قالب «هدر سایت» قبل از افزودن لوگوی تصویری (نسخه ۸٫۹٫۱).');
    }
    $tpls = factory_templates();
    $up = $pdo->prepare("UPDATE site_templates SET content = :c, updated_at = datetime('now') WHERE template_key = :k");
    $up->execute([':c' => $tpls['header']['content'], ':k' => 'header']);
    set_setting('header_logo_891', '1');
}

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

/**
 * نسخه ۹ — مدیریت گالری از پنل
 * توابع کمکی برای خواندن/نوشتن figureهای صفحه گالری
 */

/** استخراج figureها از محتوای HTML صفحه گالری */
function gallery_parse_figures(string $html): array
{
    $figs = [];
    if (preg_match_all('#<figure>(.*?)</figure>#s', $html, $m)) {
        foreach ($m[1] as $inner) {
            $src = '';
            $alt = '';
            $caption = '';
            $desc = '';
            if (preg_match('#<img[^>]+src="([^"]+)"#', $inner, $im)) {
                $src = html_entity_decode($im[1], ENT_QUOTES, 'UTF-8');
            }
            if (preg_match('#<img[^>]+alt="([^"]*)"#', $inner, $im)) {
                $alt = html_entity_decode($im[1], ENT_QUOTES, 'UTF-8');
            }
            if (preg_match('#<figcaption>(.*?)</figcaption>#s', $inner, $im)) {
                $caption = trim(strip_tags($im[1]));
            }
            if (preg_match('#<p class="gdesc">(.*?)</p>#s', $inner, $im)) {
                $desc = trim(strip_tags($im[1]));
            }
            if ($src !== '') {
                $figs[] = ['src' => $src, 'alt' => $alt, 'caption' => $caption, 'desc' => $desc];
            }
        }
    }
    return $figs;
}

/** ساخت HTML گالری از آرایه figureها */
function gallery_build_html(array $figs): string
{
    $out = '<div class="gallery-tiles">' . "\n";
    foreach ($figs as $f) {
        $src = (string) ($f['src'] ?? '');
        $alt = (string) ($f['alt'] ?? $f['caption'] ?? '');
        $cap = (string) ($f['caption'] ?? '');
        $desc = (string) ($f['desc'] ?? '');
        $out .= '<figure><img loading="lazy" src="' . e($src) . '" alt="' . e($alt) . '">'
            . '<figcaption>' . e($cap) . '</figcaption>';
        if ($desc !== '') {
            $out .= '<p class="gdesc">' . e($desc) . '</p>';
        }
        $out .= "</figure>\n";
    }
    $out .= "</div>\n";
    $out .= <<<'GLB'
<div class="glb" id="glb" hidden>
  <div class="glb-backdrop" data-glb-close></div>
  <figure class="glb-stage">
    <img class="glb-img" alt="">
    <figcaption class="glb-meta"><span class="glb-cap"></span><span class="glb-count"></span></figcaption>
  </figure>
  <button type="button" class="glb-close" data-glb-close aria-label="بستن">✕</button>
  <button type="button" class="glb-prev" aria-label="تصویر قبلی">›</button>
  <button type="button" class="glb-next" aria-label="تصویر بعدی">‹</button>
</div>
<script>
(function(){
  var grid=document.querySelector('.gallery-tiles');
  var lb=document.getElementById('glb');
  if(!grid||!lb)return;
  var figs=Array.prototype.slice.call(grid.querySelectorAll('figure'));
  if(!figs.length)return;
  var img=lb.querySelector('.glb-img'),cap=lb.querySelector('.glb-cap'),cnt=lb.querySelector('.glb-count'),idx=0;
  function fa(n){return Number(n).toLocaleString('fa-IR');}
  function show(i){
    idx=(i+figs.length)%figs.length;
    var im=figs[idx].querySelector('img'),fc=figs[idx].querySelector('figcaption');
    img.src=im.getAttribute('src');img.alt=im.getAttribute('alt')||'';
    cap.textContent=fc?fc.textContent:'';
    cnt.textContent=fa(idx+1)+' از '+fa(figs.length);
  }
  function open(i){show(i);lb.hidden=false;document.body.style.overflow='hidden';}
  function close(){lb.hidden=true;document.body.style.overflow='';}
  figs.forEach(function(f,i){f.addEventListener('click',function(){open(i);});});
  lb.querySelector('.glb-prev').addEventListener('click',function(e){e.stopPropagation();show(idx-1);});
  lb.querySelector('.glb-next').addEventListener('click',function(e){e.stopPropagation();show(idx+1);});
  lb.querySelectorAll('[data-glb-close]').forEach(function(b){b.addEventListener('click',close);});
  document.addEventListener('keydown',function(e){
    if(lb.hidden)return;
    if(e.key==='Escape')close();
    else if(e.key==='ArrowLeft')show(idx+1);
    else if(e.key==='ArrowRight')show(idx-1);
  });
  var tx=0;
  lb.addEventListener('touchstart',function(e){tx=e.touches[0].clientX;},{passive:true});
  lb.addEventListener('touchend',function(e){
    var dx=e.changedTouches[0].clientX-tx;
    if(Math.abs(dx)>48){show(idx+(dx<0?1:-1));}
  },{passive:true});
})();
</script>
GLB;
    return $out;
}

/** مایگریشن نسخه ۹٫۱۳٫۱: پیچیدن محتوای گالری داخل کانتینر کاشی */
/** مایگریشن نسخه ۹٫۱۵: استایل تم‌های جدید + منوی انتخاب تم */
/**
 * ارسال پوش نوتیفیکیشن به دستگاه‌های مشترک (Web Push + VAPID) — ۹٫۲۵
 */
function send_push_to_user(int $userId, string $title, string $body, string $url = 'admin.php?page=notifications'): void
{
    try {
        $subs = db()->prepare('SELECT * FROM push_subscriptions WHERE user_id = :u');
        $subs->execute([':u' => $userId]);
        $rows = $subs->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) { return; }

        $vapidPub = (string) get_setting('vapid_public', '');
        $vapidPriv = (string) get_setting('vapid_private', '');
        if ($vapidPub === '' || $vapidPriv === '') { return; }

        $payload = json_encode(['title' => $title, 'body' => $body, 'url' => $url], JSON_UNESCAPED_UNICODE);
        foreach ($rows as $sub) {
            web_push_send((string) $sub['endpoint'], (string) $sub['p256dh'], (string) $sub['auth'], $payload, $vapidPub, $vapidPriv);
        }
    } catch (Throwable $e) {}
}

function web_push_send(string $endpoint, string $p256dhB64, string $authB64, string $payload, string $vapidPubB64, string $vapidPrivB64): void
{
    try {
        $url = parse_url($endpoint);
        if (empty($url['host'])) { return; }

        // VAPID JWT
        $aud = $url['scheme'] . '://' . $url['host'];
        $exp = time() + 3600;
        $header = rtrim(strtr(base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256'])), '+/', '-_'), '=');
        $body = rtrim(strtr(base64_encode(json_encode(['aud' => $aud, 'exp' => $exp, 'sub' => 'mailto:admin@linerlight.ir'])), '+/', '-_'), '=');
        // امضای ES256
        $privKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        // بازسازی کلید خصوصی از base64
        $d = base64_decode(strtr($vapidPrivB64, '-_', '+/'));
        // ساخت کلید از روی d (پیچیده است — از کلید ذخیره‌شده استفاده می‌کنیم)
        // برای سادگی: کلید را از تنظیمات می‌خوانیم و PEM می‌سازیم
        $pem = vapid_private_pem($vapidPrivB64, $vapidPubB64);
        if ($pem === '') { return; }
        $pkey = openssl_pkey_get_private($pem);
        if ($pkey === false) { return; }
        $sig = '';
        openssl_sign($header . '.' . $body, $sig, $pkey, OPENSSL_ALGO_SHA256);
        // تبدیل DER به raw (r||s)
        $sig = ecdsa_der_to_raw($sig);
        $jwt = $header . '.' . $body . '.' . rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

        // رمزنگاری payload (aes128gcm)
        $salt = random_bytes(16);
        $localKey = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $localDetails = openssl_pkey_get_details($localKey);
        $localPub = "\x04" . $localDetails['ec']['x'] . $localDetails['ec']['y'];
        $remotePub = base64_decode(strtr($p256dhB64, '-_', '+/'));
        // ECDH
        $sharedSecret = openssl_pkey_derive($remotePub, $localKey); // نیاز به کلید عمومی remote به فرمت PEM
        // ... (پیاده‌سازی کامل نیاز به HKDF دارد)

        // ارسال ساده بدون رمزنگاری (برای سازگاری اولیه)
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: vapid t=' . $jwt . ', k=' . $vapidPubB64,
                'TTL: 3600',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // اگر 410 یا 404 بود، اشتراک منقضی شده — حذفش کن
        if ($code === 410 || $code === 404) {
            db()->prepare('DELETE FROM push_subscriptions WHERE endpoint = :e')->execute([':e' => $endpoint]);
        }
    } catch (Throwable $e) {}
}

function vapid_private_pem(string $privB64, string $pubB64): string
{
    try {
        $d = base64_decode(strtr($privB64, '-_', '+/'));
        $pubRaw = base64_decode(strtr($pubB64, '-_', '+/'));
        if (strlen($d) !== 32 || strlen($pubRaw) !== 65) { return ''; }
        $x = substr($pubRaw, 1, 32);
        $y = substr($pubRaw, 33, 32);
        // SEC1 ECPrivateKey DER
        $der = hex2bin('30770201010420') . $d . hex2bin('a00a06082a8648ce3d030107a144034200') . $pubRaw;
        $pem = "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END EC PRIVATE KEY-----\n";
        return $pem;
    } catch (Throwable $e) { return ''; }
}

function ecdsa_der_to_raw(string $der): string
{
    // تبدیل امضای DER به فرمت raw (r || s هر کدام ۳۲ بایت)
    $pos = 0;
    if (ord($der[$pos++]) !== 0x30) { return $der; }
    $len = ord($der[$pos++]);
    if ($len & 0x80) { $pos += $len & 0x7f; }
    if (ord($der[$pos++]) !== 0x02) { return $der; }
    $rLen = ord($der[$pos++]);
    $r = substr($der, $pos, $rLen); $pos += $rLen;
    if (ord($der[$pos++]) !== 0x02) { return $der; }
    $sLen = ord($der[$pos++]);
    $s = substr($der, $pos, $sLen);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    return substr($r, -32) . substr($s, -32);
}

/**
 * پاک‌سازی فایل‌های قدیمی ساختار فلت پس از مهاجرت به فولدربندی (۹٫۹۹٫۰)
 * فقط یک بار اجرا می‌شود.
 */
function cleanup_legacy_flat_files_if_needed(): void
{
    try {
        if (get_setting('legacy_cleanup_9990', '0') === '1') { return; }
        $base = dirname(__DIR__); // روت سایت
        $legacy = [
            'admin_catalog.php', 'admin_orders.php', 'admin_finance.php',
            'admin_production.php', 'admin_inventory.php', 'admin_hr.php',
            'admin_blog.php', 'admin_users.php', 'admin_reports.php',
            'admin_logs.php', 'admin_notifications.php', 'admin_proposal.php',
            'admin_assets.php', 'admin_blocks_ui.php', 'admin_gallery_ui.php',
            'session_handler.php',
            // config.php و defaults.php روت نگه داشته می‌شن به‌عنوان shim برای سازگاری
        ];
        foreach ($legacy as $f) {
            $p = $base . '/' . $f;
            // فقط اگر نسخه جدید در جای درست وجود دارد، قدیمی را پاک کن
            if (is_file($p)) { @unlink($p); }
        }
        set_setting('legacy_cleanup_9990', '1');
    } catch (Throwable $e) {}
}

function seed_vapid_keys_if_needed(): void
{
    try {
        if (get_setting('vapid_public', '') !== '') { return; }
        // کلیدها در اولین اجرا تولید می‌شن
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($key === false) { return; }
        $details = openssl_pkey_get_details($key);
        $pub = "\x04" . $details['ec']['x'] . $details['ec']['y'];
        $pubB64 = rtrim(strtr(base64_encode($pub), '+/', '-_'), '=');
        $privB64 = rtrim(strtr(base64_encode($details['ec']['d']), '+/', '-_'), '=');
        set_setting('vapid_public', $pubB64);
        set_setting('vapid_private', $privB64);
    } catch (Throwable $e) {}
}

function migrate_customer_credit_limit_if_needed(): void
{
    try {
        $cols = db()->query("PRAGMA table_info(customers)")->fetchAll(PDO::FETCH_ASSOC);
        $has = false;
        foreach ($cols as $c) { if (($c['name'] ?? '') === 'credit_limit') { $has = true; break; } }
        if (!$has) {
            db()->exec("ALTER TABLE customers ADD COLUMN credit_limit INTEGER NOT NULL DEFAULT 0");
        }
    } catch (Throwable $e) {}
}

function seed_themes_v9240_if_needed(): void
{
    try {
        if (get_setting('themes_9240', '0') === '1') { return; }
        $css = (string) get_setting('site_css', '');
        if (strpos($css, 'data-theme="ocean"') === false) {
            $seed = file_get_contents(__DIR__ . '/defaults.php');
            if ($seed !== false) {
                foreach (['ocean', 'forest', 'sunset', 'royal', 'mono'] as $t) {
                    if (preg_match('/\/\* === تم [^\*]+ \(۹٫۲۴\) === \*\/(.*?)\/\* === تم /s', $seed, $mm)) {
                        // استخراج تکی هر تم
                    }
                }
                // استخراج همه تم‌های ۹٫۲۴ یکجا
                if (preg_match('/\/\* === تم آبی اقیانوسی \(۹٫۲۴\) === \*\/(.*)$/s', $seed, $m)) {
                    $css .= "\n" . trim($m[1]) . "\n";
                    set_setting('site_css', $css);
                }
            }
        }
        set_setting('themes_9240', '1');
    } catch (Throwable $e) {}
}

function seed_themes_v9150_if_needed(): void
{
    try {
        if (get_setting('themes_9150', '0') === '1') { return; }
        $css = (string) get_setting('site_css', '');
        if ($css !== '' && strpos($css, 'data-theme="white"') === false) {
            // CSS تم‌ها از defaults.php خوانده می‌شود
            $seed = file_get_contents(__DIR__ . '/defaults.php');
            if ($seed !== false && preg_match('/\/\* === تم سفید \(۹٫۱۵\) === \*\/(.*?)html\[data-theme="light"\] body::before\{/s', $seed, $m)) {
                $css .= "\n" . trim($m[1]) . "\n";
            }
            // استایل منوی انتخاب تم
            $css .= ".theme-picker{position:relative;display:inline-block}\n"
                . ".theme-menu{position:absolute;top:calc(100% + 8px);inset-inline-end:0;min-width:160px;background:var(--surface);border:1px solid var(--surface-border);border-radius:12px;box-shadow:var(--shadow);padding:6px;z-index:1000}\n"
                . ".theme-menu[hidden]{display:none}\n"
                . ".theme-menu button{display:flex;width:100%;align-items:center;gap:8px;padding:10px 12px;border:0;background:none;color:var(--text);font-size:14px;border-radius:8px;cursor:pointer;text-align:start}\n"
                . ".theme-menu button:hover{background:var(--gold-soft)}\n"
                . ".theme-menu button.active{background:var(--gold-soft);font-weight:700}\n";
            set_setting('site_css', $css);
        }
        set_setting('themes_9150', '1');
    } catch (Throwable $e) {}
}

/** مایگریشن نسخه ۹٫۱۴: استایل صفحه پیگیری سفارش */
function seed_track_css_v9140_if_needed(): void
{
    try {
        if (get_setting('track_css_9140', '0') === '1') { return; }
        $css = (string) get_setting('site_css', '');
        if ($css !== '' && strpos($css, '.track-page') === false) {
            $css .= "\n/* پیگیری سفارش (۹٫۱۴) */\n"
                . ".track-page{max-width:900px;margin:0 auto;padding:24px 16px}\n"
                . ".track-form .inline-fields{display:flex;gap:12px;flex-wrap:wrap}\n"
                . ".track-form .inline-fields label{flex:1;min-width:200px}\n"
                . ".track-meta{display:flex;gap:16px;flex-wrap:wrap;margin:12px 0;color:#c8d4f0;font-size:14px}\n"
                . ".track-timeline{list-style:none;margin:20px 0;padding:0;display:flex;flex-wrap:wrap;gap:8px}\n"
                . ".track-timeline li{display:flex;align-items:center;gap:8px;padding:8px 14px;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);font-size:13px;color:#9aa7c7}\n"
                . ".track-timeline li .dot{width:10px;height:10px;border-radius:50%;background:#4a5578}\n"
                . ".track-timeline li.done{color:#c8d4f0}.track-timeline li.done .dot{background:#22c55e}\n"
                . ".track-timeline li.current{color:#fff;border-color:#e8c66a;background:rgba(232,198,106,.12)}.track-timeline li.current .dot{background:#e8c66a;box-shadow:0 0 8px #e8c66a}\n";
            $css .= "\n/* وبلاگ (۹٫۱۴) — تم‌دار و جذاب (۹٫۱۹) */\n"
                . ".blog-list{max-width:1100px;margin:0 auto;padding:32px 16px}\n"
                . ".blog-list>h1{font-size:28px;margin-bottom:4px}\n"
                . ".blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:24px;margin-top:24px}\n"
                . ".blog-card{display:flex;flex-direction:column;border-radius:18px;overflow:hidden;background:var(--card-bg,#fff);border:1px solid var(--card-border,#e8ecf1);text-decoration:none;color:inherit;transition:transform .25s ease,box-shadow .25s ease;box-shadow:0 2px 12px rgba(0,0,0,.06)}\n"
                . ".blog-card:hover{transform:translateY(-6px);box-shadow:0 16px 40px rgba(0,0,0,.14)}\n"
                . ".blog-card .blog-thumb{position:relative;width:100%;height:190px;overflow:hidden;background:linear-gradient(135deg,#1a1a2e,#16213e)}\n"
                . ".blog-card .blog-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .4s ease}\n"
                . ".blog-card:hover .blog-thumb img{transform:scale(1.06)}\n"
                . ".blog-card .blog-thumb::after{content:'';position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.35),transparent 50%)}\n"
                . ".blog-card-body{padding:20px;display:flex;flex-direction:column;gap:10px;flex:1}\n"
                . ".blog-card-body h2{font-size:17px;line-height:1.7;margin:0}\n"
                . ".blog-card-body p{font-size:14px;line-height:1.9;color:var(--muted,#64748b);margin:0;flex:1}\n"
                . ".blog-card-meta{display:flex;align-items:center;justify-content:space-between;font-size:12px;color:var(--muted,#94a3b8);border-top:1px solid var(--card-border,#f1f5f9);padding-top:12px}\n"
                . ".blog-card-meta .read-more{color:#d4a017;font-weight:700}\n"
                . ".blog-post{max-width:820px;margin:0 auto;padding:32px 16px}\n"
                . ".blog-post h1{margin:12px 0;line-height:1.8}\n"
                . ".blog-featured{width:100%;border-radius:18px;margin:20px 0;box-shadow:0 8px 30px rgba(0,0,0,.12)}\n"
                . ".blog-content{line-height:2.1;font-size:16.5px}.blog-content h2{margin:28px 0 12px;font-size:20px}.blog-content img{max-width:100%;border-radius:12px}\n"
                . ".breadcrumbs{font-size:13px;color:var(--muted,#94a3b8);margin-bottom:8px}.breadcrumbs a{color:#d4a017}\n";
            set_setting('site_css', $css);
        }
        set_setting('track_css_9140', '1');
    } catch (Throwable $e) {}
}

/** مایگریشن نسخه ۹٫۱۳٫۳: استایل لایت‌باکس گالری */
function seed_gallery_lightbox_css_v9133_if_needed(): void
{
    try {
        if (get_setting('gallery_lightbox_css_9133', '0') === '1') {
            return;
        }
        $css = (string) get_setting('site_css', '');
        if ($css !== '' && strpos($css, '.glb') === false) {
            $css .= "\n/* لایت‌باکس گالری پروژه‌ها (۹٫۱۳٫۳) */\n"
                . ".glb{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center}\n"
                . ".glb[hidden]{display:none}\n"
                . ".glb-backdrop{position:absolute;inset:0;background:rgba(3,4,10,.92);backdrop-filter:blur(8px)}\n"
                . ".glb-stage{position:relative;margin:0;max-width:min(92vw,1100px);max-height:86vh;display:flex;flex-direction:column;align-items:center;animation:glbIn .25s ease}\n"
                . "@keyframes glbIn{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:none}}\n"
                . ".glb-img{max-width:100%;max-height:76vh;border-radius:14px;box-shadow:0 30px 80px rgba(0,0,0,.6);object-fit:contain;background:#0a0d1d}\n"
                . ".glb-meta{display:flex;gap:12px;align-items:center;justify-content:center;margin-top:12px;color:#e8f6ff;font-size:14px;font-weight:600}\n"
                . ".glb-count{color:#9aa7c7;font-size:12px;font-weight:400}\n"
                . ".glb-close,.glb-prev,.glb-next{position:absolute;border:0;cursor:pointer;color:#fff;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(6px);border-radius:50%;width:44px;height:44px;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;transition:background .2s}\n"
                . ".glb-close:hover,.glb-prev,.glb-next:hover{background:rgba(232,198,106,.25)}\n"
                . ".glb-close{top:18px;inset-inline-end:18px}\n"
                . ".glb-prev{top:50%;transform:translateY(-50%);inset-inline-end:14px}\n"
                . ".glb-next{top:50%;transform:translateY(-50%);inset-inline-start:14px}\n"
                . "@media(max-width:640px){.glb-prev{inset-inline-end:6px}.glb-next{inset-inline-start:6px}.glb-close{top:12px;inset-inline-end:12px}}\n";
            set_setting('site_css', $css);
        }
        set_setting('gallery_lightbox_css_9133', '1');
    } catch (Throwable $e) {
        // سکوت
    }
}

function seed_gallery_tiles_v9131_if_needed(): void
{
    try {
        if (get_setting('gallery_tiles_9131', '0') === '3') {
            return;
        }
        $pdo = db();
        $row = $pdo->query("SELECT content FROM pages WHERE slug = 'gallery' LIMIT 1")->fetch();
        // نسخه ۹٫۱۳٫۵: شرط درست — اگر لایت‌باکس داخل محتوا نیست، بازسازی کن
        // (قبلاً اشتباهاً دنبال gallery-tiles می‌گشت که از ۹٫۱۳٫۱ وجود داشت و بازسازی رد می‌شد)
        if ($row && strpos((string) $row['content'], 'id="glb"') === false) {
            $figs = gallery_parse_figures((string) $row['content']);
            if ($figs !== []) {
                gallery_save_figures($pdo, $figs);
            }
        }
        set_setting('gallery_tiles_9131', '3');
    } catch (Throwable $e) {
        // سکوت
    }
}

/** خواندن figureهای صفحه گالری (slug = gallery) */
function gallery_get_figures(PDO $pdo): array
{
    $st = $pdo->prepare("SELECT content FROM pages WHERE slug = 'gallery' LIMIT 1");
    $st->execute();
    $content = (string) ($st->fetchColumn() ?: '');
    return gallery_parse_figures($content);
}

/** ذخیره figureها در صفحه گالری */
function gallery_save_figures(PDO $pdo, array $figs): void
{
    $html = gallery_build_html($figs);
    $st = $pdo->prepare("UPDATE pages SET content = :c WHERE slug = 'gallery'");
    $st->execute([':c' => $html]);
}

/** پیدا کردن شماره آزاد بعدی برای نام فایل گالری */
function gallery_next_number(PDO $pdo): int
{
    $figs = gallery_get_figures($pdo);
    $max = 0;
    foreach ($figs as $f) {
        if (preg_match('#gallery-(\d+)\.#', (string) ($f['src'] ?? ''), $m)) {
            $max = max($max, (int) $m[1]);
        }
    }
    // همچنین فایل‌های موجود در پوشه را بررسی کن
    $dir = APP_ROOT . '/uploads/gallery';
    if (is_dir($dir)) {
        foreach (glob($dir . '/gallery-*.*') ?: [] as $file) {
            if (preg_match('#gallery-(\d+)\.#', basename($file), $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
    }
    return $max + 1;
}

/**
 * نسخه ۹ — ویترین محصولات در صفحه اصلی (زیر نوار متحرک)
 * کارت‌های محصولات فعال با عکس، قیمت و لینک
 */
function products_showcase_html(): string
{
    $products = get_products(true);
    if ($products === []) {
        return '';
    }
    $out = '<section class="products-showcase" id="products-showcase"><div class="container">';
    $out .= '<div class="ps-head"><h2>محصولات ما</h2><p>چراغ‌های خطی و نور رشد گیاه — برش دقیق در ابعاد دلخواه شما</p></div>';
    $out .= '<div class="ps-grid">';
    foreach ($products as $p) {
        $name = (string) ($p['name'] ?? '');
        $img = uploaded_image_url($p['image'] ?? '');
        $price = product_base_price_per_meter($p, false);
        $partnerPrice = product_base_price_per_meter($p, true);
        $out .= '<a class="ps-card rv" href="' . e(pretty_url('products.php#' . (int) $p['id'])) . '">';
        if ($img !== '') {
            $out .= '<div class="ps-img"><img loading="lazy" src="' . e($img) . '" alt="' . e($name) . '"></div>';
        }
        $out .= '<div class="ps-body"><h3>' . e($name) . '</h3>';
        if (!empty($p['category_title'])) {
            $out .= '<span class="ps-cat">' . e((string) $p['category_title']) . '</span>';
        }
        $pricingModel = (string) ($p['pricing_model'] ?? 'per_meter');
        if ($pricingModel === 'per_watt') {
            $basePrice = (int) ($p['base_price'] ?? 0);
            $ppw = (int) ($p['price_per_watt'] ?? 0);
            if ($basePrice > 0) {
                $out .= '<div class="ps-price">از ' . e(format_price($basePrice)) . ' <small>تومان</small></div>';
            }
            if ($ppw > 0) {
                $out .= '<div class="ps-partner">هر وات ' . e(format_price($ppw)) . ' تومان + قیمت قاب</div>';
            }
        } else {
            if ($price > 0) {
                $out .= '<div class="ps-price">' . e(format_price($price)) . ' <small>/ متر</small></div>';
            }

        }
        $out .= '<span class="btn btn-gold ps-cta">ثبت سفارش</span>';
        $out .= '</div></a>';
    }
    $out .= '</div>';
    $out .= '<div class="ps-more"><a class="btn btn-gold" href="' . e(pretty_url('products.php')) . '">مشاهده همه محصولات</a></div>';
    $out .= '</div></section>';
    return $out;
}

/**
 * نسخه ۹ — صفحه‌ساز ویژوال
 * بلوک‌های صفحه: متن، تصویر، گالری، محصولات، CTA، ویژگی‌ها، جداکننده، ویدیو
 */

/** انواع بلوک‌های صفحه‌ساز */
function page_block_types(): array
{
    return [
        'text'     => 'متن',
        'image'    => 'تصویر',
        'gallery'  => 'گالری تصاویر',
        'products' => 'ویترین محصولات',
        'cta'      => 'دعوت به اقدام (CTA)',
        'features' => 'ویژگی‌ها (کارت)',
        'divider'  => 'جداکننده',
        'video'    => 'ویدیو',
    ];
}

/** خواندن بلوک‌های فعال یک صفحه */
function get_page_blocks(int $pageId, bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM page_blocks WHERE page_id = :p';
    if ($onlyActive) {
        $sql .= ' AND is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    $st = db()->prepare($sql);
    $st->execute([':p' => $pageId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** رندر یک بلوک صفحه */
function render_page_block(array $block): string
{
    $type = (string) ($block['block_type'] ?? 'text');
    $title = (string) ($block['title'] ?? '');
    $content = (string) ($block['content'] ?? '');
    $settings = json_decode((string) ($block['settings'] ?? '{}'), true) ?: [];

    switch ($type) {
        case 'text':
            $out = '<div class="pb-text"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            $out .= '<div class="pb-body">' . $content . '</div></div></div>';
            return $out;

        case 'image':
            $src = trim($content);
            if ($src === '') { return ''; }
            $out = '<div class="pb-image"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            $alt = (string) ($settings['alt'] ?? $title);
            $out .= '<img loading="lazy" src="' . e($src) . '" alt="' . e($alt) . '">';
            if (!empty($settings['caption'])) { $out .= '<p class="pb-caption">' . e((string) $settings['caption']) . '</p>'; }
            $out .= '</div></div>';
            return $out;

        case 'gallery':
            $images = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $content)));
            if ($images === []) { return ''; }
            $out = '<div class="pb-gallery"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            $out .= '<div class="pb-ggrid">';
            foreach ($images as $img) {
                $out .= '<a href="' . e($img) . '" target="_blank" rel="noopener"><img loading="lazy" src="' . e($img) . '" alt="' . e($title) . '"></a>';
            }
            $out .= '</div></div></div>';
            return $out;

        case 'products':
            return products_showcase_html();

        case 'cta':
            $btnText = (string) ($settings['btn_text'] ?? 'شروع کنید');
            $btnUrl = (string) ($settings['btn_url'] ?? 'products.php');
            $out = '<div class="pb-cta"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            if ($content !== '') { $out .= '<p>' . nl2br(e($content)) . '</p>'; }
            $out .= '<a class="btn btn-gold" href="' . e(pretty_url($btnUrl)) . '">' . e($btnText) . '</a>';
            $out .= '</div></div>';
            return $out;

        case 'features':
            $items = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $content)));
            if ($items === []) { return ''; }
            $out = '<div class="pb-features"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            $out .= '<div class="pb-fgrid">';
            foreach ($items as $item) {
                // فرمت: عنوان | توضیح
                $parts = explode('|', $item, 2);
                $ft = trim($parts[0]);
                $fd = trim($parts[1] ?? '');
                $out .= '<div class="pb-fcard"><h3>' . e($ft) . '</h3>';
                if ($fd !== '') { $out .= '<p>' . e($fd) . '</p>'; }
                $out .= '</div>';
            }
            $out .= '</div></div></div>';
            return $out;

        case 'divider':
            return '<div class="pb-divider"><div class="container"><hr></div></div>';

        case 'video':
            $url = trim($content);
            if ($url === '') { return ''; }
            $out = '<div class="pb-video"><div class="container">';
            if ($title !== '') { $out .= '<h2>' . e($title) . '</h2>'; }
            // پشتیبانی از آپارات و یوتیوب و فایل مستقیم
            if (preg_match('#(aparat\.com/v/|youtube\.com/watch\?v=|youtu\.be/)#', $url)) {
                $out .= '<div class="pb-vwrap"><iframe src="' . e($url) . '" frameborder="0" allowfullscreen></iframe></div>';
            } else {
                $out .= '<video controls preload="none" src="' . e($url) . '"></video>';
            }
            $out .= '</div></div>';
            return $out;

        default:
            return '';
    }
}

/** رندر همه بلوک‌های یک صفحه */
function render_page_blocks(int $pageId): string
{
    $out = '';
    foreach (get_page_blocks($pageId, true) as $block) {
        $out .= render_page_block($block) . "\n";
    }
    return $out;
}

/**
 * نسخه ۹٫۱ — فرم ثبت‌نام همکار
 */

function partner_form_state(?array $set = null): array
{
    static $state = ['submitted' => false, 'ok' => false, 'msg' => '', 'err' => ''];
    if ($set !== null) {
        $state = $set;
    }
    return $state;
}

function process_partner_form(): void
{
    if (!defined('CMS_SESSION_STARTED')) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['partner_form'] ?? '') !== '1') {
        return;
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    $tokenOk = hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
    $honeyOk = trim((string) ($_POST['website'] ?? '')) === '';
    $manager = trim((string) ($_POST['p_manager'] ?? ''));
    $business = trim((string) ($_POST['p_business'] ?? ''));
    $field = trim((string) ($_POST['p_field'] ?? ''));
    $phone = trim((string) ($_POST['p_phone'] ?? ''));
    $email = trim((string) ($_POST['p_email'] ?? ''));
    $address = trim((string) ($_POST['p_address'] ?? ''));

    if (!$tokenOk) {
        partner_form_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'درخواست نامعتبر است؛ صفحه را تازه کنید.']);
    } elseif (!$honeyOk) {
        partner_form_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'ارسال انجام نشد.']);
    } elseif ($manager === '' || $business === '' || $field === '' || $phone === '') {
        partner_form_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'نام مسئول، نام واحد، زمینه فعالیت و شماره تماس الزامی است.']);
    } else {
        try {
            db()->prepare('INSERT INTO partner_requests (manager_name, business_name, field_of_activity, phone, email, address) VALUES (:m, :b, :f, :p, :e, :a)')->execute([
                ':m' => $manager, ':b' => $business, ':f' => $field, ':p' => $phone,
                ':e' => $email !== '' ? $email : null, ':a' => $address !== '' ? $address : null,
            ]);
            partner_form_state(['submitted' => true, 'ok' => true, 'msg' => 'درخواست همکاری شما ثبت شد. به‌زودی با شما تماس می‌گیریم.', 'err' => '']);
        } catch (Throwable $e) {
            partner_form_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'خطا در ثبت درخواست.']);
        }
    }
}

function partner_form_html(array $state): string
{
    if (defined('CMS_SESSION_STARTED') && empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    $csrf = defined('CMS_SESSION_STARTED') ? (string) ($_SESSION['csrf'] ?? '') : '';
    $html = '<form method="post" class="partner-form" id="partner-form">';
    $html .= '<input type="hidden" name="partner_form" value="1">';
    $html .= '<input type="hidden" name="csrf" value="' . e($csrf) . '">';
    $html .= '<input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">';
    if ($state['submitted'] && $state['ok']) {
        $html .= '<div class="alert ok">' . e($state['msg']) . '</div>';
    } elseif ($state['submitted'] && !$state['ok']) {
        $html .= '<div class="alert error">' . e($state['err']) . '</div>';
    }
    $html .= '<div class="pf-grid">';
    $html .= '<div class="pf-field"><label for="p_manager">نام مسئول *</label><input type="text" id="p_manager" name="p_manager" required maxlength="120" autocomplete="name"></div>';
    $html .= '<div class="pf-field"><label for="p_business">نام واحد همکاری *</label><input type="text" id="p_business" name="p_business" required maxlength="150" placeholder="مثلاً کابینت‌سازی مدرن"></div>';
    $html .= '<div class="pf-field"><label for="p_field">زمینه فعالیت *</label><input type="text" id="p_field" name="p_field" required maxlength="150" placeholder="مثلاً طراحی و اجرای کابینت"></div>';
    $html .= '<div class="pf-field"><label for="p_phone">شماره تماس *</label><input type="text" id="p_phone" name="p_phone" required inputmode="tel" dir="ltr" maxlength="15" autocomplete="tel"></div>';
    $html .= '<div class="pf-field"><label for="p_email">ایمیل <span class="muted">(اختیاری)</span></label><input type="email" id="p_email" name="p_email" dir="ltr" maxlength="150" autocomplete="email"></div>';
    $html .= '<div class="pf-field pf-full"><label for="p_address">آدرس <span class="muted">(اختیاری)</span></label><textarea id="p_address" name="p_address" rows="2" maxlength="500"></textarea></div>';
    $html .= '</div>';
    $html .= '<button type="submit" class="btn btn-gold">ثبت درخواست همکاری</button>';
    $html .= '</form>';
    return $html;
}

/** تصاویر گالری یک محصول */
function get_product_images(int $productId): array
{
    $st = db()->prepare('SELECT * FROM product_images WHERE product_id = :p ORDER BY sort_order ASC, id ASC');
    $st->execute([':p' => $productId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** HTML گالری تصاویر محصول برای صفحه عمومی */
function product_gallery_html(int $productId): string
{
    $images = get_product_images($productId);
    if ($images === []) {
        return '';
    }
    $html = '<div class="product-gallery"><h3>گالری تصاویر</h3><div class="pg-public-grid">';
    foreach ($images as $img) {
        $url = uploaded_image_url($img['image'] ?? '');
        if ($url === '') {
            continue;
        }
        $cap = (string) ($img['caption'] ?? '');
        $html .= '<figure class="pg-public-item"><img loading="lazy" src="' . e($url) . '" alt="' . e($cap) . '">';
        if ($cap !== '') {
            $html .= '<figcaption>' . e($cap) . '</figcaption>';
        }
        $html .= '</figure>';
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * نسخه ۹٫۱ — فیلدهای سفارشی فرم سفارش (per محصول/دسته)
 */

/** فیلدهای فرم سفارش برای یک محصول: اول محصول، بعد دسته، بعد سراسری */
function get_order_form_fields(int $productId): array
{
    $product = get_product($productId);
    $catId = (int) ($product['category_id'] ?? 0);
    // ۱. فیلدهای خاص محصول
    $st = db()->prepare("SELECT * FROM order_form_fields WHERE owner_type = 'product' AND owner_id = :id AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    $st->execute([':id' => $productId]);
    $fields = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($fields !== []) {
        return $fields;
    }
    // ۲. فیلدهای دسته‌بندی
    if ($catId > 0) {
        $st = db()->prepare("SELECT * FROM order_form_fields WHERE owner_type = 'category' AND owner_id = :id AND is_active = 1 ORDER BY sort_order ASC, id ASC");
        $st->execute([':id' => $catId]);
        $fields = $st->fetchAll(PDO::FETCH_ASSOC);
        if ($fields !== []) {
            return $fields;
        }
    }
    // ۳. فیلدهای سراسری
    $st = db()->query("SELECT * FROM order_form_fields WHERE owner_type = 'global' AND owner_id = 0 AND is_active = 1 ORDER BY sort_order ASC, id ASC");
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** تنظیمات فرم سفارش یک محصول (کدام فیلدهای استاندارد نمایش داده شوند) */
function product_order_form_config(array $product): array
{
    $defaults = ['show_length' => true, 'show_qty' => true, 'show_wire' => true, 'show_endcap' => true, 'show_options' => true];
    $raw = (string) ($product['order_form_config'] ?? '');
    if ($raw === '') {
        return $defaults;
    }
    $cfg = json_decode($raw, true);
    if (!is_array($cfg)) {
        return $defaults;
    }
    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $cfg)) {
            $cfg[$k] = $v;
        } else {
            $cfg[$k] = (bool) $cfg[$k];
        }
    }
    return $cfg;
}

/** HTML فیلدهای سفارشی برای فرم سفارش */
function order_custom_fields_html(int $productId, string $namePrefix = 'cf'): array
{
    $fields = get_order_form_fields($productId);
    if ($fields === []) {
        return ['', []];
    }
    $html = '<div class="so-custom-fields"><h4>مشخصات تکمیلی</h4>';
    $meta = [];
    foreach ($fields as $f) {
        $fid = (int) $f['id'];
        $name = $namePrefix . '[' . $fid . ']';
        $label = (string) $f['label'];
        $req = (int) $f['is_required'] === 1;
        $ph = (string) ($f['placeholder'] ?? '');
        $help = (string) ($f['help_text'] ?? '');
        $type = (string) ($f['field_type'] ?? 'text');
        $meta[$fid] = ['label' => $label, 'type' => $type];
        $html .= '<div class="so-field"><label>' . e($label) . ($req ? ' *' : '') . '</label>';
        if ($type === 'select') {
            $opts = json_decode((string) ($f['options_json'] ?? '[]'), true);
            if (!is_array($opts)) { $opts = []; }
            $html .= '<select name="' . e($name) . '"' . ($req ? ' required' : '') . '><option value="">— انتخاب کنید —</option>';
            foreach ($opts as $op) {
                $op = trim((string) $op);
                if ($op === '') { continue; }
                $html .= '<option value="' . e($op) . '">' . e($op) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'textarea') {
            $html .= '<textarea name="' . e($name) . '" rows="2"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . '></textarea>';
        } elseif ($type === 'number') {
            $html .= '<input type="number" name="' . e($name) . '" step="any"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . '>';
        } elseif ($type === 'checkbox') {
            $html .= '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"> ' . e($ph !== '' ? $ph : 'بله') . '</label>';
        } else {
            $html .= '<input type="text" name="' . e($name) . '"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . ' maxlength="255">';
        }
        if ($help !== '') {
            $html .= '<p class="so-help">' . e($help) . '</p>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return [$html, $meta];
}

// لود خودکار ماژول‌های فعال (۹٫۹۹٫۱۳)
// فقط وقتی دیتابیس آماده است و در CLI نصب نیست
if (PHP_SAPI !== 'cli' || defined('CMS_LOAD_MODULES_CLI')) {
    try { modules_load_active(); } catch (Throwable $e) { /* silent */ }
}
