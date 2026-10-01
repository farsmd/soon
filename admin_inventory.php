<?php
// admin_inventory.php — بخش «انبار» پنل مدیریت (فاز ۲٫۵ نسخه ۷): مواد اولیه، گردش انبار و موجودی کارگاه.
// این فایل فقط از admin.php و بعد از احراز هویت صدا زده می‌شود؛ اکشن‌ها و صفحه‌های مواد اولیه
// و گردش انبار اینجاست تا admin.php کوچک بماند (سقف حجم آپدیت گیت‌هاب).
// منطق موجودی و بهای تمام‌شده در config.php است (apply_stock_movement و product_material_cost و ...).

declare(strict_types=1);

// دسترسی مستقیم ممنوع: این فایل به‌تنهایی هیچ خروجی و هیچ سطح مدیریتی ندارد.
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به انبار و مواد اولیه */
function inventory_post_actions(): array
{
    return ['add_material', 'update_material', 'delete_material', 'toggle_material',
        'stock_in', 'stock_out', 'stock_adjust', 'update_material_prices'];
}

/**
 * پردازش اکشن‌های POST انبار — موفق‌ها با redirect_admin تمام می‌شوند
 * و خطاها با استثنا به catch اصلی admin.php برمی‌گردند.
 */
function inventory_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
        case 'add_material':
        case 'update_material':
            $mid = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $unit = trim((string) ($_POST['unit'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('نام ماده اولیه را وارد کنید.');
            }
            if ($unit === '') {
                $unit = 'عدد';
            }
            $threshold = (float) ($_POST['low_stock_threshold'] ?? 0);
            if ($threshold < 0) {
                $threshold = 0.0;
            }
            // طول واحد تازه (شاخه/رول) به سانتی‌متر برای مواد برش‌خور — ۰ یعنی بدون برش مستقیم
            $cutUnitCm = (float) ($_POST['cut_unit_cm'] ?? 0);
            if ($cutUnitCm < 0) {
                $cutUnitCm = 0.0;
            }
            // نام واحد تازه برای لیست برش (شاخه، رول، بسته…) — ۸٫۵٫۰
            $cutLabel = trim((string) ($_POST['cut_unit_label'] ?? ''));
            if ($cutLabel === '') {
                $cutLabel = 'واحد';
            }
            $data = [
                ':name'  => $name,
                ':unit'  => $unit,
                ':thr'   => $threshold,
                ':cut'   => $cutUnitCm,
                ':cutlbl' => $cutLabel,
                ':notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                ':act'   => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($action === 'update_material' && $mid > 0) {
                $data[':id'] = $mid;
                $pdo->prepare('UPDATE materials SET name = :name, unit = :unit, low_stock_threshold = :thr, cut_unit_cm = :cut, cut_unit_label = :cutlbl, notes = :notes, is_active = :act, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                flash('ok', 'ماده اولیه به‌روزرسانی شد.');
                redirect_admin('admin.php?page=materials&edit_id=' . $mid);
            }
            $pdo->prepare('INSERT INTO materials (name, unit, low_stock_threshold, cut_unit_cm, cut_unit_label, notes, is_active) VALUES (:name, :unit, :thr, :cut, :cutlbl, :notes, :act)')->execute($data);
            flash('ok', 'ماده اولیه تازه ثبت شد. حالا از دکمه «ورود خرید» موجودی و قیمت خریدش را وارد کنید.');
            redirect_admin('admin.php?page=materials');
            // no break

        case 'toggle_material':
            $mid = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE materials SET is_active = 1 - is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':id' => $mid]);
            redirect_admin('admin.php?page=materials');
            // no break

        case 'delete_material':
            $mid = (int) ($_POST['id'] ?? 0);
            $mat = get_material($mid);
            if ($mat === null) {
                throw new RuntimeException('ماده اولیه پیدا نشد.');
            }
            $bomUse = (int) $pdo->query('SELECT COUNT(*) FROM product_materials WHERE material_id = ' . $mid)->fetchColumn();
            if ($bomUse > 0) {
                throw new RuntimeException('این ماده در فرمول ساخت ' . $bomUse . ' محصول استفاده شده و قابل حذف نیست؛ اگر دیگر مصرف نمی‌شود، آن را «غیرفعال» کنید.');
            }
            $moveCount = (int) $pdo->query('SELECT COUNT(*) FROM stock_movements WHERE material_id = ' . $mid)->fetchColumn();
            if ($moveCount > 0) {
                throw new RuntimeException('این ماده ' . $moveCount . ' گردش انبار ثبت‌شده دارد و برای حفظ سوابق قابل حذف نیست؛ آن را «غیرفعال» کنید تا از فهرست انتخاب‌ها کنار برود.');
            }
            $pdo->prepare('DELETE FROM materials WHERE id = :id')->execute([':id' => $mid]);
            flash('ok', 'ماده اولیه حذف شد.');
            redirect_admin('admin.php?page=materials');
            // no break

        case 'stock_in':
        case 'stock_out':
        case 'stock_adjust':
            $mid = (int) ($_POST['material_id'] ?? 0);
            $mat = get_material($mid);
            if ($mat === null) {
                throw new RuntimeException('ماده اولیه را انتخاب کنید.');
            }
            $reason = trim((string) ($_POST['reason'] ?? ''));
            if ($action === 'stock_in') {
                $qty = (float) ($_POST['qty'] ?? 0);
                $priceRaw = trim((string) ($_POST['unit_price'] ?? ''));
                $price = $priceRaw === '' ? null : max(0, (int) $priceRaw);
                $r = apply_stock_movement($pdo, $mid, 'in', $qty, $price, $reason !== '' ? $reason : 'خرید');
                if (!$r['ok']) {
                    throw new RuntimeException((string) $r['error']);
                }
                flash('ok', 'ورود خرید ثبت شد؛ موجودی «' . (string) $mat['name'] . '» شد ' . format_qty((float) $r['balance_after']) . ' ' . (string) $mat['unit'] . ($price !== null ? ' و آخرین قیمت خرید به‌روز شد.' : '.'));
            } elseif ($action === 'stock_out') {
                $qty = (float) ($_POST['qty'] ?? 0);
                $r = apply_stock_movement($pdo, $mid, 'out', $qty, null, $reason !== '' ? $reason : 'مصرف دستی');
                if (!$r['ok']) {
                    throw new RuntimeException((string) $r['error']);
                }
                flash('ok', 'خروج از انبار ثبت شد؛ موجودی «' . (string) $mat['name'] . '» شد ' . format_qty((float) $r['balance_after']) . ' ' . (string) $mat['unit'] . '.');
            } else {
                $target = (float) ($_POST['target_qty'] ?? 0);
                $r = apply_stock_movement($pdo, $mid, 'adjust', $target, null, $reason !== '' ? $reason : 'اصلاح موجودی (شمارش)');
                if (!$r['ok']) {
                    throw new RuntimeException((string) $r['error']);
                }
                flash('ok', 'موجودی «' . (string) $mat['name'] . '» اصلاح شد و روی ' . format_qty((float) $r['balance_after']) . ' ' . (string) $mat['unit'] . ' تنظیم شد.');
            }
        case 'update_material_prices':
            $prices = $_POST['price'] ?? [];
            if (!is_array($prices)) {
                $prices = [];
            }
            $updated = 0;
            $updStmt = $pdo->prepare('UPDATE materials SET last_price = :p, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
            foreach ($prices as $midRaw => $priceRaw) {
                $mid = (int) $midRaw;
                if ($mid <= 0) {
                    continue;
                }
                $priceRaw = trim((string) $priceRaw);
                if ($priceRaw === '') {
                    continue;
                }
                if (!is_numeric($priceRaw)) {
                    throw new RuntimeException('قیمت واردشده معتبر نیست.');
                }
                $price = (int) round((float) $priceRaw);
                if ($price < 0) {
                    throw new RuntimeException('قیمت نمی‌تواند منفی باشد.');
                }
                $cur = $pdo->query('SELECT last_price FROM materials WHERE id = ' . $mid)->fetchColumn();
                if ($cur === false) {
                    continue;
                }
                if ((int) $cur !== $price) {
                    $updStmt->execute([':p' => $price, ':id' => $mid]);
                    $updated++;
                }
            }
            log_admin_event('materials_price_list', 'به‌روزرسانی گروهی لیست قیمت مواد (' . $updated . ' قلم)');
            flash('ok', $updated > 0 ? 'قیمت ' . $updated . ' ماده به‌روزرسانی شد.' : 'هیچ قیمتی تغییر نکرد.');
            redirect_admin('admin.php?page=material_prices');
            // no break
    }
}

