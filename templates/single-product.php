<?php
/*
Template Name: پیش‌فرض محصول
Template Type: product
Description: قالب نمایش صفحه محصول با گالری
Author: لاینرلایت
Version: 1.0.0
*/
?>
<article class="tpl-product">
    <div class="tpl-prod-grid">
        <?php if (!empty($image)): ?>
        <div class="tpl-prod-img"><img src="<?= e($image) ?>" alt="<?= e($title ?? '') ?>"></div>
        <?php endif; ?>
        <div class="tpl-prod-info">
            <h1><?= e($title ?? '') ?></h1>
            <?php if (!empty($price)): ?><div class="tpl-price"><?= e($price) ?></div><?php endif; ?>
            <div class="tpl-desc"><?= $content ?? '' ?></div>
        </div>
    </div>
</article>
<style>
.tpl-product{max-width:1100px;margin:0 auto;padding:24px}
.tpl-prod-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px}
.tpl-prod-img img{width:100%;border-radius:12px}
.tpl-price{font-size:24px;font-weight:700;color:#b08d57;margin:12px 0}
@media(max-width:768px){.tpl-prod-grid{grid-template-columns:1fr}}
</style>
