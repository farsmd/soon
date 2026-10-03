<?php
// admin_proposal.php — پروپوزال سرمایه‌گذاری (فقط مدیر کل / owner)
declare(strict_types=1);

function proposal_render_page(): void
{
    ?>
    <style>
    .pz{--gold:#c9a227;--goldl:#e8c547;--bg:#0b0f1a;--mut:#94a3b8;max-width:960px;margin:0 auto;padding:8px 4px 40px;color:#f1f5f9}
    .pz-hero{text-align:center;padding:44px 20px 30px;background:radial-gradient(600px 260px at 50% -60px,rgba(201,162,39,.16),transparent 70%);border:1px solid rgba(201,162,39,.25);border-radius:22px;margin-bottom:22px}
    .pz-hero h1{font-size:30px;font-weight:800;margin-bottom:10px}
    .pz-hero h1 .g{color:var(--goldl)}
    .pz-hero p{color:var(--mut);font-size:15px;line-height:2;max-width:640px;margin:0 auto}
    .pz-badge{display:inline-block;background:rgba(201,162,39,.12);border:1px solid rgba(201,162,39,.4);color:var(--goldl);font-size:12px;font-weight:700;padding:6px 16px;border-radius:99px;margin-bottom:16px}
    .pz-sec{background:#101728;border:1px solid rgba(255,255,255,.07);border-radius:18px;padding:26px;margin-bottom:18px}
    .pz-sec h2{font-size:19px;margin-bottom:14px;display:flex;align-items:center;gap:10px}
    .pz-sec h2 .n{width:30px;height:30px;border-radius:10px;background:rgba(201,162,39,.14);border:1px solid rgba(201,162,39,.35);color:var(--goldl);display:inline-flex;align-items:center;justify-content:center;font-size:15px;font-weight:800;flex-shrink:0}
    .pz-sec p,.pz-sec li{color:#cbd5e1;font-size:14px;line-height:2.1}
    .pz-sec ul{padding-right:20px;margin:0}
    .pz-sec li{margin-bottom:6px}
    .pz-flow{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px}
    .pz-step{flex:1;min-width:120px;background:rgba(201,162,39,.07);border:1px solid rgba(201,162,39,.25);border-radius:14px;padding:14px 10px;text-align:center;font-size:13px;font-weight:700}
    .pz-step small{display:block;color:var(--mut);font-weight:400;font-size:11px;margin-top:4px}
    .pz table{width:100%;border-collapse:collapse;font-size:13px;margin-top:8px}
    .pz th,.pz td{padding:10px 8px;border-bottom:1px solid rgba(255,255,255,.06);text-align:center}
    .pz th{color:var(--goldl);font-size:12px}
    .pz td{color:#cbd5e1}
    .pz td:first-child,.pz th:first-child{text-align:right}
    .pz .y{color:#4ade80;font-weight:800}.pz .n2{color:#f87171}.pz .m{color:var(--goldl)}
    .pz-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-top:6px}
    .pz-stat{background:rgba(201,162,39,.06);border:1px solid rgba(201,162,39,.22);border-radius:14px;padding:16px 10px;text-align:center}
    .pz-stat b{display:block;font-size:24px;color:var(--goldl);margin-bottom:4px}
    .pz-stat span{font-size:12px;color:var(--mut)}
    .pz-road{display:flex;flex-direction:column;gap:0;margin-top:6px}
    .pz-ph{display:flex;gap:14px;padding:14px 0;border-right:2px solid rgba(201,162,39,.3);padding-right:18px;position:relative}
    .pz-ph::before{content:"";position:absolute;right:-7px;top:20px;width:12px;height:12px;border-radius:50%;background:var(--gold)}
    .pz-ph b{font-size:14px;color:var(--goldl);display:block;margin-bottom:2px}
    .pz-ph span{font-size:13px;color:#cbd5e1;line-height:1.9}
    .pz-cta{text-align:center;background:linear-gradient(135deg,rgba(201,162,39,.14),rgba(201,162,39,.04));border:1px solid rgba(201,162,39,.4);border-radius:20px;padding:34px 22px}
    .pz-cta h2{justify-content:center;font-size:22px}
    .pz-cta p{max-width:560px;margin:0 auto;color:#cbd5e1}
    @media(max-width:600px){.pz-hero h1{font-size:23px}.pz-sec{padding:20px 16px}}
    </style>
    <div class="pz">
        <div class="pz-hero">
            <div class="pz-badge">محرمانه — فقط مدیر کل</div>
            <h1>سیستم مدیریت یکپارچه <span class="g">لاینرلایت</span></h1>
            <p>ما یه سیستم ساختیم که یه کارگاه تولیدی را از صفر تا صد مدیریت می‌کنه — از سفارش مشتری تا حقوق پرسنل. لاینرلایت نمونه زنده‌شه؛ و این سیستم برای هر بیزینس مشابه دیگه هم با چند کلیک راه می‌افته.</p>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۱</span> مسئله</h2>
            <ul>
                <li>کارگاه‌های تولیدی با اکسل و دفتر مدیریت می‌شن؛ قیمت‌گذاری حدسیه، بدون اطلاع از بهای تمام‌شده واقعی</li>
                <li>سفارش‌ها گم می‌شن یا دیر تحویل داده می‌شن؛ پرت مواد سود را می‌بلعد</li>
                <li>نرم‌افزارهای موجود یا بیش از حد عمومی‌اند (حسابداری صرف) یا بیش از حد گران (ERP صنعتی)</li>
            </ul>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۲</span> راه‌حل</h2>
            <div class="pz-flow">
                <div class="pz-step">🛒 سفارش آنلاین<small>ثبت توسط مشتری</small></div>
                <div class="pz-step">🧾 پیش‌فاکتور<small>خودکار و چاپی</small></div>
                <div class="pz-step">📐 بهینه‌سازی برش<small>کمترین پرت</small></div>
                <div class="pz-step">🏭 تولید مرحله‌ای<small>برش تا بسته‌بندی</small></div>
                <div class="pz-step">💰 مالی و حقوق<small>یکپارچه</small></div>
            </div>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۳</span> مزیت رقابتی</h2>
            <table>
                <tr><th>ویژگی</th><th>لاینرلایت</th><th>حسابداری سنتی</th><th>ERP صنعتی</th></tr>
                <tr><td>بهای تمام‌شده خودکار (BOM)</td><td class="y">✓</td><td class="n2">✗</td><td class="y">✓</td></tr>
                <tr><td>بهینه‌سازی برش و مدیریت پرت</td><td class="y">✓</td><td class="n2">✗</td><td class="n2">✗</td></tr>
                <tr><td>سفارش‌گیری آنلاین یکپارچه</td><td class="y">✓</td><td class="n2">✗</td><td class="n2">✗</td></tr>
                <tr><td>هزینه راه‌اندازی</td><td class="m">کم</td><td class="m">کم</td><td class="n2">بسیار بالا</td></tr>
                <tr><td>نیاز به سرور اختصاصی</td><td class="y">✗</td><td class="y">✗</td><td class="n2">✓</td></tr>
            </table>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۴</span> مدل کسب‌وکار — دو موتور</h2>
            <ul>
                <li><b style="color:var(--goldl)">موتور ۱:</b> خود بیزینس لاینرلایت — تولید چراغ‌های خطی و نور رشد گیاه؛ سیستم حاشیه سود را با قیمت‌گذاری دقیق و کاهش پرت بالا می‌برد</li>
                <li><b style="color:var(--goldl)">موتور ۲:</b> فروش/اجاره سیستم به بیزینس‌های مشابه — ویزارد راه‌اندازی ۴ مرحله‌ای؛ هر کارگاه در چند دقیقه صاحب نسخه اختصاصی با برند خودش می‌شود (SaaS / لایسنس)</li>
            </ul>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۵</span> وضعیت فعلی</h2>
            <div class="pz-stats">
                <div class="pz-stat"><b>۹٫۱۱٫۴</b><span>نسخه عملیاتی</span></div>
                <div class="pz-stat"><b>+۱۲</b><span>ماژول یکپارچه</span></div>
                <div class="pz-stat"><b>۵mm</b><span>پرت هر برش (قانون کارگاه)</span></div>
                <div class="pz-stat"><b>۴</b><span>قدم تا راه‌اندازی مجدد</span></div>
            </div>
            <p style="margin-top:12px">نمونه زنده: <b dir="ltr">linerlight.ir</b> — در حال کار و سفارش‌گیری واقعی. سفارش، تولید، انبار و BOM، مالی، منابع انسانی، تجهیزات، لاگ، کاربران و نقش‌ها، آپدیتر یک‌کلیکی.</p>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۶</span> نقشه راه</h2>
            <div class="pz-road">
                <div class="pz-ph"><div><b>فاز ۱ — ۳ ماه</b><span>اعلان‌های لحظه‌ای، PWA، پیامک مشتری</span></div></div>
                <div class="pz-ph"><div><b>فاز ۲ — ۶ ماه</b><span>۱۰ کارگاه پایلوت همکار، مدل اشتراک ماهانه</span></div></div>
                <div class="pz-ph"><div><b>فاز ۳ — ۱۲ ماه</b><span>مارکت‌پلیس قالب‌های صنعتی (فرمول‌های آماده هر صنف)</span></div></div>
            </div>
        </div>

        <div class="pz-sec">
            <h2><span class="n">۷</span> چرا حالا؟</h2>
            <ul>
                <li>سیستم از مرحله ایده گذشته؛ محصول واقعی با کاربر واقعی است</li>
                <li>دانش عمیق عملیات کارگاهی (پرت برش، انبار پرتی، قیمت‌گذاری همکار) در کد نهادینه شده — کپی‌کردنی نیست</li>
                <li>بازار هدف هیچ راه‌حل بومی یکپارچه‌ای ندارد</li>
            </ul>
        </div>

        <div class="pz-cta">
            <h2>💰 درخواست</h2>
            <p>جذب سرمایه برای توسعه تیم فنی، بازاریابی به کارگاه‌های همکار، و تبدیل سیستم به پلتفرم چندمستأجری (SaaS).</p>
        </div>
    </div>
    <?php
}
