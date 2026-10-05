<?php
// modules/categories/frontend.php — توابع فرانت‌اند دسته‌بندی‌ها (ماژول واقعی از ۹٫۹۹٫۱۵)
declare(strict_types=1);

/** دریافت یک دسته‌بندی */
function categories_get(int $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM product_categories WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** فهرست دسته‌بندی‌های فعال */
function categories_list(?int $parentId = null): array
{
    try {
        $sql = "SELECT * FROM product_categories WHERE is_active = 1";
        $params = [];
        if ($parentId !== null) {
            $sql .= " AND " . ($parentId === 0 ? "parent_id IS NULL OR parent_id = 0" : "parent_id = ?");
            if ($parentId !== 0) $params[] = $parentId;
        }
        $sql .= " ORDER BY sort_order ASC, title ASC";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}
