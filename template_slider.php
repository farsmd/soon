<?php
// قالب اسلایدر — از محتوای خود بخش استفاده می‌کند: تیتر، متن، عکس و دکمه لینک.
// اگر برای بخش محتوا/عکس وارد نشده باشد، به محتوای پیش‌فرض سایت برمی‌گردد تا نصب تازه هم کامل دیده شود.
$heading  = trim((string) ($section['heading'] ?? '')) !== '' ? (string) $section['heading'] : $site_title;
$body     = trim((string) ($section['body'] ?? '')) !== '' ? (string) $section['body'] : $site_description;
$imgUrl   = isset($section) && is_array($section) ? section_image_url($section) : '';
$linkUrl  = trim((string) ($section['link_url'] ?? ''));
$linkText = trim((string) ($section['link_text'] ?? '')) !== '' ? (string) $section['link_text'] : 'شروع کنید';
$sliderStyle = $imgUrl !== '' ? ' style="background-image:url(\'' . e($imgUrl) . '\')"' : '';
?>
<section class="slider<?= $imgUrl !== '' ? ' has-image' : '' ?>"<?= $sliderStyle ?>>
    <div class="container slider-inner">
        <h1><?= e($heading) ?></h1>
        <div class="lead"><?= $body ?></div>
        <?php if ($linkUrl !== ''): ?>
            <a class="btn btn-light" href="<?= e($linkUrl) ?>"><?= e($linkText) ?></a>
        <?php endif; ?>
    </div>
</section>
