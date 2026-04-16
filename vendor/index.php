<?php
/**
 * لوحة تحكم التاجر
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

// التحقق من صلاحية التاجر
if (!is_vendor()) {
    redirect(BASE_URL . 'login.php?type=vendor');
}

$vendor = get_current_vendor();
$section = isset($_GET['section']) ? clean_input($_GET['section']) : 'dashboard';

$allowed_sections = ['dashboard', 'orders', 'menu', 'settings'];

if (!in_array($section, $allowed_sections)) {
    $section = 'dashboard';
}

// معالجة طلبات POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleVendorPost();
}

$page_title = 'لوحة التاجر - ' . APP_NAME;

// =====================================================
// دوال مساعدة
// =====================================================

function renderVendorStatusBadge($status) {
    $badges = [
        'new' => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'جديد'],
        'accepted' => ['bg' => '#E9D5FF', 'color' => '#7C3AED', 'text' => 'تم القبول'],
        'preparing' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'قيد التحضير'],
        'ready' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'جاهز'],
        'on_way' => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'خرج للتوصيل'],
        'delivered' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'تم التوصيل'],
        'cancelled' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'ملغي']
    ];
    
    $badge = $badges[$status] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'text' => $status];
    
    return "<span class='status-badge' style='background: {$badge['bg']}; color: {$badge['color']};'>{$badge['text']}</span>";
}

function handleVendorPost() {
    global $vendor;
    $action = $_POST['action'] ?? '';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message(BASE_URL . 'vendor/index.php', 'رمز الحماية غير صالح، حدّث الصفحة وحاول مرة أخرى', 'error');
    }
    
    switch ($action) {
        // تحديث حالة الطلب
        case 'update_order_status':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            
            $order = db_fetch(
                "SELECT * FROM orders WHERE id = ? AND vendor_id = ?",
                [$order_id, $vendor['id']]
            );
            
            if (!$order) {
                redirect_with_message(BASE_URL . 'vendor/index.php?section=orders', 'الطلب غير موجود', 'error');
            }
            
            db_update('orders', ['status' => $status], 'id = ?', [':id' => $order_id]);
            
            db_insert('order_status_logs', [
                'order_id' => $order_id,
                'status' => $status,
                'changed_by' => 'vendor',
                'changed_by_id' => $vendor['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // إرسال إشعار للعميل
            send_notification(
                $order['user_id'],
                'user',
                'تم تحديث حالة طلبك',
                "طلبك رقم #{$order['order_number']} أصبح " . get_order_status_arabic($status),
                'order',
                $order_id
            );
            
            redirect_with_message(BASE_URL . 'vendor/index.php?section=orders', 'تم تحديث حالة الطلب', 'success');
            break;
            
        // إضافة قسم
        case 'add_category':
            $name_ar = trim($_POST['name_ar'] ?? '');
            
            db_insert('categories', [
                'vendor_id' => $vendor['id'],
                'name_ar' => $name_ar,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم إضافة القسم بنجاح', 'success');
            break;
            
        // تحديث قسم
        case 'update_category':
            $category_id = (int)($_POST['category_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $status = isset($_POST['status']) ? 1 : 0;
            
            db_update('categories', [
                'name_ar' => $name_ar,
                'status' => $status
            ], 'id = ? AND vendor_id = ?', [':id' => $category_id, ':vendor_id' => $vendor['id']]);
            
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم تحديث القسم', 'success');
            break;
            
        // حذف قسم
        case 'delete_category':
            $category_id = (int)($_POST['category_id'] ?? 0);
            db_delete('categories', 'id = ? AND vendor_id = ?', [$category_id, $vendor['id']]);
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم حذف القسم', 'success');
            break;
            
        // إضافة منتج
        case 'add_product':
            $category_id = (int)($_POST['category_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $discount_price = (float)($_POST['discount_price'] ?? 0) ?: null;
            
            $data = [
                'vendor_id' => $vendor['id'],
                'category_id' => $category_id ?: null,
                'name_ar' => $name_ar,
                'description_ar' => $description_ar,
                'price' => $price,
                'discount_price' => $discount_price,
                'is_available' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = upload_image($_FILES['image'], 'products');
                if ($image_path) $data['image'] = $image_path;
            }
            
            db_insert('products', $data);
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم إضافة المنتج بنجاح', 'success');
            break;
            
        // تحديث منتج
        case 'update_product':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $category_id = (int)($_POST['category_id'] ?? 0);
            $name_ar = trim($_POST['name_ar'] ?? '');
            $description_ar = trim($_POST['description_ar'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $discount_price = (float)($_POST['discount_price'] ?? 0) ?: null;
            $is_available = isset($_POST['is_available']) ? 1 : 0;
            
            $data = [
                'category_id' => $category_id ?: null,
                'name_ar' => $name_ar,
                'description_ar' => $description_ar,
                'price' => $price,
                'discount_price' => $discount_price,
                'is_available' => $is_available
            ];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $image_path = upload_image($_FILES['image'], 'products');
                if ($image_path) $data['image'] = $image_path;
            }
            
            db_update('products', $data, 'id = ? AND vendor_id = ?', [':id' => $product_id, ':vendor_id' => $vendor['id']]);
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم تحديث المنتج', 'success');
            break;
            
        // حذف منتج
        case 'delete_product':
            $product_id = (int)($_POST['product_id'] ?? 0);
            db_delete('products', 'id = ? AND vendor_id = ?', [$product_id, $vendor['id']]);
            redirect_with_message(BASE_URL . 'vendor/index.php?section=menu', 'تم حذف المنتج', 'success');
            break;
            
        // تحديث إعدادات المتجر
        case 'update_settings':
            $business_name = trim($_POST['business_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $min_order = (float)($_POST['min_order'] ?? 0);
            $delivery_time = trim($_POST['delivery_time'] ?? '');
            $is_open = isset($_POST['is_open']) ? 1 : 0;
            
            $data = [
                'business_name' => $business_name,
                'description' => $description,
                'min_order' => $min_order,
                'delivery_time' => $delivery_time,
                'is_open' => $is_open
            ];
            
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $logo_path = upload_image($_FILES['logo'], 'vendors');
                if ($logo_path) $data['logo'] = $logo_path;
            }
            
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
                $cover_path = upload_image($_FILES['cover'], 'vendors');
                if ($cover_path) $data['cover'] = $cover_path;
            }
            
            db_update('vendors', $data, 'id = ?', [':id' => $vendor['id']]);
            redirect_with_message(BASE_URL . 'vendor/index.php?section=settings', 'تم حفظ الإعدادات', 'success');
            break;
    }
}

// جلب البيانات حسب القسم
switch ($section) {
    case 'dashboard':
        // إحصائيات اليوم
        $today_stats = db_fetch(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new_orders,
                SUM(CASE WHEN status IN ('accepted', 'preparing') THEN 1 ELSE 0 END) as active_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as today_revenue
             FROM orders 
             WHERE vendor_id = ? AND DATE(created_at) = CURDATE()",
            [$vendor['id']]
        );
        
        // إحصائيات عامة
        $total_stats = db_fetch(
            "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'delivered' THEN total ELSE NULL END) as avg_order_value
             FROM orders 
             WHERE vendor_id = ?",
            [$vendor['id']]
        );
        
        // الطلبات الجديدة
        $new_orders = db_fetch_all(
            "SELECT o.*, u.name as user_name, u.phone as user_phone,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
             FROM orders o
             JOIN users u ON o.user_id = u.id
             WHERE o.vendor_id = ? AND o.status = 'new'
             ORDER BY o.created_at ASC
             LIMIT 10",
            [$vendor['id']]
        );
        
        // الطلبات النشطة
        $active_orders = db_fetch_all(
            "SELECT o.*, u.name as user_name,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
             FROM orders o
             JOIN users u ON o.user_id = u.id
             WHERE o.vendor_id = ? AND o.status IN ('accepted', 'preparing')
             ORDER BY o.created_at ASC
             LIMIT 10",
            [$vendor['id']]
        );
        break;
        
    case 'orders':
        $status_filter = isset($_GET['status']) ? clean_input($_GET['status']) : 'all';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $where = "WHERE o.vendor_id = ?";
        $params = [$vendor['id']];
        
        if ($status_filter != 'all') {
            $where .= " AND o.status = ?";
            $params[] = $status_filter;
        }
        
        $total_orders = db_fetch("SELECT COUNT(*) as count FROM orders o $where", $params)['count'] ?? 0;
        $orders = db_fetch_all(
            "SELECT o.*, u.name as user_name, u.phone as user_phone,
                    d.name as driver_name, d.phone as driver_phone,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
             FROM orders o
             JOIN users u ON o.user_id = u.id
             LEFT JOIN drivers d ON o.driver_id = d.id
             $where
             ORDER BY o.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$limit, $offset])
        );
        $total_pages = ceil($total_orders / $limit);
        break;
        
    case 'menu':
        $categories = db_fetch_all(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM products WHERE category_id = c.id) as products_count
             FROM categories c
             WHERE c.vendor_id = ?
             ORDER BY c.sort_order ASC, c.name_ar ASC",
            [$vendor['id']]
        );
        
        $products = db_fetch_all(
            "SELECT p.*, c.name_ar as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.vendor_id = ?
             ORDER BY c.name_ar, p.name_ar",
            [$vendor['id']]
        );
        break;
        
    case 'settings':
        // البيانات موجودة في $vendor
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $page_title ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #F8F9FC;
            direction: rtl;
            padding-bottom: 80px;
        }
        
        /* Header */
        .vendor-header {
            background: linear-gradient(135deg, #FF6B35 0%, #FF8F65 100%);
            color: white;
            padding: 20px 16px 30px;
            border-radius: 0 0 30px 30px;
            position: relative;
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .vendor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .vendor-logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 3px solid rgba(255,255,255,0.3);
        }
        
        .vendor-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .vendor-name h2 {
            font-size: 18px;
            margin-bottom: 4px;
        }
        
        .vendor-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            opacity: 0.9;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10B981;
        }
        
        .status-dot.closed {
            background: #EF4444;
        }
        
        .logout-btn {
            width: 44px;
            height: 44px;
            background: rgba(255,255,255,0.2);
            border: none;
            border-radius: 14px;
            color: white;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Stats Cards */
        .stats-row {
            display: flex;
            gap: 12px;
            margin-top: -10px;
            padding: 0 16px;
        }
        
        .stat-card {
            flex: 1;
            background: white;
            border-radius: 16px;
            padding: 16px 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .stat-icon {
            width: 40px;
            height: 40px;
            background: #FFF1EB;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FF6B35;
            font-size: 20px;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 22px;
            font-weight: 800;
            color: #1F2937;
        }
        
        .stat-label {
            font-size: 12px;
            color: #6B7280;
        }
        
        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            padding: 8px 16px;
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
            border-radius: 25px 25px 0 0;
            max-width: 500px;
            margin: 0 auto;
        }
        
        .nav-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px;
            color: #9CA3AF;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s;
        }
        
        .nav-item i {
            font-size: 22px;
        }
        
        .nav-item.active {
            background: #FFF1EB;
            color: #FF6B35;
        }
        
        /* Content */
        .content {
            padding: 20px 16px;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }
        
        .section-title h3 {
            font-size: 18px;
            color: #1F2937;
        }
        
        /* Order Card */
        .order-card {
            background: white;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            border: 1px solid #F3F4F6;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .order-number {
            font-weight: 700;
            color: #FF6B35;
        }
        
        .order-time {
            font-size: 12px;
            color: #9CA3AF;
        }
        
        .order-customer {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            background: #F3F4F6;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
            font-weight: 700;
        }
        
        .customer-info h4 {
            font-size: 15px;
            margin-bottom: 2px;
        }
        
        .customer-info p {
            font-size: 13px;
            color: #6B7280;
        }
        
        .order-details {
            display: flex;
            gap: 15px;
            padding: 12px 0;
            border-top: 1px solid #F3F4F6;
            border-bottom: 1px solid #F3F4F6;
            margin-bottom: 12px;
        }
        
        .order-detail {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #6B7280;
        }
        
        .order-total {
            font-weight: 700;
            font-size: 16px;
            color: #1F2937;
        }
        
        .order-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn {
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 13px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            font-family: 'Cairo', sans-serif;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #FF6B35;
            color: white;
            flex: 1;
            justify-content: center;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid #E5E7EB;
            color: #6B7280;
        }
        
        .btn-success {
            background: #10B981;
            color: white;
        }
        
        .btn-danger {
            background: #EF4444;
            color: white;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 12px;
        }
        
        .btn-block {
            width: 100%;
            justify-content: center;
        }
        
        /* Product Item */
        .product-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: white;
            border-radius: 12px;
            margin-bottom: 8px;
            border: 1px solid #F3F4F6;
        }
        
        .product-image {
            width: 50px;
            height: 50px;
            background: #F3F4F6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9CA3AF;
            overflow: hidden;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info {
            flex: 1;
        }
        
        .product-name {
            font-weight: 700;
            margin-bottom: 2px;
        }
        
        .product-price {
            color: #FF6B35;
            font-weight: 600;
            font-size: 14px;
        }
        
        .product-actions {
            display: flex;
            gap: 6px;
        }
        
        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F3F4F6;
            border: none;
            color: #6B7280;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .icon-btn:hover {
            background: #FF6B35;
            color: white;
        }
        
        .icon-btn.danger:hover {
            background: #EF4444;
        }
        
        /* Category Section */
        .category-section {
            margin-bottom: 24px;
        }
        
        .category-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .category-title {
            font-weight: 700;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .category-count {
            background: #F3F4F6;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 12px;
            color: #6B7280;
        }
        
        /* Modal */
        .modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
        }
        
        .modal-content {
            position: relative;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            background: white;
            border-radius: 30px 30px 0 0;
            overflow: hidden;
            transform: translateY(100%);
            transition: transform 0.3s;
            margin: 0 auto;
        }
        
        .modal.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #F3F4F6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .modal-header h3 {
            font-size: 18px;
        }
        
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F3F4F6;
            border: none;
            font-size: 20px;
            color: #6B7280;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 20px;
            overflow-y: auto;
            max-height: calc(90vh - 70px);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            font-size: 14px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 3px rgba(255,107,53,0.1);
        }
        
        .form-row {
            display: flex;
            gap: 12px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-check input {
            width: 20px;
            height: 20px;
            accent-color: #FF6B35;
        }
        
        /* Toast */
        .toast-container {
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        }
        
        .toast {
            background: white;
            border-radius: 16px;
            padding: 16px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateY(-100%);
            opacity: 0;
            transition: all 0.3s;
            pointer-events: auto;
            border-right: 4px solid;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast-success { border-right-color: #10B981; }
        .toast-success i { color: #10B981; }
        .toast-error { border-right-color: #EF4444; }
        .toast-error i { color: #EF4444; }
        
        .toast span {
            flex: 1;
            font-weight: 500;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #9CA3AF;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        /* Settings */
        .settings-section {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #F3F4F6;
        }
        
        .settings-section h4 {
            margin-bottom: 16px;
            color: #1F2937;
        }
        
        .logo-upload {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .logo-preview {
            width: 80px;
            height: 80px;
            border: 2px dashed #E5E7EB;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        .logo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 20px;
        }
        
        .page-item {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            color: #6B7280;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .page-item.active {
            background: #FF6B35;
            border-color: #FF6B35;
            color: white;
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="vendor-header">
    <div class="header-top">
        <div class="vendor-info">
            <div class="vendor-logo">
                <?php if ($vendor['logo']): ?>
                <img src="<?= BASE_URL . $vendor['logo'] ?>" alt="<?= escape($vendor['business_name']) ?>">
                <?php else: ?>
                <i class="fas fa-store" style="color: #FF6B35; font-size: 24px;"></i>
                <?php endif; ?>
            </div>
            <div class="vendor-name">
                <h2><?= escape($vendor['business_name']) ?></h2>
                <div class="vendor-status">
                    <span class="status-dot <?= $vendor['is_open'] ? '' : 'closed' ?>"></span>
                    <span><?= $vendor['is_open'] ? 'مفتوح' : 'مغلق' ?></span>
                </div>
            </div>
        </div>
        <button class="logout-btn" onclick="logout()">
            <i class="fas fa-sign-out-alt"></i>
        </button>
    </div>
</div>

<!-- Stats Row -->
<?php if ($section == 'dashboard'): ?>
<div class="stats-row">
    <div class="stat-card">
        <div class="stat-icon">
            <i class="fas fa-shopping-bag"></i>
        </div>
        <div class="stat-value"><?= $today_stats['total_orders'] ?? 0 ?></div>
        <div class="stat-label">طلبات اليوم</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #D1FAE5; color: #059669;">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-value"><?= $today_stats['completed_orders'] ?? 0 ?></div>
        <div class="stat-label">مكتملة</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #FEF3C7; color: #D97706;">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-value"><?= format_price($today_stats['today_revenue'] ?? 0, false) ?></div>
        <div class="stat-label">إيرادات اليوم</div>
    </div>
</div>
<?php endif; ?>

<!-- Content -->
<div class="content">
    <?php
    // =====================================================
    // Dashboard Section
    // =====================================================
    if ($section == 'dashboard'):
    ?>
    
    <!-- طلبات جديدة -->
    <div class="section-title">
        <h3><i class="fas fa-bell" style="color: #FF6B35; margin-left: 8px;"></i> طلبات جديدة</h3>
        <?php if (!empty($new_orders)): ?>
        <span class="category-count"><?= count($new_orders) ?></span>
        <?php endif; ?>
    </div>
    
    <?php if (empty($new_orders)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>لا توجد طلبات جديدة</p>
    </div>
    <?php else: ?>
    <?php foreach ($new_orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <span class="order-number">#<?= $order['order_number'] ?></span>
            <span class="order-time"><?= time_ago($order['created_at']) ?></span>
        </div>
        <div class="order-customer">
            <div class="customer-avatar"><?= mb_substr($order['user_name'], 0, 1) ?></div>
            <div class="customer-info">
                <h4><?= escape($order['user_name']) ?></h4>
                <p><?= format_phone_display($order['user_phone']) ?></p>
            </div>
        </div>
        <div class="order-details">
            <div class="order-detail">
                <i class="fas fa-shopping-cart"></i>
                <span><?= $order['items_count'] ?> منتجات</span>
            </div>
            <div class="order-detail">
                <i class="fas fa-tag"></i>
                <span class="order-total"><?= format_price($order['total']) ?></span>
            </div>
        </div>
        <div class="order-actions">
            <form method="POST" style="display: flex; gap: 8px; width: 100%;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="status" value="accepted" class="btn btn-primary">
                    <i class="fas fa-check"></i> قبول
                </button>
                <button type="submit" name="status" value="cancelled" class="btn btn-outline">
                    <i class="fas fa-times"></i> رفض
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- طلبات نشطة -->
    <?php if (!empty($active_orders)): ?>
    <div class="section-title" style="margin-top: 24px;">
        <h3><i class="fas fa-clock" style="color: #D97706; margin-left: 8px;"></i> طلبات قيد التحضير</h3>
    </div>
    
    <?php foreach ($active_orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <span class="order-number">#<?= $order['order_number'] ?></span>
            <?= renderVendorStatusBadge($order['status']) ?>
        </div>
        <div class="order-customer">
            <div class="customer-avatar"><?= mb_substr($order['user_name'], 0, 1) ?></div>
            <div class="customer-info">
                <h4><?= escape($order['user_name']) ?></h4>
            </div>
        </div>
        <div class="order-details">
            <div class="order-detail">
                <i class="fas fa-shopping-cart"></i>
                <span><?= $order['items_count'] ?> منتجات</span>
            </div>
            <div class="order-detail">
                <i class="fas fa-tag"></i>
                <span class="order-total"><?= format_price($order['total']) ?></span>
            </div>
        </div>
        <div class="order-actions">
            <form method="POST" style="width: 100%;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="status" value="ready" class="btn btn-success btn-block">
                    <i class="fas fa-check-circle"></i> جاهز للتوصيل
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <?php
    // =====================================================
    // Orders Section
    // =====================================================
    elseif ($section == 'orders'):
    ?>
    
    <div class="section-title">
        <h3><i class="fas fa-list" style="color: #FF6B35; margin-left: 8px;"></i> جميع الطلبات</h3>
        <select class="form-control" style="width: auto; padding: 8px 16px;" onchange="window.location.href='?section=orders&status=' + this.value">
            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>الكل</option>
            <option value="new" <?= $status_filter == 'new' ? 'selected' : '' ?>>جديد</option>
            <option value="accepted" <?= $status_filter == 'accepted' ? 'selected' : '' ?>>تم القبول</option>
            <option value="preparing" <?= $status_filter == 'preparing' ? 'selected' : '' ?>>قيد التحضير</option>
            <option value="ready" <?= $status_filter == 'ready' ? 'selected' : '' ?>>جاهز</option>
            <option value="delivered" <?= $status_filter == 'delivered' ? 'selected' : '' ?>>تم التوصيل</option>
        </select>
    </div>
    
    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <i class="fas fa-receipt"></i>
        <p>لا توجد طلبات</p>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $order): ?>
    <div class="order-card">
        <div class="order-header">
            <span class="order-number">#<?= $order['order_number'] ?></span>
            <?= renderVendorStatusBadge($order['status']) ?>
        </div>
        <div class="order-customer">
            <div class="customer-avatar"><?= mb_substr($order['user_name'], 0, 1) ?></div>
            <div class="customer-info">
                <h4><?= escape($order['user_name']) ?></h4>
                <p><?= $order['driver_name'] ? '🚚 ' . escape($order['driver_name']) : 'في انتظار مندوب' ?></p>
            </div>
        </div>
        <div class="order-details">
            <div class="order-detail">
                <i class="fas fa-shopping-cart"></i>
                <span><?= $order['items_count'] ?> منتجات</span>
            </div>
            <div class="order-detail">
                <i class="fas fa-tag"></i>
                <span class="order-total"><?= format_price($order['total']) ?></span>
            </div>
        </div>
        <div class="order-actions">
            <button class="btn btn-outline btn-sm" onclick="viewOrderDetails(<?= $order['id'] ?>)">
                <i class="fas fa-eye"></i> تفاصيل
            </button>
            <?php if ($order['status'] == 'new'): ?>
            <form method="POST" style="display: contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="status" value="accepted" class="btn btn-primary btn-sm">قبول</button>
                <button type="submit" name="status" value="cancelled" class="btn btn-outline btn-sm">رفض</button>
            </form>
            <?php elseif ($order['status'] == 'accepted'): ?>
            <form method="POST" style="display: contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="status" value="preparing" class="btn btn-primary btn-sm">بدء التحضير</button>
            </form>
            <?php elseif ($order['status'] == 'preparing'): ?>
            <form method="POST" style="display: contents;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <button type="submit" name="status" value="ready" class="btn btn-success btn-sm">جاهز</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?section=orders&status=<?= $status_filter ?>&page=<?= $i ?>" class="page-item <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <?php
    // =====================================================
    // Menu Section
    // =====================================================
    elseif ($section == 'menu'):
    ?>
    
    <div class="section-title">
        <h3><i class="fas fa-utensils" style="color: #FF6B35; margin-left: 8px;"></i> الأقسام</h3>
        <button class="btn btn-outline btn-sm" onclick="openModal('addCategoryModal')">
            <i class="fas fa-plus"></i> إضافة
        </button>
    </div>
    
    <?php foreach ($categories as $category): ?>
    <div class="category-section">
        <div class="category-header">
            <div class="category-title">
                <?= escape($category['name_ar']) ?>
                <span class="category-count"><?= $category['products_count'] ?></span>
            </div>
            <div style="display: flex; gap: 6px;">
                <button class="icon-btn" onclick="editCategory(<?= $category['id'] ?>, '<?= escape($category['name_ar']) ?>', <?= $category['status'] ?>)">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="icon-btn danger" onclick="deleteCategory(<?= $category['id'] ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        
        <?php
        $cat_products = array_filter($products, function($p) use ($category) {
            return $p['category_id'] == $category['id'];
        });
        ?>
        
        <?php foreach ($cat_products as $product): ?>
        <div class="product-item">
            <div class="product-image">
                <?php if ($product['image']): ?>
                <img src="<?= BASE_URL . $product['image'] ?>" alt="<?= escape($product['name_ar']) ?>">
                <?php else: ?>
                <i class="fas fa-box"></i>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <div class="product-name">
                    <?= escape($product['name_ar']) ?>
                    <?php if (!$product['is_available']): ?>
                    <span style="color: #EF4444; font-size: 11px; margin-right: 6px;">(غير متوفر)</span>
                    <?php endif; ?>
                </div>
                <div class="product-price">
                    <?php if ($product['discount_price']): ?>
                    <span><?= format_price($product['discount_price']) ?></span>
                    <span style="text-decoration: line-through; color: #9CA3AF; font-size: 12px; margin-right: 6px;"><?= format_price($product['price']) ?></span>
                    <?php else: ?>
                    <?= format_price($product['price']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="product-actions">
                <button class="icon-btn" onclick="editProduct(<?= $product['id'] ?>)">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="icon-btn danger" onclick="deleteProduct(<?= $product['id'] ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        
        <button class="btn btn-outline btn-block btn-sm" style="margin-top: 8px;" onclick="openAddProductModal(<?= $category['id'] ?>)">
            <i class="fas fa-plus"></i> إضافة منتج
        </button>
    </div>
    <?php endforeach; ?>
    
    <!-- منتجات بدون قسم -->
    <?php
    $uncategorized = array_filter($products, function($p) {
        return empty($p['category_id']);
    });
    ?>
    
    <?php if (!empty($uncategorized)): ?>
    <div class="category-section">
        <div class="category-header">
            <div class="category-title">
                بدون قسم
                <span class="category-count"><?= count($uncategorized) ?></span>
            </div>
        </div>
        
        <?php foreach ($uncategorized as $product): ?>
        <div class="product-item">
            <div class="product-image">
                <?php if ($product['image']): ?>
                <img src="<?= BASE_URL . $product['image'] ?>" alt="<?= escape($product['name_ar']) ?>">
                <?php else: ?>
                <i class="fas fa-box"></i>
                <?php endif; ?>
            </div>
            <div class="product-info">
                <div class="product-name"><?= escape($product['name_ar']) ?></div>
                <div class="product-price"><?= format_price($product['price']) ?></div>
            </div>
            <div class="product-actions">
                <button class="icon-btn" onclick="editProduct(<?= $product['id'] ?>)">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="icon-btn danger" onclick="deleteProduct(<?= $product['id'] ?>)">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <button class="btn btn-primary btn-block" style="margin-top: 20px;" onclick="openAddProductModal(0)">
        <i class="fas fa-plus"></i> إضافة منتج جديد
    </button>
    
    <?php
    // =====================================================
    // Settings Section
    // =====================================================
    elseif ($section == 'settings'):
    ?>
    
    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_settings">
        
        <div class="settings-section">
            <h4><i class="fas fa-store"></i> معلومات المتجر</h4>
            
            <div class="form-group">
                <label>اسم المتجر</label>
                <input type="text" name="business_name" class="form-control" value="<?= escape($vendor['business_name']) ?>" required>
            </div>
            
            <div class="form-group">
                <label>وصف المتجر</label>
                <textarea name="description" class="form-control" rows="3"><?= escape($vendor['description']) ?></textarea>
            </div>
            
            <div class="form-group">
                <label>الشعار</label>
                <div class="logo-upload">
                    <div class="logo-preview">
                        <?php if ($vendor['logo']): ?>
                        <img src="<?= BASE_URL . $vendor['logo'] ?>" alt="Logo">
                        <?php else: ?>
                        <i class="fas fa-store" style="font-size: 32px; color: #9CA3AF;"></i>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="logo" accept="image/*">
                </div>
            </div>
            
            <div class="form-group">
                <label>صورة الغلاف</label>
                <input type="file" name="cover" accept="image/*">
            </div>
        </div>
        
        <div class="settings-section">
            <h4><i class="fas fa-cog"></i> إعدادات الطلب</h4>
            
            <div class="form-row">
                <div class="form-group">
                    <label>الحد الأدنى للطلب (ر.س)</label>
                    <input type="number" name="min_order" class="form-control" value="<?= $vendor['min_order'] ?>" min="0" step="0.01">
                </div>
                <div class="form-group">
                    <label>وقت التوصيل التقديري</label>
                    <input type="text" name="delivery_time" class="form-control" value="<?= escape($vendor['delivery_time'] ?: '30-45 دقيقة') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="is_open" value="1" <?= $vendor['is_open'] ? 'checked' : '' ?>>
                    <span>المتجر مفتوح ويستقبل طلبات</span>
                </label>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary btn-block">
            <i class="fas fa-save"></i> حفظ الإعدادات
        </button>
    </form>
    
    <?php endif; ?>
</div>

<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="?section=dashboard" class="nav-item <?= $section == 'dashboard' ? 'active' : '' ?>">
        <i class="fas fa-home"></i>
        <span>الرئيسية</span>
    </a>
    <a href="?section=orders" class="nav-item <?= $section == 'orders' ? 'active' : '' ?>">
        <i class="fas fa-receipt"></i>
        <span>الطلبات</span>
    </a>
    <a href="?section=menu" class="nav-item <?= $section == 'menu' ? 'active' : '' ?>">
        <i class="fas fa-utensils"></i>
        <span>القائمة</span>
    </a>
    <a href="?section=settings" class="nav-item <?= $section == 'settings' ? 'active' : '' ?>">
        <i class="fas fa-cog"></i>
        <span>الإعدادات</span>
    </a>
</div>

<!-- Add Category Modal -->
<div class="modal" id="addCategoryModal">
    <div class="modal-overlay" onclick="closeModal('addCategoryModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>إضافة قسم جديد</h3>
            <button class="modal-close" onclick="closeModal('addCategoryModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_category">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>اسم القسم</label>
                    <input type="text" name="name_ar" class="form-control" required>
                </div>
            </div>
            
            <div class="modal-body" style="padding-top: 0;">
                <button type="submit" class="btn btn-primary btn-block">إضافة</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal" id="addProductModal">
    <div class="modal-overlay" onclick="closeModal('addProductModal')"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="productModalTitle">إضافة منتج</h3>
            <button class="modal-close" onclick="closeModal('addProductModal')">&times;</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" id="productAction" value="add_product">
            <input type="hidden" name="product_id" id="productId">
            
            <div class="modal-body">
                <div class="form-group">
                    <label>القسم</label>
                    <select name="category_id" id="productCategory" class="form-control">
                        <option value="">بدون قسم</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= escape($cat['name_ar']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>اسم المنتج</label>
                    <input type="text" name="name_ar" id="productName" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>الوصف</label>
                    <textarea name="description_ar" id="productDesc" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>السعر</label>
                        <input type="number" name="price" id="productPrice" class="form-control" min="0" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>سعر الخصم</label>
                        <input type="number" name="discount_price" id="productDiscount" class="form-control" min="0" step="0.01">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الصورة</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                
                <div class="form-group" id="productAvailableGroup">
                    <label class="form-check">
                        <input type="checkbox" name="is_available" id="productAvailable" value="1" checked>
                        <span>متوفر</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-body" style="padding-top: 0;">
                <button type="submit" class="btn btn-primary btn-block">حفظ</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= get_csrf_token() ?>';

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

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function editCategory(id, name, status) {
    // يمكن إضافة مودال تعديل
    alert('تعديل القسم: ' + name);
}

function deleteCategory(id) {
    if (confirm('هل أنت متأكد من حذف هذا القسم؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="category_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function openAddProductModal(categoryId) {
    document.getElementById('productModalTitle').textContent = 'إضافة منتج';
    document.getElementById('productAction').value = 'add_product';
    document.getElementById('productId').value = '';
    document.getElementById('productCategory').value = categoryId;
    document.getElementById('productName').value = '';
    document.getElementById('productDesc').value = '';
    document.getElementById('productPrice').value = '';
    document.getElementById('productDiscount').value = '';
    document.getElementById('productAvailableGroup').style.display = 'none';
    openModal('addProductModal');
}

function editProduct(id) {
    // يمكن تحميل بيانات المنتج عبر AJAX
    document.getElementById('productModalTitle').textContent = 'تعديل منتج';
    document.getElementById('productAction').value = 'update_product';
    document.getElementById('productId').value = id;
    document.getElementById('productAvailableGroup').style.display = 'block';
    openModal('addProductModal');
}

function deleteProduct(id) {
    if (confirm('هل أنت متأكد من حذف هذا المنتج؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="action" value="delete_product">
            <input type="hidden" name="product_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'times-circle'}"></i>
        <span>${message}</span>
    `;
    container.appendChild(toast);
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Flash Message
<?php $flash = show_flash_message(); if ($flash): ?>
showToast('<?= addslashes(strip_tags($flash)) ?>', 'success');
<?php endif; ?>
</script>

</body>
</html>
