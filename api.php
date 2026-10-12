<?php
/**
 * API بکند نسخه ۱ — خواندن و تغییر محتوای سایت با توکن
 * برای استفاده ریکی، ایجنت‌ها یا نرم‌افزارهای دیگر.
 *
 * احراز هویت (یکی کافی است):
 *   Authorization: Bearer <token>  |  X-API-Token: <token>  |  ?api_token=<token>
 * مدیریت توکن: پنل مدیریت ← سیستم ← دسترسی API
 * پاسخ همیشه JSON است: {ok:true, ...} یا {ok:false, error:"..."}
 */

require __DIR__ . '/core/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function api_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(string $msg, int $code = 400): void
{
    api_out(['ok' => false, 'error' => $msg], $code);
}

// خطایابی: هر استثنای گرفته‌نشده به‌جای ۵۰۰ خالی، JSON خطا برمی‌گرداند
set_exception_handler(static function (Throwable $e): void {
    try {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'خطای سرور: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $ignored) {
    }
    exit;
});

// ---------- محدودیت نرخ: ۱۲۰ درخواست در دقیقه برای هر توکن ----------
function api_rate_limit(string $tokenHash): void
{
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_hits (token_hash TEXT NOT NULL, window_min INTEGER NOT NULL, hits INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (token_hash, window_min))");
    $win = (int) (time() / 60);
    $pdo->prepare('DELETE FROM api_hits WHERE window_min < :w')->execute([':w' => $win - 2]);
    $pdo->prepare('INSERT INTO api_hits (token_hash, window_min, hits) VALUES (:h, :w, 1) ON CONFLICT(token_hash, window_min) DO UPDATE SET hits = hits + 1')
        ->execute([':h' => $tokenHash, ':w' => $win]);
    $hits = (int) $pdo->query("SELECT hits FROM api_hits WHERE token_hash = " . $pdo->quote($tokenHash) . " AND window_min = $win")->fetchColumn();
    if ($hits > 120) {
        api_fail('محدودیت نرخ: بیش از ۱۲۰ درخواست در دقیقه.', 429);
    }
}

// ---------- احراز هویت ----------
function api_auth(): array
{
    if (get_setting('api_enabled', '0') !== '1') {
        api_fail('API غیرفعال است. از پنل مدیریت ← سیستم ← دسترسی API آن را فعال کنید.', 503);
    }
    $token = '';
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        $token = trim($m[1]);
    }
    if ($token === '' && isset($_SERVER['HTTP_X_API_TOKEN'])) {
        $token = trim((string) $_SERVER['HTTP_X_API_TOKEN']);
    }
    if ($token === '') {
        $token = trim((string) ($_GET['api_token'] ?? $_POST['api_token'] ?? ''));
    }
    $want = (string) get_setting('api_token_hash', '');
    if ($token === '' || $want === '' || !hash_equals($want, hash('sha256', $token))) {
        api_fail('توکن نامعتبر است.', 401);
    }
    $hash = hash('sha256', $token);
    api_rate_limit($hash);
    $scopes = array_filter(array_map('trim', explode(',', (string) get_setting('api_scopes', 'read'))));
    return ['scopes' => $scopes, 'can_write' => in_array('write', $scopes, true)];
}

function api_need_write(array $auth): void
{
    if (!$auth['can_write']) {
        api_fail('این توکن فقط دسترسی خواندن دارد.', 403);
    }
}

