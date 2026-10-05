<?php
// modules/update/render.php — قالب صفحه آپدیت (استخراج از admin.php در ۹٫۹۹٫۲۶)
if (!defined('CMS_ADMIN_PANEL')) { http_response_code(403); exit; }
global $pdo, $page, $updateCfg, $updateInfo;
?>
            <h1>آپدیت سیستم</h1>
            <p class="muted">از این صفحه می‌توانید ببینید نسخه تازه‌ای از سیستم در مخزن گیت‌هاب منتشر شده یا نه و با یک کلیک سایت را آپدیت کنید. آپدیت فقط فایل‌های کد را جایگزین می‌کند؛ فایل <code>database.sqlite</code>، تنظیمات داخل آن، عکس‌های آپلودی شما در <code>uploads</code> و فولدر <code>backups</code> هرگز دست نمی‌خورند و هیچ فایل محلی‌ای پاک نمی‌شود. بعد از آپدیت، مهاجرت دیتابیس در اولین بازدید به‌صورت خودکار اجرا می‌شود و پسورد و تنظیمات فعلی حفظ می‌شوند.</p>

            <section class="card wide">
                <h2>وضعیت نسخه</h2>
                <table>
                    <tbody>
                        <tr><th>نسخه نصب‌شده فعلی</th><td><span dir="ltr"><?= e(APP_VERSION) ?></span></td></tr>
                        <tr><th>مخزن گیت‌هاب</th><td><code><?= e($updateCfg['repo']) ?></code></td></tr>
                        <tr><th>شاخه</th><td><code><?= e($updateCfg['branch']) ?></code></td></tr>
                        <?php if (!empty($updateCfg['zip_url'])): ?>
                        <tr><th>آدرس مستقیم فایل آپدیت</th><td><code><?= e($updateCfg['zip_url']) ?></code></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <p>
                    <a class="btn" href="admin.php?page=update&check=1">بررسی نسخه تازه در گیت‌هاب</a>
                    <a class="btn" href="admin.php?page=update&check_all=1">بررسی کلی آپدیت (هسته + ماژول‌ها)</a>
                </p>

                <?php if (isset($_GET['check_all'])):
                    $modUpdates = modules_check_updates();
                ?>
                <section class="card" style="margin-top:16px">
                    <h2>وضعیت ماژول‌ها</h2>
                    <?php if (!$modUpdates['ok']): ?>
                        <div class="alert error"><?= e($modUpdates['error']) ?></div>
                    <?php elseif (empty($modUpdates['updates'])): ?>
                        <p><span class="status-pill ok">همه ماژول‌ها به‌روز هستند</span></p>
                    <?php else: ?>
                        <p><span class="status-pill warn"><?= count($modUpdates['updates']) ?> ماژول آپدیت دارد</span></p>
                        <table><thead><tr><th>ماژول</th><th>نسخه فعلی</th><th>نسخه جدید</th><th></th></tr></thead><tbody>
                        <?php foreach ($modUpdates['updates'] as $uk => $u): ?>
                            <tr>
                                <td><?= e($u['name']) ?></td>
                                <td dir="ltr"><?= e($u['local']) ?></td>
                                <td dir="ltr"><strong><?= e($u['remote']) ?></strong></td>
                                <td><a class="btn btn-sm" href="admin.php?page=modules">مدیریت</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody></table>
                        <p class="muted">برای نصب آپدیت هر ماژول به صفحه <a href="admin.php?page=modules">مدیریت ماژول‌ها</a> بروید.</p>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <?php if ($updateInfo !== null): ?>
                    <?php if (!empty($updateInfo['error'])): ?>
                        <div class="alert error"><?= e($updateInfo['error']) ?></div>
                    <?php elseif (!empty($updateInfo['checked'])): ?>
                        <?php if (!empty($updateInfo['update_available'])): ?>
                            <p><span class="status-pill ok">آپدیت تازه موجود است</span></p>
                            <p>آخرین نسخه در مخزن: <strong dir="ltr"><?= e((string) $updateInfo['latest']) ?></strong> — نسخه فعلی شما: <span dir="ltr"><?= e(APP_VERSION) ?></span></p>
                        <?php else: ?>
                            <p><span class="status-pill ok">سیستم به‌روز است</span></p>
                            <p class="muted">آخرین نسخه مخزن (<span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span>) با نسخه فعلی شما یکی است.</p>
                        <?php endif; ?>

                        <?php if (!empty($updateInfo['commits'])): ?>
                            <h3>آخرین تغییرات مخزن</h3>
                            <table>
                                <thead><tr><th>توضیح تغییر</th><th>تاریخ</th></tr></thead>
                                <tbody>
                                <?php foreach ($updateInfo['commits'] as $c): ?>
                                    <tr><td><?= e($c['message']) ?></td><td><?= e($c['date']) ?></td></tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="muted">برای دیدن آخرین نسخه و فهرست تغییرات، روی «بررسی نسخه تازه در گیت‌هاب» کلیک کنید.</p>
                <?php endif; ?>
            </section>

            <section class="card wide">
                <h2>آپدیت با یک کلیک</h2>
                <p class="muted">قبل از جایگزینی فایل‌ها، در صورت تیک خوردن گزینه زیر، از دیتابیس فعلی در فولدر <code>backups</code> بکاپ گرفته می‌شود. اگر گرفتن بکاپ ناموفق باشد، آپدیت اصلاً شروع نمی‌شود. اگر بکاپ نمی‌خواهید، تیک را بردارید؛ در این صورت آپدیت بدون بکاپ انجام می‌شود.</p>
                <form method="post" onsubmit="return confirm('فایل‌های کد سایت با نسخه تازه جایگزین شوند؟ دیتابیس و فایل‌های آپلودی شما دست نمی‌خورند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="perform_update">
                    <label class="check">
                        <input type="checkbox" name="backup_db" value="1" checked>
                        گرفتن بکاپ از دیتابیس قبل از آپدیت
                    </label>
                    <?php if ($updateInfo !== null && !empty($updateInfo['update_available'])): ?>
                        <button type="submit" class="btn primary">آپدیت به نسخه <span dir="ltr"><?= e((string) $updateInfo['latest']) ?></span></button>
                    <?php else: ?>
                        <button type="submit" class="btn primary" disabled>اول نسخه تازه را بررسی کنید</button>
                        <p class="muted">دکمه آپدیت فقط وقتی فعال می‌شود که نسخه تازه‌تری در مخزن پیدا شود.</p>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card wide">
                <h2>تنظیمات مخزن آپدیت</h2>
                <p class="muted">مخزن و شاخه‌ای که آپدیت‌ها از آن خوانده می‌شوند. به‌صورت پیش‌فرض مخزن رسمی همین سیستم است. «آدرس مستقیم فایل ZIP آپدیت» یک گزینه پیشرفته است و معمولاً باید خالی بماند؛ اگر پر شود، دانلود و بررسی نسخه از همان آدرس انجام می‌شود (مثلاً برای میرور یا تست).</p>
                <form method="post" class="card">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="save_update_settings">
                    <label>مخزن گیت‌هاب (owner/repo)
                        <input type="text" name="update_repo" dir="ltr" value="<?= e($updateCfg['repo']) ?>" required>
                    </label>
                    <label>شاخه
                        <input type="text" name="update_branch" dir="ltr" value="<?= e($updateCfg['branch']) ?>" required>
                    </label>
                    <label>آدرس مستقیم فایل ZIP آپدیت (پیشرفته — معمولاً خالی)
                        <input type="text" name="update_zip_url" dir="ltr" value="<?= e($updateCfg['zip_url']) ?>" placeholder="https://example.com/update.zip">
                    </label>
                    <button type="submit" class="btn primary">ذخیره تنظیمات آپدیت</button>
                </form>
            </section>
