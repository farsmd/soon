<?php
// admin_assets.php — بخش «تجهیزات و دارایی‌ها» پنل مدیریت (موج ۱ نسخه ۹٫۷).
// این فایل فقط از admin.php و بعد از احراز هویت صدا زده می‌شود؛ اکشن‌ها و صفحه‌های
// تجهیزات، استهلاک و دفترچه تعمیرات اینجاست تا admin.php کوچک بماند
// (سقف حجم آپدیت گیت‌هاب). منطق استهلاک خط مستقیم همین‌جاست.

declare(strict_types=1);

// دسترسی مستقیم ممنوع: این فایل به‌تنهایی هیچ خروجی و هیچ سطح مدیریتی ندارد.
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به تجهیزات و دارایی‌ها */
function assets_post_actions(): array
{
    return ['add_equipment', 'update_equipment', 'retire_equipment',
        'add_maintenance', 'delete_maintenance'];
}

/** دسته‌بندی‌های تجهیز */
function equipment_categories(): array
{
    return [
        'cutting'     => 'دستگاه برش',
        'assembly'    => 'دستگاه مونتاژ',
        'hand_tool'   => 'ابزار دستی',
        'electrical'  => 'تجهیزات برقی',
        'computer'    => 'کامپیوتر',
        'vehicle'     => 'خودرو',
        'other'       => 'سایر',
    ];
}

/** برچسب فارسی دسته‌بندی تجهیز */
function equipment_category_label(string $cat): string
{
    return equipment_categories()[$cat] ?? ($cat !== '' ? $cat : '—');
}

/** وضعیت‌های تجهیز */
function equipment_statuses(): array
{
    return [
        'active'    => 'فعال',
        'in_repair' => 'در تعمیر',
        'retired'   => 'اسقاط',
    ];
}

/** برچسب فارسی وضعیت تجهیز */
function equipment_status_label(string $st): string
{
    return equipment_statuses()[$st] ?? $st;
}

/** انواع سرویس/تعمیر */
function maintenance_types(): array
{
    return [
        'repair'      => 'تعمیر',
        'service'     => 'سرویس دوره‌ای',
        'calibration' => 'کالیبراسیون',
    ];
}

/** برچسب فارسی نوع تعمیر */
function maintenance_type_label(string $t): string
{
    return maintenance_types()[$t] ?? $t;
}

/**
 * استهلاک خط مستقیم یک تجهیز.
 * برمی‌گرداند: ['annual' => استهلاک سالانه, 'years' => سال‌های گذشته از خرید,
 *               'book_value' => ارزش دفتری فعلی, 'depreciated' => استهلاک انباشته]
 */
function equipment_depreciation(array $eq): array
{
    $price = max(0, (int) ($eq['purchase_price'] ?? 0));
    $life = (float) ($eq['useful_life_years'] ?? 5);
    if ($life <= 0) {
        $life = 5.0;
    }
    $annual = $price / $life;
    $years = 0.0;
    $pdate = trim((string) ($eq['purchase_date'] ?? ''));
    if ($pdate !== '') {
        $ts = strtotime($pdate);
        if ($ts !== false && $ts <= time()) {
            $years = (time() - $ts) / (365.25 * 86400);
        }
    }
    $depreciated = (int) round($annual * $years);
    $book = max(0, $price - $depreciated);
    return [
        'annual'       => (int) round($annual),
        'years'        => $years,
        'book_value'   => $book,
        'depreciated'  => $depreciated,
    ];
}

