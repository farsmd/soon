<?php
/*
Template Name: آرشیو دسته
Template Type: category
Description: قالب نمایش فهرست دسته‌بندی
Author: لاینرلایت
Version: 1.0.0
*/
?>
<section class="tpl-category">
    <header class="tpl-head">
        <h1><?= e($title ?? '') ?></h1>
        <?php if (!empty($description)): ?><p class="muted"><?= e($description) ?></p><?php endif; ?>
    </header>
    <div class="tpl-items"><?= $content ?? '' ?></div>
</section>
<style>
.tpl-category{max-width:1100px;margin:0 auto;padding:24px}
.tpl-head h1{font-size:28px;margin-bottom:8px}
.tpl-items{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-top:20px}
</style>
