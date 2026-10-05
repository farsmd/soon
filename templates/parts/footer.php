<?php
/*
Template Name: فوتر مشترک
Template Type: part
Description: فوتر مشترک همه صفحات — اگر خالی باشد از قالب دیتابیس استفاده می‌شود
Author: لاینرلایت
Version: 1.0.0
*/
// این فایل اگر فقط همین کامنت را داشته باشد، سیستم از قالب دیتابیس استفاده می‌کند.
// برای سفارشی‌سازی، کد HTML/PHP فوتر را اینجا بنویسید.
?>
<?php
// Fallback به قالب دیتابیس
if (!isset($__part_customized) || !$__part_customized) {
    echo render_db_template('footer', $settings ?? all_settings());
    return;
}
?>
<!-- کد سفارشی فوتر را اینجا بنویسید -->
<footer class="site-footer">
    <p>© لاینرلایت</p>
</footer>
