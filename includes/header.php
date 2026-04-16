<?php
/**
 * هيدر واجهة العميل - نسخة كاملة مع CSS و JavaScript مدمجين
 * Saree3 - تطبيق توصيل ومارت
 * @version 3.0
 */

// لو BASE_URL مش موجود، نحمل الإعدادات
if (!defined('BASE_URL')) {
    if (file_exists(__DIR__ . '/config/config.php')) {
        require_once __DIR__ . '/config/config.php';
    } elseif (file_exists(dirname(__DIR__) . '/config/config.php')) {
        require_once dirname(__DIR__) . '/config/config.php';
    }
}

if (!defined('BASE_URL')) {
    die('Configuration not found');
}

start_session();

// الحصول على بيانات المستخدم الحالي
$current_user = null;
if (is_logged_in()) {
    $current_user = get_current_user_data();
}

// الحصول على عدد عناصر السلة
$cart_count = 0;
if (is_logged_in()) {
    $cart = db_fetch(
        "SELECT COUNT(*) as count FROM cart_items ci 
         JOIN carts c ON ci.cart_id = c.id 
         WHERE c.user_id = ?",
        [get_current_user_id()]
    );
    $cart_count = (int)($cart['count'] ?? 0);
} elseif (!empty($_SESSION['cart_session_id'])) {
    $cart = db_fetch(
        "SELECT COUNT(*) as count FROM cart_items ci 
         JOIN carts c ON ci.cart_id = c.id 
         WHERE c.session_id = ?",
        [$_SESSION['cart_session_id']]
    );
    $cart_count = (int)($cart['count'] ?? 0);
}

// الحصول على الإشعارات غير المقروءة
$unread_notifications = 0;
if (is_logged_in()) {
    $notif = db_fetch(
        "SELECT COUNT(*) as count FROM notifications 
         WHERE user_id = ? AND user_type = 'user' AND is_read = 0",
        [get_current_user_id()]
    );
    $unread_notifications = (int)($notif['count'] ?? 0);
}

// الصفحة الحالية
$current_page = basename(parse_url($_SERVER['PHP_SELF'] ?? '', PHP_URL_PATH));

