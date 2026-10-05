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

// === توابع منتقل‌شده از هسته (۹٫۹۹٫۲۴) ===

function product_offered_attributes(int $productId): array
{
    $values = [];
    foreach (get_product_attribute_values($productId) as $v) {
        $values[(int) $v['attribute_id']] = $v;
    }
    $out = [];
    foreach (get_attributes(true) as $attr) {
        $aid = (int) $attr['id'];
        if (!isset($values[$aid])) {
            continue;
        }
        $v = $values[$aid];
        $type = (string) $attr['input_type'];
        if ($type === 'select') {
            $options = get_attribute_options($aid);
            $optIds = array_map(static fn ($o) => (int) $o['id'], $options);
            $defId = isset($v['option_id']) ? (int) $v['option_id'] : 0;
            if ($defId <= 0 || !in_array($defId, $optIds, true)) {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => $options, 'default_option_id' => $defId, 'num_value' => null, 'text_value' => null];
        } elseif ($type === 'number') {
            if ($v['num_value'] === null || $v['num_value'] === '') {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => [], 'default_option_id' => null, 'num_value' => (float) $v['num_value'], 'text_value' => null];
        } else {
            $tv = trim((string) ($v['text_value'] ?? ''));
            if ($tv === '') {
                continue;
            }
            $out[] = ['attribute' => $attr, 'options' => [], 'default_option_id' => null, 'num_value' => null, 'text_value' => $tv];
        }
    }
    return $out;
}

function product_base_price_per_meter(array $product, bool $isPartner): float
{
    $price = (float) ($product['price_per_meter'] ?? 0);
    if (!$isPartner) {
        return $price;
    }
    $partner = $product['partner_price_per_meter'] ?? null;
    if ($partner !== null && $partner !== '' && (float) $partner > 0) {
        return (float) $partner;
    }
    return $price * (1 - partner_discount_percent() / 100);
}

function product_unit_price(array $product, array $selectedOptionIds = [], bool $isPartner = false): float
{
    $unit = product_base_price_per_meter($product, $isPartner);
    foreach ($selectedOptionIds as $oid) {
        $opt = get_attribute_option((int) $oid);
        if ($opt !== null) {
            $unit += (float) ($opt['price_delta_per_meter'] ?? 0);
        }
    }
    return $unit;
}

function product_bom_lines(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT pm.*, m.name AS material_name, m.unit AS material_unit, m.last_price AS material_price, m.stock_qty AS material_stock, m.is_active AS material_active
         FROM product_materials pm
         JOIN materials m ON m.id = pm.material_id
         WHERE pm.product_id = :p
         ORDER BY pm.sort_order ASC, pm.id ASC'
    );
    $stmt->execute([':p' => $productId]);
    return $stmt->fetchAll();
}

function product_material_cost(int $productId): array
{
    $perMeter = 0.0;
    $perFixture = 0.0;
    $lines = [];
    foreach (product_bom_lines($productId) as $l) {
        $unitPrice = (int) $l['material_price'];
        $q = (float) $l['qty'];
        $lineCost = $q * $unitPrice;
        if ((string) $l['basis'] === 'per_fixture') {
            $perFixture += $lineCost;
        } else {
            $perMeter += $lineCost;
        }
        $lines[] = [
            'material_id' => (int) $l['material_id'],
            'name'        => (string) $l['material_name'],
            'unit'        => (string) $l['material_unit'],
            'qty'         => $q,
            'basis'       => (string) $l['basis'],
            'unit_price'  => $unitPrice,
            'line_cost'   => $lineCost,
        ];
    }
    // هزینه تولید (نسخه ۹٫۱): دستمزد/سربار به‌ازای هر متر و هر چراغ
    $prod = get_product($productId);
    $laborMeter = (float) ($prod['labor_cost_per_meter'] ?? 0);
    $laborFixture = (float) ($prod['labor_cost_per_fixture'] ?? 0);
    if ($laborMeter > 0) {
        $lines[] = [
            'material_id' => 0,
            'name'        => 'هزینه تولید (هر متر)',
            'unit'        => '',
            'qty'         => 1,
            'basis'       => 'per_meter',
            'unit_price'  => (int) round($laborMeter),
            'line_cost'   => $laborMeter,
            'is_labor'    => true,
        ];
        $perMeter += $laborMeter;
    }
    if ($laborFixture > 0) {
        $lines[] = [
            'material_id' => 0,
            'name'        => 'هزینه تولید (هر چراغ)',
            'unit'        => '',
            'qty'         => 1,
            'basis'       => 'per_fixture',
            'unit_price'  => (int) round($laborFixture),
            'line_cost'   => $laborFixture,
            'is_labor'    => true,
        ];
        $perFixture += $laborFixture;
    }
    return ['per_meter' => (int) round($perMeter), 'per_fixture' => (int) round($perFixture), 'lines' => $lines];
}

function product_required_materials(int $productId, float $lengthMeters, int $fixtures = 1): array
{
    if (!is_finite($lengthMeters) || $lengthMeters < 0) {
        $lengthMeters = 0.0;
    }
    if ($fixtures < 0) {
        $fixtures = 0;
    }
    $agg = [];
    foreach (product_bom_lines($productId) as $l) {
        $mid = (int) $l['material_id'];
        if (!isset($agg[$mid])) {
            $agg[$mid] = [
                'material_id' => $mid,
                'name'        => (string) $l['material_name'],
                'unit'        => (string) $l['material_unit'],
                'needed'      => 0.0,
                'stock'       => (float) $l['material_stock'],
            ];
        }
        $agg[$mid]['needed'] += ((string) $l['basis'] === 'per_fixture')
            ? (float) $l['qty'] * $fixtures
            : (float) $l['qty'] * $lengthMeters;
    }
    $out = [];
    foreach ($agg as $r) {
        $r['needed']   = round($r['needed'], 6);
        $r['shortage'] = round(max(0.0, $r['needed'] - $r['stock']), 6);
        $out[] = $r;
    }
    return $out;
}

