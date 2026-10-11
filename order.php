<?php
/**
 * فرم ثبت سفارش مستقیم - بدون نیاز به رفتن از طریق صفحه محصول
 * Direct order form - standalone, submits REAL orders via process_site_order()
 */
require_once __DIR__ . '/core/config.php';

// نشست عمومی (برای CSRF) — باید قبل از هر خروجی شروع شود
cms_session_start();

// پردازش ثبت سفارش واقعی قبل از هر خروجی (همان منطق فرم صفحه محصول)
process_site_order();
$state = site_order_state();

// توکن CSRF برای فرم
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['csrf'];

$products = [];
try {
    $st = db()->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}
$preselectProduct = (int) ($_GET['product'] ?? 0);
// محصول برای فرم کامل (از URL یا اولین محصول)
$orderProduct = null;
if ($preselectProduct > 0) {
    foreach ($products as $p) {
        if ((int)$p['id'] === $preselectProduct) { $orderProduct = $p; break; }
    }
}
// محصول پیش‌فرض انتخاب نمی‌شود — کاربر از لیست فرم انتخاب می‌کند
if ($orderProduct === null) {
    $orderProduct = ['id' => 0, 'name' => ''];
}

$siteName = get_setting('site_name', 'لاینر لایت');
$lengthUnit = get_setting('length_unit', 'mm');
$unitLabel = ($lengthUnit === 'mm') ? 'میلی‌متر' : 'سانتی‌متر';
// ضریب تبدیل واحد نمایشی به سانتی‌متر (ورودی پردازشگر سفارش سانتی‌متر است)
$toCm = ($lengthUnit === 'mm') ? 0.1 : 1.0;

$submittedOk = !empty($state['submitted']) && !empty($state['ok']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت سفارش مستقیم | <?= e($siteName) ?></title>
<link rel="canonical" href="<?= e(site_base_url() . '/order') ?>">
<link rel="stylesheet" href="/style.php">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f8fafc; color: #0f172a; padding: 20px; line-height: 1.9; }
.container { max-width: 680px; margin: 0 auto; padding: 0 12px; }
@media (max-width: 640px) {
    body { padding: 12px 8px; }
    .container { padding: 0 4px; }
}
h1 { text-align: center; margin-bottom: 24px; color: #0f172a; font-size: 24px; }
.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; font-weight: bold; }
/* input styles from style.php (Design Studio) */
input:focus, select:focus, textarea:focus { outline: none; border-color: #d4af37; }
.btn { background: #d4af37; color: #000; padding: 15px 30px; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; width: 100%; font-family: inherit; }
.btn:hover { background: #b8941f; }
.btn:disabled { opacity: .6; cursor: wait; }
.hint { font-size: 13px; color: #888; margin-top: 5px; }
.alert { border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size: 15px; }
.alert.ok { background: #12351f; border: 1px solid #2c6b3f; color: #b9e6c5; }
.alert.error { background: #4a1a1a; border: 1px solid #8a2a2a; color: #f3b8b8; }
/* فیلد honeypot ضد اسپم — از دید کاربر مخفی */
.hp-field { position: absolute; left: -9999px; top: auto; width: 1px; height: 1px; overflow: hidden; }
.back-link { display: block; text-align: center; margin-top: 18px; color: #d4af37; text-decoration: none; }
</style>
</head>
<body>
<div class="container">
    <!-- h1 حذف شد — فرم عنوان خودش را دارد -->
    <?php if ($orderProduct !== null): ?>
    <?= site_order_form_html($orderProduct) ?>
    <?php else: ?>
    <p>محصولی برای سفارش یافت نشد.</p>
    <?php endif; ?>
</div>
</body>
</html>
