<?php
// admin_users.php — فاز ۰ (نسخه ۸٫۷٫۰): کاربران و نقش‌های پنل مدیریت
// ورود چندکاربره با نام کاربری + پسورد؛ هر نقش تعیین می‌کند کاربر کدام صفحه‌ها را
// ببیند و کدام اکشن‌ها را اجرا کند. نقش‌ها و دسترسی‌هایشان کاملاً از همین صفحه
// قابل ویرایش‌اند و هیچ دسترسی‌ای در کد ثابت نشده است. نقش «مدیر کل» (owner) برای
// جلوگیری از قفل‌شدن سیستم همیشه به همهٔ صفحه‌ها دسترسی دارد.

declare(strict_types=1);

// ---------- فهرست صفحه‌ها (مبنای چک‌باکس‌های سطح دسترسی) ----------

/** صفحه‌های پنل به تفکیک گروه منو: [برچسب گروه => [کلید صفحه => عنوان]] */
function admin_page_catalog(): array
{
    return [
        'اصلی و محتوا' => [
            'dashboard' => 'داشبورد',
            'pages'     => 'صفحه‌ها',
            'gallery'   => 'مدیریت گالری',
            'sections'  => 'بخش‌های صفحه اصلی',
            'messages'  => 'پیام‌های تماس',
            'partners'  => 'درخواست‌های همکاری',
            'design'    => 'قالب و استایل',
            'notifications' => 'اعلان‌ها',
        ],
        'کاتالوگ و مشتریان' => [
            'customers'  => 'مشتری‌ها',
            'categories' => 'دسته‌بندی‌های محصولات',
            'products'   => 'محصولات',
            'attributes' => 'ویژگی‌های محصول',
        ],
        'انبار' => [
            'materials'       => 'مواد اولیه و انبار',
            'material_prices' => 'لیست قیمت مواد اولیه',
            'stock'           => 'گردش انبار',
            'remnants'        => 'انبار پرتی',
        ],
        'سفارش‌ها' => [
            'orders'      => 'سفارش‌ها',
            'order_new'   => 'سفارش تازه',
            'order_view'  => 'جزئیات سفارش',
            'order_rules' => 'قوانین قیمت‌گذاری',
            'order_forms' => 'فرم‌های سفارش',
        ],
        'تولید' => [
            'production'       => 'تولید',
            'production_view'  => 'برگه تولید',
            'production_rules' => 'مراحل و قوانین تولید',
        ],
        'مالی' => [
            'finance'       => 'داشبورد مالی',
            'invoices'      => 'فاکتورها',
            'invoice_view'  => 'فاکتور',
            'expenses'      => 'هزینه‌ها',
            'statements'    => 'صورتحساب مشتریان',
            'finance_rules' => 'قوانین مالی',
        ],
        'گزارش‌ها' => [
            'reports' => 'گزارش‌ها',
        ],
        'منابع' => [
            'employees'          => 'پرسنل',
            'employee_profile'   => 'پروفایل پرسنل',
            'payroll'            => 'حقوق و دستمزد',
            'payslip'            => 'فیش حقوقی',
            'assets'             => 'تجهیزات و دارایی‌ها',
            'assets_maintenance' => 'سوابق تعمیرات',
        ],
        'تنظیمات' => [
            'settings'         => 'تنظیمات سایت',
            'order_rules'      => 'قوانین قیمت‌گذاری',
            'order_forms'      => 'فرم‌های سفارش',
            'production_rules' => 'مراحل و قوانین تولید',
            'finance_rules'    => 'قوانین مالی',
            'sitemap'          => 'نقشه سایت',
        ],
        'سیستم' => [
            'sysinfo'  => 'مشخصات نرم‌افزار',
            'update'   => 'آپدیت سیستم',
            'database' => 'اتصال دیتابیس',
            'tools'    => 'ابزار و بکاپ',
            'api'      => 'دسترسی API',
            'users'    => 'کاربران و نقش‌ها',
            'logs'     => 'لاگ‌ها',
        ],
    ];
}

