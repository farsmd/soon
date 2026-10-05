<?php
// admin_catalog.php — بخش کاتالوگ و مشتری‌های پنل مدیریت (فاز ۲ نسخه ۶).
// این فایل فقط از admin.php و بعد از احراز هویت صدا زده می‌شود؛ اکشن‌ها و صفحه‌های
// مشتری‌ها، دسته‌بندی‌ها، محصولات و ویژگی‌های محصول اینجاست تا admin.php کوچک بماند
// (سقف حجم آپدیت گیت‌هاب). رفتار و خروجی دقیقاً همان ساختار قبلی تک‌فایلی است.

declare(strict_types=1);

// دسترسی مستقیم ممنوع: این فایل به‌تنهایی هیچ خروجی و هیچ سطح مدیریتی ندارد.
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به کاتالوگ و مشتری‌ها */
function catalog_post_actions(): array
{
    return ['add_customer', 'update_customer', 'delete_customer',
        'add_category', 'update_category', 'delete_category', 'move_category',
        'add_product', 'update_product', 'delete_product', 'move_product',
        'add_attribute', 'update_attribute', 'delete_attribute', 'move_attribute',
        'add_option', 'update_option', 'delete_option', 'move_option'];
}

/**
 * پردازش اکشن‌های POST کاتالوگ و مشتری‌ها — همان منطق قبلی داخل سوییچ admin.php.
 * موفق‌ها با redirect_admin تمام می‌شوند و خطاها با استثنا به catch اصلی برمی‌گردند.
 */
