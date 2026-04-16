<?php
/**
 * صفحة السلة وإتمام الطلب
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = BASE_URL . 'checkout.php';
    redirect(BASE_URL . 'login.php');
}

$page_title = 'إتمام الطلب - ' . APP_NAME;
$user_id = $_SESSION['user_id'];

// جلب بيانات السلة
$cart = db_fetch(
    "SELECT c.*, v.business_name, v.id as vendor_id, v.min_order, v.delivery_time,
            v.zone_id, v.business_type
     FROM carts c
     JOIN vendors v ON c.vendor_id = v.id
     WHERE c.user_id = ?",
    [$user_id]
);

if (!$cart) {
    redirect(BASE_URL . 'index.php');
}

// جلب عناصر السلة
$cart_items = db_fetch_all(
    "SELECT ci.*, p.name_ar, p.image, p.price as original_price
     FROM cart_items ci
     JOIN products p ON ci.product_id = p.id
     WHERE ci.cart_id = ?",
    [$cart['id']]
);

if (empty($cart_items)) {
    // حذف السلة الفارغة
    db_delete('carts', 'id = ?', [$cart['id']]);
    redirect(BASE_URL . 'index.php');
}

// حساب المجموع
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['unit_price'] * $item['quantity'];
}

// جلب رسوم التوصيل
$delivery_fee_row = db_fetch(
    "SELECT fee, min_order_for_free FROM delivery_fees WHERE zone_id = ?",
    [$cart['zone_id']]
);
$delivery_fee = $delivery_fee_row['fee'] ?? DELIVERY_FEE_DEFAULT;
$free_delivery_threshold = $delivery_fee_row['min_order_for_free'] ?? null;

// تطبيق التوصيل المجاني إذا تجاوز الحد الأدنى
if ($free_delivery_threshold && $subtotal >= $free_delivery_threshold) {
    $delivery_fee = 0;
}

// رسوم الخدمة
$service_fee = SERVICE_FEE;

// جلب العروض النشطة للمتجر
$active_offer = db_fetch(
    "SELECT * FROM offers 
     WHERE (vendor_id = ? OR vendor_id IS NULL) 
     AND status = 1 
     AND start_date <= NOW() 
     AND end_date >= NOW()
     AND min_order <= ?
     ORDER BY discount_value DESC
     LIMIT 1",
    [$cart['vendor_id'], $subtotal]
);

$discount_amount = 0;
if ($active_offer) {
    if ($active_offer['offer_type'] == 'percentage' || $active_offer['discount_type'] == 'percentage') {
        $discount_amount = $subtotal * ($active_offer['discount_value'] / 100);
        if ($active_offer['max_discount']) {
            $discount_amount = min($discount_amount, $active_offer['max_discount']);
        }
    } else {
        $discount_amount = $active_offer['discount_value'];
    }
}

// حساب الإجمالي
$total = $subtotal + $delivery_fee + $service_fee - $discount_amount;

// جلب عناوين المستخدم
$addresses = db_fetch_all(
    "SELECT a.*, ar.name_ar as area_name, z.name_ar as zone_name, z.city
     FROM addresses a
     LEFT JOIN areas ar ON a.area_id = ar.id
     LEFT JOIN zones z ON ar.zone_id = z.id
     WHERE a.user_id = ?
     ORDER BY a.is_default DESC, a.created_at DESC",
    [$user_id]
);

// جلب وسائل الدفع المتاحة
$payment_methods = [
    ['id' => 'cash', 'name' => 'الدفع عند الاستلام', 'icon' => 'fa-money-bill-wave'],
    ['id' => 'card', 'name' => 'بطاقة ائتمان', 'icon' => 'fa-credit-card'],
    ['id' => 'wallet', 'name' => 'المحفظة', 'icon' => 'fa-wallet']
];

// جلب إعدادات البقشيش
$tip_options = [5, 10, 15, 20];

include INCLUDES_PATH . '/header.php';
?>

<!-- Checkout Header with Steps -->
<div class="checkout-header">
    <div class="checkout-container">
        <h1 class="checkout-title"><i class="fas fa-shopping-cart"></i> إتمام الطلب</h1>
        
        <div class="checkout-steps">
            <div class="step active" data-step="1">
                <div class="step-number">1</div>
                <span class="step-title">السلة</span>
            </div>
            <div class="step-line"></div>
            <div class="step" data-step="2">
                <div class="step-number">2</div>
                <span class="step-title">العنوان</span>
            </div>
            <div class="step-line"></div>
            <div class="step" data-step="3">
                <div class="step-number">3</div>
                <span class="step-title">الدفع</span>
            </div>
        </div>
    </div>
</div>

<!-- Checkout Content -->
<div class="checkout-container">
    <div class="checkout-grid">
        <!-- العمود الأيسر: خطوات إتمام الطلب -->
        <div class="checkout-main">
            <form id="checkoutForm" method="POST">
                <?= csrf_field() ?>
                
                <!-- Step 1: Cart Review -->
                <div class="checkout-step-content active" id="step1">
                    <div class="step-card">
                        <div class="step-header">
                            <h2><i class="fas fa-basket-shopping"></i> مراجعة الطلب</h2>
                            <span class="vendor-name"><?= escape($cart['business_name']) ?></span>
                        </div>
                        
                        <div class="cart-items-list">
                            <?php foreach ($cart_items as $item): ?>
                            <div class="checkout-cart-item">
                                <div class="item-image">
                                    <img src="<?= asset_url($item['image'], 'assets/images/default-product.jpg') ?>" 
                                         alt="<?= escape($item['name_ar']) ?>"
                                         onerror="this.style.display='none'">
                                    <?php if (!$item['image']): ?>
                                    <i class="fas fa-box"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="item-details">
                                    <div class="item-header">
                                        <h4><?= escape($item['name_ar']) ?></h4>
                                        <span class="item-quantity">× <?= $item['quantity'] ?></span>
                                    </div>
                                    <?php if ($item['notes']): ?>
                                    <p class="item-notes"><i class="fas fa-pencil"></i> <?= escape($item['notes']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($item['options']): ?>
                                    <p class="item-options">
                                        <i class="fas fa-list"></i>
                                        <?php
                                        $options = json_decode($item['options'], true);
                                        if ($options) {
                                            echo escape(implode('، ', array_column($options, 'name')));
                                        }
                                        ?>
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="item-price">
                                    <?= format_price($item['unit_price'] * $item['quantity']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cart-actions">
                            <a href="<?= BASE_URL ?>store.php?id=<?= $cart['vendor_id'] ?>" class="btn btn-outline">
                                <i class="fas fa-plus"></i> أضف المزيد
                            </a>
                            <button type="button" class="btn btn-primary next-step" data-next="2">
                                متابعة <i class="fas fa-arrow-left"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Address -->
                <div class="checkout-step-content" id="step2">
                    <div class="step-card">
                        <div class="step-header">
                            <h2><i class="fas fa-map-pin"></i> عنوان التوصيل</h2>
                        </div>
                        
                        <div class="addresses-list">
                            <?php if (!empty($addresses)): ?>
                                <?php foreach ($addresses as $index => $address): ?>
                                <label class="address-card <?= $address['is_default'] ? 'default' : '' ?>">
                                    <input type="radio" name="address_id" value="<?= $address['id'] ?>" 
                                           <?= $address['is_default'] ? 'checked' : '' ?>>
                                    <span class="address-radio"></span>
                                    <div class="address-content">
                                        <div class="address-header">
                                            <span class="address-title">
                                                <i class="fas fa-home"></i>
                                                <?= escape($address['title']) ?>
                                            </span>
                                            <?php if ($address['is_default']): ?>
                                            <span class="default-badge"><i class="fas fa-check"></i> افتراضي</span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="address-text"><?= escape($address['address']) ?></p>
                                        <div class="address-meta">
                                            <?php if ($address['building']): ?>
                                            <span><i class="fas fa-building"></i> مبنى <?= escape($address['building']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($address['floor']): ?>
                                            <span><i class="fas fa-layer-group"></i> دور <?= escape($address['floor']) ?></span>
                                            <?php endif; ?>
                                            <?php if ($address['apartment']): ?>
                                            <span><i class="fas fa-door-open"></i> شقة <?= escape($address['apartment']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($address['area_name']): ?>
                                        <p class="address-area">
                                            <i class="fas fa-location-dot"></i>
                                            <?= escape($address['area_name']) ?>، <?= escape($address['zone_name']) ?>، <?= escape($address['city']) ?>
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($address['landmark']): ?>
                                        <p class="address-landmark">
                                            <i class="fas fa-landmark"></i>
                                            <?= escape($address['landmark']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="address-actions">
                                        <button type="button" class="edit-address-btn" data-id="<?= $address['id'] ?>">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-addresses">
                                    <i class="fas fa-map-pin"></i>
                                    <p>لا توجد عناوين محفوظة</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <button type="button" class="btn btn-outline btn-block add-address-btn" id="showAddressForm">
                            <i class="fas fa-plus-circle"></i> إضافة عنوان جديد
                        </button>
                        
                        <!-- نموذج إضافة عنوان جديد -->
                        <div class="address-form-container" id="addressForm" style="display: none;">
                            <h3>إضافة عنوان جديد</h3>
                            <div class="address-form">
                                <div class="form-group">
                                    <label>اسم العنوان</label>
                                    <input type="text" name="new_address_title" class="form-control" 
                                           placeholder="مثال: المنزل، العمل" value="المنزل">
                                </div>
                                <div class="form-group">
                                    <label>العنوان التفصيلي</label>
                                    <textarea name="new_address" class="form-control" rows="2" 
                                              placeholder="اكتب عنوانك بالتفصيل"></textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>رقم المبنى</label>
                                        <input type="text" name="new_building" class="form-control" placeholder="اختياري">
                                    </div>
                                    <div class="form-group">
                                        <label>الدور</label>
                                        <input type="text" name="new_floor" class="form-control" placeholder="اختياري">
                                    </div>
                                    <div class="form-group">
                                        <label>رقم الشقة</label>
                                        <input type="text" name="new_apartment" class="form-control" placeholder="اختياري">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>أقرب معلم</label>
                                    <input type="text" name="new_landmark" class="form-control" 
                                           placeholder="مثال: بجوار المسجد، خلف المول">
                                </div>
                                <div class="form-group">
                                    <label>المنطقة</label>
                                    <select name="new_area_id" class="form-control" id="areaSelect">
                                        <option value="">اختر المنطقة</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>تحديد الموقع على الخريطة</label>
                                    <div id="locationMap" style="height: 200px; border-radius: var(--radius-md);"></div>
                                    <input type="hidden" name="new_latitude" id="latitude">
                                    <input type="hidden" name="new_longitude" id="longitude">
                                </div>
                                <div class="form-check">
                                    <input type="checkbox" name="new_is_default" id="newIsDefault" value="1">
                                    <label for="newIsDefault">تعيين كعنوان افتراضي</label>
                                </div>
                                <div class="form-actions">
                                    <button type="button" class="btn btn-primary save-address-btn">
                                        <i class="fas fa-save"></i> حفظ العنوان
                                    </button>
                                    <button type="button" class="btn btn-outline cancel-address-btn">
                                        إلغاء
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="delivery-notes">
                            <label><i class="fas fa-pencil"></i> ملاحظات التوصيل (اختياري)</label>
                            <textarea name="delivery_notes" class="form-control" rows="2" 
                                      placeholder="مثال: يرجى الاتصال قبل الوصول، الباب الجانبي..."></textarea>
                        </div>
                        
                        <div class="step-actions">
                            <button type="button" class="btn btn-outline prev-step" data-prev="1">
                                <i class="fas fa-arrow-right"></i> السابق
                            </button>
                            <button type="button" class="btn btn-primary next-step" data-next="3">
                                متابعة <i class="fas fa-arrow-left"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Payment -->
                <div class="checkout-step-content" id="step3">
                    <div class="step-card">
                        <div class="step-header">
                            <h2><i class="fas fa-credit-card"></i> طريقة الدفع</h2>
                        </div>
                        
                        <div class="payment-methods">
                            <?php foreach ($payment_methods as $method): ?>
                            <label class="payment-method-card">
                                <input type="radio" name="payment_method" value="<?= $method['id'] ?>" 
                                       <?= $method['id'] === 'cash' ? 'checked' : '' ?>>
                                <span class="payment-radio"></span>
                                <i class="fas <?= $method['icon'] ?>"></i>
                                <span><?= $method['name'] ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="tip-section">
                            <h3><i class="fas fa-hand-holding-heart"></i> إكرامية المندوب (اختياري)</h3>
                            <div class="tip-options">
                                <?php foreach ($tip_options as $tip): ?>
                                <label class="tip-option">
                                    <input type="radio" name="tip_amount" value="<?= $tip ?>">
                                    <span><?= format_price($tip) ?></span>
                                </label>
                                <?php endforeach; ?>
                                <label class="tip-option custom-tip">
                                    <input type="radio" name="tip_amount" value="custom">
                                    <span>مخصص</span>
                                </label>
                            </div>
                            <div class="custom-tip-input" style="display: none;">
                                <input type="number" name="custom_tip_amount" class="form-control" 
                                       placeholder="أدخل المبلغ" min="0" step="1">
                            </div>
                            <label class="no-tip">
                                <input type="radio" name="tip_amount" value="0" checked>
                                <span>بدون إكرامية</span>
                            </label>
                        </div>
                        
                        <div class="step-actions">
                            <button type="button" class="btn btn-outline prev-step" data-prev="2">
                                <i class="fas fa-arrow-right"></i> السابق
                            </button>
                            <button type="submit" class="btn btn-success btn-lg" id="placeOrderBtn">
                                <i class="fas fa-check-circle"></i> تأكيد الطلب
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- العمود الأيمن: ملخص الطلب -->
        <div class="checkout-sidebar">
            <div class="order-summary-card">
                <h3><i class="fas fa-receipt"></i> ملخص الطلب</h3>
                
                <div class="summary-vendor">
                    <i class="fas fa-store"></i>
                    <span><?= escape($cart['business_name']) ?></span>
                </div>
                
                <div class="summary-items">
                    <?php foreach (array_slice($cart_items, 0, 3) as $item): ?>
                    <div class="summary-item">
                        <span><?= $item['quantity'] ?>× <?= escape($item['name_ar']) ?></span>
                        <span><?= format_price($item['unit_price'] * $item['quantity']) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($cart_items) > 3): ?>
                    <div class="summary-item more-items">
                        <span>+ <?= count($cart_items) - 3 ?> منتجات أخرى</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="summary-calculations">
                    <div class="summary-row">
                        <span>المجموع الفرعي</span>
                        <span id="summarySubtotal"><?= format_price($subtotal) ?></span>
                    </div>
                    <div class="summary-row">
                        <span>رسوم التوصيل</span>
                        <span id="summaryDelivery">
                            <?php if ($delivery_fee == 0): ?>
                            <span class="free-delivery">مجاني</span>
                            <?php else: ?>
                            <?= format_price($delivery_fee) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span>رسوم الخدمة</span>
                        <span><?= format_price($service_fee) ?></span>
                    </div>
                    <?php if ($discount_amount > 0): ?>
                    <div class="summary-row discount-row">
                        <span>
                            <i class="fas fa-tag"></i>
                            خصم <?= $active_offer['code'] ? '(' . escape($active_offer['code']) . ')' : '' ?>
                        </span>
                        <span class="discount-amount">- <?= format_price($discount_amount) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="summary-total">
                    <span>الإجمالي</span>
                    <span id="summaryTotal"><?= format_price($total) ?></span>
                </div>
                
                <?php if ($cart['min_order'] > $subtotal): ?>
                <div class="min-order-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>الحد الأدنى للطلب: <?= format_price($cart['min_order']) ?></span>
                    <p>أضف <?= format_price($cart['min_order'] - $subtotal) ?> أخرى لإكمال الطلب</p>
                </div>
                <?php endif; ?>
                
                <?php if ($active_offer): ?>
                <div class="applied-offer">
                    <i class="fas fa-check-circle"></i>
                    <span>تم تطبيق العرض: <?= escape($active_offer['title_ar']) ?></span>
                </div>
                <?php endif; ?>
                
                <div class="promo-code-section">
                    <label><i class="fas fa-ticket"></i> كود خصم</label>
                    <div class="promo-input-group">
                        <input type="text" class="form-control" id="promoCode" placeholder="أدخل الكود">
                        <button type="button" class="btn btn-outline" id="applyPromoBtn">تطبيق</button>
                    </div>
                </div>
            </div>
            
            <div class="delivery-info-card">
                <h4><i class="fas fa-clock"></i> وقت التوصيل المتوقع</h4>
                <p class="delivery-estimate"><?= escape($cart['delivery_time'] ?: '30-45 دقيقة') ?></p>
                <p class="delivery-note">
                    <i class="fas fa-info-circle"></i>
                    سيتم إشعارك بحالة الطلب عبر الإشعارات
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places&language=ar&region=SA"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // إدارة الخطوات
    const steps = document.querySelectorAll('.checkout-step-content');
    const stepIndicators = document.querySelectorAll('.checkout-steps .step');
    let currentStep = 1;
    
    function showStep(step) {
        steps.forEach((s, i) => {
            s.classList.toggle('active', i + 1 === step);
        });
        
        stepIndicators.forEach((indicator, i) => {
            indicator.classList.toggle('active', i + 1 === step);
            indicator.classList.toggle('completed', i + 1 < step);
        });
        
        currentStep = step;
        
        // تمرير للأعلى
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    // أزرار التنقل
    document.querySelectorAll('.next-step').forEach(btn => {
        btn.addEventListener('click', function() {
            const nextStep = parseInt(this.dataset.next);
            
            // التحقق من صحة البيانات في الخطوة الحالية
            if (currentStep === 1) {
                // التحقق من السلة
                if (<?= $subtotal ?> < <?= $cart['min_order'] ?>) {
                    showToast('الرجاء إضافة منتجات أكثر للوصول للحد الأدنى', 'error');
                    return;
                }
            }
            
            if (currentStep === 2) {
                // التحقق من اختيار عنوان
                const selectedAddress = document.querySelector('input[name="address_id"]:checked');
                if (!selectedAddress) {
                    showToast('الرجاء اختيار عنوان التوصيل', 'error');
                    return;
                }
            }
            
            showStep(nextStep);
        });
    });
    
    document.querySelectorAll('.prev-step').forEach(btn => {
        btn.addEventListener('click', function() {
            const prevStep = parseInt(this.dataset.prev);
            showStep(prevStep);
        });
    });
    
    // إظهار/إخفاء نموذج العنوان
    document.getElementById('showAddressForm')?.addEventListener('click', function() {
        document.getElementById('addressForm').style.display = 'block';
        this.style.display = 'none';
        initMap();
    });
    
    document.querySelector('.cancel-address-btn')?.addEventListener('click', function() {
        document.getElementById('addressForm').style.display = 'none';
        document.getElementById('showAddressForm').style.display = 'block';
    });
    
    // البقشيش المخصص
    document.querySelectorAll('input[name="tip_amount"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const customInput = document.querySelector('.custom-tip-input');
            if (this.value === 'custom') {
                customInput.style.display = 'block';
            } else {
                customInput.style.display = 'none';
            }
        });
    });
    
    // حفظ عنوان جديد
    document.querySelector('.save-address-btn')?.addEventListener('click', function() {
        const formData = new FormData();
        formData.append('action', 'save_address');
        formData.append('title', document.querySelector('[name="new_address_title"]').value);
        formData.append('address', document.querySelector('[name="new_address"]').value);
        formData.append('building', document.querySelector('[name="new_building"]').value);
        formData.append('floor', document.querySelector('[name="new_floor"]').value);
        formData.append('apartment', document.querySelector('[name="new_apartment"]').value);
        formData.append('landmark', document.querySelector('[name="new_landmark"]').value);
        formData.append('area_id', document.querySelector('[name="new_area_id"]').value);
        formData.append('latitude', document.querySelector('[name="new_latitude"]').value);
        formData.append('longitude', document.querySelector('[name="new_longitude"]').value);
        formData.append('is_default', document.querySelector('[name="new_is_default"]').checked ? '1' : '0');
        formData.append('csrf_token', CSRF_TOKEN);
        
        fetch(BASE_URL + 'api/handler.php?action=save_address', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم حفظ العنوان بنجاح', 'success');
                location.reload();
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    });
    
    // تطبيق كود الخصم
    document.getElementById('applyPromoBtn')?.addEventListener('click', function() {
        const code = document.getElementById('promoCode').value;
        if (!code) {
            showToast('الرجاء إدخال كود الخصم', 'error');
            return;
        }
        
        fetch(BASE_URL + 'api/handler.php?action=apply_promo', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ code: code, vendor_id: <?= $cart['vendor_id'] ?> })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم تطبيق الخصم بنجاح', 'success');
                location.reload();
            } else {
                showToast(data.message || 'كود الخصم غير صالح', 'error');
            }
        });
    });
    
    // تقديم الطلب
    document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'place_order');
        formData.append('vendor_id', <?= $cart['vendor_id'] ?>);
        
        const submitBtn = document.getElementById('placeOrderBtn');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري إنشاء الطلب...';
        submitBtn.disabled = true;
        
        fetch(BASE_URL + 'api/handler.php?action=place_order', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم إنشاء الطلب بنجاح!', 'success');
                setTimeout(() => {
                    window.location.href = BASE_URL + 'account.php?tab=orders&order_id=' + data.data.order_id;
                }, 1500);
            } else {
                showToast(data.message || 'حدث خطأ أثناء إنشاء الطلب', 'error');
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }
        });
    });
    
    // تحميل المناطق
    loadAreas();
});

let map;
let marker;

function initMap() {
    const defaultLocation = { lat: 24.7136, lng: 46.6753 }; // الرياض
    
    map = new google.maps.Map(document.getElementById('locationMap'), {
        center: defaultLocation,
        zoom: 13,
        mapTypeControl: false,
        streetViewControl: false,
        fullscreenControl: false
    });
    
    marker = new google.maps.Marker({
        position: defaultLocation,
        map: map,
        draggable: true
    });
    
    // تحديث الإحداثيات عند تحريك العلامة
    marker.addListener('dragend', function() {
        const position = marker.getPosition();
        document.getElementById('latitude').value = position.lat();
        document.getElementById('longitude').value = position.lng();
    });
    
    // البحث عن المكان
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = 'ابحث عن موقعك...';
    input.className = 'map-search-input';
    document.getElementById('locationMap').parentNode.insertBefore(input, document.getElementById('locationMap'));
    
    const searchBox = new google.maps.places.SearchBox(input);
    
    map.addListener('bounds_changed', function() {
        searchBox.setBounds(map.getBounds());
    });
    
    searchBox.addListener('places_changed', function() {
        const places = searchBox.getPlaces();
        if (places.length === 0) return;
        
        const place = places[0];
        if (!place.geometry || !place.geometry.location) return;
        
        map.setCenter(place.geometry.location);
        map.setZoom(15);
        marker.setPosition(place.geometry.location);
        
        document.getElementById('latitude').value = place.geometry.location.lat();
        document.getElementById('longitude').value = place.geometry.location.lng();
    });
    
    // تحديد الموقع الحالي
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const pos = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                map.setCenter(pos);
                marker.setPosition(pos);
                document.getElementById('latitude').value = pos.lat;
                document.getElementById('longitude').value = pos.lng;
            },
            function() {
                console.log('تعذر تحديد الموقع');
            }
        );
    }
}

function loadAreas() {
    fetch(BASE_URL + 'api/handler.php?action=get_areas')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const select = document.getElementById('areaSelect');
                select.innerHTML = '<option value="">اختر المنطقة</option>';
                data.data.forEach(area => {
                    select.innerHTML += `<option value="${area.id}">${area.name_ar} - ${area.zone_name}</option>`;
                });
            }
        });
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 100);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}
</script>

<style>
    /* Checkout Layout */
    .checkout-header {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: var(--spacing-md) 0;
        position: sticky;
        top: 56px;
        z-index: var(--z-sticky);
    }
    
    .checkout-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 var(--spacing-md);
    }
    
    .checkout-title {
        font-size: 24px;
        margin-bottom: var(--spacing-md);
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }
    
    /* Steps */
    .checkout-steps {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--spacing-xs);
    }
    
    .step-number {
        width: 40px;
        height: 40px;
        background: var(--gray-200);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        color: var(--gray-600);
        transition: all var(--transition-base);
    }
    
    .step.active .step-number {
        background: var(--gradient-primary);
        color: var(--white);
        box-shadow: var(--shadow-primary);
    }
    
    .step.completed .step-number {
        background: var(--success);
        color: var(--white);
    }
    
    .step.completed .step-number::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
    }
    
    .step.completed .step-number span {
        display: none;
    }
    
    .step-title {
        font-size: 13px;
        font-weight: 500;
        color: var(--gray-600);
    }
    
    .step.active .step-title {
        color: var(--primary);
        font-weight: 700;
    }
    
    .step-line {
        width: 60px;
        height: 2px;
        background: var(--gray-300);
        margin: 0 var(--spacing-sm);
        margin-bottom: 20px;
    }
    
    .step.completed + .step-line {
        background: var(--success);
    }
    
    /* Checkout Grid */
    .checkout-grid {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: var(--spacing-xl);
        padding: var(--spacing-xl) 0;
    }
    
    /* Step Content */
    .checkout-step-content {
        display: none;
    }
    
    .checkout-step-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    .step-card {
        background: var(--white);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-card);
        padding: var(--spacing-xl);
    }
    
    .step-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-md);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .step-header h2 {
        margin: 0;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }
    
    .vendor-name {
        color: var(--primary);
        font-weight: 600;
    }
    
    /* Cart Items */
    .cart-items-list {
        margin-bottom: var(--spacing-lg);
    }
    
    .checkout-cart-item {
        display: flex;
        gap: var(--spacing-md);
        padding: var(--spacing-md) 0;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .checkout-cart-item:last-child {
        border-bottom: none;
    }
    
    .checkout-cart-item .item-image {
        width: 70px;
        height: 70px;
        background: var(--gray-100);
        border-radius: var(--radius-md);
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--gray-400);
        flex-shrink: 0;
    }
    
    .checkout-cart-item .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .checkout-cart-item .item-details {
        flex: 1;
    }
    
    .item-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 4px;
    }
    
    .item-header h4 {
        margin: 0;
        font-size: 16px;
    }
    
    .item-quantity {
        color: var(--gray-500);
        font-weight: 600;
    }
    
    .item-notes, .item-options {
        font-size: 13px;
        color: var(--gray-500);
        margin: 4px 0 0;
    }
    
    .item-price {
        font-weight: 700;
        color: var(--primary);
        flex-shrink: 0;
    }
    
    .cart-actions {
        display: flex;
        gap: var(--spacing-md);
        margin-top: var(--spacing-lg);
    }
    
    /* Address Cards */
    .addresses-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
        margin-bottom: var(--spacing-lg);
    }
    
    .address-card {
        display: flex;
        gap: var(--spacing-md);
        padding: var(--spacing-lg);
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: all var(--transition-base);
        position: relative;
    }
    
    .address-card:hover {
        border-color: var(--primary-light);
    }
    
    .address-card input[type="radio"] {
        display: none;
    }
    
    .address-card input[type="radio"]:checked + .address-radio {
        background: var(--primary);
        border-color: var(--primary);
    }
    
    .address-card input[type="radio"]:checked + .address-radio::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: var(--white);
        font-size: 12px;
    }
    
    .address-card input[type="radio"]:checked ~ .address-content {
        border-color: var(--primary);
    }
    
    .address-radio {
        width: 24px;
        height: 24px;
        border: 2px solid var(--gray-400);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 4px;
    }
    
    .address-content {
        flex: 1;
    }
    
    .address-header {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-xs);
    }
    
    .address-title {
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
    }
    
    .default-badge {
        padding: 2px 8px;
        background: var(--success-light);
        color: var(--success);
        font-size: 11px;
        font-weight: 600;
        border-radius: var(--radius-full);
    }
    
    .address-text {
        font-weight: 500;
        margin-bottom: var(--spacing-xs);
    }
    
    .address-meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-md);
        font-size: 13px;
        color: var(--gray-500);
        margin-bottom: var(--spacing-xs);
    }
    
    .address-area, .address-landmark {
        font-size: 13px;
        color: var(--gray-500);
        margin: 2px 0;
    }
    
    .address-actions {
        position: absolute;
        top: var(--spacing-md);
        left: var(--spacing-md);
    }
    
    .edit-address-btn {
        width: 32px;
        height: 32px;
        background: var(--white);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-full);
        color: var(--gray-600);
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .edit-address-btn:hover {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }
    
    /* Address Form */
    .address-form-container {
        background: var(--gray-50);
        border-radius: var(--radius-lg);
        padding: var(--spacing-lg);
        margin: var(--spacing-lg) 0;
    }
    
    .address-form-container h3 {
        margin-bottom: var(--spacing-md);
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-md);
    }
    
    .form-actions {
        display: flex;
        gap: var(--spacing-md);
        margin-top: var(--spacing-lg);
    }
    
    /* Delivery Notes */
    .delivery-notes {
        margin-top: var(--spacing-lg);
    }
    
    .delivery-notes label {
        display: block;
        margin-bottom: var(--spacing-sm);
        font-weight: 600;
    }
    
    .step-actions {
        display: flex;
        gap: var(--spacing-md);
        margin-top: var(--spacing-xl);
    }
    
    /* Payment Methods */
    .payment-methods {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
        margin-bottom: var(--spacing-xl);
    }
    
    .payment-method-card {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
        padding: var(--spacing-lg);
        background: var(--gray-50);
        border: 2px solid var(--gray-200);
        border-radius: var(--radius-lg);
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .payment-method-card:hover {
        border-color: var(--primary-light);
    }
    
    .payment-method-card input[type="radio"] {
        display: none;
    }
    
    .payment-method-card input[type="radio"]:checked + .payment-radio {
        background: var(--primary);
        border-color: var(--primary);
    }
    
    .payment-method-card input[type="radio"]:checked + .payment-radio::after {
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: var(--white);
        font-size: 12px;
    }
    
    .payment-radio {
        width: 24px;
        height: 24px;
        border: 2px solid var(--gray-400);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .payment-method-card i {
        font-size: 24px;
        color: var(--primary);
        width: 32px;
    }
    
    /* Tip Section */
    .tip-section {
        padding: var(--spacing-lg);
        background: var(--gray-50);
        border-radius: var(--radius-lg);
    }
    
    .tip-section h3 {
        margin-bottom: var(--spacing-md);
    }
    
    .tip-options {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-md);
    }
    
    .tip-option {
        padding: var(--spacing-sm) var(--spacing-lg);
        background: var(--white);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-full);
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .tip-option input {
        display: none;
    }
    
    .tip-option:has(input:checked) {
        background: var(--primary);
        color: var(--white);
        border-color: var(--primary);
    }
    
    .custom-tip-input {
        margin-bottom: var(--spacing-md);
    }
    
    .no-tip {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        cursor: pointer;
    }
    
    /* Sidebar */
    .order-summary-card {
        background: var(--white);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-card);
        padding: var(--spacing-xl);
        position: sticky;
        top: 140px;
    }
    
    .order-summary-card h3 {
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-md);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .summary-vendor {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm) 0;
        color: var(--primary);
        font-weight: 600;
    }
    
    .summary-items {
        margin: var(--spacing-md) 0;
    }
    
    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-xs) 0;
        font-size: 14px;
        color: var(--gray-600);
    }
    
    .summary-calculations {
        padding: var(--spacing-md) 0;
        border-top: 1px solid var(--gray-200);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .summary-row {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-xs) 0;
        font-size: 14px;
    }
    
    .free-delivery {
        color: var(--success);
        font-weight: 700;
    }
    
    .discount-row {
        color: var(--success);
    }
    
    .discount-amount {
        color: var(--success);
        font-weight: 700;
    }
    
    .summary-total {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-md) 0;
        font-size: 20px;
        font-weight: 800;
    }
    
    .min-order-warning {
        padding: var(--spacing-md);
        background: var(--warning-light);
        border-radius: var(--radius-md);
        color: var(--warning);
        margin: var(--spacing-md) 0;
    }
    
    .applied-offer {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--success-light);
        border-radius: var(--radius-md);
        color: var(--success);
        font-size: 14px;
        margin: var(--spacing-md) 0;
    }
    
    .promo-code-section {
        margin-top: var(--spacing-lg);
    }
    
    .promo-code-section label {
        display: block;
        margin-bottom: var(--spacing-sm);
        font-weight: 600;
    }
    
    .promo-input-group {
        display: flex;
        gap: var(--spacing-sm);
    }
    
    .delivery-info-card {
        background: var(--white);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow-card);
        padding: var(--spacing-lg);
        margin-top: var(--spacing-lg);
    }
    
    .delivery-info-card h4 {
        margin-bottom: var(--spacing-sm);
    }
    
    .delivery-estimate {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: var(--spacing-sm);
    }
    
    .delivery-note {
        font-size: 13px;
        color: var(--gray-500);
    }
    
    /* Map Search Input */
    .map-search-input {
        width: 100%;
        padding: var(--spacing-sm) var(--spacing-md);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-sm);
        font-family: var(--font-primary);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .checkout-grid {
            grid-template-columns: 1fr;
        }
        
        .step-line {
            width: 40px;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
        
        .step-card {
            padding: var(--spacing-md);
        }
        
        .order-summary-card {
            position: static;
        }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
