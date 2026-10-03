<?php
// admin_logs.php — صفحه «لاگ‌ها»ی پنل مدیریت (نسخه ۸٫۲).
// لاگ بازدید سایت (آی‌پی، منبع ورود، صفحه دیده‌شده و کلیک‌ها) و لاگ فعالیت
// مدیریت (ورود/خروج و اکشن‌ها)، تنظیمات نشست و لاگ، و پاک‌کردن لاگ‌ها.
// فقط از admin.php و بعد از احراز هویت صدا زده می‌شود (گارد CMS_ADMIN_PANEL).

declare(strict_types=1);

// دسترسی مستقیم ممنوع
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به لاگ‌ها */
function logs_post_actions(): array
{
    return ['save_log_settings', 'clear_visit_logs', 'clear_admin_logs', 'backfill_visit_geo'];
}

/** قالب‌بندی عدد با جداکننده هزارگان (هم‌سبک بقیه پنل) */
function logs_num($n): string
{
    return number_format((float) $n, 0, '.', ',');
}

/** پردازش اکشن‌های POST لاگ‌ها */
function logs_handle_post(string $action): void
{
    switch ($action) {
        case 'save_log_settings': {
            $hours = (int) ($_POST['session_lifetime_hours'] ?? 168);
            if ($hours < 1) { $hours = 1; }
            if ($hours > 720) { $hours = 720; }
            set_setting('session_lifetime_hours', (string) $hours);
            set_setting('visit_log_enabled', isset($_POST['visit_log_enabled']) ? '1' : '0');
            set_setting('admin_log_enabled', isset($_POST['admin_log_enabled']) ? '1' : '0');
            $tz = trim((string) ($_POST['log_timezone'] ?? 'Asia/Tehran'));
            try {
                new DateTimeZone($tz);
            } catch (Throwable $e) {
                $tz = 'Asia/Tehran';
            }
            set_setting('log_timezone', $tz);
            $days = (int) ($_POST['log_retention_days'] ?? 90);
            if ($days < 1) { $days = 1; }
            if ($days > 3650) { $days = 3650; }
            set_setting('log_retention_days', (string) $days);
            flash('ok', 'تنظیمات نشست و لاگ ذخیره شد. (مدت نشست از درخواست بعدی اعمال می‌شود.)');
            redirect_admin('admin.php?page=logs&tab=' . ((string) ($_POST['tab'] ?? 'visits') === 'admin' ? 'admin' : 'visits'));
        }
        case 'clear_visit_logs': {
            db()->exec('DELETE FROM visit_logs');
            flash('ok', 'لاگ بازدید پاک شد.');
            redirect_admin('admin.php?page=logs&tab=visits');
        }
        case 'clear_admin_logs': {
            db()->exec('DELETE FROM admin_logs');
            flash('ok', 'لاگ مدیریت پاک شد.');
            redirect_admin('admin.php?page=logs&tab=admin');
        }
        case 'backfill_visit_geo': {
            // تکمیل اطلاعات کشور/دستگاه/مرورگر برای ردیف‌های قدیمی — هر بار ۱۰۰ ردیف
            $rows = db()->query("SELECT id, ip, user_agent FROM visit_logs WHERE (country_code = '' AND country_name = '' AND ip NOT LIKE '127.%' AND ip != '::1') OR device_type = '' ORDER BY id DESC LIMIT 100")->fetchAll();
            $n = 0;
            foreach ($rows as $r) {
                $geo = detect_country_from_ip((string) $r['ip']);
                $dbb = detect_device_browser((string) $r['user_agent']);
                db()->prepare('UPDATE visit_logs SET country_code = ?, country_name = ?, device_type = ?, browser_name = ? WHERE id = ?')
                    ->execute([(string) ($geo['code'] ?? ''), (string) ($geo['name'] ?? ''), (string) ($dbb['device'] ?? ''), (string) ($dbb['browser'] ?? ''), (int) $r['id']]);
                $n++;
            }
            $left = (int) db()->query("SELECT COUNT(*) FROM visit_logs WHERE device_type = ''")->fetchColumn();
            flash('ok', $n > 0 ? "اطلاعات $n ردیف تکمیل شد." . ($left > 0 ? " ($left ردیف باقی مانده — دوباره بزنید.)" : '') : 'همه ردیف‌ها کامل‌اند.');
            redirect_admin('admin.php?page=logs&tab=visits');
        }
    }
}

