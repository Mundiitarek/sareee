<?php
/**
 * صفحة إنشاء الحسابات التجريبية
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 * 
 * تحذير: احذف هذا الملف بعد الاستخدام في بيئة الإنتاج!
 */

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

$page_title = 'إنشاء حسابات تجريبية - ' . APP_NAME;
$message = '';
$message_type = 'info';
$created_accounts = [];

// =====================================================
// إنشاء الحسابات
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_accounts'])) {
    $accounts_to_create = $_POST['accounts'] ?? [];
    
    // 1. إنشاء حساب أدمن
    if (in_array('admin', $accounts_to_create)) {
        $admin_phone = '0500000001';
        $admin_password = 'Admin@123';
        
        $existing = db_fetch("SELECT id FROM admins WHERE phone = ?", [$admin_phone]);
        if (!$existing) {
            db_insert('admins', [
                'name' => 'مدير النظام',
                'phone' => $admin_phone,
                'password_hash' => password_hash($admin_password, PASSWORD_DEFAULT),
                'role' => 'super',
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $created_accounts['admin'] = [
                'phone' => $admin_phone,
                'password' => $admin_password,
                'role' => 'مدير عام'
            ];
        }
    }
    
    // 2. إنشاء حساب تاجر (مطعم)
    if (in_array('vendor', $accounts_to_create)) {
        $vendor_phone = '0500000002';
        $vendor_password = 'Vendor@123';
        
        $existing = db_fetch("SELECT id FROM vendors WHERE phone = ?", [$vendor_phone]);
        if (!$existing) {
            // التأكد من وجود منطقة
            $zone = db_fetch("SELECT id FROM zones WHERE status = 1 LIMIT 1");
            $zone_id = $zone['id'] ?? null;
            
            if (!$zone_id) {
                // إنشاء منطقة افتراضية
                $zone_id = db_insert('zones', [
                    'name_ar' => 'وسط المدينة',
                    'name_en' => 'Downtown',
                    'city' => 'الرياض',
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            $vendor_id = db_insert('vendors', [
                'name' => 'أحمد المالك',
                'phone' => $vendor_phone,
                'password_hash' => password_hash($vendor_password, PASSWORD_DEFAULT),
                'business_name' => 'مطعم البيتزا الإيطالية',
                'business_type' => 'restaurant',
                'description' => 'أشهى أنواع البيتزا والمعجنات الإيطالية',
                'address' => 'شارع التحلية، الرياض',
                'zone_id' => $zone_id,
                'min_order' => 50,
                'delivery_time' => '30-45 دقيقة',
                'commission_rate' => 10,
                'is_open' => 1,
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // إضافة أقسام افتراضية
            $categories = ['بيتزا', 'مقبلات', 'مشروبات', 'حلويات'];
            foreach ($categories as $cat_name) {
                $cat_id = db_insert('categories', [
                    'vendor_id' => $vendor_id,
                    'name_ar' => $cat_name,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // إضافة منتجات افتراضية
                $products = [
                    ['بيتزا مارغريتا', 45, 35],
                    ['بيتزا بيبروني', 55, 45],
                    ['بيتزا خضار', 40, 30],
                ];
                foreach ($products as $prod) {
                    db_insert('products', [
                        'vendor_id' => $vendor_id,
                        'category_id' => $cat_name == 'بيتزا' ? $cat_id : null,
                        'name_ar' => $prod[0],
                        'price' => $prod[1],
                        'discount_price' => $prod[2],
                        'is_available' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            $created_accounts['vendor'] = [
                'phone' => $vendor_phone,
                'password' => $vendor_password,
                'business' => 'مطعم البيتزا الإيطالية'
            ];
        }
    }
    
    // 3. إنشاء حساب تاجر (مارت)
    if (in_array('mart', $accounts_to_create)) {
        $mart_phone = '0500000003';
        $mart_password = 'Mart@123';
        
        $existing = db_fetch("SELECT id FROM vendors WHERE phone = ?", [$mart_phone]);
        if (!$existing) {
            $zone = db_fetch("SELECT id FROM zones WHERE status = 1 LIMIT 1");
            $zone_id = $zone['id'] ?? 1;
            
            $mart_id = db_insert('vendors', [
                'name' => 'سارة العمري',
                'phone' => $mart_phone,
                'password_hash' => password_hash($mart_password, PASSWORD_DEFAULT),
                'business_name' => 'مارت التسوق السريع',
                'business_type' => 'mart',
                'description' => 'جميع احتياجاتك اليومية في مكان واحد',
                'address' => 'شارع العليا، الرياض',
                'zone_id' => $zone_id,
                'min_order' => 30,
                'delivery_time' => '45-60 دقيقة',
                'commission_rate' => 8,
                'is_open' => 1,
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // إضافة أقسام المارت
            $categories = ['مقاضي', 'مشروبات', 'حلويات', 'منظفات'];
            foreach ($categories as $cat_name) {
                $cat_id = db_insert('categories', [
                    'vendor_id' => $mart_id,
                    'name_ar' => $cat_name,
                    'status' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $products = [
                    ['مياه معدنية', 2, 1.5],
                    ['عصير برتقال', 5, 4],
                    ['شوكولاتة', 10, 8],
                ];
                foreach ($products as $prod) {
                    db_insert('products', [
                        'vendor_id' => $mart_id,
                        'category_id' => $cat_id,
                        'name_ar' => $prod[0],
                        'price' => $prod[1],
                        'discount_price' => $prod[2],
                        'is_available' => 1,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }
            
            $created_accounts['mart'] = [
                'phone' => $mart_phone,
                'password' => $mart_password,
                'business' => 'مارت التسوق السريع'
            ];
        }
    }
    
    // 4. إنشاء حساب مندوب
    if (in_array('driver', $accounts_to_create)) {
        $driver_phone = '0500000004';
        $driver_password = 'Driver@123';
        
        $existing = db_fetch("SELECT id FROM drivers WHERE phone = ?", [$driver_phone]);
        if (!$existing) {
            $zone = db_fetch("SELECT id FROM zones WHERE status = 1 LIMIT 1");
            $zone_id = $zone['id'] ?? 1;
            
            db_insert('drivers', [
                'name' => 'محمد السالم',
                'phone' => $driver_phone,
                'password_hash' => password_hash($driver_password, PASSWORD_DEFAULT),
                'vehicle_type' => 'motorcycle',
                'vehicle_number' => '1234 ABC',
                'zone_id' => $zone_id,
                'is_online' => 1,
                'is_busy' => 0,
                'status' => 'approved',
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $created_accounts['driver'] = [
                'phone' => $driver_phone,
                'password' => $driver_password,
                'name' => 'محمد السالم'
            ];
        }
    }
    
    // 5. إنشاء حساب عميل
    if (in_array('customer', $accounts_to_create)) {
        $customer_phone = '0500000005';
        
        $existing = db_fetch("SELECT id FROM users WHERE phone = ?", [$customer_phone]);
        if (!$existing) {
            db_insert('users', [
                'name' => 'خالد العتيبي',
                'phone' => $customer_phone,
                'status' => 1,
                'total_orders' => 5,
                'total_spent' => 350,
                'loyalty_points' => 150,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            $created_accounts['customer'] = [
                'phone' => $customer_phone,
                'name' => 'خالد العتيبي',
                'note' => 'يستخدم OTP للدخول'
            ];
        }
    }
    
    if (!empty($created_accounts)) {
        $message = 'تم إنشاء الحسابات التجريبية بنجاح!';
        $message_type = 'success';
    } else {
        $message = 'جميع الحسابات موجودة مسبقاً';
        $message_type = 'info';
    }
}

// حذف جميع الحسابات التجريبية
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_accounts'])) {
    db_delete('admins', "phone IN ('0500000001')", []);
    db_delete('vendors', "phone IN ('0500000002', '0500000003')", []);
    db_delete('drivers', "phone = '0500000004'", []);
    db_delete('users', "phone = '0500000005'", []);
    
    $message = 'تم حذف جميع الحسابات التجريبية!';
    $message_type = 'success';
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .warning-banner {
            background: #FEF3C7;
            border: 2px solid #F59E0B;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #92400E;
        }
        
        .warning-banner i {
            font-size: 28px;
            color: #F59E0B;
        }
        
        .warning-banner strong {
            display: block;
            margin-bottom: 4px;
        }
        
        .card {
            background: white;
            border-radius: 24px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            margin-bottom: 24px;
        }
        
        .card h2 {
            font-size: 22px;
            margin-bottom: 20px;
            color: #1F2937;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 12px;
            border-bottom: 2px solid #F3F4F6;
        }
        
        .card h2 i {
            color: #667eea;
        }
        
        .accounts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .account-checkbox {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #F9FAFB;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .account-checkbox:hover {
            border-color: #667eea;
            background: #EEF2FF;
        }
        
        .account-checkbox.selected {
            border-color: #667eea;
            background: #EEF2FF;
        }
        
        .account-checkbox input {
            width: 22px;
            height: 22px;
            accent-color: #667eea;
            cursor: pointer;
        }
        
        .account-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        
        .account-icon.admin { background: #FEE2E2; color: #DC2626; }
        .account-icon.vendor { background: #D1FAE5; color: #059669; }
        .account-icon.mart { background: #DBEAFE; color: #2563EB; }
        .account-icon.driver { background: #FEF3C7; color: #D97706; }
        .account-icon.customer { background: #E9D5FF; color: #7C3AED; }
        
        .account-info h3 {
            font-size: 16px;
            margin-bottom: 2px;
            color: #1F2937;
        }
        
        .account-info p {
            font-size: 13px;
            color: #6B7280;
        }
        
        .btn {
            padding: 14px 28px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s;
            font-family: 'Cairo', sans-serif;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-danger {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        
        .btn-danger:hover {
            background: #DC2626;
            color: white;
        }
        
        .btn-outline {
            background: white;
            border: 1px solid #E5E7EB;
            color: #6B7280;
        }
        
        .accounts-list {
            margin-top: 24px;
        }
        
        .account-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: #F9FAFB;
            border-radius: 14px;
            margin-bottom: 10px;
        }
        
        .account-item-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        
        .account-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        
        .account-badge.admin { background: #FEE2E2; color: #DC2626; }
        .account-badge.vendor { background: #D1FAE5; color: #059669; }
        .account-badge.mart { background: #DBEAFE; color: #2563EB; }
        .account-badge.driver { background: #FEF3C7; color: #D97706; }
        .account-badge.customer { background: #E9D5FF; color: #7C3AED; }
        
        .credentials {
            font-family: monospace;
            background: #1F2937;
            color: #10B981;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .copy-btn {
            background: none;
            border: none;
            color: #6B7280;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .copy-btn:hover {
            background: #E5E7EB;
            color: #1F2937;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 16px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .alert-info {
            background: #DBEAFE;
            color: #1E40AF;
            border: 1px solid #BFDBFE;
        }
        
        .alert i {
            font-size: 24px;
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }
        
        .quick-link {
            padding: 10px 18px;
            background: #F3F4F6;
            border-radius: 30px;
            color: #4B5563;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        
        .quick-link:hover {
            background: #667eea;
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>
            <i class="fas fa-flask"></i>
            إنشاء حسابات تجريبية
        </h1>
        <p>لاختبار تطبيق سريع - للحصول على صلاحيات مختلفة</p>
    </div>
    
    <div class="warning-banner">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>تحذير هام!</strong>
            <p>هذه الصفحة مخصصة للبيئة التجريبية فقط. يجب حذف هذا الملف قبل رفع المشروع على بيئة الإنتاج.</p>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-<?= $message_type ?>">
        <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'info-circle' ?>"></i>
        <span><?= $message ?></span>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>
            <i class="fas fa-user-plus"></i>
            اختر الحسابات المراد إنشاؤها
        </h2>
        
        <form method="POST">
            <div class="accounts-grid">
                <label class="account-checkbox" id="checkAdmin">
                    <input type="checkbox" name="accounts[]" value="admin" onchange="toggleCheckbox(this)">
                    <div class="account-icon admin"><i class="fas fa-user-shield"></i></div>
                    <div class="account-info">
                        <h3>مدير النظام</h3>
                        <p>صلاحيات كاملة للوحة التحكم</p>
                    </div>
                </label>
                
                <label class="account-checkbox" id="checkVendor">
                    <input type="checkbox" name="accounts[]" value="vendor" onchange="toggleCheckbox(this)">
                    <div class="account-icon vendor"><i class="fas fa-store"></i></div>
                    <div class="account-info">
                        <h3>تاجر (مطعم)</h3>
                        <p>يدير مطعم ومنتجاته</p>
                    </div>
                </label>
                
                <label class="account-checkbox" id="checkMart">
                    <input type="checkbox" name="accounts[]" value="mart" onchange="toggleCheckbox(this)">
                    <div class="account-icon mart"><i class="fas fa-shopping-basket"></i></div>
                    <div class="account-info">
                        <h3>تاجر (مارت)</h3>
                        <p>يدير متجر مقاضي</p>
                    </div>
                </label>
                
                <label class="account-checkbox" id="checkDriver">
                    <input type="checkbox" name="accounts[]" value="driver" onchange="toggleCheckbox(this)">
                    <div class="account-icon driver"><i class="fas fa-motorcycle"></i></div>
                    <div class="account-info">
                        <h3>مندوب توصيل</h3>
                        <p>يستقبل ويوصل الطلبات</p>
                    </div>
                </label>
                
                <label class="account-checkbox" id="checkCustomer">
                    <input type="checkbox" name="accounts[]" value="customer" onchange="toggleCheckbox(this)">
                    <div class="account-icon customer"><i class="fas fa-user"></i></div>
                    <div class="account-info">
                        <h3>عميل</h3>
                        <p>يطلب من المطاعم والمتاجر</p>
                    </div>
                </label>
            </div>
            
            <button type="submit" name="create_accounts" class="btn btn-primary">
                <i class="fas fa-magic"></i>
                إنشاء الحسابات المحددة
            </button>
        </form>
        
        <form method="POST" style="margin-top: 12px;">
            <button type="submit" name="delete_accounts" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف جميع الحسابات التجريبية؟')">
                <i class="fas fa-trash"></i>
                حذف جميع الحسابات التجريبية
            </button>
        </form>
    </div>
    
    <?php if (!empty($created_accounts)): ?>
    <div class="card">
        <h2>
            <i class="fas fa-check-circle" style="color: #10B981;"></i>
            تم إنشاء الحسابات بنجاح
        </h2>
        
        <div class="accounts-list">
            <?php if (isset($created_accounts['admin'])): ?>
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon admin" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <strong>مدير النظام</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            <span class="credentials" id="adminPhone"><?= $created_accounts['admin']['phone'] ?></span>
                            <button class="copy-btn" onclick="copyText('adminPhone')"><i class="far fa-copy"></i></button>
                            &nbsp;|&nbsp;
                            <span class="credentials" id="adminPass"><?= $created_accounts['admin']['password'] ?></span>
                            <button class="copy-btn" onclick="copyText('adminPass')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                </div>
                <span class="account-badge admin"><?= $created_accounts['admin']['role'] ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($created_accounts['vendor'])): ?>
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon vendor" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <strong><?= $created_accounts['vendor']['business'] ?></strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            <span class="credentials" id="vendorPhone"><?= $created_accounts['vendor']['phone'] ?></span>
                            <button class="copy-btn" onclick="copyText('vendorPhone')"><i class="far fa-copy"></i></button>
                            &nbsp;|&nbsp;
                            <span class="credentials" id="vendorPass"><?= $created_accounts['vendor']['password'] ?></span>
                            <button class="copy-btn" onclick="copyText('vendorPass')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                </div>
                <span class="account-badge vendor">مطعم</span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($created_accounts['mart'])): ?>
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon mart" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <div>
                        <strong><?= $created_accounts['mart']['business'] ?></strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            <span class="credentials" id="martPhone"><?= $created_accounts['mart']['phone'] ?></span>
                            <button class="copy-btn" onclick="copyText('martPhone')"><i class="far fa-copy"></i></button>
                            &nbsp;|&nbsp;
                            <span class="credentials" id="martPass"><?= $created_accounts['mart']['password'] ?></span>
                            <button class="copy-btn" onclick="copyText('martPass')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                </div>
                <span class="account-badge mart">مارت</span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($created_accounts['driver'])): ?>
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon driver" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div>
                        <strong><?= $created_accounts['driver']['name'] ?></strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            <span class="credentials" id="driverPhone"><?= $created_accounts['driver']['phone'] ?></span>
                            <button class="copy-btn" onclick="copyText('driverPhone')"><i class="far fa-copy"></i></button>
                            &nbsp;|&nbsp;
                            <span class="credentials" id="driverPass"><?= $created_accounts['driver']['password'] ?></span>
                            <button class="copy-btn" onclick="copyText('driverPass')"><i class="far fa-copy"></i></button>
                        </div>
                    </div>
                </div>
                <span class="account-badge driver">مندوب</span>
            </div>
            <?php endif; ?>
            
            <?php if (isset($created_accounts['customer'])): ?>
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon customer" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <strong><?= $created_accounts['customer']['name'] ?></strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            <span class="credentials" id="customerPhone"><?= $created_accounts['customer']['phone'] ?></span>
                            <button class="copy-btn" onclick="copyText('customerPhone')"><i class="far fa-copy"></i></button>
                            &nbsp;|&nbsp;
                            <span style="color: #6B7280;">OTP: 000000 (تجريبي)</span>
                        </div>
                    </div>
                </div>
                <span class="account-badge customer">عميل</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <h2>
            <i class="fas fa-link"></i>
            روابط سريعة للوحات التحكم
        </h2>
        
        <div class="quick-links">
            <a href="<?= BASE_URL ?>admin/" class="quick-link" target="_blank">
                <i class="fas fa-user-shield"></i>
                لوحة الأدمن
            </a>
            <a href="<?= BASE_URL ?>vendor/" class="quick-link" target="_blank">
                <i class="fas fa-store"></i>
                لوحة التاجر
            </a>
            <a href="<?= BASE_URL ?>driver/" class="quick-link" target="_blank">
                <i class="fas fa-motorcycle"></i>
                لوحة المندوب
            </a>
            <a href="<?= BASE_URL ?>index.php" class="quick-link" target="_blank">
                <i class="fas fa-home"></i>
                الصفحة الرئيسية
            </a>
            <a href="<?= BASE_URL ?>login.php" class="quick-link" target="_blank">
                <i class="fas fa-sign-in-alt"></i>
                صفحة الدخول
            </a>
        </div>
    </div>
    
    <div class="card">
        <h2>
            <i class="fas fa-key"></i>
            بيانات الدخول الافتراضية (إذا كانت موجودة مسبقاً)
        </h2>
        
        <div class="accounts-list">
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon admin" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div>
                        <strong>مدير النظام</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            0500000001 | Admin@123
                        </div>
                    </div>
                </div>
                <span class="account-badge admin">مدير</span>
            </div>
            
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon vendor" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <strong>تاجر (مطعم)</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            0500000002 | Vendor@123
                        </div>
                    </div>
                </div>
                <span class="account-badge vendor">مطعم</span>
            </div>
            
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon mart" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    <div>
                        <strong>تاجر (مارت)</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            0500000003 | Mart@123
                        </div>
                    </div>
                </div>
                <span class="account-badge mart">مارت</span>
            </div>
            
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon driver" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div>
                        <strong>مندوب توصيل</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            0500000004 | Driver@123
                        </div>
                    </div>
                </div>
                <span class="account-badge driver">مندوب</span>
            </div>
            
            <div class="account-item">
                <div class="account-item-left">
                    <div class="account-icon customer" style="width: 40px; height: 40px; font-size: 18px;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <strong>عميل</strong>
                        <div style="font-size: 13px; color: #6B7280; margin-top: 4px;">
                            0500000005 | OTP: 000000
                        </div>
                    </div>
                </div>
                <span class="account-badge customer">عميل</span>
            </div>
        </div>
        
        <div class="alert alert-info" style="margin-top: 20px;">
            <i class="fas fa-lightbulb"></i>
            <span>في بيئة التطوير، رمز OTP يظهر في Console المتصفح. يمكنك استخدام أي 6 أرقام للتجربة.</span>
        </div>
    </div>
</div>

<script>
function toggleCheckbox(checkbox) {
    const parent = checkbox.closest('.account-checkbox');
    if (checkbox.checked) {
        parent.classList.add('selected');
    } else {
        parent.classList.remove('selected');
    }
}

function copyText(elementId) {
    const element = document.getElementById(elementId);
    const text = element.textContent;
    
    navigator.clipboard.writeText(text).then(() => {
        // تغيير مؤقت للأيقونة
        const btn = element.nextElementSibling;
        const icon = btn.querySelector('i');
        icon.classList.remove('fa-copy');
        icon.classList.add('fa-check');
        icon.style.color = '#10B981';
        
        setTimeout(() => {
            icon.classList.remove('fa-check');
            icon.classList.add('fa-copy');
            icon.style.color = '';
        }, 1500);
    }).catch(err => {
        alert('فشل النسخ: ' + err);
    });
}

// تفعيل الحالة للـ checkboxes الموجودة
document.querySelectorAll('.account-checkbox input[type="checkbox"]').forEach(cb => {
    toggleCheckbox(cb);
});
</script>

</body>
</html>