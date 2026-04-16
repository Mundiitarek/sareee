<?php
/**
 * صفحة حسابي - الملف الشخصي والطلبات والإشعارات
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = BASE_URL . 'account.php';
    redirect(BASE_URL . 'login.php');
}

$page_title = 'حسابي - ' . APP_NAME;
$user_id = $_SESSION['user_id'];

// جلب بيانات المستخدم
$user = get_current_user_data();
if (!$user) {
    logout_user();
    redirect(BASE_URL . 'login.php');
}

// تحديد التبويب النشط
$active_tab = isset($_GET['tab']) ? clean_input($_GET['tab']) : 'orders';

// جلب البيانات حسب التبويب
$orders = [];
$notifications = [];
$ratings = [];
$reports = [];
$loyalty_data = [];

switch ($active_tab) {
    case 'orders':
        $orders = db_fetch_all(
            "SELECT o.*, v.business_name, v.logo as vendor_logo,
                    d.name as driver_name, d.phone as driver_phone, d.avatar as driver_avatar,
                    (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
             FROM orders o
             JOIN vendors v ON o.vendor_id = v.id
             LEFT JOIN drivers d ON o.driver_id = d.id
             WHERE o.user_id = ?
             ORDER BY o.created_at DESC
             LIMIT 20",
            [$user_id]
        );
        break;
        
    case 'notifications':
        $notifications = db_fetch_all(
            "SELECT * FROM notifications 
             WHERE user_id = ? AND user_type = 'user'
             ORDER BY created_at DESC
             LIMIT 30",
            [$user_id]
        );
        // تحديث الإشعارات كمقروءة
        db_update('notifications', ['is_read' => 1], 'user_id = ? AND user_type = ?', [$user_id, 'user']);
        break;
        
    case 'ratings':
        $ratings = db_fetch_all(
            "SELECT r.*, v.business_name, d.name as driver_name, p.name_ar as product_name,
                    o.order_number
             FROM ratings r
             JOIN orders o ON r.order_id = o.id
             JOIN vendors v ON o.vendor_id = v.id
             LEFT JOIN drivers d ON r.driver_id = d.id
             LEFT JOIN products p ON r.product_id = p.id
             WHERE r.user_id = ? AND r.rated_by = 'user'
             ORDER BY r.created_at DESC
             LIMIT 20",
            [$user_id]
        );
        break;
        
    case 'reports':
        $reports = db_fetch_all(
            "SELECT * FROM reports 
             WHERE user_id = ?
             ORDER BY created_at DESC",
            [$user_id]
        );
        break;
        
    case 'loyalty':
        $loyalty_data = [
            'points' => $user['loyalty_points'] ?? 0,
            'total_orders' => $user['total_orders'] ?? 0,
            'total_spent' => $user['total_spent'] ?? 0,
            'monthly_orders' => db_fetch(
                "SELECT COUNT(*) as count FROM orders 
                 WHERE user_id = ? AND status = 'delivered' 
                 AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
                [$user_id]
            )['count'] ?? 0
        ];
        
        $loyalty_data['logs'] = db_fetch_all(
            "SELECT ll.*, lr.name_ar as rule_name, lr.icon, lr.color
             FROM loyalty_logs ll
             LEFT JOIN loyalty_rules lr ON ll.rule_id = lr.id
             WHERE ll.user_id = ?
             ORDER BY ll.created_at DESC
             LIMIT 20",
            [$user_id]
        );
        
        $loyalty_data['rules'] = db_fetch_all(
            "SELECT * FROM loyalty_rules WHERE status = 1 ORDER BY orders_count ASC",
            []
        );
        break;
}

include INCLUDES_PATH . '/header.php';
?>

<!-- Account Header -->
<div class="account-header">
    <div class="account-header-bg">
        <div class="bg-pattern"></div>
    </div>
    <div class="account-container">
        <div class="profile-summary">
            <div class="profile-avatar-wrapper">
                <div class="profile-avatar">
                    <?php if ($user['avatar']): ?>
                    <img src="<?= asset_url($user['avatar'], 'assets/images/default-avatar.png') ?>" alt="<?= escape($user['name']) ?>">
                    <?php else: ?>
                    <div class="avatar-placeholder"><?= mb_substr($user['name'], 0, 1) ?></div>
                    <?php endif; ?>
                    <button class="change-avatar-btn" id="changeAvatarBtn">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
            </div>
            <div class="profile-info">
                <h1 class="profile-name"><?= escape($user['name']) ?></h1>
                <p class="profile-phone">
                    <i class="fas fa-phone"></i>
                    <?= format_phone_display($user['phone']) ?>
                </p>
                <div class="profile-stats">
                    <div class="stat-badge">
                        <i class="fas fa-shopping-bag"></i>
                        <span><?= $user['total_orders'] ?? 0 ?> طلب</span>
                    </div>
                    <div class="stat-badge">
                        <i class="fas fa-star"></i>
                        <span><?= $user['loyalty_points'] ?? 0 ?> نقطة</span>
                    </div>
                </div>
            </div>
            <button class="edit-profile-btn" id="editProfileBtn">
                <i class="fas fa-pen"></i>
            </button>
        </div>
    </div>
</div>

<!-- Account Tabs -->
<div class="account-tabs-wrapper">
    <div class="account-container">
        <div class="account-tabs">
            <a href="?tab=orders" class="account-tab <?= $active_tab == 'orders' ? 'active' : '' ?>">
                <i class="fas fa-receipt"></i>
                <span>طلباتي</span>
                <?php 
                $pending_count = db_fetch(
                    "SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status IN ('new', 'accepted', 'preparing', 'on_way')",
                    [$user_id]
                )['count'] ?? 0;
                if ($pending_count > 0): 
                ?>
                <span class="tab-badge"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=profile" class="account-tab <?= $active_tab == 'profile' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span>ملفي</span>
            </a>
            <a href="?tab=notifications" class="account-tab <?= $active_tab == 'notifications' ? 'active' : '' ?>">
                <i class="fas fa-bell"></i>
                <span>إشعاراتي</span>
                <?php 
                $unread = db_fetch(
                    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND user_type = 'user' AND is_read = 0",
                    [$user_id]
                )['count'] ?? 0;
                if ($unread > 0): 
                ?>
                <span class="tab-badge"><?= $unread ?></span>
                <?php endif; ?>
            </a>
            <a href="?tab=ratings" class="account-tab <?= $active_tab == 'ratings' ? 'active' : '' ?>">
                <i class="fas fa-star"></i>
                <span>تقييماتي</span>
            </a>
            <a href="?tab=reports" class="account-tab <?= $active_tab == 'reports' ? 'active' : '' ?>">
                <i class="fas fa-flag"></i>
                <span>بلاغاتي</span>
            </a>
            <a href="?tab=loyalty" class="account-tab <?= $active_tab == 'loyalty' ? 'active' : '' ?>">
                <i class="fas fa-crown"></i>
                <span>ولائي</span>
            </a>
        </div>
    </div>
</div>

<!-- Tab Content -->
<div class="account-container">
    <div class="account-content">
        
        <!-- Orders Tab -->
        <?php if ($active_tab == 'orders'): ?>
        <div class="tab-pane active" id="orders-tab">
            <div class="tab-header">
                <h2><i class="fas fa-receipt"></i> طلباتي</h2>
                <div class="order-filters">
                    <select id="orderStatusFilter" class="filter-select">
                        <option value="all">جميع الطلبات</option>
                        <option value="active">الطلبات النشطة</option>
                        <option value="delivered">تم التوصيل</option>
                        <option value="cancelled">ملغية</option>
                    </select>
                </div>
            </div>
            
            <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-shopping-bag"></i>
                <h3>لا توجد طلبات حتى الآن</h3>
                <p>ابدأ بطلب وجبتك الأولى من أفضل المطاعم</p>
                <a href="<?= BASE_URL ?>index.php" class="btn btn-primary">
                    <i class="fas fa-utensils"></i> تصفح المطاعم
                </a>
            </div>
            <?php else: ?>
            <div class="orders-list">
                <?php foreach ($orders as $order): ?>
                <div class="order-card" data-status="<?= $order['status'] ?>">
                    <div class="order-header">
                        <div class="order-vendor">
                            <img src="<?= asset_url($order['vendor_logo'], 'assets/images/default-logo.jpg') ?>" 
                                 alt="<?= escape($order['business_name']) ?>"
                                 onerror="this.style.display='none'">
                            <div>
                                <h4><?= escape($order['business_name']) ?></h4>
                                <span class="order-number">#<?= escape($order['order_number']) ?></span>
                            </div>
                        </div>
                        <div class="order-status-badge" style="background: <?= get_order_status_color($order['status']) ?>20; color: <?= get_order_status_color($order['status']) ?>">
                            <i class="fas <?= get_order_status_icon($order['status']) ?>"></i>
                            <?= get_order_status_arabic($order['status']) ?>
                        </div>
                    </div>
                    
                    <div class="order-body">
                        <div class="order-info">
                            <div class="order-info-item">
                                <i class="fas fa-calendar"></i>
                                <span><?= format_date($order['created_at']) ?></span>
                            </div>
                            <div class="order-info-item">
                                <i class="fas fa-shopping-cart"></i>
                                <span><?= $order['items_count'] ?> منتجات</span>
                            </div>
                            <div class="order-info-item">
                                <i class="fas fa-tag"></i>
                                <span><?= format_price($order['total']) ?></span>
                            </div>
                        </div>
                        
                        <?php if ($order['driver_name']): ?>
                        <div class="order-driver">
                            <i class="fas fa-motorcycle"></i>
                            <span>المندوب: <?= escape($order['driver_name']) ?></span>
                            <a href="tel:<?= $order['driver_phone'] ?>" class="driver-call">
                                <i class="fas fa-phone"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="order-footer">
                        <a href="#" class="btn btn-outline btn-sm view-order-details" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-eye"></i> التفاصيل
                        </a>
                        
                        <?php if ($order['status'] == 'delivered'): ?>
                        <button class="btn btn-outline-success btn-sm rate-order-btn" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-star"></i> تقييم
                        </button>
                        <?php endif; ?>
                        
                        <?php if (in_array($order['status'], ['new', 'accepted'])): ?>
                        <button class="btn btn-outline-danger btn-sm cancel-order-btn" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-times"></i> إلغاء
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($order['status'] == 'on_way'): ?>
                        <button class="btn btn-outline-primary btn-sm track-order-btn" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-map-marker-alt"></i> تتبع
                        </button>
                        <?php endif; ?>
                        
                        <button class="btn btn-outline-warning btn-sm report-order-btn" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-flag"></i> بلاغ
                        </button>
                        
                        <a href="#" class="btn btn-outline btn-sm reorder-btn" data-order-id="<?= $order['id'] ?>">
                            <i class="fas fa-redo"></i> إعادة الطلب
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Profile Tab -->
        <?php if ($active_tab == 'profile'): ?>
        <div class="tab-pane active" id="profile-tab">
            <div class="tab-header">
                <h2><i class="fas fa-user-circle"></i> معلوماتي الشخصية</h2>
            </div>
            
            <form id="profileForm" class="profile-form">
                <?= csrf_field() ?>
                
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> البيانات الأساسية</h3>
                    
                    <div class="form-group">
                        <label>الاسم الكامل</label>
                        <input type="text" name="name" class="form-control" value="<?= escape($user['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>رقم الجوال</label>
                        <input type="tel" name="phone" class="form-control" value="<?= format_phone_display($user['phone']) ?>" readonly disabled>
                        <small class="form-hint">لا يمكن تغيير رقم الجوال</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-map-pin"></i> العناوين المحفوظة</h3>
                    
                    <div class="saved-addresses" id="savedAddresses">
                        <!-- يتم تحميلها عبر AJAX -->
                    </div>
                    
                    <button type="button" class="btn btn-outline add-address-btn" id="addAddressFromProfile">
                        <i class="fas fa-plus-circle"></i> إضافة عنوان جديد
                    </button>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-cog"></i> إعدادات الحساب</h3>
                    
                    <div class="form-check">
                        <input type="checkbox" name="notifications_enabled" id="notificationsEnabled" checked>
                        <label for="notificationsEnabled">تفعيل الإشعارات</label>
                    </div>
                    
                    <div class="form-check">
                        <input type="checkbox" name="promo_emails" id="promoEmails" checked>
                        <label for="promoEmails">استلام العروض والتخفيضات</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> حفظ التغييرات
                    </button>
                </div>
            </form>
            
            <div class="danger-zone">
                <h3><i class="fas fa-exclamation-triangle"></i> منطقة الخطر</h3>
                <p>حذف الحساب نهائي ولا يمكن التراجع عنه</p>
                <button class="btn btn-danger" id="deleteAccountBtn">
                    <i class="fas fa-trash"></i> حذف الحساب
                </button>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Notifications Tab -->
        <?php if ($active_tab == 'notifications'): ?>
        <div class="tab-pane active" id="notifications-tab">
            <div class="tab-header">
                <h2><i class="fas fa-bell"></i> الإشعارات</h2>
                <?php if (!empty($notifications)): ?>
                <button class="btn btn-outline btn-sm" id="markAllReadBtn">
                    <i class="fas fa-check-double"></i> تحديد الكل كمقروء
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (empty($notifications)): ?>
            <div class="empty-state">
                <i class="fas fa-bell-slash"></i>
                <h3>لا توجد إشعارات</h3>
                <p>ستظهر هنا إشعارات الطلبات والعروض</p>
            </div>
            <?php else: ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notif): ?>
                <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" data-id="<?= $notif['id'] ?>">
                    <div class="notification-icon" style="background: <?= getNotificationColor($notif['type']) ?>">
                        <i class="fas <?= getNotificationIcon($notif['type']) ?>"></i>
                    </div>
                    <div class="notification-content">
                        <h4><?= escape($notif['title_ar']) ?></h4>
                        <p><?= escape($notif['body_ar']) ?></p>
                        <span class="notification-time"><?= time_ago($notif['created_at']) ?></span>
                    </div>
                    <?php if (!$notif['is_read']): ?>
                    <span class="unread-indicator"></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Ratings Tab -->
        <?php if ($active_tab == 'ratings'): ?>
        <div class="tab-pane active" id="ratings-tab">
            <div class="tab-header">
                <h2><i class="fas fa-star"></i> تقييماتي</h2>
            </div>
            
            <?php if (empty($ratings)): ?>
            <div class="empty-state">
                <i class="fas fa-star-half-alt"></i>
                <h3>لا توجد تقييمات</h3>
                <p>قيم طلباتك لتحسين تجربتك</p>
            </div>
            <?php else: ?>
            <div class="ratings-list">
                <?php foreach ($ratings as $rating): ?>
                <div class="rating-card">
                    <div class="rating-card-header">
                        <div class="rated-entity">
                            <?php if ($rating['rated_for'] == 'vendor'): ?>
                            <i class="fas fa-store"></i>
                            <span><?= escape($rating['business_name']) ?></span>
                            <?php elseif ($rating['rated_for'] == 'driver'): ?>
                            <i class="fas fa-motorcycle"></i>
                            <span><?= escape($rating['driver_name']) ?></span>
                            <?php else: ?>
                            <i class="fas fa-box"></i>
                            <span><?= escape($rating['product_name']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rating-stars-display">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $rating['rating'] ? 'active' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($rating['review']): ?>
                    <p class="rating-review-text"><?= escape($rating['review']) ?></p>
                    <?php endif; ?>
                    <div class="rating-meta">
                        <span class="order-ref">طلب #<?= escape($rating['order_number']) ?></span>
                        <span class="rating-date"><?= time_ago($rating['created_at']) ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Reports Tab -->
        <?php if ($active_tab == 'reports'): ?>
        <div class="tab-pane active" id="reports-tab">
            <div class="tab-header">
                <h2><i class="fas fa-flag"></i> بلاغاتي</h2>
                <a href="#" class="btn btn-primary btn-sm" id="newReportBtn">
                    <i class="fas fa-plus"></i> بلاغ جديد
                </a>
            </div>
            
            <?php if (empty($reports)): ?>
            <div class="empty-state">
                <i class="fas fa-flag-checkered"></i>
                <h3>لا توجد بلاغات</h3>
                <p>يمكنك تقديم بلاغ عن أي مشكلة تواجهك</p>
            </div>
            <?php else: ?>
            <div class="reports-list">
                <?php foreach ($reports as $report): ?>
                <div class="report-card">
                    <div class="report-header">
                        <div>
                            <span class="report-number">#<?= escape($report['report_number']) ?></span>
                            <span class="report-status" style="background: <?= getReportStatusColor($report['status']) ?>20; color: <?= getReportStatusColor($report['status']) ?>">
                                <?= getReportStatusArabic($report['status']) ?>
                            </span>
                        </div>
                        <span class="report-date"><?= time_ago($report['created_at']) ?></span>
                    </div>
                    <h4><?= escape($report['subject']) ?></h4>
                    <p><?= escape(truncate($report['description'], 150)) ?></p>
                    <?php if ($report['admin_response']): ?>
                    <div class="admin-response">
                        <i class="fas fa-reply"></i>
                        <p><?= escape($report['admin_response']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Loyalty Tab -->
        <?php if ($active_tab == 'loyalty'): ?>
        <div class="tab-pane active" id="loyalty-tab">
            <div class="loyalty-header">
                <div class="loyalty-points-card">
                    <div class="points-circle">
                        <span class="points-number"><?= $loyalty_data['points'] ?></span>
                        <span class="points-label">نقطة</span>
                    </div>
                    <div class="points-info">
                        <h3><i class="fas fa-crown"></i> برنامج الولاء</h3>
                        <p>اجمع النقاط واستبدلها بخصومات ومكافآت</p>
                    </div>
                </div>
                
                <div class="loyalty-stats">
                    <div class="stat-card">
                        <i class="fas fa-shopping-bag"></i>
                        <div>
                            <span class="stat-value"><?= $loyalty_data['total_orders'] ?></span>
                            <span class="stat-label">إجمالي الطلبات</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-calendar"></i>
                        <div>
                            <span class="stat-value"><?= $loyalty_data['monthly_orders'] ?></span>
                            <span class="stat-label">طلبات هذا الشهر</span>
                        </div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-wallet"></i>
                        <div>
                            <span class="stat-value"><?= format_price($loyalty_data['total_spent'], false) ?></span>
                            <span class="stat-label">إجمالي الإنفاق</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="loyalty-levels">
                <h3><i class="fas fa-trophy"></i> مستويات الولاء</h3>
                <div class="levels-container">
                    <?php foreach ($loyalty_data['rules'] as $index => $rule): 
                        $is_achieved = $loyalty_data['monthly_orders'] >= $rule['orders_count'];
                        $is_next = !$is_achieved && ($index == 0 || $loyalty_data['monthly_orders'] >= $loyalty_data['rules'][$index-1]['orders_count']);
                    ?>
                    <div class="level-card <?= $is_achieved ? 'achieved' : '' ?> <?= $is_next ? 'next' : '' ?>">
                        <div class="level-icon" style="background: <?= $rule['color'] ?>20; color: <?= $rule['color'] ?>">
                            <?php if ($rule['icon']): ?>
                            <i class="fas fa-<?= $rule['icon'] ?>"></i>
                            <?php else: ?>
                            <i class="fas fa-medal"></i>
                            <?php endif; ?>
                        </div>
                        <h4><?= escape($rule['name_ar']) ?></h4>
                        <p class="level-requirement"><?= $rule['orders_count'] ?> طلب شهرياً</p>
                        <div class="level-rewards">
                            <?php if ($rule['points_reward']): ?>
                            <span><i class="fas fa-star"></i> +<?= $rule['points_reward'] ?> نقطة</span>
                            <?php endif; ?>
                            <?php if ($rule['discount_reward']): ?>
                            <span><i class="fas fa-tag"></i> خصم <?= $rule['discount_reward'] ?><?= $rule['discount_type'] == 'percentage' ? '%' : ' ر.س' ?></span>
                            <?php endif; ?>
                            <?php if ($rule['free_delivery']): ?>
                            <span><i class="fas fa-truck"></i> توصيل مجاني</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($is_achieved): ?>
                        <span class="level-badge achieved"><i class="fas fa-check-circle"></i> تم التحقيق</span>
                        <?php elseif ($is_next): ?>
                        <div class="level-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?= ($loyalty_data['monthly_orders'] / $rule['orders_count']) * 100 ?>%"></div>
                            </div>
                            <span>متبقي <?= $rule['orders_count'] - $loyalty_data['monthly_orders'] ?> طلب</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="loyalty-history">
                <h3><i class="fas fa-history"></i> سجل النقاط</h3>
                <?php if (empty($loyalty_data['logs'])): ?>
                <p class="no-history">لا يوجد سجل نقاط حتى الآن</p>
                <?php else: ?>
                <div class="history-list">
                    <?php foreach ($loyalty_data['logs'] as $log): ?>
                    <div class="history-item">
                        <div class="history-icon" style="background: <?= $log['color'] ?? '#FF6B35' ?>20; color: <?= $log['color'] ?? '#FF6B35' ?>">
                            <i class="fas fa-<?= $log['icon'] ?? 'star' ?>"></i>
                        </div>
                        <div class="history-details">
                            <span class="history-title"><?= escape($log['description'] ?? $log['rule_name'] ?? 'نقاط ولاء') ?></span>
                            <span class="history-date"><?= time_ago($log['created_at']) ?></span>
                        </div>
                        <div class="history-points <?= $log['points_earned'] > 0 ? 'earned' : 'redeemed' ?>">
                            <?= $log['points_earned'] > 0 ? '+' : '-' ?><?= abs($log['points_earned'] ?: $log['points_redeemed']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Order Details Modal -->
<div class="modal" id="orderDetailsModal">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h3>تفاصيل الطلب</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="orderDetailsContent">
            <!-- المحتوى يتم تحميله ديناميكياً -->
        </div>
    </div>
</div>

<!-- Rating Modal -->
<div class="modal" id="ratingModal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-star"></i> تقييم الطلب</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="ratingForm">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" id="ratingOrderId">
                
                <div class="rating-section">
                    <label>تقييم المطعم</label>
                    <div class="star-rating" data-for="vendor">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star" data-rating="<?= $i ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="vendor_rating" id="vendorRating" value="0">
                </div>
                
                <div class="rating-section">
                    <label>تقييم المندوب</label>
                    <div class="star-rating" data-for="driver">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="far fa-star" data-rating="<?= $i ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" name="driver_rating" id="driverRating" value="0">
                </div>
                
                <div class="rating-section">
                    <label>تقييمك العام</label>
                    <textarea name="review" class="form-control" rows="3" placeholder="اكتب تقييمك..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> إرسال التقييم
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal" id="reportModal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-flag"></i> تقديم بلاغ</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="reportForm">
                <?= csrf_field() ?>
                <input type="hidden" name="order_id" id="reportOrderId">
                
                <div class="form-group">
                    <label>نوع البلاغ</label>
                    <select name="reported_type" class="form-control" required>
                        <option value="">اختر نوع البلاغ</option>
                        <option value="order">مشكلة في الطلب</option>
                        <option value="vendor">مشكلة في المطعم</option>
                        <option value="driver">مشكلة في المندوب</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>عنوان البلاغ</label>
                    <input type="text" name="subject" class="form-control" required placeholder="مثال: تأخر في التوصيل">
                </div>
                
                <div class="form-group">
                    <label>وصف المشكلة</label>
                    <textarea name="description" class="form-control" rows="4" required placeholder="اشرح المشكلة بالتفصيل..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-paper-plane"></i> إرسال البلاغ
                </button>
            </form>
        </div>
    </div>
</div>

<?php
function getNotificationColor($type) {
    $colors = [
        'order' => '#FF6B35',
        'promo' => '#10B981',
        'system' => '#3B82F6',
        'general' => '#6B7280'
    ];
    return $colors[$type] ?? '#6B7280';
}

function getNotificationIcon($type) {
    $icons = [
        'order' => 'fa-shopping-bag',
        'promo' => 'fa-tag',
        'system' => 'fa-cog',
        'general' => 'fa-bell'
    ];
    return $icons[$type] ?? 'fa-bell';
}

function getReportStatusColor($status) {
    $colors = [
        'pending' => '#F59E0B',
        'investigating' => '#3B82F6',
        'resolved' => '#10B981',
        'rejected' => '#EF4444'
    ];
    return $colors[$status] ?? '#6B7280';
}

function getReportStatusArabic($status) {
    $statuses = [
        'pending' => 'قيد الانتظار',
        'investigating' => 'قيد المراجعة',
        'resolved' => 'تم الحل',
        'rejected' => 'مرفوض'
    ];
    return $statuses[$status] ?? $status;
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // تفعيل تقييم النجوم
    document.querySelectorAll('.star-rating').forEach(ratingDiv => {
        const stars = ratingDiv.querySelectorAll('i');
        const forType = ratingDiv.dataset.for;
        const hiddenInput = document.getElementById(forType + 'Rating');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = this.dataset.rating;
                hiddenInput.value = rating;
                
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.remove('far');
                        s.classList.add('fas', 'active');
                    } else {
                        s.classList.remove('fas', 'active');
                        s.classList.add('far');
                    }
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = this.dataset.rating;
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('hover');
                    } else {
                        s.classList.remove('hover');
                    }
                });
            });
            
            star.addEventListener('mouseleave', function() {
                stars.forEach(s => s.classList.remove('hover'));
            });
        });
    });
    
    // فتح مودال التفاصيل
    document.querySelectorAll('.view-order-details').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.dataset.orderId;
            loadOrderDetails(orderId);
        });
    });
    
    // فتح مودال التقييم
    document.querySelectorAll('.rate-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            document.getElementById('ratingOrderId').value = orderId;
            document.getElementById('ratingModal').classList.add('active');
        });
    });
    
    // فتح مودال البلاغ
    document.querySelectorAll('.report-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            document.getElementById('reportOrderId').value = orderId;
            document.getElementById('reportModal').classList.add('active');
        });
    });
    
    document.getElementById('newReportBtn')?.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('reportOrderId').value = '';
        document.getElementById('reportModal').classList.add('active');
    });
    
    // إغلاق المودالات
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', function() {
            this.closest('.modal').classList.remove('active');
        });
    });
    
    // إرسال التقييم
    document.getElementById('ratingForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'submit_rating');
        
        fetch(BASE_URL + 'api/handler.php?action=submit_rating', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم إرسال التقييم بنجاح', 'success');
                document.getElementById('ratingModal').classList.remove('active');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    });
    
    // إرسال البلاغ
    document.getElementById('reportForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'submit_report');
        
        fetch(BASE_URL + 'api/handler.php?action=submit_report', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم إرسال البلاغ بنجاح', 'success');
                document.getElementById('reportModal').classList.remove('active');
                setTimeout(() => location.href = BASE_URL + 'account.php?tab=reports', 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    });
    
    // إلغاء الطلب
    document.querySelectorAll('.cancel-order-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            if (confirm('هل أنت متأكد من إلغاء الطلب؟')) {
                fetch(BASE_URL + 'api/handler.php?action=cancel_order', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': CSRF_TOKEN
                    },
                    body: JSON.stringify({ order_id: orderId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('تم إلغاء الطلب بنجاح', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast(data.message || 'حدث خطأ', 'error');
                    }
                });
            }
        });
    });
    
    // إعادة الطلب
    document.querySelectorAll('.reorder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const orderId = this.dataset.orderId;
            
            fetch(BASE_URL + 'api/handler.php?action=reorder&order_id=' + orderId)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = BASE_URL + 'checkout.php';
                    } else {
                        showToast(data.message || 'حدث خطأ', 'error');
                    }
                });
        });
    });
    
    // تصفية الطلبات
    document.getElementById('orderStatusFilter')?.addEventListener('change', function() {
        const filter = this.value;
        const cards = document.querySelectorAll('.order-card');
        
        cards.forEach(card => {
            const status = card.dataset.status;
            if (filter === 'all') {
                card.style.display = 'block';
            } else if (filter === 'active') {
                card.style.display = ['new', 'accepted', 'preparing', 'on_way'].includes(status) ? 'block' : 'none';
            } else {
                card.style.display = status === filter ? 'block' : 'none';
            }
        });
    });
    
    // تحديد الكل كمقروء
    document.getElementById('markAllReadBtn')?.addEventListener('click', function() {
        fetch(BASE_URL + 'api/handler.php?action=mark_all_notifications_read', {
            method: 'POST',
            headers: { 'X-CSRF-Token': CSRF_TOKEN }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                location.reload();
            }
        });
    });
});

function loadOrderDetails(orderId) {
    fetch(BASE_URL + 'api/handler.php?action=get_order_details&id=' + orderId)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const order = data.data;
                const content = document.getElementById('orderDetailsContent');
                
                let itemsHtml = '';
                order.items.forEach(item => {
                    itemsHtml += `
                        <div class="order-detail-item">
                            <span>${item.quantity}× ${item.name_ar}</span>
                            <span>${formatPrice(item.total_price)}</span>
                        </div>
                    `;
                });
                
                content.innerHTML = `
                    <div class="order-details">
                        <div class="order-detail-header">
                            <h4>طلب #${order.order_number}</h4>
                            <span class="order-detail-status" style="color: ${getOrderStatusColor(order.status)}">
                                ${getOrderStatusArabic(order.status)}
                            </span>
                        </div>
                        
                        <div class="order-detail-vendor">
                            <i class="fas fa-store"></i>
                            <span>${escapeHtml(order.vendor_name)}</span>
                        </div>
                        
                        <div class="order-detail-items">
                            <h5>المنتجات</h5>
                            ${itemsHtml}
                        </div>
                        
                        <div class="order-detail-summary">
                            <div class="summary-row">
                                <span>المجموع الفرعي</span>
                                <span>${formatPrice(order.subtotal)}</span>
                            </div>
                            <div class="summary-row">
                                <span>رسوم التوصيل</span>
                                <span>${formatPrice(order.delivery_fee)}</span>
                            </div>
                            ${order.discount > 0 ? `
                            <div class="summary-row discount">
                                <span>الخصم</span>
                                <span>- ${formatPrice(order.discount)}</span>
                            </div>
                            ` : ''}
                            ${order.tip > 0 ? `
                            <div class="summary-row">
                                <span>إكرامية</span>
                                <span>${formatPrice(order.tip)}</span>
                            </div>
                            ` : ''}
                            <div class="summary-row total">
                                <span>الإجمالي</span>
                                <span>${formatPrice(order.total)}</span>
                            </div>
                        </div>
                        
                        ${order.driver_name ? `
                        <div class="order-detail-driver">
                            <h5><i class="fas fa-motorcycle"></i> المندوب</h5>
                            <p>${escapeHtml(order.driver_name)} - ${escapeHtml(order.driver_phone)}</p>
                        </div>
                        ` : ''}
                        
                        <div class="order-detail-address">
                            <h5><i class="fas fa-map-pin"></i> عنوان التوصيل</h5>
                            <p>${escapeHtml(order.address_snapshot ? JSON.parse(order.address_snapshot).address : 'غير محدد')}</p>
                        </div>
                        
                        ${order.notes ? `
                        <div class="order-detail-notes">
                            <h5><i class="fas fa-pencil"></i> ملاحظات</h5>
                            <p>${escapeHtml(order.notes)}</p>
                        </div>
                        ` : ''}
                        
                        <div class="order-timeline">
                            <h5><i class="fas fa-clock"></i> سجل الحالات</h5>
                            <div class="timeline">
                                ${order.status_logs.map(log => `
                                    <div class="timeline-item">
                                        <div class="timeline-icon" style="background: ${getOrderStatusColor(log.status)}">
                                            <i class="fas ${getOrderStatusIcon(log.status)}"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <span class="timeline-status">${getOrderStatusArabic(log.status)}</span>
                                            <span class="timeline-time">${timeAgo(log.created_at)}</span>
                                            ${log.note ? `<p>${escapeHtml(log.note)}</p>` : ''}
                                        </div>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                `;
                
                document.getElementById('orderDetailsModal').classList.add('active');
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    return parseFloat(price).toFixed(2) + ' ' + CURRENCY_SYMBOL;
}

function getOrderStatusColor(status) {
    const colors = {
        'new': '#FF9800', 'accepted': '#2196F3', 'preparing': '#9C27B0',
        'on_way': '#FF6B35', 'delivered': '#4CAF50', 'cancelled': '#F44336'
    };
    return colors[status] || '#757575';
}

function getOrderStatusArabic(status) {
    const statuses = {
        'new': 'جديد', 'accepted': 'تم القبول', 'preparing': 'قيد التحضير',
        'on_way': 'خرج للتوصيل', 'delivered': 'تم التوصيل', 'cancelled': 'ملغي'
    };
    return statuses[status] || status;
}

function getOrderStatusIcon(status) {
    const icons = {
        'new': 'fa-bell', 'accepted': 'fa-check', 'preparing': 'fa-utensils',
        'on_way': 'fa-motorcycle', 'delivered': 'fa-check-circle', 'cancelled': 'fa-times-circle'
    };
    return icons[status] || 'fa-circle';
}

function timeAgo(datetime) {
    return 'منذ قليل';
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
    /* Account Header */
    .account-header {
        background: var(--gradient-primary);
        position: relative;
        overflow: hidden;
        margin-bottom: var(--spacing-xl);
    }
    
    .account-header-bg {
        position: absolute;
        inset: 0;
    }
    
    .bg-pattern {
        width: 100%;
        height: 100%;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.08'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.5;
    }
    
    .account-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 var(--spacing-md);
    }
    
    .profile-summary {
        display: flex;
        align-items: center;
        gap: var(--spacing-lg);
        padding: var(--spacing-xl) 0;
        position: relative;
        z-index: 2;
    }
    
    .profile-avatar-wrapper {
        position: relative;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        background: var(--white);
        border-radius: 50%;
        overflow: hidden;
        border: 4px solid rgba(255,255,255,0.5);
        position: relative;
    }
    
    .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .avatar-placeholder {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-dark);
        color: var(--white);
        font-size: 40px;
        font-weight: 700;
    }
    
    .change-avatar-btn {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 36px;
        height: 36px;
        background: var(--white);
        border: none;
        border-radius: 50%;
        color: var(--primary);
        font-size: 16px;
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-bounce);
    }
    
    .change-avatar-btn:hover {
        transform: scale(1.1);
        background: var(--primary);
        color: var(--white);
    }
    
    .profile-info {
        flex: 1;
        color: var(--white);
    }
    
    .profile-name {
        font-size: 28px;
        margin: 0 0 var(--spacing-xs);
        color: var(--white);
    }
    
    .profile-phone {
        font-size: 16px;
        opacity: 0.95;
        margin-bottom: var(--spacing-sm);
    }
    
    .profile-stats {
        display: flex;
        gap: var(--spacing-md);
    }
    
    .stat-badge {
        padding: 6px 16px;
        background: rgba(255,255,255,0.2);
        border-radius: var(--radius-full);
        backdrop-filter: blur(4px);
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        font-size: 14px;
    }
    
    .edit-profile-btn {
        width: 48px;
        height: 48px;
        background: rgba(255,255,255,0.2);
        border: none;
        border-radius: 50%;
        color: var(--white);
        font-size: 20px;
        cursor: pointer;
        backdrop-filter: blur(4px);
        transition: all var(--transition-bounce);
    }
    
    .edit-profile-btn:hover {
        background: var(--white);
        color: var(--primary);
        transform: scale(1.1);
    }
    
    /* Account Tabs */
    .account-tabs-wrapper {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        position: sticky;
        top: 56px;
        z-index: var(--z-sticky);
    }
    
    .account-tabs {
        display: flex;
        gap: var(--spacing-xs);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding: var(--spacing-sm) 0;
    }
    
    .account-tab {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        padding: var(--spacing-sm) var(--spacing-lg);
        color: var(--gray-600);
        font-weight: 500;
        border-radius: var(--radius-full);
        transition: all var(--transition-base);
        white-space: nowrap;
        position: relative;
    }
    
    .account-tab:hover {
        background: var(--gray-100);
        color: var(--primary);
    }
    
    .account-tab.active {
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 700;
    }
    
    .tab-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        min-width: 18px;
        height: 18px;
        padding: 0 4px;
        background: var(--danger);
        color: var(--white);
        font-size: 10px;
        font-weight: 700;
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    /* Account Content */
    .account-content {
        padding: var(--spacing-xl) 0;
    }
    
    .tab-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--spacing-xl);
    }
    
    .tab-header h2 {
        margin: 0;
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }
    
    /* Orders List */
    .orders-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    
    .order-card {
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
        overflow: hidden;
        transition: all var(--transition-base);
    }
    
    .order-card:hover {
        box-shadow: var(--shadow-lg);
    }
    
    .order-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: var(--spacing-md) var(--spacing-lg);
        border-bottom: 1px solid var(--gray-100);
    }
    
    .order-vendor {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }
    
    .order-vendor img {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        object-fit: cover;
    }
    
    .order-vendor h4 {
        margin: 0 0 4px;
    }
    
    .order-number {
        font-size: 13px;
        color: var(--gray-500);
    }
    
    .order-status-badge {
        padding: 4px 12px;
        border-radius: var(--radius-full);
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .order-body {
        padding: var(--spacing-md) var(--spacing-lg);
    }
    
    .order-info {
        display: flex;
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-sm);
    }
    
    .order-info-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-xs);
        color: var(--gray-600);
        font-size: 14px;
    }
    
    .order-driver {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--gray-50);
        border-radius: var(--radius-md);
    }
    
    .driver-call {
        margin-right: auto;
        width: 32px;
        height: 32px;
        background: var(--success);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
    }
    
    .order-footer {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        padding: var(--spacing-md) var(--spacing-lg);
        background: var(--gray-50);
        border-top: 1px solid var(--gray-200);
    }
    
    .order-footer .btn {
        flex: 1;
        min-width: 100px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: var(--spacing-3xl);
        color: var(--gray-400);
    }
    
    .empty-state i {
        font-size: 64px;
        margin-bottom: var(--spacing-lg);
        opacity: 0.5;
    }
    
    .empty-state h3 {
        margin-bottom: var(--spacing-sm);
        color: var(--gray-600);
    }
    
    /* Profile Form */
    .profile-form {
        max-width: 600px;
    }
    
    .form-section {
        margin-bottom: var(--spacing-xl);
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
    }
    
    .form-section h3 {
        margin-bottom: var(--spacing-lg);
        padding-bottom: var(--spacing-md);
        border-bottom: 1px solid var(--gray-200);
    }
    
    .danger-zone {
        margin-top: var(--spacing-xl);
        padding: var(--spacing-lg);
        background: var(--danger-light);
        border: 1px solid var(--danger);
        border-radius: var(--radius-lg);
    }
    
    .danger-zone h3 {
        color: var(--danger);
    }
    
    /* Notifications List */
    .notifications-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .notification-item {
        display: flex;
        align-items: flex-start;
        gap: var(--spacing-md);
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        position: relative;
        transition: all var(--transition-base);
    }
    
    .notification-item.unread {
        background: var(--primary-soft);
    }
    
    .notification-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .notification-content {
        flex: 1;
    }
    
    .notification-content h4 {
        margin: 0 0 4px;
        font-size: 16px;
    }
    
    .notification-content p {
        color: var(--gray-600);
        margin-bottom: 4px;
    }
    
    .notification-time {
        font-size: 12px;
        color: var(--gray-400);
    }
    
    .unread-indicator {
        width: 10px;
        height: 10px;
        background: var(--primary);
        border-radius: 50%;
        position: absolute;
        top: var(--spacing-md);
        right: var(--spacing-md);
    }
    
    /* Loyalty Tab */
    .loyalty-header {
        margin-bottom: var(--spacing-xl);
    }
    
    .loyalty-points-card {
        display: flex;
        align-items: center;
        gap: var(--spacing-xl);
        padding: var(--spacing-xl);
        background: var(--gradient-primary);
        border-radius: var(--radius-xl);
        color: var(--white);
        margin-bottom: var(--spacing-lg);
    }
    
    .points-circle {
        width: 120px;
        height: 120px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        border: 4px solid rgba(255,255,255,0.5);
    }
    
    .points-number {
        font-size: 48px;
        font-weight: 800;
        line-height: 1;
    }
    
    .points-label {
        font-size: 16px;
        opacity: 0.9;
    }
    
    .points-info h3 {
        color: var(--white);
        margin-bottom: var(--spacing-sm);
    }
    
    .loyalty-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-md);
    }
    
    .stat-card {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
    }
    
    .stat-card i {
        font-size: 32px;
        color: var(--primary);
    }
    
    .stat-card div {
        display: flex;
        flex-direction: column;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--gray-900);
    }
    
    .stat-label {
        font-size: 13px;
        color: var(--gray-500);
    }
    
    .loyalty-levels {
        margin-bottom: var(--spacing-xl);
    }
    
    .levels-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-lg);
        margin-top: var(--spacing-lg);
    }
    
    .level-card {
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
        text-align: center;
        position: relative;
        border: 2px solid transparent;
    }
    
    .level-card.achieved {
        border-color: var(--success);
        background: var(--success-light);
    }
    
    .level-card.next {
        border-color: var(--primary);
    }
    
    .level-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto var(--spacing-md);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .level-card h4 {
        margin-bottom: var(--spacing-xs);
    }
    
    .level-requirement {
        color: var(--gray-500);
        margin-bottom: var(--spacing-md);
    }
    
    .level-rewards {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-xs);
        margin-bottom: var(--spacing-md);
    }
    
    .level-rewards span {
        font-size: 13px;
        color: var(--gray-600);
    }
    
    .level-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: var(--radius-full);
        font-size: 12px;
        font-weight: 600;
    }
    
    .level-badge.achieved {
        background: var(--success);
        color: var(--white);
    }
    
    .level-progress {
        margin-top: var(--spacing-md);
    }
    
    .progress-bar {
        height: 8px;
        background: var(--gray-200);
        border-radius: var(--radius-full);
        overflow: hidden;
        margin-bottom: var(--spacing-xs);
    }
    
    .progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: var(--radius-full);
    }
    
    .loyalty-history {
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
    }
    
    .history-list {
        margin-top: var(--spacing-md);
    }
    
    .history-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
        padding: var(--spacing-md) 0;
        border-bottom: 1px solid var(--gray-100);
    }
    
    .history-item:last-child {
        border-bottom: none;
    }
    
    .history-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .history-details {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .history-title {
        font-weight: 600;
    }
    
    .history-date {
        font-size: 12px;
        color: var(--gray-500);
    }
    
    .history-points {
        font-weight: 700;
        font-size: 18px;
    }
    
    .history-points.earned {
        color: var(--success);
    }
    
    .history-points.redeemed {
        color: var(--danger);
    }
    
    /* Order Details Modal */
    .modal-lg {
        max-width: 700px;
    }
    
    .order-details {
        padding: var(--spacing-sm);
    }
    
    .order-detail-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--spacing-lg);
    }
    
    .order-detail-status {
        font-weight: 700;
    }
    
    .order-detail-vendor {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md);
        background: var(--gray-50);
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-lg);
    }
    
    .order-detail-items {
        margin-bottom: var(--spacing-lg);
    }
    
    .order-detail-item {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-sm) 0;
        border-bottom: 1px dashed var(--gray-200);
    }
    
    .order-detail-summary {
        padding: var(--spacing-md);
        background: var(--gray-50);
        border-radius: var(--radius-md);
        margin-bottom: var(--spacing-lg);
    }
    
    .order-detail-summary .summary-row {
        display: flex;
        justify-content: space-between;
        padding: var(--spacing-xs) 0;
    }
    
    .order-detail-summary .total {
        font-weight: 800;
        font-size: 18px;
        margin-top: var(--spacing-sm);
        padding-top: var(--spacing-sm);
        border-top: 1px solid var(--gray-300);
    }
    
    .order-timeline {
        margin-top: var(--spacing-lg);
    }
    
    .timeline {
        margin-top: var(--spacing-md);
    }
    
    .timeline-item {
        display: flex;
        gap: var(--spacing-md);
        padding-bottom: var(--spacing-md);
        position: relative;
    }
    
    .timeline-item::before {
        content: '';
        position: absolute;
        right: 16px;
        top: 32px;
        bottom: -8px;
        width: 2px;
        background: var(--gray-300);
    }
    
    .timeline-item:last-child::before {
        display: none;
    }
    
    .timeline-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-size: 14px;
        flex-shrink: 0;
        z-index: 1;
    }
    
    .timeline-content {
        flex: 1;
    }
    
    .timeline-status {
        font-weight: 700;
        display: block;
        margin-bottom: 4px;
    }
    
    .timeline-time {
        font-size: 12px;
        color: var(--gray-500);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .profile-summary {
            flex-wrap: wrap;
        }
        
        .edit-profile-btn {
            margin-right: auto;
        }
        
        .loyalty-stats {
            grid-template-columns: 1fr;
        }
        
        .levels-container {
            grid-template-columns: 1fr;
        }
        
        .order-footer .btn {
            min-width: calc(50% - 4px);
        }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
