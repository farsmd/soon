<?php
/*
Template Name: پیش‌فرض صفحه
Template Type: page
Description: قالب استاندارد صفحات سایت
Author: لاینرلایت
Version: 1.0.0
*/
?>
<article class="tpl-page">
    <header class="tpl-head"><h1><?= e($title ?? '') ?></h1></header>
    <div class="tpl-content"><?= $content ?? '' ?></div>
</article>
<style>
.tpl-page{max-width:900px;margin:0 auto;padding:32px 24px}
.tpl-head h1{font-size:30px;margin-bottom:20px}
.tpl-content{line-height:2.1;font-size:16px}
</style>