/** همهٔ کلیدهای صفحه به‌صورت فهرست تخت */
function admin_all_page_keys(): array
{
    static $keys = null;
    if ($keys === null) {
        $keys = [];
        foreach (admin_page_catalog() as $items) {
            foreach ($items as $k => $_title) {
                $keys[] = $k;
            }
        }
    }
    return $keys;
}

// ---------- نقش‌ها (ذخیره در تنظیم user_roles، قابل ویرایش از پنل) ----------

/** نقش‌های پیش‌فرض؛ فقط وقتی ساخته می‌شوند که هنوز نقشی ذخیره نشده باشد */
function user_roles_default(): array
{
    return [
        ['key' => 'owner', 'title' => 'مدیر کل', 'pages' => admin_all_page_keys()],
        ['key' => 'sales', 'title' => 'مسئول فروش', 'pages' => ['dashboard', 'customers', 'categories', 'products', 'orders', 'order_new', 'order_view', 'statements', 'reports']],
        ['key' => 'production_op', 'title' => 'اپراتور تولید', 'pages' => ['dashboard', 'production', 'production_view', 'materials', 'stock', 'remnants']],
        ['key' => 'accountant', 'title' => 'حسابدار', 'pages' => ['dashboard', 'finance', 'invoices', 'invoice_view', 'expenses', 'statements', 'customers', 'orders', 'order_view', 'reports', 'material_prices']],
    ];
}

/** فهرست نقش‌ها؛ نقش owner همیشه هست و صفحه‌هایش همیشه کامل است */
function user_roles(): array
{
    static $roles = null;
    if ($roles !== null) {
        return $roles;
    }
    $roles = [];
    $raw = trim((string) get_setting('user_roles', ''));
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            foreach ($decoded as $r) {
                if (is_array($r) && trim((string) ($r['key'] ?? '')) !== '') {
                    $pages = [];
                    foreach ((array) ($r['pages'] ?? []) as $p) {
                        if (is_string($p) && in_array($p, admin_all_page_keys(), true)) {
                            $pages[] = $p;
                        }
                    }
                    $roles[] = [
                        'key'   => (string) $r['key'],
                        'title' => trim((string) ($r['title'] ?? '')) !== '' ? (string) $r['title'] : (string) $r['key'],
                        'pages' => $pages,
                    ];
                }
            }
        }
    }
    if ($roles === []) {
        $roles = user_roles_default();
    }
    $hasOwner = false;
    foreach ($roles as $i => $r) {
        if ($r['key'] === 'owner') {
            $hasOwner = true;
            $roles[$i]['pages'] = admin_all_page_keys();
        }
    }
    if (!$hasOwner) {
        array_unshift($roles, ['key' => 'owner', 'title' => 'مدیر کل', 'pages' => admin_all_page_keys()]);
    }
    return $roles;
}

/** یک نقش با کلیدش (یا null) */
function user_role(string $key): ?array
{
    foreach (user_roles() as $r) {
        if ($r['key'] === $key) {
            return $r;
        }
    }
    return null;
}

/** عنوان نمایشی نقش */
function user_role_title(string $key): string
{
    $r = user_role($key);
    return $r !== null ? (string) $r['title'] : $key;
}

/** صفحه‌های مجاز یک نقش؛ owner همیشه همهٔ صفحه‌ها */
function user_role_pages(string $key): array
{
    if ($key === 'owner') {
        return admin_all_page_keys();
    }
    $r = user_role($key);
    return $r !== null ? (array) $r['pages'] : [];
}

// ---------- کاربر جاری و کنترل دسترسی ----------

/**
 * کاربر واردشدهٔ فعلی (ردیف admin_users) یا null.
 * نشست‌های قدیمی (قبل از ۸٫۷) که فقط پرچم ورود دارند، به کاربر «admin» وصل می‌شوند.
 */
function current_admin_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['admin_logged_in'])) {
        return $user;
    }
    try {
        if (!empty($_SESSION['admin_user_id'])) {
            $st = db()->prepare('SELECT * FROM admin_users WHERE id = ? AND is_active = 1');
            $st->execute([(int) $_SESSION['admin_user_id']]);
            $row = $st->fetch();
            if ($row) {
                $user = $row;
            }
            return $user;
        }
        $row = db()->query("SELECT * FROM admin_users WHERE username = 'admin' AND is_active = 1")->fetch();
        if ($row) {
            $user = $row;
        }
    } catch (Throwable $ignored) {
    }
    return $user;
}

