<?php
// defaults.php — داده‌های کارخانه‌ای نسخه ۶: قالب‌های پیش‌فرض سیستم و CSS پایه سایت.
// این فایل فقط تابع‌هایی دارد که رشته‌های ثابت برمی‌گردانند و از config.php صدا زده می‌شوند.
// جدا شدنشان از config.php صرفاً برای کوچک‌ماندن حجم هر فایل است (سقف حجم آپدیت گیت‌هاب)؛
// محتوای برگشتی دقیقاً همان قبلی است و رفتار برنامه هیچ تفاوتی نکرده است.

declare(strict_types=1);
/** قالب‌های کارخانه‌ای سیستم (کلید => عنوان و محتوای HTML امن با پلیس‌هولدر) */
function factory_templates(): array
{
    return [
        'header' => [
            'title'   => 'هدر سایت',
            'content' => <<<'HTML'
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.php">{{site_title}}</a>
        {{menu}}
    </div>
</header>
HTML,
        ],
        'slider' => [
            'title'   => 'اسلایدر (قهرمان صفحه)',
            'content' => <<<'HTML'
<section class="slider{{#if section_image}} has-image{{/if}}"{{#if section_image}} style="background-image:url('{{section_image_url}}')"{{/if}}>
    <div class="container slider-inner">
        <h1>{{section_heading}}</h1>
        <div class="lead">{{section_body}}</div>
        {{#if section_link_url}}<a class="btn btn-light" href="{{section_link_url}}">{{section_link_text}}</a>{{/if}}
    </div>
</section>
HTML,
        ],
        'features' => [
            'title'   => 'ویژگی‌ها',
            'content' => <<<'HTML'
<section id="features" class="features">
    <div class="container">
        <h2>{{section_heading}}</h2>
        {{#if section_image}}<p class="section-image"><img src="{{section_image_url}}" alt="{{section_heading}}" loading="lazy" decoding="async"></p>{{/if}}
        {{#if section_body}}<div class="section-body">{{section_body}}</div>{{else}}<div class="cards">
            <article class="card">
                <h3>سریع و سبک</h3>
                <p>بر پایه PHP و SQLite، بدون نیاز به دیتابیس جدا.</p>
            </article>
            <article class="card">
                <h3>قالب‌های داخل دیتابیس</h3>
                <p>هدر، اسلایدر و فوتر را از پنل «قالب و استایل» ویرایش کنید؛ آپدیت سایت آن‌ها را پاک نمی‌کند.</p>
            </article>
            <article class="card">
                <h3>چندصفحه‌ای و قابل ویرایش</h3>
                <p>صفحه بسازید، محتوا و عکس هر بخش را از ادمین عوض کنید و منو خودکار ساخته می‌شود.</p>
            </article>
        </div>{{/if}}
    </div>
</section>
HTML,
        ],
        'content' => [
            'title'   => 'محتوای اصلی',
            'content' => <<<'HTML'
<section id="content" class="content-section">
    <div class="container">
        <h2>{{section_heading}}</h2>
        {{#if section_image}}<p class="section-image"><img src="{{section_image_url}}" alt="{{section_heading}}" loading="lazy" decoding="async"></p>{{/if}}
        {{#if section_body}}<div class="section-body">{{section_body}}</div>{{else}}<p>{{site_description}}</p>
        <p>این یک بخش محتوای نمونه است. از پنل مدیریت، «بخش‌های صفحه اصلی»، همین بخش را ویرایش کنید و تیتر، متن و عکس خودش را بدهید؛ یا از «قالب و استایل» ظاهر آن را تغییر دهید.</p>{{/if}}
        {{#if section_link_url}}{{#if section_link_text}}<p><a class="btn" href="{{section_link_url}}">{{section_link_text}}</a></p>{{/if}}{{/if}}
    </div>
</section>
HTML,
        ],
        'footer' => [
            'title'   => 'فوتر سایت',
            'content' => <<<'HTML'
<footer class="site-footer">
    <div class="container">
        <p>© {{current_year}} {{site_title}} — همه حقوق محفوظ است.</p>
        <p class="muted">ساخته‌شده با مدیریت محتوای ساده PHP و SQLite — نسخه ۶</p>
    </div>
</footer>
HTML,
        ],
        'contact' => [
            'title'   => 'فرم تماس',
            'content' => <<<'HTML'
<section id="contact" class="content-section contact-section">
    <div class="container">
        <h2>{{section_heading}}</h2>
        {{#if section_body}}<div class="section-body">{{section_body}}</div>{{/if}}
        {{contact_form}}
    </div>
</section>
HTML,
        ],
        'single' => [
            'title'   => 'صفحه تکی',
            'content' => <<<'HTML'
<section class="content-section page-content">
    <div class="container">
        <h1>{{page_title}}</h1>
        <div class="page-body">{{page_content}}</div>
    </div>
</section>
HTML,
        ],
        'catalog' => [
            'title'   => 'فهرست کاتالوگ محصولات',
            'content' => <<<'HTML'
<section class="content-section catalog-section">
    <div class="container">
        <h1>{{catalog_title}}</h1>
        {{categories_nav}}
        {{products_grid}}
    </div>
</section>
HTML,
        ],
        'product' => [
            'title'   => 'صفحه محصول',
            'content' => <<<'HTML'
<section class="content-section product-section">
    <div class="container">
        <p class="product-breadcrumb"><a href="products.php">← بازگشت به کاتالوگ</a>{{#if category_title}} <span class="muted">/ {{category_title}}</span>{{/if}}</p>
        <div class="product-layout">
            {{#if product_image}}<div class="product-media"><img src="{{product_image}}" alt="{{product_name}}"></div>{{/if}}
            <div class="product-info">
                <h1>{{product_name}}</h1>
                {{#if product_description}}<div class="product-desc">{{product_description}}</div>{{/if}}
                <p class="product-price-line">قیمت متری: <strong>{{price_per_meter_formatted}}</strong> تومان{{#if partner_price_per_meter_formatted}} <span class="muted">| قیمت همکار: {{partner_price_per_meter_formatted}} تومان</span>{{/if}}</p>
                {{product_specs}}
            </div>
        </div>
        {{estimator}}
    </div>
</section>
HTML,
        ],
    ];
}

/**
 * CSS پیش‌فرض نسخه ۵: موبایل‌اول، بخش‌بندی‌شده و سازگار گسترده.
 * رنگ/فونت/عرض/گردی از متغیرها می‌آیند و از پنل «قالب و استایل» بدون ویرایش کد عوض می‌شوند.
 */
function default_site_css(): string
{
    return <<<'CSS'
/* ============================================================
   استایل پایه سایت — نسخه ۵ (داخل دیتابیس از «قالب و استایل» ویرایش می‌شود)
   ساختار: توکن‌ها ← تم تیره ← ریست و پایه ← ابزارها ← هدر و ناوبری
   ← اسلایدر و کاروسل ← دکمه و فرم ← بخش‌ها ← فوتر ← شبکه مدرن
   ← ناوبری موبایل ← حرکت کم ← چاپ
   ============================================================ */

/* ---------- توکن‌ها (تنظیمات ظاهری پنل روی این‌ها می‌نشیند) ---------- */
:root{
    --primary:#2563eb;
    --primary-dark:#1d4ed8;
    --accent:#0d9488;
    --accent-dark:#0f766e;
    --font-family:-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Tahoma, Arial, sans-serif;
    --container-width:1200px;
    --radius:12px;
    --bg:#ffffff;
    --surface:#f9fafb;
    --surface-border:#e5e7eb;
    --text:#111827;
    --muted:#6b7280;
    --header-bg:#111827;
    --header-text:#ffffff;
    --header-link:#d1d5db;
    --footer-bg:#111827;
    --footer-text:#d1d5db;
    --input-bg:#ffffff;
    --input-border:#d1d5db;
    --alert-ok-bg:#dcfce7;
    --alert-ok-text:#166534;
    --alert-error-bg:#fee2e2;
    --alert-error-text:#991b1b;
    --shadow:0 10px 30px rgba(15,23,42,.14);
}

/* ---------- تم تیره (با دکمه تغییر تم و کلاس روی html) ---------- */
html[data-theme="dark"]{
    --bg:#0f172a;
    --surface:#1e293b;
    --surface-border:#334155;
    --text:#e5e7eb;
    --muted:#94a3b8;
    --header-bg:#020617;
    --header-text:#f8fafc;
    --header-link:#cbd5e1;
    --footer-bg:#020617;
    --footer-text:#94a3b8;
    --input-bg:#1e293b;
    --input-border:#475569;
    --alert-ok-bg:#14532d;
    --alert-ok-text:#bbf7d0;
    --alert-error-bg:#7f1d1d;
    --alert-error-text:#fecaca;
    --shadow:0 10px 30px rgba(0,0,0,.45);
}

/* ---------- ریست و پایه ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font-family);background:var(--bg);color:var(--text);line-height:1.9;transition:background .25s ease,color .25s ease}
img{max-width:100%;height:auto}
a{color:var(--primary);text-decoration:none}
a:hover{text-decoration:underline}
:focus{outline:none}
:focus-visible{outline:2px solid var(--primary);outline-offset:2px;border-radius:4px}
code{background:var(--surface);border:1px solid var(--surface-border);padding:1px 6px;border-radius:5px;direction:ltr;display:inline-block;font-size:.92em}
pre{overflow-x:auto;direction:ltr;text-align:left}
button{font-family:inherit}

/* ---------- ابزارها ---------- */
.container{width:100%;max-width:1080px;max-width:var(--container-width,1080px);margin-right:auto;margin-left:auto;margin-inline:auto;padding-right:16px;padding-left:16px;padding-inline:16px}
.muted{color:var(--muted);font-size:13px}
.skip-link{position:absolute;top:-100px;inset-inline-start:12px;z-index:100;background:var(--primary);color:#fff;padding:10px 16px;border-radius:0 0 var(--radius) var(--radius);min-height:44px;display:inline-flex;align-items:center}
.skip-link:focus{top:0;color:#fff;text-decoration:none}

/* ---------- هدر و ناوبری ---------- */
.site-header{background:var(--header-bg);color:var(--header-text);padding:10px 0;position:sticky;top:0;z-index:50;box-shadow:0 2px 12px rgba(0,0,0,.18)}
.header-inner{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.logo{color:var(--header-text);font-weight:bold;font-size:19px;line-height:1.4}
.logo:hover{color:var(--header-text);text-decoration:none}
.main-nav{position:relative;display:flex;align-items:center}
.nav-toggle{display:none;align-items:center;justify-content:center;width:44px;height:44px;border:1px solid var(--header-link);border-radius:var(--radius);background:transparent;color:var(--header-text);font-size:20px;cursor:pointer}
.nav-list{display:flex;align-items:center;gap:2px;flex-wrap:wrap}
.nav-list a{color:var(--header-link);padding:10px 12px;border-radius:8px;min-height:44px;display:inline-flex;align-items:center}
.nav-list a:hover{color:var(--header-text);text-decoration:none;background:rgba(255,255,255,.08)}
.theme-toggle{background:transparent;border:1px solid var(--header-link);color:var(--header-text);border-radius:99px;padding:6px 12px;cursor:pointer;margin-inline-start:10px;font-size:14px;min-height:44px;min-width:44px}

/* ---------- اسلایدر (قهرمان صفحه) ---------- */
.slider{background:linear-gradient(135deg,var(--primary),var(--accent));color:#fff;padding:64px 0;padding:clamp(52px,9vw,96px) 0;text-align:center;background-size:cover;background-position:center;position:relative}
.slider.has-image::before{content:"";position:absolute;top:0;right:0;bottom:0;left:0;background:rgba(2,6,23,.55)}
.slider-inner{position:relative;z-index:1}
.slider h1{margin:0 0 14px;font-size:30px;font-size:clamp(27px,5.5vw,42px);line-height:1.4}
.lead{font-size:17px;font-size:clamp(16px,2.6vw,20px);opacity:.97;max-width:720px;margin:0 auto}
.lead p{margin:6px 0}

/* ---------- کاروسل اسلایدها برای {{slider_slides}} ---------- */
.slides{position:relative;overflow:hidden;border-radius:var(--radius);box-shadow:var(--shadow);background:var(--surface)}
.slide{display:none;margin:0;position:relative}
.slide.is-active{display:block}
.slide img{width:100%;display:block}
.slide-caption{position:absolute;right:0;left:0;inset-inline:0;bottom:0;padding:38px 18px 46px;background:linear-gradient(to top,rgba(2,6,23,.78),rgba(2,6,23,0));color:#fff}
.slide-caption h3{margin:0 0 6px;font-size:22px;font-size:clamp(18px,3.4vw,26px);line-height:1.5}
.slide-text{font-size:15px;opacity:.95}
.slide-btn{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:1px solid rgba(255,255,255,.5);background:rgba(2,6,23,.45);color:#fff;font-size:24px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:2;padding:0}
.slide-btn:hover{background:rgba(2,6,23,.7)}
.slide-prev{inset-inline-start:10px}
.slide-next{inset-inline-end:10px}
.slide-dots{position:absolute;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:2px;z-index:2}
.slide-dot{width:30px;height:30px;border:0;background:transparent;cursor:pointer;position:relative;padding:0}
.slide-dot::after{content:"";position:absolute;top:50%;left:50%;width:10px;height:10px;border-radius:50%;transform:translate(-50%,-50%);background:rgba(255,255,255,.55)}
.slide-dot.is-active::after{background:#fff;width:12px;height:12px}

/* ---------- دکمه، هشدار و فرم ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 22px;border-radius:var(--radius);background:var(--primary);color:#fff;margin-top:16px;border:1px solid var(--primary);cursor:pointer;font-size:15px;min-height:44px;line-height:1.4}
.btn:hover{background:var(--primary-dark);border-color:var(--primary-dark);color:#fff;text-decoration:none}
.btn-light{background:#fff;color:var(--primary-dark);border-color:#fff}
.btn-light:hover{background:#f1f5f9;border-color:#f1f5f9;color:var(--primary-dark)}
.alert{padding:12px 14px;border-radius:var(--radius);margin:12px auto;font-size:14px;max-width:640px}
.alert.ok{background:var(--alert-ok-bg);color:var(--alert-ok-text)}
.alert.error{background:var(--alert-error-bg);color:var(--alert-error-text)}
.contact-form{max-width:640px;margin:18px auto 0;background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:20px}
.contact-form label{display:block;margin:12px 0;font-size:14px}
.contact-form input[type="text"],.contact-form textarea{width:100%;padding:11px;margin-top:6px;border:1px solid var(--input-border);border-radius:var(--radius);font-family:inherit;font-size:16px;background:var(--input-bg);color:var(--text)}
.hp-field{position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden}

/* ---------- بخش‌ها و محتوای متنی ---------- */
.features,.content-section,.custom-section{padding:48px 0;padding:clamp(40px,7vw,64px) 0}
.features h2,.content-section h2{text-align:center;margin-top:0;font-size:25px;font-size:clamp(22px,4vw,30px);line-height:1.5}
.page-content h1{font-size:28px;font-size:clamp(24px,4.5vw,34px);line-height:1.5;margin-top:0}
.section-body{max-width:820px;margin:0 auto}
.section-body p,.page-body p{margin:0 0 14px}
.section-body ul,.section-body ol,.page-body ul,.page-body ol{padding-inline-start:22px}
.section-body img,.page-body img{border-radius:var(--radius)}
.section-body table,.page-body table{display:block;overflow-x:auto;border-collapse:collapse;max-width:100%}
.section-body th,.section-body td,.page-body th,.page-body td{border:1px solid var(--surface-border);padding:8px 10px}
.section-body blockquote,.page-body blockquote{margin:16px 0;padding:10px 16px;border-inline-start:4px solid var(--primary);background:var(--surface);border-radius:var(--radius)}
.section-image{text-align:center}
.section-image img{border-radius:var(--radius);box-shadow:var(--shadow)}
.cards{display:flex;flex-direction:column;gap:18px;margin-top:22px}
.card{background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:20px}
.card h3{margin-top:0}

/* ---------- فوتر ---------- */
.site-footer{background:var(--footer-bg);color:var(--footer-text);text-align:center;padding:30px 0}
.site-footer .muted{color:var(--footer-text);opacity:.75}
.empty-state{text-align:center;padding:70px 16px}

/* ---------- شبکه کارت‌ها (با پس‌روی فلکس برای مرورگرهای قدیمی) ---------- */
@supports (display:grid){
    .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr))}
    @media (min-width:640px){
        .cards{grid-template-columns:repeat(auto-fit,minmax(min(230px,100%),1fr))}
    }
}

/* ---------- ناوبری موبایل و صفحه‌های کوچک ---------- */
@media (max-width:860px){
    .nav-toggle{display:inline-flex}
    .nav-list{display:none;position:absolute;top:calc(100% + 10px);inset-inline-end:0;min-width:210px;flex-direction:column;align-items:stretch;background:var(--header-bg);border:1px solid rgba(255,255,255,.16);border-radius:var(--radius);padding:8px;box-shadow:var(--shadow);z-index:60}
    .nav-list.open{display:flex}
    .nav-list a{width:100%}
    .theme-toggle{margin:6px 0 0;width:100%}
}
@media (max-width:480px){
    .logo{font-size:17px}
    .slider{padding:46px 0}
    .contact-form{padding:14px}
    .slide-caption{padding-bottom:40px}
}

/* ---------- حرکت کم (احترام به ترجیح کاربر) ---------- */
@media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
}

/* ---------- چاپ ---------- */
@media print{
    .site-header,.site-footer,.nav-toggle,.theme-toggle,.slide-btn,.slide-dots,.skip-link{display:none!important}
    body{background:#fff;color:#000}
    .container{max-width:100%}
}
CSS
    . "\n" . catalog_css_block()
    . "\n" . order_form_css();
}

/** CSS کاتالوگ محصولات: در نصب تازه داخل CSS پیش‌فرض است و در مهاجرت فقط اگر نشانگر نباشد، یک بار به site_css افزوده می‌شود */
function catalog_css_block(): string
{
    return <<<'CSS'
/* === کاتالوگ محصولات (نسخه ۶) === */
.catalog-nav{display:flex;flex-wrap:wrap;gap:8px;margin:18px 0 22px}
.catalog-nav a{padding:9px 16px;min-height:44px;display:inline-flex;align-items:center;border:1px solid var(--surface-border);border-radius:99px;background:var(--surface);color:var(--text);font-size:14px}
.catalog-nav a:hover{text-decoration:none;border-color:var(--primary);color:var(--primary)}
.catalog-nav a.active{background:var(--primary);border-color:var(--primary);color:#fff}
.products-grid{display:flex;flex-wrap:wrap;gap:16px;margin:10px 0 8px}
.product-card{flex:1 1 230px;max-width:100%;background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);overflow:hidden;display:flex;flex-direction:column}
.product-card img{width:100%;height:170px;object-fit:cover;display:block;background:var(--surface-border)}
.product-card-body{padding:14px 16px 16px;display:flex;flex-direction:column;gap:6px;flex:1}
.product-card h3{margin:0;font-size:17px;line-height:1.6}
.product-card h3 a{color:var(--text)}
.product-card .cat{color:var(--muted);font-size:12.5px}
.product-card .price{margin-top:auto;font-size:14px}
.product-card .price strong{color:var(--primary);font-size:16px}
.product-card .btn{align-self:flex-start;margin-top:8px}
@supports (display:grid){
    .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(230px,100%),1fr))}
    .product-card{max-width:none}
}
.product-breadcrumb{margin-top:0}
.product-layout{display:flex;flex-wrap:wrap;gap:22px;align-items:flex-start}
.product-media{flex:1 1 300px;max-width:520px}
.product-media img{width:100%;border-radius:var(--radius);box-shadow:var(--shadow);display:block}
.product-info{flex:2 1 320px;min-width:min(320px,100%)}
.product-price-line{font-size:16px;background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:10px 14px}
.spec-table{width:100%;border-collapse:collapse;margin:14px 0;font-size:14.5px}
.spec-table th,.spec-table td{border:1px solid var(--surface-border);padding:9px 12px;text-align:right;vertical-align:top}
.spec-table th{background:var(--surface);width:38%;font-weight:600}
.estimator{margin-top:26px;background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:18px}
.estimator h2{margin-top:0;font-size:20px;text-align:right}
.estimator .field{margin-bottom:12px}
.estimator label{display:block;margin-bottom:5px;font-size:14px;font-weight:600}
.estimator input,.estimator select{width:100%;padding:11px 12px;min-height:44px;border:1px solid var(--input-border);border-radius:var(--radius);background:var(--input-bg);color:var(--text);font:inherit;font-size:16px}
.estimator-result{margin:14px 0 4px;font-size:16px}
.estimator-result strong{color:var(--primary);font-size:19px}
.estimator .partner-line{color:var(--muted);font-size:14px}
@media (max-width:640px){
    .product-card img{height:150px}
    .estimator{padding:14px}
}
@media print{
    .catalog-nav,.estimator{display:none!important}
}
/* استایل فرم ثبت سفارش از order_form_css() می‌آید (در ادامه همین فایل) */
.hp-field{position:absolute!important;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}
@media print{
    .site-order{display:none!important}
}
CSS;
}

// ---------- سازنده‌های HTML کاتالوگ (از config.php منتقل شدند تا آن فایل کوچک بماند) ----------
// این توابع فقط برای صفحه عمومی کاتالوگ (products.php) خروجی امن می‌سازند؛ ورودی‌ها escape می‌شوند.

// ---------- سازنده‌های HTML کاتالوگ (خروجی خام داخلیِ مطمئن؛ ورودی‌ها escape می‌شوند) ----------

/** ناو دسته‌های کاتالوگ (چیپ‌ها) با حالت فعال */
function catalog_categories_nav_html(?int $activeCategoryId): string
{
    $cats = get_categories(true);
    $html = '<nav class="catalog-nav" aria-label="دسته‌بندی محصولات">';
    $html .= '<a href="products.php"' . ($activeCategoryId === null ? ' class="active"' : '') . '>همه محصولات</a>';
    $idsWithProducts = [];
    foreach (get_products(true) as $p) {
        if (!empty($p['category_id'])) {
            $idsWithProducts[(int) $p['category_id']] = true;
        }
    }
    foreach ($cats as $c) {
        $cid = (int) $c['id'];
        if (!isset($idsWithProducts[$cid]) && $activeCategoryId !== $cid) {
            $has = false;
            foreach (category_ids_with_children($cid) as $sub) {
                if (isset($idsWithProducts[$sub])) {
                    $has = true;
                    break;
                }
            }
            if (!$has) {
                continue;
            }
        }
        $indent = !empty($c['parent_id']) ? ' style="margin-inline-start:10px"' : '';
        $html .= '<a href="products.php?cat=' . $cid . '"' . ($activeCategoryId === $cid ? ' class="active"' : '') . $indent . '>' . e($c['title']) . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

/** شبکه کارت‌های محصول برای صفحه فهرست کاتالوگ */
function catalog_products_grid_html(array $products): string
{
    if ($products === []) {
        return '<div class="empty-state"><p>هنوز محصولی در این بخش ثبت نشده است.</p></div>';
    }
    $html = '<div class="products-grid">';
    foreach ($products as $p) {
        $pid = (int) $p['id'];
        $img = uploaded_image_url($p['image'] ?? '');
        $html .= '<article class="product-card">';
        if ($img !== '') {
            $html .= '<a href="products.php?id=' . $pid . '"><img src="' . e($img) . '" alt="' . e($p['name']) . '" loading="lazy" decoding="async"></a>';
        }
        $html .= '<div class="product-card-body">';
        if (!empty($p['category_title'])) {
            $html .= '<span class="cat">' . e($p['category_title']) . '</span>';
        }
        $html .= '<h3><a href="products.php?id=' . $pid . '">' . e($p['name']) . '</a></h3>';
        $html .= '<p class="price">قیمت متری: <strong>' . e(format_price($p['price_per_meter'] ?? 0)) . '</strong> تومان</p>';
        $html .= '<a class="btn small" href="products.php?id=' . $pid . '">مشاهده و برآورد قیمت</a>';
        $html .= '</div></article>';
    }
    $html .= '</div>';
    return $html;
}

/** جدول مشخصات محصول از ویژگی‌های ارائه‌شده (گزینه پیش‌فرض / مقدار عددی / متن) */
function product_specs_html(array $product): string
{
    $offered = product_offered_attributes((int) $product['id']);
    if ($offered === []) {
        return '';
    }
    $html = '<table class="spec-table"><tbody>';
    foreach ($offered as $item) {
        $attr = $item['attribute'];
        $html .= '<tr><th>' . e($attr['title']) . '</th><td>';
        if ($item['default_option_id'] !== null) {
            $label = '';
            foreach ($item['options'] as $o) {
                if ((int) $o['id'] === $item['default_option_id']) {
                    $label = (string) $o['title'];
                    break;
                }
            }
            $html .= e($label);
        } elseif ($item['num_value'] !== null) {
            $html .= e(format_price($item['num_value'])) . (!empty($attr['unit']) ? ' ' . e($attr['unit']) : '');
        } else {
            $html .= e((string) $item['text_value']);
        }
        $html .= '</td></tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** انتخاب‌های آپشن برای برآوردگر (select برای هر ویژگی انتخابی ارائه‌شده) */
function product_options_selects_html(array $product): string
{
    $offered = product_offered_attributes((int) $product['id']);
    $html = '';
    foreach ($offered as $item) {
        if ($item['default_option_id'] === null) {
            continue;
        }
        $attr = $item['attribute'];
        $html .= '<div class="field"><label for="est-opt-' . (int) $attr['id'] . '">' . e($attr['title']) . '</label>';
        $html .= '<select id="est-opt-' . (int) $attr['id'] . '" class="est-option">';
        foreach ($item['options'] as $o) {
            $delta = (float) ($o['price_delta_per_meter'] ?? 0);
            $html .= '<option value="' . (int) $o['id'] . '" data-delta="' . $delta . '"'
                . ((int) $o['id'] === $item['default_option_id'] ? ' selected' : '') . '>'
                . e($o['title'])
                . ($delta != 0.0 ? ' (' . ($delta > 0 ? '+' : '') . e(format_price($delta)) . ' تومان/متر)' : '')
                . '</option>';
        }
        $html .= '</select></div>';
    }
    return $html;
}

/** بلوک برآوردگر قیمت صفحه محصول: طول (متر) + آپشن‌ها → برآورد زنده مشتری و همکار */
function product_estimator_html(array $product): string
{
    $retailBase  = product_base_price_per_meter($product, false);
    $partnerBase = product_base_price_per_meter($product, true);
    $html = '<div class="estimator" id="estimator" data-retail-base="' . (float) $retailBase . '" data-partner-base="' . (float) $partnerBase . '">';
    $html .= '<h2>برآورد قیمت</h2>';
    $html .= '<div class="field"><label for="est-length">طول (متر)</label>';
    $html .= '<input type="number" id="est-length" min="0.1" step="0.1" value="1" inputmode="decimal"></div>';
    $html .= product_options_selects_html($product);
    $html .= '<p class="estimator-result">برآورد مشتری: <strong id="est-retail">' . e(format_price($retailBase)) . '</strong> تومان</p>';
    if ($partnerBase != $retailBase) {
        $html .= '<p class="partner-line">برآورد همکار: <strong id="est-partner">' . e(format_price($partnerBase)) . '</strong> تومان</p>';
    }
    $html .= '<p><a class="btn" href="index.php#contact">برای ثبت سفارش و مشاوره با ما در تماس باشید</a></p>';
    $html .= '<script>(function(){var box=document.getElementById("estimator");if(!box)return;'
        . 'var len=document.getElementById("est-length"),r=document.getElementById("est-retail"),p=document.getElementById("est-partner");'
        . 'function fmt(n){return Math.round(n).toString().replace(/\\B(?=(\\d{3})+(?!\\d))/g,",");}'
        . 'function calc(){var L=parseFloat(len.value)||0;if(L<0)L=0;var d=0;'
        . 'box.querySelectorAll(".est-option").forEach(function(s){var o=s.options[s.selectedIndex];d+=o?parseFloat(o.getAttribute("data-delta")||"0"):0;});'
        . 'var rb=parseFloat(box.getAttribute("data-retail-base")||"0"),pb=parseFloat(box.getAttribute("data-partner-base")||"0");'
        . 'if(r)r.textContent=fmt((rb+d)*L);if(p)p.textContent=fmt((pb+d)*L);}'
        . 'len.addEventListener("input",calc);box.querySelectorAll(".est-option").forEach(function(s){s.addEventListener("change",calc);});calc();})();</script>';
    $html .= '</div>';
    return $html;
}

/* ---------- فرم «ثبت سفارش» صفحه محصول (فاز ۳ / نسخه ۸) ---------- */

/** وضعیت ارسال فرم سفارش سایت (مثل contact_state) */
function site_order_state(?array $set = null): array
{
    static $state = ['submitted' => false, 'ok' => false, 'msg' => '', 'err' => ''];
    if ($set !== null) {
        $state = $set;
    }
    return $state;
}

/**
 * پردازش ثبت سفارش از صفحه محصول — قبل از هر خروجی و بعد از شروع سشن صدا زده شود.
 * CSRF سشنی + honeypot + محدودیت نرخ (حداکثر ۵ سفارش در ساعت و حداقل ۳۰ ثانیه فاصله).
 * مشتری با موبایل تازه ساخته می‌شود (نوع خرده)؛ سفارش با منبع site و وضعیت «جدید».
 * ورودی ردیف‌ها: so_lines[i][product_id/length_cm/qty/wire_cm/options/endcap/note]؛
 * برای سازگاری، فیلدهای تکی قدیمی (product_id, so_length_cm, ...) هم به‌عنوان یک ردیف پذیرفته می‌شوند.
 */
function process_site_order(): void
{
    if (!defined('CMS_SESSION_STARTED')) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['site_order'] ?? '') !== '1') {
        return;
    }
    if (order_setting('orders_public', '1') !== '1') {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'ثبت سفارش آنلاین در حال حاضر فعال نیست.']);
        return;
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    $tokenOk = hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
    $honeyOk = trim((string) ($_POST['website2'] ?? '')) === '';
    $times = array_filter((array) ($_SESSION['site_order_times'] ?? []), function ($t) {
        return is_numeric($t) && (time() - (int) $t) < 3600;
    });
    $rateOk = count($times) < 5 && (empty($times) || (time() - max($times)) >= 30);

    $name   = trim((string) ($_POST['so_name'] ?? ''));
    $mobile = preg_replace('/\D+/', '', (string) ($_POST['so_mobile'] ?? ''));
    $orderNote = trim((string) ($_POST['so_order_note'] ?? ''));

    $step = max(1, (int) order_setting('wire_step_cm', 5));
    $wireMax = (int) order_setting('wire_max_cm', 100);
    $wireDef = (float) order_setting('wire_default_cm', 20);
    $lineNoteOn = order_setting('order_line_note', '1') === '1';

    // یک ردیف را از روی آرایه ورودی اعتبارسنجی و نرمال می‌کند؛ [ردیف|null, پیام خطا]
    $parseLine = function (array $src) use ($step, $wireMax, $wireDef, $lineNoteOn): array {
        $pid = (int) ($src['product_id'] ?? 0);
        $lenCm = round((float) ($src['length_cm'] ?? 0), 1);
        $qty = max(1, (int) ($src['qty'] ?? 1));
        if ($pid <= 0 || $lenCm <= 0) {
            return [null, '']; // ردیف خالی → نادیده گرفته می‌شود
        }
        $product = get_product($pid);
        if ($product === null || (int) ($product['is_active'] ?? 0) !== 1) {
            return [null, 'محصول انتخابی معتبر نیست.'];
        }
        $wire = (isset($src['wire_cm']) && $src['wire_cm'] !== '') ? (float) $src['wire_cm'] : $wireDef;
        if ($wire < 0 || ($step > 0 && abs($wire / $step - round($wire / $step)) > 0.0001)) {
            return [null, 'طول سیم باید مضربی از ' . $step . ' سانت باشد.'];
        }
        if ($wire > $wireMax) {
            return [null, 'طول سیم نمی‌تواند بیشتر از ' . $wireMax . ' سانت باشد.'];
        }
        $opts = [];
        $rawOpts = $src['options'] ?? [];
        if (is_array($rawOpts)) {
            foreach ($rawOpts as $aidRaw => $oidRaw) {
                $oid = (int) $oidRaw;
                if ($oid > 0 && get_attribute_option($oid) !== null) {
                    $opts[(int) $aidRaw] = $oid;
                }
            }
        }
        $note = trim((string) ($src['note'] ?? ''));
        return [[
            'product_id' => $pid,
            'length_cm' => $lenCm,
            'qty' => $qty,
            'wire_length_cm' => $wire,
            'has_endcap' => !empty($src['endcap']),
            'options' => $opts,
            'note' => ($lineNoteOn && $note !== '') ? mb_substr($note, 0, 500) : '',
        ], ''];
    };

    $lines = [];
    $lineErr = '';
    $rawLines = $_POST['so_lines'] ?? null;
    if (is_array($rawLines)) {
        foreach ($rawLines as $rl) {
            if (!is_array($rl)) {
                continue;
            }
            [$ln, $e] = $parseLine($rl);
            if ($e !== '') {
                $lineErr = $e;
                break;
            }
            if ($ln !== null) {
                $lines[] = $ln;
            }
        }
    } else {
        // مسیر قدیمی تک‌ردیفه (سازگاری با فرم‌های کش‌شده و ارسال‌های دستی)
        [$ln, $e] = $parseLine([
            'product_id' => $_POST['product_id'] ?? 0,
            'length_cm' => $_POST['so_length_cm'] ?? 0,
            'qty' => $_POST['so_qty'] ?? 1,
            'wire_cm' => $_POST['so_wire_cm'] ?? '',
            'options' => $_POST['so_options'] ?? [],
            'endcap' => $_POST['so_endcap'] ?? '',
            'note' => $_POST['so_note'] ?? '',
        ]);
        if ($e !== '') {
            $lineErr = $e;
        } elseif ($ln !== null) {
            $lines[] = $ln;
        }
    }

    if (!$tokenOk) {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'درخواست نامعتبر است؛ صفحه را تازه کنید.']);
    } elseif (!$honeyOk || !$rateOk) {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'ارسال انجام نشد؛ کمی صبر کنید و دوباره تلاش کنید.']);
    } elseif ($name === '' || strlen($mobile) < 10) {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'نام و شماره موبایل معتبر را وارد کنید.']);
    } elseif ($lineErr !== '') {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => $lineErr]);
    } elseif ($lines === []) {
        site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'حداقل یک چراغ با طول معتبر وارد کنید.']);
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $ex = $pdo->prepare('SELECT * FROM customers WHERE mobile = :m LIMIT 1');
            $ex->execute([':m' => $mobile]);
            $customer = $ex->fetch();
            if ($customer === false) {
                $pdo->prepare('INSERT INTO customers (full_name, mobile, customer_type) VALUES (:n, :m, :t)')
                    ->execute([':n' => $name, ':m' => $mobile, ':t' => 'retail']);
                $customer = get_customer((int) $pdo->lastInsertId());
            }
            $tot = compute_order_totals($lines, false);
            $orderNo = (int) order_setting('next_order_no', 1001);
            $prepDays = (int) order_setting('default_prep_days', 3);
            foreach ($lines as $ln) {
                $pp = get_product((int) $ln['product_id']);
                if ($pp !== null) {
                    $prepDays = max($prepDays, (int) ($pp['prep_days'] ?? 0));
                }
            }
            $pdo->prepare('INSERT INTO orders (order_no, customer_id, customer_type, source, status, subtotal, discount_percent, discount_amount, total, total_meters, total_fixtures, prep_days, notes, created_by)
                VALUES (:no, :cid, :ct, :src, :st, :sub, :dp, :da, :tot, :m, :f, :prep, :notes, :by)')
                ->execute([
                    ':no' => $orderNo, ':cid' => (int) $customer['id'], ':ct' => 'retail',
                    ':src' => 'site', ':st' => 'new',
                    ':sub' => $tot['subtotal'], ':dp' => $tot['discount_percent'], ':da' => $tot['discount_amount'],
                    ':tot' => $tot['total'], ':m' => $tot['total_meters'], ':f' => $tot['total_fixtures'],
                    ':prep' => $prepDays,
                    ':notes' => $orderNote !== '' ? ('ثبت از سایت: ' . mb_substr($orderNote, 0, 500)) : 'ثبت از سایت',
                    ':by' => 'site',
                ]);
            $oid = (int) $pdo->lastInsertId();
            $sort = 10;
            foreach ($tot['lines'] as $idx => $tl) {
                $ln = $lines[$idx] ?? null;
                if ($ln === null) {
                    continue;
                }
                $optSnap = [];
                foreach ((array) ($ln['options'] ?? []) as $aid => $opid) {
                    $a = get_attribute((int) $aid);
                    $oo = get_attribute_option((int) $opid);
                    if ($a !== null && $oo !== null) {
                        $optSnap[] = ['attr' => (string) $a['title'], 'option' => (string) $oo['title'], 'delta' => (float) ($oo['price_delta_per_meter'] ?? 0)];
                    }
                }
                $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, length_cm, qty, billable_m, unit_price_per_m, options_json, options_extra_per_m, wire_length_cm, wire_steps, wire_extra_total, has_endcap, note, line_subtotal, line_total, sort_order)
                    VALUES (:o, :p, :pn, :len, :q, :bm, :up, :oj, :oe, :w, :ws, :we, :ec, :note, :ls, :lt, :so)')
                    ->execute([
                        ':o' => $oid, ':p' => (int) $tl['product_id'], ':pn' => (string) ($tl['product_id'] ? (string) (get_product((int) $tl['product_id'])['name'] ?? '') : ''),
                        ':len' => $tl['length_cm'], ':q' => $tl['qty'], ':bm' => $tl['billable_m'],
                        ':up' => $tl['unit_price_per_m'], ':oj' => $optSnap === [] ? null : json_encode($optSnap, JSON_UNESCAPED_UNICODE),
                        ':oe' => $tl['options_extra_per_m'], ':w' => $tl['wire_length_cm'], ':ws' => $tl['wire_steps'],
                        ':we' => $tl['wire_extra_total'], ':ec' => !empty($ln['has_endcap']) ? 1 : 0,
                        ':note' => ($ln['note'] !== '' ? $ln['note'] : null),
                        ':ls' => $tl['line_subtotal'], ':lt' => $tl['line_total'], ':so' => $sort,
                    ]);
                $sort += 10;
            }
            $pdo->prepare("INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, NULL, 'new', :n)")
                ->execute([':o' => $oid, ':n' => 'ثبت سفارش از سایت']);
            set_setting('next_order_no', (string) ($orderNo + 1));
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            site_order_state(['submitted' => true, 'ok' => false, 'msg' => '', 'err' => 'خطا در ثبت سفارش؛ لطفاً دوباره تلاش کنید.']);
            return;
        }
        $times[] = time();
        $_SESSION['site_order_times'] = array_values($times);
        site_order_state(['submitted' => true, 'ok' => true, 'msg' => 'سفارش شما با شماره ' . $orderNo . ' ثبت شد (' . format_price($tot['total']) . ' تومان، ' . $tot['total_fixtures'] . ' چراغ). به‌زودی با شما تماس می‌گیریم.', 'err' => '']);
    }
}

/** استایل فرم ثبت سفارش — منبع واحد؛ هم در CSS پیش‌فرض می‌نشیند هم مهاجرت ۸٫۲٫۳ آن را به CSS دیتابیس اضافه می‌کند */
function order_form_css(): string
{
    return <<<'CSS'
/* ===== فرم ثبت سفارش (بازطراحی نسخه ۸٫۲٫۳) ===== v8.2.3-order-form */
.site-order{margin:26px 0 120px;background:var(--surface);border:1px solid var(--surface-border);border-radius:calc(var(--radius) + 6px);padding:20px;box-shadow:0 8px 28px rgba(15,23,42,.07)}
.site-order h2{margin:0;font-size:21px;line-height:1.5}
.site-order .alert{max-width:none;margin:0 0 16px}
.so-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid var(--surface-border);margin-bottom:18px}
.so-product{display:flex;align-items:center;gap:12px;min-width:0}
.so-thumb{width:58px;height:58px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--surface-border);flex:none}
.so-product-name{font-weight:700;font-size:16px}
.so-price{margin:0;font-size:13px;color:var(--muted);text-align:end}
.so-price strong{display:block;color:var(--primary);font-size:18px;line-height:1.6}
.so-body{display:grid;gap:22px}
.so-section h3{margin:0 0 12px;font-size:15px}
.so-grid{display:grid;grid-template-columns:1fr;gap:14px}
.so-field{min-width:0}
.so-grid .so-full{grid-column:1/-1}
.site-order .so-field>label,.site-order .so-label{display:block;margin-bottom:6px;font-size:13.5px;font-weight:600}
.site-order input[type=text],.site-order input[type=tel],.site-order input[type=number],.site-order textarea,.site-order select{width:100%;padding:11px 12px;min-height:48px;border:1px solid var(--input-border);border-radius:var(--radius);background:var(--input-bg);color:var(--text);font:inherit;font-size:16px}
.site-order select{appearance:auto}
.site-order textarea{min-height:84px;resize:vertical}
.site-order input:focus,.site-order textarea:focus,.site-order select:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,.18);outline:none}
.so-help{color:var(--muted);font-size:12.5px;margin:5px 0 0;line-height:1.8}
.so-input-unit{position:relative}
.so-input-unit input{padding-inline-end:72px}
.so-unit{position:absolute;inset-inline-end:12px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:13px;pointer-events:none}
.so-stepper{display:flex;border:1px solid var(--input-border);border-radius:var(--radius);background:var(--input-bg);overflow:hidden}
.so-stepper button{width:48px;min-height:48px;border:0;background:var(--surface);color:var(--text);font-size:22px;line-height:1;cursor:pointer;flex:none;padding:0}
.so-stepper button:active{background:var(--surface-border)}
.site-order .so-stepper input{border:0;box-shadow:none;text-align:center;padding-right:4px;padding-left:4px}
.so-range{display:flex;align-items:center;gap:12px}
.site-order input[type=range]{flex:1;width:auto;min-height:48px;padding:0;border:0;background:transparent;accent-color:var(--primary);cursor:pointer;box-shadow:none}
.so-range-val{flex:none;min-width:84px;text-align:center;background:var(--bg);border:1px solid var(--surface-border);border-radius:99px;padding:7px 10px;font-weight:700;font-size:14px}
.so-chips{display:flex;flex-wrap:wrap;gap:8px}
.so-chip{position:relative;display:inline-block;margin:0}
.site-order .so-chip input{position:absolute;inset:0;width:100%;height:100%;min-height:0;padding:0;border:0;opacity:0;cursor:pointer}
.so-chip span{display:inline-flex;align-items:center;gap:6px;min-height:44px;padding:8px 14px;border:1.5px solid var(--input-border);border-radius:99px;background:var(--input-bg);font-size:14px;line-height:1.6;transition:border-color .15s ease,background .15s ease,color .15s ease}
.so-chip small{font-size:11.5px;opacity:.8}
.so-chip:hover span{border-color:var(--primary)}
.so-chip input:checked+span{border-color:var(--primary);background:var(--primary);color:#fff}
.so-chip input:focus-visible+span{outline:2px solid var(--primary);outline-offset:2px}
.so-lines{display:grid;gap:14px}
.so-line{border:1px solid var(--surface-border);border-radius:var(--radius);padding:14px;background:var(--bg);display:grid;gap:12px}
.so-line-head{display:flex;align-items:center;justify-content:space-between;gap:10px}
.so-line-title{font-weight:700;font-size:14.5px}
.so-line-remove{border:1px solid var(--input-border);background:var(--input-bg);color:var(--muted);border-radius:99px;padding:6px 14px;font:inherit;font-size:12.5px;cursor:pointer;min-height:38px}
.so-line-remove:hover{color:#dc2626;border-color:#dc2626}
.so-line-total{font-size:13.5px;color:var(--muted);border-top:1px dashed var(--surface-border);padding-top:8px}
.so-line-total strong{color:var(--primary);font-size:16px}
.so-add{width:100%;margin-top:2px;border:1.5px dashed var(--input-border);background:transparent;color:var(--primary);border-radius:var(--radius);padding:12px;min-height:52px;font:inherit;font-weight:700;font-size:15px;cursor:pointer}
.so-add:hover{border-color:var(--primary);background:rgba(37,99,235,.05)}
.so-summary{position:sticky;bottom:10px;z-index:5;background:var(--bg);border:1px solid var(--surface-border);border-radius:calc(var(--radius) + 4px);padding:12px 16px 14px;box-shadow:var(--shadow)}
.so-toggle{display:flex;width:100%;align-items:center;justify-content:space-between;border:0;background:none;color:var(--muted);font:inherit;font-size:13px;cursor:pointer;padding:2px 0 8px;min-height:36px}
.so-toggle svg{transition:transform .2s ease;flex:none}
.so-summary.open .so-toggle svg{transform:rotate(180deg)}
.so-meta{display:none}
.so-summary.open .so-meta{display:block}
.so-sum-row{display:flex;justify-content:space-between;align-items:center;gap:10px;font-size:14px;color:var(--muted);padding:3px 0}
.so-sum-row strong{color:var(--text);font-weight:700}
.so-sum-total{display:flex;justify-content:space-between;align-items:center;gap:10px;border-top:1px dashed var(--surface-border);margin-top:6px;padding-top:10px;font-size:15px}
.so-sum-total strong{color:var(--primary);font-size:20px;white-space:nowrap}
.so-minbill{color:var(--muted);font-size:12.5px;margin:8px 0 0;line-height:1.8}
.so-submit{width:100%;margin-top:12px;padding:13px 20px;min-height:52px;border:1px solid var(--primary);border-radius:var(--radius);background:var(--primary);color:#fff;font:inherit;font-size:16px;font-weight:700;cursor:pointer}
.so-submit:hover{background:var(--primary-dark);border-color:var(--primary-dark)}
.so-submit[disabled]{opacity:.65;cursor:wait}
@media (min-width:720px){
    .site-order{padding:26px;margin-bottom:26px}
    .so-grid{grid-template-columns:1fr 1fr}
    .so-meta{display:block}
    .so-toggle{display:none}
}
@media print{
    .site-order{display:none!important}
}
CSS;
}

/** فرم «ثبت سفارش» زیر صفحه محصول (فقط وقتی orders_public=1) — چندردیفی، نسخه ۸٫۲٫۳ */
function site_order_form_html(array $product): string
{
    if (order_setting('orders_public', '1') !== '1' || (int) ($product['id'] ?? 0) <= 0) {
        return '';
    }
    $state = site_order_state();
    if (defined('CMS_SESSION_STARTED') && empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    $pid = (int) $product['id'];
    $step = max(1, (int) order_setting('wire_step_cm', 5));
    $wireDef = (int) order_setting('wire_default_cm', 20);
    $wireMax = (int) order_setting('wire_max_cm', 100);
    $minBill = (float) order_setting('min_billable_m', 0.5);
    $lineNoteOn = order_setting('order_line_note', '1') === '1';
    $img = uploaded_image_url($product['image'] ?? '');

    // کاتالوگ محصولات فعال + گزینه‌های قابل‌ارائه هر محصول (برای انتخاب محصول هر ردیف و چیپ‌ها)
    $catalog = [];
    foreach (get_products(true) as $p) {
        $cpid = (int) $p['id'];
        $attrs = [];
        foreach (product_offered_attributes($cpid) as $item) {
            if ($item['default_option_id'] === null) {
                continue;
            }
            $ao = ['id' => (int) $item['attribute']['id'], 'title' => (string) $item['attribute']['title'], 'def' => (int) $item['default_option_id'], 'options' => []];
            foreach ($item['options'] as $op) {
                $ao['options'][] = ['id' => (int) $op['id'], 'title' => (string) $op['title'], 'delta' => (float) ($op['price_delta_per_meter'] ?? 0)];
            }
            $attrs[] = $ao;
        }
        $catalog[$cpid] = ['name' => (string) $p['name'], 'price' => (float) product_base_price_per_meter($p, false), 'attrs' => $attrs];
    }

    $optHtml = function (?int $sel) use ($catalog): string {
        $s = '';
        foreach ($catalog as $cpid => $c) {
            $s .= '<option value="' . $cpid . '"' . ($sel !== null && $cpid === $sel ? ' selected' : '') . '>' . e($c['name']) . ' — ' . e(format_price($c['price'])) . ' تومان/متر</option>';
        }
        return $s;
    };
    $chipsHtml = function (?int $cpid, $idx) use ($catalog): string {
        if ($cpid === null || !isset($catalog[$cpid])) {
            return '';
        }
        $h = '';
        foreach ($catalog[$cpid]['attrs'] as $a) {
            $h .= '<div class="so-field" data-attr-group><span class="so-label">' . e($a['title']) . '</span><div class="so-chips" role="radiogroup" data-attrs>';
            foreach ($a['options'] as $op) {
                $d = (float) $op['delta'];
                $h .= '<label class="so-chip"><input type="radio" class="so-option" name="so_lines[' . $idx . '][options][' . $a['id'] . ']" value="' . $op['id'] . '" data-delta="' . $d . '"' . ($op['id'] === $a['def'] ? ' checked' : '') . '><span>' . e($op['title']);
                if ($d != 0.0) {
                    $h .= ' <small>(' . ($d > 0 ? '+' : '') . e(format_price($d)) . ' تومان/متر)</small>';
                }
                $h .= '</span></label>';
            }
            $h .= '</div></div>';
        }
        return $h;
    };
    $lineHtml = function ($idx, ?int $selPid, bool $removable) use ($optHtml, $chipsHtml, $step, $wireDef, $wireMax, $lineNoteOn): string {
        $h = '<div class="so-line" data-line data-idx="' . $idx . '">';
        $h .= '<div class="so-line-head"><span class="so-line-title">چراغ <span data-num>۱</span></span>';
        if ($removable) {
            $h .= '<button type="button" class="so-line-remove" data-remove>حذف این چراغ</button>';
        }
        $h .= '</div>';
        $h .= '<div class="so-field"><label for="so-prod-' . $idx . '">محصول *</label><select id="so-prod-' . $idx . '" name="so_lines[' . $idx . '][product_id]" class="so-product-sel" required>' . $optHtml($selPid) . '</select></div>';
        $h .= $chipsHtml($selPid, $idx);
        $h .= '<div class="so-grid">';
        $h .= '<div class="so-field"><label for="so-len-' . $idx . '">طول هر چراغ *</label><div class="so-input-unit"><input type="number" id="so-len-' . $idx . '" name="so_lines[' . $idx . '][length_cm]" step="0.1" min="0.1" required inputmode="decimal" data-len><span class="so-unit">سانتی‌متر</span></div><p class="so-help" data-meters>طول را به سانتی‌متر وارد کنید؛ تا یک رقم اعشار.</p></div>';
        $h .= '<div class="so-field"><label>تعداد چراغ *</label><div class="so-stepper"><button type="button" data-step="-1" aria-label="کاهش تعداد">−</button><input type="number" name="so_lines[' . $idx . '][qty]" value="1" min="1" step="1" required inputmode="numeric" data-qty><button type="button" data-step="1" aria-label="افزایش تعداد">+</button></div></div>';
        $h .= '</div>';
        $h .= '<div class="so-field"><label>طول سیم هر چراغ</label><div class="so-range"><input type="range" name="so_lines[' . $idx . '][wire_cm]" min="0" max="' . $wireMax . '" step="' . $step . '" value="' . $wireDef . '" data-wire><output class="so-range-val" data-wire-val>' . $wireDef . ' سانت</output></div><p class="so-help">پیش‌فرض ' . $wireDef . ' سانت است؛ سیمِ بیشتر از پیش‌فرض در فاکتور حساب می‌شود.</p></div>';
        $h .= '<div class="so-field"><span class="so-label" id="so-ec-' . $idx . '">درپوش دو سر چراغ</span><div class="so-chips" role="radiogroup" aria-labelledby="so-ec-' . $idx . '">'
            . '<label class="so-chip"><input type="radio" name="so_lines[' . $idx . '][endcap]" value="1"><span>با درپوش</span></label>'
            . '<label class="so-chip"><input type="radio" name="so_lines[' . $idx . '][endcap]" value="0" checked><span>بدون درپوش</span></label></div>'
            . '<p class="so-help">درپوش، دو سر پروفیل را می‌بندد و ظاهر چراغ را تمیز و کامل می‌کند.</p></div>';
        if ($lineNoteOn) {
            $h .= '<div class="so-field"><label for="so-note-' . $idx . '">توضیح این چراغ</label><textarea id="so-note-' . $idx . '" name="so_lines[' . $idx . '][note]" rows="2" maxlength="500" placeholder="مثلاً: یونیت زیر گاز"></textarea></div>';
        }
        $h .= '<div class="so-line-total">برآورد این چراغ: <strong data-line-total>۰ تومان</strong></div>';
        $h .= '</div>';
        return $h;
    };

    $html = '<div class="site-order" id="site-order">';
    $html .= '<div class="so-head"><div class="so-product">';
    if ($img !== '') {
        $html .= '<img class="so-thumb" src="' . e($img) . '" alt="" loading="lazy" decoding="async">';
    }
    $html .= '<div><h2>ثبت سفارش</h2><div class="so-product-name">' . e((string) ($product['name'] ?? '')) . '</div></div></div>';
    $html .= '<p class="so-price">ثبت چند چراغ با ابعاد مختلف در یک سفارش</p></div>';
    if ((string) ($state['msg'] ?? '') !== '') {
        $html .= '<div class="alert ok" role="status">' . e((string) $state['msg']) . '</div>';
    }
    if ((string) ($state['err'] ?? '') !== '') {
        $html .= '<div class="alert error" role="alert">' . e((string) $state['err']) . '</div>';
    }
    $html .= '<form method="post" action="products.php?id=' . $pid . '#site-order" id="so-form">';
    $html .= '<input type="hidden" name="site_order" value="1"><input type="hidden" name="product_id" value="' . $pid . '"><input type="hidden" name="csrf" value="' . e((string) ($_SESSION['csrf'] ?? '')) . '">';
    $html .= '<div class="hp-field" aria-hidden="true"><label>این فیلد را خالی بگذارید<input type="text" name="website2" tabindex="-1" autocomplete="off"></label></div>';

    $html .= '<div class="so-body"><section class="so-section" aria-label="چراغ‌ها"><h3>چراغ‌ها</h3>';
    $html .= '<div class="so-lines" id="so-lines">' . $lineHtml(0, $pid, false) . '</div>';
    $html .= '<button type="button" class="so-add" id="so-add">+ افزودن چراغ دیگر</button>';
    $html .= '<template id="so-line-tpl">' . $lineHtml('__I__', null, true) . '</template>';
    $html .= '</section>';
    $html .= '<section class="so-section" aria-label="اطلاعات تماس"><h3>اطلاعات تماس</h3><div class="so-grid">';
    $html .= '<div class="so-field"><label for="so-name">نام و نام خانوادگی *</label><input type="text" id="so-name" name="so_name" required autocomplete="name" maxlength="120"></div>';
    $html .= '<div class="so-field"><label for="so-mobile">شماره موبایل *</label><input type="text" id="so-mobile" name="so_mobile" required inputmode="numeric" autocomplete="tel" dir="ltr" maxlength="15"><p class="so-help">برای هماهنگی و پیگیری سفارش، با این شماره در تماسیم.</p></div>';
    $html .= '<div class="so-field so-full"><label for="so-order-note">توضیحات سفارش (اختیاری)</label><textarea id="so-order-note" name="so_order_note" rows="2" maxlength="500" placeholder="توضیحی که به کل سفارش مربوط است"></textarea></div>';
    $html .= '</div></section></div>';
    $html .= '<div class="so-summary" id="so-summary">'
        . '<button type="button" class="so-toggle" id="so-toggle" aria-expanded="false"><span>جزئیات سفارش</span><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>'
        . '<div class="so-meta"><div class="so-sum-row"><span>متراژ قابل صورتحساب</span><strong id="so-billm">—</strong></div>'
        . '<div class="so-sum-row"><span>تعداد چراغ</span><strong id="so-qtyv">—</strong></div></div>'
        . '<div class="so-sum-total"><span>برآورد مبلغ</span><strong><span id="so-total">۰</span> تومان</strong></div>'
        . '<p class="so-minbill">طول کمتر از ' . e(format_qty($minBill)) . ' متر، ' . e(format_qty($minBill)) . ' متر حساب می‌شود. این برآورد اولیه است؛ مبلغ نهایی (با احتساب سیم اضافه و تخفیف) در پیش‌فاکتور مشخص می‌شود.</p>'
        . '<button type="submit" class="so-submit" id="so-submit">ثبت سفارش</button></div>';
    $html .= '</form>';
    $html .= '<script>window.SO_CATALOG=' . json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) . ';window.SO_CFG=' . json_encode(['wireStep' => $step, 'wireDef' => $wireDef, 'wireMax' => $wireMax, 'minBill' => $minBill], JSON_UNESCAPED_UNICODE) . ';window.SO_DEF_PID=' . $pid . ';</script>';
    $html .= <<<'JS'
<script>(function(){var f=document.getElementById('so-form');if(!f)return;var CAT=window.SO_CATALOG||{},CFG=window.SO_CFG||{},DEF=window.SO_DEF_PID||0;var wrap=document.getElementById('so-lines'),tpl=document.getElementById('so-line-tpl'),counter=1;function esc(s){return String(s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}function fa(n,d){return Number(n).toLocaleString('fa-IR',{maximumFractionDigits:d});}function money(n){return Math.round(n).toLocaleString('fa-IR');}function attrsHtml(pid,idx){var p=CAT[pid];if(!p||!p.attrs||!p.attrs.length)return'';var h='';p.attrs.forEach(function(a){h+='<div class="so-field" data-attr-group><span class="so-label">'+esc(a.title)+'</span><div class="so-chips" role="radiogroup" data-attrs>';a.options.forEach(function(o){var d=parseFloat(o.delta)||0;h+='<label class="so-chip"><input type="radio" class="so-option" name="so_lines['+idx+'][options]['+a.id+']" value="'+o.id+'" data-delta="'+d+'"'+(o.id===a.def?' checked':'')+'><span>'+esc(o.title)+(d!==0?(' <small>('+(d>0?'+':'')+fa(d,0)+' تومان/متر)</small>'):'')+'</span></label>';});h+='</div></div>';});return h;}function paintAttrs(line){var sel=line.querySelector('.so-product-sel');if(!sel)return;var idx=line.getAttribute('data-idx');line.querySelectorAll('[data-attr-group]').forEach(function(g){g.remove();});var holder=document.createElement('div');holder.innerHTML=attrsHtml(sel.value,idx);var ref=sel.closest('.so-field');while(holder.firstChild){ref.parentNode.insertBefore(holder.firstChild,ref.nextSibling);}calc();}function renumber(){var ls=wrap.querySelectorAll('[data-line]');ls.forEach(function(el,k){var n=el.querySelector('[data-num]');if(n)n.textContent=fa(k+1,0);var rm=el.querySelector('[data-remove]');if(rm)rm.style.display=(ls.length>1)?'':'none';});}function lineVals(line){var sel=line.querySelector('.so-product-sel');var pid=sel?sel.value:'0';var p=CAT[pid];var price=p?parseFloat(p.price):0;var d=0;line.querySelectorAll('.so-option:checked').forEach(function(s){d+=parseFloat(s.getAttribute('data-delta')||'0');});var lenEl=line.querySelector('[data-len]');var qtyEl=line.querySelector('[data-qty]');var L=(parseFloat(lenEl?lenEl.value:'')||0)/100;var Q=parseInt(qtyEl?qtyEl.value:'10',10);if(!(Q>0))Q=0;var minB=parseFloat(CFG.minBill||'0.5');var billOne=Math.max(L,minB);return{L:L,Q:Q,billOne:billOne,unit:price+d,lineTotal:billOne*(price+d)*Q};}function calc(){var totM=0,totQ=0,totP=0;wrap.querySelectorAll('[data-line]').forEach(function(line){var v=lineVals(line);var lenEl=line.querySelector('[data-len]');var mEl=line.querySelector('[data-meters]');if(mEl)mEl.textContent=v.L>0?('≈ '+fa(v.L,2)+' متر'):'طول را به سانتی‌متر وارد کنید؛ تا یک رقم اعشار.';var wEl=line.querySelector('[data-wire]');var wv=line.querySelector('[data-wire-val]');if(wEl&&wv)wv.textContent=fa(parseInt(wEl.value,10)||0,0)+' سانت';var lt=line.querySelector('[data-line-total]');if(lt)lt.textContent=(v.L>0&&v.Q>0)?(money(v.lineTotal)+' تومان'):'۰ تومان';if(v.L>0&&v.Q>0){totM+=v.billOne*v.Q;totQ+=v.Q;totP+=v.lineTotal;}});document.getElementById('so-billm').textContent=(totQ>0)?(fa(totM,2)+' متر'):'—';document.getElementById('so-qtyv').textContent=(totQ>0)?(fa(totQ,0)+' چراغ'):'—';document.getElementById('so-total').textContent=money(totP);}function addLine(){var html=tpl.innerHTML.replace(/__I__/g,String(counter));var d=document.createElement('div');d.innerHTML=html;var line=d.firstElementChild;line.setAttribute('data-idx',String(counter));counter++;wrap.appendChild(line);var sel=line.querySelector('.so-product-sel');if(sel&&DEF)sel.value=String(DEF);paintAttrs(line);renumber();calc();if(line.scrollIntoView)line.scrollIntoView({behavior:'smooth',block:'nearest'});}document.getElementById('so-add').addEventListener('click',addLine);f.addEventListener('click',function(e){var rm=e.target.closest('[data-remove]');if(rm){var ls=wrap.querySelectorAll('[data-line]');if(ls.length>1){rm.closest('[data-line]').remove();renumber();calc();}return;}var st=e.target.closest('[data-step]');if(st){var q=st.closest('.so-stepper').querySelector('[data-qty]');var v=parseInt(q.value,10)||1;v=Math.max(1,v+parseInt(st.getAttribute('data-step'),10));q.value=v;calc();return;}if(e.target.closest('#so-toggle')){var s=document.getElementById('so-summary');var open=s.classList.toggle('open');document.getElementById('so-toggle').setAttribute('aria-expanded',open?'true':'false');}});f.addEventListener('input',function(e){calc();});f.addEventListener('change',function(e){var sel=e.target.closest?e.target.closest('.so-product-sel'):null;if(sel){paintAttrs(sel.closest('[data-line]'));}else{calc();}});f.addEventListener('submit',function(){var b=document.getElementById('so-submit');if(b){b.disabled=true;b.textContent='در حال ثبت…';}});wrap.querySelectorAll('[data-line]').forEach(function(line){paintAttrs(line);});renumber();calc();})();</script>
JS;
    $html .= '</div>';
    return $html;
}
