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
            case 'delete_customer':
                // Delegate به modules/customers/admin.php (۹٫۹۹٫۱۸)
                if (function_exists('customers_handle_post')) {
                    customers_handle_post($action);
                }
                break;

            // ---------- فاز ۲: دسته‌بندی محصولات ----------
            // ---------- فاز ۲: دسته‌بندی محصولات ----------
            case 'add_category':
            case 'update_category':
            case 'delete_category':
            case 'move_category':
                // Delegate به modules/categories/admin.php (۹٫۹۹٫۱۸)
                if (function_exists('categories_handle_post')) {
                    categories_handle_post($action);
                }
                break;

            // ---------- فاز ۲: محصولات ----------
            case 'add_product':
            case 'update_product':
            case 'delete_product_image':
            case 'move_product_image':
            case 'quick_price_product':
            case 'delete_order_field':
            case 'delete_product':
            case 'move_product':
                // Delegate به modules/products/admin.php (۹٫۹۹٫۱۸)
                if (function_exists('products_handle_post')) {
                    products_handle_post($action);
                }
                break;

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
    // Wrapper سازگاری — پیاده‌سازی واقعی در modules/customers/admin.php است (۹٫۹۹٫۱۵)
    if (function_exists('customers_render_admin')) {
        customers_render_admin($d);
        return;
    }
    echo '<p>ماژول مشتری‌ها لود نشده است.</p>';
}

function catalog_render_categories(array $d): void
{
    // Wrapper سازگاری — پیاده‌سازی واقعی در modules/categories/admin.php است (۹٫۹۹٫۱۵)
    if (function_exists('categories_render_admin')) {
        categories_render_admin($d);
        return;
    }
    echo '<p>ماژول دسته‌بندی‌ها لود نشده است.</p>';
}

function catalog_render_products(array $d): void
{
    // Wrapper سازگاری — پیاده‌سازی واقعی در modules/products/admin.php است (۹٫۹۹٫۱۴)
    if (function_exists('products_render_admin')) {
        products_render_admin($d);
        return;
    }
    echo '<p>ماژول محصولات لود نشده است.</p>';
}

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
