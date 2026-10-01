<?php
// session_handler.php — نشستِ خودترمیم روی دیتابیس + کوکیِ ورودِ امضاشده (نسخه ۸٫۲٫۲).
//
// چرا نشستِ معمول PHP روی این هاست کافی نبود؟
// ۱) نشست فایلی (نسخه‌های ۸٫۱ و ۸٫۲): مسیر ذخیرهٔ نشست با تنظیم مدیریتی هاست قفل است
//    (php_admin_value) و نشست در مسیر پیش‌فرضِ پاک‌شوندهٔ هاست می‌میرد.
// ۲) نشست دیتابیسی با مکانیزم خود PHP (نسخه ۸٫۲٫۱): روی همین هاست، پاسخ سرور برای
//    صفحهٔ ورود هدر Set-Cookie نشست را اصلاً ارسال نمی‌کند (با curl و مرورگر واقعی هر
//    دو تأیید شد)، بنابراین مرورگر آی‌دی نشست را نگه نمی‌دارد و کاربر هرگز لاگین نمی‌ماند.
//
// راه‌حل این نسخه: نشست را به‌طور کامل خودمان مدیریت می‌کنیم؛ دیگر به جلسهٔ PHP
// (session_start و چرخهٔ کوکی آن) وابسته نیستیم. کوکی را خودمان با setcookie می‌فرستیم،
// امضای HMAC دارد تا قابل جعل نباشد، و دادهٔ نشست داخل جدول app_sessions خود سیستم
// ذخیره می‌شود. $_SESSION دقیقاً مثل قبل در دسترس همهٔ صفحات است و هیچ تغییری در
// بقیهٔ کد لازم نیست. هیچ خطایی در این مسیر نباید سایت را خراب کند.
//
// از config.php صدا زده می‌شود (require انتهای بخش نشست) تا توابع زیر همه‌جا در دسترس باشند.

declare(strict_types=1);

// نام کوکی نشست سیستم؛ عمداً با نام پیش‌فرض PHP فرق دارد تا با کوکی‌های قدیمی قاطی نشود.
const CMS_COOKIE_NAME = 'cmsaz';

