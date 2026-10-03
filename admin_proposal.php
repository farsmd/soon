<?php
// admin_proposal.php — پروپوزال تعاملی سرمایه‌گذاری (فقط مدیر کل / owner)
declare(strict_types=1);

function proposal_render_page(): void
{
    ?>
    <style>
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('assets/fonts/Vazirmatn-Regular.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('assets/fonts/Vazirmatn-Medium.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('assets/fonts/Vazirmatn-Bold.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:800;font-display:swap;src:url('assets/fonts/Vazirmatn-ExtraBold.woff2') format('woff2')}
    </style>
    <style>
    .pzx{--gold:#c9a227;--goldl:#e8c547;--bg:#0b0f1a;--card:#111a2e;--mut:#94a3b8;--txt:#f1f5f9;
      font-family:Vazirmatn,Tahoma,sans-serif;color:var(--txt);max-width:1020px;margin:0 auto;padding-bottom:50px}
    .pzx *{box-sizing:border-box}
    /* ناوبری چسبان */
    .pzx-nav{position:sticky;top:0;z-index:20;display:flex;gap:8px;overflow-x:auto;padding:12px 4px;margin-bottom:18px;
      background:rgba(11,15,26,.88);backdrop-filter:blur(10px);border-bottom:1px solid rgba(201,162,39,.18)}
    .pzx-nav a{white-space:nowrap;font-size:12.5px;color:var(--mut);text-decoration:none;padding:8px 14px;border-radius:99px;border:1px solid transparent;transition:.2s}
    .pzx-nav a.on,.pzx-nav a:hover{color:var(--goldl);border-color:rgba(201,162,39,.45);background:rgba(201,162,39,.08)}
    .pzx-bar{height:3px;background:linear-gradient(90deg,var(--gold),var(--goldl));width:0;position:sticky;top:0;z-index:21}
    /* هیرو */
    .pzx-hero{text-align:center;padding:60px 24px 44px;border-radius:26px;margin-bottom:20px;position:relative;overflow:hidden;
      background:radial-gradient(700px 320px at 50% -80px,rgba(201,162,39,.18),transparent 70%),linear-gradient(160deg,#131b31,#0b0f1a);
      border:1px solid rgba(201,162,39,.3)}
    .pzx-hero::after{content:"";position:absolute;top:0;right:10%;left:10%;height:3px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
    .pzx-badge{display:inline-block;font-size:12px;font-weight:700;color:var(--goldl);border:1px solid rgba(201,162,39,.45);
      background:rgba(201,162,39,.1);padding:7px 18px;border-radius:99px;margin-bottom:18px}
    .pzx-hero h1{font-size:34px;font-weight:900;margin:0 0 14px;line-height:1.6}
    .pzx-hero h1 .g{background:linear-gradient(135deg,var(--goldl),var(--gold));-webkit-background-clip:text;background-clip:text;color:transparent}
    .pzx-hero p{color:var(--mut);font-size:15.5px;line-height:2.2;max-width:660px;margin:0 auto}
    .pzx-hero .stats{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:28px}
    .pzx-hstat{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:14px 22px;min-width:130px}
    .pzx-hstat b{display:block;font-size:26px;color:var(--goldl);font-weight:800}
    .pzx-hstat span{font-size:12px;color:var(--mut)}
    /* سکشن */
    .pzx-sec{background:var(--card);border:1px solid rgba(255,255,255,.07);border-radius:22px;padding:34px 30px;margin-bottom:18px;scroll-margin-top:90px;
      opacity:0;transform:translateY(28px);transition:opacity .7s ease,transform .7s ease}
    .pzx-sec.vis{opacity:1;transform:none}
    .pzx-sec h2{font-size:21px;margin:0 0 8px;display:flex;align-items:center;gap:12px}
    .pzx-sec h2 .n{width:34px;height:34px;border-radius:11px;background:rgba(201,162,39,.14);border:1px solid rgba(201,162,39,.4);
      color:var(--goldl);display:inline-flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
    .pzx-sub{color:var(--mut);font-size:13.5px;margin:0 0 20px;line-height:2}
    /* کارت‌های مسئله */
    .pzx-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
    .pzx-card{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px;transition:.25s}
    .pzx-card:hover{border-color:rgba(201,162,39,.5);transform:translateY(-4px);box-shadow:0 14px 34px rgba(0,0,0,.35)}
    .pzx-card .e{font-size:28px;margin-bottom:10px;display:block}
    .pzx-card b{display:block;font-size:14.5px;margin-bottom:6px;color:var(--txt)}
    .pzx-card p{font-size:13px;color:var(--mut);line-height:2;margin:0}
    /* فلو تعاملی */
    .pzx-flow{display:flex;gap:6px;align-items:stretch;margin-bottom:16px;overflow-x:auto;padding-bottom:6px}
    .pzx-step{flex:1;min-width:110px;text-align:center;padding:16px 8px;border-radius:16px;cursor:pointer;border:1px solid rgba(255,255,255,.09);
      background:rgba(255,255,255,.025);transition:.25s;position:relative}
    .pzx-step .e{font-size:26px;display:block;margin-bottom:8px}
    .pzx-step b{font-size:13px;display:block}
    .pzx-step small{color:var(--mut);font-size:11px}
    .pzx-step.on{border-color:var(--gold);background:rgba(201,162,39,.1);box-shadow:0 0 0 1px var(--gold),0 10px 30px rgba(201,162,39,.15)}
    .pzx-step:not(:last-child)::after{content:"◀";position:absolute;left:-11px;top:50%;transform:translateY(-50%);color:var(--gold);font-size:12px;z-index:2}
    .pzx-detail{background:rgba(201,162,39,.06);border:1px solid rgba(201,162,39,.3);border-radius:16px;padding:20px 22px;min-height:96px}
    .pzx-detail b{color:var(--goldl);font-size:15px;display:block;margin-bottom:6px}
    .pzx-detail p{color:#cbd5e1;font-size:13.5px;line-height:2.1;margin:0}
    /* جدول مقایسه */
    .pzx-filters{display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap}
    .pzx-f{font-size:12.5px;padding:8px 16px;border-radius:99px;border:1px solid rgba(255,255,255,.12);background:transparent;color:var(--mut);cursor:pointer;transition:.2s;font-family:inherit}
    .pzx-f.on{background:rgba(201,162,39,.14);border-color:var(--gold);color:var(--goldl);font-weight:700}
    .pzx-twrap{overflow-x:auto;border-radius:14px;border:1px solid rgba(255,255,255,.08)}
    .pzx table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:520px}
    .pzx th,.pzx td{padding:13px 12px;text-align:center;border-bottom:1px solid rgba(255,255,255,.05)}
    .pzx th{background:rgba(201,162,39,.08);color:var(--goldl);font-size:12.5px}
    .pzx td:first-child,.pzx th:first-child{text-align:right;font-weight:700;color:var(--txt)}
    .pzx tr.hl td{background:rgba(201,162,39,.07)}
    .pzx .y{color:#4ade80;font-weight:800;font-size:17px}.pzx .n2{color:#f87171;font-size:15px}.pzx .mm{color:var(--goldl);font-weight:700}
    /* موتورها */
    .pzx-eng{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:14px}
    .pzx-en{background:linear-gradient(150deg,rgba(201,162,39,.1),rgba(201,162,39,.02));border:1px solid rgba(201,162,39,.3);border-radius:18px;padding:24px;transition:.25s}
    .pzx-en:hover{transform:translateY(-4px);box-shadow:0 16px 40px rgba(201,162,39,.12)}
    .pzx-en .e{font-size:32px;display:block;margin-bottom:12px}
    .pzx-en b{font-size:16px;color:var(--goldl);display:block;margin-bottom:8px}
    .pzx-en p{font-size:13.5px;color:#cbd5e1;line-height:2.1;margin:0}
    /* شمارنده‌ها */
    .pzx-nums{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px}
    .pzx-num{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.08);border-radius:18px;padding:24px 12px;text-align:center}
    .pzx-num b{display:block;font-size:34px;font-weight:900;color:var(--goldl);margin-bottom:6px;font-variant-numeric:tabular-nums}
    .pzx-num span{font-size:12.5px;color:var(--mut);line-height:1.9}
    /* نقشه راه */
    .pzx-road{position:relative;padding-right:26px}
    .pzx-road::before{content:"";position:absolute;right:8px;top:10px;bottom:10px;width:2px;background:linear-gradient(var(--gold),rgba(201,162,39,.1))}
    .pzx-ph{position:relative;margin-bottom:12px}
    .pzx-ph::before{content:"";position:absolute;right:-24px;top:22px;width:14px;height:14px;border-radius:50%;background:var(--bg);border:3px solid var(--gold)}
    .pzx-phh{background:rgba(255,255,255,.025);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:18px 20px;cursor:pointer;transition:.25s;display:flex;justify-content:space-between;align-items:center;gap:10px}
    .pzx-phh:hover{border-color:rgba(201,162,39,.45)}
    .pzx-ph.open .pzx-phh{border-color:var(--gold);background:rgba(201,162,39,.07)}
    .pzx-phh b{font-size:15px;color:var(--goldl)}
    .pzx-phh .tg{color:var(--mut);font-size:12px;background:rgba(255,255,255,.05);padding:4px 12px;border-radius:99px;flex-shrink:0}
    .pzx-phb{max-height:0;overflow:hidden;transition:max-height .35s ease}
    .pzx-ph.open .pzx-phb{max-height:220px}
    .pzx-phb p{font-size:13.5px;color:#cbd5e1;line-height:2.1;margin:0;padding:14px 4px 4px}
    .pzx-phb ul{margin:0;padding:0 20px 6px 0;font-size:13px;color:#cbd5e1;line-height:2}
    /* چرا حالا */
    .pzx-why{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
    .pzx-w{background:rgba(74,222,128,.05);border:1px solid rgba(74,222,128,.25);border-radius:16px;padding:20px}
    .pzx-w .e{font-size:26px;display:block;margin-bottom:8px}
    .pzx-w p{font-size:13px;color:#cbd5e1;line-height:2;margin:0}
    /* CTA */
    .pzx-cta{text-align:center;border-radius:24px;padding:48px 26px;background:radial-gradient(500px 240px at 50% 0,rgba(201,162,39,.2),transparent 70%),linear-gradient(160deg,#141c33,#0b0f1a);border:1px solid rgba(201,162,39,.4)}
    .pzx-cta h2{font-size:26px;margin:0 0 12px;justify-content:center}
    .pzx-cta p{color:#cbd5e1;font-size:14.5px;line-height:2.2;max-width:600px;margin:0 auto}
    .pzx-cta .ask{display:inline-block;margin-top:20px;font-size:13px;color:var(--goldl);border:1px dashed rgba(201,162,39,.5);padding:10px 22px;border-radius:99px}
    .pzx-foot{text-align:center;color:var(--mut);font-size:11.5px;margin-top:22px}
    @media(max-width:640px){.pzx-hero h1{font-size:25px}.pzx-sec{padding:24px 18px}.pzx-step{min-width:96px}}
    </style>

    <div class="pzx" id="pzx">
        <div class="pzx-bar" id="pzxBar"></div>
        <nav class="pzx-nav" id="pzxNav">
            <a href="#pz-1">مسئله</a><a href="#pz-2">راه‌حل</a><a href="#pz-3">مزیت رقابتی</a><a href="#pz-4">مدل کسب‌وکار</a><a href="#pz-5">وضعیت فعلی</a><a href="#pz-6">نقشه راه</a><a href="#pz-7">چرا حالا</a><a href="#pz-8">درخواست</a>
        </nav>

        <div class="pzx-hero">
            <div class="pzx-badge">🔒 محرمانه — فقط مدیر کل</div>
            <h1>سیستم مدیریت یکپارچه<br><span class="g">لاینرلایت</span></h1>
            <p>ما یه سیستم ساختیم که یه کارگاه تولیدی را از صفر تا صد مدیریت می‌کنه — از سفارش مشتری تا حقوق پرسنل. لاینرلایت نمونه زنده‌شه؛ و این سیستم برای هر بیزینس مشابه دیگه هم با چند کلیک راه می‌افته.</p>
            <div class="stats">
                <div class="pzx-hstat"><b>۹٫۱۲٫۱</b><span>نسخه عملیاتی</span></div>
                <div class="pzx-hstat"><b>+۱۲</b><span>ماژول یکپارچه</span></div>
                <div class="pzx-hstat"><b>۵mm</b><span>دقت پرت برش</span></div>
            </div>
        </div>

        <div class="pzx-sec" id="pz-1">
            <h2><span class="n">۱</span> مسئله</h2>
            <p class="pzx-sub">کارگاه‌های تولیدی کوچک و متوسط در ایران با اکسل، دفتر و حافظه مدیریت می‌شوند.</p>
            <div class="pzx-cards">
                <div class="pzx-card"><span class="e">🎯</span><b>قیمت‌گذاری حدسی</b><p>بدون اطلاع از بهای تمام‌شده واقعی؛ سود واقعی نامشخص است.</p></div>
                <div class="pzx-card"><span class="e">📦</span><b>سفارش‌های گمشده</b><p>سفارش‌ها گم می‌شوند یا دیر تحویل داده می‌شوند؛ مشتری ناراضی.</p></div>
                <div class="pzx-card"><span class="e">✂️</span><b>پرت پنهان</b><p>پرت مواد و هزینه‌های پنهان، بی‌صدا سود را می‌بلعد.</p></div>
                <div class="pzx-card"><span class="e">🧩</span><b>خلأ بازار</b><p>نرم‌افزارها یا بیش از حد عمومی‌اند یا بیش از حد گران (ERP صنعتی).</p></div>
            </div>
        </div>

        <div class="pzx-sec" id="pz-2">
            <h2><span class="n">۲</span> راه‌حل — روی هر مرحله بزن</h2>
            <p class="pzx-sub">یک چرخه یکپارچه: از کلیک مشتری تا سود. هیچ کاغذبازی نیست.</p>
            <div class="pzx-flow" id="pzxFlow">
                <div class="pzx-step on" data-t="سفارش آنلاین" data-d="مشتری بدون تماس تلفنی، با فرم هوشمند سفارش ثبت می‌کند؛ قیمت لحظه‌ای محاسبه می‌شود و اعلان برای کارشناس می‌رود."><span class="e">🛒</span><b>سفارش</b><small>آنلاین</small></div>
                <div class="pzx-step" data-t="پیش‌فاکتور خودکار" data-d="بلافاصله بعد از ثبت، پیش‌فاکتور رسمی با تخفیف همکار پلکانی صادر و قابل چاپ است."><span class="e">🧾</span><b>پیش‌فاکتور</b><small>خودکار</small></div>
                <div class="pzx-step" data-t="بهینه‌سازی برش" data-d="الگوریتم برش، پروفیل ۳ متری و ریسه ۵ متری را طوری می‌برد که پرت به حداقل برسد؛ پرتی‌های بالای ۲۰ سانت به انبار برمی‌گردد."><span class="e">📐</span><b>برش بهینه</b><small>کمترین پرت</small></div>
                <div class="pzx-step" data-t="تولید مرحله‌ای" data-d="برگه تولید با مراحل برش، مونتاژ، تست و بسته‌بندی؛ هر مرحله ثبت و قابل پیگیری است."><span class="e">🏭</span><b>تولید</b><small>مرحله‌ای</small></div>
                <div class="pzx-step" data-t="مالی یکپارچه" data-d="درآمد، هزینه، حقوق پرسنل و استهلاک تجهیزات — همه در یک داشبورد؛ سود هر سفارش شفاف است."><span class="e">💰</span><b>مالی</b><small>یکپارچه</small></div>
            </div>
            <div class="pzx-detail"><b id="pzxDT">سفارش آنلاین</b><p id="pzxDD">مشتری بدون تماس تلفنی، با فرم هوشمند سفارش ثبت می‌کند؛ قیمت لحظه‌ای محاسبه می‌شود و اعلان برای کارشناس می‌رود.</p></div>
        </div>

        <div class="pzx-sec" id="pz-3">
            <h2><span class="n">۳</span> مزیت رقابتی</h2>
            <p class="pzx-sub">فیلتر کن و مقایسه را ببین:</p>
            <div class="pzx-filters" id="pzxFilters">
                <button class="pzx-f on" data-f="all">همه</button>
                <button class="pzx-f" data-f="win">فقط برتری‌های ما</button>
                <button class="pzx-f" data-f="cost">هزینه و زیرساخت</button>
            </div>
            <div class="pzx-twrap"><table id="pzxTable">
                <tr><th>ویژگی</th><th>لاینرلایت</th><th>حسابداری سنتی</th><th>ERP صنعتی</th></tr>
                <tr data-c="win"><td>بهای تمام‌شده خودکار (BOM)</td><td class="y">✓</td><td class="n2">✗</td><td class="y">✓</td></tr>
                <tr data-c="win"><td>بهینه‌سازی برش و مدیریت پرت</td><td class="y">✓</td><td class="n2">✗</td><td class="n2">✗</td></tr>
                <tr data-c="win"><td>سفارش‌گیری آنلاین یکپارچه</td><td class="y">✓</td><td class="n2">✗</td><td class="n2">✗</td></tr>
                <tr data-c="win"><td>قیمت‌گذاری همکار پلکانی</td><td class="y">✓</td><td class="n2">✗</td><td class="n2">✗</td></tr>
                <tr data-c="cost"><td>هزینه راه‌اندازی</td><td class="mm">کم</td><td class="mm">کم</td><td class="n2">بسیار بالا</td></tr>
                <tr data-c="cost"><td>نیاز به سرور اختصاصی</td><td class="y">✗</td><td class="y">✗</td><td class="n2">✓ لازم</td></tr>
            </table></div>
        </div>

        <div class="pzx-sec" id="pz-4">
            <h2><span class="n">۴</span> مدل کسب‌وکار — دو موتور</h2>
            <p class="pzx-sub">یک محصول، دو جریان درآمدی.</p>
            <div class="pzx-eng">
                <div class="pzx-en"><span class="e">💡</span><b>موتور ۱ — خود بیزینس</b><p>تولید چراغ‌های خطی و نور رشد گیاه. سیستم با قیمت‌گذاری دقیق، کاهش پرت و حذف خطا، حاشیه سود را بالا می‌برد.</p></div>
                <div class="pzx-en"><span class="e">🚀</span><b>موتور ۲ — فروش سیستم</b><p>ویزارد راه‌اندازی ۴ مرحله‌ای: هر کارگاه مشابه در چند دقیقه صاحب نسخه اختصاصی با برند خودش می‌شود. مدل SaaS / لایسنس.</p></div>
            </div>
        </div>

        <div class="pzx-sec" id="pz-5">
            <h2><span class="n">۵</span> وضعیت فعلی</h2>
            <p class="pzx-sub">نمونه زنده: <b dir="ltr" style="color:var(--goldl)">linerlight.ir</b> — در حال کار و سفارش‌گیری واقعی.</p>
            <div class="pzx-nums">
                <div class="pzx-num"><b><span class="cnt" data-n="12">۰</span>+</b><span>ماژول یکپارچه<br>سفارش تا حقوق</span></div>
                <div class="pzx-num"><b><span class="cnt" data-n="9">۰</span></b><span>نسخه اصلی<br>منتشرشده</span></div>
                <div class="pzx-num"><b><span class="cnt" data-n="5">۰</span>mm</b><span>پرت هر برش<br>قانون مستند کارگاه</span></div>
                <div class="pzx-num"><b><span class="cnt" data-n="4">۰</span></b><span>قدم تا راه‌اندازی<br>برای بیزینس تازه</span></div>
            </div>
        </div>

        <div class="pzx-sec" id="pz-6">
            <h2><span class="n">۶</span> نقشه راه — باز کن</h2>
            <p class="pzx-sub">روی هر فاز بزن تا جزئیاتش را ببینی.</p>
            <div class="pzx-road" id="pzxRoad">
                <div class="pzx-ph open"><div class="pzx-phh"><b>فاز ۱ — ۳ ماه آینده</b><span class="tg">نزدیک</span></div>
                    <div class="pzx-phb"><ul><li>اعلان‌های لحظه‌ای (شروع شده ✓)</li><li>اپلیکیشن موبایل PWA (شروع شده ✓)</li><li>پیامک خودکار به مشتری</li></ul></div></div>
                <div class="pzx-ph"><div class="pzx-phh"><b>فاز ۲ — ۶ ماه آینده</b><span class="tg">رشد</span></div>
                    <div class="pzx-phb"><ul><li>۱۰ کارگاه پایلوت از همکاران</li><li>مدل اشتراک ماهانه</li><li>پشتیبانی و آموزش آنلاین</li></ul></div></div>
                <div class="pzx-ph"><div class="pzx-phh"><b>فاز ۳ — ۱۲ ماه آینده</b><span class="tg">مقیاس</span></div>
                    <div class="pzx-phb"><ul><li>مارکت‌پلیس قالب‌های صنعتی</li><li>فرمول‌های آماده برای هر صنف</li><li>پلتفرم چندمستأجری (SaaS)</li></ul></div></div>
            </div>
        </div>

        <div class="pzx-sec" id="pz-7">
            <h2><span class="n">۷</span> چرا حالا؟</h2>
            <div class="pzx-why">
                <div class="pzx-w"><span class="e">✅</span><p>سیستم از مرحله ایده گذشته؛ <b>محصول واقعی با کاربر واقعی</b> است.</p></div>
                <div class="pzx-w"><span class="e">🧠</span><p>دانش عمیق عملیات کارگاهی در کد نهادینه شده — <b>کپی‌کردنی نیست</b>.</p></div>
                <div class="pzx-w"><span class="e">🎯</span><p>بازار هدف (هزاران کارگاه) <b>هیچ راه‌حل بومی یکپارچه‌ای</b> ندارد.</p></div>
            </div>
        </div>

        <div class="pzx-cta" id="pz-8">
            <h2>💰 درخواست سرمایه</h2>
            <p>جذب سرمایه برای توسعه تیم فنی، بازاریابی به کارگاه‌های همکار، و تبدیل سیستم به پلتفرم چندمستأجری (SaaS).</p>
            <div class="ask">لاینرلایت — مهر ۱۴۰۵</div>
        </div>
        <div class="pzx-foot">این صفحه فقط برای مدیر کل قابل مشاهده است.</div>
    </div>

    <script>
    (function(){
      var root=document.getElementById('pzx'); if(!root) return;
      // reveal on scroll
      var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('vis');io.unobserve(e.target);}})},{threshold:.12});
      root.querySelectorAll('.pzx-sec').forEach(function(s){io.observe(s)});
      // progress bar + nav highlight
      var bar=document.getElementById('pzxBar'), nav=document.getElementById('pzxNav');
      var links=nav.querySelectorAll('a'), secs=['pz-1','pz-2','pz-3','pz-4','pz-5','pz-6','pz-7','pz-8'].map(function(id){return document.getElementById(id)});
      function onScroll(){
        var h=document.documentElement, p=h.scrollTop/(h.scrollHeight-h.clientHeight)*100;
        bar.style.width=p+'%';
        var cur=0; secs.forEach(function(s,i){if(s&&s.getBoundingClientRect().top<140)cur=i});
        links.forEach(function(a,i){a.classList.toggle('on',i===cur)});
      }
      window.addEventListener('scroll',onScroll,{passive:true}); onScroll();
      // interactive flow
      var steps=root.querySelectorAll('#pzxFlow .pzx-step'), dt=document.getElementById('pzxDT'), dd=document.getElementById('pzxDD');
      steps.forEach(function(s){s.addEventListener('click',function(){
        steps.forEach(function(x){x.classList.remove('on')}); s.classList.add('on');
        dt.textContent=s.getAttribute('data-t'); dd.textContent=s.getAttribute('data-d');
      })});
      // table filters
      var fs=root.querySelectorAll('#pzxFilters .pzx-f'), rows=root.querySelectorAll('#pzxTable tr[data-c]');
      fs.forEach(function(f){f.addEventListener('click',function(){
        fs.forEach(function(x){x.classList.remove('on')}); f.classList.add('on');
        var v=f.getAttribute('data-f');
        rows.forEach(function(r){
          var show=(v==='all')||r.getAttribute('data-c')===v;
          r.style.display=show?'':'none';
          r.classList.toggle('hl',v!=='all'&&show);
        });
      })});
      // roadmap accordion
      root.querySelectorAll('#pzxRoad .pzx-phh').forEach(function(h){h.addEventListener('click',function(){
        h.parentElement.classList.toggle('open');
      })});
      // animated counters (fa digits)
      var fa='۰۱۲۳۴۵۶۷۸۹', cio=new IntersectionObserver(function(es){es.forEach(function(e){
        if(!e.isIntersecting)return; cio.unobserve(e.target);
        var el=e.target, n=parseInt(el.getAttribute('data-n'),10), t0=null;
        function fr(t){if(!t0)t0=t; var p=Math.min((t-t0)/1200,1), v=Math.round(n*(1-Math.pow(1-p,3)));
          el.textContent=String(v).replace(/\d/g,function(d){return fa[d]}); if(p<1)requestAnimationFrame(fr);}
        requestAnimationFrame(fr);
      })},{threshold:.4});
      root.querySelectorAll('.cnt').forEach(function(c){cio.observe(c)});
    })();
    </script>
    <?php
}