// ---------- ورودی ----------
$rawBody = file_get_contents('php://input');
$jsonBody = [];
if ($rawBody !== '' && stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json') !== false) {
    $decoded = json_decode($rawBody, true);
    if (is_array($decoded)) {
        $jsonBody = $decoded;
    }
}
function api_in(string $key, $default = null)
{
    global $jsonBody;
    if (array_key_exists($key, $jsonBody)) {
        return $jsonBody[$key];
    }
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

$auth = api_auth();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$res = strtolower(trim((string) ($_GET['res'] ?? 'ping')));
$isWrite = ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE');
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

function api_logged_write(string $res, string $detail): void
{
    log_admin_event('api:' . $res, $detail, true, 'api');
}

// ===================================================================
if ($res === 'ping') {
    api_out(['ok' => true, 'version' => 'v1', 'app' => defined('APP_VERSION') ? APP_VERSION : '', 'time' => date('c')]);
}

// ---------------- صفحه‌ها (محتوا) ----------------
if ($res === 'pages') {
    $rows = db()->query('SELECT id, title, slug, is_active, sort_order, show_in_menu FROM pages ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'pages' => $rows]);
}

if ($res === 'page') {
    $id = (int) (api_in('id', 0));
    $slug = trim((string) api_in('slug', ''));
    $row = false;
    if ($id > 0) {
        $st = db()->prepare('SELECT * FROM pages WHERE id = :i LIMIT 1');
        $st->execute([':i' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } elseif ($slug !== '') {
        $st = db()->prepare('SELECT * FROM pages WHERE slug = :s LIMIT 1');
        $st->execute([':s' => $slug]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    }
    if ($row === false) {
        api_fail('صفحه پیدا نشد.', 404);
    }
    if ($isWrite) {
        api_need_write($auth);
        $fields = [];
        foreach (['title' => 's', 'slug' => 's', 'content' => 's', 'seo_title' => 's', 'seo_description' => 's', 'is_active' => 'i', 'show_in_menu' => 'i', 'sort_order' => 'i'] as $k => $t) {
            $v = api_in($k, null);
            if ($v !== null) {
                if ($k === 'slug' && !preg_match('/^[a-z0-9\-_]{1,80}$/', (string) $v)) {
                    api_fail('slug نامعتبر است (فقط حروف کوچک انگلیسی، عدد، خط تیره).');
                }
                if ($k === 'title' && trim((string) $v) === '') {
                    api_fail('عنوان صفحه نمی‌تواند خالی باشد.');
                }
                $fields[$k] = $t === 'i' ? (int) $v : (string) $v;
            }
        }
        if ($fields === []) {
            api_fail('فیلدی برای به‌روزرسانی ارسال نشده است.');
        }
        $set = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k = :$k";
        }
        try {
            $st = db()->prepare('UPDATE pages SET ' . implode(', ', $set) . ' WHERE id = :id');
            $st->execute($fields + [':id' => (int) $row['id']]);
        } catch (Throwable $e) {
            api_fail('به‌روزرسانی صفحه انجام نشد (slug تکراری؟).');
        }
        api_logged_write('page', 'ویرایش صفحه #' . $row['id'] . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) $row['id'], 'updated' => array_keys($fields)]);
    }
    api_out(['ok' => true, 'page' => $row]);
}

// ---------------- بخش‌های صفحه اصلی ----------------
if ($res === 'sections') {
    $rows = db()->query('SELECT id, title, template_file, sort_order, is_active FROM sections ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'sections' => $rows]);
}

if ($res === 'section') {
    $id = (int) api_in('id', 0);
    $st = db()->prepare('SELECT * FROM sections WHERE id = :i LIMIT 1');
    $st->execute([':i' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        api_fail('بخش پیدا نشد.', 404);
    }
    if ($isWrite) {
        api_need_write($auth);
        $fields = [];
        foreach (['title' => 's', 'heading' => 's', 'body' => 's', 'link_url' => 's', 'link_text' => 's', 'is_active' => 'i', 'sort_order' => 'i'] as $k => $t) {
            $v = api_in($k, null);
            if ($v !== null) {
                if ($k === 'title' && trim((string) $v) === '') {
                    api_fail('عنوان بخش نمی‌تواند خالی باشد.');
                }
                $caps = ['title' => 200, 'heading' => 300, 'body' => 20000, 'link_url' => 500, 'link_text' => 120];
                if (isset($caps[$k])) {
                    $v = mb_substr((string) $v, 0, $caps[$k]);
                }
                if ($k === 'link_url' && (string) $v !== '' && !preg_match('~^(https?://|/|#|\?)~', (string) $v) && !preg_match('/^[a-z0-9_\-\.\/]+(#.*)?$/i', (string) $v)) {
                    api_fail('نشانی پیوند نامعتبر است.');
                }
                $fields[$k] = $t === 'i' ? (int) $v : (string) $v;
            }
        }
        if ($fields === []) {
            api_fail('فیلدی برای به‌روزرسانی ارسال نشده است.');
        }
        $set = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k = :$k";
        }
        $st = db()->prepare('UPDATE sections SET ' . implode(', ', $set) . ' WHERE id = :id');
        $st->execute($fields + [':id' => (int) $row['id']]);
        api_logged_write('section', 'ویرایش بخش #' . $row['id'] . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) $row['id'], 'updated' => array_keys($fields)]);
    }
    api_out(['ok' => true, 'section' => $row]);
}

// ---------------- تنظیمات محتوایی ----------------
$API_SETTINGS = ['site_title', 'site_description', 'seo_title', 'seo_description', 'catalog_title', 'catalog_public', 'payment_terms', 'warranty_text', 'qc_text', 'order_line_note', 'orders_public', 'site_css', 'site_url', 'sitemap_enabled', 'wire_min_cm', 'wire_max_cm', 'wire_default_cm', 'endcap_price'];

if ($res === 'settings') {
    $out = [];
    foreach ($API_SETTINGS as $k) {
        $out[$k] = get_setting($k, '');
    }
    if ($isWrite) {
        api_need_write($auth);
        $updated = [];
        foreach ($API_SETTINGS as $k) {
            $v = api_in($k, null);
            if ($v !== null) {
                $sv = (string) $v;
                if (in_array($k, ['order_line_note', 'orders_public', 'catalog_public', 'sitemap_enabled'], true)) {
                    $sv = ($sv === '1' || strtolower($sv) === 'true' || strtolower($sv) === 'on') ? '1' : '0';
                }
                $limit = ($k === 'site_css') ? 200000 : 5000;
                set_setting($k, mb_substr($sv, 0, $limit));
                $updated[] = $k;
            }
        }
        if ($updated === []) {
            api_fail('کلید معتبری برای به‌روزرسانی ارسال نشده است.');
        }
        api_logged_write('settings', 'ویرایش تنظیمات (' . implode(',', $updated) . ') از IP ' . $ip);
        foreach ($updated as $k) {
            $out[$k] = get_setting($k, '');
        }
        api_out(['ok' => true, 'updated' => $updated, 'settings' => $out]);
    }
    api_out(['ok' => true, 'settings' => $out]);
}

// ---------------- محصولات ----------------
if ($res === 'products') {
    $rows = db()->query('SELECT id, category_id, name, sku, price_per_meter, partner_price_per_meter, is_active, sort_order FROM products ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'products' => $rows]);
}

if ($res === 'product') {
    $id = (int) api_in('id', 0);
    if ($isWrite && $id === 0) {
        // ساخت محصول تازه
        api_need_write($auth);
        $name = trim((string) api_in('name', ''));
        $price = (int) api_in('price_per_meter', -1);
        if ($name === '' || $price < 0) {
            api_fail('نام و قیمت متری (price_per_meter) الزامی است.');
        }
        $st = db()->prepare('INSERT INTO products (category_id, name, sku, description, price_per_meter, partner_price_per_meter, is_active, sort_order) VALUES (:c, :n, :s, :d, :p, :pp, :a, :o)');
        $st->execute([
            ':c' => (int) api_in('category_id', 0) ?: null,
            ':n' => mb_substr($name, 0, 200),
            ':s' => mb_substr(trim((string) api_in('sku', '')), 0, 80) ?: null,
            ':d' => mb_substr((string) api_in('description', ''), 0, 5000) ?: null,
            ':p' => $price,
            ':pp' => api_in('partner_price_per_meter', null) !== null ? (int) api_in('partner_price_per_meter') : null,
            ':a' => (int) api_in('is_active', 1) ? 1 : 0,
            ':o' => (int) api_in('sort_order', 0),
        ]);
        $nid = (int) db()->lastInsertId();
        api_logged_write('product', 'ساخت محصول #' . $nid . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => $nid, 'created' => true], 201);
    }
    $st = db()->prepare('SELECT * FROM products WHERE id = :i LIMIT 1');
    $st->execute([':i' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        api_fail('محصول پیدا نشد.', 404);
    }
    if ($isWrite) {
        api_need_write($auth);
        // حذف محصول (۱۰٫۲۹) — همراه با مقادیر ویژگی‌ها و تصاویر گالری‌اش
        if (api_in('delete', null) !== null && (int) api_in('delete', 0) === 1) {
            $pid = (int) $row['id'];
            db()->prepare('DELETE FROM product_attribute_values WHERE product_id = :p')->execute([':p' => $pid]);
            db()->prepare('DELETE FROM product_images WHERE product_id = :p')->execute([':p' => $pid]);
            db()->prepare('DELETE FROM products WHERE id = :p')->execute([':p' => $pid]);
            api_logged_write('product', 'حذف محصول #' . $pid . ' از IP ' . $ip);
            api_out(['ok' => true, 'id' => $pid, 'deleted' => true]);
        }
        $fields = [];
        foreach (['category_id' => 'i', 'name' => 's', 'sku' => 's', 'description' => 's', 'image' => 's', 'price_per_meter' => 'i', 'partner_price_per_meter' => 'i', 'is_active' => 'i', 'sort_order' => 'i', 'order_form_config' => 's', 'pricing_model' => 's', 'price_per_watt' => 'i', 'frame_options_json' => 's'] as $k => $t) {
            $v = api_in($k, null);
            if ($v !== null) {
                if ($k === 'name' && trim((string) $v) === '') {
                    api_fail('نام محصول نمی‌تواند خالی باشد.');
                }
                if (in_array($k, ['price_per_meter', 'partner_price_per_meter'], true) && (int) $v < 0) {
                    api_fail('قیمت نمی‌تواند منفی باشد.');
                }
                if ($k === 'image') {
                    // (۱۰٫۲۹) فقط مسیر داخل uploads مجاز است
                    $iv = trim((string) $v);
                    if ($iv !== '' && (!str_starts_with($iv, 'uploads/') || str_contains($iv, '..'))) {
                        api_fail('مسیر عکس باید داخل uploads/ باشد.');
                    }
                    $v = mb_substr($iv, 0, 255);
                }
                $fields[$k] = $t === 'i' ? (int) $v : (string) $v;
            }
        }
        if ($fields === []) {
            api_fail('فیلدی برای به‌روزرسانی ارسال نشده است.');
        }
        $set = [];
        foreach ($fields as $k => $v) {
            $set[] = "$k = :$k";
        }
        $st = db()->prepare('UPDATE products SET ' . implode(', ', $set) . ", updated_at = datetime('now') WHERE id = :id");
        $st->execute($fields + [':id' => (int) $row['id']]);
        api_logged_write('product', 'ویرایش محصول #' . $row['id'] . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) $row['id'], 'updated' => array_keys($fields)]);
    }
    api_out(['ok' => true, 'product' => $row]);
}

