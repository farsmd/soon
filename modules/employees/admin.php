<?php
// modules/employees/admin.php — مدیریت پرسنل (جدا از HR.php در ۹٫۹۹٫۲۶)
declare(strict_types=1);
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }


function hr_post_actions(): array
{
    return ['add_employee', 'update_employee', 'deactivate_employee', 'activate_employee',
        'add_salary_payment', 'delete_salary_payment', 'save_wage_params'];
}

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

function hr_employment_type_label(string $type): string
{
    return [
        'full_time' => 'تمام‌وقت',
        'part_time' => 'پاره‌وقت',
        'contract'  => 'پروژه‌ای',
        'intern'    => 'کارآموز',
    ][$type] ?? $type;
}

function hr_status_label(string $status): string
{
    return $status === 'active' ? 'فعال' : 'غیرفعال';
}

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

function hr_render_employees(array $d): void
{
    extract($d);
    $activeCount = count($activeEmployees);
    ?>
            <h1>پرسنل</h1>
            <p class="muted">فهرست پرسنل کارگاه؛ پرسنل اخراج‌شده یا تسویه‌شده را «غیرفعال» کنید تا سوابق حقوقی‌شان حفظ شود (حذف کامل انجام نمی‌شود).</p>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>کل پرسنل</span><strong><?= count($employeesList) ?></strong></div>
                <div class="stat-card"><span>پرسنل فعال</span><strong><?= $activeCount ?></strong></div>
                <a class="stat-card" href="admin.php?page=payroll"><span>حقوق و دستمزد</span><strong>مشاهده فیش‌ها</strong></a>
            </div>

            <?php if ($editEmployee === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="employee-form-panel" aria-expanded="false">+ افزودن پرسنل</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="employee-form-panel" <?= $editEmployee !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editEmployee !== null ? 'ویرایش پرسنل: ' . e($editEmployee['full_name'] ?? '') : 'پرسنل تازه' ?></h2>
            <form method="post" class="card wide">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editEmployee !== null ? 'update_employee' : 'add_employee' ?>">
                <?php if ($editEmployee !== null): ?><input type="hidden" name="id" value="<?= (int) $editEmployee['id'] ?>"><?php endif; ?>
                <div class="inline-fields">
                    <label>نام و نام خانوادگی *
                        <input type="text" name="full_name" required maxlength="120" value="<?= e($editEmployee['full_name'] ?? '') ?>" placeholder="مثلاً علی رضایی">
                    </label>
                    <label>سمت / عنوان شغلی
                        <input type="text" name="position" maxlength="120" value="<?= e($editEmployee['position'] ?? '') ?>" placeholder="مثلاً تکنسین مونتاژ">
                    </label>
                </div>
                <div class="inline-fields">
                    <label>شماره موبایل
                        <input type="text" name="mobile" maxlength="20" inputmode="tel" dir="ltr" value="<?= e($editEmployee['mobile'] ?? '') ?>" placeholder="09...">
                    </label>
                    <label>کد ملی
                        <input type="text" name="national_id" maxlength="20" inputmode="numeric" dir="ltr" value="<?= e($editEmployee['national_id'] ?? '') ?>">
                    </label>
                </div>
                <div class="inline-fields">
                    <label>تاریخ استخدام
                        <input type="date" name="hire_date" value="<?= e($editEmployee['hire_date'] ?? '') ?>">
                    </label>
                    <label>حقوق پایه ماهانه (تومان)
                        <input type="number" name="base_salary" min="0" step="1" inputmode="numeric" dir="ltr" value="<?= (int) ($editEmployee['base_salary'] ?? 0) ?>">
                    </label>
                    <label>نوع همکاری
                        <select name="employment_type">
                            <?php foreach (['full_time', 'part_time', 'contract', 'intern'] as $et): ?>
                            <option value="<?= $et ?>" <?= ($editEmployee['employment_type'] ?? 'full_time') === $et ? 'selected' : '' ?>><?= e(hr_employment_type_label($et)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>تعداد فرزند (برای حق اولاد)
                        <input type="number" name="children_count" min="0" max="20" step="1" inputmode="numeric" dir="ltr" value="<?= (int) ($editEmployee['children_count'] ?? 0) ?>">
                    </label>
                </div>
                <label>یادداشت
                    <textarea name="notes" rows="2" maxlength="1000"><?= e($editEmployee['notes'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="btn <?= $editEmployee !== null ? 'edit' : 'add' ?>"><?= $editEmployee !== null ? 'ذخیره تغییرات' : 'ثبت پرسنل' ?></button>
                <?php if ($editEmployee !== null): ?><a class="btn" href="admin.php?page=employees">انصراف</a><?php endif; ?>
            </form>
            </div>

            <h2>فهرست پرسنل (<?= count($employeesList) ?>)</h2>
            <?php if ($employeesList === []): ?>
                <div class="card wide"><p class="muted">هنوز پرسنلی ثبت نشده است. اولین نفر را با دکمه «+ افزودن پرسنل» ثبت کنید.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>نام</th><th>سمت</th><th>موبایل</th><th>نوع همکاری</th><th>حقوق پایه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($employeesList as $emp):
                    $eid = (int) $emp['id'];
                    $isActive = ($emp['status'] ?? 'active') === 'active';
                ?>
                    <tr>
                        <td><strong><?= e($emp['full_name']) ?></strong><?php if (!empty($emp['national_id'])): ?><br><span class="muted" dir="ltr"><?= e($emp['national_id']) ?></span><?php endif; ?></td>
                        <td><?= e($emp['position'] ?? '') !== '' ? e($emp['position']) : '<span class="muted">—</span>' ?></td>
                        <td dir="ltr" style="text-align:end"><?= e($emp['mobile'] ?? '') !== '' ? e($emp['mobile']) : '<span class="muted">—</span>' ?></td>
                        <td><?= e(hr_employment_type_label((string) ($emp['employment_type'] ?? 'full_time'))) ?></td>
                        <td><?= e(format_price($emp['base_salary'])) ?> تومان</td>
                        <td><?php if ($isActive): ?><span class="badge ok">فعال</span><?php else: ?><span class="badge off">غیرفعال</span><?php endif; ?></td>
                        <td class="actions">
                            <a class="btn small" href="admin.php?page=employee_profile&id=<?= $eid ?>">👤 پروفایل</a>
                            <a class="btn small edit" href="admin.php?page=employees&edit_id=<?= $eid ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('<?= $isActive ? 'این پرسنل غیرفعال شود؟ سوابق حقوقی او حفظ می‌شود.' : 'این پرسنل دوباره فعال شود؟' ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="<?= $isActive ? 'deactivate_employee' : 'activate_employee' ?>">
                                <input type="hidden" name="id" value="<?= $eid ?>">
                                <button type="submit" class="btn small <?= $isActive ? 'danger-btn' : 'add' ?>"><?= $isActive ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    <?php
}

function hr_render_employee_profile(array $d): void
{
    extract($d);
    $emp = $profileEmployee;
    if ($emp === null) {
        echo '<h1>پرسنل پیدا نشد</h1><p><a class="btn" href="admin.php?page=employees">بازگشت به فهرست پرسنل</a></p>';
        return;
    }
    $eid = (int) $emp['id'];
    $isActive = ($emp['status'] ?? 'active') === 'active';
    // حروف اول نام برای آواتار
    $nameParts = preg_split('/\s+/u', trim((string) $emp['full_name']));
    $initials = '';
    foreach (array_slice($nameParts, 0, 2) as $np) {
        $initials .= mb_substr($np, 0, 1);
    }
    ?>
            <p><a href="admin.php?page=employees">← بازگشت به فهرست پرسنل</a></p>
            <h1>پروفایل پرسنل</h1>

            <section class="card wide">
                <div style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
                    <div style="width:84px;height:84px;border-radius:50%;background:linear-gradient(135deg,#c9a227,#e8c66a);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;color:#07090d;flex-shrink:0"><?= e($initials) ?></div>
                    <div style="flex:1;min-width:220px">
                        <h2 style="margin:0 0 6px"><?= e($emp['full_name']) ?></h2>
                        <p class="muted" style="margin:0 0 8px"><?= e($emp['position'] ?? '') !== '' ? e($emp['position']) : '—' ?> · <?= e(hr_employment_type_label((string) ($emp['employment_type'] ?? 'full_time'))) ?></p>
                        <?php if ($isActive): ?><span class="badge ok">فعال</span><?php else: ?><span class="badge off">غیرفعال</span><?php endif; ?>
                        <span class="muted">کد پرسنلی: <strong dir="ltr"><?= $eid ?></strong></span>
                    </div>
                    <div>
                        <a class="btn small edit" href="admin.php?page=employees&edit_id=<?= $eid ?>">ویرایش مشخصات</a>
                    </div>
                </div>
            </section>

            <div class="stat-grid dash-cards">
                <div class="stat-card"><span>حقوق پایه ماهانه</span><strong><?= e(format_price($emp['base_salary'])) ?> تومان</strong></div>
                <div class="stat-card"><span>مدت همکاری</span><strong><?= e(hr_tenure((string) ($emp['hire_date'] ?? ''))) ?></strong></div>
                <div class="stat-card"><span>تعداد فیش‌های حقوقی</span><strong><?= (int) $profileTotals['count'] ?></strong></div>
                <div class="stat-card"><span>جمع خالص دریافتی</span><strong><?= e(format_price($profileTotals['net'])) ?> تومان</strong></div>
            </div>

            <section class="card wide">
                <h2 style="margin-top:0">مشخصات</h2>
                <table>
                    <tbody>
                        <tr><th style="width:180px">شماره موبایل</th><td dir="ltr" style="text-align:end"><?= e($emp['mobile'] ?? '') !== '' ? e($emp['mobile']) : '<span class="muted">—</span>' ?></td></tr>
                        <tr><th>کد ملی</th><td dir="ltr" style="text-align:end"><?= e($emp['national_id'] ?? '') !== '' ? e($emp['national_id']) : '<span class="muted">—</span>' ?></td></tr>
                        <tr><th>تاریخ استخدام</th><td><?= e($emp['hire_date'] ?? '') !== '' ? e($emp['hire_date']) : '<span class="muted">—</span>' ?></td></tr>
                        <tr><th>تعداد فرزند</th><td><?= (int) ($emp['children_count'] ?? 0) ?> نفر</td></tr>
                        <tr><th>نوع همکاری</th><td><?= e(hr_employment_type_label((string) ($emp['employment_type'] ?? 'full_time'))) ?></td></tr>
                        <?php if (!empty($emp['notes'])): ?><tr><th>یادداشت</th><td><?= e($emp['notes']) ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </section>

            <h2>سوابق حقوقی (<?= (int) $profileTotals['count'] ?>)</h2>
            <?php if ($profilePayments === []): ?>
                <div class="card wide"><p class="muted">هنوز فیش حقوقی برای این پرسنل ثبت نشده است.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>ماه</th><th>پایه</th><th>پاداش</th><th>کسورات</th><th>خالص پرداختی</th><th>تاریخ پرداخت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($profilePayments as $p): ?>
                    <tr>
                        <td dir="ltr"><strong><?= e($p['pay_month']) ?></strong></td>
                        <td><?= e(format_price($p['base_amount'])) ?></td>
                        <td><?= e(format_price($p['bonus'])) ?></td>
                        <td><?= e(format_price($p['deduction'])) ?></td>
                        <td><strong><?= e(format_price($p['net_amount'])) ?> تومان</strong></td>
                        <td><?= e($p['paid_date'] ?? '') !== '' ? e($p['paid_date']) : '<span class="muted">—</span>' ?></td>
                        <td class="actions"><a class="btn small" href="admin.php?page=payslip&slip_id=<?= (int) $p['id'] ?>" target="_blank">🖨 چاپ فیش</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
    <?php
}
