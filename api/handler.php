<?php
/**
 * API Handler - معالج جميع طلبات API
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

// تعيين الهيدرز
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// الحصول على الإجراء المطلوب
$action = isset($_GET['action']) ? clean_input($_GET['action']) : '';

// التحقق من CSRF للطلبات غير GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $action = $_GET['action'] ?? '';
    
    // تجاهل CSRF لكل الإجراءات دي
    $skip_csrf = ['send_otp', 'verify_otp', 'resend_otp', 'password_login', 'admin_login', 'vendor_login', 'driver_login'];
    
    if (!in_array($action, $skip_csrf)) {
        $csrf_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!verify_csrf_token($csrf_token)) {
            json_error('رمز الحماية غير صالح', 403);
        }
    }
}

// توجيه الطلب حسب الإجراء
switch ($action) {
    // ============ OTP ============
    case 'send_otp':
        handle_send_otp();
        break;
        
    case 'verify_otp':
        handle_verify_otp();
        break;
        
    case 'resend_otp':
        handle_resend_otp();
        break;
        
    // ============ Authentication ============
    case 'password_login':
        handle_password_login();
        break;
        
    case 'logout':
        handle_logout();
        break;
        
    // ============ Location ============
    case 'get_locations':
        handle_get_locations();
        break;
        
    case 'get_areas':
        handle_get_areas();
        break;
        
    // ============ Cart ============
    case 'add_to_cart':
        require_user();
        handle_add_to_cart();
        break;
        
    case 'update_cart_item':
        require_user();
        handle_update_cart_item();
        break;
        
    case 'remove_from_cart':
        require_user();
        handle_remove_from_cart();
        break;
        
    case 'get_cart':
        require_user();
        handle_get_cart();
        break;
        
    // ============ Favorites ============
    case 'toggle_favorite':
        require_user();
        handle_toggle_favorite();
        break;
        
    // ============ Orders ============
    case 'place_order':
        require_user();
        handle_place_order();
        break;
        
    case 'get_order_details':
        require_user();
        handle_get_order_details();
        break;
        
    case 'cancel_order':
        require_user();
        handle_cancel_order();
        break;
        
    case 'reorder':
        require_user();
        handle_reorder();
        break;
        
    case 'track_order':
        require_user();
        handle_track_order();
        break;
        
    // ============ Ratings ============
    case 'submit_rating':
        require_user();
        handle_submit_rating();
        break;
        
    // ============ Reports ============
    case 'submit_report':
        require_user();
        handle_submit_report();
        break;
        
    // ============ Notifications ============
    case 'get_notifications':
        require_user();
        handle_get_notifications();
        break;
        
    case 'mark_notification_read':
        require_user();
        handle_mark_notification_read();
        break;
        
    case 'mark_all_notifications_read':
        require_user();
        handle_mark_all_notifications_read();
        break;
        
    // ============ Profile ============
    case 'update_profile':
        require_user();
        handle_update_profile();
        break;
        
    case 'save_address':
        require_user();
        handle_save_address();
        break;
        
    case 'delete_address':
        require_user();
        handle_delete_address();
        break;
        
    case 'upload_avatar':
        require_user();
        handle_upload_avatar();
        break;
        
    // ============ Vendors & Products ============
    case 'get_vendors':
        handle_get_vendors();
        break;
        
    case 'get_marts':
        handle_get_marts();
        break;
        
    case 'get_product_details':
        handle_get_product_details();
        break;
        
    // ============ Promo ============
    case 'apply_promo':
        require_user();
        handle_apply_promo();
        break;
        
    // ============ Wheel ============
    case 'spin_wheel':
        require_user();
        handle_spin_wheel();
        break;
        
    case 'claim_reward':
        require_user();
        handle_claim_reward();
        break;
        
    // ============ Loyalty ============
    case 'get_loyalty_info':
        require_user();
        handle_get_loyalty_info();
        break;
        
    // ============ Admin Actions ============
    case 'admin_login':
        handle_admin_login();
        break;
        
    case 'vendor_login':
        handle_vendor_login();
        break;
        
    case 'driver_login':
        handle_driver_login();
        break;
        
    default:
        json_error('الإجراء غير معروف', 404);
}

// =====================================================
// OTP Functions
// =====================================================

function handle_send_otp() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');
    
    if (!is_valid_phone($phone)) {
        json_error('رقم الجوال غير صحيح');
    }
    
    $result = send_otp($phone);
    
    if ($result['success']) {
        start_session();
        $_SESSION['temp_phone'] = $phone;
        $_SESSION['temp_name'] = $name;
        $_SESSION['otp_expiry'] = OTP_EXPIRY_MINUTES * 60;
        
        json_success([
            'phone' => $phone,
            'code' => $result['code'] ?? null
        ], 'تم إرسال رمز التحقق');
    } else {
        json_error($result['message']);
    }
}

function handle_verify_otp() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $remember_me = isset($_POST['remember_me']) && $_POST['remember_me'] == '1';
    
    if (!is_valid_phone($phone) || empty($code)) {
        json_error('بيانات غير صحيحة');
    }
    
    $result = verify_otp($phone, $code);
    
    if ($result['success']) {
        $login_result = login_or_register_user($phone, $name, $remember_me);
        
        if ($login_result['success']) {
            start_session();
            unset($_SESSION['temp_phone'], $_SESSION['temp_name'], $_SESSION['otp_expiry']);
            
            $redirect = $_SESSION['redirect_after_login'] ?? BASE_URL . 'index.php';
            unset($_SESSION['redirect_after_login']);
            
            json_success([
                'user' => $login_result['user'],
                'redirect' => $redirect
            ], 'تم تسجيل الدخول بنجاح');
        } else {
            json_error($login_result['message']);
        }
    } else {
        json_error($result['message']);
    }
}

function handle_resend_otp() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    
    if (!is_valid_phone($phone)) {
        json_error('رقم الجوال غير صحيح');
    }
    
    $result = send_otp($phone);
    
    if ($result['success']) {
        json_success(['code' => $result['code'] ?? null], 'تم إعادة إرسال رمز التحقق');
    } else {
        json_error($result['message']);
    }
}

// =====================================================
// Authentication Functions
// =====================================================

function handle_password_login() {
    $type = clean_input($_POST['login_type'] ?? 'admin');
    $phone = normalize_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!is_valid_phone($phone) || empty($password)) {
        json_error('جميع الحقول مطلوبة');
    }
    
    switch ($type) {
        case 'admin':
            $result = login_admin($phone, $password);
            $redirect = BASE_URL . 'admin/index.php';
            break;
        case 'vendor':
            $result = login_vendor($phone, $password);
            $redirect = BASE_URL . 'vendor/index.php';
            break;
        case 'driver':
            $result = login_driver($phone, $password);
            $redirect = BASE_URL . 'driver/index.php';
            break;
        default:
            json_error('نوع الدخول غير صحيح');
    }
    
    if ($result['success']) {
        json_success(['redirect' => $redirect], $result['message']);
    } else {
        json_error($result['message']);
    }
}

function handle_logout() {
    if (is_logged_in()) {
        logout_user();
    } elseif (is_admin()) {
        logout_admin();
    } elseif (is_vendor()) {
        logout_vendor();
    } elseif (is_driver()) {
        logout_driver();
    }
    
    json_success(null, 'تم تسجيل الخروج بنجاح');
}

// =====================================================
// Location Functions
// =====================================================

function handle_get_locations() {
    $cities = db_fetch_all(
        "SELECT DISTINCT city, city as id, city as name FROM zones WHERE status = 1 ORDER BY city",
        []
    );
    
    $zones = db_fetch_all(
        "SELECT id, name_ar as name, city FROM zones WHERE status = 1 ORDER BY city, name_ar",
        []
    );
    
    $areas = db_fetch_all(
        "SELECT a.id, a.name_ar as name, a.zone_id, z.name_ar as zone_name 
         FROM areas a 
         JOIN zones z ON a.zone_id = z.id 
         WHERE a.status = 1 AND z.status = 1 
         ORDER BY z.city, a.name_ar",
        []
    );
    
    json_success([
        'cities' => $cities,
        'zones' => $zones,
        'areas' => $areas
    ]);
}

function handle_get_areas() {
    $zone_id = isset($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
    
    $sql = "SELECT a.id, a.name_ar, a.zone_id, z.name_ar as zone_name, z.city 
            FROM areas a 
            JOIN zones z ON a.zone_id = z.id 
            WHERE a.status = 1 AND z.status = 1";
    $params = [];
    
    if ($zone_id) {
        $sql .= " AND a.zone_id = ?";
        $params[] = $zone_id;
    }
    
    $sql .= " ORDER BY a.name_ar";
    
    $areas = db_fetch_all($sql, $params);
    json_success($areas);
}

// =====================================================
// Cart Functions
// =====================================================

function handle_add_to_cart() {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    $notes = trim($_POST['notes'] ?? '');
    $options = $_POST['options'] ?? null;
    
    if (!$product_id || $quantity < 1) {
        json_error('بيانات المنتج غير صحيحة');
    }
    
    $product = db_fetch(
        "SELECT p.*, v.id as vendor_id 
         FROM products p 
         JOIN vendors v ON p.vendor_id = v.id 
         WHERE p.id = ? AND p.is_available = 1 AND v.status = 'approved'",
        [$product_id]
    );
    
    if (!$product) {
        json_error('المنتج غير متوفر');
    }
    
    $vendor_id = $product['vendor_id'];
    $price = $product['discount_price'] ?: $product['price'];
    
    // البحث عن سلة نشطة
    $cart = db_fetch(
        "SELECT * FROM carts WHERE user_id = ?",
        [$user_id]
    );
    
    // إذا كانت هناك سلة لمتجر آخر، نحذفها
    if ($cart && $cart['vendor_id'] != $vendor_id) {
        db_delete('cart_items', 'cart_id = ?', [$cart['id']]);
        db_delete('carts', 'id = ?', [$cart['id']]);
        $cart = null;
    }
    
    // إنشاء سلة جديدة إذا لم توجد
    if (!$cart) {
        $cart_id = db_insert('carts', [
            'user_id' => $user_id,
            'vendor_id' => $vendor_id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } else {
        $cart_id = $cart['id'];
    }
    
    // البحث عن المنتج في السلة
    $existing_item = db_fetch(
        "SELECT * FROM cart_items WHERE cart_id = ? AND product_id = ?",
        [$cart_id, $product_id]
    );
    
    if ($existing_item) {
        db_update(
            'cart_items',
            ['quantity' => $existing_item['quantity'] + $quantity],
            'id = ?',
            [':id' => $existing_item['id']]
        );
    } else {
        db_insert('cart_items', [
            'cart_id' => $cart_id,
            'product_id' => $product_id,
            'quantity' => $quantity,
            'unit_price' => $price,
            'notes' => $notes,
            'options' => $options ? json_encode($options) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // حساب عدد عناصر السلة
    $cart_count = db_fetch(
        "SELECT SUM(quantity) as count FROM cart_items WHERE cart_id = ?",
        [$cart_id]
    )['count'] ?? 0;
    
    json_success(['cart_count' => $cart_count], 'تمت الإضافة إلى السلة');
}

function handle_update_cart_item() {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)($_POST['product_id'] ?? 0);
    $change = (int)($_POST['change'] ?? 0);
    
    $cart = db_fetch("SELECT * FROM carts WHERE user_id = ?", [$user_id]);
    if (!$cart) {
        json_error('السلة فارغة');
    }
    
    $item = db_fetch(
        "SELECT * FROM cart_items WHERE cart_id = ? AND product_id = ?",
        [$cart['id'], $product_id]
    );
    
    if (!$item) {
        json_error('المنتج غير موجود في السلة');
    }
    
    $new_quantity = $item['quantity'] + $change;
    
    if ($new_quantity <= 0) {
        db_delete('cart_items', 'id = ?', [$item['id']]);
    } else {
        db_update(
            'cart_items',
            ['quantity' => $new_quantity],
            'id = ?',
            [':id' => $item['id']]
        );
    }
    
    json_success(['quantity' => max(0, $new_quantity)], 'تم تحديث السلة');
}

function handle_remove_from_cart() {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    $cart = db_fetch("SELECT * FROM carts WHERE user_id = ?", [$user_id]);
    if (!$cart) {
        json_error('السلة فارغة');
    }
    
    db_delete(
        'cart_items',
        'cart_id = ? AND product_id = ?',
        [$cart['id'], $product_id]
    );
    
    json_success(null, 'تم حذف المنتج من السلة');
}

function handle_get_cart() {
    $user_id = $_SESSION['user_id'];
    
    $cart = db_fetch(
        "SELECT c.*, v.business_name, v.min_order, v.delivery_time 
         FROM carts c 
         JOIN vendors v ON c.vendor_id = v.id 
         WHERE c.user_id = ?",
        [$user_id]
    );
    
    if (!$cart) {
        json_success(['items' => [], 'total' => 0]);
        return;
    }
    
    $items = db_fetch_all(
        "SELECT ci.*, p.name_ar, p.image 
         FROM cart_items ci 
         JOIN products p ON ci.product_id = p.id 
         WHERE ci.cart_id = ?",
        [$cart['id']]
    );
    
    $total = 0;
    foreach ($items as $item) {
        $total += $item['unit_price'] * $item['quantity'];
    }
    
    json_success([
        'vendor' => $cart,
        'items' => $items,
        'total' => $total
    ]);
}

// =====================================================
// Favorites Functions
// =====================================================

function handle_toggle_favorite() {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $type = $input['type'] ?? '';
    $id = (int)($input['id'] ?? 0);
    
    if (!in_array($type, ['vendor', 'product']) || !$id) {
        json_error('بيانات غير صحيحة');
    }
    
    $table = $type . '_favorites';
    $column = $type . '_id';
    
    $existing = db_fetch(
        "SELECT * FROM $table WHERE user_id = ? AND $column = ?",
        [$user_id, $id]
    );
    
    if ($existing) {
        db_delete($table, 'id = ?', [$existing['id']]);
        json_success(['is_favorite' => false], 'تم الإزالة من المفضلة');
    } else {
        db_insert($table, [
            'user_id' => $user_id,
            $column => $id,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        json_success(['is_favorite' => true], 'تمت الإضافة إلى المفضلة');
    }
}

// =====================================================
// Order Functions
// =====================================================

function handle_place_order() {
    $user_id = $_SESSION['user_id'];
    $address_id = (int)($_POST['address_id'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');
    $delivery_notes = trim($_POST['delivery_notes'] ?? '');
    $tip_amount = (float)($_POST['tip_amount'] ?? 0);
    $promo_code = trim($_POST['promo_code'] ?? '');
    
    // التحقق من السلة
    $cart = db_fetch(
        "SELECT c.*, v.zone_id, v.min_order 
         FROM carts c 
         JOIN vendors v ON c.vendor_id = v.id 
         WHERE c.user_id = ?",
        [$user_id]
    );
    
    if (!$cart) {
        json_error('السلة فارغة');
    }
    
    $items = db_fetch_all(
        "SELECT ci.*, p.price as original_price 
         FROM cart_items ci 
         JOIN products p ON ci.product_id = p.id 
         WHERE ci.cart_id = ?",
        [$cart['id']]
    );
    
    if (empty($items)) {
        json_error('السلة فارغة');
    }
    
    // حساب المجموع
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['unit_price'] * $item['quantity'];
    }
    
    if ($subtotal < $cart['min_order']) {
        json_error('الطلب أقل من الحد الأدنى: ' . format_price($cart['min_order']));
    }
    
    // التحقق من العنوان
    $address = db_fetch(
        "SELECT * FROM addresses WHERE id = ? AND user_id = ?",
        [$address_id, $user_id]
    );
    
    if (!$address) {
        json_error('عنوان التوصيل غير صحيح');
    }
    
    // حساب رسوم التوصيل
    $delivery_fee = calculate_delivery_fee(0, $cart['zone_id']);
    
    // حساب الخصم
    $discount = 0;
    if ($promo_code) {
        $offer = db_fetch(
            "SELECT * FROM offers 
             WHERE code = ? AND status = 1 
             AND start_date <= NOW() AND end_date >= NOW()
             AND (vendor_id IS NULL OR vendor_id = ?)",
            [$promo_code, $cart['vendor_id']]
        );
        
        if ($offer) {
            if ($offer['offer_type'] == 'percentage' || $offer['discount_type'] == 'percentage') {
                $discount = $subtotal * ($offer['discount_value'] / 100);
                if ($offer['max_discount']) {
                    $discount = min($discount, $offer['max_discount']);
                }
            } else {
                $discount = $offer['discount_value'];
            }
        }
    }
    
    $service_fee = SERVICE_FEE;
    $total = $subtotal + $delivery_fee + $service_fee - $discount + $tip_amount;
    
    // إنشاء الطلب
    $order_number = generate_order_number();
    $order_id = db_insert('orders', [
        'order_number' => $order_number,
        'user_id' => $user_id,
        'vendor_id' => $cart['vendor_id'],
        'address_id' => $address_id,
        'address_snapshot' => json_encode($address),
        'subtotal' => $subtotal,
        'delivery_fee' => $delivery_fee,
        'service_fee' => $service_fee,
        'discount' => $discount,
        'tip' => $tip_amount,
        'total' => $total,
        'payment_method' => $payment_method,
        'status' => 'new',
        'notes' => $delivery_notes,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // حفظ عناصر الطلب
    foreach ($items as $item) {
        db_insert('order_items', [
            'order_id' => $order_id,
            'product_id' => $item['product_id'],
            'product_snapshot' => json_encode([
                'name' => $item['name_ar'] ?? '',
                'price' => $item['original_price']
            ]),
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'total_price' => $item['unit_price'] * $item['quantity'],
            'notes' => $item['notes'],
            'options' => $item['options']
        ]);
    }
    
    // حفظ سجل الحالة
    db_insert('order_status_logs', [
        'order_id' => $order_id,
        'status' => 'new',
        'changed_by' => 'user',
        'changed_by_id' => $user_id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // إرسال الطلب للمناديب المتاحين
    notify_drivers_for_order($order_id, $cart['zone_id']);
    
    // إرسال إشعار للمستخدم
    send_notification(
        $user_id,
        'user',
        'تم استلام طلبك',
        "طلبك رقم #{$order_number} قيد المعالجة",
        'order',
        $order_id,
        'order'
    );
    
    // إرسال إشعار للتاجر
    $vendor = db_fetch("SELECT * FROM vendors WHERE id = ?", [$cart['vendor_id']]);
    if ($vendor) {
        send_notification(
            $vendor['id'],
            'vendor',
            'طلب جديد',
            "لديك طلب جديد رقم #{$order_number}",
            'order',
            $order_id,
            'order'
        );
    }
    
    // تفريغ السلة
    db_delete('cart_items', 'cart_id = ?', [$cart['id']]);
    db_delete('carts', 'id = ?', [$cart['id']]);
    
    // تسجيل النشاط
    log_activity('user', $user_id, 'place_order', "إنشاء طلب جديد #{$order_number}");
    
    json_success([
        'order_id' => $order_id,
        'order_number' => $order_number
    ], 'تم إنشاء الطلب بنجاح');
}

function notify_drivers_for_order($order_id, $zone_id) {
    $drivers = db_fetch_all(
        "SELECT * FROM drivers 
         WHERE zone_id = ? AND status = 'approved' 
         AND is_online = 1 AND is_busy = 0",
        [$zone_id]
    );
    
    foreach ($drivers as $driver) {
        db_insert('order_assignments', [
            'order_id' => $order_id,
            'driver_id' => $driver['id'],
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        send_notification(
            $driver['id'],
            'driver',
            'طلب جديد متاح',
            'يوجد طلب جديد في منطقتك',
            'order',
            $order_id,
            'order'
        );
    }
}

function handle_get_order_details() {
    $user_id = $_SESSION['user_id'];
    $order_id = (int)($_GET['id'] ?? 0);
    
    $order = db_fetch(
        "SELECT o.*, v.business_name as vendor_name, 
                d.name as driver_name, d.phone as driver_phone
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.id
         LEFT JOIN drivers d ON o.driver_id = d.id
         WHERE o.id = ? AND o.user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        json_error('الطلب غير موجود');
    }
    
    $items = db_fetch_all(
        "SELECT oi.*, 
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(oi.product_snapshot, '$.name')), 'منتج') as name_ar
         FROM order_items oi
         WHERE oi.order_id = ?",
        [$order_id]
    );
    
    $status_logs = db_fetch_all(
        "SELECT * FROM order_status_logs WHERE order_id = ? ORDER BY created_at ASC",
        [$order_id]
    );
    
    $order['items'] = $items;
    $order['status_logs'] = $status_logs;
    
    json_success($order);
}

function handle_cancel_order() {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($input['order_id'] ?? 0);
    
    $order = db_fetch(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        json_error('الطلب غير موجود');
    }
    
    if (!in_array($order['status'], ['new', 'accepted'])) {
        json_error('لا يمكن إلغاء الطلب في هذه الحالة');
    }
    
    db_update(
        'orders',
        [
            'status' => 'cancelled',
            'cancelled_by' => 'user',
            'cancellation_reason' => 'إلغاء من قبل العميل'
        ],
        'id = ?',
        [':id' => $order_id]
    );
    
    db_insert('order_status_logs', [
        'order_id' => $order_id,
        'status' => 'cancelled',
        'note' => 'تم الإلغاء من قبل العميل',
        'changed_by' => 'user',
        'changed_by_id' => $user_id,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    log_activity('user', $user_id, 'cancel_order', "إلغاء الطلب #{$order['order_number']}");
    
    json_success(null, 'تم إلغاء الطلب بنجاح');
}

function handle_reorder() {
    $user_id = $_SESSION['user_id'];
    $order_id = (int)($_GET['order_id'] ?? 0);
    
    $order = db_fetch(
        "SELECT * FROM orders WHERE id = ? AND user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        json_error('الطلب غير موجود');
    }
    
    // حذف السلة الحالية
    $cart = db_fetch("SELECT * FROM carts WHERE user_id = ?", [$user_id]);
    if ($cart) {
        db_delete('cart_items', 'cart_id = ?', [$cart['id']]);
        db_delete('carts', 'id = ?', [$cart['id']]);
    }
    
    // إنشاء سلة جديدة
    $cart_id = db_insert('carts', [
        'user_id' => $user_id,
        'vendor_id' => $order['vendor_id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // نسخ عناصر الطلب للسلة
    $items = db_fetch_all(
        "SELECT * FROM order_items WHERE order_id = ?",
        [$order_id]
    );
    
    foreach ($items as $item) {
        db_insert('cart_items', [
            'cart_id' => $cart_id,
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'notes' => $item['notes'],
            'options' => $item['options'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    json_success(null, 'تم إعادة الطلب إلى السلة');
}

function handle_track_order() {
    $user_id = $_SESSION['user_id'];
    $order_id = (int)($_GET['order_id'] ?? 0);
    
    $order = db_fetch(
        "SELECT o.*, d.latitude, d.longitude, d.name as driver_name, d.phone as driver_phone
         FROM orders o
         LEFT JOIN drivers d ON o.driver_id = d.id
         WHERE o.id = ? AND o.user_id = ?",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        json_error('الطلب غير موجود');
    }
    
    json_success([
        'status' => $order['status'],
        'driver' => [
            'name' => $order['driver_name'],
            'phone' => $order['driver_phone'],
            'latitude' => $order['latitude'],
            'longitude' => $order['longitude']
        ]
    ]);
}

// =====================================================
// Rating Functions
// =====================================================

function handle_submit_rating() {
    $user_id = $_SESSION['user_id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    $vendor_rating = (int)($_POST['vendor_rating'] ?? 0);
    $driver_rating = (int)($_POST['driver_rating'] ?? 0);
    $review = trim($_POST['review'] ?? '');
    
    $order = db_fetch(
        "SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = 'delivered'",
        [$order_id, $user_id]
    );
    
    if (!$order) {
        json_error('لا يمكن تقييم هذا الطلب');
    }
    
    // التحقق من عدم وجود تقييم سابق
    $existing = db_fetch(
        "SELECT * FROM ratings WHERE order_id = ? AND user_id = ? AND rated_by = 'user' AND rated_for = 'vendor'",
        [$order_id, $user_id]
    );
    
    if ($existing) {
        json_error('تم تقييم هذا الطلب مسبقاً');
    }
    
    // تقييم المطعم
    if ($vendor_rating > 0) {
        db_insert('ratings', [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'vendor_id' => $order['vendor_id'],
            'rated_by' => 'user',
            'rated_for' => 'vendor',
            'rating' => $vendor_rating,
            'review' => $review,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // تقييم المندوب
    if ($driver_rating > 0 && $order['driver_id']) {
        db_insert('ratings', [
            'order_id' => $order_id,
            'user_id' => $user_id,
            'driver_id' => $order['driver_id'],
            'rated_by' => 'user',
            'rated_for' => 'driver',
            'rating' => $driver_rating,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    json_success(null, 'شكراً لتقييمك!');
}

// =====================================================
// Report Functions
// =====================================================

function handle_submit_report() {
    $user_id = $_SESSION['user_id'];
    $order_id = (int)($_POST['order_id'] ?? 0);
    $reported_type = clean_input($_POST['reported_type'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($subject) || empty($description)) {
        json_error('جميع الحقول مطلوبة');
    }
    
    $report_number = generate_report_number();
    
    $data = [
        'report_number' => $report_number,
        'user_id' => $user_id,
        'reported_type' => $reported_type,
        'reported_id' => $order_id,
        'subject' => $subject,
        'description' => $description,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if ($order_id) {
        $order = db_fetch("SELECT * FROM orders WHERE id = ?", [$order_id]);
        if ($order) {
            switch ($reported_type) {
                case 'vendor':
                    $data['reported_id'] = $order['vendor_id'];
                    break;
                case 'driver':
                    $data['reported_id'] = $order['driver_id'];
                    break;
                default:
                    $data['reported_id'] = $order_id;
            }
        }
        $data['order_id'] = $order_id;
    }
    
    db_insert('reports', $data);
    
    log_activity('user', $user_id, 'submit_report', "تقديم بلاغ جديد #{$report_number}");
    
    json_success(['report_number' => $report_number], 'تم استلام بلاغك وسيتم مراجعته');
}

// =====================================================
// Notification Functions
// =====================================================

function handle_get_notifications() {
    $user_id = $_SESSION['user_id'];
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $notifications = db_fetch_all(
        "SELECT * FROM notifications 
         WHERE user_id = ? AND user_type = 'user'
         ORDER BY created_at DESC 
         LIMIT ? OFFSET ?",
        [$user_id, $limit, $offset]
    );
    
    $unread_count = db_fetch(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE user_id = ? AND user_type = 'user' AND is_read = 0",
        [$user_id]
    )['count'] ?? 0;
    
    json_success([
        'notifications' => $notifications,
        'unread_count' => $unread_count
    ]);
}

function handle_mark_notification_read() {
    $user_id = $_SESSION['user_id'];
    $notification_id = (int)($_POST['notification_id'] ?? 0);
    
    db_update(
        'notifications',
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
        'id = ? AND user_id = ?',
        [':id' => $notification_id, ':user_id' => $user_id]
    );
    
    json_success(null, 'تم تحديد الإشعار كمقروء');
}

function handle_mark_all_notifications_read() {
    $user_id = $_SESSION['user_id'];
    
    db_update(
        'notifications',
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
        'user_id = ? AND user_type = ?',
        [':user_id' => $user_id, ':user_type' => 'user']
    );
    
    json_success(null, 'تم تحديد جميع الإشعارات كمقروءة');
}

// =====================================================
// Profile Functions
// =====================================================

function handle_update_profile() {
    $user_id = $_SESSION['user_id'];
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        json_error('الاسم مطلوب');
    }
    
    db_update(
        'users',
        ['name' => $name],
        'id = ?',
        [':id' => $user_id]
    );
    
    $_SESSION['user_name'] = $name;
    
    json_success(['name' => $name], 'تم تحديث الملف الشخصي');
}

function handle_save_address() {
    $user_id = $_SESSION['user_id'];
    
    $data = [
        'user_id' => $user_id,
        'title' => trim($_POST['title'] ?? 'المنزل'),
        'address' => trim($_POST['address'] ?? ''),
        'building' => trim($_POST['building'] ?? ''),
        'floor' => trim($_POST['floor'] ?? ''),
        'apartment' => trim($_POST['apartment'] ?? ''),
        'landmark' => trim($_POST['landmark'] ?? ''),
        'latitude' => (float)($_POST['latitude'] ?? 0),
        'longitude' => (float)($_POST['longitude'] ?? 0),
        'area_id' => (int)($_POST['area_id'] ?? 0),
        'is_default' => isset($_POST['is_default']) && $_POST['is_default'] == '1' ? 1 : 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if (empty($data['address'])) {
        json_error('العنوان مطلوب');
    }
    
    // إذا كان العنوان افتراضي، نلغي الافتراضي عن باقي العناوين
    if ($data['is_default']) {
        db_update(
            'addresses',
            ['is_default' => 0],
            'user_id = ?',
            [':user_id' => $user_id]
        );
    }
    
    $address_id = (int)($_POST['address_id'] ?? 0);
    
    if ($address_id) {
        // تحديث عنوان موجود
        unset($data['user_id'], $data['created_at']);
        db_update('addresses', $data, 'id = ? AND user_id = ?', [':id' => $address_id, ':user_id' => $user_id]);
    } else {
        // إضافة عنوان جديد
        $address_id = db_insert('addresses', $data);
    }
    
    json_success(['address_id' => $address_id], 'تم حفظ العنوان بنجاح');
}

function handle_delete_address() {
    $user_id = $_SESSION['user_id'];
    $address_id = (int)($_POST['address_id'] ?? 0);
    
    db_delete('addresses', 'id = ? AND user_id = ?', [$address_id, $user_id]);
    
    json_success(null, 'تم حذف العنوان بنجاح');
}

function handle_upload_avatar() {
    $user_id = $_SESSION['user_id'];
    
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        json_error('فشل رفع الصورة');
    }
    
    $avatar_path = upload_image($_FILES['avatar'], 'avatars');
    
    if (!$avatar_path) {
        json_error('فشل رفع الصورة، تأكد من نوع وحجم الملف');
    }
    
    db_update('users', ['avatar' => $avatar_path], 'id = ?', [':id' => $user_id]);
    
    json_success(['avatar' => $avatar_path], 'تم تحديث الصورة الشخصية');
}

// =====================================================
// Vendors & Products Functions
// =====================================================

function handle_get_vendors() {
    $page = (int)($_GET['page'] ?? 1);
    $category_id = (int)($_GET['category_id'] ?? 0);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT v.*, z.name_ar as zone_name,
                   (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
                   (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
            FROM vendors v
            LEFT JOIN zones z ON v.zone_id = z.id
            WHERE v.status = 'approved' 
            AND v.business_type IN ('restaurant', 'both')
            AND v.is_open = 1";
    $params = [];
    
    if ($category_id) {
        $sql .= " AND EXISTS (SELECT 1 FROM products p WHERE p.vendor_id = v.id AND p.category_id = ?)";
        $params[] = $category_id;
    }
    
    $sql .= " ORDER BY v.sort_order ASC, v.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $vendors = db_fetch_all($sql, $params);
    
    json_success($vendors);
}

function handle_get_marts() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20;
    $offset = ($page - 1) * $limit;
    
    $marts = db_fetch_all(
        "SELECT v.*, z.name_ar as zone_name,
                (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
                (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
         FROM vendors v
         LEFT JOIN zones z ON v.zone_id = z.id
         WHERE v.status = 'approved' 
         AND v.business_type IN ('mart', 'both')
         AND v.is_open = 1
         ORDER BY v.sort_order ASC, v.created_at DESC 
         LIMIT ? OFFSET ?",
        [$limit, $offset]
    );
    
    json_success($marts);
}

function handle_get_product_details() {
    $product_id = (int)($_GET['id'] ?? 0);
    
    $product = db_fetch(
        "SELECT p.*, v.business_name as vendor_name, c.name_ar as category_name
         FROM products p
         JOIN vendors v ON p.vendor_id = v.id
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.id = ? AND p.is_available = 1 AND v.status = 'approved'",
        [$product_id]
    );
    
    if (!$product) {
        json_error('المنتج غير موجود');
    }
    
    $options = db_fetch_all(
        "SELECT * FROM product_options WHERE product_id = ? AND status = 1 ORDER BY sort_order",
        [$product_id]
    );
    
    $product['options'] = $options;
    
    json_success($product);
}

// =====================================================
// Promo Functions
// =====================================================

function handle_apply_promo() {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $code = trim($input['code'] ?? '');
    $vendor_id = (int)($input['vendor_id'] ?? 0);
    
    if (empty($code)) {
        json_error('الرجاء إدخال كود الخصم');
    }
    
    $offer = db_fetch(
        "SELECT * FROM offers 
         WHERE code = ? AND status = 1 
         AND start_date <= NOW() AND end_date >= NOW()
         AND (vendor_id IS NULL OR vendor_id = ?)",
        [$code, $vendor_id]
    );
    
    if (!$offer) {
        json_error('كود الخصم غير صالح أو منتهي الصلاحية');
    }
    
    // التحقق من عدد مرات الاستخدام
    if ($offer['usage_limit'] && $offer['usage_count'] >= $offer['usage_limit']) {
        json_error('تم استخدام هذا الكود بالكامل');
    }
    
    // التحقق من استخدام المستخدم للكود
    $user_usage = db_fetch(
        "SELECT COUNT(*) as count FROM orders 
         WHERE user_id = ? AND discount > 0",
        [$user_id]
    );
    
    if ($offer['user_limit'] && $user_usage['count'] >= $offer['user_limit']) {
        json_error('لقد تجاوزت الحد المسموح لاستخدام هذا الكود');
    }
    
    json_success([
        'offer' => [
            'id' => $offer['id'],
            'title' => $offer['title_ar'],
            'type' => $offer['offer_type'],
            'value' => $offer['discount_value'],
            'max_discount' => $offer['max_discount']
        ]
    ], 'تم تطبيق الخصم بنجاح');
}

// =====================================================
// Wheel Functions
// =====================================================

function handle_spin_wheel() {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $reward_id = (int)($input['reward_id'] ?? 0);
    
    // التحقق من المحاولات
    $today_spins = db_fetch(
        "SELECT COUNT(*) as count FROM wheel_spins 
         WHERE user_id = ? AND DATE(created_at) = CURDATE()",
        [$user_id]
    )['count'] ?? 0;
    
    $max_spins = WHEEL_MAX_SPINS_PER_DAY;
    
    if ($today_spins >= $max_spins) {
        json_error('لقد استنفذت محاولاتك اليومية');
    }
    
    // التحقق من الحد الأدنى للطلبات
    $completed_orders = db_fetch(
        "SELECT COUNT(*) as count FROM orders 
         WHERE user_id = ? AND status = 'delivered'",
        [$user_id]
    )['count'] ?? 0;
    
    if ($completed_orders < WHEEL_MIN_ORDERS_FOR_SPIN) {
        json_error('يجب إكمال ' . WHEEL_MIN_ORDERS_FOR_SPIN . ' طلبات للدوران');
    }
    
    $reward = db_fetch(
        "SELECT * FROM lucky_wheel_rewards WHERE id = ? AND status = 1",
        [$reward_id]
    );
    
    if (!$reward) {
        json_error('الجائزة غير موجودة');
    }
    
    // التحقق من الحد الأقصى للاستخدام
    if ($reward['max_usage'] && $reward['used_count'] >= $reward['max_usage']) {
        json_error('عذراً، نفذت هذه الجائزة');
    }
    
    // حفظ نتيجة الدوران
    $spin_id = db_insert('wheel_spins', [
        'user_id' => $user_id,
        'reward_id' => $reward_id,
        'reward_snapshot' => json_encode($reward),
        'is_claimed' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    // تحديث عدد استخدامات الجائزة
    db_update(
        'lucky_wheel_rewards',
        ['used_count' => $reward['used_count'] + 1],
        'id = ?',
        [':id' => $reward_id]
    );
    
    json_success([
        'spin_id' => $spin_id,
        'reward' => [
            'name' => $reward['name_ar'],
            'type' => $reward['reward_type'],
            'value' => $reward['reward_value']
        ]
    ], 'مبروك! لقد ربحت ' . $reward['name_ar']);
}

function handle_claim_reward() {
    $user_id = $_SESSION['user_id'];
    $input = json_decode(file_get_contents('php://input'), true);
    $spin_id = (int)($input['spin_id'] ?? 0);
    
    $spin = db_fetch(
        "SELECT ws.*, lwr.reward_type, lwr.reward_value 
         FROM wheel_spins ws
         JOIN lucky_wheel_rewards lwr ON ws.reward_id = lwr.id
         WHERE ws.id = ? AND ws.user_id = ? AND ws.is_claimed = 0",
        [$spin_id, $user_id]
    );
    
    if (!$spin) {
        json_error('الجائزة غير موجودة أو تم استلامها مسبقاً');
    }
    
    // تطبيق الجائزة
    switch ($spin['reward_type']) {
        case 'points':
            db_update(
                'users',
                ['loyalty_points' => db_fetch("SELECT loyalty_points FROM users WHERE id = ?", [$user_id])['loyalty_points'] + $spin['reward_value']],
                'id = ?',
                [':id' => $user_id]
            );
            break;
        case 'free_delivery':
            // إضافة كود توصيل مجاني
            break;
    }
    
    db_update(
        'wheel_spins',
        ['is_claimed' => 1, 'claimed_at' => date('Y-m-d H:i:s')],
        'id = ?',
        [':id' => $spin_id]
    );
    
    json_success(null, 'تم استلام الجائزة بنجاح');
}

// =====================================================
// Loyalty Functions
// =====================================================

function handle_get_loyalty_info() {
    $user_id = $_SESSION['user_id'];
    
    $user = db_fetch("SELECT loyalty_points, total_orders FROM users WHERE id = ?", [$user_id]);
    
    $monthly_orders = db_fetch(
        "SELECT COUNT(*) as count FROM orders 
         WHERE user_id = ? AND status = 'delivered' 
         AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
        [$user_id]
    )['count'] ?? 0;
    
    $rules = db_fetch_all(
        "SELECT * FROM loyalty_rules WHERE status = 1 ORDER BY orders_count ASC",
        []
    );
    
    $logs = db_fetch_all(
        "SELECT * FROM loyalty_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
        [$user_id]
    );
    
    json_success([
        'points' => $user['loyalty_points'],
        'total_orders' => $user['total_orders'],
        'monthly_orders' => $monthly_orders,
        'rules' => $rules,
        'logs' => $logs
    ]);
}

// =====================================================
// Admin/Driver/Vendor Login Functions
// =====================================================

function handle_admin_login() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!is_valid_phone($phone) || empty($password)) {
        json_error('جميع الحقول مطلوبة');
    }
    
    $result = login_admin($phone, $password);
    
    if ($result['success']) {
        json_success(['redirect' => BASE_URL . 'admin/index.php'], $result['message']);
    } else {
        json_error($result['message']);
    }
}

function handle_vendor_login() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!is_valid_phone($phone) || empty($password)) {
        json_error('جميع الحقول مطلوبة');
    }
    
    $result = login_vendor($phone, $password);
    
    if ($result['success']) {
        json_success(['redirect' => BASE_URL . 'vendor/index.php'], $result['message']);
    } else {
        json_error($result['message']);
    }
}

function handle_driver_login() {
    $phone = normalize_phone($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!is_valid_phone($phone) || empty($password)) {
        json_error('جميع الحقول مطلوبة');
    }
    
    $result = login_driver($phone, $password);
    
    if ($result['success']) {
        json_success(['redirect' => BASE_URL . 'driver/index.php'], $result['message']);
    } else {
        json_error($result['message']);
    }
}
