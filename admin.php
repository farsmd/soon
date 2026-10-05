<?php
// admin.php — پنل مدیریت محتوای ساده (پسورددار) — نسخه ۸
// مدیریت بخش‌ها (با محتوای واقعی و آپلود عکس)، صفحه‌ها، قالب و CSS دیتابیسی، پیام‌های تماس، بکاپ، اتصال دیتابیس، آپدیت یک‌کلیکی ، مشتری‌ها و کاتالوگ محصول (فاز ۲)، مواد اولیه و انبار (فاز ۲٫۵)، سفارش‌ها و پیش‌فاکتور (فاز ۳) و تنظیمات

declare(strict_types=1);

require __DIR__ . '/core/config.php';

// صفحات و اکشن‌های کاتالوگ فاز ۲ در فایل جدا هستند تا admin.php برای آپدیت گیت‌هاب کوچک بماند
define('CMS_ADMIN_PANEL', true);
require_once __DIR__ . '/modules/admin/Catalog.php';
// صفحات و اکشن‌های انبار و مواد اولیه فاز ۲٫۵ (نسخه ۷) هم در فایل جدا هستند
require_once __DIR__ . '/modules/admin/Inventory.php';
// صفحات و اکشن‌های سفارش‌ها و پیش‌فاکتور (فاز ۳ / نسخه ۸) هم در فایل جدا هستند
require_once __DIR__ . '/modules/admin/Orders.php';
// صفحات و اکشن‌های تولید و برگه کارگاه (فاز ۴ / نسخه ۸٫۳) هم در فایل جدا هستند
require_once __DIR__ . '/modules/admin/Production.php';
// صفحات و اکشن‌های مالی: فاکتور، دریافتی، هزینه و صورتحساب (فاز ۵ / نسخه ۸٫۴) هم در فایل جدا هستند
require_once __DIR__ . '/modules/admin/Finance.php';
// منابع انسانی: پرسنل و حقوق (نسخه ۹٫۷)
require_once __DIR__ . '/modules/admin/HR.php';
require_once __DIR__ . '/modules/admin/Blog.php';
// تجهیزات و دارایی‌ها (نسخه ۹٫۷)
require_once __DIR__ . '/modules/admin/Assets.php';
// گزارش‌های مدیریتی (فاز ۶ / نسخه ۸٫۶): فروش، محصولات، مصرف مواد، مشتریان و تولید
require_once __DIR__ . '/modules/admin/Reports.php';
// کاربران و نقش‌های پنل (فاز ۰ / نسخه ۸٫۷): ورود چندکاربره و سطح دسترسی صفحه/اکشن
require_once __DIR__ . '/modules/admin/Users.php';
// صفحه لاگ‌های بازدید و مدیریت (نسخه ۸٫۲)
require_once __DIR__ . '/modules/admin/Logs.php';
require_once __DIR__ . '/modules/admin/Notifications.php';
require_once __DIR__ . '/modules/admin/Proposal.php';
require_once __DIR__ . '/modules/admin/Modules.php';
require_once __DIR__ . '/modules/admin/Templates.php';
// seed ماژول‌های پیش‌فرض (فقط بار اول)
if (function_exists('modules_seed')) { modules_seed(); }

// ---------- تاریخ شمسی (جلالی) — پیاده‌سازی الگوریتم استاندارد، بدون وابستگی خارجی ----------
if (!function_exists('ll_gregorian_to_jalali')) {
    /** تبدیل تاریخ میلادی به جلالی؛ خروجی [سال، ماه، روز] */
    function ll_gregorian_to_jalali(int $gy, int $gm, int $gd): array
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
    /** ارقام لاتین به فارسی */
    function ll_fa_digits(string $s): string
    {
        return strtr($s, '0123456789', '۰۱۲۳۴۵۶۷۸۹');
    }
    /** قالب‌بندی شمسی یک رشته تاریخ/زمان؛ مثلاً «۱۱ مهر ۱۴۰۵ — ۱۴:۳۲» */
    function ll_jalali_format(string $datetime, bool $withTime = false, bool $withWeekday = false): string
    {
        $ts = strtotime($datetime);
        if ($ts === false) {
            return '';
        }
        [$jy, $jm, $jd] = ll_gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
        static $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        static $weekdays = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
        $out = '';
        if ($withWeekday) {
            $w = (int) date('w', $ts);
            $out .= $weekdays[($w + 1) % 7] . ' ';
        }
        $out .= $jd . ' ' . $months[$jm - 1] . ' ' . $jy;
        if ($withTime) {
            $out .= ' — ' . date('H:i', $ts);
        }
        return ll_fa_digits($out);
    }
    /** تاریخ امروز شمسی با روز هفته؛ مثلاً «شنبه ۱۱ مهر ۱۴۰۵» */
    function ll_jalali_today(): string
    {
        return ll_jalali_format(date('Y-m-d H:i:s'), false, true);
    }
}

// ---------- نشست ----------
// نشست‌ها داخل دیتابیس سیستم ذخیره می‌شوند (نسخه ۸٫۲٫۱) تا پاک‌سازی و قفلِ مسیر
// نشستِ هاست اشتراکی نتواند نشست مدیر را بکشد و فرمِ بازمانده را با خطای CSRF بکشد.
cms_session_start();

function is_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function check_csrf(): void
{
    if (hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        return;
    }
    // نشست منقضی یا صفحهٔ خیلی بازمانده نباید بن‌بست باشد: درخواست مثل قبل رد و
    // هیچ‌وقت اجرا نمی‌شود، ولی کاربر هیچ‌وقت متن خامِ خطای CSRF نمی‌بیند؛ مدیرِ
    // واردشده با پیام روشن به همان صفحه برمی‌گردد و اگر نشست کاملاً منقضی شده
    // بود، به صفحهٔ ورود می‌رود و همان‌جا پیام را می‌بیند.
    $_SESSION['flash'] = ['type' => 'error', 'message' => 'نشستت منقضی شده بود یا این صفحه خیلی باز مانده بود، برای همین فرمت ثبت نشد. حالا دوباره تلاش کن.'];
    if (!empty($_SESSION['admin_logged_in'])) {
        $back = (string) ($_GET['page'] ?? '');
        if (!preg_match('/^[a-z_]+$/', $back)) {
            $back = 'dashboard';
        }
        header('Location: admin.php?page=' . $back);
        exit;
    }
    header('Location: admin.php');
    exit;
}

function redirect_admin(string $url = 'admin.php'): void
{
    // لاگ فعالیت مدیریت: هر اکشن موفق POST که با ریدایرکت تمام می‌شود همین‌جا ثبت می‌شود
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_SESSION['admin_logged_in'])) {
        $logAction = (string) ($_POST['action'] ?? '');
        if ($logAction !== '' && $logAction !== 'login' && $logAction !== 'setup') {
            log_admin_event($logAction, '', true, (string) ($_GET['page'] ?? ''));
        }
    }
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function logout_admin(): void
{
    // نشست خودترمیم دیتابیسی (نسخه ۸٫۲٫۲): ردیف نشست حذف و کوکی از مرورگر پاک می‌شود
    cms_session_destroy_current();
}

/** آیکون SVG منوی کناری پنل. */
function nav_icon(string $name): string
{
    static $paths = [
        'dashboard' => '<path d="M3 3h7v7H3zM14 3h7v7h-7zM14 14h7v7h-7zM3 14h7v7H3z"/>',
        'info' => '<path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>',
        'menu'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'eye'       => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'file'      => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8"/>',
        'layout'    => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'droplet'   => '<path d="M12 2.7 17.66 8.36a8 8 0 1 1-11.31 0z"/>',
        'users'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        'templates' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/>',
        'modules'   => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'folder'    => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
        'bag'       => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'list'      => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'sliders'   => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3"/><path d="M1 14h6M9 8h6M17 16h6"/>',
        'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>',
        'archive'   => '<path d="M3 3h18v5H3zM5 8v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8M10 12h4"/>',
        'refresh'   => '<path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6"/>',
        'chart'     => '<path d="M3 3v18h18"/><path d="M8 17v-5M13 17V8M18 17v-8"/>',
        'box'       => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'swap'      => '<path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'receipt'   => '<path d="M5 2h14a1 1 0 0 1 1 1v18l-3-2-2 2-2-2-2 2-2-2-2 2-2-2-1 1V3a1 1 0 0 1 1-1z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'cut'       => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M8.7 8.7 20 20M8.7 15.3 20 4"/>',
        'key'       => '<circle cx="8" cy="15" r="4"/><path d="M11 12 21 2M16 7l3 3M13 10l2 2"/>',
        'image'     => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'handshake' => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
        'bell'      => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'chevron'   => '<path d="m6 9 6 6 6-6"/>',
    ];
    return '<svg class="nav-ico" viewBox="0 0 24 24" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** آمار عکس‌های سنگین uploads برای نمایش در ابزار */
function tools_image_stats(): array
{
    $count = 0; $size = 0;
    $d = __DIR__ . '/uploads';
    if (is_dir($d)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) { continue; }
            if ($f->getSize() < 150 * 1024) { continue; }
            $count++; $size += $f->getSize();
        }
    }
    return ['count' => $count, 'size' => format_bytes($size)];
}

/** بهینه‌سازی عکس‌های uploads: تغییر اندازه به حداکثر ۱۲۰۰ پیکسل + فشرده‌سازی (یک کلیک) */
function tools_optimize_images(): array
{
    if (!extension_loaded('gd')) {
        return ['ok' => false, 'error' => 'افزونه GD روی این سرور فعال نیست.'];
    }
    $dirs = [__DIR__ . '/uploads'];
    $files = [];
    foreach ($dirs as $d) {
        if (!is_dir($d)) { continue; }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile()) { continue; }
            $ext = strtolower($f->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) { continue; }
            if ($f->getSize() < 150 * 1024) { continue; } // فقط فایل‌های بالای ۱۵۰KB
            $files[] = $f->getPathname();
        }
    }
    if (empty($files)) {
        return ['ok' => true, 'done' => 0, 'saved' => '۰ بایت'];
    }
    $done = 0; $savedBytes = 0;
    foreach ($files as $path) {
        $before = filesize($path);
        $info = @getimagesize($path);
        if ($info === false) { continue; }
        [$w, $h, $type] = $info;
        // اگر کوچک است و JPEG است، فقط بازنویسی با کیفیت بهتر
        $maxDim = 1200;
        $scale = min(1, $maxDim / max($w, $h));
        $nw = (int) round($w * $scale); $nh = (int) round($h * $scale);
        $srcImg = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if ($srcImg === false) { continue; }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP) {
            imagealphablending($dst, false); imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $tmp = $path . '.opt';
        $ok = match ($type) {
            IMAGETYPE_JPEG => imagejpeg($dst, $tmp, 82),
            IMAGETYPE_PNG => imagepng($dst, $tmp, 6),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dst, $tmp, 82) : false,
            default => false,
        };
        imagedestroy($srcImg); imagedestroy($dst);
        if ($ok && is_file($tmp) && filesize($tmp) < $before) {
            @rename($tmp, $path);
            $savedBytes += $before - filesize($path);
            $done++;
        } else {
            @unlink($tmp);
        }
        if ($done >= 60) { break; } // سقف هر اجرا برای جلوگیری از تایم‌اوت
    }
    return ['ok' => true, 'done' => $done, 'saved' => format_bytes($savedBytes)];
}

/** صفحه «دسترسی API»: مدیریت توکن، دامنه‌ها و راهنمای اتصال عامل‌ها */
function api_render(): void
{
    $enabled = get_setting('api_enabled', '0') === '1';
    $scopes = array_filter(array_map('trim', explode(',', (string) get_setting('api_scopes', 'read'))));
    $hasToken = get_setting('api_token_hash', '') !== '';
    $created = get_setting('api_token_created', '');
    $newToken = $_SESSION['api_new_token'] ?? null;
    unset($_SESSION['api_new_token']);
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base = $scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'example.com') . rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin.php')), '/\\');
    $apiUrl = $base . '/api.php';
    $logs = db()->query("SELECT created_at, ip, action, detail, ok FROM admin_logs WHERE page = 'api' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <h1>دسترسی API</h1>
    <p class="muted">با این API می‌توانید خواندن و تغییر محتوای سایت را به یک عامل (مثل ریکی) یا نرم‌افزار بسپارید. احراز هویت با توکن است؛ توکن فقط هنگام ساخت یک بار نمایش داده می‌شود و در دیتابیس فقط هش آن ذخیره می‌شود.</p>

    <?php if ($newToken !== null): ?>
        <div class="card wide">
            <h3>توکن تازه — همین حالا کپی کنید</h3>
            <p class="muted">این توکن دیگر نمایش داده نمی‌شود. آن را در جای امن نگه دارید.</p>
            <p><code dir="ltr" style="user-select:all;word-break:break-all;font-size:15px"><?= e((string) $newToken) ?></code></p>
            <button type="button" class="btn small" onclick="navigator.clipboard&&navigator.clipboard.writeText(this.previousElementSibling.textContent.trim());this.textContent='کپی شد'">کپی توکن</button>
        </div>
    <?php endif; ?>

    <div class="card wide">
        <h3>وضعیت</h3>
        <p>API: <strong><?= $enabled ? 'فعال' : 'غیرفعال' ?></strong> ·
           توکن: <strong><?= $hasToken ? 'صادر شده' . ($created !== '' ? ' (' . e($created) . ')' : '') : 'صادر نشده' ?></strong> ·
           دامنه‌ها: <strong><?= e(implode('، ', $scopes) === '' ? '—' : implode('، ', $scopes)) ?></strong></p>
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="api_save">
            <label class="check"><input type="checkbox" name="api_enabled" value="1" <?= $enabled ? 'checked' : '' ?>> API فعال باشد</label>
            <label class="check"><input type="checkbox" name="scope_read" value="1" <?= in_array('read', $scopes, true) ? 'checked' : '' ?>> خواندن (read)</label>
            <label class="check"><input type="checkbox" name="scope_write" value="1" <?= in_array('write', $scopes, true) ? 'checked' : '' ?>> نوشتن (write) — تغییر محتوا، محصول و وضعیت سفارش</label>
            <p><button type="submit" class="btn primary">ذخیره تنظیمات</button></p>
        </form>
        <form method="post" class="inline" onsubmit="return confirm('توکن تازه ساخته می‌شود و توکن قبلی از کار می‌افتد. ادامه می‌دهید؟')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="api_generate">
            <button type="submit" class="btn">ساخت توکن تازه</button>
        </form>
        <?php if ($hasToken): ?>
        <form method="post" class="inline" onsubmit="return confirm('توکن فعلی لغو می‌شود و همه اتصال‌های API قطع می‌شود. ادامه می‌دهید؟')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="api_revoke">
            <button type="submit" class="btn danger-btn">لغو توکن</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="card wide">
        <h3>راهنمای اتصال</h3>
        <p class="muted">توکن را در هدر <code dir="ltr">Authorization: Bearer &lt;token&gt;</code> یا هدر <code dir="ltr">X-API-Token</code> بفرستید. همه پاسخ‌ها JSON هستند. نوشتن (POST) به دامنه write نیاز دارد. محدودیت نرخ: ۱۲۰ درخواست در دقیقه.</p>
        <p><code dir="ltr" style="word-break:break-all"><?= e($apiUrl) ?>?res=pages</code></p>
        <pre dir="ltr" style="text-align:left;overflow:auto">curl -H "Authorization: Bearer &lt;token&gt;" "<?= e($apiUrl) ?>?res=pages"
curl -X POST -H "Authorization: Bearer &lt;token&gt;" \
  -H "Content-Type: application/json" \
  -d '{"key":"value"}' "<?= e($apiUrl) ?>?res=settings"</pre>
        <details>
            <summary>فهرست endpointها</summary>
            <ul>
                <li><code dir="ltr">GET ?res=ping</code> — بررسی اتصال</li>
                <li><code dir="ltr">GET ?res=pages</code> / <code dir="ltr">GET ?res=page&amp;id=1</code> یا <code dir="ltr">&amp;slug=about</code> — فهرست و متن صفحه‌ها</li>
                <li><code dir="ltr">POST ?res=page</code> — ویرایش صفحه (id + فیلدها) [write]</li>
                <li><code dir="ltr">GET ?res=sections</code> — بخش‌های صفحه اصلی</li>
                <li><code dir="ltr">POST ?res=section</code> — ویرایش بخش (id + title/is_active/sort_order) [write]</li>
                <li><code dir="ltr">GET ?res=settings</code> — تنظیمات محتوایی</li>
                <li><code dir="ltr">POST ?res=settings</code> — ویرایش تنظیمات محتوایی [write]</li>
                <li><code dir="ltr">GET ?res=products</code> / <code dir="ltr">GET ?res=product&amp;id=1</code> — محصولات</li>
                <li><code dir="ltr">POST ?res=product</code> — ساخت/ویرایش محصول [write]</li>
                <li><code dir="ltr">GET ?res=orders</code> / <code dir="ltr">GET ?res=order&amp;id=1</code> — سفارش‌ها</li>
                <li><code dir="ltr">POST ?res=order</code> — تغییر وضعیت سفارش (id + status) [write]</li>
            </ul>
        </details>
    </div>

    <div class="card wide">
        <h3>آخرین فعالیت‌های API</h3>
        <?php if ($logs === []): ?>
            <p class="muted">هنوز فعالیتی ثبت نشده است.</p>
        <?php else: ?>
            <div style="overflow-x:auto"><table>
                <thead><tr><th>زمان</th><th>IP</th><th>عملیات</th><th>جزئیات</th><th>نتیجه</th></tr></thead>
                <tbody>
                <?php foreach ($logs as $l): ?>
                    <tr><td dir="ltr"><?= e((string) $l['created_at']) ?></td><td dir="ltr"><?= e((string) $l['ip']) ?></td><td><?= e((string) $l['action']) ?></td><td><?= e((string) $l['detail']) ?></td><td><?= (int) $l['ok'] === 1 ? 'موفق' : 'ناموفق' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        <?php endif; ?>
    </div>
    <?php
}

$pdo = db();
$passwordHash = get_setting('admin_password_hash', '');

// ---------- خروج ----------
if (isset($_GET['logout'])) {
    log_admin_event('logout', 'خروج از پنل');
    logout_admin();
    redirect_admin();
}

// ---------- ساخت پسورد برای اولین بار ----------
if ($passwordHash === '') {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'setup') {
        check_csrf();
        $pw  = (string) ($_POST['password'] ?? '');
        $pw2 = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($pw) < 8) {
            $error = 'پسورد باید حداقل ۸ کاراکتر باشد.';
        } elseif ($pw !== $pw2) {
            $error = 'تکرار پسورد با پسورد یکسان نیست.';
        } else {
            set_setting('admin_password_hash', password_hash($pw, PASSWORD_DEFAULT));
            // کاربر مالک «admin» هم ساخته می‌شود تا ورود از این به بعد با نام کاربری باشد
            try {
                $pdo->prepare("INSERT INTO admin_users (username, pass_hash, display_name, role_key, is_active) VALUES ('admin', ?, 'مدیر', 'owner', 1)")
                    ->execute([password_hash($pw, PASSWORD_DEFAULT)]);
                $_SESSION['admin_user_id'] = (int) $pdo->lastInsertId();
                $_SESSION['admin_username'] = 'admin';
            } catch (Throwable $ignored) {
            }
            cms_regenerate_session_id();
            $_SESSION['admin_logged_in'] = true;
            log_admin_event('setup', 'ساخت پسورد اولیه و ورود');
            redirect_admin();
        }
    }
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>راه‌اندازی مدیریت</title>
<link rel="stylesheet" href="assets/bootstrap.rtl.min.css">
<script src="assets/chart.min.js"></script>
<style><?= admin_css() ?></style>
<?= admin_custom_style_css() ?>
</head>
<body>
<div class="auth-box">
    <h1>راه‌اندازی مدیریت</h1>
    <p class="muted">برای اولین بار وارد می‌شوید. یک پسورد برای پنل مدیریت بسازید.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="setup">
        <label>پسورد مدیریت
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>تکرار پسورد
            <input type="password" name="password_confirm" required autocomplete="new-password">
        </label>
        <button type="submit" class="btn primary block">ساخت پسورد و ورود</button>
    </form>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- فرم ورود ----------
if (!is_logged_in()) {
    $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
        check_csrf();
        $loginName = trim((string) ($_POST['username'] ?? ''));
        if ($loginName === '') {
            $loginName = 'admin';
        }
        $pw = (string) ($_POST['password'] ?? '');
        $loginUser = null;
        try {
            $st = $pdo->prepare('SELECT * FROM admin_users WHERE username = ? AND is_active = 1');
            $st->execute([$loginName]);
            $row = $st->fetch();
            if ($row && password_verify($pw, (string) $row['pass_hash'])) {
                $loginUser = $row;
            }
        } catch (Throwable $ignored) {
        }
        // مسیر جایگزین: فقط اگر جدول کاربران هنوز خالی است، پسورد قدیمی مدیریت
        // برای ساخت و ورود کاربر admin کار می‌کند (نباید راه ورود کاربر غیرفعال شود)
        if ($loginUser === null && $loginName === 'admin' && $passwordHash !== '' && password_verify($pw, $passwordHash)) {
            try {
                if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() === 0) {
                    $pdo->prepare("INSERT INTO admin_users (username, pass_hash, display_name, role_key, is_active) VALUES ('admin', ?, 'مدیر', 'owner', 1)")
                        ->execute([$passwordHash]);
                    $loginUser = ['id' => (int) $pdo->lastInsertId(), 'username' => 'admin', 'role_key' => 'owner'];
                }
            } catch (Throwable $ignored) {
            }
        }
        if ($loginUser !== null) {
            cms_regenerate_session_id();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user_id'] = (int) ($loginUser['id'] ?? 0);
            $_SESSION['admin_username'] = (string) ($loginUser['username'] ?? 'admin');
            if ((int) ($loginUser['id'] ?? 0) > 0) {
                try {
                    $pdo->prepare("UPDATE admin_users SET last_login_at = datetime('now') WHERE id = ?")
                        ->execute([(int) $loginUser['id']]);
                } catch (Throwable $ignored) {
                }
            }
            log_admin_event('login', 'ورود موفق به پنل (' . (string) ($loginUser['username'] ?? '') . ')');
            redirect_admin();
        }
        log_admin_event('login', 'تلاش ناموفق برای ورود (' . $loginName . ')', false);
        $error = 'نام کاربری یا پسورد اشتباه است.';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // فرمی از نشستی تمام‌شده رسیده است (مثلاً صفحه از قبل باز بوده و نشست
        // منقضی شده)؛ فرم اجرا نمی‌شود و کاربر با یک پیام روشن به ورود برمی‌گردد.
        $_SESSION['flash'] = ['type' => 'error', 'message' => 'نشستت منقضی شده بود، برای همین فرمت ثبت نشد. دوباره وارد شو و تلاش کن.'];
        header('Location: admin.php');
        exit;
    }
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-admin-theme="<?= e((string) get_setting('admin_theme', 'light')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ورود مدیریت</title>
<link rel="stylesheet" href="assets/bootstrap.rtl.min.css">
<style><?= admin_css() ?></style>
</head>
<body>
<div class="auth-box">
    <h1>ورود مدیریت</h1>
    <?php $authFlash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); ?>
    <?php if ($authFlash): ?><div class="alert <?= e((string) ($authFlash['type'] ?? 'error')) ?>"><?= e((string) ($authFlash['message'] ?? '')) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="login">
        <label>نام کاربری
            <input type="text" name="username" dir="ltr" autocomplete="username" placeholder="admin" autofocus>
        </label>
        <label>پسورد
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn primary block">ورود</button>
    </form>
    <p class="muted center"><a href="index.php">مشاهده سایت</a></p>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- از اینجا به بعد فقط ادمین واردشده ----------

$page  = (string) ($_GET['page'] ?? 'dashboard');
// نام قدیمی صفحه «قالب‌ها» به صفحه جدید «قالب و استایل» نگاشت می‌شود (قالب‌ها دیگر فایلی نیستند)
if ($page === 'templates') {
    $page = 'design';
}

// ---------- گارد سطح دسترسی (نسخه ۸٫۷٫۰) ----------
// نشست هست ولی کاربرش حذف/غیرفعال شده → خروج تمیز به صفحهٔ ورود
if (current_admin_user() === null) {
    logout_admin();
    redirect_admin();
}
// صفحه‌ای بیرون از نقش کاربر → برگشت به اولین صفحهٔ مجاز او
if (!admin_can_page($page)) {
    $firstAllowed = admin_first_allowed_page();
    if ($firstAllowed === $page) {
        // هیچ صفحه‌ای برای این نقش باز نیست؛ نشست بسته می‌شود تا حلقهٔ ریدایرکت ساخته نشود
        logout_admin();
        redirect_admin();
    }
    flash('error', 'به این بخش دسترسی نداری. اگر لازمش داری، از مدیر کل بخواه دسترسی نقش تو را از صفحهٔ «کاربران و نقش‌ها» تنظیم کند.');
    redirect_admin('admin.php?page=' . $firstAllowed);
}

// عنوان صفحه‌ها
$pageTitles = [
    'dashboard'  => 'داشبورد',
    'sections'   => 'بخش‌های صفحه اصلی',
    'pages'      => 'صفحه‌ها',
    'gallery'    => 'مدیریت گالری',
    'design'     => 'قالب و استایل',
    'messages'   => 'پیام‌های تماس',
    'partners'   => 'درخواست‌های همکاری',
    'customers'  => 'مشتری‌ها',
    'categories' => 'دسته‌بندی‌های محصولات',
    'products'   => 'محصولات',
    'attributes' => 'ویژگی‌های محصول',
    'materials'  => 'مواد اولیه و انبار',
    'material_prices' => 'لیست قیمت مواد اولیه',
    'stock'      => 'گردش انبار',
    'orders'     => 'سفارش‌ها',
    'order_new'  => 'سفارش تازه',
    'order_view' => 'جزئیات سفارش',
    'order_rules' => 'قوانین قیمت‌گذاری',
    'remnants'   => 'انبار پرتی',
    'production' => 'تولید',
    'production_view' => 'برگه تولید',
    'production_rules' => 'مراحل و قوانین تولید',
    'finance'    => 'داشبورد مالی',
    'invoices'   => 'فاکتورها',
    'invoice_view' => 'فاکتور',
    'expenses'   => 'هزینه‌ها',
    'statements' => 'صورتحساب مشتریان',
    'finance_rules' => 'قوانین مالی',
    'reports'    => 'گزارش‌ها',
    'tools'      => 'ابزار و بکاپ',
    'database'   => 'اتصال دیتابیس',
    'update'     => 'آپدیت سیستم',
    'cleaner'    => 'تمیزکننده هاست',
    'sysinfo'    => 'مشخصات نرم‌افزار',
    'employees'  => 'پرسنل',
    'payroll'    => 'حقوق و دستمزد',
    'blog'       => 'وبلاگ',
    'employee_profile' => 'پروفایل پرسنل',
    'payslip'    => 'فیش حقوقی',
    'assets'     => 'تجهیزات و دارایی‌ها',
    'assets_maintenance' => 'سوابق تعمیرات',
    'logs'       => 'لاگ‌ها',
    'notifications' => 'اعلان‌ها',
    'proposal' => 'پروپوزال سرمایه‌گذاری',
    'api'        => 'دسترسی API',
    'users'      => 'کاربران و نقش‌ها',
    'settings'   => 'تنظیمات سایت',
    'sitemap'    => 'نقشه سایت (Sitemap)',
];
$currentPageTitle = $pageTitles[$page] ?? 'پنل مدیریت';

// ساختار منوی کناری: کلید گروه => [برچسب، [ [نشانی، آیکون، برچسب، کلید صفحه، ویژگی اضافه] ]]
$navGroups = [
    'main' => ['اصلی', [
        ['admin.php?page=dashboard', 'dashboard', 'داشبورد', 'dashboard'],
    ]],
    'content' => ['محتوا', [
        ['admin.php?page=pages', 'file', 'صفحه‌ها', 'pages'],
        ['admin.php?page=blog', 'file', 'مقالات', 'blog'],
        ['admin.php?page=gallery', 'image', 'مدیریت گالری', 'gallery'],
        ['admin.php?page=sections', 'layout', 'بخش‌های صفحه اصلی', 'sections'],
        ['admin.php?page=design', 'droplet', 'قالب و استایل', 'design'],
        ['admin.php?page=templates', 'templates', 'قالب‌های نمایشی', 'templates'],
    ]],
    'customers' => ['مشتریان', [
        ['admin.php?page=customers', 'users', 'مشتری‌ها', 'customers'],
        ['admin.php?page=partners', 'handshake', 'درخواست‌های همکاری', 'partners'],
        ['admin.php?page=messages', 'mail', 'پیام‌های تماس', 'messages'],
    ]],
    'catalog' => ['محصولات', [
        ['admin.php?page=categories', 'folder', 'دسته‌بندی‌ها', 'categories'],
        ['admin.php?page=products', 'bag', 'محصولات', 'products'],
        ['admin.php?page=attributes', 'list', 'ویژگی‌های محصول', 'attributes'],
    ]],
    'inventory' => ['انبار', [
        ['admin.php?page=materials', 'box', 'مواد اولیه', 'materials'],
        ['admin.php?page=material_prices', 'list', 'لیست قیمت مواد', 'material_prices'],
        ['admin.php?page=stock', 'swap', 'گردش انبار', 'stock'],
        ['admin.php?page=remnants', 'cut', 'انبار پرتی', 'remnants'],
    ]],
    'orders' => ['سفارش‌ها', [
        ['admin.php?page=orders', 'receipt', 'سفارش‌ها', 'orders'],
        ['admin.php?page=order_new', 'plus', 'سفارش تازه', 'order_new'],
    ]],
    'production' => ['تولید', [
        ['admin.php?page=production', 'cut', 'برگه‌های تولید', 'production'],
    ]],
    'finance' => ['مالی', [
        ['admin.php?page=finance', 'dashboard', 'داشبورد مالی', 'finance'],
        ['admin.php?page=invoices', 'receipt', 'فاکتورها', 'invoices'],
        ['admin.php?page=expenses', 'bag', 'هزینه‌ها', 'expenses'],
        ['admin.php?page=statements', 'users', 'صورتحساب مشتریان', 'statements'],
    ]],
    'reports' => ['گزارش‌ها', [
        ['admin.php?page=reports', 'chart', 'گزارش‌ها', 'reports'],
    ]],
    'settings' => ['تنظیمات', [
        ['admin.php?page=settings', 'sliders', 'تنظیمات سایت', 'settings'],
        ['admin.php?page=order_rules', 'sliders', 'قوانین قیمت‌گذاری', 'order_rules'],
        ['admin.php?page=order_forms', 'layout', 'فرم‌های سفارش', 'order_forms'],
        ['admin.php?page=production_rules', 'sliders', 'مراحل تولید', 'production_rules'],
        ['admin.php?page=finance_rules', 'sliders', 'قوانین مالی', 'finance_rules'],
        ['admin.php?page=sitemap', 'map', 'نقشه سایت', 'sitemap'],
    ]],
    'resources' => ['منابع', [
        ['admin.php?page=employees', 'users', 'پرسنل', 'employees'],
        ['admin.php?page=payroll', 'receipt', 'حقوق و دستمزد', 'payroll'],
        ['admin.php?page=assets', 'box', 'تجهیزات و دارایی‌ها', 'assets'],
        ['admin.php?page=assets_maintenance', 'list', 'سوابق تعمیرات', 'assets_maintenance'],
    ]],
    'system' => ['سیستم', [
        ['admin.php?page=sysinfo', 'info', 'مشخصات نرم‌افزار', 'sysinfo'],
        ['admin.php?page=update', 'refresh', 'آپدیت', 'update', ''],
        ['admin.php?page=cleaner', 'trash', 'تمیزکننده', 'cleaner'],
        ['admin.php?page=database', 'database', 'اتصال دیتابیس', 'database'],
        ['admin.php?page=tools', 'archive', 'ابزار و بکاپ', 'tools'],
        ['admin.php?page=api', 'key', 'دسترسی API', 'api'],
        ['admin.php?page=users', 'users', 'کاربران و نقش‌ها', 'users'],
        ['admin.php?page=modules', 'modules', 'مدیریت ماژول‌ها', 'modules'],
        ['admin.php?page=logs', 'list', 'لاگ‌ها', 'logs'],
        ['admin.php?page=notifications', 'bell', 'اعلان‌ها', 'notifications'],
        ['admin.php?page=proposal', 'chart', 'پروپوزال سرمایه‌گذاری', 'proposal'],
    ]],
];
$activeNavGroup = 'main';
foreach ($navGroups as $gKey => $gData) {
    foreach ($gData[1] as $gItem) {
        if ($gItem[3] !== '' && $gItem[3] === $page) {
            $activeNavGroup = $gKey;
            break 2;
        }
    }
}
$error = '';

