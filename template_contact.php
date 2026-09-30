<?php
// قالب فرم تماس — یک «بخش» با این قالب بسازید تا فرم تماس در صفحه اصلی (به همان ترتیب بخش‌ها) نمایش داده شود.
// فرم: CSRF بر پایه سشن + فیلد مخفی ضدربات (honeypot) + بررسی زمان ارسال.
// تیتر و متن از محتوای بخش می‌آید (از پنل مدیریت، ویرایش همان بخش).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$contactMsg = '';
$contactErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['contact_form'] ?? '') === '1') {
    $tokenOk = hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''));
    $honeyOk = trim((string) ($_POST['website'] ?? '')) === ''; // ربات‌ها این فیلد مخفی را پر می‌کنند
    $started = (int) ($_SESSION['contact_form_time'] ?? 0);
    $timeOk  = $started > 0 && (time() - $started) >= 2; // ارسال خیلی سریع = ربات
    $name    = trim((string) ($_POST['c_name'] ?? ''));
    $contact = trim((string) ($_POST['c_contact'] ?? ''));
    $message = trim((string) ($_POST['c_message'] ?? ''));
    if (!$tokenOk) {
        $contactErr = 'درخواست نامعتبر است؛ صفحه را تازه کنید و دوباره تلاش کنید.';
    } elseif (!$honeyOk || !$timeOk) {
        $contactErr = 'ارسال انجام نشد؛ چند ثانیه صبر کنید و دوباره تلاش کنید.';
    } elseif ($name === '' || $contact === '' || $message === '') {
        $contactErr = 'نام، راه تماس و متن پیام را کامل کنید.';
    } elseif (strlen($message) < 3) {
        $contactErr = 'متن پیام خیلی کوتاه است.';
    } else {
        save_contact_message($name, $contact, $message);
        $contactMsg = 'پیام شما ثبت شد. ممنون از تماس شما.';
        $_SESSION['contact_form_time'] = time();
    }
} else {
    $_SESSION['contact_form_time'] = time();
}
$heading = trim((string) ($section['heading'] ?? '')) !== '' ? (string) $section['heading'] : 'تماس با ما';
$body    = trim((string) ($section['body'] ?? ''));
?>
<section id="contact" class="content-section contact-section">
    <div class="container">
        <h2><?= e($heading) ?></h2>
        <?php if ($body !== ''): ?><div class="section-body"><?= $body ?></div><?php endif; ?>
        <?php if ($contactMsg !== ''): ?><div class="alert ok"><?= e($contactMsg) ?></div><?php endif; ?>
        <?php if ($contactErr !== ''): ?><div class="alert error"><?= e($contactErr) ?></div><?php endif; ?>
        <form method="post" class="contact-form" action="<?= e((string) ($_SERVER['PHP_SELF'] ?? 'index.php')) ?>#contact">
            <input type="hidden" name="contact_form" value="1">
            <input type="hidden" name="csrf" value="<?= e((string) $_SESSION['csrf']) ?>">
            <div class="hp-field" aria-hidden="true">
                <label>وب‌سایت<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
            <label>نام شما
                <input type="text" name="c_name" required maxlength="120">
            </label>
            <label>ایمیل یا شماره تماس
                <input type="text" name="c_contact" required maxlength="120">
            </label>
            <label>پیام
                <textarea name="c_message" rows="5" required maxlength="4000"></textarea>
            </label>
            <button type="submit" class="btn">ارسال پیام</button>
        </form>
    </div>
</section>
