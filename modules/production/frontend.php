<?php
// modules/production/frontend.php — توابع تولید (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

/** وضعیت تولید یک سفارش */
function production_order_status(int $orderId): string
{
    try {
        $st = db()->prepare("SELECT status FROM orders WHERE id = ? LIMIT 1");
        $st->execute([$orderId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return (string) ($row['status'] ?? 'new');
    } catch (Throwable $e) { return 'new'; }
}
