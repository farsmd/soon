<?php
// admin_production.php — بخش «تولید» پنل مدیریت (فاز ۴ / نسخه ۸٫۳).
// برگه تولید از سفارش، لیست برش بهینه (اول از انبار پرتی، بعد شاخه/رول تازه؛
// پرت برگشتی ≥ حد تنظیم‌شده برمی‌گردد به انبار پرتی)، مصرف مواد هنگام «شروع تولید»
// با برگشت دقیق هنگام «لغو تولید»، مراحل کارگاه قابل‌ویرایش از پنل و برگه چاپی کارگاه.
// فقط از admin.php و بعد از احراز هویت صدا زده می‌شود (گارد CMS_ADMIN_PANEL).

declare(strict_types=1);

// دسترسی مستقیم ممنوع
if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

/** فهرست اکشن‌های POST مربوط به تولید */
function production_post_actions(): array
{
    return [
        'create_production', 'production_start', 'production_advance', 'production_back',
        'production_finish', 'production_cancel', 'production_save_meta',
        'add_pstage', 'update_pstage', 'delete_pstage', 'move_pstage', 'toggle_pstage',
        'save_qc_items',
    ];
}

/** مراحل تولید به ترتیب (پیش‌فرض فقط فعال‌ها) */
function production_stages(bool $onlyActive = true): array
{
    $sql = 'SELECT * FROM production_stages' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, id ASC';
    return db()->query($sql)->fetchAll();
}

/** عنوان نمایشی یک مرحله (با fallback به خود کلید) */
function production_stage_title(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (production_stages(false) as $s) {
            $map[(string) $s['stage_key']] = (string) $s['title'];
        }
    }
    return $map[$key] ?? $key;
}

/** رنگ یک مرحله (با fallback خاکستری) */
function production_stage_color(string $key): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (production_stages(false) as $s) {
            $map[(string) $s['stage_key']] = (string) $s['color'];
        }
    }
    return $map[$key] ?? '#6b7280';
}

/** برچسب وضعیت کلی برگه تولید */
function production_state_label(string $state): string
{
    return ['open' => 'در جریان', 'finished' => 'تمام‌شده', 'cancelled' => 'لغوشده'][$state] ?? $state;
}

/** آیتم‌های برگه تست و کنترل کیفیت (۸٫۵٫۰) — تنظیم qc_checklist، کاملاً از پنل قابل‌ویرایش است */
function qc_checklist_items(): array
{
    $raw = (string) get_setting('qc_checklist', '');
    $items = $raw !== '' ? json_decode($raw, true) : null;
    if (is_array($items)) {
        $items = array_values(array_filter(array_map('trim', array_map('strval', $items)), static fn ($s) => $s !== ''));
        if ($items !== []) {
            return $items;
        }
    }
    // مقادیر اولیه — پس از اولین ذخیره در پنل دیگر از تنظیمات خوانده می‌شود
    return [
        'روشنایی کامل همه ردیف‌های LED بدون بخش تاریک',
        'یکنواختی نور در کل طول چراغ',
        'تطابق رنگ نور با سفارش (آفتابی/نچرال/مهتابی)',
        'بررسی لحیم‌کاری و اتصال سیم‌ها',
        'تست توان و دمای کارکرد ذره‌ای (burn-in) مطابق تنظیمات',
        'تمیزی سطح پروفیل و لنز/دیفیوزر',
        'بررسی درپوش‌ها و اتصالات (در صورت وجود)',
        'تطابق تعداد و طول چراغ‌ها با برگه تولید',
    ];
}

