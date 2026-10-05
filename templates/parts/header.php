<?php
/*
Template Name: هدر مشترک
Template Type: part
Description: هدر مشترک همه صفحات — اگر خالی باشد از قالب دیتابیس استفاده می‌شود
Author: لاینرلایت
Version: 1.0.0
*/
// این فایل اگر فقط همین کامنت را داشته باشد، سیستم از قالب دیتابیس استفاده می‌کند.
// برای سفارشی‌سازی، کد HTML/PHP هدر را اینجا بنویسید.
// متغیرهای در دسترس: $settings (تنظیمات سایت)
?>
<?php
// Fallback به قالب دیتابیس
if (!isset($__part_customized) || !$__part_customized) {
    echo render_db_template('header', $settings ?? all_settings());
    return;
}
?>
<!-- کد سفارشی هدر را اینجا بنویسید -->
<header class="site-header">
    <nav><!-- منوی سایت --></nav>
</header>
