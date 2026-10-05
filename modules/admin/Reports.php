<?php
// admin_reports.php — فاز ۶ (نسخه ۸٫۶٫۰): گزارش‌های مدیریتی
// گزارش فروش دوره‌ای، محصولات پرفروش، مصرف مواد اولیه، مشتریان و بدهی، و گزارش تولید.
// همه گزارش‌ها فقط خواندنی‌اند؛ بازه پیش‌فرض و تعداد ردیف‌های برتر از خودِ صفحه گزارش‌ها قابل تنظیم است.

declare(strict_types=1);

// ---------- اکشن‌های POST ----------

function reports_post_actions(): array
{
    return ['save_report_settings'];
}

function reports_handle_post(string $action): void
{
    if ($action !== 'save_report_settings') {
        return;
    }
    $range = (string) ($_POST['reports_default_range'] ?? 'month');
    if (!in_array($range, reports_range_presets(), true)) {
        $range = 'month';
    }
    $limit = (int) ($_POST['reports_top_limit'] ?? 10);
    if ($limit < 3) {
        $limit = 3;
    }
    if ($limit > 50) {
        $limit = 50;
    }
    set_setting('reports_default_range', $range);
    set_setting('reports_top_limit', (string) $limit);
    flash('ok', 'تنظیمات گزارش‌ها ذخیره شد.');
    redirect_admin('admin.php?page=reports');
}

// ---------- بازه‌های تاریخی ----------

/** کلیدهای بازه‌های آماده (به‌جز custom که از ورودی کاربر می‌آید) */
function reports_range_presets(): array
{
    return ['today', 'last7', 'last30', 'month', 'prev_month', 'year', 'all'];
}

/** برچسب فارسی هر بازه آماده */
function reports_range_label(string $key): string
{
    return [
        'today'      => 'امروز',
        'last7'      => '۷ روز اخیر',
        'last30'     => '۳۰ روز اخیر',
        'month'      => 'این ماه',
        'prev_month' => 'ماه گذشته',
        'year'       => 'امسال',
        'all'        => 'همهٔ زمان‌ها',
        'custom'     => 'بازه دلخواه',
    ][$key] ?? $key;
}

/**
 * بازه فعال را از GET یا تنظیم پیش‌فرض برمی‌گرداند:
 * ['preset' => ..., 'from' => 'Y-m-d'|null, 'to' => 'Y-m-d'|null]
 * null یعنی بدون محدودیت آن سمت.
 */
function reports_resolve_range(): array
{
    $preset = (string) ($_GET['range'] ?? '');
    $from = trim((string) ($_GET['from'] ?? ''));
    $to   = trim((string) ($_GET['to'] ?? ''));
    $validDate = static fn (string $s): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s);

    if ($preset === 'custom' || ($preset === '' && ($validDate($from) || $validDate($to)))) {
        return [
            'preset' => 'custom',
            'from'   => $validDate($from) ? $from : null,
            'to'     => $validDate($to) ? $to : null,
        ];
    }
    if (!in_array($preset, reports_range_presets(), true)) {
        $preset = (string) get_setting('reports_default_range', 'month');
        if (!in_array($preset, reports_range_presets(), true)) {
            $preset = 'month';
        }
    }
    $today = date('Y-m-d');
    switch ($preset) {
        case 'today':
            return ['preset' => $preset, 'from' => $today, 'to' => $today];
        case 'last7':
            return ['preset' => $preset, 'from' => date('Y-m-d', strtotime('-6 days')), 'to' => $today];
        case 'last30':
            return ['preset' => $preset, 'from' => date('Y-m-d', strtotime('-29 days')), 'to' => $today];
        case 'month':
            return ['preset' => $preset, 'from' => date('Y-m-01'), 'to' => $today];
        case 'prev_month':
            return ['preset' => $preset, 'from' => date('Y-m-01', strtotime('first day of last month')), 'to' => date('Y-m-t', strtotime('last day of last month'))];
        case 'year':
            return ['preset' => $preset, 'from' => date('Y-01-01'), 'to' => $today];
        default: // all
            return ['preset' => 'all', 'from' => null, 'to' => null];
    }
}

/** شرط تاریخ روی یک ستون متنی (Y-m-d H:i:s) + پارامترهایش */
function reports_date_cond(string $column, array $range, string $pfx): array
{
    $cond = '';
    $params = [];
    if ($range['from'] !== null) {
        $cond .= " AND substr($column, 1, 10) >= :{$pfx}f";
        $params[":{$pfx}f"] = $range['from'];
    }
    if ($range['to'] !== null) {
        $cond .= " AND substr($column, 1, 10) <= :{$pfx}t";
        $params[":{$pfx}t"] = $range['to'];
    }
    return [$cond, $params];
}

/** برچسب انسانی بازه برای عنوان گزارش */
function reports_range_text(array $range): string
{
    if ($range['preset'] !== 'custom') {
        $t = reports_range_label((string) $range['preset']);
        if ($range['from'] !== null && $range['to'] !== null) {
            return $t . ' (' . $range['from'] . ' تا ' . $range['to'] . ')';
        }
        return $t;
    }
    $f = $range['from'] ?? 'ابتدا';
    $t = $range['to'] ?? 'امروز';
    return "از $f تا $t";
}

// ---------- نمودار میله‌ای پاستیلی (SVG، هم‌سبک داشبورد ۸٫۵٫۰) ----------