function catalog_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
            case 'add_customer':
            case 'update_customer':
                $cid = (int) ($_POST['id'] ?? 0);
                $fullName = trim((string) ($_POST['full_name'] ?? ''));
                $mobile   = trim((string) ($_POST['mobile'] ?? ''));
                $ctype    = (string) ($_POST['customer_type'] ?? 'retail');
                if (!array_key_exists($ctype, customer_types())) {
                    $ctype = 'retail';
                }
                if ($fullName === '') {
                    throw new RuntimeException('نام مشتری را وارد کنید.');
                }
                if ($mobile === '') {
                    throw new RuntimeException('شماره موبایل مشتری الزامی است.');
                }
                if (customer_mobile_exists($mobile, $action === 'update_customer' ? $cid : 0)) {
                    throw new RuntimeException('این شماره موبایل (' . $mobile . ') قبلاً برای مشتری دیگری ثبت شده است. لطفاً از همان مشتری استفاده کنید یا شماره دیگری وارد کنید.');
                }
                $creditLimit = max(0, (int) ($_POST['credit_limit'] ?? 0));
                $data = [
                    ':full_name' => $fullName,
                    ':company'   => trim((string) ($_POST['company'] ?? '')) ?: null,
                    ':mobile'    => $mobile,
                    ':type'      => $ctype,
                    ':city'      => trim((string) ($_POST['city'] ?? '')) ?: null,
                    ':address'   => trim((string) ($_POST['address'] ?? '')) ?: null,
                    ':notes'     => trim((string) ($_POST['notes'] ?? '')) ?: null,
                    ':credit_limit' => $creditLimit,
                ];
                if ($action === 'update_customer' && $cid > 0) {
                    $data[':id'] = $cid;
                    $pdo->prepare("UPDATE customers SET full_name = :full_name, company = :company, mobile = :mobile, customer_type = :type, city = :city, address = :address, notes = :notes, credit_limit = :credit_limit, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute($data);
                    flash('ok', 'مشتری به‌روزرسانی شد.');
                    redirect_admin('admin.php?page=customers&view=' . $cid);
                }
                $pdo->prepare("INSERT INTO customers (full_name, company, mobile, customer_type, city, address, notes, credit_limit) VALUES (:full_name, :company, :mobile, :type, :city, :address, :notes, :credit_limit)")->execute($data);
                flash('ok', 'مشتری جدید ثبت شد.');
                redirect_admin('admin.php?page=customers');
                // no break

            case 'delete_customer':
                $cid = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM customers WHERE id = :id')->execute([':id' => $cid]);
                flash('ok', 'مشتری حذف شد.');
                redirect_admin('admin.php?page=customers');
                // no break

            // ---------- فاز ۲: دسته‌بندی محصولات ----------
            case 'add_category':
            case 'update_category':
                $catId = (int) ($_POST['id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('عنوان دسته را وارد کنید.');
                }
                $slug = trim((string) ($_POST['slug'] ?? ''));
                if ($slug === '') {
                    // نامک خودکار یکتا برای دسته‌های فارسی
                    $base = 'cat';
                    $n = 0;
                    do {
                        $try = $n === 0 ? $base . '-' . $catId : $base . '-' . $catId . '-' . $n;
                        if ($catId === 0) {
                            $try = $base . '-' . bin2hex(random_bytes(3)) . ($n === 0 ? '' : '-' . $n);
                        }
                        $n++;
                    } while (category_slug_exists($try, $catId));
                    $slug = $try;
                }
                if (category_slug_exists($slug, $catId)) {
                    throw new RuntimeException('این نامک (slug) قبلاً برای دسته دیگری ثبت شده است.');
                }
                $parentId = (int) ($_POST['parent_id'] ?? 0);
                if ($parentId <= 0 || $parentId === $catId || get_category($parentId) === null) {
                    $parentId = null;
                }
                $old = $catId > 0 ? get_category($catId) : null;
                $image = (string) ($old['image'] ?? '');
                if (!empty($_POST['remove_image']) && $image !== '') {
                    $oldPath = UPLOADS_DIR . '/' . basename($image);
                    if (is_file($oldPath)) { @unlink($oldPath); }
                    $image = '';
                }
                $up = handle_section_image_upload($_FILES['image'] ?? null, $image !== '' ? $image : null);
                if (!$up['ok']) {
                    throw new RuntimeException((string) $up['error']);
                }
                $image = (string) ($up['filename'] ?? '');
                $data = [
                    ':title' => $title,
                    ':slug'  => $slug,
                    ':parent' => $parentId,
                    ':desc'  => trim((string) ($_POST['description'] ?? '')) ?: null,
                    ':image' => $image ?: null,
                    ':sort'  => (int) ($_POST['sort_order'] ?? 0),
                    ':active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($action === 'update_category' && $catId > 0) {
                    $data[':id'] = $catId;
                    $pdo->prepare('UPDATE product_categories SET title = :title, slug = :slug, parent_id = :parent, description = :desc, image = :image, sort_order = :sort, is_active = :active WHERE id = :id')->execute($data);
                    flash('ok', 'دسته‌بندی به‌روزرسانی شد.');
                } else {
                    $pdo->prepare('INSERT INTO product_categories (title, slug, parent_id, description, image, sort_order, is_active) VALUES (:title, :slug, :parent, :desc, :image, :sort, :active)')->execute($data);
                    flash('ok', 'دسته‌بندی جدید ساخته شد.');
                }
                redirect_admin('admin.php?page=categories');
                // no break

            case 'delete_category':
                $catId = (int) ($_POST['id'] ?? 0);
                $subCount = (int) $pdo->query('SELECT COUNT(*) FROM product_categories WHERE parent_id = ' . $catId)->fetchColumn();
                $prodCount = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE category_id = ' . $catId)->fetchColumn();
                if ($subCount > 0 || $prodCount > 0) {
                    $parts = [];
                    if ($prodCount > 0) { $parts[] = $prodCount . ' محصول'; }
                    if ($subCount > 0) { $parts[] = $subCount . ' زیردسته'; }
                    throw new RuntimeException('این دسته قابل حذف نیست چون ' . implode(' و ', $parts) . ' دارد. اول آن‌ها را جابه‌جا یا حذف کنید.');
                }
                $cat = get_category($catId);
                $pdo->prepare('DELETE FROM product_categories WHERE id = :id')->execute([':id' => $catId]);
                if ($cat !== null && !empty($cat['image'])) {
                    @unlink(UPLOADS_DIR . '/' . basename((string) $cat['image']));
                }
                flash('ok', 'دسته‌بندی حذف شد.');
                redirect_admin('admin.php?page=categories');
                // no break

            case 'move_category':
                move_row($pdo, 'product_categories', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
                redirect_admin('admin.php?page=categories');
                // no break

            // ---------- فاز ۲: محصولات ----------
            case 'add_product':
            case 'update_product':
                $pid = (int) ($_POST['id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('نام محصول را وارد کنید.');
                }
                $price = max(0, (int) ($_POST['price_per_meter'] ?? 0));
                $partnerRaw = trim((string) ($_POST['partner_price_per_meter'] ?? ''));
                $partnerPrice = $partnerRaw === '' ? null : max(0, (int) $partnerRaw);
                $pricingModel = in_array(($_POST['pricing_model'] ?? ''), ['per_meter', 'per_watt'], true) ? (string) $_POST['pricing_model'] : 'per_meter';
                $pricePerWatt = max(0, (int) ($_POST['price_per_watt'] ?? 0));
                $basePrice = max(0, (int) ($_POST['base_price'] ?? 0));
                // قاب‌ها: آرایه نام/قیمت از فرم
                $frames = [];
                $frameNames = (array) ($_POST['frame_name'] ?? []);
                $framePrices = (array) ($_POST['frame_price'] ?? []);
                foreach ($frameNames as $i => $fn) {
                    $fn = trim((string) $fn);
                    if ($fn === '') { continue; }
                    $fp = max(0, (int) ($framePrices[$i] ?? 0));
                    $frames[] = ['name' => $fn, 'price' => $fp];
                }
                $framesJson = $frames !== [] ? json_encode($frames, JSON_UNESCAPED_UNICODE) : null;
                $catId = (int) ($_POST['category_id'] ?? 0);
                if ($catId <= 0 || get_category($catId) === null) {
                    $catId = null;
                }
                $old = $pid > 0 ? get_product($pid) : null;
                $image = (string) ($old['image'] ?? '');
                if (!empty($_POST['remove_image']) && $image !== '') {
                    $oldPath = UPLOADS_DIR . '/' . basename($image);
                    if (is_file($oldPath)) { @unlink($oldPath); }
                    $image = '';
                }
                $up = handle_section_image_upload($_FILES['image'] ?? null, $image !== '' ? $image : null);
                if (!$up['ok']) {
                    throw new RuntimeException((string) $up['error']);
                }
                $image = (string) ($up['filename'] ?? '');
                $laborMeter = max(0, (int) ($_POST['labor_cost_per_meter'] ?? 0));
                $laborFixture = max(0, (int) ($_POST['labor_cost_per_fixture'] ?? 0));
                $ofcCfg = json_encode([
                    'show_length'  => isset($_POST['ofc_show_length']),
                    'show_qty'     => isset($_POST['ofc_show_qty']),
                    'show_wire'    => isset($_POST['ofc_show_wire']),
                    'show_endcap'  => isset($_POST['ofc_show_endcap']),
                    'show_options' => isset($_POST['ofc_show_options']),
                ], JSON_UNESCAPED_UNICODE);
                $data = [
                    ':cat'     => $catId,
                    ':name'    => $name,
                    ':sku'     => trim((string) ($_POST['sku'] ?? '')) ?: null,
                    ':desc'    => trim((string) ($_POST['description'] ?? '')) ?: null,
                    ':image'   => $image ?: null,
                    ':price'   => $price,
                    ':pprice'  => $partnerPrice,
                    ':pmodel'  => $pricingModel,
                    ':ppw'     => $pricePerWatt,
                    ':bprice'  => $basePrice,
                    ':frames'  => $framesJson,
                    ':labor_m' => $laborMeter,
                    ':labor_f' => $laborFixture,
                    ':ofc'      => $ofcCfg,
                    ':active'  => isset($_POST['is_active']) ? 1 : 0,
                    ':sort'    => (int) ($_POST['sort_order'] ?? 0),
                    ':prep'    => max(0, (int) ($_POST['prep_days'] ?? 0)),
                    ':seo_t'   => trim((string) ($_POST['seo_title'] ?? '')) ?: null,
                    ':seo_d'   => trim((string) ($_POST['seo_description'] ?? '')) ?: null,
                    ':seo_k'   => trim((string) ($_POST['seo_keywords'] ?? '')) ?: null,
                ];
                if ($action === 'update_product' && $pid > 0) {
                    $data[':id'] = $pid;
                    $pdo->prepare('UPDATE products SET category_id = :cat, name = :name, sku = :sku, description = :desc, image = :image, price_per_meter = :price, partner_price_per_meter = :pprice, pricing_model = :pmodel, price_per_watt = :ppw, base_price = :bprice, frame_options_json = :frames, labor_cost_per_meter = :labor_m, labor_cost_per_fixture = :labor_f, order_form_config = :ofc, seo_title = :seo_t, seo_description = :seo_d, seo_keywords = :seo_k, is_active = :active, sort_order = :sort, prep_days = :prep, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                    flash('ok', 'محصول به‌روزرسانی شد.');
                } else {
                    $pdo->prepare('INSERT INTO products (category_id, name, sku, description, image, price_per_meter, partner_price_per_meter, pricing_model, price_per_watt, base_price, frame_options_json, labor_cost_per_meter, labor_cost_per_fixture, order_form_config, seo_title, seo_description, seo_keywords, is_active, sort_order, prep_days) VALUES (:cat, :name, :sku, :desc, :image, :price, :pprice, :pmodel, :ppw, :bprice, :frames, :labor_m, :labor_f, :ofc, :seo_t, :seo_d, :seo_k, :active, :sort, :prep)')->execute($data);
                    $pid = (int) $pdo->lastInsertId();
                    flash('ok', 'محصول جدید ثبت شد.');
                }
                // مقادیر ویژگی‌های محصول
                $delVals = $pdo->prepare('DELETE FROM product_attribute_values WHERE product_id = :p AND attribute_id = :a');
                $insVal  = $pdo->prepare('INSERT INTO product_attribute_values (product_id, attribute_id, option_id, num_value, text_value) VALUES (:p, :a, :o, :n, :t)');
                foreach (get_attributes(false) as $attr) {
                    $aid = (int) $attr['id'];
                    if (($attr['input_type'] ?? 'select') === 'select') {
                        $selId = (int) ($_POST['attr_' . $aid] ?? 0);
                        $valid = false;
                        if ($selId > 0) {
                            foreach (get_attribute_options($aid) as $opt) {
                                if ((int) $opt['id'] === $selId) { $valid = true; break; }
                            }
                        }
                        $delVals->execute([':p' => $pid, ':a' => $aid]);
                        if ($valid) {
                            $insVal->execute([':p' => $pid, ':a' => $aid, ':o' => $selId, ':n' => null, ':t' => null]);
                        }
                    } elseif ($attr['input_type'] === 'number') {
                        $raw = trim((string) ($_POST['attr_' . $aid] ?? ''));
                        $delVals->execute([':p' => $pid, ':a' => $aid]);
                        if ($raw !== '' && is_numeric($raw)) {
                            $insVal->execute([':p' => $pid, ':a' => $aid, ':o' => null, ':n' => (float) $raw, ':t' => null]);
                        }
                    } else {
                        $raw = trim((string) ($_POST['attr_' . $aid] ?? ''));
                        $delVals->execute([':p' => $pid, ':a' => $aid]);
                        if ($raw !== '') {
                            $insVal->execute([':p' => $pid, ':a' => $aid, ':o' => null, ':n' => null, ':t' => $raw]);
                        }
                    }
                }
                // فرمول ساخت (BOM) — مواد مصرفی محصول (فاز ۲٫۵): ردیف‌های معتبر جایگزین قبلی‌ها می‌شوند؛ ترکیب تکراری (ماده+مبنای مصرف) با هم جمع می‌شود
                $pdo->prepare('DELETE FROM product_materials WHERE product_id = :p')->execute([':p' => $pid]);
                $bomMids  = $_POST['bom_material_id'] ?? [];
                $bomQtys  = $_POST['bom_qty'] ?? [];
                $bomBases = $_POST['bom_basis'] ?? [];
                $bomConds = $_POST['bom_cond'] ?? [];
                if (is_array($bomMids)) {
                    $acc = [];
                    $orderKeys = [];
                    foreach ($bomMids as $bi => $midRaw) {
                        $mid = (int) $midRaw;
                        $qty = (float) ($bomQtys[$bi] ?? 0);
                        $basis = (string) ($bomBases[$bi] ?? 'per_meter');
                        $cond = (string) ($bomConds[$bi] ?? 'always');
                        if ($mid <= 0 || $qty <= 0 || !in_array($basis, ['per_meter', 'per_fixture'], true) || get_material($mid) === null) {
                            continue;
                        }
                        // شرط «فقط وقتی درپوش دارد» فقط برای مصرف به‌ازای هر چراغ معنا دارد
                        if ($basis !== 'per_fixture' || $cond !== 'endcap') {
                            $cond = 'always';
                        }
                        $key = $mid . '|' . $basis . '|' . $cond;
                        if (!isset($acc[$key])) {
                            $acc[$key] = ['mid' => $mid, 'qty' => 0.0, 'basis' => $basis, 'cond' => $cond];
                            $orderKeys[] = $key;
                        }
                        $acc[$key]['qty'] += $qty;
                    }
                    $insBom = $pdo->prepare('INSERT INTO product_materials (product_id, material_id, qty, basis, apply_condition, sort_order) VALUES (:p, :m, :q, :b, :c, :s)');
                    $bso = 0;
                    foreach ($orderKeys as $key) {
                        $bso += 10;
                        $insBom->execute([':p' => $pid, ':m' => $acc[$key]['mid'], ':q' => round($acc[$key]['qty'], 6), ':b' => $acc[$key]['basis'], ':c' => $acc[$key]['cond'], ':s' => $bso]);
                    }
                }
                // افزودن فیلد سفارشی فرم سفارش (نسخه ۹٫۱)
                if (!empty($_POST['add_order_field']) && $pid > 0) {
                    $flabel = trim((string) ($_POST['new_field_label'] ?? ''));
                    $ftype = (string) ($_POST['new_field_type'] ?? 'text');
                    if (!in_array($ftype, ['text', 'number', 'select', 'textarea', 'checkbox'], true)) {
                        $ftype = 'text';
                    }
                    if ($flabel !== '') {
                        $fopts = trim((string) ($_POST['new_field_options'] ?? ''));
                        $optsArr = [];
                        if ($fopts !== '') {
                            foreach (explode('|', $fopts) as $op) {
                                $op = trim($op);
                                if ($op !== '') { $optsArr[] = $op; }
                            }
                        }
                        $maxSort = (int) $pdo->query("SELECT COALESCE(MAX(sort_order), 0) FROM order_form_fields WHERE owner_type = 'product' AND owner_id = " . $pid)->fetchColumn();
                        $pdo->prepare("INSERT INTO order_form_fields (owner_type, owner_id, field_type, label, options_json, is_required, sort_order) VALUES ('product', :p, :t, :l, :o, :r, :s)")
                            ->execute([':p' => $pid, ':t' => $ftype, ':l' => $flabel, ':o' => $optsArr !== [] ? json_encode($optsArr, JSON_UNESCAPED_UNICODE) : null, ':r' => !empty($_POST['new_field_required']) ? 1 : 0, ':s' => $maxSort + 10]);
                        flash('ok', 'فیلد سفارشی اضافه شد.');
                    } else {
                        flash('error', 'برچسب فیلد را وارد کنید.');
                    }
                    redirect_admin('admin.php?page=products&edit_id=' . $pid);
                }
                // افزودن عکس به گالری محصول (نسخه ۹٫۱)
                if (!empty($_POST['add_gallery_image']) && $pid > 0 && !empty($_FILES['gallery_image']['tmp_name'])) {
                    $gup = handle_section_image_upload($_FILES['gallery_image'] ?? null, null);
                    if ($gup['ok'] && !empty($gup['filename'])) {
                        $maxSort = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ' . $pid)->fetchColumn();
                        $pdo->prepare('INSERT INTO product_images (product_id, image, caption, sort_order) VALUES (:p, :i, :c, :s)')
                            ->execute([':p' => $pid, ':i' => $gup['filename'], ':c' => trim((string) ($_POST['gallery_caption'] ?? '')), ':s' => $maxSort + 10]);
                        flash('ok', 'عکس به گالری محصول اضافه شد.');
                    } else {
                        flash('error', (string) ($gup['error'] ?? 'خطا در آپلود عکس.'));
                    }
                    redirect_admin('admin.php?page=products&edit_id=' . $pid . '#gallery');
                }
                redirect_admin('admin.php?page=products');
                // no break

            case 'delete_product_image':
                $giid = (int) ($_POST['id'] ?? 0);
                $gpid = (int) ($_POST['product_id'] ?? 0);
                $grow = $pdo->query('SELECT * FROM product_images WHERE id = ' . $giid)->fetch(PDO::FETCH_ASSOC);
                if ($grow) {
                    @unlink(UPLOADS_DIR . '/' . basename((string) $grow['image']));
                    $pdo->prepare('DELETE FROM product_images WHERE id = :id')->execute([':id' => $giid]);
                    flash('ok', 'عکس از گالری حذف شد.');
                }
                redirect_admin('admin.php?page=products&edit_id=' . $gpid . '#gallery');
                // no break

            case 'move_product_image':
                $giid = (int) ($_POST['id'] ?? 0);
                $gpid = (int) ($_POST['product_id'] ?? 0);
                $gdir = (int) ($_POST['dir'] ?? 0);
                $cur = $pdo->query('SELECT * FROM product_images WHERE id = ' . $giid)->fetch(PDO::FETCH_ASSOC);
                if ($cur) {
                    $op = $gdir < 0 ? '<' : '>';
                    $ord = $gdir < 0 ? 'DESC' : 'ASC';
                    $nbr = $pdo->query('SELECT * FROM product_images WHERE product_id = ' . (int) $cur['product_id'] . ' AND sort_order ' . $op . ' ' . (int) $cur['sort_order'] . ' ORDER BY sort_order ' . $ord . ' LIMIT 1')->fetch(PDO::FETCH_ASSOC);
                    if ($nbr) {
                        $pdo->prepare('UPDATE product_images SET sort_order = :s WHERE id = :id')->execute([':s' => $nbr['sort_order'], ':id' => $cur['id']]);
                        $pdo->prepare('UPDATE product_images SET sort_order = :s WHERE id = :id')->execute([':s' => $cur['sort_order'], ':id' => $nbr['id']]);
                    }
                }
                redirect_admin('admin.php?page=products&edit_id=' . $gpid . '#gallery');
                // no break

            case 'quick_price_product':
                $qpid = (int) ($_POST['id'] ?? 0);
                $qprice = max(0, (int) ($_POST['price_per_meter'] ?? 0));
                $qpartnerRaw = trim((string) ($_POST['partner_price_per_meter'] ?? ''));
                $qpartner = $qpartnerRaw === '' ? null : max(0, (int) $qpartnerRaw);
                $pdo->prepare('UPDATE products SET price_per_meter = :p, partner_price_per_meter = :pp, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                    ->execute([':p' => $qprice, ':pp' => $qpartner, ':id' => $qpid]);
                flash('ok', 'قیمت محصول به‌روز شد.');
                redirect_admin('admin.php?page=products');
                // no break

            case 'delete_order_field':
                $ffid = (int) ($_POST['id'] ?? 0);
                $fpid = (int) ($_POST['product_id'] ?? 0);
                $pdo->prepare('DELETE FROM order_form_fields WHERE id = :id')->execute([':id' => $ffid]);
                flash('ok', 'فیلد سفارشی حذف شد.');
                redirect_admin('admin.php?page=products&edit_id=' . $fpid);
                // no break

            case 'delete_product':
                $pid = (int) ($_POST['id'] ?? 0);
                $prod = get_product($pid);
                $pdo->prepare('DELETE FROM product_attribute_values WHERE product_id = :p')->execute([':p' => $pid]);
                $pdo->prepare('DELETE FROM product_materials WHERE product_id = :p')->execute([':p' => $pid]);
                $pdo->prepare('DELETE FROM order_form_fields WHERE owner_type = :t AND owner_id = :p')->execute([':t' => 'product', ':p' => $pid]);
                foreach ($pdo->query('SELECT image FROM product_images WHERE product_id = ' . $pid)->fetchAll(PDO::FETCH_COLUMN) as $gimg) {
                    @unlink(UPLOADS_DIR . '/' . basename((string) $gimg));
                }
                $pdo->prepare('DELETE FROM product_images WHERE product_id = :p')->execute([':p' => $pid]);
                $pdo->prepare('DELETE FROM products WHERE id = :id')->execute([':id' => $pid]);
                if ($prod !== null && !empty($prod['image'])) {
                    @unlink(UPLOADS_DIR . '/' . basename((string) $prod['image']));
                }
                flash('ok', 'محصول حذف شد.');
                redirect_admin('admin.php?page=products');
                // no break

            case 'move_product':
                move_row($pdo, 'products', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
                redirect_admin('admin.php?page=products');
                // no break

            // ---------- فاز ۲: ویژگی‌های محصول و گزینه‌ها ----------
            case 'add_attribute':
            case 'update_attribute':
                $aid = (int) ($_POST['id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($title === '') {
                    throw new RuntimeException('عنوان ویژگی را وارد کنید.');
                }
                $inputType = (string) ($_POST['input_type'] ?? 'select');
                if (!in_array($inputType, ['select', 'number', 'text'], true)) {
                    $inputType = 'select';
                }
                $attrKey = trim((string) ($_POST['attr_key'] ?? ''));
                if ($attrKey === '') {
                    $attrKey = 'attr_' . bin2hex(random_bytes(3));
                }
                $dupe = $pdo->prepare('SELECT COUNT(*) FROM product_attributes WHERE attr_key = :k AND id != :x');
                $dupe->execute([':k' => $attrKey, ':x' => $aid]);
                if ((int) $dupe->fetchColumn() > 0) {
                    throw new RuntimeException('این کلید ویژگی قبلاً استفاده شده است.');
                }
                $data = [
                    ':title' => $title,
                    ':key'   => $attrKey,
                    ':type'  => $inputType,
                    ':unit'  => trim((string) ($_POST['unit'] ?? '')) ?: null,
                    ':sort'  => (int) ($_POST['sort_order'] ?? 0),
                    ':active' => isset($_POST['is_active']) ? 1 : 0,
                ];
                if ($action === 'update_attribute' && $aid > 0) {
                    $data[':id'] = $aid;
                    $pdo->prepare('UPDATE product_attributes SET title = :title, attr_key = :key, input_type = :type, unit = :unit, sort_order = :sort, is_active = :active WHERE id = :id')->execute($data);
                    flash('ok', 'ویژگی به‌روزرسانی شد.');
                    redirect_admin('admin.php?page=attributes&edit_id=' . $aid);
                }
                $pdo->prepare('INSERT INTO product_attributes (title, attr_key, input_type, unit, sort_order, is_active) VALUES (:title, :key, :type, :unit, :sort, :active)')->execute($data);
                flash('ok', 'ویژگی جدید ساخته شد. حالا گزینه‌هایش را اضافه کنید.');
                redirect_admin('admin.php?page=attributes&edit_id=' . (int) $pdo->lastInsertId());
                // no break

            case 'delete_attribute':
                $aid = (int) ($_POST['id'] ?? 0);
                $pdo->prepare('DELETE FROM product_attribute_values WHERE attribute_id = :a')->execute([':a' => $aid]);
                $pdo->prepare('DELETE FROM product_attribute_options WHERE attribute_id = :a')->execute([':a' => $aid]);
                $pdo->prepare('DELETE FROM product_attributes WHERE id = :id')->execute([':id' => $aid]);
                flash('ok', 'ویژگی، گزینه‌ها و مقادیرش از محصولات حذف شد.');
                redirect_admin('admin.php?page=attributes');
                // no break

            case 'move_attribute':
                move_row($pdo, 'product_attributes', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
                redirect_admin('admin.php?page=attributes');
                // no break

            case 'add_option':
            case 'update_option':
                $oid = (int) ($_POST['id'] ?? 0);
                $aid = (int) ($_POST['attribute_id'] ?? 0);
                $title = trim((string) ($_POST['title'] ?? ''));
                if ($aid <= 0 || get_attribute($aid) === null) {
                    throw new RuntimeException('ویژگی گزینه مشخص نیست.');
                }
                if ($title === '') {
                    throw new RuntimeException('عنوان گزینه را وارد کنید.');
                }
                $data = [
                    ':a'     => $aid,
                    ':title' => $title,
                    ':delta' => (int) ($_POST['price_delta_per_meter'] ?? 0),
                    ':sort'  => (int) ($_POST['sort_order'] ?? 0),
                ];
                if ($action === 'update_option' && $oid > 0) {
                    $data[':id'] = $oid;
                    $pdo->prepare('UPDATE product_attribute_options SET attribute_id = :a, title = :title, price_delta_per_meter = :delta, sort_order = :sort WHERE id = :id')->execute($data);
                    flash('ok', 'گزینه به‌روزرسانی شد.');
                } else {
                    $pdo->prepare('INSERT INTO product_attribute_options (attribute_id, title, price_delta_per_meter, sort_order) VALUES (:a, :title, :delta, :sort)')->execute($data);
                    flash('ok', 'گزینه جدید اضافه شد.');
                }
                redirect_admin('admin.php?page=attributes&edit_id=' . $aid);
                // no break

            case 'delete_option':
                $oid = (int) ($_POST['id'] ?? 0);
                $aid = (int) ($_POST['attribute_id'] ?? 0);
                $pdo->prepare('DELETE FROM product_attribute_values WHERE option_id = :o')->execute([':o' => $oid]);
                $pdo->prepare('DELETE FROM product_attribute_options WHERE id = :id')->execute([':id' => $oid]);
                flash('ok', 'گزینه حذف شد.');
                redirect_admin('admin.php?page=attributes&edit_id=' . $aid);
                // no break

            case 'move_option':
                move_row($pdo, 'product_attribute_options', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
                redirect_admin('admin.php?page=attributes&edit_id=' . (int) ($_POST['attribute_id'] ?? 0));
                // no break
    }
}

/** بارگذاری داده‌های صفحه‌های کاتالوگ (همان کوئری‌ها و متغیرهای قبلی) */
function catalog_load_data(string $page): array
{
$customersList = [];
$customerSearch = '';
$customerTypeFilter = '';
$viewCustomer = null;
$viewCustomerOrders = [];
$editCustomer = null;
if ($page === 'customers') {
    $customerSearch = trim((string) ($_GET['q'] ?? ''));
    $customerTypeFilter = trim((string) ($_GET['type'] ?? ''));
    $customersList = get_customers($customerSearch, $customerTypeFilter);
    if (isset($_GET['view'])) {
        $viewCustomer = get_customer((int) $_GET['view']);
        if ($viewCustomer !== null) {
            $ost = db()->prepare('SELECT * FROM orders WHERE customer_id = :c ORDER BY id DESC LIMIT 100');
            $ost->execute([':c' => (int) $viewCustomer['id']]);
            $viewCustomerOrders = $ost->fetchAll();
            // خلاصه مالی مشتری (۹٫۲۵)
            $cid = (int) $viewCustomer['id'];
            $viewCustomerTotalOrders = (int) db()->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE customer_id = ' . $cid)->fetchColumn();
            $viewCustomerTotalPaid = (int) db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = " . $cid . " AND kind = 'receipt'")->fetchColumn();
            $viewCustomerBalance = $viewCustomerTotalPaid - $viewCustomerTotalOrders; // مثبت = بستانکار، منفی = بدهکار
            $viewCustomerCreditLimit = (int) ($viewCustomer['credit_limit'] ?? 0);
            $viewCustomerCreditRemain = $viewCustomerCreditLimit + $viewCustomerBalance; // اعتبار باقی‌مانده
            $pst = db()->prepare('SELECT p.*, o.order_no FROM payments p LEFT JOIN orders o ON o.id = p.order_id WHERE p.customer_id = :c ORDER BY p.paid_at DESC LIMIT 50');
            $pst->execute([':c' => $cid]);
            $viewCustomerPayments = $pst->fetchAll();
        }
    }
    if (isset($_GET['edit_id'])) {
        $editCustomer = get_customer((int) $_GET['edit_id']);
    }
}
$categoriesList = get_categories(false);
$editCategory = null;
if ($page === 'categories' && isset($_GET['edit_id'])) {
    $editCategory = get_category((int) $_GET['edit_id']);
}
$productsList = [];
$editProduct = null;
$editProductValues = [];
$editProductBom = [];
$allMaterials = get_materials(false);
$estLength = null;
$estFixtures = 1;
$estRows = [];
if ($page === 'products') {
    $productsList = get_products(false);
    if (isset($_GET['edit_id'])) {
        $editProduct = get_product((int) $_GET['edit_id']);
        if ($editProduct !== null) {
            foreach (get_product_attribute_values((int) $editProduct['id']) as $v) {
                $editProductValues[(int) $v['attribute_id']] = $v;
            }
            // فرمول ساخت (مواد مصرفی) محصول — فاز ۲٫۵
            $bomStmt = db()->query('SELECT * FROM product_materials WHERE product_id = ' . (int) $editProduct['id'] . ' ORDER BY sort_order ASC, id ASC');
            $editProductBom = $bomStmt->fetchAll();
            if (isset($_GET['est_length'])) {
                $estLength = max(0.0, (float) $_GET['est_length']);
                $estFixtures = max(0, (int) ($_GET['est_fixtures'] ?? 1));
                $estRows = product_required_materials((int) $editProduct['id'], $estLength, $estFixtures);
            }
        }
    }
}
$attributesList = [];
$editAttribute = null;
$editAttributeOptions = [];
$editOption = null;
if ($page === 'attributes') {
    $attributesList = get_attributes(false);
    if (isset($_GET['edit_id'])) {
        $editAttribute = get_attribute((int) $_GET['edit_id']);
        if ($editAttribute !== null) {
            $editAttributeOptions = get_attribute_options((int) $editAttribute['id']);
            if (isset($_GET['edit_option'])) {
                foreach ($editAttributeOptions as $opt) {
                    if ((int) $opt['id'] === (int) $_GET['edit_option']) { $editOption = $opt; break; }
                }
            }
        }
    }
}
    return [
        'customersList' => $customersList,
        'customerSearch' => $customerSearch,
        'customerTypeFilter' => $customerTypeFilter,
        'viewCustomer' => $viewCustomer,
        'viewCustomerOrders' => $viewCustomerOrders,
        'editCustomer' => $editCustomer,
        'categoriesList' => $categoriesList,
        'editCategory' => $editCategory,
        'productsList' => $productsList,
        'editProduct' => $editProduct,
        'editProductValues' => $editProductValues,
        'editProductBom' => $editProductBom,
        'allMaterials' => $allMaterials,
        'estLength' => $estLength,
        'estFixtures' => $estFixtures,
        'estRows' => $estRows,
        'attributesList' => $attributesList,
        'editAttribute' => $editAttribute,
        'editAttributeOptions' => $editAttributeOptions,
        'editOption' => $editOption,
    ];
}

/** رندر صفحه «مشتری‌ها» */
function payment_method_title(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        try {
            foreach (db()->query('SELECT method_key, title FROM payment_methods')->fetchAll() as $r) {
                $map[(string) $r['method_key']] = (string) $r['title'];
            }
        } catch (Throwable $e) {}
    }
    return $map[$key] ?? $key;
}

function catalog_render_customers(array $d): void
{
    extract($d);
    ?>            <?php if ($viewCustomer !== null): ?>
                <h1>پروفایل مشتری</h1>
                <p><a href="admin.php?page=customers">← بازگشت به فهرست مشتری‌ها</a></p>
                <section class="card wide">
                    <h2><?= e($viewCustomer['full_name']) ?> <span class="badge ok"><?= e(customer_type_label((string) $viewCustomer['customer_type'])) ?></span></h2>
                    <table>
                        <tbody>
                            <tr><th>شرکت</th><td><?= e($viewCustomer['company'] ?? '—') ?></td></tr>
                            <tr><th>موبایل</th><td><span dir="ltr"><?= e($viewCustomer['mobile']) ?></span></td></tr>
                            <tr><th>شهر</th><td><?= e($viewCustomer['city'] ?? '—') ?></td></tr>
                            <tr><th>نشانی</th><td><?= nl2br(e($viewCustomer['address'] ?? '—')) ?></td></tr>
                            <tr><th>یادداشت</th><td><?= nl2br(e($viewCustomer['notes'] ?? '—')) ?></td></tr>
                            <tr><th>تاریخ ثبت</th><td><?= e($viewCustomer['created_at'] ?? '') ?></td></tr>
                        </tbody>
                    </table>
                    <p>
                        <a class="btn small edit" href="admin.php?page=customers&edit_id=<?= (int) $viewCustomer['id'] ?>">ویرایش مشتری</a>
                    </p>
                </section>
                <section class="card wide">
                    <h2>وضعیت مالی</h2>
                    <div class="stat-grid" style="margin-top:0">
                        <div class="stat-card"><span>جمع سفارش‌ها</span><strong><?= e(format_price($viewCustomerTotalOrders ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>جمع دریافتی</span><strong><?= e(format_price($viewCustomerTotalPaid ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>تراز مالی</span><strong style="color:<?= ($viewCustomerBalance ?? 0) >= 0 ? '#16a34a' : '#dc2626' ?>"><?= e(format_price($viewCustomerBalance ?? 0)) ?></strong><span class="muted" style="font-size:11px"><?= ($viewCustomerBalance ?? 0) >= 0 ? 'بستانکار' : 'بدهکار' ?></span></div>
                        <div class="stat-card"><span>سقف اعتبار</span><strong><?= e(format_price($viewCustomerCreditLimit ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>اعتبار باقی‌مانده</span><strong><?= e(format_price($viewCustomerCreditRemain ?? 0)) ?></strong></div>
                    </div>
                    <h3 style="margin-top:16px">سابقه مالی (<?= count($viewCustomerPayments ?? []) ?>)</h3>
                    <?php if (empty($viewCustomerPayments)): ?>
                        <p class="muted">پرداختی ثبت نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>تاریخ</th><th>مبلغ (تومان)</th><th>روش</th><th>سفارش</th><th>یادداشت</th></tr></thead>
                        <tbody>
                        <?php foreach ($viewCustomerPayments as $vp): ?>
                            <tr>
                                <td class="muted"><?= e(mb_substr((string) ($vp['paid_at'] ?? ''), 0, 10)) ?></td>
                                <td><?= e(format_price($vp['amount'] ?? 0)) ?></td>
                                <td><?= e(payment_method_title((string) ($vp['method_key'] ?? ''))) ?></td>
                                <td><?= !empty($vp['order_no']) ? '#' . (int) $vp['order_no'] : '—' ?></td>
                                <td class="muted"><?= e($vp['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>
                    <form method="post" class="inline" onsubmit="return confirm('این مشتری حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_customer">
                        <input type="hidden" name="id" value="<?= (int) $viewCustomer['id'] ?>">
                        <button type="submit" class="btn small danger-btn">حذف مشتری</button>
                    </form>
                </section>
                <section class="card wide">
                    <h2>سفارش‌های این مشتری (<?= count($viewCustomerOrders) ?>)</h2>
                    <?php if ($viewCustomerOrders === []): ?>
                        <p class="muted">هنوز سفارشی برای این مشتری ثبت نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>شماره</th><th>متراژ</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($viewCustomerOrders as $vo): ?>
                            <tr>
                                <td><strong>#<?= (int) $vo['order_no'] ?></strong></td>
                                <td><?= e(format_qty((float) ($vo['total_meters'] ?? 0))) ?> متر</td>
                                <td><?= e(format_price($vo['total'] ?? 0)) ?></td>
                                <td><span class="badge" style="background:<?= e(order_status_color((string) $vo['status'])) ?>22;color:<?= e(order_status_color((string) $vo['status'])) ?>"><?= e(order_status_title((string) $vo['status'])) ?></span></td>
                                <td class="muted"><?= e(mb_substr((string) ($vo['created_at'] ?? ''), 0, 10)) ?></td>
                                <td class="actions"><a class="btn small" href="admin.php?page=order_view&id=<?= (int) $vo['id'] ?>">جزئیات</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <p><a class="btn small" href="admin.php?page=order_new">+ سفارش تازه برای این مشتری</a></p>
                </section>
            <?php else: ?>
                <h1>مشتری‌ها</h1>
                <p class="muted">بیشتر مشتری‌های لاینرلایت همکار هستند؛ نوع هر مشتری (همکار/مشتری/شرکت) مشخص می‌کند قیمت همکاری برایش حساب شود یا قیمت عادی. شماره موبایل برای هر مشتری یکتاست.</p>
                <form method="get" class="card wide">
                    <input type="hidden" name="page" value="customers">
                    <label>جستجو (نام، شرکت یا موبایل)
                        <input type="text" name="q" value="<?= e($customerSearch) ?>" placeholder="مثلاً: کابینت پارس یا 0912">
                    </label>
                    <label>نوع مشتری
                        <select name="type">
                            <option value="">همه انواع</option>
                            <?php foreach (customer_types() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $customerTypeFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn primary">جستجو</button>
                    <?php if ($customerSearch !== '' || $customerTypeFilter !== ''): ?><a class="btn" href="admin.php?page=customers">حذف فیلتر</a><?php endif; ?>
                </form>

                <?php if ($editCustomer === null): ?>
                <div class="crud-toolbar">
                    <button type="button" class="btn add" data-toggle-panel="customer-form-panel" aria-expanded="false">+ افزودن مشتری</button>
                </div>
                <?php endif; ?>
                <div class="crud-panel" id="customer-form-panel" <?= $editCustomer !== null ? 'data-open="1"' : 'hidden' ?>>
                <h2><?= $editCustomer !== null ? 'ویرایش مشتری: ' . e($editCustomer['full_name'] ?? '') : 'افزودن مشتری تازه' ?></h2>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editCustomer !== null ? 'update_customer' : 'add_customer' ?>">
                    <?php if ($editCustomer !== null): ?><input type="hidden" name="id" value="<?= (int) $editCustomer['id'] ?>"><?php endif; ?>
                    <label>نام و نام خانوادگی *
                        <input type="text" name="full_name" required value="<?= e($editCustomer['full_name'] ?? '') ?>">
                    </label>
                    <label>شرکت / فروشگاه
                        <input type="text" name="company" value="<?= e($editCustomer['company'] ?? '') ?>">
                    </label>
                    <label>موبایل *
                        <input type="text" name="mobile" required dir="ltr" value="<?= e($editCustomer['mobile'] ?? '') ?>" placeholder="09xxxxxxxxx">
                    </label>
                    <label>نوع مشتری
                        <select name="customer_type">
                            <?php foreach (customer_types() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= ($editCustomer['customer_type'] ?? 'partner') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>شهر
                        <input type="text" name="city" value="<?= e($editCustomer['city'] ?? '') ?>">
                    </label>
                    <label>نشانی
                        <textarea name="address" rows="2"><?= e($editCustomer['address'] ?? '') ?></textarea>
                    </label>
                    <label>یادداشت
                        <textarea name="notes" rows="2"><?= e($editCustomer['notes'] ?? '') ?></textarea>
                    </label>
                    <label>سقف اعتبار (تومان)
                        <input type="number" name="credit_limit" min="0" step="1000" value="<?= e((string) ($editCustomer['credit_limit'] ?? 0)) ?>">
                    </label>
                    <button type="submit" class="btn <?= $editCustomer !== null ? 'edit' : 'add' ?>"><?= $editCustomer !== null ? 'ذخیره تغییرات' : 'ثبت مشتری' ?></button>
                    <?php if ($editCustomer !== null): ?><a class="btn" href="admin.php?page=customers">انصراف</a><?php endif; ?>
                </form>
                </div>

                <h2>فهرست مشتری‌ها (<?= count($customersList) ?>)</h2>
                <?php if ($customersList === []): ?>
                    <div class="card wide"><p class="muted">هنوز مشتری‌ای ثبت نشده است. اولین مشتری را با دکمه «+ افزودن مشتری» بسازید؛ بعداً سفارش‌ها به همین مشتری‌ها وصل می‌شوند.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>نام</th><th>شرکت</th><th>موبایل</th><th>نوع</th><th>شهر</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($customersList as $c): ?>
                        <tr>
                            <td><a href="admin.php?page=customers&view=<?= (int) $c['id'] ?>"><?= e($c['full_name']) ?></a></td>
                            <td><?= e($c['company'] ?? '—') ?></td>
                            <td><span dir="ltr"><?= e($c['mobile']) ?></span></td>
                            <td><span class="badge ok"><?= e(customer_type_label((string) $c['customer_type'])) ?></span></td>
                            <td><?= e($c['city'] ?? '—') ?></td>
                            <td class="actions">
                                <a class="btn small" href="admin.php?page=customers&view=<?= (int) $c['id'] ?>">پروفایل</a>
                                <a class="btn small edit" href="admin.php?page=customers&edit_id=<?= (int) $c['id'] ?>">ویرایش</a>
                                <form method="post" class="inline" onsubmit="return confirm('این مشتری حذف شود؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn small danger-btn">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «دسته‌بندی‌ها» */
function catalog_render_categories(array $d): void
{
    extract($d);
    ?>            <h1>دسته‌بندی‌های محصولات</h1>
            <p class="muted">دسته‌ها می‌توانند زیردسته داشته باشند. دسته‌ای که محصول یا زیردسته دارد حذف نمی‌شود. پیش‌نمایش زنده کاتالوگ: <a href="/products" target="_blank">/products</a></p>

            <?php if ($editCategory === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="category-form-panel" aria-expanded="false">+ افزودن دسته‌بندی</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="category-form-panel" <?= $editCategory !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editCategory !== null ? 'ویرایش دسته‌بندی: ' . e($editCategory['title'] ?? '') : 'دسته‌بندی تازه' ?></h2>
            <form method="post" enctype="multipart/form-data" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editCategory !== null ? 'update_category' : 'add_category' ?>">
                <?php if ($editCategory !== null): ?><input type="hidden" name="id" value="<?= (int) $editCategory['id'] ?>"><?php endif; ?>
                <label>عنوان دسته *
                    <input type="text" name="title" required value="<?= e($editCategory['title'] ?? '') ?>">
                </label>
                <label>نامک (slug) — خالی بماند خودکار ساخته می‌شود
                    <input type="text" name="slug" dir="ltr" value="<?= e($editCategory['slug'] ?? '') ?>" placeholder="wardrobe-linear">
                </label>
                <label>دسته والد
                    <select name="parent_id">
                        <option value="0">— بدون والد (دسته اصلی) —</option>
                        <?php foreach ($categoriesList as $cc): ?>
                            <?php if ($editCategory !== null && (int) $cc['id'] === (int) $editCategory['id']) { continue; } ?>
                            <option value="<?= (int) $cc['id'] ?>" <?= ($editCategory['parent_id'] ?? null) !== null && (int) $editCategory['parent_id'] === (int) $cc['id'] ? 'selected' : '' ?>><?= e($cc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>توضیح
                    <textarea name="description" rows="2"><?= e($editCategory['description'] ?? '') ?></textarea>
                </label>
                <label>عکس دسته
                    <input type="file" name="image" accept="image/*">
                </label>
                <?php if ($editCategory !== null && !empty($editCategory['image'])): ?>
                    <p><img src="<?= e(UPLOADS_URL . '/' . basename((string) $editCategory['image'])) ?>" alt="" style="max-width:180px;border-radius:8px"></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editCategory['sort_order'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editCategory['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (نمایش در کاتالوگ)
                </label>
                <button type="submit" class="btn <?= $editCategory !== null ? 'edit' : 'add' ?>"><?= $editCategory !== null ? 'ذخیره تغییرات' : 'ساخت دسته' ?></button>
                <?php if ($editCategory !== null): ?><a class="btn" href="admin.php?page=categories">انصراف</a><?php endif; ?>
            </form>
            </div>

            <h2>فهرست دسته‌ها (<?= count($categoriesList) ?>)</h2>
            <?php if ($categoriesList === []): ?>
                <div class="card wide"><p class="muted">هنوز دسته‌ای ساخته نشده است.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>عنوان</th><th>نامک</th><th>والد</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php
                $catTitles = [];
                foreach ($categoriesList as $cc) { $catTitles[(int) $cc['id']] = (string) $cc['title']; }
                foreach ($categoriesList as $cc):
                ?>
                    <tr>
                        <td><?= !empty($cc['parent_id']) ? '↳ ' : '' ?><?= e($cc['title']) ?></td>
                        <td><span dir="ltr"><?= e($cc['slug']) ?></span></td>
                        <td><?= !empty($cc['parent_id']) ? e($catTitles[(int) $cc['parent_id']] ?? '—') : '—' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑ بالا</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓ پایین</button>
                            </form>
                            <?= (int) $cc['sort_order'] ?>
                        </td>
                        <td><?= $cc['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small edit" href="admin.php?page=categories&edit_id=<?= (int) $cc['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این دسته حذف شود؟ (اگر محصول یا زیردسته داشته باشد حذف نمی‌شود)')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «محصولات» */
function catalog_render_products(array $d): void
{
    extract($d);
    ?>            <h1>محصولات</h1>
            <p class="muted">قیمت‌ها «متری» و به تومان هستند. آپشن‌ها (مثل سنسور) از بخش «ویژگی‌های محصول» تعریف می‌شوند و به قیمت متری اضافه می‌شوند. پیش‌نمایش زنده: <a href="/products" target="_blank">کاتالوگ عمومی</a></p>

            <?php if ($editProduct === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="product-form-panel" aria-expanded="false">+ افزودن محصول</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="product-form-panel" <?= $editProduct !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editProduct !== null ? 'ویرایش محصول: ' . e($editProduct['name'] ?? '') : 'محصول تازه' ?></h2>
            <form method="post" enctype="multipart/form-data" class="card wide" id="product-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editProduct !== null ? 'update_product' : 'add_product' ?>">
                <?php if ($editProduct !== null): ?><input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>"><?php endif; ?>
                <label>نام محصول *
                    <input type="text" name="name" required value="<?= e($editProduct['name'] ?? '') ?>">
                </label>
                <label>دسته‌بندی
                    <select name="category_id">
                        <option value="0">— بدون دسته —</option>
                        <?php foreach ($categoriesList as $cc): ?>
                            <option value="<?= (int) $cc['id'] ?>" <?= ($editProduct['category_id'] ?? null) !== null && (int) $editProduct['category_id'] === (int) $cc['id'] ? 'selected' : '' ?>><?= !empty($cc['parent_id']) ? '↳ ' : '' ?><?= e($cc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>کد محصول (SKU)
                    <input type="text" name="sku" dir="ltr" value="<?= e($editProduct['sku'] ?? '') ?>">
                </label>
                <label>توضیح
                    <textarea name="description" id="product-desc-editor" rows="3"><?= e($editProduct['description'] ?? '') ?></textarea>
                </label>
                <script src="assets/tinymce/tinymce.min.js"></script>
                <script src="assets/tinymce-init.js"></script>
                <script>initTinyMCE('#product-desc-editor', 280);</script>
                <label>عکس محصول
                    <input type="file" name="image" accept="image/*">
                </label>
                <?php if ($editProduct !== null && !empty($editProduct['image'])): ?>
                    <p><img src="<?= e(UPLOADS_URL . '/' . basename((string) $editProduct['image'])) ?>" alt="" style="max-width:220px;border-radius:8px"></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>قیمت متری مشتری (تومان) *
                    <input type="number" name="price_per_meter" id="pf-retail" min="0" step="any" required value="<?= (int) ($editProduct['price_per_meter'] ?? 0) ?>">
                </label>
                <label>تخفیف همکار — قیمت متری همکار (تومان) — خالی بماند تا درصد تخفیف همکار از تنظیمات اعمال شود
                    <input type="number" name="partner_price_per_meter" id="pf-partner" min="0" step="any" value="<?= ($editProduct['partner_price_per_meter'] ?? null) !== null && ($editProduct['partner_price_per_meter'] ?? '') !== '' ? (int) $editProduct['partner_price_per_meter'] : '' ?>" placeholder="خالی = خودکار با درصد همکار">
                </label>
                <label>مدل قیمت‌گذاری
                    <select name="pricing_model" id="pf-model">
                        <option value="per_meter" <?= (($editProduct['pricing_model'] ?? 'per_meter') === 'per_meter') ? 'selected' : '' ?>>متری (چراغ خطی)</option>
                        <option value="per_watt" <?= (($editProduct['pricing_model'] ?? '') === 'per_watt') ? 'selected' : '' ?>>سفارشی بر اساس وات (چراغ رشد گیاه)</option>
                    </select>
                </label>
                <div id="pf-watt-box" style="<?= (($editProduct['pricing_model'] ?? 'per_meter') === 'per_watt') ? '' : 'display:none' ?>">
                    <div class="inline-fields">
                        <label>قیمت هر وات (تومان)
                            <input type="number" name="price_per_watt" min="0" step="any" value="<?= (int) ($editProduct['price_per_watt'] ?? 0) ?>">
                        </label>
                        <label>قیمت شروع (تومان) — در کاتالوگ «از X تومان» نمایش داده می‌شود
                            <input type="number" name="base_price" min="0" step="any" value="<?= (int) ($editProduct['base_price'] ?? 0) ?>">
                        </label>
                    </div>
                    <h4 style="margin:12px 0 6px">قاب‌ها (مدل + قیمت هر کدام)</h4>
                    <p class="muted">قیمت صفر یعنی «طبق سفارش».</p>
                    <div id="frame-rows">
                        <?php
                        $frameRows = [];
                        if (!empty($editProduct['frame_options_json'])) {
                            $decoded = json_decode((string) $editProduct['frame_options_json'], true);
                            if (is_array($decoded)) { $frameRows = $decoded; }
                        }
                        if ($frameRows === []) { $frameRows = [['name' => 'سقفی', 'price' => 0], ['name' => 'آویز', 'price' => 0], ['name' => 'ریلی', 'price' => 0]]; }
                        $frameRows[] = ['name' => '', 'price' => ''];
                        foreach ($frameRows as $fr):
                        ?>
                        <div class="frame-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:end">
                            <label style="flex:2">مدل قاب
                                <input type="text" name="frame_name[]" value="<?= e((string) ($fr['name'] ?? '')) ?>" placeholder="مثلاً سقفی">
                            </label>
                            <label style="flex:1">قیمت (تومان)
                                <input type="number" name="frame_price[]" min="0" step="any" value="<?= ($fr['price'] ?? '') !== '' ? (int) $fr['price'] : '' ?>" placeholder="0 = طبق سفارش">
                            </label>
                            <button type="button" class="btn small frame-remove">حذف</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p><button type="button" class="btn small" id="frame-add">+ افزودن قاب</button></p>
                </div>
                <script>
                // Fallback: اگر اسکریپت فوتر admin.php لود نشده باشد، دکمه منوی موبایل کار کند
                if (!window.__toggleAdminNav) {
                    window.__toggleAdminNav = function(){
                        var b = document.body;
                        var mq = window.matchMedia('(max-width:899px)');
                        if (mq.matches) {
                            var op = b.classList.toggle('nav-open');
                            var t = document.getElementById('navToggle');
                            if (t) { t.setAttribute('aria-expanded', op ? 'true' : 'false'); }
                        } else {
                            b.classList.toggle('nav-rail');
                        }
                    };
                }
                </script>
                <script>
                (function(){
                    var modelSel = document.getElementById('pf-model');
                    var wattBox = document.getElementById('pf-watt-box');
                    if (modelSel && wattBox) {
                        modelSel.addEventListener('change', function(){
                            wattBox.style.display = modelSel.value === 'per_watt' ? '' : 'none';
                        });
                    }
                    var fbox = document.getElementById('frame-rows');
                    var fadd = document.getElementById('frame-add');
                    function bindRm(btn){ btn.addEventListener('click', function(){ btn.closest('.frame-row').remove(); }); }
                    if (fbox) { fbox.querySelectorAll('.frame-remove').forEach(bindRm); }
                    if (fadd && fbox) {
                        fadd.addEventListener('click', function(){
                            var first = fbox.querySelector('.frame-row');
                            if (!first) return;
                            var clone = first.cloneNode(true);
                            clone.querySelectorAll('input').forEach(function(el){ el.value = ''; });
                            fbox.appendChild(clone);
                            bindRm(clone.querySelector('.frame-remove'));
                        });
                    }
                })();
                </script>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editProduct['sort_order'] ?? 0) ?>">
                </label>
                <label>زمان آماده‌سازی این محصول (روز) — در سفارش، بیشترین زمان بین ردیف‌ها لحاظ می‌شود
                    <input type="number" name="prep_days" min="0" step="1" value="<?= (int) ($editProduct['prep_days'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (نمایش در کاتالوگ)
                </label>

                <h3>سئو (SEO)</h3>
                <p class="muted">اگر خالی بماند، از نام و توضیح محصول استفاده می‌شود.</p>
                <label>عنوان سئو (Title)
                    <input type="text" name="seo_title" value="<?= e((string) ($editProduct['seo_title'] ?? '')) ?>" maxlength="70" placeholder="حداکثر ۷۰ کاراکتر">
                </label>
                <label>توضیح متا (Meta Description)
                    <textarea name="seo_description" rows="2" maxlength="160" placeholder="حداکثر ۱۶۰ کاراکتر"><?= e((string) ($editProduct['seo_description'] ?? '')) ?></textarea>
                </label>
                <label>کلمات کلیدی (با کاما جدا کنید)
                    <input type="text" name="seo_keywords" value="<?= e((string) ($editProduct['seo_keywords'] ?? '')) ?>" placeholder="چراغ خطی, نورپردازی کمد, ...">
                </label>

                <h3>ویژگی‌ها و آپشن‌های این محصول</h3>
                <p class="muted">برای هر ویژگی انتخابی، گزینه پیش‌فرض محصول را انتخاب کنید تا در صفحه محصول و برآورد قیمت نمایش داده شود. «ارائه نمی‌شود» یعنی این محصول آن ویژگی را ندارد.</p>
                <?php foreach (get_attributes(false) as $attr): ?>
                    <?php $aid = (int) $attr['id']; $cur = $editProductValues[$aid] ?? null; ?>
                    <?php if ($attr['input_type'] === 'select'): ?>
                        <label><?= e($attr['title']) ?>
                            <select name="attr_<?= $aid ?>" class="pf-attr-select">
                                <option value="0">— ارائه نمی‌شود —</option>
                                <?php foreach (get_attribute_options($aid) as $opt): ?>
                                    <option value="<?= (int) $opt['id'] ?>" data-delta="<?= (float) $opt['price_delta_per_meter'] ?>" <?= $cur !== null && isset($cur['option_id']) && (int) $cur['option_id'] === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['title']) ?><?= (float) $opt['price_delta_per_meter'] != 0.0 ? ' (' . ($opt['price_delta_per_meter'] > 0 ? '+' : '') . e(format_price($opt['price_delta_per_meter'])) . ' تومان/متر)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php elseif ($attr['input_type'] === 'number'): ?>
                        <label><?= e($attr['title']) ?><?= !empty($attr['unit']) ? ' (' . e($attr['unit']) . ')' : '' ?>
                            <input type="number" step="any" name="attr_<?= $aid ?>" value="<?= $cur !== null && $cur['num_value'] !== null ? e((string) $cur['num_value']) : '' ?>">
                        </label>
                    <?php else: ?>
                        <label><?= e($attr['title']) ?>
                            <input type="text" name="attr_<?= $aid ?>" value="<?= $cur !== null ? e((string) ($cur['text_value'] ?? '')) : '' ?>">
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>پیش‌نمایش قیمت واحد (هر متر):</strong>
                    <div>قیمت مشتری: <strong id="pf-retail-preview">۰</strong> تومان</div>
                    <div>قیمت همکار: <strong id="pf-partner-preview">۰</strong> تومان</div>
                    <p class="muted">با تغییر قیمت یا آپشن‌ها، این پیش‌نمایش زنده به‌روز می‌شود. درصد تخفیف همکار فعلی: <?= e(format_price(partner_discount_percent())) ?>٪ (از تنظیمات).</p>
                </div>

                <h3>مواد مصرفی و بهای تمام‌شده (فرمول ساخت)</h3>
                <div class="card" style="background:#fffbeb;margin:10px 0">
                    <strong>هزینه تولید (دستمزد و سربار)</strong>
                    <p class="muted">علاوه بر مواد، هزینه ساخت هر متر و هر چراغ را وارد کنید تا در بهای تمام‌شده لحاظ شود.</p>
                    <div class="inline-fields">
                        <label>هزینه تولید هر متر (تومان)
                            <input type="number" name="labor_cost_per_meter" min="0" step="any" value="<?= (int) ($editProduct['labor_cost_per_meter'] ?? 0) ?>">
                        </label>
                        <label>هزینه تولید هر چراغ (تومان)
                            <input type="number" name="labor_cost_per_fixture" min="0" step="any" value="<?= (int) ($editProduct['labor_cost_per_fixture'] ?? 0) ?>">
                        </label>
                    </div>
                </div>
                <p class="muted">برای ساخت این محصول چه مواد خامی مصرف می‌شود؟ مصرف «به‌ازای هر متر» برای موادی است که با طول چراغ کم‌وزیاد می‌شوند (اعشاری هم می‌شود؛ مثلاً چسب حرارتی: یک بسته در ۵۰ متر = <span dir="ltr">0.02</span> بسته در متر) و مصرف «به‌ازای هر چراغ» برای موادی مثل درایور و درپوش که برای هر چراغ یک بار مصرف می‌شوند. بهای تمام‌شده فقط در پنل دیده می‌شود و هرگز در سایت عمومی نمایش داده نمی‌شود.</p>
                <div id="bom-rows">
                    <?php
                    $bomShowRows = $editProductBom;
                    $bomShowRows[] = ['material_id' => 0, 'qty' => '', 'basis' => 'per_meter', 'apply_condition' => 'always'];
                    foreach ($bomShowRows as $br):
                    ?>
                    <div class="bom-row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:8px">
                        <label style="flex:2;min-width:160px">ماده اولیه
                            <select name="bom_material_id[]">
                                <option value="0">— انتخاب ماده —</option>
                                <?php foreach ($allMaterials as $mm): ?>
                                    <option value="<?= (int) $mm['id'] ?>" <?= (int) ($br['material_id'] ?? 0) === (int) $mm['id'] ? 'selected' : '' ?>><?= e($mm['name']) ?> (<?= e($mm['unit']) ?>)<?= (int) $mm['is_active'] !== 1 ? ' — غیرفعال' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="flex:1;min-width:100px">مقدار مصرف
                            <input type="number" name="bom_qty[]" step="any" min="0" value="<?= ($br['qty'] ?? '') !== '' ? e(format_qty((float) $br['qty'])) : '' ?>" placeholder="مثلاً 1 یا 0.02">
                        </label>
                        <label style="flex:1;min-width:130px">مبنای مصرف
                            <select name="bom_basis[]">
                                <option value="per_meter" <?= ($br['basis'] ?? 'per_meter') !== 'per_fixture' ? 'selected' : '' ?>>به‌ازای هر متر</option>
                                <option value="per_fixture" <?= ($br['basis'] ?? '') === 'per_fixture' ? 'selected' : '' ?>>به‌ازای هر چراغ</option>
                            </select>
                        </label>
                        <label style="flex:1;min-width:130px">شرط مصرف
                            <select name="bom_cond[]">
                                <option value="always" <?= ($br['apply_condition'] ?? 'always') !== 'endcap' ? 'selected' : '' ?>>همیشه</option>
                                <option value="endcap" <?= ($br['apply_condition'] ?? '') === 'endcap' ? 'selected' : '' ?>>فقط وقتی درپوش دارد</option>
                            </select>
                        </label>
                        <button type="button" class="btn small bom-remove">حذف ردیف</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="btn small" id="bom-add">+ افزودن ماده دیگر</button></p>
                <?php if ($allMaterials === []): ?>
                <p class="muted">هنوز ماده اولیه‌ای تعریف نشده است؛ اول از منوی «انبار ← مواد اولیه» مواد را بسازید.</p>
                <?php endif; ?>
                <script>
                (function(){
                    var box = document.getElementById('bom-rows');
                    var addBtn = document.getElementById('bom-add');
                    if(!box || !addBtn) return;
                    function bindRemove(btn){
                        btn.addEventListener('click', function(){ btn.closest('.bom-row').remove(); });
                    }
                    box.querySelectorAll('.bom-remove').forEach(bindRemove);
                    addBtn.addEventListener('click', function(){
                        var first = box.querySelector('.bom-row');
                        if(!first) return;
                        var clone = first.cloneNode(true);
                        clone.querySelectorAll('select,input').forEach(function(el){
                            if(el.tagName === 'SELECT'){ el.selectedIndex = 0; } else { el.value = ''; }
                        });
                        box.appendChild(clone);
                        bindRemove(clone.querySelector('.bom-remove'));
                    });
                })();
                </script>

                <?php if ($editProduct !== null):
                    $pcost = product_material_cost((int) $editProduct['id']);
                    $pmargin = product_margin((int) $editProduct['id']);
                ?>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>بهای تمام‌شده (مواد با آخرین قیمت خرید + هزینه تولید):</strong>
                    <div>بهای تمام‌شده هر متر: <strong><?= e(format_price($pcost['per_meter'])) ?></strong> تومان</div>
                    <div>بهای تمام‌شده ثابت هر چراغ: <strong><?= e(format_price($pcost['per_fixture'])) ?></strong> تومان <span class="muted">(به‌ازای هر چراغ/سفارش جدا از متری)</span></div>
                    <div>حاشیه سود متری مشتری: <strong><?= e(format_price($pmargin['retail_margin'])) ?></strong> تومان (<?= e(format_price($pmargin['retail_margin_percent'])) ?>٪ از قیمت فروش)</div>
                    <div>حاشیه سود متری همکار: <strong><?= e(format_price($pmargin['partner_margin'])) ?></strong> تومان (<?= e(format_price($pmargin['partner_margin_percent'])) ?>٪ از قیمت همکار)</div>
                    <?php if ($pmargin['partner_margin'] < 0): ?>
                    <div class="alert error" style="margin-top:8px">هشدار: قیمت همکار این محصول از بهای مواد هر متر کمتر است؛ یعنی فروش به همکار با این قیمت، فقط از نظر مواد هم زیان‌ده است.</div>
                    <?php endif; ?>
                    <?php if ($pmargin['retail_margin'] < 0): ?>
                    <div class="alert error" style="margin-top:8px">هشدار: قیمت فروش این محصول از بهای مواد هر متر کمتر است.</div>
                    <?php endif; ?>
                </div>
                <p style="margin:10px 0"><button type="submit" class="btn <?= $editProduct !== null ? 'edit' : 'add' ?>" style="width:100%;padding:12px;font-size:16px"><?= $editProduct !== null ? '💾 ذخیره تغییرات' : 'ثبت محصول' ?></button></p>

                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>برآورد نیاز مواد برای یک سفارش</strong>
                    <p class="muted">طول و تعداد چراغ را بدهید تا ببینید برای ساخت، چقدر از هر ماده لازم است و کجا کمبود دارید.</p>
                    <div class="inline-fields">
                        <label>طول کل (متر)
                            <input type="number" id="est-len" step="any" min="0" value="<?= $estLength !== null ? e(format_qty($estLength)) : '10' ?>">
                        </label>
                        <label>تعداد چراغ
                            <input type="number" id="est-fix" step="1" min="0" value="<?= (int) $estFixtures ?>">
                        </label>
                        <button type="button" class="btn small" id="est-go">محاسبه نیاز مواد</button>
                    </div>
                    <script>
                    document.getElementById('est-go').addEventListener('click', function(){
                        var l = document.getElementById('est-len').value || '0';
                        var f = document.getElementById('est-fix').value || '1';
                        window.location.href = 'admin.php?page=products&edit_id=<?= (int) $editProduct['id'] ?>&est_length=' + encodeURIComponent(l) + '&est_fixtures=' + encodeURIComponent(f);
                    });
                    </script>
                    <?php if ($estLength !== null): ?>
                    <?php if ($estRows === []): ?>
                    <p class="muted">برای این محصول هنوز ماده‌ای در فرمول ساخت تعریف نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>ماده</th><th>نیاز</th><th>موجودی انبار</th><th>وضعیت</th></tr></thead>
                        <tbody>
                        <?php foreach ($estRows as $er): ?>
                            <tr>
                                <td><?= e($er['name']) ?></td>
                                <td><?= e(format_qty((float) $er['needed'])) ?> <?= e($er['unit']) ?></td>
                                <td><?= e(format_qty((float) $er['stock'])) ?> <?= e($er['unit']) ?></td>
                                <td><?= $er['shortage'] > 0 ? '<span class="badge" style="background:#fee2e2;color:#b91c1c">کمبود: ' . e(format_qty((float) $er['shortage'])) . ' ' . e($er['unit']) . '</span>' : '<span class="badge ok">کافی</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <h3>تنظیمات فرم سفارش</h3>
                <p class="muted">مشخص کنید در فرم ثبت سفارش این محصول کدام فیلدها نمایش داده شوند. مثلاً برای چراغ رشد گیاه، طول و سیم لازم نیست.</p>
                <?php $ofc = $editProduct !== null ? product_order_form_config($editProduct) : ['show_length' => true, 'show_qty' => true, 'show_wire' => true, 'show_endcap' => true, 'show_options' => true]; ?>
                <div class="inline-fields">
                    <label class="check"><input type="checkbox" name="ofc_show_length" value="1"<?= $ofc['show_length'] ? ' checked' : '' ?>> طول چراغ</label>
                    <label class="check"><input type="checkbox" name="ofc_show_qty" value="1"<?= $ofc['show_qty'] ? ' checked' : '' ?>> تعداد</label>
                    <label class="check"><input type="checkbox" name="ofc_show_wire" value="1"<?= $ofc['show_wire'] ? ' checked' : '' ?>> طول سیم</label>
                    <label class="check"><input type="checkbox" name="ofc_show_endcap" value="1"<?= $ofc['show_endcap'] ? ' checked' : '' ?>> درپوش</label>
                    <label class="check"><input type="checkbox" name="ofc_show_options" value="1"<?= $ofc['show_options'] ? ' checked' : '' ?>> آپشن‌ها (ویژگی‌ها)</label>
                </div>
                <?php if ($editProduct !== null):
                    $formFields = $pdo->query("SELECT * FROM order_form_fields WHERE owner_type = 'product' AND owner_id = " . (int) $editProduct['id'] . " ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>فیلدهای سفارشی فرم سفارش</strong>
                    <p class="muted">فیلدهای اضافه‌ای که فقط برای این محصول در فرم سفارش نمایش داده می‌شوند (مثلاً مساحت، نوع نصب).</p>
                    <table>
                        <thead><tr><th>برچسب</th><th>نوع</th><th>گزینه‌ها</th><th>الزامی</th><th>ترتیب</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($formFields as $ff): ?>
                            <tr>
                                <td><?= e($ff['label']) ?></td>
                                <td><?= e(['text' => 'متن', 'number' => 'عدد', 'select' => 'انتخابی', 'textarea' => 'متن بلند', 'checkbox' => 'تیک'][($ff['field_type'] ?? 'text')] ?? $ff['field_type']) ?></td>
                                <td><?= e($ff['options_json'] ?? '') ?></td>
                                <td><?= (int) $ff['is_required'] === 1 ? 'بله' : 'خیر' ?></td>
                                <td><?= (int) $ff['sort_order'] ?></td>
                                <td>
                                    <form method="post" class="inline" onsubmit="return confirm('این فیلد حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_order_field"><input type="hidden" name="id" value="<?= (int) $ff['id'] ?>"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($formFields === []): ?><tr><td colspan="6" class="muted">فیلد سفارشی تعریف نشده است.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <div class="inline-fields" style="margin-top:10px">
                        <label>برچسب فیلد <input type="text" name="new_field_label" maxlength="100"></label>
                        <label>نوع
                            <select name="new_field_type">
                                <option value="text">متن</option>
                                <option value="number">عدد</option>
                                <option value="select">انتخابی</option>
                                <option value="textarea">متن بلند</option>
                                <option value="checkbox">تیک</option>
                            </select>
                        </label>
                        <label>گزینه‌ها (با | جدا کنید، برای نوع انتخابی)
                            <input type="text" name="new_field_options" dir="rtl" placeholder="مثلاً: آویز | سقفی | دیواری">
                        </label>
                        <label class="check"><input type="checkbox" name="new_field_required" value="1"> الزامی</label>
                        <button type="submit" name="add_order_field" value="1" class="btn small add">+ افزودن فیلد</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($editProduct !== null):
                    $galleryImages = get_product_images((int) $editProduct['id']);
                ?>
                <h3>گالری تصاویر محصول</h3>
                <p class="muted">علاوه بر عکس اصلی، می‌توانید چند عکس دیگر برای گالری این محصول اضافه کنید.</p>
                <div class="pg-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin:10px 0">
                    <?php foreach ($galleryImages as $gi): ?>
                    <div class="pg-item" style="position:relative;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <img src="<?= e(UPLOADS_URL . '/' . basename((string) $gi['image'])) ?>" alt="" style="width:100%;aspect-ratio:1/1;object-fit:cover;display:block">
                        <?php if (!empty($gi['caption'])): ?><div style="padding:4px 8px;font-size:11px"><?= e($gi['caption']) ?></div><?php endif; ?>
                        <div style="display:flex;gap:4px;padding:4px">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="dir" value="-1"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small" title="قبلی">→</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="dir" value="1"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small" title="بعدی">←</button></form>
                            <form method="post" class="inline" onsubmit="return confirm('این عکس حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($galleryImages === []): ?><p class="muted">هنوز عکسی در گالری نیست.</p><?php endif; ?>
                </div>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>افزودن عکس به گالری</strong>
                    <div class="inline-fields" style="margin-top:8px">
                        <label>فایل عکس <input type="file" name="gallery_image" accept="image/*"></label>
                        <label>توضیح (اختیاری) <input type="text" name="gallery_caption" maxlength="150"></label>
                        <button type="submit" name="add_gallery_image" value="1" class="btn small add">+ افزودن به گالری</button>
                    </div>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn <?= $editProduct !== null ? 'edit' : 'add' ?>"><?= $editProduct !== null ? 'ذخیره تغییرات' : 'ثبت محصول' ?></button>
                <?php if ($editProduct !== null): ?><a class="btn" href="admin.php?page=products">انصراف</a><?php endif; ?>
            </form>
            </div>
            <script>
            (function(){
                var discount = <?= json_encode(partner_discount_percent()) ?>;
                var retail = document.getElementById('pf-retail');
                var partner = document.getElementById('pf-partner');
                var rp = document.getElementById('pf-retail-preview');
                var pp = document.getElementById('pf-partner-preview');
                if(!retail || !rp) return;
                function fmt(n){ return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
                function currentDelta(){
                    var d = 0;
                    document.querySelectorAll('#product-form .pf-attr-select').forEach(function(s){
                        var o = s.options[s.selectedIndex];
                        if(o){ d += parseFloat(o.getAttribute('data-delta') || '0'); }
                    });
                    return d;
                }
                function calc(){
                    var base = parseFloat(retail.value) || 0;
                    var d = currentDelta();
                    var pbase = base;
                    if(partner && partner.value.trim() !== '' && parseFloat(partner.value) > 0){
                        pbase = parseFloat(partner.value);
                    } else {
                        pbase = base * (1 - discount / 100);
                    }
                    rp.textContent = fmt(base + d);
                    pp.textContent = fmt(pbase + d);
                }
                ['input','change'].forEach(function(ev){
                    document.getElementById('product-form').addEventListener(ev, calc);
                });
                calc();
            })();
            </script>

            <h2>فهرست محصولات (<?= count($productsList) ?>)</h2>
            <?php if ($productsList === []): ?>
                <div class="card wide"><p class="muted">هنوز محصولی ثبت نشده است. اولین محصول را با دکمه «+ افزودن محصول» بسازید تا در کاتالوگ عمومی سایت نمایش داده شود.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>نام محصول</th><th>دسته</th><th>قیمت متری (مشتری / همکار)</th><th>بهای مواد (هر متر)</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($productsList as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?><?php if (!empty($p['sku'])): ?> <span class="muted" dir="ltr">(<?= e($p['sku']) ?>)</span><?php endif; ?></td>
                        <td><?= e($p['category_title'] ?? '—') ?></td>
                        <td>
                            <form method="post" class="inline quick-price">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="quick_price_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="number" name="price_per_meter" min="0" step="any" value="<?= (int) $p['price_per_meter'] ?>" style="width:110px" title="قیمت متری مشتری (تومان)">
                                <input type="number" name="partner_price_per_meter" min="0" step="any" value="<?= ($p['partner_price_per_meter'] ?? null) !== null && ($p['partner_price_per_meter'] ?? '') !== '' ? (int) $p['partner_price_per_meter'] : '' ?>" placeholder="خودکار" style="width:90px" title="تخفیف همکار — خالی = خودکار">
                                <button type="submit" class="btn small edit" title="ذخیره قیمت">💾</button>
                            </form>
                            <?php if (($p['partner_price_per_meter'] ?? null) === null || ($p['partner_price_per_meter'] ?? '') === '' || (float) ($p['partner_price_per_meter'] ?? 0) <= 0): ?><div class="muted" style="font-size:11px">خودکار: <?= e(format_price(product_base_price_per_meter($p, true))) ?></div><?php endif; ?>
                        </td>
                        <td><?php $pmc = product_material_cost((int) $p['id']); ?><?= $pmc['lines'] !== [] ? e(format_price($pmc['per_meter'])) . ' تومان' : '<span class="muted">فرمول ندارد</span>' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑ بالا</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓ پایین</button>
                            </form>
                            <?= (int) $p['sort_order'] ?>
                        </td>
                        <td><?= $p['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="/products?id=<?= (int) $p['id'] ?>" target="_blank">مشاهده</a>
                            <a class="btn small edit" href="admin.php?page=products&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این محصول حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «ویژگی‌های محصول» */
function catalog_render_attributes(array $d): void
{
    extract($d);
    ?>            <h1>ویژگی‌های محصول</h1>
            <p class="muted">ویژگی‌ها روی همه محصولات قابل استفاده‌اند؛ مثلاً «رنگ نور» یا «سنسور». برای ویژگی‌های انتخابی، هر گزینه می‌تواند مبلغی به قیمت متری اضافه یا از آن کم کند (آپشن پولی/رایگان).</p>

            <?php if ($editAttribute === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="attribute-form-panel" aria-expanded="false">+ افزودن ویژگی</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="attribute-form-panel" <?= $editAttribute !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editAttribute !== null ? 'ویرایش ویژگی: ' . e($editAttribute['title'] ?? '') : 'ویژگی تازه' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editAttribute !== null ? 'update_attribute' : 'add_attribute' ?>">
                <?php if ($editAttribute !== null): ?><input type="hidden" name="id" value="<?= (int) $editAttribute['id'] ?>"><?php endif; ?>
                <label>عنوان ویژگی *
                    <input type="text" name="title" required value="<?= e($editAttribute['title'] ?? '') ?>" placeholder="مثلاً: سنسور">
                </label>
                <label>کلید ویژگی (شناسه داخلی؛ خالی بماند خودکار ساخته می‌شود)
                    <input type="text" name="attr_key" dir="ltr" value="<?= e($editAttribute['attr_key'] ?? '') ?>" placeholder="sensor">
                </label>
                <label>نوع ورودی
                    <select name="input_type">
                        <option value="select" <?= ($editAttribute['input_type'] ?? 'select') === 'select' ? 'selected' : '' ?>>انتخابی (با گزینه و اختلاف قیمت)</option>
                        <option value="number" <?= ($editAttribute['input_type'] ?? '') === 'number' ? 'selected' : '' ?>>عددی</option>
                        <option value="text" <?= ($editAttribute['input_type'] ?? '') === 'text' ? 'selected' : '' ?>>متنی (فقط مشخصات)</option>
                    </select>
                </label>
                <label>واحد (برای نوع عددی؛ مثل وات)
                    <input type="text" name="unit" value="<?= e($editAttribute['unit'] ?? '') ?>">
                </label>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editAttribute['sort_order'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editAttribute['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال
                </label>
                <button type="submit" class="btn <?= $editAttribute !== null ? 'edit' : 'add' ?>"><?= $editAttribute !== null ? 'ذخیره تغییرات' : 'ساخت ویژگی' ?></button>
                <?php if ($editAttribute !== null): ?><a class="btn" href="admin.php?page=attributes">ویژگی تازه</a><?php endif; ?>
            </form>
            </div>

            <?php if ($editAttribute !== null && $editAttribute['input_type'] === 'select'): ?>
                <h2>گزینه‌های «<?= e($editAttribute['title']) ?>»</h2>
                <p class="muted">اختلاف قیمت متری هر گزینه (تومان به‌ازای هر متر) به قیمت پایه اضافه می‌شود؛ می‌تواند صفر (رایگان)، مثبت یا منفی باشد.</p>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editOption !== null ? 'update_option' : 'add_option' ?>">
                    <input type="hidden" name="attribute_id" value="<?= (int) $editAttribute['id'] ?>">
                    <?php if ($editOption !== null): ?><input type="hidden" name="id" value="<?= (int) $editOption['id'] ?>"><?php endif; ?>
                    <label>عنوان گزینه *
                        <input type="text" name="title" required value="<?= e($editOption['title'] ?? '') ?>" placeholder="مثلاً: سنسور حرکتی">
                    </label>
                    <label>اختلاف قیمت متری (تومان)
                        <input type="number" name="price_delta_per_meter" step="any" value="<?= (int) ($editOption['price_delta_per_meter'] ?? 0) ?>">
                    </label>
                    <label>ترتیب
                        <input type="number" name="sort_order" value="<?= (int) ($editOption['sort_order'] ?? 0) ?>">
                    </label>
                    <button type="submit" class="btn <?= $editOption !== null ? 'edit' : 'add' ?>"><?= $editOption !== null ? 'ذخیره گزینه' : 'افزودن گزینه' ?></button>
                    <?php if ($editOption !== null): ?><a class="btn" href="admin.php?page=attributes&edit_id=<?= (int) $editAttribute['id'] ?>">انصراف</a><?php endif; ?>
                </form>
                <?php if ($editAttributeOptions === []): ?>
                    <div class="card wide"><p class="muted">هنوز گزینه‌ای برای این ویژگی ثبت نشده است.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>گزینه</th><th>اختلاف قیمت متری</th><th>ترتیب</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($editAttributeOptions as $opt): ?>
                        <tr>
                            <td><?= e($opt['title']) ?></td>
                            <td><?= (float) $opt['price_delta_per_meter'] > 0 ? '+' : '' ?><?= e(format_price($opt['price_delta_per_meter'])) ?> تومان</td>
                            <td>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_option">
                                    <input type="hidden" name="id" value="<?= (int) $opt['id'] ?>">
                                    <input type="hidden" name="attribute_id" value="<?= (int) $editAttribute['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button type="submit" class="btn small">↑ بالا</button>
                                </form>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_option">
                                    <input type="hidden" name="id" value="<?= (int) $opt['id'] ?>">
                                    <input type="hidden" name="attribute_id" value="<?= (int) $editAttribute['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn small">↓ پایین</button>
                                </form>
                                <?= (int) $opt['sort_order'] ?>
                            </td>
                            <td class="actions">
                                <a class="btn small edit" href="admin.php?page=attributes&edit_id=<?= (int) $editAttribute['id'] ?>&edit_option=<?= (int) $opt['id'] ?>">ویرایش</a>
                                <form method="post" class="inline" onsubmit="return confirm('این گزینه حذف شود؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_option">
                                    <input type="hidden" name="id" value="<?= (int) $opt['id'] ?>">
                                    <input type="hidden" name="attribute_id" value="<?= (int) $editAttribute['id'] ?>">
                                    <button type="submit" class="btn small danger-btn">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php elseif ($editAttribute !== null): ?>
                <div class="card wide"><p class="muted">این ویژگی از نوع «<?= $editAttribute['input_type'] === 'number' ? 'عددی' : 'متنی' ?>» است و گزینه ندارد؛ مقدارش برای هر محصول در فرم محصول وارد می‌شود.</p></div>
            <?php endif; ?>

            <h2>فهرست ویژگی‌ها (<?= count($attributesList) ?>)</h2>
            <?php if ($attributesList === []): ?>
                <div class="card wide"><p class="muted">هنوز ویژگی‌ای تعریف نشده است.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>عنوان</th><th>کلید</th><th>نوع</th><th>تعداد گزینه</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($attributesList as $attr): ?>
                    <tr>
                        <td><?= e($attr['title']) ?></td>
                        <td><span dir="ltr"><?= e($attr['attr_key']) ?></span></td>
                        <td><?= $attr['input_type'] === 'select' ? 'انتخابی' : ($attr['input_type'] === 'number' ? 'عددی' : 'متنی') ?></td>
                        <td><?= $attr['input_type'] === 'select' ? count(get_attribute_options((int) $attr['id'])) : '—' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_attribute">
                                <input type="hidden" name="id" value="<?= (int) $attr['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑ بالا</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_attribute">
                                <input type="hidden" name="id" value="<?= (int) $attr['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓ پایین</button>
                            </form>
                            <?= (int) $attr['sort_order'] ?>
                        </td>
                        <td><?= $attr['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small edit" href="admin.php?page=attributes&edit_id=<?= (int) $attr['id'] ?>">ویرایش<?= $attr['input_type'] === 'select' ? ' و گزینه‌ها' : '' ?></a>
                            <form method="post" class="inline" onsubmit="return confirm('این ویژگی با همه گزینه‌ها و مقادیرش از محصولات حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_attribute">
                                <input type="hidden" name="id" value="<?= (int) $attr['id'] ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    <?php
}

// ---------- توابع کمکی مشترک پنل ----------
// (از admin.php منتقل شدند تا حجم آن فایل زیر سقف آپدیت گیت‌هاب بماند؛ رفتار هیچ تفاوتی نکرده است)

/**
 * پیش‌نمایش یک قالب دیتابیسی با داده نمونه، داخل سند کامل سایت (برای iframe سندباکس).
 * قالب فقط با پلیس‌هولدرهای امن رندر می‌شود؛ هیچ کدی از متن قالب اجرا نمی‌شود.
 */
function design_preview_html(array $settings, string $key, string $content): string
{
    $sampleSection = [
        'title'     => 'بخش نمونه',
        'heading'   => 'تیتر نمایشی بخش',
        'body'      => '<p>این یک متن نمونه برای پیش‌نمایش قالب است. متن واقعی هر بخش از پنل مدیریت می‌آید.</p><ul><li>نکته اول</li><li>نکته دوم</li></ul>',
        'image'     => '',
        'link_url'  => '#',
        'link_text' => 'متن دکمه نمونه',
    ];
    $samplePage = [
        'title'   => 'عنوان صفحه نمونه',
        'content' => '<p>این متن نمونه‌ی محتوای یک صفحه است تا چیدمان قالب «صفحه تکی» را ببینید.</p>',
    ];
    $ctx = template_context($key, $settings, $sampleSection, $samplePage);
    if (strpos($content, '{{slider_slides}}') !== false) {
        $ctx['slider_slides'] = '<div class="slides" data-slider><figure class="slide is-active"><figcaption class="slide-caption"><h3>اسلاید نمونه</h3><div class="slide-text"><p>متن نمونه اسلاید</p></div></figcaption></figure></div>';
    }
    $rendered = tpl_render($content, $ctx);
    $doc = skeleton_head($settings, 'پیش‌نمایش قالب: ' . $key, '');
    if (in_array($key, ['header', 'footer'], true)) {
        $doc .= $rendered . "\n";
    } else {
        $doc .= '<main id="main">' . "\n" . $rendered . "\n" . '</main>' . "\n";
    }
    $doc .= skeleton_foot();
    return $doc;
}

function move_row(PDO $pdo, string $table, int $id, string $direction): void
{
    $rows = $pdo->query('SELECT id, sort_order FROM ' . $table . ' ORDER BY sort_order ASC, id ASC')->fetchAll();
    $idx = null;
    foreach ($rows as $i => $r) {
        if ((int) $r['id'] === $id) { $idx = $i; break; }
    }
    if ($idx === null) return;
    $swap = $direction === 'up' ? $idx - 1 : $idx + 1;
    if ($swap < 0 || $swap >= count($rows)) return;
    $a = $rows[$idx]; $b = $rows[$swap];
    $upd = $pdo->prepare('UPDATE ' . $table . ' SET sort_order = :o WHERE id = :id');
    $upd->execute([':o' => $b['sort_order'], ':id' => $a['id']]);
    $upd->execute([':o' => $a['sort_order'], ':id' => $b['id']]);
}

// ---------- آپدیت یک‌کلیکی از گیت‌هاب (از config.php منتقل شد تا آن فایل کوچک بماند؛ فقط پنل مدیریت استفاده می‌کند) ----------

// =============================================================
// آپدیت یک‌کلیکی از گیت‌هاب (نسخه ۴)
// =============================================================

/** فولدر بکاپ‌های قبل از آپدیت (محافظت‌شده از وب)؛ در صورت نیاز ساخته می‌شود */
function backups_dir(): string
{
    return (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/backups';
}

/** ساخت فولدر backups با .htaccess محافظ؛ خروجی: مسیر یا null در صورت خطا */
function ensure_backups_dir(): ?string
{
    $dir = backups_dir();
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true)) {
            return null;
        }
    }
    $ht = $dir . '/.htaccess';
    if (!is_file($ht)) {
        @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
    $idx = $dir . '/index.html';
    if (!is_file($idx)) {
        @file_put_contents($idx, '');
    }
    return $dir;
}

/** تنظیمات مخزن آپدیت (با اعتبارسنجی سبک و مقدار پیش‌فرض امن) */
function update_repo_config(): array
{
    $repo   = trim(get_setting('update_repo', 'farsmd/soon'));
    $branch = trim(get_setting('update_branch', 'main'));
    $zipUrl = trim(get_setting('update_zip_url', ''));
    if (!preg_match('#^[A-Za-z0-9._-]+/[A-Za-z0-9._-]+$#', $repo)) {
        $repo = 'farsmd/soon';
    }
    if (!preg_match('#^[A-Za-z0-9._/-]+$#', $branch) || strpos($branch, '..') !== false) {
        $branch = 'main';
    }
    return ['repo' => $repo, 'branch' => $branch, 'zip_url' => $zipUrl];
}

/** آدرس دانلود ZIP نسخه تازه (از تنظیم مستقیم یا ساخته‌شده از مخزن و شاخه) */
function update_zip_download_url(array $cfg): string
{
    if (($cfg['zip_url'] ?? '') !== '') {
        return (string) $cfg['zip_url'];
    }
    // کش‌بان: codeload گیت‌هاب فایل ZIP را چند دقیقه کش می‌کند؛ پارامتر زمانی آن را دور می‌زند
    // (بدون این، ممکن است ZIP نسخه قبلی دانلود شود و آپدیتر بگوید «تازه‌تر نیست»)
    return 'https://codeload.github.com/' . $cfg['repo'] . '/zip/refs/heads/' . $cfg['branch'] . '?t=' . time();
}

/**
 * دریافت محتوای یک آدرس اینترنتی با cURL (اگر بود) یا file_get_contents.
 * خروجی: ['ok' => bool, 'body' => ?string, 'error' => ?string]
 */
function http_fetch(string $url, int $timeout = 15): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'LinerLight-CMS-Updater/' . APP_VERSION,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = (string) curl_error($ch);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return ['ok' => true, 'body' => (string) $body, 'error' => null];
            }
            return ['ok' => false, 'body' => null, 'error' => $err !== '' ? $err : ('HTTP ' . $code)];
        }
    }
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header'        => "User-Agent: LinerLight-CMS-Updater/" . APP_VERSION . "\r\nAccept: */*\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return ['ok' => false, 'body' => null, 'error' => 'دریافت پاسخ از سرور انجام نشد.'];
    }
    return ['ok' => true, 'body' => (string) $body, 'error' => null];
}

/**
 * دانلود یک فایل (مثل ZIP آپدیت) روی دیسک با cURL یا stream.
 * خروجی: ['ok' => bool, 'error' => ?string]
 */
function http_download(string $url, string $dest, int $timeout = 120): array
{
    if (function_exists('curl_init')) {
        $fp = @fopen($dest, 'wb');
        if ($fp === false) {
            return ['ok' => false, 'error' => 'ساخت فایل موقت برای دانلود انجام نشد.'];
        }
        $ch = curl_init($url);
        if ($ch !== false) {
            curl_setopt_array($ch, [
                CURLOPT_FILE           => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_USERAGENT      => 'LinerLight-CMS-Updater/' . APP_VERSION,
            ]);
            $ok   = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err  = (string) curl_error($ch);
            curl_close($ch);
            fclose($fp);
            if ($ok && $code >= 200 && $code < 300 && is_file($dest) && (int) filesize($dest) > 0) {
                return ['ok' => true, 'error' => null];
            }
            @unlink($dest);
            return ['ok' => false, 'error' => $err !== '' ? $err : ('HTTP ' . $code)];
        }
        fclose($fp);
    }
    $ctx = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'timeout'       => $timeout,
            'follow_location' => 1,
            'max_redirects' => 5,
            'header'        => "User-Agent: LinerLight-CMS-Updater/" . APP_VERSION . "\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false || $body === '') {
        return ['ok' => false, 'error' => 'دانلود فایل آپدیت انجام نشد.'];
    }
    if (@file_put_contents($dest, $body) === false) {
        return ['ok' => false, 'error' => 'ذخیره فایل آپدیت روی سرور انجام نشد.'];
    }
    return ['ok' => true, 'error' => null];
}

/** استخراج مقدار APP_VERSION از متن config.php */
function parse_app_version(string $configCode): ?string
{
    if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $configCode, $m)) {
        return $m[1];
    }
    if (preg_match('/define\(\s*"APP_VERSION"\s*,\s*"([^"]+)"\s*\)/', $configCode, $m)) {
        return $m[1];
    }
    return null;
}

/** خواندن متن یک فایل از داخل ZIP (با نام نسبی مثل config.php) */
function zip_read_entry(ZipArchive $zip, string $relative): ?string
{
    // ابتدا خود نام و سپس جستجو در زیرفولدر ریشه (مثل soon-main/config.php)
    $candidates = [$relative];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        $suffix = '/' . $relative;
        if (substr($name, -strlen($suffix)) === $suffix) {
            $candidates[] = $name;
        }
    }
    foreach (array_unique($candidates) as $name) {
        $data = $zip->getFromName($name);
        if ($data !== false) {
            return (string) $data;
        }
    }
    return null;
}

/** تشخیص پیشوند فولدر ریشه داخل ZIP (مثل soon-main/) یا رشته خالی */
function zip_root_prefix(ZipArchive $zip): string
{
    $roots = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = (string) $zip->getNameIndex($i);
        if ($name === '' ) {
            continue;
        }
        $parts = explode('/', $name);
        if (count($parts) > 1 && $parts[0] !== '') {
            $roots[$parts[0]] = true;
        } else {
            // فایل در ریشه ZIP است؛ پیشوندی در کار نیست
            return '';
        }
    }
    if (count($roots) === 1) {
        return array_key_first($roots) . '/';
    }
    return '';
}

/**
 * بررسی وجود نسخه تازه‌تر.
 * خروجی: current, latest (?string), update_available (bool), commits (array), error (?string), checked (bool)
 */
function update_check(array $cfg): array
{
    $result = [
        'current'          => APP_VERSION,
        'latest'           => null,
        'update_available' => false,
        'commits'          => [],
        'error'            => null,
        'checked'          => false,
    ];

    if (($cfg['zip_url'] ?? '') !== '') {
        // حالت آدرس مستقیم ZIP: خود فایل دانلود و نسخه داخلش خوانده می‌شود.
        $dir = update_temp_dir();
        if ($dir === null) {
            $result['error'] = 'ساخت فولدر موقت برای بررسی آپدیت انجام نشد.';
            return $result;
        }
        $zipPath = $dir . '/check.zip';
        try {
            $dl = http_download((string) $cfg['zip_url'], $zipPath, 60);
            if (!$dl['ok']) {
                $result['error'] = 'دانلود فایل آپدیت برای بررسی انجام نشد: ' . (string) $dl['error'];
                return $result;
            }
            if (!class_exists('ZipArchive')) {
                $result['error'] = 'افزونه ZipArchive روی این سرور فعال نیست.';
                return $result;
            }
            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                $result['error'] = 'فایل دانلودشده ZIP معتبر نیست.';
                return $result;
            }
            $code = zip_read_entry($zip, 'core/config.php');
            if ($code === null) { $code = zip_read_entry($zip, 'config.php'); }
            $zip->close();
            if ($code === null) {
                $result['error'] = 'فایل config.php داخل فایل آپدیت پیدا نشد.';
                return $result;
            }
            $latest = parse_app_version($code);
            if ($latest === null) {
                $result['error'] = 'نسخه برنامه داخل فایل آپدیت پیدا نشد.';
                return $result;
            }
            $result['latest'] = $latest;
            $result['checked'] = true;
            $result['update_available'] = version_compare($latest, APP_VERSION, '>');
            return $result;
        } finally {
            update_remove_dir($dir);
        }
    }

    // حالت مخزن گیت‌هاب: خواندن config.php خام از شاخه
    // کش‌بان: raw.githubusercontent چند دقیقه کش می‌کند؛ پارامتر زمانی آن را دور می‌زند
    // ۹٫۹۹٫۰: اول core/config.php بعد config.php (سازگاری با ساختار قدیم)
    $latest = null;
    foreach (['core/config.php', 'config.php'] as $cfgPath) {
        $rawUrl = 'https://raw.githubusercontent.com/' . $cfg['repo'] . '/' . $cfg['branch'] . '/' . $cfgPath . '?t=' . time();
        $res = http_fetch($rawUrl, 15);
        if ($res['ok']) {
            $latest = parse_app_version((string) $res['body']);
            if ($latest !== null) { break; }
        }
    }
    if ($latest === null) {
        $result['error'] = 'نسخه برنامه در فایل config.php مخزن پیدا نشد.';
        return $result;
    }
    $result['latest'] = $latest;
    $result['checked'] = true;
    $result['update_available'] = version_compare($latest, APP_VERSION, '>');

    // فهرست آخرین کامیت‌ها برای نمایش تغییرات (اختیاری؛ خطایش بی‌صدا نادیده گرفته می‌شود)
    $apiUrl = 'https://api.github.com/repos/' . $cfg['repo'] . '/commits?per_page=5&sha=' . rawurlencode($cfg['branch']);
    $commitsRes = http_fetch($apiUrl, 15);
    if ($commitsRes['ok']) {
        $decoded = json_decode((string) $commitsRes['body'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $msg  = trim((string) ($c['commit']['message'] ?? ''));
                $date = (string) ($c['commit']['author']['date'] ?? ($c['commit']['committer']['date'] ?? ''));
                $firstLine = $msg === '' ? '' : (string) strtok($msg, "\n");
                $result['commits'][] = [
                    'message' => $firstLine,
                    'date'    => $date !== '' ? date('Y/m/d H:i', (int) strtotime($date)) : '',
                ];
            }
        }
    }
    return $result;
}

/** ساخت یک فولدر موقت محافظت‌شده برای کارهای آپدیت */
function update_temp_dir(): ?string
{
    $base = ensure_backups_dir();
    if ($base === null) {
        $base = sys_get_temp_dir();
    }
    $dir = $base . '/.tmp-update-' . bin2hex(random_bytes(6));
    if (!@mkdir($dir, 0775, true)) {
        return null;
    }
    return $dir;
}

/** حذف بازگشتی یک فولدر (برای پاک‌سازی موقت‌ها) */
function update_remove_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $item) {
        if ($item->isDir()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }
    @rmdir($dir);
}

/** آیا این مسیر نسبی در آپدیت محافظت می‌شود و نباید دست بخورد؟ */
function update_is_protected(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '') {
        return true;
    }
    // فایل دیتابیس و هر فایل دیتابیس دیگر (.sqlite/.db)
    if ($rel === 'database.sqlite') {
        return true;
    }
    if (preg_match('/\.(sqlite|sqlite3|db)$/i', $rel)) {
        return true;
    }
    // فولدر بکاپ‌ها و متعلقات گیت
    if (strpos($rel, 'backups/') === 0 || $rel === 'backups') {
        return true;
    }
    if (strpos($rel, '.git') === 0 || $rel === '.git') {
        return true;
    }
    // فایل‌های کاربر در uploads (فقط فایل‌های سیستمی آن فولدر آپدیت می‌شوند)
    if (strpos($rel, 'uploads/') === 0) {
        return !in_array($rel, ['uploads/.htaccess', 'uploads/index.html'], true);
    }
    return false;
}

/**
 * اجرای آپدیت یک‌کلیکی.
 * خروجی: ['ok' => bool, 'error' => ?string, 'new_version' => ?string, 'backup_file' => ?string]
 */
function perform_update(array $cfg, bool $backupDb): array
{
    if (!class_exists('ZipArchive')) {
        return ['ok' => false, 'error' => 'افزونه ZipArchive روی این سرور فعال نیست؛ آپدیت خودکار ممکن نیست. PHP را با افزونه zip فعال کنید یا آپدیت را دستی انجام دهید.', 'new_version' => null, 'backup_file' => null];
    }

    $work = update_temp_dir();
    if ($work === null) {
        return ['ok' => false, 'error' => 'ساخت فولدر موقت برای آپدیت انجام نشد؛ مجوز نوشتن فولدر برنامه را بررسی کنید.', 'new_version' => null, 'backup_file' => null];
    }

    $backupFile = null;
    try {
        // الف) بکاپ دیتابیس قبل از آپدیت (در صورت تیک خوردن)
        if ($backupDb) {
            $bdir = ensure_backups_dir();
            if ($bdir === null) {
                return ['ok' => false, 'error' => 'ساخت فولدر بکاپ انجام نشد؛ آپدیت متوقف شد تا دیتابیس بدون بکاپ دست نخورد.', 'new_version' => null, 'backup_file' => null];
            }
            if (!is_file(DB_FILE)) {
                return ['ok' => false, 'error' => 'فایل دیتابیس پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => null];
            }
            $backupFile = 'database-backup-before-update-' . date('Ymd-His') . '.sqlite';
            if (!@copy(DB_FILE, $bdir . '/' . $backupFile)) {
                return ['ok' => false, 'error' => 'گرفتن بکاپ از دیتابیس انجام نشد؛ آپدیت متوقف شد. مجوز نوشتن فولدر backups را بررسی کنید.', 'new_version' => null, 'backup_file' => null];
            }
        }

        // ب) دانلود ZIP نسخه تازه
        $zipUrl  = update_zip_download_url($cfg);
        $zipPath = $work . '/update.zip';
        $dl = http_download($zipUrl, $zipPath, 120);
        if (!$dl['ok']) {
            return ['ok' => false, 'error' => 'دانلود فایل آپدیت از گیت‌هاب انجام نشد: ' . (string) $dl['error'], 'new_version' => null, 'backup_file' => $backupFile];
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'فایل دانلودشده ZIP معتبر نیست.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $configCode = zip_read_entry($zip, 'core/config.php');
        if ($configCode === null) { $configCode = zip_read_entry($zip, 'config.php'); }
        if ($configCode === null) {
            $zip->close();
            return ['ok' => false, 'error' => 'فایل config.php داخل فایل آپدیت پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $newVersion = parse_app_version($configCode);
        if ($newVersion === null) {
            $zip->close();
            return ['ok' => false, 'error' => 'نسخه برنامه داخل فایل آپدیت پیدا نشد؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        if (!version_compare($newVersion, APP_VERSION, '>')) {
            $zip->close();
            return ['ok' => false, 'error' => 'فایل آپدیت نسخه ' . $newVersion . ' را دارد که از نسخه فعلی (' . APP_VERSION . ') تازه‌تر نیست؛ آپدیت انجام نشد.', 'new_version' => null, 'backup_file' => $backupFile];
        }

        $extractDir = $work . '/extract';
        if (!@mkdir($extractDir, 0775, true) || !$zip->extractTo($extractDir)) {
            $zip->close();
            return ['ok' => false, 'error' => 'باز کردن فایل آپدیت روی سرور انجام نشد.', 'new_version' => null, 'backup_file' => $backupFile];
        }
        $zip->close();

        // تشخیص فولدر ریشه داخل ZIP (مثل soon-main/)
        $srcRoot = $extractDir;
        $entries = array_values(array_filter(scandir($extractDir) ?: [], static fn($x) => $x !== '.' && $x !== '..'));
        if (count($entries) === 1 && is_dir($extractDir . '/' . $entries[0])) {
            $srcRoot = $extractDir . '/' . $entries[0];
        }
        if (!is_file($srcRoot . '/core/config.php') && !is_file($srcRoot . '/config.php')) {
            return ['ok' => false, 'error' => 'ساختار فایل آپدیت درست نیست (config.php پیدا نشد)؛ آپدیت متوقف شد.', 'new_version' => null, 'backup_file' => $backupFile];
        }

        // ج) کپی فایل‌های تازه روی برنامه — بدون حذف هیچ فایل محلی و بدون دست‌زدن به فایل‌های محافظت‌شده
        $base = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            $rel  = ltrim(str_replace('\\', '/', substr($path, strlen($srcRoot))), '/');
            if ($rel === '' || update_is_protected($rel)) {
                continue;
            }
            $dest = $base . '/' . $rel;
            if ($item->isDir()) {
                if (!is_dir($dest)) {
                    @mkdir($dest, 0775, true);
                }
                continue;
            }
            $destDir = dirname($dest);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0775, true);
            }
            if (!@copy($path, $dest)) {
                return ['ok' => false, 'error' => 'کپی فایل «' . $rel . '» انجام نشد؛ مجوز نوشتن فایل‌های برنامه را بررسی کنید.', 'new_version' => null, 'backup_file' => $backupFile];
            }
        }

        // د) تازه‌سازی کش آپکد در صورت وجود
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return ['ok' => true, 'error' => null, 'new_version' => $newVersion, 'backup_file' => $backupFile];
    } finally {
        // هـ) پاک‌سازی فایل‌های موقت در همه مسیرها
        update_remove_dir($work);
    }
}
