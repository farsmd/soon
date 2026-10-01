<?php
// products.php — کاتالوگ عمومی محصولات (فاز ۲ / نسخه ۶)
// فهرست محصولات و صفحه هر محصول با قالب‌های دیتابیسی «catalog» و «product» ساخته می‌شود.
// قیمت‌ها متری هستند؛ برآوردگر صفحه محصول با همان موتور قیمت config.php کار می‌کند.
// ثبت سفارش در فاز ۳ اضافه می‌شود؛ فعلاً CTA به فرم تماس صفحه اصلی است.

declare(strict_types=1);

require __DIR__ . '/config.php';

// سشن عمومی (برای CSRF فرم ثبت سفارش) — باید قبل از هر خروجی شروع شود
// روی هاست اشتراکی مسیر پیش‌فرض سشن زود پاک می‌شود و فرم‌ها خطای CSRF می‌دهند؛
// سشن را داخل پوشهٔ خود سیستم نگه می‌داریم (اگر ساخته نشد، پیش‌فرض هاست می‌ماند).
if (session_status() !== PHP_SESSION_ACTIVE) {
    $cmsPubSessionDir = __DIR__ . '/sessions';
    if (!is_dir($cmsPubSessionDir)) {
        @mkdir($cmsPubSessionDir, 0700, true);
    }
    if (is_dir($cmsPubSessionDir) && is_writable($cmsPubSessionDir)) {
        ini_set('session.save_path', $cmsPubSessionDir);
    }
    ini_set('session.gc_maxlifetime', '604800');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// پردازش ثبت سفارش از صفحه محصول قبل از هر خروجی
process_site_order();

$settings = all_settings();
$siteTitle = (string) ($settings['site_title'] ?? 'وب‌سایت من');
$catalogTitle = (string) ($settings['catalog_title'] ?? 'کاتالوگ محصولات');
$catalogPublic = ($settings['catalog_public'] ?? '1') === '1';

// ---------- کاتالوگ غیرفعال است ----------
if (!$catalogPublic) {
    http_response_code(404);
    $title = 'صفحه پیدا نشد — ' . $siteTitle;
    $desc = (string) ($settings['site_description'] ?? '');
    $body = '<section class="content-section"><div class="container">'
        . '<h1>این بخش در دسترس نیست</h1>'
        . '<p>کاتالوگ محصولات در حال حاضر نمایش عمومی ندارد. برای دریافت اطلاعات محصولات با ما در تماس باشید.</p>'
        . '<p><a class="btn" href="index.php">بازگشت به صفحه اصلی</a></p>'
        . '</div></section>';
    echo skeleton_head($settings, $title, $desc);
    echo render_db_template('header', $settings) . "\n";
    echo '<main id="main">' . "\n" . $body . "\n" . '</main>' . "\n";
    echo render_db_template('footer', $settings) . "\n";
    echo skeleton_foot();
    exit;
}

$productId = (int) ($_GET['id'] ?? 0);

// ---------- صفحه یک محصول ----------
if ($productId > 0) {
    $product = get_product($productId);
    if ($product === null || (int) ($product['is_active'] ?? 0) !== 1) {
        http_response_code(404);
        $title = 'محصول پیدا نشد — ' . $siteTitle;
        $body = '<section class="content-section"><div class="container">'
            . '<h1>محصول پیدا نشد</h1>'
            . '<p>این محصول وجود ندارد یا غیرفعال است.</p>'
            . '<p><a class="btn" href="products.php">بازگشت به کاتالوگ</a></p>'
            . '</div></section>';
        echo skeleton_head($settings, $title, (string) ($settings['site_description'] ?? ''));
        echo render_db_template('header', $settings) . "\n";
        echo '<main id="main">' . "\n" . $body . "\n" . '</main>' . "\n";
        echo render_db_template('footer', $settings) . "\n";
        echo skeleton_foot();
        exit;
    }

    $retailBase  = product_base_price_per_meter($product, false);
    $partnerBase = product_base_price_per_meter($product, true);
    $extra = [
        'catalog_title' => $catalogTitle,
        'product_name'  => (string) $product['name'],
        'product_image' => uploaded_image_url($product['image'] ?? ''),
        'product_description' => (string) ($product['description'] ?? ''),
        'category_title'      => (string) ($product['category_title'] ?? ''),
        'price_per_meter_formatted'         => format_price($retailBase),
        'partner_price_per_meter_formatted' => $partnerBase != $retailBase ? format_price($partnerBase) : '',
        'product_specs'      => product_specs_html($product),
        'attributes_options' => product_options_selects_html($product),
        'estimator'          => product_estimator_html($product),
    ];
    $title = (string) $product['name'] . ' — ' . $siteTitle;
    $desc = trim((string) ($product['description'] ?? '')) !== ''
        ? mb_substr(trim(strip_tags((string) $product['description'])), 0, 160)
        : (string) ($settings['site_description'] ?? '');

    echo skeleton_head($settings, $title, $desc);
    echo render_db_template('header', $settings) . "\n";
    echo '<main id="main">' . "\n";
    echo render_db_template('product', $settings, null, null, $extra) . "\n";
    $orderFormHtml = site_order_form_html($product);
    if ($orderFormHtml !== '') {
        echo '<section class="content-section"><div class="container">' . "\n" . $orderFormHtml . "\n" . '</div></section>' . "\n";
    }
    echo '</main>' . "\n";
    echo render_db_template('footer', $settings) . "\n";
    echo skeleton_foot();
    exit;
}

// ---------- فهرست کاتالوگ (با فیلتر دسته) ----------
$catId = (int) ($_GET['cat'] ?? 0);
$activeCat = null;
if ($catId > 0) {
    $activeCat = get_category($catId);
    if ($activeCat === null || (int) ($activeCat['is_active'] ?? 0) !== 1) {
        $activeCat = null;
        $catId = 0;
    }
}
$products = get_products(true, $activeCat !== null ? (int) $activeCat['id'] : null);

$extra = [
    'catalog_title'    => $catalogTitle,
    'categories_nav'   => catalog_categories_nav_html($activeCat !== null ? (int) $activeCat['id'] : null),
    'products_grid'    => catalog_products_grid_html($products),
    'category_title'   => $activeCat !== null ? (string) $activeCat['title'] : '',
];

$title = $catalogTitle . ($activeCat !== null ? ' — ' . (string) $activeCat['title'] : '') . ' — ' . $siteTitle;
$desc = (string) ($settings['site_description'] ?? '');

echo skeleton_head($settings, $title, $desc);
echo render_db_template('header', $settings) . "\n";
echo '<main id="main">' . "\n";
echo render_db_template('catalog', $settings, null, null, $extra) . "\n";
echo '</main>' . "\n";
echo render_db_template('footer', $settings) . "\n";
echo skeleton_foot();