/** برچسب فارسی برای اکشن‌های پرتکرار لاگ مدیریت */
function log_action_label(string $action): string
{
    static $labels = [
        'login' => 'ورود به پنل',
        'logout' => 'خروج از پنل',
        'setup' => 'راه‌اندازی اولیه',
        'download_backup' => 'دانلود بکاپ دیتابیس',
        'save_settings' => 'ذخیره تنظیمات سایت',
        'change_password' => 'تغییر پسورد',
        'save_update_settings' => 'ذخیره تنظیمات آپدیت',
        'perform_update' => 'آپدیت سیستم',
        'add_section' => 'ساخت بخش صفحه اصلی',
        'update_section' => 'ویرایش بخش صفحه اصلی',
        'delete_section' => 'حذف بخش صفحه اصلی',
        'toggle_section' => 'فعال/غیرفعال بخش',
        'move_section' => 'جابه‌جایی بخش',
        'add_page' => 'ساخت صفحه',
        'update_page' => 'ویرایش صفحه',
        'delete_page' => 'حذف صفحه',
        'restore_backup' => 'بازیابی بکاپ',
        'import_template' => 'ورود قالب',
        'delete_template' => 'حذف قالب',
        'add_customer' => 'مشتری تازه',
        'update_customer' => 'ویرایش مشتری',
        'delete_customer' => 'حذف مشتری',
        'add_category' => 'دسته تازه',
        'update_category' => 'ویرایش دسته',
        'delete_category' => 'حذف دسته',
        'add_product' => 'محصول تازه',
        'update_product' => 'ویرایش محصول',
        'delete_product' => 'حذف محصول',
        'add_material' => 'ماده اولیه تازه',
        'update_material' => 'ویرایش ماده اولیه',
        'delete_material' => 'حذف ماده اولیه',
        'stock_in' => 'ورود به انبار',
        'stock_out' => 'خروج از انبار',
        'stock_adjust' => 'اصلاح موجودی',
        'add_order' => 'ثبت سفارش',
        'set_order_status' => 'تغییر وضعیت سفارش',
        'delete_order' => 'حذف سفارش',
        'update_order_note' => 'ویرایش یادداشت سفارش',
        'save_order_settings' => 'ذخیره قوانین سفارش',
        'add_tier' => 'پلکان تخفیف تازه',
        'update_tier' => 'ویرایش پلکان تخفیف',
        'delete_tier' => 'حذف پلکان تخفیف',
        'add_ostatus' => 'وضعیت سفارش تازه',
        'update_ostatus' => 'ویرایش وضعیت سفارش',
        'delete_ostatus' => 'حذف وضعیت سفارش',
        'add_remnant' => 'ثبت پرت',
        'delete_remnant' => 'حذف پرت',
        'save_log_settings' => 'ذخیره تنظیمات لاگ',
        'clear_visit_logs' => 'پاک‌کردن لاگ بازدید',
        'clear_admin_logs' => 'پاک‌کردن لاگ مدیریت',
    ];
    return $labels[$action] ?? $action;
}

/** میزبان منبع ورود (از کجا آمده) برای نمایش خوانا؛ خالی یعنی ورود مستقیم. */
function log_referer_host(string $referer): string
{
    if ($referer === '') {
        return '';
    }
    $host = (string) (parse_url($referer, PHP_URL_HOST) ?? '');
    return $host !== '' ? $host : $referer;
}

/** منطقه زمانی نمایشی لاگ‌ها (قابل تنظیم از پنل). */
function logs_tz(): DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        try {
            $tz = new DateTimeZone((string) get_setting('log_timezone', 'Asia/Tehran'));
        } catch (Throwable $e) {
            $tz = new DateTimeZone('Asia/Tehran');
        }
    }
    return $tz;
}

/** تبدیل زمان UTC دیتابیس به وقت محلی تنظیم‌شده برای نمایش. */
function log_local_time(string $utc): string
{
    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(logs_tz());
        return $dt->format('Y-m-d H:i');
    } catch (Throwable $e) {
        return $utc;
    }
}

