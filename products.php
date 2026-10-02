<?php
// products.php — کاتالوگ عمومی محصولات (فاز ۲ / نسخه ۶)
// فهرست محصولات و صفحه هر محصول با قالب‌های دیتابیسی «catalog» و «product» ساخته می‌شود.
// قیمت‌ها متری هستند؛ برآوردگر صفحه محصول با همان موتور قیمت config.php کار می‌کند.
// ثبت سفارش در فاز ۳ اضافه می‌شود؛ فعلاً CTA به فرم تماس صفحه اصلی است.

declare(strict_types=1);

require __DIR__ . '/config.php';

// نشست عمومی (برای CSRF فرم ثبت سفارش) — باید قبل از هر خروجی شروع شود
// نشست‌ها داخل دیتابیس سیستم ذخیره می‌شوند (نسخه ۸٫۲٫۱) تا پاک‌سازی و قفلِ مسیر
// نشستِ هاست اشتراکی نتواند نشست را بکشد و فرم‌ها را با خطای CSRF بکشد.
cms_session_start();

// لاگ بازدید و کلیک‌های سایت (نسخه ۸٫۲) — برای بیکن کلیک همین‌جا پاسخ داده و تمام می‌شود
track_public_request();

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
        'product_gallery'    => product_gallery_html((int) $product['id']),
        'attributes_options' => product_options_selects_html($product),
        'estimator'          => product_estimator_html($product),
    ];
    // سئوی محصول (نسخه ۸٫۱۰٫۰): از فیلدهای سئو استفاده می‌شود، در غیر این صورت از نام و توضیح
    $seoTitle = trim((string) ($product['seo_title'] ?? ''));
    $title = $seoTitle !== '' ? $seoTitle : (string) $product['name'] . ' — ' . $siteTitle;
    $seoDesc = trim((string) ($product['seo_description'] ?? ''));
    if ($seoDesc !== '') {
        $desc = $seoDesc;
    } elseif (trim((string) ($product['description'] ?? '')) !== '') {
        $desc = mb_substr(trim(strip_tags((string) $product['description'])), 0, 160);
    } else {
        $desc = (string) ($settings['site_description'] ?? '');
    }
    $seoKeywords = trim((string) ($product['seo_keywords'] ?? ''));
    // JSON-LD محصول
    $baseUrl = rtrim((string) ($settings['site_url'] ?? ''), '/');
    $prodImage = (string) ($product['image'] ?? '');
    $productJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => (string) $product['name'],
        'description' => $desc,
        'sku' => (string) ($product['sku'] ?? ''),
        'brand' => ['@type' => 'Brand', 'name' => $siteTitle],
    ];
    if ($prodImage !== '') {
        $productJsonLd['image'] = $prodImage;
    }
    $retailPrice = product_base_price_per_meter($product, false);
    if ($retailPrice > 0) {
        $productJsonLd['offers'] = [
            '@type' => 'Offer',
            'priceCurrency' => 'IRR',
            'price' => (string) $retailPrice,
            'availability' => 'https://schema.org/InStock',
        ];
    }
    $seoData = [
        'type' => 'product',
        'keywords' => $seoKeywords,
        'image' => $prodImage,
        'jsonld' => $productJsonLd,
    ];

    echo skeleton_head($settings, $title, $desc, $seoData);
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
