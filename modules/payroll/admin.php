<?php
// modules/payroll/admin.php — مدیریت حقوق و دستمزد (جدا از HR.php در ۹٫۹۹٫۲۶)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }


if (!function_exists('hr_post_actions')) {
function hr_post_actions(): array
{
    return ['add_employee', 'update_employee', 'deactivate_employee', 'activate_employee',
        'add_salary_payment', 'delete_salary_payment', 'save_wage_params'];
}
}

if (!function_exists('hr_wage_param_defs')) {
function hr_wage_param_defs(): array
{
    return [
        'wage_min_daily'        => ['label' => 'حداقل مزد روزانه (تومان)', 'def' => '554185'],
        'wage_min_monthly'      => ['label' => 'حداقل مزد ماهانه ۳۰ روزه (تومان)', 'def' => '16625550'],
        'wage_bon_kargari'      => ['label' => 'بن کارگری ماهانه (تومان)', 'def' => '2200000'],
        'wage_housing'          => ['label' => 'حق مسکن ماهانه (تومان)', 'def' => '3000000'],
        'wage_marriage'         => ['label' => 'حق تأهل ماهانه (تومان)', 'def' => '500000'],
        'wage_child_allowance'  => ['label' => 'حق اولاد هر فرزند (تومان)', 'def' => '1662555'],
        'wage_seniority'        => ['label' => 'پایه سنوات ماهانه (تومان)', 'def' => '500000'],
        'wage_insurance_worker' => ['label' => 'بیمه سهم کارگر (٪)', 'def' => '7'],
        'wage_insurance_employer' => ['label' => 'بیمه سهم کارفرما (٪)', 'def' => '23'],
        'wage_tax_threshold'    => ['label' => 'سقف معافیت مالیاتی ماهانه (تومان)', 'def' => '24000000'],
    ];
}
}

if (!function_exists('hr_employment_type_label')) {
function hr_employment_type_label(string $type): string
{
    return [
        'full_time' => 'تمام‌وقت',
        'part_time' => 'پاره‌وقت',
        'contract'  => 'پروژه‌ای',
        'intern'    => 'کارآموز',
    ][$type] ?? $type;
}
}

if (!function_exists('hr_status_label')) {
function hr_status_label(string $status): string
{
    return $status === 'active' ? 'فعال' : 'غیرفعال';
}
}

if (!function_exists('hr_tenure')) {
function hr_tenure(string $hireDate): string
{
    $hireDate = trim($hireDate);
    if ($hireDate === '') {
        return '—';
    }
    try {
        $start = new DateTime($hireDate);
    } catch (Throwable $e) {
        return '—';
    }
    $now = new DateTime();
    if ($start > $now) {
        return '—';
    }
    $diff = $start->diff($now);
    $parts = [];
    if ($diff->y > 0) {
        $parts[] = $diff->y . ' سال';
    }
    if ($diff->m > 0) {
        $parts[] = $diff->m . ' ماه';
    }
    if ($parts === []) {
        return 'کمتر از یک ماه';
    }
    return implode(' و ', $parts);
}
}