function reports_bar_chart(array $bars, string $unitLabel = ''): string
{
    if ($bars === []) {
        return '<p class="muted">داده‌ای برای نمایش نیست.</p>';
    }
    $pastels = ['#f9a8d4', '#93c5fd', '#6ee7b7', '#fcd34d', '#c4b5fd', '#fda4af', '#7dd3fc', '#bef264'];
    $max = 1.0;
    foreach ($bars as $b) {
        $max = max($max, (float) $b['value']);
    }
    $w = max(320, count($bars) * 74);
    $h = 190;
    $base = 160;
    $bw = min(46, max(22, (int) (($w / max(1, count($bars))) * 0.55)));
    $gap = $w / count($bars);
    $out = '<svg viewBox="0 0 ' . $w . ' ' . $h . '" role="img" style="width:100%;height:auto;display:block" xmlns="http://www.w3.org/2000/svg">';
    $out .= '<line x1="8" y1="' . $base . '" x2="' . ($w - 8) . '" y2="' . $base . '" stroke="#e5e7eb" stroke-width="2"/>';
    $i = 0;
    foreach ($bars as $b) {
        $val = (float) $b['value'];
        $bh = max(2, (int) round($val / $max * 118));
        $x = (int) round(8 + $i * $gap + ($gap - $bw) / 2);
        $y = $base - $bh;
        $color = (string) ($b['color'] ?? $pastels[$i % count($pastels)]);
        $label = (string) ($b['label'] ?? '');
        $disp = $unitLabel !== '' ? number_format((int) $val) . ' ' . $unitLabel : number_format((int) $val);
        $out .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '" rx="7" fill="' . e($color) . '"/>';
        $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($y - 6) . '" font-size="11" font-weight="700" text-anchor="middle" fill="#374151">' . e($disp) . '</text>';
        $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($base + 16) . '" font-size="10.5" text-anchor="middle" fill="#6b7280">' . e(mb_substr($label, 0, 14)) . '</text>';
        $i++;
    }
    $out .= '</svg>';
    return $out;
}

// ---------- بارگذاری داده‌ها ----------

function reports_load_data(string $page): array
{
    $d = [
        'repType' => 'sales',
        'repRange' => ['preset' => 'month', 'from' => null, 'to' => null],
        'repRangeText' => '',
        'repTopLimit' => 10,
        // فروش
        'repSalesCards' => [],
        'repSalesRows' => [],
        'repSalesChart' => [],
        // محصولات
        'repProducts' => [],
        // مواد
        'repMatConsumption' => [],
        'repMatPurchases' => [],
        'repMatTotals' => ['cost' => 0, 'purchase' => 0, 'remnant_use_m' => 0.0, 'remnant_new_m' => 0.0],
        // مشتریان
        'repTopCustomers' => [],
        'repDebtors' => [],
        'repDebtTotal' => 0,
        // تولید
        'repProdCards' => [],
        'repProdStageChart' => [],
        'repProdSheets' => [],
    ];
    if ($page !== 'reports') {
        return $d;
    }

    $type = (string) ($_GET['type'] ?? 'sales');
    if (!in_array($type, ['sales', 'products', 'materials', 'customers', 'production'], true)) {
        $type = 'sales';
    }
    $d['repType'] = $type;
    $range = reports_resolve_range();
    $d['repRange'] = $range;
    $d['repRangeText'] = reports_range_text($range);
    $limit = (int) get_setting('reports_top_limit', '10');
    $d['repTopLimit'] = $limit >= 3 && $limit <= 50 ? $limit : 10;

    // خروجی CSV قبل از هر HTML (این تابع پیش از رندر صدا زده می‌شود)
    if ((string) ($_GET['export'] ?? '') === 'csv') {
        reports_fill($d);
        reports_export_csv($d);
        exit;
    }

    reports_fill($d);
    return $d;
}