/** یک برگه تولید همراه مشخصات سفارش و مشتری */
function production_get(int $id): ?array
{
    $stmt = db()->prepare('SELECT p.*, o.order_no, o.customer_id, o.status AS order_status, c.full_name AS customer_name, c.mobile AS customer_mobile
        FROM production_orders p
        JOIN orders o ON o.id = p.order_id
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE p.id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

/** برگه تولید باز (در جریان) یک سفارش، اگر هست */
function production_open_for_order(int $orderId): ?array
{
    $stmt = db()->prepare("SELECT * FROM production_orders WHERE order_id = :o AND state = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([':o' => $orderId]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

/** آخرین برگه تولید یک سفارش با هر وضعیتی */
function production_latest_for_order(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM production_orders WHERE order_id = :o ORDER BY id DESC LIMIT 1');
    $stmt->execute([':o' => $orderId]);
    $r = $stmt->fetch();
    return $r === false ? null : $r;
}

/** تعداد برگه‌های تولید در جریان (برای منو و داشبورد) */
function production_active_count(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM production_orders WHERE state = 'open'")->fetchColumn();
}

/** ثبت یک گذر مرحله در تاریخچه برگه تولید */
function production_history_add(int $productionId, ?string $from, string $to, ?string $responsible, ?string $note): void
{
    db()->prepare('INSERT INTO production_stage_history (production_id, from_stage, to_stage, responsible, note) VALUES (:p, :f, :t, :r, :n)')
        ->execute([':p' => $productionId, ':f' => $from, ':t' => $to, ':r' => $responsible, ':n' => $note]);
}

/**
 * همگام‌سازی وضعیت سفارش با تولید — فقط وقتی آن وضعیت در فهرست وضعیت‌های سفارش
 * تعریف شده باشد و فقط همراه ثبت در تاریخچه سفارش (بدون هیچ عدد/کلید تحمیلی).
 */
function production_sync_order_status(int $orderId, string $toKey, string $note): void
{
    $order = order_get($orderId);
    if ($order === null || (string) $order['status'] === $toKey) {
        return;
    }
    $chk = db()->prepare('SELECT COUNT(*) FROM order_statuses WHERE status_key = :k AND is_active = 1');
    $chk->execute([':k' => $toKey]);
    if ((int) $chk->fetchColumn() === 0) {
        return;
    }
    db()->prepare('UPDATE orders SET status = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([':s' => $toKey, ':id' => $orderId]);
    db()->prepare('INSERT INTO order_status_history (order_id, from_status, to_status, note) VALUES (:o, :f, :t, :n)')
        ->execute([':o' => $orderId, ':f' => (string) $order['status'], ':t' => $toKey, ':n' => $note]);
}

// ---------- موتور برش و نیاز مواد ----------

/**
 * قطعه‌های برش هر ماده برش‌خور از روی ردیف‌های سفارش و فرمول ساخت:
 * برای هر چراغ، یک قطعه به طول (طول چراغ × ضریب مصرف متری ماده).
 * خروجی: [material_id => ['material' => row, 'unit_cm' => float, 'pieces' => [float, ...]]]
 */
function production_cut_pieces(array $items): array
{
    $out = [];
    foreach ($items as $it) {
        $pid = (int) ($it['product_id'] ?? 0);
        $qty = max(1, (int) ($it['qty'] ?? 1));
        $lenCm = round((float) ($it['length_cm'] ?? 0), 1);
        $hasEndcap = (int) ($it['has_endcap'] ?? 0) === 1;
        if ($pid <= 0 || $lenCm <= 0) {
            continue;
        }
        foreach (product_bom_lines($pid) as $bom) {
            if (($bom['basis'] ?? '') !== 'per_meter') {
                continue;
            }
            if (($bom['apply_condition'] ?? 'always') === 'endcap' && !$hasEndcap) {
                continue;
            }
            $mat = get_material((int) $bom['material_id']);
            if ($mat === null) {
                continue;
            }
            $unitCm = (float) ($mat['cut_unit_cm'] ?? 0);
            if ($unitCm <= 0) {
                continue;
            }
            $pieceLen = round($lenCm * (float) $bom['qty'], 1);
            if ($pieceLen <= 0) {
                continue;
            }
            $mid = (int) $mat['id'];
            if (!isset($out[$mid])) {
                $out[$mid] = ['material' => $mat, 'unit_cm' => $unitCm, 'pieces' => []];
            }
            for ($i = 0; $i < $qty; $i++) {
                $out[$mid]['pieces'][] = $pieceLen;
            }
        }
    }
    return $out;
}

/**
 * برنامه برش یک ماده: قطعه‌ها (بزرگ به کوچک) اول در کوچک‌ترین پرتِ جاگیرِ انبار پرتی
 * چیده می‌شوند و بعد در واحدهای تازه (شاخه/رول). باقی‌مانده هر منبع اگر به حد پرت
 * برگشتی برسد «پرت برگشتی» است، وگرنه ضایعات حساب می‌شود.
 * $remnants: ردیف‌های material_remnants همین ماده (هر ردیف qty تکه هم‌اندازه).
 */
function production_plan_cutting(array $pieces, array $remnants, float $unitCm, float $minRemnantCm): array
{
    $pieces = array_map(static fn ($p): float => round((float) $p, 1), $pieces);
    rsort($pieces, SORT_NUMERIC);

    $bins = []; // هر bin: یک تکه پرت موجود یا یک واحد تازه، با قطعه‌های چیده‌شده در آن
    foreach ($remnants as $r) {
        $q = max(1, (int) ($r['qty'] ?? 1));
        for ($i = 0; $i < $q; $i++) {
            $bins[] = ['kind' => 'remnant', 'remnant_id' => (int) $r['id'], 'capacity' => round((float) $r['length_cm'], 1), 'pieces' => []];
        }
    }
    // پرت‌ها کوچک به بزرگ تا اول ریزترین پرتِ قابل‌استفاده مصرف شود؛ واحدهای تازه آخر صف‌اند
    usort($bins, static fn (array $a, array $b): int => $a['capacity'] <=> $b['capacity']);

    $unplaced = [];
    foreach ($pieces as $p) {
        $placed = false;
        foreach ($bins as $bi => $bin) {
            $used = array_sum($bin['pieces']);
            if ($bin['capacity'] - $used >= $p - 1e-9) {
                $bins[$bi]['pieces'][] = $p;
                $placed = true;
                break;
            }
        }
        if (!$placed) {
            if ($unitCm > 0 && $p <= $unitCm + 1e-9) {
                $bins[] = ['kind' => 'new', 'remnant_id' => null, 'capacity' => $unitCm, 'pieces' => [$p]];
            } else {
                $unplaced[] = $p; // بلندتر از هر پرت و از واحد تازه — با این انبار قابل برش نیست
            }
        }
    }

    $newUnits = 0;
    $waste = 0.0;
    $leftoverTotal = 0.0;
    foreach ($bins as &$bin) {
        $rest = round($bin['capacity'] - array_sum($bin['pieces']), 1);
        if ($bin['kind'] === 'new') {
            $newUnits++;
        }
        if ($rest > 0 && $rest >= $minRemnantCm) {
            $bin['leftover'] = $rest;
            $bin['waste'] = 0.0;
            $leftoverTotal += $rest;
        } else {
            $bin['leftover'] = 0.0;
            $bin['waste'] = max(0.0, $rest);
            $waste += max(0.0, $rest);
        }
    }
    unset($bin);

    return [
        // فقط منابعی که واقعاً قطعه خورده‌اند «مصرف» محسوب می‌شوند
        'bins' => array_values(array_filter($bins, static fn (array $b): bool => $b['pieces'] !== [])),
        'new_units' => $newUnits,
        'new_meters' => $newUnits * $unitCm / 100.0,
        'pieces_count' => count($pieces) - count($unplaced),
        'waste_cm' => round($waste, 1),
        'leftover_cm' => round($leftoverTotal, 1),
        'unplaced' => $unplaced,
    ];
}

/**
 * نیاز کامل مواد یک برگه تولید: برای مواد برش‌خور برنامه برش زنده (پرت + موجودی فعلی)
 * و برای بقیه مواد، نیاز فرمول ساخت؛ کمبود هر ماده هم حساب می‌شود.
 */
function production_requirements(array $items): array
{
    $lines = [];
    foreach ($items as $it) {
        $lines[] = [
            'product_id' => (int) ($it['product_id'] ?? 0),
            'length_cm' => (float) ($it['length_cm'] ?? 0),
            'qty' => (int) ($it['qty'] ?? 1),
            'has_endcap' => (int) ($it['has_endcap'] ?? 0) === 1,
        ];
    }
    $required = order_required_materials($lines);
    $cutPacks = production_cut_pieces($items);
    $minCm = (float) get_setting('remnant_min_cm', '20');

    $materials = [];
    foreach ($required as $r) {
        $mid = (int) $r['material_id'];
        $row = [
            'material_id' => $mid,
            'name' => (string) $r['name'],
            'unit' => (string) $r['unit'],
            'stock' => (float) $r['stock'],
            'cuttable' => false,
            'unit_cm' => 0.0,
            'needed_other' => (float) $r['needed'],
            'plan' => null,
            'shortage' => max(0.0, (float) $r['needed'] - (float) $r['stock']),
        ];
        if (isset($cutPacks[$mid])) {
            $stmt = db()->prepare('SELECT * FROM material_remnants WHERE material_id = :m ORDER BY length_cm ASC, id ASC');
            $stmt->execute([':m' => $mid]);
            $plan = production_plan_cutting($cutPacks[$mid]['pieces'], $stmt->fetchAll(), (float) $cutPacks[$mid]['unit_cm'], $minCm);
            $row['cuttable'] = true;
            $row['unit_cm'] = (float) $cutPacks[$mid]['unit_cm'];
            // نام واحد تازه برای لیست برش (شاخه، رول، بسته…) — ۸٫۵٫۰
            $row['cut_unit_label'] = trim((string) ($cutPacks[$mid]['material']['cut_unit_label'] ?? '')) ?: 'واحد';
            $row['needed_other'] = 0.0;
            $row['plan'] = $plan;
            $row['shortage'] = max(0.0, (float) $plan['new_meters'] - (float) $r['stock']);
        }
        $materials[] = $row;
    }
    return ['materials' => $materials];
}

// ---------- اجرای شروع تولید و برگشت لغو ----------

/**
 * اجرای واقعی شروع تولید بر اساس برنامه محاسبه‌شده:
 * اول همه‌چیز اعتبارسنجی می‌شود (هیچ تغییر نیمه‌کاره‌ای رخ نمی‌دهد)، بعد مصرف‌ها اعمال
 * و تک‌تک در production_consumptions ثبت می‌شوند تا لغو، دقیقاً همان را برگرداند.
 */
function production_apply_start(array $prod, array $req): void
{
    global $pdo;
    $pid = (int) $prod['id'];
    $pno = (int) $prod['production_no'];

    // اعتبارسنجی کامل پیش از هر تغییری
    $errors = [];
    foreach ($req['materials'] as $m) {
        if (!empty($m['cuttable']) && !empty($m['plan']['unplaced'])) {
            foreach ((array) $m['plan']['unplaced'] as $p) {
                $errors[] = 'قطعه ' . format_qty((float) $p) . ' سانتی‌متری از «' . $m['name'] . '» از طول واحد تازه (' . format_qty((float) $m['unit_cm']) . ' سانت) و از همه پرت‌های موجود بلندتر است؛ اول یک واحد بلندتر به انبار اضافه کنید.';
            }
        }
        if ((float) ($m['shortage'] ?? 0) > 1e-9) {
            $errors[] = 'موجودی «' . $m['name'] . '» کافی نیست؛ کمبود: ' . format_qty((float) $m['shortage']) . ' ' . $m['unit'] . ' — اول از صفحه «مواد اولیه» خرید ثبت کنید.';
        }
    }
    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }

    $record = static function (int $mid, string $kind, float $qty, ?int $movementId, ?int $remnantId, ?array $detail) use ($pdo, $pid): void {
        $pdo->prepare('INSERT INTO production_consumptions (production_id, material_id, kind, qty, movement_id, remnant_id, detail) VALUES (:p, :m, :k, :q, :mv, :r, :d)')
            ->execute([
                ':p' => $pid, ':m' => $mid, ':k' => $kind, ':q' => $qty,
                ':mv' => $movementId, ':r' => $remnantId,
                ':d' => $detail !== null ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
            ]);
    };

    foreach ($req['materials'] as $m) {
        $mid = (int) $m['material_id'];
        if (!empty($m['cuttable'])) {
            $plan = (array) $m['plan'];
            // خروج واحدهای تازه از موجودی اصلی (به متر)
            if ((int) $plan['new_units'] > 0) {
                $qtyM = round((float) $plan['new_meters'], 6);
                $r = apply_stock_movement($pdo, $mid, 'out', $qtyM, null, 'مصرف تولید — برگه تولید ' . $pno, 'production', $pid);
                if (empty($r['ok'])) {
                    throw new RuntimeException((string) ($r['error'] ?? 'خطا در کسر موجودی.'));
                }
                $record($mid, 'stock_out', $qtyM, (int) $r['movement_id'], null, ['new_units' => (int) $plan['new_units'], 'unit_cm' => (float) $m['unit_cm']]);
            }
            // مصرف تکه‌های پرت و ثبت پرت‌های برگشتی
            foreach ((array) $plan['bins'] as $bin) {
                if ($bin['kind'] === 'remnant' && !empty($bin['remnant_id'])) {
                    $sel = $pdo->prepare('SELECT * FROM material_remnants WHERE id = :id');
                    $sel->execute([':id' => (int) $bin['remnant_id']]);
                    $row = $sel->fetch();
                    if ($row !== false) {
                        if ((int) $row['qty'] > 1) {
                            $pdo->prepare('UPDATE material_remnants SET qty = qty - 1 WHERE id = :id')->execute([':id' => (int) $bin['remnant_id']]);
                        } else {
                            $pdo->prepare('DELETE FROM material_remnants WHERE id = :id')->execute([':id' => (int) $bin['remnant_id']]);
                        }
                        $record($mid, 'remnant_use', (float) $bin['capacity'], null, (int) $bin['remnant_id'], ['len' => (float) $bin['capacity'], 'pieces' => array_values((array) $bin['pieces'])]);
                    }
                }
                if (($bin['leftover'] ?? 0) > 0) {
                    $pdo->prepare('INSERT INTO material_remnants (material_id, length_cm, qty, source, note) VALUES (:m, :l, 1, :s, :n)')
                        ->execute([':m' => $mid, ':l' => (float) $bin['leftover'], ':s' => 'production', ':n' => 'باقی‌مانده برش — برگه تولید ' . $pno]);
                    $record($mid, 'remnant_new', (float) $bin['leftover'], null, (int) $pdo->lastInsertId(), ['from' => (string) $bin['kind']]);
                }
            }
        } else {
            $need = round((float) ($m['needed_other'] ?? 0), 6);
            if ($need > 1e-9) {
                $r = apply_stock_movement($pdo, $mid, 'out', $need, null, 'مصرف تولید — برگه تولید ' . $pno, 'production', $pid);
                if (empty($r['ok'])) {
                    throw new RuntimeException((string) ($r['error'] ?? 'خطا در کسر موجودی.'));
                }
                $record($mid, 'stock_out', $need, (int) $r['movement_id'], null, null);
            }
        }
    }

    // اسنپ‌شات برنامه اجراشده + زمان شروع + رفتن خودکار به مرحله بعد (معمولاً «برش»)
    $stages = production_stages(true);
    $keys = array_map(static fn (array $s): string => (string) $s['stage_key'], $stages);
    $idx = array_search((string) $prod['stage_key'], $keys, true);
    $nextKey = ($idx !== false && isset($keys[$idx + 1])) ? $keys[$idx + 1] : (string) $prod['stage_key'];
    $pdo->prepare('UPDATE production_orders SET started_at = :t, plan_json = :j, stage_key = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute([
            ':t' => date('Y-m-d H:i:s'),
            ':j' => json_encode($req['materials'], JSON_UNESCAPED_UNICODE),
            ':s' => $nextKey,
            ':id' => $pid,
        ]);
    production_history_add($pid, (string) $prod['stage_key'], $nextKey, $prod['responsible'] !== null && $prod['responsible'] !== '' ? (string) $prod['responsible'] : null, 'شروع تولید؛ مواد از انبار کسر و پرت‌های تازه در انبار پرتی ثبت شدند.');
}

/** برگشت دقیق همه مصرف‌های ثبت‌شده یک برگه (هنگام لغو تولید) */
function production_apply_reversal(array $prod): void
{
    global $pdo;
    $pid = (int) $prod['id'];
    $pno = (int) $prod['production_no'];
    $stmt = $pdo->prepare('SELECT * FROM production_consumptions WHERE production_id = :p AND reversed = 0 ORDER BY id DESC');
    $stmt->execute([':p' => $pid]);
    foreach ($stmt->fetchAll() as $c) {
        $mid = (int) $c['material_id'];
        if ($c['kind'] === 'stock_out') {
            $r = apply_stock_movement($pdo, $mid, 'in', (float) $c['qty'], null, 'برگشت مصرف — لغو برگه تولید ' . $pno, 'production', $pid);
            if (empty($r['ok'])) {
                throw new RuntimeException((string) ($r['error'] ?? 'خطا در برگشت موجودی.'));
            }
        } elseif ($c['kind'] === 'remnant_new') {
            // پرتی که همین تولید ساخته بود؛ اگر هنوز هست حذف می‌شود (اگر مصرف شده باشد کاری نمی‌کنیم)
            if (!empty($c['remnant_id'])) {
                $pdo->prepare('DELETE FROM material_remnants WHERE id = :id')->execute([':id' => (int) $c['remnant_id']]);
            }
        } elseif ($c['kind'] === 'remnant_use') {
            // تکه پرت مصرف‌شده برمی‌گردد: اگر ردیفش مانده qty زیاد می‌شود، وگرنه ردیف تازه با همان طول
            $detail = json_decode((string) ($c['detail'] ?? ''), true);
            $len = (float) (is_array($detail) && isset($detail['len']) ? $detail['len'] : $c['qty']);
            $rid = (int) ($c['remnant_id'] ?? 0);
            $exists = false;
            if ($rid > 0) {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM material_remnants WHERE id = :id');
                $chk->execute([':id' => $rid]);
                $exists = (int) $chk->fetchColumn() > 0;
            }
            if ($exists) {
                $pdo->prepare('UPDATE material_remnants SET qty = qty + 1 WHERE id = :id')->execute([':id' => $rid]);
            } else {
                $pdo->prepare('INSERT INTO material_remnants (material_id, length_cm, qty, source, note) VALUES (:m, :l, 1, :s, :n)')
                    ->execute([':m' => $mid, ':l' => $len, ':s' => 'production', ':n' => 'برگشت از لغو — برگه تولید ' . $pno]);
            }
        }
        $pdo->prepare('UPDATE production_consumptions SET reversed = 1 WHERE id = :id')->execute([':id' => (int) $c['id']]);
    }
}

/** اجرای اکشن‌های POST تولید؛ خروجی: فلش + ریدایرکت (مثل بقیه ماژول‌ها) */
function production_handle_post(string $action): void
{
    global $pdo;

    if ($action === 'create_production') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $order = order_get($orderId);
        if ($order === null) {
            throw new RuntimeException('سفارش پیدا نشد.');
        }
        if ((string) $order['status'] === 'cancelled') {
            throw new RuntimeException('سفارش لغوشده به تولید فرستاده نمی‌شود.');
        }
        $open = production_open_for_order($orderId);
        if ($open !== null) {
            flash('ok', 'برای این سفارش برگه تولید باز وجود دارد.');
            redirect_admin('admin.php?page=production_view&id=' . (int) $open['id']);
        }
        $no = (int) get_setting('next_production_no', '1');
        if ($no < 1) {
            $no = 1;
        }
        $stages = production_stages(true);
        if ($stages === []) {
            throw new RuntimeException('هیچ مرحله تولید فعالی تعریف نشده است؛ اول از صفحه «مراحل تولید» یک مرحله فعال بسازید.');
        }
        $pdo->prepare("INSERT INTO production_orders (production_no, order_id, stage_key, state) VALUES (:n, :o, :s, 'open')")
            ->execute([':n' => $no, ':o' => $orderId, ':s' => (string) $stages[0]['stage_key']]);
        $pid = (int) $pdo->lastInsertId();
        set_setting('next_production_no', (string) ($no + 1));
        production_history_add($pid, null, (string) $stages[0]['stage_key'], null, 'ایجاد برگه تولید از سفارش #' . (int) $order['order_no']);
        production_sync_order_status($orderId, 'in_production', 'ارسال به تولید — برگه تولید ' . $no);
        log_admin_event($action, 'ساخت برگه تولید ' . $no . ' برای سفارش #' . (int) $order['order_no'], true, 'production');
        flash('ok', 'برگه تولید ' . $no . ' ساخته شد. برای کسر مواد از انبار، «شروع تولید» را بزنید.');
        redirect_admin('admin.php?page=production_view&id=' . $pid);
    }

    if ($action === 'production_start') {
        $prod = production_get((int) ($_POST['id'] ?? 0));
        if ($prod === null) {
            throw new RuntimeException('برگه تولید پیدا نشد.');
        }
        if ((string) $prod['state'] !== 'open') {
            throw new RuntimeException('این برگه تولید بسته شده است.');
        }
        if (!empty($prod['started_at'])) {
            throw new RuntimeException('تولید این برگه قبلاً شروع شده است.');
        }
        $req = production_requirements(order_items((int) $prod['order_id']));
        production_apply_start($prod, $req);
        log_admin_event($action, 'شروع تولید برگه ' . (int) $prod['production_no'] . ' و کسر مواد', true, 'production');
        flash('ok', 'تولید شروع شد؛ مواد از انبار کسر شدند و پرت‌های تازه در انبار پرتی ثبت شدند.');
        redirect_admin('admin.php?page=production_view&id=' . (int) $prod['id']);
    }

    if ($action === 'production_advance' || $action === 'production_back') {
        $prod = production_get((int) ($_POST['id'] ?? 0));
        if ($prod === null) {
            throw new RuntimeException('برگه تولید پیدا نشد.');
        }
        if ((string) $prod['state'] !== 'open') {
            throw new RuntimeException('این برگه تولید بسته شده است.');
        }
        $keys = array_map(static fn (array $s): string => (string) $s['stage_key'], production_stages(true));
        $idx = array_search((string) $prod['stage_key'], $keys, true);
        if ($idx === false) {
            $idx = -1; // مرحله فعلی غیرفعال/حذف شده؛ از اول صف حساب می‌کنیم
        }
        $target = $action === 'production_advance' ? ($keys[$idx + 1] ?? null) : ($keys[$idx - 1] ?? null);
        if ($target === null) {
            throw new RuntimeException($action === 'production_advance'
                ? 'این آخرین مرحله است؛ برای اتمام کار از «پایان تولید» استفاده کنید.'
                : 'این اولین مرحله است.');
        }
        $pdo->prepare('UPDATE production_orders SET stage_key = :s, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':s' => $target, ':id' => (int) $prod['id']]);
        production_history_add((int) $prod['id'], (string) $prod['stage_key'], $target,
            $prod['responsible'] !== null && $prod['responsible'] !== '' ? (string) $prod['responsible'] : null, null);
        flash('ok', 'مرحله برگه تولید به «' . production_stage_title($target) . '» تغییر کرد.');
        redirect_admin('admin.php?page=production_view&id=' . (int) $prod['id']);
    }

    if ($action === 'production_finish') {
        $prod = production_get((int) ($_POST['id'] ?? 0));
        if ($prod === null) {
            throw new RuntimeException('برگه تولید پیدا نشد.');
        }
        if ((string) $prod['state'] !== 'open') {
            throw new RuntimeException('این برگه تولید بسته شده است.');
        }
        if (empty($prod['started_at'])) {
            throw new RuntimeException('اول «شروع تولید» را بزنید تا مواد از انبار کسر شوند، بعد پایان تولید.');
        }
        $pdo->prepare("UPDATE production_orders SET state = 'finished', finished_at = :t, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute([':t' => date('Y-m-d H:i:s'), ':id' => (int) $prod['id']]);
        production_history_add((int) $prod['id'], (string) $prod['stage_key'], (string) $prod['stage_key'],
            $prod['responsible'] !== null && $prod['responsible'] !== '' ? (string) $prod['responsible'] : null, 'پایان تولید');
        production_sync_order_status((int) $prod['order_id'], 'ready', 'تولید تمام شد — برگه تولید ' . (int) $prod['production_no']);
        log_admin_event($action, 'پایان تولید برگه ' . (int) $prod['production_no'], true, 'production');
        flash('ok', 'تولید برگه ' . (int) $prod['production_no'] . ' تمام شد و سفارش «آماده» شد.');
        redirect_admin('admin.php?page=production_view&id=' . (int) $prod['id']);
    }

    if ($action === 'production_cancel') {
        $prod = production_get((int) ($_POST['id'] ?? 0));
        if ($prod === null) {
            throw new RuntimeException('برگه تولید پیدا نشد.');
        }
        if ((string) $prod['state'] !== 'open') {
            throw new RuntimeException('این برگه تولید بسته شده است.');
        }
        $wasStarted = !empty($prod['started_at']);
        if ($wasStarted) {
            production_apply_reversal($prod);
        }
        $pdo->prepare("UPDATE production_orders SET state = 'cancelled', cancelled_at = :t, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute([':t' => date('Y-m-d H:i:s'), ':id' => (int) $prod['id']]);
        production_history_add((int) $prod['id'], (string) $prod['stage_key'], (string) $prod['stage_key'],
            $prod['responsible'] !== null && $prod['responsible'] !== '' ? (string) $prod['responsible'] : null,
            $wasStarted ? 'لغو تولید؛ همه مصرف‌ها دقیق برگشت خوردند.' : 'لغو برگه پیش از شروع تولید.');
        log_admin_event($action, 'لغو برگه تولید ' . (int) $prod['production_no'] . ($wasStarted ? ' + برگشت مواد' : ''), true, 'production');
        flash('ok', $wasStarted ? 'برگه تولید لغو شد و همه مواد و پرت‌ها به انبار برگشتند.' : 'برگه تولید لغو شد.');
        redirect_admin('admin.php?page=production_view&id=' . (int) $prod['id']);
    }

    if ($action === 'production_save_meta') {
        $prod = production_get((int) ($_POST['id'] ?? 0));
        if ($prod === null) {
            throw new RuntimeException('برگه تولید پیدا نشد.');
        }
        $responsible = mb_substr(trim((string) ($_POST['responsible'] ?? '')), 0, 120);
        $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 2000);
        $pdo->prepare('UPDATE production_orders SET responsible = :r, notes = :n, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':r' => $responsible !== '' ? $responsible : null, ':n' => $notes !== '' ? $notes : null, ':id' => (int) $prod['id']]);
        flash('ok', 'مشخصات برگه تولید ذخیره شد.');
        redirect_admin('admin.php?page=production_view&id=' . (int) $prod['id']);
    }

    // ----- مدیریت مراحل تولید (از صفحه production_rules) -----
    if ($action === 'save_qc_items') {
        $raw = mb_substr((string) ($_POST['qc_items'] ?? ''), 0, 5000);
        $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)), static fn ($s) => $s !== ''));
        $items = array_slice($items, 0, 50);
        foreach ($items as $i => $line) {
            $items[$i] = mb_substr($line, 0, 200);
        }
        set_setting('qc_checklist', json_encode($items, JSON_UNESCAPED_UNICODE));
        log_admin_event('production_qc', 'ویرایش آیتم‌های برگه تست و کنترل کیفیت (' . count($items) . ' مورد)');
        flash('ok', 'آیتم‌های برگه تست و کنترل کیفیت ذخیره شد.');
        redirect_admin('admin.php?page=production_rules');
    }
    if ($action === 'add_pstage' || $action === 'update_pstage') {
        $key = strtolower(trim((string) ($_POST['stage_key'] ?? '')));
        $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 80);
        $color = trim((string) ($_POST['color'] ?? ''));
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
            $color = '#6b7280';
        }
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $active = isset($_POST['is_active']) ? 1 : 0;
        if (preg_match('/^[a-z0-9_]{2,30}$/', $key) !== 1) {
            throw new RuntimeException('کلید مرحله باید با حروف کوچک انگلیسی، عدد یا _ باشد (۲ تا ۳۰ نویسه).');
        }
        if ($title === '') {
            throw new RuntimeException('عنوان مرحله را بنویسید.');
        }
        if ($action === 'add_pstage') {
            $pdo->prepare('INSERT INTO production_stages (stage_key, title, color, is_active, sort_order) VALUES (:k, :t, :c, :a, :s)')
                ->execute([':k' => $key, ':t' => $title, ':c' => $color, ':a' => $active, ':s' => $sort]);
            flash('ok', 'مرحله تولید تازه اضافه شد.');
            redirect_admin('admin.php?page=production_rules');
        }
        $id = (int) ($_POST['id'] ?? 0);
        // کلید مرحله برگه‌های موجود هم با تغییر کلید جابه‌جا می‌شود تا چیزی یتیم نماند
        $old = $pdo->prepare('SELECT stage_key FROM production_stages WHERE id = :id');
        $old->execute([':id' => $id]);
        $oldKey = $old->fetchColumn();
        if ($oldKey === false) {
            throw new RuntimeException('مرحله پیدا نشد.');
        }
        $pdo->prepare('UPDATE production_stages SET stage_key = :k, title = :t, color = :c, is_active = :a, sort_order = :s WHERE id = :id')
            ->execute([':k' => $key, ':t' => $title, ':c' => $color, ':a' => $active, ':s' => $sort, ':id' => $id]);
        if ((string) $oldKey !== $key) {
            $pdo->prepare('UPDATE production_orders SET stage_key = :k WHERE stage_key = :o')
                ->execute([':k' => $key, ':o' => (string) $oldKey]);
        }
        flash('ok', 'مرحله تولید ویرایش شد.');
        redirect_admin('admin.php?page=production_rules');
    }

    if ($action === 'delete_pstage') {
        $id = (int) ($_POST['id'] ?? 0);
        $sel = $pdo->prepare('SELECT stage_key FROM production_stages WHERE id = :id');
        $sel->execute([':id' => $id]);
        $key = $sel->fetchColumn();
        if ($key === false) {
            throw new RuntimeException('مرحله پیدا نشد.');
        }
        $used = $pdo->prepare('SELECT COUNT(*) FROM production_orders WHERE stage_key = :k');
        $used->execute([':k' => (string) $key]);
        if ((int) $used->fetchColumn() > 0) {
            throw new RuntimeException('این مرحله در برگه‌های تولید استفاده شده و حذف نمی‌شود؛ می‌توانید غیرفعالش کنید.');
        }
        $pdo->prepare('DELETE FROM production_stages WHERE id = :id')->execute([':id' => $id]);
        flash('ok', 'مرحله تولید حذف شد.');
        redirect_admin('admin.php?page=production_rules');
    }

    if ($action === 'move_pstage') {
        move_row($pdo, 'production_stages', (int) ($_POST['id'] ?? 0), (string) ($_POST['direction'] ?? ''));
        flash('ok', 'ترتیب مراحل به‌روز شد.');
        redirect_admin('admin.php?page=production_rules');
    }

    if ($action === 'toggle_pstage') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE production_stages SET is_active = 1 - is_active WHERE id = :id')->execute([':id' => $id]);
        flash('ok', 'وضعیت نمایش مرحله تغییر کرد.');
        redirect_admin('admin.php?page=production_rules');
    }
}

