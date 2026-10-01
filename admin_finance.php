<?php
// admin_finance.php — بخش «مالی» پنل مدیریت (فاز ۵ نسخه ۸٫۴): فاکتور ساده و چاپی،
// دریافتی‌ها و پیش‌پرداخت‌ها، بدهی و صورتحساب مشتری، هزینه‌ها (با تأیید مدیر
// برای خرید مواد) و سود هر سفارش.
// این فایل فقط از admin.php و بعد از احراز هویت صدا زده می‌شود؛ همه قواعد پولی
// (درصد مالیات، روش‌های پرداخت، دسته‌های هزینه) از دیتابیس و پنل «قوانین مالی» می‌آیند.

declare(strict_types=1);

// دسترسی مستقیم ممنوع: این فایل به‌تنهایی هیچ خروجی و هیچ سطح مدیریتی ندارد.
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به مالی */
function finance_post_actions(): array
{
    return [
        'add_payment', 'delete_payment',
        'issue_invoice', 'void_invoice',
        'add_expense', 'update_expense', 'delete_expense', 'confirm_expense', 'reject_expense',
        'add_payment_method', 'update_payment_method', 'delete_payment_method', 'toggle_payment_method', 'move_payment_method',
        'add_expense_category', 'update_expense_category', 'delete_expense_category', 'toggle_expense_category', 'move_expense_category',
        'save_finance_settings',
    ];
}

// ---------- روش‌های پرداخت و دسته‌های هزینه (جدول‌های قابل‌ویرایش از پنل) ----------

function finance_methods(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM payment_methods';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function finance_method_title(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT method_key, title FROM payment_methods')->fetchAll() as $r) {
            $map[(string) $r['method_key']] = (string) $r['title'];
        }
    }
    return $map[$key] ?? $key;
}

function finance_categories(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM expense_categories';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function finance_cat_title(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT cat_key, title FROM expense_categories')->fetchAll() as $r) {
            $map[(string) $r['cat_key']] = (string) $r['title'];
        }
    }
    return $map[$key] ?? $key;
}

function finance_cat_color(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db()->query('SELECT cat_key, color FROM expense_categories')->fetchAll() as $r) {
            $map[(string) $r['cat_key']] = (string) $r['color'];
        }
    }
    return $map[$key] ?? '#6b7280';
}

// ---------- فاکتور و دریافتی هر سفارش ----------

function finance_order_invoice(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM invoices WHERE order_id = :o');
    $stmt->execute([':o' => $orderId]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** جمع دریافتی‌های یک سفارش (تومان) */
function finance_order_paid(int $orderId): int
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = :o');
    $stmt->execute([':o' => $orderId]);
    return (int) $stmt->fetchColumn();
}

/** مبلغ قابل پرداخت سفارش: جمع فاکتور اگر صادر شده، وگرنه جمع سفارش */
function finance_order_gross(array $order): int
{
    $inv = finance_order_invoice((int) $order['id']);
    return $inv !== null ? (int) $inv['total'] : (int) $order['total'];
}

/** مانده بدهی یک سفارش (تومان)؛ ممکن است با پرداخت اضافه منفی شود */
function finance_order_due(array $order): int
{
    return finance_order_gross($order) - finance_order_paid((int) $order['id']);
}

/** تعداد و جمع هزینه‌هایی که در انتظار تأیید مدیرند (برای بج منو و داشبورد) */
function finance_pending_expenses(): array
{
    try {
        $row = db()->query("SELECT COUNT(*) AS c, COALESCE(SUM(amount), 0) AS s FROM expenses WHERE status = 'pending'")->fetch();
        return ['count' => (int) ($row['c'] ?? 0), 'sum' => (int) ($row['s'] ?? 0)];
    } catch (Throwable) {
        return ['count' => 0, 'sum' => 0];
    }
}

// ---------- سود سفارش ----------

/**
 * بهای مواد یک سفارش (تومان) با منبع محاسبه:
 *   production — از روی مصرف واقعی برگه تولید (خروج انبار با قیمت همان گردش +
 *   ارزش پرت مصرف‌شده منهای پرت برگشتی، با آخرین قیمت خرید)
 *   bom        — برآورد از روی فرمول ساخت محصول و آخرین قیمت خرید مواد
 * خروجی: ['cost' => int, 'basis' => 'production'|'bom', 'lines' => [ ['name','amount'] ]]
 */
function finance_order_material_cost(int $orderId): array
{
    // ۱) برگه تولید شروع‌شده برای همین سفارش؟
    $stmt = db()->prepare("SELECT * FROM production_orders WHERE order_id = :o AND state != 'cancelled' AND started_at IS NOT NULL ORDER BY id DESC");
    $stmt->execute([':o' => $orderId]);
    $prod = $stmt->fetch();
    if ($prod !== false) {
        $c = db()->prepare(
            'SELECT pc.kind, pc.qty, m.name AS material_name, m.unit AS material_unit, m.last_price,
                    sm.unit_price AS movement_price
             FROM production_consumptions pc
             JOIN materials m ON m.id = pc.material_id
             LEFT JOIN stock_movements sm ON sm.id = pc.movement_id
             WHERE pc.production_id = :p AND pc.reversed = 0
             ORDER BY pc.id ASC'
        );
        $c->execute([':p' => (int) $prod['id']]);
        $rows = $c->fetchAll();
        if ($rows !== []) {
            $total = 0.0;
            $lines = [];
            $acc = [];
            $addLine = static function (string $label, float $amount) use (&$acc): void {
                if (!isset($acc[$label])) {
                    $acc[$label] = 0.0;
                }
                $acc[$label] += $amount;
            };
            foreach ($rows as $r) {
                $label = (string) $r['material_name'];
                if ((string) $r['kind'] === 'stock_out') {
                    $price = $r['movement_price'] !== null ? (int) $r['movement_price'] : (int) $r['last_price'];
                    $amt = (float) $r['qty'] * $price;
                    $total += $amt;
                    $addLine($label, $amt);
                } elseif ((string) $r['kind'] === 'remnant_use') {
                    // qty طول تکه پرت مصرف‌شده (سانتی‌متر) است؛ ارزشش با آخرین قیمت متری همان ماده
                    $amt = ((float) $r['qty'] / 100) * (int) $r['last_price'];
                    $total += $amt;
                    $addLine($label . ' (از پرت)', $amt);
                } elseif ((string) $r['kind'] === 'remnant_new') {
                    $amt = ((float) $r['qty'] / 100) * (int) $r['last_price'];
                    $total -= $amt;
                    $addLine($label . ' (پرت برگشتی)', -$amt);
                }
            }
            foreach ($acc as $label => $amt) {
                $lines[] = ['name' => $label, 'amount' => (int) round($amt)];
            }
            return ['cost' => (int) round($total), 'basis' => 'production', 'lines' => $lines];
        }
    }

    // ۲) برآورد از روی فرمول ساخت: مصرف متری × طول قابل‌محاسبه + مصرف هر چراغ × تعداد
    $total = 0.0;
    $lines = [];
    foreach (order_items($orderId) as $it) {
        $cost = product_material_cost((int) $it['product_id']);
        $lineCost = $cost['per_meter'] * (float) $it['billable_m'] + $cost['per_fixture'] * (int) $it['qty'];
        $total += $lineCost;
        $lines[] = [
            'name' => (string) $it['product_name'] . ' — ' . format_qty((float) $it['billable_m']) . ' متر × ' . (int) $it['qty'] . ' چراغ',
            'amount' => (int) round($lineCost),
        ];
    }
    return ['cost' => (int) round($total), 'basis' => 'bom', 'lines' => $lines];
}

/** هزینه‌های قطعیِ چسبیده به یک سفارش (مثلاً حمل و نصب همان سفارش) */
function finance_order_linked_expenses(int $orderId): int
{
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE order_id = :o AND status = 'confirmed'");
    $stmt->execute([':o' => $orderId]);
    return (int) $stmt->fetchColumn();
}

/** سود یک سفارش: مبلغ سفارش (بدون مالیات) منهای بهای مواد و هزینه‌های مستقیمش */
function finance_order_profit(int $orderId): array
{
    $order = order_get($orderId);
    if ($order === null) {
        return ['revenue' => 0, 'material_cost' => 0, 'basis' => 'bom', 'linked_expenses' => 0, 'profit' => 0, 'lines' => []];
    }
    $mat = finance_order_material_cost($orderId);
    $linked = finance_order_linked_expenses($orderId);
    $revenue = (int) $order['total'];
    return [
        'revenue' => $revenue,
        'material_cost' => (int) $mat['cost'],
        'basis' => (string) $mat['basis'],
        'lines' => (array) $mat['lines'],
        'linked_expenses' => $linked,
        'profit' => $revenue - (int) $mat['cost'] - $linked,
    ];
}

// ---------- بدهی مشتری ----------

/** سفارش‌های بدهکار یک مشتری (به‌جز لغوشده‌ها) با مانده هر کدام */
function finance_customer_open_orders(int $customerId): array
{
    $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = :c AND status != 'cancelled' ORDER BY id DESC");
    $stmt->execute([':c' => $customerId]);
    $out = [];
    foreach ($stmt->fetchAll() as $o) {
        $due = finance_order_due($o);
        if ($due > 0) {
            $o['due'] = $due;
            $out[] = $o;
        }
    }
    return $out;
}

/** مانده حساب مشتری: جمع بدهی سفارش‌های تسویه‌نشده‌اش */
function finance_customer_balance(int $customerId): int
{
    $sum = 0;
    foreach (finance_customer_open_orders($customerId) as $o) {
        $sum += (int) $o['due'];
    }
    return $sum;
}

/** فهرست مشتریان بدهکار با مانده حسابشان (بزرگ‌ترین بدهی اول) */
function finance_debtors(): array
{
    $rows = db()->query(
        'SELECT DISTINCT c.id, c.full_name, c.company, c.mobile
         FROM customers c JOIN orders o ON o.customer_id = c.id
         WHERE o.status != \'cancelled\'
         ORDER BY c.full_name ASC'
    )->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $bal = finance_customer_balance((int) $r['id']);
        if ($bal > 0) {
            $r['balance'] = $bal;
            $out[] = $r;
        }
    }
    usort($out, static fn (array $a, array $b): int => $b['balance'] <=> $a['balance']);
    return $out;
}

