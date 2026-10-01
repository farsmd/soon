<?php
// admin_orders.php — بخش «سفارش‌ها» پنل مدیریت (فاز ۳ / نسخه ۸).
// ثبت سفارش همکار/مشتری با ردیف‌های (محصول + طول + تعداد)، موتور قیمت پلکانی،
// وضعیت‌ها، تاریخچه، پیش‌فاکتور چاپی، قوانین قیمت‌گذاری و انبار پرتی.
// فقط از admin.php و بعد از احراز هویت صدا زده می‌شود (گارد CMS_ADMIN_PANEL).

declare(strict_types=1);

// دسترسی مستقیم ممنوع
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به سفارش‌ها */
function orders_post_actions(): array
{
    return [
        'save_order_settings',
        'add_tier', 'update_tier', 'delete_tier', 'move_tier',
        'add_ostatus', 'update_ostatus', 'delete_ostatus', 'move_ostatus', 'toggle_ostatus',
        'add_order', 'set_order_status', 'delete_order', 'update_order_note',
        'add_remnant', 'delete_remnant',
    ];
}

/** برچسب فارسی منبع سفارش */
function order_source_label(string $s): string
{
    return ['admin' => 'پنل', 'site' => 'سایت'][$s] ?? $s;
}

/** پردازش اکشن‌های POST سفارش‌ها */
function orders_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
        // ---------- تنظیمات عددی/متنی سفارش ----------
        case 'save_order_settings': {
            $nums = [
                'partner_min_bars' => ['min' => 0, 'max' => 1000, 'float' => false],
                'bar_length_m'     => ['min' => 0.1, 'max' => 100, 'float' => true],
                'min_billable_m'   => ['min' => 0, 'max' => 10, 'float' => true],
                'wire_default_cm'  => ['min' => 0, 'max' => 500, 'float' => false],
                'wire_step_cm'     => ['min' => 1, 'max' => 100, 'float' => false],
                'wire_price_per_step' => ['min' => 0, 'max' => 100000000, 'float' => false],
                'wire_max_cm'      => ['min' => 0, 'max' => 1000, 'float' => false],
                'remnant_min_cm'   => ['min' => 0, 'max' => 500, 'float' => false],
                'default_prep_days' => ['min' => 0, 'max' => 365, 'float' => false],
                'deposit_percent'  => ['min' => 0, 'max' => 100, 'float' => false],
                'next_order_no'    => ['min' => 1, 'max' => 1000000000, 'float' => false],
            ];
            // سازگاری طول پیش‌فرض و سقف سیم — قبل از ذخیره بررسی می‌شود تا مقدار ناسازگار ذخیره نشود
            $newDef = trim((string) ($_POST['wire_default_cm'] ?? '')) !== '' ? (int) $_POST['wire_default_cm'] : (int) order_setting('wire_default_cm', 20);
            $newMax = trim((string) ($_POST['wire_max_cm'] ?? '')) !== '' ? (int) $_POST['wire_max_cm'] : (int) order_setting('wire_max_cm', 100);
            if ($newDef > $newMax) {
                throw new RuntimeException('طول پیش‌فرض سیم نمی‌تواند از حداکثر طول سیم بیشتر باشد؛ اول حداکثر را بالا ببرید.');
            }
            foreach ($nums as $k => $rule) {
                $raw = trim((string) ($_POST[$k] ?? ''));
                if ($raw === '') {
                    continue;
                }
                $v = $rule['float'] ? (float) $raw : (int) $raw;
                if (!is_numeric($raw) || $v < $rule['min'] || $v > $rule['max']) {
                    throw new RuntimeException('مقدار «' . $k . '» معتبر نیست.');
                }
                set_setting($k, (string) $v);
            }
            foreach (['payment_terms', 'warranty_text', 'qc_text'] as $k) {
                set_setting($k, trim((string) ($_POST[$k] ?? '')));
            }
            set_setting('orders_public', isset($_POST['orders_public']) ? '1' : '0');
            set_setting('order_line_note', isset($_POST['order_line_note']) ? '1' : '0');
            $emp = (string) ($_POST['enforce_min_partner'] ?? 'warn');
            set_setting('enforce_min_partner', in_array($emp, ['warn', 'block'], true) ? $emp : 'warn');
            flash('ok', 'تنظیمات سفارش ذخیره شد.');
            redirect_admin('admin.php?page=order_rules');
            // no break
        }

        // ---------- پلکان‌های تخفیف ----------
        case 'add_tier':
        case 'update_tier': {
            $id = (int) ($_POST['id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $minM = (float) ($_POST['min_meters'] ?? 0);
            $maxRaw = trim((string) ($_POST['max_meters'] ?? ''));
            $maxM = $maxRaw === '' ? null : (float) $maxRaw;
            $pct = (float) ($_POST['discount_percent'] ?? 0);
            $applies = (string) ($_POST['applies_to'] ?? 'partner');
            if ($title === '') {
                throw new RuntimeException('عنوان پلکان را وارد کنید.');
            }
            if ($minM < 0 || $pct < 0 || $pct > 100 || ($maxM !== null && $maxM <= $minM)) {
                throw new RuntimeException('بازه یا درصد تخفیف معتبر نیست.');
            }
            if (!in_array($applies, ['partner', 'all'], true)) {
                $applies = 'partner';
            }
            $data = [
                ':t' => $title, ':min' => $minM, ':max' => $maxM, ':pct' => $pct,
                ':ap' => $applies, ':act' => isset($_POST['is_active']) ? 1 : 0,
                ':sort' => (int) ($_POST['sort_order'] ?? 0),
            ];
            if ($action === 'update_tier' && $id > 0) {
                $data[':id'] = $id;
                $pdo->prepare('UPDATE price_tiers SET title = :t, min_meters = :min, max_meters = :max, discount_percent = :pct, applies_to = :ap, is_active = :act, sort_order = :sort WHERE id = :id')->execute($data);
                flash('ok', 'پلکان به‌روزرسانی شد.');
            } else {
                $pdo->prepare('INSERT INTO price_tiers (title, min_meters, max_meters, discount_percent, applies_to, is_active, sort_order) VALUES (:t, :min, :max, :pct, :ap, :act, :sort)')->execute($data);
                flash('ok', 'پلکان تازه اضافه شد.');
            }
            redirect_admin('admin.php?page=order_rules');
            // no break
        }
        case 'delete_tier': {
            $pdo->prepare('DELETE FROM price_tiers WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
            flash('ok', 'پلکان حذف شد.');
            redirect_admin('admin.php?page=order_rules');
            // no break
        }
        case 'move_tier':
            move_row($pdo, 'price_tiers', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
            redirect_admin('admin.php?page=order_rules');
            // no break

        // ---------- وضعیت‌های سفارش ----------
        case 'add_ostatus':
        case 'update_ostatus': {
            $id = (int) ($_POST['id'] ?? 0);
            $key = trim((string) ($_POST['status_key'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? ''));
            $color = trim((string) ($_POST['color'] ?? '#6b7280'));
            if (!preg_match('/^[a-z0-9_]{2,30}$/', $key)) {
                throw new RuntimeException('کلید وضعیت باید انگلیسی و بدون فاصله باشد (مثلاً ready_to_ship).');
            }
            if ($title === '') {
                throw new RuntimeException('عنوان وضعیت را وارد کنید.');
            }
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                $color = '#6b7280';
            }
            $data = [
                ':k' => $key, ':t' => $title, ':c' => $color,
                ':act' => isset($_POST['is_active']) ? 1 : 0,
                ':sort' => (int) ($_POST['sort_order'] ?? 0),
            ];
            if ($action === 'update_ostatus' && $id > 0) {
                $data[':id'] = $id;
                $pdo->prepare('UPDATE order_statuses SET status_key = :k, title = :t, color = :c, is_active = :act, sort_order = :sort WHERE id = :id')->execute($data);
                flash('ok', 'وضعیت به‌روزرسانی شد.');
            } else {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM order_statuses WHERE status_key = :k');
                $chk->execute([':k' => $key]);
                if ((int) $chk->fetchColumn() > 0) {
                    throw new RuntimeException('این کلید وضعیت قبلاً استفاده شده است.');
                }
                $pdo->prepare('INSERT INTO order_statuses (status_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, :act, :sort)')->execute($data);
                flash('ok', 'وضعیت تازه اضافه شد.');
            }
            redirect_admin('admin.php?page=order_rules');
            // no break
        }
        case 'delete_ostatus': {
            $id = (int) ($_POST['id'] ?? 0);
            $row = $pdo->query('SELECT status_key FROM order_statuses WHERE id = ' . $id)->fetch();
            if ($row === false) {
                throw new RuntimeException('وضعیت پیدا نشد.');
            }
            $used = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = " . $pdo->quote((string) $row['status_key']))->fetchColumn();
            if ($used > 0) {
                throw new RuntimeException('این وضعیت در ' . $used . ' سفارش استفاده شده و قابل حذف نیست؛ آن را غیرفعال کنید.');
            }
            $pdo->prepare('DELETE FROM order_statuses WHERE id = :id')->execute([':id' => $id]);
            flash('ok', 'وضعیت حذف شد.');
            redirect_admin('admin.php?page=order_rules');
            // no break
        }
        case 'move_ostatus':
            move_row($pdo, 'order_statuses', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? 'up'));
            redirect_admin('admin.php?page=order_rules');
            // no break
        case 'toggle_ostatus':
            $pdo->prepare('UPDATE order_statuses SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
            redirect_admin('admin.php?page=order_rules');
            // no break

        // ---------- ثبت سفارش تازه ----------
        case 'add_order': {
            $customerId = (int) ($_POST['customer_id'] ?? 0);
            $customer = $customerId > 0 ? get_customer($customerId) : null;
            if ($customer === null) {
                // افزودن سریع مشتری
                $nm = trim((string) ($_POST['new_name'] ?? ''));
                $mm = preg_replace('/\D+/', '', (string) ($_POST['new_mobile'] ?? ''));
                $nt = (string) ($_POST['new_type'] ?? 'retail');
                if ($nm === '' || $mm === '') {
                    throw new RuntimeException('مشتری را انتخاب کنید یا نام و موبایل مشتری تازه را وارد کنید.');
                }
                if (!in_array($nt, ['partner', 'retail', 'company'], true)) {
                    $nt = 'retail';
                }
                $ex = $pdo->prepare('SELECT * FROM customers WHERE mobile = :m LIMIT 1');
                $ex->execute([':m' => $mm]);
                $customer = $ex->fetch();
                if ($customer === false) {
                    $pdo->prepare('INSERT INTO customers (full_name, mobile, customer_type) VALUES (:n, :m, :t)')
                        ->execute([':n' => $nm, ':m' => $mm, ':t' => $nt]);
                    $customer = get_customer((int) $pdo->lastInsertId());
                }
            }
            $rawItems = $_POST['items'] ?? [];
            if (!is_array($rawItems) || $rawItems === []) {
                throw new RuntimeException('دست‌کم یک ردیف سفارش اضافه کنید.');
            }
            $isPartner = ((string) ($customer['customer_type'] ?? '')) === 'partner';
            $lines = [];
            $prepDays = (int) order_setting('default_prep_days', 3);
            foreach ($rawItems as $ri) {
                if (!is_array($ri)) {
                    continue;
                }
                $pid = (int) ($ri['product_id'] ?? 0);
                $product = get_product($pid);
                if ($product === null || (int) ($product['is_active'] ?? 0) !== 1) {
                    throw new RuntimeException('یک ردیف، محصول معتبر ندارد.');
                }
                $len = round((float) ($ri['length_cm'] ?? 0), 1);
                $qty = max(1, (int) ($ri['qty'] ?? 1));
                if ($len <= 0) {
                    throw new RuntimeException('طول ردیف «' . (string) $product['name'] . '» باید بیشتر از صفر باشد.');
                }
                $wire = (float) ($ri['wire_cm'] ?? order_setting('wire_default_cm', 20));
                $step = (int) order_setting('wire_step_cm', 5);
                if ($wire < 0 || ($step > 0 && abs($wire / $step - round($wire / $step)) > 0.0001)) {
                    throw new RuntimeException('طول سیم ردیف «' . (string) $product['name'] . '» باید مضربی از ' . $step . ' سانت باشد.');
                }
                $wireMax = (int) order_setting('wire_max_cm', 100);
                if ($wire > $wireMax) {
                    throw new RuntimeException('طول سیم ردیف «' . (string) $product['name'] . '» نمی‌تواند بیشتر از ' . $wireMax . ' سانت باشد (از «قوانین قیمت‌گذاری» قابل‌تغییر است).');
                }
                $opts = [];
                $rawOpts = $ri['options'] ?? [];
                if (is_array($rawOpts)) {
                    foreach ($rawOpts as $aidRaw => $oidRaw) {
                        $oid = (int) $oidRaw;
                        if ($oid > 0 && get_attribute_option($oid) !== null) {
                            $opts[(int) $aidRaw] = $oid;
                        }
                    }
                }
                $lines[] = [
                    'product_id' => $pid,
                    'length_cm' => $len,
                    'qty' => $qty,
                    'wire_length_cm' => $wire,
                    'has_endcap' => !empty($ri['endcap']),
                    'note' => mb_substr(trim((string) ($ri['note'] ?? '')), 0, 500),
                    'options' => $opts,
                ];
                $pp = (int) ($product['prep_days'] ?? 0);
                if ($pp > $prepDays) {
                    $prepDays = $pp;
                }
            }
            if ($lines === []) {
                throw new RuntimeException('ردیف معتبری برای سفارش پیدا نشد.');
            }
            $tot = compute_order_totals($lines, $isPartner);
            // حداقل سفارش همکار
            $minMeters = (float) order_setting('partner_min_bars', 3) * (float) order_setting('bar_length_m', 3);
            $warnNote = '';
            if ($isPartner && $tot['total_meters'] < $minMeters) {
                $msg = 'متراژ کل سفارش (' . format_qty($tot['total_meters']) . ' متر) از حداقل سفارش همکار (' . format_qty($minMeters) . ' متر) کمتر است.';
                if (order_setting('enforce_min_partner', 'warn') === 'block') {
                    throw new RuntimeException($msg . ' ثبت سفارش مجاز نیست.');
                }
                $warnNote = 'هشدار حداقل سفارش همکار: ' . $msg;
            }
            $notes = trim((string) ($_POST['notes'] ?? ''));
            if ($warnNote !== '') {
                $notes = ($notes !== '' ? $notes . "\n" : '') . $warnNote;
            }
            $pdo->beginTransaction();
            try {
                $orderNo = (int) order_setting('next_order_no', 1001);
                $ins = $pdo->prepare('INSERT INTO orders (order_no, customer_id, customer_type, source, status, subtotal, discount_percent, discount_amount, total, total_meters, total_fixtures, prep_days, notes, created_by)
                    VALUES (:no, :cid, :ct, :src, :st, :sub, :dp, :da, :tot, :m, :f, :prep, :notes, :by)');
                $ins->execute([
                    ':no' => $orderNo,
                    ':cid' => (int) $customer['id'],
                    ':ct' => (string) $customer['customer_type'],
                    ':src' => 'admin',
                    ':st' => 'new',
                    ':sub' => $tot['subtotal'],
                    ':dp' => $tot['discount_percent'],
                    ':da' => $tot['discount_amount'],
                    ':tot' => $tot['total'],
                    ':m' => $tot['total_meters'],
                    ':f' => $tot['total_fixtures'],
                    ':prep' => $prepDays,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':by' => 'admin',
                ]);
                $oid = (int) $pdo->lastInsertId();
                $insItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, length_cm, qty, billable_m, unit_price_per_m, options_json, options_extra_per_m, wire_length_cm, wire_steps, wire_extra_total, has_endcap, note, line_subtotal, line_total, sort_order)
                    VALUES (:o, :p, :pn, :len, :q, :bm, :up, :oj, :oe, :w, :ws, :we, :ec, :note, :ls, :lt, :s)');
                $so = 0;
                foreach ($tot['lines'] as $li => $tl) {
                    $src = $lines[$li];
                    $prod = get_product((int) $src['product_id']);
                    $optSnap = [];
                    foreach (($src['options'] ?? []) as $aid => $opid) {
                        $a = get_attribute((int) $aid);
                        $o = get_attribute_option((int) $opid);
                        if ($a !== null && $o !== null) {
                            $optSnap[] = ['attr' => (string) $a['title'], 'option' => (string) $o['title'], 'delta' => (float) ($o['price_delta_per_meter'] ?? 0)];
                        }
                    }
                    $so += 10;
                    $insItem->execute([
                        ':o' => $oid, ':p' => (int) $src['product_id'], ':pn' => (string) ($prod['name'] ?? ''),
                        ':len' => $src['length_cm'], ':q' => $src['qty'], ':bm' => $tl['billable_m'],
                        ':up' => $tl['unit_price_per_m'], ':oj' => $optSnap === [] ? null : json_encode($optSnap, JSON_UNESCAPED_UNICODE),
                        ':oe' => $tl['options_extra_per_m'], ':w' => $src['wire_length_cm'], ':ws' => $tl['wire_steps'],
                        ':we' => $tl['wire_extra_total'], ':ec' => $src['has_endcap'] ? 1 : 0,
                        ':note' => ($tl['note'] ?? '') !== '' ? $tl['note'] : null,
                        ':ls' => $tl['line_subtotal'], ':lt' => $tl['line_total'], ':s' => $so,
                    ]);
                }
                $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, NULL, :t, :n)')
                    ->execute([':o' => $oid, ':t' => 'new', ':n' => 'سفارش ثبت شد.']);
                set_setting('next_order_no', (string) ($orderNo + 1));
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            $msg = 'سفارش شماره ' . $orderNo . ' ثبت شد (مبلغ: ' . format_price($tot['total']) . ' تومان).';
            if ($warnNote !== '') {
                $msg .= ' ' . $warnNote;
            }
            flash('ok', $msg);
            redirect_admin('admin.php?page=order_view&id=' . $oid);
            // no break
        }

        // ---------- تغییر وضعیت ----------
        case 'set_order_status': {
            $oid = (int) ($_POST['id'] ?? 0);
            $to = (string) ($_POST['to_status'] ?? '');
            $order = order_get($oid);
            if ($order === null) {
                throw new RuntimeException('سفارش پیدا نشد.');
            }
            $valid = false;
            foreach (order_statuses(true) as $s) {
                if ($s['status_key'] === $to) {
                    $valid = true;
                    break;
                }
            }
            if (!$valid) {
                throw new RuntimeException('وضعیت مقصد معتبر نیست.');
            }
            $note = trim((string) ($_POST['note'] ?? ''));
            $pdo->prepare('UPDATE orders SET status = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':s' => $to, ':id' => $oid]);
            $pdo->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, :f, :t, :n)')
                ->execute([':o' => $oid, ':f' => (string) $order['status'], ':t' => $to, ':n' => $note !== '' ? $note : null]);
            flash('ok', 'وضعیت سفارش به «' . order_status_title($to) . '» تغییر کرد.');
            redirect_admin('admin.php?page=order_view&id=' . $oid);
            // no break
        }

        case 'update_order_note': {
            $oid = (int) ($_POST['id'] ?? 0);
            if (order_get($oid) === null) {
                throw new RuntimeException('سفارش پیدا نشد.');
            }
            $pdo->prepare('UPDATE orders SET notes = :n, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute([':n' => trim((string) ($_POST['notes'] ?? '')) ?: null, ':id' => $oid]);
            flash('ok', 'توضیحات سفارش ذخیره شد.');
            redirect_admin('admin.php?page=order_view&id=' . $oid);
            // no break
        }

        case 'delete_order': {
            $oid = (int) ($_POST['id'] ?? 0);
            $order = order_get($oid);
            if ($order === null) {
                throw new RuntimeException('سفارش پیدا نشد.');
            }
            if (!in_array((string) $order['status'], ['new', 'cancelled'], true)) {
                throw new RuntimeException('فقط سفارش‌های «جدید» یا «لغوشده» قابل حذف‌اند.');
            }
            $pdo->beginTransaction();
            try {
                $pdo->prepare('DELETE FROM order_status_history WHERE order_id = :o')->execute([':o' => $oid]);
                $pdo->prepare('DELETE FROM order_items WHERE order_id = :o')->execute([':o' => $oid]);
                $pdo->prepare('DELETE FROM orders WHERE id = :o')->execute([':o' => $oid]);
                $pdo->commit();
            } catch (Throwable $ex) {
                $pdo->rollBack();
                throw $ex;
            }
            flash('ok', 'سفارش حذف شد.');
            redirect_admin('admin.php?page=orders');
            // no break
        }

        // ---------- انبار پرتی (ثبت دستی؛ مصرف خودکار در فاز تولید) ----------
        case 'add_remnant': {
            $mid = (int) ($_POST['material_id'] ?? 0);
            $mat = get_material($mid);
            if ($mat === null) {
                throw new RuntimeException('ماده اولیه را انتخاب کنید.');
            }
            $len = round((float) ($_POST['length_cm'] ?? 0), 1);
            $qty = max(1, (int) ($_POST['qty'] ?? 1));
            if ($len <= 0) {
                throw new RuntimeException('طول پرت باید بیشتر از صفر باشد.');
            }
            $pdo->prepare('INSERT INTO material_remnants (material_id, length_cm, qty, source, note) VALUES (:m, :l, :q, :s, :n)')
                ->execute([
                    ':m' => $mid, ':l' => $len, ':q' => $qty, ':s' => 'manual',
                    ':n' => trim((string) ($_POST['note'] ?? '')) ?: null,
                ]);
            flash('ok', 'پرت به انبار پرتی اضافه شد.');
            redirect_admin('admin.php?page=remnants');
            // no break
        }
        case 'delete_remnant':
            $pdo->prepare('DELETE FROM material_remnants WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
            flash('ok', 'پرت حذف شد.');
            redirect_admin('admin.php?page=remnants');
            // no break
    }
}

/** خواندن یک سفارش با نام مشتری */
function order_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT o.*, c.full_name AS customer_name, c.mobile AS customer_mobile, c.company AS customer_company
        FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

/** ردیف‌های یک سفارش */
function order_items(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :o ORDER BY sort_order ASC, id ASC');
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

/** تاریخچه وضعیت یک سفارش */
function order_history(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_status_history WHERE order_id = :o ORDER BY id ASC');
    $stmt->execute([':o' => $orderId]);
    return $stmt->fetchAll();
}

/** بارگذاری داده‌های صفحه‌های سفارش */
function orders_load_data(string $page): array
{
    $d = [
        'ordersList' => [],
        'orderStatusFilter' => '',
        'orderStatusCounts' => [],
        'statusesList' => order_statuses(false),
        'newOrderProducts' => [],
        'newOrderProductsJs' => '[]',
        'newOrderCustomers' => [],
        'viewOrder' => null,
        'viewItems' => [],
        'viewHistory' => [],
        'viewMaterials' => [],
        'viewPrepDate' => '',
        'editTier' => null,
        'editStatus' => null,
        'remnantsList' => [],
        'remnantMaterials' => [],
        'orderSettings' => [
            'partner_min_bars' => order_setting('partner_min_bars', 3),
            'bar_length_m' => order_setting('bar_length_m', 3),
            'min_billable_m' => order_setting('min_billable_m', 0.5),
            'wire_default_cm' => order_setting('wire_default_cm', 20),
            'wire_step_cm' => order_setting('wire_step_cm', 5),
            'wire_price_per_step' => order_setting('wire_price_per_step', 0),
            'wire_max_cm' => order_setting('wire_max_cm', 100),
            'order_line_note' => order_setting('order_line_note', '1'),
            'remnant_min_cm' => order_setting('remnant_min_cm', 20),
            'default_prep_days' => order_setting('default_prep_days', 3),
            'deposit_percent' => order_setting('deposit_percent', 50),
            'orders_public' => order_setting('orders_public', '1'),
            'enforce_min_partner' => order_setting('enforce_min_partner', 'warn'),
            'next_order_no' => order_setting('next_order_no', 1001),
            'payment_terms' => order_setting('payment_terms', ''),
            'warranty_text' => order_setting('warranty_text', ''),
            'qc_text' => order_setting('qc_text', ''),
        ],
    ];
    if ($page === 'orders') {
        $f = trim((string) ($_GET['status'] ?? ''));
        $d['orderStatusFilter'] = $f;
        $sql = 'SELECT o.*, c.full_name AS customer_name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id';
        $params = [];
        if ($f !== '') {
            $sql .= ' WHERE o.status = :s';
            $params[':s'] = $f;
        }
        $sql .= ' ORDER BY o.id DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $d['ordersList'] = $stmt->fetchAll();
        foreach (db()->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status')->fetchAll() as $r) {
            $d['orderStatusCounts'][(string) $r['status']] = (int) $r['c'];
        }
    }
    if ($page === 'order_new') {
        $d['newOrderCustomers'] = get_customers('', '');
        $prods = get_products(false);
        $js = [];
        foreach ($prods as $p) {
            $pid = (int) $p['id'];
            $offered = product_offered_attributes($pid);
            $optGroups = [];
            foreach ($offered as $item) {
                if ($item['default_option_id'] === null) {
                    continue;
                }
                $opts = [];
                foreach ($item['options'] as $o) {
                    $opts[] = ['id' => (int) $o['id'], 'title' => (string) $o['title'], 'delta' => (float) ($o['price_delta_per_meter'] ?? 0)];
                }
                $optGroups[] = [
                    'attr_id' => (int) $item['attribute']['id'],
                    'attr_title' => (string) $item['attribute']['title'],
                    'default' => (int) $item['default_option_id'],
                    'options' => $opts,
                ];
            }
            $js[$pid] = [
                'name' => (string) $p['name'],
                'retail' => (float) product_base_price_per_meter($p, false),
                'partner' => (float) product_base_price_per_meter($p, true),
                'optgroups' => $optGroups,
            ];
        }
        $d['newOrderProducts'] = $prods;
        $d['newOrderProductsJs'] = json_encode($js, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    if ($page === 'order_view' && isset($_GET['id'])) {
        $o = order_get((int) $_GET['id']);
        if ($o !== null) {
            $d['viewOrder'] = $o;
            $d['viewItems'] = order_items((int) $o['id']);
            $d['viewHistory'] = order_history((int) $o['id']);
            // نیاز مواد سفارش (با طول واقعی؛ درپوش فقط برای ردیف‌های درپوش‌دار)
            $orderLines = [];
            foreach ($d['viewItems'] as $it) {
                $orderLines[] = [
                    'product_id' => (int) $it['product_id'],
                    'length_cm' => (float) $it['length_cm'],
                    'qty' => (int) $it['qty'],
                    'has_endcap' => (int) $it['has_endcap'] === 1,
                ];
            }
            $d['viewMaterials'] = order_required_materials($orderLines);
            $prep = (int) ($o['prep_days'] ?? 0);
            $ts = strtotime((string) ($o['created_at'] ?? 'now'));
            $d['viewPrepDate'] = $ts !== false && $prep > 0 ? date('Y/m/d', $ts + $prep * 86400) : '—';
        }
    }
    if ($page === 'order_rules') {
        if (isset($_GET['edit_tier'])) {
            $stmt = db()->prepare('SELECT * FROM price_tiers WHERE id = :id');
            $stmt->execute([':id' => (int) $_GET['edit_tier']]);
            $r = $stmt->fetch();
            $d['editTier'] = $r === false ? null : $r;
        }
        if (isset($_GET['edit_status'])) {
            $stmt = db()->prepare('SELECT * FROM order_statuses WHERE id = :id');
            $stmt->execute([':id' => (int) $_GET['edit_status']]);
            $r = $stmt->fetch();
            $d['editStatus'] = $r === false ? null : $r;
        }
    }
    if ($page === 'remnants') {
        $stmt = db()->query('SELECT r.*, m.name AS material_name, m.unit AS material_unit FROM material_remnants r JOIN materials m ON m.id = r.material_id ORDER BY r.id DESC LIMIT 300');
        $d['remnantsList'] = $stmt->fetchAll();
        $d['remnantMaterials'] = get_materials(false);
    }
    return $d;
}

/** رندر فهرست سفارش‌ها */
function orders_render_list(array $d): void
{
    extract($d);
    ?>
    <h1>سفارش‌ها</h1>
    <p class="muted">ثبت سفارش همکار/مشتری با طول دقیق هر چراغ، تخفیف پلکانی و پیش‌فاکتور چاپی.</p>
    <p><a class="btn primary" href="admin.php?page=order_new">+ سفارش تازه</a></p>
    <div class="chips" style="margin:10px 0">
        <a class="chip<?= $orderStatusFilter === '' ? ' active' : '' ?>" href="admin.php?page=orders">همه</a>
        <?php foreach ($statusesList as $s): ?>
            <a class="chip<?= $orderStatusFilter === $s['status_key'] ? ' active' : '' ?>" href="admin.php?page=orders&status=<?= e($s['status_key']) ?>">
                <span class="dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['title']) ?>
                (<?= (int) ($orderStatusCounts[$s['status_key']] ?? 0) ?>)
            </a>
        <?php endforeach; ?>
    </div>
    <?php if ($ordersList === []): ?>
        <div class="card wide"><p class="muted">هنوز سفارشی ثبت نشده است.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>شماره</th><th>مشتری</th><th>نوع</th><th>متراژ</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($ordersList as $o): ?>
            <tr>
                <td><strong>#<?= (int) $o['order_no'] ?></strong><?php if (($o['source'] ?? '') === 'site'): ?> <span class="badge">سایت</span><?php endif; ?></td>
                <td><?= e($o['customer_name'] ?? '—') ?></td>
                <td><?= e(customer_type_label((string) ($o['customer_type'] ?? ''))) ?></td>
                <td><?= e(format_qty((float) ($o['total_meters'] ?? 0))) ?> متر</td>
                <td><?= e(format_price($o['total'] ?? 0)) ?></td>
                <td><span class="badge" style="background:<?= e(order_status_color((string) $o['status'])) ?>22;color:<?= e(order_status_color((string) $o['status'])) ?>"><?= e(order_status_title((string) $o['status'])) ?></span></td>
                <td class="muted"><?= e(mb_substr((string) ($o['created_at'] ?? ''), 0, 10)) ?></td>
                <td class="actions"><a class="btn small" href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>">جزئیات</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php
}

/** رندر فرم «سفارش تازه» */
function orders_render_new(array $d): void
{
    extract($d);
    $s = $orderSettings;
    $tiers = price_tiers(true);
    $tiersJs = [];
    foreach (price_tiers(false) as $t) {
        $tiersJs[] = ['min' => (float) $t['min_meters'], 'max' => $t['max_meters'] === null ? null : (float) $t['max_meters'], 'pct' => (float) $t['discount_percent'], 'applies' => (string) $t['applies_to']];
    }
    ?>
    <h1>سفارش تازه</h1>
    <p class="muted">مشتری را انتخاب کنید (یا سریع بسازید)، بعد برای هر چراغ: محصول + طول به سانتی‌متر (یک رقم اعشار) + تعداد. حداقل طول قابل‌صورتحساب <?= e(format_qty((float) $s['min_billable_m'])) ?> متر است.</p>
    <form method="post" class="card wide" id="order-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_order">
        <div class="inline-fields">
            <label>مشتری (موجود)
                <select name="customer_id" id="of-customer">
                    <option value="0">— مشتری تازه (فرم سریع) —</option>
                    <?php foreach ($newOrderCustomers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-type="<?= e($c['customer_type']) ?>"><?= e($c['full_name']) ?> — <?= e(customer_type_label((string) $c['customer_type'])) ?> <span dir="ltr"><?= e($c['mobile']) ?></span></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <div id="of-quick" class="card" style="background:#f9fafb;margin:10px 0">
            <strong>مشتری تازه (سریع)</strong>
            <div class="inline-fields">
                <label>نام و نام خانوادگی *<input type="text" name="new_name" id="of-new-name"></label>
                <label>موبایل *<input type="text" name="new_mobile" id="of-new-mobile" dir="ltr" inputmode="numeric"></label>
                <label>نوع
                    <select name="new_type" id="of-new-type">
                        <?php foreach (customer_types() as $k => $lbl): ?>
                            <option value="<?= e($k) ?>"><?= e($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
        <h3>ردیف‌های سفارش</h3>
        <div id="order-rows"></div>
        <p><button type="button" class="btn small" id="or-add">+ افزودن ردیف</button></p>
        <div class="card" style="background:#f0fdf4;margin:10px 0" id="or-preview">
            <strong>پیش‌نمایش مبلغ</strong>
            <div id="or-preview-body" class="muted">ردیفی اضافه کنید…</div>
        </div>
        <label>توضیحات سفارش
            <textarea name="notes" rows="2" placeholder="توضیح آزاد سفارش…"></textarea>
        </label>
        <button type="submit" class="btn primary">ثبت سفارش</button>
        <a class="btn" href="admin.php?page=orders">انصراف</a>
    </form>
    <script>
    (function(){
        var PRODUCTS = <?= $newOrderProductsJs ?>;
        var TIERS = <?= json_encode($tiersJs, JSON_UNESCAPED_UNICODE) ?>;
        var MIN_BILL = <?= json_encode((float) $s['min_billable_m']) ?>;
        var WIRE_STEP = <?= json_encode((int) $s['wire_step_cm']) ?>;
        var WIRE_DEFAULT = <?= json_encode((int) $s['wire_default_cm']) ?>;
        var WIRE_DEF = <?= json_encode((int) $s['wire_default_cm']) ?>;
        var WIRE_PRICE = <?= json_encode((int) $s['wire_price_per_step']) ?>;
        var WIRE_MAX = <?= json_encode((int) $s['wire_max_cm']) ?>;
        var LINE_NOTE = <?= json_encode((string) $s['order_line_note'] === '1') ?>;
        var MIN_METERS = <?= json_encode((float) $s['partner_min_bars'] * (float) $s['bar_length_m']) ?>;
        var box = document.getElementById('order-rows');
        var addBtn = document.getElementById('or-add');
        var prevBody = document.getElementById('or-preview-body');
        var custSel = document.getElementById('of-customer');
        var newType = document.getElementById('of-new-type');
        var idx = 0;
        function fmt(n){ return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
        function isPartner(){
            if (custSel.value !== '0') {
                var o = custSel.options[custSel.selectedIndex];
                return o && o.getAttribute('data-type') === 'partner';
            }
            return newType.value === 'partner';
        }
        function tierPct(meters, partner){
            var best = 0, bestMin = -1;
            TIERS.forEach(function(t){
                if (t.applies === 'partner' && !partner) return;
                var hit = meters >= t.min && (t.max === null ? meters > t.min : meters <= t.max);
                if (hit && t.min >= bestMin) { bestMin = t.min; best = t.pct; }
            });
            return best;
        }
        function wireOptions(sel){
            var h = '';
            for (var w = 0; w <= WIRE_MAX; w += WIRE_STEP) {
                h += '<option value="' + w + '"' + (w === WIRE_DEF ? ' selected' : '') + '>' + w + ' سانت</option>';
            }
            sel.innerHTML = h;
        }
        function productOptions(pid){
            var p = PRODUCTS[pid];
            var h = '';
            if (p && p.optgroups) {
                p.optgroups.forEach(function(g){
                    h += '<label style="min-width:140px">' + g.attr_title + '<select name="items[' + cur + '][options][' + g.attr_id + ']">';
                    g.options.forEach(function(o){
                        h += '<option value="' + o.id + '"' + (o.id === g.default ? ' selected' : '') + ' data-delta="' + o.delta + '">' + o.title + '</option>';
                    });
                    h += '</select></label>';
                });
            }
            return h;
        }
        var cur = 0;
        function addRow(){
            cur = idx++;
            var div = document.createElement('div');
            div.className = 'order-row card';
            div.style.cssText = 'background:#f9fafb;margin:8px 0;padding:10px';
            div.setAttribute('data-idx', cur);
            var ph = '';
            ph += '<div class="inline-fields">';
            ph += '<label style="flex:2;min-width:180px">محصول<select name="items[' + cur + '][product_id]" class="or-product">';
            ph += '<option value="0">— انتخاب محصول —</option>';
            for (var pid in PRODUCTS) {
                ph += '<option value="' + pid + '">' + PRODUCTS[pid].name + '</option>';
            }
            ph += '</select></label>';
            ph += '<label>طول هر چراغ (سانتی‌متر)<input type="number" name="items[' + cur + '][length_cm]" class="or-len" step="0.1" min="0.1" placeholder="مثلاً 120.5"></label>';
            ph += '<label>تعداد<input type="number" name="items[' + cur + '][qty]" class="or-qty" min="1" value="1"></label>';
            ph += '<label>سیم هر چراغ<select name="items[' + cur + '][wire_cm]" class="or-wire"></select></label>';
            ph += '<label class="check" style="align-self:end"><input type="checkbox" name="items[' + cur + '][endcap]" value="1" class="or-endcap"> درپوش انتهایی</label>';
            ph += '<button type="button" class="btn small danger-btn or-remove" style="align-self:end">حذف</button>';
            ph += '</div><div class="inline-fields or-opts"></div>';
            if (LINE_NOTE) {
                ph += '<div class="inline-fields"><label style="flex:1">توضیح این محصول (اختیاری)<input type="text" name="items[' + cur + '][note]" maxlength="500" placeholder="مثلاً یونیت زیر گاز"></label></div>';
            }
            ph += '<div class="or-line muted"></div>';
            div.innerHTML = ph;
            box.appendChild(div);
            wireOptions(div.querySelector('.or-wire'));
            div.querySelector('.or-remove').addEventListener('click', function(){ div.remove(); calc(); });
            div.querySelector('.or-product').addEventListener('change', function(){
                div.querySelector('.or-opts').innerHTML = productOptions(this.value);
                div.querySelectorAll('.or-opts select').forEach(function(s){ s.addEventListener('change', calc); });
                calc();
            });
            div.querySelectorAll('input,select').forEach(function(el){ el.addEventListener('input', calc); el.addEventListener('change', calc); });
        }
        function calc(){
            var partner = isPartner();
            var rows = box.querySelectorAll('.order-row');
            var totalM = 0, sub = 0, wireSum = 0, fixtures = 0, html = '';
            rows.forEach(function(div){
                var pid = div.querySelector('.or-product').value;
                var p = PRODUCTS[pid];
                if (!p) return;
                var lenCm = parseFloat(div.querySelector('.or-len').value) || 0;
                var qty = parseInt(div.querySelector('.or-qty').value) || 0;
                if (lenCm <= 0 || qty <= 0) return;
                var lenM = lenCm / 100;
                var bill = Math.max(lenM, MIN_BILL);
                var delta = 0;
                div.querySelectorAll('.or-opts select').forEach(function(s){
                    var o = s.options[s.selectedIndex];
                    if (o) delta += parseFloat(o.getAttribute('data-delta') || '0');
                });
                var unit = (partner ? p.partner : p.retail) + delta;
                var lineSub = Math.round(bill * unit * qty);
                var wire = parseInt(div.querySelector('.or-wire').value) || 0;
                var steps = WIRE_STEP > 0 ? Math.max(0, Math.round((wire - WIRE_DEFAULT) / WIRE_STEP)) : 0;
                var wireExtra = steps * WIRE_PRICE * qty;
                totalM += bill * qty; sub += lineSub; wireSum += wireExtra; fixtures += qty;
                div.querySelector('.or-line').textContent = 'مبلغ ردیف: ' + fmt(lineSub + wireExtra) + ' تومان (' + qty + ' × ' + lenCm + ' سانت)';
            });
            var pct = tierPct(totalM, partner);
            var disc = Math.round(sub * pct / 100);
            var total = sub - disc + wireSum;
            if (rows.length === 0 || sub === 0) { prevBody.innerHTML = '<span class="muted">ردیفی اضافه کنید…</span>'; return; }
            html = 'متراژ قابل‌صورتحساب: <strong>' + totalM.toFixed(2) + '</strong> متر (' + fixtures + ' چراغ)<br>';
            html += 'جمع ردیف‌ها: <strong>' + fmt(sub) + '</strong> تومان<br>';
            if (WIRE_PRICE > 0 && wireSum > 0) html += 'اضافه سیم: <strong>' + fmt(wireSum) + '</strong> تومان<br>';
            html += 'تخفیف پلکانی (' + pct + '٪): <strong>' + fmt(disc) + '</strong> تومان<br>';
            html += '<big>مبلغ نهایی: <strong>' + fmt(total) + '</strong> تومان</big>';
            if (partner && totalM < MIN_METERS) {
                html += '<div class="alert error" style="margin-top:8px">هشدار: متراژ از حداقل سفارش همکار (' + MIN_METERS + ' متر) کمتر است.</div>';
            }
            prevBody.innerHTML = html;
        }
        addBtn.addEventListener('click', addRow);
        custSel.addEventListener('change', function(){
            document.getElementById('of-quick').style.display = this.value === '0' ? '' : 'none';
            calc();
        });
        newType.addEventListener('change', calc);
        document.getElementById('of-quick').style.display = 'none';
        addRow();
    })();
    </script>
    <?php
}

/** رندر جزئیات سفارش + پیش‌فاکتور چاپی */
function orders_render_view(array $d): void
{
    extract($d);
    $o = $viewOrder;
    if ($o === null) {
        echo '<h1>سفارش پیدا نشد</h1><p><a href="admin.php?page=orders">بازگشت به سفارش‌ها</a></p>';
        return;
    }
    $s = $orderSettings;
    $statusColor = order_status_color((string) $o['status']);
    $deposit = (int) round((float) ($o['total'] ?? 0) * (float) $s['deposit_percent'] / 100);
    $print = isset($_GET['print']);
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .proforma { display: block !important; border: none !important; }
    }
    .proforma { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
    .proforma h2 { margin-top: 0; }
    .proforma table { width: 100%; }
    .sig-row { display: flex; gap: 40px; margin-top: 40px; }
    .sig-row div { flex: 1; border-top: 1px dashed #9ca3af; padding-top: 8px; text-align: center; }
    </style>
    <div class="screen-area">
        <p><a href="admin.php?page=orders">← بازگشت به سفارش‌ها</a></p>
        <h1>سفارش #<?= (int) $o['order_no'] ?>
            <span class="badge" style="background:<?= e($statusColor) ?>22;color:<?= e($statusColor) ?>"><?= e(order_status_title((string) $o['status'])) ?></span>
            <?php if (($o['source'] ?? '') === 'site'): ?><span class="badge">ثبت‌شده از سایت</span><?php endif; ?>
        </h1>
        <p>
            <a class="btn small" href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>&print=1" target="_blank">🖨 چاپ پیش‌فاکتور</a>
            <?php if (in_array((string) $o['status'], ['new', 'cancelled'], true)): ?>
            <form method="post" class="inline" onsubmit="return confirm('این سفارش حذف شود؟')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_order">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <button type="submit" class="btn small danger-btn">حذف سفارش</button>
            </form>
            <?php endif; ?>
        </p>
        <section class="card wide">
            <h2>اطلاعات سفارش</h2>
            <table><tbody>
                <tr><th>مشتری</th><td><a href="admin.php?page=customers&view=<?= (int) $o['customer_id'] ?>"><?= e($o['customer_name'] ?? '—') ?></a> (<?= e(customer_type_label((string) ($o['customer_type'] ?? ''))) ?>) — <span dir="ltr"><?= e($o['customer_mobile'] ?? '') ?></span></td></tr>
                <tr><th>تاریخ ثبت</th><td><?= e($o['created_at'] ?? '') ?></td></tr>
                <tr><th>متراژ / تعداد چراغ</th><td><?= e(format_qty((float) ($o['total_meters'] ?? 0))) ?> متر — <?= (int) ($o['total_fixtures'] ?? 0) ?> چراغ</td></tr>
                <tr><th>زمان آماده‌سازی</th><td><?= (int) ($o['prep_days'] ?? 0) ?> روز (تخمینی: <?= e($viewPrepDate) ?>)</td></tr>
                <?php if (!empty($o['notes'])): ?><tr><th>توضیحات</th><td><?= nl2br(e($o['notes'])) ?></td></tr><?php endif; ?>
            </tbody></table>
            <form method="post" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_note">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <label style="flex:1">ویرایش توضیحات<textarea name="notes" rows="2"><?= e($o['notes'] ?? '') ?></textarea></label>
                <button type="submit" class="btn small" style="align-self:end">ذخیره توضیح</button>
            </form>
        </section>
        <section class="card wide">
            <h2>تغییر وضعیت</h2>
            <form method="post" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="set_order_status">
                <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                <label>وضعیت تازه
                    <select name="to_status">
                        <?php foreach ($statusesList as $st): ?>
                            <option value="<?= e($st['status_key']) ?>"<?= $st['status_key'] === (string) $o['status'] ? ' selected' : '' ?>><?= e($st['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>یادداشت<input type="text" name="note" placeholder="اختیاری…"></label>
                <button type="submit" class="btn small primary" style="align-self:end">ثبت تغییر وضعیت</button>
            </form>
            <h3>تاریخچه</h3>
            <?php if ($viewHistory === []): ?>
                <p class="muted">تاریخچه‌ای ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>از</th><th>به</th><th>یادداشت</th><th>زمان</th></tr></thead>
                <tbody>
                <?php foreach ($viewHistory as $h): ?>
                    <tr>
                        <td><?= $h['from_status'] !== null ? e(order_status_title((string) $h['from_status'])) : '—' ?></td>
                        <td><span class="badge" style="background:<?= e(order_status_color((string) $h['to_status'])) ?>22;color:<?= e(order_status_color((string) $h['to_status'])) ?>"><?= e(order_status_title((string) $h['to_status'])) ?></span></td>
                        <td><?= e($h['note'] ?? '—') ?></td>
                        <td class="muted"><?= e($h['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <section class="card wide">
            <h2>نیاز مواد این سفارش</h2>
            <p class="muted">بر اساس طول واقعی چراغ‌ها (نه طول صورتحسابی) و فرمول ساخت هر محصول. درپوش فقط برای ردیف‌هایی که درپوش دارند حساب می‌شود.</p>
            <?php if ($viewMaterials === []): ?>
                <p class="muted">برای محصولات این سفارش فرمول ساخت تعریف نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>ماده</th><th>نیاز</th><th>موجودی</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($viewMaterials as $mr): ?>
                    <tr>
                        <td><?= e($mr['name']) ?></td>
                        <td><?= e(format_qty((float) $mr['needed'])) ?> <?= e($mr['unit']) ?></td>
                        <td><?= e(format_qty((float) $mr['stock'])) ?> <?= e($mr['unit']) ?></td>
                        <td><?= (float) $mr['shortage'] > 0 ? '<span class="badge" style="background:#fee2e2;color:#b91c1c">کمبود: ' . e(format_qty((float) $mr['shortage'])) . '</span>' : '<span class="badge ok">کافی</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <?php
        // ---------- بخش مالی سفارش (فاز ۵) ----------
        $finInvoice = finance_order_invoice((int) $o['id']);
        $finPaid = finance_order_paid((int) $o['id']);
        $finGross = finance_order_gross($o);
        $finDue = $finGross - $finPaid;
        $finProfit = finance_order_profit((int) $o['id']);
        $finPayments = db()->prepare('SELECT * FROM payments WHERE order_id = :o ORDER BY id DESC');
        $finPayments->execute([':o' => (int) $o['id']]);
        $finPayments = $finPayments->fetchAll();
        $finVatPercent = (float) get_setting('vat_percent', '0');
        ?>
        <section class="card wide">
            <h2>مالی: فاکتور، دریافتی و سود</h2>
            <table><tbody>
                <tr><th>مبلغ سفارش (بدون مالیات)</th><td><?= e(format_price($o['total'])) ?> تومان</td></tr>
                <?php if ($finInvoice !== null): ?>
                <tr><th>فاکتور</th><td><strong>#<?= (int) $finInvoice['invoice_no'] ?></strong> — جمع فاکتور: <?= e(format_price($finInvoice['total'])) ?> تومان<?= (int) $finInvoice['vat_applied'] === 1 ? ' (همراه مالیات ' . e(format_price($finInvoice['vat_amount'])) . ' تومان، ' . e(format_price($finInvoice['vat_percent'])) . '٪)' : ' (بدون مالیات)' ?>
                    — <a href="admin.php?page=invoice_view&order_id=<?= (int) $o['id'] ?>">مشاهده و چاپ فاکتور</a></td></tr>
                <?php else: ?>
                <tr><th>فاکتور</th><td>صادر نشده است — از فرم پایین صادرش کنید.</td></tr>
                <?php endif; ?>
                <tr><th>دریافت‌شده</th><td><?= e(format_price($finPaid)) ?> تومان</td></tr>
                <tr><th>مانده بدهی</th><td><strong style="color:<?= $finDue > 0 ? '#b91c1c' : '#16a34a' ?>"><?= e(format_price($finDue)) ?> تومان</strong><?= $finDue <= 0 && $finInvoice !== null ? ' — تسویه شده 🎉' : '' ?></td></tr>
                <tr><th>سود سفارش (بدون مالیات)</th><td>درآمد <?= e(format_price($finProfit['revenue'])) ?> − بهای مواد <?= e(format_price($finProfit['material_cost'])) ?> (<?= $finProfit['basis'] === 'production' ? 'مصرف واقعی تولید' : 'برآورد BOM' ?>)<?= $finProfit['linked_expenses'] > 0 ? ' − هزینه مستقیم ' . e(format_price($finProfit['linked_expenses'])) : '' ?> = <strong style="color:<?= $finProfit['profit'] >= 0 ? '#16a34a' : '#b91c1c' ?>"><?= e(format_price($finProfit['profit'])) ?> تومان</strong></td></tr>
            </tbody></table>

            <?php if ($finInvoice === null && (string) $o['status'] !== 'cancelled'): ?>
            <h3>صدور فاکتور ساده</h3>
            <form method="post" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="issue_invoice">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <label style="flex:1">یادداشت فاکتور (اختیاری)<input type="text" name="note" maxlength="300"></label>
                <label style="align-self:end"><input type="checkbox" name="apply_vat" value="1"> اعمال مالیات بر ارزش افزوده (<?= e(format_price($finVatPercent)) ?>٪ = <?= e(format_price((int) round(max(0, (int) $o['subtotal'] - (int) $o['discount_amount']) * $finVatPercent / 100))) ?> تومان)</label>
                <button type="submit" class="btn small primary" style="align-self:end">صدور فاکتور</button>
            </form>
            <?php elseif ($finInvoice !== null): ?>
            <p>
                <a class="btn small primary" href="admin.php?page=invoice_view&order_id=<?= (int) $o['id'] ?>&print=1" target="_blank">🖨 چاپ فاکتور #<?= (int) $finInvoice['invoice_no'] ?></a>
                <form method="post" style="display:inline" onsubmit="return confirm('فاکتور باطل شود؟ بعدش می‌توانید با تنظیم تازه دوباره صادر کنید.')"><?= csrf_field() ?><input type="hidden" name="action" value="void_invoice"><input type="hidden" name="id" value="<?= (int) $finInvoice['id'] ?>"><button type="submit" class="btn small danger-btn">ابطال فاکتور</button></form>
            </p>
            <?php endif; ?>

            <h3>ثبت دریافتی</h3>
            <form method="post" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_payment">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <label>مبلغ (تومان)<input type="number" name="amount" min="1" step="1" required></label>
                <label>روش
                    <select name="method_key">
                        <?php foreach (finance_methods(true) as $m): ?><option value="<?= e($m['method_key']) ?>"><?= e($m['title']) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>نوع
                    <select name="kind"><option value="receipt">دریافتی</option><option value="prepayment">پیش‌پرداخت</option></select>
                </label>
                <label>تاریخ<input type="date" name="paid_on" value="<?= e(date('Y-m-d')) ?>"></label>
                <label style="flex:1">توضیح<input type="text" name="note" maxlength="200" placeholder="مثلاً بیعانه، شماره پیگیری…"></label>
                <button type="submit" class="btn small primary" style="align-self:end">+ ثبت دریافتی</button>
            </form>
            <?php if ($finPayments === []): ?>
                <p class="muted">هنوز دریافتی برای این سفارش ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>مبلغ</th><th>نوع</th><th>روش</th><th>تاریخ</th><th>توضیح</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($finPayments as $p): ?>
                    <tr>
                        <td><strong><?= e(format_price($p['amount'])) ?></strong></td>
                        <td><?= e(finance_payment_kind_label((string) $p['kind'])) ?></td>
                        <td><?= e(finance_method_title((string) $p['method_key'])) ?></td>
                        <td class="muted"><?= e(mb_substr((string) $p['paid_at'], 0, 10)) ?></td>
                        <td><?= e($p['note'] ?? '—') ?></td>
                        <td><form method="post" onsubmit="return confirm('این دریافتی حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <section class="card wide">
            <h2>تولید</h2>
            <?php $prodOpen = production_open_for_order((int) $o['id']); $prodLatest = $prodOpen ?? production_latest_for_order((int) $o['id']); ?>
            <?php if ($prodOpen !== null): ?>
                <p>برگه تولید <strong>#<?= (int) $prodOpen['production_no'] ?></strong> در جریان است — مرحله فعلی:
                    <span class="badge" style="background:<?= e(production_stage_color((string) $prodOpen['stage_key'])) ?>22;color:<?= e(production_stage_color((string) $prodOpen['stage_key'])) ?>"><?= e(production_stage_title((string) $prodOpen['stage_key'])) ?></span>
                    <a class="btn small primary" href="admin.php?page=production_view&id=<?= (int) $prodOpen['id'] ?>">مشاهده برگه تولید</a>
                </p>
            <?php else: ?>
                <?php if ($prodLatest !== null): ?>
                    <p class="muted">آخرین برگه تولید این سفارش: <strong>#<?= (int) $prodLatest['production_no'] ?></strong> (<?= e(production_state_label((string) $prodLatest['state'])) ?>) — <a href="admin.php?page=production_view&id=<?= (int) $prodLatest['id'] ?>">مشاهده برگه</a></p>
                <?php else: ?>
                    <p class="muted">این سفارش هنوز به تولید فرستاده نشده است.</p>
                <?php endif; ?>
                <?php if ((string) $o['status'] !== 'cancelled'): ?>
                <form method="post" class="inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="create_production">
                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                    <button type="submit" class="btn small primary"><?= $prodLatest !== null ? 'تولید مجدد (برگه تازه)' : '📤 ارسال به تولید' ?></button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
    <div class="proforma" id="proforma"<?= $print ? '' : ' style="display:none"' ?>>
        <h2>پیش‌فاکتور سفارش #<?= (int) $o['order_no'] ?></h2>
        <p class="muted"><?= e(all_settings()['site_title'] ?? '') ?> — تاریخ: <?= e(mb_substr((string) ($o['created_at'] ?? ''), 0, 10)) ?></p>
        <table><tbody>
            <tr><th>مشتری</th><td><?= e($o['customer_name'] ?? '—') ?> (<?= e(customer_type_label((string) ($o['customer_type'] ?? ''))) ?>)</td></tr>
            <tr><th>موبایل</th><td><span dir="ltr"><?= e($o['customer_mobile'] ?? '') ?></span></td></tr>
            <?php if (!empty($o['customer_company'])): ?><tr><th>شرکت</th><td><?= e($o['customer_company']) ?></td></tr><?php endif; ?>
        </tbody></table>
        <table>
            <thead><tr><th>#</th><th>محصول</th><th>طول (سانت)</th><th>تعداد</th><th>متراژ صورتحساب</th><th>قیمت واحد/متر</th><th>سیم</th><th>درپوش</th><th>مبلغ ردیف</th></tr></thead>
            <tbody>
            <?php $rn = 0; foreach ($viewItems as $it): $rn++; ?>
                <tr>
                    <td><?= $rn ?></td>
                    <td><?= e($it['product_name']) ?>
                        <?php $oj = json_decode((string) ($it['options_json'] ?? ''), true); if (is_array($oj) && $oj !== []): ?>
                            <br><small class="muted"><?php foreach ($oj as $osnap): ?><?= e($osnap['attr']) ?>: <?= e($osnap['option']) ?>؛ <?php endforeach; ?></small>
                        <?php endif; ?>
                        <?php if (!empty($it['note'])): ?>
                            <br><small>توضیح: <?= e($it['note']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_qty((float) $it['length_cm'])) ?></td>
                    <td><?= (int) $it['qty'] ?></td>
                    <td><?= e(format_qty((float) $it['billable_m'])) ?> متر</td>
                    <td><?= e(format_price($it['unit_price_per_m'])) ?> تومان</td>
                    <td><?= (int) $it['wire_length_cm'] ?> سانت<?= (int) $it['wire_extra_total'] > 0 ? ' (+' . e(format_price($it['wire_extra_total'])) . ')' : '' ?></td>
                    <td><?= (int) $it['has_endcap'] === 1 ? 'دارد' : '—' ?></td>
                    <td><?= e(format_price($it['line_total'])) ?> تومان</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table><tbody>
            <tr><th>جمع ردیف‌ها</th><td><?= e(format_price($o['subtotal'])) ?> تومان</td></tr>
            <?php if ((float) ($o['discount_percent'] ?? 0) > 0): ?>
            <tr><th>تخفیف پلکانی (<?= e(format_price($o['discount_percent'])) ?>٪)</th><td><?= e(format_price($o['discount_amount'])) ?> تومان</td></tr>
            <?php endif; ?>
            <tr><th><big>مبلغ نهایی</big></th><td><big><strong><?= e(format_price($o['total'])) ?> تومان</strong></big></td></tr>
            <tr><th>بیعانه (<?= e(format_price($s['deposit_percent'])) ?>٪)</th><td><?= e(format_price($deposit)) ?> تومان</td></tr>
            <tr><th>زمان آماده‌سازی</th><td><?= (int) ($o['prep_days'] ?? 0) ?> روز (تخمینی: <?= e($viewPrepDate) ?>)</td></tr>
        </tbody></table>
        <?php if (trim((string) $s['payment_terms']) !== ''): ?>
            <h3>شرایط پرداخت</h3><p><?= nl2br(e($s['payment_terms'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) $s['warranty_text']) !== ''): ?>
            <h3>گارانتی</h3><p><?= nl2br(e($s['warranty_text'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) $s['qc_text']) !== ''): ?>
            <h3>کنترل کیفیت</h3><p><?= nl2br(e($s['qc_text'])) ?></p>
        <?php endif; ?>
        <?php if (!empty($o['notes'])): ?>
            <h3>توضیحات</h3><p><?= nl2br(e($o['notes'])) ?></p>
        <?php endif; ?>
        <div class="sig-row"><div>امضای فروشنده</div><div>امضای خریدار</div></div>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php
}

/** رندر صفحه «قوانین قیمت‌گذاری» (پلکان‌ها + تنظیمات + وضعیت‌ها) */
function orders_render_rules(array $d): void
{
    extract($d);
    $s = $orderSettings;
    $tiers = price_tiers(false);
    ?>
    <h1>قوانین قیمت‌گذاری سفارش</h1>
    <p class="muted">همه قواعد تجاری سفارش اینجا قابل‌ویرایش است؛ هیچ عددی در کد ثابت نیست.</p>

    <h2>تنظیمات کلی سفارش</h2>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_order_settings">
        <div class="inline-fields">
            <label>حداقل سفارش همکار (تعداد شاخه)<input type="number" name="partner_min_bars" min="0" step="1" value="<?= e($s['partner_min_bars']) ?>"></label>
            <label>طول هر شاخه پروفیل (متر)<input type="number" name="bar_length_m" min="0.1" step="any" value="<?= e($s['bar_length_m']) ?>"></label>
            <label>حداقل طول قابل‌صورتحساب هر چراغ (متر)<input type="number" name="min_billable_m" min="0" step="any" value="<?= e($s['min_billable_m']) ?>"></label>
            <label>طول پیش‌فرض سیم هر چراغ (سانت)<input type="number" name="wire_default_cm" min="0" step="1" value="<?= e($s['wire_default_cm']) ?>"></label>
            <label>گام تنظیم سیم (سانت)<input type="number" name="wire_step_cm" min="1" step="1" value="<?= e($s['wire_step_cm']) ?>"></label>
            <label>قیمت هر گام سیم (تومان)<input type="number" name="wire_price_per_step" min="0" step="any" value="<?= e($s['wire_price_per_step']) ?>"></label>
            <label>حداکثر طول سیم هر چراغ (سانت)<input type="number" name="wire_max_cm" min="0" step="1" value="<?= e($s['wire_max_cm']) ?>"></label>
            <label>حداقل طول پرت برگشتی به انبار (سانت)<input type="number" name="remnant_min_cm" min="0" step="1" value="<?= e($s['remnant_min_cm']) ?>"></label>
            <label>زمان آماده‌سازی پیش‌فرض (روز)<input type="number" name="default_prep_days" min="0" step="1" value="<?= e($s['default_prep_days']) ?>"></label>
            <label>درصد بیعانه پیش‌فاکتور<input type="number" name="deposit_percent" min="0" max="100" step="any" value="<?= e($s['deposit_percent']) ?>"></label>
            <label>شماره سفارش بعدی<input type="number" name="next_order_no" min="1" step="1" value="<?= e($s['next_order_no']) ?>"></label>
        </div>
        <div class="inline-fields">
            <label>برخورد با سفارش همکار زیر حداقل متراژ
                <select name="enforce_min_partner">
                    <option value="warn"<?= $s['enforce_min_partner'] === 'warn' ? ' selected' : '' ?>>فقط هشدار بده</option>
                    <option value="block"<?= $s['enforce_min_partner'] === 'block' ? ' selected' : '' ?>>جلوگیری از ثبت</option>
                </select>
            </label>
            <label class="check"><input type="checkbox" name="orders_public" value="1"<?= $s['orders_public'] === '1' ? ' checked' : '' ?>> فرم «ثبت سفارش» در صفحه محصول سایت نمایش داده شود</label>
            <label class="check"><input type="checkbox" name="order_line_note" value="1"<?= $s['order_line_note'] === '1' ? ' checked' : '' ?>> برای هر محصول در سفارش، فیلد «توضیح» فعال باشد (مثلاً محل نصب: یونیت زیر گاز)</label>
        </div>
        <label>شرایط پرداخت (در پیش‌فاکتور چاپ می‌شود)<textarea name="payment_terms" rows="3"><?= e($s['payment_terms']) ?></textarea></label>
        <label>متن گارانتی<textarea name="warranty_text" rows="3"><?= e($s['warranty_text']) ?></textarea></label>
        <label>متن کنترل کیفیت<textarea name="qc_text" rows="3"><?= e($s['qc_text']) ?></textarea></label>
        <button type="submit" class="btn primary">ذخیره تنظیمات</button>
    </form>

    <h2>پلکان‌های تخفیف متراژ</h2>
    <p class="muted">تخفیف روی «متراژ کل سفارش» اعمال می‌شود. کف و سقف هر بازه شامل‌اند؛ در مرز مشترک دو پلکان، پلکان بالاتر اعمال می‌شود و پلکانِ بدون سقف فقط «بالای» کف خودش اعمال می‌شود (مثلاً دقیقاً ۳۰۰ متر همان پلکان ۱۲۰–۳۰۰ است).</p>
    <h3><?= $editTier !== null ? 'ویرایش پلکان' : 'پلکان تازه' ?></h3>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editTier !== null ? 'update_tier' : 'add_tier' ?>">
        <?php if ($editTier !== null): ?><input type="hidden" name="id" value="<?= (int) $editTier['id'] ?>"><?php endif; ?>
        <div class="inline-fields">
            <label>عنوان *<input type="text" name="title" required value="<?= e($editTier['title'] ?? '') ?>" placeholder="مثلاً ۹ تا ۱۸ متر"></label>
            <label>از (متر)<input type="number" name="min_meters" min="0" step="any" value="<?= e($editTier['min_meters'] ?? '9') ?>"></label>
            <label>تا (متر) — خالی = بدون سقف<input type="number" name="max_meters" min="0" step="any" value="<?= $editTier !== null && $editTier['max_meters'] !== null ? e($editTier['max_meters']) : '' ?>"></label>
            <label>درصد تخفیف<input type="number" name="discount_percent" min="0" max="100" step="any" value="<?= e($editTier['discount_percent'] ?? '3') ?>"></label>
            <label>اعمال برای
                <select name="applies_to">
                    <option value="partner"<?= ($editTier['applies_to'] ?? 'partner') === 'partner' ? ' selected' : '' ?>>فقط همکار</option>
                    <option value="all"<?= ($editTier['applies_to'] ?? '') === 'all' ? ' selected' : '' ?>>همه مشتری‌ها</option>
                </select>
            </label>
            <label>ترتیب<input type="number" name="sort_order" value="<?= (int) ($editTier['sort_order'] ?? 0) ?>"></label>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= ($editTier['is_active'] ?? 1) ? ' checked' : '' ?>> فعال</label>
        </div>
        <button type="submit" class="btn primary"><?= $editTier !== null ? 'ذخیره' : 'افزودن پلکان' ?></button>
        <?php if ($editTier !== null): ?><a class="btn" href="admin.php?page=order_rules">انصراف</a><?php endif; ?>
    </form>
    <?php if ($tiers !== []): ?>
    <table>
        <thead><tr><th>عنوان</th><th>بازه (متر)</th><th>تخفیف</th><th>اعمال برای</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($tiers as $t): ?>
            <tr>
                <td><?= e($t['title']) ?></td>
                <td><?= e(format_qty((float) $t['min_meters'])) ?> تا <?= $t['max_meters'] === null ? '∞' : e(format_qty((float) $t['max_meters'])) ?></td>
                <td><?= e(format_price($t['discount_percent'])) ?>٪</td>
                <td><?= $t['applies_to'] === 'all' ? 'همه' : 'همکار' ?></td>
                <td>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_tier"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn small">↑</button></form>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_tier"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn small">↓</button></form>
                    <?= (int) $t['sort_order'] ?>
                </td>
                <td><?= (int) $t['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                <td class="actions">
                    <a class="btn small" href="admin.php?page=order_rules&edit_tier=<?= (int) $t['id'] ?>">ویرایش</a>
                    <form method="post" class="inline" onsubmit="return confirm('این پلکان حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_tier"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn small danger-btn">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <h2>وضعیت‌های سفارش</h2>
    <h3><?= $editStatus !== null ? 'ویرایش وضعیت' : 'وضعیت تازه' ?></h3>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editStatus !== null ? 'update_ostatus' : 'add_ostatus' ?>">
        <?php if ($editStatus !== null): ?><input type="hidden" name="id" value="<?= (int) $editStatus['id'] ?>"><?php endif; ?>
        <div class="inline-fields">
            <label>کلید (انگلیسی، بدون فاصله) *<input type="text" name="status_key" dir="ltr" required value="<?= e($editStatus['status_key'] ?? '') ?>" placeholder="ready_to_ship"></label>
            <label>عنوان *<input type="text" name="title" required value="<?= e($editStatus['title'] ?? '') ?>"></label>
            <label>رنگ<input type="color" name="color" value="<?= e($editStatus['color'] ?? '#6b7280') ?>"></label>
            <label>ترتیب<input type="number" name="sort_order" value="<?= (int) ($editStatus['sort_order'] ?? 0) ?>"></label>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= ($editStatus['is_active'] ?? 1) ? ' checked' : '' ?>> فعال</label>
        </div>
        <button type="submit" class="btn primary"><?= $editStatus !== null ? 'ذخیره' : 'افزودن وضعیت' ?></button>
        <?php if ($editStatus !== null): ?><a class="btn" href="admin.php?page=order_rules">انصراف</a><?php endif; ?>
    </form>
    <table>
        <thead><tr><th>عنوان</th><th>کلید</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($statusesList as $st): ?>
            <tr>
                <td><span class="badge" style="background:<?= e($st['color']) ?>22;color:<?= e($st['color']) ?>"><?= e($st['title']) ?></span></td>
                <td dir="ltr" class="muted"><?= e($st['status_key']) ?></td>
                <td>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_ostatus"><input type="hidden" name="id" value="<?= (int) $st['id'] ?>"><input type="hidden" name="direction" value="up"><button class="btn small">↑</button></form>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_ostatus"><input type="hidden" name="id" value="<?= (int) $st['id'] ?>"><input type="hidden" name="direction" value="down"><button class="btn small">↓</button></form>
                    <?= (int) $st['sort_order'] ?>
                </td>
                <td><?= (int) $st['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                <td class="actions">
                    <a class="btn small" href="admin.php?page=order_rules&edit_status=<?= (int) $st['id'] ?>">ویرایش</a>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_ostatus"><input type="hidden" name="id" value="<?= (int) $st['id'] ?>"><button class="btn small"><?= (int) $st['is_active'] === 1 ? 'غیرفعال' : 'فعال' ?></button></form>
                    <form method="post" class="inline" onsubmit="return confirm('این وضعیت حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_ostatus"><input type="hidden" name="id" value="<?= (int) $st['id'] ?>"><button class="btn small danger-btn">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/** رندر صفحه «انبار پرتی» */
function orders_render_remnants(array $d): void
{
    extract($d);
    ?>
    <h1>انبار پرتی</h1>
    <p class="muted">پرت‌های برش با طول <?= e(format_qty((float) order_setting('remnant_min_cm', 20))) ?> سانت و بیشتر به اینجا برمی‌گردند و در سفارش‌های بعدی قابل‌استفاده‌اند. ثبت خودکار پرت در فاز تولید (برش واقعی) انجام می‌شود؛ فعلاً ثبت دستی.</p>
    <h2>ثبت پرت تازه</h2>
    <form method="post" class="card wide">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_remnant">
        <div class="inline-fields">
            <label>ماده اولیه
                <select name="material_id">
                    <?php foreach ($remnantMaterials as $m): ?>
                        <option value="<?= (int) $m['id'] ?>"><?= e($m['name']) ?> (<?= e($m['unit']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>طول (سانتی‌متر)<input type="number" name="length_cm" step="0.1" min="0.1" required></label>
            <label>تعداد<input type="number" name="qty" min="1" value="1"></label>
            <label>یادداشت<input type="text" name="note" placeholder="مثلاً پرت سفارش #1001"></label>
        </div>
        <button type="submit" class="btn primary">ثبت پرت</button>
    </form>
    <h2>موجودی پرتی (<?= count($remnantsList) ?>)</h2>
    <?php if ($remnantsList === []): ?>
        <div class="card wide"><p class="muted">هنوز پرتی ثبت نشده است.</p></div>
    <?php else: ?>
    <table>
        <thead><tr><th>ماده</th><th>طول</th><th>تعداد</th><th>منبع</th><th>یادداشت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($remnantsList as $r): ?>
            <tr>
                <td><?= e($r['material_name']) ?></td>
                <td><?= e(format_qty((float) $r['length_cm'])) ?> سانت</td>
                <td><?= (int) $r['qty'] ?></td>
                <td><?= $r['source'] === 'cutting' ? 'برش' : 'دستی' ?></td>
                <td><?= e($r['note'] ?? '—') ?></td>
                <td class="muted"><?= e(mb_substr((string) ($r['created_at'] ?? ''), 0, 10)) ?></td>
                <td class="actions">
                    <form method="post" class="inline" onsubmit="return confirm('این پرت حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_remnant"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn small danger-btn">حذف</button></form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <?php
}