/** آیا کاربر جاری به این صفحه دسترسی دارد؟ (صفحه‌های زیرمجموعه از صفحهٔ مادر هم ارث می‌برند) */
function admin_can_page(string $page): bool
{
    $u = current_admin_user();
    if ($u === null) {
        return false;
    }
    $pages = user_role_pages((string) ($u['role_key'] ?? ''));
    if (in_array($page, $pages, true)) {
        return true;
    }
    $parent = [
        'order_new'       => 'orders',
        'order_view'      => 'orders',
        'invoice_view'    => 'invoices',
        'production_view' => 'production',
    ][$page] ?? '';
    return $parent !== '' && in_array($parent, $pages, true);
}

/** نگاشت هر اکشن POST به صفحه‌ای که مالک آن است (مبنای گارد اکشن‌ها) */
function admin_action_page_map(): array
{
    return [
        // کاتالوگ و مشتریان
        'add_customer' => 'customers', 'update_customer' => 'customers', 'delete_customer' => 'customers',
        'add_category' => 'categories', 'update_category' => 'categories', 'delete_category' => 'categories', 'move_category' => 'categories',
        'add_product' => 'products', 'update_product' => 'products', 'delete_product' => 'products', 'move_product' => 'products',
        'add_option' => 'products', 'update_option' => 'products', 'delete_option' => 'products', 'move_option' => 'products',
        'add_attribute' => 'attributes', 'update_attribute' => 'attributes', 'delete_attribute' => 'attributes', 'move_attribute' => 'attributes',
        // انبار
        'add_material' => 'materials', 'update_material' => 'materials', 'delete_material' => 'materials', 'toggle_material' => 'materials',
        'stock_in' => 'stock', 'stock_out' => 'stock', 'stock_adjust' => 'stock',
        'update_material_prices' => 'material_prices',
        // سفارش‌ها
        'add_order' => 'orders', 'set_order_status' => 'orders', 'delete_order' => 'orders', 'update_order_note' => 'orders',
        'save_order_settings' => 'order_rules',
        'add_tier' => 'order_rules', 'update_tier' => 'order_rules', 'delete_tier' => 'order_rules', 'move_tier' => 'order_rules',
        'add_ostatus' => 'order_rules', 'update_ostatus' => 'order_rules', 'delete_ostatus' => 'order_rules', 'move_ostatus' => 'order_rules', 'toggle_ostatus' => 'order_rules',
        'add_remnant' => 'remnants', 'delete_remnant' => 'remnants',
        // تولید
        'create_production' => 'production', 'production_start' => 'production', 'production_advance' => 'production',
        'production_back' => 'production', 'production_finish' => 'production', 'production_cancel' => 'production', 'production_save_meta' => 'production',
        'add_pstage' => 'production_rules', 'update_pstage' => 'production_rules', 'delete_pstage' => 'production_rules',
        'move_pstage' => 'production_rules', 'toggle_pstage' => 'production_rules', 'save_qc_items' => 'production_rules',
        // مالی
        'add_payment' => 'invoices', 'delete_payment' => 'invoices', 'issue_invoice' => 'invoices', 'void_invoice' => 'invoices',
        'add_expense' => 'expenses', 'update_expense' => 'expenses', 'delete_expense' => 'expenses', 'confirm_expense' => 'expenses', 'reject_expense' => 'expenses',
        'add_payment_method' => 'finance_rules', 'update_payment_method' => 'finance_rules', 'delete_payment_method' => 'finance_rules',
        'toggle_payment_method' => 'finance_rules', 'move_payment_method' => 'finance_rules',
        'add_expense_category' => 'finance_rules', 'update_expense_category' => 'finance_rules', 'delete_expense_category' => 'finance_rules',
        'toggle_expense_category' => 'finance_rules', 'move_expense_category' => 'finance_rules',
        'save_finance_settings' => 'finance_rules',
        // گزارش‌ها و لاگ‌ها
        'save_report_settings' => 'reports',
        'save_log_settings' => 'logs', 'clear_visit_logs' => 'logs', 'clear_admin_logs' => 'logs',
        // کاربران و نقش‌ها
        'save_user' => 'users', 'delete_user' => 'users', 'save_role' => 'users', 'delete_role' => 'users',
        // اکشن‌های داخلی admin.php
        'api_save' => 'api', 'api_generate' => 'api', 'api_revoke' => 'api',
        'download_backup' => 'tools', 'restore_backup' => 'tools',
        'save_dashboard' => 'settings',
        'stat_customers' => 'dashboard', 'stat_products' => 'dashboard', 'stat_orders' => 'dashboard', 'stat_new_orders' => 'dashboard',
        'stat_pending' => 'dashboard', 'stat_debt' => 'dashboard', 'stat_finance_month' => 'dashboard', 'stat_production' => 'dashboard',
        'stat_messages' => 'dashboard', 'stat_version' => 'dashboard',
        'chart_income' => 'dashboard', 'chart_orders' => 'dashboard', 'chart_expenses' => 'dashboard', 'chart_production' => 'dashboard',
        'add_page' => 'pages', 'update_page' => 'pages', 'delete_page' => 'pages', 'move_page' => 'pages', 'toggle_page' => 'pages',
        'gallery_upload' => 'gallery', 'gallery_update' => 'gallery', 'gallery_delete' => 'gallery', 'gallery_move' => 'gallery',
        'add_block' => 'pages', 'update_block' => 'pages', 'delete_block' => 'pages', 'move_block' => 'pages', 'toggle_block' => 'pages',
        'add_section' => 'sections', 'update_section' => 'sections', 'delete_section' => 'sections', 'move_section' => 'sections', 'toggle_section' => 'sections',
        'delete_message' => 'messages',
        'notif_mark_read' => 'notifications', 'notif_mark_all_read' => 'notifications',
        'save_css' => 'design', 'reset_css' => 'design', 'save_visual_settings' => 'design', 'restore_revision' => 'design', 'apply_theme' => 'design',
        'create_db_template' => 'design', 'delete_db_template' => 'design', 'save_db_template' => 'design', 'reset_db_template' => 'design', 'delete_legacy_files' => 'design',
        'save_settings' => 'settings', 'change_password' => 'settings',
        'perform_update' => 'update', 'save_update_settings' => 'update',
        'switch_database_file' => 'database', 'upload_database_file' => 'database',
    ];
}

