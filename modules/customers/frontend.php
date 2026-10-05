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

// === توابع منتقل‌شده از هسته (۹٫۹۹٫۲۴) ===

function customer_types(): array
{
    return [
        'partner' => 'همکار',
        'retail'  => 'مشتری',
        'company' => 'شرکت',
    ];
}

function customer_type_label(string $key): string
{
    $types = customer_types();
    return $types[$key] ?? $types['retail'];
}

function customer_mobile_exists(string $mobile, int $excludeId = 0): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM customers WHERE mobile = :m AND id != :x');
    $stmt->execute([':m' => $mobile, ':x' => $excludeId]);
    return (int) $stmt->fetchColumn() > 0;
}
