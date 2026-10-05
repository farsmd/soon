<?php
// modules/dashboard/render.php — قالب داشبورد (استخراج از admin.php در ۹٫۹۹٫۲۵)
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
global $pdo, $page;
?>
            <?php
            // ----- سیستم ویجت‌های داشبورد (۸٫۵٫۰) — کاربر از همین صفحه فعال/غیرفعال و مرتبشان می‌کند -----
            $dashWidgetDefs = [
                'stat_customers'      => ['کارت آماری: مشتری‌ها', 'stat'],
                'stat_products'       => ['کارت آماری: محصولات', 'stat'],
                'stat_orders'         => ['کارت آماری: سفارش‌ها', 'stat'],
                'stat_new_orders'     => ['کارت آماری: سفارش‌های جدید (فقط وقتی > ۰)', 'stat'],
                'stat_production'     => ['کارت آماری: برگه‌های تولید در جریان (فقط وقتی > ۰)', 'stat'],
                'stat_finance_month'  => ['کارت آماری: تراز مالی این ماه', 'stat'],
                'stat_pending'        => ['کارت آماری: هزینه‌های در انتظار تأیید (فقط وقتی > ۰)', 'stat'],
                'stat_debt'           => ['کارت آماری: بدهی مشتریان (فقط وقتی > ۰)', 'stat'],
                'stat_messages'       => ['کارت آماری: پیام‌های تماس', 'stat'],
                'stat_visits'         => ['کارت آماری: بازدید سایت (امروز + این ماه)', 'stat'],
                'stat_version'        => ['کارت آماری: نسخه برنامه', 'stat'],
                'kpi_revenue'         => ['شاخص: درآمد این ماه', 'stat'],
                'kpi_orders'          => ['شاخص: سفارش‌های این ماه', 'stat'],
                'kpi_employees'       => ['شاخص: پرسنل فعال', 'stat'],
                'kpi_payroll'         => ['شاخص: حقوق این ماه', 'stat'],
                'kpi_assets'          => ['شاخص: ارزش دفتری تجهیزات', 'stat'],
                'chart_income'        => ['نمودار دریافتی ۶ ماه اخیر', 'chart'],
                'chart_orders'        => ['نمودار وضعیت سفارش‌ها', 'chart'],
                'chart_expenses'      => ['نمودار هزینه‌ها برحسب دسته', 'chart'],
                'chart_production'    => ['نمودار برگه‌های تولید برحسب مرحله', 'chart'],
                'chart_donut_orders'  => ['🍩 توزیع سفارش‌ها (دونات)', 'chart'],
                'chart_profit'        => ['💰 درآمد در برابر هزینه', 'chart'],
                'chart_funnel'        => ['🔻 قیف سفارشات', 'chart'],
                'chart_timeline'      => ['📊 تایم‌لاین ۳۰ روزه سفارشات', 'chart'],
                'chart_visits'        => ['بازدید روزانه (بدون ربات و ادمین)', 'chart'],
            ];
            $dashWidgetPages = [
                'stat_customers'     => 'customers',
                'stat_products'      => 'products',
                'stat_orders'        => 'orders',
                'stat_new_orders'    => 'orders',
                'stat_production'    => 'production',
                'stat_finance_month' => 'finance',
                'stat_pending'       => 'expenses',
                'stat_debt'          => 'statements',
                'stat_messages'      => 'messages',
                'stat_visits'        => 'logs',
                'stat_version'       => 'dashboard',
                'kpi_revenue'        => 'orders',
                'kpi_orders'         => 'orders',
                'kpi_employees'      => 'employees',
                'kpi_payroll'        => 'payroll',
                'kpi_assets'         => 'assets',
                'chart_income'       => 'finance',
                'chart_orders'       => 'orders',
                'chart_expenses'     => 'expenses',
                'chart_production'   => 'production',
                'chart_donut_orders' => 'orders',
                'chart_profit'       => 'finance',
                'chart_funnel'       => 'orders',
                'chart_timeline'     => 'orders',
                'chart_visits'       => 'logs',
            ];
            $dashEnabledRaw = json_decode((string) get_setting('dash_widgets', ''), true);
            $dashEnabled = (is_array($dashEnabledRaw) && $dashEnabledRaw !== [])
                ? array_values(array_filter(array_map('strval', $dashEnabledRaw), static fn ($k) => isset($dashWidgetDefs[$k])))
                : array_keys($dashWidgetDefs);
            if ($dashEnabled === []) {
                $dashEnabled = array_keys($dashWidgetDefs);
            }
            $dashOn = array_fill_keys($dashEnabled, true);
            // نمودار میله‌ای پاستیلی (SVG بدون کتابخانه)
            $pastelPalette = ['#f9a8d4', '#93c5fd', '#6ee7b7', '#fcd34d', '#c4b5fd', '#fda4af', '#7dd3fc', '#bef264'];
            // سازنده نمودار Chart.js با تم نئون شیشه‌ای (۹٫۱۸)
            $chartJs = static function (string $type, array $labels, array $datasets, array $opts = []): string {
                static $idx = 0; $idx++;
                $cid = 'chartjs' . $idx;
                $neonColors = ['#00e5ff', '#ff4081', '#69f0ae', '#ffd740', '#b388ff', '#ff8a80', '#40c4ff', '#b2ff59'];
                foreach ($datasets as $di => &$ds) {
                    if (!isset($ds['backgroundColor'])) {
                        if ($type === 'doughnut') {
                            $ds['backgroundColor'] = array_slice($neonColors, 0, count($labels));
                            $ds['borderColor'] = '#ffffff';
                            $ds['borderWidth'] = 3;
                            $ds['hoverOffset'] = 12;
                        } elseif ($type === 'line') {
                            $c = $neonColors[$di % count($neonColors)];
                            $ds['borderColor'] = $c;
                            $ds['backgroundColor'] = $c . '33';
                            $ds['fill'] = $ds['fill'] ?? true;
                            $ds['tension'] = 0.4;
                            $ds['borderWidth'] = 3;
                            $ds['pointBackgroundColor'] = $c;
                            $ds['pointBorderColor'] = '#fff';
                            $ds['pointBorderWidth'] = 2;
                            $ds['pointRadius'] = 5;
                            $ds['pointHoverRadius'] = 8;
                        } else {
                            $ds['backgroundColor'] = array_map(fn($i) => $neonColors[$i % count($neonColors)] . 'cc', array_keys($labels));
                            $ds['borderColor'] = array_map(fn($i) => $neonColors[$i % count($neonColors)], array_keys($labels));
                            $ds['borderWidth'] = 2;
                            $ds['borderRadius'] = 8;
                        }
                    }
                }
                $config = [
                    'type' => $type,
                    'data' => ['labels' => $labels, 'datasets' => $datasets],
                    'options' => array_merge([
                        'responsive' => true,
                        'maintainAspectRatio' => false,
                        'plugins' => [
                            'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'padding' => 16, 'font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 12]]],
                            'tooltip' => [
                                'backgroundColor' => 'rgba(17,24,39,.9)',
                                'backdropFilter' => 'blur(8px)',
                                'titleFont' => ['family' => 'Vazirmatn, Tahoma', 'size' => 13],
                                'bodyFont' => ['family' => 'Vazirmatn, Tahoma', 'size' => 12],
                                'padding' => 12,
                                'cornerRadius' => 10,
                                'displayColors' => true,
                            ],
                        ],
                        'scales' => $type === 'doughnut' ? [] : [
                            'x' => ['grid' => ['display' => false], 'ticks' => ['font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 11]]],
                            'y' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(0,0,0,.06)'], 'ticks' => ['font' => ['family' => 'Vazirmatn, Tahoma', 'size' => 11]]],
                        ],
                    ], $opts),
                ];
                $json = json_encode($config, JSON_UNESCAPED_UNICODE);
                return '<div class="chartjs-wrap"><canvas id="' . $cid . '"></canvas></div>'
                    . '<script>(function(){if(typeof Chart==="undefined")return;new Chart(document.getElementById("' . $cid . '"),' . $json . ');})();</script>';
            };
            $renderBarChart = static function (array $bars, string $unitLabel = '') use ($pastelPalette): string {
                if ($bars === []) {
                    return '<p class="muted">داده‌ای برای نمایش نیست.</p>';
                }
                $max = 1;
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
                    $color = (string) ($b['color'] ?? $pastelPalette[$i % count($pastelPalette)]);
                    $label = (string) ($b['label'] ?? '');
                    $disp = $unitLabel !== '' ? number_format((int) $val) . ' ' . $unitLabel : number_format((int) $val);
                    $out .= '<rect x="' . $x . '" y="' . $y . '" width="' . $bw . '" height="' . $bh . '" rx="7" fill="' . e($color) . '"/>';
                    $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($y - 6) . '" font-size="11" font-weight="700" text-anchor="middle" fill="#374151">' . e($disp) . '</text>';
                    $out .= '<text x="' . ($x + (int) ($bw / 2)) . '" y="' . ($base + 16) . '" font-size="10.5" text-anchor="middle" fill="#6b7280">' . e(mb_substr($label, 0, 14)) . '</text>';
                    $i++;
                }
                $out .= '</svg>';
                return $out;
            };
            $dash_icon = static function (string $name): string {
                $paths = [
                    'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                    'box' => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
                    'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1Z"/><path d="M8 7h8"/><path d="M8 11h8"/><path d="M8 15h5"/>',
                    'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
                    'factory' => '<path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M17 18h1"/><path d="M12 18h1"/><path d="M7 18h1"/>',
                    'scale' => '<path d="M12 3v18"/><path d="M5 7l-3 7a3.5 3.5 0 0 0 6 0L5 7z"/><path d="M19 7l-3 7a3.5 3.5 0 0 0 6 0l-3-7z"/><path d="M3 7h18"/>',
                    'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
                    'card' => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
                    'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
                    'gear' => '<circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.2 4.2l2.8 2.8M17 17l2.8 2.8M1 12h4M19 12h4M4.2 19.8 7 17M17 7l2.8-2.8"/>',
                    'coins' => '<circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/>',
                    'wallet' => '<path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/>',
                    'alert' => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                ];
                $p = $paths[$name] ?? $paths['box'];
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="20" height="20" aria-hidden="true">' . $p . '</svg>';
            };
            // بررسی سبک نسخه جدید: حداکثر هر ۶ ساعت یک بار، با تایم‌اوت کوتاه؛ در صورت خطا بی‌صدا رد می‌شود
            $dash_version_info = static function (): array {
                $out = ['latest' => '', 'ok' => false];
                try {
                    $cached = (string) get_setting('update_check_latest', '');
                    $cachedAt = (int) get_setting('update_check_at', '0');
                    if ((time() - $cachedAt) < 21600) {
                        // کش تازه است (حتی اگر خالی باشد یعنی تلاش ناموفق اخیر)؛ بدون درخواست شبکه
                        $out['latest'] = $cached;
                        $out['ok'] = $cached !== '';
                        return $out;
                    }
                    $ctx = stream_context_create(['http' => ['timeout' => 5, 'user_agent' => 'linerlight-cms-update-check']]);
                    $body = @file_get_contents('https://raw.githubusercontent.com/farsmd/soon/main/config.php', false, $ctx);
                    if (is_string($body) && preg_match("/define\s*\(\s*'APP_VERSION'\s*,\s*'([^']+)'/", $body, $m)) {
                        $out['latest'] = $m[1];
                        $out['ok'] = true;
                    }
                    // ثبت زمان آخرین تلاش (موفق یا ناموفق) تا داشبورد بیش از حد لازم درخواست نزند
                    try {
                        set_setting('update_check_latest', $out['latest']);
                        set_setting('update_check_at', (string) time());
                    } catch (Throwable $e) {
                    }
                } catch (Throwable $e) {
                }
                return $out;
            };
            $renderStat = static function (string $key) use ($visitsToday, $visitsMonth, $dashCounts, $productionActiveCount, $messages, $lowStockCount, $finPending, $finMonthIncome, $finMonthExpenses, $finDebtTotal, $dashWidgetPages, $dash_icon, $dash_version_info): string {

                if (isset($dashWidgetPages[$key]) && !admin_can_page($dashWidgetPages[$key])) {
                    return '';
                }
                switch ($key) {
                    case 'stat_customers':
                        return '<a class="stat-card sc-blue" href="admin.php?page=customers"><span><i class="sc-ico">' . $dash_icon('users') . '</i>مشتری‌ها</span><strong>' . (int) $dashCounts['customers'] . '</strong></a>';
                    case 'stat_products':
                        return '<a class="stat-card sc-indigo" href="admin.php?page=products"><span><i class="sc-ico">' . $dash_icon('box') . '</i>محصولات</span><strong>' . (int) $dashCounts['products'] . '</strong></a>';
                    case 'stat_orders':
                        return '<a class="stat-card sc-teal" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('receipt') . '</i>سفارش‌ها</span><strong>' . (int) $dashCounts['orders'] . '</strong></a>';
                    case 'stat_new_orders':
                        return (int) $dashCounts['new_orders'] > 0 ? '<a class="stat-card sc-blue sc-alert-blue" href="admin.php?page=orders&status=new"><span><i class="sc-ico">' . $dash_icon('bell') . '</i>سفارش‌های جدید</span><strong>' . (int) $dashCounts['new_orders'] . '</strong></a>' : '';
                    case 'stat_production':
                        return (int) ($productionActiveCount ?? 0) > 0 ? '<a class="stat-card sc-amber sc-alert-amber" href="admin.php?page=production"><span><i class="sc-ico">' . $dash_icon('factory') . '</i>تولید در جریان</span><strong>' . (int) $productionActiveCount . '</strong></a>' : '';
                    case 'stat_finance_month':
                        return (isset($finPending) && is_array($finPending)) ? '<a class="stat-card sc-green" href="admin.php?page=finance"><span><i class="sc-ico">' . $dash_icon('scale') . '</i>تراز مالی این ماه</span><strong>' . e(format_price((int) ($finMonthIncome ?? 0) - (int) ($finMonthExpenses ?? 0))) . ' تومان</strong></a>' : '';
                    case 'stat_pending':
                        return (isset($finPending) && is_array($finPending) && (int) ($finPending['count'] ?? 0) > 0) ? '<a class="stat-card sc-amber sc-alert-amber" href="admin.php?page=expenses&status=pending"><span><i class="sc-ico">' . $dash_icon('clock') . '</i>در انتظار تأیید</span><strong>' . (int) $finPending['count'] . ' مورد</strong></a>' : '';
                    case 'stat_debt':
                        return ((int) ($finDebtTotal ?? 0) > 0) ? '<a class="stat-card sc-red sc-alert-red" href="admin.php?page=statements"><span><i class="sc-ico">' . $dash_icon('card') . '</i>بدهی مشتریان</span><strong>' . e(format_price((int) $finDebtTotal)) . ' تومان</strong></a>' : '';
                    case 'stat_visits':
                        return '<a class="stat-card sc-cyan stat-dual" href="admin.php?page=logs"><span><i class="sc-ico">' . $dash_icon('eye') . '</i>بازدید سایت</span><div class="dual-rows"><div><small>امروز</small><strong>' . (int) $visitsToday . '</strong></div><div><small>این ماه</small><strong>' . (int) $visitsMonth . '</strong></div></div></a>';
                    case 'stat_messages':
                        return '<a class="stat-card sc-purple" href="admin.php?page=messages"><span><i class="sc-ico">' . $dash_icon('mail') . '</i>پیام‌های تماس</span><strong>' . count($messages) . '</strong></a>';
                    case 'stat_version':
                        $vi = $dash_version_info();
                        $verSub = '';
                        if ($vi['ok'] && $vi['latest'] !== '' && version_compare((string) APP_VERSION, (string) $vi['latest'], '<')) {
                            $verSub = '<span class="sc-sub" style="color:#b45309;font-weight:700">نسخه ' . e($vi['latest']) . ' موجود است ← آپدیت</span>';
                        } elseif ($vi['ok'] && $vi['latest'] !== '') {
                            $verSub = '<span class="sc-sub" style="color:#16a34a">به‌روز است ✓</span>';
                        }
                        return '<a class="stat-card" href="admin.php?page=update"><span><i class="sc-ico">' . $dash_icon('gear') . '</i>نسخه برنامه</span><strong dir="ltr">' . e(APP_VERSION) . '</strong>' . $verSub . '</a>';
                    case 'kpi_revenue':
                        return '<a class="stat-card kpi kpi-revenue" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('coins') . '</i>درآمد این ماه</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['month_revenue'] ?? 0))) . ' تومان</strong></a>';
                    case 'kpi_orders':
                        return '<a class="stat-card kpi kpi-orders" href="admin.php?page=orders"><span><i class="sc-ico">' . $dash_icon('box') . '</i>سفارش‌های این ماه</span><strong>' . (int) ($GLOBALS['kpi']['month_orders'] ?? 0) . '</strong></a>';
                    case 'kpi_employees':
                        return '<a class="stat-card kpi kpi-employees" href="admin.php?page=employees"><span><i class="sc-ico">' . $dash_icon('users') . '</i>پرسنل فعال</span><strong>' . (int) ($GLOBALS['kpi']['active_employees'] ?? 0) . ' نفر</strong></a>';
                    case 'kpi_payroll':
                        return '<a class="stat-card kpi kpi-payroll" href="admin.php?page=payroll"><span><i class="sc-ico">' . $dash_icon('wallet') . '</i>حقوق این ماه</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['month_payroll'] ?? 0))) . ' تومان</strong></a>';
                    case 'kpi_assets':
                        return '<a class="stat-card kpi kpi-assets" href="admin.php?page=assets"><span><i class="sc-ico">' . $dash_icon('factory') . '</i>ارزش دفتری تجهیزات</span><strong>' . e(format_price((int) ($GLOBALS['kpi']['asset_book_value'] ?? 0))) . ' تومان</strong></a>';
                }
                return '';
            };
            // --- نمودارهای SVG فانتزی (۹٫۱۶) ---
            $svgDonut = static function (array $items, int $size = 180): string {
                $total = array_sum(array_column($items, 'value'));
                if ($total <= 0) { return '<p class="muted">داده‌ای نیست</p>'; }
                $cx = $cy = $size / 2; $r = $size / 2 - 14; $inner = $r * 0.62;
                $circ = 2 * M_PI * $r; $offset = 0; $segs = ''; $uid = 'd' . substr(md5(json_encode($items)), 0, 6);
                $legend = '';
                foreach ($items as $i => $it) {
                    $frac = (float) $it['value'] / $total;
                    $len = $frac * $circ;
                    $color = (string) ($it['color'] ?? '#a7b8e0');
                    $segs .= sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="22" stroke-dasharray="%s %s" stroke-dashoffset="%s" stroke-linecap="round" transform="rotate(-90 %s %s)" class="donut-seg" style="animation-delay:%sms"/>',
                        $cx, $cy, $r, $color, max(0, $len - 3), $circ - max(0, $len - 3), -$offset, $cx, $cy, $i * 120);
                    $offset += $len;
                    $pct = round($frac * 100);
                    $legend .= '<div class="donut-legend-item"><span class="donut-dot" style="background:' . $color . '"></span>' . e((string) $it['label']) . ' <b>' . (int) $it['value'] . '</b> <span class="muted">' . $pct . '٪</span></div>';
                }
                return '<div class="donut-wrap"><svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
                    . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="#e5e7eb" stroke-width="22"/>'
                    . $segs
                    . '<text x="' . $cx . '" y="' . ($cy - 4) . '" text-anchor="middle" class="donut-total">' . $total . '</text>'
                    . '<text x="' . $cx . '" y="' . ($cy + 18) . '" text-anchor="middle" class="donut-label">سفارش</text>'
                    . '</svg><div class="donut-legend">' . $legend . '</div></div>';
            };
            $svgArea = static function (array $data): string {
                // نمودار خطی سود/هزینه با گرادیان
                $w = 560; $h = 220; $pad = 36;
                $maxV = 1;
                foreach ($data as $d) { $maxV = max($maxV, $d['revenue'], $d['cost']); }
                $n = count($data);
                if ($n < 2) { return '<p class="muted">داده کافی نیست</p>'; }
                $x = static function ($i) use ($n, $w, $pad) { return $pad + ($i * ($w - 2 * $pad) / max(1, $n - 1)); };
                $y = static function ($v) use ($maxV, $h, $pad) { return $h - $pad - ($v / $maxV) * ($h - 2 * $pad); };
                $revPts = []; $costPts = [];
                foreach ($data as $i => $d) {
                    $revPts[] = round($x($i), 1) . ',' . round($y($d['revenue']), 1);
                    $costPts[] = round($x($i), 1) . ',' . round($y($d['cost']), 1);
                }
                $revLine = implode(' ', $revPts); $costLine = implode(' ', $costPts);
                $revArea = $pad . ',' . ($h - $pad) . ' ' . $revLine . ' ' . ($w - $pad) . ',' . ($h - $pad);
                $costArea = $pad . ',' . ($h - $pad) . ' ' . $costLine . ' ' . ($w - $pad) . ',' . ($h - $pad);
                $uid = 'a' . substr(md5($revLine), 0, 6);
                $dots = '';
                foreach ($data as $i => $d) {
                    $dots .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($d['revenue']), 1) . '" r="4.5" fill="#16a34a" stroke="#fff" stroke-width="2"><title>' . e($d['month']) . ': ' . number_format($d['revenue']) . '</title></circle>';
                    $dots .= '<circle cx="' . round($x($i), 1) . '" cy="' . round($y($d['cost']), 1) . '" r="4.5" fill="#dc2626" stroke="#fff" stroke-width="2"><title>' . e($d['month']) . ': ' . number_format($d['cost']) . '</title></circle>';
                }
                $labels = '';
                foreach ($data as $i => $d) {
                    if ($i % 2 === 0) { $labels .= '<text x="' . round($x($i), 1) . '" y="' . ($h - 10) . '" text-anchor="middle" class="chart-xlabel">' . e(substr($d['month'], 5)) . '</text>'; }
                }
                return '<svg viewBox="0 0 ' . $w . ' ' . $h . '" class="fancy-chart neon-chart" role="img">'
                    . '<defs><linearGradient id="' . $uid . 'g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#00e676" stop-opacity=".4"/><stop offset="1" stop-color="#00e676" stop-opacity="0"/></linearGradient>'
                    . '<linearGradient id="' . $uid . 'r" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff5252" stop-opacity=".3"/><stop offset="1" stop-color="#ff5252" stop-opacity="0"/></linearGradient>'
                    . '<filter id="' . $uid . 'glow" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="3.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter></defs>'
                    . '<polygon points="' . $revArea . '" fill="url(#' . $uid . 'g)"/>'
                    . '<polygon points="' . $costArea . '" fill="url(#' . $uid . 'r)"/>'
                    . '<polyline points="' . $revLine . '" fill="none" stroke="#00e676" stroke-width="3.5" stroke-linecap="round" class="chart-line" filter="url(#' . $uid . 'glow)"/>'
                    . '<polyline points="' . $costLine . '" fill="none" stroke="#ff5252" stroke-width="3" stroke-linecap="round" stroke-dasharray="7 4" class="chart-line" filter="url(#' . $uid . 'glow)"/>'
                    . $dots . $labels
                    . '</svg><div class="chart-legend"><span><i style="background:#00e676;box-shadow:0 0 8px #00e676"></i>درآمد</span><span><i style="background:#ff5252;box-shadow:0 0 8px #ff5252"></i>هزینه</span></div>';
            };
            $svgFunnel = static function (array $stages): string {
                // قیف پایپ‌لاین سفارش
                $labels = ['new' => 'جدید', 'confirmed' => 'تأیید شده', 'in_production' => 'در تولید', 'ready' => 'آماده', 'delivered' => 'تحویل شده'];
                $colors = ['new' => '#93c5fd', 'confirmed' => '#6ee7b7', 'in_production' => '#fcd34d', 'ready' => '#c4b5fd', 'delivered' => '#6ee7b7'];
                $max = max(1, max($stages));
                $html = '<div class="funnel">';
                $prev = null;
                foreach ($labels as $k => $t) {
                    $v = (int) ($stages[$k] ?? 0);
                    $w = max(12, round($v / $max * 100));
                    $conv = '';
                    if ($prev !== null && $prev > 0) {
                        $pct = round($v / $prev * 100);
                        $conv = '<span class="funnel-conv">▼ ' . $pct . '٪</span>';
                    }
                    $html .= $conv . '<div class="funnel-bar" style="width:' . $w . '%;background:linear-gradient(135deg,' . $colors[$k] . ',' . $colors[$k] . 'cc)"><span>' . e($t) . '</span><b>' . $v . '</b></div>';
                    $prev = $v;
                }
                return $html . '</div>';
            };
            $svgTimeline = static function (array $data): string {
                // تایم‌لاین ۳۰ روزه میله‌ای
                $max = 1;
                foreach ($data as $d) { $max = max($max, $d['value']); }
                $bars = '';
                foreach ($data as $d) {
                    $h = max(4, round($d['value'] / $max * 100));
                    $bars .= '<div class="tl-bar" style="height:' . $h . '%" title="' . e($d['day']) . ': ' . (int) $d['value'] . ' سفارش"></div>';
                }
                return '<div class="timeline-chart">' . $bars . '</div><p class="muted center">سفارش‌های ۳۰ روز اخیر</p>';
            };
            // شمارنده بازدید امروز و این ماه — بدون ربات و ادمین (۹٫۲۲)
            $visitsToday = 0; $visitsMonth = 0;
            try {
                $visitsToday = (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND substr(created_at,1,10) = date('now')")->fetchColumn();
                $visitsMonth = (int) $pdo->query("SELECT COUNT(*) FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND substr(created_at,1,7) = substr(date('now'),1,7)")->fetchColumn();
            } catch (Throwable $e) {}
            // بازدید روزانه ۱۴ روز اخیر — بدون ربات و ادمین (۹٫۲۲)
            $chartVisits = [];
            try {
                $vrows = $pdo->query("SELECT substr(created_at,1,10) as d, COUNT(*) as c FROM visit_logs WHERE kind = 'visit' AND COALESCE(is_bot,0) = 0 AND COALESCE(admin_user,'') = '' AND created_at >= date('now','-13 days') GROUP BY d ORDER BY d")->fetchAll(PDO::FETCH_ASSOC);
                $vmap = [];
                foreach ($vrows as $vr) { $vmap[$vr['d']] = (int) $vr['c']; }
                for ($i = 13; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-$i days"));
                    $chartVisits[] = ['day' => $d, 'value' => $vmap[$d] ?? 0];
                }
            } catch (Throwable $e) {}

            $renderChart = static function (string $key) use ($chartIncome, $chartOrderStatus, $chartExpenseCat, $chartProdStages, $chartProfit, $chartFunnel, $chartTimeline, $chartVisits, $renderBarChart, $dashWidgetPages, $chartJs): string {
                if (isset($dashWidgetPages[$key]) && !admin_can_page($dashWidgetPages[$key])) {
                    return '';
                }
                switch ($key) {
                    case 'chart_income':
                        $labels = array_column($chartIncome, 'month');
                        $vals = array_map(fn($r) => (int) $r['value'], $chartIncome);
                        return $labels === [] ? '' : '<section class="card"><h3>دریافتی ۶ ماه اخیر (تومان)</h3>' . $chartJs('bar', $labels, [['label' => 'دریافتی', 'data' => $vals]]) . '</section>';
                    case 'chart_orders':
                        $labels = array_column($chartOrderStatus, 'label');
                        $vals = array_column($chartOrderStatus, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>سفارش‌ها برحسب وضعیت</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]]) . '</section>';
                    case 'chart_expenses':
                        $labels = array_column($chartExpenseCat, 'label');
                        $vals = array_column($chartExpenseCat, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>هزینه‌ها برحسب دسته</h3>' . $chartJs('bar', $labels, [['label' => 'مبلغ', 'data' => $vals]]) . '</section>';
                    case 'chart_production':
                        $labels = array_column($chartProdStages, 'label');
                        $vals = array_column($chartProdStages, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>تولید برحسب مرحله</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]]) . '</section>';
                    case 'chart_donut_orders':
                        $labels = array_column($chartOrderStatus, 'label');
                        $vals = array_column($chartOrderStatus, 'value');
                        return $labels === [] ? '' : '<section class="card"><h3>توزیع سفارش‌ها</h3>' . $chartJs('doughnut', $labels, [['data' => $vals]]) . '</section>';
                    case 'chart_profit':
                        $labels = array_column($chartProfit, 'month');
                        $rev = array_column($chartProfit, 'revenue');
                        $cost = array_column($chartProfit, 'cost');
                        return $labels === [] ? '' : '<section class="card wide"><h3>درآمد در برابر هزینه — ۶ ماه اخیر</h3>' . $chartJs('line', $labels, [['label' => 'درآمد', 'data' => $rev], ['label' => 'هزینه', 'data' => $cost]]) . '</section>';
                    case 'chart_funnel':
                        $flabels = ['new' => 'جدید', 'confirmed' => 'تأیید شده', 'in_production' => 'در تولید', 'ready' => 'آماده', 'delivered' => 'تحویل شده'];
                        $labels = []; $vals = [];
                        foreach ($flabels as $k => $t) { $labels[] = $t; $vals[] = (int) ($chartFunnel[$k] ?? 0); }
                        return '<section class="card"><h3>قیف سفارشات</h3>' . $chartJs('bar', $labels, [['label' => 'تعداد', 'data' => $vals]], ['indexAxis' => 'y']) . '</section>';
                    case 'chart_timeline':
                        $labels = array_map(fn($d) => substr($d['day'], 5), $chartTimeline);
                        $vals = array_column($chartTimeline, 'value');
                        return '<section class="card wide"><h3>تایم‌لاین سفارشات — ۳۰ روز اخیر</h3>' . $chartJs('bar', $labels, [['label' => 'سفارش', 'data' => $vals]]) . '</section>';
                    case 'chart_visits':
                        $vlabels = array_map(fn($d) => substr($d['day'], 5), $chartVisits);
                        $vvals = array_column($chartVisits, 'value');
                        $vtotal = array_sum($vvals);
                        return '<section class="card wide"><h3>بازدید روزانه — ۱۴ روز اخیر <span class="muted">(مجموع: ' . (int) $vtotal . ')</span></h3>' . $chartJs('line', $vlabels, [['label' => 'بازدید', 'data' => $vvals]]) . '</section>';
                }
                return '';
            };
            ?>
            <div class="dash-head">
                <h1>داشبورد</h1>
                <?php $nowTs = time(); $gregDate = date('Y/m/d', $nowTs); $gregWeekdays = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday']; $gregW = $gregWeekdays[(int)date('w',$nowTs)]; ?>
                <span class="dash-date has-tooltip"><?= nav_icon('calendar') ?> <?= e(ll_jalali_today()) ?>
                    <span class="tooltip">
                        <strong><?= e(ll_jalali_format(date('Y-m-d H:i:s', $nowTs), false, true)) ?></strong><br>
                        <span class="muted"><?= e($gregDate) ?> · <?= e($gregW) ?></span>
                    </span>
                </span>
            </div>
            <p class="muted">نمای کلی پنل و دسترسی سریع به بخش‌های پرکاربرد. هر کاربر فقط کارت‌ها و نمودارهای مربوط به بخش‌های مجاز خودش را می‌بیند.</p>

            <?php if (admin_can_page('settings')): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn" data-toggle-panel="dash-customize-panel" aria-expanded="false">شخصی‌سازی داشبورد</button>
            </div>
            <div class="crud-panel" id="dash-customize-panel" hidden>
                <section class="card wide">
                    <h2 style="margin-top:0">ویجت‌های داشبورد</h2>
                    <p class="muted">تیک هر ویجت = نمایش آن؛ دکمه‌های ↑ و ↓ ترتیب نمایش را جابه‌جا می‌کنند. کارت‌های شرطی (مثل «در انتظار تأیید») فقط وقتی مقداری دارند نشان داده می‌شوند.</p>
                    <form method="post" id="dash-widget-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save_dashboard">
                        <div id="dash-widget-rows">
                        <?php $allKeysOrdered = array_merge($dashEnabled, array_values(array_diff(array_keys($dashWidgetDefs), $dashEnabled))); ?>
                        <?php foreach ($allKeysOrdered as $wk): ?>
                            <div class="dash-wrow" style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px dashed #e5e7eb">
                                <label class="check" style="flex:1;margin:0"><input type="checkbox" name="on[<?= e($wk) ?>]" value="1"<?= isset($dashOn[$wk]) ? ' checked' : '' ?>> <?= e($dashWidgetDefs[$wk][0]) ?></label>
                                <input type="hidden" name="w[]" value="<?= e($wk) ?>">
                                <button type="button" class="btn small" data-move="up" title="بالا">↑</button>
                                <button type="button" class="btn small" data-move="down" title="پایین">↓</button>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn add" style="margin-top:12px">💾 ذخیره چیدمان</button>
                    </form>
                    <script>
                    (function(){
                        var box = document.getElementById('dash-widget-rows');
                        if (!box) return;
                        box.addEventListener('click', function(ev){
                            var btn = ev.target.closest('button[data-move]');
                            if (!btn) return;
                            var row = btn.closest('.dash-wrow');
                            if (!row) return;
                            if (btn.getAttribute('data-move') === 'up' && row.previousElementSibling) {
                                box.insertBefore(row, row.previousElementSibling);
                            }
                            if (btn.getAttribute('data-move') === 'down' && row.nextElementSibling) {
                                box.insertBefore(row.nextElementSibling, row);
                            }
                        });
                    })();
                    </script>
                </section>
            </div>
            <?php endif; ?>

            <div class="flagship-hero">
                <span class="flagship-live">زنده</span>
                <h2>داشبورد لاینرلایت</h2>
                <p><?= e($shamsiDate ?? '') ?> — نمای کلی کسب‌وکار شما</p>
            </div>
            <div class="stat-grid dash-cards" id="dash-cards">
                <?php foreach ($dashEnabled as $wk): if (($dashWidgetDefs[$wk][1] ?? '') !== 'stat' || str_starts_with($wk, 'kpi_')) { continue; } echo $renderStat($wk); ?>
                <?php if ($wk === 'stat_orders' && $lowStockCount > 0 && admin_can_page('materials')): ?>
                <a class="stat-card sc-red sc-alert-red" href="admin.php?page=materials"><span><i class="sc-ico">' . $dash_icon('alert') . '</i>مواد رو به اتمام</span><strong><?= (int) $lowStockCount ?> ماده</strong></a>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="stat-grid dash-kpi" id="dash-kpi">
                <?php foreach ($dashEnabled as $wk): if (!str_starts_with($wk, 'kpi_')) { continue; } echo $renderStat($wk); endforeach; ?>
            </div>

            <?php $hasChart = false; foreach ($dashEnabled as $wk) { if (($dashWidgetDefs[$wk][1] ?? '') === 'chart' && (!isset($dashWidgetPages[$wk]) || admin_can_page($dashWidgetPages[$wk]))) { $hasChart = true; break; } } ?>
            <?php if ($hasChart): ?>
            <div class="stat-grid dash-charts" id="dash-charts">
                <?php foreach ($dashEnabled as $wk): if (($dashWidgetDefs[$wk][1] ?? '') !== 'chart') { continue; } echo $renderChart($wk); endforeach; ?>
            </div>
            <?php endif; ?>

            <script src="assets/sortable.min.js"></script>
            <script>
            (function(){
                if (typeof Sortable === 'undefined') return;
                ['dash-cards', 'dash-kpi', 'dash-charts'].forEach(function(id){
                    var el = document.getElementById(id);
                    if (!el) return;
                    // بازیابی ترتیب ذخیره‌شده
                    var saved = null;
                    try { saved = JSON.parse(localStorage.getItem('dash-order-' + id) || 'null'); } catch(e){}
                    if (Array.isArray(saved) && saved.length) {
                        var items = Array.from(el.children);
                        var map = {};
                        items.forEach(function(c){ var k = c.dataset.widget || c.textContent.trim().slice(0,30); map[k] = c; });
                        saved.forEach(function(k){ if (map[k]) el.appendChild(map[k]); });
                    }
                    // فعال‌سازی درگ
                    Sortable.create(el, {
                        animation: 200,
                        delay: 250,
                        delayOnTouchOnly: true,
                        ghostClass: 'dash-drag-ghost',
                        chosenClass: 'dash-drag-chosen',
                        dragClass: 'dash-drag-active',
                        preventOnFilter: false,
                        onStart: function(){ document.body.classList.add('dash-dragging'); },
                        onEnd: function(evt){
                            document.body.classList.remove('dash-dragging');
                            var order = Array.from(el.children).map(function(c, i){ return 'w' + i + ':' + (c.textContent.trim().slice(0,20)); });
                            try { localStorage.setItem('dash-order-' + id, JSON.stringify(order)); } catch(e){}
                        },
                        onMove: function(){ return true; }
                    });
                    // جلوگیری از کلیک لینک بعد از درگ
                    el.addEventListener('click', function(e){
                        if (document.body.classList.contains('dash-dragging')) { e.preventDefault(); e.stopPropagation(); }
                    }, true);
                });
            })();
            </script>
            <style>
            .dash-drag-ghost{opacity:.4;background:#dbeafe!important}
            .dash-drag-chosen{box-shadow:0 8px 24px rgba(37,99,235,.3)!important}
            .dash-drag-active{opacity:.9}
            body.dash-dragging #dash-cards .stat-card, body.dash-dragging #dash-kpi .stat-card{pointer-events:none}
            body.dash-dragging{user-select:none;-webkit-user-select:none}
            #dash-cards .stat-card, #dash-kpi .stat-card{cursor:grab;touch-action:pan-y}
            #dash-cards .stat-card:active, #dash-kpi .stat-card:active{cursor:grabbing}
            </style>
            <?php if ($lowStockCount > 0 && admin_can_page('materials')): ?>
            <div class="alert error">
                موجودی این مواد به حد هشدار رسیده است:
                <?php foreach ($lowStockList as $lm): ?>
                    <strong><?= e($lm['name']) ?></strong> (<?= e(format_qty((float) $lm['stock_qty'])) ?> <?= e($lm['unit']) ?>)
                <?php endforeach; ?>
                — <a href="admin.php?page=materials">رفتن به انبار</a>
            </div>
            <?php endif; ?>