// پردازش فرم‌ها (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string) ($_POST['action'] ?? '');

    // گارد اکشن (نسخه ۸٫۷٫۰): اکشن هر صفحه فقط برای نقش‌هایی که آن صفحه را دارند اجرا می‌شود
    if ($action !== '' && !admin_can_action($action)) {
        log_admin_event($action, 'تلاش برای اجرای اکشن بدون دسترسی', false, $page);
        flash('error', 'اجازهٔ انجام این کار را نداری.');
        header('Location: admin.php?page=' . $page);
        exit;
    }

    // ---------- مدیریت توکن API ----------
    if ($action === 'api_save') {
        set_setting('api_enabled', isset($_POST['api_enabled']) ? '1' : '0');
        $sc = [];
        if (isset($_POST['scope_read'])) {
            $sc[] = 'read';
        }
        if (isset($_POST['scope_write'])) {
            $sc[] = 'write';
        }
        if ($sc === []) {
            $sc[] = 'read';
        }
        set_setting('api_scopes', implode(',', $sc));
        flash('ok', 'تنظیمات API ذخیره شد.');
        redirect_admin('admin.php?page=api');
    }
    if ($action === 'api_generate') {
        $token = bin2hex(random_bytes(32));
        set_setting('api_token_hash', hash('sha256', $token));
        set_setting('api_token_created', date('Y-m-d H:i:s'));
        set_setting('api_enabled', '1');
        $_SESSION['api_new_token'] = $token; // فقط همین یک بار نمایش داده می‌شود
        flash('ok', 'توکن تازه ساخته شد و API فعال شد. آن را همین حالا کپی کنید؛ دیگر نمایش داده نمی‌شود.');
        redirect_admin('admin.php?page=api');
    }
    if ($action === 'api_revoke') {
        set_setting('api_token_hash', '');
        set_setting('api_token_created', '');
        flash('ok', 'توکن API لغو شد. توکن قبلی دیگر کار نمی‌کند.');
        redirect_admin('admin.php?page=api');
    }

    // دانلود بکاپ دیتابیس (فقط ادمین واردشده، با CSRF) — خروجی فایل است و همین‌جا تمام می‌شود
    if ($action === 'optimize_images') {
        $optResult = tools_optimize_images();
        if ($optResult['ok']) {
            flash('success', 'بهینه‌سازی انجام شد: ' . $optResult['done'] . ' عکس (' . $optResult['saved'] . ')');
            log_admin_event('optimize_images', 'بهینه‌سازی ' . $optResult['done'] . ' عکس');
        } else {
            flash('error', $optResult['error']);
        }
        redirect_admin('admin.php?page=tools');
    }
    if ($action === 'download_backup') {
        $tmp = __DIR__ . '/backup-' . date('YmdHis') . '.sqlite';
        if (!@copy(DB_FILE, $tmp)) {
            $error = 'ساخت نسخه پشتیبان انجام نشد.';
        } else {
            log_admin_event('download_backup', 'دانلود نسخه پشتیبان دیتابیس');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="site-backup-' . date('Ymd-His') . '.sqlite"');
            header('Content-Length: ' . filesize($tmp));
            readfile($tmp);
            @unlink($tmp);
            exit;
        }
    }

    try {
        // اکشن‌های فاز ۲ (مشتری‌ها و کاتالوگ) در admin_catalog.php پردازش می‌شوند
        if (in_array($action, catalog_post_actions(), true)) {
            catalog_handle_post($action);
        }
        // اکشن‌های فاز ۲٫۵ (مواد اولیه و انبار) در admin_inventory.php پردازش می‌شوند
        if (in_array($action, inventory_post_actions(), true)) {
            inventory_handle_post($action);
        }
        // اکشن‌های فاز ۳ (سفارش‌ها) در admin_orders.php پردازش می‌شوند
        if (in_array($action, orders_post_actions(), true)) {
            orders_handle_post($action);
        }
        // اکشن‌های فاز ۴ (تولید) در admin_production.php پردازش می‌شوند
        if (in_array($action, production_post_actions(), true)) {
            production_handle_post($action);
        }
        // اکشن‌های فاز ۵ (مالی) در admin_finance.php پردازش می‌شوند
        if (in_array($action, finance_post_actions(), true)) {
            finance_handle_post($action);
        }
        // اکشن‌های منابع انسانی در admin_hr.php پردازش می‌شوند
        if (in_array($action, hr_post_actions(), true)) {
            hr_handle_post($action);
        }
        // اکشن‌های وبلاگ در admin_blog.php پردازش می‌شوند
        if (in_array($action, blog_post_actions(), true)) {
            blog_handle_post($action);
        }
        // اکشن‌های تجهیزات در admin_assets.php پردازش می‌شوند
        if (in_array($action, assets_post_actions(), true)) {
            assets_handle_post($action);
        }
        // اکشن‌های فاز ۶ (گزارش‌ها) در admin_reports.php پردازش می‌شوند
        if (in_array($action, reports_post_actions(), true)) {
            reports_handle_post($action);
        }
        if (in_array($action, users_post_actions(), true)) {
            users_handle_post($action);
        }
        // اکشن‌های لاگ‌ها و نشست (نسخه ۸٫۲) در admin_logs.php پردازش می‌شوند
        if (in_array($action, logs_post_actions(), true)) {
            logs_handle_post($action);
        }
        if (in_array($action, notifications_post_actions(), true)) {
            notifications_handle_post($action);
        }
        // شخصی‌سازی داشبورد (نسخه ۸٫۵٫۰): ترتیب و فعال‌بودن ویجت‌ها
        if ($action === 'save_dashboard') {
            $order = (array) ($_POST['w'] ?? []);
            $onFlags = (array) ($_POST['on'] ?? []);
            $enabled = [];
            foreach ($order as $k) {
                $k = (string) $k;
                if ($k !== '' && !empty($onFlags[$k]) && !in_array($k, $enabled, true)) {
                    $enabled[] = $k;
                }
            }
            set_setting('dash_widgets', json_encode($enabled, JSON_UNESCAPED_UNICODE));
            log_admin_event('dashboard', 'شخصی‌سازی داشبورد (' . count($enabled) . ' ویجت)');
            flash('ok', 'چیدمان داشبورد ذخیره شد.');
            redirect_admin('admin.php?page=dashboard');
        }
        switch ($action) {
            case 'add_section':
            case 'update_section':
                $title = trim((string) ($_POST['title'] ?? ''));
                $file  = basename((string) ($_POST['template_file'] ?? ''));
                $order = (int) ($_POST['sort_order'] ?? 0);
                $active = isset($_POST['is_active']) ? 1 : 0;
                $heading  = trim((string) ($_POST['heading'] ?? ''));
                $body     = (string) ($_POST['body'] ?? '');
                $linkUrl  = trim((string) ($_POST['link_url'] ?? ''));
                $linkText = trim((string) ($_POST['link_text'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('عنوان بخش را وارد کنید.');
                }
                if (!in_array($file, available_templates(), true)) {
                    throw new RuntimeException('قالب انتخاب‌شده معتبر نیست.');
                }
                $id = (int) ($_POST['id'] ?? 0);
                $oldImage = null;
                if ($action === 'update_section' && $id > 0) {
                    $st = $pdo->prepare('SELECT image FROM sections WHERE id = :id');
                    $st->execute([':id' => $id]);
                    $oldImage = ($r = $st->fetch()) ? ($r['image'] ?? null) : null;
                }
                // حذف عکس فعلی؟
                if (isset($_POST['remove_image']) && $oldImage) {
                    $oldPath = UPLOADS_DIR . '/' . basename((string) $oldImage);
                    if (is_file($oldPath)) { @unlink($oldPath); }
                    $oldImage = null;
                }
                $up = handle_section_image_upload($_FILES['image'] ?? null, $oldImage ? (string) $oldImage : null);
                if (!$up['ok']) {
                    throw new RuntimeException((string) $up['error']);
                }
                $image = $up['filename'];
                if ($action === 'add_section') {
                    $stmt = $pdo->prepare('INSERT INTO sections (title, template_file, sort_order, is_active, heading, body, image, link_url, link_text) VALUES (:t,:f,:o,:a,:h,:b,:im,:lu,:lt)');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active, ':h' => $heading, ':b' => $body, ':im' => $image, ':lu' => $linkUrl, ':lt' => $linkText]);
                    flash('ok', 'بخش جدید ساخته شد.');
                } else {
                    $stmt = $pdo->prepare('UPDATE sections SET title=:t, template_file=:f, sort_order=:o, is_active=:a, heading=:h, body=:b, image=:im, link_url=:lu, link_text=:lt WHERE id=:id');
                    $stmt->execute([':t' => $title, ':f' => $file, ':o' => $order, ':a' => $active, ':h' => $heading, ':b' => $body, ':im' => $image, ':lu' => $linkUrl, ':lt' => $linkText, ':id' => $id]);
                    flash('ok', 'بخش ویرایش شد.');
                }
                redirect_admin('admin.php?page=sections');
                // no break (redirect exits)

            case 'delete_section':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM sections WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'بخش حذف شد.');
                redirect_admin('admin.php?page=sections');
                // no break

            case 'toggle_section':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE sections SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
                redirect_admin('admin.php?page=sections');
                // no break

            case 'move_section':
                $id = (int) ($_POST['id'] ?? 0);
                $dir = (string) ($_POST['direction'] ?? '');
                move_row($pdo, 'sections', $id, $dir);
                redirect_admin('admin.php?page=sections');
                // no break

            case 'add_page':
            case 'update_page':
                $ptitle = trim((string) ($_POST['title'] ?? ''));
                $slug   = trim((string) ($_POST['slug'] ?? ''));
                $content = (string) ($_POST['content'] ?? '');
                $seoT   = trim((string) ($_POST['seo_title'] ?? ''));
                $seoD   = trim((string) ($_POST['seo_description'] ?? ''));
                $porder = (int) ($_POST['sort_order'] ?? 0);
                $pactive = isset($_POST['is_active']) ? 1 : 0;
                $inMenu  = isset($_POST['show_in_menu']) ? 1 : 0;
                $pid = (int) ($_POST['id'] ?? 0);
                if ($ptitle === '') {
                    throw new RuntimeException('عنوان صفحه را وارد کنید.');
                }
                if ($slug === '' || !is_valid_slug($slug)) {
                    throw new RuntimeException('نامک (slug) فقط می‌تواند حروف انگلیسی، عدد، خط تیره و آندرلاین باشد.');
                }
                // یکتایی نامک
                $chk = $pdo->prepare('SELECT id FROM pages WHERE slug = :s AND (:id = 0 OR id != :id)');
                $chk->execute([':s' => $slug, ':id' => $pid]);
                if ($chk->fetch()) {
                    throw new RuntimeException('صفحه‌ای با این نامک از قبل وجود دارد؛ نامک دیگری انتخاب کنید.');
                }
                if ($action === 'add_page') {
                    $stmt = $pdo->prepare('INSERT INTO pages (title, slug, content, seo_title, seo_description, is_active, sort_order, show_in_menu) VALUES (:t,:s,:c,:st,:sd,:a,:o,:m)');
                    $stmt->execute([':t' => $ptitle, ':s' => $slug, ':c' => $content, ':st' => $seoT, ':sd' => $seoD, ':a' => $pactive, ':o' => $porder, ':m' => $inMenu]);
                    flash('ok', 'صفحه جدید ساخته شد.');
                } else {
                    $stmt = $pdo->prepare('UPDATE pages SET title=:t, slug=:s, content=:c, seo_title=:st, seo_description=:sd, is_active=:a, sort_order=:o, show_in_menu=:m WHERE id=:id');
                    $stmt->execute([':t' => $ptitle, ':s' => $slug, ':c' => $content, ':st' => $seoT, ':sd' => $seoD, ':a' => $pactive, ':o' => $porder, ':m' => $inMenu, ':id' => $pid]);
                    flash('ok', 'صفحه ویرایش شد.');
                }
                redirect_admin('admin.php?page=pages');
                // no break

            case 'delete_page':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM pages WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'صفحه حذف شد.');
                redirect_admin('admin.php?page=pages');
                // no break

            case 'toggle_page':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE pages SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
                redirect_admin('admin.php?page=pages');
                // no break

            case 'move_page':
                $id = (int) ($_POST['id'] ?? 0);
                $dir = (string) ($_POST['direction'] ?? '');
                move_row($pdo, 'pages', $id, $dir);
                redirect_admin('admin.php?page=pages');
                // no break

            // --- نسخه ۹: مدیریت گالری ---
            case 'gallery_upload':
                $f = $_FILES['gallery_image'] ?? null;
                if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('فایل عکس را انتخاب کنید.');
                }
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    throw new RuntimeException('فقط فایل JPG، PNG یا WebP مجاز است.');
                }
                if (($f['size'] ?? 0) > 8 * 1024 * 1024) {
                    throw new RuntimeException('حجم فایل نباید بیشتر از ۸ مگابایت باشد.');
                }
                $imgInfo = @getimagesize((string) $f['tmp_name']);
                if ($imgInfo === false) {
                    throw new RuntimeException('فایل انتخاب‌شده عکس معتبر نیست.');
                }
                $dir = __DIR__ . '/uploads/gallery';
                if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                    throw new RuntimeException('پوشه آپلود ساخته نشد.');
                }
                $num = gallery_next_number($pdo);
                $filename = 'gallery-' . str_pad((string) $num, 2, '0', STR_PAD_LEFT) . '.jpg';
                $dest = $dir . '/' . $filename;
                // تبدیل به JPG برای یکدستی
                $saved = false;
                if ($ext === 'jpg' || $ext === 'jpeg') {
                    $saved = @move_uploaded_file((string) $f['tmp_name'], $dest);
                } else {
                    // تبدیل PNG/WebP به JPG با GD
                    $srcImg = $ext === 'png' ? @imagecreatefrompng((string) $f['tmp_name']) : @imagecreatefromwebp((string) $f['tmp_name']);
                    if ($srcImg !== false) {
                        $w = imagesx($srcImg); $h = imagesy($srcImg);
                        $dstImg = imagecreatetruecolor($w, $h);
                        $white = imagecolorallocate($dstImg, 255, 255, 255);
                        imagefilledrectangle($dstImg, 0, 0, $w, $h, $white);
                        imagecopy($dstImg, $srcImg, 0, 0, 0, 0, $w, $h);
                        $saved = @imagejpeg($dstImg, $dest, 85);
                        imagedestroy($srcImg); imagedestroy($dstImg);
                    }
                }
                if (!$saved) {
                    throw new RuntimeException('ذخیره فایل ناموفق بود.');
                }
                $caption = trim((string) ($_POST['caption'] ?? ''));
                $desc = trim((string) ($_POST['description'] ?? ''));
                $figs = gallery_get_figures($pdo);
                $figs[] = ['src' => 'uploads/gallery/' . $filename, 'alt' => $caption, 'caption' => $caption, 'desc' => $desc];
                gallery_save_figures($pdo, $figs);
                flash('ok', 'عکس به گالری اضافه شد.');
                redirect_admin('admin.php?page=gallery');
                // no break

            case 'gallery_update':
                $idx = (int) ($_POST['index'] ?? -1);
                $figs = gallery_get_figures($pdo);
                if (!isset($figs[$idx])) {
                    throw new RuntimeException('عکس پیدا نشد.');
                }
                $figs[$idx]['caption'] = trim((string) ($_POST['caption'] ?? ''));
                $figs[$idx]['alt'] = trim((string) ($_POST['caption'] ?? ''));
                $figs[$idx]['desc'] = trim((string) ($_POST['description'] ?? ''));
                gallery_save_figures($pdo, $figs);
                flash('ok', 'توضیحات عکس به‌روزرسانی شد.');
                redirect_admin('admin.php?page=gallery');
                // no break

            case 'gallery_delete':
                $idx = (int) ($_POST['index'] ?? -1);
                $deleteFile = !empty($_POST['delete_file']);
                $figs = gallery_get_figures($pdo);
                if (!isset($figs[$idx])) {
                    throw new RuntimeException('عکس پیدا نشد.');
                }
                $src = (string) ($figs[$idx]['src'] ?? '');
                array_splice($figs, $idx, 1);
                gallery_save_figures($pdo, $figs);
                if ($deleteFile && $src !== '' && strpos($src, 'uploads/gallery/') === 0) {
                    $path = __DIR__ . '/' . $src;
                    if (is_file($path)) { @unlink($path); }
                }
                flash('ok', 'عکس از گالری حذف شد.');
                redirect_admin('admin.php?page=gallery');
                // no break

            case 'gallery_move':
                $idx = (int) ($_POST['index'] ?? -1);
                $dir = (string) ($_POST['direction'] ?? '');
                $figs = gallery_get_figures($pdo);
                if (!isset($figs[$idx])) {
                    throw new RuntimeException('عکس پیدا نشد.');
                }
                $swap = $dir === 'up' ? $idx - 1 : $idx + 1;
                if (isset($figs[$swap])) {
                    $tmp = $figs[$idx]; $figs[$idx] = $figs[$swap]; $figs[$swap] = $tmp;
                    gallery_save_figures($pdo, $figs);
                }
                redirect_admin('admin.php?page=gallery');
                // no break

            // --- نسخه ۹: بلوک‌های صفحه‌ساز ---
            case 'add_block':
            case 'update_block':
                $blockId = (int) ($_POST['block_id'] ?? 0);
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $btype = (string) ($_POST['block_type'] ?? 'text');
                $btitle = trim((string) ($_POST['block_title'] ?? ''));
                $bcontent = (string) ($_POST['block_content'] ?? '');
                $bsettings = [];
                foreach (['alt', 'caption', 'btn_text', 'btn_url'] as $sk) {
                    $v = trim((string) ($_POST['setting_' . $sk] ?? ''));
                    if ($v !== '') { $bsettings[$sk] = $v; }
                }
                $bactive = !empty($_POST['is_active']) ? 1 : 0;
                if (!array_key_exists($btype, page_block_types())) {
                    throw new RuntimeException('نوع بلوک نامعتبر است.');
                }
                if ($action === 'add_block') {
                    $maxOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM page_blocks WHERE page_id = ' . $pageId)->fetchColumn();
                    $pdo->prepare('INSERT INTO page_blocks (page_id, block_type, title, content, settings, sort_order, is_active) VALUES (:p, :t, :ti, :c, :s, :o, :a)')->execute([
                        ':p' => $pageId, ':t' => $btype, ':ti' => $btitle, ':c' => $bcontent,
                        ':s' => json_encode($bsettings, JSON_UNESCAPED_UNICODE), ':o' => $maxOrder + 10, ':a' => $bactive,
                    ]);
                    flash('ok', 'بلوک جدید اضافه شد.');
                } else {
                    $pdo->prepare('UPDATE page_blocks SET block_type = :t, title = :ti, content = :c, settings = :s, is_active = :a WHERE id = :id')->execute([
                        ':t' => $btype, ':ti' => $btitle, ':c' => $bcontent,
                        ':s' => json_encode($bsettings, JSON_UNESCAPED_UNICODE), ':a' => $bactive, ':id' => $blockId,
                    ]);
                    flash('ok', 'بلوک به‌روزرسانی شد.');
                }
                redirect_admin('admin.php?page=pages&edit_id=' . $pageId . '#blocks');
                // no break

            case 'delete_block':
                $blockId = (int) ($_POST['block_id'] ?? 0);
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $pdo->prepare('DELETE FROM page_blocks WHERE id = :id')->execute([':id' => $blockId]);
                flash('ok', 'بلوک حذف شد.');
                redirect_admin('admin.php?page=pages&edit_id=' . $pageId . '#blocks');
                // no break

            case 'move_block':
                $blockId = (int) ($_POST['block_id'] ?? 0);
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $dir = (string) ($_POST['direction'] ?? '');
                move_row($pdo, 'page_blocks', $blockId, $dir);
                redirect_admin('admin.php?page=pages&edit_id=' . $pageId . '#blocks');
                // no break

            case 'toggle_block':
                $blockId = (int) ($_POST['block_id'] ?? 0);
                $pageId = (int) ($_POST['page_id'] ?? 0);
                $pdo->prepare('UPDATE page_blocks SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $blockId]);
                redirect_admin('admin.php?page=pages&edit_id=' . $pageId . '#blocks');
                // no break

            case 'delete_message':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM contact_messages WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'پیام حذف شد.');
                redirect_admin('admin.php?page=messages');
                // no break

            case 'add_order_form_field':
                $otype = (string) ($_POST['owner_type'] ?? 'global');
                if (!in_array($otype, ['global', 'category'], true)) { $otype = 'global'; }
                $oid = $otype === 'category' ? (int) ($_POST['owner_id'] ?? 0) : 0;
                $flabel = trim((string) ($_POST['field_label'] ?? ''));
                $ftype = (string) ($_POST['field_type'] ?? 'text');
                if (!in_array($ftype, ['text', 'number', 'select', 'textarea', 'checkbox'], true)) { $ftype = 'text'; }
                if ($flabel !== '') {
                    $fopts = trim((string) ($_POST['field_options'] ?? ''));
                    $optsArr = [];
                    if ($fopts !== '') {
                        foreach (explode('|', $fopts) as $op) {
                            $op = trim($op);
                            if ($op !== '') { $optsArr[] = $op; }
                        }
                    }
                    $maxSort = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM order_form_fields WHERE owner_type = '" . $otype . "' AND owner_id = " . $oid)->fetchColumn();
                    $pdo->prepare("INSERT INTO order_form_fields (owner_type, owner_id, field_type, label, options_json, placeholder, help_text, is_required, sort_order) VALUES (:t, :o, :ft, :l, :oj, :ph, :ht, :r, :s)")
                        ->execute([
                            ':t' => $otype, ':o' => $oid, ':ft' => $ftype, ':l' => $flabel,
                            ':oj' => $optsArr !== [] ? json_encode($optsArr, JSON_UNESCAPED_UNICODE) : null,
                            ':ph' => trim((string) ($_POST['field_placeholder'] ?? '')),
                            ':ht' => trim((string) ($_POST['field_help'] ?? '')),
                            ':r' => !empty($_POST['field_required']) ? 1 : 0,
                            ':s' => $maxSort + 10,
                        ]);
                    flash('ok', 'فیلد اضافه شد.');
                } else {
                    flash('error', 'برچسب فیلد را وارد کنید.');
                }
                redirect_admin('admin.php?page=order_forms');
                // no break

            case 'delete_order_form_field':
                $fid = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM order_form_fields WHERE id = :id')->execute([':id' => $fid]);
                flash('ok', 'فیلد حذف شد.');
                redirect_admin('admin.php?page=order_forms');
                // no break

            case 'toggle_order_form_field':
                $fid = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('UPDATE order_form_fields SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $fid]);
                redirect_admin('admin.php?page=order_forms');
                // no break

            // --- مرورگر دیتابیس (۹٫۱۹): فقط مدیر کل ---
            case 'db_cell_update':
                if ((string) (current_admin_user()['role_key'] ?? '') !== 'owner') { flash('error', 'فقط مدیر کل.'); redirect_admin('admin.php?page=database'); }
                $tbl = trim((string) ($_POST['table'] ?? ''));
                $col = trim((string) ($_POST['col'] ?? ''));
                $rowid = (int) ($_POST['rowid'] ?? 0);
                $val = (string) ($_POST['val'] ?? '');
                // اعتبارسنجی
                $valid = false;
                try {
                    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array($tbl, $tables, true)) {
                        $cols = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $tbl) . '")')->fetchAll(PDO::FETCH_COLUMN, 1);
                        $valid = in_array($col, $cols, true) && $rowid > 0;
                    }
                } catch (Throwable $e) {}
                if ($valid) {
                    $pdo->prepare('UPDATE "' . str_replace('"', '""', $tbl) . '" SET "' . str_replace('"', '""', $col) . '" = :v WHERE rowid = :r')
                        ->execute([':v' => $val, ':r' => $rowid]);
                    flash('ok', 'ذخیره شد.');
                } else {
                    flash('error', 'نامعتبر.');
                }
                redirect_admin('admin.php?page=db_browse&table=' . urlencode($tbl) . '&p=' . (int) ($_POST['p'] ?? 1));
                // no break

            case 'db_row_delete':
                if ((string) (current_admin_user()['role_key'] ?? '') !== 'owner') { flash('error', 'فقط مدیر کل.'); redirect_admin('admin.php?page=database'); }
                $tbl = trim((string) ($_POST['table'] ?? ''));
                $rowid = (int) ($_POST['rowid'] ?? 0);
                try {
                    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array($tbl, $tables, true) && $rowid > 0) {
                        $pdo->prepare('DELETE FROM "' . str_replace('"', '""', $tbl) . '" WHERE rowid = :r')->execute([':r' => $rowid]);
                        flash('ok', 'ردیف حذف شد.');
                    }
                } catch (Throwable $e) { flash('error', 'خطا در حذف.'); }
                redirect_admin('admin.php?page=db_browse&table=' . urlencode($tbl));
                // no break

            case 'edit_order_form_field':
                $fid = (int) ($_POST['id'] ?? 0);
                $flabel = trim((string) ($_POST['field_label'] ?? ''));
                $ftype = (string) ($_POST['field_type'] ?? 'text');
                if (!in_array($ftype, ['text', 'number', 'select', 'textarea', 'checkbox'], true)) { $ftype = 'text'; }
                if ($fid > 0 && $flabel !== '') {
                    $fopts = trim((string) ($_POST['field_options'] ?? ''));
                    $optsArr = [];
                    if ($fopts !== '') {
                        foreach (explode('|', $fopts) as $op) {
                            $op = trim($op);
                            if ($op !== '') { $optsArr[] = $op; }
                        }
                    }
                    $pdo->prepare("UPDATE order_form_fields SET field_type = :ft, label = :l, options_json = :oj, placeholder = :ph, help_text = :ht, is_required = :r WHERE id = :id")
                        ->execute([
                            ':ft' => $ftype, ':l' => $flabel,
                            ':oj' => $optsArr !== [] ? json_encode($optsArr, JSON_UNESCAPED_UNICODE) : null,
                            ':ph' => trim((string) ($_POST['field_placeholder'] ?? '')),
                            ':ht' => trim((string) ($_POST['field_help'] ?? '')),
                            ':r' => !empty($_POST['field_required']) ? 1 : 0,
                            ':id' => $fid,
                        ]);
                    flash('ok', 'فیلد ویرایش شد.');
                } else {
                    flash('error', 'برچسب فیلد را وارد کنید.');
                }
                redirect_admin('admin.php?page=order_forms');
                // no break

            case 'delete_partner_request':
                $id = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM partner_requests WHERE id = :id')->execute([':id' => $id]);
                flash('ok', 'درخواست حذف شد.');
                redirect_admin('admin.php?page=partners');
                // no break

            case 'partner_request_status':
                $id = (int) ($_POST['id'] ?? 0);
                $status = (string) ($_POST['status'] ?? 'new');
                if (!in_array($status, ['new', 'contacted', 'approved', 'rejected'], true)) {
                    $status = 'new';
                }
                $pdo->prepare('UPDATE partner_requests SET status = :s WHERE id = :id')->execute([':s' => $status, ':id' => $id]);
                flash('ok', 'وضعیت درخواست به‌روز شد.');
                redirect_admin('admin.php?page=partners');
                // no break

            case 'restore_backup':
                $f = $_FILES['backup_file'] ?? null;
                if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('فایل بکاپ را انتخاب کنید.');
                }
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['sqlite', 'sqlite3', 'db'], true)) {
                    throw new RuntimeException('فقط فایل بکاپ با پسوند sqlite یا db مجاز است.');
                }
                if ((int) ($f['size'] ?? 0) > 20 * 1024 * 1024) {
                    throw new RuntimeException('حجم فایل بکاپ بیش از حد مجاز است.');
                }
                $tmpRestore = __DIR__ . '/restore-' . date('YmdHis') . '.sqlite';
                if (!move_uploaded_file((string) $f['tmp_name'], $tmpRestore)) {
                    throw new RuntimeException('ذخیره فایل بکاپ آپلودی انجام نشد.');
                }
                // اعتبارسنجی: باید دیتابیس SQLite سالم و دارای جدول settings باشد
                $valid = false;
                try {
                    $test = new PDO('sqlite:' . $tmpRestore, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $q = $test->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'");
                    $valid = $q !== false && $q->fetch() !== false;
                    $test = null;
                } catch (Throwable $ignored) {
                    $valid = false;
                }
                if (!$valid) {
                    @unlink($tmpRestore);
                    throw new RuntimeException('فایل انتخاب‌شده دیتابیس SQLite معتبرِ این سیستم (دارای جدول تنظیمات) نیست؛ بازیابی انجام نشد.');
                }
                // نسخه امن از دیتابیس فعلی نگه داشته می‌شود
                @copy(DB_FILE, __DIR__ . '/database-backup-before-restore.sqlite');
                if (!@copy($tmpRestore, DB_FILE)) {
                    @unlink($tmpRestore);
                    throw new RuntimeException('جایگزینی دیتابیس انجام نشد؛ مجوز نوشتن فایل دیتابیس را بررسی کنید.');
                }
                @unlink($tmpRestore);
                // بعد از بازیابی، خروج اجباری تا با دیتابیس جدید وارد شوید
                logout_admin();
                // سشن تازه برای پیام
                cms_session_start();
                flash('ok', 'بکاپ بازیابی شد. نسخه قبلی دیتابیس با نام database-backup-before-restore.sqlite نگه داشته شد. دوباره وارد شوید.');
                redirect_admin();
                // no break

            case 'switch_database_file':
                $selected = basename((string) ($_POST['database_file'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9._-]+\.(sqlite|sqlite3|db)$/i', $selected)) {
                    throw new RuntimeException('نام فایل دیتابیس معتبر نیست.');
                }
                $selectedPath = __DIR__ . '/' . $selected;
                if (!is_file($selectedPath)) {
                    throw new RuntimeException('فایل دیتابیس انتخاب‌شده پیدا نشد.');
                }
                if (is_file(DB_FILE) && realpath($selectedPath) === realpath(DB_FILE)) {
                    throw new RuntimeException('این فایل همین حالا دیتابیس فعال است.');
                }
                $switch = activate_database_file($selectedPath);
                if (!$switch['ok']) {
                    throw new RuntimeException((string) $switch['error']);
                }
                logout_admin();
                cms_session_start();
                flash('ok', 'دیتابیس تغییر کرد. نسخه قبلی با نام database-backup-before-switch.sqlite نگه داشته شد. با پسورد دیتابیس جدید دوباره وارد شوید.');
                redirect_admin();
                // no break

            case 'upload_database_file':
                $f = $_FILES['database_file'] ?? null;
                if ($f === null || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('فایل دیتابیس را انتخاب کنید.');
                }
                $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['sqlite', 'db'], true)) {
                    throw new RuntimeException('فقط فایل دیتابیس با پسوند sqlite یا db مجاز است.');
                }
                if ((int) ($f['size'] ?? 0) <= 0) {
                    throw new RuntimeException('فایل دیتابیس خالی است.');
                }
                if ((int) ($f['size'] ?? 0) > 20 * 1024 * 1024) {
                    throw new RuntimeException('حجم فایل دیتابیس بیش از ۲۰ مگابایت است.');
                }
                $tmpUpload = __DIR__ . '/database-upload-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.sqlite';
                if (!move_uploaded_file((string) $f['tmp_name'], $tmpUpload)) {
                    throw new RuntimeException('ذخیره فایل دیتابیس آپلودی انجام نشد.');
                }
                $switch = activate_database_file($tmpUpload);
                @unlink($tmpUpload);
                if (!$switch['ok']) {
                    throw new RuntimeException((string) $switch['error']);
                }
                logout_admin();
                cms_session_start();
                flash('ok', 'دیتابیس آپلودی فعال شد. نسخه قبلی با نام database-backup-before-switch.sqlite نگه داشته شد. با پسورد دیتابیس جدید دوباره وارد شوید.');
                redirect_admin();
                // no break

            case 'save_db_template':
                $key = trim((string) ($_POST['template_key'] ?? ''));
                if (get_template_row($key) === null) {
                    throw new RuntimeException('قالب پیدا نشد.');
                }
                save_template_content($key, (string) ($_POST['content'] ?? ''));
                flash('ok', 'قالب «' . $key . '» ذخیره شد. نسخه قبلی در تاریخچه نسخه‌ها نگه داشته شد و هر وقت خواستید برمی‌گردد.');
                redirect_admin('admin.php?page=design&tab=templates&edit_tpl=' . urlencode($key));
                // no break

            case 'reset_db_template':
                $key = trim((string) ($_POST['template_key'] ?? ''));
                if (!reset_template_to_factory($key)) {
                    throw new RuntimeException('بازنشانی انجام نشد؛ قالب پیدا نشد.');
                }
                flash('ok', 'قالب «' . $key . '» به نسخه کارخانه‌ای برگشت. نسخه قبلی در تاریخچه نگه داشته شد.');
                redirect_admin('admin.php?page=design&tab=templates&edit_tpl=' . urlencode($key));
                // no break

            case 'create_db_template':
                $key = strtolower(trim((string) ($_POST['template_key'] ?? '')));
                $tplTitle = trim((string) ($_POST['template_title'] ?? ''));
                if ($tplTitle === '') {
                    $tplTitle = $key;
                }
                if (!create_template($key, $tplTitle)) {
                    throw new RuntimeException('ساخت قالب انجام نشد: کلید فقط می‌تواند حروف کوچک انگلیسی، عدد و آندرلاین باشد و نباید تکراری باشد.');
                }
                flash('ok', 'قالب سفارشی «' . $key . '» ساخته شد. حالا می‌توانید آن را به یک بخش بدهید.');
                redirect_admin('admin.php?page=design&tab=templates&edit_tpl=' . urlencode($key));
                // no break

            case 'delete_db_template':
                $key = trim((string) ($_POST['template_key'] ?? ''));
                if (!delete_template($key)) {
                    throw new RuntimeException('حذف انجام نشد: قالب پیدا نشد، قالب سیستمی است، یا در یک یا چند بخش استفاده شده است.');
                }
                flash('ok', 'قالب «' . $key . '» حذف شد.');
                redirect_admin('admin.php?page=design&tab=templates');
                // no break

            case 'restore_revision':
                $id = (int) ($_POST['revision_id'] ?? 0);
                if (!restore_design_revision($id)) {
                    throw new RuntimeException('بازیابی این نسخه انجام نشد.');
                }
                flash('ok', 'نسخه انتخاب‌شده بازیابی شد. نسخه‌ای که قبل از بازیابی فعال بود هم در تاریخچه نگه داشته شد.');
                if ((string) ($_POST['return_to'] ?? '') === 'css') {
                    redirect_admin('admin.php?page=design&tab=css');
                }
                $rk = trim((string) ($_POST['template_key'] ?? ''));
                redirect_admin('admin.php?page=design&tab=templates' . ($rk !== '' ? '&edit_tpl=' . urlencode($rk) : ''));
                // no break

            case 'save_push_subscription':
                $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
                $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
                $auth = trim((string) ($_POST['auth'] ?? ''));
                if ($endpoint === '' || $p256dh === '' || $auth === '') {
                    api_out(['ok' => false, 'error' => 'invalid']);
                }
                $me = current_admin_user();
                $uid = (int) ($me['id'] ?? 0);
                $pdo->prepare("INSERT OR REPLACE INTO push_subscriptions (user_id, endpoint, p256dh, auth) VALUES (:u, :e, :p, :a)")
                    ->execute([':u' => $uid, ':e' => $endpoint, ':p' => $p256dh, ':a' => $auth]);
                api_out(['ok' => true]);
                // no break

            case 'save_visual_settings':
                $font = (string) ($_POST['site_font'] ?? 'system');
                if (!in_array($font, ['system', 'tahoma', 'vazirmatn'], true)) {
                    $font = 'system';
                }
                $theme = (string) ($_POST['default_theme'] ?? 'light');
                if (!in_array($theme, ['light', 'dark', 'white', 'glass', 'smoke', 'system'], true)) {
                    $theme = 'light';
                }
                set_setting('primary_color', valid_hex_color($_POST['primary_color'] ?? null, '#2563eb'));
                set_setting('accent_color', valid_hex_color($_POST['accent_color'] ?? null, '#0d9488'));
                set_setting('site_font', $font);
                set_setting('container_width', (string) max(880, min(1600, (int) ($_POST['container_width'] ?? 1200))));
                set_setting('border_radius', (string) max(0, min(32, (int) ($_POST['border_radius'] ?? 12))));
                set_setting('default_theme', $theme);
                $adminTheme = (string) ($_POST['admin_theme'] ?? 'light');
                if (!in_array($adminTheme, ['light', 'architect', 'glass-white', 'glass-smoke'], true)) {
                    $adminTheme = 'light';
                }
                set_setting('admin_theme', $adminTheme);
                set_setting('header_sticky', isset($_POST['header_sticky']) ? '1' : '0');
                // تنظیمات جزئی استایل ادمین (۹٫۲۵)
                set_setting('admin_sidebar_bg', valid_hex_color($_POST['admin_sidebar_bg'] ?? null, ''));
                set_setting('admin_sidebar_text', valid_hex_color($_POST['admin_sidebar_text'] ?? null, ''));
                set_setting('admin_header_bg', valid_hex_color($_POST['admin_header_bg'] ?? null, ''));
                set_setting('admin_accent', valid_hex_color($_POST['admin_accent'] ?? null, ''));
                set_setting('admin_font_size', (string) max(12, min(18, (int) ($_POST['admin_font_size'] ?? 14))));
                set_setting('admin_radius', (string) max(0, min(24, (int) ($_POST['admin_radius'] ?? 8))));
                set_setting('admin_card_padding', (string) max(8, min(32, (int) ($_POST['admin_card_padding'] ?? 16))));
                bump_css_updated();
                flash('ok', 'تنظیمات ظاهری ذخیره شد و بلافاصله روی سایت اعمال می‌شود.');
                redirect_admin('admin.php?page=design&tab=css');
                // no break

            case 'save_css':
                $which = (string) ($_POST['which'] ?? 'site_css');
                if (!in_array($which, ['site_css', 'custom_css'], true)) {
                    throw new RuntimeException('هدف CSS معتبر نیست.');
                }
                save_css_content($which, (string) ($_POST['content'] ?? ''));
                flash('ok', ($which === 'site_css' ? 'CSS اصلی' : 'CSS سفارشی') . ' ذخیره شد. نسخه قبلی در تاریخچه نگه داشته شد.');
                redirect_admin('admin.php?page=design&tab=css');
                // no break

            case 'reset_css':
                $which = (string) ($_POST['which'] ?? 'site_css');
                if (!in_array($which, ['site_css', 'custom_css'], true)) {
                    throw new RuntimeException('هدف CSS معتبر نیست.');
                }
                reset_css_content($which);
                flash('ok', ($which === 'site_css' ? 'CSS اصلی به نسخه کارخانه‌ای برگشت.' : 'CSS سفارشی خالی شد.') . ' نسخه قبلی در تاریخچه نگه داشته شد.');
                redirect_admin('admin.php?page=design&tab=css');
                // no break

            case 'apply_theme':
                $themeKey = (string) ($_POST['theme'] ?? '');
                $defs = site_theme_definitions();
                if (!isset($defs[$themeKey])) {
                    throw new RuntimeException('قالب معتبر نیست.');
                }
                save_css_content('site_css', theme_css($themeKey) . "\n\n" . order_form_css(), 'قبل از تغییر قالب به «' . $defs[$themeKey]['title'] . '»');
                set_setting('site_theme', $themeKey);
                log_admin_event('apply_theme', 'تغییر قالب فرانت‌اند به «' . $defs[$themeKey]['title'] . '»');
                flash('ok', 'قالب «' . $defs[$themeKey]['title'] . '» فعال شد. نسخه قبلی CSS در تاریخچه نگه داشته شد.');
                redirect_admin('admin.php?page=design&tab=themes');
                // no break

            case 'delete_legacy_files':
                if (trim((string) ($_POST['confirm_text'] ?? '')) !== 'حذف') {
                    throw new RuntimeException('برای حذف فایل‌های قدیمی، کلمه «حذف» را دقیقاً تایپ کنید.');
                }
                if (all_templates() === []) {
                    throw new RuntimeException('قالب‌های دیتابیس هنوز آماده نیستند؛ برای امنیت، حذف فایل‌های قدیمی انجام نشد.');
                }
                $deleted = [];
                foreach (legacy_cleanup_candidates() as $name) {
                    $path = __DIR__ . '/' . $name;
                    if (is_file($path) && @unlink($path)) {
                        $deleted[] = $name;
                    }
                }
                flash('ok', $deleted !== [] ? 'فایل‌های قدیمی حذف شدند: ' . implode('، ', $deleted) . '. سایت از این به بعد فقط از قالب و CSS دیتابیس استفاده می‌کند.' : 'فایل قدیمی‌ای برای حذف پیدا نشد.');
                redirect_admin('admin.php?page=design&tab=templates');
                // no break

            case 'save_settings':
                set_setting('site_title', trim((string) ($_POST['site_title'] ?? '')));
                set_setting('site_description', trim((string) ($_POST['site_description'] ?? '')));
                set_setting('seo_title', trim((string) ($_POST['seo_title'] ?? '')));
                set_setting('seo_description', trim((string) ($_POST['seo_description'] ?? '')));
                set_setting('seo_keywords', trim((string) ($_POST['seo_keywords'] ?? '')));
                set_setting('site_url', trim((string) ($_POST['site_url'] ?? '')));
                set_setting('contact_phone', trim((string) ($_POST['contact_phone'] ?? '')));
                set_setting('contact_email', trim((string) ($_POST['contact_email'] ?? '')));
                set_setting('contact_whatsapp', trim((string) ($_POST['contact_whatsapp'] ?? '')));
                // نقشه سایت
                set_setting('sitemap_enabled', isset($_POST['sitemap_enabled']) ? '1' : '0');
                set_setting('sitemap_home_freq', trim((string) ($_POST['sitemap_home_freq'] ?? 'daily')));
                set_setting('sitemap_home_priority', trim((string) ($_POST['sitemap_home_priority'] ?? '1.0')));
                set_setting('sitemap_pages_freq', trim((string) ($_POST['sitemap_pages_freq'] ?? 'weekly')));
                set_setting('sitemap_pages_priority', trim((string) ($_POST['sitemap_pages_priority'] ?? '0.8')));
                set_setting('sitemap_products_freq', trim((string) ($_POST['sitemap_products_freq'] ?? 'weekly')));
                set_setting('sitemap_products_priority', trim((string) ($_POST['sitemap_products_priority'] ?? '0.7')));
                // فاز ۲: تنظیمات مشتری و کاتالوگ محصول
                $discount = (int) ($_POST['partner_discount_percent'] ?? 10);
                if ($discount < 0) { $discount = 0; }
                if ($discount > 90) { $discount = 90; }
                set_setting('partner_discount_percent', (string) $discount);
                set_setting('catalog_public', isset($_POST['catalog_public']) ? '1' : '0');
                set_setting('catalog_title', trim((string) ($_POST['catalog_title'] ?? '')) ?: 'کاتالوگ محصولات');
                // حقوق و دستمزد وزارت‌کاری (نسخه ۹٫۹)
                set_setting('wage_bon_kargari', (string) max(0, (int) ($_POST['wage_bon_kargari'] ?? 2200000)));
                set_setting('wage_housing', (string) max(0, (int) ($_POST['wage_housing'] ?? 900000)));
                set_setting('wage_child_allowance', (string) max(0, (int) ($_POST['wage_child_allowance'] ?? 1250000)));
                set_setting('wage_tax_threshold', (string) max(0, (int) ($_POST['wage_tax_threshold'] ?? 24000000)));
                // مدت نشست مدیریت (ساعت) — نسخه ۸٫۲؛ بین ۱ ساعت تا ۳۰ روز
                $sessHours = (int) ($_POST['session_lifetime_hours'] ?? 168);
                if ($sessHours < 1) { $sessHours = 1; }
                if ($sessHours > 720) { $sessHours = 720; }
                set_setting('session_lifetime_hours', (string) $sessHours);
                flash('ok', 'تنظیمات ذخیره شد.');
                redirect_admin('admin.php?page=settings');
                // no break

            case 'save_favicon':
                if (!empty($_FILES['favicon']['tmp_name'])) {
                    $fup = handle_section_image_upload($_FILES['favicon'] ?? null, null);
                    if ($fup['ok'] && !empty($fup['filename'])) {
                        $oldFav = get_setting('favicon_path', '');
                        if ($oldFav !== '' && $oldFav !== 'uploads/gallery/favicon.png') {
                            @unlink(UPLOADS_DIR . '/' . basename($oldFav));
                        }
                        set_setting('favicon_path', 'uploads/' . basename((string) $fup['filename']));
                        flash('ok', 'فاوآیکون به‌روز شد.');
                    } else {
                        flash('error', (string) ($fup['error'] ?? 'خطا در آپلود فاوآیکون.'));
                    }
                } else {
                    flash('error', 'فایلی انتخاب نشده است.');
                }
                redirect_admin('admin.php?page=settings');
                // no break

            case 'save_update_settings':
                $repo = trim((string) ($_POST['update_repo'] ?? ''));
                $branch = trim((string) ($_POST['update_branch'] ?? ''));
                $zipUrl = trim((string) ($_POST['update_zip_url'] ?? ''));
                if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
                    throw new RuntimeException('نام مخزن باید به شکل owner/repo باشد؛ مثل farsmd/soon.');
                }
                if (!preg_match('#^[A-Za-z0-9._/-]+$#', $branch) || strpos($branch, '..') !== false) {
                    throw new RuntimeException('نام شاخه معتبر نیست.');
                }
                if ($zipUrl !== '' && !preg_match('#^https?://#i', $zipUrl)) {
                    throw new RuntimeException('آدرس مستقیم فایل ZIP باید با http یا https شروع شود.');
                }
                set_setting('update_repo', $repo);
                set_setting('update_branch', $branch);
                set_setting('update_zip_url', $zipUrl);
                flash('ok', 'تنظیمات آپدیت ذخیره شد.');
                redirect_admin('admin.php?page=update');
                // no break

            case 'perform_update':
                $cfg = update_repo_config();
                $wantBackup = isset($_POST['backup_db']);
                $result = perform_update($cfg, $wantBackup);
                if (!$result['ok']) {
                    throw new RuntimeException((string) $result['error']);
                }
                $msg = 'آپدیت با موفقیت انجام شد. نسخه جدید: ' . (string) $result['new_version'] . '.';
                if (!empty($result['backup_file'])) {
                    $msg .= ' بکاپ دیتابیس قبل از آپدیت با نام ' . (string) $result['backup_file'] . ' در فولدر backups ذخیره شد.';
                } else {
                    $msg .= ' (بدون گرفتن بکاپ دیتابیس، طبق انتخاب شما.)';
                }
                flash('ok', $msg);
                redirect_admin('admin.php?page=update&done=1');
                // no break

            case 'change_password':
                $current = (string) ($_POST['current_password'] ?? '');
                $new     = (string) ($_POST['new_password'] ?? '');
                $confirm = (string) ($_POST['new_password_confirm'] ?? '');
                // از نسخه ۸٫۷ پسوردِ خودِ کاربرِ واردشده عوض می‌شود، نه یک پسورد سراسری
                $meUser = current_admin_user();
                $myHash = $meUser !== null ? (string) ($meUser['pass_hash'] ?? '') : '';
                if ($myHash === '') {
                    $myHash = (string) get_setting('admin_password_hash', '');
                }
                if (!password_verify($current, $myHash)) {
                    throw new RuntimeException('پسورد فعلی اشتباه است.');
                }
                if (strlen($new) < 8) {
                    throw new RuntimeException('پسورد جدید باید حداقل ۸ کاراکتر باشد.');
                }
                if ($new !== $confirm) {
                    throw new RuntimeException('تکرار پسورد جدید یکسان نیست.');
                }
                $newPassHash = password_hash($new, PASSWORD_DEFAULT);
                if ($meUser !== null) {
                    $pdo->prepare('UPDATE admin_users SET pass_hash = ? WHERE id = ?')
                        ->execute([$newPassHash, (int) $meUser['id']]);
                    if ((string) ($meUser['username'] ?? '') === 'admin') {
                        set_setting('admin_password_hash', $newPassHash);
                    }
                } else {
                    set_setting('admin_password_hash', $newPassHash);
                }
                flash('ok', 'پسورد تغییر کرد.');
                redirect_admin('admin.php?page=settings');
                // no break

        }
    } catch (Throwable $ex) {
        // اکشن ناموفق هم در لاگ مدیریت ثبت می‌شود
        log_admin_event((string) ($action ?? ''), (string) $ex->getMessage(), false, (string) ($_GET['page'] ?? ''));
        $error = $ex->getMessage();
    }
}

