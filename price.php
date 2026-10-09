<?php
// price.php — صفحه لیست قیمت و قوانین قیمت‌گذاری لاینرلایت
declare(strict_types=1);

require __DIR__ . '/core/config.php';
cms_session_start();
track_public_request();

$settings = all_settings();
$siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');
$title = 'لیست قیمت و قوانین — ' . $siteTitle;
$desc = 'لیست قیمت چراغ‌های خطی لاینرلایت، تخفیف‌های پلکانی همکار و قوانین ثبت سفارش.';
$canonBase = site_base_url($settings);
$seo = [
    'url' => $canonBase !== '' ? $canonBase . '/price' : '',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => $canonBase . '/'],
        ['name' => 'لیست قیمت', 'url' => $canonBase . '/price'],
    ],
];

echo skeleton_head($settings, $title, $desc, $seo);
echo render_db_template('header', $settings) . "\n";
?>
<main id="main">
<div class="container" style="max-width:760px;padding:48px 20px">
    <div style="text-align:center;margin-bottom:36px">
        <h1 style="font-size:28px;margin-bottom:8px">پیشنهاد همکاری و لیست قیمت</h1>
        <p class="muted" style="font-size:13px;letter-spacing:2px">LINER LIGHT EXCLUSIVE OFFER</p>
    </div>

    <h2 class="sec-title" style="font-size:18px">تخفیف‌های پلکانی و قیمت نهایی (تومان)</h2>
    <p class="muted">قیمت‌های زیر برای محصول نهایی (مونتاژ کامل با درپوش و سیم) به ازای هر متر طول محاسبه شده است:</p>

    <table class="spec-table">
        <thead>
            <tr><th>حجم سفارش در ماه</th><th>تخفیف</th><th>قیمت T1 و L1</th><th>قیمت T2</th></tr>
        </thead>
        <tbody>
            <tr><td>۱ تا ۱۵ متر (خرد)</td><td class="num">0%</td><td class="num">1,500,000</td><td class="num">1,600,000</td></tr>
            <tr><td>۱۵ تا ۳۰ متر</td><td class="num">3%</td><td class="num">1,455,000</td><td class="num">1,552,000</td></tr>
            <tr><td>۳۰ تا ۶۰ متر</td><td class="num">6%</td><td class="num">1,410,000</td><td class="num">1,504,000</td></tr>
            <tr><td>۶۰ تا ۱۲۰ متر</td><td class="num">9%</td><td class="num">1,365,000</td><td class="num">1,456,000</td></tr>
            <tr><td>بالای ۱۲۰ متر</td><td class="num">12%</td><td class="num">1,320,000</td><td class="num">1,408,000</td></tr>
        </tbody>
    </table>

    <div style="background:#fcf9f2;border:1px solid #f4ead6;padding:20px;border-radius:16px;margin:28px 0">
        <h4 style="color:#b3883c;margin:0 0 10px;font-size:15px">آفر ویژه پیش‌خرید ۱۰۰ متری</h4>
        <p style="margin:0;color:#5a4a2e;font-size:14px">در صورت پیش‌خرید نقدی بسته ۱۰۰ متری، <strong>تخفیف پلکان آخر (۱۲٪)</strong> به عنوان یک امتیاز ویژه برای شما لحاظ شده و این قیمت برای سفارشات آینده در برابر تورم تثبیت می‌گردد. متراژ خریداری‌شده به عنوان اعتبار در حساب شما ذخیره شده و در سفارش‌های بعدی با ابعاد دلخواه کسر و تولید می‌شود.</p>
    </div>

    <h2 class="sec-title" style="font-size:18px">شرایط ثبت سفارش و پرداخت</h2>
    <p>برای تسریع در روند تامین متریال و ورود بی‌وقفه سفارشات به صف تولید، پیش‌فاکتورها بر مبنای <strong>تسویه نقدی</strong> تنظیم شده‌اند. لذا ثبت نهایی سفارش و آغاز فرآیند ساخت، پس از تکمیل فرآیند مالی انجام می‌پذیرد.</p>

    <div style="background:#fafafa;border-right:3px solid #ddd;padding:15px 20px;margin-top:24px;font-size:13px;color:#666;border-radius:8px">
        <strong>تبصره قطعات خرد:</strong><br>
        با توجه به هزینه‌های ثابتِ برش، درپوش، سیم‌کشی و دستمزد مونتاژ، حداقل طول محاسباتی برای هر قطعه مستقل (زیر ۵۰ سانت)، معادل نیم متر (۵۰ سانتی‌متر) لحاظ می‌گردد.
    </div>

    <p class="muted" style="text-align:center;margin-top:36px;font-size:13px">مدیریت فروش لاینر لایت | LinerLight.ir — لیست قیمت شهریور ۱۴۰۵</p>
</div>
</main>
<?php
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();
