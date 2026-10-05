<?php
/**
 * پیگیری آنلاین سفارش + پروفایل مشتری (نسخه ۹٫۱۴)
 * آدرس تمیز: /track
 * مشتری با شماره سفارش + موبایل، وضعیت سفارش را می‌بیند؛
 * با فقط موبایل، فهرست سفارش‌های خودش (پروفایل) را می‌بیند.
 */
declare(strict_types=1);

require __DIR__ . '/core/config.php';
cms_session_start();

$settings = all_settings();
$siteTitle = (string) ($settings['site_title'] ?? 'لاینرلایت');

// --- محدودیت نرخ ساده: حداکثر ۱۵ جست‌وجو در ۱۰ دقیقه ---
$now = time();
$attempts = $_SESSION['track_attempts'] ?? [];
$attempts = array_filter($attempts, static fn($t) => ($now - (int) $t) < 600);
if (count($attempts) >= 15) {
    $rateLimited = true;
} else {
    $rateLimited = false;
}

$orderNo = trim((string) ($_POST['order_no'] ?? $_GET['order_no'] ?? ''));
$mobile = trim((string) ($_POST['mobile'] ?? $_GET['mobile'] ?? ''));
$mobile = preg_replace('/[^0-9]/', '', $mobile);
// نرمال‌سازی موبایل ایرانی: 0912... یا 912... یا +98...
if (preg_match('/^98(\d{10})$/', $mobile, $m)) { $mobile = '0' . $m[1]; }
elseif (preg_match('/^(\d{10})$/', $mobile, $m)) { $mobile = '0' . $m[1]; }

$result = null;
$error = null;
$profile = null;

if (!$rateLimited && ($_SERVER['REQUEST_METHOD'] === 'POST' || $orderNo !== '' || $mobile !== '')) {
    $attempts[] = $now;
    $_SESSION['track_attempts'] = $attempts;

    if ($mobile === '' || !preg_match('/^09\d{9}$/', $mobile)) {
        $error = 'شماره موبایل معتبر وارد کنید (مثل 09120000000).';
    } else {
        $pdo = db();
        // پیدا کردن مشتری با این موبایل
        $cst = $pdo->prepare('SELECT * FROM customers WHERE mobile = :m LIMIT 1');
        $cst->execute([':m' => $mobile]);
        $customer = $cst->fetch(PDO::FETCH_ASSOC);

        if ($customer === false) {
            // تلاش با موبایل بدون صفر اول
            $cst->execute([':m' => ltrim($mobile, '0')]);
            $customer = $cst->fetch(PDO::FETCH_ASSOC);
        }

        if ($customer === false) {
            $error = 'مشتری با این شماره موبایل پیدا نشد.';
        } elseif ($orderNo !== '') {
            // حالت پیگیری تکی
            $ono = (int) preg_replace('/[^0-9]/', '', $orderNo);
            $st = $pdo->prepare('SELECT * FROM orders WHERE order_no = :ono AND customer_id = :cid LIMIT 1');
            $st->execute([':ono' => $ono, ':cid' => $customer['id']]);
            $order = $st->fetch(PDO::FETCH_ASSOC);
            if ($order === false) {
                $error = 'سفارشی با این شماره برای این موبایل پیدا نشد.';
            } else {
                $result = track_order_detail($pdo, $order);
            }
        } else {
            // حالت پروفایل: فهرست سفارش‌ها
            $st = $pdo->prepare('SELECT * FROM orders WHERE customer_id = :cid ORDER BY id DESC');
            $st->execute([':cid' => $customer['id']]);
            $orders = $st->fetchAll(PDO::FETCH_ASSOC);
            $profile = ['customer' => $customer, 'orders' => $orders];
        }
    }
}

/** جزئیات کامل سفارش برای نمایش عمومی (بدون اطلاعات حساس) */
function track_order_detail(PDO $pdo, array $order): array
{
    $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
    $items->execute([':oid' => $order['id']]);
    $order['items'] = $items->fetchAll(PDO::FETCH_ASSOC);

    $hist = $pdo->prepare(
        'SELECT h.*, s.title AS status_title FROM order_status_history h ' .
        'LEFT JOIN order_statuses s ON s.status_key = h.to_status ' .
        'WHERE h.order_id = :oid ORDER BY h.id ASC'
    );
    $hist->execute([':oid' => $order['id']]);
    $order['history'] = $hist->fetchAll(PDO::FETCH_ASSOC);

    // ترتیب وضعیت‌ها برای تایم‌لاین
    $order['all_statuses'] = order_statuses(true);
    return $order;
}

$canonBase = site_base_url($settings);
$seo = [
    'url' => $canonBase !== '' ? $canonBase . '/track' : '',
    'breadcrumbs' => [
        ['name' => 'خانه', 'url' => $canonBase . '/'],
        ['name' => 'پیگیری سفارش', 'url' => $canonBase . '/track'],
    ],
];
$title = 'پیگیری سفارش — ' . $siteTitle;
$desc = 'پیگیری آنلاین وضعیت سفارش لاینرلایت با شماره سفارش و موبایل.';