// ---------------- دسته‌بندی‌های محصول (۱۰٫۲۹) ----------------
if ($res === 'categories') {
    if ($isWrite) {
        api_need_write($auth);
        $title = trim((string) api_in('title', ''));
        if ($title === '') {
            api_fail('عنوان دسته‌بندی الزامی است.');
        }
        $slug = trim((string) api_in('slug', ''));
        if ($slug === '') {
            $slug = 'cat-' . time();
        }
        $slug = mb_substr(preg_replace('/[^a-z0-9_-]+/i', '-', $slug) ?? '', 0, 80);
        try {
            $st = db()->prepare('INSERT INTO product_categories (title, slug, parent_id, description, sort_order, is_active) VALUES (:t, :s, :p, :d, :o, 1)');
            $st->execute([
                ':t' => mb_substr($title, 0, 120),
                ':s' => $slug,
                ':p' => (int) api_in('parent_id', 0) ?: null,
                ':d' => mb_substr((string) api_in('description', ''), 0, 2000) ?: null,
                ':o' => (int) api_in('sort_order', 0),
            ]);
        } catch (PDOException $e) {
            api_fail('ساخت دسته‌بندی انجام نشد (احتمالاً نامک تکراری است).');
        }
        $nid = (int) db()->lastInsertId();
        api_logged_write('categories', 'ساخت دسته‌بندی #' . $nid . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => $nid, 'created' => true], 201);
    }
    $rows = db()->query('SELECT id, title, slug, parent_id, sort_order, is_active FROM product_categories ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'categories' => $rows]);
}

