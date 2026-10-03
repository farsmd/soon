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
        <a class="logo" href="/"><img class="logo-img" src="uploads/gallery/logo.png" alt="{{site_title}}" onerror="this.remove()"><span>{{site_title}}</span></a>
        {{menu}}
        <a class="btn btn-gold btn-sm header-cta" href="/products">ثبت سفارش</a>
    </div>
</header>
HTML,
        ],
        'slider' => [
            'title'   => 'اسلایدر (قهرمان صفحه)',
            'content' => <<<'HTML'
<section class="slider cinematic-hero">
    <div class="hero-media" style="background-image:url('uploads/gallery/gallery-14.jpg')"></div>
    <canvas class="hero-canvas" aria-hidden="true"></canvas>
    <div class="hero-shade"></div>
    <div class="container hero-inner">
        <p class="hero-kicker">استودیو تخصصی نور خطی</p>
        <h1>{{section_heading}}</h1>
        <div class="lead">{{section_body}}</div>
        <div class="hero-ctas">
            {{#if section_link_url}}<a class="btn btn-gold" href="{{section_link_url}}">{{section_link_text}}</a>{{/if}}
            <a class="btn btn-ghost" href="/gallery">مشاهده پروژه‌ها</a>
        </div>
    </div>
    <a class="hero-scroll" href="#features" aria-label="رفتن به بخش بعد"><span></span></a>
</section>
<div class="marquee" aria-hidden="true">
    <div class="mq-track">
        <span class="mq-item"><b>لاینرلایت</b>&nbsp;— طراحی و تولید چراغ خطی سفارشی</span><span class="mq-dot">✦</span>
        <span class="mq-item">برش دقیق در ابعاد دلخواه شما</span><span class="mq-dot">✦</span>
        <span class="mq-item">نورپردازی کمد، کابینت و کلوزت</span><span class="mq-dot">✦</span>
        <span class="mq-item">پروفیل آلومینیومی و سیلیکونی</span><span class="mq-dot">✦</span>
        <span class="mq-item"><b>۲۰۰۰ لومن</b>&nbsp;در هر متر</span><span class="mq-dot">✦</span>
        <span class="mq-item">گارانتی و کنترل کیفیت</span><span class="mq-dot">✦</span>
        <span class="mq-item"><b>لاینرلایت</b>&nbsp;— طراحی و تولید چراغ خطی سفارشی</span><span class="mq-dot">✦</span>
        <span class="mq-item">برش دقیق در ابعاد دلخواه شما</span><span class="mq-dot">✦</span>
        <span class="mq-item">نورپردازی کمد، کابینت و کلوزت</span><span class="mq-dot">✦</span>
        <span class="mq-item">پروفیل آلومینیومی و سیلیکونی</span><span class="mq-dot">✦</span>
        <span class="mq-item"><b>۲۰۰۰ لومن</b>&nbsp;در هر متر</span><span class="mq-dot">✦</span>
        <span class="mq-item">گارانتی و کنترل کیفیت</span><span class="mq-dot">✦</span>
    </div>
</div>
{{products_showcase}}
<script>
(function(){
    /* ذرات نورانی هیرو */
    var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var c=document.querySelector('.hero-canvas');
    if(c&&c.getContext&&!reduce){
        var x=c.getContext('2d'),W=0,H=0,P=[],i;
        function rs(){W=c.width=c.offsetWidth;H=c.height=c.offsetHeight;}
        rs();window.addEventListener('resize',rs);
        for(i=0;i<70;i++){P.push({x:Math.random(),y:Math.random(),r:Math.random()*1.8+.5,s:Math.random()*.0006+.0002,o:Math.random()*.5+.15});}
        (function t(){
            x.clearRect(0,0,W,H);
            for(i=0;i<P.length;i++){var p=P[i];p.y-=p.s;if(p.y<-.02){p.y=1.02;p.x=Math.random();}
                x.globalAlpha=p.o;x.fillStyle='#f0d488';x.beginPath();x.arc(p.x*W,p.y*H,p.r,0,6.3);x.fill();}
            x.globalAlpha=1;requestAnimationFrame(t);
        })();
    }
    /* ظهور هنگام اسکرول — نسخه ۸٫۱۰٫۱: بعد از لود کامل DOM اجرا شود */
    function initReveal(){
        var els=document.querySelectorAll('.rv');
        if(!('IntersectionObserver' in window)||reduce){for(var j=0;j<els.length;j++){els[j].classList.add('in');}return;}
        var o=new IntersectionObserver(function(es){for(var k=0;k<es.length;k++){if(es[k].isIntersecting){es[k].target.classList.add('in');o.unobserve(es[k].target);}}},{threshold:.12});
        for(var j=0;j<els.length;j++){o.observe(els[j]);}
    }
    if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',initReveal);}else{initReveal();}
})();
</script>
HTML,
        ],
        'features' => [
            'title'   => 'ویژگی‌ها',
            'content' => <<<'HTML'
<section id="features" class="features">
    <div class="container">
        <p class="sec-kicker rv">ویترین محصولات</p>
        <h2 class="sec-title rv">{{section_heading}}</h2>
        {{#if section_body}}<div class="section-body rv">{{section_body}}</div>{{/if}}
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
    <div class="container footer-grid">
        <div class="f-brand">
            <p class="f-logo">{{site_title}}</p>
            <p class="muted">استودیو طراحی و تولید چراغ‌های خطی سفارشی؛ نورپردازی کمد، کابینت، کلوزت و فضاهای دکوراتیو با برش دقیق در ابعاد دلخواه شما.</p>
        </div>
        <div>
            <p class="f-title">دسترسی سریع</p>
            <nav class="f-links" aria-label="دسترسی سریع">
                <a href="/">خانه</a>
                <a href="/products">محصولات</a>
                <a href="/gallery">گالری پروژه‌ها</a>
                <a href="/about">درباره ما</a>
            </nav>
        </div>
        <div>
            <p class="f-title">سفارش و کاتالوگ</p>
            <nav class="f-links" aria-label="سفارش و کاتالوگ">
                <a href="/products">ثبت سفارش</a>
                <a href="/catalog/linerlight-catalog.pdf">دانلود کاتالوگ (PDF)</a>
            </nav>
        </div>
    </div>
    <div class="container f-bottom">
        <p>© {{current_year}} {{site_title}} — همه حقوق محفوظ است.</p>
        <p class="muted">طراحی‌شده با نور، برای فضای شما</p>
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
        'contact_page' => [
            'title'   => 'صفحه تماس با ما',
            'content' => <<<'HTML'
<section class="content-section contact-page">
    <div class="container">
        <div class="contact-hero">
            <h1>{{page_title}}</h1>
            <p class="contact-subtitle">برای مشاوره، ثبت سفارش و همکاری با ما در تماس باشید. کارشناسان ما در اسرع وقت پاسخگوی شما هستند.</p>
        </div>
        <div class="contact-grid">
            <div class="contact-cards">
                {{#if contact_phone}}
                <a href="tel:{{contact_phone}}" class="contact-card">
                    <span class="contact-icon">📞</span>
                    <span class="contact-label">تلفن تماس</span>
                    <span class="contact-value" dir="ltr">{{contact_phone}}</span>
                </a>
                {{/if}}
                {{#if contact_whatsapp}}
                <a href="https://wa.me/{{contact_whatsapp_digits}}" target="_blank" rel="noopener" class="contact-card">
                    <span class="contact-icon">💬</span>
                    <span class="contact-label">واتساپ</span>
                    <span class="contact-value" dir="ltr">{{contact_whatsapp}}</span>
                </a>
                {{/if}}
                {{#if contact_email}}
                <a href="mailto:{{contact_email}}" class="contact-card">
                    <span class="contact-icon">✉️</span>
                    <span class="contact-label">ایمیل</span>
                    <span class="contact-value" dir="ltr">{{contact_email}}</span>
                </a>
                {{/if}}
                <a href="/card" class="contact-card contact-qr-card">
                    <span class="contact-icon">📱</span>
                    <span class="contact-label">کارت ویزیت دیجیتال (QR)</span>
                    <img src="qr-card.png" alt="QR کارت ویزیت لاینرلایت" class="contact-qr-img" loading="lazy">
                    <span class="contact-value contact-qr-hint">اسکن کنید یا لمس کنید</span>
                </a>
            </div>
            <div class="contact-form-wrap">
                <h2>ارسال پیام</h2>
                <p class="muted">فرم زیر را پر کنید تا با شما تماس بگیریم.</p>
                {{contact_form}}
            </div>
        </div>
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
        <p class="product-breadcrumb"><a href="/products">← بازگشت به کاتالوگ</a>{{#if category_title}} <span class="muted">/ {{category_title}}</span>{{/if}}</p>
        <div class="product-layout">
            {{#if product_image}}<div class="product-media"><img src="{{product_image}}" alt="{{product_name}}"></div>{{/if}}
            <div class="product-info">
                <h1>{{product_name}}</h1>
                {{#if product_description}}<div class="product-desc">{{product_description}}</div>{{/if}}
                {{#if is_per_watt}}<p class="product-price-line">{{#if base_price_formatted}}از <strong>{{base_price_formatted}}</strong> تومان{{/if}}{{#if price_per_watt_formatted}} <span class="muted">— هر وات {{price_per_watt_formatted}} تومان + قیمت قاب</span>{{/if}}{{#if frame_options_line}}<br><span class="muted">قاب‌ها: {{frame_options_line}}</span>{{/if}}</p>{{else}}<p class="product-price-line">قیمت متری: <strong>{{price_per_meter_formatted}}</strong> تومان{{#if partner_price_per_meter_formatted}} <span class="muted">| تخفیف همکار: {{partner_price_per_meter_formatted}} تومان</span>{{/if}}</p>{{/if}}
                {{product_specs}}
                {{product_gallery}}
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
function cinematic_base_css(): string
{
    return <<<'CSS'
/* ============================================================
   تم سینمایی لاینرلایت — نسخه ۸٫۹٫۰
   تیره، طلایی، مینیمال؛ همه توکن‌ها با «تنظیمات ظاهری» پنل قابل تغییرند.
   ساختار: توکن‌ها ← پایه ← هدر ← هیرو ← دکمه ← بخش‌ها ← ویترین محصولات
   ← گالری ← روند ← آمار ← فوتر ← صفحات داخلی ← فرم‌ها ← موبایل ← حرکت کم ← چاپ
   ============================================================ */

/* ---------- توکن‌ها ---------- */
:root{
    --primary:#c9a227;
    --primary-dark:#a8841c;
    --accent:#e8c66a;
    --accent-dark:#c9a227;
    --font-family:"Vazirmatn",Tahoma,"Segoe UI",Arial,sans-serif;
    --container-width:1200px;
    --radius:14px;
    --bg:#07090d;
    --bg-soft:#0b0e14;
    --surface:#0e131b;
    --surface-border:#1f2836;
    --text:#f2ede1;
    --muted:#9d937e;
    --gold-soft:rgba(201,162,39,.14);
    --header-bg:rgba(7,9,13,.72);
    --header-text:#f2ede1;
    --header-link:#cfc7b2;
    --footer-bg:#04060a;
    --footer-text:#b8ae97;
    --input-bg:#0e131b;
    --card:#0e131b;
    --border:#1f2836;
    --input-border:#2a3547;
    --alert-ok-bg:#12351f;
    --alert-ok-text:#b9e6c5;
    --alert-error-bg:#4a1a1a;
    --alert-error-text:#f3b8b8;
    --shadow:0 24px 60px rgba(0,0,0,.5);
    --line-glow:0 0 24px rgba(232,198,106,.55),0 0 80px rgba(201,162,39,.25);
}

/* ---------- پایه ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font-family);background:var(--bg);color:var(--text);line-height:2;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
    background:radial-gradient(1100px 520px at 85% -8%,rgba(201,162,39,.09),transparent 60%),
               radial-gradient(900px 500px at 8% 22%,rgba(232,198,106,.05),transparent 60%),
               radial-gradient(700px 700px at 50% 110%,rgba(201,162,39,.05),transparent 60%)}
img{max-width:100%;height:auto;display:block}
a{color:var(--accent);text-decoration:none}
a:hover{color:#fff}
h1,h2,h3{line-height:1.6;margin:0 0 .6em;font-weight:800}
p{margin:0 0 1em}
.container{width:min(var(--container-width),100% - 40px);margin-inline:auto}
.skip-link{position:absolute;top:-60px;right:16px;z-index:200;background:var(--primary);color:#111;padding:8px 16px;border-radius:8px;transition:top .2s}
.skip-link:focus{top:12px;color:#111}
.muted{color:var(--muted)}
/* دکمه تغییر تم روشن/تیره */
.theme-toggle{background:var(--gold-soft);border:1px solid var(--surface-border);border-radius:99px;
    width:44px;height:44px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;
    transition:transform .2s,background .2s}
.theme-toggle:hover{transform:scale(1.08);background:rgba(201,162,39,.25)}
main{display:block}
section{scroll-margin-top:84px}

/* ---------- دکمه‌ها ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 34px;border-radius:999px;
    font-family:inherit;font-weight:700;font-size:15px;cursor:pointer;border:1px solid transparent;
    transition:transform .25s ease,box-shadow .25s ease,background .25s ease,color .25s ease;white-space:nowrap}
.btn:active{transform:scale(.97)}
.btn-gold{background:linear-gradient(135deg,var(--accent),var(--primary));color:#191204;box-shadow:var(--line-glow)}
.btn-gold:hover{transform:translateY(-2px);color:#000;box-shadow:0 0 34px rgba(232,198,106,.7),0 0 90px rgba(201,162,39,.3)}
.btn-ghost{background:rgba(255,255,255,.04);border-color:rgba(232,198,106,.35);color:var(--text);backdrop-filter:blur(6px)}
.btn-ghost:hover{border-color:var(--accent);color:#fff;transform:translateY(-2px)}
.btn-light{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.16);color:#fff}
.btn-light:hover{border-color:var(--accent);color:#fff}
.btn-sm{padding:9px 22px;font-size:13.5px}

/* ---------- هدر ---------- */
.site-header{position:fixed;top:0;right:0;left:0;z-index:100;background:var(--header-bg);
    backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid rgba(232,198,106,.12)}
.header-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:70px;padding-block:10px}
.logo{font-weight:900;font-size:20px;color:var(--header-text);display:flex;align-items:center;gap:10px}
.logo::before{content:"";width:34px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--accent),transparent);box-shadow:var(--line-glow)}
.logo-img{height:38px;width:auto;border-radius:8px}
.logo:has(.logo-img)::before{display:none}
.main-nav{display:flex;align-items:center}
.nav-toggle{display:none;background:none;border:1px solid var(--surface-border);color:var(--text);
    border-radius:10px;padding:8px 14px;font-size:18px;cursor:pointer;font-family:inherit}
.nav-list{display:flex;align-items:center;gap:6px}
.nav-list a{color:var(--header-link);font-size:14.5px;font-weight:500;padding:9px 15px;border-radius:999px;transition:.25s}
.nav-list a:hover{color:#fff;background:rgba(232,198,106,.1)}
.header-cta{flex-shrink:0}

/* ---------- هیرو ---------- */
.slider{position:relative}
.cinematic-hero{min-height:100svh;display:flex;align-items:center;overflow:hidden;background:#04060a}
.hero-media{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.34;transform:scale(1.06)}
.hero-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(4,6,10,.62) 0%,rgba(4,6,10,.28) 42%,var(--bg) 100%)}
.hero-canvas{position:absolute;inset:0;width:100%;height:100%}
.hero-inner{position:relative;z-index:2;text-align:center;padding:130px 20px 90px;max-width:900px}
.hero-kicker{display:inline-flex;align-items:center;gap:10px;color:var(--accent);font-size:14px;letter-spacing:.5px;
    border:1px solid rgba(232,198,106,.35);border-radius:999px;padding:8px 20px;margin-bottom:26px;background:rgba(201,162,39,.07)}
.hero-kicker::before,.hero-kicker::after{content:"";width:26px;height:1px;background:linear-gradient(90deg,transparent,var(--accent))}
.hero-kicker::after{background:linear-gradient(90deg,var(--accent),transparent)}
.cinematic-hero h1{font-size:clamp(38px,7.2vw,84px);font-weight:900;line-height:1.5;margin:0 0 18px;
    background:linear-gradient(180deg,#fff 30%,var(--accent) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;
    text-shadow:0 0 90px rgba(201,162,39,.25)}
.cinematic-hero .lead{font-size:clamp(15px,2.4vw,19px);color:#d8d0bc;max-width:640px;margin:0 auto 36px;line-height:2.1}
.hero-ctas{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.hero-scroll{position:absolute;bottom:26px;right:50%;transform:translateX(50%);z-index:2;width:26px;height:44px;
    border:2px solid rgba(232,198,106,.5);border-radius:14px;display:flex;justify-content:center;padding-top:8px}
.hero-scroll span{width:4px;height:9px;border-radius:4px;background:var(--accent);-webkit-animation:scrollDot 1.8s ease-in-out infinite;animation:scrollDot 1.8s ease-in-out infinite}
@-webkit-keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}
@keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}

/* ---------- نوار متحرک (نسخه بهبودیافته ۸٫۹٫۴) ---------- */
.marquee{position:relative;overflow:hidden;padding:20px 0;z-index:2;direction:ltr;
    background:linear-gradient(180deg,rgba(201,162,39,.07),rgba(201,162,39,.02) 50%,rgba(201,162,39,.07));
    border-block:1px solid rgba(232,198,106,.2)}
.marquee::before,.marquee::after{content:"";position:absolute;left:0;right:0;height:1px;
    background:linear-gradient(90deg,transparent,rgba(232,198,106,.55),transparent)}
.marquee::before{top:0}.marquee::after{bottom:0}
.mq-track{display:flex;align-items:center;width:max-content;-webkit-animation:mq 34s linear infinite;animation:mq 34s linear infinite;white-space:nowrap;will-change:transform}
.mq-track:hover{-webkit-animation-play-state:paused;animation-play-state:paused}
.mq-item{display:inline-flex;align-items:center;padding:0 30px;font-size:15px;font-weight:500;color:#ddd6c4;direction:rtl;letter-spacing:.2px}
.mq-item b{color:var(--accent);font-weight:800;text-shadow:0 0 22px rgba(232,198,106,.4)}
.mq-dot{color:var(--accent);font-size:9px;opacity:.75;text-shadow:0 0 14px rgba(232,198,106,.7);flex-shrink:0}
@-webkit-keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}
@keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}

/* ---------- تیتر بخش‌ها ---------- */
.sec-kicker{color:var(--accent);font-size:14px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:12px;justify-content:center}
.sec-kicker::before{content:"";width:34px;height:2px;background:var(--accent);border-radius:2px;box-shadow:var(--line-glow)}
.sec-title{font-size:clamp(26px,4.4vw,42px);font-weight:900;text-align:center;margin-bottom:14px}
.sec-sub{text-align:center;color:var(--muted);max-width:620px;margin:0 auto 46px;font-size:15.5px}

/* ---------- بخش‌ها ---------- */
.features{padding:0;position:relative}
.workshop-showcase{position:relative;padding:100px 0;overflow:hidden;border-radius:28px;margin:40px 20px;
    border:1px solid rgba(232,198,106,.2)}
.workshop-showcase::before{content:"";position:absolute;inset:0;
    background:url('uploads/gallery/workshop_glow.jpg') center/cover;filter:blur(8px) brightness(.35);transform:scale(1.05)}
.workshop-showcase::after{content:"";position:absolute;inset:0;
    background:linear-gradient(180deg,rgba(7,9,13,.6),rgba(7,9,13,.85))}
.workshop-showcase>*{position:relative;z-index:2}
html[data-theme="light"] .workshop-showcase::before{filter:blur(8px) brightness(.55)}
html[data-theme="light"] .workshop-showcase::after{background:linear-gradient(180deg,rgba(250,248,242,.75),rgba(250,248,242,.92))}
@media(max-width:640px){.workshop-showcase{margin:20px 12px;padding:60px 0;border-radius:20px}
.workshop-showcase .container{padding:0 16px;max-width:100%}
.features .cards{grid-template-columns:1fr;gap:14px;margin-inline:0;width:100%}
.features .card{padding:28px 20px;margin:0}}
.features{padding:110px 0 30px}
/* ---------- کارت‌های خلاقانه ویژگی‌ها ---------- */
.features .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:40px}
.features .card{position:relative;background:linear-gradient(160deg,var(--surface),rgba(201,162,39,.04));
    border:1px solid var(--surface-border);border-radius:22px;padding:36px 28px;overflow:hidden;
    transition:transform .45s cubic-bezier(.2,.7,.3,1.2),box-shadow .45s,border-color .45s}
.features .card::before{content:"";position:absolute;top:0;inset-inline:0;height:3px;
    background:linear-gradient(90deg,transparent,var(--accent),transparent);opacity:0;transition:opacity .4s}
.features .card::after{content:"";position:absolute;top:-60px;inset-inline-end:-60px;width:140px;height:140px;border-radius:50%;
    background:radial-gradient(circle,rgba(201,162,39,.18),transparent 70%);transition:transform .5s}
.features .card:hover{transform:translateY(-8px);border-color:rgba(232,198,106,.45);
    box-shadow:0 24px 60px rgba(0,0,0,.4),0 0 40px rgba(201,162,39,.12)}
.features .card:hover::before{opacity:1}
.features .card:hover::after{transform:scale(1.6)}
.features .card-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;
    background:linear-gradient(135deg,rgba(201,162,39,.18),rgba(201,162,39,.06));border:1px solid rgba(232,198,106,.3);color:var(--accent);
    transition:transform .4s,box-shadow .4s}
.features .card-icon svg{width:28px;height:28px}
.features .card:hover .card-icon{transform:scale(1.1) rotate(-6deg);box-shadow:0 8px 24px rgba(201,162,39,.3)}
.features .card h3{font-size:19px;font-weight:800;margin:0 0 12px;color:var(--text)}

.features .card p{font-size:14.5px;color:var(--muted);margin:0;line-height:2}
html[data-theme="light"] .features .card{background:linear-gradient(160deg,#ffffff,rgba(201,162,39,.06));box-shadow:0 4px 20px rgba(120,90,20,.08)}
html[data-theme="light"] .features .card:hover{box-shadow:0 24px 60px rgba(120,90,20,.18),0 0 40px rgba(201,162,39,.15)}
.content-section{padding:70px 0}
.section-body{font-size:15.5px;color:var(--text)}
.content-section h2:empty{display:none}
.section-image{margin:0 0 26px}
.section-image img{border-radius:var(--radius);box-shadow:var(--shadow)}

/* ---------- ویترین محصولات ---------- */
.prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;margin-top:8px}
.p-card{background:linear-gradient(180deg,rgba(255,255,255,.035),rgba(255,255,255,.008));border:1px solid var(--surface-border);
    border-radius:20px;overflow:hidden;transition:transform .35s ease,border-color .35s ease,box-shadow .35s ease;display:flex;flex-direction:column}
.p-card:hover{transform:translateY(-8px);border-color:rgba(232,198,106,.45);box-shadow:0 26px 60px rgba(0,0,0,.55),0 0 40px rgba(201,162,39,.12)}
.p-media{aspect-ratio:4/3;overflow:hidden;background:#0a0d13;position:relative}
.p-media img{width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
.p-card:hover .p-media img{transform:scale(1.07)}
.p-media::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 55%,rgba(4,6,10,.55))}
.p-body{padding:22px 20px 24px;display:flex;flex-direction:column;gap:10px;flex:1}
.p-tag{align-self:flex-start;font-size:12px;font-weight:700;color:var(--accent);background:var(--gold-soft);
    border:1px solid rgba(232,198,106,.3);padding:4px 14px;border-radius:999px}
.p-name{font-size:18px;font-weight:800;margin:0}
.p-desc{font-size:13.5px;color:var(--muted);line-height:1.9;margin:0;flex:1}
.p-link{font-size:14px;font-weight:700;color:var(--accent);display:inline-flex;align-items:center;gap:8px}
.p-link:hover{color:#fff}

/* ---------- مانیفست ---------- */
.manifesto{padding:90px 0;text-align:center;position:relative}
.mani-kicker{color:var(--accent);font-size:14px;font-weight:700;margin-bottom:18px}
html[data-theme="light"] .mani-kicker{color:#8a6d1f}
.mani-text{font-size:clamp(20px,3.6vw,30px);font-weight:700;line-height:2.2;max-width:860px;margin:0 auto}
.mani-text em{font-style:normal;color:var(--accent);text-shadow:0 0 30px rgba(232,198,106,.4)}

/* ---------- بلوک‌های صفحه‌ساز ویژوال (نسخه ۹) ---------- */
.pb-text,.pb-image,.pb-gallery,.pb-cta,.pb-features,.pb-video{padding:50px 0}
.pb-text h2,.pb-image h2,.pb-gallery h2,.pb-cta h2,.pb-features h2,.pb-video h2{text-align:center;color:var(--text);margin:0 0 24px;font-size:clamp(22px,3.5vw,30px)}
.pb-body{max-width:800px;margin:0 auto;line-height:2;color:#c8cdd6}
.pb-image img{max-width:100%;height:auto;border-radius:16px;display:block;margin:0 auto;box-shadow:0 12px 40px rgba(0,0,0,.4)}
.pb-caption{text-align:center;color:#9aa3b2;font-size:14px;margin-top:12px}
.pb-ggrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.pb-ggrid img{width:100%;height:160px;object-fit:cover;border-radius:12px;display:block;transition:transform .3s}
.pb-ggrid a:hover img{transform:scale(1.04)}
.pb-cta{text-align:center;background:linear-gradient(135deg,rgba(201,162,39,.12),rgba(201,162,39,.04));border-top:1px solid rgba(201,162,39,.2);border-bottom:1px solid rgba(201,162,39,.2)}
.pb-cta p{color:#9aa3b2;max-width:600px;margin:0 auto 24px;line-height:2}
.pb-fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.pb-fcard{background:rgba(255,255,255,.03);border:1px solid rgba(201,162,39,.18);border-radius:14px;padding:22px}
.pb-fcard h3{color:#f0d488;margin:0 0 8px;font-size:17px}
.pb-fcard p{color:#9aa3b2;margin:0;font-size:14px;line-height:1.9}
.pb-divider hr{border:none;border-top:1px solid rgba(201,162,39,.25);margin:0}
.pb-video video{max-width:800px;width:100%;border-radius:16px;display:block;margin:0 auto}
.pb-vwrap{position:relative;max-width:800px;margin:0 auto;aspect-ratio:16/9}
.pb-vwrap iframe{position:absolute;inset:0;width:100%;height:100%;border-radius:16px}

/* ---------- ویترین محصولات صفحه اصلی (نسخه ۹) ---------- */
.products-showcase{padding:70px 0;background:linear-gradient(180deg,rgba(201,162,39,.04),transparent 60%)}
.ps-head{text-align:center;margin-bottom:36px}
.ps-head h2{font-size:clamp(24px,4vw,36px);color:#f0d488;margin:0 0 8px}
.ps-head p{color:#9aa3b2;margin:0}
.ps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.ps-card{display:block;background:rgba(255,255,255,.03);border:1px solid rgba(201,162,39,.18);border-radius:16px;overflow:hidden;text-decoration:none;color:inherit;transition:transform .25s,box-shadow .25s,border-color .25s}
.ps-card:hover{transform:translateY(-6px);border-color:rgba(201,162,39,.5);box-shadow:0 18px 40px rgba(0,0,0,.45)}
.ps-img{aspect-ratio:1/1;overflow:hidden;background:#111}
.ps-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.ps-card:hover .ps-img img{transform:scale(1.06)}
.ps-body{padding:16px}
.ps-body h3{margin:0 0 6px;font-size:17px;color:var(--text)}
.ps-cat{font-size:12px;color:#c9a227;background:rgba(201,162,39,.12);padding:2px 10px;border-radius:99px}
.ps-price{margin-top:10px;font-size:18px;font-weight:700;color:#f0d488}
.ps-price small{font-size:12px;font-weight:400;color:#9aa3b2}
.ps-partner{font-size:13px;color:#9aa3b2;margin-top:2px}
.ps-link{display:inline-block;margin-top:12px;font-size:14px;color:#c9a227}
.ps-cta{display:inline-block;margin-top:12px}
.ps-more{text-align:center;margin-top:32px}

/* ---------- نوار گالری ---------- */
.g-wrap{padding:20px 0 90px}
.g-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;grid-auto-flow:dense;padding:10px 20px 24px}
.g-item{border-radius:16px;overflow:hidden;position:relative;border:1px solid var(--surface-border);cursor:pointer;
    transition:transform .4s cubic-bezier(.2,.7,.3,1.2),box-shadow .4s,border-color .4s}
.g-item:nth-child(6n+1){grid-row:span 2}
.g-item:nth-child(6n+1) img{aspect-ratio:3/4}
.g-item img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;transition:transform .6s ease}
.g-item:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 50px rgba(0,0,0,.35),0 0 30px rgba(201,162,39,.15);border-color:rgba(232,198,106,.5);z-index:2}
.g-item:hover img{transform:scale(1.08)}
.g-item figcaption{position:absolute;inset-inline:0;bottom:0;padding:28px 14px 12px;font-size:12.5px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(0,0,0,.82));opacity:0;transform:translateY(10px);transition:opacity .35s,transform .35s}
.g-item:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.g-strip{grid-template-columns:repeat(2,1fr);gap:10px}
.g-item:nth-child(6n+1){grid-row:span 1}
.g-item:nth-child(6n+1) img{aspect-ratio:4/3}}
.g-more{text-align:center;margin-top:26px}

/* ---------- روند کار ---------- */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;counter-reset:step}
.step{background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;padding:38px 28px;position:relative;overflow:hidden;transition:.35s}
.step:hover{border-color:rgba(232,198,106,.4);transform:translateY(-5px)}
.step::before{counter-increment:step;content:"0" counter(step);position:absolute;top:14px;left:22px;font-size:44px;font-weight:900;color:transparent;-webkit-text-stroke:1px rgba(232,198,106,.35)}
.step h3{font-size:18px;margin:0 0 10px;color:var(--accent)}
.step p{font-size:14px;color:var(--muted);margin:0;line-height:2}

/* ---------- آمار ---------- */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:70px}
.stat{text-align:center;padding:30px 12px;border:1px solid var(--surface-border);border-radius:18px;background:rgba(255,255,255,.015)}
.stat b{display:block;font-size:clamp(28px,4vw,40px);font-weight:900;color:var(--accent);text-shadow:0 0 26px rgba(232,198,106,.35)}
.stat span{font-size:13.5px;color:var(--muted)}

/* ---------- دعوت نهایی ---------- */
.final-cta{margin:90px 0 110px;padding:70px 30px;text-align:center;border-radius:28px;position:relative;overflow:hidden;
    background:linear-gradient(135deg,rgba(201,162,39,.12),rgba(201,162,39,.03));border:1px solid rgba(232,198,106,.25)}
.final-cta::before{content:"";position:absolute;top:-70px;right:50%;transform:translateX(50%);width:420px;height:140px;
    background:radial-gradient(closest-side,rgba(232,198,106,.28),transparent);pointer-events:none}
.final-cta h2{font-size:clamp(24px,4.6vw,40px);margin-bottom:12px}
.final-cta p{color:var(--muted);margin-bottom:30px}

/* ---------- فوتر ---------- */
.site-footer{background:var(--footer-bg);border-top:1px solid rgba(232,198,106,.12);padding:70px 0 0;margin-top:40px;color:var(--footer-text)}
.footer-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:36px;padding-bottom:46px}
.f-logo{font-size:22px;font-weight:900;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:10px}
.f-logo::before{content:"";width:30px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--accent),transparent);box-shadow:var(--line-glow)}
.f-brand p{font-size:14px;line-height:2}
.f-title{font-size:15px;font-weight:800;color:var(--text);margin-bottom:16px}
.f-links{display:flex;flex-direction:column;gap:10px}
.f-links a{color:var(--footer-text);font-size:14px;transition:.25s}
.f-links a:hover{color:var(--accent);padding-right:6px}
.f-bottom{border-top:1px solid rgba(255,255,255,.07);padding:22px 0;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px}

/* ---------- صفحات داخلی ---------- */
.page-head{padding:150px 0 40px;text-align:center}
.page-head h1{font-size:clamp(28px,5vw,46px)}
.page-body{font-size:15.5px;color:var(--text);line-height:2.1;max-width:860px;margin:0 auto;padding-bottom:90px}
.page-body img{border-radius:var(--radius)}
/* ---------- گالری تایلی مدرن ---------- */
.page-body .gallery-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;grid-auto-flow:dense}
.page-body .gallery-tiles figure{margin:0;border-radius:14px;overflow:hidden;position:relative;cursor:pointer;
    border:1px solid var(--surface-border);background:var(--surface);transition:transform .35s ease,box-shadow .35s,border-color .35s}
.page-body .gallery-tiles figure:nth-child(6n+1){grid-row:span 2}
.page-body .gallery-tiles figure:nth-child(6n+1) img{aspect-ratio:3/4}
.page-body .gallery-tiles figure img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;transition:transform .5s ease}
.page-body .gallery-tiles figure:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.3),0 0 24px rgba(201,162,39,.12);border-color:rgba(232,198,106,.45);z-index:2}
.page-body .gallery-tiles figure:hover img{transform:scale(1.06)}
.page-body .gallery-tiles figcaption{position:absolute;inset-inline:0;bottom:0;padding:24px 12px 10px;font-size:12px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(0,0,0,.8));opacity:0;transform:translateY(8px);transition:opacity .3s,transform .3s}
.page-body .gallery-tiles figure:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.page-body .gallery-tiles{grid-template-columns:repeat(2,1fr);gap:10px}
.page-body .gallery-tiles figure:nth-child(5n+1){grid-row:span 1}
.page-body .gallery-tiles figure:nth-child(5n+1) img{aspect-ratio:4/3}}
.catalog-nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:0 0 40px}
.cat{padding:9px 22px;border-radius:999px;border:1px solid var(--surface-border);color:var(--muted);font-size:14px;font-weight:600;transition:.25s;background:rgba(255,255,255,.02)}
.cat:hover{color:#fff;border-color:var(--accent)}
.cat.active{background:linear-gradient(135deg,var(--accent),var(--primary));color:#191204;border-color:transparent;font-weight:800}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:24px;padding-bottom:100px}
.product-card{background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;overflow:hidden;transition:.35s;display:flex;flex-direction:column}
.product-card:hover{transform:translateY(-6px);border-color:rgba(232,198,106,.4);box-shadow:var(--shadow)}
.product-card .product-media{aspect-ratio:1/1;overflow:hidden;background:#0a0d13;border-radius:16px 16px 0 0}
.product-card .product-media img{width:100%;height:100%;object-fit:cover}
.product-card-body{padding:22px;display:flex;flex-direction:column;gap:10px;flex:1}
.product-card-body h3{margin:0;font-size:17px}
.price{color:var(--accent);font-weight:800;font-size:16px}
.product-desc{font-size:13.5px;color:var(--muted);line-height:1.9;flex:1}
.product-breadcrumb{font-size:13.5px;color:var(--muted);margin-bottom:18px}
.product-breadcrumb a{color:var(--accent)}
.product-layout{display:grid;grid-template-columns:1fr 1fr;gap:44px;padding:150px 0 90px;align-items:start}
.product-media{aspect-ratio:1/1;overflow:hidden;border-radius:20px}
.product-media img{width:100%;height:100%;object-fit:cover;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--surface-border)}
.product-info h1{font-size:clamp(24px,4vw,36px)}
.product-price-line{display:flex;align-items:center;gap:14px;margin:18px 0;padding:16px 20px;background:var(--gold-soft);
    border:1px solid rgba(232,198,106,.3);border-radius:14px}
.partner-line{font-size:13.5px;color:var(--muted);background:rgba(255,255,255,.03);border:1px dashed var(--surface-border);
    padding:10px 16px;border-radius:12px;margin-top:12px}
.estimator{background:var(--surface);border:1px solid var(--surface-border);border-radius:14px;padding:18px;margin-top:18px}
.estimator h3{margin-top:0;font-size:17px}
.est-option{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--surface-border);font-size:14px}
.est-option:last-child{border-bottom:0}
.estimator-result{margin-top:16px;font-size:17px;font-weight:800;color:var(--accent)}

/* ---------- فرم‌ها ---------- */
.field{margin-bottom:18px}
label{display:block;font-size:14px;font-weight:600;margin-bottom:8px;color:var(--text)}
input[type=text],input[type=tel],input[type=number],input[type=email],input[type=password],textarea,select{
    width:100%;background:var(--input-bg);border:1px solid var(--input-border);color:var(--text);
    border-radius:12px;padding:12px 16px;font-family:inherit;font-size:15px;transition:border-color .25s,box-shadow .25s}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(232,198,106,.15)}
textarea{min-height:120px;resize:vertical}
.hp-field{position:absolute;right:-9999px;opacity:0;height:0;overflow:hidden}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14.5px;border:1px solid}
.alert-ok,.alert.ok{background:var(--alert-ok-bg);color:var(--alert-ok-text);border-color:rgba(255,255,255,.08)}
.alert-error,.alert.error{background:var(--alert-error-bg);color:var(--alert-error-text);border-color:rgba(255,255,255,.08)}
.empty-state{text-align:center;padding:140px 20px 100px}
.empty-state h1{font-size:30px;margin-bottom:12px}

/* ---------- انیمیشن ظهور ---------- */
.rv{opacity:0;-webkit-transform:translateY(28px);transform:translateY(28px);-webkit-transition:opacity .8s ease,-webkit-transform .8s ease;transition:opacity .8s ease,transform .8s ease}
.rv.in{opacity:1;-webkit-transform:none;transform:none}

/* ---------- ریسپانسیو ---------- */
@media (max-width:1024px){
    .prod-grid{grid-template-columns:repeat(2,1fr)}
    .footer-grid{grid-template-columns:1fr 1fr}
    .product-layout{grid-template-columns:1fr;gap:28px}
}
@media (max-width:768px){
    .nav-toggle{display:block}
    .nav-list{position:fixed;top:70px;right:12px;left:12px;flex-direction:column;align-items:stretch;gap:4px;
        background:rgba(10,13,19,.97);border:1px solid var(--surface-border);border-radius:18px;padding:14px;
        box-shadow:var(--shadow);display:none;backdrop-filter:blur(16px)}
    .nav-list.open{display:flex}
    .nav-list a{padding:13px 18px;font-size:15px}
    .header-cta{display:none}
    .steps{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr)}
    .hero-ctas .btn{width:100%;max-width:340px}
    .footer-grid{grid-template-columns:1fr}
    .f-bottom{justify-content:center;text-align:center}
    .final-cta{margin:60px 0 80px;padding:50px 22px}
    .page-head,.product-layout{padding-top:120px}
}
@media (max-width:520px){
    .prod-grid{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr);gap:12px}
}

/* ---------- حرکت کم ---------- */
@media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
    .rv{opacity:1;transform:none}
    .hero-canvas{display:none}
    .mq-track{animation:none}
}

/* ---------- چاپ ---------- */
@media print{
    .site-header,.hero-canvas,.hero-scroll,.marquee,.final-cta,.g-wrap{display:none}
    body{background:#fff;color:#000}
    .cinematic-hero{min-height:auto}
    .cinematic-hero h1{color:#000;-webkit-text-fill-color:#000;background:none}
}

/* ============================================================
   تم روشن (نسخه ۹٫۰٫۴) — با دکمه 🌓 در هدر بین تیره و روشن جابه‌جا می‌شود
   ============================================================ */
html[data-theme="light"]{
    --bg:#faf8f2;
    --bg-soft:#f4efe3;
    --surface:#ffffff;
    --surface-border:#e6dcc4;
    --text:#1a1510;
    --muted:#4a4436;
    --gold-soft:rgba(201,162,39,.13);
    --header-bg:rgba(250,248,242,.88);
    --header-text:#1a1510;
    --header-link:#3a3428;
    --footer-bg:#e8e0cd;
    --footer-text:#3a3428;
    --input-bg:#ffffff;
    --input-border:#d9cfb4;
    --card:#ffffff;
    --border:#e6dcc4;
    --alert-ok-bg:#e2f3e7;
    --alert-ok-text:#1c5c2e;
    --alert-error-bg:#fbe3e3;
    --alert-error-text:#8f1f1f;
    --shadow:0 24px 60px rgba(120,90,20,.14);
    --line-glow:0 0 24px rgba(201,162,39,.4),0 0 80px rgba(201,162,39,.18);
}
html[data-theme="light"] body::before{
    background:radial-gradient(1100px 520px at 85% -8%,rgba(201,162,39,.14),transparent 60%),
               radial-gradient(900px 500px at 8% 22%,rgba(201,162,39,.08),transparent 60%),
               radial-gradient(700px 700px at 50% 110%,rgba(201,162,39,.07),transparent 60%)}
html[data-theme="light"] .cinematic-hero .hero-shade{
    background:linear-gradient(180deg,rgba(250,248,242,.55) 0%,rgba(250,248,242,.25) 45%,var(--bg) 100%)}
html[data-theme="light"] .cinematic-hero h1{color:#2a2417}
html[data-theme="light"] .hero-kicker{color:#8a6d1f;background:rgba(201,162,39,.16);border-color:rgba(201,162,39,.35)}
html[data-theme="light"] .hero-lead{color:#3a3428}
html[data-theme="light"] .cinematic-hero .lead{color:#2a2417}
html[data-theme="light"] .btn-ghost{border-color:#c9a227;color:#7a5f14}
html[data-theme="light"] .btn-ghost:hover{background:rgba(201,162,39,.14)}
html[data-theme="light"] .marquee{background:linear-gradient(180deg,rgba(201,162,39,.1),rgba(201,162,39,.05))}
html[data-theme="light"] .mq-item{color:#5a4d2e}
html[data-theme="light"] .section-head h2{color:#2a2417}
html[data-theme="light"] .section-head p{color:#4a4436}
html[data-theme="light"] .ps-card{background:#fff;box-shadow:0 8px 28px rgba(120,90,20,.1)}
html[data-theme="light"] .ps-card:hover{box-shadow:0 18px 40px rgba(120,90,20,.18)}
html[data-theme="light"] .ps-body h3{color:#221d12}
html[data-theme="light"] .ps-price{color:#8a6d1f}
html[data-theme="light"] .ps-cat{background:rgba(201,162,39,.16);color:#7a5f14}
html[data-theme="light"] .manifesto{background:linear-gradient(135deg,#f4efe3,#faf8f2)}
html[data-theme="light"] .manifesto p{color:#3a3423}
html[data-theme="light"] .g-card{background:#fff}
html[data-theme="light"] .g-card figcaption{background:#fff;color:#3a3423}
html[data-theme="light"] .step{background:#fff;border-color:var(--surface-border)}
html[data-theme="light"] .step h3{color:#2a2417}
html[data-theme="light"] .step p{color:#4a4436}
html[data-theme="light"] .stat-num{color:#8a6d1f}
html[data-theme="light"] .stat-label{color:#4a4436}
html[data-theme="light"] .final-cta{background:linear-gradient(135deg,#2a2417,#4a3d1c)}
html[data-theme="light"] .pb-fcard{background:#fff;box-shadow:0 8px 24px rgba(120,90,20,.08)}
html[data-theme="light"] .pb-fcard h3{color:#8a6d1f}
html[data-theme="light"] .gallery-card{background:#fff}
html[data-theme="light"] input,html[data-theme="light"] select,html[data-theme="light"] textarea{color:#1a1510}
html[data-theme="light"] .nav-list a{color:#3a3428}
html[data-theme="light"] .nav-list a:hover{color:#1a1510;background:rgba(201,162,39,.16)}
@media (max-width:768px){
    html[data-theme="light"] .nav-list{background:rgba(250,248,242,.98);border-color:#e6dcc4;box-shadow:0 24px 60px rgba(120,90,20,.2)}
    html[data-theme="light"] .theme-toggle{background:rgba(201,162,39,.14);border-color:#e6dcc4}
}
html[data-theme="light"] .product-card{background:#fff}
html[data-theme="light"] .product-card h3 a{color:#221d12}
html[data-theme="light"] .catalog-nav a{background:#fff;border-color:var(--surface-border);color:#5a5344}
html[data-theme="light"] .catalog-nav a.active{background:#c9a227;color:#fff}
/* ===== فیلدهای سفارشی فرم سفارش (نسخه ۹٫۱) ===== */
.so-custom-fields{margin:14px 0;padding:14px;border:1px dashed var(--surface-border);border-radius:12px;background:rgba(201,162,39,.04)}
.so-custom-fields h4{margin:0 0 10px;font-size:14px;color:var(--accent)}
/* ===== گالری محصول (نسخه ۹٫۱) ===== */
.product-gallery{margin:28px 0}
.product-gallery h3{margin:0 0 14px;font-size:18px}
.pg-public-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.pg-public-item{margin:0;border-radius:14px;overflow:hidden;background:var(--card,#fff);border:1px solid var(--border,#e5e7eb);cursor:zoom-in;transition:transform .25s}
.pg-public-item:hover{transform:scale(1.03)}
.pg-public-item img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}
.pg-public-item figcaption{padding:8px 10px;font-size:12px;color:var(--muted,#6b7280)}
/* ===== فرم ثبت‌نام همکار (نسخه ۹٫۱) ===== */
.partner-form{max-width:640px;margin:24px auto;background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 4px 24px rgba(0,0,0,.06)}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.pf-field{display:flex;flex-direction:column;gap:6px}
.pf-field.pf-full{grid-column:1/-1}
.pf-field label{font-size:13px;font-weight:600;color:var(--text)}
.pf-field input,.pf-field textarea{border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;font-family:inherit;background:var(--bg);color:var(--text);width:100%;box-sizing:border-box}
.pf-field input:focus,.pf-field textarea:focus{outline:2px solid #c9a227;outline-offset:1px;border-color:#c9a227}
.pf-field input::placeholder,.pf-field textarea::placeholder{color:var(--muted);opacity:.75}
.pf-field .muted{font-weight:400;font-size:12px}
@media(max-width:560px){.pf-grid{grid-template-columns:1fr}.partner-form{padding:18px}}

CSS;
}

/** CSS قالب «مدرن روشن» (نسخه ۹٫۳٫۰) — روشن، مینیمال و امروزی */
function theme_modern_css(): string
{
    return <<<'CSS'
/* ============================================================
   تم «مدرن روشن» لاینرلایت — نسخه ۹٫۳٫۰
   روشن، مینیمال و امروزی؛ فضای سفید فراوان، لهجه فیروزه‌ای و طلایی.
   ساختار: توکن‌ها ← پایه ← هدر ← هیرو ← دکمه ← بخش‌ها ← ویترین محصولات
   ← گالری ← روند ← آمار ← فوتر ← صفحات داخلی ← فرم‌ها ← موبایل ← حرکت کم ← چاپ
   ============================================================ */

/* ---------- توکن‌ها ---------- */
:root{
    --primary:#0ea5a4;
    --primary-dark:#0b7f7e;
    --accent:#c9a227;
    --accent-dark:#a8841c;
    --font-family:"Vazirmatn",Tahoma,"Segoe UI",Arial,sans-serif;
    --container-width:1200px;
    --radius:16px;
    --bg:#ffffff;
    --bg-soft:#f6f8f9;
    --surface:#ffffff;
    --surface-border:#e6ebef;
    --text:#17222e;
    --muted:#5f7185;
    --gold-soft:rgba(201,162,39,.12);
    --header-bg:rgba(255,255,255,.86);
    --header-text:#17222e;
    --header-link:#46586c;
    --footer-bg:#0e1822;
    --footer-text:#a9b8c7;
    --input-bg:#ffffff;
    --card:#ffffff;
    --border:#e6ebef;
    --input-border:#d4dce2;
    --alert-ok-bg:#e4f6ef;
    --alert-ok-text:#0b6b4f;
    --alert-error-bg:#fdecec;
    --alert-error-text:#b4232a;
    --shadow:0 20px 50px rgba(18,45,60,.10);
    --line-glow:0 8px 28px rgba(14,165,164,.35);
}

/* ---------- پایه ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font-family);background:var(--bg);color:var(--text);line-height:2;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
    background:radial-gradient(900px 480px at 85% -6%,rgba(14,165,164,.08),transparent 60%),
               radial-gradient(800px 480px at 10% 20%,rgba(201,162,39,.07),transparent 60%)}
img{max-width:100%;height:auto;display:block}
a{color:var(--primary);text-decoration:none}
a:hover{color:var(--primary-dark)}
h1,h2,h3{line-height:1.6;margin:0 0 .6em;font-weight:800;color:var(--text)}
p{margin:0 0 1em}
.container{width:min(var(--container-width),100% - 40px);margin-inline:auto}
.skip-link{position:absolute;top:-60px;right:16px;z-index:200;background:var(--primary);color:#fff;padding:8px 16px;border-radius:8px;transition:top .2s}
.skip-link:focus{top:12px;color:#fff}
.muted{color:var(--muted)}
/* دکمه تغییر تم */
.theme-toggle{background:#fff;border:1px solid var(--surface-border);border-radius:99px;width:44px;height:44px;
    font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;
    transition:transform .2s,box-shadow .2s;box-shadow:0 4px 14px rgba(18,45,60,.08)}
.theme-toggle:hover{transform:scale(1.08)}
main{display:block}
section{scroll-margin-top:84px}

/* ---------- دکمه‌ها ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 34px;border-radius:999px;
    font-family:inherit;font-weight:700;font-size:15px;cursor:pointer;border:1px solid transparent;
    transition:transform .25s ease,box-shadow .25s ease,background .25s ease,color .25s ease;white-space:nowrap}
.btn:active{transform:scale(.97)}
.btn-gold{background:linear-gradient(135deg,#e8c66a,#c9a227);color:#2a2103;box-shadow:0 10px 26px rgba(201,162,39,.35)}
.btn-gold:hover{transform:translateY(-2px);color:#000;box-shadow:0 14px 34px rgba(201,162,39,.45)}
.btn-ghost{background:#fff;border-color:#d4dce2;color:var(--text);box-shadow:0 4px 14px rgba(18,45,60,.06)}
.btn-ghost:hover{border-color:var(--primary);color:var(--primary-dark);transform:translateY(-2px)}
.btn-light{background:var(--primary);border-color:transparent;color:#fff}
.btn-light:hover{background:var(--primary-dark);color:#fff}
.btn-sm{padding:9px 22px;font-size:13.5px}

/* ---------- هدر ---------- */
.site-header{position:fixed;top:0;right:0;left:0;z-index:100;background:var(--header-bg);
    backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid var(--surface-border)}
.header-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:70px;padding-block:10px}
.logo{font-weight:900;font-size:20px;color:var(--header-text);display:flex;align-items:center;gap:10px}
.logo::before{content:"";width:34px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--primary),transparent)}
.logo-img{height:38px;width:auto;border-radius:8px}
.logo:has(.logo-img)::before{display:none}
.main-nav{display:flex;align-items:center}
.nav-toggle{display:none;background:#fff;border:1px solid var(--surface-border);color:var(--text);
    border-radius:10px;padding:8px 14px;font-size:18px;cursor:pointer;font-family:inherit}
.nav-list{display:flex;align-items:center;gap:6px}
.nav-list a{color:var(--header-link);font-size:14.5px;font-weight:500;padding:9px 15px;border-radius:999px;transition:.25s}
.nav-list a:hover{color:var(--primary-dark);background:rgba(14,165,164,.08)}
.header-cta{flex-shrink:0}

/* ---------- هیرو ---------- */
.slider{position:relative}
.cinematic-hero{min-height:100svh;display:flex;align-items:center;overflow:hidden;
    background:linear-gradient(180deg,#eef5f6 0%,#ffffff 72%)}
.hero-media{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.14;transform:scale(1.06)}
.hero-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(255,255,255,.3) 0%,rgba(255,255,255,0) 45%,var(--bg) 100%)}
.hero-canvas{position:absolute;inset:0;width:100%;height:100%;opacity:.45}
.hero-inner{position:relative;z-index:2;text-align:center;padding:130px 20px 90px;max-width:900px}
.hero-kicker{display:inline-flex;align-items:center;gap:10px;color:var(--primary-dark);font-size:14px;letter-spacing:.5px;
    border:1px solid rgba(14,165,164,.35);border-radius:999px;padding:8px 20px;margin-bottom:26px;background:rgba(14,165,164,.07)}
.hero-kicker::before,.hero-kicker::after{content:"";width:26px;height:1px;background:linear-gradient(90deg,transparent,var(--primary))}
.hero-kicker::after{background:linear-gradient(90deg,var(--primary),transparent)}
.cinematic-hero h1{font-size:clamp(38px,7.2vw,84px);font-weight:900;line-height:1.5;margin:0 0 18px;color:#12202b}
.cinematic-hero .lead{font-size:clamp(15px,2.4vw,19px);color:var(--muted);max-width:640px;margin:0 auto 36px;line-height:2.1}
.hero-ctas{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.hero-scroll{position:absolute;bottom:26px;right:50%;transform:translateX(50%);z-index:2;width:26px;height:44px;
    border:2px solid rgba(14,165,164,.5);border-radius:14px;display:flex;justify-content:center;padding-top:8px}
.hero-scroll span{width:4px;height:9px;border-radius:4px;background:var(--primary);-webkit-animation:scrollDot 1.8s ease-in-out infinite;animation:scrollDot 1.8s ease-in-out infinite}
@-webkit-keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}
@keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}

/* ---------- نوار متحرک ---------- */
.marquee{position:relative;overflow:hidden;padding:18px 0;z-index:2;direction:ltr;
    background:#f2f7f8;border-block:1px solid #e2eaee}
.mq-track{display:flex;align-items:center;width:max-content;-webkit-animation:mq 34s linear infinite;animation:mq 34s linear infinite;white-space:nowrap;will-change:transform}
.mq-track:hover{-webkit-animation-play-state:paused;animation-play-state:paused}
.mq-item{display:inline-flex;align-items:center;padding:0 30px;font-size:15px;font-weight:500;color:#42566a;direction:rtl;letter-spacing:.2px}
.mq-item b{color:var(--primary-dark);font-weight:800}
.mq-dot{color:var(--primary);font-size:9px;opacity:.7;flex-shrink:0}
@-webkit-keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}
@keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}

/* ---------- تیتر بخش‌ها ---------- */
.sec-kicker{color:var(--primary-dark);font-size:14px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:12px;justify-content:center}
.sec-kicker::before{content:"";width:34px;height:2px;background:var(--primary);border-radius:2px}
.sec-title{font-size:clamp(26px,4.4vw,42px);font-weight:900;text-align:center;margin-bottom:14px;color:var(--text)}
.sec-sub{text-align:center;color:var(--muted);max-width:620px;margin:0 auto 46px;font-size:15.5px}

/* ---------- بخش‌ها ---------- */
.features{padding:110px 0 30px;position:relative}
.workshop-showcase{position:relative;padding:100px 0;overflow:hidden;border-radius:28px;margin:40px 20px;
    border:1px solid var(--surface-border);box-shadow:var(--shadow)}
.workshop-showcase::before{content:"";position:absolute;inset:0;
    background:url('uploads/gallery/workshop_glow.jpg') center/cover;filter:blur(8px) brightness(.85);transform:scale(1.05);opacity:.35}
.workshop-showcase::after{content:"";position:absolute;inset:0;
    background:linear-gradient(180deg,rgba(255,255,255,.55),rgba(246,248,249,.92))}
.workshop-showcase>*{position:relative;z-index:2}
@media(max-width:640px){.workshop-showcase{margin:20px 12px;padding:60px 0;border-radius:20px}
.workshop-showcase .container{padding:0 16px;max-width:100%}
.features .cards{grid-template-columns:1fr;gap:14px;margin-inline:0;width:100%}
.features .card{padding:28px 20px;margin:0}}
/* ---------- کارت‌های ویژگی‌ها ---------- */
.features .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:40px}
.features .card{position:relative;background:var(--surface);border:1px solid var(--surface-border);border-radius:22px;
    padding:36px 28px;overflow:hidden;box-shadow:0 6px 24px rgba(18,45,60,.06);
    transition:transform .35s ease,box-shadow .35s ease,border-color .35s ease}
.features .card::before{content:"";position:absolute;top:0;inset-inline:0;height:3px;
    background:linear-gradient(90deg,transparent,var(--primary),transparent);opacity:0;transition:opacity .4s}
.features .card:hover{transform:translateY(-8px);border-color:rgba(14,165,164,.4);
    box-shadow:0 24px 50px rgba(18,45,60,.12)}
.features .card:hover::before{opacity:1}
.features .card-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;
    background:linear-gradient(135deg,rgba(14,165,164,.14),rgba(14,165,164,.05));border:1px solid rgba(14,165,164,.25);color:var(--primary-dark);
    transition:transform .4s}
.features .card-icon svg{width:28px;height:28px}
.features .card:hover .card-icon{transform:scale(1.08)}
.features .card h3{font-size:19px;font-weight:800;margin:0 0 12px;color:var(--text)}
.features .card p{font-size:14.5px;color:var(--muted);margin:0;line-height:2}
.content-section{padding:70px 0}
.section-body{font-size:15.5px;color:var(--text)}
.content-section h2:empty{display:none}
.section-image{margin:0 0 26px}
.section-image img{border-radius:var(--radius);box-shadow:var(--shadow)}

/* ---------- ویترین محصولات ---------- */
.prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;margin-top:8px}
.p-card{background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;overflow:hidden;
    transition:transform .35s ease,border-color .35s ease,box-shadow .35s ease;display:flex;flex-direction:column;
    box-shadow:0 6px 22px rgba(18,45,60,.05)}
.p-card:hover{transform:translateY(-8px);border-color:rgba(14,165,164,.4);box-shadow:0 26px 50px rgba(18,45,60,.12)}
.p-media{aspect-ratio:4/3;overflow:hidden;background:#eef2f4;position:relative}
.p-media img{width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
.p-card:hover .p-media img{transform:scale(1.07)}
.p-body{padding:22px 20px 24px;display:flex;flex-direction:column;gap:10px;flex:1}
.p-tag{align-self:flex-start;font-size:12px;font-weight:700;color:var(--primary-dark);background:rgba(14,165,164,.09);
    border:1px solid rgba(14,165,164,.28);padding:4px 14px;border-radius:999px}
.p-name{font-size:18px;font-weight:800;margin:0;color:var(--text)}
.p-desc{font-size:13.5px;color:var(--muted);line-height:1.9;margin:0;flex:1}
.p-link{font-size:14px;font-weight:700;color:var(--primary-dark);display:inline-flex;align-items:center;gap:8px}
.p-link:hover{color:var(--primary)}

/* ---------- مانیفست ---------- */
.manifesto{padding:90px 0;text-align:center;position:relative;background:linear-gradient(180deg,#f6f8f9,#ffffff)}
.mani-kicker{color:var(--primary-dark);font-size:14px;font-weight:700;margin-bottom:18px}
.mani-text{font-size:clamp(20px,3.6vw,30px);font-weight:700;line-height:2.2;max-width:860px;margin:0 auto;color:var(--text)}
.mani-text em{font-style:normal;color:var(--primary-dark)}

/* ---------- بلوک‌های صفحه‌ساز ---------- */
.pb-text,.pb-image,.pb-gallery,.pb-cta,.pb-features,.pb-video{padding:50px 0}
.pb-text h2,.pb-image h2,.pb-gallery h2,.pb-cta h2,.pb-features h2,.pb-video h2{text-align:center;color:var(--text);margin:0 0 24px;font-size:clamp(22px,3.5vw,30px)}
.pb-body{max-width:800px;margin:0 auto;line-height:2;color:var(--text)}
.pb-image img{max-width:100%;height:auto;border-radius:16px;display:block;margin:0 auto;box-shadow:0 12px 40px rgba(18,45,60,.12)}
.pb-caption{text-align:center;color:var(--muted);font-size:14px;margin-top:12px}
.pb-ggrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.pb-ggrid img{width:100%;height:160px;object-fit:cover;border-radius:12px;display:block;transition:transform .3s}
.pb-ggrid a:hover img{transform:scale(1.04)}
.pb-cta{text-align:center;background:linear-gradient(135deg,rgba(14,165,164,.09),rgba(14,165,164,.03));border-top:1px solid rgba(14,165,164,.2);border-bottom:1px solid rgba(14,165,164,.2)}
.pb-cta p{color:var(--muted);max-width:600px;margin:0 auto 24px;line-height:2}
.pb-fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.pb-fcard{background:#fff;border:1px solid var(--surface-border);border-radius:14px;padding:22px;box-shadow:0 6px 20px rgba(18,45,60,.05)}
.pb-fcard h3{color:var(--primary-dark);margin:0 0 8px;font-size:17px}
.pb-fcard p{color:var(--muted);margin:0;font-size:14px;line-height:1.9}
.pb-divider hr{border:none;border-top:1px solid var(--surface-border);margin:0}
.pb-video video{max-width:800px;width:100%;border-radius:16px;display:block;margin:0 auto}
.pb-vwrap{position:relative;max-width:800px;margin:0 auto;aspect-ratio:16/9}
.pb-vwrap iframe{position:absolute;inset:0;width:100%;height:100%;border-radius:16px}

/* ---------- ویترین محصولات صفحه اصلی ---------- */
.products-showcase{padding:70px 0;background:linear-gradient(180deg,rgba(14,165,164,.05),transparent 60%)}
.ps-head{text-align:center;margin-bottom:36px}
.ps-head h2{font-size:clamp(24px,4vw,36px);color:var(--text);margin:0 0 8px}
.ps-head p{color:var(--muted);margin:0}
.ps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.ps-card{display:block;background:#fff;border:1px solid var(--surface-border);border-radius:16px;overflow:hidden;
    text-decoration:none;color:inherit;transition:transform .25s,box-shadow .25s,border-color .25s;
    box-shadow:0 6px 22px rgba(18,45,60,.05)}
.ps-card:hover{transform:translateY(-6px);border-color:rgba(14,165,164,.45);box-shadow:0 18px 40px rgba(18,45,60,.12)}
.ps-img{aspect-ratio:1/1;overflow:hidden;background:#eef2f4}
.ps-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.ps-card:hover .ps-img img{transform:scale(1.06)}
.ps-body{padding:16px}
.ps-body h3{margin:0 0 6px;font-size:17px;color:var(--text)}
.ps-cat{font-size:12px;color:var(--primary-dark);background:rgba(14,165,164,.09);padding:2px 10px;border-radius:99px}
.ps-price{margin-top:10px;font-size:18px;font-weight:700;color:var(--text)}
.ps-price small{font-size:12px;font-weight:400;color:var(--muted)}
.ps-partner{font-size:13px;color:var(--muted);margin-top:2px}
.ps-link{display:inline-block;margin-top:12px;font-size:14px;color:var(--primary-dark)}
.ps-cta{display:inline-block;margin-top:12px}
.ps-more{text-align:center;margin-top:32px}

/* ---------- نوار گالری ---------- */
.g-wrap{padding:20px 0 90px}
.g-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;grid-auto-flow:dense;padding:10px 20px 24px}
.g-item{border-radius:16px;overflow:hidden;position:relative;border:1px solid var(--surface-border);cursor:pointer;
    box-shadow:0 6px 20px rgba(18,45,60,.06);
    transition:transform .4s cubic-bezier(.2,.7,.3,1.2),box-shadow .4s,border-color .4s}
.g-item:nth-child(6n+1){grid-row:span 2}
.g-item:nth-child(6n+1) img{aspect-ratio:3/4}
.g-item img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;transition:transform .6s ease}
.g-item:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 44px rgba(18,45,60,.14);border-color:rgba(14,165,164,.5);z-index:2}
.g-item:hover img{transform:scale(1.08)}
.g-item figcaption{position:absolute;inset-inline:0;bottom:0;padding:28px 14px 12px;font-size:12.5px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(10,25,35,.82));opacity:0;transform:translateY(10px);transition:opacity .35s,transform .35s}
.g-item:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.g-strip{grid-template-columns:repeat(2,1fr);gap:10px}
.g-item:nth-child(6n+1){grid-row:span 1}
.g-item:nth-child(6n+1) img{aspect-ratio:4/3}}
.g-more{text-align:center;margin-top:26px}

/* ---------- روند کار ---------- */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;counter-reset:step}
.step{background:#fff;border:1px solid var(--surface-border);border-radius:20px;padding:38px 28px;position:relative;
    overflow:hidden;box-shadow:0 6px 22px rgba(18,45,60,.05);transition:.35s}
.step:hover{border-color:rgba(14,165,164,.4);transform:translateY(-5px);box-shadow:0 18px 40px rgba(18,45,60,.1)}
.step::before{counter-increment:step;content:"0" counter(step);position:absolute;top:14px;left:22px;font-size:44px;
    font-weight:900;color:transparent;-webkit-text-stroke:1px rgba(14,165,164,.45)}
.step h3{font-size:18px;margin:0 0 10px;color:var(--primary-dark)}
.step p{font-size:14px;color:var(--muted);margin:0;line-height:2}

/* ---------- آمار ---------- */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:70px}
.stat{text-align:center;padding:30px 12px;border:1px solid var(--surface-border);border-radius:18px;background:#fff;
    box-shadow:0 6px 22px rgba(18,45,60,.05)}
.stat b{display:block;font-size:clamp(28px,4vw,40px);font-weight:900;color:var(--primary-dark)}
.stat span{font-size:13.5px;color:var(--muted)}

/* ---------- دعوت نهایی ---------- */
.final-cta{margin:90px 0 110px;padding:70px 30px;text-align:center;border-radius:28px;position:relative;overflow:hidden;
    background:linear-gradient(135deg,#0b7f7e,#0ea5a4);color:#fff;box-shadow:0 24px 60px rgba(14,165,164,.3)}
.final-cta h2{font-size:clamp(24px,4.6vw,40px);margin-bottom:12px;color:#fff}
.final-cta p{color:rgba(255,255,255,.85);margin-bottom:30px}
.final-cta .btn-gold{box-shadow:0 10px 26px rgba(0,0,0,.2)}

/* ---------- فوتر ---------- */
.site-footer{background:var(--footer-bg);padding:70px 0 0;margin-top:40px;color:var(--footer-text)}
.footer-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:36px;padding-bottom:46px}
.f-logo{font-size:22px;font-weight:900;color:#fff;margin-bottom:12px;display:flex;align-items:center;gap:10px}
.f-logo::before{content:"";width:30px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--primary),transparent)}
.f-brand p{font-size:14px;line-height:2;color:var(--footer-text)}
.f-title{font-size:15px;font-weight:800;color:#fff;margin-bottom:16px}
.f-links{display:flex;flex-direction:column;gap:10px}
.f-links a{color:var(--footer-text);font-size:14px;transition:.25s}
.f-links a:hover{color:#fff;padding-right:6px}
.f-bottom{border-top:1px solid rgba(255,255,255,.08);padding:22px 0;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px}

/* ---------- صفحات داخلی ---------- */
.page-head{padding:150px 0 40px;text-align:center;background:linear-gradient(180deg,#eef5f6,transparent)}
.page-head h1{font-size:clamp(28px,5vw,46px);color:var(--text)}
.page-body{font-size:15.5px;color:var(--text);line-height:2.1;max-width:860px;margin:0 auto;padding-bottom:90px}
.page-body img{border-radius:var(--radius)}
.page-body .gallery-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;grid-auto-flow:dense}
.page-body .gallery-tiles figure{margin:0;border-radius:14px;overflow:hidden;position:relative;cursor:pointer;
    border:1px solid var(--surface-border);background:#fff;box-shadow:0 6px 18px rgba(18,45,60,.05);
    transition:transform .35s ease,box-shadow .35s,border-color .35s}
.page-body .gallery-tiles figure:nth-child(6n+1){grid-row:span 2}
.page-body .gallery-tiles figure:nth-child(6n+1) img{aspect-ratio:3/4}
.page-body .gallery-tiles figure img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;transition:transform .5s ease}
.page-body .gallery-tiles figure:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(18,45,60,.12);border-color:rgba(14,165,164,.45);z-index:2}
.page-body .gallery-tiles figure:hover img{transform:scale(1.06)}
.page-body .gallery-tiles figcaption{position:absolute;inset-inline:0;bottom:0;padding:24px 12px 10px;font-size:12px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(10,25,35,.8));opacity:0;transform:translateY(8px);transition:opacity .3s,transform .3s}
.page-body .gallery-tiles figure:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.page-body .gallery-tiles{grid-template-columns:repeat(2,1fr);gap:10px}
.page-body .gallery-tiles figure:nth-child(5n+1){grid-row:span 1}
.page-body .gallery-tiles figure:nth-child(5n+1) img{aspect-ratio:4/3}}
.catalog-nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:0 0 40px}
.cat{padding:9px 22px;border-radius:999px;border:1px solid var(--surface-border);color:var(--muted);font-size:14px;
    font-weight:600;transition:.25s;background:#fff}
.cat:hover{color:var(--primary-dark);border-color:var(--primary)}
.cat.active{background:var(--primary);color:#fff;border-color:transparent;font-weight:800}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:24px;padding-bottom:100px}
.product-card{background:#fff;border:1px solid var(--surface-border);border-radius:20px;overflow:hidden;transition:.35s;
    display:flex;flex-direction:column;box-shadow:0 6px 22px rgba(18,45,60,.05)}
.product-card:hover{transform:translateY(-6px);border-color:rgba(14,165,164,.4);box-shadow:var(--shadow)}
.product-card .product-media{aspect-ratio:1/1;overflow:hidden;background:#eef2f4;border-radius:16px 16px 0 0}
.product-card .product-media img{width:100%;height:100%;object-fit:cover}
.product-card-body{padding:22px;display:flex;flex-direction:column;gap:10px;flex:1}
.product-card-body h3{margin:0;font-size:17px;color:var(--text)}
.price{color:var(--primary-dark);font-weight:800;font-size:16px}
.product-desc{font-size:13.5px;color:var(--muted);line-height:1.9;flex:1}
.product-breadcrumb{font-size:13.5px;color:var(--muted);margin-bottom:18px}
.product-breadcrumb a{color:var(--primary-dark)}
.product-layout{display:grid;grid-template-columns:1fr 1fr;gap:44px;padding:150px 0 90px;align-items:start}
.product-media{aspect-ratio:1/1;overflow:hidden;border-radius:20px}
.product-media img{width:100%;height:100%;object-fit:cover;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--surface-border)}
.product-info h1{font-size:clamp(24px,4vw,36px);color:var(--text)}
.product-price-line{display:flex;align-items:center;gap:14px;margin:18px 0;padding:16px 20px;background:var(--gold-soft);
    border:1px solid rgba(201,162,39,.35);border-radius:14px;color:var(--text)}
.partner-line{font-size:13.5px;color:var(--muted);background:var(--bg-soft);border:1px dashed var(--surface-border);
    padding:10px 16px;border-radius:12px;margin-top:12px}
.estimator{background:#fff;border:1px solid var(--surface-border);border-radius:14px;padding:18px;margin-top:18px;
    box-shadow:0 6px 22px rgba(18,45,60,.05)}
.estimator h3{margin-top:0;font-size:17px;color:var(--text)}
.est-option{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--surface-border);font-size:14px;color:var(--text)}
.est-option:last-child{border-bottom:0}
.estimator-result{margin-top:16px;font-size:17px;font-weight:800;color:var(--primary-dark)}
/* فیلدهای سفارشی فرم سفارش */
.so-custom-fields{margin:14px 0;padding:14px;border:1px dashed var(--surface-border);border-radius:12px;background:rgba(14,165,164,.04)}
.so-custom-fields h4{margin:0 0 10px;font-size:14px;color:var(--primary-dark)}
/* گالری محصول */
.product-gallery{margin:28px 0}
.product-gallery h3{margin:0 0 14px;font-size:18px;color:var(--text)}
.pg-public-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.pg-public-item{margin:0;border-radius:14px;overflow:hidden;background:#fff;border:1px solid var(--border);cursor:zoom-in;transition:transform .25s}
.pg-public-item:hover{transform:scale(1.03)}
.pg-public-item img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}
.pg-public-item figcaption{padding:8px 10px;font-size:12px;color:var(--muted)}
/* فرم همکاری */
.partner-form{max-width:640px;margin:24px auto;background:#fff;border:1px solid var(--border);border-radius:16px;
    padding:24px;box-shadow:0 8px 28px rgba(18,45,60,.08)}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.pf-field{display:flex;flex-direction:column;gap:6px}
.pf-field.pf-full{grid-column:1/-1}
.pf-field label{font-size:13px;font-weight:600;color:var(--text)}
.pf-field input,.pf-field textarea{border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;
    font-family:inherit;background:#fff;color:var(--text);width:100%;box-sizing:border-box}
.pf-field input:focus,.pf-field textarea:focus{outline:2px solid var(--primary);outline-offset:1px;border-color:var(--primary)}
.pf-field input::placeholder,.pf-field textarea::placeholder{color:var(--muted);opacity:.75}
.pf-field .muted{font-weight:400;font-size:12px}
@media(max-width:560px){.pf-grid{grid-template-columns:1fr}.partner-form{padding:18px}}

/* ---------- فرم‌ها ---------- */
.field{margin-bottom:18px}
label{display:block;font-size:14px;font-weight:600;margin-bottom:8px;color:var(--text)}
input[type=text],input[type=tel],input[type=number],input[type=email],input[type=password],textarea,select{
    width:100%;background:var(--input-bg);border:1px solid var(--input-border);color:var(--text);
    border-radius:12px;padding:12px 16px;font-family:inherit;font-size:15px;transition:border-color .25s,box-shadow .25s}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(14,165,164,.15)}
textarea{min-height:120px;resize:vertical}
.hp-field{position:absolute;right:-9999px;opacity:0;height:0;overflow:hidden}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14.5px;border:1px solid}
.alert-ok,.alert.ok{background:var(--alert-ok-bg);color:var(--alert-ok-text);border-color:rgba(11,107,79,.15)}
.alert-error,.alert.error{background:var(--alert-error-bg);color:var(--alert-error-text);border-color:rgba(180,35,42,.15)}
.empty-state{text-align:center;padding:140px 20px 100px}
.empty-state h1{font-size:30px;margin-bottom:12px;color:var(--text)}

/* ---------- انیمیشن ظهور ---------- */
.rv{opacity:0;-webkit-transform:translateY(28px);transform:translateY(28px);-webkit-transition:opacity .8s ease,-webkit-transform .8s ease;transition:opacity .8s ease,transform .8s ease}
.rv.in{opacity:1;-webkit-transform:none;transform:none}

/* ---------- ریسپانسیو ---------- */
@media (max-width:1024px){
    .prod-grid{grid-template-columns:repeat(2,1fr)}
    .footer-grid{grid-template-columns:1fr 1fr}
    .product-layout{grid-template-columns:1fr;gap:28px}
}
@media (max-width:768px){
    .nav-toggle{display:block}
    .nav-list{position:fixed;top:70px;right:12px;left:12px;flex-direction:column;align-items:stretch;gap:4px;
        background:rgba(255,255,255,.98);border:1px solid var(--surface-border);border-radius:18px;padding:14px;
        box-shadow:var(--shadow);display:none;backdrop-filter:blur(16px)}
    .nav-list.open{display:flex}
    .nav-list a{padding:13px 18px;font-size:15px}
    .header-cta{display:none}
    .steps{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr)}
    .hero-ctas .btn{width:100%;max-width:340px}
    .footer-grid{grid-template-columns:1fr}
    .f-bottom{justify-content:center;text-align:center}
    .final-cta{margin:60px 0 80px;padding:50px 22px}
    .page-head,.product-layout{padding-top:120px}
}
@media (max-width:520px){
    .prod-grid{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr);gap:12px}
}

/* ---------- حرکت کم ---------- */
@media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
    .rv{opacity:1;transform:none}
    .hero-canvas{display:none}
    /* نوار متحرک حتی با حرکت کم هم می‌چرخد (خواسته کاربر) */
    .mq-track{animation:mq 34s linear infinite!important}
}

/* ---------- چاپ ---------- */
@media print{
    .site-header,.hero-canvas,.hero-scroll,.marquee,.final-cta,.g-wrap,.theme-toggle{display:none}
    body{background:#fff;color:#000}
    body::before{display:none}
    .cinematic-hero{min-height:auto;background:#fff}
    .cinematic-hero h1{color:#000}
    .ps-card,.p-card,.step,.stat{box-shadow:none}
}

CSS;
}

/** CSS قالب «نئون شب» (نسخه ۹٫۳٫۰) — تیره و آینده‌نگر با لهجه‌های نئونی */
function theme_neon_css(): string
{
    return <<<'CSS'
/* ============================================================
   تم «نئون شب» لاینرلایت — نسخه ۹٫۳٫۰
   تیره و آینده‌نگر؛ لهجه‌های نئونی فیروزه‌ای و بنفش، حس تکنولوژی.
   ساختار: توکن‌ها ← پایه ← هدر ← هیرو ← دکمه ← بخش‌ها ← ویترین محصولات
   ← گالری ← روند ← آمار ← فوتر ← صفحات داخلی ← فرم‌ها ← موبایل ← حرکت کم ← چاپ
   ============================================================ */

/* ---------- توکن‌ها ---------- */
:root{
    --primary:#22d3ee;
    --primary-dark:#0ea5e9;
    --accent:#a78bfa;
    --accent-dark:#8b5cf6;
    --font-family:"Vazirmatn",Tahoma,"Segoe UI",Arial,sans-serif;
    --container-width:1200px;
    --radius:16px;
    --bg:#05060f;
    --bg-soft:#0a0d1d;
    --surface:#0c1024;
    --surface-border:#1e2a4a;
    --text:#e8f6ff;
    --muted:#9aa7c7;
    --gold-soft:rgba(34,211,238,.10);
    --header-bg:rgba(5,6,15,.78);
    --header-text:#e8f6ff;
    --header-link:#9aa7c7;
    --footer-bg:#03040a;
    --footer-text:#8b98b8;
    --input-bg:#0c1024;
    --card:#0c1024;
    --border:#1e2a4a;
    --input-border:#2a3a5f;
    --alert-ok-bg:#0a2e2a;
    --alert-ok-text:#7ef0d4;
    --alert-error-bg:#3a0f1e;
    --alert-error-text:#ff9db0;
    --shadow:0 24px 60px rgba(0,0,0,.6);
    --line-glow:0 0 24px rgba(34,211,238,.5),0 0 80px rgba(167,139,250,.25);
}

/* ---------- پایه ---------- */
*,*::before,*::after{box-sizing:border-box}
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}
body{margin:0;font-family:var(--font-family);background:var(--bg);color:var(--text);line-height:2;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;
    background:radial-gradient(1000px 520px at 85% -8%,rgba(34,211,238,.08),transparent 60%),
               radial-gradient(900px 520px at 8% 25%,rgba(167,139,250,.09),transparent 60%),
               radial-gradient(700px 700px at 50% 110%,rgba(34,211,238,.05),transparent 60%)}
img{max-width:100%;height:auto;display:block}
a{color:var(--primary);text-decoration:none}
a:hover{color:#fff}
h1,h2,h3{line-height:1.6;margin:0 0 .6em;font-weight:800;color:var(--text)}
p{margin:0 0 1em}
.container{width:min(var(--container-width),100% - 40px);margin-inline:auto}
.skip-link{position:absolute;top:-60px;right:16px;z-index:200;background:var(--primary);color:#03121a;padding:8px 16px;border-radius:8px;transition:top .2s}
.skip-link:focus{top:12px;color:#03121a}
.muted{color:var(--muted)}
/* دکمه تغییر تم */
.theme-toggle{background:rgba(34,211,238,.08);border:1px solid var(--surface-border);border-radius:99px;
    width:44px;height:44px;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center;
    transition:transform .2s,box-shadow .2s}
.theme-toggle:hover{transform:scale(1.08);box-shadow:var(--line-glow)}
main{display:block}
section{scroll-margin-top:84px}

/* ---------- دکمه‌ها ---------- */
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:13px 34px;border-radius:999px;
    font-family:inherit;font-weight:700;font-size:15px;cursor:pointer;border:1px solid transparent;
    transition:transform .25s ease,box-shadow .25s ease,background .25s ease,color .25s ease;white-space:nowrap}
.btn:active{transform:scale(.97)}
.btn-gold{background:linear-gradient(135deg,var(--primary),var(--accent));color:#04121f;box-shadow:var(--line-glow)}
.btn-gold:hover{transform:translateY(-2px);color:#000;box-shadow:0 0 34px rgba(34,211,238,.7),0 0 90px rgba(167,139,250,.3)}
.btn-ghost{background:rgba(34,211,238,.05);border-color:rgba(34,211,238,.35);color:var(--text);backdrop-filter:blur(6px)}
.btn-ghost:hover{border-color:var(--primary);color:#fff;transform:translateY(-2px);box-shadow:0 0 24px rgba(34,211,238,.25)}
.btn-light{background:rgba(167,139,250,.1);border-color:rgba(167,139,250,.35);color:#fff}
.btn-light:hover{border-color:var(--accent);color:#fff;box-shadow:0 0 24px rgba(167,139,250,.3)}
.btn-sm{padding:9px 22px;font-size:13.5px}

/* ---------- هدر ---------- */
.site-header{position:fixed;top:0;right:0;left:0;z-index:100;background:var(--header-bg);
    backdrop-filter:blur(14px);-webkit-backdrop-filter:blur(14px);border-bottom:1px solid rgba(34,211,238,.14)}
.header-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;min-height:70px;padding-block:10px}
.logo{font-weight:900;font-size:20px;color:var(--header-text);display:flex;align-items:center;gap:10px}
.logo::before{content:"";width:34px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--primary),transparent);box-shadow:var(--line-glow)}
.logo-img{height:38px;width:auto;border-radius:8px}
.logo:has(.logo-img)::before{display:none}
.main-nav{display:flex;align-items:center}
.nav-toggle{display:none;background:none;border:1px solid var(--surface-border);color:var(--text);
    border-radius:10px;padding:8px 14px;font-size:18px;cursor:pointer;font-family:inherit}
.nav-list{display:flex;align-items:center;gap:6px}
.nav-list a{color:var(--header-link);font-size:14.5px;font-weight:500;padding:9px 15px;border-radius:999px;transition:.25s}
.nav-list a:hover{color:#fff;background:rgba(34,211,238,.1);box-shadow:inset 0 0 18px rgba(34,211,238,.08)}
.header-cta{flex-shrink:0}

/* ---------- هیرو ---------- */
.slider{position:relative}
.cinematic-hero{min-height:100svh;display:flex;align-items:center;overflow:hidden;background:#04050d}
.cinematic-hero::before{content:"";position:absolute;inset:0;pointer-events:none;opacity:.5;
    background-image:linear-gradient(rgba(34,211,238,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(34,211,238,.05) 1px,transparent 1px);
    background-size:56px 56px;
    -webkit-mask-image:radial-gradient(ellipse 90% 80% at 50% 40%,#000 30%,transparent 75%);
    mask-image:radial-gradient(ellipse 90% 80% at 50% 40%,#000 30%,transparent 75%)}
.hero-media{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.22;transform:scale(1.06)}
.hero-shade{position:absolute;inset:0;background:linear-gradient(180deg,rgba(4,5,13,.6) 0%,rgba(4,5,13,.25) 42%,var(--bg) 100%)}
.hero-canvas{position:absolute;inset:0;width:100%;height:100%}
.hero-inner{position:relative;z-index:2;text-align:center;padding:130px 20px 90px;max-width:900px}
.hero-kicker{display:inline-flex;align-items:center;gap:10px;color:var(--primary);font-size:14px;letter-spacing:.5px;
    border:1px solid rgba(34,211,238,.35);border-radius:999px;padding:8px 20px;margin-bottom:26px;background:rgba(34,211,238,.06);
    box-shadow:inset 0 0 24px rgba(34,211,238,.06)}
.hero-kicker::before,.hero-kicker::after{content:"";width:26px;height:1px;background:linear-gradient(90deg,transparent,var(--primary))}
.hero-kicker::after{background:linear-gradient(90deg,var(--primary),transparent)}
.cinematic-hero h1{font-size:clamp(38px,7.2vw,84px);font-weight:900;line-height:1.5;margin:0 0 18px;
    background:linear-gradient(120deg,#fff 20%,var(--primary) 60%,var(--accent) 100%);-webkit-background-clip:text;background-clip:text;color:transparent;
    filter:drop-shadow(0 0 34px rgba(34,211,238,.3))}
.cinematic-hero .lead{font-size:clamp(15px,2.4vw,19px);color:#b9c6e2;max-width:640px;margin:0 auto 36px;line-height:2.1}
.hero-ctas{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
.hero-scroll{position:absolute;bottom:26px;right:50%;transform:translateX(50%);z-index:2;width:26px;height:44px;
    border:2px solid rgba(34,211,238,.5);border-radius:14px;display:flex;justify-content:center;padding-top:8px}
.hero-scroll span{width:4px;height:9px;border-radius:4px;background:var(--primary);box-shadow:0 0 12px var(--primary);
    -webkit-animation:scrollDot 1.8s ease-in-out infinite;animation:scrollDot 1.8s ease-in-out infinite}
@-webkit-keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}
@keyframes scrollDot{0%{-webkit-transform:translateY(0);transform:translateY(0);opacity:1}70%{-webkit-transform:translateY(14px);transform:translateY(14px);opacity:0}100%{opacity:0}}

/* ---------- نوار متحرک ---------- */
.marquee{position:relative;overflow:hidden;padding:20px 0;z-index:2;direction:ltr;
    background:linear-gradient(180deg,rgba(34,211,238,.06),rgba(167,139,250,.03) 50%,rgba(34,211,238,.06));
    border-block:1px solid rgba(34,211,238,.22)}
.marquee::before,.marquee::after{content:"";position:absolute;left:0;right:0;height:1px;
    background:linear-gradient(90deg,transparent,rgba(34,211,238,.55),transparent)}
.marquee::before{top:0}.marquee::after{bottom:0}
.mq-track{display:flex;align-items:center;width:max-content;-webkit-animation:mq 34s linear infinite;animation:mq 34s linear infinite;white-space:nowrap;will-change:transform}
.mq-track:hover{-webkit-animation-play-state:paused;animation-play-state:paused}
.mq-item{display:inline-flex;align-items:center;padding:0 30px;font-size:15px;font-weight:500;color:#c3cfe8;direction:rtl;letter-spacing:.2px}
.mq-item b{color:var(--primary);font-weight:800;text-shadow:0 0 22px rgba(34,211,238,.5)}
.mq-dot{color:var(--accent);font-size:9px;opacity:.8;text-shadow:0 0 14px rgba(167,139,250,.8);flex-shrink:0}
@-webkit-keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}
@keyframes mq{to{-webkit-transform:translateX(-50%);transform:translateX(-50%)}}

/* ---------- تیتر بخش‌ها ---------- */
.sec-kicker{color:var(--primary);font-size:14px;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:12px;justify-content:center;text-shadow:0 0 18px rgba(34,211,238,.4)}
.sec-kicker::before{content:"";width:34px;height:2px;background:var(--primary);border-radius:2px;box-shadow:var(--line-glow)}
.sec-title{font-size:clamp(26px,4.4vw,42px);font-weight:900;text-align:center;margin-bottom:14px}
.sec-sub{text-align:center;color:var(--muted);max-width:620px;margin:0 auto 46px;font-size:15.5px}

/* ---------- بخش‌ها ---------- */
.features{padding:110px 0 30px;position:relative}
.workshop-showcase{position:relative;padding:100px 0;overflow:hidden;border-radius:28px;margin:40px 20px;
    border:1px solid rgba(34,211,238,.22);box-shadow:var(--shadow)}
.workshop-showcase::before{content:"";position:absolute;inset:0;
    background:url('uploads/gallery/workshop_glow.jpg') center/cover;filter:blur(8px) brightness(.3) hue-rotate(160deg);transform:scale(1.05)}
.workshop-showcase::after{content:"";position:absolute;inset:0;
    background:linear-gradient(180deg,rgba(5,6,15,.55),rgba(5,6,15,.88))}
.workshop-showcase>*{position:relative;z-index:2}
@media(max-width:640px){.workshop-showcase{margin:20px 12px;padding:60px 0;border-radius:20px}
.workshop-showcase .container{padding:0 16px;max-width:100%}
.features .cards{grid-template-columns:1fr;gap:14px;margin-inline:0;width:100%}
.features .card{padding:28px 20px;margin:0}}
/* ---------- کارت‌های ویژگی‌ها ---------- */
.features .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:40px}
.features .card{position:relative;background:linear-gradient(160deg,var(--surface),rgba(34,211,238,.03));
    border:1px solid var(--surface-border);border-radius:22px;padding:36px 28px;overflow:hidden;
    transition:transform .45s cubic-bezier(.2,.7,.3,1.2),box-shadow .45s,border-color .45s}
.features .card::before{content:"";position:absolute;top:0;inset-inline:0;height:3px;
    background:linear-gradient(90deg,transparent,var(--primary),transparent);opacity:0;transition:opacity .4s}
.features .card::after{content:"";position:absolute;top:-60px;inset-inline-end:-60px;width:140px;height:140px;border-radius:50%;
    background:radial-gradient(circle,rgba(34,211,238,.16),transparent 70%);transition:transform .5s}
.features .card:hover{transform:translateY(-8px);border-color:rgba(34,211,238,.5);
    box-shadow:0 24px 60px rgba(0,0,0,.5),0 0 40px rgba(34,211,238,.14)}
.features .card:hover::before{opacity:1}
.features .card:hover::after{transform:scale(1.6)}
.features .card-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;margin-bottom:18px;
    background:linear-gradient(135deg,rgba(34,211,238,.16),rgba(167,139,250,.08));border:1px solid rgba(34,211,238,.3);color:var(--primary);
    transition:transform .4s,box-shadow .4s}
.features .card-icon svg{width:28px;height:28px}
.features .card:hover .card-icon{transform:scale(1.1) rotate(-6deg);box-shadow:0 0 28px rgba(34,211,238,.4)}
.features .card h3{font-size:19px;font-weight:800;margin:0 0 12px;color:var(--text)}
.features .card p{font-size:14.5px;color:var(--muted);margin:0;line-height:2}
.content-section{padding:70px 0}
.section-body{font-size:15.5px;color:var(--text)}
.content-section h2:empty{display:none}
.section-image{margin:0 0 26px}
.section-image img{border-radius:var(--radius);box-shadow:var(--shadow)}

/* ---------- ویترین محصولات ---------- */
.prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:22px;margin-top:8px}
.p-card{background:linear-gradient(180deg,rgba(34,211,238,.04),rgba(34,211,238,.01));border:1px solid var(--surface-border);
    border-radius:20px;overflow:hidden;transition:transform .35s ease,border-color .35s ease,box-shadow .35s ease;display:flex;flex-direction:column}
.p-card:hover{transform:translateY(-8px);border-color:rgba(34,211,238,.5);box-shadow:0 26px 60px rgba(0,0,0,.55),0 0 40px rgba(34,211,238,.14)}
.p-media{aspect-ratio:4/3;overflow:hidden;background:#070a16;position:relative}
.p-media img{width:100%;height:100%;object-fit:cover;transition:transform .6s ease}
.p-card:hover .p-media img{transform:scale(1.07)}
.p-media::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 55%,rgba(4,5,13,.6))}
.p-body{padding:22px 20px 24px;display:flex;flex-direction:column;gap:10px;flex:1}
.p-tag{align-self:flex-start;font-size:12px;font-weight:700;color:var(--primary);background:var(--gold-soft);
    border:1px solid rgba(34,211,238,.3);padding:4px 14px;border-radius:999px}
.p-name{font-size:18px;font-weight:800;margin:0;color:var(--text)}
.p-desc{font-size:13.5px;color:var(--muted);line-height:1.9;margin:0;flex:1}
.p-link{font-size:14px;font-weight:700;color:var(--primary);display:inline-flex;align-items:center;gap:8px}
.p-link:hover{color:#fff}

/* ---------- مانیفست ---------- */
.manifesto{padding:90px 0;text-align:center;position:relative}
.mani-kicker{color:var(--primary);font-size:14px;font-weight:700;margin-bottom:18px;text-shadow:0 0 18px rgba(34,211,238,.4)}
.mani-text{font-size:clamp(20px,3.6vw,30px);font-weight:700;line-height:2.2;max-width:860px;margin:0 auto}
.mani-text em{font-style:normal;color:var(--primary);text-shadow:0 0 30px rgba(34,211,238,.45)}

/* ---------- بلوک‌های صفحه‌ساز ---------- */
.pb-text,.pb-image,.pb-gallery,.pb-cta,.pb-features,.pb-video{padding:50px 0}
.pb-text h2,.pb-image h2,.pb-gallery h2,.pb-cta h2,.pb-features h2,.pb-video h2{text-align:center;color:var(--text);margin:0 0 24px;font-size:clamp(22px,3.5vw,30px)}
.pb-body{max-width:800px;margin:0 auto;line-height:2;color:#c3cfe8}
.pb-image img{max-width:100%;height:auto;border-radius:16px;display:block;margin:0 auto;box-shadow:0 12px 40px rgba(0,0,0,.5)}
.pb-caption{text-align:center;color:var(--muted);font-size:14px;margin-top:12px}
.pb-ggrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px}
.pb-ggrid img{width:100%;height:160px;object-fit:cover;border-radius:12px;display:block;transition:transform .3s}
.pb-ggrid a:hover img{transform:scale(1.04)}
.pb-cta{text-align:center;background:linear-gradient(135deg,rgba(34,211,238,.09),rgba(167,139,250,.05));border-top:1px solid rgba(34,211,238,.2);border-bottom:1px solid rgba(34,211,238,.2)}
.pb-cta p{color:var(--muted);max-width:600px;margin:0 auto 24px;line-height:2}
.pb-fgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
.pb-fcard{background:rgba(34,211,238,.03);border:1px solid rgba(34,211,238,.18);border-radius:14px;padding:22px}
.pb-fcard h3{color:var(--primary);margin:0 0 8px;font-size:17px}
.pb-fcard p{color:var(--muted);margin:0;font-size:14px;line-height:1.9}
.pb-divider hr{border:none;border-top:1px solid rgba(34,211,238,.22);margin:0}
.pb-video video{max-width:800px;width:100%;border-radius:16px;display:block;margin:0 auto}
.pb-vwrap{position:relative;max-width:800px;margin:0 auto;aspect-ratio:16/9}
.pb-vwrap iframe{position:absolute;inset:0;width:100%;height:100%;border-radius:16px}

/* ---------- ویترین محصولات صفحه اصلی ---------- */
.products-showcase{padding:70px 0;background:linear-gradient(180deg,rgba(34,211,238,.04),transparent 60%)}
.ps-head{text-align:center;margin-bottom:36px}
.ps-head h2{font-size:clamp(24px,4vw,36px);color:var(--text);margin:0 0 8px;text-shadow:0 0 30px rgba(34,211,238,.25)}
.ps-head p{color:var(--muted);margin:0}
.ps-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:20px}
.ps-card{display:block;background:rgba(34,211,238,.03);border:1px solid rgba(34,211,238,.18);border-radius:16px;overflow:hidden;
    text-decoration:none;color:inherit;transition:transform .25s,box-shadow .25s,border-color .25s}
.ps-card:hover{transform:translateY(-6px);border-color:rgba(34,211,238,.55);box-shadow:0 18px 40px rgba(0,0,0,.5),0 0 34px rgba(34,211,238,.16)}
.ps-img{aspect-ratio:1/1;overflow:hidden;background:#070a16}
.ps-img img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .4s}
.ps-card:hover .ps-img img{transform:scale(1.06)}
.ps-body{padding:16px}
.ps-body h3{margin:0 0 6px;font-size:17px;color:var(--text)}
.ps-cat{font-size:12px;color:var(--primary);background:rgba(34,211,238,.1);padding:2px 10px;border-radius:99px}
.ps-price{margin-top:10px;font-size:18px;font-weight:700;color:var(--primary);text-shadow:0 0 20px rgba(34,211,238,.35)}
.ps-price small{font-size:12px;font-weight:400;color:var(--muted);text-shadow:none}
.ps-partner{font-size:13px;color:var(--muted);margin-top:2px}
.ps-link{display:inline-block;margin-top:12px;font-size:14px;color:var(--primary)}
.ps-cta{display:inline-block;margin-top:12px}
.ps-more{text-align:center;margin-top:32px}

/* ---------- نوار گالری ---------- */
.g-wrap{padding:20px 0 90px}
.g-strip{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;grid-auto-flow:dense;padding:10px 20px 24px}
.g-item{border-radius:16px;overflow:hidden;position:relative;border:1px solid var(--surface-border);cursor:pointer;
    transition:transform .4s cubic-bezier(.2,.7,.3,1.2),box-shadow .4s,border-color .4s}
.g-item:nth-child(6n+1){grid-row:span 2}
.g-item:nth-child(6n+1) img{aspect-ratio:3/4}
.g-item img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;transition:transform .6s ease}
.g-item:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 50px rgba(0,0,0,.4),0 0 30px rgba(34,211,238,.18);border-color:rgba(34,211,238,.55);z-index:2}
.g-item:hover img{transform:scale(1.08)}
.g-item figcaption{position:absolute;inset-inline:0;bottom:0;padding:28px 14px 12px;font-size:12.5px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(2,4,10,.85));opacity:0;transform:translateY(10px);transition:opacity .35s,transform .35s}
.g-item:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.g-strip{grid-template-columns:repeat(2,1fr);gap:10px}
.g-item:nth-child(6n+1){grid-row:span 1}
.g-item:nth-child(6n+1) img{aspect-ratio:4/3}}
.g-more{text-align:center;margin-top:26px}

/* ---------- روند کار ---------- */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:22px;counter-reset:step}
.step{background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;padding:38px 28px;position:relative;overflow:hidden;transition:.35s}
.step:hover{border-color:rgba(167,139,250,.45);transform:translateY(-5px);box-shadow:0 0 34px rgba(167,139,250,.14)}
.step::before{counter-increment:step;content:"0" counter(step);position:absolute;top:14px;left:22px;font-size:44px;font-weight:900;color:transparent;-webkit-text-stroke:1px rgba(34,211,238,.4)}
.step h3{font-size:18px;margin:0 0 10px;color:var(--primary)}
.step p{font-size:14px;color:var(--muted);margin:0;line-height:2}

/* ---------- آمار ---------- */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-top:70px}
.stat{text-align:center;padding:30px 12px;border:1px solid var(--surface-border);border-radius:18px;background:rgba(34,211,238,.02)}
.stat b{display:block;font-size:clamp(28px,4vw,40px);font-weight:900;color:var(--primary);text-shadow:0 0 26px rgba(34,211,238,.4)}
.stat span{font-size:13.5px;color:var(--muted)}

/* ---------- دعوت نهایی ---------- */
.final-cta{margin:90px 0 110px;padding:70px 30px;text-align:center;border-radius:28px;position:relative;overflow:hidden;
    background:linear-gradient(135deg,rgba(34,211,238,.12),rgba(167,139,250,.08));border:1px solid rgba(34,211,238,.3);
    box-shadow:inset 0 0 80px rgba(34,211,238,.05),0 0 60px rgba(34,211,238,.08)}
.final-cta::before{content:"";position:absolute;top:-70px;right:50%;transform:translateX(50%);width:420px;height:140px;
    background:radial-gradient(closest-side,rgba(34,211,238,.3),transparent);pointer-events:none}
.final-cta h2{font-size:clamp(24px,4.6vw,40px);margin-bottom:12px;text-shadow:0 0 30px rgba(34,211,238,.3)}
.final-cta p{color:var(--muted);margin-bottom:30px}

/* ---------- فوتر ---------- */
.site-footer{background:var(--footer-bg);border-top:1px solid rgba(34,211,238,.14);padding:70px 0 0;margin-top:40px;color:var(--footer-text)}
.footer-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:36px;padding-bottom:46px}
.f-logo{font-size:22px;font-weight:900;color:var(--text);margin-bottom:12px;display:flex;align-items:center;gap:10px}
.f-logo::before{content:"";width:30px;height:3px;border-radius:3px;background:linear-gradient(90deg,var(--primary),transparent);box-shadow:var(--line-glow)}
.f-brand p{font-size:14px;line-height:2}
.f-title{font-size:15px;font-weight:800;color:var(--text);margin-bottom:16px}
.f-links{display:flex;flex-direction:column;gap:10px}
.f-links a{color:var(--footer-text);font-size:14px;transition:.25s}
.f-links a:hover{color:var(--primary);padding-right:6px;text-shadow:0 0 16px rgba(34,211,238,.5)}
.f-bottom{border-top:1px solid rgba(255,255,255,.07);padding:22px 0;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px}

/* ---------- صفحات داخلی ---------- */
.page-head{padding:150px 0 40px;text-align:center}
.page-head h1{font-size:clamp(28px,5vw,46px);text-shadow:0 0 40px rgba(34,211,238,.25)}
.page-body{font-size:15.5px;color:var(--text);line-height:2.1;max-width:860px;margin:0 auto;padding-bottom:90px}
.page-body img{border-radius:var(--radius)}
.page-body .gallery-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;grid-auto-flow:dense}
.page-body .gallery-tiles figure{margin:0;border-radius:14px;overflow:hidden;position:relative;cursor:pointer;
    border:1px solid var(--surface-border);background:var(--surface);transition:transform .35s ease,box-shadow .35s,border-color .35s}
.page-body .gallery-tiles figure:nth-child(6n+1){grid-row:span 2}
.page-body .gallery-tiles figure:nth-child(6n+1) img{aspect-ratio:3/4}
.page-body .gallery-tiles figure img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block;transition:transform .5s ease}
.page-body .gallery-tiles figure:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(0,0,0,.35),0 0 24px rgba(34,211,238,.14);border-color:rgba(34,211,238,.5);z-index:2}
.page-body .gallery-tiles figure:hover img{transform:scale(1.06)}
.page-body .gallery-tiles figcaption{position:absolute;inset-inline:0;bottom:0;padding:24px 12px 10px;font-size:12px;font-weight:600;color:#fff;
    background:linear-gradient(180deg,transparent,rgba(2,4,10,.82));opacity:0;transform:translateY(8px);transition:opacity .3s,transform .3s}
.page-body .gallery-tiles figure:hover figcaption{opacity:1;transform:translateY(0)}
@media(max-width:640px){.page-body .gallery-tiles{grid-template-columns:repeat(2,1fr);gap:10px}
.page-body .gallery-tiles figure:nth-child(5n+1){grid-row:span 1}
.page-body .gallery-tiles figure:nth-child(5n+1) img{aspect-ratio:4/3}}
.catalog-nav{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin:0 0 40px}
.cat{padding:9px 22px;border-radius:999px;border:1px solid var(--surface-border);color:var(--muted);font-size:14px;
    font-weight:600;transition:.25s;background:rgba(34,211,238,.02)}
.cat:hover{color:#fff;border-color:var(--primary)}
.cat.active{background:linear-gradient(135deg,var(--primary),var(--accent));color:#04121f;border-color:transparent;font-weight:800;
    box-shadow:0 0 24px rgba(34,211,238,.35)}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:24px;padding-bottom:100px}
.product-card{background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;overflow:hidden;transition:.35s;display:flex;flex-direction:column}
.product-card:hover{transform:translateY(-6px);border-color:rgba(34,211,238,.45);box-shadow:var(--shadow),0 0 30px rgba(34,211,238,.12)}
.product-card .product-media{aspect-ratio:1/1;overflow:hidden;background:#070a16;border-radius:16px 16px 0 0}
.product-card .product-media img{width:100%;height:100%;object-fit:cover}
.product-card-body{padding:22px;display:flex;flex-direction:column;gap:10px;flex:1}
.product-card-body h3{margin:0;font-size:17px;color:var(--text)}
.price{color:var(--primary);font-weight:800;font-size:16px;text-shadow:0 0 18px rgba(34,211,238,.35)}
.product-desc{font-size:13.5px;color:var(--muted);line-height:1.9;flex:1}
.product-breadcrumb{font-size:13.5px;color:var(--muted);margin-bottom:18px}
.product-breadcrumb a{color:var(--primary)}
.product-layout{display:grid;grid-template-columns:1fr 1fr;gap:44px;padding:150px 0 90px;align-items:start}
.product-media{aspect-ratio:1/1;overflow:hidden;border-radius:20px}
.product-media img{width:100%;height:100%;object-fit:cover;border-radius:20px;box-shadow:var(--shadow);border:1px solid var(--surface-border)}
.product-info h1{font-size:clamp(24px,4vw,36px)}
.product-price-line{display:flex;align-items:center;gap:14px;margin:18px 0;padding:16px 20px;background:var(--gold-soft);
    border:1px solid rgba(34,211,238,.3);border-radius:14px}
.partner-line{font-size:13.5px;color:var(--muted);background:rgba(34,211,238,.03);border:1px dashed var(--surface-border);
    padding:10px 16px;border-radius:12px;margin-top:12px}
.estimator{background:var(--surface);border:1px solid var(--surface-border);border-radius:14px;padding:18px;margin-top:18px}
.estimator h3{margin-top:0;font-size:17px}
.est-option{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--surface-border);font-size:14px}
.est-option:last-child{border-bottom:0}
.estimator-result{margin-top:16px;font-size:17px;font-weight:800;color:var(--primary);text-shadow:0 0 18px rgba(34,211,238,.35)}
/* فیلدهای سفارشی فرم سفارش */
.so-custom-fields{margin:14px 0;padding:14px;border:1px dashed var(--surface-border);border-radius:12px;background:rgba(34,211,238,.04)}
.so-custom-fields h4{margin:0 0 10px;font-size:14px;color:var(--primary)}
/* گالری محصول */
.product-gallery{margin:28px 0}
.product-gallery h3{margin:0 0 14px;font-size:18px}
.pg-public-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px}
.pg-public-item{margin:0;border-radius:14px;overflow:hidden;background:var(--card);border:1px solid var(--border);cursor:zoom-in;transition:transform .25s,box-shadow .25s}
.pg-public-item:hover{transform:scale(1.03);box-shadow:0 0 24px rgba(34,211,238,.2)}
.pg-public-item img{width:100%;aspect-ratio:1/1;object-fit:cover;display:block}
.pg-public-item figcaption{padding:8px 10px;font-size:12px;color:var(--muted)}
/* فرم همکاری */
.partner-form{max-width:640px;margin:24px auto;background:var(--card);border:1px solid var(--border);border-radius:16px;
    padding:24px;box-shadow:0 8px 32px rgba(0,0,0,.4)}
.pf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px}
.pf-field{display:flex;flex-direction:column;gap:6px}
.pf-field.pf-full{grid-column:1/-1}
.pf-field label{font-size:13px;font-weight:600;color:var(--text)}
.pf-field input,.pf-field textarea{border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;
    font-family:inherit;background:var(--bg);color:var(--text);width:100%;box-sizing:border-box}
.pf-field input:focus,.pf-field textarea:focus{outline:2px solid var(--primary);outline-offset:1px;border-color:var(--primary)}
.pf-field input::placeholder,.pf-field textarea::placeholder{color:var(--muted);opacity:.75}
.pf-field .muted{font-weight:400;font-size:12px}
@media(max-width:560px){.pf-grid{grid-template-columns:1fr}.partner-form{padding:18px}}

/* ---------- فرم‌ها ---------- */
.field{margin-bottom:18px}
label{display:block;font-size:14px;font-weight:600;margin-bottom:8px;color:var(--text)}
input[type=text],input[type=tel],input[type=number],input[type=email],input[type=password],textarea,select{
    width:100%;background:var(--input-bg);border:1px solid var(--input-border);color:var(--text);
    border-radius:12px;padding:12px 16px;font-family:inherit;font-size:15px;transition:border-color .25s,box-shadow .25s}
input:focus,textarea:focus,select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(34,211,238,.18),0 0 18px rgba(34,211,238,.12)}
textarea{min-height:120px;resize:vertical}
.hp-field{position:absolute;right:-9999px;opacity:0;height:0;overflow:hidden}
.alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-size:14.5px;border:1px solid}
.alert-ok,.alert.ok{background:var(--alert-ok-bg);color:var(--alert-ok-text);border-color:rgba(126,240,212,.18)}
.alert-error,.alert.error{background:var(--alert-error-bg);color:var(--alert-error-text);border-color:rgba(255,157,176,.18)}
.empty-state{text-align:center;padding:140px 20px 100px}
.empty-state h1{font-size:30px;margin-bottom:12px}

/* ---------- انیمیشن ظهور ---------- */
.rv{opacity:0;-webkit-transform:translateY(28px);transform:translateY(28px);-webkit-transition:opacity .8s ease,-webkit-transform .8s ease;transition:opacity .8s ease,transform .8s ease}
.rv.in{opacity:1;-webkit-transform:none;transform:none}

/* ---------- ریسپانسیو ---------- */
@media (max-width:1024px){
    .prod-grid{grid-template-columns:repeat(2,1fr)}
    .footer-grid{grid-template-columns:1fr 1fr}
    .product-layout{grid-template-columns:1fr;gap:28px}
}
@media (max-width:768px){
    .nav-toggle{display:block}
    .nav-list{position:fixed;top:70px;right:12px;left:12px;flex-direction:column;align-items:stretch;gap:4px;
        background:rgba(8,10,22,.97);border:1px solid var(--surface-border);border-radius:18px;padding:14px;
        box-shadow:var(--shadow);display:none;backdrop-filter:blur(16px)}
    .nav-list.open{display:flex}
    .nav-list a{padding:13px 18px;font-size:15px}
    .header-cta{display:none}
    .steps{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr)}
    .hero-ctas .btn{width:100%;max-width:340px}
    .footer-grid{grid-template-columns:1fr}
    .f-bottom{justify-content:center;text-align:center}
    .final-cta{margin:60px 0 80px;padding:50px 22px}
    .page-head,.product-layout{padding-top:120px}
}
@media (max-width:520px){
    .prod-grid{grid-template-columns:1fr}
    .stats{grid-template-columns:repeat(2,1fr);gap:12px}
}

/* ---------- حرکت کم ---------- */
@media (prefers-reduced-motion:reduce){
    html{scroll-behavior:auto}
    *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}
    .rv{opacity:1;transform:none}
    .hero-canvas{display:none}
    /* نوار متحرک حتی با حرکت کم هم می‌چرخد (خواسته کاربر) */
    .mq-track{animation:mq 34s linear infinite!important}
}

/* ---------- چاپ ---------- */
@media print{
    .site-header,.hero-canvas,.hero-scroll,.marquee,.final-cta,.g-wrap,.theme-toggle{display:none}
    body{background:#fff;color:#000}
    body::before{display:none}
    .cinematic-hero{min-height:auto;background:#fff}
    .cinematic-hero::before{display:none}
    .cinematic-hero h1{background:none;color:#000;filter:none}
    .ps-card,.p-card,.step,.stat{box-shadow:none}
}

CSS;
}

/** CSS پایه سایت — تم سینمایی لاینرلایت (نسخه ۸٫۹٫۰) + استایل فرم سفارش */
function default_site_css(): string
{
    return theme_css((string) get_setting('site_theme', 'cinematic')) . "\n\n" . order_form_css();
}

/**
 * رجیستری قالب‌های فرانت‌اند (نسخه ۹٫۳٫۰) — سه قالب مدرن با تعویض تک‌کلیکی از پنل.
 * هر قالب: کلید، عنوان فارسی، توضیح، و رنگ‌های شاخص برای پیش‌نمایش در پنل.
 */
function site_theme_definitions(): array
{
    return [
        'cinematic' => [
            'title' => 'سینمایی',
            'desc' => 'تیره و طلایی؛ لوکس و چشمگیر با هیروی تمام‌صفحه و ذرات نور.',
            'swatches' => ['#07090d', '#c9a227', '#e8c66a', '#f2ede1'],
        ],
        'modern' => [
            'title' => 'مدرن روشن',
            'desc' => 'روشن و مینیمال؛ تمیز و امروزی با تأکید بر خوانایی و فضای سفید.',
            'swatches' => ['#ffffff', '#0ea5a4', '#c9a227', '#1a2330'],
        ],
        'neon' => [
            'title' => 'نئون شب',
            'desc' => 'تیره و آینده‌نگر؛ با لهجه‌های نئونی فیروزه‌ای و بنفش برای حس تکنولوژی.',
            'swatches' => ['#05060f', '#22d3ee', '#a78bfa', '#e8f6ff'],
        ],
    ];
}

/** CSS کامل یک قالب بر اساس کلید؛ کلید نامعتبر → سینمایی. */
function theme_css(string $key): string
{
    switch ($key) {
        case 'modern':
            return theme_modern_css();
        case 'neon':
            return theme_neon_css();
        case 'cinematic':
        default:
            return cinematic_base_css();
    }
}

// ---------- سازنده‌های HTML کاتالوگ (از config.php منتقل شدند تا آن فایل کوچک بماند) ----------
// این توابع فقط برای صفحه عمومی کاتالوگ (products.php) خروجی امن می‌سازند؛ ورودی‌ها escape می‌شوند.

// ---------- سازنده‌های HTML کاتالوگ (خروجی خام داخلیِ مطمئن؛ ورودی‌ها escape می‌شوند) ----------

/** ناو دسته‌های کاتالوگ (چیپ‌ها) با حالت فعال */
function catalog_categories_nav_html(?int $activeCategoryId): string
{
    $cats = get_categories(true);
    $html = '<nav class="catalog-nav" aria-label="دسته‌بندی محصولات">';
    $html .= '<a href="/products"' . ($activeCategoryId === null ? ' class="active"' : '') . '>همه محصولات</a>';
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
        $html .= '<a href="/products?cat=' . $cid . '"' . ($activeCategoryId === $cid ? ' class="active"' : '') . $indent . '>' . e($c['title']) . '</a>';
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
            $html .= '<a href="/products?id=' . $pid . '"><img src="' . e($img) . '" alt="' . e($p['name']) . '" loading="lazy" decoding="async"></a>';
        }
        $html .= '<div class="product-card-body">';
        if (!empty($p['category_title'])) {
            $html .= '<span class="cat">' . e($p['category_title']) . '</span>';
        }
        $html .= '<h3><a href="/products?id=' . $pid . '">' . e($p['name']) . '</a></h3>';
        $html .= '<p class="price">قیمت متری: <strong>' . e(format_price($p['price_per_meter'] ?? 0)) . '</strong> تومان</p>';
        $html .= '<a class="btn small" href="/products?id=' . $pid . '">مشاهده و برآورد قیمت</a>';
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
        $html .= '<p class="partner-line">تخفیف همکار: <strong id="est-partner">' . e(format_price($partnerBase)) . '</strong> تومان</p>';
    }

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
        // ورودی میلی‌متر (اولویت) یا سانتی‌متر؛ ذخیره داخلی همیشه سانتی‌متر با دقت ۰٫۱ (= ۱ میلی‌متر)
        if (isset($src['length_mm']) && $src['length_mm'] !== '') {
            $lenMm = (float) $src['length_mm'];
            if ($lenMm < 1) {
                return [null, 'طول هر چراغ باید حداقل ۱ میلی‌متر باشد.'];
            }
            $lenCm = round($lenMm / 10, 1);
        } else {
            $lenCm = round((float) ($src['length_cm'] ?? 0), 1);
        }
        $qty = max(1, (int) ($src['qty'] ?? 1));
        $product = $pid > 0 ? get_product($pid) : null;
        $isPerWatt = $product !== null && (string) ($product['pricing_model'] ?? 'per_meter') === 'per_watt';
        $watt = max(0, (int) ($src['watt'] ?? 0));
        if ($pid <= 0) {
            return [null, '']; // ردیف خالی → نادیده گرفته می‌شود
        }
        if ($product === null || (int) ($product['is_active'] ?? 0) !== 1) {
            return [null, 'محصول انتخابی معتبر نیست.'];
        }
        if ($isPerWatt) {
            if ($watt <= 0) {
                return [null, 'توان چراغ رشد گیاه را وارد کنید.'];
            }
            $lenCm = 0.0; // محصول پر-وات طول ندارد
        } elseif ($lenCm <= 0) {
            return [null, '']; // ردیف خالی → نادیده گرفته می‌شود
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
        // فیلدهای سفارشی فرم سفارش (نسخه ۹٫۱)
        $customVals = [];
        $rawCustom = $src['custom'] ?? [];
        if (is_array($rawCustom)) {
            foreach (get_order_form_fields($pid) as $ff) {
                $ffid = (int) $ff['id'];
                $val = trim((string) ($rawCustom[$ffid] ?? $rawCustom[(string) $ffid] ?? ''));
                if ($val !== '') {
                    $customVals[] = ['field' => (string) $ff['label'], 'value' => mb_substr($val, 0, 500)];
                } elseif ((int) ($ff['is_required'] ?? 0) === 1) {
                    return [null, 'فیلد «' . (string) $ff['label'] . '» الزامی است.'];
                }
            }
        }
        return [[
            'product_id' => $pid,
            'length_cm' => $lenCm,
            'watt' => $watt,
            'qty' => $qty,
            'wire_length_cm' => $wire,
            'has_endcap' => !empty($src['endcap']),
            'options' => $opts,
            'note' => ($lineNoteOn && $note !== '') ? mb_substr($note, 0, 500) : '',
            'custom_fields' => $customVals,
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
            'length_mm' => $_POST['so_length_mm'] ?? null,
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
                $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, length_cm, qty, billable_m, unit_price_per_m, options_json, options_extra_per_m, wire_length_cm, wire_steps, wire_extra_total, has_endcap, note, custom_fields_json, line_subtotal, line_total, sort_order)
                    VALUES (:o, :p, :pn, :len, :q, :bm, :up, :oj, :oe, :w, :ws, :we, :ec, :note, :cf, :ls, :lt, :so)')
                    ->execute([
                        ':o' => $oid, ':p' => (int) $tl['product_id'], ':pn' => (string) ($tl['product_id'] ? (string) (get_product((int) $tl['product_id'])['name'] ?? '') : ''),
                        ':len' => $tl['length_cm'], ':q' => $tl['qty'], ':bm' => $tl['billable_m'],
                        ':up' => $tl['unit_price_per_m'], ':oj' => $optSnap === [] ? null : json_encode($optSnap, JSON_UNESCAPED_UNICODE),
                        ':oe' => $tl['options_extra_per_m'], ':w' => $tl['wire_length_cm'], ':ws' => $tl['wire_steps'],
                        ':we' => $tl['wire_extra_total'], ':ec' => !empty($ln['has_endcap']) ? 1 : 0,
                        ':note' => ($ln['note'] !== '' ? $ln['note'] : null),
                        ':cf' => !empty($ln['custom_fields']) ? json_encode($ln['custom_fields'], JSON_UNESCAPED_UNICODE) : null,
                        ':ls' => $tl['line_subtotal'], ':lt' => $tl['line_total'], ':so' => $sort,
                    ]);
                $sort += 10;
            }
            $pdo->prepare("INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, NULL, 'new', :n)")
                ->execute([':o' => $oid, ':n' => 'ثبت سفارش از سایت']);
            set_setting('next_order_no', (string) ($orderNo + 1));
            $pdo->commit();
            notify_admins('new_order', 'سفارش جدید ثبت شد', 'سفارش #' . $orderNo . ' از سایت ثبت شد (' . format_price($tot['total']) . ' تومان).', 'admin.php?page=orders');
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
.site-order{margin:20px 0 120px;background:var(--surface);border:1px solid var(--surface-border);border-radius:calc(var(--radius) + 6px);padding:16px;box-shadow:0 8px 28px rgba(15,23,42,.07)}
.site-order h2{margin:0;font-size:18px;line-height:1.5}
.site-order .alert{max-width:none;margin:0 0 16px}
.so-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;padding-bottom:16px;border-bottom:1px solid var(--surface-border);margin-bottom:18px}
.so-product{display:flex;align-items:center;gap:12px;min-width:0}
.so-thumb{width:58px;height:58px;object-fit:cover;border-radius:var(--radius);border:1px solid var(--surface-border);flex:none}
.so-product-name{font-weight:700;font-size:16px}
.so-price{margin:0;font-size:13px;color:var(--muted);text-align:end}
.so-price strong{display:block;color:var(--primary);font-size:18px;line-height:1.6}
.so-body{display:grid;gap:16px}
.so-section h3{margin:0 0 12px;font-size:15px}
.so-grid{display:grid;grid-template-columns:1fr;gap:14px}
.so-field{min-width:0}
.so-grid .so-full{grid-column:1/-1}
.site-order .so-field>label,.site-order .so-label{display:block;margin-bottom:6px;font-size:13.5px;font-weight:600}
.site-order input[type=text],.site-order input[type=tel],.site-order input[type=number],.site-order textarea,.site-order select{width:100%;padding:9px 10px;min-height:44px;border:1px solid var(--input-border);border-radius:var(--radius);background:var(--input-bg);color:var(--text);font:inherit;font-size:16px}
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
.so-summary{position:sticky;bottom:10px;z-index:5;background:var(--bg);border:1px solid var(--surface-border);border-radius:calc(var(--radius) + 4px);padding:10px 14px 12px;box-shadow:var(--shadow)}
.so-minbill{display:none;font-size:11.5px;color:var(--muted);margin:8px 0 0;line-height:1.8}
.so-summary.open .so-minbill{display:block}
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

/* ===== صفحه تماس با ما ===== */
.contact-page{padding:40px 0 80px}
.contact-hero{text-align:center;max-width:640px;margin:0 auto 40px}
.contact-hero h1{font-size:32px;margin:0 0 12px}
.contact-subtitle{color:var(--muted);font-size:17px;line-height:1.8;margin:0}
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start}
.contact-cards{display:flex;flex-direction:column;gap:16px}
.contact-card{display:flex;align-items:center;gap:16px;background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:20px;text-decoration:none;color:var(--text);transition:transform .2s,box-shadow .2s,border-color .2s}
.contact-card:hover{transform:translateY(-3px);box-shadow:0 12px 32px rgba(0,0,0,.18);border-color:var(--primary)}
.contact-icon{font-size:32px;flex-shrink:0;width:56px;height:56px;display:flex;align-items:center;justify-content:center;background:var(--gold-soft);border-radius:12px}
.contact-label{display:block;font-size:13px;color:var(--muted);margin-bottom:4px}
.contact-value{display:block;font-size:18px;font-weight:700}
.contact-qr-card{flex-wrap:wrap}
.contact-qr-img{width:110px;height:110px;border-radius:12px;background:#fff;padding:6px;flex-shrink:0}
.contact-qr-hint{font-size:12px !important;font-weight:400 !important;color:var(--muted)}
.contact-form-wrap{background:var(--surface);border:1px solid var(--surface-border);border-radius:var(--radius);padding:28px}
.contact-form-wrap h2{margin:0 0 8px;font-size:22px}
.contact-form-wrap .muted{margin:0 0 20px}
.contact-form-wrap .contact-form label{display:block;margin-bottom:16px;font-weight:600}
.contact-form-wrap .contact-form input,.contact-form-wrap .contact-form textarea{width:100%;margin-top:6px;padding:12px;border:1px solid var(--surface-border);border-radius:10px;background:var(--bg-soft);color:var(--text);font-family:inherit;font-size:15px}
.contact-form-wrap .contact-form textarea{min-height:120px;resize:vertical}
.contact-form-wrap .contact-form .btn{width:100%;padding:14px;font-size:16px}
@media (max-width:768px){
    .contact-grid{grid-template-columns:1fr}
    .contact-hero h1{font-size:26px}
    .contact-form-wrap{padding:20px}
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
    $lenUnit = order_setting('length_unit', 'mm') === 'mm' ? 'mm' : 'cm';
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
        // فیلدهای سفارشی و تنظیمات فرم سفارش (نسخه ۹٫۱)
        $cf = [];
        foreach (get_order_form_fields($cpid) as $ff) {
            $opts = json_decode((string) ($ff['options_json'] ?? '[]'), true);
            if (!is_array($opts)) { $opts = []; }
            $cf[] = [
                'id' => (int) $ff['id'],
                'type' => (string) ($ff['field_type'] ?? 'text'),
                'label' => (string) $ff['label'],
                'options' => array_values(array_filter(array_map('trim', array_map('strval', $opts)))),
                'placeholder' => (string) ($ff['placeholder'] ?? ''),
                'help' => (string) ($ff['help_text'] ?? ''),
                'required' => (int) ($ff['is_required'] ?? 0) === 1,
            ];
        }
        $catalog[$cpid]['custom'] = $cf;
        $catalog[$cpid]['formcfg'] = product_order_form_config($p);
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
    $lineHtml = function ($idx, ?int $selPid, bool $removable) use ($optHtml, $chipsHtml, $step, $wireDef, $wireMax, $lineNoteOn, $lenUnit): string {
        $h = '<div class="so-line" data-line data-idx="' . $idx . '">';
        $h .= '<div class="so-line-head"><span class="so-line-title">چراغ <span data-num>۱</span></span>';
        if ($removable) {
            $h .= '<button type="button" class="so-line-remove" data-remove>حذف این چراغ</button>';
        }
        $h .= '</div>';
        $h .= '<div class="so-field"><label for="so-prod-' . $idx . '">محصول *</label><select id="so-prod-' . $idx . '" name="so_lines[' . $idx . '][product_id]" class="so-product-sel" required>' . $optHtml($selPid) . '</select></div>';
        $h .= $chipsHtml($selPid, $idx);
        $h .= '<div class="so-grid">';
        if ($lenUnit === 'mm') {
            $h .= '<div class="so-field"><label for="so-len-' . $idx . '">طول هر چراغ *</label><div class="so-input-unit"><input type="number" id="so-len-' . $idx . '" name="so_lines[' . $idx . '][length_mm]" step="1" min="1" required inputmode="numeric" data-len><span class="so-unit">میلی‌متر</span></div><p class="so-help" data-meters>طول را به میلی‌متر وارد کنید؛ مثلاً ۲۱۰۰.</p></div>';
        } else {
            $h .= '<div class="so-field"><label for="so-len-' . $idx . '">طول هر چراغ *</label><div class="so-input-unit"><input type="number" id="so-len-' . $idx . '" name="so_lines[' . $idx . '][length_cm]" step="0.1" min="0.1" required inputmode="decimal" data-len><span class="so-unit">سانتی‌متر</span></div><p class="so-help" data-meters>طول را به سانتی‌متر وارد کنید؛ تا یک رقم اعشار.</p></div>';
        }
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
    $html .= '<form method="post" action="/products?id=' . $pid . '#site-order" id="so-form">';
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
    $html .= '<script>window.SO_CATALOG=' . json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS) . ';window.SO_CFG=' . json_encode(['wireStep' => $step, 'wireDef' => $wireDef, 'wireMax' => $wireMax, 'minBill' => $minBill, 'lenUnit' => $lenUnit], JSON_UNESCAPED_UNICODE) . ';window.SO_DEF_PID=' . $pid . ';</script>';
    $html .= <<<'JS'
<script>(function(){var f=document.getElementById('so-form');if(!f)return;var CAT=window.SO_CATALOG||{},CFG=window.SO_CFG||{},DEF=window.SO_DEF_PID||0;var wrap=document.getElementById('so-lines'),tpl=document.getElementById('so-line-tpl'),counter=1;function esc(s){return String(s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}function fa(n,d){return Number(n).toLocaleString('fa-IR',{maximumFractionDigits:d});}function money(n){return Math.round(n).toLocaleString('fa-IR');}function attrsHtml(pid,idx){var p=CAT[pid];if(!p||!p.attrs||!p.attrs.length)return'';var h='';p.attrs.forEach(function(a){h+='<div class="so-field" data-attr-group><span class="so-label">'+esc(a.title)+'</span><div class="so-chips" role="radiogroup" data-attrs>';a.options.forEach(function(o){var d=parseFloat(o.delta)||0;h+='<label class="so-chip"><input type="radio" class="so-option" name="so_lines['+idx+'][options]['+a.id+']" value="'+o.id+'" data-delta="'+d+'"'+(o.id===a.def?' checked':'')+'><span>'+esc(o.title)+(d!==0?(' <small>('+(d>0?'+':'')+fa(d,0)+' تومان/متر)</small>'):'')+'</span></label>';});h+='</div></div>';});return h;}function paintAttrs(line){var sel=line.querySelector('.so-product-sel');if(!sel)return;var idx=line.getAttribute('data-idx');line.querySelectorAll('[data-attr-group]').forEach(function(g){g.remove();});var holder=document.createElement('div');holder.innerHTML=attrsHtml(sel.value,idx);var ref=sel.closest('.so-field');while(holder.firstChild){ref.parentNode.insertBefore(holder.firstChild,ref.nextSibling);}paintCustom(line);applyFormCfg(line);calc();}
function customHtml(pid,idx){var p=CAT[pid];if(!p||!p.custom||!p.custom.length)return'';var h='<div class="so-custom-fields" data-custom-group><h4>مشخصات تکمیلی</h4>';p.custom.forEach(function(f){var nm='so_lines['+idx+'][custom]['+f.id+']';h+='<div class="so-field"><label>'+esc(f.label)+(f.required?' *':'')+'</label>';if(f.type==='select'){h+='<select name="'+nm+'"'+(f.required?' required':'')+'><option value="">— انتخاب کنید —</option>';f.options.forEach(function(o){h+='<option value="'+esc(o)+'">'+esc(o)+'</option>';});h+='</select>';}else if(f.type==='textarea'){h+='<textarea name="'+nm+'" rows="2"'+(f.required?' required':'')+(f.placeholder?' placeholder="'+esc(f.placeholder)+'"':'')+'></textarea>';}else if(f.type==='number'){h+='<input type="number" name="'+nm+'" step="any"'+(f.required?' required':'')+(f.placeholder?' placeholder="'+esc(f.placeholder)+'"':'')+'>';}else if(f.type==='checkbox'){h+='<label class="check"><input type="checkbox" name="'+nm+'" value="1"> '+esc(f.placeholder||'بله')+'</label>';}else{h+='<input type="text" name="'+nm+'"'+(f.required?' required':'')+(f.placeholder?' placeholder="'+esc(f.placeholder)+'"':'')+' maxlength="255">';}if(f.help){h+='<p class="so-help">'+esc(f.help)+'</p>';}h+='</div>';});h+='</div>';return h;}
function paintCustom(line){var sel=line.querySelector('.so-product-sel');if(!sel)return;var idx=line.getAttribute('data-idx');line.querySelectorAll('[data-custom-group]').forEach(function(g){g.remove();});var html=customHtml(sel.value,idx);if(!html)return;var holder=document.createElement('div');holder.innerHTML=html;var total=line.querySelector('[data-line-total]');if(total){line.insertBefore(holder.firstChild,total);}else{line.appendChild(holder.firstChild);}}
function applyFormCfg(line){var sel=line.querySelector('.so-product-sel');if(!sel)return;var p=CAT[sel.value];var cfg=(p&&p.formcfg)?p.formcfg:{show_length:true,show_qty:true,show_wire:true,show_endcap:true,show_options:true};var lenF=line.querySelector('[data-len]');if(lenF){var f=lenF.closest('.so-field');if(f)f.style.display=cfg.show_length?'':'none';lenF.required=!!cfg.show_length;}var qtyF=line.querySelector('[data-qty]');if(qtyF){var qf=qtyF.closest('.so-field');if(qf)qf.style.display=cfg.show_qty?'':'none';}var wireF=line.querySelector('[data-wire]');if(wireF){var wf=wireF.closest('.so-field');if(wf)wf.style.display=cfg.show_wire?'':'none';}var ecF=line.querySelector('input[name$="[endcap]"]');if(ecF){var ef=ecF.closest('.so-field');if(ef)ef.style.display=cfg.show_endcap?'':'none';}line.querySelectorAll('[data-attr-group]').forEach(function(g){g.style.display=cfg.show_options?'':'none';});}function renumber(){var ls=wrap.querySelectorAll('[data-line]');ls.forEach(function(el,k){var n=el.querySelector('[data-num]');if(n)n.textContent=fa(k+1,0);var rm=el.querySelector('[data-remove]');if(rm)rm.style.display=(ls.length>1)?'':'none';});}function lineVals(line){var sel=line.querySelector('.so-product-sel');var pid=sel?sel.value:'0';var p=CAT[pid];var price=p?parseFloat(p.price):0;var d=0;line.querySelectorAll('.so-option:checked').forEach(function(s){d+=parseFloat(s.getAttribute('data-delta')||'0');});var lenEl=line.querySelector('[data-len]');var qtyEl=line.querySelector('[data-qty]');var L=(parseFloat(lenEl?lenEl.value:'')||0)/(CFG.lenUnit==='mm'?1000:100);var Q=parseInt(qtyEl?qtyEl.value:'10',10);if(!(Q>0))Q=0;var minB=parseFloat(CFG.minBill||'0.5');var billOne=Math.max(L,minB);return{L:L,Q:Q,billOne:billOne,unit:price+d,lineTotal:billOne*(price+d)*Q};}function calc(){var totM=0,totQ=0,totP=0;wrap.querySelectorAll('[data-line]').forEach(function(line){var v=lineVals(line);var lenEl=line.querySelector('[data-len]');var mEl=line.querySelector('[data-meters]');if(mEl)mEl.textContent=v.L>0?('≈ '+fa(v.L,2)+' متر'):(CFG.lenUnit==='mm'?'طول را به میلی‌متر وارد کنید؛ مثلاً ۲۱۰۰.':'طول را به سانتی‌متر وارد کنید؛ تا یک رقم اعشار.');var wEl=line.querySelector('[data-wire]');var wv=line.querySelector('[data-wire-val]');if(wEl&&wv)wv.textContent=fa(parseInt(wEl.value,10)||0,0)+' سانت';var lt=line.querySelector('[data-line-total]');if(lt)lt.textContent=(v.L>0&&v.Q>0)?(money(v.lineTotal)+' تومان'):'۰ تومان';if(v.L>0&&v.Q>0){totM+=v.billOne*v.Q;totQ+=v.Q;totP+=v.lineTotal;}});document.getElementById('so-billm').textContent=(totQ>0)?(fa(totM,2)+' متر'):'—';document.getElementById('so-qtyv').textContent=(totQ>0)?(fa(totQ,0)+' چراغ'):'—';document.getElementById('so-total').textContent=money(totP);}function addLine(){var html=tpl.innerHTML.replace(/__I__/g,String(counter));var d=document.createElement('div');d.innerHTML=html;var line=d.firstElementChild;line.setAttribute('data-idx',String(counter));counter++;wrap.appendChild(line);var sel=line.querySelector('.so-product-sel');if(sel&&DEF)sel.value=String(DEF);paintAttrs(line);renumber();calc();if(line.scrollIntoView)line.scrollIntoView({behavior:'smooth',block:'nearest'});}document.getElementById('so-add').addEventListener('click',addLine);f.addEventListener('click',function(e){var rm=e.target.closest('[data-remove]');if(rm){var ls=wrap.querySelectorAll('[data-line]');if(ls.length>1){rm.closest('[data-line]').remove();renumber();calc();}return;}var st=e.target.closest('[data-step]');if(st){var q=st.closest('.so-stepper').querySelector('[data-qty]');var v=parseInt(q.value,10)||1;v=Math.max(1,v+parseInt(st.getAttribute('data-step'),10));q.value=v;calc();return;}if(e.target.closest('#so-toggle')){var s=document.getElementById('so-summary');var open=s.classList.toggle('open');document.getElementById('so-toggle').setAttribute('aria-expanded',open?'true':'false');}});f.addEventListener('input',function(e){calc();});f.addEventListener('change',function(e){var sel=e.target.closest?e.target.closest('.so-product-sel'):null;if(sel){paintAttrs(sel.closest('[data-line]'));}else{calc();}});f.addEventListener('submit',function(){var b=document.getElementById('so-submit');if(b){b.disabled=true;b.textContent='در حال ثبت…';}});wrap.querySelectorAll('[data-line]').forEach(function(line){paintAttrs(line);});renumber();calc();})();</script>
JS;
    $html .= '</div>';
    return $html;
}