function product_margin(int $productId): array
{
    $product = get_product($productId);
    $cost = product_material_cost($productId);
    $retail  = $product !== null ? product_base_price_per_meter($product, false) : 0.0;
    $partner = $product !== null ? product_base_price_per_meter($product, true) : 0.0;
    $costPerMeter = (float) $cost['per_meter'];
    $calc = static function (float $price) use ($costPerMeter): array {
        $margin = $price - $costPerMeter;
        return [$margin, $price > 0 ? ($margin / $price) * 100 : 0.0];
    };
    [$retailMargin, $retailPct] = $calc($retail);
    [$partnerMargin, $partnerPct] = $calc($partner);
    return [
        'retail_price'          => $retail,
        'partner_price'         => $partner,
        'cost_per_meter'        => $cost['per_meter'],
        'cost_per_fixture'      => $cost['per_fixture'],
        'retail_margin'         => $retailMargin,
        'retail_margin_percent' => $retailPct,
        'partner_margin'        => $partnerMargin,
        'partner_margin_percent' => $partnerPct,
    ];
}

function products_showcase_html(): string
{
    $products = get_products(true);
    if ($products === []) {
        return '';
    }
    $out = '<section class="products-showcase" id="products-showcase"><div class="container">';
    $out .= '<div class="ps-head"><h2>محصولات ما</h2><p>چراغ‌های خطی و نور رشد گیاه — برش دقیق در ابعاد دلخواه شما</p></div>';
    $out .= '<div class="ps-grid">';
    foreach ($products as $p) {
        $name = (string) ($p['name'] ?? '');
        $img = uploaded_image_url($p['image'] ?? '');
        $price = product_base_price_per_meter($p, false);
        $partnerPrice = product_base_price_per_meter($p, true);
        $out .= '<a class="ps-card rv" href="' . e(pretty_url('products.php#' . (int) $p['id'])) . '">';
        if ($img !== '') {
            $out .= '<div class="ps-img"><img loading="lazy" src="' . e($img) . '" alt="' . e($name) . '"></div>';
        }
        $out .= '<div class="ps-body"><h3>' . e($name) . '</h3>';
        if (!empty($p['category_title'])) {
            $out .= '<span class="ps-cat">' . e((string) $p['category_title']) . '</span>';
        }
        $pricingModel = (string) ($p['pricing_model'] ?? 'per_meter');
        if ($pricingModel === 'per_watt') {
            $basePrice = (int) ($p['base_price'] ?? 0);
            $ppw = (int) ($p['price_per_watt'] ?? 0);
            if ($basePrice > 0) {
                $out .= '<div class="ps-price">از ' . e(format_price($basePrice)) . ' <small>تومان</small></div>';
            }
            if ($ppw > 0) {
                $out .= '<div class="ps-partner">هر وات ' . e(format_price($ppw)) . ' تومان + قیمت قاب</div>';
            }
        } else {
            if ($price > 0) {
                $out .= '<div class="ps-price">' . e(format_price($price)) . ' <small>/ متر</small></div>';
            }
            if ($partnerPrice > 0 && $partnerPrice != $price) {
                $out .= '<div class="ps-partner">تخفیف همکار: ' . e(format_price($partnerPrice)) . '</div>';
            }
        }
        $out .= '<span class="btn btn-gold ps-cta">مشاهده و ثبت سفارش ←</span>';
        $out .= '</div></a>';
    }
    $out .= '</div>';
    $out .= '<div class="ps-more"><a class="btn btn-gold" href="' . e(pretty_url('products.php')) . '">مشاهده همه محصولات</a></div>';
    $out .= '</div></section>';
    return $out;
}

function product_gallery_html(int $productId): string
{
    $images = get_product_images($productId);
    if ($images === []) {
        return '';
    }
    $html = '<div class="product-gallery"><h3>گالری تصاویر</h3><div class="pg-public-grid">';
    foreach ($images as $img) {
        $url = uploaded_image_url($img['image'] ?? '');
        if ($url === '') {
            continue;
        }
        $cap = (string) ($img['caption'] ?? '');
        $html .= '<figure class="pg-public-item"><img loading="lazy" src="' . e($url) . '" alt="' . e($cap) . '">';
        if ($cap !== '') {
            $html .= '<figcaption>' . e($cap) . '</figcaption>';
        }
        $html .= '</figure>';
    }
    $html .= '</div></div>';
    return $html;
}

function product_order_form_config(array $product): array
{
    $defaults = ['show_length' => true, 'show_qty' => true, 'show_wire' => true, 'show_endcap' => true, 'show_options' => true];
    $raw = (string) ($product['order_form_config'] ?? '');
    if ($raw === '') {
        return $defaults;
    }
    $cfg = json_decode($raw, true);
    if (!is_array($cfg)) {
        return $defaults;
    }
    foreach ($defaults as $k => $v) {
        if (!array_key_exists($k, $cfg)) {
            $cfg[$k] = $v;
        } else {
            $cfg[$k] = (bool) $cfg[$k];
        }
    }
    return $cfg;
}
