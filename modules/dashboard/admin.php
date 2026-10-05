<?php
// modules/dashboard/admin.php — داشبورد مدیریتی (استخراج از admin.php در ۹٫۹۹٫۲۵)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }

/** هندلر صفحه داشبورد */
function dashboard_handle_page(): void
{
    global $pdo, $page;

        // KPIهای مدیریتی (نسخه ۹٫۷) — برای ارائه به سرمایه‌گذار
        $kpi = ['month_revenue' => 0, 'month_orders' => 0, 'active_employees' => 0, 'month_payroll' => 0, 'asset_book_value' => 0, 'total_customers' => 0];
        try {
            $mk = date('Y-m');
            $kpi['month_revenue'] = (int) $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE substr(created_at,1,7) = '$mk' AND status != 'cancelled'")->fetchColumn();
            $kpi['month_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,7) = '$mk' AND status != 'cancelled'")->fetchColumn();
            $kpi['active_employees'] = (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
            $kpi['month_payroll'] = (int) $pdo->query("SELECT COALESCE(SUM(net_amount),0) FROM salary_payments WHERE pay_month = '$mk'")->fetchColumn();
            // ارزش دفتری تجهیزات
            $eqs = $pdo->query("SELECT purchase_price, useful_life_years, purchase_date FROM equipment WHERE status != 'retired'")->fetchAll();
            $bv = 0;
            foreach ($eqs as $eq) {
                $pp = (int) $eq['purchase_price'];
                $life = max(0.1, (float) $eq['useful_life_years']);
                $annual = $pp / $life;
                $years = 0;
                if (!empty($eq['purchase_date'])) {
                    $years = max(0, (time() - strtotime($eq['purchase_date'])) / (365.25 * 86400));
                }
                $bv += max(0, (int) round($pp - $annual * $years));
            }
            $kpi['asset_book_value'] = $bv;
        } catch (Throwable $ignored) {
        }
        $GLOBALS['kpi'] = $kpi;
        try {
            $dashCounts['customers'] = (int) $pdo->query('SELECT COUNT(*) FROM customers')->fetchColumn();
            $dashCounts['products']  = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
            $dashCounts['orders']     = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
            $dashCounts['new_orders'] = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'new'")->fetchColumn();
        } catch (Throwable $ignored) {
        }
        try {
            // دریافتی ۶ ماه اخیر (ماه میلادی از paid_date)
            $rows = $pdo->query("SELECT substr(paid_date, 1, 7) AS m, COALESCE(SUM(amount), 0) AS s FROM payments WHERE paid_date >= date('now', '-6 months') GROUP BY m")->fetchAll();
            $byMonth = [];
            foreach ($rows as $r) {
                $byMonth[(string) $r['m']] = (int) $r['s'];
            }
            for ($i = 5; $i >= 0; $i--) {
                $mk = date('Y-m', strtotime("-$i months"));
                $chartIncome[] = ['month' => $mk, 'value' => $byMonth[$mk] ?? 0];
            }
        } catch (Throwable $ignored) {
        }
        try {
            $rows = $pdo->query('SELECT status, COUNT(*) AS c FROM orders GROUP BY status ORDER BY c DESC')->fetchAll();
            foreach ($rows as $r) {
                $chartOrderStatus[] = [
                    'label' => function_exists('order_status_title') ? order_status_title((string) $r['status']) : (string) $r['status'],
                    'value' => (int) $r['c'],
                    'color' => function_exists('order_status_color') ? order_status_color((string) $r['status']) : '#a7b8e0',
                ];
            }
        } catch (Throwable $ignored) {
        }
        try {
            $rows = $pdo->query("SELECT c.title AS t, COALESCE(SUM(e.amount), 0) AS s FROM expenses e JOIN expense_categories c ON c.id = e.category_id WHERE e.status = 'approved' GROUP BY c.id ORDER BY s DESC LIMIT 8")->fetchAll();
            $pastels = ['#ff6b9d', '#4fc3f7', '#69f0ae', '#ffd740', '#b388ff', '#ff8a80', '#40c4ff', '#b2ff59'];
            $ci = 0;
            foreach ($rows as $r) {
                $chartExpenseCat[] = ['label' => (string) $r['t'], 'value' => (int) $r['s'], 'color' => $pastels[$ci % count($pastels)]];
                $ci++;
            }
        } catch (Throwable $ignored) {
        }
        try {
            $rows = $pdo->query("SELECT stage_key, COUNT(*) AS c FROM production_orders WHERE state = 'open' GROUP BY stage_key")->fetchAll();
            foreach ($rows as $r) {
                $chartProdStages[] = [
                    'label' => function_exists('production_stage_title') ? production_stage_title((string) $r['stage_key']) : (string) $r['stage_key'],
                    'value' => (int) $r['c'],
                    'color' => function_exists('production_stage_color') ? production_stage_color((string) $r['stage_key']) : '#fde68a',
                ];
            }
        } catch (Throwable $ignored) {
        }
        // --- نمودارهای فانتزی ۹٫۱۶: سود در برابر هزینه (۶ ماه) ---
        $chartProfit = [];
        try {
            for ($i = 5; $i >= 0; $i--) {
                $mk = date('Y-m', strtotime("-$i months"));
                $rev = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE substr(paid_date,1,7) = '$mk'")->fetchColumn();
                $cost = (int) $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE substr(expense_date,1,7) = '$mk' AND status = 'approved'")->fetchColumn();
                $chartProfit[] = ['month' => $mk, 'revenue' => $rev, 'cost' => $cost, 'profit' => $rev - $cost];
            }
        } catch (Throwable $ignored) {
        }
        // --- قیف سفارشات (پایپ‌لاین) ---
        $chartFunnel = [];
        try {
            $funnelStages = ['new' => 0, 'confirmed' => 0, 'in_production' => 0, 'ready' => 0, 'delivered' => 0];
            $rows = $pdo->query("SELECT status, COUNT(*) AS c FROM orders WHERE status != 'cancelled' GROUP BY status")->fetchAll();
            $byStatus = [];
            foreach ($rows as $r) { $byStatus[(string) $r['status']] = (int) $r['c']; }
            foreach ($funnelStages as $sk => $v) { $funnelStages[$sk] = $byStatus[$sk] ?? 0; }
            $chartFunnel = $funnelStages;
        } catch (Throwable $ignored) {
        }
        // --- تایم‌لاین سفارشات ۳۰ روز اخیر ---
        $chartTimeline = [];
        try {
            for ($i = 29; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $c = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE substr(created_at,1,10) = '$d' AND status != 'cancelled'")->fetchColumn();
                $chartTimeline[] = ['day' => $d, 'value' => $c];
            }
        } catch (Throwable $ignored) {
        }
    }
    // عنوان‌های نمایشی قالب‌های دیتابیس (برای برچسب فهرست قالب در فرم بخش‌ها)
    $templateTitles = [];
    foreach (all_templates() as $tplRow) {
        $templateTitles[(string) $tplRow['template_key']] = (string) $tplRow['title'];
    }

    // ---------- داده‌های صفحه «قالب و استایل» ----------
    $designTab = 'templates';
    $dbTemplates = [];
    $editTplRow = null;
    $editTplRevisions = [];
    $cssRevisions = [];
    $customCssRevisions = [];
    $legacyTplWarnings = [];
    $legacyCssWarnings = [];
    $cleanupCandidates = [];
    $tplUsedIn = [];
    $visual = validated_visual_settings($settings);
    $adminTheme = (string) get_setting('admin_theme', 'light');
    if (!in_array($adminTheme, ['light', 'architect', 'glass-white', 'glass-smoke'], true)) {
        $adminTheme = 'light';
}

/** رندر صفحه داشبورد */
function dashboard_render_page(): void
{
    global $pdo, $page;
    include __DIR__ . '/render.php';
}
