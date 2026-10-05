<?php
/*
Template Name: تمام‌عرض
Template Type: post, page, product
Description: قالب تمام‌عرض بدون محدودیت پهنا
Author: لاینرلایت
Version: 1.0.0
*/
?>
<article class="tpl-fullwidth">
    <header class="tpl-head"><h1><?= e($title ?? '') ?></h1></header>
    <div class="tpl-content"><?= $content ?? '' ?></div>
</article>
<style>
.tpl-fullwidth{padding:24px}
.tpl-head h1{font-size:32px;margin-bottom:16px}
.tpl-content{line-height:2}
</style>
