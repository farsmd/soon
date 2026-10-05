<?php
/*
Template Name: پیش‌فرض مقاله
Template Type: post
Description: قالب استاندارد نمایش مقاله با تصویر شاخص
Author: لاینرلایت
Version: 1.0.0
*/
?>
<article class="tpl-single">
    <?php if (!empty($image)): ?>
    <div class="tpl-featured"><img src="<?= e($image) ?>" alt="<?= e($title ?? '') ?>"></div>
    <?php endif; ?>
    <header class="tpl-head">
        <h1><?= e($title ?? '') ?></h1>
        <?php if (!empty($date)): ?><time class="muted"><?= e($date) ?></time><?php endif; ?>
    </header>
    <div class="tpl-content"><?= $content ?? '' ?></div>
</article>
<style>
.tpl-single{max-width:800px;margin:0 auto;padding:24px}
.tpl-featured img{width:100%;border-radius:12px;margin-bottom:20px}
.tpl-head h1{font-size:28px;margin-bottom:8px}
.tpl-content{line-height:2;font-size:16px}
</style>
