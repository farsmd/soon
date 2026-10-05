<?php
// admin_notifications.php — صندوق اعلان‌های مدیریتی (نسخه ۹٫۱۰).
// اعلان‌های رویدادهای مهم سایت (سفارش تازه، پیام تماس تازه) برای مدیران.
// فقط از admin.php و بعد از احراز هویت صدا زده می‌شود (گارد CMS_ADMIN_PANEL).

declare(strict_types=1);

// دسترسی مستقیم ممنوع
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به اعلان‌ها */
function notifications_post_actions(): array
{
    return ['notif_mark_read', 'notif_mark_all_read'];
}

/** پردازش اکشن‌های POST اعلان‌ها (CSRF به‌صورت متمرکز در admin.php بررسی می‌شود) */
function notifications_handle_post(string $action): void
{
    switch ($action) {
        case 'notif_mark_read': {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id')->execute([':id' => $id]);
            }
            redirect_admin('admin.php?page=notifications');
        }
        case 'notif_mark_all_read': {
            db()->exec('UPDATE notifications SET is_read = 1 WHERE is_read = 0');
            flash('ok', 'همهٔ اعلان‌ها خوانده شدند.');
            redirect_admin('admin.php?page=notifications');
        }
    }
}

/** زمان نسبی فارسی (مثلاً «۵ دقیقه پیش») */
function notif_time_ago(string $createdAt): string
{
    $ts = strtotime($createdAt);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'لحظاتی پیش';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' دقیقه پیش';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return $h . ' ساعت پیش';
    }
    if ($diff < 86400 * 30) {
        $d = (int) floor($diff / 86400);
        return $d . ' روز پیش';
    }
    return date('Y/m/d H:i', $ts);
}

/** آیکون SVG بر اساس نوع اعلان */
function notif_type_icon(string $type): string
{
    $paths = [
        'new_order'   => '<path d="M5 2h14a1 1 0 0 1 1 1v18l-3-2-2 2-2-2-2 2-2-2-2 2-2-2-1 1V3a1 1 0 0 1 1-1z"/><path d="M9 7h6M9 11h6M9 15h4"/>',
        'new_message' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    ];
    $p = $paths[$type] ?? '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="22" height="22" aria-hidden="true">' . $p . '</svg>';
}

/** رندر صفحهٔ صندوق اعلان‌ها */
function notifications_render_page(): void
{
    $rows = [];
    try {
        $rows = db()->query('SELECT id, type, title, message, link, is_read, created_at FROM notifications ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }
    $unread = 0;
    foreach ($rows as $r) {
        if (empty($r['is_read'])) {
            $unread++;
        }
    }
    ?>
    <h1>اعلان‌ها</h1>
    <div class="card wide" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
        <button type="button" class="btn" id="enablePushBtn">فعال‌سازی اعلان روی گوشی</button>
        <script>
        (function(){
            var btn = document.getElementById('enablePushBtn');
            if (!btn || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                if (btn) btn.style.display = 'none';
                return;
            }
            function urlB64ToU8(s){ var p='='.repeat((4-s.length%4)%4); var b=(s+p).replace(/-/g,'+').replace(/_/g,'/'); var r=window.atob(b); var o=new Uint8Array(r.length); for(var i=0;i<r.length;i++) o[i]=r.charCodeAt(i); return o; }
            btn.addEventListener('click', function(){
                Notification.requestPermission().then(function(perm){
                    if (perm !== 'granted') { alert('اجازه اعلان داده نشد.'); return; }
                    navigator.serviceWorker.register('admin-push-sw.js').then(function(reg){
                        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: urlB64ToU8('<?= e((string) get_setting('vapid_public', '')) ?>') });
                    }).then(function(sub){
                        var kj = sub.toJSON();
                        return fetch('admin.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'action=save_push_subscription&csrf=<?= e(csrf_token()) ?>&endpoint='+encodeURIComponent(sub.endpoint)+'&p256dh='+encodeURIComponent(kj.keys.p256dh)+'&auth='+encodeURIComponent(kj.keys.auth); });
                    }).then(function(){ btn.textContent = 'اعلان روی گوشی فعال شد'; btn.disabled = true; })
                    .catch(function(e){ alert('خطا: ' + e.message); });
                });
            });
        })();
        </script>
        <p class="muted" style="margin:0"><?= $unread > 0 ? ('<strong>' . $unread . '</strong> اعلان خوانده‌نشده داری.') : 'همهٔ اعلان‌ها خوانده شده‌اند.' ?></p>
        <?php if ($unread > 0): ?>
            <form method="post" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="notif_mark_all_read">
                <button type="submit" class="btn small add">✓ خواندن همه</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($rows === []): ?>
        <div class="card wide" style="text-align:center;padding:40px 16px">
            <p class="muted" style="font-size:15px">اعلانی ثبت نشده است. سفارش‌ها و پیام‌های تازهٔ سایت اینجا نمایش داده می‌شوند.</p>
        </div>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($rows as $r): ?>
                <?php
                $nid = (int) $r['id'];
                $isUnread = empty($r['is_read']);
                $link = trim((string) ($r['link'] ?? ''));
                ?>
                <div class="notif-item<?= $isUnread ? ' unread' : '' ?>">
                    <span class="notif-ico"><?= notif_type_icon((string) $r['type']) ?></span>
                    <div class="notif-body">
                        <strong class="notif-title"><?= e((string) $r['title']) ?></strong>
                        <?php if (trim((string) $r['message']) !== ''): ?>
                            <span class="notif-msg"><?= e((string) $r['message']) ?></span>
                        <?php endif; ?>
                        <span class="notif-time"><?= e(notif_time_ago((string) $r['created_at'])) ?></span>
                    </div>
                    <div class="notif-actions">
                        <?php if ($link !== ''): ?>
                            <a class="btn small" href="<?= e($link) ?>">مشاهده</a>
                        <?php endif; ?>
                        <?php if ($isUnread): ?>
                            <form method="post" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="notif_mark_read">
                                <input type="hidden" name="id" value="<?= $nid ?>">
                                <button type="submit" class="btn small">خوانده شد</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <style>
    .notif-list{display:flex;flex-direction:column;gap:10px;margin-top:14px;max-width:900px}
    .notif-item{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e8ecf1;border-radius:12px;padding:12px 14px}
    .notif-item.unread{border-color:#2563eb;background:#eff6ff;box-shadow:0 2px 10px rgba(37,99,235,.08)}
    .notif-ico{display:inline-flex;align-items:center;justify-content:center;width:42px;height:42px;border-radius:12px;background:#f1f5f9;color:#475569;flex-shrink:0}
    .notif-item.unread .notif-ico{background:#dbeafe;color:#1d4ed8}
    .notif-body{min-width:0;flex:1;display:flex;flex-direction:column;gap:2px}
    .notif-title{font-size:14px}
    .notif-msg{font-size:13px;color:#64748b}
    .notif-time{font-size:11px;color:#94a3b8}
    .notif-actions{display:flex;gap:6px;flex-shrink:0}
    @media (max-width:560px){
        .notif-item{flex-wrap:wrap}
        .notif-actions{width:100%}
        .notif-actions .btn,.notif-actions form{flex:1}
        .notif-actions form .btn{width:100%}
    }
    </style>
    <?php
}