// آیا این درخواست روی HTTPS است؟ (مستقیم یا از پشت پراکسی هاست اشتراکی)
// تابع است نه ثابت، چون مقدارش به $_SERVER همان لحظه بستگی دارد و در عبارت ثابت نمی‌گنجد.
function cms_is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
        return true;
    }
    $proto = strtolower(trim(explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $proto === 'https';
}

/** مشخصات کوکی نشست؛ نشست «مرورگری» است (با بستن مرورگر می‌پرد) ولی داده‌اش تا عمر تنظیم‌شدهٔ پنل روی سرور می‌ماند. */
function cms_cookie_params(): array
{
    $p = ['expires' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax'];
    if (cms_is_https()) {
        $p['secure'] = true;
    }
    return $p;
}

/** آی‌دی نشست تازه: ۶۴ کاراکتر هگز تصادفی. */
function cms_new_sid(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * کلید امضای کوکی؛ یک بار ساخته و داخل تنظیمات دیتابیس ذخیره می‌شود.
 * بدون این کلید هیچ‌کس نمی‌تواند کوکی نشست معتبر جعل کند.
 */
function cms_auth_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $secret = '';
    try {
        $s = trim((string) get_setting('session_auth_secret', ''));
        if ($s === '') {
            $s = bin2hex(random_bytes(32));
            // INSERT OR IGNORE تا اگر دو درخواست هم‌زمان رسیدند، کلید یکی شود
            db()->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES ('session_auth_secret', ?)")
                ->execute([$s]);
            $s = trim((string) get_setting('session_auth_secret', ''));
        }
        $secret = $s;
    } catch (Throwable $ignored) {
        $secret = '';
    }
    if ($secret === '') {
        // دیتابیس در دسترس نیست؛ امضا با کلید موقت (نشست هم روی دیتابیس است و کار نمی‌کند)
        $secret = hash('sha256', __FILE__ . '|cms-fallback');
    }
    return $secret;
}

/** کوکی امضاشده: آی‌دی + نقطه + امضای HMAC. */
function cms_sign(string $sid): string
{
    return $sid . '.' . hash_hmac('sha256', $sid, cms_auth_secret());
}

/** بررسی کوکی ورودی؛ آی‌دی معتبر برمی‌گرداند یا null. */
function cms_verify_cookie(?string $raw): ?string
{
    if ($raw === null || $raw === '' || !str_contains($raw, '.')) {
        return null;
    }
    [$sid, $sig] = explode('.', $raw, 2);
    if (!preg_match('/^[A-Za-z0-9,-]{16,128}$/', $sid)) {
        return null;
    }
    $expected = hash_hmac('sha256', $sid, cms_auth_secret());
    return hash_equals($expected, $sig) ? $sid : null;
}

/** آی‌دی نشست از کوکی همین درخواست (در صورت اعتبار امضا). */
function cms_cookie_sid(): ?string
{
    return cms_verify_cookie(isset($_COOKIE[CMS_COOKIE_NAME]) ? (string) $_COOKIE[CMS_COOKIE_NAME] : null);
}

/** خواندن دادهٔ نشست از دیتابیس (فقط اگر منقضی نشده باشد). */
function cms_load_data(string $sid): array
{
    try {
        $stmt = db()->prepare('SELECT data FROM app_sessions WHERE id = ? AND expires_at > ?');
        $stmt->execute([$sid, time()]);
        $row = $stmt->fetch();
        if (!$row) {
            return [];
        }
        $data = unserialize((string) $row['data'], ['allowed_classes' => false]);
        return is_array($data) ? $data : [];
    } catch (Throwable $ignored) {
        return [];
    }
}

/** نوشتن دادهٔ نشست در دیتابیس؛ انقضا همان عمر تنظیم‌شده در پنل است. */
function cms_save_data(string $sid, array $data): void
{
    try {
        db()->prepare('INSERT INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?) '
            . 'ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at')
            ->execute([$sid, serialize($data), time() + session_lifetime_seconds()]);
        // پاک‌سازی گاه‌به‌گاه ردیف‌های منقضی
        if (random_int(1, 50) === 1) {
            db()->exec('DELETE FROM app_sessions WHERE expires_at <= ' . time());
        }
    } catch (Throwable $ignored) {
        // نشست هرگز نباید سایت را خراب کند
    }
}

/** حذف یک ردیف نشست. */
function cms_delete_data(string $sid): void
{
    try {
        db()->prepare('DELETE FROM app_sessions WHERE id = ?')->execute([$sid]);
    } catch (Throwable $ignored) {
    }
}

/**
 * شروع نشست خودترمیم: کوکی امضاشده خوانده می‌شود و $_SESSION از دیتابیس پر می‌شود.
 * اگر کوکی معتبر نباشد، آی‌دی تازه ساخته و کوکی همان لحظه (قبل از هر خروجی) ارسال
 * می‌شود تا مرورگر از همان اول آی‌دی داشته باشد. ذخیره‌سازی نهایی در خاموش‌شدن
 * درخواست (shutdown) انجام می‌شود؛ این تابع جایگزین session_start() است.
 */
function cms_session_start(): void
{
    if (defined('CMS_SESSION_STARTED')) {
        return;
    }
    define('CMS_SESSION_STARTED', true);
    try {
        db(); // جدول app_sessions اگر نیست ساخته می‌شود
    } catch (Throwable $ignored) {
    }
    $sid = cms_cookie_sid();
    $created = false;
    if ($sid === null) {
        $sid = cms_new_sid();
        $created = true;
        $_SESSION = [];
    } else {
        $_SESSION = cms_load_data($sid);
    }
    $GLOBALS['CMS_SID'] = $sid;
    if ($created && !headers_sent()) {
        setcookie(CMS_COOKIE_NAME, cms_sign($sid), cms_cookie_params());
    }
    register_shutdown_function('cms_session_persist');
}

/** ذخیرهٔ خودکار نشست در پایان هر درخواست (فقط اگر داده‌ای هست). */
function cms_session_persist(): void
{
    $sid = $GLOBALS['CMS_SID'] ?? null;
    if ($sid === null) {
        return;
    }
    if (empty($_SESSION)) {
        cms_delete_data($sid);
        return;
    }
    cms_save_data($sid, $_SESSION);
    // اگر مرورگر به هر دلیلی کوکی ما را نفرستاده بود (کوکی قدیمی نسخهٔ قبل)، دوباره بفرست
    if (!isset($_COOKIE[CMS_COOKIE_NAME]) && !headers_sent()) {
        setcookie(CMS_COOKIE_NAME, cms_sign($sid), cms_cookie_params());
    }
}

/** ساخت آی‌دی تازه هنگام لاگین (جلوگیری از تثبیت نشست) و حفظ داده‌های فعلی. */
function cms_regenerate_session_id(): void
{
    $old = $GLOBALS['CMS_SID'] ?? null;
    $new = cms_new_sid();
    $GLOBALS['CMS_SID'] = $new;
    if ($old !== null && $old !== $new) {
        cms_delete_data($old);
    }
    if (!empty($_SESSION)) {
        cms_save_data($new, $_SESSION);
    }
    if (!headers_sent()) {
        setcookie(CMS_COOKIE_NAME, cms_sign($new), cms_cookie_params());
    }
}

/** نابودی کامل نشست فعلی (خروج از مدیریت) + پاک‌کردن کوکی از مرورگر. */
function cms_session_destroy_current(): void
{
    $sid = $GLOBALS['CMS_SID'] ?? null;
    if ($sid !== null) {
        cms_delete_data($sid);
    }
    $_SESSION = [];
    $GLOBALS['CMS_SID'] = null;
    if (!headers_sent()) {
        $p = cms_cookie_params();
        $p['expires'] = time() - 3600;
        setcookie(CMS_COOKIE_NAME, '', $p);
        // کوکی نشست پیش‌فرض PHP از نسخه‌های قدیمی‌تر را هم پاک کن (بهترین تلاش)
        $lp = $p;
        unset($lp['samesite']);
        setcookie('PHPSESSID', '', $lp);
    }
}
