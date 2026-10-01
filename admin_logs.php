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
    return ['save_log_settings', 'clear_visit_logs', 'clear_admin_logs'];
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
            set_setting('log_skip_bots', isset($_POST['log_skip_bots']) ? '1' : '0');
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

/** آماده‌سازی داده‌های صفحه لاگ‌ها (آمار، فهرست صفحه‌بندی‌شده، تنظیمات) */
function logs_prepare(array $get): array
{
    $pdo = db();
    $tab = ((string) ($get['tab'] ?? 'visits') === 'admin') ? 'admin' : 'visits';
    $q = trim((string) ($get['q'] ?? ''));
    $pageNum = max(1, (int) ($get['p'] ?? 1));
    $perPage = 30;

    // آمار بالای صفحه (امروز)
    $stats = [
        'visits_today'  => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind='visit' AND date(created_at)=date('now')")->fetchColumn(),
        'clicks_today'  => (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind='click' AND date(created_at)=date('now')")->fetchColumn(),
        'ips_today'     => (int) $pdo->query("SELECT COUNT(DISTINCT ip) FROM visit_logs WHERE date(created_at)=date('now')")->fetchColumn(),
        'admin_today'   => (int) $pdo->query("SELECT COUNT(*) FROM admin_logs WHERE date(created_at)=date('now')")->fetchColumn(),
        'visits_total'  => (int) $pdo->query('SELECT COUNT(*) FROM visit_logs')->fetchColumn(),
        'admin_total'   => (int) $pdo->query('SELECT COUNT(*) FROM admin_logs')->fetchColumn(),
    ];

    $rows = [];
    $total = 0;
    if ($tab === 'visits') {
        $sql = 'SELECT * FROM visit_logs';
        $countSql = 'SELECT COUNT(*) FROM visit_logs';
        $params = [];
        if ($q !== '') {
            $where = ' WHERE (ip LIKE :q OR path LIKE :q OR target LIKE :q OR referer LIKE :q)';
            $sql .= $where;
            $countSql .= $where;
            $params[':q'] = '%' . $q . '%';
        }
        $st = $pdo->prepare($countSql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($pageNum - 1) * $perPage));
        $st->execute($params);
        $rows = $st->fetchAll();
    } else {
        $sql = 'SELECT * FROM admin_logs';
        $countSql = 'SELECT COUNT(*) FROM admin_logs';
        $params = [];
        if ($q !== '') {
            $where = ' WHERE (ip LIKE :q OR action LIKE :q OR detail LIKE :q)';
            $sql .= $where;
            $countSql .= $where;
            $params[':q'] = '%' . $q . '%';
        }
        $st = $pdo->prepare($countSql);
        $st->execute($params);
        $total = (int) $st->fetchColumn();
        $st = $pdo->prepare($sql . ' ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($pageNum - 1) * $perPage));
        $st->execute($params);
        $rows = $st->fetchAll();
    }

    return [
        'tab' => $tab,
        'q' => $q,
        'page' => $pageNum,
        'per_page' => $perPage,
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $perPage)),
        'rows' => $rows,
        'stats' => $stats,
        'settings' => [
            'session_lifetime_hours' => get_setting('session_lifetime_hours', '168'),
            'visit_log_enabled' => get_setting('visit_log_enabled', '1'),
            'admin_log_enabled' => get_setting('admin_log_enabled', '1'),
            'log_skip_bots' => get_setting('log_skip_bots', '1'),
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
    $tabUrl = static fn (string $t, int $p = 1): string => 'admin.php?page=logs&tab=' . $t
        . ($d['q'] !== '' ? '&q=' . urlencode($d['q']) : '')
        . ($p > 1 ? '&p=' . $p : '');
    ?>
    <h1>لاگ‌ها</h1>
    <div class="stat-grid">
        <div class="stat-card"><span>بازدید امروز</span><strong><?= logs_num($d['stats']['visits_today']) ?></strong></div>
        <div class="stat-card"><span>کلیک امروز</span><strong><?= logs_num($d['stats']['clicks_today']) ?></strong></div>
        <div class="stat-card"><span>آی‌پی یکتای امروز</span><strong><?= logs_num($d['stats']['ips_today']) ?></strong></div>
        <div class="stat-card"><span>رویداد مدیریت امروز</span><strong><?= logs_num($d['stats']['admin_today']) ?></strong></div>
    </div>

    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_log_settings">
        <input type="hidden" name="tab" value="<?= e($d['tab']) ?>">
        <h2>تنظیمات نشست و لاگ</h2>
        <label>مدت اعتبار نشست مدیریت (ساعت) — نشست بعد از این مدت منقضی می‌شود و ورود دوباره لازم است
            <input type="number" name="session_lifetime_hours" min="1" max="720" step="1" value="<?= e((string) $hours) ?>">
        </label>
        <p class="muted">در حال حاضر: <strong><?= e($hoursFa) ?></strong>. نشست‌ها داخل پوشه داخلی خود سیستم نگه داشته می‌شوند تا روی هاست اشتراکی زود پاک نشوند؛ این مقدار از درخواست بعدی اعمال می‌شود.</p>
        <label>مدت نگهداری لاگ‌ها (روز) — قدیمی‌تر از این به‌صورت خودکار پاک می‌شود
            <input type="number" name="log_retention_days" min="1" max="3650" step="1" value="<?= e((string) $s['log_retention_days']) ?>">
        </label>
        <label class="check"><input type="checkbox" name="visit_log_enabled" value="1" <?= $s['visit_log_enabled'] === '1' ? 'checked' : '' ?>> ثبت لاگ بازدید سایت (بازدید صفحه و کلیک‌ها)</label>
        <label class="check"><input type="checkbox" name="log_skip_bots" value="1" <?= $s['log_skip_bots'] === '1' ? 'checked' : '' ?>> ربات‌ها و خزنده‌ها (مثل موتورهای جستجو) در لاگ بازدید ثبت نشوند</label>
        <label class="check"><input type="checkbox" name="admin_log_enabled" value="1" <?= $s['admin_log_enabled'] === '1' ? 'checked' : '' ?>> ثبت لاگ فعالیت مدیریت (ورود، خروج و اکشن‌ها)</label>
        <button type="submit" class="btn primary">ذخیره تنظیمات</button>
    </form>

    <div class="card">
        <nav class="tabs" aria-label="نوع لاگ">
            <a class="tab<?= $d['tab'] === 'visits' ? ' active' : '' ?>" href="<?= e($tabUrl('visits')) ?>">لاگ بازدید سایت (<?= logs_num($d['stats']['visits_total']) ?>)</a>
            <a class="tab<?= $d['tab'] === 'admin' ? ' active' : '' ?>" href="<?= e($tabUrl('admin')) ?>">لاگ فعالیت مدیریت (<?= logs_num($d['stats']['admin_total']) ?>)</a>
        </nav>
        <form method="get" class="inline">
            <input type="hidden" name="page" value="logs">
            <input type="hidden" name="tab" value="<?= e($d['tab']) ?>">
            <label style="display:inline-block;min-width:260px">جستجو
                <input type="text" name="q" value="<?= e($d['q']) ?>" placeholder="<?= $d['tab'] === 'visits' ? 'آی‌پی، نشانی صفحه، منبع ورود…' : 'آی‌پی، اکشن، توضیح…' ?>">
            </label>
            <button type="submit" class="btn">جستجو</button>
            <?php if ($d['q'] !== ''): ?><a class="btn" href="<?= e($tabUrl($d['tab'])) ?>">حذف فیلتر</a><?php endif; ?>
        </form>
        <?php if ($d['tab'] === 'visits'): ?>
        <form method="post" class="inline" onsubmit="return confirm('همه لاگ بازدید پاک شود؟')" style="float:left">
            <?= csrf_field() ?><input type="hidden" name="action" value="clear_visit_logs">
            <button type="submit" class="danger">پاک‌کردن لاگ بازدید</button>
        </form>
        <?php else: ?>
        <form method="post" class="inline" onsubmit="return confirm('همه لاگ مدیریت پاک شود؟')" style="float:left">
            <?= csrf_field() ?><input type="hidden" name="action" value="clear_admin_logs">
            <button type="submit" class="danger">پاک‌کردن لاگ مدیریت</button>
        </form>
        <?php endif; ?>
        <div style="clear:both"></div>

        <?php if ($d['rows'] === []): ?>
            <p class="muted">هنوز چیزی ثبت نشده است<?= $d['q'] !== '' ? ' (برای این جستجو)' : '' ?>.</p>
        <?php elseif ($d['tab'] === 'visits'): ?>
        <table>
            <thead><tr><th>زمان</th><th>نوع</th><th>آی‌پی</th><th>از کجا آمده</th><th>صفحه / کلیک‌شده</th></tr></thead>
            <tbody>
            <?php foreach ($d['rows'] as $r): ?>
                <tr>
                    <td dir="ltr" style="white-space:nowrap"><?= e((string) $r['created_at']) ?></td>
                    <td><?= ($r['kind'] ?? 'visit') === 'click' ? '<span class="badge off">کلیک</span>' : '<span class="badge ok">بازدید</span>' ?></td>
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
            <thead><tr><th>زمان</th><th>آی‌پی</th><th>کار</th><th>توضیح</th><th>نتیجه</th></tr></thead>
            <tbody>
            <?php foreach ($d['rows'] as $r): ?>
                <tr>
                    <td dir="ltr" style="white-space:nowrap"><?= e((string) $r['created_at']) ?></td>
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