// ---------------- ویژگی‌ها و آپشن‌های محصول (۱۰٫۲۹) ----------------
if ($res === 'product_attributes') {
    if ($isWrite) {
        api_need_write($auth);
        $action = trim((string) api_in('action', ''));
        if ($action === 'create_attribute') {
            $title = trim((string) api_in('title', ''));
            $key = trim((string) api_in('attr_key', ''));
            if ($title === '' || $key === '') {
                api_fail('عنوان و کلید ویژگی الزامی است.');
            }
            if (!preg_match('/^[a-z0-9_]{1,60}$/', $key)) {
                api_fail('کلید ویژگی باید انگلیسی و بدون فاصله باشد.');
            }
            $type = in_array(api_in('input_type', 'select'), ['select', 'number', 'text'], true) ? (string) api_in('input_type', 'select') : 'select';
            try {
                $st = db()->prepare("INSERT INTO product_attributes (title, attr_key, input_type, unit, sort_order, is_active) VALUES (:t, :k, :ty, :u, :o, 1)");
                $st->execute([
                    ':t' => mb_substr($title, 0, 120),
                    ':k' => $key,
                    ':ty' => $type,
                    ':u' => mb_substr((string) api_in('unit', ''), 0, 20) ?: null,
                    ':o' => (int) api_in('sort_order', 0),
                ]);
            } catch (PDOException $e) {
                api_fail('ساخت ویژگی انجام نشد (احتمالاً کلید تکراری است).');
            }
            $nid = (int) db()->lastInsertId();
            api_logged_write('product_attributes', 'ساخت ویژگی #' . $nid . ' از IP ' . $ip);
            api_out(['ok' => true, 'id' => $nid, 'created' => true], 201);
        }
        if ($action === 'create_option') {
            $aid = (int) api_in('attribute_id', 0);
            $title = trim((string) api_in('title', ''));
            if ($aid <= 0 || $title === '') {
                api_fail('شناسه ویژگی و عنوان گزینه الزامی است.');
            }
            $chk = db()->prepare('SELECT id FROM product_attributes WHERE id = :a LIMIT 1');
            $chk->execute([':a' => $aid]);
            if ($chk->fetchColumn() === false) {
                api_fail('ویژگی پیدا نشد.', 404);
            }
            $st = db()->prepare('INSERT INTO product_attribute_options (attribute_id, title, price_delta_per_meter, sort_order) VALUES (:a, :t, :p, :o)');
            $st->execute([
                ':a' => $aid,
                ':t' => mb_substr($title, 0, 120),
                ':p' => (int) api_in('price_delta_per_meter', 0),
                ':o' => (int) api_in('sort_order', 0),
            ]);
            $nid = (int) db()->lastInsertId();
            api_logged_write('product_attributes', 'ساخت گزینه #' . $nid . ' برای ویژگی #' . $aid . ' از IP ' . $ip);
            api_out(['ok' => true, 'id' => $nid, 'created' => true], 201);
        }
        if ($action === 'assign') {
            $pid = (int) api_in('product_id', 0);
            $aid = (int) api_in('attribute_id', 0);
            $oid = (int) api_in('option_id', 0);
            if ($pid <= 0 || $aid <= 0) {
                api_fail('شناسه محصول و ویژگی الزامی است.');
            }
            foreach (['products' => $pid, 'product_attributes' => $aid] as $tbl => $vid) {
                $chk = db()->prepare("SELECT id FROM {$tbl} WHERE id = :v LIMIT 1");
                $chk->execute([':v' => $vid]);
                if ($chk->fetchColumn() === false) {
                    api_fail('محصول یا ویژگی پیدا نشد.', 404);
                }
            }
            if ($oid > 0) {
                $chk = db()->prepare('SELECT id FROM product_attribute_options WHERE id = :o AND attribute_id = :a LIMIT 1');
                $chk->execute([':o' => $oid, ':a' => $aid]);
                if ($chk->fetchColumn() === false) {
                    api_fail('گزینه متعلق به این ویژگی نیست.', 404);
                }
            }
            db()->prepare('DELETE FROM product_attribute_values WHERE product_id = :p AND attribute_id = :a')->execute([':p' => $pid, ':a' => $aid]);
            db()->prepare('INSERT INTO product_attribute_values (product_id, attribute_id, option_id) VALUES (:p, :a, :o)')->execute([
                ':p' => $pid, ':a' => $aid, ':o' => $oid > 0 ? $oid : null,
            ]);
            api_logged_write('product_attributes', "تخصیص ویژگی #{$aid} به محصول #{$pid} از IP " . $ip);
            api_out(['ok' => true, 'assigned' => true]);
        }
        api_fail('عمل نامشخص است (create_attribute/create_option/assign).');
    }
    $attrs = db()->query('SELECT id, title, attr_key, input_type, unit, sort_order, is_active FROM product_attributes ORDER BY sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    $opts = db()->query('SELECT id, attribute_id, title, price_delta_per_meter, sort_order FROM product_attribute_options ORDER BY attribute_id ASC, sort_order ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'attributes' => $attrs, 'options' => $opts]);
}

