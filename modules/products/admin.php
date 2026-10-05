<?php
// modules/products/admin.php — مدیریت محصولات (ماژول واقعی از ۹٫۹۹٫۱۴)
// منتقل‌شده از modules/admin/Catalog.php
declare(strict_types=1);

if (!defined('CMS_ADMIN_PANEL')) {
    http_response_code(403);
    exit;
}

function products_render_admin(array $d): void
{
    extract($d);
    ?>            <h1>محصولات</h1>
            <p class="muted">قیمت‌ها «متری» و به تومان هستند. آپشن‌ها (مثل سنسور) از بخش «ویژگی‌های محصول» تعریف می‌شوند و به قیمت متری اضافه می‌شوند. پیش‌نمایش زنده: <a href="/products" target="_blank">کاتالوگ عمومی</a></p>

            <?php if ($editProduct === null): ?>
            <div class="crud-toolbar">
                <button type="button" class="btn add" data-toggle-panel="product-form-panel" aria-expanded="false">+ افزودن محصول</button>
            </div>
            <?php endif; ?>
            <div class="crud-panel" id="product-form-panel" <?= $editProduct !== null ? 'data-open="1"' : 'hidden' ?>>
            <h2><?= $editProduct !== null ? 'ویرایش محصول: ' . e($editProduct['name'] ?? '') : 'محصول تازه' ?></h2>
            <form method="post" enctype="multipart/form-data" class="card wide" id="product-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editProduct !== null ? 'update_product' : 'add_product' ?>">
                <?php if ($editProduct !== null): ?><input type="hidden" name="id" value="<?= (int) $editProduct['id'] ?>"><?php endif; ?>
                <label>نام محصول *
                    <input type="text" name="name" required value="<?= e($editProduct['name'] ?? '') ?>">
                </label>
                <label>دسته‌بندی
                    <select name="category_id">
                        <option value="0">— بدون دسته —</option>
                        <?php foreach ($categoriesList as $cc): ?>
                            <option value="<?= (int) $cc['id'] ?>" <?= ($editProduct['category_id'] ?? null) !== null && (int) $editProduct['category_id'] === (int) $cc['id'] ? 'selected' : '' ?>><?= !empty($cc['parent_id']) ? '↳ ' : '' ?><?= e($cc['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>کد محصول (SKU)
                    <input type="text" name="sku" dir="ltr" value="<?= e($editProduct['sku'] ?? '') ?>">
                </label>
                <label>توضیح
                    <textarea name="description" id="product-desc-editor" rows="3"><?= e($editProduct['description'] ?? '') ?></textarea>
                </label>
                <script src="assets/tinymce/tinymce.min.js"></script>
                <script src="assets/tinymce-init.js"></script>
                <script>initTinyMCE('#product-desc-editor', 280);</script>
                <label>عکس محصول
                    <input type="file" name="image" accept="image/*">
                </label>
                <?php if ($editProduct !== null && !empty($editProduct['image'])): ?>
                    <p><img src="<?= e(UPLOADS_URL . '/' . basename((string) $editProduct['image'])) ?>" alt="" style="max-width:220px;border-radius:8px"></p>
                    <label class="check"><input type="checkbox" name="remove_image" value="1"> حذف عکس فعلی</label>
                <?php endif; ?>
                <label>قیمت متری مشتری (تومان) *
                    <input type="number" name="price_per_meter" id="pf-retail" min="0" step="any" required value="<?= (int) ($editProduct['price_per_meter'] ?? 0) ?>">
                </label>
                <label>تخفیف همکار — قیمت متری همکار (تومان) — خالی بماند تا درصد تخفیف همکار از تنظیمات اعمال شود
                    <input type="number" name="partner_price_per_meter" id="pf-partner" min="0" step="any" value="<?= ($editProduct['partner_price_per_meter'] ?? null) !== null && ($editProduct['partner_price_per_meter'] ?? '') !== '' ? (int) $editProduct['partner_price_per_meter'] : '' ?>" placeholder="خالی = خودکار با درصد همکار">
                </label>
                <label>مدل قیمت‌گذاری
                    <select name="pricing_model" id="pf-model">
                        <option value="per_meter" <?= (($editProduct['pricing_model'] ?? 'per_meter') === 'per_meter') ? 'selected' : '' ?>>متری (چراغ خطی)</option>
                        <option value="per_watt" <?= (($editProduct['pricing_model'] ?? '') === 'per_watt') ? 'selected' : '' ?>>سفارشی بر اساس وات (چراغ رشد گیاه)</option>
                    </select>
                </label>
                <div id="pf-watt-box" style="<?= (($editProduct['pricing_model'] ?? 'per_meter') === 'per_watt') ? '' : 'display:none' ?>">
                    <div class="inline-fields">
                        <label>قیمت هر وات (تومان)
                            <input type="number" name="price_per_watt" min="0" step="any" value="<?= (int) ($editProduct['price_per_watt'] ?? 0) ?>">
                        </label>
                        <label>قیمت شروع (تومان) — در کاتالوگ «از X تومان» نمایش داده می‌شود
                            <input type="number" name="base_price" min="0" step="any" value="<?= (int) ($editProduct['base_price'] ?? 0) ?>">
                        </label>
                    </div>
                    <h4 style="margin:12px 0 6px">قاب‌ها (مدل + قیمت هر کدام)</h4>
                    <p class="muted">قیمت صفر یعنی «طبق سفارش».</p>
                    <div id="frame-rows">
                        <?php
                        $frameRows = [];
                        if (!empty($editProduct['frame_options_json'])) {
                            $decoded = json_decode((string) $editProduct['frame_options_json'], true);
                            if (is_array($decoded)) { $frameRows = $decoded; }
                        }
                        if ($frameRows === []) { $frameRows = [['name' => 'سقفی', 'price' => 0], ['name' => 'آویز', 'price' => 0], ['name' => 'ریلی', 'price' => 0]]; }
                        $frameRows[] = ['name' => '', 'price' => ''];
                        foreach ($frameRows as $fr):
                        ?>
                        <div class="frame-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:end">
                            <label style="flex:2">مدل قاب
                                <input type="text" name="frame_name[]" value="<?= e((string) ($fr['name'] ?? '')) ?>" placeholder="مثلاً سقفی">
                            </label>
                            <label style="flex:1">قیمت (تومان)
                                <input type="number" name="frame_price[]" min="0" step="any" value="<?= ($fr['price'] ?? '') !== '' ? (int) $fr['price'] : '' ?>" placeholder="0 = طبق سفارش">
                            </label>
                            <button type="button" class="btn small frame-remove">حذف</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p><button type="button" class="btn small" id="frame-add">+ افزودن قاب</button></p>
                </div>
                <script>
                // Fallback: اگر اسکریپت فوتر admin.php لود نشده باشد، دکمه منوی موبایل کار کند
                if (!window.__toggleAdminNav) {
                    window.__toggleAdminNav = function(){
                        var b = document.body;
                        var mq = window.matchMedia('(max-width:899px)');
                        if (mq.matches) {
                            var op = b.classList.toggle('nav-open');
                            var t = document.getElementById('navToggle');
                            if (t) { t.setAttribute('aria-expanded', op ? 'true' : 'false'); }
                        } else {
                            b.classList.toggle('nav-rail');
                        }
                    };
                }
                </script>
                <script>
                (function(){
                    var modelSel = document.getElementById('pf-model');
                    var wattBox = document.getElementById('pf-watt-box');
                    if (modelSel && wattBox) {
                        modelSel.addEventListener('change', function(){
                            wattBox.style.display = modelSel.value === 'per_watt' ? '' : 'none';
                        });
                    }
                    var fbox = document.getElementById('frame-rows');
                    var fadd = document.getElementById('frame-add');
                    function bindRm(btn){ btn.addEventListener('click', function(){ btn.closest('.frame-row').remove(); }); }
                    if (fbox) { fbox.querySelectorAll('.frame-remove').forEach(bindRm); }
                    if (fadd && fbox) {
                        fadd.addEventListener('click', function(){
                            var first = fbox.querySelector('.frame-row');
                            if (!first) return;
                            var clone = first.cloneNode(true);
                            clone.querySelectorAll('input').forEach(function(el){ el.value = ''; });
                            fbox.appendChild(clone);
                            bindRm(clone.querySelector('.frame-remove'));
                        });
                    }
                })();
                </script>
                <label>ترتیب نمایش
                    <input type="number" name="sort_order" value="<?= (int) ($editProduct['sort_order'] ?? 0) ?>">
                </label>
                <label>زمان آماده‌سازی این محصول (روز) — در سفارش، بیشترین زمان بین ردیف‌ها لحاظ می‌شود
                    <input type="number" name="prep_days" min="0" step="1" value="<?= (int) ($editProduct['prep_days'] ?? 0) ?>">
                </label>
                <label class="check">
                    <input type="checkbox" name="is_active" value="1" <?= ($editProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
                    فعال (نمایش در کاتالوگ)
                </label>

                <h3>سئو (SEO)</h3>
                <p class="muted">اگر خالی بماند، از نام و توضیح محصول استفاده می‌شود.</p>
                <label>عنوان سئو (Title)
                    <input type="text" name="seo_title" value="<?= e((string) ($editProduct['seo_title'] ?? '')) ?>" maxlength="70" placeholder="حداکثر ۷۰ کاراکتر">
                </label>
                <label>توضیح متا (Meta Description)
                    <textarea name="seo_description" rows="2" maxlength="160" placeholder="حداکثر ۱۶۰ کاراکتر"><?= e((string) ($editProduct['seo_description'] ?? '')) ?></textarea>
                </label>
                <label>کلمات کلیدی (با کاما جدا کنید)
                    <input type="text" name="seo_keywords" value="<?= e((string) ($editProduct['seo_keywords'] ?? '')) ?>" placeholder="چراغ خطی, نورپردازی کمد, ...">
                </label>

                <h3>ویژگی‌ها و آپشن‌های این محصول</h3>
                <p class="muted">برای هر ویژگی انتخابی، گزینه پیش‌فرض محصول را انتخاب کنید تا در صفحه محصول و برآورد قیمت نمایش داده شود. «ارائه نمی‌شود» یعنی این محصول آن ویژگی را ندارد.</p>
                <?php foreach (get_attributes(false) as $attr): ?>
                    <?php $aid = (int) $attr['id']; $cur = $editProductValues[$aid] ?? null; ?>
                    <?php if ($attr['input_type'] === 'select'): ?>
                        <label><?= e($attr['title']) ?>
                            <select name="attr_<?= $aid ?>" class="pf-attr-select">
                                <option value="0">— ارائه نمی‌شود —</option>
                                <?php foreach (get_attribute_options($aid) as $opt): ?>
                                    <option value="<?= (int) $opt['id'] ?>" data-delta="<?= (float) $opt['price_delta_per_meter'] ?>" <?= $cur !== null && isset($cur['option_id']) && (int) $cur['option_id'] === (int) $opt['id'] ? 'selected' : '' ?>><?= e($opt['title']) ?><?= (float) $opt['price_delta_per_meter'] != 0.0 ? ' (' . ($opt['price_delta_per_meter'] > 0 ? '+' : '') . e(format_price($opt['price_delta_per_meter'])) . ' تومان/متر)' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php elseif ($attr['input_type'] === 'number'): ?>
                        <label><?= e($attr['title']) ?><?= !empty($attr['unit']) ? ' (' . e($attr['unit']) . ')' : '' ?>
                            <input type="number" step="any" name="attr_<?= $aid ?>" value="<?= $cur !== null && $cur['num_value'] !== null ? e((string) $cur['num_value']) : '' ?>">
                        </label>
                    <?php else: ?>
                        <label><?= e($attr['title']) ?>
                            <input type="text" name="attr_<?= $aid ?>" value="<?= $cur !== null ? e((string) ($cur['text_value'] ?? '')) : '' ?>">
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>

                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>پیش‌نمایش قیمت واحد (هر متر):</strong>
                    <div>قیمت مشتری: <strong id="pf-retail-preview">۰</strong> تومان</div>
                    <div>قیمت همکار: <strong id="pf-partner-preview">۰</strong> تومان</div>
                    <p class="muted">با تغییر قیمت یا آپشن‌ها، این پیش‌نمایش زنده به‌روز می‌شود. درصد تخفیف همکار فعلی: <?= e(format_price(partner_discount_percent())) ?>٪ (از تنظیمات).</p>
                </div>

                <h3>مواد مصرفی و بهای تمام‌شده (فرمول ساخت)</h3>
                <div class="card" style="background:#fffbeb;margin:10px 0">
                    <strong>هزینه تولید (دستمزد و سربار)</strong>
                    <p class="muted">علاوه بر مواد، هزینه ساخت هر متر و هر چراغ را وارد کنید تا در بهای تمام‌شده لحاظ شود.</p>
                    <div class="inline-fields">
                        <label>هزینه تولید هر متر (تومان)
                            <input type="number" name="labor_cost_per_meter" min="0" step="any" value="<?= (int) ($editProduct['labor_cost_per_meter'] ?? 0) ?>">
                        </label>
                        <label>هزینه تولید هر چراغ (تومان)
                            <input type="number" name="labor_cost_per_fixture" min="0" step="any" value="<?= (int) ($editProduct['labor_cost_per_fixture'] ?? 0) ?>">
                        </label>
                    </div>
                </div>
                <p class="muted">برای ساخت این محصول چه مواد خامی مصرف می‌شود؟ مصرف «به‌ازای هر متر» برای موادی است که با طول چراغ کم‌وزیاد می‌شوند (اعشاری هم می‌شود؛ مثلاً چسب حرارتی: یک بسته در ۵۰ متر = <span dir="ltr">0.02</span> بسته در متر) و مصرف «به‌ازای هر چراغ» برای موادی مثل درایور و درپوش که برای هر چراغ یک بار مصرف می‌شوند. بهای تمام‌شده فقط در پنل دیده می‌شود و هرگز در سایت عمومی نمایش داده نمی‌شود.</p>
                <div id="bom-rows">
                    <?php
                    $bomShowRows = $editProductBom;
                    $bomShowRows[] = ['material_id' => 0, 'qty' => '', 'basis' => 'per_meter', 'apply_condition' => 'always'];
                    foreach ($bomShowRows as $br):
                    ?>
                    <div class="bom-row" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-bottom:8px">
                        <label style="flex:2;min-width:160px">ماده اولیه
                            <select name="bom_material_id[]">
                                <option value="0">— انتخاب ماده —</option>
                                <?php foreach ($allMaterials as $mm): ?>
                                    <option value="<?= (int) $mm['id'] ?>" <?= (int) ($br['material_id'] ?? 0) === (int) $mm['id'] ? 'selected' : '' ?>><?= e($mm['name']) ?> (<?= e($mm['unit']) ?>)<?= (int) $mm['is_active'] !== 1 ? ' — غیرفعال' : '' ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label style="flex:1;min-width:100px">مقدار مصرف
                            <input type="number" name="bom_qty[]" step="any" min="0" value="<?= ($br['qty'] ?? '') !== '' ? e(format_qty((float) $br['qty'])) : '' ?>" placeholder="مثلاً 1 یا 0.02">
                        </label>
                        <label style="flex:1;min-width:130px">مبنای مصرف
                            <select name="bom_basis[]">
                                <option value="per_meter" <?= ($br['basis'] ?? 'per_meter') !== 'per_fixture' ? 'selected' : '' ?>>به‌ازای هر متر</option>
                                <option value="per_fixture" <?= ($br['basis'] ?? '') === 'per_fixture' ? 'selected' : '' ?>>به‌ازای هر چراغ</option>
                            </select>
                        </label>
                        <label style="flex:1;min-width:130px">شرط مصرف
                            <select name="bom_cond[]">
                                <option value="always" <?= ($br['apply_condition'] ?? 'always') !== 'endcap' ? 'selected' : '' ?>>همیشه</option>
                                <option value="endcap" <?= ($br['apply_condition'] ?? '') === 'endcap' ? 'selected' : '' ?>>فقط وقتی درپوش دارد</option>
                            </select>
                        </label>
                        <button type="button" class="btn small bom-remove">حذف ردیف</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p><button type="button" class="btn small" id="bom-add">+ افزودن ماده دیگر</button></p>
                <?php if ($allMaterials === []): ?>
                <p class="muted">هنوز ماده اولیه‌ای تعریف نشده است؛ اول از منوی «انبار ← مواد اولیه» مواد را بسازید.</p>
                <?php endif; ?>
                <script>
                (function(){
                    var box = document.getElementById('bom-rows');
                    var addBtn = document.getElementById('bom-add');
                    if(!box || !addBtn) return;
                    function bindRemove(btn){
                        btn.addEventListener('click', function(){ btn.closest('.bom-row').remove(); });
                    }
                    box.querySelectorAll('.bom-remove').forEach(bindRemove);
                    addBtn.addEventListener('click', function(){
                        var first = box.querySelector('.bom-row');
                        if(!first) return;
                        var clone = first.cloneNode(true);
                        clone.querySelectorAll('select,input').forEach(function(el){
                            if(el.tagName === 'SELECT'){ el.selectedIndex = 0; } else { el.value = ''; }
                        });
                        box.appendChild(clone);
                        bindRemove(clone.querySelector('.bom-remove'));
                    });
                })();
                </script>

                <?php if ($editProduct !== null):
                    $pcost = product_material_cost((int) $editProduct['id']);
                    $pmargin = product_margin((int) $editProduct['id']);
                ?>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>بهای تمام‌شده (مواد با آخرین قیمت خرید + هزینه تولید):</strong>
                    <div>بهای تمام‌شده هر متر: <strong><?= e(format_price($pcost['per_meter'])) ?></strong> تومان</div>
                    <div>بهای تمام‌شده ثابت هر چراغ: <strong><?= e(format_price($pcost['per_fixture'])) ?></strong> تومان <span class="muted">(به‌ازای هر چراغ/سفارش جدا از متری)</span></div>
                    <div>حاشیه سود متری مشتری: <strong><?= e(format_price($pmargin['retail_margin'])) ?></strong> تومان (<?= e(format_price($pmargin['retail_margin_percent'])) ?>٪ از قیمت فروش)</div>
                    <div>حاشیه سود متری همکار: <strong><?= e(format_price($pmargin['partner_margin'])) ?></strong> تومان (<?= e(format_price($pmargin['partner_margin_percent'])) ?>٪ از قیمت همکار)</div>
                    <?php if ($pmargin['partner_margin'] < 0): ?>
                    <div class="alert error" style="margin-top:8px">هشدار: قیمت همکار این محصول از بهای مواد هر متر کمتر است؛ یعنی فروش به همکار با این قیمت، فقط از نظر مواد هم زیان‌ده است.</div>
                    <?php endif; ?>
                    <?php if ($pmargin['retail_margin'] < 0): ?>
                    <div class="alert error" style="margin-top:8px">هشدار: قیمت فروش این محصول از بهای مواد هر متر کمتر است.</div>
                    <?php endif; ?>
                </div>
                <p style="margin:10px 0"><button type="submit" class="btn <?= $editProduct !== null ? 'edit' : 'add' ?>" style="width:100%;padding:12px;font-size:16px"><?= $editProduct !== null ? '💾 ذخیره تغییرات' : 'ثبت محصول' ?></button></p>

                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>برآورد نیاز مواد برای یک سفارش</strong>
                    <p class="muted">طول و تعداد چراغ را بدهید تا ببینید برای ساخت، چقدر از هر ماده لازم است و کجا کمبود دارید.</p>
                    <div class="inline-fields">
                        <label>طول کل (متر)
                            <input type="number" id="est-len" step="any" min="0" value="<?= $estLength !== null ? e(format_qty($estLength)) : '10' ?>">
                        </label>
                        <label>تعداد چراغ
                            <input type="number" id="est-fix" step="1" min="0" value="<?= (int) $estFixtures ?>">
                        </label>
                        <button type="button" class="btn small" id="est-go">محاسبه نیاز مواد</button>
                    </div>
                    <script>
                    document.getElementById('est-go').addEventListener('click', function(){
                        var l = document.getElementById('est-len').value || '0';
                        var f = document.getElementById('est-fix').value || '1';
                        window.location.href = 'admin.php?page=products&edit_id=<?= (int) $editProduct['id'] ?>&est_length=' + encodeURIComponent(l) + '&est_fixtures=' + encodeURIComponent(f);
                    });
                    </script>
                    <?php if ($estLength !== null): ?>
                    <?php if ($estRows === []): ?>
                    <p class="muted">برای این محصول هنوز ماده‌ای در فرمول ساخت تعریف نشده است.</p>
                    <?php else: ?>
                    <table>
                        <thead><tr><th>ماده</th><th>نیاز</th><th>موجودی انبار</th><th>وضعیت</th></tr></thead>
                        <tbody>
                        <?php foreach ($estRows as $er): ?>
                            <tr>
                                <td><?= e($er['name']) ?></td>
                                <td><?= e(format_qty((float) $er['needed'])) ?> <?= e($er['unit']) ?></td>
                                <td><?= e(format_qty((float) $er['stock'])) ?> <?= e($er['unit']) ?></td>
                                <td><?= $er['shortage'] > 0 ? '<span class="badge" style="background:#fee2e2;color:#b91c1c">کمبود: ' . e(format_qty((float) $er['shortage'])) . ' ' . e($er['unit']) . '</span>' : '<span class="badge ok">کافی</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <h3>تنظیمات فرم سفارش</h3>
                <p class="muted">مشخص کنید در فرم ثبت سفارش این محصول کدام فیلدها نمایش داده شوند. مثلاً برای چراغ رشد گیاه، طول و سیم لازم نیست.</p>
                <?php $ofc = $editProduct !== null ? product_order_form_config($editProduct) : ['show_length' => true, 'show_qty' => true, 'show_wire' => true, 'show_endcap' => true, 'show_options' => true]; ?>
                <div class="inline-fields">
                    <label class="check"><input type="checkbox" name="ofc_show_length" value="1"<?= $ofc['show_length'] ? ' checked' : '' ?>> طول چراغ</label>
                    <label class="check"><input type="checkbox" name="ofc_show_qty" value="1"<?= $ofc['show_qty'] ? ' checked' : '' ?>> تعداد</label>
                    <label class="check"><input type="checkbox" name="ofc_show_wire" value="1"<?= $ofc['show_wire'] ? ' checked' : '' ?>> طول سیم</label>
                    <label class="check"><input type="checkbox" name="ofc_show_endcap" value="1"<?= $ofc['show_endcap'] ? ' checked' : '' ?>> درپوش</label>
                    <label class="check"><input type="checkbox" name="ofc_show_options" value="1"<?= $ofc['show_options'] ? ' checked' : '' ?>> آپشن‌ها (ویژگی‌ها)</label>
                </div>
                <?php if ($editProduct !== null):
                    $formFields = $pdo->query("SELECT * FROM order_form_fields WHERE owner_type = 'product' AND owner_id = " . (int) $editProduct['id'] . " ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>فیلدهای سفارشی فرم سفارش</strong>
                    <p class="muted">فیلدهای اضافه‌ای که فقط برای این محصول در فرم سفارش نمایش داده می‌شوند (مثلاً مساحت، نوع نصب).</p>
                    <table>
                        <thead><tr><th>برچسب</th><th>نوع</th><th>گزینه‌ها</th><th>الزامی</th><th>ترتیب</th><th>عملیات</th></tr></thead>
                        <tbody>
                        <?php foreach ($formFields as $ff): ?>
                            <tr>
                                <td><?= e($ff['label']) ?></td>
                                <td><?= e(['text' => 'متن', 'number' => 'عدد', 'select' => 'انتخابی', 'textarea' => 'متن بلند', 'checkbox' => 'تیک'][($ff['field_type'] ?? 'text')] ?? $ff['field_type']) ?></td>
                                <td><?= e($ff['options_json'] ?? '') ?></td>
                                <td><?= (int) $ff['is_required'] === 1 ? 'بله' : 'خیر' ?></td>
                                <td><?= (int) $ff['sort_order'] ?></td>
                                <td>
                                    <form method="post" class="inline" onsubmit="return confirm('این فیلد حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_order_field"><input type="hidden" name="id" value="<?= (int) $ff['id'] ?>"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($formFields === []): ?><tr><td colspan="6" class="muted">فیلد سفارشی تعریف نشده است.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <div class="inline-fields" style="margin-top:10px">
                        <label>برچسب فیلد <input type="text" name="new_field_label" maxlength="100"></label>
                        <label>نوع
                            <select name="new_field_type">
                                <option value="text">متن</option>
                                <option value="number">عدد</option>
                                <option value="select">انتخابی</option>
                                <option value="textarea">متن بلند</option>
                                <option value="checkbox">تیک</option>
                            </select>
                        </label>
                        <label>گزینه‌ها (با | جدا کنید، برای نوع انتخابی)
                            <input type="text" name="new_field_options" dir="rtl" placeholder="مثلاً: آویز | سقفی | دیواری">
                        </label>
                        <label class="check"><input type="checkbox" name="new_field_required" value="1"> الزامی</label>
                        <button type="submit" name="add_order_field" value="1" class="btn small add">+ افزودن فیلد</button>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($editProduct !== null):
                    $galleryImages = get_product_images((int) $editProduct['id']);
                ?>
                <h3>گالری تصاویر محصول</h3>
                <p class="muted">علاوه بر عکس اصلی، می‌توانید چند عکس دیگر برای گالری این محصول اضافه کنید.</p>
                <div class="pg-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin:10px 0">
                    <?php foreach ($galleryImages as $gi): ?>
                    <div class="pg-item" style="position:relative;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden">
                        <img src="<?= e(UPLOADS_URL . '/' . basename((string) $gi['image'])) ?>" alt="" style="width:100%;aspect-ratio:1/1;object-fit:cover;display:block">
                        <?php if (!empty($gi['caption'])): ?><div style="padding:4px 8px;font-size:11px"><?= e($gi['caption']) ?></div><?php endif; ?>
                        <div style="display:flex;gap:4px;padding:4px">
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="dir" value="-1"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small" title="قبلی">→</button></form>
                            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="move_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="dir" value="1"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small" title="بعدی">←</button></form>
                            <form method="post" class="inline" onsubmit="return confirm('این عکس حذف شود؟')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_product_image"><input type="hidden" name="id" value="<?= (int) $gi['id'] ?>"><input type="hidden" name="product_id" value="<?= (int) $editProduct['id'] ?>"><button type="submit" class="btn small danger-btn">حذف</button></form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($galleryImages === []): ?><p class="muted">هنوز عکسی در گالری نیست.</p><?php endif; ?>
                </div>
                <div class="card" style="background:#f9fafb;margin:10px 0">
                    <strong>افزودن عکس به گالری</strong>
                    <div class="inline-fields" style="margin-top:8px">
                        <label>فایل عکس <input type="file" name="gallery_image" accept="image/*"></label>
                        <label>توضیح (اختیاری) <input type="text" name="gallery_caption" maxlength="150"></label>
                        <button type="submit" name="add_gallery_image" value="1" class="btn small add">+ افزودن به گالری</button>
                    </div>
                </div>
                <?php endif; ?>
                <button type="submit" class="btn <?= $editProduct !== null ? 'edit' : 'add' ?>"><?= $editProduct !== null ? 'ذخیره تغییرات' : 'ثبت محصول' ?></button>
                <?php if ($editProduct !== null): ?><a class="btn" href="admin.php?page=products">انصراف</a><?php endif; ?>
            </form>
            </div>
            <script>
            (function(){
                var discount = <?= json_encode(partner_discount_percent()) ?>;
                var retail = document.getElementById('pf-retail');
                var partner = document.getElementById('pf-partner');
                var rp = document.getElementById('pf-retail-preview');
                var pp = document.getElementById('pf-partner-preview');
                if(!retail || !rp) return;
                function fmt(n){ return Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
                function currentDelta(){
                    var d = 0;
                    document.querySelectorAll('#product-form .pf-attr-select').forEach(function(s){
                        var o = s.options[s.selectedIndex];
                        if(o){ d += parseFloat(o.getAttribute('data-delta') || '0'); }
                    });
                    return d;
                }
                function calc(){
                    var base = parseFloat(retail.value) || 0;
                    var d = currentDelta();
                    var pbase = base;
                    if(partner && partner.value.trim() !== '' && parseFloat(partner.value) > 0){
                        pbase = parseFloat(partner.value);
                    } else {
                        pbase = base * (1 - discount / 100);
                    }
                    rp.textContent = fmt(base + d);
                    pp.textContent = fmt(pbase + d);
                }
                ['input','change'].forEach(function(ev){
                    document.getElementById('product-form').addEventListener(ev, calc);
                });
                calc();
            })();
            </script>

            <h2>فهرست محصولات (<?= count($productsList) ?>)</h2>
            <?php if ($productsList === []): ?>
                <div class="card wide"><p class="muted">هنوز محصولی ثبت نشده است. اولین محصول را با دکمه «+ افزودن محصول» بسازید تا در کاتالوگ عمومی سایت نمایش داده شود.</p></div>
            <?php else: ?>
            <table>
                <thead><tr><th>نام محصول</th><th>دسته</th><th>قیمت متری (مشتری / همکار)</th><th>بهای مواد (هر متر)</th><th>ترتیب</th><th>وضعیت</th><th>عملیات</th></tr></thead>
                <tbody>
                <?php foreach ($productsList as $p): ?>
                    <tr>
                        <td><?= e($p['name']) ?><?php if (!empty($p['sku'])): ?> <span class="muted" dir="ltr">(<?= e($p['sku']) ?>)</span><?php endif; ?></td>
                        <td><?= e($p['category_title'] ?? '—') ?></td>
                        <td>
                            <form method="post" class="inline quick-price">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="quick_price_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="number" name="price_per_meter" min="0" step="any" value="<?= (int) $p['price_per_meter'] ?>" style="width:110px" title="قیمت متری مشتری (تومان)">
                                <input type="number" name="partner_price_per_meter" min="0" step="any" value="<?= ($p['partner_price_per_meter'] ?? null) !== null && ($p['partner_price_per_meter'] ?? '') !== '' ? (int) $p['partner_price_per_meter'] : '' ?>" placeholder="خودکار" style="width:90px" title="تخفیف همکار — خالی = خودکار">
                                <button type="submit" class="btn small edit" title="ذخیره قیمت">💾</button>
                            </form>
                            <?php if (($p['partner_price_per_meter'] ?? null) === null || ($p['partner_price_per_meter'] ?? '') === '' || (float) ($p['partner_price_per_meter'] ?? 0) <= 0): ?><div class="muted" style="font-size:11px">خودکار: <?= e(format_price(product_base_price_per_meter($p, true))) ?></div><?php endif; ?>
                        </td>
                        <td><?php $pmc = product_material_cost((int) $p['id']); ?><?= $pmc['lines'] !== [] ? e(format_price($pmc['per_meter'])) . ' تومان' : '<span class="muted">فرمول ندارد</span>' ?></td>
                        <td>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button type="submit" class="btn small">↑ بالا</button>
                            </form>
                            <form method="post" class="inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button type="submit" class="btn small">↓ پایین</button>
                            </form>
                            <?= (int) $p['sort_order'] ?>
                        </td>
                        <td><?= $p['is_active'] ? '<span class="badge ok">فعال</span>' : '<span class="badge off">غیرفعال</span>' ?></td>
                        <td class="actions">
                            <a class="btn small" href="/products?id=<?= (int) $p['id'] ?>" target="_blank">مشاهده</a>
                            <a class="btn small edit" href="admin.php?page=products&edit_id=<?= (int) $p['id'] ?>">ویرایش</a>
                            <form method="post" class="inline" onsubmit="return confirm('این محصول حذف شود؟')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                <button type="submit" class="btn small danger-btn">حذف</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

    <?php
}

/** رندر صفحه «ویژگی‌های محصول» */