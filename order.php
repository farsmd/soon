<?php
/**
 * فرم ثبت سفارش مستقیم - بدون نیاز به رفتن از طریق صفحه محصول
 * Direct order form - standalone, not tied to a specific product page
 */
require_once __DIR__ . '/config.php';

$products = [];
try {
    $st = db()->query("SELECT id, name, price_per_meter, pricing_model, price_per_watt FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
}

$siteName = get_setting('site_name', 'لاینر لایت');
$lengthUnit = get_setting('length_unit', 'mm');
$unitLabel = ($lengthUnit === 'mm') ? 'میلی‌متر' : 'سانتی‌متر';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت سفارش مستقیم | <?= e($siteName) ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #0f0f0f; color: #fff; padding: 20px; }
.container { max-width: 600px; margin: 0 auto; }
h1 { text-align: center; margin-bottom: 30px; color: #d4af37; }
.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; font-weight: bold; }
input, select, textarea { width: 100%; padding: 12px; border: 1px solid #333; border-radius: 8px; background: #1a1a1a; color: #fff; font-size: 16px; }
input:focus, select:focus, textarea:focus { outline: none; border-color: #d4af37; }
.btn { background: #d4af37; color: #000; padding: 15px 30px; border: none; border-radius: 8px; font-size: 18px; font-weight: bold; cursor: pointer; width: 100%; }
.btn:hover { background: #b8941f; }
.hint { font-size: 13px; color: #888; margin-top: 5px; }
</style>
</head>
<body>
<div class="container">
    <h1>📝 ثبت سفارش مستقیم</h1>
    <form id="directOrderForm">
        <div class="form-group">
            <label>نام و نام خانوادگی *</label>
            <input type="text" name="customer_name" required>
        </div>
        <div class="form-group">
            <label>شماره تماس *</label>
            <input type="tel" name="customer_phone" required dir="ltr">
        </div>
        <div class="form-group">
            <label>محصول *</label>
            <select name="product_id" id="productSelect" required>
                <option value="">— انتخاب کنید —</option>
                <?php foreach ($products as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" 
                            data-pricing="<?= e($p['pricing_model'] ?? 'per_meter') ?>"
                            data-price-watt="<?= (int)($p['price_per_watt'] ?? 0) ?>">
                        <?= e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" id="lengthGroup">
            <label>طول (<?= $unitLabel ?>) *</label>
            <input type="number" name="length" min="1" step="1" required>
            <div class="hint">دقت برش ما میلی‌متر است</div>
        </div>
        <div class="form-group" id="wattGroup" style="display:none;">
            <label>توان (وات) *</label>
            <input type="number" name="watt" min="1" step="1">
            <div class="hint">هر وات ۳۰۰٬۰۰۰ تومان + قیمت قاب</div>
        </div>
        <div class="form-group">
            <label>تعداد *</label>
            <input type="number" name="qty" min="1" value="1" required>
        </div>
        <div class="form-group">
            <label>توضیحات</label>
            <textarea name="notes" rows="3"></textarea>
        </div>
        <button type="submit" class="btn">ثبت سفارش</button>
    </form>
    <div id="result" style="margin-top: 20px;"></div>
</div>

<script>
document.getElementById('productSelect').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const pricing = opt.getAttribute('data-pricing');
    const isWatt = (pricing === 'per_watt');
    document.getElementById('lengthGroup').style.display = isWatt ? 'none' : 'block';
    document.getElementById('wattGroup').style.display = isWatt ? 'block' : 'none';
    document.querySelector('#lengthGroup input').required = !isWatt;
    document.querySelector('#wattGroup input').required = isWatt;
});

document.getElementById('directOrderForm').addEventListener('submit', function(e) {
    e.preventDefault();
    // TODO: Submit via API
    document.getElementById('result').innerHTML = '<p style="color:#d4af37;">سفارش شما ثبت شد! به زودی با شما تماس می‌گیریم.</p>';
});
</script>
</body>
</html>
