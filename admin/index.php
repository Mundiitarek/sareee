<?php
/**
 * لوحة تحكم الأدمن - الجزء الأول
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

// التحقق من صلاحية الأدمن
if (!is_admin()) {
    redirect(BASE_URL . 'login.php?type=admin');
}

$admin = get_current_admin();
$section = isset($_GET['section']) ? clean_input($_GET['section']) : 'dashboard';

// قائمة الأقسام المتاحة
$allowed_sections = [
    'dashboard', 'users', 'vendors', 'drivers', 'orders', 'zones', 
    'banners', 'offers', 'ratings', 'reports', 'tips', 'loyalty', 
    'wheel', 'notifications', 'settings', 'statistics'
];

if (!in_array($section, $allowed_sections)) {
    $section = 'dashboard';
}

// معالجة طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleAdminPost();
}

$page_title = 'لوحة التحكم - ' . APP_NAME;

// =====================================================
// دوال مساعدة للوحة التحكم
// =====================================================

function renderStatusBadge($status, $type = 'default') {
    $badges = [
        'pending' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'قيد الانتظار'],
        'approved' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'موافق'],
        'rejected' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'مرفوض'],
        'suspended' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'موقوف'],
        'active' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'نشط'],
        'blocked' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'محظور'],
        'new' => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'جديد'],
        'accepted' => ['bg' => '#E9D5FF', 'color' => '#7C3AED', 'text' => 'تم القبول'],
        'preparing' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'قيد التحضير'],
        'on_way' => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'خرج للتوصيل'],
        'delivered' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'تم التوصيل'],
        'cancelled' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'ملغي'],
        'investigating' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'قيد المراجعة'],
        'resolved' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'تم الحل']
    ];
    
    $badge = $badges[$status] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'text' => $status];
    
    return "<span class='status-badge' style='background: {$badge['bg']}; color: {$badge['color']};'>{$badge['text']}</span>";
}

function handleAdminPost() {
    global $db, $section;
    
    $action = $_POST['action'] ?? '';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message(BASE_URL . 'admin/index.php?section=' . urlencode($section), 'رمز الحماية غير صالح، حدّث الصفحة وحاول مرة أخرى', 'error');
    }
    
    switch ($action) {
        // Users
        case 'update_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $is_blocked = isset($_POST['is_blocked']) ? 1 : 0;
            $block_reason = trim($_POST['block_reason'] ?? '');
            
            db_update('users', [
                'name' => $name,
                'is_blocked' => $is_blocked,
                'block_reason' => $block_reason
            ], 'id = ?', [':id' => $user_id]);
            
            log_activity('admin', $_SESSION['admin_id'], 'update_user', "تحديث بيانات المستخدم #$user_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=users', 'تم تحديث بيانات المستخدم بنجاح', 'success');
            break;
            
        case 'delete_user':
            $user_id = (int)($_POST['user_id'] ?? 0);
            db_delete('users', 'id = ?', [$user_id]);
            log_activity('admin', $_SESSION['admin_id'], 'delete_user', "حذف المستخدم #$user_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=users', 'تم حذف المستخدم بنجاح', 'success');
            break;
            
        // Vendors
        case 'approve_vendor':
            $vendor_id = (int)($_POST['vendor_id'] ?? 0);
            db_update('vendors', ['status' => 'approved'], 'id = ?', [':id' => $vendor_id]);
            
            $vendor = db_fetch("SELECT * FROM vendors WHERE id = ?", [$vendor_id]);
            send_notification($vendor_id, 'vendor', 'تم قبول حسابك', 'تم قبول حسابك في سريع. يمكنك الآن استقبال الطلبات!', 'system');
            
            log_activity('admin', $_SESSION['admin_id'], 'approve_vendor', "الموافقة على التاجر #$vendor_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=vendors', 'تم قبول التاجر بنجاح', 'success');
            break;
            
        case 'reject_vendor':
            $vendor_id = (int)($_POST['vendor_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? '');
            db_update('vendors', ['status' => 'rejected', 'rejection_reason' => $reason], 'id = ?', [':id' => $vendor_id]);
            
            $vendor = db_fetch("SELECT * FROM vendors WHERE id = ?", [$vendor_id]);
            send_notification($vendor_id, 'vendor', 'تم رفض حسابك', "نعتذر، تم رفض حسابك. السبب: $reason", 'system');
            
            log_activity('admin', $_SESSION['admin_id'], 'reject_vendor', "رفض التاجر #$vendor_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=vendors', 'تم رفض التاجر', 'success');
            break;
            
        case 'suspend_vendor':
            $vendor_id = (int)($_POST['vendor_id'] ?? 0);
            db_update('vendors', ['status' => 'suspended', 'is_open' => 0], 'id = ?', [':id' => $vendor_id]);
            log_activity('admin', $_SESSION['admin_id'], 'suspend_vendor', "تعليق التاجر #$vendor_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=vendors', 'تم تعليق التاجر', 'success');
            break;
            
        case 'update_vendor_commission':
            $vendor_id = (int)($_POST['vendor_id'] ?? 0);
            $commission = (float)($_POST['commission_rate'] ?? 10);
            db_update('vendors', ['commission_rate' => $commission], 'id = ?', [':id' => $vendor_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=vendors', 'تم تحديث نسبة العمولة', 'success');
            break;
            
        // Drivers
        case 'approve_driver':
            $driver_id = (int)($_POST['driver_id'] ?? 0);
            db_update('drivers', ['status' => 'approved'], 'id = ?', [':id' => $driver_id]);
            
            $driver = db_fetch("SELECT * FROM drivers WHERE id = ?", [$driver_id]);
            send_notification($driver_id, 'driver', 'تم قبول حسابك', 'تم قبول حسابك كمناديب في سريع. يمكنك الآن استقبال الطلبات!', 'system');
            
            log_activity('admin', $_SESSION['admin_id'], 'approve_driver', "الموافقة على المندوب #$driver_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=drivers', 'تم قبول المندوب بنجاح', 'success');
            break;
            
        case 'reject_driver':
            $driver_id = (int)($_POST['driver_id'] ?? 0);
            $reason = trim($_POST['rejection_reason'] ?? '');
            db_update('drivers', ['status' => 'rejected', 'rejection_reason' => $reason], 'id = ?', [':id' => $driver_id]);
            
            $driver = db_fetch("SELECT * FROM drivers WHERE id = ?", [$driver_id]);
            send_notification($driver_id, 'driver', 'تم رفض حسابك', "نعتذر، تم رفض حسابك. السبب: $reason", 'system');
            
            log_activity('admin', $_SESSION['admin_id'], 'reject_driver', "رفض المندوب #$driver_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=drivers', 'تم رفض المندوب', 'success');
            break;
            
        case 'suspend_driver':
            $driver_id = (int)($_POST['driver_id'] ?? 0);
            db_update('drivers', ['status' => 'suspended', 'is_online' => 0, 'is_busy' => 0], 'id = ?', [':id' => $driver_id]);
            log_activity('admin', $_SESSION['admin_id'], 'suspend_driver', "تعليق المندوب #$driver_id");
            redirect_with_message(BASE_URL . 'admin/index.php?section=drivers', 'تم تعليق المندوب', 'success');
            break;
            
        // Zones
        case 'add_zone':
            $name_ar = trim($_POST['name_ar'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $city = trim($_POST['city'] ?? '');
            
            db_insert('zones', [
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'city' => $city,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            log_activity('admin', $_SESSION['admin_id'], 'add_zone', "إضافة منطقة جديدة: $name_ar");
            redirect_with_message(BASE_URL . 'admin/index.php?section=zones', 'تم إضافة المنطقة بنجاح', 'success');
            break;
            
        case 'update_zone':
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;
            
            db_update('zones', [
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'city' => $city,
                'status' => $status
            ], 'id = ?', [':id' => $zone_id]);
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=zones', 'تم تحديث المنطقة بنجاح', 'success');
            break;
            
        case 'delete_zone':
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            db_delete('zones', 'id = ?', [$zone_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=zones', 'تم حذف المنطقة بنجاح', 'success');
            break;
            
        case 'add_area':
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            
            db_insert('areas', [
                'zone_id' => $zone_id,
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=zones', 'تم إضافة الحي بنجاح', 'success');
            break;
            
        case 'update_delivery_fee':
            $zone_id = (int)($_POST['zone_id'] ?? 0);
            $fee = (float)($_POST['fee'] ?? 0);
            $min_order_for_free = (float)($_POST['min_order_for_free'] ?? 0);
            
            $existing = db_fetch("SELECT * FROM delivery_fees WHERE zone_id = ?", [$zone_id]);
            
            if ($existing) {
                db_update('delivery_fees', [
                    'fee' => $fee,
                    'min_order_for_free' => $min_order_for_free
                ], 'zone_id = ?', [':zone_id' => $zone_id]);
            } else {
                db_insert('delivery_fees', [
                    'zone_id' => $zone_id,
                    'fee' => $fee,
                    'min_order_for_free' => $min_order_for_free,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=zones', 'تم تحديث رسوم التوصيل', 'success');
            break;
            
        // Banners
        case 'add_banner':
        case 'update_banner':
            $banner_id = (int)($_POST['banner_id'] ?? 0);
            $title_ar = trim($_POST['title_ar'] ?? '');
            $title_en = trim($_POST['title_en'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $description_en = trim($_POST['description_en'] ?? '');
            $link_type = $_POST['link_type'] ?? 'none';
            $link_value = trim($_POST['link_value'] ?? '');
            $target = $_POST['target'] ?? 'both';
            $position = $_POST['position'] ?? 'home_top';
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = isset($_POST['status']) ? 1 : 0;
            
            $data = [
                'title_ar' => $title_ar,
                'title_en' => $title_en,
                'description_ar' => $description_ar,
                'description_en' => $description_en,
                'link_type' => $link_type,
                'link_value' => $link_value,
                'target' => $target,
                'position' => $position,
                'sort_order' => $sort_order,
                'status' => $status
            ];
            
            // رفع الصورة
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = upload_image($_FILES['image'], 'banners');
                if ($image_path) {
                    $data['image'] = $image_path;
                }
            }
            
            if ($action == 'add_banner') {
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('banners', $data);
                $msg = 'تم إضافة البانر بنجاح';
            } else {
                db_update('banners', $data, 'id = ?', [':id' => $banner_id]);
                $msg = 'تم تحديث البانر بنجاح';
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=banners', $msg, 'success');
            break;
            
        case 'delete_banner':
            $banner_id = (int)($_POST['banner_id'] ?? 0);
            db_delete('banners', 'id = ?', [$banner_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=banners', 'تم حذف البانر بنجاح', 'success');
            break;
            
        // Offers
        case 'add_offer':
        case 'update_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);
            $code = trim($_POST['code'] ?? '');
            $title_ar = trim($_POST['title_ar'] ?? '');
            $title_en = trim($_POST['title_en'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $offer_type = $_POST['offer_type'] ?? 'discount';
            $discount_value = (float)($_POST['discount_value'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $min_order = (float)($_POST['min_order'] ?? 0);
            $max_discount = (float)($_POST['max_discount'] ?? 0);
            $vendor_id = (int)($_POST['vendor_id'] ?? 0) ?: null;
            $usage_limit = (int)($_POST['usage_limit'] ?? 0) ?: null;
            $user_limit = (int)($_POST['user_limit'] ?? 1);
            $start_date = $_POST['start_date'] ?? date('Y-m-d H:i:s');
            $end_date = $_POST['end_date'] ?? date('Y-m-d H:i:s', strtotime('+30 days'));
            $status = isset($_POST['status']) ? 1 : 0;
            
            $data = [
                'code' => $code ?: null,
                'title_ar' => $title_ar,
                'title_en' => $title_en,
                'description_ar' => $description_ar,
                'offer_type' => $offer_type,
                'discount_value' => $discount_value,
                'discount_type' => $discount_type,
                'min_order' => $min_order,
                'max_discount' => $max_discount ?: null,
                'vendor_id' => $vendor_id,
                'usage_limit' => $usage_limit,
                'user_limit' => $user_limit,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'status' => $status
            ];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = upload_image($_FILES['image'], 'offers');
                if ($image_path) {
                    $data['image'] = $image_path;
                }
            }
            
            if ($action == 'add_offer') {
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('offers', $data);
                $msg = 'تم إضافة العرض بنجاح';
            } else {
                db_update('offers', $data, 'id = ?', [':id' => $offer_id]);
                $msg = 'تم تحديث العرض بنجاح';
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=offers', $msg, 'success');
            break;
            
        case 'delete_offer':
            $offer_id = (int)($_POST['offer_id'] ?? 0);
            db_delete('offers', 'id = ?', [$offer_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=offers', 'تم حذف العرض بنجاح', 'success');
            break;
            
        // Settings
        case 'update_settings':
            foreach ($_POST as $key => $value) {
                if (strpos($key, 'setting_') === 0) {
                    $setting_key = str_replace('setting_', '', $key);
                    
                    // رفع الشعار
                    if ($setting_key == 'logo' && isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $value = upload_image($_FILES['logo'], 'settings');
                    }
                    
                    // رفع الأيقونة
                    if ($setting_key == 'favicon' && isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                        $value = upload_image($_FILES['favicon'], 'settings');
                    }
                    
                    db_update('settings', ['setting_value' => $value], 'setting_key = ?', [':setting_key' => $setting_key]);
                }
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=settings', 'تم حفظ الإعدادات بنجاح', 'success');
            break;
            
        // Reports
        case 'update_report':
            $report_id = (int)($_POST['report_id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $admin_response = trim($_POST['admin_response'] ?? '');
            
            db_update('reports', [
                'status' => $status,
                'admin_response' => $admin_response,
                'resolved_by' => $_SESSION['admin_id'],
                'resolved_at' => $status == 'resolved' ? date('Y-m-d H:i:s') : null
            ], 'id = ?', [':id' => $report_id]);
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=reports', 'تم تحديث البلاغ بنجاح', 'success');
            break;
            
        // Loyalty
        case 'add_loyalty_rule':
        case 'update_loyalty_rule':
            $rule_id = (int)($_POST['rule_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $orders_count = (int)($_POST['orders_count'] ?? 0);
            $points_reward = (int)($_POST['points_reward'] ?? 0);
            $discount_reward = (float)($_POST['discount_reward'] ?? 0);
            $discount_type = $_POST['discount_type'] ?? 'fixed';
            $free_delivery = isset($_POST['free_delivery']) ? 1 : 0;
            $icon = trim($_POST['icon'] ?? '');
            $color = trim($_POST['color'] ?? '#FF6B35');
            $period = $_POST['period'] ?? 'monthly';
            $status = isset($_POST['status']) ? 1 : 0;
            
            $data = [
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'orders_count' => $orders_count,
                'points_reward' => $points_reward,
                'discount_reward' => $discount_reward,
                'discount_type' => $discount_type,
                'free_delivery' => $free_delivery,
                'icon' => $icon,
                'color' => $color,
                'period' => $period,
                'status' => $status
            ];
            
            if ($action == 'add_loyalty_rule') {
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('loyalty_rules', $data);
                $msg = 'تم إضافة قاعدة الولاء بنجاح';
            } else {
                db_update('loyalty_rules', $data, 'id = ?', [':id' => $rule_id]);
                $msg = 'تم تحديث قاعدة الولاء بنجاح';
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=loyalty', $msg, 'success');
            break;
            
        case 'delete_loyalty_rule':
            $rule_id = (int)($_POST['rule_id'] ?? 0);
            db_delete('loyalty_rules', 'id = ?', [$rule_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=loyalty', 'تم حذف قاعدة الولاء بنجاح', 'success');
            break;
            
        // Wheel
        case 'add_wheel_reward':
        case 'update_wheel_reward':
            $reward_id = (int)($_POST['reward_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $name_en = trim($_POST['name_en'] ?? '');
            $reward_type = $_POST['reward_type'] ?? 'points';
            $reward_value = trim($_POST['reward_value'] ?? '');
            $probability = (float)($_POST['probability'] ?? 10);
            $color = trim($_POST['color'] ?? '#FF6B35');
            $icon = trim($_POST['icon'] ?? 'fa-gift');
            $max_usage = (int)($_POST['max_usage'] ?? 0) ?: null;
            $status = isset($_POST['status']) ? 1 : 0;
            
            $data = [
                'name_ar' => $name_ar,
                'name_en' => $name_en,
                'reward_type' => $reward_type,
                'reward_value' => $reward_value,
                'probability' => $probability,
                'color' => $color,
                'icon' => $icon,
                'max_usage' => $max_usage,
                'status' => $status
            ];
            
            if ($action == 'add_wheel_reward') {
                $data['created_at'] = date('Y-m-d H:i:s');
                db_insert('lucky_wheel_rewards', $data);
                $msg = 'تم إضافة الجائزة بنجاح';
            } else {
                db_update('lucky_wheel_rewards', $data, 'id = ?', [':id' => $reward_id]);
                $msg = 'تم تحديث الجائزة بنجاح';
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=wheel', $msg, 'success');
            break;
            
        case 'delete_wheel_reward':
            $reward_id = (int)($_POST['reward_id'] ?? 0);
            db_delete('lucky_wheel_rewards', 'id = ?', [$reward_id]);
            redirect_with_message(BASE_URL . 'admin/index.php?section=wheel', 'تم حذف الجائزة بنجاح', 'success');
            break;
            
        // Notifications
        case 'send_notification':
            $user_type = $_POST['user_type'] ?? 'all';
            $user_id = (int)($_POST['user_id'] ?? 0);
            $title_ar = trim($_POST['title_ar'] ?? '');
            $body_ar = trim($_POST['body_ar'] ?? '');
            
            if ($user_type == 'all') {
                $users = db_fetch_all("SELECT id FROM users WHERE status = 1", []);
                foreach ($users as $user) {
                    send_notification($user['id'], 'user', $title_ar, $body_ar, 'system');
                }
            } elseif ($user_type == 'vendors') {
                $vendors = db_fetch_all("SELECT id FROM vendors WHERE status = 'approved'", []);
                foreach ($vendors as $vendor) {
                    send_notification($vendor['id'], 'vendor', $title_ar, $body_ar, 'system');
                }
            } elseif ($user_type == 'drivers') {
                $drivers = db_fetch_all("SELECT id FROM drivers WHERE status = 'approved'", []);
                foreach ($drivers as $driver) {
                    send_notification($driver['id'], 'driver', $title_ar, $body_ar, 'system');
                }
            } elseif ($user_id > 0) {
                send_notification($user_id, $user_type, $title_ar, $body_ar, 'system');
            }
            
            redirect_with_message(BASE_URL . 'admin/index.php?section=notifications', 'تم إرسال الإشعار بنجاح', 'success');
            break;
    }
}

// جلب الإحصائيات العامة للـ Dashboard
if ($section == 'dashboard' || $section == 'statistics') {
    $stats = [
        'total_users' => db_fetch("SELECT COUNT(*) as count FROM users")['count'] ?? 0,
        'active_users' => db_fetch("SELECT COUNT(*) as count FROM users WHERE status = 1 AND is_blocked = 0")['count'] ?? 0,
        'blocked_users' => db_fetch("SELECT COUNT(*) as count FROM users WHERE is_blocked = 1")['count'] ?? 0,
        'new_users_today' => db_fetch("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
        'total_vendors' => db_fetch("SELECT COUNT(*) as count FROM vendors WHERE status = 'approved'")['count'] ?? 0,
        'pending_vendors' => db_fetch("SELECT COUNT(*) as count FROM vendors WHERE status = 'pending'")['count'] ?? 0,
        'suspended_vendors' => db_fetch("SELECT COUNT(*) as count FROM vendors WHERE status = 'suspended'")['count'] ?? 0,
        'total_drivers' => db_fetch("SELECT COUNT(*) as count FROM drivers WHERE status = 'approved'")['count'] ?? 0,
        'pending_drivers' => db_fetch("SELECT COUNT(*) as count FROM drivers WHERE status = 'pending'")['count'] ?? 0,
        'online_drivers' => db_fetch("SELECT COUNT(*) as count FROM drivers WHERE is_online = 1")['count'] ?? 0,
        'total_orders' => db_fetch("SELECT COUNT(*) as count FROM orders")['count'] ?? 0,
        'orders_today' => db_fetch("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = CURDATE()")['count'] ?? 0,
        'orders_delivered' => db_fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'delivered'")['count'] ?? 0,
        'orders_cancelled' => db_fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'cancelled'")['count'] ?? 0,
        'total_revenue' => db_fetch("SELECT SUM(total) as total FROM orders WHERE status = 'delivered'")['total'] ?? 0,
        'revenue_today' => db_fetch("SELECT SUM(total) as total FROM orders WHERE status = 'delivered' AND DATE(created_at) = CURDATE()")['total'] ?? 0,
        'revenue_this_month' => db_fetch("SELECT SUM(total) as total FROM orders WHERE status = 'delivered' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())")['total'] ?? 0,
        'total_tips' => db_fetch("SELECT SUM(amount) as total FROM tips WHERE status = 'paid'")['total'] ?? 0,
        'pending_reports' => db_fetch("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")['count'] ?? 0,
        'total_ratings' => db_fetch("SELECT COUNT(*) as count FROM ratings")['count'] ?? 0,
        'avg_rating' => db_fetch("SELECT AVG(rating) as avg FROM ratings")['avg'] ?? 0
    ];
    
    // بيانات الرسم البياني للطلبات (آخر 7 أيام)
    $order_chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = db_fetch("SELECT COUNT(*) as count FROM orders WHERE DATE(created_at) = ?", [$date])['count'] ?? 0;
        $order_chart_data[] = $count;
    }
    
    // بيانات الرسم البياني للإيرادات (آخر 7 أيام)
    $revenue_chart_data = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $revenue = db_fetch("SELECT SUM(total) as total FROM orders WHERE status = 'delivered' AND DATE(created_at) = ?", [$date])['total'] ?? 0;
        $revenue_chart_data[] = round($revenue, 2);
    }
    
    // آخر الطلبات
    $recent_orders = db_fetch_all(
        "SELECT o.*, u.name as user_name, v.business_name as vendor_name 
         FROM orders o 
         JOIN users u ON o.user_id = u.id 
         JOIN vendors v ON o.vendor_id = v.id 
         ORDER BY o.created_at DESC LIMIT 10",
        []
    );
    
    // آخر المستخدمين المسجلين
    $recent_users = db_fetch_all(
        "SELECT * FROM users ORDER BY created_at DESC LIMIT 5",
        []
    );
    
    // أفضل المطاعم مبيعاً
    $top_vendors = db_fetch_all(
        "SELECT v.*, COUNT(o.id) as order_count, SUM(o.total) as total_revenue 
         FROM vendors v 
         JOIN orders o ON v.id = o.vendor_id 
         WHERE o.status = 'delivered' 
         GROUP BY v.id 
         ORDER BY order_count DESC LIMIT 5",
        []
    );
    
    // أفضل المناديب
    $top_drivers = db_fetch_all(
        "SELECT d.*, COUNT(o.id) as delivery_count, AVG(r.rating) as avg_rating 
         FROM drivers d 
         JOIN orders o ON d.id = o.driver_id 
         LEFT JOIN ratings r ON d.id = r.driver_id AND r.rated_for = 'driver' 
         WHERE o.status = 'delivered' 
         GROUP BY d.id 
         ORDER BY delivery_count DESC LIMIT 5",
        []
    );
}

// جلب البيانات حسب القسم
switch ($section) {
    case 'users':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        
        $where = "WHERE 1=1";
        $params = [];
        if ($search) {
            $where .= " AND (name LIKE ? OR phone LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $total_users = db_fetch("SELECT COUNT(*) as count FROM users $where", $params)['count'] ?? 0;
        $users = db_fetch_all(
            "SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_users / $limit);
        break;
        
    case 'vendors':
        $filter = isset($_GET['filter']) ? clean_input($_GET['filter']) : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = "";
        $params = [];
        if ($filter != 'all') {
            $where = "WHERE status = ?";
            $params[] = $filter;
        }
        
        $total_vendors = db_fetch("SELECT COUNT(*) as count FROM vendors $where", $params)['count'] ?? 0;
        $vendors = db_fetch_all(
            "SELECT v.*, z.name_ar as zone_name,
                    (SELECT COUNT(*) FROM orders WHERE vendor_id = v.id) as total_orders,
                    (SELECT SUM(total) FROM orders WHERE vendor_id = v.id AND status = 'delivered') as total_revenue
             FROM vendors v 
             LEFT JOIN zones z ON v.zone_id = z.id 
             $where 
             ORDER BY v.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_vendors / $limit);
        break;
        
    case 'drivers':
        $filter = isset($_GET['filter']) ? clean_input($_GET['filter']) : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = "";
        $params = [];
        if ($filter != 'all') {
            $where = "WHERE status = ?";
            $params[] = $filter;
        }
        
        $total_drivers = db_fetch("SELECT COUNT(*) as count FROM drivers $where", $params)['count'] ?? 0;
        $drivers = db_fetch_all(
            "SELECT d.*, z.name_ar as zone_name,
                    (SELECT COUNT(*) FROM orders WHERE driver_id = d.id AND status = 'delivered') as total_deliveries,
                    (SELECT AVG(rating) FROM ratings WHERE driver_id = d.id AND rated_for = 'driver') as avg_rating
             FROM drivers d 
             LEFT JOIN zones z ON d.zone_id = z.id 
             $where 
             ORDER BY d.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_drivers / $limit);
        break;
        
    case 'orders':
        $status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = "";
        $params = [];
        if ($status_filter != 'all') {
            $where = "WHERE o.status = ?";
            $params[] = $status_filter;
        }
        
        $total_orders = db_fetch("SELECT COUNT(*) as count FROM orders o $where", $params)['count'] ?? 0;
        $orders = db_fetch_all(
            "SELECT o.*, u.name as user_name, u.phone as user_phone,
                    v.business_name as vendor_name, d.name as driver_name
             FROM orders o 
             JOIN users u ON o.user_id = u.id 
             JOIN vendors v ON o.vendor_id = v.id 
             LEFT JOIN drivers d ON o.driver_id = d.id 
             $where 
             ORDER BY o.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_orders / $limit);
        break;
        
    case 'zones':
        $zones = db_fetch_all("SELECT * FROM zones ORDER BY city, name_ar", []);
        $areas = db_fetch_all(
            "SELECT a.*, z.name_ar as zone_name, z.city 
             FROM areas a 
             JOIN zones z ON a.zone_id = z.id 
             ORDER BY z.city, a.name_ar",
            []
        );
        $delivery_fees = db_fetch_all(
            "SELECT df.*, z.name_ar as zone_name, z.city 
             FROM delivery_fees df 
             JOIN zones z ON df.zone_id = z.id",
            []
        );
        break;
        
    case 'banners':
        $banners = db_fetch_all("SELECT * FROM banners ORDER BY position, sort_order", []);
        break;
        
    case 'offers':
        $offers = db_fetch_all(
            "SELECT o.*, v.business_name as vendor_name 
             FROM offers o 
             LEFT JOIN vendors v ON o.vendor_id = v.id 
             ORDER BY o.created_at DESC",
            []
        );
        $vendors_list = db_fetch_all("SELECT id, business_name FROM vendors WHERE status = 'approved' ORDER BY business_name", []);
        break;
        
    case 'ratings':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $total_ratings = db_fetch("SELECT COUNT(*) as count FROM ratings", [])['count'] ?? 0;
        $ratings = db_fetch_all(
            "SELECT r.*, u.name as user_name, v.business_name as vendor_name, d.name as driver_name 
             FROM ratings r 
             JOIN users u ON r.user_id = u.id 
             LEFT JOIN vendors v ON r.vendor_id = v.id 
             LEFT JOIN drivers d ON r.driver_id = d.id 
             ORDER BY r.created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        $total_pages = ceil($total_ratings / $limit);
        break;
        
    case 'reports':
        $filter = isset($_GET['filter']) ? clean_input($_GET['filter']) : 'pending';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = "";
        $params = [];
        if ($filter != 'all') {
            $where = "WHERE r.status = ?";
            $params[] = $filter;
        }
        
        $total_reports = db_fetch("SELECT COUNT(*) as count FROM reports r $where", $params)['count'] ?? 0;
        $reports = db_fetch_all(
            "SELECT r.*, u.name as user_name, u.phone as user_phone,
                    adm.name as admin_name
             FROM reports r 
             JOIN users u ON r.user_id = u.id 
             LEFT JOIN admins adm ON r.resolved_by = adm.id 
             $where 
             ORDER BY 
                CASE r.priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'high' THEN 2 
                    WHEN 'medium' THEN 3 
                    WHEN 'low' THEN 4 
                END,
                r.created_at DESC 
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_reports / $limit);
        break;
        
    case 'tips':
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $total_tips = db_fetch("SELECT COUNT(*) as count FROM tips", [])['count'] ?? 0;
        $tips = db_fetch_all(
            "SELECT t.*, u.name as user_name, d.name as driver_name, o.order_number 
             FROM tips t 
             JOIN users u ON t.user_id = u.id 
             JOIN drivers d ON t.driver_id = d.id 
             JOIN orders o ON t.order_id = o.id 
             ORDER BY t.created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        $total_pages = ceil($total_tips / $limit);
        
        // إحصائيات البقشيش
        $tip_stats = [
            'total_tips' => db_fetch("SELECT SUM(amount) as total FROM tips WHERE status = 'paid'")['total'] ?? 0,
            'avg_tip' => db_fetch("SELECT AVG(amount) as avg FROM tips WHERE status = 'paid'")['avg'] ?? 0,
            'total_tips_today' => db_fetch("SELECT SUM(amount) as total FROM tips WHERE status = 'paid' AND DATE(created_at) = CURDATE()")['total'] ?? 0
        ];
        break;
        
    case 'loyalty':
        $rules = db_fetch_all("SELECT * FROM loyalty_rules ORDER BY orders_count ASC", []);
        $logs = db_fetch_all(
            "SELECT ll.*, u.name as user_name, u.phone as user_phone, lr.name_ar as rule_name 
             FROM loyalty_logs ll 
             JOIN users u ON ll.user_id = u.id 
             LEFT JOIN loyalty_rules lr ON ll.rule_id = lr.id 
             ORDER BY ll.created_at DESC LIMIT 50",
            []
        );
        break;
        
    case 'wheel':
        $rewards = db_fetch_all("SELECT * FROM lucky_wheel_rewards ORDER BY probability DESC", []);
        $spins = db_fetch_all(
            "SELECT ws.*, u.name as user_name, lwr.name_ar as reward_name 
             FROM wheel_spins ws 
             JOIN users u ON ws.user_id = u.id 
             JOIN lucky_wheel_rewards lwr ON ws.reward_id = lwr.id 
             ORDER BY ws.created_at DESC LIMIT 50",
            []
        );
        
        // إعدادات الدولاب
        $wheel_settings = [
            'min_orders' => db_fetch("SELECT setting_value FROM settings WHERE setting_key = 'wheel_min_orders'")['setting_value'] ?? WHEEL_MIN_ORDERS_FOR_SPIN,
            'max_spins' => db_fetch("SELECT setting_value FROM settings WHERE setting_key = 'wheel_max_spins_per_day'")['setting_value'] ?? WHEEL_MAX_SPINS_PER_DAY
        ];
        break;
        
    case 'settings':
        $settings = db_fetch_all("SELECT * FROM settings ORDER BY setting_group, id", []);
        $grouped_settings = [];
        foreach ($settings as $setting) {
            $grouped_settings[$setting['setting_group']][] = $setting;
        }
        break;
        
    case 'statistics':
        // بيانات إضافية للإحصائيات المتقدمة
        $orders_by_month = db_fetch_all(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, SUM(total) as revenue 
             FROM orders WHERE status = 'delivered' 
             GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
             ORDER BY month DESC LIMIT 12",
            []
        );
        
        $orders_by_hour = db_fetch_all(
            "SELECT HOUR(created_at) as hour, COUNT(*) as count 
             FROM orders 
             GROUP BY HOUR(created_at) 
             ORDER BY hour",
            []
        );
        
        $users_by_city = db_fetch_all(
            "SELECT z.city, COUNT(DISTINCT u.id) as count 
             FROM users u 
             JOIN addresses a ON u.id = a.user_id 
             JOIN areas ar ON a.area_id = ar.id 
             JOIN zones z ON ar.zone_id = z.id 
             GROUP BY z.city",
            []
        );
        break;
}

// =====================================================
// بداية HTML
// =====================================================
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Panel CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/panel.css?v=<?= time() ?>">
    
    <style>
        /* تنسيقات إضافية للوحة الأدمن */
        .stat-card.large {
            grid-column: span 2;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 24px;
        }
        
        .quick-action {
            background: white;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid var(--panel-border);
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }
        
        .quick-action:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-color: var(--panel-primary);
        }
        
        .quick-action i {
            font-size: 32px;
            color: var(--panel-primary);
            margin-bottom: 12px;
        }
        
        .quick-action h4 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .quick-action p {
            font-size: 12px;
            color: #6B7280;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        
        .info-box {
            background: linear-gradient(135deg, var(--panel-primary) 0%, var(--panel-primary-light) 100%);
            border-radius: 16px;
            padding: 20px;
            color: white;
            margin-bottom: 24px;
        }
        
        .info-box h3 {
            color: white;
            margin-bottom: 12px;
        }
        
        .info-box p {
            opacity: 0.95;
        }
        
        .vendor-detail-row, .driver-detail-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }
        
        .detail-card {
            background: #F9FAFB;
            border-radius: 12px;
            padding: 16px;
        }
        
        .detail-card h4 {
            margin-bottom: 12px;
            color: var(--panel-primary);
        }
        
        .progress-stats {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .progress-circle {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: conic-gradient(var(--panel-primary) 0deg, var(--panel-primary) calc(360deg * 0.75), #E5E7EB calc(360deg * 0.75), #E5E7EB 360deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .progress-circle::before {
            content: '';
            position: absolute;
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
        }
        
        .progress-circle span {
            position: relative;
            z-index: 1;
            font-weight: 700;
            color: var(--panel-primary);
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .rating-big {
            font-size: 48px;
            font-weight: 800;
            color: var(--panel-primary);
        }
        
        .rating-stars-large i {
            font-size: 20px;
            color: #FBBF24;
        }
        
        /* Modal Large */
        .modal-xl {
            max-width: 900px;
        }
        
        /* Settings Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .setting-group {
            background: #F9FAFB;
            border-radius: 16px;
            padding: 20px;
        }
        
        .setting-group h3 {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #E5E7EB;
        }
        
        .color-picker-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .color-picker-wrapper input[type="color"] {
            width: 50px;
            height: 40px;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .logo-preview {
            width: 100px;
            height: 100px;
            border: 2px dashed #E5E7EB;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="panel-wrapper">
        <!-- Sidebar -->
        <div class="panel-sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">س</div>
                <div class="sidebar-brand">
                    <h2>سريع</h2>
                    <span>لوحة التحكم</span>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">الرئيسية</div>
                    <a href="?section=dashboard" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i>
                        <span>لوحة التحكم</span>
                    </a>
                    <a href="?section=statistics" class="nav-item <?= $section == 'statistics' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span>الإحصائيات</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">الإدارة</div>
                    <a href="?section=users" class="nav-item <?= $section == 'users' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i>
                        <span>العملاء</span>
                    </a>
                    <a href="?section=vendors" class="nav-item <?= $section == 'vendors' ? 'active' : '' ?>">
                        <i class="fas fa-store"></i>
                        <span>المطاعم والمتاجر</span>
                        <?php if ($stats['pending_vendors'] > 0): ?>
                        <span class="nav-badge"><?= $stats['pending_vendors'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?section=drivers" class="nav-item <?= $section == 'drivers' ? 'active' : '' ?>">
                        <i class="fas fa-motorcycle"></i>
                        <span>المناديب</span>
                        <?php if ($stats['pending_drivers'] > 0): ?>
                        <span class="nav-badge"><?= $stats['pending_drivers'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?section=orders" class="nav-item <?= $section == 'orders' ? 'active' : '' ?>">
                        <i class="fas fa-shopping-bag"></i>
                        <span>الطلبات</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">المحتوى</div>
                    <a href="?section=zones" class="nav-item <?= $section == 'zones' ? 'active' : '' ?>">
                        <i class="fas fa-map-pin"></i>
                        <span>المناطق والأسعار</span>
                    </a>
                    <a href="?section=banners" class="nav-item <?= $section == 'banners' ? 'active' : '' ?>">
                        <i class="fas fa-image"></i>
                        <span>البانرات</span>
                    </a>
                    <a href="?section=offers" class="nav-item <?= $section == 'offers' ? 'active' : '' ?>">
                        <i class="fas fa-tag"></i>
                        <span>العروض والخصومات</span>
                    </a>
                    <a href="?section=loyalty" class="nav-item <?= $section == 'loyalty' ? 'active' : '' ?>">
                        <i class="fas fa-crown"></i>
                        <span>برنامج الولاء</span>
                    </a>
                    <a href="?section=wheel" class="nav-item <?= $section == 'wheel' ? 'active' : '' ?>">
                        <i class="fas fa-ticket-alt"></i>
                        <span>دولاب الحظ</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">التفاعلات</div>
                    <a href="?section=ratings" class="nav-item <?= $section == 'ratings' ? 'active' : '' ?>">
                        <i class="fas fa-star"></i>
                        <span>التقييمات</span>
                    </a>
                    <a href="?section=reports" class="nav-item <?= $section == 'reports' ? 'active' : '' ?>">
                        <i class="fas fa-flag"></i>
                        <span>البلاغات</span>
                        <?php if ($stats['pending_reports'] > 0): ?>
                        <span class="nav-badge"><?= $stats['pending_reports'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?section=tips" class="nav-item <?= $section == 'tips' ? 'active' : '' ?>">
                        <i class="fas fa-hand-holding-heart"></i>
                        <span>البقشيش</span>
                    </a>
                    <a href="?section=notifications" class="nav-item <?= $section == 'notifications' ? 'active' : '' ?>">
                        <i class="fas fa-bell"></i>
                        <span>إرسال إشعارات</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">النظام</div>
                    <a href="?section=settings" class="nav-item <?= $section == 'settings' ? 'active' : '' ?>">
                        <i class="fas fa-cog"></i>
                        <span>الإعدادات</span>
                    </a>
                </div>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <?= mb_substr($admin['name'], 0, 1) ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?= escape($admin['name']) ?></div>
                        <div class="user-role"><?= $admin['role'] == 'super' ? 'مدير عام' : 'مدير' ?></div>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="panel-main">
            <header class="panel-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="mobileMenuToggle" style="display: none;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">
                        <?php
                        $titles = [
                            'dashboard' => 'لوحة التحكم',
                            'users' => 'إدارة العملاء',
                            'vendors' => 'إدارة المطاعم والمتاجر',
                            'drivers' => 'إدارة المناديب',
                            'orders' => 'إدارة الطلبات',
                            'zones' => 'المناطق ورسوم التوصيل',
                            'banners' => 'إدارة البانرات',
                            'offers' => 'العروض والخصومات',
                            'ratings' => 'التقييمات',
                            'reports' => 'البلاغات',
                            'tips' => 'إحصائيات البقشيش',
                            'loyalty' => 'برنامج الولاء',
                            'wheel' => 'دولاب الحظ',
                            'notifications' => 'إرسال إشعارات',
                            'settings' => 'إعدادات النظام',
                            'statistics' => 'الإحصائيات المتقدمة'
                        ];
                        echo $titles[$section] ?? 'لوحة التحكم';
                        ?>
                    </h1>
                </div>
                <div class="header-right">
                    <div class="header-search">
                        <input type="text" placeholder="بحث سريع...">
                        <i class="fas fa-search"></i>
                    </div>
                    <div class="header-notifications">
                        <button class="notification-btn" id="notificationBtn">
                            <i class="far fa-bell"></i>
                            <?php if ($stats['pending_reports'] > 0 || $stats['pending_vendors'] > 0): ?>
                            <span class="notification-badge"><?= $stats['pending_reports'] + $stats['pending_vendors'] + $stats['pending_drivers'] ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                    <div class="header-date">
                        <i class="far fa-calendar-alt"></i>
                        <span><?= date('d F Y') ?></span>
                    </div>
                </div>
            </header>
            
            <div class="panel-content">
                <?php
                // تضمين القسم المناسب
                $section_file = __DIR__ . '/sections/' . $section . '.php';
                if (file_exists($section_file)) {
                    include $section_file;
                } else {
                    // عرض القسم مباشرة
                    switch ($section) {
                        case 'dashboard':
                            include __DIR__ . '/sections/dashboard_content.php';
                            break;
                        default:
                            echo "<div class='panel-card'><div class='card-body'><p>قسم قيد التطوير...</p></div></div>";
                    }
                }
                
                // عرض المحتوى المضمن
                if ($section == 'dashboard' || $section == 'statistics') {
                    // تم تعريف المحتوى في الأعلى
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="panel-toast-container" id="panelToastContainer"></div>
    
    <!-- Panel JS -->
    <script src="<?= BASE_URL ?>assets/js/panel.js?v=<?= time() ?>"></script>
    
    <script>
    const BASE_URL = '<?= BASE_URL ?>';
    const CSRF_TOKEN = '<?= get_csrf_token() ?>';
    const CURRENT_SECTION = '<?= $section ?>';
    
    // دالة تسجيل الخروج
    function logout() {
        if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
            fetch(BASE_URL + 'api/handler.php?action=logout', {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TOKEN }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    window.location.href = BASE_URL + 'login.php';
                }
            });
        }
    }
    
    // دوال مساعدة
    function showToast(message, type = 'info') {
        Panel.showToast(message, type);
    }
    
    function openModal(modalId) {
        Panel.openModal(modalId);
    }
    
    function closeModal(modalId) {
        Panel.closeModal(modalId);
    }
    
    function confirmAction(message) {
        return confirm(message);
    }
    
    // إحصائيات Dashboard
    <?php if ($section == 'dashboard'): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // رسم بياني للطلبات
        const orderCtx = document.getElementById('ordersChart')?.getContext('2d');
        if (orderCtx) {
            new Chart(orderCtx, {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_map(function($i) { return date('d/m', strtotime("-$i days")); }, range(6, 0))) ?>,
                    datasets: [{
                        label: 'عدد الطلبات',
                        data: <?= json_encode($order_chart_data) ?>,
                        borderColor: '#FF6B35',
                        backgroundColor: 'rgba(255, 107, 53, 0.1)',
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#FF6B35',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' طلب';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#E5E7EB' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        
        // رسم بياني للإيرادات
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_map(function($i) { return date('d/m', strtotime("-$i days")); }, range(6, 0))) ?>,
                    datasets: [{
                        label: 'الإيرادات (ر.س)',
                        data: <?= json_encode($revenue_chart_data) ?>,
                        backgroundColor: '#10B981',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.parsed.y + ' ر.س';
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#E5E7EB' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        }
        
        // رسم بياني لحالات الطلبات
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['مكتمل', 'قيد التوصيل', 'قيد التحضير', 'جديد', 'ملغي'],
                    datasets: [{
                        data: [
                            <?= $stats['orders_delivered'] ?? 0 ?>,
                            <?= db_fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'on_way'")['count'] ?? 0 ?>,
                            <?= db_fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'preparing'")['count'] ?? 0 ?>,
                            <?= db_fetch("SELECT COUNT(*) as count FROM orders WHERE status = 'new'")['count'] ?? 0 ?>,
                            <?= $stats['orders_cancelled'] ?? 0 ?>
                        ],
                        backgroundColor: ['#10B981', '#3B82F6', '#F59E0B', '#FF6B35', '#EF4444'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    cutout: '60%'
                }
            });
        }
    });
    <?php endif; ?>
    </script>
    
    <!-- Flash Message -->
    <?php $flash = show_flash_message(); if ($flash): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast('<?= addslashes(strip_tags($flash)) ?>', 'success'); });</script>
    <?php endif; ?>
</body>
</html>
