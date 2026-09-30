<?php
// قالب محتوای اصلی — تیتر، متن، عکس و دکمه از محتوای بخش؛ با پس‌روی به محتوای پیش‌فرض اگر خالی باشد.
$heading  = trim((string) ($section['heading'] ?? '')) !== '' ? (string) $section['heading'] : 'درباره ' . $site_title;
$body     = trim((string) ($section['body'] ?? ''));
$imgUrl   = isset($section) && is_array($section) ? section_image_url($section) : '';
$linkUrl  = trim((string) ($section['link_url'] ?? ''));
$linkText = trim((string) ($section['link_text'] ?? ''));
?>
<section id="content" class="content-section">
    <div class="container">
        <h2><?= e($heading) ?></h2>
        <?php if ($imgUrl !== ''): ?>
            <p class="section-image"><img src="<?= e($imgUrl) ?>" alt="<?= e($heading) ?>"></p>
        <?php endif; ?>
        <?php if ($body !== ''): ?>
            <div class="section-body"><?= $body ?></div>
        <?php else: ?>
            <p>{{site_description}}</p>
            <p>این یک بخش محتوای نمونه است. از پنل مدیریت، «بخش‌های صفحه اصلی»، همین بخش را ویرایش کنید و تیتر، متن و عکس خودش را بدهید؛ یا از «قالب‌ها» کد فایل <code>template_content.php</code> را تغییر دهید.</p>
        <?php endif; ?>
        <?php if ($linkUrl !== '' && $linkText !== ''): ?>
            <p><a class="btn" href="<?= e($linkUrl) ?>"><?= e($linkText) ?></a></p>
        <?php endif; ?>
    </div>
</section>