// ---------------- سفارش‌ها ----------------
if ($res === 'orders') {
    $status = trim((string) api_in('status', ''));
    $limit = min(100, max(1, (int) api_in('limit', 20)));
    $sql = 'SELECT o.id, o.order_no, o.customer_type, o.source, o.status, o.subtotal, o.discount_percent, o.discount_amount, o.total, o.total_meters, o.total_fixtures, o.created_at, c.full_name AS customer_name, c.mobile AS customer_mobile FROM orders o LEFT JOIN customers c ON c.id = o.customer_id';
    $params = [];
    if ($status !== '') {
        $sql .= ' WHERE o.status = :s';
        $params[':s'] = $status;
    }
    $sql .= ' ORDER BY o.id DESC LIMIT ' . $limit;
    $st = db()->prepare($sql);
    $st->execute($params);
    api_out(['ok' => true, 'orders' => $st->fetchAll(PDO::FETCH_ASSOC)]);
}

if ($res === 'order') {
    $id = (int) api_in('id', 0);
    $st = db()->prepare('SELECT o.*, c.full_name AS customer_name, c.mobile AS customer_mobile FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = :i LIMIT 1');
    $st->execute([':i' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        api_fail('سفارش پیدا نشد.', 404);
    }
    if ($isWrite) {
        api_need_write($auth);
        $newStatus = trim((string) api_in('status', ''));
        if ($newStatus === '') {
            api_fail('وضعیت تازه (status) ارسال نشده است.');
        }
        $valid = db()->query("SELECT status_key FROM order_statuses WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($newStatus, $valid, true)) {
            api_fail('وضعیت نامعتبر است. وضعیت‌های مجاز: ' . implode(', ', $valid));
        }
        if ($newStatus !== $row['status']) {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("UPDATE orders SET status = :s, updated_at = datetime('now') WHERE id = :i")->execute([':s' => $newStatus, ':i' => $id]);
                $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, :f, :t, :n)')
                    ->execute([':o' => $id, ':f' => $row['status'], ':t' => $newStatus, ':n' => 'تغییر وضعیت از API']);
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                api_fail('تغییر وضعیت انجام نشد.');
            }
            api_logged_write('order', 'تغییر وضعیت سفارش #' . $id . ' به ' . $newStatus . ' از IP ' . $ip);
        }
        api_out(['ok' => true, 'id' => $id, 'status' => $newStatus]);
    }
    $items = db()->prepare('SELECT * FROM order_items WHERE order_id = :i ORDER BY sort_order ASC, id ASC');
    $items->execute([':i' => $id]);
    $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'order' => $row]);
}

