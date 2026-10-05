<?php
// modules/customers/admin.php — مدیریت مشتری‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

function customers_render_admin(array $d): void
{
    extract($d);
    ?>            <?php if ($viewCustomer !== null): ?>
                <h1>پروفایل مشتری</h1>
                <p><a href="admin.php?page=customers">← بازگشت به فهرست مشتری‌ها</a></p>
                <section class="card wide">
                    <h2><?= e($viewCustomer['full_name']) ?> <span class="badge ok"><?= e(customer_type_label((string) $viewCustomer['customer_type'])) ?></span></h2>
                    <table>
                        <tbody>
                            <tr><th>شرکت</th><td><?= e($viewCustomer['company'] ?? '—') ?></td></tr>
                            <tr><th>موبایل</th><td><span dir="ltr"><?= e($viewCustomer['mobile']) ?></span></td></tr>
                            <tr><th>شهر</th><td><?= e($viewCustomer['city'] ?? '—') ?></td></tr>
                            <tr><th>نشانی</th><td><?= nl2br(e($viewCustomer['address'] ?? '—')) ?></td></tr>
                            <tr><th>یادداشت</th><td><?= nl2br(e($viewCustomer['notes'] ?? '—')) ?></td></tr>
                            <tr><th>تاریخ ثبت</th><td><?= e($viewCustomer['created_at'] ?? '') ?></td></tr>
                        </tbody>
                    </table>
                    <p>
                        <a class="btn small edit" href="admin.php?page=customers&edit_id=<?= (int) $viewCustomer['id'] ?>">ویرایش مشتری</a>
                    </p>
                </section>
                <section class="card wide">
                    <h2>وضعیت مالی</h2>
                    <div class="stat-grid" style="margin-top:0">
                        <div class="stat-card"><span>جمع سفارش‌ها</span><strong><?= e(format_price($viewCustomerTotalOrders ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>جمع دریافتی</span><strong><?= e(format_price($viewCustomerTotalPaid ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>تراز مالی</span><strong style="color:<?= ($viewCustomerBalance ?? 0) >= 0 ? '#16a34a' : '#dc2626' ?>"><?= e(format_price($viewCustomerBalance ?? 0)) ?></strong><span class="muted" style="font-size:11px"><?= ($viewCustomerBalance ?? 0) >= 0 ? 'بستانکار' : 'بدهکار' ?></span></div>
                        <div class="stat-card"><span>سقف اعتبار</span><strong><?= e(format_price($viewCustomerCreditLimit ?? 0)) ?></strong></div>
                        <div class="stat-card"><span>اعتبار باقی‌مانده</span><strong><?= e(format_price($viewCustomerCreditRemain ?? 0)) ?></strong></div>
                    </div>
                    <h3 style="margin-top:16px">سابقه مالی (<?= count($viewCustomerPayments ?? []) ?>)</h3>
                    <?php if (empty($viewCustomerPayments)): ?>
                        <p class="muted">پرداختی ثبت نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>تاریخ</th><th>مبلغ (تومان)</th><th>روش</th><th>سفارش</th><th>یادداشت</th></tr></thead>
                        <tbody>
                        <?php foreach ($viewCustomerPayments as $vp): ?>
                            <tr>
                                <td class="muted"><?= e(mb_substr((string) ($vp['paid_at'] ?? ''), 0, 10)) ?></td>
                                <td><?= e(format_price($vp['amount'] ?? 0)) ?></td>
                                <td><?= e(payment_method_title((string) ($vp['method_key'] ?? ''))) ?></td>
                                <td><?= !empty($vp['order_no']) ? '#' . (int) $vp['order_no'] : '—' ?></td>
                                <td class="muted"><?= e($vp['note'] ?? '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </section>
                    <form method="post" class="inline" onsubmit="return confirm('این مشتری حذف شود؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_customer">
                        <input type="hidden" name="id" value="<?= (int) $viewCustomer['id'] ?>">
                        <button type="submit" class="btn small danger-btn">حذف مشتری</button>
                    </form>
                </section>
                <section class="card wide">
                    <h2>سفارش‌های این مشتری (<?= count($viewCustomerOrders) ?>)</h2>
                    <?php if ($viewCustomerOrders === []): ?>
                        <p class="muted">هنوز سفارشی برای این مشتری ثبت نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>شماره</th><th>متراژ</th><th>مبلغ (تومان)</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($viewCustomerOrders as $vo): ?>
                            <tr>
                                <td><strong>#<?= (int) $vo['order_no'] ?></strong></td>
                                <td><?= e(format_qty((float) ($vo['total_meters'] ?? 0))) ?> متر</td>
                                <td><?= e(format_price($vo['total'] ?? 0)) ?></td>
                                <td><span class="badge" style="background:<?= e(order_status_color((string) $vo['status'])) ?>22;color:<?= e(order_status_color((string) $vo['status'])) ?>"><?= e(order_status_title((string) $vo['status'])) ?></span></td>
                                <td class="muted"><?= e(mb_substr((string) ($vo['created_at'] ?? ''), 0, 10)) ?></td>
                                <td class="actions"><a class="btn small" href="admin.php?page=order_view&id=<?= (int) $vo['id'] ?>">جزئیات</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <p><a class="btn small" href="admin.php?page=order_new">+ سفارش تازه برای این مشتری</a></p>
                </section>
            <?php else: ?>
                <h1>مشتری‌ها</h1>
                <p class="muted">بیشتر مشتری‌های لاینرلایت همکار هستند؛ نوع هر مشتری (همکار/مشتری/شرکت) مشخص می‌کند قیمت همکاری برایش حساب شود یا قیمت عادی. شماره موبایل برای هر مشتری یکتاست.</p>
                <form method="get" class="card wide">
                    <input type="hidden" name="page" value="customers">
                    <label>جستجو (نام، شرکت یا موبایل)
                        <input type="text" name="q" value="<?= e($customerSearch) ?>" placeholder="مثلاً: کابینت پارس یا 0912">
                    </label>
                    <label>نوع مشتری
                        <select name="type">
                            <option value="">همه انواع</option>
                            <?php foreach (customer_types() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= $customerTypeFilter === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="submit" class="btn primary">جستجو</button>
                    <?php if ($customerSearch !== '' || $customerTypeFilter !== ''): ?><a class="btn" href="admin.php?page=customers">حذف فیلتر</a><?php endif; ?>
                </form>

                <?php if ($editCustomer === null): ?>
                <div class="crud-toolbar">
                    <button type="button" class="btn add" data-toggle-panel="customer-form-panel" aria-expanded="false">+ افزودن مشتری</button>
                </div>
                <?php endif; ?>
                <div class="crud-panel" id="customer-form-panel" <?= $editCustomer !== null ? 'data-open="1"' : 'hidden' ?>>
                <h2><?= $editCustomer !== null ? 'ویرایش مشتری: ' . e($editCustomer['full_name'] ?? '') : 'افزودن مشتری تازه' ?></h2>
                <form method="post" class="card wide">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editCustomer !== null ? 'update_customer' : 'add_customer' ?>">
                    <?php if ($editCustomer !== null): ?><input type="hidden" name="id" value="<?= (int) $editCustomer['id'] ?>"><?php endif; ?>
                    <label>نام و نام خانوادگی *
                        <input type="text" name="full_name" required value="<?= e($editCustomer['full_name'] ?? '') ?>">
                    </label>
                    <label>شرکت / فروشگاه
                        <input type="text" name="company" value="<?= e($editCustomer['company'] ?? '') ?>">
                    </label>
                    <label>موبایل *
                        <input type="text" name="mobile" required dir="ltr" value="<?= e($editCustomer['mobile'] ?? '') ?>" placeholder="09xxxxxxxxx">
                    </label>
                    <label>نوع مشتری
                        <select name="customer_type">
                            <?php foreach (customer_types() as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= ($editCustomer['customer_type'] ?? 'partner') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>شهر
                        <input type="text" name="city" value="<?= e($editCustomer['city'] ?? '') ?>">
                    </label>
                    <label>نشانی
                        <textarea name="address" rows="2"><?= e($editCustomer['address'] ?? '') ?></textarea>
                    </label>
                    <label>یادداشت
                        <textarea name="notes" rows="2"><?= e($editCustomer['notes'] ?? '') ?></textarea>
                    </label>
                    <label>سقف اعتبار (تومان)
                        <input type="number" name="credit_limit" min="0" step="1000" value="<?= e((string) ($editCustomer['credit_limit'] ?? 0)) ?>">
                    </label>
                    <button type="submit" class="btn <?= $editCustomer !== null ? 'edit' : 'add' ?>"><?= $editCustomer !== null ? 'ذخیره تغییرات' : 'ثبت مشتری' ?></button>
                    <?php if ($editCustomer !== null): ?><a class="btn" href="admin.php?page=customers">انصراف</a><?php endif; ?>
                </form>
                </div>

                <h2>فهرست مشتری‌ها (<?= count($customersList) ?>)</h2>
                <?php if ($customersList === []): ?>
                    <div class="card wide"><p class="muted">هنوز مشتری‌ای ثبت نشده است. اولین مشتری را با دکمه «+ افزودن مشتری» بسازید؛ بعداً سفارش‌ها به همین مشتری‌ها وصل می‌شوند.</p></div>
                <?php else: ?>
                <table>
                    <thead><tr><th>نام</th><th>شرکت</th><th>موبایل</th><th>نوع</th><th>شهر</th><th>عملیات</th></tr></thead>
                    <tbody>
                    <?php foreach ($customersList as $c): ?>
                        <tr>
                            <td><a href="admin.php?page=customers&view=<?= (int) $c['id'] ?>"><?= e($c['full_name']) ?></a></td>
                            <td><?= e($c['company'] ?? '—') ?></td>
                            <td><span dir="ltr"><?= e($c['mobile']) ?></span></td>
                            <td><span class="badge ok"><?= e(customer_type_label((string) $c['customer_type'])) ?></span></td>
                            <td><?= e($c['city'] ?? '—') ?></td>
                            <td class="actions">
                                <a class="btn small" href="admin.php?page=customers&view=<?= (int) $c['id'] ?>">پروفایل</a>
                                <a class="btn small edit" href="admin.php?page=customers&edit_id=<?= (int) $c['id'] ?>">ویرایش</a>
                                <form method="post" class="inline" onsubmit="return confirm('این مشتری حذف شود؟')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_customer">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn small danger-btn">حذف</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «دسته‌بندی‌ها» */
