<?php
// modules/orders/frontend.php — توابع فرانت‌اند سفارش‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

/** دریافت سفارش */
function orders_get(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** پیگیری سفارش با شماره و موبایل */
function orders_track(string $orderNo, string $mobile): ?array
{
    try {
        $st = db()->prepare("SELECT o.* FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE o.id = ? AND c.phone = ? LIMIT 1");
        $st->execute([$orderNo, $mobile]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}
