<?php
/**
 * ملف الإعدادات والثوابت الرئيسية
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

// =====================================================
// إعدادات البيئة
// =====================================================
define('ENVIRONMENT', 'development'); // production, development
define('BASE_URL', 'https://benzed.me/'); // غيرها حسب الدومين
define('TIMEZONE', 'Asia/Riyadh');

// =====================================================
// إعدادات الجلسة
// =====================================================
define('SESSION_NAME', 'saree3_session');
define('SESSION_LIFETIME', 86400 * 30); // 30 يوم
define('SESSION_COOKIE_SECURE', false); // true للإنتاج مع HTTPS
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// =====================================================
// إعدادات OTP
// =====================================================
define('OTP_LENGTH', 6);
define('OTP_EXPIRY_MINUTES', 5);
define('OTP_MAX_ATTEMPTS', 3);
define('OTP_RESEND_COOLDOWN', 60); // ثانية

// =====================================================
// إعدادات SMS (Mock للاختبار)
// =====================================================
define('SMS_PROVIDER', 'mock'); // mock, twilio, unifonic
define('SMS_API_KEY', '');
define('SMS_SENDER_NAME', 'Saree3');

// =====================================================
// إعدادات الملفات والرفع
// =====================================================
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// =====================================================
// إعدادات الطلب
// =====================================================
define('SERVICE_FEE', 5);
define('TAX_RATE', 15);
define('MIN_ORDER_DEFAULT', 50);
define('DELIVERY_FEE_DEFAULT', 15);

// =====================================================
// إعدادات دولاب الحظ
// =====================================================
define('WHEEL_MIN_ORDERS_FOR_SPIN', 3);
define('WHEEL_MAX_SPINS_PER_DAY', 1);

// =====================================================
// إعدادات الولاء
// =====================================================
define('LOYALTY_POINTS_PER_ORDER', 10);
define('LOYALTY_POINTS_PER_SAR', 1);

// =====================================================
// إعدادات الأمان
// =====================================================
define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_EXPIRY', 3600); // ساعة واحدة
define('RATE_LIMIT_MAX_REQUESTS', 100);
define('RATE_LIMIT_WINDOW', 60); // ثانية

// =====================================================
// ضبط المنطقة الزمنية
// =====================================================
date_default_timezone_set(TIMEZONE);

// =====================================================
// إعدادات عرض الأخطاء
// =====================================================
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// =====================================================
// إعدادات الجلسة
// =====================================================
ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);

if (SESSION_COOKIE_SECURE) {
    ini_set('session.cookie_secure', 1);
}

// =====================================================
// دوال مساعدة للإعدادات من قاعدة البيانات
// =====================================================
function get_setting($key, $default = null) {
    global $db;
    static $settings_cache = null;
    
    if ($settings_cache === null) {
        $settings_cache = [];
        try {
            if (isset($db) && $db) {
                $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings_cache[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (Exception $e) {
            // سيتم التعامل مع الخطأ لاحقاً
        }
    }
    
    return $settings_cache[$key] ?? $default;
}

// =====================================================
// ثوابت ديناميكية من قاعدة البيانات
// =====================================================
define('APP_NAME', get_setting('app_name', 'سريع'));
define('APP_NAME_EN', get_setting('app_name_en', 'Saree3'));
define('CURRENCY', get_setting('currency', 'SAR'));
define('CURRENCY_SYMBOL', get_setting('currency_symbol', 'ر.س'));

// =====================================================
// إعدادات مسارات الملفات
// =====================================================
define('CONFIG_PATH', __DIR__);
define('INCLUDES_PATH', dirname(__DIR__) . '/includes');
define('PUBLIC_PATH', dirname(__DIR__) . '/public');
define('ASSETS_PATH', dirname(__DIR__) . '/assets');
define('ADMIN_PATH', dirname(__DIR__) . '/admin');
define('VENDOR_PATH', dirname(__DIR__) . '/vendor');
define('DRIVER_PATH', dirname(__DIR__) . '/driver');
define('API_PATH', dirname(__DIR__) . '/api');

// =====================================================
// تحميل ملف الاتصال بقاعدة البيانات
// =====================================================
require_once CONFIG_PATH . '/database.php';

// =====================================================
// تحميل الدوال المساعدة الأساسية
// =====================================================
require_once INCLUDES_PATH . '/helpers.php';
require_once INCLUDES_PATH . '/csrf.php';