/** داده‌های صفحات تولید */
function production_load_data(string $page): array
{
    $d = [
        'productionActiveCount' => 0,
        'productionList' => [],
        'productionStageFilter' => '',
        'productionStageCounts' => [],
        'productionStagesList' => [],
        'productionCreatableOrders' => [],
        'prodView' => null,
        'prodItems' => [],
        'prodHistory' => [],
        'prodConsumptions' => [],
        'prodReq' => null,
        'editPStage' => null,
    ];
    try {
        $d['productionActiveCount'] = production_active_count();
        $d['productionStagesList'] = production_stages(false);
    } catch (Throwable) {
        // جدول‌ها هنوز ساخته نشده‌اند (نصب خیلی قدیمی) — منو بدون بج نمایش داده می‌شود
    }

    if ($page === 'production') {
        $d['productionStageFilter'] = trim((string) ($_GET['stage'] ?? ''));
        $countStmt = db()->query("SELECT stage_key, COUNT(*) AS c FROM production_orders WHERE state = 'open' GROUP BY stage_key");
        foreach ($countStmt->fetchAll() as $row) {
            $d['productionStageCounts'][(string) $row['stage_key']] = (int) $row['c'];
        }
        $sql = 'SELECT p.*, o.order_no, o.status AS order_status, c.full_name AS customer_name
            FROM production_orders p
            JOIN orders o ON o.id = p.order_id
            LEFT JOIN customers c ON c.id = o.customer_id';
        $params = [];
        if ($d['productionStageFilter'] !== '') {
            $sql .= " WHERE p.stage_key = :st AND p.state = 'open'";
            $params[':st'] = $d['productionStageFilter'];
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 300';
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        $d['productionList'] = $stmt->fetchAll();
        // سفارش‌هایی که هنوز برگه تولید باز ندارند (برای ساخت سریع برگه)
        $d['productionCreatableOrders'] = db()->query(
            "SELECT o.id, o.order_no, o.status, c.full_name AS customer_name
             FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.status != 'cancelled'
               AND NOT EXISTS (SELECT 1 FROM production_orders p WHERE p.order_id = o.id AND p.state = 'open')
             ORDER BY o.id DESC LIMIT 200"
        )->fetchAll();
    }

    if ($page === 'production_view' && isset($_GET['id'])) {
        $prod = production_get((int) $_GET['id']);
        if ($prod !== null) {
            $d['prodView'] = $prod;
            $d['prodItems'] = order_items((int) $prod['order_id']);
            $h = db()->prepare('SELECT * FROM production_stage_history WHERE production_id = :p ORDER BY id DESC');
            $h->execute([':p' => (int) $prod['id']]);
            $d['prodHistory'] = $h->fetchAll();
            $c = db()->prepare('SELECT pc.*, m.name AS material_name, m.unit AS material_unit
                FROM production_consumptions pc JOIN materials m ON m.id = pc.material_id
                WHERE pc.production_id = :p ORDER BY pc.id ASC');
            $c->execute([':p' => (int) $prod['id']]);
            $d['prodConsumptions'] = $c->fetchAll();
            if (empty($prod['started_at'])) {
                $d['prodReq'] = production_requirements($d['prodItems']);
            } elseif (!empty($prod['plan_json'])) {
                $snap = json_decode((string) $prod['plan_json'], true);
                if (is_array($snap)) {
                    $d['prodReq'] = ['materials' => $snap, 'snapshot' => true];
                }
            }
        }
    }

    if ($page === 'production_rules' && isset($_GET['edit_pstage'])) {
        $stmt = db()->prepare('SELECT * FROM production_stages WHERE id = :id');
        $stmt->execute([':id' => (int) $_GET['edit_pstage']]);
        $r = $stmt->fetch();
        $d['editPStage'] = $r === false ? null : $r;
    }

    return $d;
}

// ---------- رندرها ----------

/**
 * نمایش برنامه برش و نیاز مواد (هم برای پیش‌نمایش زنده قبل از شروع، هم برای اسنپ‌شات
 * ذخیره‌شده موقع شروع). هیچ قیمتی اینجا نمایش داده نمی‌شود — برگه کارگاه است.
 */
function production_render_plan(array $materials, bool $snapshot): void
{
    $others = [];
    foreach ($materials as $m) {
        if (empty($m['cuttable'])) {
            $others[] = $m;
            continue;
        }
        $plan = (array) ($m['plan'] ?? []);
        ?>
        <div class="card" style="margin-bottom:14px">
            <h3 style="margin-top:0">✂️ برش «<?= e($m['name']) ?>»
                <small class="muted">(هر <?= e($m['cut_unit_label'] ?? 'واحد') ?>: <?= e(format_qty((float) ($m['unit_cm'] ?? 0))) ?> سانت — موجودی انبار: <?= e(format_qty((float) $m['stock'])) ?> <?= e($m['unit']) ?>)</small>
            </h3>
            <?php if (!empty($plan['unplaced'])): ?>
                <p><span class="badge" style="background:#fee2e2;color:#b91c1c">⚠ قطعه‌های جاافتاده:
                    <?php foreach ((array) $plan['unplaced'] as $p): ?><?= e(format_qty((float) $p)) ?> سانت؛ <?php endforeach; ?>
                    این قطعه‌ها از طول واحد تازه و همه پرت‌ها بلندترند و تولید شروع نمی‌شود.</span></p>
            <?php endif; ?>
            <?php if (($plan['bins'] ?? []) === []): ?>
                <p class="muted">قطعه‌ای برای برش نیست.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>منبع برش</th><th>قطعه‌ها (سانت)</th><th>باقی‌مانده</th></tr></thead>
                <tbody>
                <?php $newNo = 0; foreach ((array) $plan['bins'] as $bin): ?>
                    <tr>
                        <td><?php if (($bin['kind'] ?? '') === 'remnant'): ?>
                                پرت انبار (<?= e(format_qty((float) $bin['capacity'])) ?> سانت)
                            <?php else: $newNo++; ?>
                                واحد تازه <?= $newNo ?> (<?= e(format_qty((float) $bin['capacity'])) ?> سانت)
                            <?php endif; ?></td>
                        <td><?php foreach ((array) $bin['pieces'] as $pc): ?><?= e(format_qty((float) $pc)) ?>؛ <?php endforeach; ?></td>
                        <td><?php if (($bin['leftover'] ?? 0) > 0): ?>
                                <span class="badge ok"><?= e(format_qty((float) $bin['leftover'])) ?> سانت ← انبار پرتی</span>
                            <?php elseif (($bin['waste'] ?? 0) > 0): ?>
                                <span class="muted">ضایعات <?= e(format_qty((float) $bin['waste'])) ?> سانت</span>
                            <?php else: ?>مصرف کامل<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <p class="muted" style="margin-bottom:0">
                واحد تازه لازم: <strong><?= (int) ($plan['new_units'] ?? 0) ?> عدد</strong>
                (<?= e(format_qty((float) ($plan['new_meters'] ?? 0))) ?> متر)
                — پرت برگشتی به انبار: <?= e(format_qty((float) ($plan['leftover_cm'] ?? 0))) ?> سانت
                — ضایعات: <?= e(format_qty((float) ($plan['waste_cm'] ?? 0))) ?> سانت
                <?php if ((float) ($m['shortage'] ?? 0) > 0): ?>
                    — <span class="badge" style="background:#fee2e2;color:#b91c1c">کمبود موجودی: <?= e(format_qty((float) $m['shortage'])) ?> <?= e($m['unit']) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
    if ($others !== []): ?>
    <div class="card" style="margin-bottom:14px">
        <h3 style="margin-top:0">مواد مصرفی دیگر</h3>
        <table>
            <thead><tr><th>ماده</th><th>نیاز</th><th>موجودی</th><th>وضعیت</th></tr></thead>
            <tbody>
            <?php foreach ($others as $m): ?>
                <tr>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e(format_qty((float) ($m['needed_other'] ?? 0))) ?> <?= e($m['unit']) ?></td>
                    <td><?= e(format_qty((float) $m['stock'])) ?> <?= e($m['unit']) ?></td>
                    <td><?= (float) ($m['shortage'] ?? 0) > 0
                        ? '<span class="badge" style="background:#fee2e2;color:#b91c1c">کمبود: ' . e(format_qty((float) $m['shortage'])) . '</span>'
                        : '<span class="badge ok">کافی</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif;
}

/** رندر صفحه «تولید» (فهرست برگه‌ها + ساخت برگه از سفارش) */
function production_render_list(array $d): void
{
    extract($d);
    $activeStages = array_values(array_filter($productionStagesList, static fn (array $s): bool => (int) $s['is_active'] === 1));
    ?>
    <h1>تولید</h1>
    <p class="muted">برگه‌های تولید کارگاه؛ از هر سفارش یک برگه بسازید، با «شروع تولید» مواد از انبار کسر و پرت‌ها مدیریت می‌شوند.</p>

    <div class="stat-grid dash-cards">
        <a class="stat-card" href="admin.php?page=production"><span>برگه‌های در جریان</span><strong><?= (int) $productionActiveCount ?></strong></a>
        <a class="stat-card" href="admin.php?page=production_rules"><span>مراحل تولید</span><strong><?= count($activeStages) ?> مرحله فعال</strong></a>
        <a class="stat-card" href="admin.php?page=remnants"><span>انبار پرتی</span><strong>مدیریت تکه‌ها</strong></a>
    </div>

    <section class="card wide">
        <h2>ساخت برگه تولید از سفارش</h2>
        <?php if ($productionCreatableOrders === []): ?>
            <p class="muted">سفارشی بدون برگه تولید باز وجود ندارد.</p>
        <?php else: ?>
        <form method="post" class="inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_production">
            <label style="flex:1">سفارش
                <select name="order_id" required>
                    <?php foreach ($productionCreatableOrders as $o): ?>
                        <option value="<?= (int) $o['id'] ?>">سفارش #<?= (int) $o['order_no'] ?> — <?= e($o['customer_name'] ?? 'بدون مشتری') ?> (<?= e(order_status_title((string) $o['status'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit" class="btn add" style="align-self:end">+ ساخت برگه تولید</button>
        </form>
        <p class="muted">با ساخت برگه، وضعیت سفارش (اگر وضعیت «در حال تولید» تعریف شده باشد) خودکار به «در حال تولید» می‌رود. کسر مواد هنگام «شروع تولید» انجام می‌شود، نه حالا.</p>
        <?php endif; ?>
    </section>

    <p>
        <a class="btn small<?= $productionStageFilter === '' ? ' primary' : '' ?>" href="admin.php?page=production">همه</a>
        <?php foreach ($activeStages as $s): ?>
            <a class="btn small<?= $productionStageFilter === (string) $s['stage_key'] ? ' primary' : '' ?>" href="admin.php?page=production&stage=<?= e($s['stage_key']) ?>"><?= e($s['title']) ?> (<?= (int) ($productionStageCounts[(string) $s['stage_key']] ?? 0) ?>)</a>
        <?php endforeach; ?>
    </p>

    <section class="card wide">
        <h2>برگه‌های تولید</h2>
        <?php if ($productionList === []): ?>
            <p class="muted">هنوز برگه تولیدی ساخته نشده است.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>برگه</th><th>سفارش</th><th>مشتری</th><th>مرحله</th><th>وضعیت</th><th>مسئول</th><th>شروع</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($productionList as $p): ?>
                <tr>
                    <td><strong>#<?= (int) $p['production_no'] ?></strong></td>
                    <td><a href="admin.php?page=order_view&id=<?= (int) $p['order_id'] ?>">سفارش #<?= (int) $p['order_no'] ?></a></td>
                    <td><?= e($p['customer_name'] ?? '—') ?></td>
                    <td><span class="badge" style="background:<?= e(production_stage_color((string) $p['stage_key'])) ?>22;color:<?= e(production_stage_color((string) $p['stage_key'])) ?>"><?= e(production_stage_title((string) $p['stage_key'])) ?></span></td>
                    <td><?= e(production_state_label((string) $p['state'])) ?></td>
                    <td><?= e($p['responsible'] ?? '—') ?></td>
                    <td class="muted"><?= e($p['started_at'] ?? '—') ?></td>
                    <td><a class="btn small" href="admin.php?page=production_view&id=<?= (int) $p['id'] ?>">مشاهده</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>
    <?php
}

/** رندر صفحه یک برگه تولید + برگه چاپی کارگاه (بدون قیمت) */
function production_render_view(array $d): void
{
    extract($d);
    $p = $prodView;
    if ($p === null) {
        echo '<h1>برگه تولید پیدا نشد</h1><p><a href="admin.php?page=production">بازگشت به تولید</a></p>';
        return;
    }
    $print = isset($_GET['print']);
    $qcMode = isset($_GET['qc']);
    $isOpen = (string) $p['state'] === 'open';
    $started = !empty($p['started_at']);
    $stageKeys = array_map(static fn (array $s): string => (string) $s['stage_key'], production_stages(true));
    $curIdx = array_search((string) $p['stage_key'], $stageKeys, true);
    $nextKey = ($curIdx !== false && isset($stageKeys[$curIdx + 1])) ? $stageKeys[$curIdx + 1] : null;
    $prevKey = ($curIdx !== false && $curIdx > 0) ? $stageKeys[$curIdx - 1] : null;
    $planMaterials = ($prodReq !== null && isset($prodReq['materials']) && is_array($prodReq['materials'])) ? $prodReq['materials'] : null;
    $isSnapshot = !empty($prodReq['snapshot']);
    ?>
    <style>
    @media print {
        header, aside.sidebar, .nav-overlay, .screen-area { display: none !important; }
        .layout { display: block !important; }
        main.content { margin: 0 !important; padding: 0 !important; max-width: none !important; }
        .worksheet { display: block !important; border: none !important; }
    }
    .worksheet { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; }
    .worksheet h2 { margin-top: 0; }
    .worksheet table { width: 100%; }
    .sig-row { display: flex; gap: 40px; margin-top: 40px; }
    .sig-row div { flex: 1; border-top: 1px dashed #9ca3af; padding-top: 8px; text-align: center; }
    </style>
    <div class="screen-area">
        <p><a href="admin.php?page=production">← بازگشت به تولید</a></p>
        <h1>برگه تولید #<?= (int) $p['production_no'] ?>
            <span class="badge" style="background:<?= e(production_stage_color((string) $p['stage_key'])) ?>22;color:<?= e(production_stage_color((string) $p['stage_key'])) ?>"><?= e(production_stage_title((string) $p['stage_key'])) ?></span>
            <span class="badge"><?= e(production_state_label((string) $p['state'])) ?></span>
        </h1>
        <p>
            <a class="btn small" href="admin.php?page=production_view&id=<?= (int) $p['id'] ?>&print=1" target="_blank">🖨 چاپ برگه کارگاه</a>
            <a class="btn small" href="admin.php?page=production_view&id=<?= (int) $p['id'] ?>&qc=1" target="_blank">🧪 چاپ برگه تست و کنترل کیفیت</a>
            <a class="btn small" href="admin.php?page=order_view&id=<?= (int) $p['order_id'] ?>">مشاهده سفارش #<?= (int) $p['order_no'] ?></a>
        </p>

        <section class="card wide">
            <h2>اطلاعات برگه</h2>
            <table><tbody>
                <tr><th>سفارش</th><td><a href="admin.php?page=order_view&id=<?= (int) $p['order_id'] ?>">#<?= (int) $p['order_no'] ?></a> — وضعیت سفارش: <?= e(order_status_title((string) $p['order_status'])) ?></td></tr>
                <tr><th>مشتری</th><td><?= e($p['customer_name'] ?? '—') ?> — <span dir="ltr"><?= e($p['customer_mobile'] ?? '') ?></span></td></tr>
                <tr><th>مسئول تولید</th><td><?= e($p['responsible'] ?? '—') ?></td></tr>
                <tr><th>ساخته‌شده</th><td><?= e($p['created_at'] ?? '') ?></td></tr>
                <?php if ($started): ?><tr><th>شروع تولید</th><td><?= e($p['started_at']) ?></td></tr><?php endif; ?>
                <?php if (!empty($p['finished_at'])): ?><tr><th>پایان تولید</th><td><?= e($p['finished_at']) ?></td></tr><?php endif; ?>
                <?php if (!empty($p['cancelled_at'])): ?><tr><th>لغو</th><td><?= e($p['cancelled_at']) ?></td></tr><?php endif; ?>
                <?php if (!empty($p['notes'])): ?><tr><th>یادداشت برگه</th><td><?= nl2br(e($p['notes'])) ?></td></tr><?php endif; ?>
            </tbody></table>
            <?php if ($isOpen): ?>
            <form method="post" class="inline-fields">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="production_save_meta">
                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                <label>مسئول تولید<input type="text" name="responsible" value="<?= e($p['responsible'] ?? '') ?>" placeholder="نام مسئول این برگه…"></label>
                <label style="flex:1">یادداشت برگه<input type="text" name="notes" value="<?= e($p['notes'] ?? '') ?>" placeholder="اختیاری…"></label>
                <button type="submit" class="btn small edit" style="align-self:end">ذخیره</button>
            </form>
            <?php endif; ?>
        </section>

        <?php if ($isOpen): ?>
        <section class="card wide">
            <h2>اقدام‌های تولید</h2>
            <div class="inline-fields" style="flex-wrap:wrap;gap:8px;align-items:end">
                <?php if (!$started): ?>
                <form method="post" onsubmit="return confirm('تولید شروع شود؟ مواد طبق برنامه برش از انبار کسر و پرت‌های تازه ثبت می‌شوند.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="production_start">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn primary">▶ شروع تولید و کسر مواد</button>
                </form>
                <?php endif; ?>
                <?php if ($prevKey !== null): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="production_back">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn small">→ مرحله قبل: <?= e(production_stage_title($prevKey)) ?></button>
                </form>
                <?php endif; ?>
                <?php if ($nextKey !== null): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="production_advance">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn small primary">مرحله بعد: <?= e(production_stage_title($nextKey)) ?> ←</button>
                </form>
                <?php endif; ?>
                <?php if ($started): ?>
                <form method="post" onsubmit="return confirm('تولید این برگه تمام شد؟ سفارش «آماده» می‌شود.')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="production_finish">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn small add">✓ پایان تولید</button>
                </form>
                <?php endif; ?>
                <form method="post" onsubmit="return confirm('<?= $started ? 'برگه لغو شود؟ همه مواد مصرف‌شده و پرت‌ها دقیق به انبار برمی‌گردند.' : 'این برگه تولید لغو شود؟' ?>')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="production_cancel">
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                    <button type="submit" class="btn small danger-btn">لغو برگه تولید</button>
                </form>
            </div>
            <?php if (!$started): ?><p class="muted">کسر از انبار فقط با «شروع تولید» انجام می‌شود؛ تا آن لحظه برنامه برش زیر فقط پیش‌نمایش زنده است.</p><?php endif; ?>
        </section>
        <?php endif; ?>

        <section class="card wide">
            <h2>ردیف‌های تولید</h2>
            <table>
                <thead><tr><th>#</th><th>محصول</th><th>طول (سانت)</th><th>تعداد</th><th>سیم</th><th>درپوش</th><th>توضیح</th></tr></thead>
                <tbody>
                <?php $rn = 0; foreach ($prodItems as $it): $rn++; ?>
                    <tr>
                        <td><?= $rn ?></td>
                        <td><?= e($it['product_name']) ?>
                            <?php $oj = json_decode((string) ($it['options_json'] ?? ''), true); if (is_array($oj) && $oj !== []): ?>
                                <br><small class="muted"><?php foreach ($oj as $osnap): ?><?= e($osnap['attr'] ?? '') ?>: <?= e($osnap['option'] ?? '') ?>؛ <?php endforeach; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= e(format_qty((float) $it['length_cm'])) ?></td>
                        <td><?= (int) $it['qty'] ?></td>
                        <td><?= (int) $it['wire_length_cm'] ?> سانت</td>
                        <td><?= (int) $it['has_endcap'] === 1 ? 'دارد' : '—' ?></td>
                        <td><?= e($it['note'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="card wide">
            <h2><?= $isSnapshot ? 'برنامه برش اجراشده (موقع شروع تولید)' : 'پیش‌نمایش برنامه برش و نیاز مواد' ?></h2>
            <?php if (!$isSnapshot && $isOpen): ?><p class="muted">این برنامه با موجودی و پرت‌های همین لحظه حساب شده و موقع «شروع تولید» دوباره بررسی می‌شود؛ اول پرت‌های انبار مصرف می‌شوند، بعد واحد تازه باز می‌شود.</p><?php endif; ?>
            <?php if ($planMaterials === null || $planMaterials === []): ?>
                <p class="muted">برای محصولات این سفارش فرمول ساخت (BOM) تعریف نشده است؛ از صفحه ویرایش محصول، مصرف مواد را مشخص کنید.</p>
            <?php else: ?>
                <?php production_render_plan($planMaterials, $isSnapshot); ?>
            <?php endif; ?>
        </section>

        <?php if ($prodConsumptions !== []): ?>
        <section class="card wide">
            <h2>مصرف‌های ثبت‌شده این برگه</h2>
            <table>
                <thead><tr><th>ماده</th><th>نوع</th><th>مقدار</th><th>زمان</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($prodConsumptions as $c): ?>
                    <tr>
                        <td><?= e($c['material_name']) ?></td>
                        <td><?= e(['stock_out' => 'خروج از انبار اصلی', 'remnant_use' => 'مصرف تکه پرت', 'remnant_new' => 'پرت تازه برگشتی'][$c['kind']] ?? $c['kind']) ?></td>
                        <td><?= e(format_qty((float) $c['qty'])) ?> <?= e($c['kind'] === 'stock_out' ? $c['material_unit'] : 'سانت') ?></td>
                        <td class="muted"><?= e($c['created_at'] ?? '') ?></td>
                        <td><?= (int) $c['reversed'] === 1 ? '<span class="badge">برگشت خورده (لغو)</span>' : '<span class="badge ok">اعمال‌شده</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>
        <?php endif; ?>

        <section class="card wide">
            <h2>تاریخچه مراحل</h2>
            <?php if ($prodHistory === []): ?>
                <p class="muted">تاریخچه‌ای ثبت نشده است.</p>
            <?php else: ?>
            <table>
                <thead><tr><th>از</th><th>به</th><th>مسئول</th><th>یادداشت</th><th>زمان</th></tr></thead>
                <tbody>
                <?php foreach ($prodHistory as $h): ?>
                    <tr>
                        <td><?= $h['from_stage'] !== null ? e(production_stage_title((string) $h['from_stage'])) : '—' ?></td>
                        <td><span class="badge" style="background:<?= e(production_stage_color((string) $h['to_stage'])) ?>22;color:<?= e(production_stage_color((string) $h['to_stage'])) ?>"><?= e(production_stage_title((string) $h['to_stage'])) ?></span></td>
                        <td><?= e($h['responsible'] ?? '—') ?></td>
                        <td><?= e($h['note'] ?? '—') ?></td>
                        <td class="muted"><?= e($h['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>
    </div>

    <div class="worksheet" id="worksheet"<?= $print && !isset($_GET['qc']) ? '' : ' style="display:none"' ?>>
        <h2>برگه تولید #<?= (int) $p['production_no'] ?> — کارگاه</h2>
        <p class="muted"><?= e(all_settings()['site_title'] ?? '') ?> — سفارش #<?= (int) $p['order_no'] ?> — مشتری: <?= e($p['customer_name'] ?? '—') ?> — مرحله: <?= e(production_stage_title((string) $p['stage_key'])) ?><?= $started ? ' — شروع: ' . e($p['started_at']) : ' (برنامه پیشنهادی؛ هنوز شروع نشده)' ?></p>
        <table>
            <thead><tr><th>#</th><th>محصول</th><th>طول (سانت)</th><th>تعداد</th><th>سیم</th><th>درپوش</th><th>توضیح</th></tr></thead>
            <tbody>
            <?php $rn = 0; foreach ($prodItems as $it): $rn++; ?>
                <tr>
                    <td><?= $rn ?></td>
                    <td><?= e($it['product_name']) ?>
                        <?php $oj = json_decode((string) ($it['options_json'] ?? ''), true); if (is_array($oj) && $oj !== []): ?>
                            <br><small><?php foreach ($oj as $osnap): ?><?= e($osnap['attr'] ?? '') ?>: <?= e($osnap['option'] ?? '') ?>؛ <?php endforeach; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(format_qty((float) $it['length_cm'])) ?></td>
                    <td><?= (int) $it['qty'] ?></td>
                    <td><?= (int) $it['wire_length_cm'] ?> سانت</td>
                    <td><?= (int) $it['has_endcap'] === 1 ? 'دارد' : '—' ?></td>
                    <td><?= e($it['note'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($planMaterials !== null && $planMaterials !== []): ?>
        <h3>☑ چک‌لیست مواد خام</h3>
        <table>
            <thead><tr><th style="width:38px">✓</th><th>ماده</th><th>مقدار لازم</th><th>منبع تامین</th></tr></thead>
            <tbody>
            <?php foreach ($planMaterials as $m): $plan = (array) ($m['plan'] ?? []); ?>
                <tr>
                    <td style="font-size:18px">☐</td>
                    <td><strong><?= e($m['name']) ?></strong></td>
                    <td>
                        <?php if (!empty($m['cuttable'])): ?>
                            <?= (int) ($plan['new_units'] ?? 0) ?> <?= e($m['cut_unit_label'] ?? 'واحد') ?> تازه
                            <?php $remUse = 0; foreach ((array) ($plan['bins'] ?? []) as $bin) { if (($bin['kind'] ?? '') === 'remnant') { $remUse++; } } ?>
                            <?= $remUse > 0 ? ' + ' . $remUse . ' تکه پرت انبار' : '' ?>
                        <?php else: ?>
                            <?= e(format_qty((float) ($m['needed_other'] ?? 0))) ?> <?= e($m['unit']) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= !empty($m['cuttable']) ? 'انبار پرتی + انبار اصلی' : 'انبار اصلی' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>✂️ لیست برش</h3>
        <?php $totWaste = 0.0; $totLeft = 0.0; $totUnits = 0; $totPieces = 0; ?>
        <?php foreach ($planMaterials as $m): if (empty($m['cuttable'])) { continue; } $plan = (array) ($m['plan'] ?? []); $clbl = (string) ($m['cut_unit_label'] ?? 'واحد'); ?>
            <h4 style="margin:18px 0 6px">«<?= e($m['name']) ?>» — هر <?= e($clbl) ?>: <?= e(format_qty((float) ($m['unit_cm'] ?? 0))) ?> سانت</h4>
            <table>
                <thead><tr><th style="width:38px">✓</th><th>منبع برش</th><th>قطعه‌ها (سانت)</th><th>باقی‌مانده</th></tr></thead>
                <tbody>
                <?php $newNo = 0; foreach ((array) ($plan['bins'] ?? []) as $bin): $isRem = ($bin['kind'] ?? '') === 'remnant'; if (!$isRem) { $newNo++; } ?>
                    <tr>
                        <td style="font-size:18px">☐</td>
                        <td><?= $isRem ? 'تکه پرت انبار (' . e(format_qty((float) $bin['capacity'])) . ' سانت)' : e($clbl) . ' تازه ' . $newNo . ' (' . e(format_qty((float) $bin['capacity'])) . ' سانت)' ?></td>
                        <td><?php foreach ((array) $bin['pieces'] as $pc): ?><?= e(format_qty((float) $pc)) ?>؛ <?php endforeach; ?></td>
                        <td><?php if (($bin['leftover'] ?? 0) > 0): ?>
                                <?= e(format_qty((float) $bin['leftover'])) ?> سانت ← انبار پرتی
                            <?php elseif (($bin['waste'] ?? 0) > 0): ?>
                                ضایعات <?= e(format_qty((float) $bin['waste'])) ?> سانت
                            <?php else: ?>مصرف کامل<?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $totWaste += (float) ($plan['waste_cm'] ?? 0);
            $totLeft += (float) ($plan['leftover_cm'] ?? 0);
            $totUnits += (int) ($plan['new_units'] ?? 0);
            $totPieces += (int) ($plan['pieces_count'] ?? 0);
            ?>
        <?php endforeach; ?>

        <h3>📊 میزان پرت این برگه</h3>
        <table>
            <thead><tr><th>ماده</th><th>قطعه‌ها</th><th><?= 'هر ' ?>واحد تازه</th><th>پرت برگشتی به انبار</th><th>ضایعات</th></tr></thead>
            <tbody>
            <?php foreach ($planMaterials as $m): if (empty($m['cuttable'])) { continue; } $plan = (array) ($m['plan'] ?? []); ?>
                <tr>
                    <td><?= e($m['name']) ?></td>
                    <td><?= (int) ($plan['pieces_count'] ?? 0) ?> قطعه</td>
                    <td><?= (int) ($plan['new_units'] ?? 0) ?> <?= e($m['cut_unit_label'] ?? 'واحد') ?> (<?= e(format_qty((float) ($plan['new_meters'] ?? 0))) ?> متر)</td>
                    <td><?= e(format_qty((float) ($plan['leftover_cm'] ?? 0))) ?> سانت</td>
                    <td><?= e(format_qty((float) ($plan['waste_cm'] ?? 0))) ?> سانت</td>
                </tr>
            <?php endforeach; ?>
                <tr>
                    <td><strong>جمع</strong></td>
                    <td><strong><?= $totPieces ?> قطعه</strong></td>
                    <td><strong><?= $totUnits ?> واحد تازه</strong></td>
                    <td><strong><?= e(format_qty($totLeft)) ?> سانت</strong></td>
                    <td><strong><?= e(format_qty($totWaste)) ?> سانت</strong></td>
                </tr>
            </tbody>
        </table>
        <?php endif; ?>
        <p>مسئول تولید: <?= e($p['responsible'] ?? '……………………') ?></p>
        <div class="sig-row"><div>مسئول تولید</div><div>کنترل کیفیت</div></div>
    </div>

    <div class="worksheet" id="qc-sheet"<?= ($print && $qcMode) ? '' : ' style="display:none"' ?>>
        <h2>برگه تست و کنترل کیفیت — برگه تولید #<?= (int) $p['production_no'] ?></h2>
        <p class="muted"><?= e(all_settings()['site_title'] ?? '') ?> — سفارش #<?= (int) $p['order_no'] ?> — مشتری: <?= e($p['customer_name'] ?? '—') ?> — مرحله: <?= e(production_stage_title((string) $p['stage_key'])) ?> — تاریخ چاپ: <?= e(date('Y-m-d')) ?></p>

        <h3>مشخصات محصول</h3>
        <table>
            <thead><tr><th>#</th><th>محصول</th><th>طول (سانت)</th><th>تعداد</th><th>سیم</th><th>درپوش</th></tr></thead>
            <tbody>
            <?php $rn = 0; foreach ($prodItems as $it): $rn++; ?>
                <tr>
                    <td><?= $rn ?></td>
                    <td><?= e($it['product_name']) ?></td>
                    <td><?= e(format_qty((float) $it['length_cm'])) ?></td>
                    <td><?= (int) $it['qty'] ?></td>
                    <td><?= (int) $it['wire_length_cm'] ?> سانت</td>
                    <td><?= (int) $it['has_endcap'] === 1 ? 'دارد' : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <h3>چک‌لیست تست و کنترل کیفیت</h3>
        <?php $qcItems = qc_checklist_items(); ?>
        <table>
            <thead><tr><th style="width:38px">✓</th><th>آیتم تست</th><th style="width:80px">قبول ☐</th><th style="width:80px">رد ☐</th><th>یادداشت</th></tr></thead>
            <tbody>
            <?php foreach ($qcItems as $item): ?>
                <tr>
                    <td style="font-size:18px">☐</td>
                    <td><?= e($item) ?></td>
                    <td style="text-align:center;font-size:18px">☐</td>
                    <td style="text-align:center;font-size:18px">☐</td>
                    <td>&nbsp;</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p style="margin-top:18px"><strong>نتیجه نهایی:</strong> ☐ تایید — ☐ رد — ☐ نیاز به اصلاح</p>
        <p><strong>تاریخ تست:</strong> <?= e(date('Y-m-d')) ?></p>
        <div class="sig-row"><div>تست‌کننده</div><div>کنترل کیفیت</div><div>تایید نهایی</div></div>
    </div>
    <?php if ($print): ?>
    <script>window.addEventListener('load', function(){ window.print(); });</script>
    <?php endif; ?>
    <?php
}

/** رندر صفحه «مراحل و قوانین تولید» */
function production_render_rules(array $d): void
{
    extract($d);
    $edit = $editPStage;
    ?>
    <h1>مراحل و قوانین تولید</h1>
    <p class="muted">مراحل کارگاه کاملاً از همین‌جا قابل‌ویرایش‌اند؛ ترتیب و رنگ هر مرحله روی برگه‌های تولید و بج منو اثر می‌گذارد. هیچ مرحله‌ای در کد ثابت نیست.</p>

    <?php if ($edit === null): ?>
    <div class="crud-toolbar">
        <button type="button" class="btn add" data-toggle-panel="pstage-form-panel" aria-expanded="false">+ افزودن مرحله</button>
    </div>
    <?php endif; ?>
    <div class="crud-panel" id="pstage-form-panel" <?= $edit !== null ? 'data-open="1"' : 'hidden' ?>>
    <section class="card wide">
        <h2><?= $edit !== null ? 'ویرایش مرحله: ' . e($edit['title'] ?? '') : 'افزودن مرحله تازه' ?></h2>
        <form method="post" class="inline-fields">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $edit !== null ? 'update_pstage' : 'add_pstage' ?>">
            <?php if ($edit !== null): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
            <label>کلید (انگلیسی)<input type="text" name="stage_key" dir="ltr" required pattern="[a-z0-9_]{2,30}" value="<?= e($edit['stage_key'] ?? '') ?>" placeholder="مثلاً cutting"></label>
            <label>عنوان<input type="text" name="title" required value="<?= e($edit['title'] ?? '') ?>" placeholder="مثلاً برش"></label>
            <label>رنگ<input type="color" name="color" value="<?= e($edit['color'] ?? '#6b7280') ?>"></label>
            <label>ترتیب<input type="number" name="sort_order" step="1" value="<?= (int) ($edit['sort_order'] ?? 0) ?>"></label>
            <label class="check"><input type="checkbox" name="is_active" value="1"<?= $edit === null || (int) $edit['is_active'] === 1 ? ' checked' : '' ?>> فعال</label>
            <button type="submit" class="btn <?= $edit !== null ? 'edit' : 'add' ?>" style="align-self:end"><?= $edit !== null ? 'ذخیره ویرایش' : 'افزودن مرحله' ?></button>
            <?php if ($edit !== null): ?><a class="btn" style="align-self:end" href="admin.php?page=production_rules">انصراف</a><?php endif; ?>
        </form>
        <p class="muted">اولین مرحله فعال، مرحله شروع هر برگه تازه است؛ مرحله «در صف» را معمولاً اول نگه دارید. تغییر کلید یک مرحله، برگه‌های موجود را هم به کلید تازه منتقل می‌کند.</p>
    </section>
    </div>

    <section class="card wide">
        <h2>مراحل تولید</h2>
        <?php if ($productionStagesList === []): ?>
            <p class="muted">هنوز مرحله‌ای تعریف نشده است.</p>
        <?php else: ?>
        <table>
            <thead><tr><th>ترتیب</th><th>عنوان</th><th>کلید</th><th>رنگ</th><th>وضعیت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ($productionStagesList as $s): ?>
                <tr>
                    <td>
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_pstage"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="up"><button type="submit" class="btn small" title="بالا">↑ بالا</button></form>
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_pstage"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><input type="hidden" name="direction" value="down"><button type="submit" class="btn small" title="پایین">↓ پایین</button></form>
                        <span class="muted"><?= (int) $s['sort_order'] ?></span>
                    </td>
                    <td><?= e($s['title']) ?></td>
                    <td><code dir="ltr"><?= e($s['stage_key']) ?></code></td>
                    <td><span class="badge" style="background:<?= e($s['color']) ?>22;color:<?= e($s['color']) ?>"><?= e($s['color']) ?></span></td>
                    <td><?= (int) $s['is_active'] === 1 ? '<span class="badge ok">فعال</span>' : '<span class="badge">غیرفعال</span>' ?></td>
                    <td>
                        <a class="btn small edit" href="admin.php?page=production_rules&edit_pstage=<?= (int) $s['id'] ?>">ویرایش</a>
                        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_pstage"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="btn small warn"><?= (int) $s['is_active'] === 1 ? 'غیرفعال‌کردن' : 'فعال‌کردن' ?></button></form>
                        <form method="post" class="inline" onsubmit="return confirm('این مرحله حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_pstage"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </section>

    <section class="card wide">
        <h2>آیتم‌های برگه تست و کنترل کیفیت (۸٫۵٫۰)</h2>
        <p class="muted">هر خط یک آیتم تست است. برگه تست و کنترل کیفیت هر برگه تولید از همین‌جا خوانده می‌شود؛ آیتم‌ها را با نیاز کارگاه خودتان تنظیم کنید.</p>
        <form method="post" class="card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_qc_items">
            <label style="display:block">آیتم‌های چک‌لیست (هر خط یک آیتم)
                <textarea name="qc_items" rows="8" style="width:100%"><?= e(implode("\n", qc_checklist_items())) ?></textarea>
            </label>
            <button type="submit" class="btn add">💾 ذخیره آیتم‌های کنترل کیفیت</button>
        </form>
    </section>

    <section class="card wide">
        <h2>قوانین مرتبط</h2>
        <table><tbody>
            <tr><th>حداقل طول پرت برگشتی به انبار پرتی</th><td><?= e(format_qty((float) get_setting('remnant_min_cm', '20'))) ?> سانت — از صفحه <a href="admin.php?page=order_rules">قوانین قیمت‌گذاری</a> قابل تغییر است. باقی‌مانده کوتاه‌تر از این حد، ضایعات حساب می‌شود.</td></tr>
            <tr><th>طول واحد تازه هر ماده (شاخه/رول)</th><td>برای هر ماده برش‌خور از صفحه <a href="admin.php?page=materials">مواد اولیه</a> ستون «طول هر واحد تازه (سانت)» را پر کنید (مثلاً پروفیل ۳۰۰، نوار LED ‏۵۰۰). موجودی این مواد بر حسب متر ثبت می‌شود.</td></tr>
            <tr><th>وضعیت‌های سفارش</th><td>با ساخت برگه، سفارش به وضعیت <code dir="ltr">in_production</code> و با پایان تولید به <code dir="ltr">ready</code> می‌رود — فقط اگر این وضعیت‌ها در <a href="admin.php?page=order_rules">قوانین قیمت‌گذاری</a> تعریف و فعال باشند.</td></tr>
        </tbody></table>
    </section>
    <?php
}