if (!function_exists('hr_handle_post')) {
function hr_handle_post(string $action): void
{
    global $pdo;
    switch ($action) {
        case 'add_employee':
        case 'update_employee':
            $eid = (int) ($_POST['id'] ?? 0);
            $fullName = trim((string) ($_POST['full_name'] ?? ''));
            if ($fullName === '') {
                throw new RuntimeException('نام و نام خانوادگی پرسنل را وارد کنید.');
            }
            $employmentType = (string) ($_POST['employment_type'] ?? 'full_time');
            if (!in_array($employmentType, ['full_time', 'part_time', 'contract', 'intern'], true)) {
                $employmentType = 'full_time';
            }
            $baseSalary = max(0, (int) ($_POST['base_salary'] ?? 0));
            $childrenCount = max(0, (int) ($_POST['children_count'] ?? 0));
            $data = [
                ':name'  => mb_substr($fullName, 0, 120),
                ':child' => $childrenCount,
                ':pos'   => mb_substr(trim((string) ($_POST['position'] ?? '')), 0, 120),
                ':mob'   => mb_substr(trim((string) ($_POST['mobile'] ?? '')), 0, 20),
                ':nid'   => mb_substr(trim((string) ($_POST['national_id'] ?? '')), 0, 20),
                ':hire'  => trim((string) ($_POST['hire_date'] ?? '')),
                ':sal'   => $baseSalary,
                ':etype' => $employmentType,
                ':notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 1000),
            ];
            if ($action === 'update_employee' && $eid > 0) {
                $data[':id'] = $eid;
                $pdo->prepare('UPDATE employees SET full_name = :name, position = :pos, mobile = :mob, national_id = :nid, hire_date = :hire, base_salary = :sal, employment_type = :etype, children_count = :child, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                flash('ok', 'مشخصات پرسنل به‌روزرسانی شد.');
                redirect_admin('admin.php?page=employees&edit_id=' . $eid);
            }
            $pdo->prepare("INSERT INTO employees (full_name, position, mobile, national_id, hire_date, base_salary, employment_type, children_count, status, notes) VALUES (:name, :pos, :mob, :nid, :hire, :sal, :etype, :child, 'active', :notes)")->execute($data);
            flash('ok', 'پرسنل تازه ثبت شد.');
            redirect_admin('admin.php?page=employees');
            // no break

        case 'deactivate_employee':
        case 'activate_employee':
            $eid = (int) ($_POST['id'] ?? 0);
            if ($eid <= 0) {
                throw new RuntimeException('پرسنل نامعتبر است.');
            }
            $newStatus = $action === 'activate_employee' ? 'active' : 'inactive';
            $pdo->prepare('UPDATE employees SET status = :st, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute([':st' => $newStatus, ':id' => $eid]);
            flash('ok', $newStatus === 'active' ? 'پرسنل فعال شد.' : 'پرسنل غیرفعال شد. سوابق حقوقی او حفظ می‌شود.');
            redirect_admin('admin.php?page=employees');
            // no break

        case 'add_salary_payment':
            $eid = (int) ($_POST['employee_id'] ?? 0);
            $payMonth = trim((string) ($_POST['pay_month'] ?? ''));
            if ($eid <= 0) {
                throw new RuntimeException('پرسنل را انتخاب کنید.');
            }
            if (!preg_match('/^\d{4}-\d{2}$/', $payMonth)) {
                throw new RuntimeException('ماه پرداخت را با قالب سال-ماه وارد کنید؛ مثلاً 1405-07.');
            }
            $emp = $pdo->prepare('SELECT id, full_name, base_salary FROM employees WHERE id = :id LIMIT 1');
            $emp->execute([':id' => $eid]);
            $employee = $emp->fetch();
            if ($employee === false) {
                throw new RuntimeException('پرسنل انتخاب‌شده وجود ندارد.');
            }
            // جلوگیری از ثبت تکراری برای یک پرسنل در یک ماه
            $dup = $pdo->prepare('SELECT COUNT(*) FROM salary_payments WHERE employee_id = :eid AND pay_month = :pm');
            $dup->execute([':eid' => $eid, ':pm' => $payMonth]);
            if ((int) $dup->fetchColumn() > 0) {
                throw new RuntimeException('برای این پرسنل در ماه ' . $payMonth . ' قبلاً فیش حقوقی ثبت شده است.');
            }
            $base = max(0, (int) ($_POST['base_amount'] ?? 0));
            $bonus = max(0, (int) ($_POST['bonus'] ?? 0));
            $deduction = max(0, (int) ($_POST['deduction'] ?? 0));
            // خالص واقعی با فرمول کامل فیش (مزایا − بیمه − مالیات)، نه فرمول ساده؛
            // تا رقم لیست با رقم داخل فیش یکی باشد (نسخه ۹٫۱۴٫۲)
            $calc = hr_calculate_payslip($employee, [
                'base_amount' => $base,
                'bonus'       => $bonus,
                'deduction'   => $deduction,
            ]);
            $net = (int) ($calc['net'] ?? ($base + $bonus - $deduction));
            $paidDate = trim((string) ($_POST['paid_date'] ?? ''));
            $pdo->prepare('INSERT INTO salary_payments (employee_id, pay_month, base_amount, bonus, deduction, net_amount, paid_date, notes) VALUES (:eid, :pm, :base, :bonus, :ded, :net, :pd, :notes)')->execute([
                ':eid'   => $eid,
                ':pm'    => $payMonth,
                ':base'  => $base,
                ':bonus' => $bonus,
                ':ded'   => $deduction,
                ':net'   => $net,
                ':pd'    => $paidDate,
                ':notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 500),
            ]);
            flash('ok', 'فیش حقوقی ' . $employee['full_name'] . ' برای ماه ' . $payMonth . ' ثبت شد (خالص: ' . format_price($net) . ' تومان).');
            redirect_admin('admin.php?page=payroll&pay_month=' . urlencode($payMonth));
            // no break

        case 'save_wage_params':
            foreach (hr_wage_param_defs() as $k => $def) {
                $v = max(0, (int) ($_POST[$k] ?? $def['def']));
                set_setting($k, (string) $v);
            }
            flash('ok', 'پارامترهای حقوق وزارت کار ذخیره شد.');
            redirect_admin('admin.php?page=payroll&wage=1');
            // no break

        case 'delete_salary_payment':
            $pid = (int) ($_POST['id'] ?? 0);
            if ($pid <= 0) {
                throw new RuntimeException('فیش حقوقی نامعتبر است.');
            }
            $pdo->prepare('DELETE FROM salary_payments WHERE id = :id')->execute([':id' => $pid]);
            flash('ok', 'فیش حقوقی حذف شد.');
            redirect_admin('admin.php?page=payroll');
            // no break
    }
}
}

if (!function_exists('hr_load_data')) {
function hr_load_data(): array
{
    $pdo = db();
    $data = [
        'employeesList'   => [],
        'activeEmployees' => [],
        'editEmployee'    => null,
        'paymentsList'    => [],
        'monthlyTotals'   => [],
        'payMonthFilter'  => trim((string) ($_GET['pay_month'] ?? '')),
        'totalNetAll'     => 0,
    ];
    $data['employeesList'] = $pdo->query("SELECT * FROM employees ORDER BY status = 'active' DESC, full_name ASC")->fetchAll();
    $data['activeEmployees'] = array_values(array_filter($data['employeesList'], fn($e) => ($e['status'] ?? '') === 'active'));
    if (isset($_GET['edit_id'])) {
        $st = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
        $st->execute([':id' => (int) $_GET['edit_id']]);
        $row = $st->fetch();
        $data['editEmployee'] = $row === false ? null : $row;
    }
    // فیش‌های حقوقی + جمع ماهانه
    $sql = 'SELECT sp.*, e.full_name AS employee_name FROM salary_payments sp JOIN employees e ON e.id = sp.employee_id WHERE 1 = 1';
    $params = [];
    if ($data['payMonthFilter'] !== '') {
        $sql .= ' AND sp.pay_month = :pm';
        $params[':pm'] = $data['payMonthFilter'];
    }
    $sql .= ' ORDER BY sp.pay_month DESC, sp.id DESC LIMIT 300';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $data['paymentsList'] = $st->fetchAll();
    foreach ($data['paymentsList'] as $p) {
        $data['totalNetAll'] += (int) $p['net_amount'];
    }
    $data['monthlyTotals'] = $pdo->query('SELECT pay_month, COUNT(*) AS cnt, SUM(net_amount) AS total_net, SUM(base_amount) AS total_base, SUM(bonus) AS total_bonus, SUM(deduction) AS total_deduction FROM salary_payments GROUP BY pay_month ORDER BY pay_month DESC')->fetchAll();
    // پروفایل پرسنل
    $data['profileEmployee'] = null;
    $data['profilePayments'] = [];
    $data['profileTotals'] = ['count' => 0, 'net' => 0, 'base' => 0];
    if (isset($_GET['id']) && (int) $_GET['id'] > 0) {
        $pst = $pdo->prepare('SELECT * FROM employees WHERE id = :id LIMIT 1');
        $pst->execute([':id' => (int) $_GET['id']]);
        $prow = $pst->fetch();
        if ($prow !== false) {
            $data['profileEmployee'] = $prow;
            $ppst = $pdo->prepare('SELECT * FROM salary_payments WHERE employee_id = :eid ORDER BY pay_month DESC, id DESC');
            $ppst->execute([':eid' => (int) $prow['id']]);
            $data['profilePayments'] = $ppst->fetchAll();
            foreach ($data['profilePayments'] as $pp) {
                $data['profileTotals']['count']++;
                $data['profileTotals']['net'] += (int) $pp['net_amount'];
                $data['profileTotals']['base'] += (int) $pp['base_amount'];
            }
        }
    }
    // فیش حقوقی تکی برای چاپ
    $data['payslipPayment'] = null;
    $data['payslipEmployee'] = null;
    if (isset($_GET['slip_id']) && (int) $_GET['slip_id'] > 0) {
        // ستون‌های هم‌نام sp و e را جدا می‌گیریم تا قاطی نشوند
        $sst = $pdo->prepare('SELECT sp.id AS slip_id, sp.pay_month, sp.base_amount, sp.bonus, sp.deduction, sp.net_amount, sp.paid_date, sp.notes AS slip_notes, e.* FROM salary_payments sp JOIN employees e ON e.id = sp.employee_id WHERE sp.id = :id LIMIT 1');
        $sst->execute([':id' => (int) $_GET['slip_id']]);
        $srow = $sst->fetch();
        if ($srow !== false) {
            $data['payslipEmployee'] = $srow;
            $data['payslipPayment'] = [
                'id' => (int) $srow['slip_id'],
                'pay_month' => $srow['pay_month'],
                'base_amount' => $srow['base_amount'],
                'bonus' => $srow['bonus'],
                'deduction' => $srow['deduction'],
                'net_amount' => $srow['net_amount'],
                'paid_date' => $srow['paid_date'],
                'notes' => $srow['slip_notes'],
            ];
        }
    }
    return $data;
}
}

if (!function_exists('hr_calculate_payslip')) {
function hr_calculate_payslip(array $employee, array $payment): array
{
    $base      = max(0, (int) ($payment['base_amount'] ?? 0));
    $bonus     = max(0, (int) ($payment['bonus'] ?? 0));       // اضافه‌کاری / پاداش
    $otherDed  = max(0, (int) ($payment['deduction'] ?? 0));   // سایر کسورات
    $children  = max(0, (int) ($employee['children_count'] ?? 0));

    $bonKargari    = max(0, (int) get_setting('wage_bon_kargari', '2200000'));
    $housing       = max(0, (int) get_setting('wage_housing', '3000000'));
    $marriage      = max(0, (int) get_setting('wage_marriage', '500000'));
    $childAllow    = max(0, (int) get_setting('wage_child_allowance', '1662555'));
    $seniority     = max(0, (int) get_setting('wage_seniority', '500000'));
    $insWorkerPct  = max(0, (int) get_setting('wage_insurance_worker', '7'));
    $taxThreshold  = max(0, (int) get_setting('wage_tax_threshold', '24000000'));

    $childTotal = $children * $childAllow;
    // پایه سنوات: فقط برای پرسنل با حداقل ۱ سال سابقه
    $tenure = hr_tenure((string) ($employee['hire_date'] ?? ''));
    $seniorityAmt = ($tenure !== '—' && $tenure !== 'کمتر از یک ماه' && strpos($tenure, 'سال') !== false) ? $seniority : 0;

    $earnings = [
        ['label' => 'حقوق پایه', 'amount' => $base],
        ['label' => 'بن کارگری (کمک‌هزینه اقلام مصرفی)', 'amount' => $bonKargari],
        ['label' => 'حق مسکن', 'amount' => $housing],
        ['label' => 'حق تأهل', 'amount' => $marriage],
        ['label' => 'حق اولاد (' . $children . ' فرزند)', 'amount' => $childTotal],
        ['label' => 'پایه سنوات', 'amount' => $seniorityAmt],
        ['label' => 'اضافه‌کاری / پاداش', 'amount' => $bonus],
    ];
    $totalEarnings = $base + $bonKargari + $housing + $marriage + $childTotal + $seniorityAmt + $bonus;

    // بیمه سهم کارگر: درصد قابل‌تنظیم از جمع مزایا (پیش‌فرض ۷٪)
    $insurance = (int) round($totalEarnings * $insWorkerPct / 100);
    // مالیات حقوق: ۱۰٪ مازاد بر سقف معافیت
    $taxable = max(0, $totalEarnings - $taxThreshold);
    $tax = (int) round($taxable * 0.10);

    $deductions = [
        ['label' => 'بیمه سهم کارگر (' . $insWorkerPct . '٪)', 'amount' => $insurance],
        ['label' => 'مالیات حقوق', 'amount' => $tax],
        ['label' => 'سایر کسورات', 'amount' => $otherDed],
    ];
    $totalDeductions = $insurance + $tax + $otherDed;

    $net = $totalEarnings - $totalDeductions;

    return [
        'earnings'         => $earnings,
        'total_earnings'   => $totalEarnings,
        'deductions'       => $deductions,
        'total_deductions' => $totalDeductions,
        'net'              => $net,
        'children'         => $children,
        'params'           => [
            'bon_kargari'    => $bonKargari,
            'housing'        => $housing,
            'child_allowance'=> $childAllow,
            'tax_threshold'  => $taxThreshold,
        ],
    ];
}
}

if (!function_exists('hr_render_payroll')) {
function hr_render_payroll(array $d): void
{
    extract($d);
    ?>
            <h1>حقوق و دستمزد</h1>
            <p class="muted">فیش حقوقی هر پرسنل برای هر ماه؛ خالص پرداختی به‌صورت خودکار حساب می‌شود: <strong>پایه + پاداش − کسورات</strong>. برای یک پرسنل در یک ماه فقط یک فیش ثبت می‌شود.</p>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>جمع خالص (نمایش فعلی)</span><strong><?= e(format_price($totalNetAll)) ?> تومان</strong></div>
                <div class="stat-card"><span>تعداد فیش‌ها</span><strong><?= count($paymentsList) ?></strong></div>
                <a class="stat-card" href="admin.php?page=employees"><span>پرسنل</span><strong>مدیریت پرسنل</strong></a>
            </div>

            <div class="crud-toolbar">
                <button type="button" class="btn" data-toggle-panel="wage-params-panel" aria-expanded="false">⚙ پارامترهای حقوق وزارت کار (۱۴۰۵)</button>
            </div>
            <div class="crud-panel" id="wage-params-panel" hidden>
            <h2>پارامترهای حقوق (مصوب ۱۴۰۵)</h2>
            <p class="muted">نرخ‌های رسمی شورای عالی کار؛ اگر تا پایان مهر ترمیمی تصویب شد، فقط همین اعداد را عوض کنید — نیازی به تغییر کد نیست.</p>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_wage_params">
                <div class="inline-fields">
                <?php foreach (hr_wage_param_defs() as $k => $def): ?>
                    <label><?= e($def['label']) ?>
                        <input type="number" name="<?= e($k) ?>" min="0" step="1" dir="ltr" value="<?= e((string) get_setting($k, $def['def'])) ?>">
                    </label>
                <?php endforeach; ?>
                </div>
                <button type="submit" class="btn add">ذخیره پارامترها</button>
            </form>
            </div>

            <?php if ($monthlyTotals !== []): ?>
            <h2>جمع حقوق هر ماه</h2>
            <table>
                <thead><tr><th>ماه</th><th>تعداد فیش</th><th>جمع پایه</th><th>جمع پاداش</th><th>جمع کسورات</th><th>جمع خالص پرداختی</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($monthlyTotals as $mt): ?>
                    <tr>
                        <td><strong dir="ltr"><?= e($mt['pay_month']) ?></strong></td>
                        <td><?= (int) $mt['cnt'] ?></td>
                        <td><?= e(format_price($mt['total_base'])) ?></td>
                        <td><?= e(format_price($mt['total_bonus'])) ?></td>
                        <td><?= e(format_price($mt['total_deduction'])) ?></td>
                        <td><strong><?= e(format_price($mt['total_net'])) ?> تومان</strong></td>
                        <td><a class="btn small" href="admin.php?page=payroll&pay_month=<?= e(urlencode((string) $mt['pay_month'])) ?>">فیش‌های این ماه</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="payroll-form-panel" aria-expanded="false">+ ثبت فیش حقوقی</button>
            </div>
            <div class="crud-panel" id="payroll-form-panel" hidden>
            <h2>فیش حقوقی تازه</h2>
            <?php if ($activeEmployees === []): ?>
                <div class="card wide"><p class="muted">اول باید از صفحه «پرسنل» حداقل یک پرسنل فعال ثبت کنید.</p></div>
            <?php else: ?>
            <form method="post" class="card wide" id="salary-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_salary_payment">
                <div class="inline-fields">
                    <label>پرسنل *
                        <select name="employee_id" id="sp-employee" required>
                            <option value="">— انتخاب پرسنل —</option>
                            <?php foreach ($activeEmployees as $ae): ?>
                            <option value="<?= (int) $ae['id'] ?>" data-salary="<?= (int) $ae['base_salary'] ?>"><?= e($ae['full_name']) ?> (پایه: <?= e(format_price($ae['base_salary'])) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>ماه پرداخت (سال-ماه) *
                        <input type="text" name="pay_month" required dir="ltr" placeholder="1405-07" pattern="\d{4}-\d{2}" maxlength="7">
                    </label>
                    <label>تاریخ پرداخت
                        <input type="date" name="paid_date" value="<?= e(date('Y-m-d')) ?>">
                    </label>
                </div>
                <div class="inline-fields">
                    <label>مبلغ پایه (تومان) *
                        <input type="number" name="base_amount" id="sp-base" min="0" step="1" required inputmode="numeric" dir="ltr" value="0">
                    </label>
                    <label>پاداش (تومان)
                        <input type="number" name="bonus" id="sp-bonus" min="0" step="1" inputmode="numeric" dir="ltr" value="0">
                    </label>
                    <label>کسورات (تومان)
                        <input type="number" name="deduction" id="sp-deduction" min="0" step="1" inputmode="numeric" dir="ltr" value="0">
                    </label>
                </div>
                <p><strong>خالص پرداختی: <span id="sp-net-preview">۰</span> تومان</strong> <span class="muted">(پایه + پاداش − کسورات)</span></p>
                <label>یادداشت
                    <input type="text" name="notes" maxlength="500" placeholder="مثلاً اضافه‌کاری، وام...">
                </label>
                <button type="submit" class="btn add">ثبت فیش حقوقی</button>
            </form>
            <script>
            (function(){
                var emp = document.getElementById('sp-employee');
                var base = document.getElementById('sp-base');
                var bonus = document.getElementById('sp-bonus');
                var ded = document.getElementById('sp-deduction');
                var prev = document.getElementById('sp-net-preview');
                if (!emp || !base) return;
                function fmt(n){ return Number(n || 0).toLocaleString('fa-IR'); }
                function calc(){
                    var net = (+base.value || 0) + (+bonus.value || 0) - (+ded.value || 0);
                    prev.textContent = fmt(net);
                }
                emp.addEventListener('change', function(){
                    var opt = emp.options[emp.selectedIndex];
                    if (opt && opt.getAttribute('data-salary')) { base.value = opt.getAttribute('data-salary'); }
                    calc();
                });
                [base, bonus, ded].forEach(function(el){ el.addEventListener('input', calc); });
                calc();
            })();
            </script>
            <?php endif; ?>
            </div>

            <h2>فیش‌های حقوقی<?= $payMonthFilter !== '' ? ' — ماه ' . e($payMonthFilter) : '' ?> (<?= count($paymentsList) ?>)</h2>
            <?php if ($payMonthFilter !== ''): ?>
            <p><a class="btn small" href="admin.php?page=payroll">حذف فیلتر ماه</a></p>
            <?php endif; ?>
            <?php if ($paymentsList === []): ?>
                <div class="card wide"><p class="muted">هنوز فیش حقوقی ثبت نشده است. با دکمه «+ ثبت فیش حقوقی» اولین فیش را ثبت کنید.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>ماه</th><th>پرسنل</th><th>پایه</th><th>پاداش</th><th>کسورات</th><th>خالص پرداختی</th><th>تاریخ پرداخت</th><th>یادداشت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($paymentsList as $p): ?>
                    <tr>
                        <td dir="ltr"><strong><?= e($p['pay_month']) ?></strong></td>
                        <td><?= e($p['employee_name']) ?></td>
                        <td><?= e(format_price($p['base_amount'])) ?></td>
                        <td><?= e(format_price($p['bonus'])) ?></td>
                        <td><?= e(format_price($p['deduction'])) ?></td>
                        <td><strong><?= e(format_price($p['net_amount'])) ?> تومان</strong></td>
                        <td><?= e($p['paid_date'] ?? '') !== '' ? e($p['paid_date']) : '<span class="muted">—</span>' ?></td>
                        <td><?= e($p['notes'] ?? '') !== '' ? e($p['notes']) : '<span class="muted">—</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=payslip&slip_id=<?= (int) $p['id'] ?>" target="_blank">🖨 چاپ فیش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این فیش حقوقی حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_salary_payment">
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
}

if (!function_exists('hr_render_payslip')) {
function hr_render_payslip(array $d): void
{
    extract($d);
    $emp = $payslipEmployee;
    $pay = $payslipPayment;
    if ($emp === null || $pay === null) {
        echo '<h1>فیش حقوقی پیدا نشد</h1><p><a class="btn" href="admin.php?page=payroll">بازگشت به حقوق و دستمزد</a></p>';
        return;
    }
    $calc = hr_calculate_payslip($emp, $pay);
    $settings = all_settings();
    $company = (string) ($settings['site_title'] ?? 'شرکت');
    $print = isset($_GET['print']);
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .payslip-sheet { display: block !important; border: none !important; box-shadow: none !important; }
        @page { size: A5 landscape; margin: 10mm; }
    }
    .payslip-sheet { background: #fff; color: #111; border: 1px solid #e5e7eb; border-radius: 12px; padding: 28px; max-width: 900px; margin: 0 auto; }
    .payslip-sheet h2 { margin: 0; font-size: 20px; }
    .payslip-head { text-align: center; border-bottom: 3px double #111; padding-bottom: 14px; margin-bottom: 18px; }
    .payslip-head .co { font-size: 22px; font-weight: 800; }
    .payslip-head .doc { font-size: 16px; margin-top: 6px; }
    .payslip-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px 24px; margin-bottom: 18px; font-size: 14px; }
    .payslip-info div span { color: #555; }
    .payslip-sheet table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 14px; }
    .payslip-sheet th, .payslip-sheet td { border: 1px solid #9ca3af; padding: 8px 10px; }
    .payslip-sheet thead th { background: #f3f4f6; }
    .payslip-sheet td.num, .payslip-sheet th.num { text-align: left; }
    .payslip-total td { font-weight: 800; background: #f9fafb; }
    .payslip-net td { font-weight: 800; font-size: 16px; background: #ecfdf5; }
    .sig-row { display: flex; gap: 40px; margin-top: 44px; }
    .sig-row div { flex: 1; border-top: 1px dashed #6b7280; padding-top: 8px; text-align: center; font-size: 14px; }
    </style>
    <div class="screen-area">
        <p><a href="admin.php?page=payroll">← بازگشت به حقوق و دستمزد</a><?php if ($emp): ?> | <a href="admin.php?page=employee_profile&id=<?= (int) $emp['id'] ?>">پروفایل <?= e($emp['full_name']) ?></a><?php endif; ?></p>
        <h1>فیش حقوقی</h1>
        <p><a class="btn primary small" href="admin.php?page=payslip&slip_id=<?= (int) $pay['id'] ?>&print=1" target="_blank">🖨 چاپ فیش</a></p>
    </div>
    <div class="payslip-sheet" id="payslip-sheet">
        <div class="payslip-head">
            <div class="co"><?= e($company) ?></div>
            <div class="doc">فیش حقوقی پرسنل — دوره: <strong dir="ltr"><?= e($pay['pay_month']) ?></strong></div>
        </div>
        <div class="payslip-info">
            <div><span>نام و نام خانوادگی: </span><strong><?= e($emp['full_name']) ?></strong></div>
            <div><span>کد پرسنلی: </span><strong dir="ltr"><?= (int) $emp['id'] ?></strong></div>
            <div><span>سمت: </span><strong><?= e($emp['position'] ?? '—') ?></strong></div>
            <div><span>کد ملی: </span><strong dir="ltr"><?= e($emp['national_id'] ?? '—') ?></strong></div>
            <div><span>تعداد فرزند: </span><strong><?= (int) ($emp['children_count'] ?? 0) ?> نفر</strong></div>
            <div><span>تاریخ پرداخت: </span><strong><?= e($pay['paid_date'] ?? '—') ?></strong></div>
        </div>

        <table>
            <thead><tr><th>شرح مزایا</th><th class="num">مبلغ (تومان)</th></tr></thead>
            <tbody>
                <?php foreach ($calc['earnings'] as $er): ?>
                <tr><td><?= e($er['label']) ?></td><td class="num"><?= e(format_price($er['amount'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="payslip-total"><td>جمع مزایا</td><td class="num"><?= e(format_price($calc['total_earnings'])) ?></td></tr>
            </tbody>
        </table>

        <table>
            <thead><tr><th>شرح کسورات</th><th class="num">مبلغ (تومان)</th></tr></thead>
            <tbody>
                <?php foreach ($calc['deductions'] as $dd): ?>
                <tr><td><?= e($dd['label']) ?></td><td class="num"><?= e(format_price($dd['amount'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="payslip-total"><td>جمع کسورات</td><td class="num"><?= e(format_price($calc['total_deductions'])) ?></td></tr>
                <tr class="payslip-net"><td>خالص پرداختی</td><td class="num"><?= e(format_price($calc['net'])) ?></td></tr>
            </tbody>
        </table>

        <?php if (!empty($pay['notes'])): ?><p style="font-size:13px;color:#555">یادداشت: <?= e($pay['notes']) ?></p><?php endif; ?>

        <div class="sig-row">
            <div>امضای کارفرما</div>
            <div>امضای پرسنل</div>
            <div>تاریخ: ....................</div>
        </div>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php
}
}
