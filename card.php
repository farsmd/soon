<?php
// card.php — کارت ویزیت دیجیتال لاینرلایت (نسخه ۹٫۱۱)
// با اسکن QR باز می‌شود؛ دکمه «ذخیره مخاطب» فایل vCard می‌دهد.
declare(strict_types=1);

const CARD_NAME     = 'نام شما'; // ← نام را اینجا بنویسید
const CARD_TITLE    = 'طراح روشنایی';
const CARD_ORG      = 'لاینرلایت';
const CARD_PHONE    = '+989366121221';
const CARD_PHONE_S  = '0936 612 1221';
const CARD_EMAIL    = 'info@linerlight.ir';
const CARD_SITE     = 'https://linerlight.ir';
const CARD_SITE_S   = 'linerlight.ir';

// --- دانلود vCard ---
if (isset($_GET['vcard'])) {
    $v  = "BEGIN:VCARD\r\nVERSION:3.0\r\n";
    $v .= 'FN:' . CARD_NAME . "\r\n";
    $v .= 'ORG:' . CARD_ORG . "\r\n";
    $v .= 'TITLE:' . CARD_TITLE . "\r\n";
    $v .= 'TEL;TYPE=CELL,VOICE:' . CARD_PHONE . "\r\n";
    $v .= 'EMAIL;TYPE=WORK:' . CARD_EMAIL . "\r\n";
    $v .= 'URL:' . CARD_SITE . "\r\n";
    $v .= "END:VCARD\r\n";
    header('Content-Type: text/vcard; charset=utf-8');
    header('Content-Disposition: attachment; filename="linerlight-contact.vcf"');
    header('Content-Length: ' . strlen($v));
    echo $v;
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0b0f1a">
<title><?= htmlspecialchars(CARD_NAME, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars(CARD_ORG, ENT_QUOTES, 'UTF-8') ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--gold:#c9a227;--gold-l:#e8c547;--bg:#0b0f1a;--card:#121828;--txt:#f1f5f9;--mut:#94a3b8}
body{background:var(--bg);color:var(--txt);font-family:"Vazirmatn","IRANSans","Segoe UI",Tahoma,sans-serif;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:20px;
background-image:radial-gradient(600px 300px at 50% -80px,rgba(201,162,39,.14),transparent 70%)}
.card{background:linear-gradient(160deg,#141b30,#0d1322);border:1px solid rgba(201,162,39,.28);border-radius:24px;max-width:380px;width:100%;padding:34px 26px 28px;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.55),inset 0 1px 0 rgba(255,255,255,.06);position:relative;overflow:hidden}
.card::before{content:"";position:absolute;top:0;right:0;left:0;height:4px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
.logo{width:74px;height:74px;border-radius:22px;margin:0 auto 16px;background:linear-gradient(145deg,#1a2340,#0d1322);border:1px solid rgba(201,162,39,.4);display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;color:var(--gold);box-shadow:0 8px 24px rgba(201,162,39,.18)}
h1{font-size:24px;font-weight:800;margin-bottom:4px}
.role{color:var(--gold-l);font-size:14px;margin-bottom:2px}
.org{color:var(--mut);font-size:13px;margin-bottom:20px}
.divider{height:1px;background:linear-gradient(90deg,transparent,rgba(201,162,39,.4),transparent);margin:0 10px 20px}
.rows{display:flex;flex-direction:column;gap:10px;margin-bottom:22px;text-align:right}
.row{display:flex;align-items:center;gap:12px;background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:12px 14px;text-decoration:none;color:var(--txt);transition:.2s}
.row:active{transform:scale(.98)}
.row .ic{width:38px;height:38px;flex-shrink:0;border-radius:12px;background:rgba(201,162,39,.12);border:1px solid rgba(201,162,39,.3);display:flex;align-items:center;justify-content:center;color:var(--gold)}
.row .ic svg{width:19px;height:19px}
.row .tx small{display:block;color:var(--mut);font-size:11px;margin-bottom:2px}
.row .tx b{font-size:14px;font-weight:600}
.row .tx b[dir="ltr"]{letter-spacing:.3px}
.save{display:block;width:100%;padding:15px;border:none;border-radius:16px;background:linear-gradient(135deg,var(--gold-l),var(--gold));color:#1a1405;font-size:16px;font-weight:800;cursor:pointer;text-decoration:none;box-shadow:0 10px 28px rgba(201,162,39,.35);transition:.2s}
.save:active{transform:scale(.97)}
.foot{margin-top:16px;color:var(--mut);font-size:11px}
.foot a{color:var(--gold-l);text-decoration:none}
</style>
</head>
<body>
<main class="card">
    <div class="logo">L</div>
    <h1><?= htmlspecialchars(CARD_NAME, ENT_QUOTES, 'UTF-8') ?></h1>
    <div class="role"><?= htmlspecialchars(CARD_TITLE, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="org"><?= htmlspecialchars(CARD_ORG, ENT_QUOTES, 'UTF-8') ?></div>
    <div class="divider"></div>
    <div class="rows">
        <a class="row" href="tel:<?= htmlspecialchars(CARD_PHONE, ENT_QUOTES, 'UTF-8') ?>">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg></span>
            <span class="tx"><small>تلفن تماس</small><b dir="ltr"><?= htmlspecialchars(CARD_PHONE_S, ENT_QUOTES, 'UTF-8') ?></b></span>
        </a>
        <a class="row" href="https://wa.me/989366121221" target="_blank" rel="noopener">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg></span>
            <span class="tx"><small>واتساپ</small><b dir="ltr"><?= htmlspecialchars(CARD_PHONE_S, ENT_QUOTES, 'UTF-8') ?></b></span>
        </a>
        <a class="row" href="mailto:<?= htmlspecialchars(CARD_EMAIL, ENT_QUOTES, 'UTF-8') ?>">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg></span>
            <span class="tx"><small>ایمیل</small><b dir="ltr"><?= htmlspecialchars(CARD_EMAIL, ENT_QUOTES, 'UTF-8') ?></b></span>
        </a>
        <a class="row" href="<?= htmlspecialchars(CARD_SITE, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
            <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
            <span class="tx"><small>وب‌سایت</small><b dir="ltr"><?= htmlspecialchars(CARD_SITE_S, ENT_QUOTES, 'UTF-8') ?></b></span>
        </a>
    </div>
    <a class="save" href="card.php?vcard=1">⬇ ذخیره مخاطب</a>
    <div class="foot"><a href="<?= htmlspecialchars(CARD_SITE, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(CARD_ORG, ENT_QUOTES, 'UTF-8') ?></a> — کارت ویزیت دیجیتال</div>
</main>
</body>
</html>
