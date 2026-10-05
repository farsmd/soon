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

// === توابع منتقل‌شده از هسته (۹٫۹۹٫۲۴) ===

function order_setting(string $key, $default = '')
{
    return get_setting($key, (string) $default);
}

function order_statuses(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM order_statuses';
    if ($onlyActive) {
        $sql .= ' WHERE is_active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

function order_status_title(string $key): string
{
    foreach (order_statuses(false) as $s) {
        if ((string) $s['status_key'] === $key) {
            return (string) $s['title'];
        }
    }
    return $key;
}

function order_status_color(string $key): string
{
    foreach (order_statuses(false) as $s) {
        if ((string) $s['status_key'] === $key) {
            return (string) ($s['color'] ?? '#6b7280');
        }
    }
    return '#6b7280';
}

function order_required_materials(array $orderLines): array
{
    $acc = [];
    foreach ($orderLines as $ln) {
        $pid = (int) ($ln['product_id'] ?? 0);
        $qty = max(1, (int) ($ln['qty'] ?? 1));
        $lengthM = round((float) ($ln['length_cm'] ?? 0), 1) / 100.0;
        $hasEndcap = !empty($ln['has_endcap']);
        foreach (product_bom_lines($pid) as $bom) {
            if (($bom['apply_condition'] ?? 'always') === 'endcap' && !$hasEndcap) {
                continue;
            }
            $mid = (int) $bom['material_id'];
            $need = $bom['basis'] === 'per_fixture' ? (float) $bom['qty'] * $qty : (float) $bom['qty'] * $lengthM * $qty;
            if (!isset($acc[$mid])) {
                $mat = get_material($mid);
                $acc[$mid] = [
                    'material_id' => $mid,
                    'name' => (string) ($mat['name'] ?? ('#' . $mid)),
                    'unit' => (string) ($mat['unit'] ?? ''),
                    'needed' => 0.0,
                    'stock' => (float) ($mat['stock_qty'] ?? 0),
                ];
            }
            $acc[$mid]['needed'] += $need;
        }
    }
    foreach ($acc as &$r) {
        $r['shortage'] = max(0.0, (float) $r['needed'] - (float) $r['stock']);
    }
    unset($r);
    return array_values($acc);
}

function order_custom_fields_html(int $productId, string $namePrefix = 'cf'): array
{
    $fields = get_order_form_fields($productId);
    if ($fields === []) {
        return ['', []];
    }
    $html = '<div class="so-custom-fields"><h4>مشخصات تکمیلی</h4>';
    $meta = [];
    foreach ($fields as $f) {
        $fid = (int) $f['id'];
        $name = $namePrefix . '[' . $fid . ']';
        $label = (string) $f['label'];
        $req = (int) $f['is_required'] === 1;
        $ph = (string) ($f['placeholder'] ?? '');
        $help = (string) ($f['help_text'] ?? '');
        $type = (string) ($f['field_type'] ?? 'text');
        $meta[$fid] = ['label' => $label, 'type' => $type];
        $html .= '<div class="so-field"><label>' . e($label) . ($req ? ' *' : '') . '</label>';
        if ($type === 'select') {
            $opts = json_decode((string) ($f['options_json'] ?? '[]'), true);
            if (!is_array($opts)) { $opts = []; }
            $html .= '<select name="' . e($name) . '"' . ($req ? ' required' : '') . '><option value="">— انتخاب کنید —</option>';
            foreach ($opts as $op) {
                $op = trim((string) $op);
                if ($op === '') { continue; }
                $html .= '<option value="' . e($op) . '">' . e($op) . '</option>';
            }
            $html .= '</select>';
        } elseif ($type === 'textarea') {
            $html .= '<textarea name="' . e($name) . '" rows="2"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . '></textarea>';
        } elseif ($type === 'number') {
            $html .= '<input type="number" name="' . e($name) . '" step="any"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . '>';
        } elseif ($type === 'checkbox') {
            $html .= '<label class="check"><input type="checkbox" name="' . e($name) . '" value="1"> ' . e($ph !== '' ? $ph : 'بله') . '</label>';
        } else {
            $html .= '<input type="text" name="' . e($name) . '"' . ($req ? ' required' : '') . ($ph !== '' ? ' placeholder="' . e($ph) . '"' : '') . ' maxlength="255">';
        }
        if ($help !== '') {
            $html .= '<p class="so-help">' . e($help) . '</p>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return [$html, $meta];
}
