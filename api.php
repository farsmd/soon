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

require __DIR__ . '/config.php';

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
$API_SETTINGS = ['site_title', 'site_description', 'seo_title', 'seo_description', 'catalog_title', 'payment_terms', 'warranty_text', 'qc_text', 'order_line_note', 'orders_public', 'site_css', 'site_url'];

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
                if (in_array($k, ['order_line_note', 'orders_public'], true)) {
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
        $fields = [];
        foreach (['category_id' => 'i', 'name' => 's', 'sku' => 's', 'description' => 's', 'price_per_meter' => 'i', 'partner_price_per_meter' => 'i', 'is_active' => 'i', 'sort_order' => 'i', 'order_form_config' => 's', 'pricing_model' => 's', 'price_per_watt' => 'i', 'frame_options_json' => 's'] as $k => $t) {
            $v = api_in($k, null);
            if ($v !== null) {
                if ($k === 'name' && trim((string) $v) === '') {
                    api_fail('نام محصول نمی‌تواند خالی باشد.');
                }
                if (in_array($k, ['price_per_meter', 'partner_price_per_meter'], true) && (int) $v < 0) {
                    api_fail('قیمت نمی‌تواند منفی باشد.');
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

api_fail('منبع نامعتبر است (res). منابع مجاز: ping, pages, page, sections, section, settings, products, product, orders, order, gallery_upload', 404);
