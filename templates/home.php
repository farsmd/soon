<?php
/*
Template Name: صفحه اصلی
Template Type: home
Description: قالب صفحه اصلی سایت
Author: لاینرلایت
Version: 1.0.0
*/
?>
<div class="tpl-home">
    <?php get_header(); ?>
    <main id="main">
        <?= $content ?? '' ?>
    </main>
    <?php get_footer(); ?>
</div>
