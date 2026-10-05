<?php
// modules/settings/admin.php — مدیریت تنظیمات سایت (ماژول واقعی از ۹٫۹۹٫۲۵)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }

/** هندلر صفحه تنظیمات */
function settings_handle_page(): void
{
    global $pdo;
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['settings_save'])) {
        // ذخیره تنظیمات
        $allowed = ['site_title', 'site_description', 'contact_phone', 'contact_email', 'contact_address'];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $val = trim((string) $_POST[$key]);
                try {
                    $st = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
                    $st->execute([$key, $val]);
                } catch (Throwable $e) {
                    // fallback برای دیتابیس‌های قدیمی
                    $st = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $st->execute([$val, $key]);
                }
            }
        }
    }
}

/** رندر صفحه تنظیمات */
function settings_render_page(): void
{
    global $pdo;
    $settings = [];
    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
    } catch (Throwable $e) {}
    ?>
    <h1>تنظیمات سایت</h1>
    <p class="muted">تنظیمات کلی سایت — از اینجا قابل ویرایش است.</p>
    <form method="post" class="card">
        <input type="hidden" name="settings_save" value="1">
        <div style="display:grid;gap:12px;max-width:600px">
            <label>عنوان سایت
                <input type="text" name="site_title" value="<?= e($settings['site_title'] ?? '') ?>" style="width:100%">
            </label>
            <label>توضیحات سایت
                <textarea name="site_description" rows="3" style="width:100%"><?= e($settings['site_description'] ?? '') ?></textarea>
            </label>
            <label>تلفن تماس
                <input type="text" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>" style="width:100%" dir="ltr">
            </label>
            <label>ایمیل تماس
                <input type="text" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>" style="width:100%" dir="ltr">
            </label>
            <label>آدرس
                <textarea name="contact_address" rows="2" style="width:100%"><?= e($settings['contact_address'] ?? '') ?></textarea>
            </label>
            <div>
                <button type="submit" class="btn primary">ذخیره تنظیمات</button>
            </div>
        </div>
    </form>
    <?php
}
