<?php
// core/templates.php — سیستم قالب وردپرسی (نسخه ۹٫۹۹٫۱۲)
// هر قالب یک فایل PHP در پوشه templates/ با هدر مشخصات است.

declare(strict_types=1);

/** مسیر پوشه قالب‌ها */
function templates_dir(): string
{
    return (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/templates';
}

/** خواندن هدر مشخصات یک فایل قالب */
function template_parse_header(string $file): ?array
{
    if (!is_file($file)) return null;
    $content = (string) file_get_contents($file);
    // خواندن ۲KB اول برای هدر
    $head = substr($content, 0, 2048);
    if (!preg_match('/\/\*(.*?)\*\//s', $head, $m)) return null;
    $header = $m[1];

    $get = function($key) use ($header) {
        if (preg_match('/' . preg_quote($key, '/') . '\s*:\s*(.+)/i', $header, $mm)) {
            return trim($mm[1]);
        }
        return '';
    };

    $name = $get('Template Name');
    if ($name === '') return null;

    $types = array_filter(array_map('trim', explode(',', $get('Template Type'))));
    if (empty($types)) $types = ['post', 'page']; // پیش‌فرض

    return [
        'file' => basename($file),
        'name' => $name,
        'description' => $get('Description'),
        'types' => $types,
        'author' => $get('Author'),
        'version' => $get('Version') ?: '1.0.0',
    ];
}

/** کشف همه قالب‌های موجود */
function templates_discover(?string $type = null): array
{
    $dir = templates_dir();
    $found = [];
    if (!is_dir($dir)) return $found;
    foreach (scandir($dir) ?: [] as $entry) {
        if (!str_ends_with($entry, '.php')) continue;
        $t = template_parse_header($dir . '/' . $entry);
        if ($t === null) continue;
        if ($type !== null && !in_array($type, $t['types'], true) && !in_array('all', $t['types'], true)) continue;
        $found[$t['file']] = $t;
    }
    // مرتب‌سازی بر اساس نام
    uasort($found, fn($a, $b) => strcmp($a['name'], $b['name']));
    return $found;
}

/** قالب پیش‌فرض برای هر نوع */
function template_default(string $type): string
{
    $defaults = [
        'post' => 'single.php',
        'page' => 'page.php',
        'product' => 'single-product.php',
        'category' => 'category.php',
    ];
    return $defaults[$type] ?? 'single.php';
}

/** انتخاب قالب مناسب با سلسله‌مراتب وردپرسی */
function template_resolve(string $type, ?string $selected = null, ?string $slug = null): string
{
    $dir = templates_dir();

    // ۱. قالب انتخاب‌شده توسط کاربر
    if ($selected && is_file($dir . '/' . $selected)) {
        return $selected;
    }
    // ۲. قالب مخصوص اسلاگ (مثل single-hello-world.php)
    if ($slug) {
        $slugFile = $type . '-' . $slug . '.php';
        if (is_file($dir . '/' . $slugFile)) {
            return $slugFile;
        }
    }
    // ۳. قالب پیش‌فرض نوع
    $default = template_default($type);
    if (is_file($dir . '/' . $default)) {
        return $default;
    }
    // ۴. اولین قالب موجود از آن نوع
    $available = templates_discover($type);
    if (!empty($available)) {
        return array_key_first($available);
    }
    return '';
}

/** رندر محتوا با قالب */
function template_render(string $type, array $data = [], ?string $selected = null, ?string $slug = null): void
{
    $template = template_resolve($type, $selected, $slug);
    if ($template === '') {
        echo '<p>قالب یافت نشد.</p>';
        return;
    }
    // داده‌ها به‌صورت متغیر در دسترس قالب
    extract($data, EXTR_SKIP);
    $template_type = $type;
    include templates_dir() . '/' . $template;
}

/** dropdown انتخاب قالب برای فرم‌ها */
function template_select_html(string $type, ?string $current = null, string $fieldName = 'template'): string
{
    $templates = templates_discover($type);
    $html = '<select name="' . e($fieldName) . '" class="form-control">';
    $html .= '<option value="">— قالب پیش‌فرض —</option>';
    foreach ($templates as $file => $t) {
        $sel = ($current === $file) ? ' selected' : '';
        $html .= '<option value="' . e($file) . '"' . $sel . '>' . e($t['name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/** لود بخشی از قالب (مثل هدر/فوتر) — مثل وردپرس */
function get_template_part(string $part, array $data = []): void
{
    $file = templates_dir() . '/parts/' . $part . '.php';
    if (!is_file($file)) {
        // fallback به دیتابیس برای هدر/فوتر
        if (in_array($part, ['header', 'footer'], true) && function_exists('render_db_template')) {
            $settings = $data['settings'] ?? (function_exists('all_settings') ? all_settings() : []);
            echo render_db_template($part, $settings);
        }
        return;
    }
    extract($data, EXTR_SKIP);
    include $file;
}

/** هدر — مثل وردپرس */
function get_header(array $data = []): void
{
    get_template_part('header', $data);
}

/** فوتر — مثل وردپرس */
function get_footer(array $data = []): void
{
    get_template_part('footer', $data);
}

/** آیا بخشی سفارشی‌سازی شده؟ (بیش از کامنت هدر دارد) */
function template_part_is_customized(string $part): bool
{
    $file = templates_dir() . '/parts/' . $part . '.php';
    if (!is_file($file)) return false;
    $content = (string) file_get_contents($file);
    // حذف کامنت هدر
    $content = preg_replace('/\/\*.*?\*\//s', '', $content);
    $content = trim($content);
    // اگر فقط کد fallback است، سفارشی نشده
    if (strpos($content, '__part_customized') !== false && strlen($content) < 500) {
        return false;
    }
    return strlen($content) > 50;
}
