<?php
// modules/customers/frontend.php — توابع مشتری‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

/** دریافت مشتری */
function customers_get(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** فهرست مشتریان */
function customers_list(): array
{
    try {
        return db()->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}
