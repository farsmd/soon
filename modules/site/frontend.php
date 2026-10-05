<?php
// modules/site/frontend.php — توابع پایه سایت (منتقل از هسته در ۹٫۹۹٫۲۶)
declare(strict_types=1);

function site_base_url(?array $settings = null): string
{
    static $base = null;
    $useCache = $settings === null;
    if ($useCache && $base !== null) {
        return $base;
    }
    if ($settings === null) {
        try {
            $settings = all_settings();
        } catch (Throwable $ignored) {
            $settings = [];
        }
    }
    $out = rtrim((string) ($settings['site_url'] ?? ''), '/');
    if ($out === '') {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $out = $host !== '' ? $proto . '://' . $host : '';
    }
    if ($useCache) {
        $base = $out;
    }
    return $out;
}

function site_css_version(array $settings): string
{
    return substr(sha1(build_site_css($settings) . '|' . (string) ($settings['css_updated_at'] ?? '')), 0, 10);
}