/** بازه UTC «امروز/دیروز/۷روز/۳۰روز» بر اساس وقت محلی تنظیم‌شده. */
function logs_day_range(string $which): array
{
    try {
        $tz = logs_tz();
        $utc = new DateTimeZone('UTC');
        $now = new DateTime('now', $tz);
        switch ($which) {
            case 'today':
                $s = (clone $now)->setTime(0, 0, 0);
                $e = (clone $s)->modify('+1 day');
                break;
            case 'yesterday':
                $s = (clone $now)->setTime(0, 0, 0)->modify('-1 day');
                $e = (clone $s)->modify('+1 day');
                break;
            case '7d':
                $e = (clone $now)->setTime(0, 0, 0)->modify('+1 day');
                $s = (clone $e)->modify('-7 days');
                break;
            case '30d':
                $e = (clone $now)->setTime(0, 0, 0)->modify('+1 day');
                $s = (clone $e)->modify('-30 days');
                break;
            default:
                return ['', ''];
        }
        return [$s->setTimezone($utc)->format('Y-m-d H:i:s'), $e->setTimezone($utc)->format('Y-m-d H:i:s')];
    } catch (Throwable $e) {
        return ['', ''];
    }
}

/**
 * شرط SQL تشخیص «بازدید ادمین»: یا نام کاربری ادمین مستقیم ثبت شده،
 * یا آی‌پی در ورودهای موفق اخیر به پنل دیده شده است.
 */
function logs_admin_cond(string $alias = 'v'): string
{
    $hours = max(1, (int) get_setting('session_lifetime_hours', '168'));
    $a = $alias !== '' ? $alias . '.' : '';
    return "({$a}admin_user != '' OR {$a}ip IN (SELECT DISTINCT ip FROM admin_logs WHERE action = 'login' AND detail LIKE 'ورود موفق%' AND created_at >= datetime('now', '-{$hours} hours')))";
}

/** حدس نام ادمین از روی آی‌پی (تطبیق با ورودهای موفق اخیر به پنل). */
function visit_admin_guess(string $ip): string
{
    static $cache = [];
    if (array_key_exists($ip, $cache)) {
        return $cache[$ip];
    }
    $name = '';
    try {
        if ($ip !== '') {
            $hours = max(1, (int) get_setting('session_lifetime_hours', '168'));
            $st = db()->prepare("SELECT detail FROM admin_logs WHERE action = 'login' AND ip = ? AND detail LIKE 'ورود موفق%' AND created_at >= datetime('now', '-{$hours} hours') ORDER BY id DESC LIMIT 1");
            $st->execute([$ip]);
            $detail = (string) ($st->fetchColumn() ?: '');
            if ($detail !== '' && preg_match('/ورود موفق به پنل \(([^)]+)\)/u', $detail, $m)) {
                $name = trim((string) $m[1]);
            }
        }
    } catch (Throwable $ignored) {
    }
    $cache[$ip] = $name;
    return $name;
}