function finance_expense_status_label(string $status): string
{
    return [
        'pending' => 'در انتظار تأیید',
        'confirmed' => 'قطعی',
        'rejected' => 'ردشده',
    ][$status] ?? $status;
}

function finance_payment_kind_label(string $kind): string
{
    return $kind === 'prepayment' ? 'پیش‌پرداخت' : 'دریافتی';
}

// ---------- پردازش POSTهای مالی ----------

function finance_handle_post(string $action): void
{
    global $pdo;

    // ----- دریافتی‌ها -----
    if ($action === 'add_payment') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = order_get($orderId);
        if ($order === null) {
            throw new RuntimeException('سفارش پیدا نشد.');
        }
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('مبلغ دریافتی را به تومان و بزرگ‌تر از صفر وارد کنید.');
        }
        $kind = (string) ($_POST['kind'] ?? 'receipt') === 'prepayment' ? 'prepayment' : 'receipt';
        $methodKey = (string) ($_POST['method_key'] ?? 'cash');
        $valid = array_map(static fn (array $m): string => (string) $m['method_key'], finance_methods(true));
        if (!in_array($methodKey, $valid, true)) {
            $methodKey = 'cash';
        }
        $date = trim((string) ($_POST['paid_on'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $pdo->prepare('INSERT INTO payments (order_id, customer_id, amount, method_key, kind, note, paid_at) VALUES (:o, :c, :a, :m, :k, :n, :d)')
            ->execute([
                ':o' => $orderId,
                ':c' => (int) $order['customer_id'],
                ':a' => $amount,
                ':m' => $methodKey,
                ':k' => $kind,
                ':n' => trim((string) ($_POST['note'] ?? '')) ?: null,
                ':d' => $date . ' ' . date('H:i:s'),
            ]);
        $left = finance_order_due($order);
        log_admin_event($action, 'دریافت ' . format_price($amount) . ' تومان برای سفارش #' . (int) $order['order_no'] . ' (' . finance_payment_kind_label($kind) . ')', true, 'finance');
        flash('ok', 'دریافتی ثبت شد. مانده این سفارش: ' . format_price($left) . ' تومان.');
        redirect_admin('admin.php?page=order_view&id=' . $orderId);
    }

    if ($action === 'delete_payment') {
        $pid = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT order_id FROM payments WHERE id = :id');
        $stmt->execute([':id' => $pid]);
        $row = $stmt->fetch();
        $pdo->prepare('DELETE FROM payments WHERE id = :id')->execute([':id' => $pid]);
        log_admin_event($action, 'حذف دریافتی ' . $pid, true, 'finance');
        flash('ok', 'دریافتی حذف شد.');
        redirect_admin('admin.php?page=order_view&id=' . (int) ($row['order_id'] ?? 0));
    }

    // ----- فاکتور -----
    if ($action === 'issue_invoice') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = order_get($orderId);
        if ($order === null) {
            throw new RuntimeException('سفارش پیدا نشد.');
        }
        if ((string) $order['status'] === 'cancelled') {
            throw new RuntimeException('برای سفارش لغوشده فاکتور صادر نمی‌شود.');
        }
        if (finance_order_invoice($orderId) !== null) {
            throw new RuntimeException('این سفارش فاکتور صادرشده دارد؛ برای صدور دوباره اول آن را باطل کنید.');
        }
        $applyVat = isset($_POST['apply_vat']);
        $vatPercent = (float) get_setting('vat_percent', '0');
        if ($vatPercent < 0) {
            $vatPercent = 0.0;
        }
        if ($vatPercent > 100) {
            $vatPercent = 100.0;
        }
        $subtotal = (int) $order['subtotal'];
        $discount = (int) $order['discount_amount'];
        $base = max(0, $subtotal - $discount);
        $vatAmount = $applyVat ? (int) round($base * $vatPercent / 100) : 0;
        $no = (int) get_setting('next_invoice_no', '1');
        if ($no < 1) {
            $no = 1;
        }
        $pdo->prepare('INSERT INTO invoices (invoice_no, order_id, vat_applied, vat_percent, subtotal, discount, vat_amount, total, note) VALUES (:n, :o, :av, :vp, :s, :d, :va, :t, :nt)')
            ->execute([
                ':n' => $no,
                ':o' => $orderId,
                ':av' => $applyVat ? 1 : 0,
                ':vp' => $applyVat ? $vatPercent : 0,
                ':s' => $subtotal,
                ':d' => $discount,
                ':va' => $vatAmount,
                ':t' => $base + $vatAmount,
                ':nt' => trim((string) ($_POST['note'] ?? '')) ?: null,
            ]);
        set_setting('next_invoice_no', (string) ($no + 1));
        log_admin_event($action, 'صدور فاکتور ' . $no . ' برای سفارش #' . (int) $order['order_no'] . ($applyVat ? ' (با مالیات ' . format_price($vatAmount) . ' تومان)' : ' (بدون مالیات)'), true, 'finance');
        flash('ok', 'فاکتور شماره ' . $no . ' صادر شد' . ($applyVat ? '؛ مالیات بر ارزش افزوده ' . format_price($vatAmount) . ' تومان روی آن محاسبه شد.' : ' (بدون مالیات).'));
        redirect_admin('admin.php?page=invoice_view&order_id=' . $orderId);
    }

    if ($action === 'void_invoice') {
        $iid = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT invoice_no, order_id FROM invoices WHERE id = :id');
        $stmt->execute([':id' => $iid]);
        $row = $stmt->fetch();
        if ($row === false) {
            throw new RuntimeException('فاکتور پیدا نشد.');
        }
        $pdo->prepare('DELETE FROM invoices WHERE id = :id')->execute([':id' => $iid]);
        log_admin_event($action, 'ابطال فاکتور ' . (int) $row['invoice_no'], true, 'finance');
        flash('ok', 'فاکتور ' . (int) $row['invoice_no'] . ' باطل شد؛ حالا می‌توانید فاکتور تازه‌ای صادر کنید.');
        redirect_admin('admin.php?page=order_view&id=' . (int) $row['order_id']);
    }

    // ----- هزینه‌ها -----
    if ($action === 'add_expense' || $action === 'update_expense') {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            throw new RuntimeException('عنوان هزینه را وارد کنید.');
        }
        $amount = (int) ($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            throw new RuntimeException('مبلغ هزینه را به تومان و بزرگ‌تر از صفر وارد کنید.');
        }
        $catKey = (string) ($_POST['cat_key'] ?? 'other');
        $valid = array_map(static fn (array $c): string => (string) $c['cat_key'], finance_categories(false));
        if (!in_array($catKey, $valid, true)) {
            $catKey = 'other';
        }
        $date = trim((string) ($_POST['expense_date'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        // سفارش مرتبط: از روی شماره سفارش (فرم) یا شناسه مستقیم حل می‌شود
        $orderId = null;
        $orderNoRaw = (int) ($_POST['order_no'] ?? 0);
        $orderIdRaw = (int) ($_POST['order_id'] ?? 0);
        if ($orderNoRaw > 0) {
            $q = $pdo->prepare('SELECT id FROM orders WHERE order_no = :no');
            $q->execute([':no' => $orderNoRaw]);
            $found = $q->fetchColumn();
            $orderId = $found === false ? null : (int) $found;
        } elseif ($orderIdRaw > 0) {
            $orderId = $orderIdRaw;
        }
        $data = [
            ':cat' => $catKey,
            ':t' => $title,
            ':a' => $amount,
            ':o' => $orderId,
            ':d' => $date,
            ':n' => trim((string) ($_POST['note'] ?? '')) ?: null,
        ];
        if ($action === 'update_expense') {
            $eid = (int) ($_POST['id'] ?? 0);
            // هزینه خودکار خرید مواد: عنوان، مبلغ و دسته‌اش از تراکنش انبار می‌آید و در ویرایش دستی قفل است
            $cur = $pdo->prepare('SELECT source, title, amount, cat_key FROM expenses WHERE id = :id');
            $cur->execute([':id' => $eid]);
            $existing = $cur->fetch();
            if ($existing !== false && (string) $existing['source'] === 'material_purchase') {
                $data[':t'] = (string) $existing['title'];
                $data[':a'] = (int) $existing['amount'];
                $data[':cat'] = (string) $existing['cat_key'];
            }
            $data[':id'] = $eid;
            $pdo->prepare('UPDATE expenses SET cat_key = :cat, title = :t, amount = :a, order_id = :o, expense_date = :d, note = :n WHERE id = :id')->execute($data);
            log_admin_event($action, 'ویرایش هزینه ' . $eid . ': ' . $title, true, 'finance');
            flash('ok', 'هزینه به‌روزرسانی شد.');
        } else {
            // هزینه دستی پیش‌فرض قطعی است؛ از فرم می‌شود «ثبت اولیه» هم انتخاب کرد تا مدیر بعداً تأیید کند
            $statusWanted = (string) ($_POST['status'] ?? 'confirmed') === 'pending' ? 'pending' : 'confirmed';
            $pdo->prepare("INSERT INTO expenses (cat_key, title, amount, order_id, status, source, expense_date, note, confirmed_at) VALUES (:cat, :t, :a, :o, :st, 'manual', :d, :n, CASE WHEN :st = 'confirmed' THEN CURRENT_TIMESTAMP ELSE NULL END)")
                ->execute($data + [':st' => $statusWanted]);
            log_admin_event($action, 'ثبت هزینه: ' . $title . ' — ' . format_price($amount) . ' تومان', true, 'finance');
            flash('ok', $statusWanted === 'pending' ? 'هزینه به‌صورت ثبت اولیه ذخیره شد؛ بعد از تأیید مدیر قطعی می‌شود.' : 'هزینه ثبت و قطعی شد.');
        }
        redirect_admin('admin.php?page=expenses');
    }

    if ($action === 'confirm_expense' || $action === 'reject_expense') {
        $eid = (int) ($_POST['id'] ?? 0);
        $newStatus = $action === 'confirm_expense' ? 'confirmed' : 'rejected';
        $stmt = $pdo->prepare("UPDATE expenses SET status = :s, confirmed_at = CASE WHEN :s = 'confirmed' THEN CURRENT_TIMESTAMP ELSE confirmed_at END WHERE id = :id");
        $stmt->execute([':s' => $newStatus, ':id' => $eid]);
        log_admin_event($action, ($newStatus === 'confirmed' ? 'تأیید هزینه ' : 'رد هزینه ') . $eid, true, 'finance');
        flash('ok', $newStatus === 'confirmed' ? 'هزینه تأیید و قطعی شد.' : 'هزینه رد شد و در گزارش‌ها حساب نمی‌شود.');
        redirect_admin('admin.php?page=expenses');
    }

    if ($action === 'delete_expense') {
        $eid = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM expenses WHERE id = :id')->execute([':id' => $eid]);
        log_admin_event($action, 'حذف هزینه ' . $eid, true, 'finance');
        flash('ok', 'هزینه حذف شد.');
        redirect_admin('admin.php?page=expenses');
    }

    // ----- روش‌های پرداخت -----
    if ($action === 'add_payment_method' || $action === 'update_payment_method') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $key = strtolower(trim((string) ($_POST['method_key'] ?? '')));
        if ($title === '') {
            throw new RuntimeException('عنوان روش پرداخت لازم است.');
        }
        if ($action === 'update_payment_method') {
            // کلید در ویرایش دست نمی‌خورد و وضعیت فعال‌بودن هم فقط با دکمه تغییر وضعیت عوض می‌شود
            $mid = (int) ($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT method_key FROM payment_methods WHERE id = :id');
            $stmt->execute([':id' => $mid]);
            $old = $stmt->fetch();
            if ($old === false) {
                throw new RuntimeException('روش پرداخت پیدا نشد.');
            }
            $pdo->prepare('UPDATE payment_methods SET title = :t WHERE id = :id')
                ->execute([':t' => $title, ':id' => $mid]);
            log_admin_event($action, 'ویرایش روش پرداخت ' . (string) $old['method_key'], true, 'finance');
            flash('ok', 'روش پرداخت به‌روزرسانی شد.');
        } else {
            if (!preg_match('/^[a-z0-9_]{2,30}$/', $key)) {
                throw new RuntimeException('کلید روش پرداخت فقط حروف انگلیسی کوچک، عدد و آندرلاین (۲ تا ۳۰ حرف) می‌تواند باشد.');
            }
            $max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM payment_methods')->fetchColumn();
            $pdo->prepare('INSERT INTO payment_methods (method_key, title, is_active, sort_order) VALUES (:k, :t, 1, :s)')
                ->execute([':k' => $key, ':t' => $title, ':s' => $max + 10]);
            log_admin_event($action, 'افزودن روش پرداخت ' . $key, true, 'finance');
            flash('ok', 'روش پرداخت تازه اضافه شد.');
        }
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'delete_payment_method') {
        $mid = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT method_key FROM payment_methods WHERE id = :id');
        $stmt->execute([':id' => $mid]);
        $row = $stmt->fetch();
        if ($row !== false) {
            $used = (int) $pdo->query('SELECT COUNT(*) FROM payments WHERE method_key = ' . $pdo->quote((string) $row['method_key']))->fetchColumn();
            if ($used > 0) {
                throw new RuntimeException('این روش پرداخت در ' . $used . ' دریافتی استفاده شده و حذف نمی‌شود؛ به‌جایش غیرفعالش کنید.');
            }
        }
        $pdo->prepare('DELETE FROM payment_methods WHERE id = :id')->execute([':id' => $mid]);
        log_admin_event($action, 'حذف روش پرداخت ' . $mid, true, 'finance');
        flash('ok', 'روش پرداخت حذف شد.');
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'toggle_payment_method') {
        $pdo->prepare('UPDATE payment_methods SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'move_payment_method') {
        move_row($pdo, 'payment_methods', (int) ($_POST['id'] ?? 0), (string) ($_POST['dir'] ?? 'up'));
        redirect_admin('admin.php?page=finance_rules');
    }

    // ----- دسته‌های هزینه -----
    if ($action === 'add_expense_category' || $action === 'update_expense_category') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $key = strtolower(trim((string) ($_POST['cat_key'] ?? '')));
        $color = trim((string) ($_POST['color'] ?? '#6b7280'));
        if ($title === '') {
            throw new RuntimeException('عنوان دسته هزینه را وارد کنید.');
        }
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            $color = '#6b7280';
        }
        if ($action === 'update_expense_category') {
            // وضعیت فعال‌بودن در ویرایش عنوان/رنگ دست نمی‌خورد
            $cid = (int) ($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE expense_categories SET title = :t, color = :c WHERE id = :id')
                ->execute([':t' => $title, ':c' => $color, ':id' => $cid]);
            log_admin_event($action, 'ویرایش دسته هزینه ' . $cid, true, 'finance');
            flash('ok', 'دسته هزینه به‌روزرسانی شد.');
        } else {
            if (!preg_match('/^[a-z0-9_]{2,30}$/', $key)) {
                throw new RuntimeException('کلید دسته فقط حروف انگلیسی کوچک، عدد و آندرلاین (۲ تا ۳۰ حرف) می‌تواند باشد.');
            }
            $max = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM expense_categories')->fetchColumn();
            $pdo->prepare('INSERT INTO expense_categories (cat_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, 1, :s)')
                ->execute([':k' => $key, ':t' => $title, ':c' => $color, ':s' => $max + 10]);
            log_admin_event($action, 'افزودن دسته هزینه ' . $key, true, 'finance');
            flash('ok', 'دسته هزینه تازه اضافه شد.');
        }
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'delete_expense_category') {
        $cid = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT cat_key FROM expense_categories WHERE id = :id');
        $stmt->execute([':id' => $cid]);
        $row = $stmt->fetch();
        if ($row !== false) {
            if ((string) $row['cat_key'] === 'materials') {
                throw new RuntimeException('دسته «خرید مواد اولیه» دسته سیستمی است و حذف نمی‌شود؛ چون خریدهای انبار خودکار داخل آن ثبت اولیه می‌شوند.');
            }
            $used = (int) $pdo->query('SELECT COUNT(*) FROM expenses WHERE cat_key = ' . $pdo->quote((string) $row['cat_key']))->fetchColumn();
            if ($used > 0) {
                throw new RuntimeException('این دسته در ' . $used . ' هزینه استفاده شده و حذف نمی‌شود؛ به‌جایش غیرفعالش کنید.');
            }
        }
        $pdo->prepare('DELETE FROM expense_categories WHERE id = :id')->execute([':id' => $cid]);
        log_admin_event($action, 'حذف دسته هزینه ' . $cid, true, 'finance');
        flash('ok', 'دسته هزینه حذف شد.');
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'toggle_expense_category') {
        $pdo->prepare('UPDATE expense_categories SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => (int) ($_POST['id'] ?? 0)]);
        redirect_admin('admin.php?page=finance_rules');
    }

    if ($action === 'move_expense_category') {
        move_row($pdo, 'expense_categories', (int) ($_POST['id'] ?? 0), (string) ($_POST['dir'] ?? 'up'));
        redirect_admin('admin.php?page=finance_rules');
    }

    // ----- تنظیمات مالی -----
    if ($action === 'save_finance_settings') {
        $vat = (float) ($_POST['vat_percent'] ?? 0);
        if ($vat < 0) {
            $vat = 0.0;
        }
        if ($vat > 100) {
            $vat = 100.0;
        }
        set_setting('vat_percent', rtrim(rtrim(number_format($vat, 2, '.', ''), '0'), '.'));
        $no = (int) ($_POST['next_invoice_no'] ?? 1);
        if ($no < 1) {
            $no = 1;
        }
        set_setting('next_invoice_no', (string) $no);
        log_admin_event($action, 'ذخیره تنظیمات مالی (مالیات ' . format_price($vat) . '٪)', true, 'finance');
        flash('ok', 'تنظیمات مالی ذخیره شد.');
        redirect_admin('admin.php?page=finance_rules');
    }
}

// ---------- بارگذاری داده صفحه‌های مالی ----------

function finance_load_data(string $page): array
{
    $d = [
        'finPending' => ['count' => 0, 'sum' => 0],
        'finMonthIncome' => 0,
        'finMonthExpenses' => 0,
        'finDebtTotal' => 0,
        'finDebtors' => [],
        'finPendingList' => [],
        'finRecentPayments' => [],
        'finRecentExpenses' => [],
        'finUnpaidOrders' => [],
        'finProfitRows' => [],
        'finInvoices' => [],
        'finInvOrder' => null,
        'finInvItems' => [],
        'finInvCustomer' => null,
        'finInvPayments' => [],
        'finExpenses' => [],
        'finExpenseFilter' => '',
        'finEditExpense' => null,
        'finCustomers' => [],
        'finStmtCustomer' => null,
        'finStmtOrders' => [],
        'finStmtPayments' => [],
        'finStmtBalance' => 0,
        'finMethods' => [],
        'finMethodsAll' => [],
        'finCats' => [],
        'finCatsAll' => [],
        'finVatPercent' => (float) get_setting('vat_percent', '0'),
        'finNextInvoiceNo' => (int) get_setting('next_invoice_no', '1'),
    ];
    try {
        $d['finPending'] = finance_pending_expenses();
    } catch (Throwable) {
        // جدول‌های مالی هنوز ساخته نشده‌اند (نصب خیلی قدیمی) — منو بدون بج نمایش داده می‌شود
    }

    if ($page === 'finance') {
        $month = date('Y-m');
        $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE substr(paid_at, 1, 7) = :m");
        $stmt->execute([':m' => $month]);
        $d['finMonthIncome'] = (int) $stmt->fetchColumn();
        $stmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'confirmed' AND substr(COALESCE(expense_date, created_at), 1, 7) = :m");
        $stmt->execute([':m' => $month]);
        $d['finMonthExpenses'] = (int) $stmt->fetchColumn();

        $d['finDebtors'] = finance_debtors();
        foreach ($d['finDebtors'] as $debtor) {
            $d['finDebtTotal'] += (int) $debtor['balance'];
        }

        $d['finPendingList'] = db()->query(
            "SELECT e.*, c.full_name AS customer_name
             FROM expenses e LEFT JOIN orders o ON o.id = e.order_id LEFT JOIN customers c ON c.id = o.customer_id
             WHERE e.status = 'pending' ORDER BY e.id DESC LIMIT 30"
        )->fetchAll();

        $d['finRecentPayments'] = db()->query(
            'SELECT p.*, o.order_no, c.full_name AS customer_name
             FROM payments p JOIN orders o ON o.id = p.order_id LEFT JOIN customers c ON c.id = p.customer_id
             ORDER BY p.id DESC LIMIT 15'
        )->fetchAll();

        $d['finRecentExpenses'] = db()->query(
            "SELECT * FROM expenses WHERE status != 'pending' ORDER BY id DESC LIMIT 15"
        )->fetchAll();

        // سفارش‌های باز با مانده بدهی (جدیدترین اول)
        $open = db()->query(
            "SELECT o.*, c.full_name AS customer_name
             FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.status != 'cancelled'
             ORDER BY o.id DESC LIMIT 200"
        )->fetchAll();
        foreach ($open as $o) {
            $due = finance_order_due($o);
            if ($due > 0) {
                $o['due'] = $due;
                $o['paid'] = finance_order_paid((int) $o['id']);
                $o['has_invoice'] = finance_order_invoice((int) $o['id']) !== null ? 1 : 0;
                $d['finUnpaidOrders'][] = $o;
            }
            if (count($d['finUnpaidOrders']) >= 30) {
                break;
            }
        }

        // سود سفارش‌های اخیر (فقط سفارش‌هایی که فاکتور یا برگه تولید دارند)
        $recent = db()->query(
            "SELECT o.*, c.full_name AS customer_name
             FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.status != 'cancelled'
               AND (EXISTS (SELECT 1 FROM invoices i WHERE i.order_id = o.id)
                    OR EXISTS (SELECT 1 FROM production_orders p WHERE p.order_id = o.id AND p.state != 'cancelled'))
             ORDER BY o.id DESC LIMIT 30"
        )->fetchAll();
        foreach ($recent as $o) {
            $p = finance_order_profit((int) $o['id']);
            $d['finProfitRows'][] = ['order' => $o] + $p;
        }
    }

    if ($page === 'invoices') {
        $d['finInvoices'] = db()->query(
            'SELECT i.*, o.order_no, o.status AS order_status, c.full_name AS customer_name, c.mobile AS customer_mobile
             FROM invoices i JOIN orders o ON o.id = i.order_id LEFT JOIN customers c ON c.id = o.customer_id
             ORDER BY i.id DESC LIMIT 300'
        )->fetchAll();
        foreach ($d['finInvoices'] as &$inv) {
            $orderRow = order_get((int) $inv['order_id']);
            $inv['paid'] = finance_order_paid((int) $inv['order_id']);
            $inv['due'] = $orderRow !== null ? finance_order_due($orderRow) : (int) $inv['total'] - (int) $inv['paid'];
        }
        unset($inv);
    }

    if ($page === 'invoice_view' && isset($_GET['order_id'])) {
        $order = order_get((int) $_GET['order_id']);
        if ($order !== null) {
            $d['finInvOrder'] = $order;
            $d['finInvItems'] = order_items((int) $order['id']);
            $d['finInvCustomer'] = get_customer((int) $order['customer_id']);
            $stmt = db()->prepare('SELECT * FROM payments WHERE order_id = :o ORDER BY id ASC');
            $stmt->execute([':o' => (int) $order['id']]);
            $d['finInvPayments'] = $stmt->fetchAll();
        }
    }

    if ($page === 'expenses') {
        $d['finExpenseFilter'] = trim((string) ($_GET['status'] ?? ''));
        $sql = 'SELECT e.*, o.order_no AS linked_order_no FROM expenses e LEFT JOIN orders o ON o.id = e.order_id';
        $params = [];
        if (in_array($d['finExpenseFilter'], ['pending', 'confirmed', 'rejected'], true)) {
            $sql .= ' WHERE e.status = :s';
            $params[':s'] = $d['finExpenseFilter'];
        }
        $sql .= ' ORDER BY e.id DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $d['finExpenses'] = $stmt->fetchAll();
        $d['finCats'] = finance_categories(true);
        if (isset($_GET['edit_id'])) {
            $stmt = db()->prepare('SELECT * FROM expenses WHERE id = :id');
            $stmt->execute([':id' => (int) $_GET['edit_id']]);
            $r = $stmt->fetch();
            $d['finEditExpense'] = $r === false ? null : $r;
        }
    }

    if ($page === 'statements') {
        $d['finDebtors'] = finance_debtors();
        foreach ($d['finDebtors'] as $debtor) {
            $d['finDebtTotal'] += (int) $debtor['balance'];
        }
        $d['finCustomers'] = db()->query('SELECT id, full_name, company, mobile FROM customers ORDER BY full_name ASC')->fetchAll();
        $cid = (int) ($_GET['customer_id'] ?? 0);
        if ($cid > 0) {
            $d['finStmtCustomer'] = get_customer($cid);
            if ($d['finStmtCustomer'] !== null) {
                $stmt = db()->prepare("SELECT * FROM orders WHERE customer_id = :c AND status != 'cancelled' ORDER BY id ASC");
                $stmt->execute([':c' => $cid]);
                foreach ($stmt->fetchAll() as $o) {
                    $o['gross'] = finance_order_gross($o);
                    $o['paid'] = finance_order_paid((int) $o['id']);
                    $o['due'] = $o['gross'] - $o['paid'];
                    $o['invoice_no'] = ($inv = finance_order_invoice((int) $o['id'])) !== null ? (int) $inv['invoice_no'] : null;
                    $d['finStmtOrders'][] = $o;
                    $d['finStmtBalance'] += (int) $o['due'];
                }
                $stmt = db()->prepare('SELECT p.*, o.order_no FROM payments p JOIN orders o ON o.id = p.order_id WHERE p.customer_id = :c ORDER BY p.id ASC');
                $stmt->execute([':c' => $cid]);
                $d['finStmtPayments'] = $stmt->fetchAll();
            }
        }
    }

    if ($page === 'finance_rules') {
        $d['finMethods'] = finance_methods(true);
        $d['finMethodsAll'] = finance_methods(false);
        $d['finCats'] = finance_categories(true);
        $d['finCatsAll'] = finance_categories(false);
    }

    return $d;
}

// ---------- رندرها ----------

/** رندر داشبورد مالی */
function finance_render_dashboard(array $d): void
{
    extract($d);
    $net = $finMonthIncome - $finMonthExpenses;
    ?>
    <h1>داشبورد مالی</h1>
    <p class="muted">تصویر مالی کسب‌وکار: دریافتی‌ها، هزینه‌های قطعی، بدهی مشتریان و سود سفارش‌ها. همه مبالغ به تومان.</p>

    <div class="stat-grid dash-cards">
        <a class="stat-card" href="admin.php?page=invoices"><span>دریافتی‌های این ماه</span><strong><?= e(format_price($finMonthIncome)) ?></strong></a>
        <a class="stat-card" href="admin.php?page=expenses"><span>هزینه‌های قطعی این ماه</span><strong><?= e(format_price($finMonthExpenses)) ?></strong></a>
        <div class="stat-card"><span>تراز این ماه (دریافت − هزینه)</span><strong style="color:<?= $net >= 0 ? '#16a34a' : '#b91c1c' ?>"><?= e(format_price($net)) ?></strong></div>
        <a class="stat-card" href="admin.php?page=statements" style="border-color:#fca5a5;background:#fef2f2"><span>کل بدهی مشتریان</span><strong style="color:#b91c1c"><?= e(format_price($finDebtTotal)) ?></strong></a>
        <a class="stat-card" href="admin.php?page=expenses&status=pending" style="border-color:#fcd34d;background:#fffbeb"><span>هزینه‌های در انتظار تأیید</span><strong style="color:#92400e"><?= (int) $finPending['count'] ?> مورد — <?= e(format_price($finPending['sum'])) ?> تومان</strong></a>
    </div>

    <section class="card wide">
        <h2>هزینه‌های در انتظار تأیید مدیر</h2>
        <?php if ($finPendingList === []): ?>
            <p class="muted">موردی در انتظار تأیید نیست. خریدهای مواد از انبار، اینجا «ثبت اولیه» می‌شوند و بعد از تأیید شما هزینه قطعی به حساب می‌آیند.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>عنوان</th><th>دسته</th><th>مبلغ</th><th>تاریخ</th><th>منبع</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($finPendingList as $ex): ?>
                <tr>
                    <td><?= e($ex['title']) ?></td>
                    <td><span class="badge" style="background:<?= e(finance_cat_color((string) $ex['cat_key'])) ?>22;color:<?= e(finance_cat_color((string) $ex['cat_key'])) ?>"><?= e(finance_cat_title((string) $ex['cat_key'])) ?></span></td>
                    <td><strong><?= e(format_price($ex['amount'])) ?></strong></td>
                    <td class="muted"><?= e($ex['expense_date'] ?? $ex['created_at']) ?></td>
                    <td class="muted"><?= (string) $ex['source'] === 'material_purchase' ? 'خرید مواد (خودکار)' : 'دستی' ?></td>
                    <td style="white-space:nowrap">
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_expense"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button type="submit" class="btn small primary">✓ تأیید</button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('این هزینه رد شود و در گزارش‌ها حساب نشود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="reject_expense"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button type="submit" class="btn small">رد</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card wide">
        <h2>سفارش‌های تسویه‌نشده</h2>
        <?php if ($finUnpaidOrders === []): ?>
            <p class="muted">همه سفارش‌ها تسویه‌اند. 🎉</p>
        <?php else: ?>
        <table>
            <thead><tr><th>سفارش</th><th>مشتری</th><th>مبلغ قابل پرداخت</th><th>دریافت‌شده</th><th>مانده</th><th>فاکتور</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($finUnpaidOrders as $o): ?>
                <tr>
                    <td><a href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>">#<?= (int) $o['order_no'] ?></a></td>
                    <td><?= e($o['customer_name'] ?? '—') ?></td>
                    <td><?= e(format_price(finance_order_gross($o))) ?></td>
                    <td><?= e(format_price($o['paid'])) ?></td>
                    <td><strong style="color:#b91c1c"><?= e(format_price($o['due'])) ?></strong></td>
                    <td><?= (int) $o['has_invoice'] === 1 ? 'صادر شده' : '<span class="muted">ندارد</span>' ?></td>
                    <td><a class="btn small" href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>">مشاهده و دریافت</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card wide">
        <h2>سود سفارش‌ها (بدون مالیات)</h2>
        <p class="muted">درآمد سفارش منهای بهای مواد و هزینه‌های مستقیم همان سفارش. بهای مواد: اول از روی مصرف واقعی تولید؛ اگر برگه تولید شروع نشده باشد، از روی فرمول ساخت (BOM) و آخرین قیمت خرید برآورد می‌شود.</p>
        <?php if ($finProfitRows === []): ?>
            <p class="muted">هنوز سفارشی با فاکتور یا برگه تولید نیست تا سودش حساب شود.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>سفارش</th><th>مشتری</th><th>درآمد</th><th>بهای مواد</th><th>مبنای بهای مواد</th><th>هزینه مستقیم</th><th>سود</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($finProfitRows as $row): $o = $row['order']; ?>
                <tr>
                    <td><a href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>">#<?= (int) $o['order_no'] ?></a></td>
                    <td><?= e($o['customer_name'] ?? '—') ?></td>
                    <td><?= e(format_price($row['revenue'])) ?></td>
                    <td><?= e(format_price($row['material_cost'])) ?></td>
                    <td class="muted"><?= $row['basis'] === 'production' ? 'مصرف واقعی تولید' : 'برآورد BOM' ?></td>
                    <td><?= e(format_price($row['linked_expenses'])) ?></td>
                    <td><strong style="color:<?= $row['profit'] >= 0 ? '#16a34a' : '#b91c1c' ?>"><?= e(format_price($row['profit'])) ?></strong></td>
                    <td><a class="btn small" href="admin.php?page=invoice_view&order_id=<?= (int) $o['id'] ?>">فاکتور</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px">
        <section class="card">
            <h2>آخرین دریافتی‌ها</h2>
            <?php if ($finRecentPayments === []): ?>
                <p class="muted">هنوز دریافتی ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>مبلغ</th><th>سفارش</th><th>روش</th></tr></thead>
                <tbody>
                <?php foreach ($finRecentPayments as $p): ?>
                    <tr>
                        <td><strong><?= e(format_price($p['amount'])) ?></strong><br><small class="muted"><?= e(finance_payment_kind_label((string) $p['kind'])) ?> — <?= e(mb_substr((string) $p['paid_at'], 0, 10)) ?></small></td>
                        <td><a href="admin.php?page=order_view&id=<?= (int) $p['order_id'] ?>">#<?= (int) $p['order_no'] ?></a><br><small class="muted"><?= e($p['customer_name'] ?? '') ?></small></td>
                        <td><?= e(finance_method_title((string) $p['method_key'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <section class="card">
            <h2>آخرین هزینه‌ها</h2>
            <?php if ($finRecentExpenses === []): ?>
                <p class="muted">هنوز هزینه‌ای ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>عنوان</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($finRecentExpenses as $ex): ?>
                    <tr>
                        <td><?= e($ex['title']) ?><br><small class="muted"><?= e(finance_cat_title((string) $ex['cat_key'])) ?> — <?= e($ex['expense_date'] ?? $ex['created_at']) ?></small></td>
                        <td><?= e(format_price($ex['amount'])) ?></td>
                        <td><?= e(finance_expense_status_label((string) $ex['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>
    <?php
}

/** رندر فهرست فاکتورها */
function finance_render_invoices(array $d): void
{
    extract($d);
    ?>
    <h1>فاکتورها</h1>
    <p class="muted">فاکتور ساده و چاپی؛ برای هر سفارش از صفحه همان سفارش صادر می‌شود. مالیات بر ارزش افزوده فقط وقتی اعمال می‌شود که هنگام صدور، تیک «اعمال مالیات» خورده باشد.</p>

    <section class="card wide">
        <?php if ($finInvoices === []): ?>
            <p class="muted">هنوز فاکتوری صادر نشده است. وارد یک سفارش شوید و «صدور فاکتور» را بزنید.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>شماره فاکتور</th><th>سفارش</th><th>مشتری</th><th>صدور</th><th>جمع فاکتور</th><th>مالیات</th><th>دریافت‌شده</th><th>مانده</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($finInvoices as $inv): ?>
                <tr>
                    <td><strong>#<?= (int) $inv['invoice_no'] ?></strong></td>
                    <td><a href="admin.php?page=order_view&id=<?= (int) $inv['order_id'] ?>">سفارش #<?= (int) $inv['order_no'] ?></a></td>
                    <td><?= e($inv['customer_name'] ?? '—') ?><br><small class="muted" dir="ltr"><?= e($inv['customer_mobile'] ?? '') ?></small></td>
                    <td class="muted"><?= e(mb_substr((string) $inv['issued_at'], 0, 10)) ?></td>
                    <td><strong><?= e(format_price($inv['total'])) ?></strong></td>
                    <td><?= (int) $inv['vat_applied'] === 1 ? e(format_price($inv['vat_amount'])) . ' (' . e(format_price($inv['vat_percent'])) . '٪)' : '<span class="muted">—</span>' ?></td>
                    <td><?= e(format_price($inv['paid'])) ?></td>
                    <td><strong style="color:<?= (int) $inv['due'] > 0 ? '#b91c1c' : '#16a34a' ?>"><?= e(format_price($inv['due'])) ?></strong></td>
                    <td style="white-space:nowrap">
                        <a class="btn small" href="admin.php?page=invoice_view&order_id=<?= (int) $inv['order_id'] ?>">مشاهده/چاپ</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('فاکتور <?= (int) $inv['invoice_no'] ?> باطل شود؟ بعدش می‌توانید با تنظیم تازه دوباره صادر کنید.')"><?= csrf_field() ?><input type="hidden" name="action" value="void_invoice"><input type="hidden" name="id" value="<?= (int) $inv['id'] ?>"><button type="submit" class="btn small danger-btn">ابطال</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    <?php
}

/** رندر صفحه فاکتور یک سفارش (نمایش و چاپ فاکتور ساده) */
function finance_render_invoice_view(array $d): void
{
    extract($d);
    $o = $finInvOrder;
    if ($o === null) {
        echo '<h1>سفارش پیدا نشد</h1><p><a href="admin.php?page=invoices">بازگشت به فاکتورها</a></p>';
        return;
    }
    $print = isset($_GET['print']);
    $inv = finance_order_invoice((int) $o['id']);
    $settings = all_settings();
    $paid = finance_order_paid((int) $o['id']);
    $gross = finance_order_gross($o);
    $due = $gross - $paid;
    $vatPercent = (float) get_setting('vat_percent', '0');
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .invoice-sheet { display: block !important; border: none !important; }
    }
    .invoice-sheet { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
    .invoice-sheet h2 { margin-top: 0; }
    .invoice-sheet table { width: 100%; }
    .sig-row { display: flex; gap: 40px; margin-top: 40px; }
    .sig-row div { flex: 1; border-top: 1px dashed #9ca3af; padding-top: 8px; text-align: center; }
    </style>
    <div class="screen-area">
        <p><a href="admin.php?page=invoices">← بازگشت به فاکتورها</a> | <a href="admin.php?page=order_view&id=<?= (int) $o['id'] ?>">مشاهده سفارش #<?= (int) $o['order_no'] ?></a></p>
        <h1>فاکتور سفارش #<?= (int) $o['order_no'] ?></h1>

        <?php if ($inv === null): ?>
        <section class="card wide">
            <h2>صدور فاکتور</h2>
            <p class="muted">این سفارش هنوز فاکتور ندارد. شماره فاکتور بعدی: <strong><?= (int) get_setting('next_invoice_no', '1') ?></strong> — مبلغ سفارش: <?= e(format_price($o['total'])) ?> تومان (جمع ردیف‌ها <?= e(format_price($o['subtotal'])) ?> − تخفیف <?= e(format_price($o['discount_amount'])) ?>).</p>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="issue_invoice">
                <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                <label style="display:flex;gap:8px;align-items:center">
                    <input type="checkbox" name="apply_vat" value="1">
                    اعمال مالیات بر ارزش افزوده (<?= e(format_price($vatPercent)) ?>٪ روی مبلغ بعد از تخفیف: <?= e(format_price((int) round(max(0, (int) $o['subtotal'] - (int) $o['discount_amount']) * $vatPercent / 100))) ?> تومان)
                </label>
                <label>یادداشت فاکتور (اختیاری)
                    <input type="text" name="note" maxlength="300" placeholder="مثلاً: پرداخت تا پایان هفته">
                </label>
                <button type="submit" class="btn primary">صدور فاکتور</button>
            </form>
        </section>
        <?php else: ?>
        <section class="card wide">
            <h2>فاکتور #<?= (int) $inv['invoice_no'] ?> صادر شده است</h2>
            <p class="muted">صدور: <?= e($inv['issued_at']) ?> — جمع فاکتور: <strong><?= e(format_price($inv['total'])) ?> تومان</strong><?= (int) $inv['vat_applied'] === 1 ? ' (شامل مالیات ' . e(format_price($inv['vat_amount'])) . ' تومان با نرخ ' . e(format_price($inv['vat_percent'])) . '٪)' : ' (بدون مالیات)' ?> — دریافت‌شده: <?= e(format_price($paid)) ?> تومان — مانده: <strong style="color:<?= $due > 0 ? '#b91c1c' : '#16a34a' ?>"><?= e(format_price($due)) ?> تومان</strong></p>
            <p>
                <a class="btn primary small" href="admin.php?page=invoice_view&order_id=<?= (int) $o['id'] ?>&print=1" target="_blank">🖨 چاپ فاکتور</a>
                <form method="post" style="display:inline" onsubmit="return confirm('فاکتور باطل شود؟ بعدش می‌توانید دوباره صادر کنید.')"><?= csrf_field() ?><input type="hidden" name="action" value="void_invoice"><input type="hidden" name="id" value="<?= (int) $inv['id'] ?>"><button type="submit" class="btn small danger-btn">ابطال فاکتور</button></form>
            </p>
        </section>
        <?php endif; ?>

        <section class="card wide">
            <h2>دریافتی‌های این سفارش</h2>
            <form method="post" class="inline-fields" style="margin-bottom:12px">
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
                <label style="flex:1">توضیح<input type="text" name="note" maxlength="200"></label>
                <button type="submit" class="btn primary" style="align-self:end">+ ثبت دریافتی</button>
            </form>
            <?php if ($finInvPayments === []): ?>
                <p class="muted">هنوز دریافتی ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>مبلغ</th><th>نوع</th><th>روش</th><th>تاریخ</th><th>توضیح</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($finInvPayments as $p): ?>
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
    </div>

    <?php if ($inv !== null): ?>
    <div class="invoice-sheet" id="invoice-sheet"<?= $print ? '' : ' style="display:none"' ?>>
        <h2>فاکتور فروش #<?= (int) $inv['invoice_no'] ?></h2>
        <p class="muted"><?= e($settings['site_title'] ?? '') ?> — سفارش #<?= (int) $o['order_no'] ?> — تاریخ صدور: <?= e(mb_substr((string) $inv['issued_at'], 0, 10)) ?></p>
        <table><tbody>
            <tr><th>خریدار</th><td><?= e($finInvCustomer['full_name'] ?? '—') ?><?= !empty($finInvCustomer['company']) ? ' — ' . e($finInvCustomer['company']) : '' ?></td></tr>
            <tr><th>موبایل</th><td><span dir="ltr"><?= e($finInvCustomer['mobile'] ?? '') ?></span></td></tr>
            <?php if (!empty($finInvCustomer['address'])): ?><tr><th>نشانی</th><td><?= e($finInvCustomer['address']) ?></td></tr><?php endif; ?>
        </tbody></table>
        <table>
            <thead><tr><th>#</th><th>محصول</th><th>طول (سانت)</th><th>تعداد</th><th>متراژ صورتحساب</th><th>قیمت واحد/متر (تومان)</th><th>مبلغ ردیف (تومان)</th></tr></thead>
            <tbody>
            <?php $rn = 0; foreach ($finInvItems as $it): $rn++; ?>
                <tr>
                    <td><?= $rn ?></td>
                    <td><?= e($it['product_name']) ?>
                        <?php $oj = json_decode((string) ($it['options_json'] ?? ''), true); if (is_array($oj) && $oj !== []): ?>
                            <br><small class="muted"><?php foreach ($oj as $osnap): ?><?= e($osnap['attr'] ?? '') ?>: <?= e($osnap['option'] ?? '') ?>؛ <?php endforeach; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_qty((float) $it['length_cm'])) ?></td>
                    <td><?= (int) $it['qty'] ?></td>
                    <td><?= e(format_qty((float) $it['billable_m'])) ?> متر</td>
                    <td><?= e(format_price($it['unit_price_per_m'])) ?></td>
                    <td><?= e(format_price($it['line_total'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table><tbody>
            <tr><th>جمع ردیف‌ها</th><td><?= e(format_price($inv['subtotal'])) ?> تومان</td></tr>
            <?php if ((int) $inv['discount'] > 0): ?>
            <tr><th>تخفیف</th><td><?= e(format_price($inv['discount'])) ?> تومان</td></tr>
            <?php endif; ?>
            <?php if ((int) $inv['vat_applied'] === 1): ?>
            <tr><th>مالیات بر ارزش افزوده (<?= e(format_price($inv['vat_percent'])) ?>٪)</th><td><?= e(format_price($inv['vat_amount'])) ?> تومان</td></tr>
            <?php endif; ?>
            <tr><th><big>مبلغ قابل پرداخت</big></th><td><big><strong><?= e(format_price($inv['total'])) ?> تومان</strong></big></td></tr>
            <?php if ($finInvPayments !== []): ?>
            <tr><th>دریافت‌شده</th><td><?= e(format_price($paid)) ?> تومان</td></tr>
            <tr><th>مانده</th><td><strong><?= e(format_price($due)) ?> تومان</strong></td></tr>
            <?php endif; ?>
        </tbody></table>
        <?php if (!empty($inv['note'])): ?>
            <h3>توضیحات فاکتور</h3><p><?= nl2br(e($inv['note'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($settings['payment_terms'] ?? '')) !== ''): ?>
            <h3>شرایط پرداخت</h3><p><?= nl2br(e($settings['payment_terms'])) ?></p>
        <?php endif; ?>
        <?php if (trim((string) ($settings['warranty_text'] ?? '')) !== ''): ?>
            <h3>گارانتی</h3><p><?= nl2br(e($settings['warranty_text'])) ?></p>
        <?php endif; ?>
        <div class="sig-row"><div>امضای فروشنده</div><div>امضای خریدار</div></div>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php endif; ?>
    <?php
}

// ---------- رندر صفحه هزینه‌ها ----------

function finance_render_expenses(array $d): void
{
    extract($d);
    $edit = $finEditExpense;
    ?>
    <h1>هزینه‌ها</h1>
    <p class="muted">خرید مواد از انبار به‌صورت «ثبت اولیه» وارد می‌شود و بعد از تأیید مدیر هزینه قطعی می‌شود. هزینه‌های دستی از همان لحظه قطعی‌اند. هزینه مستقیم هر سفارش را می‌توانید به خود سفارش وصل کنید تا در سود آن حساب شود.</p>

    <?php if ((int) $finPending['count'] > 0 && $finExpenseFilter !== 'pending'): ?>
        <p style="background:#fffbeb;border:1px solid #fcd34d;border-radius:10px;padding:10px 14px">
            ⚠ <?= (int) $finPending['count'] ?> هزینه (جمع <?= e(format_price($finPending['sum'])) ?> تومان) در انتظار تأیید مدیر است.
            <a href="admin.php?page=expenses&status=pending">مشاهده و بررسی</a>
        </p>
    <?php endif; ?>

    <section class="card wide">
        <h2><?= $edit !== null ? 'ویرایش هزینه #' . (int) $edit['id'] : 'ثبت هزینه جدید' ?></h2>
        <?php if ($edit !== null && (string) $edit['source'] === 'material_purchase'): ?>
            <p class="muted">این هزینه از خرید مواد انبار به‌صورت خودکار ساخته شده است؛ عنوان، مبلغ و دسته‌اش از روی همان تراکنش قفل است و فقط تاریخ/توضیح/وصل‌کردن به سفارش قابل ویرایش است.</p>
        <?php endif; ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit !== null ? 'update_expense' : 'add_expense' ?>">
            <?php if ($edit !== null): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <div class="inline-fields">
                <label style="flex:2">عنوان
                    <input type="text" name="title" maxlength="200" required value="<?= e($edit['title'] ?? '') ?>" <?= ($edit !== null && (string) $edit['source'] === 'material_purchase') ? 'readonly' : '' ?>>
                </label>
                <label>دسته
                    <select name="cat_key">
                        <?php foreach ($finCats as $c): ?>
                            <option value="<?= e($c['cat_key']) ?>" <?= ($edit !== null && (string) $edit['cat_key'] === (string) $c['cat_key']) ? 'selected' : '' ?>><?= e($c['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>مبلغ (تومان)
                    <input type="number" name="amount" min="1" step="1" required value="<?= e((string) ($edit['amount'] ?? '')) ?>" <?= ($edit !== null && (string) $edit['source'] === 'material_purchase') ? 'readonly' : '' ?>>
                </label>
                <label>تاریخ
                    <input type="date" name="expense_date" value="<?= e($edit['expense_date'] ?? date('Y-m-d')) ?>">
                </label>
            </div>
            <div class="inline-fields">
                <label style="flex:1">توضیح
                    <input type="text" name="note" maxlength="300" value="<?= e($edit['note'] ?? '') ?>">
                </label>
                <label style="flex:1">وصل به سفارش (شماره سفارش، اختیاری)
                    <input type="number" name="order_no" min="1" step="1" placeholder="مثلاً 12"
                        value="<?= ($edit !== null && (int) ($edit['order_id'] ?? 0) > 0) ? e((string) db()->query('SELECT order_no FROM orders WHERE id = ' . (int) $edit['order_id'])->fetchColumn()) : '' ?>">
                </label>
                <?php if ($edit === null): ?>
                <label style="flex:1">وضعیت ثبت
                    <select name="status">
                        <option value="confirmed">قطعی (همین حالا هزینه نهایی)</option>
                        <option value="pending">ثبت اولیه (بعد از تأیید مدیر)</option>
                    </select>
                </label>
                <?php endif; ?>
                <button type="submit" class="btn primary" style="align-self:end"><?= $edit !== null ? 'ذخیره' : '+ ثبت هزینه' ?></button>
                <?php if ($edit !== null): ?><a class="btn" href="admin.php?page=expenses" style="align-self:end">انصراف</a><?php endif; ?>
            </div>
        </form>
    </section>

    <p>
        <a class="btn small<?= $finExpenseFilter === '' ? ' primary' : '' ?>" href="admin.php?page=expenses">همه</a>
        <a class="btn small<?= $finExpenseFilter === 'pending' ? ' primary' : '' ?>" href="admin.php?page=expenses&status=pending">در انتظار تأیید (<?= (int) $finPending['count'] ?>)</a>
        <a class="btn small<?= $finExpenseFilter === 'confirmed' ? ' primary' : '' ?>" href="admin.php?page=expenses&status=confirmed">قطعی</a>
        <a class="btn small<?= $finExpenseFilter === 'rejected' ? ' primary' : '' ?>" href="admin.php?page=expenses&status=rejected">ردشده</a>
    </p>

    <section class="card wide">
        <?php if ($finExpenses === []): ?>
            <p class="muted">در این نما هزینه‌ای نیست.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>عنوان</th><th>دسته</th><th>مبلغ</th><th>تاریخ</th><th>وضعیت</th><th>منبع</th><th>سفارش</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($finExpenses as $ex): ?>
                <tr>
                    <td><?= e($ex['title']) ?><?php if (!empty($ex['note'])): ?><br><small class="muted"><?= e($ex['note']) ?></small><?php endif; ?></td>
                    <td><span class="badge" style="background:<?= e(finance_cat_color((string) $ex['cat_key'])) ?>22;color:<?= e(finance_cat_color((string) $ex['cat_key'])) ?>"><?= e(finance_cat_title((string) $ex['cat_key'])) ?></span></td>
                    <td><strong><?= e(format_price($ex['amount'])) ?></strong></td>
                    <td class="muted"><?= e($ex['expense_date'] ?? $ex['created_at']) ?></td>
                    <td><?= e(finance_expense_status_label((string) $ex['status'])) ?></td>
                    <td class="muted"><?= (string) $ex['source'] === 'material_purchase' ? 'خرید مواد (خودکار)' : 'دستی' ?></td>
                    <td><?= !empty($ex['linked_order_no']) ? '<a href="admin.php?page=order_view&id=' . (int) $ex['order_id'] . '">#' . (int) $ex['linked_order_no'] . '</a>' : '—' ?></td>
                    <td style="white-space:nowrap">
                        <?php if ((string) $ex['status'] === 'pending'): ?>
                            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_expense"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button type="submit" class="btn small primary">✓ تأیید</button></form>
                            <form method="post" style="display:inline" onsubmit="return confirm('این هزینه رد شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="reject_expense"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button type="submit" class="btn small">رد</button></form>
                        <?php endif; ?>
                        <a class="btn small" href="admin.php?page=expenses&edit_id=<?= (int) $ex['id'] ?>">ویرایش</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('این هزینه حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="id" value="<?= (int) $ex['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <p class="muted">جمع این نما: <?= e(format_price(array_sum(array_map(static fn ($r) => (int) $r['amount'], $finExpenses)))) ?> تومان.</p>
    </section>
    <?php
}

// ---------- رندر صورتحساب مشتری ----------

function finance_render_statements(array $d): void
{
    extract($d);
    $print = isset($_GET['print']);
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .stmt-sheet { display: block !important; border: none !important; }
    }
    .stmt-sheet { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
    .stmt-sheet table { width: 100%; }
    </style>
    <div class="screen-area">
    <h1>صورتحساب مشتریان</h1>
    <p class="muted">مبلغ هر سفارش: اگر فاکتور دارد مبلغ فاکتور، وگرنه مبلغ سفارش. بدهی = جمع مبالغ − جمع دریافتی‌ها.</p>

    <section class="card wide">
        <h2>بدهکاران (<?= e(format_price($finDebtTotal)) ?> تومان)</h2>
        <?php if ($finDebtors === []): ?>
            <p class="muted">هیچ مشتری بدهکاری نیست. 🎉</p>
        <?php else: ?>
        <table>
            <thead><tr><th>مشتری</th><th>موبایل</th><th>جمع بدهی</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($finDebtors as $deb): ?>
                <tr>
                    <td><strong><?= e($deb['customer']['full_name']) ?></strong><?= !empty($deb['customer']['company']) ? ' — ' . e($deb['customer']['company']) : '' ?></td>
                    <td dir="ltr"><?= e($deb['customer']['mobile'] ?? '') ?></td>
                    <td><strong style="color:#b91c1c"><?= e(format_price($deb['balance'])) ?></strong></td>
                    <td><a class="btn small" href="admin.php?page=statements&customer_id=<?= (int) $deb['customer']['id'] ?>">صورتحساب</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <form method="get" class="inline-fields" style="margin-top:12px">
            <input type="hidden" name="page" value="statements">
            <label style="flex:1">انتخاب مشتری
                <select name="customer_id" required>
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($finCustomers as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= ($finStmtCustomer !== null && (int) $finStmtCustomer['id'] === (int) $c['id']) ? 'selected' : '' ?>><?= e($c['full_name']) ?><?= !empty($c['company']) ? ' — ' . e($c['company']) : '' ?> (<?= e($c['mobile']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn primary" style="align-self:end">نمایش صورتحساب</button>
        </form>
    </section>

    <?php if ($finStmtCustomer !== null): ?>
        <p>
            <a class="btn small primary" href="admin.php?page=statements&customer_id=<?= (int) $finStmtCustomer['id'] ?>&print=1" target="_blank">🖨 چاپ صورتحساب</a>
            مانده این مشتری: <strong style="color:<?= $finStmtBalance > 0 ? '#b91c1c' : '#16a34a' ?>"><?= e(format_price($finStmtBalance)) ?> تومان<?= $finStmtBalance > 0 ? ' (بدهکار)' : '' ?></strong>
        </p>
    <?php endif; ?>
    </div>

    <?php if ($finStmtCustomer !== null): ?>
    <div class="stmt-sheet"<?= $print ? '' : ' style="display:none"' ?>>
        <h2>صورتحساب — <?= e($finStmtCustomer['full_name']) ?></h2>
        <p class="muted"><?= e(all_settings()['site_title'] ?? '') ?> — تاریخ تهیه: <?= e(date('Y-m-d')) ?></p>
        <table>
            <thead><tr><th>سفارش</th><th>فاکتور</th><th>تاریخ</th><th>مبلغ (تومان)</th><th>دریافت‌شده</th><th>مانده</th></tr></thead>
            <tbody>
            <?php foreach ($finStmtOrders as $o): ?>
                <tr>
                    <td>#<?= (int) $o['order_no'] ?> (<?= e(order_status_title((string) $o['status'])) ?>)</td>
                    <td><?= $o['invoice_no'] !== null ? '#' . (int) $o['invoice_no'] : '—' ?></td>
                    <td class="muted"><?= e(mb_substr((string) $o['created_at'], 0, 10)) ?></td>
                    <td><?= e(format_price($o['gross'])) ?></td>
                    <td><?= e(format_price($o['paid'])) ?></td>
                    <td><strong><?= e(format_price($o['due'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <table><tbody>
            <tr><th>جمع مانده (بدهی مشتری)</th><td><big><strong><?= e(format_price($finStmtBalance)) ?> تومان</strong></big></td></tr>
        </tbody></table>
        <?php if ($finStmtPayments !== []): ?>
            <h3>دریافتی‌ها</h3>
            <table>
                <thead><tr><th>سفارش</th><th>مبلغ</th><th>روش</th><th>نوع</th><th>تاریخ</th></tr></thead>
                <tbody>
                <?php foreach ($finStmtPayments as $p): ?>
                    <tr>
                        <td>#<?= (int) $p['order_no'] ?></td>
                        <td><?= e(format_price($p['amount'])) ?> تومان</td>
                        <td><?= e(finance_method_title((string) $p['method_key'])) ?></td>
                        <td><?= e(finance_payment_kind_label((string) $p['kind'])) ?></td>
                        <td class="muted"><?= e(mb_substr((string) $p['paid_at'], 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php endif; ?>
    <?php
}

// ---------- رندر قوانین مالی ----------

function finance_render_rules(array $d): void
{
    extract($d);
    ?>
    <h1>قوانین مالی</h1>
    <p class="muted">روش‌های دریافت، دسته‌های هزینه، درصد مالیات و شماره فاکتور بعدی — همه از همین‌جا قابل تغییرند و هیچ‌کدام در کد ثابت نیستند.</p>

    <section class="card wide">
        <h2>تنظیمات فاکتور</h2>
        <form method="post" class="inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_finance_settings">
            <label>مالیات بر ارزش افزوده (٪)<input type="number" name="vat_percent" min="0" max="100" step="0.1" value="<?= e(format_qty($finVatPercent)) ?>"></label>
            <label>شماره فاکتور بعدی<input type="number" name="next_invoice_no" min="1" step="1" value="<?= (int) $finNextInvoiceNo ?>"></label>
            <button type="submit" class="btn primary" style="align-self:end">ذخیره</button>
        </form>
        <p class="muted">مالیات فقط وقتی روی فاکتور می‌آید که هنگام صدور، تیک «اعمال مالیات بر ارزش افزوده» خورده باشد؛ درصد و مبلغش همان لحظه در فاکتور ثبت می‌شود و با تغییر بعدی این عدد عوض نمی‌شود.</p>
    </section>

    <section class="card wide">
        <h2>روش‌های دریافت</h2>
        <p class="muted">در فرم ثبت دریافتی سفارش، فقط روش‌های فعال دیده می‌شوند. روشِ استفاده‌شده حذف نمی‌شود.</p>
        <table>
            <thead><tr><th>ترتیب</th><th>عنوان</th><th>کلید</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($finMethodsAll as $m): ?>
                <tr>
                    <td>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_payment_method"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="dir" value="up"><button type="submit" class="btn small">↑</button></form>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_payment_method"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="dir" value="down"><button type="submit" class="btn small">↓</button></form>
                    </td>
                    <td>
                        <form method="post" class="inline-fields">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_payment_method">
                            <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                            <input type="text" name="title" value="<?= e($m['title']) ?>" maxlength="60" required>
                            <button type="submit" class="btn small">ذخیره</button>
                        </form>
                    </td>
                    <td class="muted"><?= e($m['method_key']) ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_payment_method"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="btn small"><?= (int) $m['is_active'] === 1 ? 'فعال — غیرفعالش کن' : 'غیرفعال — فعالش کن' ?></button></form>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('این روش حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_payment_method"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="inline-fields" style="margin-top:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_payment_method">
            <label>عنوان روش جدید<input type="text" name="title" maxlength="60" required placeholder="مثلاً: کارت‌به‌کارت"></label>
            <label>کلید (انگلیسی، ثابت)<input type="text" name="method_key" maxlength="30" required placeholder="مثلاً: card2card" dir="ltr"></label>
            <button type="submit" class="btn primary" style="align-self:end">+ افزودن روش</button>
        </form>
    </section>

    <section class="card wide">
        <h2>دسته‌های هزینه</h2>
        <p class="muted">دسته «خرید مواد اولیه» سیستمی است: خریدهای انبار خودکار واردش می‌شوند، برای همین پاک نمی‌شود (ولی عنوان و رنگش عوض می‌شود).</p>
        <table>
            <thead><tr><th>ترتیب</th><th>عنوان</th><th>رنگ</th><th>کلید</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($finCatsAll as $c): ?>
                <tr>
                    <td>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_expense_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="dir" value="up"><button type="submit" class="btn small">↑</button></form>
                        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_expense_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><input type="hidden" name="dir" value="down"><button type="submit" class="btn small">↓</button></form>
                    </td>
                    <td>
                        <form method="post" class="inline-fields">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="update_expense_category">
                            <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                            <input type="text" name="title" value="<?= e($c['title']) ?>" maxlength="60" required>
                            <input type="color" name="color" value="<?= e((string) $c['color']) ?>">
                            <button type="submit" class="btn small">ذخیره</button>
                        </form>
                    </td>
                    <td><span class="badge" style="background:<?= e((string) $c['color']) ?>22;color:<?= e((string) $c['color']) ?>">نمونه</span></td>
                    <td class="muted"><?= e($c['cat_key']) ?></td>
                    <td>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_expense_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button type="submit" class="btn small"><?= (int) $c['is_active'] === 1 ? 'فعال — غیرفعالش کن' : 'غیرفعال — فعالش کن' ?></button></form>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('این دسته حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense_category"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" class="inline-fields" style="margin-top:12px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_expense_category">
            <label>عنوان دسته جدید<input type="text" name="title" maxlength="60" required placeholder="مثلاً: تبلیغات"></label>
            <label>کلید (انگلیسی، ثابت)<input type="text" name="cat_key" maxlength="30" required placeholder="مثلاً: ads" dir="ltr"></label>
            <label>رنگ<input type="color" name="color" value="#2563eb"></label>
            <button type="submit" class="btn primary" style="align-self:end">+ افزودن دسته</button>
        </form>
    </section>
    <?php
}
