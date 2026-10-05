<?php
// modules/users/frontend.php — توابع کاربران (ماژول واقعی از ۹٫۹۹٫۲۰)
declare(strict_types=1);

/** آیا کاربر جاری وارد شده؟ */
function users_is_logged_in(): bool
{
    return !empty($_SESSION['admin_user'] ?? null);
}

/** نقش کاربر جاری */
function users_current_role(): string
{
    return (string) ($_SESSION['admin_role'] ?? 'guest');
}
