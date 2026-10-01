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
                $data = [
                    ':full_name' => $fullName,
                    ':company'   => trim((string) ($_POST['company'] ?? '')) ?: null,
                    ':mobile'    => $mobile,
                    ':type'      => $ctype,
                    ':city'      => trim((string) ($_POST['city'] ?? '')) ?: null,
                    ':address'   => trim((string) ($_POST['address'] ?? '')) ?: null,
                    ':notes'     => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ];
                if ($action === 'update_customer' && $cid > 0) {
                    $data[':id'] = $cid;
                    $pdo->prepare("UPDATE customers SET full_name = :full_name, company = :company, mobile = :mobile, customer_type = :type, city = :city, address = :address, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute($data);
                    flash('ok', 'مشتری به‌روزرسانی شد.');
                    redirect_admin('admin.php?page=customers&view=' . $cid);
                }
                $pdo->prepare("INSERT INTO customers (full_name, company, mobile, customer_type, city, address, notes) VALUES (:full_name, :company, :mobile, :type, :city, :address, :notes)")->execute($data);
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
                $data = [
                    ':cat'     => $catId,
                    ':name'    => $name,
                    ':sku'     => trim((string) ($_POST['sku'] ?? '')) ?: null,
                    ':desc'    => trim((string) ($_POST['description'] ?? '')) ?: null,
                    ':image'   => $image ?: null,
                    ':price'   => $price,
                    ':pprice'  => $partnerPrice,
                    ':active'  => isset($_POST['is_active']) ? 1 : 0,
                    ':sort'    => (int) ($_POST['sort_order'] ?? 0),
                ];
                if ($action === 'update_product' && $pid > 0) {
                    $data[':id'] = $pid;
                    $pdo->prepare('UPDATE products SET category_id = :cat, name = :name, sku = :sku, description = :desc, image = :image, price_per_meter = :price, partner_price_per_meter = :pprice, is_active = :active, sort_order = :sort, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                    flash('ok', 'محصول به‌روزرسانی شد.');
                } else {
                    $pdo->prepare('INSERT INTO products (category_id, name, sku, description, image, price_per_meter, partner_price_per_meter, is_active, sort_order) VALUES (:cat, :name, :sku, :desc, :image, :price, :pprice, :active, :sort)')->execute($data);
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
                redirect_admin('admin.php?page=products');
                // no break

            case 'delete_product':
                $pid = (int) ($_POST['id'] ?? 0);
                $prod = get_product($pid);
                $pdo->prepare('DELETE FROM product_attribute_values WHERE product_id = :p')->execute([':p' => $pid]);
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
$editCustomer = null;
if ($page === 'customers') {
    $customerSearch = trim((string) ($_GET['q'] ?? ''));
    $customerTypeFilter = trim((string) ($_GET['type'] ?? ''));
    $customersList = get_customers($customerSearch, $customerTypeFilter);
    if (isset($_GET['view'])) {
        $viewCustomer = get_customer((int) $_GET['view']);
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
if ($page === 'products') {
    $productsList = get_products(false);
    if (isset($_GET['edit_id'])) {
        $editProduct = get_product((int) $_GET['edit_id']);
        if ($editProduct !== null) {
            foreach (get_product_attribute_values((int) $editProduct['id']) as $v) {
                $editProductValues[(int) $v['attribute_id']] = $v;
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
        'editCustomer' => $editCustomer,
        'categoriesList' => $categoriesList,
        'editCategory' => $editCategory,
        'productsList' => $productsList,
        'editProduct' => $editProduct,
        'editProductValues' => $editProductValues,
        'attributesList' => $attributesList,
        'editAttribute' => $editAttribute,
        'editAttributeOptions' => $editAttributeOptions,
        'editOption' => $editOption,
    ];
}

/** رندر صفحه «مشتری‌ها» */
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
                        <a class="btn small" href="admin.php?page=customers&edit_id=<?= (int) $viewCustomer['id'] ?>">ویرایش مشتری</a>
                    </p>
                    <form method="post" class="inline" onsubmit="return confirm('این مشتری حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_customer">
                        <input type="hidden" name="id" value="<?= (int) $viewCustomer['id'] ?>">
                        <button type="submit" class="btn small danger-btn">حذف مشتری</button>
                    </form>
                </section>
                <section class="card wide">
                    <h2>سفارش‌های این مشتری</h2>
                    <p class="muted">هنوز سفارشی برای این مشتری ثبت نشده است. سفارش‌ها در فاز بعد به این پروفایل وصل می‌شود و سابقه خرید هر مشتری اینجا نمایش داده خواهد شد.</p>
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

                <h2><?= $editCustomer !== null ? 'ویرایش مشتری' : 'افزودن مشتری تازه' ?></h2>
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
                    <button type="submit" class="btn primary"><?= $editCustomer !== null ? 'ذخیره تغییرات' : 'ثبت مشتری' ?></button>
                    <?php if ($editCustomer !== null): ?><a class="btn" href="admin.php?page=customers">انصراف</a><?php endif; ?>
                </form>

                <h2>فهرست مشتری‌ها (<?= count($customersList) ?>)</h2>
                <?php if ($customersList === []): ?>
                    <div class="card wide"><p class="muted">هنوز مشتری‌ای ثبت نشده است. اولین مشتری را با فرم بالا اضافه کنید؛ بعداً سفارش‌ها به همین مشتری‌ها وصل می‌شوند.</p></div>
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
                                <a class="btn small" href="admin.php?page=customers&edit_id=<?= (int) $c['id'] ?>">ویرایش</a>
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
            <p class="muted">دسته‌ها می‌توانند زیردسته داشته باشند. دسته‌ای که محصول یا زیردسته دارد حذف نمی‌شود. پیش‌نمایش زنده کاتالوگ: <a href="products.php" target="_blank">products.php</a></p>

            <h2><?= $editCategory !== null ? 'ویرایش دسته‌بندی' : 'دسته‌بندی تازه' ?></h2>
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
                <button type="submit" class="btn primary"><?= $editCategory !== null ? 'ذخیره تغییرات' : 'ساخت دسته' ?></button>
                <?php if ($editCategory !== null): ?><a class="btn" href="admin.php?page=categories">انصراف</a><?php endif; ?>
            </form>

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
                                <button type="submit" class="btn small">↑</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_category">
                                <input type="hidden" name="id" value="<?= (int) $cc['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓</button>
                            </form>
                            <?= (int) $cc['sort_order'] ?>
                        </td>
                        <td><?= $cc['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=categories&edit_id=<?= (int) $cc['id'] ?>">ویرایش</a>
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
            <p class="muted">قیمت‌ها «متری» و به تومان هستند. آپشن‌ها (مثل سنسور) از بخش «ویژگی‌های محصول» تعریف می‌شوند و به قیمت متری اضافه می‌شوند. پیش‌نمایش زنده: <a href="products.php" target="_blank">کاتالوگ عمومی</a></p>

            <h2><?= $editProduct !== null ? 'ویرایش محصول' : 'محصول تازه' ?></h2>
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
                    <textarea name="description" rows="3"><?= e($editProduct['description'] ?? '') ?></textarea>
                </label>
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
                <label>قیمت متری همکار (تومان) — خالی بماند تا درصد تخفیف همکار از تنظیمات اعمال شود
                    <input type="number" name="partner_price_per_meter" id="pf-partner" min="0" step="any" value="<?= ($editProduct['partner_price_per_meter'] ?? null) !== null && ($editProduct['partner_price_per_meter'] ?? '') !== '' ? (int) $editProduct['partner_price_per_meter'] : '' ?>" placeholder="خالی = خودکار با درصد همکار">
                </label>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editProduct['sort_order'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (نمایش در کاتالوگ)
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
                <button type="submit" class="btn primary"><?= $editProduct !== null ? 'ذخیره تغییرات' : 'ثبت محصول' ?></button>
                <?php if ($editProduct !== null): ?><a class="btn" href="admin.php?page=products">انصراف</a><?php endif; ?>
            </form>
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
                <div class="card wide"><p class="muted">هنوز محصولی ثبت نشده است. اولین محصول را با فرم بالا بسازید تا در کاتالوگ عمومی سایت نمایش داده شود.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>نام محصول</th><th>دسته</th><th>قیمت متری مشتری</th><th>قیمت متری همکار</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($productsList as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?><?php if (!empty($p['sku'])): ?> <span class="muted" dir="ltr">(<?= e($p['sku']) ?>)</span><?php endif; ?></td>
                        <td><?= e($p['category_title'] ?? '—') ?></td>
                        <td><?= e(format_price($p['price_per_meter'])) ?> تومان</td>
                        <td><?= ($p['partner_price_per_meter'] ?? null) !== null && ($p['partner_price_per_meter'] ?? '') !== '' && (float) $p['partner_price_per_meter'] > 0 ? e(format_price($p['partner_price_per_meter'])) . ' تومان' : '<span class="muted">خودکار: ' . e(format_price(product_base_price_per_meter($p, true))) . ' تومان</span>' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓</button>
                            </form>
                            <?= (int) $p['sort_order'] ?>
                        </td>
                        <td><?= $p['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="products.php?id=<?= (int) $p['id'] ?>" target="_blank">مشاهده</a>
                            <a class="btn small" href="admin.php?page=products&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
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

            <h2><?= $editAttribute !== null ? 'ویرایش ویژگی' : 'ویژگی تازه' ?></h2>
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
                <button type="submit" class="btn primary"><?= $editAttribute !== null ? 'ذخیره تغییرات' : 'ساخت ویژگی' ?></button>
                <?php if ($editAttribute !== null): ?><a class="btn" href="admin.php?page=attributes">ویژگی تازه</a><?php endif; ?>
            </form>

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
                    <button type="submit" class="btn primary"><?= $editOption !== null ? 'ذخیره گزینه' : 'افزودن گزینه' ?></button>
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
                                    <button type="submit" class="btn small">↑</button>
                                </form>
                                <form method="post" class="inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_option">
                                    <input type="hidden" name="id" value="<?= (int) $opt['id'] ?>">
                                    <input type="hidden" name="attribute_id" value="<?= (int) $editAttribute['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button type="submit" class="btn small">↓</button>
                                </form>
                                <?= (int) $opt['sort_order'] ?>
                            </td>
                            <td class="actions">
                                <a class="btn small" href="admin.php?page=attributes&edit_id=<?= (int) $editAttribute['id'] ?>&edit_option=<?= (int) $opt['id'] ?>">ویرایش</a>
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
                                <button type="submit" class="btn small">↑</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_attribute">
                                <input type="hidden" name="id" value="<?= (int) $attr['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓</button>
                            </form>
                            <?= (int) $attr['sort_order'] ?>
                        </td>
                        <td><?= $attr['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=attributes&edit_id=<?= (int) $attr['id'] ?>">ویرایش<?= $attr['input_type'] === 'select' ? ' و گزینه‌ها' : '' ?></a>
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