// ---------------- آپلود عکس گالری پروژه‌ها ----------------
if ($res === 'gallery_upload') {
    if (!$isWrite) {
        api_fail('این منبع فقط با POST کار می‌کند.', 405);
    }
    api_need_write($auth);
    $file = $_FILES['image'] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        api_fail('فایل عکس (image) ارسال نشده یا خطا دارد.');
    }
    if ((int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
        api_fail('حجم عکس نباید بیشتر از ۵ مگابایت باشد.');
    }
    $info = @getimagesize((string) $file['tmp_name']);
    if ($info === false) {
        api_fail('فایل ارسال‌شده عکس معتبر نیست.');
    }
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = (string) ($info['mime'] ?? '');
    if (!isset($mimeToExt[$mime])) {
        api_fail('فرمت عکس باید JPG یا PNG یا WebP باشد.');
    }
    $ext = $mimeToExt[$mime];
    $name = trim((string) api_in('name', ''));
    if (!preg_match('/^[a-z0-9_-]{1,60}$/i', $name)) {
        $name = 'g_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
    }
    $dir = __DIR__ . '/uploads/gallery';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        api_fail('ساخت پوشه گالری انجام نشد.');
    }
    @file_put_contents($dir . '/index.html', '');
    $filename = $name . '.' . $ext;
    $dest = $dir . '/' . $filename;
    if (!@move_uploaded_file((string) $file['tmp_name'], $dest)) {
        api_fail('ذخیره عکس انجام نشد.');
    }
    api_logged_write('gallery_upload', 'آپلود ' . $filename . ' از IP ' . $ip);
    api_out(['ok' => true, 'filename' => $filename, 'url' => 'uploads/gallery/' . $filename]);
}

