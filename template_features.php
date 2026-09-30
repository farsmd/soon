<?php
// قالب ویژگی‌ها — تیتر و متن از محتوای بخش می‌آید؛ اگر خالی باشد کارت‌های پیش‌فرض نشان داده می‌شود.
$heading = trim((string) ($section['heading'] ?? '')) !== '' ? (string) $section['heading'] : 'ویژگی‌ها';
$body    = trim((string) ($section['body'] ?? ''));
$imgUrl  = isset($section) && is_array($section) ? section_image_url($section) : '';
?>
<section id="features" class="features">
    <div class="container">
        <h2><?= e($heading) ?></h2>
        <?php if ($imgUrl !== ''): ?>
            <p class="section-image"><img src="<?= e($imgUrl) ?>" alt="<?= e($heading) ?>"></p>
        <?php endif; ?>
        <?php if ($body !== ''): ?>
            <div class="section-body"><?= $body ?></div>
        <?php else: ?>
            <div class="cards">
                <article class="card">
                    <h3>سریع و سبک</h3>
                    <p>بر پایه PHP و SQLite، بدون نیاز به دیتابیس جدا.</p>
                </article>
                <article class="card">
                    <h3>قالب‌های قابل ترتیب</h3>
                    <p>هدر، اسلایدر و فوتر را از پنل مدیریت به هر ترتیبی بچینید.</p>
                </article>
                <article class="card">
                    <h3>چندصفحه‌ای و قابل ویرایش</h3>
                    <p>صفحه بسازید، محتوا و عکس هر بخش را از ادمین عوض کنید و منو خودکار ساخته می‌شود.</p>
                </article>
            </div>
        <?php endif; ?>
    </div>
</section>
