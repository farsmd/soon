<?php
// modules/finance/frontend.php — توابع مالی (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

/** مانده حساب یک سفارش */
function finance_order_balance(int $orderId): int
{
    if (!function_exists('finance_order_due')) return 0;
    try {
        $order = function_exists('orders_get') ? orders_get($orderId) : null;
        if (!$order) return 0;
        return finance_order_due($order);
    } catch (Throwable $e) { return 0; }
}