// ---------- منابع انسانی و تجهیزات (نسخه ۹٫۸٫۱) ----------
if ($res === 'employees') {
    if ($isWrite) {
        api_need_write($auth);
        $name = trim((string) api_in('full_name', ''));
        if ($name === '') { api_fail('نام پرسنل الزامی است.', 400); }
        db()->prepare('INSERT INTO employees (full_name, position, mobile, national_id, hire_date, base_salary, employment_type, status, notes) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$name, (string) api_in('position', ''), (string) api_in('mobile', ''), (string) api_in('national_id', ''), (string) api_in('hire_date', ''), (int) api_in('base_salary', 0), (string) api_in('employment_type', 'full_time'), 'active', (string) api_in('notes', '')]);
        api_logged_write('employees', 'افزودن پرسنل «' . $name . '» از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    }
    $rows = db()->query('SELECT * FROM employees ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'employees' => $rows]);
}

if ($res === 'salary_payments') {
    if ($isWrite) {
        api_need_write($auth);
        $eid = (int) api_in('employee_id', 0);
        $month = trim((string) api_in('pay_month', ''));
        if ($eid <= 0 || $month === '') { api_fail('پرسنل و ماه الزامی است.', 400); }
        $base = (int) api_in('base_amount', 0);
        $bonus = (int) api_in('bonus', 0);
        $ded = (int) api_in('deduction', 0);
        $net = $base + $bonus - $ded;
        db()->prepare('INSERT INTO salary_payments (employee_id, pay_month, base_amount, bonus, deduction, net_amount, paid_date, notes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$eid, $month, $base, $bonus, $ded, $net, (string) api_in('paid_date', ''), (string) api_in('notes', '')]);
        api_logged_write('salary_payments', 'ثبت فیش حقوقی پرسنل ' . $eid . ' ماه ' . $month . ' از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) db()->lastInsertId(), 'net_amount' => $net]);
    }
    $rows = db()->query('SELECT sp.*, e.full_name FROM salary_payments sp JOIN employees e ON e.id = sp.employee_id ORDER BY sp.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'payments' => $rows]);
}

if ($res === 'equipment') {
    if ($isWrite) {
        api_need_write($auth);
        $name = trim((string) api_in('name', ''));
        if ($name === '') { api_fail('نام تجهیز الزامی است.', 400); }
        db()->prepare('INSERT INTO equipment (name, category, purchase_date, purchase_price, useful_life_years, status, location, notes) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$name, (string) api_in('category', ''), (string) api_in('purchase_date', ''), (int) api_in('purchase_price', 0), (float) api_in('useful_life_years', 5), 'active', (string) api_in('location', ''), (string) api_in('notes', '')]);
        api_logged_write('equipment', 'افزودن تجهیز «' . $name . '» از IP ' . $ip);
        api_out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
    }
    $rows = db()->query('SELECT * FROM equipment ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
    api_out(['ok' => true, 'equipment' => $rows]);
}


// ---------------- آمار بازدید (فقط خواندنی، ۹٫۹۹٫۳۲) ----------------
if ($res === 'visits') {
    $days = min(90, max(1, (int) api_in('days', 30)));
    // ستون‌های اختیاری (ممکن است در دیتابیس‌های قدیمی نباشند)
    $cols = db()->query("PRAGMA table_info(visit_logs)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $hasBot = in_array('is_bot', $cols, true);
    $hasAdmin = in_array('admin_user', $cols, true);
    $filter = "kind = 'visit'";
    if ($hasBot) $filter .= " AND COALESCE(is_bot, 0) = 0";
    if ($hasAdmin) $filter .= " AND COALESCE(admin_user, '') = ''";

    $one = function(string $sql, array $params = []) {
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    };
    $cnt = function(string $where = '') use ($one, $filter) {
        $w = $filter . ($where !== '' ? " AND ($where)" : '');
        $r = $one("SELECT COUNT(*) AS c FROM visit_logs WHERE $w");
        return (int) ($r[0]['c'] ?? 0);
    };

    $today = $cnt("date(created_at) = date('now')");
    $yesterday = $cnt("date(created_at) = date('now', '-1 day')");
    $last7 = $cnt("date(created_at) >= date('now', '-6 days')");
    $last30 = $cnt("date(created_at) >= date('now', '-29 days')");
    $total = $cnt();

    $uniq = $one("SELECT COUNT(DISTINCT ip) AS c FROM visit_logs WHERE $filter AND date(created_at) >= date('now', '-29 days')");
    $topPages = $one("SELECT path, COUNT(*) AS visits FROM visit_logs WHERE $filter AND date(created_at) >= date('now', '-29 days') GROUP BY path ORDER BY visits DESC LIMIT 10");
    $daily = $one("SELECT date(created_at) AS d, COUNT(*) AS visits FROM visit_logs WHERE $filter AND date(created_at) >= date('now', '-" . ($days - 1) . " days') GROUP BY d ORDER BY d ASC");

    api_out(['ok' => true, 'visits' => [
        'total' => $total,
        'today' => $today,
        'yesterday' => $yesterday,
        'last_7_days' => $last7,
        'last_30_days' => $last30,
        'unique_ips_30d' => (int) ($uniq[0]['c'] ?? 0),
        'top_pages' => array_map(fn($r) => ['path' => (string) $r['path'], 'visits' => (int) $r['visits']], $topPages),
        'daily' => array_map(fn($r) => ['date' => (string) $r['d'], 'visits' => (int) $r['visits']], $daily),
    ]]);
}

api_fail('منبع نامعتبر است (res). منابع مجاز: ping, pages, page, sections, section, settings, products, product, orders, order, gallery_upload, employees, salary_payments, equipment, visits', 404);