/** آیا کاربر جاری اجازهٔ اجرای این اکشن را دارد؟ اکشن ناشناخته فقط برای مدیر کل */
function admin_can_action(string $action): bool
{
    $u = current_admin_user();
    if ($u === null) {
        return false;
    }
    if ((string) ($u['role_key'] ?? '') === 'owner') {
        return true;
    }
    $map = admin_action_page_map();
    $page = $map[$action] ?? null;
    if ($page === null) {
        return false;
    }
    return admin_can_page($page);
}

/** اولین صفحه‌ای که کاربر جاری به آن دسترسی دارد (برای برگشت بعد از گارد) */
function admin_first_allowed_page(): string
{
    $order = ['dashboard', 'orders', 'production', 'finance', 'invoices', 'customers', 'products', 'materials', 'stock', 'reports', 'pages'];
    foreach (admin_all_page_keys() as $k) {
        if (!in_array($k, $order, true)) {
            $order[] = $k;
        }
    }
    foreach ($order as $k) {
        if (admin_can_page($k)) {
            return $k;
        }
    }
    return 'dashboard';
}

/** تعداد مدیران کل فعال (برای گارد «آخرین مدیر کل») */
function admin_active_owner_count(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM admin_users WHERE role_key = 'owner' AND is_active = 1")->fetchColumn();
    } catch (Throwable $ignored) {
        return 0;
    }
}

/** یک کاربر با آی‌دی (یا null) */
function admin_user_by_id(int $id): ?array
{
    try {
        $st = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    } catch (Throwable $ignored) {
        return null;
    }
}

// ---------- اکشن‌های POST ----------

function users_post_actions(): array
{
    return ['save_user', 'delete_user', 'save_role', 'delete_role'];
}