/** پر کردن داده‌های گزارش فعال (برای رندر صفحه، چاپ و CSV مشترک است) */
function reports_fill(array &$d): void
{
    $type = (string) $d['repType'];
    $range = (array) $d['repRange'];
    $limit = (int) $d['repTopLimit'];
    $pdo = db();

    if ($type === 'sales') {
        [$cond, $params] = reports_date_cond('o.created_at', $range, 's');
        $stmt = $pdo->prepare(
            "SELECT substr(o.created_at, 1, 10) AS day, COUNT(*) AS cnt,
                    COALESCE(SUM(o.total), 0) AS sales, COALESCE(SUM(o.total_meters), 0) AS meters
             FROM orders o
             WHERE o.status != 'cancelled'$cond
             GROUP BY day ORDER BY day ASC"
        );
        $stmt->execute($params);
        $dayRows = $stmt->fetchAll();

        [$pCond, $pParams] = reports_date_cond('paid_at', $range, 'p');
        $stmt = $pdo->prepare(
            "SELECT substr(paid_at, 1, 10) AS day, COALESCE(SUM(amount), 0) AS received
             FROM payments WHERE 1 = 1$pCond GROUP BY day"
        );
        $stmt->execute($pParams);
        $payByDay = [];
        $totalReceived = 0;
        foreach ($stmt->fetchAll() as $r) {
            $payByDay[(string) $r['day']] = (int) $r['received'];
            $totalReceived += (int) $r['received'];
        }

        [$eCond, $eParams] = reports_date_cond('COALESCE(expense_date, created_at)', $range, 'e');
        $stmt = $pdo->prepare(
            "SELECT substr(COALESCE(expense_date, created_at), 1, 10) AS day, COALESCE(SUM(amount), 0) AS spent
             FROM expenses WHERE status = 'confirmed'$eCond GROUP BY day"
        );
        $stmt->execute($eParams);
        $expByDay = [];
        $totalExpenses = 0;
        foreach ($stmt->fetchAll() as $r) {
            $expByDay[(string) $r['day']] = (int) $r['spent'];
            $totalExpenses += (int) $r['spent'];
        }

        // دانه‌بندی: بازه‌های بلند (بیش از ۶۲ روز یا بدون کران) ماهانه، وگرنه روزانه
        $days = 999;
        if ($range['from'] !== null && $range['to'] !== null) {
            $days = (int) ((strtotime((string) $range['to']) - strtotime((string) $range['from'])) / 86400) + 1;
        }
        $monthly = $days > 62;
        $buckets = [];
        foreach ($dayRows as $r) {
            $key = $monthly ? substr((string) $r['day'], 0, 7) : (string) $r['day'];
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['label' => $key, 'orders' => 0, 'sales' => 0, 'meters' => 0.0, 'received' => 0, 'expenses' => 0];
            }
            $buckets[$key]['orders'] += (int) $r['cnt'];
            $buckets[$key]['sales'] += (int) $r['sales'];
            $buckets[$key]['meters'] += (float) $r['meters'];
        }
        foreach ($payByDay as $day => $v) {
            $key = $monthly ? substr($day, 0, 7) : $day;
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['label' => $key, 'orders' => 0, 'sales' => 0, 'meters' => 0.0, 'received' => 0, 'expenses' => 0];
            }
            $buckets[$key]['received'] += $v;
        }
        foreach ($expByDay as $day => $v) {
            $key = $monthly ? substr($day, 0, 7) : $day;
            if (!isset($buckets[$key])) {
                $buckets[$key] = ['label' => $key, 'orders' => 0, 'sales' => 0, 'meters' => 0.0, 'received' => 0, 'expenses' => 0];
            }
            $buckets[$key]['expenses'] += $v;
        }
        ksort($buckets);
        $d['repSalesRows'] = array_values($buckets);
        foreach ($d['repSalesRows'] as $b) {
            $d['repSalesChart'][] = ['label' => $b['label'], 'value' => (int) $b['sales']];
        }

        $totOrders = 0;
        $totSales = 0;
        $totMeters = 0.0;
        foreach ($dayRows as $r) {
            $totOrders += (int) $r['cnt'];
            $totSales += (int) $r['sales'];
            $totMeters += (float) $r['meters'];
        }
        $d['repSalesCards'] = [
            'orders'   => $totOrders,
            'sales'    => $totSales,
            'avg'      => $totOrders > 0 ? (int) round($totSales / $totOrders) : 0,
            'meters'   => $totMeters,
            'received' => $totalReceived,
            'expenses' => $totalExpenses,
            'net'      => $totalReceived - $totalExpenses,
        ];
    }

    if ($type === 'products') {
        [$cond, $params] = reports_date_cond('o.created_at', $range, 'pr');
        $stmt = $pdo->prepare(
            "SELECT oi.product_id, oi.product_name,
                    COUNT(DISTINCT oi.order_id) AS orders_cnt,
                    COALESCE(SUM(oi.qty), 0) AS fixtures,
                    COALESCE(SUM(oi.billable_m * oi.qty), 0) AS meters,
                    COALESCE(SUM(oi.line_total), 0) AS revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.status != 'cancelled'$cond
             GROUP BY oi.product_id, oi.product_name
             ORDER BY revenue DESC
             LIMIT $limit"
        );
        $stmt->execute($params);
        $d['repProducts'] = $stmt->fetchAll();
    }

    if ($type === 'materials') {
        [$cond, $params] = reports_date_cond('pc.created_at', $range, 'mc');
        $stmt = $pdo->prepare(
            "SELECT pc.material_id, pc.kind, pc.qty, m.name AS material_name, m.unit AS material_unit,
                    m.last_price, sm.unit_price AS movement_price
             FROM production_consumptions pc
             JOIN materials m ON m.id = pc.material_id
             LEFT JOIN stock_movements sm ON sm.id = pc.movement_id
             WHERE pc.reversed = 0$cond
             ORDER BY pc.id ASC LIMIT 20000"
        );
        $stmt->execute($params);
        $acc = [];
        foreach ($stmt->fetchAll() as $r) {
            $mid = (int) $r['material_id'];
            if (!isset($acc[$mid])) {
                $acc[$mid] = [
                    'name' => (string) $r['material_name'], 'unit' => (string) $r['material_unit'],
                    'out_qty' => 0.0, 'remnant_use_m' => 0.0, 'remnant_new_m' => 0.0, 'cost' => 0.0, 'sheets' => 0,
                ];
            }
            $kind = (string) $r['kind'];
            if ($kind === 'stock_out') {
                $price = $r['movement_price'] !== null ? (int) $r['movement_price'] : (int) $r['last_price'];
                $acc[$mid]['out_qty'] += (float) $r['qty'];
                $acc[$mid]['cost'] += (float) $r['qty'] * $price;
            } elseif ($kind === 'remnant_use') {
                $m = (float) $r['qty'] / 100;
                $acc[$mid]['remnant_use_m'] += $m;
                $acc[$mid]['cost'] += $m * (int) $r['last_price'];
                $d['repMatTotals']['remnant_use_m'] += $m;
            } elseif ($kind === 'remnant_new') {
                $m = (float) $r['qty'] / 100;
                $acc[$mid]['remnant_new_m'] += $m;
                $acc[$mid]['cost'] -= $m * (int) $r['last_price'];
                $d['repMatTotals']['remnant_new_m'] += $m;
            }
        }
        foreach ($acc as &$row) {
            $row['cost'] = (int) round($row['cost']);
            $d['repMatTotals']['cost'] += $row['cost'];
        }
        unset($row);
        uasort($acc, static fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);
        $d['repMatConsumption'] = array_values($acc);

        [$mCond, $mParams] = reports_date_cond('created_at', $range, 'mp');
        $stmt = $pdo->prepare(
            "SELECT m.name AS material_name, m.unit AS material_unit,
                    COALESCE(SUM(sm.qty), 0) AS qty, COALESCE(SUM(sm.qty * COALESCE(sm.unit_price, 0)), 0) AS amount
             FROM stock_movements sm
             JOIN materials m ON m.id = sm.material_id
             WHERE sm.move_type = 'in' AND COALESCE(sm.ref_type, '') != 'production'$mCond
             GROUP BY sm.material_id ORDER BY amount DESC LIMIT $limit"
        );
        $stmt->execute($mParams);
        $d['repMatPurchases'] = $stmt->fetchAll();
        foreach ($d['repMatPurchases'] as $r) {
            $d['repMatTotals']['purchase'] += (int) $r['amount'];
        }
    }

    if ($type === 'customers') {
        [$cond, $params] = reports_date_cond('o.created_at', $range, 'cu');
        $stmt = $pdo->prepare(
            "SELECT o.customer_id, c.full_name, c.company, c.mobile,
                    COUNT(*) AS orders_cnt, COALESCE(SUM(o.total), 0) AS revenue,
                    COALESCE(SUM(o.total_meters), 0) AS meters
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.status != 'cancelled'$cond
             GROUP BY o.customer_id ORDER BY revenue DESC LIMIT $limit"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $r['balance'] = finance_customer_balance((int) $r['customer_id']);
            $d['repTopCustomers'][] = $r;
        }
        // بدهکاران وضعیت فعلی‌اند (مستقل از بازه) — مثل داشبورد مالی
        $d['repDebtors'] = finance_debtors();
        foreach ($d['repDebtors'] as $deb) {
            $d['repDebtTotal'] += (int) $deb['balance'];
        }
    }

    if ($type === 'production') {
        [$cond, $params] = reports_date_cond('p.created_at', $range, 'po');
        $stmt = $pdo->prepare(
            "SELECT p.id, p.production_no, p.state, p.stage_key, p.started_at, p.finished_at, p.created_at,
                    o.order_no, c.full_name AS customer_name
             FROM production_orders p
             JOIN orders o ON o.id = p.order_id
             LEFT JOIN customers c ON c.id = o.customer_id
             WHERE 1 = 1$cond
             ORDER BY p.id DESC LIMIT 200"
        );
        $stmt->execute($params);
        $d['repProdSheets'] = $stmt->fetchAll();

        $created = count($d['repProdSheets']);
        $finished = 0;
        $cancelled = 0;
        $cycleDays = [];
        foreach ($d['repProdSheets'] as $s) {
            if ((string) $s['state'] === 'finished') {
                $finished++;
                if (!empty($s['started_at']) && !empty($s['finished_at'])) {
                    $cycleDays[] = (strtotime((string) $s['finished_at']) - strtotime((string) $s['started_at'])) / 86400;
                }
            } elseif ((string) $s['state'] === 'cancelled') {
                $cancelled++;
            }
        }
        $openNow = (int) $pdo->query("SELECT COUNT(*) FROM production_orders WHERE state = 'open'")->fetchColumn();
        $d['repProdCards'] = [
            'created'   => $created,
            'finished'  => $finished,
            'cancelled' => $cancelled,
            'open_now'  => $openNow,
            'avg_days'  => $cycleDays !== [] ? round(array_sum($cycleDays) / count($cycleDays), 1) : null,
        ];

        $rows = $pdo->query("SELECT stage_key, COUNT(*) AS c FROM production_orders WHERE state = 'open' GROUP BY stage_key")->fetchAll();
        $byStage = [];
        foreach ($rows as $r) {
            $byStage[(string) $r['stage_key']] = (int) $r['c'];
        }
        foreach (production_stages(false) as $st) {
            $key = (string) $st['stage_key'];
            if (($byStage[$key] ?? 0) > 0) {
                $d['repProdStageChart'][] = ['label' => (string) $st['title'], 'value' => $byStage[$key], 'color' => (string) $st['color']];
            }
        }
    }
}