/** یک تجهیز بر اساس id */
function get_equipment(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM equipment WHERE id = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** فهرست همه تجهیزات */
function get_all_equipment(bool $includeRetired = true): array
{
    $sql = 'SELECT * FROM equipment';
    if (!$includeRetired) {
        $sql .= " WHERE status != 'retired'";
    }
    $sql .= ' ORDER BY status = \'retired\' ASC, name ASC';
    return db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

/** مجموع هزینه تعمیرات یک تجهیز */
function equipment_maintenance_total(int $equipmentId): int
{
    $st = db()->prepare('SELECT COALESCE(SUM(cost), 0) FROM equipment_maintenance WHERE equipment_id = :id');
    $st->execute([':id' => $equipmentId]);
    return (int) $st->fetchColumn();
}

/**
 * پردازش اکشن‌های POST تجهیزات — موفق‌ها با redirect_admin تمام می‌شوند
 * و خطاها با استثنا به catch اصلی admin.php برمی‌گردند.
 */
function assets_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
        case 'add_equipment':
        case 'update_equipment':
            $eid = (int) ($_POST['id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                throw new RuntimeException('نام تجهیز را وارد کنید.');
            }
            $cat = (string) ($_POST['category'] ?? 'other');
            if (!array_key_exists($cat, equipment_categories())) {
                $cat = 'other';
            }
            $pdate = trim((string) ($_POST['purchase_date'] ?? ''));
            $price = max(0, (int) ($_POST['purchase_price'] ?? 0));
            $life = (float) ($_POST['useful_life_years'] ?? 5);
            if ($life <= 0) {
                $life = 5.0;
            }
            $status = (string) ($_POST['status'] ?? 'active');
            if (!array_key_exists($status, equipment_statuses())) {
                $status = 'active';
            }
            $data = [
                ':name'  => $name,
                ':cat'   => $cat,
                ':pdate' => $pdate,
                ':price' => $price,
                ':life'  => $life,
                ':st'    => $status,
                ':loc'   => trim((string) ($_POST['location'] ?? '')),
                ':notes' => trim((string) ($_POST['notes'] ?? '')),
            ];
            if ($action === 'update_equipment' && $eid > 0) {
                $data[':id'] = $eid;
                $pdo->prepare('UPDATE equipment SET name = :name, category = :cat, purchase_date = :pdate, purchase_price = :price, useful_life_years = :life, status = :st, location = :loc, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                flash('ok', 'تجهیز به‌روزرسانی شد.');
                redirect_admin('admin.php?page=assets&edit_id=' . $eid);
            }
            $pdo->prepare('INSERT INTO equipment (name, category, purchase_date, purchase_price, useful_life_years, status, location, notes) VALUES (:name, :cat, :pdate, :price, :life, :st, :loc, :notes)')->execute($data);
            flash('ok', 'تجهیز تازه ثبت شد.');
            redirect_admin('admin.php?page=assets');
            // no break

        case 'retire_equipment':
            $eid = (int) ($_POST['id'] ?? 0);
            $eq = get_equipment($eid);
            if ($eq === null) {
                throw new RuntimeException('تجهیز پیدا نشد.');
            }
            // اسقاط نرم: فقط وضعیت عوض می‌شود تا سوابق تعمیرات حفظ شود
            $pdo->prepare("UPDATE equipment SET status = 'retired', updated_at = CURRENT_TIMESTAMP WHERE id = :id")->execute([':id' => $eid]);
            flash('ok', 'تجهیز «' . $eq['name'] . '» اسقاط شد؛ سوابق آن حفظ می‌ماند.');
            redirect_admin('admin.php?page=assets');
            // no break

        case 'add_maintenance':
            $eid = (int) ($_POST['equipment_id'] ?? 0);
            $eq = get_equipment($eid);
            if ($eq === null) {
                throw new RuntimeException('تجهیز را انتخاب کنید.');
            }
            $mdate = trim((string) ($_POST['maint_date'] ?? ''));
            if ($mdate === '') {
                $mdate = date('Y-m-d');
            }
            $mtype = (string) ($_POST['maint_type'] ?? 'repair');
            if (!array_key_exists($mtype, maintenance_types())) {
                $mtype = 'repair';
            }
            $cost = max(0, (int) ($_POST['cost'] ?? 0));
            $desc = trim((string) ($_POST['description'] ?? ''));
            $pdo->prepare('INSERT INTO equipment_maintenance (equipment_id, maint_date, maint_type, cost, description) VALUES (:eid, :d, :t, :c, :desc)')
                ->execute([':eid' => $eid, ':d' => $mdate, ':t' => $mtype, ':c' => $cost, ':desc' => $desc]);
            flash('ok', 'سرویس/تعمیر برای «' . $eq['name'] . '» ثبت شد.');
            redirect_admin('admin.php?page=assets_maintenance&equipment_id=' . $eid);
            // no break

        case 'delete_maintenance':
            $mid = (int) ($_POST['id'] ?? 0);
            $st = $pdo->prepare('SELECT * FROM equipment_maintenance WHERE id = :id LIMIT 1');
            $st->execute([':id' => $mid]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                throw new RuntimeException('رکورد تعمیر پیدا نشد.');
            }
            $pdo->prepare('DELETE FROM equipment_maintenance WHERE id = :id')->execute([':id' => $mid]);
            flash('ok', 'رکورد تعمیر حذف شد.');
            redirect_admin('admin.php?page=assets_maintenance&equipment_id=' . (int) $row['equipment_id']);
            // no break
    }
}

/** بارگذاری داده‌های صفحه‌های تجهیزات */
function assets_load_data(string $page): array
{
    $data = [
        'equipmentList'     => [],
        'editEquipment'     => null,
        'maintenanceList'   => [],
        'maintEquipmentId'  => 0,
        'maintEquipment'    => null,
        'maintTotals'       => [], // equipment_id => total cost
        'totalPurchase'     => 0,
        'totalBookValue'    => 0,
        'totalMaintCost'    => 0,
        'activeCount'       => 0,
    ];
    $data['equipmentList'] = get_all_equipment(true);
    foreach ($data['equipmentList'] as $eq) {
        $data['totalPurchase'] += max(0, (int) ($eq['purchase_price'] ?? 0));
        $dep = equipment_depreciation($eq);
        $data['totalBookValue'] += $dep['book_value'];
        if (($eq['status'] ?? '') !== 'retired') {
            $data['activeCount']++;
        }
    }
    foreach (db()->query('SELECT equipment_id, COALESCE(SUM(cost), 0) AS total FROM equipment_maintenance GROUP BY equipment_id')->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $eid = (int) $r['equipment_id'];
        $data['maintTotals'][$eid] = (int) $r['total'];
        $data['totalMaintCost'] += (int) $r['total'];
    }
    if ($page === 'assets' && isset($_GET['edit_id'])) {
        $data['editEquipment'] = get_equipment((int) $_GET['edit_id']);
    }
    if ($page === 'assets_maintenance') {
        $data['maintEquipmentId'] = (int) ($_GET['equipment_id'] ?? 0);
        if ($data['maintEquipmentId'] > 0) {
            $data['maintEquipment'] = get_equipment($data['maintEquipmentId']);
        }
        $sql = 'SELECT em.*, e.name AS equipment_name FROM equipment_maintenance em JOIN equipment e ON e.id = em.equipment_id';
        $params = [];
        if ($data['maintEquipmentId'] > 0) {
            $sql .= ' WHERE em.equipment_id = :eid';
            $params[':eid'] = $data['maintEquipmentId'];
        }
        $sql .= ' ORDER BY em.maint_date DESC, em.id DESC';
        $st = db()->prepare($sql);
        $st->execute($params);
        $data['maintenanceList'] = $st->fetchAll(PDO::FETCH_ASSOC);
    }
    return $data;
}

/** رندر صفحه «تجهیزات و دارایی‌ها» */
function assets_render_list(array $d): void
{
    extract($d);
    ?>
            <h1>تجهیزات و دارایی‌ها</h1>
            <p class="muted">دستگاه‌ها و دارایی‌های کارگاه اینجاست؛ ارزش دفتری هر تجهیز با استهلاک خط مستقیم حساب می‌شود (قیمت خرید ÷ عمر مفید). اسقاط، حذف نیست — فقط وضعیت عوض می‌شود تا سوابق تعمیرات حفظ بماند.</p>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>ارزش خرید کل دارایی‌ها</span><strong><?= e(format_price($totalPurchase)) ?> تومان</strong></div>
                <div class="stat-card"><span>ارزش دفتری فعلی</span><strong><?= e(format_price($totalBookValue)) ?> تومان</strong></div>
                <div class="stat-card"><span>هزینه کل تعمیرات</span><strong><?= e(format_price($totalMaintCost)) ?> تومان</strong></div>
                <a class="stat-card" href="admin.php?page=assets_maintenance"><span>تجهیزات فعال</span><strong><?= (int) $activeCount ?> تجهیز</strong></a>
            </div>

            <?php if ($editEquipment === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="equipment-form-panel" aria-expanded="false">+ افزودن تجهیز</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="equipment-form-panel" <?= $editEquipment !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editEquipment !== null ? 'ویرایش تجهیز: ' . e($editEquipment['name'] ?? '') : 'تجهیز تازه' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editEquipment !== null ? 'update_equipment' : 'add_equipment' ?>">
                <?php if ($editEquipment !== null): ?><input type="hidden" name="id" value="<?= (int) $editEquipment['id'] ?>"><?php endif; ?>
                <label>نام تجهیز *
                    <input type="text" name="name" required value="<?= e($editEquipment['name'] ?? '') ?>" placeholder="مثلاً اره برش پروفیل">
                </label>
                <div class="inline-fields">
                    <label>دسته‌بندی
                        <select name="category">
                            <?php foreach (equipment_categories() as $ck => $ct): ?>
                            <option value="<?= e($ck) ?>" <?= ($editEquipment['category'] ?? 'other') === $ck ? 'selected' : '' ?>><?= e($ct) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>وضعیت
                        <select name="status">
                            <?php foreach (equipment_statuses() as $sk => $st): ?>
                            <option value="<?= e($sk) ?>" <?= ($editEquipment['status'] ?? 'active') === $sk ? 'selected' : '' ?>><?= e($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
                <div class="inline-fields">
                    <label>تاریخ خرید
                        <input type="date" name="purchase_date" value="<?= e($editEquipment['purchase_date'] ?? '') ?>" dir="ltr">
                    </label>
                    <label>قیمت خرید (تومان)
                        <input type="number" name="purchase_price" min="0" step="1" value="<?= (int) ($editEquipment['purchase_price'] ?? 0) ?>">
                    </label>
                    <label>عمر مفید (سال) — برای استهلاک خط مستقیم
                        <input type="number" name="useful_life_years" min="0.5" step="0.5" value="<?= e((string) ($editEquipment['useful_life_years'] ?? 5)) ?>">
                    </label>
                </div>
                <label>محل استقرار
                    <input type="text" name="location" value="<?= e($editEquipment['location'] ?? '') ?>" placeholder="مثلاً سالن برش">
                </label>
                <label>یادداشت
                    <input type="text" name="notes" value="<?= e($editEquipment['notes'] ?? '') ?>">
                </label>
                <button type="submit" class="btn <?= $editEquipment !== null ? 'edit' : 'add' ?>"><?= $editEquipment !== null ? 'ذخیره تغییرات' : 'ثبت تجهیز' ?></button>
                <?php if ($editEquipment !== null): ?><a class="btn" href="admin.php?page=assets">انصراف</a><?php endif; ?>
            </form>
            </div>

            <h2>فهرست تجهیزات (<?= count($equipmentList) ?>)</h2>
            <?php if ($equipmentList === []): ?>
                <div class="card wide"><p class="muted">هنوز تجهیزی ثبت نشده است. اولین تجهیز را با دکمه «+ افزودن تجهیز» بسازید.</p></div>
            <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>نام تجهیز</th><th>دسته</th><th>قیمت خرید</th><th>استهلاک سالانه</th><th>ارزش دفتری</th><th>تعمیرات</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($equipmentList as $eq):
                    $eid = (int) $eq['id'];
                    $dep = equipment_depreciation($eq);
                    $mtotal = $maintTotals[$eid] ?? 0;
                    $st = (string) ($eq['status'] ?? 'active');
                ?>
                    <tr<?= $st === 'retired' ? ' class="muted"' : '' ?>>
                        <td><strong><?= e($eq['name']) ?></strong><?php if (!empty($eq['location'])): ?><br><span class="muted"><?= e($eq['location']) ?></span><?php endif; ?></td>
                        <td><?= e(equipment_category_label((string) ($eq['category'] ?? ''))) ?></td>
                        <td><?= e(format_price($eq['purchase_price'])) ?> تومان</td>
                        <td><?= e(format_price($dep['annual'])) ?> تومان</td>
                        <td><strong><?= e(format_price($dep['book_value'])) ?></strong> تومان<br><span class="muted"><?= e(format_price($dep['depreciated'])) ?> مستهلک‌شده</span></td>
                        <td><?= $mtotal > 0 ? e(format_price($mtotal)) . ' تومان' : '<span class="muted">—</span>' ?></td>
                        <td>
                            <?php if ($st === 'active'): ?><span class="badge ok">فعال</span>
                            <?php elseif ($st === 'in_repair'): ?><span class="badge" style="background:#fef3c7;color:#92400e">در تعمیر</span>
                            <?php else: ?><span class="badge off">اسقاط</span>
                            <?php endif; ?>
                        </td>
                        <td class="row-actions">
                            <a class="btn small edit" href="admin.php?page=assets&edit_id=<?= $eid ?>">ویرایش</a>
                            <a class="btn small" href="admin.php?page=assets_maintenance&equipment_id=<?= $eid ?>">تعمیرات</a>
                            <?php if ($st !== 'retired'): ?>
                            <form method="post" class="inline" onsubmit="return confirm('این تجهیز اسقاط شود؟ سوابق تعمیرات آن حفظ می‌ماند.')"><?= csrf_field() ?><input type="hidden" name="action" value="retire_equipment"><input type="hidden" name="id" value="<?= $eid ?>"><button type="submit" class="btn small danger-btn">اسقاط</button></form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
    <?php
}

/** رندر صفحه «دفترچه تعمیرات» */
function assets_render_maintenance(array $d): void
{
    extract($d);
    ?>
            <h1>دفترچه تعمیرات و سرویس</h1>
            <p class="muted">سوابق تعمیر، سرویس دوره‌ای و کالیبراسیون تجهیزات. هزینه‌ها در جمع هزینه نگهداری هر تجهیز لحاظ می‌شود.</p>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>هزینه کل تعمیرات</span><strong><?= e(format_price($totalMaintCost)) ?> تومان</strong></div>
                <div class="stat-card"><span>تعداد رکوردها</span><strong><?= count($maintenanceList) ?></strong></div>
                <a class="stat-card" href="admin.php?page=assets"><span>فهرست تجهیزات</span><strong>بازگشت</strong></a>
            </div>

            <form method="get" class="card wide" style="margin-bottom:16px">
                <input type="hidden" name="page" value="assets_maintenance">
                <label>فیلتر بر اساس تجهیز
                    <select name="equipment_id" onchange="this.form.submit()">
                        <option value="0">— همه تجهیزات —</option>
                        <?php foreach ($equipmentList as $eq): ?>
                        <option value="<?= (int) $eq['id'] ?>" <?= $maintEquipmentId === (int) $eq['id'] ? 'selected' : '' ?>><?= e($eq['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>

            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="maintenance-form-panel" aria-expanded="false">+ ثبت سرویس / تعمیر</button>
            </div>
            <div class="crud-panel" id="maintenance-form-panel" hidden>
            <h2>ثبت سرویس / تعمیر تازه</h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_maintenance">
                <label>تجهیز *
                    <select name="equipment_id" required>
                        <option value="0">— انتخاب تجهیز —</option>
                        <?php foreach ($equipmentList as $eq): ?>
                        <option value="<?= (int) $eq['id'] ?>" <?= $maintEquipmentId === (int) $eq['id'] ? 'selected' : '' ?>><?= e($eq['name']) ?><?= ($eq['status'] ?? '') === 'retired' ? ' (اسقاط)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="inline-fields">
                    <label>تاریخ
                        <input type="date" name="maint_date" value="<?= e(date('Y-m-d')) ?>" dir="ltr">
                    </label>
                    <label>نوع
                        <select name="maint_type">
                            <?php foreach (maintenance_types() as $tk => $tt): ?>
                            <option value="<?= e($tk) ?>"><?= e($tt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>هزینه (تومان)
                        <input type="number" name="cost" min="0" step="1" value="0">
                    </label>
                </div>
                <label>شرح
                    <textarea name="description" rows="3" placeholder="مثلاً تعویض تیغ اره، سرویس دوره‌ای سه‌ماهه…"></textarea>
                </label>
                <button type="submit" class="btn add">ثبت تعمیر</button>
            </form>
            </div>

            <?php if ($maintEquipment !== null): ?>
            <h2>تعمیرات «<?= e($maintEquipment['name']) ?>» — جمع: <?= e(format_price($maintTotals[(int) $maintEquipment['id']] ?? 0)) ?> تومان</h2>
            <?php else: ?>
            <h2>همه رکوردها (<?= count($maintenanceList) ?>)</h2>
            <?php endif; ?>

            <?php if ($maintenanceList === []): ?>
                <div class="card wide"><p class="muted">رکوردی ثبت نشده است. با دکمه «+ ثبت سرویس / تعمیر» اولین رکورد را بسازید.</p></div>
            <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>تجهیز</th><th>تاریخ</th><th>نوع</th><th>هزینه</th><th>شرح</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($maintenanceList as $m): ?>
                    <tr>
                        <td><?= e($m['equipment_name']) ?></td>
                        <td dir="ltr"><?= e($m['maint_date']) ?></td>
                        <td><?= e(maintenance_type_label((string) ($m['maint_type'] ?? ''))) ?></td>
                        <td><?= e(format_price($m['cost'])) ?> تومان</td>
                        <td><?= $m['description'] !== '' ? e($m['description']) : '<span class="muted">—</span>' ?></td>
                        <td class="row-actions">
                            <form method="post" class="inline" onsubmit="return confirm('این رکورد حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_maintenance"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>

            <?php if ($maintEquipment === null && $maintTotals !== []): ?>
            <h2>جمع هزینه تعمیرات هر تجهیز</h2>
            <div class="table-wrap"><table>
                <thead><tr><th>تجهیز</th><th>جمع هزینه تعمیرات</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($equipmentList as $eq):
                    $eid = (int) $eq['id'];
                    if (!isset($maintTotals[$eid])) {
                        continue;
                    }
                ?>
                    <tr>
                        <td><?= e($eq['name']) ?></td>
                        <td><strong><?= e(format_price($maintTotals[$eid])) ?></strong> تومان</td>
                        <td><a class="btn small" href="admin.php?page=assets_maintenance&equipment_id=<?= $eid ?>">مشاهده</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?php endif; ?>
    <?php
}
