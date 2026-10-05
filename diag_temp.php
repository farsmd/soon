<?php
// فایل عیب‌یابی موقت — بعد از استفاده حذف شود
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h2>تست لود ماژول‌ها</h2><pre>";

define('CMS_ADMIN_PANEL', true);

// لود هسته
try {
    require __DIR__ . '/core/config.php';
    echo "✓ core/config.php لود شد\n";
} catch (Throwable $e) {
    echo "✗ core/config.php: " . $e->getMessage() . "\n";
    exit;
}

// لود ماژول‌ها یکی‌یکی
$mods = ['site', 'blog', 'products', 'categories', 'customers', 'orders', 
         'finance', 'production', 'users', 'notifications', 'reports', 
         'materials', 'dashboard', 'pages', 'settings', 'employees', 
         'payroll', 'update', 'templates', 'modules'];

foreach ($mods as $m) {
    $f = __DIR__ . "/modules/$m/admin.php";
    if (!is_file($f)) {
        echo "- $m: فایل نیست\n";
        continue;
    }
    try {
        require_once $f;
        echo "✓ $m\n";
    } catch (Throwable $e) {
        echo "✗ $m: " . $e->getMessage() . " در " . $e->getFile() . ":" . $e->getLine() . "\n";
    } catch (Error $e) {
        echo "✗ $m (Error): " . $e->getMessage() . "\n";
    }
}

echo "</pre><p>تمام شد</p>";