// ---------- خروجی CSV ----------

function reports_export_csv(array $d): void
{
    $type = (string) $d['repType'];
    $range = (array) $d['repRange'];
    $fname = 'report-' . $type . '-' . ($range['from'] ?? 'all') . '-' . ($range['to'] ?? 'now') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fname . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM برای اکسل فارسی

    if ($type === 'sales') {
        $c = $d['repSalesCards'];
        fputcsv($out, ['گزارش فروش', (string) $d['repRangeText']]);
        fputcsv($out, ['تعداد سفارش', $c['orders']]);
        fputcsv($out, ['جمع فروش (تومان)', $c['sales']]);
        fputcsv($out, ['میانگین سفارش (تومان)', $c['avg']]);
        fputcsv($out, ['متراژ فروخته‌شده', $c['meters']]);
        fputcsv($out, ['جمع دریافتی (تومان)', $c['received']]);
        fputcsv($out, ['جمع هزینه قطعی (تومان)', $c['expenses']]);
        fputcsv($out, ['خالص دریافتی منهای هزینه (تومان)', $c['net']]);
        fputcsv($out, []);
        fputcsv($out, ['دوره', 'تعداد سفارش', 'فروش (تومان)', 'متراژ', 'دریافتی (تومان)', 'هزینه قطعی (تومان)']);
        foreach ($d['repSalesRows'] as $r) {
            fputcsv($out, [$r['label'], $r['orders'], $r['sales'], $r['meters'], $r['received'], $r['expenses']]);
        }
    } elseif ($type === 'products') {
        fputcsv($out, ['گزارش محصولات پرفروش', (string) $d['repRangeText']]);
        fputcsv($out, ['محصول', 'تعداد سفارش', 'تعداد چراغ', 'متراژ (متر)', 'درآمد (تومان)']);
        foreach ($d['repProducts'] as $r) {
            fputcsv($out, [$r['product_name'], $r['orders_cnt'], $r['fixtures'], round((float) $r['meters'], 2), $r['revenue']]);
        }
    } elseif ($type === 'materials') {
        fputcsv($out, ['گزارش مصرف مواد اولیه', (string) $d['repRangeText']]);
        fputcsv($out, ['ماده', 'خروج از انبار', 'واحد', 'مصرف از پرتی (متر)', 'پرت برگشتی (متر)', 'بهای مصرف (تومان)']);
        foreach ($d['repMatConsumption'] as $r) {
            fputcsv($out, [$r['name'], round($r['out_qty'], 3), $r['unit'], round($r['remnant_use_m'], 2), round($r['remnant_new_m'], 2), $r['cost']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['خرید مواد در بازه']);
        fputcsv($out, ['ماده', 'مقدار ورود', 'واحد', 'مبلغ خرید (تومان)']);
        foreach ($d['repMatPurchases'] as $r) {
            fputcsv($out, [$r['material_name'], round((float) $r['qty'], 3), $r['material_unit'], (int) $r['amount']]);
        }
    } elseif ($type === 'customers') {
        fputcsv($out, ['گزارش مشتریان', (string) $d['repRangeText']]);
        fputcsv($out, ['مشتری', 'شرکت', 'موبایل', 'تعداد سفارش', 'درآمد (تومان)', 'متراژ', 'مانده بدهی فعلی (تومان)']);
        foreach ($d['repTopCustomers'] as $r) {
            fputcsv($out, [$r['full_name'], $r['company'], $r['mobile'], $r['orders_cnt'], $r['revenue'], round((float) $r['meters'], 2), $r['balance']]);
        }
        fputcsv($out, []);
        fputcsv($out, ['بدهکاران فعلی', 'جمع: ' . $d['repDebtTotal'] . ' تومان']);
        fputcsv($out, ['مشتری', 'شرکت', 'موبایل', 'مانده بدهی (تومان)']);
        foreach ($d['repDebtors'] as $deb) {
            fputcsv($out, [$deb['full_name'], $deb['company'] ?? '', $deb['mobile'] ?? '', $deb['balance']]);
        }
    } else { // production
        fputcsv($out, ['گزارش تولید', (string) $d['repRangeText']]);
        $c = $d['repProdCards'];
        fputcsv($out, ['برگه ساخته‌شده', $c['created']]);
        fputcsv($out, ['تمام‌شده', $c['finished']]);
        fputcsv($out, ['لغوشده', $c['cancelled']]);
        fputcsv($out, ['بازِ فعلی', $c['open_now']]);
        fputcsv($out, ['میانگین زمان تکمیل (روز)', $c['avg_days'] ?? '']);
        fputcsv($out, []);
        fputcsv($out, ['شماره برگه', 'سفارش', 'مشتری', 'مرحله', 'وضعیت', 'ساخته‌شده', 'شروع', 'پایان']);
        foreach ($d['repProdSheets'] as $s) {
            fputcsv($out, [
                $s['production_no'], $s['order_no'], $s['customer_name'] ?? '',
                production_stage_title((string) $s['stage_key']), $s['state'],
                mb_substr((string) $s['created_at'], 0, 10), mb_substr((string) ($s['started_at'] ?? ''), 0, 10), mb_substr((string) ($s['finished_at'] ?? ''), 0, 10),
            ]);
        }
    }
    fclose($out);
}

// ---------- رندر ----------

function reports_type_title(string $type): string
{
    return [
        'sales'      => 'فروش و دریافتی',
        'products'   => 'محصولات پرفروش',
        'materials'  => 'مصرف مواد اولیه',
        'customers'  => 'مشتریان و بدهی',
        'production' => 'تولید',
    ][$type] ?? $type;
}

function reports_render(array $d): void
{
    extract($d);
    $type = (string) $repType;
    $range = (array) $repRange;
    $print = isset($_GET['print']);
    $base = 'admin.php?page=reports&type=' . $type;
    if ($range['preset'] === 'custom') {
        $base .= '&range=custom&from=' . urlencode((string) ($range['from'] ?? '')) . '&to=' . urlencode((string) ($range['to'] ?? ''));
    } else {
        $base .= '&range=' . urlencode((string) $range['preset']);
        if ($range['from'] !== null) {
            $base .= '&from=' . urlencode((string) $range['from']);
        }
        if ($range['to'] !== null) {
            $base .= '&to=' . urlencode((string) $range['to']);
        }
    }
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .report-sheet { display: block !important; border: none !important; }
    }
    .report-sheet { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
    .report-sheet table { width: 100%; }
    .rep-tabs { display: flex; flex-wrap: wrap; gap: 8px; margin: 14px 0; }
    .rep-tabs a { padding: 8px 14px; border: 1px solid #e5e7eb; border-radius: 999px; background: #fff; color: #374151; text-decoration: none; font-size: 13.5px; }
    .rep-tabs a.active { background: #1d4ed8; border-color: #1d4ed8; color: #fff; font-weight: 700; }
    </style>

    <div class="screen-area">
    <h1>گزارش‌ها</h1>
    <p class="muted">گزارش‌های مدیریتی کسب‌وکار در بازه دلخواه — همه فقط خواندنی‌اند و چیزی را تغییر نمی‌دهند. بازه پیش‌فرض و تعداد ردیف‌های برتر از پایین همین صفحه قابل تنظیم است.</p>

    <nav class="rep-tabs">
        <?php foreach (['sales', 'products', 'materials', 'customers', 'production'] as $t): ?>
            <a href="admin.php?page=reports&type=<?= e($t) ?>" class="<?= $type === $t ? 'active' : '' ?>"><?= e(reports_type_title($t)) ?></a>
        <?php endforeach; ?>
    </nav>

    <section class="card wide">
        <form method="get" class="inline-fields">
            <input type="hidden" name="page" value="reports">
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <label>بازه آماده
                <select name="range" onchange="this.form.submit()">
                    <?php foreach (array_merge(reports_range_presets(), ['custom']) as $p): ?>
                        <option value="<?= e($p) ?>" <?= $range['preset'] === $p ? 'selected' : '' ?>><?= e(reports_range_label($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>از تاریخ
                <input type="date" name="from" value="<?= e((string) ($range['from'] ?? '')) ?>" dir="ltr">
            </label>
            <label>تا تاریخ
                <input type="date" name="to" value="<?= e((string) ($range['to'] ?? '')) ?>" dir="ltr">
            </label>
            <button type="submit" class="btn primary" style="align-self:end">نمایش گزارش</button>
        </form>
        <p class="muted" style="margin:10px 0 0">
            بازه فعلی: <strong><?= e($repRangeText) ?></strong>
            &nbsp;|&nbsp;
            <a class="btn small primary" href="<?= e($base) ?>&print=1" target="_blank">🖨 چاپ گزارش</a>
            <a class="btn small" href="<?= e($base) ?>&export=csv">⬇ خروجی CSV</a>
        </p>
    </section>

    <?php if ($type === 'sales'): ?>
        <?php $c = $repSalesCards; ?>
        <div class="stat-grid dash-cards">
            <div class="stat-card"><span>سفارش‌ها</span><strong><?= (int) $c['orders'] ?></strong></div>
            <div class="stat-card"><span>جمع فروش</span><strong><?= e(format_price($c['sales'])) ?> تومان</strong></div>
            <div class="stat-card"><span>میانگین سفارش</span><strong><?= e(format_price($c['avg'])) ?> تومان</strong></div>
            <div class="stat-card"><span>متراژ فروخته‌شده</span><strong><?= e(format_qty((float) $c['meters'])) ?> متر</strong></div>
            <div class="stat-card"><span>دریافتی</span><strong style="color:#16a34a"><?= e(format_price($c['received'])) ?> تومان</strong></div>
            <div class="stat-card"><span>هزینه قطعی</span><strong style="color:#b45309"><?= e(format_price($c['expenses'])) ?> تومان</strong></div>
            <div class="stat-card"><span>خالص (دریافت − هزینه)</span><strong style="color:<?= $c['net'] >= 0 ? '#16a34a' : '#b91c1c' ?>"><?= e(format_price($c['net'])) ?> تومان</strong></div>
        </div>
        <?php if ($repSalesChart !== []): ?>
        <section class="card wide">
            <h2 style="margin-top:0">نمودار فروش (تومان)</h2>
            <?= reports_bar_chart($repSalesChart) ?>
        </section>
        <?php endif; ?>
        <section class="card wide">
            <h2 style="margin-top:0">جزئیات دوره‌ای</h2>
            <?php if ($repSalesRows === []): ?>
                <p class="muted">در این بازه داده‌ای نیست.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>دوره</th><th>سفارش</th><th>فروش (تومان)</th><th>متراژ</th><th>دریافتی (تومان)</th><th>هزینه قطعی (تومان)</th></tr></thead>
                <tbody>
                <?php foreach ($repSalesRows as $r): ?>
                    <tr>
                        <td dir="ltr" style="text-align:right"><?= e($r['label']) ?></td>
                        <td><?= (int) $r['orders'] ?></td>
                        <td><strong><?= e(format_price($r['sales'])) ?></strong></td>
                        <td><?= e(format_qty((float) $r['meters'])) ?></td>
                        <td><?= e(format_price($r['received'])) ?></td>
                        <td><?= e(format_price($r['expenses'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($type === 'products'): ?>
        <section class="card wide">
            <h2 style="margin-top:0">پرفروش‌ترین محصولات — <?= e($repRangeText) ?></h2>
            <p class="muted">بر پایهٔ ردیف‌های سفارش‌های لغونشده. درآمد = جمع مبلغ ردیف‌ها.</p>
            <?php if ($repProducts === []): ?>
                <p class="muted">در این بازه فروشی ثبت نشده است.</p>
            <?php else: ?>
            <?php
            $bars = [];
            foreach (array_slice($repProducts, 0, 10) as $r) {
                $bars[] = ['label' => (string) $r['product_name'], 'value' => (int) $r['revenue']];
            }
            echo reports_bar_chart($bars);
            ?>
            <table>
                <thead><tr><th>محصول</th><th>سفارش</th><th>چراغ</th><th>متراژ (متر)</th><th>درآمد (تومان)</th></tr></thead>
                <tbody>
                <?php foreach ($repProducts as $r): ?>
                    <tr>
                        <td><strong><?= e($r['product_name']) ?></strong></td>
                        <td><?= (int) $r['orders_cnt'] ?></td>
                        <td><?= (int) $r['fixtures'] ?></td>
                        <td><?= e(format_qty((float) $r['meters'])) ?></td>
                        <td><strong><?= e(format_price($r['revenue'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($type === 'materials'): ?>
        <?php $t = $repMatTotals; ?>
        <div class="stat-grid dash-cards">
            <div class="stat-card"><span>بهای مصرف مواد</span><strong><?= e(format_price($t['cost'])) ?> تومان</strong></div>
            <div class="stat-card"><span>خرید مواد در بازه</span><strong><?= e(format_price($t['purchase'])) ?> تومان</strong></div>
            <div class="stat-card"><span>مصرف از پرتی</span><strong><?= e(format_qty((float) $t['remnant_use_m'])) ?> متر</strong></div>
            <div class="stat-card"><span>پرت برگشتی به انبار</span><strong><?= e(format_qty((float) $t['remnant_new_m'])) ?> متر</strong></div>
        </div>
        <section class="card wide">
            <h2 style="margin-top:0">مصرف مواد در تولید — <?= e($repRangeText) ?></h2>
            <p class="muted">بهای مصرف با همان مبنای سود سفارش حساب می‌شود: خروج از انبار × قیمت ثبت‌شدهٔ گردش (یا آخرین قیمت خرید)، مصرف/برگشت پرتی × آخرین قیمت خریدِ متری. مصرف برگشت‌خورده (لغو تولید) حساب نشده است.</p>
            <?php if ($repMatConsumption === []): ?>
                <p class="muted">در این بازه مصرف تولیدی ثبت نشده است. مصرف مواد وقتی در این گزارش می‌آید که در «تولید» برای یک برگه، دکمه «شروع تولید» زده شده باشد (فقط ساخت برگه کافی نیست).</p>
            <?php else: ?>
            <table>
                <thead><tr><th>ماده</th><th>خروج از انبار</th><th>مصرف از پرتی (متر)</th><th>پرت برگشتی (متر)</th><th>بهای مصرف (تومان)</th></tr></thead>
                <tbody>
                <?php foreach ($repMatConsumption as $r): ?>
                    <tr>
                        <td><strong><?= e($r['name']) ?></strong></td>
                        <td><?= e(format_qty((float) $r['out_qty'])) ?> <?= e($r['unit']) ?></td>
                        <td><?= e(format_qty((float) $r['remnant_use_m'])) ?></td>
                        <td><?= e(format_qty((float) $r['remnant_new_m'])) ?></td>
                        <td><strong><?= e(format_price($r['cost'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <section class="card wide">
            <h2 style="margin-top:0">خرید مواد در بازه</h2>
            <?php if ($repMatPurchases === []): ?>
                <p class="muted">در این بازه ورودی خریدی ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>ماده</th><th>مقدار ورود</th><th>مبلغ (تومان)</th></tr></thead>
                <tbody>
                <?php foreach ($repMatPurchases as $r): ?>
                    <tr>
                        <td><strong><?= e($r['material_name']) ?></strong></td>
                        <td><?= e(format_qty((float) $r['qty'])) ?> <?= e($r['material_unit']) ?></td>
                        <td><strong><?= e(format_price($r['amount'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($type === 'customers'): ?>
        <section class="card wide">
            <h2 style="margin-top:0">مشتریان برتر در بازه — <?= e($repRangeText) ?></h2>
            <?php if ($repTopCustomers === []): ?>
                <p class="muted">در این بازه سفارشی ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>مشتری</th><th>موبایل</th><th>سفارش</th><th>متراژ</th><th>درآمد (تومان)</th><th>مانده بدهی فعلی</th></tr></thead>
                <tbody>
                <?php foreach ($repTopCustomers as $r): ?>
                    <tr>
                        <td><strong><?= e($r['full_name'] ?? '—') ?></strong><?= !empty($r['company']) ? ' — ' . e($r['company']) : '' ?></td>
                        <td dir="ltr"><?= e($r['mobile'] ?? '') ?></td>
                        <td><?= (int) $r['orders_cnt'] ?></td>
                        <td><?= e(format_qty((float) $r['meters'])) ?></td>
                        <td><strong><?= e(format_price($r['revenue'])) ?></strong></td>
                        <td><?= (int) $r['balance'] > 0 ? '<strong style="color:#b91c1c">' . e(format_price($r['balance'])) . ' تومان</strong>' : '<span class="muted">تسویه</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
        <section class="card wide">
            <h2 style="margin-top:0">بدهکاران فعلی (<?= e(format_price($repDebtTotal)) ?> تومان)</h2>
            <p class="muted">مانده بدهی وضعیت فعلی است و به بازه بالا وابسته نیست.</p>
            <?php if ($repDebtors === []): ?>
                <p class="muted">هیچ مشتری بدهکاری نیست. 🎉</p>
            <?php else: ?>
            <table>
                <thead><tr><th>مشتری</th><th>موبایل</th><th>مانده بدهی</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($repDebtors as $deb): ?>
                    <tr>
                        <td><strong><?= e($deb['full_name']) ?></strong><?= !empty($deb['company']) ? ' — ' . e($deb['company']) : '' ?></td>
                        <td dir="ltr"><?= e($deb['mobile'] ?? '') ?></td>
                        <td><strong style="color:#b91c1c"><?= e(format_price($deb['balance'])) ?></strong></td>
                        <td><a class="btn small" href="admin.php?page=statements&customer_id=<?= (int) $deb['id'] ?>">صورتحساب</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if ($type === 'production'): ?>
        <?php $c = $repProdCards; ?>
        <div class="stat-grid dash-cards">
            <div class="stat-card"><span>برگه ساخته‌شده در بازه</span><strong><?= (int) $c['created'] ?></strong></div>
            <a class="stat-card" href="admin.php?page=production"><span>تمام‌شده در بازه</span><strong style="color:#16a34a"><?= (int) $c['finished'] ?></strong></a>
            <div class="stat-card"><span>لغوشده در بازه</span><strong style="color:#b91c1c"><?= (int) $c['cancelled'] ?></strong></div>
            <a class="stat-card" href="admin.php?page=production"><span>برگه باز فعلی</span><strong><?= (int) $c['open_now'] ?></strong></a>
            <div class="stat-card"><span>میانگین زمان تکمیل</span><strong><?= $c['avg_days'] !== null ? e(format_qty((float) $c['avg_days'])) . ' روز' : '—' ?></strong></div>
        </div>
        <?php if ($repProdStageChart !== []): ?>
        <section class="card wide">
            <h2 style="margin-top:0">برگه‌های باز برحسب مرحله (وضعیت فعلی)</h2>
            <?= reports_bar_chart($repProdStageChart) ?>
        </section>
        <?php endif; ?>
        <section class="card wide">
            <h2 style="margin-top:0">برگه‌های تولید در بازه</h2>
            <?php if ($repProdSheets === []): ?>
                <p class="muted">در این بازه برگه تولیدی ساخته نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>برگه</th><th>سفارش</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th><th>ساخته‌شده</th><th>پایان</th></tr></thead>
                <tbody>
                <?php foreach ($repProdSheets as $s): ?>
                    <tr>
                        <td><a href="admin.php?page=production_view&id=<?= (int) ($s['id'] ?? 0) ?>">#<?= (int) $s['production_no'] ?></a></td>
                        <td>#<?= (int) $s['order_no'] ?></td>
                        <td><?= e($s['customer_name'] ?? '—') ?></td>
                        <td><span class="badge" style="background:<?= e(production_stage_color((string) $s['stage_key'])) ?>22;color:<?= e(production_stage_color((string) $s['stage_key'])) ?>"><?= e(production_stage_title((string) $s['stage_key'])) ?></span></td>
                        <td><?= (string) $s['state'] === 'finished' ? 'تمام‌شده' : ((string) $s['state'] === 'cancelled' ? 'لغوشده' : 'در جریان') ?></td>
                        <td class="muted"><?= e(mb_substr((string) $s['created_at'], 0, 10)) ?></td>
                        <td class="muted"><?= e(mb_substr((string) ($s['finished_at'] ?? ''), 0, 10)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card wide">
        <h2 style="margin-top:0">تنظیمات گزارش‌ها</h2>
        <form method="post" class="inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_report_settings">
            <label>بازه پیش‌فرض هنگام ورود
                <select name="reports_default_range">
                    <?php foreach (reports_range_presets() as $p): ?>
                        <option value="<?= e($p) ?>" <?= get_setting('reports_default_range', 'month') === $p ? 'selected' : '' ?>><?= e(reports_range_label($p)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>تعداد ردیف‌های برتر (محصولات/مشتریان/خرید)
                <input type="number" name="reports_top_limit" min="3" max="50" value="<?= (int) get_setting('reports_top_limit', '10') ?>">
            </label>
            <button type="submit" class="btn primary" style="align-self:end">ذخیره تنظیمات</button>
        </form>
    </section>
    </div>

    <div class="report-sheet"<?= $print ? '' : ' style="display:none"' ?>>
        <h2>گزارش <?= e(reports_type_title($type)) ?></h2>
        <p class="muted"><?= e(all_settings()['site_title'] ?? '') ?> — بازه: <?= e($repRangeText) ?> — تاریخ تهیه: <?= e(date('Y-m-d')) ?></p>
        <?php reports_render_print_body($d); ?>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php
}

/** بدنه چاپی گزارش (بدون نمودار؛ همان داده‌های صفحه‌نمایش) */
function reports_render_print_body(array $d): void
{
    $type = (string) $d['repType'];
    if ($type === 'sales') {
        $c = $d['repSalesCards'];
        ?>
        <table><tbody>
            <tr><th>تعداد سفارش</th><td><?= (int) $c['orders'] ?></td><th>جمع فروش</th><td><?= e(format_price($c['sales'])) ?> تومان</td></tr>
            <tr><th>میانگین سفارش</th><td><?= e(format_price($c['avg'])) ?> تومان</td><th>متراژ فروخته‌شده</th><td><?= e(format_qty((float) $c['meters'])) ?> متر</td></tr>
            <tr><th>جمع دریافتی</th><td><?= e(format_price($c['received'])) ?> تومان</td><th>جمع هزینه قطعی</th><td><?= e(format_price($c['expenses'])) ?> تومان</td></tr>
            <tr><th>خالص (دریافت − هزینه)</th><td colspan="3"><strong><?= e(format_price($c['net'])) ?> تومان</strong></td></tr>
        </tbody></table>
        <table>
            <thead><tr><th>دوره</th><th>سفارش</th><th>فروش (تومان)</th><th>متراژ</th><th>دریافتی (تومان)</th><th>هزینه قطعی (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repSalesRows'] as $r): ?>
                <tr><td><?= e($r['label']) ?></td><td><?= (int) $r['orders'] ?></td><td><?= e(format_price($r['sales'])) ?></td><td><?= e(format_qty((float) $r['meters'])) ?></td><td><?= e(format_price($r['received'])) ?></td><td><?= e(format_price($r['expenses'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } elseif ($type === 'products') {
        ?>
        <table>
            <thead><tr><th>محصول</th><th>سفارش</th><th>چراغ</th><th>متراژ (متر)</th><th>درآمد (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repProducts'] as $r): ?>
                <tr><td><?= e($r['product_name']) ?></td><td><?= (int) $r['orders_cnt'] ?></td><td><?= (int) $r['fixtures'] ?></td><td><?= e(format_qty((float) $r['meters'])) ?></td><td><?= e(format_price($r['revenue'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } elseif ($type === 'materials') {
        $t = $d['repMatTotals'];
        ?>
        <table><tbody>
            <tr><th>بهای مصرف مواد</th><td><?= e(format_price($t['cost'])) ?> تومان</td><th>خرید مواد در بازه</th><td><?= e(format_price($t['purchase'])) ?> تومان</td></tr>
            <tr><th>مصرف از پرتی</th><td><?= e(format_qty((float) $t['remnant_use_m'])) ?> متر</td><th>پرت برگشتی</th><td><?= e(format_qty((float) $t['remnant_new_m'])) ?> متر</td></tr>
        </tbody></table>
        <h3>مصرف مواد</h3>
        <table>
            <thead><tr><th>ماده</th><th>خروج از انبار</th><th>مصرف از پرتی (متر)</th><th>پرت برگشتی (متر)</th><th>بهای مصرف (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repMatConsumption'] as $r): ?>
                <tr><td><?= e($r['name']) ?></td><td><?= e(format_qty((float) $r['out_qty'])) ?> <?= e($r['unit']) ?></td><td><?= e(format_qty((float) $r['remnant_use_m'])) ?></td><td><?= e(format_qty((float) $r['remnant_new_m'])) ?></td><td><?= e(format_price($r['cost'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h3>خرید مواد</h3>
        <table>
            <thead><tr><th>ماده</th><th>مقدار ورود</th><th>مبلغ (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repMatPurchases'] as $r): ?>
                <tr><td><?= e($r['material_name']) ?></td><td><?= e(format_qty((float) $r['qty'])) ?> <?= e($r['material_unit']) ?></td><td><?= e(format_price($r['amount'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } elseif ($type === 'customers') {
        ?>
        <h3>مشتریان برتر در بازه</h3>
        <table>
            <thead><tr><th>مشتری</th><th>سفارش</th><th>متراژ</th><th>درآمد (تومان)</th><th>مانده بدهی فعلی (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repTopCustomers'] as $r): ?>
                <tr><td><?= e($r['full_name'] ?? '—') ?><?= !empty($r['company']) ? ' — ' . e($r['company']) : '' ?></td><td><?= (int) $r['orders_cnt'] ?></td><td><?= e(format_qty((float) $r['meters'])) ?></td><td><?= e(format_price($r['revenue'])) ?></td><td><?= e(format_price($r['balance'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h3>بدهکاران فعلی — جمع <?= e(format_price($d['repDebtTotal'])) ?> تومان</h3>
        <table>
            <thead><tr><th>مشتری</th><th>موبایل</th><th>مانده بدهی (تومان)</th></tr></thead>
            <tbody>
            <?php foreach ($d['repDebtors'] as $deb): ?>
                <tr><td><?= e($deb['full_name']) ?></td><td><?= e($deb['mobile'] ?? '') ?></td><td><?= e(format_price($deb['balance'])) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    } else { // production
        $c = $d['repProdCards'];
        ?>
        <table><tbody>
            <tr><th>برگه ساخته‌شده</th><td><?= (int) $c['created'] ?></td><th>تمام‌شده</th><td><?= (int) $c['finished'] ?></td></tr>
            <tr><th>لغوشده</th><td><?= (int) $c['cancelled'] ?></td><th>باز فعلی</th><td><?= (int) $c['open_now'] ?></td></tr>
            <tr><th>میانگین زمان تکمیل</th><td colspan="3"><?= $c['avg_days'] !== null ? e(format_qty((float) $c['avg_days'])) . ' روز' : '—' ?></td></tr>
        </tbody></table>
        <table>
            <thead><tr><th>برگه</th><th>سفارش</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th><th>ساخته‌شده</th><th>پایان</th></tr></thead>
            <tbody>
            <?php foreach ($d['repProdSheets'] as $s): ?>
                <tr><td>#<?= (int) $s['production_no'] ?></td><td>#<?= (int) $s['order_no'] ?></td><td><?= e($s['customer_name'] ?? '—') ?></td><td><?= e(production_stage_title((string) $s['stage_key'])) ?></td><td><?= (string) $s['state'] === 'finished' ? 'تمام‌شده' : ((string) $s['state'] === 'cancelled' ? 'لغوشده' : 'در جریان') ?></td><td><?= e(mb_substr((string) $s['created_at'], 0, 10)) ?></td><td><?= e(mb_substr((string) ($s['finished_at'] ?? ''), 0, 10)) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
