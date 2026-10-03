<?php
/**
 * فرم ثبت سفارش مستقیم - بدون نیاز به رفتن از طریق صفحه محصول
 * Direct order form - standalone, submits REAL orders via process_site_order()
 */
require_once __DIR__ . '/config.php';

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
    $st = db()->query("SELECT id, name, price_per_meter, pricing_model, price_per_watt FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $products = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $products = [];
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
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Vazirmatn', Tahoma, sans-serif; background: #0f0f0f; color: #fff; padding: 20px; line-height: 1.9; }
.container { max-width: 600px; margin: 0 auto; }
h1 { text-align: center; margin-bottom: 24px; color: #d4af37; font-size: 24px; }
.form-group { margin-bottom: 20px; }
label { display: block; margin-bottom: 8px; font-weight: bold; }
input, select, textarea { width: 100%; padding: 12px; border: 1px solid #333; border-radius: 8px; background: #1a1a1a; color: #fff; font-size: 16px; font-family: inherit; }
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
    <h1>📝 ثبت سفارش مستقیم</h1>

    <?php if (!empty($state['submitted']) && !$submittedOk): ?>
        <div class="alert error" role="alert"><?= e((string) ($state['err'] ?? 'خطایی رخ داد.')) ?></div>
    <?php endif; ?>

    <?php if ($submittedOk): ?>
        <div class="alert ok" role="status"><?= e((string) ($state['msg'] ?? 'سفارش شما ثبت شد.')) ?></div>
        <a class="back-link" href="index.php">← بازگشت به صفحه اصلی</a>
    <?php else: ?>
    <form id="directOrderForm" method="post">
        <input type="hidden" name="site_order" value="1">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="hp-field" aria-hidden="true"><label>این فیلد را خالی بگذارید<input type="text" name="website2" tabindex="-1" autocomplete="off"></label></div>

        <!-- فیلدهای واقعی ردیف سفارش (با جاوااسکریپت از ورودی‌های نمایشی پر می‌شوند) -->
        <input type="hidden" name="so_lines[0][product_id]" id="f_product_id" value="">
        <input type="hidden" name="so_lines[0][length_cm]" id="f_length_cm" value="">
        <input type="hidden" name="so_lines[0][watt]" id="f_watt" value="">
        <input type="hidden" name="so_lines[0][qty]" id="f_qty" value="1">
        <input type="hidden" name="so_lines[0][note]" id="f_note" value="">

        <div class="form-group">
            <label>نام و نام خانوادگی *</label>
            <input type="text" name="so_name" required autocomplete="name" maxlength="120">
        </div>
        <div class="form-group">
            <label>شماره تماس *</label>
            <input type="tel" name="so_mobile" required dir="ltr" inputmode="numeric" autocomplete="tel" maxlength="15">
        </div>
        <div class="form-group">
            <label>محصول *</label>
            <select id="productSelect" required>
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
            <input type="number" id="lengthInput" min="1" step="1" required inputmode="decimal">
            <div class="hint">دقت برش ما میلی‌متر است</div>
        </div>
        <div class="form-group" id="wattGroup" style="display:none;">
            <label>توان (وات) *</label>
            <input type="number" id="wattInput" min="1" step="1" inputmode="numeric">
            <div class="hint">هر وات ۳۰۰٬۰۰۰ تومان + قیمت قاب — مبلغ نهایی در پیش‌فاکتور مشخص می‌شود</div>
        </div>
        <div class="form-group">
            <label>تعداد *</label>
            <input type="number" id="qtyInput" min="1" value="1" required inputmode="numeric">
        </div>
        <div class="form-group">
            <label>توضیحات</label>
            <textarea name="so_order_note" rows="3" maxlength="500" placeholder="مثلاً مدل قاب یا طیف نور چراغ رشد"></textarea>
        </div>
        <button type="submit" class="btn" id="submitBtn">ثبت سفارش</button>
    </form>
    <?php endif; ?>
</div>

<?php if (!$submittedOk): ?>
<script>
(function(){
    var TO_CM = <?= json_encode($toCm) ?>;
    var form = document.getElementById('directOrderForm');
    var productSelect = document.getElementById('productSelect');
    var lengthGroup = document.getElementById('lengthGroup');
    var wattGroup = document.getElementById('wattGroup');
    var lengthInput = document.getElementById('lengthInput');
    var wattInput = document.getElementById('wattInput');
    var qtyInput = document.getElementById('qtyInput');

    function isWattProduct() {
        var opt = productSelect.options[productSelect.selectedIndex];
        return opt && opt.getAttribute('data-pricing') === 'per_watt';
    }

    function toggleGroups() {
        var isWatt = isWattProduct();
        lengthGroup.style.display = isWatt ? 'none' : 'block';
        wattGroup.style.display = isWatt ? 'block' : 'none';
        lengthInput.required = !isWatt;
        wattInput.required = isWatt;
        if (isWatt) { lengthInput.value = ''; } else { wattInput.value = ''; }
    }
    productSelect.addEventListener('change', toggleGroups);
    toggleGroups();

    function faNum(n) {
        try { return Number(n).toLocaleString('fa-IR'); }
        catch (e) { return String(n); }
    }

    form.addEventListener('submit', function() {
        var opt = productSelect.options[productSelect.selectedIndex];
        var isWatt = isWattProduct();
        var qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);

        document.getElementById('f_product_id').value = productSelect.value;
        document.getElementById('f_qty').value = String(qty);

        if (isWatt) {
            var watt = Math.max(1, parseInt(wattInput.value, 10) || 1);
            document.getElementById('f_length_cm').value = '0';
            document.getElementById('f_watt').value = String(watt);
            var priceWatt = parseInt(opt.getAttribute('data-price-watt') || '0', 10);
            var note = 'توان: ' + faNum(watt) + ' وات';
            if (priceWatt > 0) {
                note += ' (برآورد: ' + faNum(watt * priceWatt * qty) + ' تومان + قیمت قاب)';
            }
            document.getElementById('f_note').value = note;
        } else {
            var lenVal = parseFloat(lengthInput.value || '0') || 0;
            document.getElementById('f_length_cm').value = (lenVal * TO_CM).toFixed(1);
            document.getElementById('f_watt').value = '';
            document.getElementById('f_note').value = '';
        }

        var btn = document.getElementById('submitBtn');
        if (btn) { btn.disabled = true; btn.textContent = 'در حال ثبت…'; }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