/** بارگذاری داده‌های صفحه‌های انبار + شمارنده هشدار موجودی (برای منو و داشبورد، در همه صفحه‌ها) */
function inventory_load_data(string $page): array
{
    $low = low_stock_materials();
    $data = [
        'lowStockCount'       => count($low),
        'lowStockList'        => $low,
        'inventoryTotalValue' => inventory_total_value(),
        'materialsList'       => [],
        'editMaterial'        => null,
        'moveMaterial'        => null,
        'moveType'            => '',
        'materialUsage'       => [],
        'movementsList'       => [],
        'stockMaterialFilter' => 0,
        'stockTypeFilter'     => '',
        'allMaterialsForFilter' => [],
    ];
    if ($page === 'materials' || $page === 'material_prices') {
        $data['materialsList'] = get_materials(false);
        if ($page === 'materials') {
        if (isset($_GET['edit_id'])) {
            $data['editMaterial'] = get_material((int) $_GET['edit_id']);
        }
        if (isset($_GET['move'])) {
            $data['moveMaterial'] = get_material((int) $_GET['move']);
            $t = (string) ($_GET['mtype'] ?? 'in');
            $data['moveType'] = in_array($t, ['in', 'out', 'adjust'], true) ? $t : 'in';
        }
        foreach (db()->query('SELECT material_id, COUNT(*) AS c FROM product_materials GROUP BY material_id')->fetchAll() as $r) {
            $data['materialUsage'][(int) $r['material_id']]['bom'] = (int) $r['c'];
        }
        foreach (db()->query('SELECT material_id, COUNT(*) AS c FROM stock_movements GROUP BY material_id')->fetchAll() as $r) {
            $data['materialUsage'][(int) $r['material_id']]['moves'] = (int) $r['c'];
        }
        } // if materials
    }
    if ($page === 'stock') {
        $data['allMaterialsForFilter'] = get_materials(false);
        $data['stockMaterialFilter'] = (int) ($_GET['material_id'] ?? 0);
        $t = (string) ($_GET['type'] ?? '');
        $data['stockTypeFilter'] = in_array($t, ['in', 'out', 'adjust'], true) ? $t : '';
        $sql = 'SELECT sm.*, m.name AS material_name, m.unit AS material_unit FROM stock_movements sm JOIN materials m ON m.id = sm.material_id WHERE 1 = 1';
        $params = [];
        if ($data['stockMaterialFilter'] > 0) {
            $sql .= ' AND sm.material_id = :mid';
            $params[':mid'] = $data['stockMaterialFilter'];
        }
        if ($data['stockTypeFilter'] !== '') {
            $sql .= ' AND sm.move_type = :t';
            $params[':t'] = $data['stockTypeFilter'];
        }
        $sql .= ' ORDER BY sm.id DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $data['movementsList'] = $stmt->fetchAll();
    }
    return $data;
}