$css_version = @filemtime(($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/css/style.css') ?: '1';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#FF6B35">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <meta name="description" content="سريع - اطلب طعامك ومقاضيك بسرعة وسهولة">
    <meta name="keywords" content="توصيل, طلبات, مطاعم, مارت, مقاضي, سريع">

    <meta property="og:title" content="<?= escape(APP_NAME) ?> - التوصيل السريع">
    <meta property="og:description" content="اطلب طعامك ومقاضيك بسرعة وسهولة">
    <meta property="og:image" content="<?= escape(asset_url('assets/images/og-image.png')) ?>">
    <meta property="og:type" content="website">

    <?= csrf_meta() ?>

    <link rel="icon" type="image/x-icon" href="<?= escape(BASE_URL) ?>assets/images/favicon.ico">
    <link rel="apple-touch-icon" href="<?= escape(BASE_URL) ?>assets/images/apple-touch-icon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= escape(BASE_URL) ?>assets/css/style.css?v=<?= $css_version ?>">

    <style>
        /* =====================================================
           Side Menu CSS - مدمج في الهيدر
           ===================================================== */
        .side-menu {
            position: fixed !important;
            top: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            z-index: 99999 !important;
            pointer-events: none !important;
        }
        
        .side-menu.active {
            pointer-events: auto !important;
        }
        
        .side-menu-overlay {
            position: fixed !important;
            inset: 0 !important;
            background: rgba(0, 0, 0, 0.5) !important;
            backdrop-filter: blur(4px) !important;
            -webkit-backdrop-filter: blur(4px) !important;
            opacity: 0 !important;
            visibility: hidden !important;
            transition: all 0.3s ease !important;
            z-index: 99998 !important;
        }
        
        .side-menu.active .side-menu-overlay {
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .side-menu-content {
            position: fixed !important;
            right: 0 !important;
            top: 0 !important;
            bottom: 0 !important;
            width: 85% !important;
            max-width: 320px !important;
            background: #FFFFFF !important;
            box-shadow: -5px 0 30px rgba(0, 0, 0, 0.15) !important;
            transform: translateX(100%) !important;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
            z-index: 99999 !important;
            overflow-y: auto !important;
            display: flex !important;
            flex-direction: column !important;
        }
        
        .side-menu.active .side-menu-content {
            transform: translateX(0) !important;
        }
        
        .side-menu-header {
            padding: 24px 20px !important;
            background: linear-gradient(135deg, #FF6B35 0%, #FF8F65 100%) !important;
            color: white !important;
        }
        
        .side-menu-header .user-info {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
        }
        
        .side-menu-header .user-avatar {
            width: 56px !important;
            height: 56px !important;
            background: white !important;
            border-radius: 50% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            overflow: hidden !important;
            border: 3px solid rgba(255, 255, 255, 0.3) !important;
        }
        
        .side-menu-header .user-avatar img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        
        .side-menu-header .avatar-placeholder {
            width: 100% !important;
            height: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            background: #E85A2A !important;
            color: white !important;
            font-size: 24px !important;
            font-weight: 700 !important;
        }
        
        .side-menu-header .user-details h3 {
            color: white !important;
            margin-bottom: 4px !important;
            font-size: 18px !important;
        }
        
        .side-menu-header .user-details p {
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 14px !important;
        }
        
        .side-menu-header .login-prompt {
            text-align: center !important;
        }
        
        .side-menu-header .login-prompt i {
            font-size: 48px !important;
            color: white !important;
            margin-bottom: 12px !important;
        }
        
        .side-menu-header .login-prompt h3 {
            color: white !important;
            margin-bottom: 8px !important;
        }
        
        .side-menu-header .login-prompt p {
            color: rgba(255, 255, 255, 0.9) !important;
            font-size: 14px !important;
            margin-bottom: 20px !important;
        }
        
        .side-menu-header .btn-primary {
            display: inline-block !important;
            padding: 12px 24px !important;
            background: white !important;
            color: #FF6B35 !important;
            border-radius: 30px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            width: 100% !important;
        }
        
        .side-menu-nav {
            flex: 1 !important;
            padding: 16px 0 !important;
        }
        
        .side-menu-nav ul {
            list-style: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }
        
        .side-menu-nav li a {
            display: flex !important;
            align-items: center !important;
            gap: 14px !important;
            padding: 14px 20px !important;
            color: #374151 !important;
            text-decoration: none !important;
            font-weight: 500 !important;
            transition: all 0.2s !important;
            border-right: 3px solid transparent !important;
        }
        
        .side-menu-nav li a i {
            width: 24px !important;
            font-size: 20px !important;
            color: #6B7280 !important;
        }
        
        .side-menu-nav li a:hover,
        .side-menu-nav li a.active {
            background: #FFF1EB !important;
            color: #FF6B35 !important;
            border-right-color: #FF6B35 !important;
        }
        
        .side-menu-nav li a:hover i,
        .side-menu-nav li a.active i {
            color: #FF6B35 !important;
        }
        
        .side-menu-nav .badge {
            margin-right: auto !important;
            padding: 2px 8px !important;
            background: #EF4444 !important;
            color: white !important;
            font-size: 11px !important;
            font-weight: 700 !important;
            border-radius: 20px !important;
        }
        
        .side-menu-footer {
            padding: 20px !important;
            border-top: 1px solid #F3F4F6 !important;
        }
        
        .side-menu-footer .btn-outline-danger {
            width: 100% !important;
            padding: 12px !important;
            background: transparent !important;
            border: 1px solid #FEE2E2 !important;
            color: #EF4444 !important;
            border-radius: 12px !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
        }
        
        .side-menu-footer .btn-outline-danger:hover {
            background: #EF4444 !important;
            color: white !important;
        }
        
        .header-avatar {
            width: 36px !important;
            height: 36px !important;
            border-radius: 50% !important;
            object-fit: cover !important;
            border: 2px solid white !important;
        }
        
        .notif-dot {
            position: absolute !important;
            top: 6px !important;
            left: 6px !important;
            width: 10px !important;
            height: 10px !important;
            background: #EF4444 !important;
            border-radius: 50% !important;
            border: 2px solid white !important;
        }
    </style>
</head>
<body>

    <!-- Splash Screen -->
    <?php if ($current_page === 'index.php' && empty($_COOKIE['splash_shown'])): ?>
    <div id="splash-screen" style="position: fixed; inset: 0; background: linear-gradient(135deg, #FF6B35 0%, #FF8F65 100%); z-index: 99999; display: flex; align-items: center; justify-content: center;">
        <div style="text-align: center;">
            <h1 style="color: white; font-size: 48px;"><?= APP_NAME ?></h1>
            <p style="color: white;">التوصيل السريع</p>
        </div>
    </div>
    <script>
        document.cookie = "splash_shown=1; max-age=86400; path=/; SameSite=Lax";
        setTimeout(function() {
            var splash = document.getElementById('splash-screen');
            if (splash) splash.style.display = 'none';
        }, 2000);
    </script>
    <?php endif; ?>

    <!-- Header -->
    <header class="main-header" style="position: sticky; top: 0; z-index: 200; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); padding: 8px 0; border-bottom: 1px solid rgba(255,107,53,0.1);">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 16px; display: flex; align-items: center; justify-content: space-between; min-height: 56px;">
            
            <div style="display: flex; align-items: center; gap: 8px; min-width: 80px;">
                <?php if (!in_array($current_page, ['index.php', 'mart.php'])): ?>
                <button onclick="history.back()" style="width: 44px; height: 44px; background: white; border: none; border-radius: 50%; font-size: 20px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <i class="fas fa-arrow-right"></i>
                </button>
                <?php else: ?>
                <button id="menuToggle" style="width: 44px; height: 44px; background: white; border: none; border-radius: 50%; font-size: 20px; cursor: pointer; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <i class="fas fa-bars"></i>
                </button>
                <?php endif; ?>
            </div>
            
            <div style="flex: 1; text-align: center;">
                <?php if ($current_page === 'index.php'): ?>
                <button id="locationSelector" style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 20px; background: #FFF1EB; border: none; border-radius: 30px; color: #FF6B35; font-weight: 600; font-size: 14px; cursor: pointer;">
                    <i class="fas fa-map-marker-alt"></i>
                    <span id="selectedLocation">اختر موقعك</span>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <?php else: ?>
                <h1 style="font-size: 20px; margin: 0;">
                    <?php
                    $page_titles = ['mart.php' => 'المارت', 'store.php' => 'المتجر', 'checkout.php' => 'إتمام الطلب', 'account.php' => 'حسابي', 'wheel.php' => 'دولاب الحظ', 'login.php' => 'تسجيل الدخول'];
                    echo $page_titles[$current_page] ?? APP_NAME;
                    ?>
                </h1>
                <?php endif; ?>
            </div>
            
            <div style="display: flex; align-items: center; gap: 8px; min-width: 80px; justify-content: flex-end;">
                <?php if (in_array($current_page, ['index.php', 'mart.php', 'store.php'])): ?>
                <a href="<?= BASE_URL ?>checkout.php" style="position: relative; width: 44px; height: 44px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #374151; text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cart_count > 0): ?>
                    <span id="cartBadge" style="position: absolute; top: -4px; left: -4px; min-width: 20px; height: 20px; background: #FF6B35; color: white; font-size: 12px; font-weight: 700; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 6px;"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <?php if (is_logged_in()): ?>
                <a href="<?= BASE_URL ?>account.php" style="position: relative; width: 44px; height: 44px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #374151; text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden;">
                    <?php if (!empty($current_user['avatar'])): ?>
                    <img src="<?= asset_url($current_user['avatar'], 'assets/images/default-avatar.png') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                    <?php if ($unread_notifications > 0): ?>
                    <span style="position: absolute; top: 6px; left: 6px; width: 10px; height: 10px; background: #EF4444; border-radius: 50%; border: 2px solid white;"></span>
                    <?php endif; ?>
                </a>
                <?php else: ?>
                <a href="<?= BASE_URL ?>login.php" style="width: 44px; height: 44px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #374151; text-decoration: none; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    <i class="fas fa-sign-in-alt"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Side Menu -->
    <div id="sideMenu" style="position: fixed; top: 0; right: 0; bottom: 0; z-index: 99999; pointer-events: none;">
        <div id="menuOverlay" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); opacity: 0; visibility: hidden; transition: all 0.3s; z-index: 99998;"></div>
        <div id="sideMenuContent" style="position: fixed; right: 0; top: 0; bottom: 0; width: 85%; max-width: 320px; background: white; transform: translateX(100%); transition: transform 0.3s; z-index: 99999; overflow-y: auto; display: flex; flex-direction: column;">
            
            <div style="padding: 24px 20px; background: linear-gradient(135deg, #FF6B35 0%, #FF8F65 100%); color: white;">
                <?php if (is_logged_in() && $current_user): ?>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 56px; height: 56px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if (!empty($current_user['avatar'])): ?>
                        <img src="<?= asset_url($current_user['avatar'], 'assets/images/default-avatar.png') ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #E85A2A; color: white; font-size: 24px; font-weight: 700;"><?= mb_substr($current_user['name'] ?? '?', 0, 1) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 style="color: white; margin: 0 0 4px; font-size: 18px;"><?= escape($current_user['name'] ?? '') ?></h3>
                        <p style="margin: 0; opacity: 0.9; font-size: 14px;"><?= format_phone_display($current_user['phone'] ?? '') ?></p>
                    </div>
                </div>
                <?php else: ?>
                <div style="text-align: center;">
                    <i class="fas fa-user-circle" style="font-size: 48px; color: white; margin-bottom: 12px;"></i>
                    <h3 style="color: white; margin-bottom: 8px;">مرحباً بك!</h3>
                    <p style="color: rgba(255,255,255,0.9); font-size: 14px; margin-bottom: 20px;">سجل دخولك للاستمتاع بكل المزايا</p>
                    <a href="<?= BASE_URL ?>login.php" style="display: block; padding: 12px; background: white; color: #FF6B35; border-radius: 30px; font-weight: 700; text-decoration: none;">تسجيل الدخول</a>
                </div>
                <?php endif; ?>
            </div>
            
            <nav style="flex: 1; padding: 16px 0;">
                <ul style="list-style: none; margin: 0; padding: 0;">
                    <li><a href="<?= BASE_URL ?>index.php" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: <?= $current_page === 'index.php' ? '#FF6B35' : '#374151' ?>; text-decoration: none; font-weight: 500; border-right: 3px solid <?= $current_page === 'index.php' ? '#FF6B35' : 'transparent' ?>; background: <?= $current_page === 'index.php' ? '#FFF1EB' : 'transparent' ?>;"><i class="fas fa-home"></i> الرئيسية</a></li>
                    <li><a href="<?= BASE_URL ?>mart.php" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: <?= $current_page === 'mart.php' ? '#FF6B35' : '#374151' ?>; text-decoration: none; font-weight: 500;"><i class="fas fa-shopping-basket"></i> المارت</a></li>
                    <?php if (is_logged_in()): ?>
                    <li><a href="<?= BASE_URL ?>account.php?tab=orders" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-receipt"></i> طلباتي</a></li>
                    <li><a href="<?= BASE_URL ?>account.php?tab=notifications" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-bell"></i> الإشعارات <?php if ($unread_notifications > 0): ?><span style="margin-right: auto; background: #EF4444; color: white; padding: 2px 8px; border-radius: 20px; font-size: 11px;"><?= $unread_notifications ?></span><?php endif; ?></a></li>
                    <li><a href="<?= BASE_URL ?>wheel.php" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-ticket-alt"></i> دولاب الحظ</a></li>
                    <?php endif; ?>
                    <li><a href="#" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-tag"></i> العروض</a></li>
                    <li><a href="#" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-headset"></i> مركز المساعدة</a></li>
                    <li><a href="#" style="display: flex; align-items: center; gap: 14px; padding: 14px 20px; color: #374151; text-decoration: none; font-weight: 500;"><i class="fas fa-info-circle"></i> عن سريع</a></li>
                </ul>
            </nav>
            
            <?php if (is_logged_in()): ?>
            <div style="padding: 20px; border-top: 1px solid #F3F4F6;">
                <button id="logoutBtn" style="width: 100%; padding: 12px; background: transparent; border: 1px solid #FEE2E2; color: #EF4444; border-radius: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // =====================================================
    // Side Menu JavaScript - مدمج في الهيدر
    // =====================================================
    (function() {
        const menuToggle = document.getElementById('menuToggle');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');
        const sideMenuContent = document.getElementById('sideMenuContent');
        
        function openMenu() {
            sideMenu.style.pointerEvents = 'auto';
            menuOverlay.style.opacity = '1';
            menuOverlay.style.visibility = 'visible';
            sideMenuContent.style.transform = 'translateX(0)';
            document.body.style.overflow = 'hidden';
        }
        
        function closeMenu() {
            sideMenu.style.pointerEvents = 'none';
            menuOverlay.style.opacity = '0';
            menuOverlay.style.visibility = 'hidden';
            sideMenuContent.style.transform = 'translateX(100%)';
            document.body.style.overflow = '';
        }
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                openMenu();
            });
        }
        
        if (menuOverlay) {
            menuOverlay.addEventListener('click', closeMenu);
        }
        
        // إغلاق القائمة عند النقر على أي رابط
        document.querySelectorAll('#sideMenuContent a').forEach(link => {
            link.addEventListener('click', closeMenu);
        });
        
        // زر تسجيل الخروج
        document.getElementById('logoutBtn')?.addEventListener('click', function() {
            if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                fetch('<?= BASE_URL ?>api/handler.php?action=logout', {
                    method: 'POST',
                    headers: { 'X-CSRF-Token': '<?= get_csrf_token() ?>' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = '<?= BASE_URL ?>index.php';
                    }
                });
            }
        });
        
        // ESC للإغلاق
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sideMenuContent.style.transform === 'translateX(0px)') {
                closeMenu();
            }
        });
    })();
    </script>

    <?= show_flash_message() ?>

    <main class="main-content <?= in_array($current_page, ['index.php', 'mart.php']) ? 'has-bottom-nav' : '' ?>">