echo skeleton_head($settings, $title, $desc, $seo);
echo render_db_template('header', $settings) . "\n";
?>
<main id="main">
<div class="page-body track-page">
    <h1>پیگیری سفارش</h1>
    <p class="muted">با شماره سفارش و شماره موبایل، وضعیت سفارش خود را ببینید. برای دیدن همه سفارش‌ها، فقط موبایل را وارد کنید.</p>

    <?php if ($rateLimited): ?>
        <div class="alert error">تعداد جست‌وجوها زیاد شد؛ لطفاً چند دقیقه دیگر تلاش کنید.</div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
        <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" class="card wide track-form" action="<?= e(pretty_url('track.php')) ?>">
        <?= csrf_field() ?>
        <div class="inline-fields">
            <label>شماره سفارش
                <input type="text" name="order_no" dir="ltr" inputmode="numeric" placeholder="مثل 1001" value="<?= e($orderNo) ?>">
            </label>
            <label>شماره موبایل *
                <input type="tel" name="mobile" dir="ltr" inputmode="tel" required placeholder="09120000000" value="<?= e($mobile) ?>">
            </label>
        </div>
        <button type="submit" class="btn primary">جست‌وجو</button>
    </form>

    <?php if ($result !== null): ?>
        <?php $o = $result; ?>
        <section class="card wide track-result">
            <h2>سفارش <span dir="ltr">#<?= (int) $o['order_no'] ?></span></h2>
            <div class="track-meta">
                <span>وضعیت: <strong style="color:<?= e(order_status_color((string) $o['status'])) ?>"><?= e(order_status_title((string) $o['status'])) ?></strong></span>
                <span>تاریخ ثبت: <?= e(track_fa_date((string) $o['created_at'])) ?></span>
                <?php if ((float) $o['total_meters'] > 0): ?><span>متراژ: <?= e(fa_num((float) $o['total_meters'])) ?> متر</span><?php endif; ?>
                <span>مبلغ: <?= e(format_price((int) $o['total'])) ?> تومان</span>
            </div>

            <?php if ($o['all_statuses'] !== []): ?>
            <ol class="track-timeline">
                <?php
                $currentKey = (string) $o['status'];
                $reached = false;
                foreach ($o['all_statuses'] as $s):
                    $isCurrent = ((string) $s['status_key'] === $currentKey);
                    if ($isCurrent) { $reached = true; }
                ?>
                <li class="<?= $isCurrent ? 'current' : ($reached ? 'todo' : 'done') ?>">
                    <span class="dot"></span>
                    <span><?= e($s['title']) ?></span>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>

            <?php if ($o['items'] !== []): ?>
            <h3>اقلام سفارش</h3>
            <table>
                <thead><tr><th>محصول</th><th>مشخصات</th><th>تعداد</th></tr></thead>
                <tbody>
                <?php foreach ($o['items'] as $it): ?>
                    <tr>
                        <td><?= e((string) ($it['product_name'] ?? '—')) ?></td>
                        <td><?= ((float) ($it['length_cm'] ?? 0) > 0) ? e(fa_num((float) $it['length_cm'] / 100) . ' متر') : '—' ?><?= trim((string) ($it['note'] ?? '')) !== '' ? ' — ' . e((string) $it['note']) : '' ?></td>
                        <td><?= (int) ($it['qty'] ?? 1) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($profile !== null): ?>
        <section class="card wide track-result">
            <h2>سفارش‌های <?= e((string) $profile['customer']['full_name']) ?></h2>
            <?php if ($profile['orders'] === []): ?>
                <p class="muted">هنوز سفارشی ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>شماره</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($profile['orders'] as $po): ?>
                    <tr>
                        <td dir="ltr"><strong>#<?= (int) $po['order_no'] ?></strong></td>
                        <td><?= e(track_fa_date((string) $po['created_at'])) ?></td>
                        <td><span style="color:<?= e(order_status_color((string) $po['status'])) ?>"><?= e(order_status_title((string) $po['status'])) ?></span></td>
                        <td><?= e(format_price((int) $po['total'])) ?> تومان</td>
                        <td><a class="btn small" href="<?= e(pretty_url('track.php')) ?>?order_no=<?= (int) $po['order_no'] ?>&mobile=<?= e($mobile) ?>">جزئیات</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
</main>
<?php
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();

/** تاریخ شمسی خوانا برای نمایش عمومی */
function track_fa_date(string $dt): string
{
    $dt = trim($dt);
    if ($dt === '') { return '—'; }
    try {
        if (function_exists('jdate')) { return jdate('Y/m/d', strtotime($dt)); }
        $d = new DateTime($dt);
        return $d->format('Y/m/d');
    } catch (Throwable $e) { return $dt; }
}

/** عدد فارسی */
function fa_num(float $n): string
{
    return number_format($n, 1, '.', ',');
}
