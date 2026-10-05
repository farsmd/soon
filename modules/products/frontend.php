<?php
// modules/products/frontend.php — توابع فرانت‌اند محصولات (ماژول واقعی از ۹٫۹۹٫۱۴)
declare(strict_types=1);

/** دریافت یک محصول فعال */
function products_get(string $id): ?array
{
    try {
        $st = db()->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1");
        $st->execute([(int) $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

/** فهرست محصولات فعال */
function products_list(?int $categoryId = null): array
{
    try {
        $sql = "SELECT * FROM products WHERE is_active = 1";
        $params = [];
        if ($categoryId !== null && $categoryId > 0) {
            $sql .= " AND category_id = ?";
            $params[] = $categoryId;
        }
        $sql .= " ORDER BY sort_order ASC, name ASC";
        $st = db()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) { return []; }
}

/** قیمت نمایشی محصول */
function products_display_price(array $product, bool $isPartner = false): string
{
    $price = $isPartner && !empty($product['partner_price_per_meter'])
        ? (int) $product['partner_price_per_meter']
        : (int) ($product['price_per_meter'] ?? 0);
    if (($product['pricing_model'] ?? 'per_meter') === 'per_watt') {
        $price = (int) ($product['price_per_watt'] ?? 0);
        return number_format($price) . ' تومان/وات';
    }
    return number_format($price) . ' تومان/متر';
}