/** برچسب فارسی نوع گردش انبار */
function stock_move_type_label(string $type): string
{
    return ['in' => 'ورود (خرید)', 'out' => 'خروج (مصرف)', 'adjust' => 'اصلاح موجودی'][$type] ?? $type;
}

/** رندر صفحه «مواد اولیه» */
function inventory_render_materials(array $d): void
{
    extract($d);
    ?>
            <h1>مواد اولیه و انبار کارگاه</h1>
            <p class="muted">موجودی هر ماده و «آخرین قیمت خرید» آن اینجاست؛ بهای تمام‌شده محصولات از روی همین قیمت‌ها حساب می‌شود. خرید تازه را با «ورود خرید» ثبت کنید تا هم موجودی زیاد شود هم قیمت جاری ماده به‌روز شود.</p>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>ارزش کل موجودی انبار</span><strong><?= e(format_price($inventoryTotalValue)) ?> تومان</strong></div>
                <div class="stat-card"><span>مواد تعریف‌شده</span><strong><?= count($materialsList) ?></strong></div>
                <a class="stat-card" href="admin.php?page=stock"><span>گردش انبار</span><strong>مشاهده سوابق</strong></a>
                <?php if ($lowStockCount > 0): ?>
                <div class="stat-card" style="border-color:#dc2626;background:#fef2f2"><span style="color:#b91c1c">⚠ رو به اتمام</span><strong style="color:#b91c1c"><?= $lowStockCount ?> ماده</strong></div>
                <?php endif; ?>
            </div>

            <?php if ($lowStockList !== []): ?>
            <div class="alert error">
                موجودی این مواد به حد هشدار رسیده یا از آن کمتر است:
                <?php foreach ($lowStockList as $lm): ?>
                    <strong><?= e($lm['name']) ?></strong> (<?= e(format_qty((float) $lm['stock_qty'])) ?> <?= e($lm['unit']) ?>)
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($moveMaterial !== null): ?>
            <h2><?= e(stock_move_type_label($moveType)) ?> — <?= e($moveMaterial['name']) ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="stock_<?= $moveType === 'adjust' ? 'adjust' : $moveType ?>">
                <input type="hidden" name="material_id" value="<?= (int) $moveMaterial['id'] ?>">
                <p class="muted">موجودی فعلی: <strong><?= e(format_qty((float) $moveMaterial['stock_qty'])) ?> <?= e($moveMaterial['unit']) ?></strong> — آخرین قیمت خرید: <strong><?= e(format_price($moveMaterial['last_price'])) ?> تومان</strong> به‌ازای هر <?= e($moveMaterial['unit']) ?></p>
                <?php if ($moveType === 'in'): ?>
                <label>مقدار خرید (<?= e($moveMaterial['unit']) ?>) *
                    <input type="number" name="qty" step="any" min="0" required>
                </label>
                <label>قیمت خرید هر <?= e($moveMaterial['unit']) ?> (تومان) *
                    <input type="number" name="unit_price" step="1" min="0" required placeholder="این قیمت، «آخرین قیمت خرید» ماده می‌شود">
                </label>
                <?php elseif ($moveType === 'out'): ?>
                <label>مقدار مصرف/خروج (<?= e($moveMaterial['unit']) ?>) *
                    <input type="number" name="qty" step="any" min="0" required>
                </label>
                <?php else: ?>
                <label>موجودی واقعی شمارش‌شده (<?= e($moveMaterial['unit']) ?>) *
                    <input type="number" name="target_qty" step="any" min="0" required value="<?= e(format_qty((float) $moveMaterial['stock_qty'])) ?>">
                </label>
                <?php endif; ?>
                <label>دلیل / توضیح
                    <input type="text" name="reason" placeholder="<?= $moveType === 'in' ? 'مثلاً خرید از فروشنده ...' : ($moveType === 'out' ? 'مثلاً مصرف برای سفارش ...' : 'مثلاً شمارش انبار ماهانه') ?>">
                </label>
                <button type="submit" class="btn primary">ثبت گردش</button>
                <a class="btn" href="admin.php?page=materials">انصراف</a>
            </form>
            <?php endif; ?>

            <?php if ($editMaterial === null && $moveMaterial === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="material-form-panel" aria-expanded="false">+ افزودن ماده اولیه</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="material-form-panel" <?= $editMaterial !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editMaterial !== null ? 'ویرایش ماده اولیه: ' . e($editMaterial['name'] ?? '') : 'ماده اولیه تازه' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editMaterial !== null ? 'update_material' : 'add_material' ?>">
                <?php if ($editMaterial !== null): ?><input type="hidden" name="id" value="<?= (int) $editMaterial['id'] ?>"><?php endif; ?>
                <label>نام ماده *
                    <input type="text" name="name" required value="<?= e($editMaterial['name'] ?? '') ?>" placeholder="مثلاً پروفیل آلومینیوم ۲۰×۱۰">
                </label>
                <label>واحد
                    <input type="text" name="unit" list="mat-units" value="<?= e($editMaterial['unit'] ?? 'عدد') ?>" placeholder="متر، عدد، بسته...">
                    <datalist id="mat-units">
                        <option value="متر"><option value="عدد"><option value="بسته"><option value="شاخه"><option value="کیلو"><option value="رول"><option value="لیتر"><option value="جفت"><option value="دست">
                    </datalist>
                </label>
                <label>حد هشدار موجودی — وقتی موجودی به این مقدار یا کمتر رسید، هشدار داده شود (۰ = بدون هشدار)
                    <input type="number" name="low_stock_threshold" step="any" min="0" value="<?= isset($editMaterial['low_stock_threshold']) ? e(format_qty((float) $editMaterial['low_stock_threshold'])) : '0' ?>">
                </label>
                <label>طول هر واحد تازه / شاخه / رول (سانت) — فقط برای مواد برش‌خور مثل پروفیل و نوار LED؛ ۰ یعنی بدون برش. موجودی این مواد بر حسب «متر» ثبت شود تا لیست برش تولید درست کار کند (مثلاً پروفیل: ۳۰۰، نوار LED: ‏۵۰۰)
                    <input type="number" name="cut_unit_cm" step="any" min="0" value="<?= isset($editMaterial['cut_unit_cm']) ? e(format_qty((float) $editMaterial['cut_unit_cm'])) : '0' ?>">
                </label>
                <label>نام واحد تازه در لیست برش — مثلاً شاخه، رول، بسته؛ در برگه تولید «شاخه ۱ (۳۰۰ سانت)» یا «رول ۲ (۵۰۰ سانت)» نوشته می‌شود
                    <input type="text" name="cut_unit_label" value="<?= e($editMaterial['cut_unit_label'] ?? 'واحد') ?>" placeholder="شاخه، رول، بسته…">
                </label>
                <label>یادداشت
                    <input type="text" name="notes" value="<?= e($editMaterial['notes'] ?? '') ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editMaterial['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (قابل انتخاب در فرمول ساخت محصولات)
                </label>
                <?php if ($editMaterial !== null): ?>
                <p class="muted">موجودی و قیمت خرید از این فرم تغییر نمی‌کند؛ برای آن‌ها از دکمه‌های «ورود خرید»، «خروج» و «اصلاح» در فهرست زیر استفاده کنید.</p>
                <?php endif; ?>
                <button type="submit" class="btn <?= $editMaterial !== null ? 'edit' : 'add' ?>"><?= $editMaterial !== null ? 'ذخیره تغییرات' : 'ثبت ماده اولیه' ?></button>
                <?php if ($editMaterial !== null): ?><a class="btn" href="admin.php?page=materials">انصراف</a><?php endif; ?>
            </form>
            </div>

            <h2>فهرست مواد (<?= count($materialsList) ?>)</h2>
            <?php if ($materialsList === []): ?>
                <div class="card wide"><p class="muted">هنوز ماده‌ای ثبت نشده است. اولین ماده را با دکمه «+ افزودن ماده اولیه» بسازید، بعد برای محصولات از صفحه ویرایش محصول «مواد مصرفی» تعریف کنید.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>نام ماده</th><th>واحد</th><th>برش</th><th>موجودی</th><th>آخرین قیمت خرید</th><th>ارزش موجودی</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($materialsList as $m):
                    $mid = (int) $m['id'];
                    $isLow = (int) $m['is_active'] === 1 && (float) $m['low_stock_threshold'] > 0 && (float) $m['stock_qty'] <= (float) $m['low_stock_threshold'];
                    $usage = $materialUsage[$mid] ?? ['bom' => 0, 'moves' => 0];
                ?>
                    <tr>
                        <td><?= e($m['name']) ?><?php if (!empty($m['notes'])): ?><br><span class="muted"><?= e($m['notes']) ?></span><?php endif; ?></td>
                        <td><?= e($m['unit']) ?></td>
                        <td><?= (float) ($m['cut_unit_cm'] ?? 0) > 0 ? e($m['cut_unit_label'] ?? 'واحد') . ' ' . e(format_qty((float) $m['cut_unit_cm'])) . ' سانت' : '<span class="muted">—</span>' ?></td>
                        <td><strong><?= e(format_qty((float) $m['stock_qty'])) ?></strong></td>
                        <td><?= e(format_price($m['last_price'])) ?> تومان</td>
                        <td><?= e(format_price((float) $m['stock_qty'] * (int) $m['last_price'])) ?> تومان</td>
                        <td>
                            <?php if ((int) $m['is_active'] !== 1): ?><span class="badge off">غیرفعال</span>
                            <?php elseif ($isLow): ?><span class="badge" style="background:#fee2e2;color:#b91c1c">⚠ رو به اتمام</span>
                            <?php else: ?><span class="badge ok">فعال</span><?php endif; ?>
                        </td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=materials&move=<?= $mid ?>&mtype=in">ورود خرید</a>
                            <a class="btn small" href="admin.php?page=materials&move=<?= $mid ?>&mtype=out">خروج</a>
                            <a class="btn small" href="admin.php?page=materials&move=<?= $mid ?>&mtype=adjust">اصلاح</a>
                            <a class="btn small edit" href="admin.php?page=materials&edit_id=<?= $mid ?>">ویرایش</a>
                            <a class="btn small" href="admin.php?page=stock&material_id=<?= $mid ?>">سوابق</a>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle_material">
                                <input type="hidden" name="id" value="<?= $mid ?>">
                                <button type="submit" class="btn small warn"><?= (int) $m['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button>
                            </form>
                            <form method="post" class="inline" onsubmit="return confirm('این ماده اولیه حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_material">
                                <input type="hidden" name="id" value="<?= $mid ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted">حذف ماده‌ای که در فرمول ساخت محصولی استفاده شده یا گردش انبار دارد ممکن نیست؛ به‌جای حذف، آن را غیرفعال کنید.</p>
            <?php endif; ?>
    <?php
}

/** رندر صفحه «گردش انبار» */
function inventory_render_stock(array $d): void
{
    extract($d);
    ?>
            <h1>گردش انبار</h1>
            <p class="muted">سوابق ورود (خرید)، خروج (مصرف) و اصلاح موجودی مواد. تغییر موجودی هر ماده فقط از همین مسیرها انجام می‌شود تا سابقه‌اش بماند.</p>

            <form method="get" class="card wide">
                <input type="hidden" name="page" value="stock">
                <label>ماده اولیه
                    <select name="material_id">
                        <option value="0">— همه مواد —</option>
                        <?php foreach ($allMaterialsForFilter as $m): ?>
                            <option value="<?= (int) $m['id'] ?>" <?= $stockMaterialFilter === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>نوع گردش
                    <select name="type">
                        <option value="">— همه نوع‌ها —</option>
                        <option value="in" <?= $stockTypeFilter === 'in' ? 'selected' : '' ?>>ورود (خرید)</option>
                        <option value="out" <?= $stockTypeFilter === 'out' ? 'selected' : '' ?>>خروج (مصرف)</option>
                        <option value="adjust" <?= $stockTypeFilter === 'adjust' ? 'selected' : '' ?>>اصلاح موجودی</option>
                    </select>
                </label>
                <button type="submit" class="btn primary">فیلتر</button>
                <a class="btn" href="admin.php?page=stock">حذف فیلتر</a>
                <a class="btn" href="admin.php?page=materials">ثبت گردش تازه</a>
            </form>

            <h2>آخرین گردش‌ها (<?= count($movementsList) ?>)</h2>
            <?php if ($movementsList === []): ?>
                <div class="card wide"><p class="muted">هنوز گردشی ثبت نشده است. از صفحه «مواد اولیه» دکمه «ورود خرید» را بزنید.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>تاریخ</th><th>ماده</th><th>نوع</th><th>تغییر موجودی</th><th>قیمت واحد (تومان)</th><th>موجودی بعد از گردش</th><th>دلیل</th></tr></thead>
                <tbody>
                <?php foreach ($movementsList as $mv): ?>
                    <tr>
                        <td><?= e($mv['created_at']) ?></td>
                        <td><?= e($mv['material_name']) ?></td>
                        <td><?= e(stock_move_type_label((string) $mv['move_type'])) ?></td>
                        <td dir="ltr" style="text-align:end"><strong style="color:<?= (float) $mv['qty'] >= 0 ? '#059669' : '#dc2626' ?>"><?= (float) $mv['qty'] >= 0 ? '+' : '' ?><?= e(format_qty((float) $mv['qty'])) ?></strong> <?= e($mv['material_unit']) ?></td>
                        <td><?= $mv['unit_price'] !== null ? e(format_price($mv['unit_price'])) : '—' ?></td>
                        <td><?= e(format_qty((float) $mv['balance_after'])) ?> <?= e($mv['material_unit']) ?></td>
                        <td><?= e($mv['reason'] ?? '') ?><?php if (!empty($mv['ref_type'])): ?> <span class="muted">(<?= e($mv['ref_type']) ?><?= !empty($mv['ref_id']) ? ' #' . (int) $mv['ref_id'] : '' ?>)</span><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    <?php
}

/** رندر صفحه «لیست قیمت مواد اولیه» — آپدیت سریع و گروهی قیمت‌ها (۸٫۵٫۰) */
function inventory_render_price_list(array $d): void
{
    extract($d);
    ?>
            <h1>لیست قیمت مواد اولیه</h1>
            <p class="muted">قیمت هر ماده را همین‌جا سریع ویرایش کنید و یک‌جا ذخیره کنید. این قیمت‌ها «آخرین قیمت خرید» ماده‌اند و بهای تمام‌شده محصولات از روی آن‌ها حساب می‌شود. تغییر قیمت، گردش انبار ثبت نمی‌کند؛ برای ثبت خرید تازه (که هم موجودی و هم قیمت را تغییر می‌دهد) از «ورود خرید» در صفحه مواد اولیه استفاده کنید.</p>

            <?php if ($materialsList === []): ?>
                <div class="card wide"><p class="muted">هنوز ماده‌ای ثبت نشده است.</p></div>
            <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_material_prices">
                <table>
                    <thead><tr><th>#</th><th>نام ماده</th><th>واحد</th><th>موجودی</th><th>قیمت فعلی (تومان)</th><th>قیمت تازه (تومان)</th></tr></thead>
                    <tbody>
                    <?php $rn = 0; foreach ($materialsList as $m): $rn++; $mid = (int) $m['id']; ?>
                        <tr>
                            <td><?= $rn ?></td>
                            <td><?= e($m['name']) ?><?php if ((int) $m['is_active'] !== 1): ?> <span class="badge off">غیرفعال</span><?php endif; ?></td>
                            <td><?= e($m['unit']) ?></td>
                            <td><strong><?= e(format_qty((float) $m['stock_qty'])) ?></strong></td>
                            <td class="muted" dir="ltr" style="text-align:end"><?= e(format_price($m['last_price'])) ?></td>
                            <td style="min-width:150px">
                                <input type="number" name="price[<?= $mid ?>]" min="0" step="1" value="<?= (int) ($m['last_price'] ?? 0) ?>" dir="ltr" style="text-align:end">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn add">💾 ذخیره همه قیمت‌ها</button>
                <a class="btn" href="admin.php?page=materials">بازگشت به مواد اولیه</a>
            </form>
            <p class="muted">فقط قیمت‌هایی که تغییر کرده‌اند به‌روز می‌شوند و در لاگ سیستم ثبت می‌شود.</p>
            <?php endif; ?>
    <?php
}
