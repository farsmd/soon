<?php
// session_handler.php — نگهدارندهٔ نشست روی دیتابیس و شروع نشست (نسخه ۸٫۲٫۱).
// نشست‌ها داخل جدول app_sessions در دیتابیس خود سیستم نگه داشته می‌شوند تا هیچ
// پاک‌سازی یا قفلِ مسیرِ هاست اشتراکی نتواند نشست مدیر را بکشد (علت خطای CSRF).
// از config.php صدا زده می‌شود؛ جدا بودنش صرفاً برای کوچک‌ماندن حجم هر فایل است
// (سقف حجم آپدیت گیت‌هاب) و رفتار برنامه هیچ تفاوتی نکرده است.

declare(strict_types=1);

/**
 * نگهدارندهٔ نشست روی دیتابیس (نسخه ۸٫۲٫۱).
 * نشست‌های فایلی روی هاست اشتراکی دو دشمن دارند: پاک‌سازی دوره‌ای مسیر موقت هاست و
 * قفل‌بودن session.save_path با تنظیم مدیریتی (php_admin_value) که ini_set را بی‌اثر
 * می‌کند و نشست را دوباره به همان مسیرِ پاک‌شونده برمی‌گرداند. نشست دیتابیسی به هیچ
 * مسیری روی دیسک وابسته نیست و انقضایش را خودمان با همان مدتِ تنظیم‌شده در پنل کنترل
 * می‌کنیم. هیچ خطایی از این کلاس نباید سایت را خراب کند، پس همه‌چیز try/catch دارد.
 */
final class CmsDbSessionHandler implements SessionHandlerInterface
{
    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        try {
            $stmt = db()->prepare('SELECT data FROM app_sessions WHERE id = ? AND expires_at > ?');
            $stmt->execute([$id, time()]);
            $row = $stmt->fetch();
            return $row ? (string) $row['data'] : '';
        } catch (Throwable $ignored) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            db()->prepare('INSERT INTO app_sessions (id, data, expires_at) VALUES (?, ?, ?) '
                . 'ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at')
                ->execute([$id, $data, time() + session_lifetime_seconds()]);
            if (random_int(1, 50) === 1) {
                db()->exec('DELETE FROM app_sessions WHERE expires_at <= ' . time());
            }
        } catch (Throwable $ignored) {
            // نشست هرگز نباید سایت را خراب کند
        }
        return true;
    }

    public function destroy(string $id): bool
    {
        try {
            db()->prepare('DELETE FROM app_sessions WHERE id = ?')->execute([$id]);
        } catch (Throwable $ignored) {
        }
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            return db()->exec('DELETE FROM app_sessions WHERE expires_at <= ' . time());
        } catch (Throwable $ignored) {
            return 0;
        }
    }
}

/** شروع نشست با نگهدارندهٔ دیتابیسی؛ اگر دیتابیس در دسترس نبود، نشست پیش‌فرض PHP. */
function cms_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    try {
        db(); // جدول نشست‌ها اگر نیست ساخته می‌شود
        session_set_save_handler(new CmsDbSessionHandler(), true);
    } catch (Throwable $ignored) {
        // دیتابیس در دسترس نیست؛ نشست فایلی پیش‌فرض استفاده می‌شود
    }
    ini_set('session.gc_maxlifetime', (string) session_lifetime_seconds()); // مدت نشست از پنل قابل تنظیم است (پیش‌فرض یک هفته)
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