/** آماده‌سازی داده‌های صفحه لاگ‌ها (آمار، فهرست صفحه‌بندی‌شده، تنظیمات) */
function logs_prepare(array $get): array
{
    $pdo = db();
    $tab = ((string) ($get['tab'] ?? 'visits') === 'admin') ? 'admin' : 'visits';
    $q = trim((string) ($get['q'] ?? ''));
    $kind = (string) ($get['kind'] ?? 'all');
    if (!in_array($kind, ['all', 'visit', 'click'], true)) { $kind = 'all'; }
    $bot = (string) ($get['bot'] ?? 'all');
    if (!in_array($bot, ['all', 'human', 'bot'], true)) { $bot = 'all'; }
    $adminf = (string) ($get['adminf'] ?? 'hide');
    if (!in_array($adminf, ['hide', 'show', 'only'], true)) { $adminf = 'hide'; }
    $dr = (string) ($get['dr'] ?? 'all');
    if (!in_array($dr, ['all', 'today', 'yesterday', '7d', '30d'], true)) { $dr = 'all'; }
    $fcountry = trim((string) ($get['country'] ?? 'all'));
    $fdevice = (string) ($get['device'] ?? 'all');
    if (!in_array($fdevice, ['all', 'موبایل', 'تبلت', 'دسکتاپ'], true)) { $fdevice = 'all'; }
    $pageNum = max(1, (int) ($get['p'] ?? 1));
    $perPage = 30;
    $tzName = logs_tz()->getName();

    [$todayStart, $todayEnd] = logs_day_range('today');
    $adminCond = logs_admin_cond('v');
    $adminCondBare = logs_admin_cond('');

    // آمار امروز به وقت محلی تنظیم‌شده
    $stats = [
        'visits_today'       => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs v WHERE v.kind='visit' AND v.is_bot=0 AND NOT ($adminCond) AND v.created_at>='$todayStart' AND v.created_at<'$todayEnd'")->fetchColumn(),
        'clicks_today'       => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs v WHERE v.kind='click' AND v.created_at>='$todayStart' AND v.created_at<'$todayEnd'")->fetchColumn(),
        'bots_today'         => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs v WHERE v.is_bot=1 AND v.created_at>='$todayStart' AND v.created_at<'$todayEnd'")->fetchColumn(),
        'admin_visits_today' => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs v WHERE ($adminCond) AND v.created_at>='$todayStart' AND v.created_at<'$todayEnd'")->fetchColumn(),
        'ips_today'          => (int) $pdo->query("SELECT COUNT(DISTINCT v.ip) FROM visit_logs v WHERE v.created_at>='$todayStart' AND v.created_at<'$todayEnd'")->fetchColumn(),
        'admin_today'        => (int) $pdo->query("SELECT COUNT(*) FROM admin_logs WHERE date(created_at)=date('now')")->fetchColumn(),
        'visits_total'       => (int) $pdo->query('SELECT COUNT(*) FROM visit_logs')->fetchColumn(),
        'admin_total'        => (int) $pdo->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn(),
    ];

    $rows = [];
    $total = 0;
    if ($tab === 'visits') {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(v.ip LIKE :q OR v.path LIKE :q OR v.target LIKE :q OR v.referer LIKE :q OR v.admin_user LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        if ($kind !== 'all') {
            $where[] = 'v.kind = :kind';
            $params[':kind'] = $kind;
        }
        if ($bot === 'human') {
            $where[] = 'v.is_bot = 0';
        } elseif ($bot === 'bot') {
            $where[] = 'v.is_bot = 1';
        }
        if ($adminf === 'hide') {
            $where[] = "NOT ($adminCond)";
        } elseif ($adminf === 'only') {
            $where[] = "($adminCond)";
        }
        if ($dr !== 'all') {
            [$ds, $de] = logs_day_range($dr);
            $where[] = 'v.created_at >= :ds AND v.created_at < :de';
            $params[':ds'] = $ds;
            $params[':de'] = $de;
        }
        if ($fcountry !== '' && $fcountry !== 'all') {
            $where[] = 'v.country_code = :fcc';
            $params[':fcc'] = $fcountry;
        }
        if ($fdevice !== 'all') {
            $where[] = 'v.device_type = :fdev';
            $params[':fdev'] = $fdevice;
        }
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';
        $st = $pdo->prepare('SELECT COUNT(*) FROM visit_logs v' . $whereSql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $pdo->prepare('SELECT v.* FROM visit_logs v' . $whereSql . ' ORDER BY v.id DESC LIMIT ' . $perPage . ' OFFSET ' . (($pageNum - 1) * $perPage));
        $st->execute($params);
        $rows = $st->fetchAll();
    } else {
        $where = '';
        $params = [];
        if ($q !== '') {
            $where = ' WHERE (ip LIKE :q OR action LIKE :q OR detail LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }
        $st = $pdo->prepare('SELECT COUNT(*) FROM admin_logs' . $where);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $pdo->prepare('SELECT * FROM admin_logs' . $where . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($pageNum - 1) * $perPage));
        $st->execute($params);
        $rows = $st->fetchAll();
    }

    $countryList = [];
    try {
        $countryList = $pdo->query("SELECT country_code, country_name, COUNT(*) AS c FROM visit_logs WHERE country_code != '' GROUP BY country_code ORDER BY c DESC LIMIT 30")->fetchAll();
    } catch (Throwable $ignored) {}
    return [
        'tab' => $tab,
        'q' => $q,
        'kind' => $kind,
        'bot' => $bot,
        'adminf' => $adminf,
        'dr' => $dr,
        'country' => $fcountry,
        'device' => $fdevice,
        'country_list' => $countryList,
        'page' => $pageNum,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $perPage)),
        'rows' => $rows,
        'stats' => $stats,
        'tz' => $tzName,
        'settings' => [
            'session_lifetime_hours' => get_setting('session_lifetime_hours', '168'),
            'visit_log_enabled' => get_setting('visit_log_enabled', '1'),
            'admin_log_enabled' => get_setting('admin_log_enabled', '1'),
            'log_timezone' => get_setting('log_timezone', 'Asia/Tehran'),
            'log_retention_days' => get_setting('log_retention_days', '90'),
        ],
    ];
}
/** نمایش صفحه لاگ‌ها */
function logs_render(?array $d): void
{
    if ($d === null) {
        return;
    }
    $s = $d['settings'];
    $hours = max(1, (int) $s['session_lifetime_hours']);
    $hoursFa = $hours >= 24 && $hours % 24 === 0
        ? logs_num($hours / 24) . ' روز'
        : logs_num($hours) . ' ساعت';
    // ساخت نشانی تب/صفحه‌بندی با حفظ همه فیلترها
    $tabUrl = static function (string $t, int $p = 1) use ($d): string {
        $u = 'admin.php?page=logs&tab=' . $t
            . '&kind=' . $d['kind'] . '&bot=' . $d['bot'] . '&adminf=' . $d['adminf'] . '&dr=' . $d['dr']
            . '&country=' . urlencode((string) ($d['country'] ?? 'all')) . '&device=' . urlencode((string) ($d['device'] ?? 'all'))
            . ($d['q'] !== '' ? '&q=' . urlencode($d['q']) : '')
            . ($p > 1 ? '&p=' . $p : '');
        return $u;
    };
    $timezones = [
        'Asia/Tehran' => 'تهران (Asia/Tehran)',
        'Asia/Dubai' => 'دبی (Asia/Dubai)',
        'Asia/Karachi' => 'کراچی (Asia/Karachi)',
        'Asia/Istanbul' => 'استانبول (Asia/Istanbul)',
        'Europe/Berlin' => 'برلین (Europe/Berlin)',
        'Europe/London' => 'لندن (Europe/London)',
        'America/New_York' => 'نیویورک (America/New_York)',
        'UTC' => 'UTC',
    ];
    ?>
    <h1>لاگ‌ها</h1>
    <div class="stat-grid">
        <div class="stat-card"><span>بازدید انسانی امروز</span><strong><?= logs_num($d['stats']['visits_today']) ?></strong></div>
        <div class="stat-card"><span>کلیک امروز</span><strong><?= logs_num($d['stats']['clicks_today']) ?></strong></div>
        <div class="stat-card"><span>ربات امروز</span><strong><?= logs_num($d['stats']['bots_today']) ?></strong></div>
        <div class="stat-card"><span>بازدید ادمین امروز</span><strong><?= logs_num($d['stats']['admin_visits_today']) ?></strong></div>
        <div class="stat-card"><span>آی‌پی یکتای امروز</span><strong><?= logs_num($d['stats']['ips_today']) ?></strong></div>
        <div class="stat-card"><span>رویداد مدیریت امروز</span><strong><?= logs_num($d['stats']['admin_today']) ?></strong></div>
    </div>
    <p class="muted">ساعت‌ها به وقت <strong><?= e($d['tz']) ?></strong> نمایش داده می‌شوند. (از تنظیمات زیر قابل تغییر است)</p>

    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_log_settings">
        <input type="hidden" name="tab" value="<?= e($d['tab']) ?>">
        <h2>تنظیمات نشست و لاگ</h2>
        <label>مدت اعتبار نشست مدیریت (ساعت) — نشست بعد از این مدت منقضی می‌شود و ورود دوباره لازم است
            <input type="number" name="session_lifetime_hours" min="1" max="720" step="1" value="<?= e((string) $hours) ?>">
        </label>
        <p class="muted">در حال حاضر: <strong><?= e($hoursFa) ?></strong>. نشست‌ها داخل پوشه داخلی خود سیستم نگه داشته می‌شوند تا روی هاست اشتراکی زود پاک نشوند؛ این مقدار از درخواست بعدی اعمال می‌شود.</p>
        <label>منطقه زمانی نمایش لاگ‌ها
            <select name="log_timezone">
                <?php foreach ($timezones as $tzKey => $tzLabel): ?>
                    <option value="<?= e($tzKey) ?>" <?= ($s['log_timezone'] ?? 'Asia/Tehran') === $tzKey ? 'selected' : '' ?>><?= e($tzLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>مدت نگهداری لاگ‌ها (روز) — قدیمی‌تر از این به‌صورت خودکار پاک می‌شود
            <input type="number" name="log_retention_days" min="1" max="3650" step="1" value="<?= e((string) $s['log_retention_days']) ?>">
        </label>
        <label class="check"><input type="checkbox" name="visit_log_enabled" value="1" <?= $s['visit_log_enabled'] === '1' ? 'checked' : '' ?>> ثبت لاگ بازدید سایت (بازدید صفحه و کلیک‌ها)</label>
        <label class="check"><input type="checkbox" name="admin_log_enabled" value="1" <?= $s['admin_log_enabled'] === '1' ? 'checked' : '' ?>> ثبت لاگ فعالیت مدیریت (ورود، خروج و اکشن‌ها)</label>
        <p class="muted">ربات‌ها و خزنده‌ها هم ثبت می‌شوند و با فیلتر «ربات» در فهرست زیر قابل جداسازی‌اند.</p>
        <button type="submit" class="btn primary">ذخیره تنظیمات</button>
    </form>

    <div class="card">
        <nav class="tabs" aria-label="نوع لاگ">
            <a class="tab<?= $d['tab'] === 'visits' ? ' active' : '' ?>" href="<?= e($tabUrl('visits')) ?>">لاگ بازدید سایت (<?= logs_num($d['stats']['visits_total']) ?>)</a>
            <a class="tab<?= $d['tab'] === 'admin' ? ' active' : '' ?>" href="<?= e($tabUrl('admin')) ?>">لاگ فعالیت مدیریت (<?= logs_num($d['stats']['admin_total']) ?>)</a>
        </nav>

        <?php if ($d['tab'] === 'visits'): ?>
        <form method="get" class="card wide" style="margin-bottom:15px">
            <input type="hidden" name="page" value="logs">
            <input type="hidden" name="tab" value="visits">
            <h3 style="margin-top:0">فیلترها</h3>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px">
                <label>جستجو
                    <input type="text" name="q" value="<?= e($d['q']) ?>" placeholder="آی‌پی، صفحه، ادمین…">
                </label>
                <label>نوع رویداد
                    <select name="kind">
                        <option value="all" <?= $d['kind'] === 'all' ? 'selected' : '' ?>>همه</option>
                        <option value="visit" <?= $d['kind'] === 'visit' ? 'selected' : '' ?>>بازدید</option>
                        <option value="click" <?= $d['kind'] === 'click' ? 'selected' : '' ?>>کلیک</option>
                    </select>
                </label>
                <label>نوع بازدیدکننده
                    <select name="bot">
                        <option value="all" <?= $d['bot'] === 'all' ? 'selected' : '' ?>>همه</option>
                        <option value="human" <?= $d['bot'] === 'human' ? 'selected' : '' ?>>انسان</option>
                        <option value="bot" <?= $d['bot'] === 'bot' ? 'selected' : '' ?>>ربات / خزنده</option>
                    </select>
                </label>
                <label>بازدید ادمین
                    <select name="adminf">
                        <option value="hide" <?= $d['adminf'] === 'hide' ? 'selected' : '' ?>>پنهان</option>
                        <option value="show" <?= $d['adminf'] === 'show' ? 'selected' : '' ?>>نمایش</option>
                        <option value="only" <?= $d['adminf'] === 'only' ? 'selected' : '' ?>>فقط ادمین</option>
                    </select>
                </label>
                <label>بازه زمانی
                    <select name="dr">
                        <option value="all" <?= $d['dr'] === 'all' ? 'selected' : '' ?>>همه زمان‌ها</option>
                        <option value="today" <?= $d['dr'] === 'today' ? 'selected' : '' ?>>امروز</option>
                        <option value="yesterday" <?= $d['dr'] === 'yesterday' ? 'selected' : '' ?>>دیروز</option>
                        <option value="7d" <?= $d['dr'] === '7d' ? 'selected' : '' ?>>۷ روز اخیر</option>
                        <option value="30d" <?= $d['dr'] === '30d' ? 'selected' : '' ?>>۳۰ روز اخیر</option>
                    </select>
                </label>
                <label>کشور
                    <select name="country">
                        <option value="all" <?= ($d['country'] ?? 'all') === 'all' ? 'selected' : '' ?>>همه کشورها</option>
                        <?php foreach (($d['country_list'] ?? []) as $cc): ?>
                            <option value="<?= e((string) $cc['country_code']) ?>" <?= ($d['country'] ?? '') === (string) $cc['country_code'] ? 'selected' : '' ?>><?= country_flag_emoji((string) $cc['country_code']) ?> <?= e((string) $cc['country_name']) ?> (<?= logs_num($cc['c']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>دستگاه
                    <select name="device">
                        <option value="all" <?= ($d['device'] ?? 'all') === 'all' ? 'selected' : '' ?>>همه</option>
                        <option value="موبایل" <?= ($d['device'] ?? '') === 'موبایل' ? 'selected' : '' ?>>📱 موبایل</option>
                        <option value="تبلت" <?= ($d['device'] ?? '') === 'تبلت' ? 'selected' : '' ?>>📲 تبلت</option>
                        <option value="دسکتاپ" <?= ($d['device'] ?? '') === 'دسکتاپ' ? 'selected' : '' ?>>🖥️ دسکتاپ</option>
                    </select>
                </label>
            </div>
            <div style="margin-top:12px">
                <button type="submit" class="btn primary">اعمال فیلتر</button>
                <a class="btn" href="admin.php?page=logs&tab=visits">حذف فیلترها</a>
            </div>
        </form>
        <?php else: ?>
        <form method="get" class="inline">
            <input type="hidden" name="page" value="logs">
            <input type="hidden" name="tab" value="admin">
            <label style="display:inline-block;min-width:260px">جستجو
                <input type="text" name="q" value="<?= e($d['q']) ?>" placeholder="آی‌پی، اکشن، توضیح…">
            </label>
            <button type="submit" class="btn">جستجو</button>
            <?php if ($d['q'] !== ''): ?><a class="btn" href="<?= e($tabUrl('admin')) ?>">حذف فیلتر</a><?php endif; ?>
        </form>
        <?php endif; ?>

        <?php if ($d['tab'] === 'visits'): ?>
        <form method="post" class="inline" style="float:left;margin-left:8px" title="تکمیل کشور/دستگاه/مرورگر برای ردیف‌های قدیمی (هر بار ۱۰۰ ردیف)">
            <?= csrf_field() ?><input type="hidden" name="action" value="backfill_visit_geo">
            <button type="submit" class="btn">🌍 تکمیل اطلاعات</button>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('همه لاگ بازدید پاک شود؟')" style="float:left">
            <?= csrf_field() ?><input type="hidden" name="action" value="clear_visit_logs">
            <button type="submit" class="btn danger-btn">پاک‌کردن لاگ بازدید</button>
        </form>
        <?php else: ?>
        <form method="post" class="inline" onsubmit="return confirm('همه لاگ مدیریت پاک شود؟')" style="float:left">
            <?= csrf_field() ?><input type="hidden" name="action" value="clear_admin_logs">
            <button type="submit" class="btn danger-btn">پاک‌کردن لاگ مدیریت</button>
        </form>
        <?php endif; ?>
        <div style="clear:both"></div>

        <?php if ($d['rows'] === []): ?>
            <p class="muted">هنوز چیزی ثبت نشده است (برای این فیلتر).</p>
        <?php elseif ($d['tab'] === 'visits'): ?>
        <table>
            <thead><tr><th>زمان (<?= e($d['tz']) ?>)</th><th>نوع</th><th>بازدیدکننده</th><th>کشور</th><th>دستگاه / مرورگر</th><th>آی‌پی</th><th>از کجا آمده</th><th>صفحه / کلیک‌شده</th></tr></thead>
            <tbody>
            <?php foreach ($d['rows'] as $r): ?>
                <?php
                $isBot = (int) ($r['is_bot'] ?? 0) === 1;
                $adminUser = trim((string) ($r['admin_user'] ?? ''));
                $isAdmin = $adminUser !== '';
                $guessed = '';
                if (!$isAdmin) {
                    $guessed = visit_admin_guess((string) ($r['ip'] ?? ''));
                }
                ?>
                <tr<?= $isBot ? ' style="opacity:.75"' : '' ?>>
                    <td dir="ltr" style="white-space:nowrap"><?= e(log_local_time((string) $r['created_at'])) ?></td>
                    <td><?= ($r['kind'] ?? 'visit') === 'click' ? '<span class="badge off">کلیک</span>' : '<span class="badge ok">بازدید</span>' ?></td>
                    <td>
                        <?php if ($isAdmin): ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e" title="این بازدید با نشست ادمین انجام شده">👤 ادمین: <?= e($adminUser) ?></span>
                        <?php elseif ($guessed !== ''): ?>
                            <span class="badge" style="background:#fef3c7;color:#92400e" title="این آی‌پی اخیراً وارد پنل مدیریت شده است">👤 احتمالاً ادمین: <?= e($guessed) ?></span>
                        <?php elseif ($isBot): ?>
                            <span class="badge" style="background:#e0e7ff;color:#3730a3">🤖 ربات</span>
                        <?php else: ?>
                            <span class="muted">انسان</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $cc = trim((string) ($r['country_code'] ?? ''));
                        $cn = trim((string) ($r['country_name'] ?? ''));
                        if ($cc !== '' || $cn !== ''): ?>
                            <span title="<?= e($cc) ?>"><?= country_flag_emoji($cc) ?> <?= e($cn !== '' ? $cn : $cc) ?></span>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $dv = trim((string) ($r['device_type'] ?? ''));
                        $br = trim((string) ($r['browser_name'] ?? ''));
                        $dvIcon = $dv === 'موبایل' ? '📱' : ($dv === 'تبلت' ? '📲' : ($dv === 'دسکتاپ' ? '🖥️' : ''));
                        ?>
                        <?php if ($dv !== '' || $br !== ''): ?>
                            <span title="دستگاه"><?= e($dvIcon . ' ' . $dv) ?></span><?php if ($br !== ''): ?><br><span class="muted" style="font-size:11px"><?= e($br) ?></span><?php endif; ?>
                        <?php else: ?><span class="muted">—</span><?php endif; ?>
                    </td>
                    <td dir="ltr"><?= e((string) $r['ip']) ?></td>
                    <td><?php $rh = log_referer_host((string) ($r['referer'] ?? '')); ?>
                        <?php if ($rh === ''): ?><span class="muted">مستقیم</span>
                        <?php else: ?><span dir="ltr" title="<?= e((string) $r['referer']) ?>"><?= e($rh) ?></span><?php endif; ?>
                    </td>
                    <td>
                        <span dir="ltr"><?= e((string) $r['path']) ?></span>
                        <?php if ((string) ($r['target'] ?? '') !== ''): ?><br><span class="muted">کلیک روی: <?= e((string) $r['target']) ?></span><?php endif; ?>
                        <?php if ((string) ($r['user_agent'] ?? '') !== ''): ?><br><span class="muted" dir="ltr" style="font-size:11px"><?= e(substr((string) $r['user_agent'], 0, 90)) ?></span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <table>
            <thead><tr><th>زمان (<?= e($d['tz']) ?>)</th><th>آی‌پی</th><th>کار</th><th>توضیح</th><th>نتیجه</th></tr></thead>
            <tbody>
            <?php foreach ($d['rows'] as $r): ?>
                <tr>
                    <td dir="ltr" style="white-space:nowrap"><?= e(log_local_time((string) $r['created_at'])) ?></td>
                    <td dir="ltr"><?= e((string) $r['ip']) ?></td>
                    <td><?= e(log_action_label((string) $r['action'])) ?><?php if ((string) ($r['page'] ?? '') !== ''): ?> <span class="muted">(<?= e((string) $r['page']) ?>)</span><?php endif; ?></td>
                    <td><?= e((string) ($r['detail'] ?? '')) ?></td>
                    <td><?= (int) ($r['ok'] ?? 1) === 1 ? '<span class="badge ok">موفق</span>' : '<span class="badge" style="background:#fee2e2;color:#b91c1c">ناموفق</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <?php if ($d['pages'] > 1): ?>
        <nav class="tabs" aria-label="صفحه‌بندی">
            <?php if ($d['page'] > 1): ?><a class="tab" href="<?= e($tabUrl($d['tab'], $d['page'] - 1)) ?>">قبلی</a><?php endif; ?>
            <span class="tab active">صفحه <?= logs_num($d['page']) ?> از <?= logs_num($d['pages']) ?> — مجموع <?= logs_num($d['total']) ?> ردیف</span>
            <?php if ($d['page'] < $d['pages']): ?><a class="tab" href="<?= e($tabUrl($d['tab'], $d['page'] + 1)) ?>">بعدی</a><?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php
}
