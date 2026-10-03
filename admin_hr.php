<?php
// admin_hr.php — بخش «منابع انسانی» پنل مدیریت: پرسنل، حقوق و دستمزد.
// این فایل فقط از admin.php و بعد از احراز هویت صدا زده می‌شود؛ اکشن‌ها و صفحه‌های
// پرسنل و فیش حقوقی اینجاست تا admin.php کوچک بماند (سقف حجم آپدیت گیت‌هاب).

declare(strict_types=1);

// دسترسی مستقیم ممنوع: این فایل به‌تنهایی هیچ خروجی و هیچ سطح مدیریتی ندارد.
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به منابع انسانی */
function hr_post_actions(): array
{
    return ['add_employee', 'update_employee', 'deactivate_employee', 'activate_employee',
        'add_salary_payment', 'delete_salary_payment'];
}

/** برچسب فارسی نوع همکاری */
function hr_employment_type_label(string $type): string
{
    return [
        'full_time' => 'تمام‌وقت',
        'part_time' => 'پاره‌وقت',
        'contract'  => 'پروژه‌ای',
        'intern'    => 'کارآموز',
    ][$type] ?? $type;
}

/** برچسب فارسی وضعیت پرسنل */
function hr_status_label(string $status): string
{
    return $status === 'active' ? 'فعال' : 'غیرفعال';
}

/**
 * پردازش اکشن‌های POST منابع انسانی — موفق‌ها با redirect_admin تمام می‌شوند
 * و خطاها با استثنا به catch اصلی admin.php برمی‌گردند.
 */
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
            $data = [
                ':name'  => mb_substr($fullName, 0, 120),
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
                $pdo->prepare('UPDATE employees SET full_name = :name, position = :pos, mobile = :mob, national_id = :nid, hire_date = :hire, base_salary = :sal, employment_type = :etype, notes = :notes, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute($data);
                flash('ok', 'مشخصات پرسنل به‌روزرسانی شد.');
                redirect_admin('admin.php?page=employees&edit_id=' . $eid);
            }
            $pdo->prepare("INSERT INTO employees (full_name, position, mobile, national_id, hire_date, base_salary, employment_type, status, notes) VALUES (:name, :pos, :mob, :nid, :hire, :sal, :etype, 'active', :notes)")->execute($data);
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
            $net = $base + $bonus - $deduction;
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

/** بارگذاری داده‌های صفحات منابع انسانی */
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
    return $data;
}

/** رندر صفحه «پرسنل» */
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

/** رندر صفحه «حقوق و دستمزد» */
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