/**
 * جابه‌جایی یک ردیف (بخش یا صفحه) به بالا/پایین با همسایه‌اش */
$sections  = get_sections(false);
$pages     = get_pages(false);
$messages  = get_contact_messages();
$templates = available_templates();
$settings  = all_settings();
$flash     = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$databaseConnected = false;
try {
    $databaseConnected = ((string) $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok');
} catch (Throwable $ignored) {
    $databaseConnected = false;
}
$dbStats = null;
$dbFiles = [];
if ($page === 'database') {
    $dbStats = db_stats($pdo);
    $databaseConnected = (bool) ($dbStats['connected'] ?? false);
    $dbFiles = db_files();
}

// مرورگر دیتابیس (۹٫۱۹) — فقط مدیر کل
$dbBrowse = null;
if ($page === 'db_browse') {
    $tbl = trim((string) ($_GET['table'] ?? ''));
    // اعتبارسنجی نام جدول
    $validTables = [];
    try {
        $validTables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {}
    if ($tbl !== '' && in_array($tbl, $validTables, true)) {
        $perPage = 25;
        $pNum = max(1, (int) ($_GET['p'] ?? 1));
        $total = (int) $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', $tbl) . '"')->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $pNum = min($pNum, $pages);
        $offset = ($pNum - 1) * $perPage;
        $cols = $pdo->query('PRAGMA table_info("' . str_replace('"', '""', $tbl) . '")')->fetchAll(PDO::FETCH_ASSOC);
        $rows = $pdo->query('SELECT rowid, * FROM "' . str_replace('"', '""', $tbl) . '" LIMIT ' . $perPage . ' OFFSET ' . $offset)->fetchAll(PDO::FETCH_ASSOC);
        // ستون کلید اصلی
        $pkCol = null;
        foreach ($cols as $c) { if ((int) ($c['pk'] ?? 0) === 1) { $pkCol = $c['name']; break; } }
        $dbBrowse = ['table' => $tbl, 'cols' => $cols, 'rows' => $rows, 'total' => $total, 'page' => $pNum, 'pages' => $pages, 'pk' => $pkCol, 'tables' => $validTables];
    } else {
        $dbBrowse = ['table' => '', 'tables' => $validTables];
    }
}

$updateCfg = update_repo_config();
$logsData = null;
if ($page === 'logs') {
    $logsData = logs_prepare($_GET);
}
$updateInfo = null;
if ($page === 'update' && (isset($_GET['check']) || isset($_GET['done']))) {
    $updateInfo = update_check($updateCfg);
}

$editSection = null;
if ($page === 'sections' && isset($_GET['edit_id'])) {
    foreach ($sections as $s) {
        if ((int) $s['id'] === (int) $_GET['edit_id']) { $editSection = $s; break; }
    }
}
$editPage = null;
$pageBlocks = [];
$editBlock = null;
if ($page === 'pages' && isset($_GET['edit_id'])) {
    foreach ($pages as $p) {
        if ((int) $p['id'] === (int) $_GET['edit_id']) { $editPage = $p; break; }
    }
    // نسخه ۹٫۰٫۲: ویرایش صفحه گالری مستقیم به مدیریت گالری می‌رود — بدون دست‌زدن به کد
    if ($editPage !== null && (string) ($editPage['slug'] ?? '') === 'gallery' && !isset($_GET['raw'])) {
        redirect_admin('admin.php?page=gallery');
    }
    if ($editPage !== null) {
        $pageBlocks = get_page_blocks((int) $editPage['id'], false);
        if (isset($_GET['edit_block'])) {
            foreach ($pageBlocks as $b) {
                if ((int) $b['id'] === (int) $_GET['edit_block']) { $editBlock = $b; break; }
            }
        }
    }
}

// ---------- داده‌های فاز ۲ (مشتری‌ها، دسته‌ها، محصولات، ویژگی‌ها) — بارگذاری در admin_catalog.php ----------
$catalogData = catalog_load_data($page);
extract($catalogData);

// ---------- داده‌های فاز ۲٫۵ (مواد اولیه و انبار) — بارگذاری در admin_inventory.php ----------
$inventoryData = inventory_load_data($page);
extract($inventoryData);

// ---------- داده‌های فاز ۳ (سفارش‌ها) — بارگذاری در admin_orders.php ----------
$ordersData = orders_load_data($page);
extract($ordersData);

// ---------- داده‌های فاز ۴ (تولید) — بارگذاری در admin_production.php ----------
$productionData = production_load_data($page);
extract($productionData);
// داده‌های فاز ۵ (مالی): فاکتور، دریافتی، هزینه و صورتحساب
$financeData = finance_load_data($page);
// داده‌های منابع انسانی (نسخه ۹٫۷)
$hrData = hr_load_data();
extract($hrData);
// داده‌های وبلاگ (نسخه ۹٫۱۴)
$blogData = ['blog_posts' => [], 'blog_edit' => null];
if ($page === 'blog') {
    $blogData['blog_posts'] = db()->query('SELECT * FROM blog_posts ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    if (isset($_GET['edit_id']) && (int) $_GET['edit_id'] > 0) {
        $st = db()->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int) $_GET['edit_id']]);
        $blogData['blog_edit'] = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
// داده‌های تجهیزات (نسخه ۹٫۷)
$assetsData = assets_load_data($page);
extract($assetsData);
extract($financeData);

// داده‌های فاز ۶ (گزارش‌ها): فروش، محصولات، مصرف مواد، مشتریان و تولید
$reportsData = reports_load_data($page);
extract($reportsData);

// داده‌های کاربران و نقش‌ها (فاز ۰ / نسخه ۸٫۷)
$usersData = users_load_data($page);
extract($usersData);

// ---------- داشبورد: شمارنده‌های کارت‌ها (کوئری‌های COUNT سبک) ----------
$dashCounts = ['customers' => 0, 'products' => 0, 'orders' => 0, 'new_orders' => 0];
// داده‌های نمودارهای پاستیلی داشبورد (۸٫۵٫۰)
$chartIncome = [];      // [['month' => 'YYYY-MM', 'value' => int], ...] ۶ ماه اخیر
$chartOrderStatus = []; // [['label' => string, 'value' => int, 'color' => string]]
$chartExpenseCat = [];  // [['label' => string, 'value' => int, 'color' => string]]
$chartProdStages = [];  // [['label' => string, 'value' => int, 'color' => string]]
if ($page === 'dashboard') {
    // KPIهای مدیریتی (نسخه ۹٫۷) — برای ارائه به سرمایه‌گذار
    $kpi = ['month_revenue' => 0, 'month_orders' => 0, 'active_employees' => 0, 'month_payroll' => 0, 'asset_book_value' => 0, 'total_customers' => 0];
    try {
        $mk = date('Y-m');
        $kpi['month_revenue'] = (int) $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE substr(created_at,1,7) = '$mk' AND status != 'cancelled'")->fetchColumn();
        $kpi['month_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,7) = '$mk' AND status != 'cancelled'")->fetchColumn();
        $kpi['active_employees'] = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
        $kpi['month_payroll'] = (int) $pdo->query("SELECT COALESCE(SUM(net_amount),0) FROM salary_payments WHERE pay_month = '$mk'")->fetchColumn();
        // ارزش دفتری تجهیزات
        $eqs = $pdo->query("SELECT purchase_price, useful_life_years, purchase_date FROM equipment WHERE status != 'retired'")->fetchAll();
        $bv = 0;
        foreach ($eqs as $eq) {
            $pp = (int) $eq['purchase_price'];
            $life = max(0.1, (float) $eq['useful_life_years']);
            $annual = $pp / $life;
            $years = 0;
            if (!empty($eq['purchase_date'])) {
                $years = max(0, (time() - strtotime($eq['purchase_date'])) / (365.25 * 86400));
            }
            $bv += max(0, (int) round($pp - $annual * $years));
        }
        $kpi['asset_book_value'] = $bv;
    } catch (Throwable $ignored) {
    }
    $GLOBALS['kpi'] = $kpi;
    try {
        $dashCounts['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
        $dashCounts['products']  = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $dashCounts['orders']     = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
        $dashCounts['new_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
    } catch (Throwable $ignored) {
    }
    try {
        // دریافتی ۶ ماه اخیر (ماه میلادی از paid_date)
        $rows = $pdo->query("SELECT substr(paid_date, 1, 7) AS m, COALESCE(SUM(amount), 0) AS s FROM payments WHERE paid_date >= date('now', '-6 months') GROUP BY m")->fetchAll();
        $byMonth = [];
        foreach ($rows as $r) {
            $byMonth[(string) $r['m']] = (int) $r['s'];
        }
        for ($i = 5; $i >= 0; $i--) {
            $mk = date('Y-m', strtotime("-$i months"));
            $chartIncome[] = ['month' => $mk, 'value' => $byMonth[$mk] ?? 0];
        }
    } catch (Throwable $ignored) {
    }
    try {
        $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status ORDER BY c DESC')->fetchAll();
        foreach ($rows as $r) {
            $chartOrderStatus[] = [
                'label' => function_exists('order_status_title') ? order_status_title((string) $r['status']) : (string) $r['status'],
                'value' => (int) $r['c'],
                'color' => function_exists('order_status_color') ? order_status_color((string) $r['status']) : '#a7b8e0',
            ];
        }
    } catch (Throwable $ignored) {
    }
    try {
        $rows = $pdo->query("SELECT c.title AS t, COALESCE(SUM(e.amount), 0) AS s FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE e.status = 'approved' GROUP BY c.id ORDER BY s DESC LIMIT 8")->fetchAll();
        $pastels = ['#ff6b9d', '#4fc3f7', '#69f0ae', '#ffd740', '#b388ff', '#ff8a80', '#40c4ff', '#b2ff59'];
        $ci = 0;
        foreach ($rows as $r) {
            $chartExpenseCat[] = ['label' => (string) $r['t'], 'value' => (int) $r['s'], 'color' => $pastels[$ci % count($pastels)]];
            $ci++;
        }
    } catch (Throwable $ignored) {
    }
    try {
        $rows = $pdo->query("SELECT stage_key, COUNT(*) AS c FROM production_orders WHERE state = 'open' GROUP BY stage_key")->fetchAll();
        foreach ($rows as $r) {
            $chartProdStages[] = [
                'label' => function_exists('production_stage_title') ? production_stage_title((string) $r['stage_key']) : (string) $r['stage_key'],
                'value' => (int) $r['c'],
                'color' => function_exists('production_stage_color') ? production_stage_color((string) $r['stage_key']) : '#fde68a',
            ];
        }
    } catch (Throwable $ignored) {
    }
    // --- نمودارهای فانتزی ۹٫۱۶: سود در برابر هزینه (۶ ماه) ---
    $chartProfit = [];
    try {
        for ($i = 5; $i >= 0; $i--) {
            $mk = date('Y-m', strtotime("-$i months"));
            $rev = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE substr(paid_date,1,7) = '$mk'")->fetchColumn();
            $cost = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE substr(expense_date,1,7) = '$mk' AND status = 'approved'")->fetchColumn();
            $chartProfit[] = ['month' => $mk, 'revenue' => $rev, 'cost' => $cost, 'profit' => $rev - $cost];
        }
    } catch (Throwable $ignored) {
    }
    // --- قیف سفارشات (پایپ‌لاین) ---
    $chartFunnel = [];
    try {
        $funnelStages = ['new' => 0, 'confirmed' => 0, 'in_production' => 0, 'ready' => 0, 'delivered' => 0];
        $rows = $pdo->query("SELECT status, COUNT(*) AS c FROM orders WHERE status != 'cancelled' GROUP BY status")->fetchAll();
        $byStatus = [];
        foreach ($rows as $r) { $byStatus[(string) $r['status']] = (int) $r['c']; }
        foreach ($funnelStages as $sk => $v) { $funnelStages[$sk] = $byStatus[$sk] ?? 0; }
        $chartFunnel = $funnelStages;
    } catch (Throwable $ignored) {
    }
    // --- تایم‌لاین سفارشات ۳۰ روز اخیر ---
    $chartTimeline = [];
    try {
        for ($i = 29; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $c = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,10) = '$d' AND status != 'cancelled'")->fetchColumn();
            $chartTimeline[] = ['day' => $d, 'value' => $c];
        }
    } catch (Throwable $ignored) {
    }
}
// عنوان‌های نمایشی قالب‌های دیتابیس (برای برچسب فهرست قالب در فرم بخش‌ها)
$templateTitles = [];
foreach (all_templates() as $tplRow) {
    $templateTitles[(string) $tplRow['template_key']] = (string) $tplRow['title'];
}

// ---------- داده‌های صفحه «قالب و استایل» ----------
$designTab = 'templates';
$dbTemplates = [];
$editTplRow = null;
$editTplRevisions = [];
$cssRevisions = [];
$customCssRevisions = [];
$legacyTplWarnings = [];
$legacyCssWarnings = [];
$cleanupCandidates = [];
$tplUsedIn = [];
$visual = validated_visual_settings($settings);
$adminTheme = (string) get_setting('admin_theme', 'light');
if (!in_array($adminTheme, ['light', 'architect', 'glass-white', 'glass-smoke'], true)) {
    $adminTheme = 'light';
}
if ($page === 'design') {
    $designTab = (string) ($_GET['tab'] ?? 'templates');
    if (!in_array($designTab, ['templates', 'css', 'themes'], true)) {
        $designTab = 'templates';
    }
    $dbTemplates = all_templates();
    foreach ($sections as $s) {
        $tplUsedIn[section_template_key($s)][] = (string) $s['title'];
    }
    $legacyTplWarnings = legacy_archived_revisions('template');
    $legacyCssWarnings = legacy_archived_revisions('css');
    $cleanupCandidates = legacy_cleanup_candidates();
    $editKey = trim((string) ($_GET['edit_tpl'] ?? ''));
    if ($editKey !== '') {
        $editTplRow = get_template_row($editKey);
        if ($editTplRow !== null) {
            $editTplRevisions = design_revisions_for('template', $editKey);
        }
    }
    $cssRevisions = design_revisions_for('css', 'site_css');
    $customCssRevisions = design_revisions_for('css', 'custom_css');
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl" data-admin-theme="<?= e((string) get_setting('admin_theme', 'light')) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($currentPageTitle) ?> — پنل مدیریت</title>
<link rel="stylesheet" href="assets/bootstrap.rtl.min.css">
<script src="assets/chart.min.js"></script>
<?php if (in_array(($page ?? ''), ['design', 'pages'], true)): ?>
<link rel="stylesheet" href="assets/codemirror/lib/codemirror.min.css">
<link rel="stylesheet" href="assets/codemirror/theme/dracula.min.css">
<style>
.CodeMirror{height:420px;direction:ltr;text-align:left;font-size:14px;border-radius:10px;border:1px solid #334155}
.cm-toolbar{display:flex;gap:8px;align-items:center;margin:8px 0;flex-wrap:wrap}
.cm-toolbar .btn.small{font-size:12px;padding:6px 12px}
.cm-fullscreen{position:fixed!important;inset:0!important;height:100vh!important;z-index:9999;border-radius:0!important}
.cm-fullscreen-wrap{position:fixed;inset:0;z-index:9998;background:#0b0f14;padding:12px}
@media(max-width:640px){.CodeMirror{height:320px;font-size:13px}}
</style>
<?php endif; ?>
<style><?= admin_css() ?></style>
</head>
<body>
<header class="topbar">
    <div class="topbar-start">
        <button type="button" class="icon-btn menu-toggle" id="navToggle" aria-label="منوی کناری" onclick="(function(){if(window.__toggleAdminNav){window.__toggleAdminNav()}else{document.body.classList.toggle('nav-open')}})()"><?= nav_icon('menu') ?></button>
        <strong class="topbar-title"><?= e($currentPageTitle) ?></strong>
    </div>
    <nav class="topbar-actions">
        <?php if (admin_can_page('notifications')): $notifUnread = unread_notification_count(); ?>
        <a href="admin.php?page=notifications" aria-label="اعلان‌ها" style="position:relative;display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:8px;background:rgba(255,255,255,.07);color:#e5e7eb">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($notifUnread > 0): ?><span style="position:absolute;top:2px;inset-inline-start:2px;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;line-height:1"><?= $notifUnread > 99 ? '99+' : $notifUnread ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
        <?php if (admin_can_page('products')): ?><a href="admin.php?page=products" class="quick-add">محصول جدید</a><?php endif; ?>
        <?php if (admin_can_page('customers')): ?><a href="admin.php?page=customers" class="quick-add">مشتری جدید</a><?php endif; ?>
        <a href="index.php" target="_blank" rel="noopener" class="icon-btn" title="مشاهده سایت" aria-label="مشاهده سایت"><?= nav_icon('eye') ?></a>
        <?php $topbarMe = current_admin_user(); ?>
        <?php if ($topbarMe !== null): ?>
        <div class="user-menu-wrap">
            <button type="button" class="icon-btn" id="userMenuBtn" aria-label="منوی کاربری" aria-haspopup="true"><?= nav_icon('user') ?></button>
            <div class="user-menu" id="userMenu" hidden>
                <div class="user-menu-head">
                    <strong><?= e((string) ($topbarMe['display_name'] ?? '') !== '' ? (string) $topbarMe['display_name'] : (string) $topbarMe['username']) ?></strong>
                    <span class="muted"><?= e(user_role_title((string) $topbarMe['role_key'])) ?></span>
                </div>
                <a href="admin.php?page=profile"><?= nav_icon('user') ?> مشاهده پروفایل</a>
                <a href="admin.php?page=profile&tab=password"><?= nav_icon('lock') ?> تغییر رمز</a>
                <a href="admin.php?logout=1" class="danger"><?= nav_icon('logout') ?> خروج</a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
</header>
<script>(function(){var b=document.getElementById('userMenuBtn'),m=document.getElementById('userMenu');if(!b||!m)return;b.addEventListener('click',function(e){e.stopPropagation();m.hidden=!m.hidden;});document.addEventListener('click',function(){m.hidden=true;});m.addEventListener('click',function(e){e.stopPropagation();});})();</script>

<div class="layout">
    <div class="nav-overlay" id="navOverlay"></div>
    <aside class="sidebar">
        <div class="sidebar-brand">
            <?php if (is_file(__DIR__ . '/uploads/gallery/logo.png')): ?>
            <img src="uploads/gallery/logo.png" alt="لوگوی لاینرلایت" class="brand-logo">
            <?php else: ?>
            <span class="brand-sub">مدیریت یکپارچه لاینرلایت</span>
            <?php endif; ?>
            <strong class="brand-name"><?= e($settings['site_title'] ?? '') ?></strong>
        </div>

        <?php foreach ($navGroups as $gKey => $gData): ?>
        <?php $navVisible = 0; foreach ($gData[1] as $navItem) { if (($navItem[3] ?? '') === '' || admin_can_page((string) $navItem[3])) { $navVisible++; } } if ($navVisible === 0) { continue; } ?>
        <details class="nav-group" data-group="<?= $gKey ?>"<?= $activeNavGroup === $gKey ? ' open' : '' ?>>
            <summary><?= e($gData[0]) ?></summary>
            <?php foreach ($gData[1] as $it): ?>
            <?php if (($it[3] ?? '') !== '' && !admin_can_page((string) $it[3])) { continue; } ?>
            <a href="<?= e($it[0]) ?>"<?= $it[4] ?? '' ?> class="<?= $page === $it[3] ? 'active' : '' ?>" title="<?= e($it[2]) ?>"><?= nav_icon($it[1]) ?><span class="nav-label"><?= e($it[2]) ?></span><?php if ($it[3] === 'messages' && $messages !== []): ?><span class="nav-badge"><?= count($messages) ?></span><?php endif; ?><?php if ($it[3] === 'materials' && $lowStockCount > 0): ?><span class="nav-badge" title="مواد رو به اتمام"><?= $lowStockCount ?></span><?php endif; ?><?php if ($it[3] === 'production' && (int) ($productionActiveCount ?? 0) > 0): ?><span class="nav-badge" title="برگه‌های تولید در جریان"><?= (int) $productionActiveCount ?></span><?php endif; ?><?php if ($it[3] === 'expenses' && (int) ($finPending['count'] ?? 0) > 0): ?><span class="nav-badge" title="هزینه‌های در انتظار تأیید"><?= (int) $finPending['count'] ?></span><?php endif; ?><?php if ($it[3] === 'database' && $databaseConnected): ?><span class="status-dot" title="دیتابیس متصل است"></span><?php endif; ?></a>
            <?php endforeach; ?>
        </details>
        <?php endforeach; ?>

        <div class="sidebar-version">نسخه برنامه <span class="version-badge" dir="ltr"><?= e(APP_VERSION) ?></span></div>

    </aside>


    <main class="content">
        <?php if ($flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <?php
            // ----- سیستم ویجت‌های داشبورد (۸٫۵٫۰) — کاربر از همین صفحه فعال/غیرفعال و مرتبشان می‌کند -----
            $dashWidgetDefs = [
                'stat_customers'      => ['کارت آماری: مشتری‌ها', 'stat'],
                'stat_products'       => ['کارت آماری: محصولات', 'stat'],
                'stat_orders'         => ['کارت آماری: سفارش‌ها', 'stat'],
                'stat_new_orders'     => ['کارت آماری: سفارش‌های جدید (فقط وقتی > ۰)', 'stat'],
                'stat_production'     => ['کارت آماری: برگه‌های تولید در جریان (فقط وقتی > ۰)', 'stat'],
                'stat_finance_month'  => ['کارت آماری: تراز مالی این ماه', 'stat'],
                'stat_pending'        => ['کارت آماری: هزینه‌های در انتظار تأیید (فقط وقتی > ۰)', 'stat'],
                'stat_debt'           => ['کارت آماری: بدهی مشتریان (فقط وقتی > ۰)', 'stat'],
                'stat_messages'       => ['کارت آماری: پیام‌های تماس', 'stat'],
                'stat_visits'         => ['کارت آماری: بازدید سایت (امروز + این ماه)', 'stat'],
                'stat_version'        => ['کارت آماری: نسخه برنامه', 'stat'],
                'kpi_revenue'         => ['شاخص: درآمد این ماه', 'stat'],
                'kpi_orders'          => ['شاخص: سفارش‌های این ماه', 'stat'],
                'kpi_employees'       => ['شاخص: پرسنل فعال', 'stat'],
                'kpi_payroll'         => ['شاخص: حقوق این ماه', 'stat'],
                'kpi_assets'          => ['شاخص: ارزش دفتری تجهیزات', 'stat'],
                'chart_income'        => ['نمودار دریافتی ۶ ماه اخیر', 'chart'],
                'chart_orders'        => ['نمودار وضعیت سفارش‌ها', 'chart'],
                'chart_expenses'      => ['نمودار هزینه‌ها برحسب دسته', 'chart'],
                'chart_production'    => ['نمودار برگه‌های تولید برحسب مرحله', 'chart'],
                'chart_donut_orders'  => ['🍩 توزیع سفارش‌ها (دونات)', 'chart'],
                'chart_profit'        => ['💰 درآمد در برابر هزینه', 'chart'],
                'chart_funnel'        => ['🔻 قیف سفارشات', 'chart'],
                'chart_timeline'      => ['📊 تایم‌لاین ۳۰ روزه سفارشات', 'chart'],
                'chart_visits'        => ['بازدید روزانه (بدون ربات و ادمین)', 'chart'],
            ];
            $dashWidgetPages = [
                'stat_customers'     => 'customers',
                'stat_products'      => 'products',
                'stat_orders'        => 'orders',
                'stat_new_orders'    => 'orders',
                'stat_production'    => 'production',
                'stat_finance_month' => 'finance',
                'stat_pending'       => 'expenses',
                'stat_debt'          => 'statements',
                'stat_messages'      => 'messages',
                'stat_visits'        => 'logs',
                'stat_version'       => 'dashboard',
                'kpi_revenue'        => 'orders',
                'kpi_orders'         => 'orders',
                'kpi_employees'      => 'employees',
                'kpi_payroll'        => 'payroll',
                'kpi_assets'         => 'assets',
                'chart_income'       => 'finance',
                'chart_orders'       => 'orders',
                'chart_expenses'     => 'expenses',
                'chart_production'   => 'production',
                'chart_donut_orders' => 'orders',
                'chart_profit'       => 'finance',
                'chart_funnel'       => 'orders',
                'chart_timeline'     => 'orders',
                'chart_visits'       => 'logs',
            ];
            $dashEnabledRaw = json_decode((string) get_setting('dash_widgets', ''), true);
            $dashEnabled = (is_array($dashEnabledRaw) && $dashEnabledRaw !== [])
                ? array_values(array_filter(array_map('strval', $dashEnabledRaw), static fn ($k) => isset($dashWidgetDefs[$k])))
                : array_keys($dashWidgetDefs);
            if ($dashEnabled === []) {
                $dashEnabled = array_keys($dashWidgetDefs);
            }
            $dashOn = array_fill_keys($dashEnabled, true);
            // نمودار میله‌ای پاستیلی (SVG بدون کتابخانه)
            $pastelPalette = ['#f9a8d4', '#93c5fd', '#6ee7b7', '#fcd34d', '#c4b5fd', '#fda4af', '#7dd3fc', '#bef264'];
            // سازنده نمودار Chart.js با تم نئون شیشه‌ای (۹٫۱۸)
            $chartJs = static function (string $type, array $labels, array $datasets, array $opts = []): string {
                static $idx = 0; $idx++;
                $cid = 'chartjs' . $idx;
                $neonColors = ['#00e5ff', '#ff4081', '#69f0ae', '#ffd740', '#b388ff', '#ff8a80', '#40c4ff', '#b2ff59'];
                foreach ($datasets as $di => &$ds) {
                    if (!isset($ds['backgroundColor'])) {
                        if ($type === 'doughnut') {
                            $ds['backgroundColor'] = array_slice($neonColors, 0, count($labels));
                            $ds['borderColor'] = '#ffffff';
                            $ds['borderWidth'] = 3;
                            $ds['hoverOffset'] = 12;
                        } elseif ($type === 'line') {
                            $c = $neonColors[$di % count($neonColors)];
                            $ds['borderColor'] = $c;
                            $ds['backgroundColor'] = $c . '33';
                            $ds['fill'] = $ds['fill'] ?? true;
                            $ds['tension'] = 0.4;
                            $ds['borderWidth'] = 3;
                            $ds['pointBackgroundColor'] = $c;
                            $ds['pointBorderColor'] = '#fff';
                            $ds['pointBorderWidth'] = 2;
                            $ds['pointRadius'] = 5;
                            $ds['pointHoverRadius'] = 8;
                        } else {
                            $ds['backgroundColor'] = array_map(fn($i) => $neonColors[$i % count($neonColors)] . 'cc', array_keys($labels));
                            $ds['borderColor'] = array_map(fn($i) => $neonColors[$i % count($neonColors)], array_keys($labels));
                            $ds['borderWidth'] = 2;
                            $ds['borderRadius'] = 8;
                        }
                    }
                }
                $config = [
                    'type' => $type,
                    'data' => ['labels' => $labels, 'datasets' => $datasets],
                    'options' => array_merge([
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => [
                            'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'padding' => 16, 'font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 12]]],
                            'tooltip' => [
                                'backgroundColor' => 'rgba(17,24,39,.9)',
                                'backdropFilter' => 'blur(8px)',
                                'titleFont' => ['family' => 'Vazirmatn, Tahoma', 'size' => 13],
                                'bodyFont' => ['family' => 'Vazirmatn, Tahoma', 'size' => 12],
                                'padding' => 12,
                                'cornerRadius' => 10,
                                'displayColors' => true,
                            ],
                        ],
                        'scales' => $type === 'doughnut' ? [] : [
                            'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 11]]],
                            'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(0,0,0,.06)'], 'ticks' => ['font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 11]]],
                        ],
                    ], $opts),
                ];
                $json = json_encode($config, JSON_UNESCAPED_UNICODE);
                return '<div class="chartjs-wrap"><canvas id="' . $cid . '"></canvas></div>'
                    . '<script>(function(){if(typeof Chart==="undefined")return;new Chart(document.getElementById("' . $cid . '"),' . $json . ');})();</script>';
            };
            $renderBarChart = static function (array $bars, string $unitLabel = '') use ($pastelPalette): string {
                if ($bars === []) {
                    return '<p class="muted">داده‌ای برای نمایش نیست.</p>';
                }
                $max = 1;
                foreach ($bars as $b) {
                    $max = max($max, (float) $b['value']);
                }
                $w = max(320, count($bars) * 74);
                $h = 190;
                $base = 160;
                $bw = min(46, max(22, (int) (($w / max(1, count($bars))) * 0.55)));
                $gap = $w / count($bars);
                $out = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" style="width:100%;height:auto;display:block" xmlns="http://www.w3.org/2000/svg">';
                $out .= '<line x1="8" y1="' . $base . '" x2="' . ($w - 8) . '" y2="' . $base . '" stroke="#e5e7eb" stroke-width="2"/>';
                $i = 0;
                foreach ($bars as $b) {
                    $val = (float) $b['value'];
                    $bh = max(2, (int) round($val / $max * 118));
                    $x = (int) round(8 + $i * $gap + ($gap - $bw) / 2);
                    $y = $base - $bh;
                    $color = (string) ($b['color'] ?? $pastelPalette[$i % count($pastelPalette)]);
                    $label = (string) ($b['label'] ?? '');
                    $disp = $unitLabel !== '' ? number_format((int) $val) . ' ' . $unitLabel : number_format((int) $val);
                    $out .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '" rx="7" fill="' . e($color) . '"/>';
                    $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($y - 6) . '" font-size="11" font-weight="700" text-anchor="middle" fill="#374151">' . e($disp) . '</text>';
                    $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($base + 16) . '" font-size="10.5" text-anchor="middle" fill="#6b7280">' . e(mb_substr($label, 0, 14)) . '</text>';
                    $i++;
                }
                $out .= '</svg>';
                return $out;
            };
            $dash_icon = static function (string $name): string {
                $paths = [
                    'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                    'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                    'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/>',
                    'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                    'factory' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/>',
                    'scale' => '<path d="M12 3v18"/><path d="M5 7l-3 7a3.5 3.5 0 0 0 6 0L5 7z"/><path d="M19 7l-3 7a3.5 3.5 0 0 0 6 0l-3-7z"/><path d="M3 7h18"/>',
                    'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                    'card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
                    'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
                    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.2 4.2l2.8 2.8M17 17l2.8 2.8M1 12h4M19 12h4M4.2 19.8 7 17M17 7l2.8-2.8"/>',
                    'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
                    'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
                    'alert' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                ];
                $p = $paths[$name] ?? $paths['box'];
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true">' . $p . '</svg>';
            };
            // بررسی سبک نسخه جدید: حداکثر هر ۶ ساعت یک بار، با تایم‌اوت کوتاه؛ در صورت خطا بی‌صدا رد می‌شود
            $dash_version_info = static function (): array {
                $out = ['latest' => '', 'ok' => false];
                try {
                    $cached = (string) get_setting('update_check_latest', '');
                    $cachedAt = (int) get_setting('update_check_at', '0');
                    if ((time() - $cachedAt) < 21600) {
                        // کش تازه است (حتی اگر خالی باشد یعنی تلاش ناموفق اخیر)؛ بدون درخواست شبکه
                        $out['latest'] = $cached;
                        $out['ok'] = $cached !== '';
                        return $out;
                    }
                    $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'linerlight-cms-update-check']]);
                    $body = @file_get_contents('https://raw.githubusercontent.com/farsmd/soon/main/config.php', false, $ctx);
                    if (is_string($body) && preg_match("/define\s*\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $body, $m)) {
                        $out['latest'] = $m[1];
                        $out['ok'] = true;
                    }
                    // ثبت زمان آخرین تلاش (موفق یا ناموفق) تا داشبورد بیش از حد لازم درخواست نزند
                    try {
                        set_setting('update_check_latest', $out['latest']);
                        set_setting('update_check_at', (string) time());
                    } catch (Throwable $e) {
                    }
                } catch (Throwable $e) {
                }
                return $out;
            };
            $renderStat = static function (string $key) use ($visitsToday, $visitsMonth, $dashCounts, $productionActiveCount, $messages, $lowStockCount, $finPending, $finMonthIncome, $finMonthExpenses, $finDebtTotal, $dashWidgetPages, $dash_icon, $dash_version_info): string {

                if (isset($dashWidgetPages[$key]) && !admin_can_page($dashWidgetPages[$key])) {
                    return '';
                }
                switch ($key) {
                    case 'stat_customers':
                        return '<a class="stat-card sc-blue" href="admin.php?page=customers"><span><i class="sc-ico">' . $dash_icon('users') . '</i>مشتری‌ها</span><strong>' . (int) $dashCounts['customers'] . '</strong></a>';
                    case 'stat_products':
                        return '<a class="stat-card sc-indigo" href="admin.php?page=products"><span><i class="sc-ico">' . $dash_icon('box') . '</i>محصولات</span><strong>' . (int) $dashCounts['products'] . '</strong></a>';
                    case 'stat_orders':
                        return '<a class="stat-card sc-teal" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('receipt') . '</i>سفارش‌ها</span><strong>' . (int) $dashCounts['orders'] . '</strong></a>';
                    case 'stat_new_orders':
                        return (int) $dashCounts['new_orders'] > 0 ? '<a class="stat-card sc-blue sc-alert-blue" href="admin.php?page=orders&status=new"><span><i class="sc-ico">' . $dash_icon('bell') . '</i>سفارش‌های جدید</span><strong>' . (int) $dashCounts['new_orders'] . '</strong></a>' : '';
                    case 'stat_production':
                        return (int) ($productionActiveCount ?? 0) > 0 ? '<a class="stat-card sc-amber sc-alert-amber" href="admin.php?page=production"><span><i class="sc-ico">' . $dash_icon('factory') . '</i>تولید در جریان</span><strong>' . (int) $productionActiveCount . '</strong></a>' : '';
                    case 'stat_finance_month':
                        return (isset($finPending) && is_array($finPending)) ? '<a class="stat-card sc-green" href="admin.php?page=finance"><span><i class="sc-ico">' . $dash_icon('scale') . '</i>تراز مالی این ماه</span><strong>' . e(format_price((int) ($finMonthIncome ?? 0) - (int) ($finMonthExpenses ?? 0))) . ' تومان</strong></a>' : '';
                    case 'stat_pending':
                        return (isset($finPending) && is_array($finPending) && (int) ($finPending['count'] ?? 0) > 0) ? '<a class="stat-card sc-amber sc-alert-amber" href="admin.php?page=expenses&status=pending"><span><i class="sc-ico">' . $dash_icon('clock') . '</i>در انتظار تأیید</span><strong>' . (int) $finPending['count'] . ' مورد</strong></a>' : '';
                    case 'stat_debt':
                        return ((int) ($finDebtTotal ?? 0) > 0) ? '<a class="stat-card sc-red sc-alert-red" href="admin.php?page=statements"><span><i class="sc-ico">' . $dash_icon('card') . '</i>بدهی مشتریان</span><strong>' . e(format_price((int) $finDebtTotal)) . ' تومان</strong></a>' : '';
                    case 'stat_visits':
                        return '<a class="stat-card sc-cyan stat-dual" href="admin.php?page=logs"><span><i class="sc-ico">' . $dash_icon('eye') . '</i>بازدید سایت</span><div class="dual-rows"><div><small>امروز</small><strong>' . (int) $visitsToday . '</strong></div><div><small>این ماه</small><strong>' . (int) $visitsMonth . '</strong></div></div></a>';
                    case 'stat_messages':
                        return '<a class="stat-card sc-purple" href="admin.php?page=messages"><span><i class="sc-ico">' . $dash_icon('mail') . '</i>پیام‌های تماس</span><strong>' . count($messages) . '</strong></a>';
                    case 'stat_version':
                        $vi = $dash_version_info();
                        $verSub = '';
                        if ($vi['ok'] && $vi['latest'] !== '' && version_compare((string) APP_VERSION, (string) $vi['latest'], '<')) {
                            $verSub = '<span class="sc-sub" style="color:#b45309;font-weight:700">نسخه ' . e($vi['latest']) . ' موجود است ← آپدیت</span>';
                        } elseif ($vi['ok'] && $vi['latest'] !== '') {
                            $verSub = '<span class="sc-sub" style="color:#16a34a">به‌روز است ✓</span>';
                        }
                        return '<a class="stat-card" href="admin.php?page=update"><span><i class="sc-ico">' . $dash_icon('gear') . '</i>نسخه برنامه</span><strong dir="ltr">' . e(APP_VERSION) . '</strong>' . $verSub . '</a>';
                    case 'kpi_revenue':
                        return '<a class="stat-card kpi kpi-revenue" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('coins') . '</i>درآمد این ماه</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['month_revenue'] ?? 0))) . ' تومان</strong></a>';
                    case 'kpi_orders':
                        return '<a class="stat-card kpi kpi-orders" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('box') . '</i>سفارش‌های این ماه</span><strong>' . (int) ($GLOBALS['kpi']['month_orders'] ?? 0) . '</strong></a>';
                    case 'kpi_employees':
                        return '<a class="stat-card kpi kpi-employees" href="admin.php?page=employees"><span><i class="sc-ico">' . $dash_icon('users') . '</i>پرسنل فعال</span><strong>' . (int) ($GLOBALS['kpi']['active_employees'] ?? 0) . ' نفر</strong></a>';
                    case 'kpi_payroll':
                        return '<a class="stat-card kpi kpi-payroll" href="admin.php?page=payroll"><span><i class="sc-ico">' . $dash_icon('wallet') . '</i>حقوق این ماه</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['month_payroll'] ?? 0))) . ' تومان</strong></a>';
                    case 'kpi_assets':
                        return '<a class="stat-card kpi kpi-assets" href="admin.php?page=assets"><span><i class="sc-ico">' . $dash_icon('factory') . '</i>ارزش دفتری تجهیزات</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['asset_book_value'] ?? 0))) . ' تومان</strong></a>';
                }
                return '';
            };
            // --- نمودارهای SVG فانتزی (۹٫۱۶) ---
            $svgDonut = static function (array $items, int $size = 180): string {
                $total = array_sum(array_column($items, 'value'));
                if ($total <= 0) { return '<p class="muted">داده‌ای نیست</p>'; }
                $cx = $cy = $size / 2; $r = $size / 2 - 14; $inner = $r * 0.62;
                $circ = 2 * M_PI * $r; $offset = 0; $segs = ''; $uid = 'd' . substr(md5(json_encode($items)), 0, 6);
                $legend = '';
                foreach ($items as $i => $it) {
                    $frac = (float) $it['value'] / $total;
                    $len = $frac * $circ;
                    $color = (string) ($it['color'] ?? '#a7b8e0');
                    $segs .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="22" stroke-dasharray="%s %s" stroke-dashoffset="%s" stroke-linecap="round" transform="rotate(-90 %s %s)" class="donut-seg" style="animation-delay:%sms"/>',
                        $cx, $cy, $r, $color, max(0, $len - 3), $circ - max(0, $len - 3), -$offset, $cx, $cy, $i * 120);
                    $offset += $len;
                    $pct = round($frac * 100);
                    $legend .= '<div class="donut-legend-item"><span class="donut-dot" style="background:' . $color . '"></span>' . e((string) $it['label']) . ' <b>' . (int) $it['value'] . '</b> <span class="muted">' . $pct . '٪</span></div>';
                }
                return '<div class="donut-wrap"><svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
                    . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#e5e7eb" stroke-width="22"/>'
                    . $segs
                    . '<text x="' . $cx . '" y="' . ($cy - 4) . '" text-anchor="middle" class="donut-total">' . $total . '</text>'
                    . '<text x="' . $cx . '" y="' . ($cy + 18) . '" text-anchor="middle" class="donut-label">سفارش</text>'
                    . '</svg><div class="donut-legend">' . $legend . '</div></div>';
            };
            $svgArea = static function (array $data): string {
                // نمودار خطی سود/هزینه با گرادیان
                $w = 560; $h = 220; $pad = 36;
                $maxV = 1;
                foreach ($data as $d) { $maxV = max($maxV, $d['revenue'], $d['cost']); }
                $n = count($data);
                if ($n < 2) { return '<p class="muted">داده کافی نیست</p>'; }
                $x = static function ($i) use ($n, $w, $pad) { return $pad + ($i * ($w - 2 * $pad) / max(1, $n - 1)); };
                $y = static function ($v) use ($maxV, $h, $pad) { return $h - $pad - ($v / $maxV) * ($h - 2 * $pad); };
                $revPts = []; $costPts = [];
                foreach ($data as $i => $d) {
                    $revPts[] = round($x($i), 1) . ',' . round($y($d['revenue']), 1);
                    $costPts[] = round($x($i), 1) . ',' . round($y($d['cost']), 1);
                }
                $revLine = implode(' ', $revPts); $costLine = implode(' ', $costPts);
                $revArea = $pad . ',' . ($h - $pad) . ' ' . $revLine . ' ' . ($w - $pad) . ',' . ($h - $pad);
                $costArea = $pad . ',' . ($h - $pad) . ' ' . $costLine . ' ' . ($w - $pad) . ',' . ($h - $pad);
                $uid = 'a' . substr(md5($revLine), 0, 6);
                $dots = '';
                foreach ($data as $i => $d) {
                    $dots .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($d['revenue']), 1) . '" r="4.5" fill="#16a34a" stroke="#fff" stroke-width="2"><title>' . e($d['month']) . ': ' . number_format($d['revenue']) . '</title></circle>';
                    $dots .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($d['cost']), 1) . '" r="4.5" fill="#dc2626" stroke="#fff" stroke-width="2"><title>' . e($d['month']) . ': ' . number_format($d['cost']) . '</title></circle>';
                }
                $labels = '';
                foreach ($data as $i => $d) {
                    if ($i % 2 === 0) { $labels .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 10) . '" text-anchor="middle" class="chart-xlabel">' . e(substr($d['month'], 5)) . '</text>'; }
                }
                return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="fancy-chart neon-chart" role="img">'
                    . '<defs><linearGradient id="' . $uid . 'g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#00e676" stop-opacity=".4"/><stop offset="1" stop-color="#00e676" stop-opacity="0"/></linearGradient>'
                    . '<linearGradient id="' . $uid . 'r" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff5252" stop-opacity=".3"/><stop offset="1" stop-color="#ff5252" stop-opacity="0"/></linearGradient>'
                    . '<filter id="' . $uid . 'glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>'
                    . '<polygon points="' . $revArea . '" fill="url(#' . $uid . 'g)"/>'
                    . '<polygon points="' . $costArea . '" fill="url(#' . $uid . 'r)"/>'
                    . '<polyline points="' . $revLine . '" fill="none" stroke="#00e676" stroke-width="3.5" stroke-linecap="round" class="chart-line" filter="url(#' . $uid . 'glow)"/>'
                    . '<polyline points="' . $costLine . '" fill="none" stroke="#ff5252" stroke-width="3" stroke-linecap="round" stroke-dasharray="7 4" class="chart-line" filter="url(#' . $uid . 'glow)"/>'
                    . $dots . $labels
                    . '</svg><div class="chart-legend"><span><i style="background:#00e676;box-shadow:0 0 8px #00e676"></i>درآمد</span><span><i style="background:#ff5252;box-shadow:0 0 8px #ff5252"></i>هزینه</span></div>';
            };
            $svgFunnel = static function (array $stages): string {
                // قیف پایپ‌لاین سفارش
                $labels = ['new' => 'جدید', 'confirmed' => 'تأیید شده', 'in_production' => 'در تولید', 'ready' => 'آماده', 'delivered' => 'تحویل شده'];
                $colors = ['new' => '#93c5fd', 'confirmed' => '#6ee7b7', 'in_production' => '#fcd34d', 'ready' => '#c4b5fd', 'delivered' => '#6ee7b7'];
                $max = max(1, max($stages));
                $html = '<div class="funnel">';
                $prev = null;
                foreach ($labels as $k => $t) {
                    $v = (int) ($stages[$k] ?? 0);
                    $w = max(12, round($v / $max * 100));
                    $conv = '';
                    if ($prev !== null && $prev > 0) {
                        $pct = round($v / $prev * 100);
                        $conv = '<span class="funnel-conv">▼ ' . $pct . '٪</span>';
                    }
                    $html .= $conv . '<div class="funnel-bar" style="width:' . $w . '%;background:linear-gradient(135deg,' . $colors[$k] . ',' . $colors[$k] . 'cc)"><span>' . e($t) . '</span><b>' . $v . '</b></div>';
                    $prev = $v;
                }
                return $html . '</div>';
            };
            $svgTimeline = static function (array $data): string {
                // تایم‌لاین ۳۰ روزه میله‌ای
                $max = 1;
                foreach ($data as $d) { $max = max($max, $d['value']); }
                $bars = '';
                foreach ($data as $d) {
                    $h = max(4, round($d['value'] / $max * 100));
                    $bars .= '<div class="tl-bar" style="height:' . $h . '%" title="' . e($d['day']) . ': ' . (int) $d['value'] . ' سفارش"></div>';
                }
                return '<div class="timeline-chart">' . $bars . '</div><p class="muted center">سفارش‌های ۳۰ روز اخیر</p>';
            };
            // شمارنده بازدید امروز و این ماه — بدون ربات و ادمین (۹٫۲۲)
            $visitsToday = 0; $visitsMonth = 0;
            try {
                $visitsToday = (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND substr(created_at,1,10) = date('now')")->fetchColumn();
                $visitsMonth = (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND substr(created_at,1,7) = substr(date('now'),1,7)")->fetchColumn();
            } catch (Throwable $e) {}
            // بازدید روزانه ۱۴ روز اخیر — بدون ربات و ادمین (۹٫۲۲)
            $chartVisits = [];
            try {
                $vrows = $pdo->query("SELECT substr(created_at,1,10) as d, COUNT(*) as c FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND created_at >= date('now','-13 days') GROUP BY d ORDER BY d")->fetchAll(PDO::FETCH_ASSOC);
                $vmap = [];
                foreach ($vrows as $vr) { $vmap[$vr['d']] = (int) $vr['c']; }
                for ($i = 13; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-$i days"));
                    $chartVisits[] = ['day' => $d, 'value' => $vmap[$d] ?? 0];
                }
            } catch (Throwable $e) {}

            $renderChart = static function (string $key) use ($chartIncome, $chartOrderStatus, $chartExpenseCat, $chartProdStages, $chartProfit, $chartFunnel, $chartTimeline, $chartVisits, $renderBarChart, $dashWidgetPages, $chartJs): string {
                if (isset($dashWidgetPages[$key]) && !admin_can_page($dashWidgetPages[$key])) {
                    return '';
                }
                switch ($key) {
                    case 'chart_income':
                        $labels = array_column($chartIncome, 'month');
                        $vals = array_map(fn($r) => (int) $r['value'], $chartIncome);
                        return $labels === [] ? '' : '<section class="card"><h3>دریافتی ۶ ماه اخیر (تومان)</h3>' . $chartJs('bar', $labels, [['label' => 'دریافتی', 'data' => $vals]]) . '</section>';
                    case 'chart_orders':
                        $labels = array_column($chartOrderStatus, 'label');
                        $vals = array_column($chartOrderStatus, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>سفارش‌ها برحسب وضعیت</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]]) . '</section>';
                    case 'chart_expenses':
                        $labels = array_column($chartExpenseCat, 'label');
                        $vals = array_column($chartExpenseCat, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>هزینه‌ها برحسب دسته</h3>' . $chartJs('bar', $labels, [['label' => 'مبلغ', 'data' => $vals]]) . '</section>';
                    case 'chart_production':
                        $labels = array_column($chartProdStages, 'label');
                        $vals = array_column($chartProdStages, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>تولید برحسب مرحله</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]]) . '</section>';
                    case 'chart_donut_orders':
                        $labels = array_column($chartOrderStatus, 'label');
                        $vals = array_column($chartOrderStatus, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>توزیع سفارش‌ها</h3>' . $chartJs('doughnut', $labels, [['data' => $vals]]) . '</section>';
                    case 'chart_profit':
                        $labels = array_column($chartProfit, 'month');
                        $rev = array_column($chartProfit, 'revenue');
                        $cost = array_column($chartProfit, 'cost');
                        return $labels === [] ? '' : '<section class="card wide"><h3>درآمد در برابر هزینه — ۶ ماه اخیر</h3>' . $chartJs('line', $labels, [['label' => 'درآمد', 'data' => $rev], ['label' => 'هزینه', 'data' => $cost]]) . '</section>';
                    case 'chart_funnel':
                        $flabels = ['new' => 'جدید', 'confirmed' => 'تأیید شده', 'in_production' => 'در تولید', 'ready' => 'آماده', 'delivered' => 'تحویل شده'];
                        $labels = []; $vals = [];
                        foreach ($flabels as $k => $t) { $labels[] = $t; $vals[] = (int) ($chartFunnel[$k] ?? 0); }
                        return '<section class="card"><h3>قیف سفارشات</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]], ['indexAxis' => 'y']) . '</section>';
                    case 'chart_timeline':
                        $labels = array_map(fn($d) => substr($d['day'], 5), $chartTimeline);
                        $vals = array_column($chartTimeline, 'value');
                        return '<section class="card wide"><h3>تایم‌لاین سفارشات — ۳۰ روز اخیر</h3>' . $chartJs('bar', $labels, [['label' => 'سفارش', 'data' => $vals]]) . '</section>';
                    case 'chart_visits':
                        $vlabels = array_map(fn($d) => substr($d['day'], 5), $chartVisits);
                        $vvals = array_column($chartVisits, 'value');
                        $vtotal = array_sum($vvals);
                        return '<section class="card wide"><h3>بازدید روزانه — ۱۴ روز اخیر <span class="muted">(مجموع: ' . (int) $vtotal . ')</span></h3>' . $chartJs('line', $vlabels, [['label' => 'بازدید', 'data' => $vvals]]) . '</section>';
                }
                return '';
            };
            ?>
            <div class="dash-head">
                <h1>داشبورد</h1>
                <?php $nowTs = time(); $gregDate = date('Y/m/d', $nowTs); $gregWeekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; $gregW = $gregWeekdays[(int)date('w',$nowTs)]; ?>
                <span class="dash-date has-tooltip"><?= nav_icon('calendar') ?> <?= e(ll_jalali_today()) ?>
                    <span class="tooltip">
                        <strong><?= e(ll_jalali_format(date('Y-m-d H:i:s', $nowTs), false, true)) ?></strong><br>
                        <span class="muted"><?= e($gregDate) ?> · <?= e($gregW) ?></span>
                    </span>
                </span>
            </div>
            <p class="muted">نمای کلی پنل و دسترسی سریع به بخش‌های پرکاربرد. هر کاربر فقط کارت‌ها و نمودارهای مربوط به بخش‌های مجاز خودش را می‌بیند.</p>

            <?php if (admin_can_page('settings')): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn" data-toggle-panel="dash-customize-panel" aria-expanded="false">شخصی‌سازی داشبورد</button>
            </div>
            <div class="crud-panel" id="dash-customize-panel" hidden>
                <section class="card wide">
                    <h2 style="margin-top:0">ویجت‌های داشبورد</h2>
                    <p class="muted">تیک هر ویجت = نمایش آن؛ دکمه‌های ↑ و ↓ ترتیب نمایش را جابه‌جا می‌کنند. کارت‌های شرطی (مثل «در انتظار تأیید») فقط وقتی مقداری دارند نشان داده می‌شوند.</p>
                    <form method="post" id="dash-widget-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_dashboard">
                        <div id="dash-widget-rows">
                        <?php $allKeysOrdered = array_merge($dashEnabled, array_values(array_diff(array_keys($dashWidgetDefs), $dashEnabled))); ?>
                        <?php foreach ($allKeysOrdered as $wk): ?>
                            <div class="dash-wrow" style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px dashed #e5e7eb">
                                <label class="check" style="flex:1;margin:0"><input type="checkbox" name="on[<?= e($wk) ?>]" value="1"<?= isset($dashOn[$wk]) ? ' checked' : '' ?>> <?= e($dashWidgetDefs[$wk][0]) ?></label>
                                <input type="hidden" name="w[]" value="<?= e($wk) ?>">
                                <button type="button" class="btn small" data-move="up" title="بالا">↑</button>
                                <button type="button" class="btn small" data-move="down" title="پایین">↓</button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn add" style="margin-top:12px">💾 ذخیره چیدمان</button>
                    </form>
                    <script>
                    (function(){
                        var box = document.getElementById('dash-widget-rows');
                        if (!box) return;
                        box.addEventListener('click', function(ev){
                            var btn = ev.target.closest('button[data-move]');
                            if (!btn) return;
                            var row = btn.closest('.dash-wrow');
                            if (!row) return;
                            if (btn.getAttribute('data-move') === 'up' && row.previousElementSibling) {
                                box.insertBefore(row, row.previousElementSibling);
                            }
                            if (btn.getAttribute('data-move') === 'down' && row.nextElementSibling) {
                                box.insertBefore(row.nextElementSibling, row);
                            }
                        });
                    })();
                    </script>
                </section>
            </div>
            <?php endif; ?>

            <div class="flagship-hero">
                <span class="flagship-live">زنده</span>
                <h2>داشبورد لاینرلایت</h2>
                <p><?= e($shamsiDate ?? '') ?> — نمای کلی کسب‌وکار شما</p>
            </div>
            <div class="stat-grid dash-cards" id="dash-cards">
                <?php foreach ($dashEnabled as $wk): if (($dashWidgetDefs[$wk][1] ?? '') !== 'stat' || str_starts_with($wk, 'kpi_')) { continue; } echo $renderStat($wk); ?>
                <?php if ($wk === 'stat_orders' && $lowStockCount > 0 && admin_can_page('materials')): ?>
                <a class="stat-card sc-red sc-alert-red" href="admin.php?page=materials"><span><i class="sc-ico">' . $dash_icon('alert') . '</i>مواد رو به اتمام</span><strong><?= (int) $lowStockCount ?> ماده</strong></a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="stat-grid dash-kpi" id="dash-kpi">
                <?php foreach ($dashEnabled as $wk): if (!str_starts_with($wk, 'kpi_')) { continue; } echo $renderStat($wk); endforeach; ?>
            </div>

            <?php $hasChart = false; foreach ($dashEnabled as $wk) { if (($dashWidgetDefs[$wk][1] ?? '') === 'chart' && (!isset($dashWidgetPages[$wk]) || admin_can_page($dashWidgetPages[$wk]))) { $hasChart = true; break; } } ?>
            <?php if ($hasChart): ?>
            <div class="stat-grid dash-charts" id="dash-charts">
                <?php foreach ($dashEnabled as $wk): if (($dashWidgetDefs[$wk][1] ?? '') !== 'chart') { continue; } echo $renderChart($wk); endforeach; ?>
            </div>
            <?php endif; ?>

            <script src="assets/sortable.min.js"></script>
            <script>
            (function(){
                if (typeof Sortable === 'undefined') return;
                ['dash-cards', 'dash-kpi', 'dash-charts'].forEach(function(id){
                    var el = document.getElementById(id);
                    if (!el) return;
                    // بازیابی ترتیب ذخیره‌شده
                    var saved = null;
                    try { saved = JSON.parse(localStorage.getItem('dash-order-' + id) || 'null'); } catch(e){}
                    if (Array.isArray(saved) && saved.length) {
                        var items = Array.from(el.children);
                        var map = {};
                        items.forEach(function(c){ var k = c.dataset.widget || c.textContent.trim().slice(0,30); map[k] = c; });
                        saved.forEach(function(k){ if (map[k]) el.appendChild(map[k]); });
                    }
                    // فعال‌سازی درگ
                    Sortable.create(el, {
                        animation: 200,
                        delay: 250,
                        delayOnTouchOnly: true,
                        ghostClass: 'dash-drag-ghost',
                        chosenClass: 'dash-drag-chosen',
                        dragClass: 'dash-drag-active',
                        preventOnFilter: false,
                        onStart: function(){ document.body.classList.add('dash-dragging'); },
                        onEnd: function(evt){
                            document.body.classList.remove('dash-dragging');
                            var order = Array.from(el.children).map(function(c, i){ return 'w' + i + ':' + (c.textContent.trim().slice(0,20)); });
                            try { localStorage.setItem('dash-order-' + id, JSON.stringify(order)); } catch(e){}
                        },
                        onMove: function(){ return true; }
                    });
                    // جلوگیری از کلیک لینک بعد از درگ
                    el.addEventListener('click', function(e){
                        if (document.body.classList.contains('dash-dragging')) { e.preventDefault(); e.stopPropagation(); }
                    }, true);
                });
            })();
            </script>
            <style>
            .dash-drag-ghost{opacity:.4;background:#dbeafe!important}
            .dash-drag-chosen{box-shadow:0 8px 24px rgba(37,99,235,.3)!important}
            .dash-drag-active{opacity:.9}
            body.dash-dragging #dash-cards .stat-card, body.dash-dragging #dash-kpi .stat-card{pointer-events:none}
            body.dash-dragging{user-select:none;-webkit-user-select:none}
            #dash-cards .stat-card, #dash-kpi .stat-card{cursor:grab;touch-action:pan-y}
            #dash-cards .stat-card:active, #dash-kpi .stat-card:active{cursor:grabbing}
            </style>
            <?php if ($lowStockCount > 0 && admin_can_page('materials')): ?>
            <div class="alert error">
                موجودی این مواد به حد هشدار رسیده است:
                <?php foreach ($lowStockList as $lm): ?>
                    <strong><?= e($lm['name']) ?></strong> (<?= e(format_qty((float) $lm['stock_qty'])) ?> <?= e($lm['unit']) ?>)
                <?php endforeach; ?>
                — <a href="admin.php?page=materials">رفتن به انبار</a>
            </div>
            <?php endif; ?>

        <?php elseif ($page === 'sections'): ?>
            <h1>بخش‌های صفحه اصلی</h1>
            <p class="muted">صفحه اصلی (index.php) بخش‌های فعال را دقیقاً به همین ترتیب نمایش می‌دهد. هر بخش محتوای خودش (تیتر، متن، عکس، لینک) و یک قالب دارد که متن و چیدمانش داخل دیتابیس است و از صفحه «قالب و استایل» ویرایش می‌شود. برای فرم تماس، یک بخش با قالب «تماس با ما» بسازید. صفحه‌های جدا هم با قالب «صفحه تکی» نمایش داده می‌شوند.</p>

            <?php if ($editSection === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="section-form-panel" aria-expanded="false">+ افزودن بخش جدید</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="section-form-panel" <?= $editSection !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editSection ? 'ویرایش بخش: ' . e($editSection['title'] ?? '') : 'افزودن بخش جدید' ?></h2>
            <form method="post" class="card wide" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editSection ? 'update_section' : 'add_section' ?>">
                <?php if ($editSection): ?><input type="hidden" name="id" value="<?= (int) $editSection['id'] ?>"><?php endif; ?>
                <label>عنوان بخش (داخلی، برای مدیریت)
                    <input type="text" name="title" required value="<?= e($editSection['title'] ?? '') ?>" placeholder="مثلاً اسلایدر اصلی">
                </label>
                <label>قالب بخش
                    <select name="template_file" required>
                        <?php foreach ($templates as $t):
                            $tKey = template_key_from_file($t);
                        ?>
                            <option value="<?= e($t) ?>" <?= ($editSection && $editSection['template_file'] === $t) ? 'selected' : '' ?>><?= e($templateTitles[$tKey] ?? $tKey) ?> (<?= e($tKey) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php if ($editSection && !in_array($editSection['template_file'], $templates, true)): ?>
                    <div class="alert error">قالب فعلی این بخش («<?= e($editSection['template_file']) ?>») در دیتابیس پیدا نشد؛ هنگام نمایش سایت، قالب «محتوا» جایگزینش می‌شود. یک قالب از فهرست بالا انتخاب و ذخیره کنید.</div>
                <?php endif; ?>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= e($editSection['sort_order'] ?? '10') ?>">
                </label>
                <label>تیتر نمایشی بخش
                    <input type="text" name="heading" value="<?= e($editSection['heading'] ?? '') ?>" placeholder="اگر خالی باشد، عنوان پیش‌فرض قالب نشان داده می‌شود">
                </label>
                <label>متن بخش <span class="muted">(HTML ساده مجاز است؛ فقط مدیر سایت این را می‌نویسد)</span>
                    <textarea name="body" rows="10" dir="ltr" spellcheck="false" class="code-editor" data-mode="htmlmixed"><?= e($editSection['body'] ?? '') ?></textarea>
                </label>
                <label>عکس بخش <span class="muted">(jpg/png/webp/gif، حداکثر ۳ مگابایت؛ در اسلایدر پس‌زمینه می‌شود)</span>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
                </label>
                <?php if ($editSection && !empty($editSection['image'])): ?>
                    <p class="muted">عکس فعلی: <code><?= e($editSection['image']) ?></code></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>آدرس لینک دکمه (اختیاری)
                    <input type="text" name="link_url" value="<?= e($editSection['link_url'] ?? '') ?>" placeholder="https://example.com یا page.php?slug=about" dir="ltr">
                </label>
                <label>متن دکمه لینک
                    <input type="text" name="link_text" value="<?= e($editSection['link_text'] ?? '') ?>" placeholder="مثلاً اطلاعات بیشتر">
                </label>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editSection || (int) $editSection['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
                <button type="submit" class="btn <?= $editSection ? 'edit' : 'add' ?>"><?= $editSection ? 'ذخیره ویرایش' : 'افزودن بخش' ?></button>
                <?php if ($editSection): ?><a class="btn" href="admin.php?page=sections">انصراف</a><?php endif; ?>
            </form>
            </div>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>قالب</th><th>عکس</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($sections as $s): ?>
                    <tr>
                        <td><?= (int) $s['sort_order'] ?></td>
                        <td><?= e($s['title']) ?></td>
                        <td><?= e($templateTitles[section_template_key($s)] ?? section_template_key($s)) ?> <span class="muted">(<code><?= e(section_template_key($s)) ?></code>)</span></td>
                        <td><?= !empty($s['image']) ? 'دارد' : '<span class="muted">—</span>' ?></td>
                        <td><?= (int) $s['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small">↑ بالا</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small">↓ پایین</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="btn small warn"><?= (int) $s['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form>
                            <a class="btn small edit" href="admin.php?page=sections&edit_id=<?= (int) $s['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این بخش حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_section"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($sections === []): ?><tr><td colspan="6" class="muted">هنوز بخشی ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'pages'): ?>
            <h1>صفحه‌ها</h1>
            <p class="muted">هر صفحه آدرس جدا دارد: <code>page.php?slug=نامک</code>. اگر «نمایش در منو» فعال باشد، لینکش خودکار به منوی سایت (هدر و قالب تک‌صفحه) اضافه می‌شود. لینک «خانه» همیشه اول منو است.</p>

            <?php if ($editPage === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="page-form-panel" aria-expanded="false">+ ساخت صفحه جدید</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="page-form-panel" <?= $editPage !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editPage ? 'ویرایش صفحه: ' . e($editPage['title'] ?? '') : 'ساخت صفحه جدید' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editPage ? 'update_page' : 'add_page' ?>">
                <?php if ($editPage): ?><input type="hidden" name="id" value="<?= (int) $editPage['id'] ?>"><?php endif; ?>
                <label>عنوان صفحه
                    <input type="text" name="title" required value="<?= e($editPage['title'] ?? '') ?>" placeholder="مثلاً درباره ما">
                </label>
                <label>نامک (slug) — فقط حروف انگلیسی، عدد، خط تیره و آندرلاین
                    <span class="file-input">page.php?slug=<input type="text" name="slug" required pattern="[A-Za-z0-9\-_]+" value="<?= e($editPage['slug'] ?? '') ?>" placeholder="about"></span>
                </label>
                <label>محتوای صفحه
                    <div class="editor-tabs">
                        <button type="button" class="btn small active" data-etab="visual">ویرایشگر بصری</button>
                        <button type="button" class="btn small" data-etab="code">کد HTML</button>
                    </div>
                    <div id="gjs-wrap" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <div id="gjs" style="height:500px"></div>
                    </div>
                    <textarea name="content" id="page-content-code" rows="10" class="code-editor" data-mode="htmlmixed" dir="ltr" style="display:none"><?= e($editPage['content'] ?? '') ?></textarea>
                </label>
                <link rel="stylesheet" href="assets/grapes.min.css">
                <script src="assets/grapes.min.js"></script>
                <script>
                (function(){
                    if (typeof grapesjs === 'undefined') return;
                    var codeArea = document.getElementById('page-content-code');
                    var gjsWrap = document.getElementById('gjs-wrap');
                    var editor = grapesjs.init({
                        container: '#gjs',
                        fromElement: false,
                        height: '600px',
                        width: 'auto',
                        storageManager: false,
                        deviceManager: {
                            devices: [
                                { name: 'دسکتاپ', width: '' },
                                { name: 'تبلت', width: '768px', widthMedia: '992px' },
                                { name: 'موبایل', width: '375px', widthMedia: '768px' },
                            ]
                        },
                        panels: {
                            defaults: [
                                { id: 'basic-actions', el: '.gjs-pn-basic', buttons: [
                                    { id: 'undo', className: 'fa fa-undo', command: 'core:undo', attributes: { title: 'برگردان' } },
                                    { id: 'redo', className: 'fa fa-repeat', command: 'core:redo', attributes: { title: 'جلوبر' } },
                                    { id: 'fullscreen', className: 'fa fa-expand', command: 'gjs-fullscreen', attributes: { title: 'تمام‌صفحه' } },
                                    { id: 'preview-devices', className: '', buttons: [
                                        { id: 'device-desktop', className: 'fa fa-desktop', command: 'set-device-desktop', attributes: { title: 'دسکتاپ' } },
                                        { id: 'device-tablet', className: 'fa fa-tablet', command: 'set-device-tablet', attributes: { title: 'تبلت' } },
                                        { id: 'device-mobile', className: 'fa fa-mobile', command: 'set-device-mobile', attributes: { title: 'موبایل' } },
                                    ]},
                                ]},
                                { id: 'panel-blocks', el: '.gjs-pn-blocks', buttons: [{ id: 'show-blocks', active: true, label: 'بلوک‌ها', command: 'show-blocks' }] },
                                { id: 'panel-layers', el: '.gjs-pn-layers', buttons: [{ id: 'show-layers', label: 'لایه‌ها', command: 'show-layers' }] },
                                { id: 'panel-styles', el: '.gjs-pn-styles', buttons: [{ id: 'show-styles', label: 'استایل', command: 'show-styles' }] },
                            ]
                        },
                        blockManager: {
                            appendTo: '#gjs-blocks',
                            blocks: [
                                { id: 'hero', label: 'هیرو', category: 'سکشن', content: '<section style="padding:80px 20px;text-align:center;background:linear-gradient(135deg,#1a1a2e,#16213e);color:#fff"><h1 style="font-size:36px;margin-bottom:16px">تیتر اصلی</h1><p style="font-size:18px;opacity:.8;margin-bottom:24px">توضیح کوتاه</p><a href="#" style="display:inline-block;padding:14px 36px;background:#d4a017;color:#fff;border-radius:12px;text-decoration:none;font-weight:700">دکمه</a></section>' },
                                { id: 'features', label: 'ویژگی‌ها', category: 'سکشن', content: '<section style="padding:60px 20px"><div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;max-width:1000px;margin:0 auto"><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">⭐</div><h3>ویژگی ۱</h3><p>توضیح</p></div><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">🚀</div><h3>ویژگی ۲</h3><p>توضیح</p></div><div style="text-align:center;padding:24px"><div style="font-size:40px;margin-bottom:12px">💡</div><h3>ویژگی ۳</h3><p>توضیح</p></div></div></section>' },
                                { id: 'cta', label: 'دعوت به اقدام', category: 'سکشن', content: '<section style="padding:60px 20px;background:#fef9c3;text-align:center"><h2>آماده‌اید؟</h2><p>همین حالا شروع کنید</p><a href="#" style="display:inline-block;padding:14px 36px;background:#16a34a;color:#fff;border-radius:12px;text-decoration:none;font-weight:700;margin-top:16px">شروع</a></section>' },
                                { id: 'faq', label: 'سؤالات متداول', category: 'سکشن', content: '<section style="padding:60px 20px;max-width:800px;margin:0 auto"><h2 style="text-align:center;margin-bottom:32px">سؤالات متداول</h2><details style="margin-bottom:12px;padding:16px;border:1px solid #e5e7eb;border-radius:10px"><summary style="font-weight:700;cursor:pointer">سؤال ۱؟</summary><p style="margin-top:8px">پاسخ...</p></details><details style="margin-bottom:12px;padding:16px;border:1px solid #e5e7eb;border-radius:10px"><summary style="font-weight:700;cursor:pointer">سؤال ۲؟</summary><p style="margin-top:8px">پاسخ...</p></details></section>' },
                                { id: 'text', label: 'متن', category: 'پایه', content: '<p>متن خود را اینجا بنویسید</p>' },
                                { id: 'heading', label: 'تیتر', category: 'پایه', content: '<h2>تیتر</h2>' },
                                { id: 'image', label: 'عکس', category: 'پایه', content: { type: 'image' } },
                                { id: 'columns-2', label: 'دو ستون', category: 'چیدمان', content: '<div style="display:flex;gap:16px"><div style="flex:1"><p>ستون ۱</p></div><div style="flex:1"><p>ستون ۲</p></div></div>' },
                                { id: 'columns-3', label: 'سه ستون', category: 'چیدمان', content: '<div style="display:flex;gap:16px"><div style="flex:1"><p>ستون ۱</p></div><div style="flex:1"><p>ستون ۲</p></div><div style="flex:1"><p>ستون ۳</p></div></div>' },
                                { id: 'button', label: 'دکمه', category: 'پایه', content: '<a href="#" style="display:inline-block;padding:12px 28px;background:#d4a017;color:#fff;border-radius:10px;text-decoration:none">دکمه</a>' },
                                { id: 'divider', label: 'جداکننده', category: 'پایه', content: '<hr>' },
                                { id: 'video', label: 'ویدیو', category: 'رسانه', content: '<video controls style="width:100%;border-radius:12px"><source src="" type="video/mp4"></video>' },
                                { id: 'map', label: 'نقشه', category: 'رسانه', content: '<iframe src="https://www.google.com/maps/embed?pb=..." style="width:100%;height:300px;border:0;border-radius:12px" loading="lazy"></iframe>' },
                            ]
                        },
                        styleManager: {
                            appendTo: '.gjs-pn-styles',
                            sectors: [
                                { name: 'ابعاد', open: false, properties: ['width', 'height', 'max-width', 'padding', 'margin'] },
                                { name: 'تایپوگرافی', open: false, properties: ['font-family', 'font-size', 'font-weight', 'color', 'line-height', 'text-align'] },
                                { name: 'پس‌زمینه', open: false, properties: ['background-color', 'background'] },
                                { name: 'کادر', open: false, properties: ['border', 'border-radius'] },
                            ]
                        },
                        layerManager: { appendTo: '.gjs-pn-layers' },
                    });
                    // دستور تمام‌صفحه
                    editor.Commands.add('gjs-fullscreen', {
                        run: function(ed) { gjsWrap.classList.add('gjs-fullscreen'); ed.refresh(); },
                        stop: function(ed) { gjsWrap.classList.remove('gjs-fullscreen'); ed.refresh(); },
                    });
                    // دستورات دستگاه
                    ['desktop', 'tablet', 'mobile'].forEach(function(d){
                        editor.Commands.add('set-device-' + d, { run: function(ed){ ed.setDevice(d === 'desktop' ? 'دسکتاپ' : d === 'tablet' ? 'تبلت' : 'موبایل'); } });
                    });
                    // نمایش پنل‌ها
                    editor.Commands.add('show-blocks', { run: function(ed){ document.getElementById('gjs-blocks').style.display = ''; document.querySelector('.gjs-pn-layers').style.display = 'none'; document.querySelector('.gjs-pn-styles').style.display = 'none'; }, stop: function(){} });
                    editor.Commands.add('show-layers', { run: function(){ document.getElementById('gjs-blocks').style.display = 'none'; document.querySelector('.gjs-pn-layers').style.display = ''; document.querySelector('.gjs-pn-styles').style.display = 'none'; }, stop: function(){} });
                    editor.Commands.add('show-styles', { run: function(){ document.getElementById('gjs-blocks').style.display = 'none'; document.querySelector('.gjs-pn-layers').style.display = 'none'; document.querySelector('.gjs-pn-styles').style.display = ''; }, stop: function(){} });
                    // بارگذاری محتوای فعلی
                    editor.setComponents(codeArea.value || '<p>محتوای صفحه...</p>');
                    // تب‌ها
                    var tabs = document.querySelectorAll('[data-etab]');
                    var gjsWrap = document.getElementById('gjs-wrap');
                    tabs.forEach(function(btn){
                        btn.addEventListener('click', function(){
                            tabs.forEach(function(b){ b.classList.remove('active'); });
                            btn.classList.add('active');
                            if (btn.dataset.etab === 'visual') {
                                gjsWrap.style.display = '';
                                codeArea.style.display = 'none';
                                editor.setComponents(codeArea.value);
                            } else {
                                codeArea.value = editor.getHtml();
                                gjsWrap.style.display = 'none';
                                codeArea.style.display = '';
                            }
                        });
                    });
                    // هنگام ارسال فرم، HTML را به textarea برگردان
                    codeArea.closest('form').addEventListener('submit', function(){
                        if (gjsWrap.style.display !== 'none') {
                            codeArea.value = editor.getHtml();
                        }
                    });
                })();
                </script>
                <style>
                .editor-tabs{display:flex;gap:8px;margin-bottom:10px}
                .editor-tabs .btn.active{background:#2563eb;color:#fff;border-color:#2563eb}
                #gjs-blocks{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
                #gjs-wrap.gjs-fullscreen{position:fixed;inset:0;z-index:9999;background:#fff}
                #gjs-wrap.gjs-fullscreen #gjs{height:100vh!important}
                .gjs-pn-panel{display:flex;gap:4px;padding:6px;background:#f8fafc;border-bottom:1px solid #e5e7eb}
                .gjs-pn-btn{padding:6px 10px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;cursor:pointer;font-size:12px}
                .gjs-pn-btn.gjs-pn-active{background:#2563eb;color:#fff;border-color:#2563eb}
                .gjs-pn-layers,.gjs-pn-styles{display:none;max-height:400px;overflow:auto;padding:10px;background:#f8fafc}
                </style>
                <div class="gjs-pn-panel gjs-pn-basic"></div>
                <div class="gjs-pn-panel gjs-pn-blocks"></div>
                <div class="gjs-pn-panel gjs-pn-layers"></div>
                <div class="gjs-pn-panel gjs-pn-styles"></div>
                <label>عنوان سئو (SEO) — اگر خالی باشد عنوان صفحه استفاده می‌شود
                    <input type="text" name="seo_title" value="<?= e($editPage['seo_title'] ?? '') ?>">
                </label>
                <label>توضیح سئو (meta description)
                    <textarea name="seo_description" rows="2"><?= e($editPage['seo_description'] ?? '') ?></textarea>
                </label>
                <label>ترتیب در منو/لیست
                    <input type="number" name="sort_order" value="<?= e($editPage['sort_order'] ?? '10') ?>">
                </label>
                <label class="check"><input type="checkbox" name="show_in_menu" value="1" <?= ($editPage && (int) $editPage['show_in_menu'] === 1) ? 'checked' : '' ?>> نمایش در منوی سایت</label>
                <label class="check"><input type="checkbox" name="is_active" value="1" <?= (!$editPage || (int) $editPage['is_active'] === 1) ? 'checked' : '' ?>> فعال باشد</label>
                <button type="submit" class="btn <?= $editPage ? 'edit' : 'add' ?>"><?= $editPage ? 'ذخیره ویرایش' : 'ساخت صفحه' ?></button>
                <?php if ($editPage): ?><a class="btn" href="admin.php?page=pages">انصراف</a><?php endif; ?>
            </form>
            </div>

            <?php if ($editPage !== null): ?>
                <?php /* admin_blocks_ui.php به modules/admin/UI/Blocks.php منتقل شد (۹٫۹۹٫۱۹) */ ?>
            <?php endif; ?>

            <table>
                <thead><tr><th>ترتیب</th><th>عنوان</th><th>نامک (slug)</th><th>در منو</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($pages as $p): ?>
                    <tr>
                        <td><?= (int) $p['sort_order'] ?></td>
                        <td><?= e($p['title']) ?></td>
                        <td><code><?= e($p['slug']) ?></code><br><a href="/<?= urlencode((string) $p['slug']) ?>" target="_blank">مشاهده</a></td>
                        <td><?= (int) $p['show_in_menu'] === 1 ? 'بله' : '<span class="muted">خیر</span>' ?></td>
                        <td><?= (int) $p['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small">↑ بالا</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small">↓ پایین</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn small warn"><?= (int) $p['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form>
                            <?php if ((string) ($p['slug'] ?? '') === 'gallery'): ?>
                            <a class="btn small add" href="admin.php?page=gallery">🖼 مدیریت گالری</a>
                            <?php else: ?>
                            <a class="btn small edit" href="admin.php?page=pages&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                            <?php endif; ?>
                            <form method="post" class="inline" onsubmit="return confirm('این صفحه حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_page"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($pages === []): ?><tr><td colspan="6" class="muted">هنوز صفحه‌ای ساخته نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'gallery'): ?>
            <?php /* admin_gallery_ui.php به modules/admin/UI/Gallery.php منتقل شد (۹٫۹۹٫۱۹) */ ?>

        <?php elseif ($page === 'design'): ?>
            <h1>قالب و استایل</h1>
            <p class="muted">از نسخه ۵، متن قالب‌ها و CSS سایت داخل دیتابیس نگه داشته می‌شوند و فقط اسکلت صفحه (سربرگ سند، منوی موبایل و اسکریپت‌های سبک) در فایل‌هاست؛ پس قالب و CSS با آپدیت یک‌کلیکی گیت‌هاب پاک نمی‌شوند و همه‌چیز از همین‌جا قابل ویرایش و بازیابی است. داخل قالب‌ها کد PHP هرگز اجرا نمی‌شود؛ فقط پلیس‌هولدرهای امن پشتیبانی می‌شوند. قبل از هر ذخیره یا بازنشانی، نسخه قبلی به‌صورت خودکار در «تاریخچه نسخه‌ها» می‌ماند تا اشتباه‌ها برگشت‌پذیر باشند.</p>

            <?php foreach ($legacyTplWarnings as $w): ?>
                <div class="alert error">قالب قدیمی «<?= e($w['item_key']) ?>» در نسخه فایل‌محور سفارشی بوده و برای امنیت به قالب دیتابیس تبدیل و فعال نشده است؛ الان نسخه کارخانه‌ای همین قالب در سایت فعال است و سورس خام فایل قدیمی فقط در جدول تاریخچه نگه داشته شده تا اگر خواستید دستی و امن تبدیلش کنید.</div>
            <?php endforeach; ?>
            <?php if ($legacyCssWarnings !== []): ?>
                <div class="alert error">فایل <code>style.css</code> قدیمی سفارشی بوده است؛ محتوای آن بدون اجرا داخل «CSS سفارشی» قرار گرفت تا ظاهر سایت حفظ شود. از تب «CSS و ظاهر» می‌توانید بررسی، ویرایش یا حذفش کنید.</div>
            <?php endif; ?>

            <nav class="design-tabs">
                <a href="admin.php?page=design&tab=themes" class="<?= $designTab === 'themes' ? 'active' : '' ?>">قالب‌های آماده</a>
                <a href="admin.php?page=design&tab=templates" class="<?= $designTab === 'templates' ? 'active' : '' ?>">قالب‌ها</a>
                <a href="admin.php?page=design&tab=css" class="<?= $designTab === 'css' ? 'active' : '' ?>">CSS و ظاهر</a>
            </nav>

            <?php if ($designTab === 'themes'): ?>
                <?php $themeDefs = site_theme_definitions(); $activeTheme = (string) get_setting('site_theme', 'cinematic'); ?>
                <p class="muted">سه قالب آماده و مدرن برای ظاهر سایت. با یک کلیک قالب عوض می‌شود؛ نسخه قبلی CSS در «تاریخچه نسخه‌ها» می‌ماند تا برگشت‌پذیر باشد. تنظیمات ظاهری و CSS سفارشی شما حفظ می‌شوند.</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;margin:16px 0">
                <?php foreach ($themeDefs as $tKey => $tDef): ?>
                    <div class="card" style="<?= $tKey === $activeTheme ? 'border:2px solid #c9a227;' : '' ?>">
                        <div style="display:flex;gap:6px;margin-bottom:12px">
                            <?php foreach ((array) $tDef['swatches'] as $sw): ?>
                                <span style="width:36px;height:36px;border-radius:10px;background:<?= e($sw) ?>;border:1px solid #0002;display:inline-block"></span>
                            <?php endforeach; ?>
                        </div>
                        <h3 style="margin:0 0 6px"><?= e($tDef['title']) ?>
                            <?php if ($tKey === $activeTheme): ?><span class="badge ok">فعال</span><?php endif; ?>
                        </h3>
                        <p class="muted" style="min-height:48px"><?= e($tDef['desc']) ?></p>
                        <?php if ($tKey !== $activeTheme): ?>
                        <form method="post" onsubmit="return confirm('قالب «<?= e($tDef['title']) ?>» فعال شود؟ CSS فعلی در تاریخچه می‌ماند.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="apply_theme">
                            <input type="hidden" name="theme" value="<?= e($tKey) ?>">
                            <button type="submit" class="btn primary">فعال‌سازی این قالب</button>
                        </form>
                        <?php else: ?>
                        <a class="btn" href="index.php" target="_blank" rel="noopener">مشاهده سایت</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>

            <?php elseif ($designTab === 'templates'): ?>
                <table>
                    <thead><tr><th>قالب</th><th>نوع</th><th>آخرین ویرایش</th><th>استفاده در بخش‌ها</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbTemplates as $row):
                        $k = (string) $row['template_key'];
                    ?>
                        <tr>
                            <td><?= e($row['title']) ?><br><span class="muted">کلید: <code><?= e($k) ?></code></span></td>
                            <td><?= (int) $row['is_system'] === 1 ? '<span class="badge ok">سیستمی</span>' : '<span class="badge off">سفارشی</span>' ?></td>
                            <td><?= e($row['updated_at']) ?></td>
                            <td><?= !empty($tplUsedIn[$k]) ? e(implode('، ', $tplUsedIn[$k])) : '<span class="muted">بلااستفاده</span>' ?></td>
                            <td class="actions">
                                <a class="btn small edit" href="admin.php?page=design&tab=templates&edit_tpl=<?= urlencode($k) ?>">ویرایش</a>
                                <?php if ((int) $row['is_system'] !== 1 && empty($tplUsedIn[$k])): ?>
                                <form method="post" class="inline" onsubmit="return confirm('این قالب سفارشی حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_db_template"><input type="hidden" name="template_key" value="<?= e($k) ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dbTemplates === []): ?><tr><td colspan="5" class="muted">هنوز قالبی در دیتابیس ثبت نشده است.</td></tr><?php endif; ?>
                    </tbody>
                </table>

                <div class="crud-toolbar">
                    <button type="button" class="btn" data-toggle-panel="tpl-help-panel" aria-expanded="false">؟ راهنمای پلیس‌هولدرها</button>
                    <?php if ($editTplRow === null): ?>
                    <button type="button" class="btn add" data-toggle-panel="tpl-add-panel" aria-expanded="false">+ ساخت قالب سفارشی</button>
                    <?php endif; ?>
                </div>
                <div class="help-panel" id="tpl-help-panel" hidden>
                    <h2>راهنمای پلیس‌هولدرها</h2>
                    <p class="muted">متن قالب HTML ساده است و این نشانه‌ها هنگام نمایش با محتوای واقعی جایگزین می‌شوند؛ مقادیر متنی خودکار امن‌سازی (escape) می‌شوند:</p>
                    <ul class="ph-list">
                        <li><code>{{site_title}}</code> عنوان سایت</li>
                        <li><code>{{site_description}}</code> توضیح سایت</li>
                        <li><code>{{menu}}</code> منوی سایت (با دکمه موبایل و تغییر تم)</li>
                        <li><code>{{current_year}}</code> سال جاری</li>
                        <li><code>{{section_title}}</code> عنوان داخلی بخش</li>
                        <li><code>{{section_heading}}</code> تیتر نمایشی بخش</li>
                        <li><code>{{section_body}}</code> متن بخش (HTML ساده مجاز است)</li>
                        <li><code>{{section_image}}</code> تگ عکس بخش (اگر عکس داشته باشد)</li>
                        <li><code>{{section_image_url}}</code> آدرس عکس بخش</li>
                        <li><code>{{section_link_url}}</code> آدرس دکمه بخش</li>
                        <li><code>{{section_link_text}}</code> متن دکمه بخش</li>
                        <li><code>{{page_title}}</code> و <code>{{page_content}}</code> عنوان و محتوای صفحه (در قالب صفحه تکی)</li>
                        <li><code>{{catalog_title}}</code> عنوان کاتالوگ (قالب‌های کاتالوگ و محصول — فاز ۲)</li>
                        <li><code>{{categories_nav}}</code> ناو دسته‌بندی‌های کاتالوگ (قالب کاتالوگ)</li>
                        <li><code>{{products_grid}}</code> شبکه کارت‌های محصولات (قالب کاتالوگ)</li>
                        <li><code>{{product_name}}</code> نام محصول (قالب محصول)</li>
                        <li><code>{{product_image}}</code> آدرس عکس محصول (قالب محصول)</li>
                        <li><code>{{product_description}}</code> توضیح محصول (قالب محصول)</li>
                        <li><code>{{category_title}}</code> عنوان دسته محصول/فیلتر فعال</li>
                        <li><code>{{price_per_meter_formatted}}</code> قیمت متری مشتری، قالب‌بندی‌شده (قالب محصول)</li>
                        <li><code>{{partner_price_per_meter_formatted}}</code> قیمت متری همکار، قالب‌بندی‌شده (اگر با مشتری فرق داشته باشد)</li>
                        <li><code>{{product_specs}}</code> جدول مشخصات محصول از ویژگی‌ها (قالب محصول)</li>
                        <li><code>{{attributes_options}}</code> انتخاب‌های آپشن (قالب محصول)</li>
                        <li><code>{{estimator}}</code> برآوردگر زنده قیمت: طول + آپشن‌ها (قالب محصول)</li>
                        <li><code>{{slider_slides}}</code> کاروسل عکس همه بخش‌های عکس‌دار</li>
                        <li><code>{{contact_form}}</code> فرم تماس آماده (فقط در قالب تماس)</li>
                        <li>بلوک شرطی: <code>{{#if section_image_url}}...{{else}}...{{/if}}</code> — اگر مقدار خالی نباشد بخش اول، وگرنه بخش دوم نشان داده می‌شود؛ شرط‌ها می‌توانند تو در تو باشند.</li>
                    </ul>
                    <p class="muted">نکته امنیتی: کد PHP داخل متن قالب هیچ‌وقت اجرا نمی‌شود و مثل متن ساده چاپ می‌شود؛ پس با خیال راحت قالب را ویرایش کنید.</p>
                </div>

                <?php if ($editTplRow === null): ?>
                <div class="crud-panel" id="tpl-add-panel" hidden>
                <h2>ساخت قالب سفارشی</h2>
                <form method="post" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_db_template">
                    <label>کلید قالب (فقط حروف کوچک انگلیسی، عدد و آندرلاین)
                        <input type="text" name="template_key" required pattern="[a-z0-9_]+" placeholder="promo" dir="ltr">
                    </label>
                    <label>عنوان نمایشی
                        <input type="text" name="template_title" placeholder="مثلاً بنر تبلیغاتی">
                    </label>
                    <button type="submit" class="btn add">ساخت قالب</button>
                </form>
                </div>
                <?php endif; ?>

                <?php if ($editTplRow !== null): ?>
                    <h2>ویرایش قالب: <?= e($editTplRow['title']) ?> <span class="muted">(کلید: <code><?= e($editTplRow['template_key']) ?></code>)</span></h2>
                    <form method="post" class="card wide">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_db_template">
                        <input type="hidden" name="template_key" value="<?= e($editTplRow['template_key']) ?>">
                        <textarea name="content" rows="20" dir="ltr" spellcheck="false" class="code-editor" data-mode="htmlmixed"><?= e($editTplRow['content']) ?></textarea>
                        <button type="submit" class="btn primary">ذخیره قالب</button>
                        <a class="btn" href="index.php" target="_blank">مشاهده سایت</a>
                    </form>
                    <?php if ((int) $editTplRow['is_system'] === 1): ?>
                    <form method="post" class="card" onsubmit="return confirm('قالب به نسخه کارخانه‌ای برگردد؟ نسخه فعلی در تاریخچه نگه داشته می‌شود.')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="reset_db_template">
                        <input type="hidden" name="template_key" value="<?= e($editTplRow['template_key']) ?>">
                        <p class="muted">اگر قالب را خراب کرده‌اید، با این دکمه به نسخه پیش‌فرض کارخانه برمی‌گردد و جای نگرانی نیست.</p>
                        <button type="submit" class="btn">بازنشانی به نسخه کارخانه‌ای</button>
                    </form>
                    <?php endif; ?>

                    <h3>پیش‌نمایش (با داده نمونه و CSS ذخیره‌شده سایت)</h3>
                    <iframe class="tpl-preview" title="پیش‌نمایش قالب" sandbox="allow-scripts" srcdoc="<?= e(design_preview_html($settings, (string) $editTplRow['template_key'], (string) $editTplRow['content'])) ?>"></iframe>
                    <p class="muted">پیش‌نمایش از آخرین نسخه ذخیره‌شده ساخته می‌شود؛ برای دیدن تغییرات، اول «ذخیره قالب» را بزنید.</p>

                    <?php if ($editTplRevisions !== []): ?>
                        <h3>تاریخچه نسخه‌های این قالب</h3>
                        <table>
                            <thead><tr><th>تاریخ</th><th>یادداشت</th><th>عملیات</th></tr></thead>
                            <tbody>
                            <?php foreach ($editTplRevisions as $rev): ?>
                                <tr>
                                    <td><?= e($rev['created_at']) ?></td>
                                    <td><?= e($rev['note']) ?></td>
                                    <td><form method="post" class="inline" onsubmit="return confirm('این نسخه جایگزین نسخه فعلی شود؟ نسخه فعلی هم در تاریخچه می‌ماند.')"><?= csrf_field() ?><input type="hidden" name="action" value="restore_revision"><input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>"><input type="hidden" name="template_key" value="<?= e($editTplRow['template_key']) ?>"><button type="submit" class="btn small">بازیابی این نسخه</button></form></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($cleanupCandidates !== []): ?>
                    <section class="card wide">
                        <h2>پاک‌سازی فایل‌های قدیمی قالب</h2>
                        <p class="muted">این فایل‌های نسخه فایل‌محور هنوز روی هاست هستند، ولی سایت دیگر از آن‌ها استفاده نمی‌کند؛ قالب و CSS فقط از دیتابیس خوانده می‌شوند:</p>
                        <ul>
                            <?php foreach ($cleanupCandidates as $name): ?><li><code><?= e($name) ?></code></li><?php endforeach; ?>
                        </ul>
                        <form method="post" onsubmit="return confirm('فایل‌های قدیمی قالب و style.css حذف شوند؟ برگشت‌پذیر نیست، ولی سایت به آن‌ها نیازی ندارد.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete_legacy_files">
                            <label>برای تأیید، کلمه «حذف» را تایپ کنید
                                <input type="text" name="confirm_text" required placeholder="حذف">
                            </label>
                            <button type="submit" class="btn danger-btn">حذف فایل‌های قدیمی</button>
                        </form>
                    </section>
                <?php endif; ?>

            <?php else: ?>
                <h2>تنظیمات ظاهری</h2>
                <p class="muted">رنگ، فونت، عرض محتوا و گردی گوشه‌ها بدون ویرایش کد عوض می‌شوند و بلافاصله روی کل سایت اعمال می‌شوند.</p>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_visual_settings">
                    <label>رنگ اصلی
                        <input type="color" name="primary_color" value="<?= e(valid_hex_color($settings['primary_color'] ?? null, '#2563eb')) ?>">
                    </label>
                    <label>رنگ تأکیدی (سر دیگر گرادیان اسلایدر)
                        <input type="color" name="accent_color" value="<?= e(valid_hex_color($settings['accent_color'] ?? null, '#0d9488')) ?>">
                    </label>
                    <label>فونت سایت
                        <select name="site_font">
                            <option value="system" <?= $visual['site_font'] === 'system' ? 'selected' : '' ?>>فونت سیستم بازدیدکننده (سریع‌ترین، بدون دانلود)</option>
                            <option value="tahoma" <?= $visual['site_font'] === 'tahoma' ? 'selected' : '' ?>>تاهوما</option>
                            <option value="vazirmatn" <?= $visual['site_font'] === 'vazirmatn' ? 'selected' : '' ?>>وزیرمتن (بارگذاری از CDN)</option>
                        </select>
                    </label>
                    <label>حداکثر عرض محتوای سایت (پیکسل، بین ۸۸۰ تا ۱۶۰۰)
                        <input type="number" name="container_width" min="880" max="1600" step="10" value="<?= (int) $visual['container_width'] ?>">
                    </label>
                    <label>گردی گوشه‌ها (پیکسل، بین ۰ تا ۳۲)
                        <input type="number" name="border_radius" min="0" max="32" step="1" value="<?= (int) $visual['border_radius'] ?>">
                    </label>
                    <label>تم سایت (فقط ظاهر سایت عمومی، ربطی به پنل مدیریت ندارد)
                        <select name="default_theme">
                            <option value="dark" <?= $visual['default_theme'] === 'dark' ? 'selected' : '' ?>>تیره</option>
                            <option value="light" <?= $visual['default_theme'] === 'light' ? 'selected' : '' ?>>روشن</option>
                            <option value="white" <?= $visual['default_theme'] === 'white' ? 'selected' : '' ?>>سفید</option>
                            <option value="glass" <?= $visual['default_theme'] === 'glass' ? 'selected' : '' ?>>شیشه‌ای</option>
                            <option value="smoke" <?= $visual['default_theme'] === 'smoke' ? 'selected' : '' ?>>دودی زرد</option>
                            <option value="ocean" <?= $visual['default_theme'] === 'ocean' ? 'selected' : '' ?>>آبی اقیانوسی</option>
                            <option value="forest" <?= $visual['default_theme'] === 'forest' ? 'selected' : '' ?>>سبز جنگلی</option>
                            <option value="sunset" <?= $visual['default_theme'] === 'sunset' ? 'selected' : '' ?>>نارنجی غروب</option>
                            <option value="royal" <?= $visual['default_theme'] === 'royal' ? 'selected' : '' ?>>بنفش سلطنتی</option>
                            <option value="mono" <?= $visual['default_theme'] === 'mono' ? 'selected' : '' ?>>مینیمال</option>
                            <option value="system" <?= $visual['default_theme'] === 'system' ? 'selected' : '' ?>>مطابق تنظیم سیستم بازدیدکننده</option>
                        </select>
                    </label>
                    <label>تم پنل مدیریت (کاملاً جدا از تم سایت)
                        <select name="admin_theme">
                            <option value="light" <?= ($adminTheme ?? 'light') === 'light' ? 'selected' : '' ?>>روشن (پیش‌فرض)</option>
                            <option value="architect" <?= ($adminTheme ?? 'light') === 'architect' ? 'selected' : '' ?>>معمارپسند</option>
                            <option value="glass-white" <?= ($adminTheme ?? 'light') === 'glass-white' ? 'selected' : '' ?>>شیشه‌ای روشن</option>
                            <option value="glass-smoke" <?= ($adminTheme ?? 'light') === 'glass-smoke' ? 'selected' : '' ?>>شیشه‌ای تیره</option>
                        </select>
                    </label>
                    <label class="check"><input type="checkbox" name="header_sticky" value="1" <?= $visual['header_sticky'] ? 'checked' : '' ?>> هدر چسبان باشد (هنگام اسکرول بالای صفحه بماند)</label>
                    <h3 style="margin-top:20px">سفارشی‌سازی جزئی استایل پنل (۹٫۲۵)</h3>
                    <p class="muted">خالی بگذارید تا از تم انتخابی استفاده شود.</p>
                    <div class="inline-fields">
                        <label>رنگ پس‌زمینه سایدبار
                            <input type="color" name="admin_sidebar_bg" value="<?= e((string) get_setting('admin_sidebar_bg', '#ffffff')) ?>">
                        </label>
                        <label>رنگ متن سایدبار
                            <input type="color" name="admin_sidebar_text" value="<?= e((string) get_setting('admin_sidebar_text', '#111827')) ?>">
                        </label>
                        <label>رنگ پس‌زمینه هدر
                            <input type="color" name="admin_header_bg" value="<?= e((string) get_setting('admin_header_bg', '#ffffff')) ?>">
                        </label>
                        <label>رنگ تأکیدی (دکمه‌ها و لینک‌ها)
                            <input type="color" name="admin_accent" value="<?= e((string) get_setting('admin_accent', '#2563eb')) ?>">
                        </label>
                    </div>
                    <div class="inline-fields">
                        <label>اندازه فونت پایه (پیکسل)
                            <input type="number" name="admin_font_size" min="12" max="18" value="<?= e((string) get_setting('admin_font_size', '14')) ?>">
                        </label>
                        <label>گردی گوشه‌ها (پیکسل)
                            <input type="number" name="admin_radius" min="0" max="24" value="<?= e((string) get_setting('admin_radius', '8')) ?>">
                        </label>
                        <label>پدینگ کارت‌ها (پیکسل)
                            <input type="number" name="admin_card_padding" min="8" max="32" value="<?= e((string) get_setting('admin_card_padding', '16')) ?>">
                        </label>
                    </div>
                    <button type="submit" class="btn primary">ذخیره تنظیمات ظاهری</button>
                </form>

                <h2>CSS اصلی سایت</h2>
                <p class="muted">این CSS از مسیر عمومی <code>style.php</code> به مرورگرهای بازدیدکنندگان می‌رسد. اگر اینجا را کاملاً خالی کنید، نسخه کارخانه‌ای به‌صورت خودکار استفاده می‌شود. قبل از هر ذخیره، نسخه قبلی در تاریخچه می‌ماند. توجه: CSS داخل دیتابیس است و با آپدیت یک‌کلیکی پاک نمی‌شود.</p>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_css">
                    <input type="hidden" name="which" value="site_css">
                    <textarea name="content" rows="24" dir="ltr" spellcheck="false" class="code-editor" data-mode="css"><?= e((string) get_setting('site_css', '')) ?></textarea>
                    <button type="submit" class="btn primary">ذخیره CSS اصلی</button>
                </form>
                <form method="post" class="card" onsubmit="return confirm('CSS اصلی به نسخه کارخانه‌ای برگردد؟ نسخه فعلی در تاریخچه می‌ماند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_css">
                    <input type="hidden" name="which" value="site_css">
                    <button type="submit" class="btn">بازنشانی CSS اصلی به کارخانه‌ای</button>
                </form>
                <?php if ($cssRevisions !== []): ?>
                    <h3>تاریخچه CSS اصلی</h3>
                    <table>
                        <thead><tr><th>تاریخ</th><th>یادداشت</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($cssRevisions as $rev): ?>
                            <tr>
                                <td><?= e($rev['created_at']) ?></td>
                                <td><?= e($rev['note']) ?></td>
                                <td><form method="post" class="inline" onsubmit="return confirm('این نسخه جایگزین نسخه فعلی شود؟ نسخه فعلی هم در تاریخچه می‌ماند.')"><?= csrf_field() ?><input type="hidden" name="action" value="restore_revision"><input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>"><input type="hidden" name="return_to" value="css"><button type="submit" class="btn small">بازیابی این نسخه</button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h2>CSS سفارشی</h2>
                <p class="muted">قانون‌هایی که اینجا بنویسید بعد از CSS اصلی اعمال می‌شوند؛ برای تغییرات کوچک و تست سریع بهترین جا همین‌جاست. این هم داخل دیتابیس است و در آپدیت‌ها حفظ می‌شود.</p>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_css">
                    <input type="hidden" name="which" value="custom_css">
                    <textarea name="content" rows="12" dir="ltr" spellcheck="false" class="code-editor" data-mode="css"><?= e((string) get_setting('custom_css', '')) ?></textarea>
                    <button type="submit" class="btn primary">ذخیره CSS سفارشی</button>
                </form>
                <form method="post" class="card" onsubmit="return confirm('CSS سفارشی کاملاً خالی شود؟ نسخه فعلی در تاریخچه می‌ماند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reset_css">
                    <input type="hidden" name="which" value="custom_css">
                    <button type="submit" class="btn">خالی‌کردن CSS سفارشی</button>
                </form>
                <?php if ($customCssRevisions !== []): ?>
                    <h3>تاریخچه CSS سفارشی</h3>
                    <table>
                        <thead><tr><th>تاریخ</th><th>یادداشت</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($customCssRevisions as $rev): ?>
                            <tr>
                                <td><?= e($rev['created_at']) ?></td>
                                <td><?= e($rev['note']) ?></td>
                                <td><form method="post" class="inline" onsubmit="return confirm('این نسخه جایگزین نسخه فعلی شود؟ نسخه فعلی هم در تاریخچه می‌ماند.')"><?= csrf_field() ?><input type="hidden" name="action" value="restore_revision"><input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>"><input type="hidden" name="return_to" value="css"><button type="submit" class="btn small">بازیابی این نسخه</button></form></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($page === 'messages'): ?>
            <h1>پیام‌های تماس</h1>
            <p class="muted">پیام‌هایی که از فرم تماس سایت (بخش با قالب «تماس با ما») فرستاده شده‌اند.</p>
            <table>
                <thead><tr><th>تاریخ</th><th>نام</th><th>راه تماس</th><th>پیام</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($messages as $m): ?>
                    <tr>
                        <td><?= e($m['created_at']) ?></td>
                        <td><?= e($m['name']) ?></td>
                        <td><?= e($m['contact']) ?></td>
                        <td><?= nl2br(e($m['message'])) ?></td>
                        <td><form method="post" class="inline" onsubmit="return confirm('این پیام حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_message"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($messages === []): ?><tr><td colspan="5" class="muted">هنوز پیامی ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'partners'): ?>
            <h1>درخواست‌های همکاری</h1>
            <p class="muted">درخواست‌هایی که از فرم «همکاری با ما» در سایت ثبت شده‌اند. صفحه عمومی: <a href="/partner" target="_blank" rel="noopener">/partner</a></p>
            <?php
            $partnerRequests = $pdo->query('SELECT * FROM partner_requests ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
            $partnerStatusLabels = ['new' => 'جدید', 'contacted' => 'تماس گرفته شد', 'approved' => 'تأیید شد', 'rejected' => 'رد شد'];
            ?>
            <table>
                <thead><tr><th>تاریخ</th><th>نام مسئول</th><th>نام واحد همکاری</th><th>زمینه فعالیت</th><th>شماره تماس</th><th>ایمیل</th><th>آدرس</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($partnerRequests as $pr): ?>
                    <tr>
                        <td><?= e($pr['created_at']) ?></td>
                        <td><?= e($pr['manager_name']) ?></td>
                        <td><?= e($pr['business_name']) ?></td>
                        <td><?= e($pr['field_of_activity']) ?></td>
                        <td dir="ltr"><?= e($pr['phone']) ?></td>
                        <td dir="ltr"><?= e($pr['email'] ?? '—') ?></td>
                        <td><?= nl2br(e($pr['address'] ?? '—')) ?></td>
                        <td><span class="badge"><?= e($partnerStatusLabels[$pr['status']] ?? $pr['status']) ?></span></td>
                        <td>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="partner_request_status"><input type="hidden" name="id" value="<?= (int) $pr['id'] ?>">
                                <select name="status" onchange="this.form.submit()">
                                    <?php foreach ($partnerStatusLabels as $sk => $sl): ?>
                                        <option value="<?= $sk ?>"<?= $pr['status'] === $sk ? ' selected' : '' ?>><?= $sl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <form method="post" class="inline" onsubmit="return confirm('این درخواست حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_partner_request"><input type="hidden" name="id" value="<?= (int) $pr['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($partnerRequests === []): ?><tr><td colspan="9" class="muted">هنوز درخواستی ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table>

        <?php elseif ($page === 'customers'): ?>
            <?php catalog_render_customers($catalogData); ?>

        <?php elseif ($page === 'categories'): ?>
            <?php catalog_render_categories($catalogData); ?>

        <?php elseif ($page === 'products'): ?>
            <?php catalog_render_products($catalogData); ?>

        <?php elseif ($page === 'attributes'): ?>
            <?php catalog_render_attributes($catalogData); ?>

        <?php elseif ($page === 'materials'): ?>
            <?php inventory_render_materials($inventoryData); ?>

        <?php elseif ($page === 'material_prices'): ?>
            <?php inventory_render_price_list($inventoryData); ?>

        <?php elseif ($page === 'stock'): ?>
            <?php inventory_render_stock($inventoryData); ?>
        <?php elseif ($page === 'orders'): ?>
            <?php orders_render_list($ordersData); ?>
        <?php elseif ($page === 'order_new'): ?>
            <?php orders_render_new($ordersData); ?>
        <?php elseif ($page === 'order_view'): ?>
            <?php orders_render_view($ordersData); ?>
        <?php elseif ($page === 'order_rules'): ?>
            <?php orders_render_rules($ordersData); ?>
        <?php elseif ($page === 'order_forms'): ?>
            <h1>فرم‌های سفارش</h1>
            <div class="crud-toolbar">
                <a href="#add-order-field-form" class="btn add">+ افزودن فیلد تازه</a>
            </div>
            <p class="muted">فیلدهای سفارشی فرم ثبت سفارش را اینجا مدیریت کنید. ترتیب اعمال: فیلد خاص محصول ← فیلد دسته‌بندی ← فیلد سراسری. اگر برای محصولی فیلد خاص تعریف شده باشد، فقط همان‌ها نمایش داده می‌شوند.</p>
            <?php
            $typeLabels = ['text' => 'متن', 'number' => 'عدد', 'select' => 'انتخابی', 'textarea' => 'متن بلند', 'checkbox' => 'تیک'];
            $renderFieldTable = function (string $otype, int $oid, string $title) use ($pdo, $typeLabels) {
                $fields = $pdo->query("SELECT * FROM order_form_fields WHERE owner_type = '" . $otype . "' AND owner_id = " . $oid . " ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
                echo '<h2>' . e($title) . '</h2>';
                echo '<table><thead><tr><th>برچسب</th><th>نوع</th><th>الزامی</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>';
                foreach ($fields as $ff) {
                    echo '<tr><td>' . e($ff['label']) . '</td>';
                    echo '<td>' . e($typeLabels[$ff['field_type']] ?? $ff['field_type']) . '</td>';
                    echo '<td>' . ((int) $ff['is_required'] === 1 ? 'بله' : 'خیر') . '</td>';
                    echo '<td>' . ((int) $ff['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>') . '</td>';
                    echo '<td><a href="admin.php?page=order_forms&edit_field=' . (int) $ff['id'] . '" class="btn small edit">ویرایش</a> ';
                    echo '<form method="post" class="inline">' . csrf_field() . '<input type="hidden" name="action" value="toggle_order_form_field"><input type="hidden" name="id" value="' . (int) $ff['id'] . '"><button type="submit" class="btn small">' . ((int) $ff['is_active'] === 1 ? 'غیرفعال' : 'فعال') . '</button></form> ';
                    echo '<form method="post" class="inline" onsubmit="return confirm(\'این فیلد حذف شود؟\')">' . csrf_field() . '<input type="hidden" name="action" value="delete_order_form_field"><input type="hidden" name="id" value="' . (int) $ff['id'] . '"><button type="submit" class="btn small danger-btn">حذف</button></form></td></tr>';
                }
                if ($fields === []) { echo '<tr><td colspan="5" class="muted">فیلدی تعریف نشده است.</td></tr>'; }
                echo '</tbody></table>';
            };
            $renderFieldTable('global', 0, 'فیلدهای سراسری (همه محصولات)');
            $cats = $pdo->query("SELECT id, title FROM categories ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cats as $cc) {
                $renderFieldTable('category', (int) $cc['id'], 'دسته: ' . (string) $cc['title']);
            }
            ?>
            <?php $editFid = (int) ($_GET['edit_field'] ?? 0); $editField = null;
            if ($editFid > 0) { $editField = $pdo->query("SELECT * FROM order_form_fields WHERE id = " . $editFid)->fetch(PDO::FETCH_ASSOC); }
            ?>
            <?php if ($editField): ?>
            <h2>ویرایش فیلد: <?= e($editField['label']) ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="edit_order_form_field">
                <input type="hidden" name="id" value="<?= (int) $editField['id'] ?>">
                <div class="inline-fields">
                    <label>برچسب فیلد *
                        <input type="text" name="field_label" required maxlength="100" value="<?= e($editField['label']) ?>">
                    </label>
                    <label>نوع
                        <select name="field_type">
                            <?php foreach ($typeLabels as $tk => $tl): ?><option value="<?= $tk ?>" <?= $editField['field_type'] === $tk ? 'selected' : '' ?>><?= e($tl) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>گزینه‌ها (با | جدا کنید)
                        <input type="text" name="field_options" value="<?= e(implode('|', json_decode((string) ($editField['options_json'] ?? '[]'), true) ?: [])) ?>">
                    </label>
                    <label>متن راهنما
                        <input type="text" name="field_placeholder" value="<?= e((string) ($editField['placeholder'] ?? '')) ?>">
                    </label>
                    <label>توضیح
                        <input type="text" name="field_help" value="<?= e((string) ($editField['help_text'] ?? '')) ?>">
                    </label>
                    <label><input type="checkbox" name="field_required" value="1" <?= (int) $editField['is_required'] === 1 ? 'checked' : '' ?>> الزامی</label>
                </div>
                <div class="crud-toolbar">
                    <button type="submit" class="btn primary">ذخیره تغییرات</button>
                    <a href="admin.php?page=order_forms" class="btn">انصراف</a>
                </div>
            </form>
            <?php endif; ?>
            <h2 id="add-order-field-form">افزودن فیلد تازه</h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_order_form_field">
                <div class="inline-fields">
                    <label>محدوده
                        <select name="owner_type" id="off-otype">
                            <option value="global">سراسری (همه محصولات)</option>
                            <?php foreach ($cats as $cc): ?><option value="category" data-oid="<?= (int) $cc['id'] ?>">دسته: <?= e($cc['title']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <input type="hidden" name="owner_id" id="off-oid" value="0">
                    <label>برچسب فیلد *
                        <input type="text" name="field_label" required maxlength="100">
                    </label>
                    <label>نوع
                        <select name="field_type">
                            <option value="text">متن</option>
                            <option value="number">عدد</option>
                            <option value="select">انتخابی</option>
                            <option value="textarea">متن بلند</option>
                            <option value="checkbox">تیک</option>
                        </select>
                    </label>
                </div>
                <div class="inline-fields">
                    <label>گزینه‌ها (با | جدا کنید، برای نوع انتخابی)
                        <input type="text" name="field_options" dir="rtl" placeholder="مثلاً: آویز | سقفی | دیواری">
                    </label>
                    <label>متن راهنما (placeholder)
                        <input type="text" name="field_placeholder" maxlength="150">
                    </label>
                    <label>توضیح زیر فیلد
                        <input type="text" name="field_help" maxlength="255">
                    </label>
                    <label class="check"><input type="checkbox" name="field_required" value="1"> الزامی</label>
                </div>
                <button type="submit" class="btn add">+ افزودن فیلد</button>
            </form>
            <script>
            (function(){
                var sel = document.getElementById('off-otype');
                var hid = document.getElementById('off-oid');
                if (!sel || !hid) return;
                function sync(){ var o = sel.options[sel.selectedIndex]; hid.value = o.getAttribute('data-oid') || '0'; }
                sel.addEventListener('change', sync); sync();
            })();
            </script>
        <?php elseif ($page === 'remnants'): ?>
            <?php orders_render_remnants($ordersData); ?>
        <?php elseif ($page === 'production'): ?>
            <?php production_render_list($productionData); ?>
        <?php elseif ($page === 'production_view'): ?>
            <?php production_render_view($productionData); ?>
        <?php elseif ($page === 'production_rules'): ?>
            <?php production_render_rules($productionData); ?>
        <?php elseif ($page === 'finance'): ?>
            <?php finance_render_dashboard($financeData); ?>
        <?php elseif ($page === 'invoices'): ?>
            <?php finance_render_invoices($financeData); ?>
        <?php elseif ($page === 'invoice_view'): ?>
            <?php finance_render_invoice_view($financeData); ?>
        <?php elseif ($page === 'expenses'): ?>
            <?php finance_render_expenses($financeData); ?>
        <?php elseif ($page === 'statements'): ?>
            <?php finance_render_statements($financeData); ?>
        <?php elseif ($page === 'finance_rules'): ?>
            <?php finance_render_rules($financeData); ?>
        <?php elseif ($page === 'reports'): ?>
            <?php reports_render($reportsData); ?>
        <?php elseif ($page === 'employees'): ?>
            <?php hr_render_employees($hrData); ?>
        <?php elseif ($page === 'payroll'): ?>
            <?php hr_render_payroll($hrData); ?>
        <?php elseif ($page === 'blog'): ?>
            <?php blog_render_admin($blogData); ?>
        <?php elseif ($page === 'employee_profile'): ?>
            <?php hr_render_employee_profile($hrData); ?>
        <?php elseif ($page === 'payslip'): ?>
            <?php hr_render_payslip($hrData); ?>
        <?php elseif ($page === 'assets'): ?>
            <?php assets_render_list($assetsData); ?>
        <?php elseif ($page === 'assets_maintenance'): ?>
            <?php assets_render_maintenance($assetsData); ?>
        <?php elseif ($page === 'users'): ?>
            <?php users_render($usersData); ?>
        <?php elseif ($page === 'modules'): ?>
            <?php modules_render(); ?>
        <?php elseif ($page === 'templates'): ?>
            <?php templates_render(); ?>
        <?php elseif ($page === 'api'): ?>
            <?php api_render(); ?>
        <?php elseif ($page === 'logs'): ?>
            <?php logs_render($logsData); ?>
        <?php elseif ($page === 'notifications'): ?>
            <?php notifications_render_page(); ?>
        <?php elseif ($page === 'proposal'): ?>
            <?php proposal_render_page(); ?>
        <?php elseif ($page === 'tools'): ?>
            <h1>ابزار و بکاپ</h1>

            <h2>دانلود بکاپ دیتابیس</h2>
            <p class="muted">یک کپی از فایل <code>database.sqlite</code> (شامل تنظیمات، بخش‌ها، صفحه‌ها و پیام‌ها) دانلود می‌شود. آن را جای امن نگه دارید.</p>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="download_backup">
                <button type="submit" class="btn primary">دانلود بکاپ دیتابیس</button>
            </form>

            <h2>بازیابی بکاپ</h2>
            <p class="muted">فایل بکاپ (sqlite/db) را آپلود کنید. قبل از جایگزینی، از دیتابیس فعلی یک نسخه امن با نام <code>database-backup-before-restore.sqlite</code> نگه داشته می‌شود و بعد از بازیابی باید دوباره وارد شوید. فایل باید دیتابیس همین سیستم (دارای جدول تنظیمات) باشد.</p>
            <form method="post" class="card" enctype="multipart/form-data" onsubmit="return confirm('دیتابیس فعلی با این بکاپ جایگزین شود؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore_backup">
                <label>فایل بکاپ
                    <input type="file" name="backup_file" required accept=".sqlite,.sqlite3,.db">
                </label>
                <button type="submit" class="btn danger-btn">بازیابی بکاپ</button>
            </form>

            <h2>بهینه‌سازی عکس‌ها</h2>
            <p class="muted">عکس‌های بالای ۱۵۰ کیلوبایت در پوشه <code>uploads</code> را به حداکثر ۱۲۰۰ پیکسل تغییر اندازه داده و فشرده می‌کند (JPEG با کیفیت ۸۲). این کار سرعت لود سایت را به‌طور محسوسی بالا می‌برد.</p>
            <?php $imgStats = tools_image_stats(); ?>
            <section class="card">
                <p>تعداد عکس‌های سنگین: <b><?= (int) $imgStats['count'] ?></b> — حجم مجموع: <b><?= e($imgStats['size']) ?></b></p>
                <form method="post" onsubmit="return confirm('عکس‌های سنگین بهینه شوند؟')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="optimize_images">
                    <button type="submit" class="btn primary" <?= $imgStats['count'] === 0 ? 'disabled' : '' ?>>بهینه‌سازی عکس‌ها</button>
                </form>
            </section>

        <?php elseif ($page === 'db_browse'): ?>
            <?php if ((string) (current_admin_user()['role_key'] ?? '') !== 'owner'): ?>
                <div class="alert error">فقط مدیر کل به مرورگر دیتابیس دسترسی دارد.</div>
            <?php elseif ($dbBrowse === null): ?>
                <div class="alert error">خطا در بارگذاری.</div>
            <?php elseif ($dbBrowse['table'] === ''): ?>
                <h1>مرورگر دیتابیس</h1>
                <p class="muted">جدولی را انتخاب کنید:</p>
                <div class="stat-grid">
                <?php foreach ($dbBrowse['tables'] as $t): ?>
                    <a class="stat-card" href="admin.php?page=db_browse&table=<?= urlencode($t) ?>"><span><?= e($t) ?></span></a>
                <?php endforeach; ?>
                </div>
            <?php else: ?>
                <h1>جدول: <code dir="ltr"><?= e($dbBrowse['table']) ?></code></h1>
                <p class="muted"><?= (int) $dbBrowse['total'] ?> ردیف · صفحه <?= (int) $dbBrowse['page'] ?> از <?= (int) $dbBrowse['pages'] ?> · برای ویرایش روی هر سلول کلیک کنید</p>
                <div class="crud-toolbar">
                    <a href="admin.php?page=database" class="btn">بازگشت به لیست جدول‌ها</a>
                </div>
                <div style="overflow-x:auto">
                <table class="db-browse">
                    <thead><tr>
                        <?php foreach ($dbBrowse['cols'] as $c): ?><th dir="ltr"><?= e($c['name']) ?><br><span class="muted"><?= e($c['type']) ?></span></th><?php endforeach; ?>
                        <th>عملیات</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($dbBrowse['rows'] as $row): $rid = (int) $row['rowid']; ?>
                        <tr>
                        <?php foreach ($dbBrowse['cols'] as $c): $cn = $c['name']; $cv = $row[$cn] ?? ''; $isPk = ($dbBrowse['pk'] === $cn); ?>
                            <td class="<?= $isPk ? 'pk-cell' : 'editable-cell' ?>" <?= $isPk ? '' : 'data-table="' . e($dbBrowse['table']) . '" data-col="' . e($cn) . '" data-rowid="' . $rid . '" data-page="' . (int) $dbBrowse['page'] . '" title="کلیک برای ویرایش"' ?>>
                                <?= $cv === null ? '<span class="muted">NULL</span>' : e(mb_strimwidth((string) $cv, 0, 80, '…')) ?>
                            </td>
                        <?php endforeach; ?>
                            <td><form method="post" class="inline" onsubmit="return confirm('این ردیف حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="db_row_delete">
                                <input type="hidden" name="table" value="<?= e($dbBrowse['table']) ?>">
                                <input type="hidden" name="rowid" value="<?= $rid ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php if ($dbBrowse['pages'] > 1): ?>
                <div class="crud-toolbar" style="justify-content:center">
                    <?php if ($dbBrowse['page'] > 1): ?><a href="admin.php?page=db_browse&table=<?= urlencode($dbBrowse['table']) ?>&p=<?= $dbBrowse['page'] - 1 ?>" class="btn small">قبلی</a><?php endif; ?>
                    <span class="muted">صفحه <?= (int) $dbBrowse['page'] ?> از <?= (int) $dbBrowse['pages'] ?></span>
                    <?php if ($dbBrowse['page'] < $dbBrowse['pages']): ?><a href="admin.php?page=db_browse&table=<?= urlencode($dbBrowse['table']) ?>&p=<?= $dbBrowse['page'] + 1 ?>" class="btn small">بعدی</a><?php endif; ?>
                </div>
                <?php endif; ?>
                <style>
                .db-browse .editable-cell{cursor:pointer;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
                .db-browse .editable-cell:hover{background:#fef9c3}
                .db-browse .pk-cell{background:#f8fafc;color:#64748b}
                .db-edit-input{width:100%;padding:6px;border:2px solid #2563eb;border-radius:6px;font-family:inherit;font-size:13px}
                </style>
                <script>
                (function(){
                    document.querySelectorAll('.db-browse .editable-cell').forEach(function(cell){
                        cell.addEventListener('click', function(){
                            if (cell.querySelector('input')) return;
                            var orig = cell.textContent.trim();
                            if (orig === 'NULL') orig = '';
                            var input = document.createElement('input');
                            input.type = 'text'; input.value = orig; input.className = 'db-edit-input';
                            cell.innerHTML = ''; cell.appendChild(input); input.focus(); input.select();
                            function save(){
                                var form = document.createElement('form');
                                form.method = 'post'; form.style.display = 'none';
                                form.innerHTML = '<?= csrf_field() ?>'
                                    + '<input name="action" value="db_cell_update">'
                                    + '<input name="table" value="' + cell.dataset.table + '">'
                                    + '<input name="col" value="' + cell.dataset.col + '">'
                                    + '<input name="rowid" value="' + cell.dataset.rowid + '">'
                                    + '<input name="p" value="' + cell.dataset.page + '">'
                                    + '<input name="val">';
                                form.querySelector('[name=val]').value = input.value;
                                document.body.appendChild(form); form.submit();
                            }
                            input.addEventListener('blur', save);
                            input.addEventListener('keydown', function(e){
                                if (e.key === 'Enter') save();
                                if (e.key === 'Escape') { cell.textContent = orig === '' ? 'NULL' : orig; }
                            });
                        });
                    });
                })();
                </script>
            <?php endif; ?>
        <?php elseif ($page === 'database'): ?>
            <h1>اتصال دیتابیس</h1>
            <p class="muted">از این صفحه می‌توانید وضعیت اتصال دیتابیس SQLite فعال را ببینید، بین فایل‌های دیتابیس موجود جابه‌جا شوید یا یک فایل دیتابیس دیگر آپلود کنید. قبل از هر تعویض، فایل تازه کامل اعتبارسنجی می‌شود و از دیتابیس فعلی نسخه امن نگه داشته می‌شود.</p>

            <?php if ($dbStats): ?>
            <section class="card wide">
                <h2>وضعیت اتصال</h2>
                <p>
                    <?php if ($dbStats['connected']): ?>
                        <span class="status-pill ok"><span class="status-dot"></span>متصل است</span>
                    <?php else: ?>
                        <span class="status-pill error">خطا در اتصال</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($dbStats['error'])): ?><div class="alert error"><?= e($dbStats['error']) ?></div><?php endif; ?>
                <table>
                    <tbody>
                        <tr><th>فایل فعال</th><td><code><?= e($dbStats['name']) ?></code></td></tr>
                        <tr><th>نسخه برنامه</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>مسیر فایل</th><td><code><?= e($dbStats['path']) ?></code></td></tr>
                        <tr><th>حجم فایل</th><td><?= e($dbStats['size_formatted']) ?></td></tr>
                        <tr><th>آخرین تغییر</th><td><?= e($dbStats['modified_formatted']) ?></td></tr>
                    </tbody>
                </table>
                <div class="stat-grid">
                    <div class="stat-card"><span>تعداد جدول‌ها</span><strong><?= (int) $dbStats['table_count'] ?></strong></div>
                    <div class="stat-card"><span>مجموع ردیف‌ها</span><strong><?= (int) $dbStats['total_rows'] ?></strong></div>
                </div>
            </section>

            <section class="card wide">
                <h2>جدول‌های دیتابیس</h2>
                <p class="muted">فهرست جدول‌های کاربر و تعداد ردیف فعلی هرکدام. جدول‌های داخلی SQLite در این فهرست نشان داده نمی‌شوند.</p>
                <table>
                    <thead><tr><th>نام جدول</th><th>تعداد ردیف</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbStats['tables'] as $table): ?>
                        <tr>
                            <td><a href="admin.php?page=db_browse&table=<?= urlencode($table['name']) ?>"><code><?= e($table['name']) ?></code></a></td>
                            <td><?= $table['rows'] === null ? 'خطا در شمارش' : (int) $table['rows'] ?></td>
                            <td><a href="admin.php?page=db_browse&table=<?= urlencode($table['name']) ?>" class="btn small">مرور</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dbStats['tables'] === []): ?><tr><td colspan="2" class="muted">جدولی پیدا نشد.</td></tr><?php endif; ?>
                    <tr><th>جمع کل</th><th><?= (int) $dbStats['total_rows'] ?> ردیف در <?= (int) $dbStats['table_count'] ?> جدول</th></tr>
                    </tbody>
                </table>
            </section>
            <?php else: ?>
                <div class="alert error">دریافت وضعیت دیتابیس انجام نشد.</div>
            <?php endif; ?>

            <section class="card wide">
                <h2>انتخاب فایل دیتابیس موجود</h2>
                <p class="muted">فایل‌های <code>sqlite</code>، <code>sqlite3</code> و <code>db</code> موجود در فولدر برنامه فهرست شده‌اند. برای فعال‌کردن یک فایل دیگر، روی «اتصال به این فایل» کلیک کنید. قبل از تعویض، از دیتابیس فعلی فایل <code>database-backup-before-switch.sqlite</code> ساخته می‌شود و بعد از تعویض باید با پسورد دیتابیس جدید دوباره وارد شوید.</p>
                <table>
                    <thead><tr><th>نام فایل</th><th>وضعیت</th><th>حجم</th><th>آخرین تغییر</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($dbFiles as $file): ?>
                        <tr>
                            <td><code><?= e($file['name']) ?></code></td>
                            <td><?php if ($file['is_active']): ?><span class="badge ok">فعال</span><?php else: ?><span class="badge off">غیرفعال</span><?php endif; ?></td>
                            <td><?= e($file['size_formatted']) ?></td>
                            <td><?= e($file['modified_formatted']) ?></td>
                            <td>
                                <?php if ($file['is_active']): ?>
                                    <button type="button" class="btn small" disabled>فایل فعال فعلی</button>
                                <?php else: ?>
                                    <form method="post" class="inline" onsubmit="return confirm('دیتابیس فعال با این فایل جایگزین شود؟ قبل از تعویض نسخه امن ساخته می‌شود و باید دوباره وارد شوید.')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="switch_database_file">
                                        <input type="hidden" name="database_file" value="<?= e($file['name']) ?>">
                                        <button type="submit" class="btn primary small">اتصال به این فایل</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($dbFiles === []): ?><tr><td colspan="5" class="muted">فایل دیتابیسی در فولدر برنامه پیدا نشد.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <section class="card wide">
                <h2>آپلود فایل دیتابیس</h2>
                <p class="muted">یک فایل <code>sqlite</code> یا <code>db</code> (حداکثر ۲۰ مگابایت) انتخاب کنید. فایل باید دیتابیس سالم همین سیستم و دارای جدول <code>settings</code> باشد. اگر اعتبارسنجی موفق نباشد، دیتابیس فعلی دست‌نخورده باقی می‌ماند.</p>
                <form method="post" class="card" enctype="multipart/form-data" onsubmit="return confirm('این فایل به‌عنوان دیتابیس فعال جایگزین شود؟ قبل از تعویض نسخه امن ساخته می‌شود و باید دوباره وارد شوید.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_database_file">
                    <label>فایل دیتابیس
                        <input type="file" name="database_file" required accept=".sqlite,.db">
                    </label>
                    <button type="submit" class="btn danger-btn">آپلود و اتصال به دیتابیس</button>
                </form>
            </section>

            <section class="card wide">
                <h2>نکته مهم درباره بکاپ و پسورد</h2>
                <p class="muted">بکاپی که از «ابزار و بکاپ» دانلود می‌کنید روی دستگاه شما ذخیره می‌شود. نسخه‌های امن قبل از بازیابی یا تعویض دیتابیس، با نام‌های <code>database-backup-before-restore.sqlite</code> و <code>database-backup-before-switch.sqlite</code> در همان فولدر برنامه نگه داشته می‌شوند. همه تنظیمات، صفحه‌ها، بخش‌ها، پیام‌ها و هش پسورد مدیریت داخل فایل <code>database.sqlite</code> است؛ بنابراین بعد از اتصال به یک دیتابیس دیگر، ورود با پسورد ذخیره‌شده در همان دیتابیس جدید انجام می‌شود.</p>
            </section>

        <?php elseif ($page === 'sysinfo'): ?>
            <h1>مشخصات نرم‌افزار</h1>
            <p class="muted">اطلاعات فنی سیستم، نسخه‌ها و وضعیت هاست.</p>
            <section class="card wide">
                <h2>نسخه نرم‌افزار</h2>
                <table>
                    <tbody>
                        <tr><th>نسخه CMS</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>نسخه PHP</th><td><span dir="ltr"><?= e(PHP_VERSION) ?></span></td></tr>
                        <tr><th>نسخه SQLite</th><td><span dir="ltr"><?= e(db()->query('SELECT sqlite_version()')->fetchColumn()) ?></span></td></tr>
                        <tr><th>سیستم‌عامل سرور</th><td><span dir="ltr"><?= e(PHP_OS) ?></span></td></tr>
                        <tr><th>وب‌سرور</th><td><?= e($_SERVER['SERVER_SOFTWARE'] ?? 'نامشخص') ?></td></tr>
                    </tbody>
                </table>
            </section>
            <section class="card wide">
                <h2>وضعیت هاست</h2>
                <table>
                    <tbody>
                        <tr><th>حافظه PHP</th><td><span dir="ltr"><?= e(ini_get('memory_limit')) ?></span></td></tr>
                        <tr><th>حداکثر حجم آپلود</th><td><span dir="ltr"><?= e(ini_get('upload_max_filesize')) ?></span></td></tr>
                        <tr><th>حداکثر زمان اجرا</th><td><span dir="ltr"><?= e(ini_get('max_execution_time')) ?> ثانیه</span></td></tr>
                        <tr><th>مسیر دیتابیس</th><td><code><?= e(DB_FILE) ?></code></td></tr>
                        <tr><th>حجم دیتابیس</th><td><?= e(format_bytes(@filesize(DB_FILE) ?: 0)) ?></td></tr>
                    </tbody>
                </table>
            </section>

        <?php elseif ($page === 'update'): ?>
            <h1>آپدیت سیستم</h1>
            <p class="muted">از این صفحه می‌توانید ببینید نسخه تازه‌ای از سیستم در مخزن گیت‌هاب منتشر شده یا نه و با یک کلیک سایت را آپدیت کنید. آپدیت فقط فایل‌های کد را جایگزین می‌کند؛ فایل <code>database.sqlite</code>، تنظیمات داخل آن، عکس‌های آپلودی شما در <code>uploads</code> و فولدر <code>backups</code> هرگز دست نمی‌خورند و هیچ فایل محلی‌ای پاک نمی‌شود. بعد از آپدیت، مهاجرت دیتابیس در اولین بازدید به‌صورت خودکار اجرا می‌شود و پسورد و تنظیمات فعلی حفظ می‌شوند.</p>

            <section class="card wide">
                <h2>وضعیت نسخه</h2>
                <table>
                    <tbody>
                        <tr><th>نسخه نصب‌شده فعلی</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>مخزن گیت‌هاب</th><td><code><?= e($updateCfg['repo']) ?></code></td></tr>
                        <tr><th>شاخه</th><td><code><?= e($updateCfg['branch']) ?></code></td></tr>
                        <?php if (!empty($updateCfg['zip_url'])): ?>
                        <tr><th>آدرس مستقیم فایل آپدیت</th><td><code><?= e($updateCfg['zip_url']) ?></code></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p>
                    <a class="btn" href="admin.php?page=update&check=1">بررسی نسخه تازه در گیت‌هاب</a>
                    <a class="btn" href="admin.php?page=update&check_all=1">بررسی کلی آپدیت (هسته + ماژول‌ها)</a>
                </p>

                <?php if (isset($_GET['check_all'])):
                    $modUpdates = modules_check_updates();
                ?>
                <section class="card" style="margin-top:16px">
                    <h2>وضعیت ماژول‌ها</h2>
                    <?php if (!$modUpdates['ok']): ?>
                        <div class="alert error"><?= e($modUpdates['error']) ?></div>
                    <?php elseif (empty($modUpdates['updates'])): ?>
                        <p><span class="status-pill ok">همه ماژول‌ها به‌روز هستند</span></p>
                    <?php else: ?>
                        <p><span class="status-pill warn"><?= count($modUpdates['updates']) ?> ماژول آپدیت دارد</span></p>
                        <table><thead><tr><th>ماژول</th><th>نسخه فعلی</th><th>نسخه جدید</th><th></th></tr></thead><tbody>
                        <?php foreach ($modUpdates['updates'] as $uk => $u): ?>
                            <tr>
                                <td><?= e($u['name']) ?></td>
                                <td dir="ltr"><?= e($u['local']) ?></td>
                                <td dir="ltr"><strong><?= e($u['remote']) ?></strong></td>
                                <td><a class="btn btn-sm" href="admin.php?page=modules">مدیریت</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <p class="muted">برای نصب آپدیت هر ماژول به صفحه <a href="admin.php?page=modules">مدیریت ماژول‌ها</a> بروید.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php if ($updateInfo !== null): ?>
                    <?php if (!empty($updateInfo['error'])): ?>
                        <div class="alert error"><?= e($updateInfo['error']) ?></div>
                    <?php elseif (!empty($updateInfo['checked'])): ?>
                        <?php if (!empty($updateInfo['update_available'])): ?>
                            <p><span class="status-pill ok">آپدیت تازه موجود است</span></p>
                            <p>آخرین نسخه در مخزن: <strong dir="ltr"><?= e((string) $updateInfo['latest']) ?></strong> — نسخه فعلی شما: <span dir="ltr"><?= e(APP_VERSION) ?></span></p>
                        <?php else: ?>
                            <p><span class="status-pill ok">سیستم به‌روز است</span></p>
                            <p class="muted">آخرین نسخه مخزن (<span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span>) با نسخه فعلی شما یکی است.</p>
                        <?php endif; ?>

                        <?php if (!empty($updateInfo['commits'])): ?>
                            <h3>آخرین تغییرات مخزن</h3>
                            <table>
                                <thead><tr><th>توضیح تغییر</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                <?php foreach ($updateInfo['commits'] as $c): ?>
                                    <tr><td><?= e($c['message']) ?></td><td><?= e($c['date']) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">برای دیدن آخرین نسخه و فهرست تغییرات، روی «بررسی نسخه تازه در گیت‌هاب» کلیک کنید.</p>
                <?php endif; ?>
            </section>

            <section class="card wide">
                <h2>آپدیت با یک کلیک</h2>
                <p class="muted">قبل از جایگزینی فایل‌ها، در صورت تیک خوردن گزینه زیر، از دیتابیس فعلی در فولدر <code>backups</code> بکاپ گرفته می‌شود. اگر گرفتن بکاپ ناموفق باشد، آپدیت اصلاً شروع نمی‌شود. اگر بکاپ نمی‌خواهید، تیک را بردارید؛ در این صورت آپدیت بدون بکاپ انجام می‌شود.</p>
                <form method="post" onsubmit="return confirm('فایل‌های کد سایت با نسخه تازه جایگزین شوند؟ دیتابیس و فایل‌های آپلودی شما دست نمی‌خورند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="perform_update">
                    <label class="check">
                        <input type="checkbox" name="backup_db" value="1" checked>
                        گرفتن بکاپ از دیتابیس قبل از آپدیت
                    </label>
                    <?php if ($updateInfo !== null && !empty($updateInfo['update_available'])): ?>
                        <button type="submit" class="btn primary">آپدیت به نسخه <span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span></button>
                    <?php else: ?>
                        <button type="submit" class="btn primary" disabled>اول نسخه تازه را بررسی کنید</button>
                        <p class="muted">دکمه آپدیت فقط وقتی فعال می‌شود که نسخه تازه‌تری در مخزن پیدا شود.</p>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card wide">
                <h2>تنظیمات مخزن آپدیت</h2>
                <p class="muted">مخزن و شاخه‌ای که آپدیت‌ها از آن خوانده می‌شوند. به‌صورت پیش‌فرض مخزن رسمی همین سیستم است. «آدرس مستقیم فایل ZIP آپدیت» یک گزینه پیشرفته است و معمولاً باید خالی بماند؛ اگر پر شود، دانلود و بررسی نسخه از همان آدرس انجام می‌شود (مثلاً برای میرور یا تست).</p>
                <form method="post" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_update_settings">
                    <label>مخزن گیت‌هاب (owner/repo)
                        <input type="text" name="update_repo" dir="ltr" value="<?= e($updateCfg['repo']) ?>" required>
                    </label>
                    <label>شاخه
                        <input type="text" name="update_branch" dir="ltr" value="<?= e($updateCfg['branch']) ?>" required>
                    </label>
                    <label>آدرس مستقیم فایل ZIP آپدیت (پیشرفته — معمولاً خالی)
                        <input type="text" name="update_zip_url" dir="ltr" value="<?= e($updateCfg['zip_url']) ?>" placeholder="https://example.com/update.zip">
                    </label>
                    <button type="submit" class="btn primary">ذخیره تنظیمات آپدیت</button>
                </form>
            </section>

        <?php elseif ($page === 'cleaner'): ?>
            <h1>تمیزکننده هاست</h1>
            <p class="muted">حذف امن فایل‌های موقت از هاست. فقط فایل‌های موقت تأییدشده حذف می‌شوند — دیتابیس، آپلودها، بکاپ‌ها، فایل‌های سیستمی و ماژول‌ها دست نمی‌خورند.</p>
            <?php
            $cleanerResult = null;
            // فقط این فایل‌های موقت مجاز به حذف هستند — هرگز چیز دیگری
            $allowedTemps = ['diag_temp.php', 'diag.php', 'revert_9913.php', 'clear_cache.php', 'recover.php', 'cleaner.php'];
            $root = __DIR__;
            // مرحله ۱: پیش‌نمایش — کدام فایل‌های مجاز واقعاً روی هاست هستند؟
            $found = [];
            foreach ($allowedTemps as $fn) {
                if (is_file("$root/$fn")) { $found[] = $fn; }
            }
            // مرحله ۲: حذف — فقط با تأیید صریح و تیک từng فایل
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['run_cleaner']) && isset($_POST['csrf'])) {
                if (!hash_equals($_SESSION['csrf_token'] ?? '', (string)$_POST['csrf'])) {
                    $cleanerResult = ['error' => 'توکن امنیتی نامعتبر است. صفحه را رفرش کنید و دوباره تلاش کنید.'];
                } else {
                    $deleted = [];
                    $errors = [];
                    $toDelete = $_POST['delete_files'] ?? [];
                    if (!is_array($toDelete)) { $toDelete = []; }
                    foreach ($toDelete as $fn) {
                        // اعتبارسنجی سخت‌گیرانه: فقط از لیست مجاز
                        if (!in_array($fn, $allowedTemps, true)) { continue; }
                        // محافظت مضاعف: هرگز مسیر دایرکتوری یا فایل خارج از روت
                        if (str_contains($fn, '/') || str_contains($fn, '\\')) { continue; }
                        $p = "$root/$fn";
                        if (is_file($p)) {
                            if (@unlink($p)) { $deleted[] = $fn; } else { $errors[] = $fn; }
                        }
                    }
                    $cleanerResult = ['deleted' => $deleted, 'errors' => $errors];
                    // رفرش لیست بعد از حذف
                    $found = [];
                    foreach ($allowedTemps as $fn) {
                        if (is_file("$root/$fn")) { $found[] = $fn; }
                    }
                }
            }
            ?>
            <?php if ($cleanerResult !== null): ?>
                <?php if (isset($cleanerResult['error'])): ?>
                    <div class="alert error"><?= e($cleanerResult['error']) ?></div>
                <?php else: ?>
                    <?php if (!empty($cleanerResult['deleted'])): ?>
                        <div class="alert ok">حذف شد (<?= count($cleanerResult['deleted']) ?> مورد):<br><?= e(implode(', ', $cleanerResult['deleted'])) ?></div>
                    <?php else: ?>
                        <div class="alert ok">چیزی حذف نشد.</div>
                    <?php endif; ?>
                    <?php if (!empty($cleanerResult['errors'])): ?>
                        <div class="alert error">خطا در حذف: <?= e(implode(', ', $cleanerResult['errors'])) ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            <div class="card">
                <h3>فایل‌های موقت مجاز برای حذف</h3>
                <?php if (empty($found)): ?>
                    <p class="muted">هیچ فایل موقتی روی هاست نیست — هاست تمیز است.</p>
                <?php else: ?>
                    <p>این فایل‌ها روی هاست پیدا شدند. هر کدام را که می‌خواهی حذف کنی تیک بزن:</p>
                    <form method="post" onsubmit="return confirm('فایل‌های تیک‌خورده حذف می‌شوند. مطمئنی؟');">
                        <?= csrf_field() ?>
                        <ul style="list-style:none; padding:0;">
                            <?php foreach ($found as $fn): ?>
                                <li style="margin:8px 0;">
                                    <label>
                                        <input type="checkbox" name="delete_files[]" value="<?= e($fn) ?>" checked>
                                        <code dir="ltr"><?= e($fn) ?></code>
                                    </label>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="submit" name="run_cleaner" value="1" class="btn primary">حذف فایل‌های انتخاب‌شده</button>
                    </form>
                <?php endif; ?>
                <h3 style="margin-top:20px;">محافظت‌شده (هرگز حذف نمی‌شوند)</h3>
                <ul class="muted" style="font-size:13px;">
                    <li>دیتابیس (<code dir="ltr">database.sqlite</code>)</li>
                    <li>پوشه‌های <code dir="ltr">uploads/</code> و <code dir="ltr">backups/</code></li>
                    <li>فایل <code dir="ltr">.htaccess</code></li>
                    <li>همه فایل‌های سیستمی (<code dir="ltr">admin.php</code>، <code dir="ltr">core/</code>، <code dir="ltr">admin_*.php</code>)</li>
                    <li>همه پوشه‌های ماژول (<code dir="ltr">modules/</code>)</li>
                </ul>
            </div>
        <?php elseif ($page === 'sitemap'): ?>
            <h1>نقشه سایت (Sitemap)</h1>
            <p class="muted">نقشه سایت XML به موتورهای جستجو کمک می‌کند صفحات شما را پیدا کنند. آدرس: <a href="sitemap.php" target="_blank" dir="ltr"><code>sitemap.php</code></a></p>

            <section class="card wide">
                <h2>تنظیمات نقشه سایت</h2>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_settings">
                    <label class="check"><input type="checkbox" name="sitemap_enabled" value="1" <?= ($settings['sitemap_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> نقشه سایت فعال باشد</label>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:15px;margin-top:15px;">
                        <label>تناوب صفحه اصلی
                            <select name="sitemap_home_freq">
                                <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                    <option value="<?= $f ?>" <?= ($settings['sitemap_home_freq'] ?? 'daily') === $f ? 'selected' : '' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>اولویت صفحه اصلی (0 تا 1)
                            <input type="number" name="sitemap_home_priority" min="0" max="1" step="0.1" value="<?= e($settings['sitemap_home_priority'] ?? '1.0') ?>">
                        </label>
                        <label>تناوب صفحه‌ها
                            <select name="sitemap_pages_freq">
                                <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                    <option value="<?= $f ?>" <?= ($settings['sitemap_pages_freq'] ?? 'weekly') === $f ? 'selected' : '' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>اولویت صفحه‌ها
                            <input type="number" name="sitemap_pages_priority" min="0" max="1" step="0.1" value="<?= e($settings['sitemap_pages_priority'] ?? '0.8') ?>">
                        </label>
                        <label>تناوب محصولات
                            <select name="sitemap_products_freq">
                                <?php foreach (['always','hourly','daily','weekly','monthly','yearly','never'] as $f): ?>
                                    <option value="<?= $f ?>" <?= ($settings['sitemap_products_freq'] ?? 'weekly') === $f ? 'selected' : '' ?>><?= $f ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>اولویت محصولات
                            <input type="number" name="sitemap_products_priority" min="0" max="1" step="0.1" value="<?= e($settings['sitemap_products_priority'] ?? '0.7') ?>">
                        </label>
                    </div>
                    <button type="submit" class="btn primary" style="margin-top:15px;">ذخیره تنظیمات</button>
                </form>
            </section>

            <section class="card wide">
                <h2>پیش‌نمایش URLها</h2>
                <?php
                $smBase = rtrim((string)($settings['site_url'] ?? ''), '/');
                if ($smBase === '') $smBase = 'https://linerlight.ir';
                $smUrls = [$smBase . '/'];
                try {
                    foreach (db()->query("SELECT slug FROM pages WHERE is_active=1 ORDER BY sort_order")->fetchAll() as $pr) {
                        $smUrls[] = $smBase . '/' . urlencode($pr['slug']);
                    }
                    foreach (db()->query("SELECT id FROM products WHERE is_active=1 ORDER BY sort_order")->fetchAll() as $pr) {
                        $smUrls[] = $smBase . '/products?id=' . (int)$pr['id'];
                    }
                    $smUrls[] = $smBase . '/products';
                } catch (Throwable $e) {}
                ?>
                <p class="muted"><?= count($smUrls) ?> آدرس در نقشه سایت:</p>
                <ul dir="ltr" style="text-align:left;max-height:300px;overflow:auto;">
                    <?php foreach ($smUrls as $u): ?><li><code><?= e($u) ?></code></li><?php endforeach; ?>
                </ul>
                <p><a class="btn" href="sitemap.php" target="_blank">مشاهده XML</a></p>
            </section>

        <?php else: ?>
            <h1>تنظیمات سایت</h1>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">
                <label>عنوان سایت
                    <input type="text" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>">
                </label>
                <label>توضیح سایت
                    <textarea name="site_description" rows="3"><?= e($settings['site_description'] ?? '') ?></textarea>
                </label>
                <label>عنوان سئوی صفحه اصلی (اگر خالی باشد عنوان سایت استفاده می‌شود)
                    <input type="text" name="seo_title" value="<?= e($settings['seo_title'] ?? '') ?>">
                </label>
                <label>توضیح سئوی صفحه اصلی (meta description)
                    <textarea name="seo_description" rows="2"><?= e($settings['seo_description'] ?? '') ?></textarea>
                </label>
                <label>کلمات کلیدی سئو (با کاما جدا کنید)
                    <input type="text" name="seo_keywords" value="<?= e($settings['seo_keywords'] ?? '') ?>" dir="rtl">
                </label>
                <label>آدرس کامل سایت (برای canonical و نقشه سایت) — مثال: https://linerlight.ir
                    <input type="text" name="site_url" value="<?= e($settings['site_url'] ?? '') ?>" dir="ltr" placeholder="https://...">
                </label>
                <h3>اطلاعات تماس (نمایش در صفحه تماس با ما)</h3>
                <label>شماره تماس
                    <input type="text" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" dir="ltr" placeholder="09...">
                </label>
                <label>ایمیل
                    <input type="text" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>" dir="ltr" placeholder="info@...">
                </label>
                <label>شماره واتساپ (اگر با شماره تماس یکی است خالی بگذارید)
                    <input type="text" name="contact_whatsapp" value="<?= e($settings['contact_whatsapp'] ?? '') ?>" dir="ltr" placeholder="09...">
                </label>
                <h3>مشتری‌ها و کاتالوگ محصول (فاز ۲)</h3>
                <label>درصد تخفیف همکار (وقتی برای محصول قیمت همکار جداگانه ثبت نشده)
                    <input type="number" name="partner_discount_percent" min="0" max="90" step="1" value="<?= e($settings['partner_discount_percent'] ?? '10') ?>">
                </label>
                <label>عنوان کاتالوگ محصولات
                    <input type="text" name="catalog_title" value="<?= e($settings['catalog_title'] ?? 'کاتالوگ محصولات') ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="catalog_public" value="1" <?= ($settings['catalog_public'] ?? '1') === '1' ? 'checked' : '' ?>>
                    نمایش عمومی کاتالوگ محصولات در سایت (و لینک «محصولات» در منو)
                </label>
                <h3>حقوق و دستمزد (مبنای فیش حقوقی وزارت‌کاری)</h3>
                <p class="muted">این مبالغ در محاسبه خودکار فیش حقوقی استفاده می‌شوند: بن کارگری و حق مسکن برای همه، حق اولاد به‌ازای هر فرزند، و مالیات ۱۰٪ فقط روی مازاد سقف معافیت.</p>
                <div class="inline-fields">
                    <label>بن کارگری ماهانه (تومان)
                        <input type="number" name="wage_bon_kargari" min="0" step="1" dir="ltr" value="<?= e($settings['wage_bon_kargari'] ?? '2200000') ?>">
                    </label>
                    <label>حق مسکن ماهانه (تومان)
                        <input type="number" name="wage_housing" min="0" step="1" dir="ltr" value="<?= e($settings['wage_housing'] ?? '900000') ?>">
                    </label>
                </div>
                <div class="inline-fields">
                    <label>حق اولاد هر فرزند در ماه (تومان)
                        <input type="number" name="wage_child_allowance" min="0" step="1" dir="ltr" value="<?= e($settings['wage_child_allowance'] ?? '1250000') ?>">
                    </label>
                    <label>سقف معافیت مالیاتی حقوق ماهانه (تومان)
                        <input type="number" name="wage_tax_threshold" min="0" step="1" dir="ltr" value="<?= e($settings['wage_tax_threshold'] ?? '24000000') ?>">
                    </label>
                </div>
                <h3>نشست مدیریت</h3>
                <label>مدت اعتبار نشست مدیریت (ساعت) — بعد از این مدت باید دوباره وارد شوید
                    <input type="number" name="session_lifetime_hours" min="1" max="720" step="1" value="<?= e($settings['session_lifetime_hours'] ?? '168') ?>">
                </label>
                <p class="muted">پیش‌فرض ۱۶۸ ساعت (یک هفته) است. نشست‌ها داخل پوشه داخلی خود سیستم نگه داشته می‌شوند تا روی هاست اشتراکی زود پاک نشوند. لاگ بازدید سایت و فعالیت مدیریت از منوی «لاگ‌ها» در گروه سیستم قابل مشاهده است.</p>
                <p class="muted">مشتری‌ها، دسته‌ها، محصولات و ویژگی‌ها از منوهای «مشتری‌ها»، «دسته‌بندی‌ها»، «محصولات» و «ویژگی‌های محصول» مدیریت می‌شوند.</p>
                <button type="submit" class="btn primary">ذخیره تنظیمات</button>
            </form>

            <h2>فاوآیکون سایت</h2>
            <form method="post" enctype="multipart/form-data" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_favicon">
                <?php $favPath = get_setting('favicon_path', 'uploads/gallery/favicon.png'); ?>
                <p><img src="<?= e($favPath) ?>" alt="فاوآیکون فعلی" style="width:48px;height:48px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb"></p>
                <label>انتخاب فاوآیکون جدید (PNG، ترجیحاً ۶۴×۶۴ یا ۱۲۸×۱۲۸)
                    <input type="file" name="favicon" accept="image/png,image/x-icon,image/svg+xml">
                </label>
                <button type="submit" class="btn add">آپلود فاوآیکون</button>
            </form>

            <h2>تغییر پسورد مدیریت</h2>
            <form method="post" class="card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <label>پسورد فعلی
                    <input type="password" name="current_password" required autocomplete="current-password">
                </label>
                <label>پسورد جدید (حداقل ۸ کاراکتر)
                    <input type="password" name="new_password" required autocomplete="new-password">
                </label>
                <label>تکرار پسورد جدید
                    <input type="password" name="new_password_confirm" required autocomplete="new-password">
                </label>
                <button type="submit" class="btn primary">تغییر پسورد</button>
            </form>
        <?php endif; ?>
    </main>
</div>
<script>
/* حفظ موقعیت اسکرول سایدبار (۹٫۲۵) */
(function(){
    try {
        var sb = document.querySelector('.sidebar');
        if (sb) {
            var saved = localStorage.getItem('adminSidebarScroll');
            if (saved !== null) { sb.scrollTop = parseInt(saved, 10) || 0; }
            var t;
            sb.addEventListener('scroll', function(){
                clearTimeout(t);
                t = setTimeout(function(){
                    try { localStorage.setItem('adminSidebarScroll', String(sb.scrollTop)); } catch(e){}
                }, 150);
            });
        }
    } catch(e){}
})();
window.__toggleAdminNav=function(){var b=document.body,mq=window.matchMedia('(max-width:899px)');if(mq.matches){var op=b.classList.toggle('nav-open');var t=document.getElementById('navToggle');if(t){t.setAttribute('aria-expanded',op?'true':'false')}}else{var r=b.classList.toggle('nav-rail');try{localStorage.setItem('adminNavRail',r?'1':'0')}catch(e){}if(r){var gs=document.querySelectorAll('.nav-group');for(var i=0;i<gs.length;i++){gs[i].open=true}}}};
(function(){var b=document.body,t=document.getElementById('navToggle'),o=document.getElementById('navOverlay'),mq=window.matchMedia('(max-width:899px)'),gs=[].slice.call(document.querySelectorAll('.nav-group')),st={};
try{st=JSON.parse(localStorage.getItem('adminNavGroups')||'{}')}catch(e){}
gs.forEach(function(g){var k=g.getAttribute('data-group');if(g.querySelector('a.active')){g.open=true}else if(k in st){g.open=!!st[k]}g.addEventListener('toggle',function(){st[k]=g.open;try{localStorage.setItem('adminNavGroups',JSON.stringify(st))}catch(e){}})});
function openAll(){gs.forEach(function(g){g.open=true})}
try{if(localStorage.getItem('adminNavRail')==='1'&&!mq.matches){b.classList.add('nav-rail');openAll()}}catch(e){}
function closeD(){b.classList.remove('nav-open');if(t){t.setAttribute('aria-expanded','false')}}
/* navToggle click handled by onclick -> window.__toggleAdminNav (robust against missing footer) */
if(o){o.addEventListener('click',closeD)}
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeD()}});
if(mq.addEventListener){mq.addEventListener('change',function(){if(!mq.matches){closeD()}})}})();
(function(){document.addEventListener('click',function(ev){var btn=ev.target;while(btn&&btn!==document&&!(btn.getAttribute&&btn.getAttribute('data-toggle-panel'))){btn=btn.parentNode}if(!btn||btn===document){return}var el=document.getElementById(btn.getAttribute('data-toggle-panel'));if(!el){return}var show=el.hasAttribute('hidden');if(show){el.removeAttribute('hidden');var f=el.querySelector('input,select,textarea');if(f){try{f.focus()}catch(e){}}}else{el.setAttribute('hidden','')}btn.setAttribute('aria-expanded',show?'true':'false')});
var op=document.querySelector('.crud-panel[data-open],.help-panel[data-open]');if(op){try{op.scrollIntoView({block:'start'})}catch(e){}}})();
</script>
<?php if (in_array(($page ?? ''), ['design', 'pages'], true)): ?>
<script src="assets/codemirror/lib/codemirror.min.js"></script>
<script src="assets/codemirror/mode/xml.min.js"></script>
<script src="assets/codemirror/mode/css.min.js"></script>
<script src="assets/codemirror/mode/javascript.min.js"></script>
<script src="assets/codemirror/mode/htmlmixed.min.js"></script>
<script src="assets/codemirror/mode/clike.min.js"></script>
<script src="assets/codemirror/mode/php.min.js"></script>
<script src="assets/codemirror/addon/matchbrackets.min.js"></script>
<script>
(function(){
if(typeof CodeMirror==='undefined'){return;}
document.querySelectorAll('textarea.code-editor').forEach(function(ta){
    var mode=ta.getAttribute('data-mode')||'htmlmixed';
    // Add toolbar
    var toolbar=document.createElement('div');
    toolbar.className='cm-toolbar';
    toolbar.innerHTML='<button type="button" class="btn small" data-cm="fs">⛶ تمام‌صفحه</button>'+
        '<button type="button" class="btn small" data-cm="font+">A+ بزرگ‌تر</button>'+
        '<button type="button" class="btn small" data-cm="font-">A- کوچک‌تر</button>'+
        '<span class="muted" style="font-size:12px">ویرایشگر حرفه‌ای کد — برای ذخیره، فرم را ثبت کنید</span>';
    ta.parentNode.insertBefore(toolbar,ta);
    var cm=CodeMirror.fromTextArea(ta,{
        mode:mode,theme:'dracula',lineNumbers:true,matchBrackets:true,
        lineWrapping:true,indentUnit:4,tabSize:4,indentWithTabs:false,
        viewportMargin:Infinity,extraKeys:{'Tab':function(c){c.replaceSelection('    ','end');}}
    });
    var fs=false;
    toolbar.addEventListener('click',function(e){
        var b=e.target.closest('[data-cm]');if(!b){return;}
        var act=b.getAttribute('data-cm');
        if(act==='fs'){
            fs=!fs;
            var wrap=cm.getWrapperElement();
            if(fs){wrap.classList.add('cm-fullscreen');document.body.style.overflow='hidden';b.textContent='✕ خروج از تمام‌صفحه';}
            else{wrap.classList.remove('cm-fullscreen');document.body.style.overflow='';b.textContent='⛶ تمام‌صفحه';}
            cm.refresh();
        }else if(act==='font+'){
            var el=cm.getWrapperElement();var s=parseInt(getComputedStyle(el).fontSize)||14;
            el.style.fontSize=Math.min(22,s+1)+'px';cm.refresh();
        }else if(act==='font-'){
            var el2=cm.getWrapperElement();var s2=parseInt(getComputedStyle(el2).fontSize)||14;
            el2.style.fontSize=Math.max(10,s2-1)+'px';cm.refresh();
        }
    });
    // Save back to textarea on form submit
    var form=ta.closest('form');
    if(form){form.addEventListener('submit',function(){cm.save();});}
});
})();
</script>
<?php endif; ?>
<script src="assets/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// استایل داخلی پنل (برای اینکه admin.php مستقل بماند)
function admin_custom_style_css(): string
{
    $css = '';
    $sbBg = (string) get_setting('admin_sidebar_bg', '');
    $sbText = (string) get_setting('admin_sidebar_text', '');
    $hBg = (string) get_setting('admin_header_bg', '');
    $accent = (string) get_setting('admin_accent', '');
    $fontSize = (string) get_setting('admin_font_size', '');
    $radius = (string) get_setting('admin_radius', '');
    $cardPad = (string) get_setting('admin_card_padding', '');
    if ($sbBg !== '' && $sbBg !== '#ffffff') { $css .= ".sidebar{background:" . $sbBg . "!important}"; }
    if ($sbText !== '' && $sbText !== '#111827') { $css .= ".sidebar .nav-link{color:" . $sbText . "!important}.sidebar .nav-group-title{color:" . $sbText . "!important;opacity:.7}"; }
    if ($hBg !== '' && $hBg !== '#ffffff') { $css .= ".topbar{background:" . $hBg . "!important}"; }
    if ($accent !== '' && $accent !== '#2563eb') { $css .= "a{color:" . $accent . "}.btn.primary{background:" . $accent . ";border-color:" . $accent . "}"; }
    if ($fontSize !== '' && $fontSize !== '14') { $css .= "body{font-size:" . (int)$fontSize . "px}"; }
    if ($radius !== '' && $radius !== '8') { $css .= ".card,.btn,input,select,textarea{border-radius:" . (int)$radius . "px}"; }
    if ($cardPad !== '' && $cardPad !== '16') { $css .= ".card{padding:" . (int)$cardPad . "px}"; }
    return $css !== '' ? "<style>" . $css . "</style>" : '';
}

function admin_css(): string
{
    return <<<'CSS'
*{box-sizing:border-box}body{font-family:Tahoma,Arial,sans-serif;background:#f3f4f6;color:#111827;margin:0}
a{color:#2563eb;text-decoration:none}.muted{color:#6b7280;font-size:13px}.center{text-align:center}
.auth-box{max-width:380px;margin:10vh auto;background:#fff;padding:24px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
label{display:block;margin:12px 0;font-size:14px}input[type=text],input[type=password],input[type=number],select,textarea,input[type=file]{width:100%;padding:9px;margin-top:6px;border:1px solid #d1d5db;border-radius:8px;font-family:inherit}
textarea[dir=ltr]{font-family:Consolas,monospace;font-size:13px}
button{font-family:inherit;font-size:13px;line-height:1.6}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:7px 14px;min-height:36px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#111827;cursor:pointer;font-family:inherit;font-size:13px;line-height:1.6;text-decoration:none;white-space:nowrap;vertical-align:middle;box-sizing:border-box;-webkit-appearance:none;appearance:none}
.btn:hover{border-color:#9ca3af;color:#111827}
.btn.primary{background:#2563eb;border-color:#2563eb;color:#fff}.btn.primary:hover{background:#1d4ed8;color:#fff}.btn.small{padding:4px 10px;min-height:30px;font-size:12px;border-radius:7px}.btn.block{width:100%}
.btn.danger-btn{background:#dc2626;border-color:#dc2626;color:#fff}.btn.danger-btn:hover{background:#b91c1c;color:#fff}
.btn.add{background:#16a34a;border-color:#15803d;color:#fff;font-weight:bold}
.btn.add:hover{background:#15803d;color:#fff}
.btn.edit{background:#facc15;border-color:#ca8a04;color:#422006;font-weight:bold}
.btn.edit:hover{background:#eab308;color:#422006}
.btn.warn{background:#fef3c7;border-color:#f59e0b;color:#92400e}.btn.warn:hover{border-color:#d97706;color:#92400e}
.btn[disabled],.btn.disabled{opacity:.55;cursor:default;pointer-events:none}
.crud-toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:14px 0 4px}
.crud-panel{border:1px dashed #86efac;background:#f0fdf4;border-radius:10px;padding:4px 14px 14px;margin:10px 0 16px}
.crud-panel .card{margin:8px 0;box-shadow:none}
.help-panel{border:1px solid #bfdbfe;background:#eff6ff;border-radius:10px;padding:4px 16px 12px;margin:10px 0 16px}
td.actions{white-space:normal;display:flex;flex-wrap:wrap;gap:6px;align-items:center}
td.actions form{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap}
button.danger{color:#dc2626;border-color:#fecaca}
.alert{padding:10px 14px;border-radius:8px;margin:12px 0;font-size:14px}.alert.ok{background:#dcfce7}.alert.error{background:#fee2e2}
.status-dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;box-shadow:0 0 0 3px #dcfce7;margin-inline-end:7px;vertical-align:middle}
.status-pill{display:inline-flex;align-items:center;padding:5px 11px;border-radius:99px;font-size:13px;font-weight:bold}.status-pill.ok{background:#dcfce7;color:#166534}.status-pill.error{background:#fee2e2;color:#991b1b}.status-pill .status-dot{box-shadow:none;margin-inline-end:6px}
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:14px}.stat-card{background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:14px}.stat-card span{display:block;color:#6b7280;font-size:13px;margin-bottom:6px}.stat-card strong{font-size:24px;color:#111827}
.content{flex:1;padding:20px;max-width:1050px}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;margin:14px 0}
th,td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:right;font-size:14px;vertical-align:top}
th{background:#f9fafb}.actions{white-space:nowrap}.inline{display:inline}
.card{background:#fff;padding:16px;border-radius:10px;margin:14px 0;max-width:700px;display:block;border:0}
.card.wide{max-width:100%}
/* فرم‌های چندفیلدی کنار هم (فیلترها و فرم‌های چندستونه): ردیف فلکس با شکستن منظم */
.inline-fields{display:flex;flex-wrap:wrap;gap:10px 14px;align-items:flex-end}
.inline-fields label{margin:0;min-width:150px;flex:1 1 170px}
.inline-fields .btn{align-self:flex-end}
/* نگهبان تداخل با بوت‌استرپ: کامپوننت‌های اختصاصی پنل همیشه شکل خودشان را نگه می‌دارند */
.alert{border:0}
.badge{line-height:1.4}
.badge{padding:2px 8px;border-radius:99px;font-size:12px}.badge.ok{background:#dcfce7}.badge.off{background:#e5e7eb}
.tabs{display:flex;gap:6px;margin:0 0 12px;flex-wrap:wrap}.tabs .tab{padding:7px 14px;border:1px solid #d1d5db;border-radius:8px;color:#111827;background:#f9fafb}.tabs .tab.active{background:#2563eb;border-color:#2563eb;color:#fff}
.chips{display:flex;flex-wrap:wrap;gap:8px}.chip{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1px solid #e5e7eb;border-radius:99px;background:#fff;font-size:13px;color:#374151}.chip:hover{border-color:#2563eb;color:#2563eb;text-decoration:none}.chip.active{background:#2563eb;border-color:#2563eb;color:#fff}.chip .dot{width:9px;height:9px;border-radius:99px;display:inline-block}
.check{display:flex;gap:8px;align-items:center}.file-input{direction:ltr;display:flex;align-items:center;gap:4px}
code{background:#f3f4f6;padding:1px 5px;border-radius:5px;direction:ltr;display:inline-block}
.ph-list{line-height:2.1}.ph-list code{margin-inline-end:6px}
.design-tabs{display:flex;gap:8px;margin:16px 0;flex-wrap:wrap}
.design-tabs a{padding:8px 18px;border:1px solid #d1d5db;border-radius:99px;background:#fff;color:#111827}
.design-tabs a.active{background:#2563eb;border-color:#2563eb;color:#fff;font-weight:bold}
.tpl-preview{width:100%;height:440px;border:1px solid #e5e7eb;border-radius:10px;background:#fff}
input[type=color]{width:72px;height:38px;padding:2px;border:1px solid #d1d5db;border-radius:8px;background:#fff;vertical-align:middle;cursor:pointer}
@media (max-width:899px){
.layout{flex-direction:column}
.content{padding:14px}
.dash-charts{grid-template-columns:1fr!important}
.dash-charts .card{min-width:0;overflow:hidden}
.dash-charts canvas{max-width:100%!important;height:auto!important}
table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}
.tpl-preview{height:320px}
.sidebar{position:fixed;top:0;bottom:0;inset-inline-start:0;width:min(300px,86vw);z-index:80;transform:translateX(110%);transition:transform .25s ease;box-shadow:0 0 44px rgba(0,0,0,.25)}
[dir="rtl"] .sidebar{transform:translateX(110%)}
body.nav-open .sidebar{transform:none!important}
.nav-overlay{display:block;position:fixed;inset:0;z-index:75;background:rgba(0,0,0,.5);opacity:0;pointer-events:none;transition:opacity .25s}
body.nav-open .nav-overlay{opacity:1;pointer-events:auto}
}
/* منوی کناری */
.topbar{position:sticky;top:0;z-index:70;display:flex;justify-content:space-between;align-items:center;background:#ffffff;color:#1a1a18;padding:8px 14px;gap:10px;box-shadow:0 1px 10px rgba(0,0,0,.08);border-bottom:1px solid #e8e8e3}
.topbar .topbar-title{color:#1a1a18!important;font-weight:800;font-size:16px}
.topbar .menu-toggle{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:10px;border:2px solid #1a1a18;background:#fff;color:#1a1a18;font-size:20px;cursor:pointer}
.topbar .menu-toggle:hover{background:#1a1a18;color:#fff}
.topbar .topbar-actions .btn{border:1px solid #d1d5db;background:#fff;color:#1a1a18;font-weight:600}
.topbar .topbar-actions .btn:hover{border-color:#b08d57;color:#b08d57}
body.nav-open .topbar{z-index:85}
.topbar-start{display:flex;align-items:center;gap:10px;min-width:0}
.topbar-title{font-size:16px;white-space:nowrap}
.topbar-actions{display:flex;align-items:center;gap:6px}
@media(max-width:768px){
.topbar{padding:8px 10px;gap:8px}
.topbar .topbar-title{font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.topbar .menu-toggle{width:44px;height:44px;font-size:22px;flex-shrink:0}
.topbar-actions{gap:4px}
.topbar-actions .quick-add{display:none}
.topbar-actions .icon-btn{width:38px;height:38px}
.user-menu-wrap .icon-btn{width:38px;height:38px}
.user-menu-wrap{position:relative}
.user-menu{position:absolute;top:calc(100% + 8px);inset-inline-end:0;min-width:220px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.15);padding:6px;z-index:1000}
.user-menu[hidden]{display:none}
.user-menu-head{padding:10px 12px;border-bottom:1px solid #f1f5f9;margin-bottom:4px}
.user-menu-head strong{display:block;font-size:14px;color:#1a1a18}
.user-menu-head .muted{font-size:12px;color:#64748b}
.user-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:#334155;font-size:14px;text-decoration:none}
.user-menu a:hover{background:#f8fafc}
.user-menu a.danger{color:#dc2626}
.user-menu a.danger:hover{background:#fef2f2}
}
.topbar-actions a{margin-inline-start:0;color:#1a1a18;padding:7px 11px;border-radius:8px;background:#f3f4f6;white-space:nowrap;font-weight:600}
.topbar-actions a:hover{background:#e8e8e3;color:#1a1a18}
.icon-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;flex:0 0 auto;border-radius:8px;border:1px solid #d1d5db;background:#f3f4f6;color:#1a1a18;cursor:pointer;padding:0}
.icon-btn:hover{background:rgba(255,255,255,.12)}
.nav-ico{width:18px;height:18px;flex:0 0 auto;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
.layout{display:flex;min-height:calc(100vh - 54px)}
.sidebar{width:232px;background:#fff;border-inline-end:1px solid #e5e7eb;display:flex;flex-direction:column;padding:0;overflow-y:auto}
.sidebar-brand{display:flex;align-items:center;gap:10px;padding:16px 16px 12px;border-bottom:1px solid #eef0f3}
.brand-logo{width:40px;height:40px;border-radius:12px;object-fit:contain;background:#0f172a;padding:4px;flex-shrink:0}.brand-sub{font-size:11px;color:#8a94a6;font-weight:600;flex-shrink:0;white-space:nowrap}
.brand-name{font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;min-width:0}
.nav-group{border-bottom:1px solid #eef0f3;padding:4px 10px 10px}
.nav-group summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;padding:8px 8px 6px;font-size:12px;font-weight:bold;color:#6b7280;border-radius:6px}
.nav-group summary::-webkit-details-marker{display:none}
.nav-group summary::after{content:"";width:7px;height:7px;border-inline-end:2px solid #9ca3af;border-bottom:2px solid #9ca3af;transform:rotate(-45deg);transition:transform .2s}
.nav-group[open] summary::after{transform:rotate(45deg)}
.sidebar a{position:relative;display:flex;align-items:center;gap:9px;padding:9px 10px;margin:2px 0;border-radius:8px;color:#111827}
.sidebar a:hover{background:#f3f4f6}
.sidebar a.active{background:#eff6ff;color:#2563eb;font-weight:bold}
.sidebar a.active::before{content:"";position:absolute;inset-inline-start:-10px;top:8px;bottom:8px;width:3px;border-radius:99px;background:#2563eb}
.nav-label{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.nav-badge{margin-inline-start:auto;background:#2563eb;color:#fff;font-size:11px;font-weight:bold;min-width:20px;height:20px;padding:0 6px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center}
.sidebar a .status-dot{margin-inline-start:auto}
.sidebar-version{margin-top:auto;border-top:1px solid #eef0f3;padding:12px 16px;display:flex;align-items:center;gap:8px;color:#9ca3af;font-size:12px}
.version-badge{background:#eef2ff;color:#2563eb;border:1px solid #dbeafe;padding:2px 8px;border-radius:99px;font-weight:bold;font-size:12px}
.sidebar a:focus-visible,.icon-btn:focus-visible,.nav-group summary:focus-visible,.topbar-actions a:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
.dash-cards a.stat-card{display:block;color:inherit}


/* ===== تم معمارپسند (۹٫۲۳) — مینیمال و دقیق ===== */
html[data-admin-theme="architect"]{
    --arch-bg:#fafaf8;
    --arch-surface:#ffffff;
    --arch-ink:#1a1a18;
    --arch-muted:#8a8a85;
    --arch-line:#e8e8e3;
    --arch-brass:#b08d57;
    --arch-brass-light:#d4b896;
}
html[data-admin-theme="architect"] body{background:var(--arch-bg);color:var(--arch-ink);font-family:'Vazirmatn',Tahoma,sans-serif}
/* سایدبار */
html[data-admin-theme="architect"] .sidebar{background:#1a1a18!important;border-inline-end:1px solid #2a2a28}
html[data-admin-theme="architect"] .sidebar{background:#1e1e1c!important}
html[data-admin-theme="architect"] .sidebar .nav-link{color:#e8e8e3!important;border-radius:8px;margin:2px 8px;transition:all .2s;font-size:13.5px}
html[data-admin-theme="architect"] .sidebar .nav-link:hover{background:rgba(176,141,87,.2)!important;color:#fff!important}
html[data-admin-theme="architect"] .sidebar .nav-link.active{background:linear-gradient(135deg,#b08d57,#d4b896)!important;color:#1a1a18!important;font-weight:700}
html[data-admin-theme="architect"] .sidebar .nav-group-title{color:#d4b896!important;font-weight:700;font-size:11px;letter-spacing:1px;opacity:1!important;margin:12px 16px 6px!important}
html[data-admin-theme="architect"] .sidebar .nav-link .nav-icon{color:inherit!important;opacity:1}
html[data-admin-theme="architect"] .sidebar .nav-link small, html[data-admin-theme="architect"] .sidebar .nav-link .badge{color:inherit!important}
html[data-admin-theme="architect"] .sidebar-brand{border-bottom:1px solid #333330!important}
html[data-admin-theme="architect"] .sidebar-brand span, html[data-admin-theme="architect"] .sidebar-brand a{color:#fff!important}
/* دکمه‌های تم */
html[data-admin-theme="architect"] .theme-switcher button{color:#e8e8e3!important;border:1px solid #444440!important;background:rgba(255,255,255,.05)!important}
html[data-admin-theme="architect"] .theme-switcher button.active{background:#b08d57!important;color:#1a1a18!important;border-color:#b08d57!important}
/* دکمه‌های تاپ‌بار */
html[data-admin-theme="architect"] .topbar .btn{color:#1a1a18!important;border-color:#d8d8d3!important;background:#fff!important;font-weight:600}
html[data-admin-theme="architect"] .topbar .btn:hover{border-color:#b08d57!important;color:#b08d57!important}
/* کارت‌ها */
html[data-admin-theme="architect"] .card{background:var(--arch-surface);border:1px solid var(--arch-line);border-radius:4px;box-shadow:0 1px 3px rgba(26,26,24,.06)}
html[data-admin-theme="architect"] .card h1, html[data-admin-theme="architect"] .card h2, html[data-admin-theme="architect"] .card h3{font-weight:700;letter-spacing:-.3px}
/* دکمه‌ها */
html[data-admin-theme="architect"] .btn{border-radius:4px;font-weight:600;letter-spacing:.2px}
html[data-admin-theme="architect"] .btn.primary{background:#1a1a18;border-color:#1a1a18}
html[data-admin-theme="architect"] .btn.primary:hover{background:#b08d57;border-color:#b08d57}
html[data-admin-theme="architect"] .btn.add{background:#b08d57;border-color:#b08d57;color:#fff}
html[data-admin-theme="architect"] .btn.add:hover{background:#9a7a4a}
/* جدول */
html[data-admin-theme="architect"] table{border:1px solid var(--arch-line)}
html[data-admin-theme="architect"] table thead{background:#1a1a18;color:#fff}
html[data-admin-theme="architect"] table thead th{font-weight:600;font-size:12px;letter-spacing:.5px;padding:12px}
html[data-admin-theme="architect"] table tbody td{border-bottom:1px solid var(--arch-line);padding:12px}
html[data-admin-theme="architect"] table tbody tr:hover{background:#fafaf8}
/* تیترها */
html[data-admin-theme="architect"] h1{font-size:24px;font-weight:800;letter-spacing:-.5px;margin-bottom:16px}
html[data-admin-theme="architect"] h1::after{content:'';display:block;width:48px;height:3px;background:var(--arch-brass);margin-top:8px}
/* اینپوت */
html[data-admin-theme="architect"] input, html[data-admin-theme="architect"] select, html[data-admin-theme="architect"] textarea{border:1px solid var(--arch-line)!important;border-radius:4px!important}
html[data-admin-theme="architect"] input:focus, html[data-admin-theme="architect"] select:focus, html[data-admin-theme="architect"] textarea:focus{border-color:var(--arch-brass)!important;box-shadow:0 0 0 3px rgba(176,141,87,.12)!important;outline:none!important}
/* تاپ‌بار */
html[data-admin-theme="architect"] .topbar{background:#1a1a18!important;border-bottom:2px solid #b08d57!important;box-shadow:0 2px 12px rgba(0,0,0,.3)!important;color:#fff!important}
html[data-admin-theme="architect"] .topbar .topbar-title{color:#fff!important;font-weight:800!important;font-size:16px!important}
html[data-admin-theme="architect"] .topbar .menu-toggle{color:#fff!important;border:2px solid #b08d57!important;background:transparent!important;border-radius:8px!important;padding:8px 12px!important;font-size:18px!important;font-weight:800!important}
html[data-admin-theme="architect"] .topbar .menu-toggle:hover{background:#b08d57!important;border-color:#b08d57!important;color:#1a1a18!important}
html[data-admin-theme="architect"] .topbar .topbar-actions .btn{color:#fff!important;border:1px solid rgba(176,141,87,.5)!important;background:transparent!important;font-weight:600!important}
html[data-admin-theme="architect"] .topbar .topbar-actions .btn:hover{background:#b08d57!important;border-color:#b08d57!important;color:#1a1a18!important}
html[data-admin-theme="architect"] .topbar .user-menu-btn{color:#1a1a18!important;border:2px solid #1a1a18!important}
html[data-admin-theme="architect"] .topbar .date-pill{background:#1a1a18!important;color:#fff!important;font-weight:600!important}

/* ===== داشبورد پرچم‌دار (۹٫۲۳) ===== */
html[data-admin-theme="architect"] .flagship-hero{background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#312e81 100%);border-radius:24px;padding:32px;margin-bottom:24px;position:relative;overflow:hidden;color:#fff}
html[data-admin-theme="architect"] .flagship-hero::before{content:'';position:absolute;top:-50%;right:-10%;width:400px;height:400px;background:radial-gradient(circle,rgba(139,92,246,.3),transparent 70%);pointer-events:none}
html[data-admin-theme="architect"] .flagship-hero::after{content:'';position:absolute;bottom:-30%;left:-5%;width:300px;height:300px;background:radial-gradient(circle,rgba(34,211,238,.25),transparent 70%);pointer-events:none}
html[data-admin-theme="architect"] .flagship-hero h2{font-size:26px;font-weight:800;margin:0 0 8px;position:relative;z-index:1}
html[data-admin-theme="architect"] .flagship-hero p{opacity:.7;margin:0;position:relative;z-index:1}
html[data-admin-theme="architect"] .flagship-hero .hero-stats{display:flex;gap:24px;margin-top:20px;position:relative;z-index:1}
html[data-admin-theme="architect"] .flagship-hero .hero-stat{text-align:center}
html[data-admin-theme="architect"] .flagship-hero .hero-stat strong{display:block;font-size:24px;font-weight:800}
html[data-admin-theme="architect"] .flagship-hero .hero-stat span{font-size:12px;opacity:.6}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi{transition:transform .3s ease,box-shadow .3s ease}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi:hover{transform:translateY(-4px) scale(1.02)}
html[data-admin-theme="architect"] .dash-cards .stat-card{transition:transform .25s ease,box-shadow .25s ease}
html[data-admin-theme="architect"] .dash-cards .stat-card:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(0,0,0,.12)}
html[data-admin-theme="architect"] @keyframes flagship-pulse{0%,100%{opacity:1}50%{opacity:.7}}
html[data-admin-theme="architect"] .flagship-live{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:#4ade80}
html[data-admin-theme="architect"] .flagship-live::before{content:'';width:8px;height:8px;border-radius:50%;background:#4ade80;animation:flagship-pulse 2s infinite}

/* ===== داشبورد مدرن — کارت‌های آماری ===== */
html[data-admin-theme="architect"] .dash-cards{grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:16px}
html[data-admin-theme="architect"] @media(max-width:640px){.dash-cards{grid-template-columns:repeat(2,1fr)!important;gap:10px}.dash-cards .stat-card{padding:14px 12px}.dash-cards .stat-card strong{font-size:22px}.dash-kpi{grid-template-columns:repeat(2,1fr)!important}}
html[data-admin-theme="architect"] .dash-cards .stat-card{position:relative;background:rgba(255,255,255,.7);backdrop-filter:blur(12px) saturate(1.4);-webkit-backdrop-filter:blur(12px) saturate(1.4);border:1px solid rgba(255,255,255,.6);border-radius:20px;padding:16px;overflow:hidden;transition:transform .22s ease,box-shadow .22s ease;box-shadow:0 8px 24px rgba(0,0,0,.08),inset 0 1px 0 rgba(255,255,255,.8)}
html[data-admin-theme="architect"] .dash-cards .stat-card::after{content:'';position:absolute;top:-30px;inset-inline-end:-30px;width:90px;height:90px;border-radius:50%;background:rgba(37,99,235,.06);pointer-events:none}
html[data-admin-theme="architect"] .dash-cards .stat-card::before{display:none}
html[data-admin-theme="architect"] .dash-cards a.stat-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px rgba(17,24,39,.12);border-color:#cbd5e1;text-decoration:none}
html[data-admin-theme="architect"] .dash-cards .stat-card span{display:flex;align-items:center;gap:12px;color:#64748b;font-size:13px;font-weight:600;margin-bottom:10px}
html[data-admin-theme="architect"] .dash-cards .stat-card strong{display:block;font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-.5px}
html[data-admin-theme="architect"] .dash-cards .stat-card .sc-sub{display:block;font-size:12px;color:#94a3b8;font-weight:400;margin-top:4px}
/* آیکون دایره‌ای */
html[data-admin-theme="architect"] .dash-cards .stat-card .sc-ico{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:12px;background:#f1f5f9;flex-shrink:0;color:#475569}.dash-cards .stat-card .sc-ico svg{width:20px;height:20px;display:block}
/* رنگ‌بندی نوار بالای کارت */
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-blue::before{background:linear-gradient(90deg,#3b82f6,#2563eb)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-blue .sc-ico{background:#dbeafe}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-cyan .sc-ico{background:#cffafe;color:#0e7490}
html[data-admin-theme="architect"] .stat-card.stat-dual{flex-direction:column;align-items:stretch!important}
html[data-admin-theme="architect"] .stat-card.stat-dual .dual-rows{display:flex;flex-direction:column;gap:8px;margin-top:8px}
html[data-admin-theme="architect"] .stat-card.stat-dual .dual-rows>div{display:flex;align-items:center;justify-content:space-between;background:rgba(14,116,144,.08);border-radius:10px;padding:8px 12px}
html[data-admin-theme="architect"] .stat-card.stat-dual .dual-rows small{display:block;font-size:11px;color:#64748b;margin-bottom:2px}
html[data-admin-theme="architect"] .stat-card.stat-dual .dual-rows strong{font-size:18px;color:#0e7490}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-green::before{background:linear-gradient(90deg,#10b981,#059669)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-green .sc-ico{background:#d1fae5}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-amber::before{background:linear-gradient(90deg,#f59e0b,#d97706)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-amber .sc-ico{background:#fef3c7}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-red::before{background:linear-gradient(90deg,#ef4444,#dc2626)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-red .sc-ico{background:#fee2e2}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-purple::before{background:linear-gradient(90deg,#8b5cf6,#7c3aed)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-purple .sc-ico{background:#ede9fe}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-teal::before{background:linear-gradient(90deg,#14b8a6,#0d9488)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-teal .sc-ico{background:#ccfbf1}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-indigo::before{background:linear-gradient(90deg,#6366f1,#4f46e5)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-indigo .sc-ico{background:#e0e7ff}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-rose::before{background:linear-gradient(90deg,#f43f5e,#e11d48)}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-rose .sc-ico{background:#ffe4e6}
/* کارت‌های KPI — شیشه‌ای نئونی، کامپکت با آیکون (۹٫۱۶٫۱) */
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi{color:#fff;border:1px solid rgba(255,255,255,.25);padding:16px;backdrop-filter:blur(12px) saturate(1.4);-webkit-backdrop-filter:blur(12px) saturate(1.4);border-radius:20px;box-shadow:0 8px 24px rgba(0,0,0,.12),inset 0 1px 0 rgba(255,255,255,.25);position:relative;overflow:hidden}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi::after{content:'';position:absolute;top:-30px;inset-inline-end:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi::before{display:none}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi span{color:rgba(255,255,255,.85);font-size:12px;font-weight:600}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi strong{color:#fff;font-size:22px;font-weight:800;text-shadow:0 1px 8px rgba(0,0,0,.2);letter-spacing:-.5px}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi .sc-ico{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;font-size:22px;background:rgba(255,255,255,.25);backdrop-filter:blur(4px);color:#fff;border:1px solid rgba(255,255,255,.3);box-shadow:0 0 16px rgba(255,255,255,.25);border-radius:14px;flex-shrink:0}.dash-kpi .stat-card.kpi .sc-ico svg{width:24px;height:24px;display:block}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi .sc-sub{color:rgba(255,255,255,.75);font-size:11px}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi-revenue{background:linear-gradient(135deg,rgba(16,185,129,.85),rgba(5,150,105,.75))}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi-orders{background:linear-gradient(135deg,rgba(59,130,246,.85),rgba(37,99,235,.75))}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi-employees{background:linear-gradient(135deg,rgba(139,92,246,.85),rgba(124,58,237,.75))}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi-payroll{background:linear-gradient(135deg,rgba(245,158,11,.85),rgba(217,119,6,.75))}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi-assets{background:linear-gradient(135deg,rgba(20,184,166,.85),rgba(13,148,136,.75))}
html[data-admin-theme="architect"] .dash-cards a.stat-card.kpi:hover{transform:translateY(-2px);box-shadow:0 14px 34px rgba(17,24,39,.25),0 0 24px rgba(255,255,255,.15)}
/* چیدمان کارت‌ها: ۳ در ردیف، آیکون و اطلاعات در یک خط (۹٫۱۶٫۳) */
html[data-admin-theme="architect"] .dash-cards{grid-template-columns:repeat(3,1fr)!important;gap:12px}
html[data-admin-theme="architect"] .dash-cards .stat-card{display:flex;align-items:center;gap:10px;padding:12px 14px}
html[data-admin-theme="architect"] .dash-cards .stat-card .sc-ico{flex-shrink:0}
html[data-admin-theme="architect"] .dash-cards .stat-card span{flex:1;font-size:13px}
html[data-admin-theme="architect"] .dash-cards .stat-card strong{font-size:20px;white-space:nowrap}
/* کارت‌های رنگی KPI: ۲ در ردیف، نوشته راست و عدد چپ */
html[data-admin-theme="architect"] .dash-kpi{grid-template-columns:repeat(2,1fr)!important;gap:12px;margin-top:12px}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi{display:flex;align-items:center;justify-content:space-between;gap:12px}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi span{display:flex;align-items:center;gap:10px;text-align:right;flex:1;min-width:0}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi span .sc-ico{flex-shrink:0}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi strong{text-align:left;white-space:nowrap}
/* KPI در موبایل هم ۲ ستونه می‌ماند */
/* موبایل: کارت‌ها تک‌ردیفه و جمع‌وجور (۹٫۱۶٫۱) */
html[data-admin-theme="architect"] @media (max-width:640px){
html[data-admin-theme="architect"] .topbar{padding:8px 10px}
html[data-admin-theme="architect"] .topbar-title{font-size:14px}
html[data-admin-theme="architect"] .topbar-actions .quick-add{display:none}
html[data-admin-theme="architect"] .sidebar{position:fixed;inset-inline-start:0;top:54px;bottom:0;z-index:90;transform:translateX(100%);transition:transform .3s;box-shadow:-8px 0 24px rgba(0,0,0,.15)}
html[data-admin-theme="architect"] [dir="rtl"] .sidebar{transform:translateX(100%)}
html[data-admin-theme="architect"] body.nav-open .sidebar{transform:translateX(0)!important}
html[data-admin-theme="architect"] .sidebar-brand{display:flex!important}
html[data-admin-theme="architect"] .brand-logo{width:36px;height:36px}
html[data-admin-theme="architect"] .dash-charts{grid-template-columns:1fr!important}
  /* جلوگیری از اسکرول افقی */
html[data-admin-theme="architect"] body{overflow-x:hidden}
html[data-admin-theme="architect"] .main{overflow-x:hidden}
html[data-admin-theme="architect"] .chartjs-wrap{max-width:100%;overflow:hidden}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi{min-width:0}
html[data-admin-theme="architect"] .dash-kpi .stat-card.kpi strong{font-size:16px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
}
/* کارت‌های هشدار */
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-alert-amber{background:#fffbeb;border-color:#fde68a}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-alert-red{background:#fef2f2;border-color:#fecaca}
html[data-admin-theme="architect"] .dash-cards .stat-card.sc-alert-blue{background:#eff6ff;border-color:#bfdbfe}
/* سربرگ داشبورد */
html[data-admin-theme="architect"] .dash-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:6px}
html[data-admin-theme="architect"] .dash-head h1{margin:0;font-size:26px;font-weight:800;color:#0f172a}
html[data-admin-theme="architect"] .dash-date{color:#334155;font-size:13px;font-weight:600;background:#f1f5f9;padding:8px 14px;border-radius:99px;border:1px solid #e2e8f0;display:inline-flex;align-items:center;gap:8px;position:relative;cursor:help}
html[data-admin-theme="architect"] .dash-date .nav-ico{width:18px;height:18px}
html[data-admin-theme="architect"] .has-tooltip .tooltip{position:absolute;top:calc(100% + 8px);inset-inline-start:0;min-width:220px;background:#1e293b;color:#f1f5f9;padding:12px 14px;border-radius:12px;font-size:13px;font-weight:400;box-shadow:0 8px 24px rgba(0,0,0,.25);opacity:0;pointer-events:none;transition:opacity .2s;z-index:1000;text-align:start}
html[data-admin-theme="architect"] .has-tooltip:hover .tooltip{opacity:1}
html[data-admin-theme="architect"] .has-tooltip .tooltip .muted{color:#94a3b8}

html[data-admin-theme="architect"] .user-menu-wrap{position:relative}
html[data-admin-theme="architect"] .user-menu{position:absolute;top:calc(100% + 8px);inset-inline-end:0;min-width:220px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 32px rgba(0,0,0,.15);padding:6px;z-index:1000}
html[data-admin-theme="architect"] .user-menu[hidden]{display:none}
html[data-admin-theme="architect"] .user-menu-head{padding:10px 12px;border-bottom:1px solid #f1f5f9;margin-bottom:4px}
html[data-admin-theme="architect"] .user-menu-head strong{display:block;font-size:14px}
html[data-admin-theme="architect"] .user-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:8px;color:#334155;font-size:14px;text-decoration:none}
html[data-admin-theme="architect"] .user-menu a:hover{background:#f8fafc}
html[data-admin-theme="architect"] .user-menu a.danger{color:#dc2626}
html[data-admin-theme="architect"] .user-menu a.danger:hover{background:#fef2f2}
html[data-admin-theme="architect"] .user-menu a .nav-ico{width:18px;height:18px}
/* کارت‌های نمودار */
html[data-admin-theme="architect"] .dash-charts{margin-top:20px;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:16px}
html[data-admin-theme="architect"] .dash-charts .card{border:1px solid #e8ecf1;border-radius:16px;box-shadow:0 2px 12px rgba(17,24,39,.05);margin:0;max-width:none}
html[data-admin-theme="architect"] .dash-charts .card h3{font-size:15px;font-weight:700;color:#0f172a;margin:0 0 14px;padding-bottom:10px;border-bottom:1px solid #f1f5f9}
/* ریسپانسیو */
html[data-admin-theme="architect"] @media (max-width:640px){
html[data-admin-theme="architect"] .dash-cards{grid-template-columns:repeat(2,1fr);gap:10px}
html[data-admin-theme="architect"] .dash-cards .stat-card{padding:14px 12px;border-radius:12px}
html[data-admin-theme="architect"] .dash-cards .stat-card strong{font-size:19px}
html[data-admin-theme="architect"] .dash-cards .stat-card .sc-ico{width:30px;height:30px;font-size:15px;border-radius:9px}
html[data-admin-theme="architect"] .dash-cards .stat-card span{font-size:11px}
html[data-admin-theme="architect"] .dash-charts{grid-template-columns:1fr}
html[data-admin-theme="architect"] .dash-head h1{font-size:21px}
}
html[data-admin-theme="architect"] .nav-overlay{display:none}
html[data-admin-theme="architect"] @media (min-width:900px){
html[data-admin-theme="architect"] .sidebar{position:sticky;top:54px;height:calc(100vh - 54px)}
html[data-admin-theme="architect"] body.nav-rail .sidebar{width:78px}
html[data-admin-theme="architect"] body.nav-rail .brand-name, html[data-admin-theme="architect"] body.nav-rail .nav-label, html[data-admin-theme="architect"] body.nav-rail .nav-group summary, html[data-admin-theme="architect"] body.nav-rail .sidebar-version{display:none}
html[data-admin-theme="architect"] body.nav-rail .sidebar-brand{justify-content:center;padding:14px 6px 10px}
html[data-admin-theme="architect"] body.nav-rail .nav-group{padding:8px}
html[data-admin-theme="architect"] body.nav-rail .sidebar a{justify-content:center;padding:10px 0}
html[data-admin-theme="architect"] body.nav-rail .sidebar a.active::before{inset-inline-start:-8px}
html[data-admin-theme="architect"] body.nav-rail .nav-badge{position:absolute;top:1px;inset-inline-end:1px;margin:0;min-width:16px;height:16px;font-size:10px;padding:0 4px}
html[data-admin-theme="architect"] body.nav-rail .sidebar a .status-dot{position:absolute;bottom:3px;inset-inline-end:3px;margin:0}
}
html[data-admin-theme="architect"] @media (max-width:899px){
html[data-admin-theme="architect"] .layout{flex-direction:column}
html[data-admin-theme="architect"] .sidebar{position:fixed;top:0;bottom:0;inset-inline-start:0;width:min(300px,86vw);height:auto;z-index:80;align-items:stretch;transform:translateX(110%);transition:transform .25s ease;box-shadow:0 0 44px rgba(17,24,39,.35)}
html[data-admin-theme="architect"] body.nav-open .sidebar{transform:none}
html[data-admin-theme="architect"] .nav-overlay{display:block;position:fixed;inset:0;z-index:75;background:rgba(17,24,39,.5);opacity:0;pointer-events:none;transition:opacity .25s}
html[data-admin-theme="architect"] body.nav-open .nav-overlay{opacity:1;pointer-events:auto}
html[data-admin-theme="architect"] .content{padding:14px}
html[data-admin-theme="architect"] .topbar-actions .quick-add{display:none}
}
html[data-admin-theme="architect"] @media (max-width:480px){.topbar-title{font-size:14px}.topbar-actions a{padding:6px 8px}}
html[data-admin-theme="architect"] @media (prefers-reduced-motion:reduce){.sidebar,.nav-overlay,.nav-group summary::after{transition:none}}
/* === نمودارهای فانتزی داشبورد (۹٫۱۶) === */
html[data-admin-theme="architect"] .fancy-chart{width:100%;height:auto;display:block}
html[data-admin-theme="architect"] .chart-line{stroke-dasharray:1000;stroke-dashoffset:1000;animation:drawLine 1.6s ease forwards}
html[data-admin-theme="architect"] @keyframes drawLine{to{stroke-dashoffset:0}}
html[data-admin-theme="architect"] .chart-xlabel{font-size:11px;fill:#6b7280}
html[data-admin-theme="architect"] .chart-legend{display:flex;gap:18px;justify-content:center;margin-top:8px;font-size:13px}
html[data-admin-theme="architect"] .chart-legend i{display:inline-block;width:14px;height:14px;border-radius:4px;margin-inline-end:6px;vertical-align:middle}
html[data-admin-theme="architect"] .donut-wrap{display:flex;align-items:center;gap:20px;flex-wrap:wrap;justify-content:center}
html[data-admin-theme="architect"] .donut-seg{opacity:0;animation:fadeSeg .5s ease forwards}
html[data-admin-theme="architect"] @keyframes fadeSeg{to{opacity:1}}
html[data-admin-theme="architect"] .donut-total{font-size:26px;font-weight:800;fill:#111827}
html[data-admin-theme="architect"] .donut-label{font-size:13px;fill:#6b7280}
html[data-admin-theme="architect"] .donut-legend{display:flex;flex-direction:column;gap:8px;font-size:13px}
html[data-admin-theme="architect"] .donut-legend-item{display:flex;align-items:center;gap:8px}
html[data-admin-theme="architect"] .donut-dot{width:12px;height:12px;border-radius:50%;display:inline-block}
html[data-admin-theme="architect"] .funnel{display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px 0}
html[data-admin-theme="architect"] .funnel-bar{display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-radius:14px;color:#1f2937;font-size:14px;min-width:120px;transition:transform .2s;box-shadow:0 2px 8px rgba(0,0,0,.08)}
html[data-admin-theme="architect"] .funnel-bar:hover{transform:scale(1.03)}
html[data-admin-theme="architect"] .funnel-bar b{font-size:18px}
html[data-admin-theme="architect"] .funnel-conv{font-size:11px;color:#9ca3af;margin:2px 0}
html[data-admin-theme="architect"] .timeline-chart{display:flex;align-items:flex-end;gap:3px;height:140px;padding:10px 4px 0;direction:ltr}
html[data-admin-theme="architect"] .tl-bar{flex:1;min-width:4px;background:linear-gradient(180deg,#c9a227,#e8c66a);border-radius:4px 4px 0 0;transition:all .2s;cursor:pointer}
html[data-admin-theme="architect"] .tl-bar:hover{background:linear-gradient(180deg,#a8841c,#c9a227);transform:scaleY(1.05)}
html[data-admin-theme="architect"] .card.wide{max-width:100%}
/* نمودارهای نئونی شیشه‌ای (۹٫۱۶٫۱) */
html[data-admin-theme="architect"] .neon-chart{background:rgba(255,255,255,.4);backdrop-filter:blur(8px);border-radius:16px;padding:8px}
html[data-admin-theme="architect"] .donut-wrap svg{filter:drop-shadow(0 4px 12px rgba(0,0,0,.1))}
html[data-admin-theme="architect"] .tl-bar{background:linear-gradient(180deg,#00e5ff,#2979ff);box-shadow:0 0 6px rgba(0,229,255,.4)}
html[data-admin-theme="architect"] .tl-bar:hover{background:linear-gradient(180deg,#2979ff,#00e5ff);box-shadow:0 0 12px rgba(0,229,255,.7)}
html[data-admin-theme="architect"] .funnel-bar{box-shadow:0 2px 12px rgba(0,0,0,.1),inset 0 1px 0 rgba(255,255,255,.4)}
/* Chart.js — تم نئون شیشه‌ای (۹٫۱۸) */
html[data-admin-theme="architect"] .chartjs-wrap{position:relative;height:280px;padding:8px}
html[data-admin-theme="architect"] .chartjs-wrap canvas{background:rgba(255,255,255,.5);backdrop-filter:blur(8px);border-radius:16px;padding:12px}
/* === تم‌های شیشه‌ای پنل ادمین (۹٫۱۵٫۴) === */
html[data-admin-theme="glass-white"] body{background:#f0f4f8;background-image:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(34,197,94,.08),transparent)}
html[data-admin-theme="glass-white"] .card,html[data-admin-theme="glass-white"] .stat-card{background:rgba(255,255,255,.65);backdrop-filter:blur(16px) saturate(1.4);-webkit-backdrop-filter:blur(16px) saturate(1.4);border:1px solid rgba(34,197,94,.18);box-shadow:0 4px 16px rgba(34,197,94,.1);border-radius:18px}
html[data-admin-theme="glass-white"] table{background:rgba(255,255,255,.6);backdrop-filter:blur(12px);border:1px solid rgba(34,197,94,.15);border-radius:16px}
html[data-admin-theme="glass-white"] .sidebar{background:rgba(255,255,255,.6)!important;backdrop-filter:blur(20px) saturate(1.5);border-inline-end:1px solid rgba(34,197,94,.15)!important}
html[data-admin-theme="glass-white"] .btn{border-radius:12px}
html[data-admin-theme="glass-white"] .topbar{background:rgba(255,255,255,.7)!important;backdrop-filter:blur(20px);color:#111827!important;border-bottom:1px solid rgba(34,197,94,.15)!important}
html[data-admin-theme="glass-white"] .topbar .topbar-title{color:#111827!important;font-weight:700}
html[data-admin-theme="glass-white"] .topbar .menu-toggle{color:#111827!important;border:1px solid rgba(34,197,94,.3)!important;background:rgba(255,255,255,.6)!important}
html[data-admin-theme="glass-smoke"] .topbar{background:rgba(28,28,30,.8)!important;backdrop-filter:blur(20px);color:#f5f5f7!important;border-bottom:1px solid rgba(255,255,255,.08)!important}
html[data-admin-theme="glass-smoke"] .topbar .topbar-title{color:#f5f5f7!important;font-weight:700}
html[data-admin-theme="glass-smoke"] .topbar .menu-toggle{color:#f5f5f7!important;border:1px solid rgba(255,255,255,.15)!important;background:rgba(72,72,74,.6)!important}
html[data-admin-theme="glass-smoke"] body{background:#1c1c1e;background-image:radial-gradient(ellipse 80% 50% at 50% -10%,rgba(250,204,21,.06),transparent);color:#f5f5f7;-webkit-font-smoothing:antialiased}
html[data-admin-theme="glass-smoke"] a{color:#facc15}
html[data-admin-theme="glass-smoke"] .muted{color:#98989f}
html[data-admin-theme="glass-smoke"] .card,html[data-admin-theme="glass-smoke"] .stat-card{background:rgba(44,44,46,.6);backdrop-filter:blur(16px) saturate(1.4);-webkit-backdrop-filter:blur(16px) saturate(1.4);border:1px solid rgba(255,255,255,.1);box-shadow:0 4px 16px rgba(0,0,0,.25);border-radius:18px;color:#f5f5f7}
html[data-admin-theme="glass-smoke"] .stat-card span{color:#98989f}html[data-admin-theme="glass-smoke"] .stat-card strong{color:#f5f5f7}
html[data-admin-theme="glass-smoke"] table{background:rgba(44,44,46,.5);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.08);border-radius:16px}
html[data-admin-theme="glass-smoke"] th,html[data-admin-theme="glass-smoke"] td{border-bottom:1px solid rgba(255,255,255,.06);color:#e5e5ea}
html[data-admin-theme="glass-smoke"] th{background:rgba(58,58,60,.5);color:#98989f}
html[data-admin-theme="glass-smoke"] .sidebar{background:rgba(28,28,30,.7)!important;backdrop-filter:blur(20px) saturate(1.5);border-inline-end:1px solid rgba(255,255,255,.08)!important}
html[data-admin-theme="glass-smoke"] h1,html[data-admin-theme="glass-smoke"] h2,html[data-admin-theme="glass-smoke"] h3{color:#f5f5f7}
html[data-admin-theme="glass-smoke"] input[type=text],html[data-admin-theme="glass-smoke"] input[type=password],html[data-admin-theme="glass-smoke"] input[type=number],html[data-admin-theme="glass-smoke"] select,html[data-admin-theme="glass-smoke"] textarea{border:1px solid rgba(255,255,255,.15);background:rgba(58,58,60,.6);color:#f5f5f7;border-radius:12px}
html[data-admin-theme="glass-smoke"] label{color:#e5e5ea}
html[data-admin-theme="glass-smoke"] .auth-box{background:rgba(44,44,46,.7);backdrop-filter:blur(20px) saturate(1.5);border:1px solid rgba(255,255,255,.12);border-radius:20px}
html[data-admin-theme="glass-smoke"] .alert.ok{background:rgba(48,209,88,.15);border:1px solid rgba(48,209,88,.3);color:#7df097}
html[data-admin-theme="glass-smoke"] .alert.error{background:rgba(255,69,58,.15);border:1px solid rgba(255,69,58,.3);color:#ff9d97}
html[data-admin-theme="glass-smoke"] .btn{background:rgba(72,72,74,.6);border:1px solid rgba(255,255,255,.15);color:#f5f5f7;border-radius:12px}
html[data-admin-theme="glass-smoke"] .btn.primary{background:#facc15;border-color:#facc15;color:#1c1c1e}
html[data-admin-theme="glass-smoke"] .btn.add{background:#30d158;border-color:#30d158;color:#fff}
html[data-admin-theme="glass-smoke"] .btn.edit{background:#facc15;border-color:#facc15;color:#1c1c1e}

CSS;
}