function users_handle_post(string $action): void
{
    switch ($action) {
        case 'save_user': {
            $id       = (int) ($_POST['id'] ?? 0);
            $username = trim((string) ($_POST['username'] ?? ''));
            $display  = trim((string) ($_POST['display_name'] ?? ''));
            $roleKey  = (string) ($_POST['role_key'] ?? '');
            $active   = isset($_POST['is_active']) ? 1 : 0;
            $pw       = (string) ($_POST['password'] ?? '');

            if (!preg_match('/^[\p{L}0-9_.\-]{3,40}$/u', $username)) {
                throw new RuntimeException('نام کاربری باید ۳ تا ۴۰ کاراکتر باشد (حرف، عدد، خط تیره، نقطه یا _).');
            }
            if ($display === '') {
                $display = $username;
            }
            if (user_role($roleKey) === null) {
                throw new RuntimeException('نقش انتخاب‌شده معتبر نیست.');
            }
            $st = db()->prepare('SELECT id FROM admin_users WHERE username = ? AND id != ?');
            $st->execute([$username, $id]);
            if ($st->fetch()) {
                throw new RuntimeException('این نام کاربری قبلاً گرفته شده است.');
            }

            $target = $id > 0 ? admin_user_by_id($id) : null;
            if ($id > 0 && $target === null) {
                throw new RuntimeException('کاربر پیدا نشد.');
            }
            $me = current_admin_user();
            if ($target !== null && $me !== null && (int) $target['id'] === (int) $me['id']) {
                if ($active === 0) {
                    throw new RuntimeException('نمی‌توانی حساب خودت را غیرفعال کنی.');
                }
                if ($roleKey !== (string) $target['role_key']) {
                    throw new RuntimeException('نقش حساب خودت را نمی‌توانی عوض کنی؛ از یک مدیر کل دیگر بخواه.');
                }
            }
            if ($target !== null && (string) $target['role_key'] === 'owner' && (int) $target['is_active'] === 1
                && ($active === 0 || $roleKey !== 'owner') && admin_active_owner_count() <= 1) {
                throw new RuntimeException('حداقل یک مدیر کل فعال باید در سیستم بماند.');
            }
            if ($id === 0 && $pw === '') {
                throw new RuntimeException('برای کاربر تازه پسورد تعیین کنید.');
            }
            if ($pw !== '' && strlen($pw) < 8) {
                throw new RuntimeException('پسورد باید حداقل ۸ کاراکتر باشد.');
            }

            if ($id === 0) {
                db()->prepare('INSERT INTO admin_users (username, pass_hash, display_name, role_key, is_active) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $display, $roleKey, $active]);
                log_admin_event('user_create', 'ساخت کاربر «' . $username . '» با نقش ' . user_role_title($roleKey));
                flash('ok', 'کاربر «' . $username . '» ساخته شد.');
            } else {
                if ($pw !== '') {
                    $newHash = password_hash($pw, PASSWORD_DEFAULT);
                    db()->prepare('UPDATE admin_users SET username = ?, pass_hash = ?, display_name = ?, role_key = ?, is_active = ? WHERE id = ?')
                        ->execute([$username, $newHash, $display, $roleKey, $active, $id]);
                    if ($username === 'admin') {
                        // هش تنظیم قدیمی هم‌گام می‌ماند تا مسیر ورود جایگزین و تغییر پسورد تنظیمات یکدست بمانند
                        set_setting('admin_password_hash', $newHash);
                    }
                } else {
                    db()->prepare('UPDATE admin_users SET username = ?, display_name = ?, role_key = ?, is_active = ? WHERE id = ?')
                        ->execute([$username, $display, $roleKey, $active, $id]);
                }
                log_admin_event('user_update', 'ویرایش کاربر «' . $username . '»');
                flash('ok', 'تغییرات کاربر «' . $username . '» ذخیره شد.');
            }
            redirect_admin('admin.php?page=users');
        }
        case 'delete_user': {
            $id = (int) ($_POST['id'] ?? 0);
            $target = admin_user_by_id($id);
            if ($target === null) {
                throw new RuntimeException('کاربر پیدا نشد.');
            }
            $me = current_admin_user();
            if ($me !== null && (int) $target['id'] === (int) $me['id']) {
                throw new RuntimeException('نمی‌توانی حساب خودت را حذف کنی.');
            }
            if ((string) $target['role_key'] === 'owner' && (int) $target['is_active'] === 1 && admin_active_owner_count() <= 1) {
                throw new RuntimeException('حداقل یک مدیر کل فعال باید در سیستم بماند.');
            }
            db()->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$id]);
            log_admin_event('user_delete', 'حذف کاربر «' . (string) $target['username'] . '»');
            flash('ok', 'کاربر «' . (string) $target['username'] . '» حذف شد.');
            redirect_admin('admin.php?page=users');
        }
        case 'save_role': {
            $key   = trim((string) ($_POST['role_key'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $pages = [];
            foreach ((array) ($_POST['pages'] ?? []) as $p) {
                if (is_string($p) && in_array($p, admin_all_page_keys(), true) && !in_array($p, $pages, true)) {
                    $pages[] = $p;
                }
            }
            if ($title === '') {
                throw new RuntimeException('عنوان نقش را وارد کنید.');
            }
            $roles = user_roles();
            if ($key === 'owner') {
                foreach ($roles as $i => $r) {
                    if ($r['key'] === 'owner') {
                        $roles[$i]['title'] = $title;
                        $roles[$i]['pages'] = admin_all_page_keys();
                    }
                }
            } else {
                if (!preg_match('/^[a-z0-9_]{2,40}$/', $key)) {
                    throw new RuntimeException('کلید نقش باید ۲ تا ۴۰ کاراکتر لاتین کوچک، عدد یا _ باشد (مثل sales).');
                }
                $found = false;
                foreach ($roles as $i => $r) {
                    if ($r['key'] === $key) {
                        $roles[$i]['title'] = $title;
                        $roles[$i]['pages'] = $pages;
                        $found = true;
                    }
                }
                if (!$found) {
                    $roles[] = ['key' => $key, 'title' => $title, 'pages' => $pages];
                }
            }
            set_setting('user_roles', (string) json_encode($roles, JSON_UNESCAPED_UNICODE));
            log_admin_event('role_save', 'ذخیره نقش «' . $title . '» (' . $key . ')');
            flash('ok', 'نقش «' . $title . '» ذخیره شد.');
            redirect_admin('admin.php?page=users');
        }
        case 'delete_role': {
            $key = trim((string) ($_POST['role_key'] ?? ''));
            if ($key === 'owner') {
                throw new RuntimeException('نقش مدیر کل حذف‌شدنی نیست.');
            }
            $st = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE role_key = ?');
            $st->execute([$key]);
            if ((int) $st->fetchColumn() > 0) {
                throw new RuntimeException('این نقش کاربر دارد؛ اول نقش آن کاربرها را عوض کنید.');
            }
            $roles = [];
            foreach (user_roles() as $r) {
                if ($r['key'] !== $key) {
                    $roles[] = $r;
                }
            }
            set_setting('user_roles', (string) json_encode($roles, JSON_UNESCAPED_UNICODE));
            log_admin_event('role_delete', 'حذف نقش «' . $key . '»');
            flash('ok', 'نقش حذف شد.');
            redirect_admin('admin.php?page=users');
        }
    }
}

// ---------- بارگذاری داده ----------

function users_load_data(string $page): array
{
    $data = ['usersList' => [], 'rolesList' => [], 'editUser' => null, 'meId' => 0];
    if ($page !== 'users') {
        return $data;
    }
    $me = current_admin_user();
    $data['meId'] = $me !== null ? (int) $me['id'] : 0;
    try {
        $data['usersList'] = db()->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll();
    } catch (Throwable $ignored) {
    }
    $counts = [];
    try {
        foreach (db()->query('SELECT role_key, COUNT(*) AS c FROM admin_users GROUP BY role_key')->fetchAll() as $r) {
            $counts[(string) $r['role_key']] = (int) $r['c'];
        }
    } catch (Throwable $ignored) {
    }
    foreach (user_roles() as $r) {
        $r['user_count'] = $counts[$r['key']] ?? 0;
        $data['rolesList'][] = $r;
    }
    if (isset($_GET['edit'])) {
        $data['editUser'] = admin_user_by_id((int) $_GET['edit']);
    }
    return $data;
}

// ---------- رندر صفحه ----------

function users_render(array $data): void
{
    $usersList = $data['usersList'] ?? [];
    $rolesList = $data['rolesList'] ?? [];
    $editUser  = $data['editUser'] ?? null;
    $meId      = (int) ($data['meId'] ?? 0);
    $catalog   = admin_page_catalog();
    ?>
    <h1>کاربران و نقش‌ها</h1>
    <p class="muted">برای هر نفر از تیم یک حساب جدا بسازید و با نقش مشخص کنید به کدام بخش‌های پنل دسترسی داشته باشد. هر کس فقط منوها و صفحه‌هایی را می‌بیند که نقش او اجازه می‌دهد و اکشن‌های بقیهٔ بخش‌ها هم برایش بسته است. دسترسی نقش‌ها را از بخش «نقش‌ها و سطح دسترسی» همین صفحه عوض کنید.</p>

    <?php if ($editUser === null): ?>
    <div class="crud-toolbar">
        <button type="button" class="btn add" data-toggle-panel="user-form-panel" aria-expanded="false">+ افزودن کاربر</button>
    </div>
    <?php endif; ?>
    <div class="crud-panel" id="user-form-panel" <?= $editUser !== null ? 'data-open="1"' : 'hidden' ?>>
        <h2><?= $editUser !== null ? 'ویرایش کاربر: ' . e((string) $editUser['username']) : 'افزودن کاربر تازه' ?></h2>
        <form method="post" class="card wide">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_user">
            <?php if ($editUser !== null): ?><input type="hidden" name="id" value="<?= (int) $editUser['id'] ?>"><?php endif; ?>
            <label>نام کاربری *
                <input type="text" name="username" required dir="ltr" value="<?= e((string) ($editUser['username'] ?? '')) ?>" placeholder="مثلاً operator1">
            </label>
            <label>نام نمایشی
                <input type="text" name="display_name" value="<?= e((string) ($editUser['display_name'] ?? '')) ?>" placeholder="مثلاً: آقای رضایی — اپراتور تولید">
            </label>
            <label>نقش
                <select name="role_key">
                    <?php foreach ($rolesList as $r): ?>
                        <option value="<?= e((string) $r['key']) ?>" <?= ($editUser['role_key'] ?? 'sales') === $r['key'] ? 'selected' : '' ?>><?= e((string) $r['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>پسورد <?= $editUser !== null ? '(خالی = بدون تغییر)' : '*' ?>
                <input type="password" name="password" dir="ltr" autocomplete="new-password" <?= $editUser === null ? 'required' : '' ?> placeholder="حداقل ۸ کاراکتر">
            </label>
            <label class="check">
                <input type="checkbox" name="is_active" value="1" <?= (int) ($editUser['is_active'] ?? 1) === 1 ? 'checked' : '' ?>> حساب فعال باشد
            </label>
            <button type="submit" class="btn <?= $editUser !== null ? 'edit' : 'add' ?>"><?= $editUser !== null ? 'ذخیره تغییرات' : 'ثبت کاربر' ?></button>
            <?php if ($editUser !== null): ?><a class="btn" href="admin.php?page=users">انصراف</a><?php endif; ?>
        </form>
    </div>

    <h2>فهرست کاربران (<?= count($usersList) ?>)</h2>
    <?php if ($usersList === []): ?>
        <div class="card wide"><p class="muted">هنوز کاربری ثبت نشده است.</p></div>
    <?php else: ?>
    <div style="overflow-x:auto"><table>
        <thead><tr><th>نام کاربری</th><th>نام نمایشی</th><th>نقش</th><th>وضعیت</th><th>آخرین ورود</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($usersList as $u): ?>
            <tr>
                <td><span dir="ltr"><?= e((string) $u['username']) ?></span><?= (int) $u['id'] === $meId ? ' <span class="badge ok">شما</span>' : '' ?></td>
                <td><?= e((string) ($u['display_name'] ?? '')) ?></td>
                <td><?= e(user_role_title((string) $u['role_key'])) ?></td>
                <td><?= (int) $u['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge">غیرفعال</span>' ?></td>
                <td><span dir="ltr"><?= e((string) ($u['last_login_at'] ?? '') !== '' ? (string) $u['last_login_at'] : '—') ?></span></td>
                <td class="actions">
                    <a class="btn small edit" href="admin.php?page=users&edit=<?= (int) $u['id'] ?>">ویرایش</a>
                    <?php if ((int) $u['id'] !== $meId): ?>
                    <form method="post" style="display:inline" onsubmit="return confirm('کاربر «<?= e((string) $u['username']) ?>» حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="btn small danger-btn">حذف</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>

    <h2>نقش‌ها و سطح دسترسی</h2>
    <p class="muted">تیک هر صفحه یعنی کاربران آن نقش آن صفحه را می‌بینند و اکشن‌هایش را اجرا می‌کنند. نقش «مدیر کل» همیشه به همهٔ صفحه‌ها دسترسی دارد و حذف‌شدنی نیست.</p>

    <div class="crud-toolbar">
        <button type="button" class="btn add" data-toggle-panel="role-form-panel" aria-expanded="false">+ افزودن نقش</button>
    </div>
    <div class="crud-panel" id="role-form-panel" hidden>
        <h2>افزودن نقش تازه</h2>
        <form method="post" class="card wide">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_role">
            <label>کلید نقش (لاتین) *
                <input type="text" name="role_key" required dir="ltr" placeholder="مثلاً warehouse">
            </label>
            <label>عنوان نقش *
                <input type="text" name="title" required placeholder="مثلاً: انباردار">
            </label>
            <fieldset>
                <legend>صفحه‌های مجاز</legend>
                <?php foreach ($catalog as $group => $items): ?>
                    <p class="muted" style="margin:8px 0 2px"><strong><?= e($group) ?></strong></p>
                    <?php foreach ($items as $k => $title): ?>
                        <label class="check" style="display:inline-block;margin:2px 10px 2px 0">
                            <input type="checkbox" name="pages[]" value="<?= e($k) ?>"> <?= e($title) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </fieldset>
            <button type="submit" class="btn add">ثبت نقش</button>
        </form>
    </div>

    <?php foreach ($rolesList as $r): ?>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_role">
        <input type="hidden" name="role_key" value="<?= e((string) $r['key']) ?>">
        <h3 style="margin-top:0"><?= e((string) $r['title']) ?> <span class="muted" dir="ltr">(<?= e((string) $r['key']) ?>)</span>
            <span class="badge"><?= (int) ($r['user_count'] ?? 0) ?> کاربر</span>
        </h3>
        <label>عنوان نقش
            <input type="text" name="title" required value="<?= e((string) $r['title']) ?>">
        </label>
        <?php if ($r['key'] === 'owner'): ?>
            <p class="muted">مدیر کل همیشه به همهٔ صفحه‌ها دسترسی دارد؛ صفحه‌های این نقش قابل کم‌وزیاد کردن نیست تا هیچ‌وقت سیستم بدون مدیر نماند.</p>
        <?php else: ?>
        <fieldset>
            <legend>صفحه‌های مجاز</legend>
            <?php foreach ($catalog as $group => $items): ?>
                <p class="muted" style="margin:8px 0 2px"><strong><?= e($group) ?></strong></p>
                <?php foreach ($items as $k => $title): ?>
                    <label class="check" style="display:inline-block;margin:2px 10px 2px 0">
                        <input type="checkbox" name="pages[]" value="<?= e($k) ?>" <?= in_array($k, (array) $r['pages'], true) ? 'checked' : '' ?>> <?= e($title) ?>
                    </label>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </fieldset>
        <?php endif; ?>
        <button type="submit" class="btn edit">ذخیره نقش</button>
    </form>
    <?php if ($r['key'] !== 'owner'): ?>
    <form method="post" style="margin:-6px 0 14px" onsubmit="return confirm('نقش «<?= e((string) $r['title']) ?>» حذف شود؟')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_role">
        <input type="hidden" name="role_key" value="<?= e((string) $r['key']) ?>">
        <button type="submit" class="btn small danger-btn">حذف نقش «<?= e((string) $r['title']) ?>»</button>
    </form>
    <?php endif; ?>
    <?php endforeach; ?>
    <?php
}
