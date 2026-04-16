<?php
/**
 * الدوال المساعدة العامة
 * Saree3 - تطبيق توصيل ومارت
 * @version 2.0 - نظيف، آمن، بدون أخطاء
 */

defined('APP_NAME') or die('Access Denied');

// =====================================================
// دوال الأمان والتنظيف
// =====================================================

/**
 * تنظيف مدخلات المستخدم
 */
function clean_input(string $data): string {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * تنظيف المخرجات للحماية من XSS
 */
function escape($data): string {
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

/**
 * التحقق من صحة رقم الهاتف (دولي)
 */
function is_valid_phone(string $phone): bool {
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    return (bool) preg_match('/^\+?[0-9]{7,15}$/', $phone);
}

/**
 * تطبيع رقم الهاتف: إزالة المسافات والرموز فقط
 */
function normalize_phone(string $phone): string {
    return preg_replace('/[\s\-\(\)]/', '', $phone);
}

/**
 * توحيد صيغة الجوال السعودي إلى +9665XXXXXXXX
 */
function format_saudi_phone(string $phone): string {
    $phone = preg_replace('/\D+/', '', $phone);

    if (str_starts_with($phone, '00966')) {
        $phone = substr($phone, 5);
    } elseif (str_starts_with($phone, '966')) {
        $phone = substr($phone, 3);
    } elseif (str_starts_with($phone, '05')) {
        $phone = substr($phone, 1);
    }

    if (!str_starts_with($phone, '5')) {
        $phone = '5' . ltrim($phone, '0');
    }

    return '+966' . $phone;
}

/**
 * توليد رقم طلب فريد
 */
function generate_order_number(): string {
    return 'ORD-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * توليد رقم بلاغ فريد
 */
function generate_report_number(): string {
    return 'REP-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

/**
 * توليد رمز OTP رقمي عشوائي آمن
 */
function generate_otp(int $length = 6): string {
    $max = (int) pow(10, $length) - 1;
    return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
}

/**
 * توليد Token عشوائي آمن
 */
function generate_token(int $length = 32): string {
    return bin2hex(random_bytes((int) ceil($length / 2)));
}

/**
 * توليد slug من النص (عربي أو إنجليزي)
 */
function generate_slug(string $string): string {
    $string = preg_replace('/[^\p{L}\p{N}]+/u', '-', $string);
    $string = trim($string, '-');
    return mb_strtolower($string, 'UTF-8');
}

// =====================================================
// دوال الجلسة والمصادقة
// =====================================================

/**
 * بدء الجلسة بشكل آمن
 */
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

/**
 * التحقق من تسجيل دخول المستخدم
 */
function is_logged_in(): bool {
    start_session();
    return !empty($_SESSION['user_id']);
}

/**
 * الحصول على ID المستخدم الحالي
 */
function get_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * الحصول على بيانات المستخدم الحالي من DB
 */
function get_current_user_data(): ?array {
    if (!is_logged_in()) return null;
    return db_fetch("SELECT * FROM users WHERE id = ? AND status = 'active'", [get_current_user_id()]);
}

/**
 * التحقق من صلاحية الجلسة (مقارنة بـ DB)
 */
function check_session_validity(): bool {
    if (!is_logged_in()) return false;

    $token = $_SESSION['session_token'] ?? null;
    if (!$token) return false;

    $session = db_fetch(
        "SELECT id FROM user_sessions 
         WHERE user_id = ? AND session_token = ? AND expires_at > NOW()",
        [get_current_user_id(), $token]
    );

    return $session !== null;
}

// =====================================================
// دوال التحقق من الصلاحيات
// =====================================================

function is_admin(): bool {
    start_session();
    return !empty($_SESSION['admin_id']);
}

function is_vendor(): bool {
    start_session();
    return !empty($_SESSION['vendor_id']);
}

function is_driver(): bool {
    start_session();
    return !empty($_SESSION['driver_id']);
}

function get_current_admin(): ?array {
    if (!is_admin()) return null;
    return db_fetch("SELECT * FROM admins WHERE id = ?", [(int)$_SESSION['admin_id']]);
}

function get_current_vendor(): ?array {
    if (!is_vendor()) return null;
    return db_fetch("SELECT * FROM vendors WHERE id = ?", [(int)$_SESSION['vendor_id']]);
}

function get_current_driver(): ?array {
    if (!is_driver()) return null;
    return db_fetch("SELECT * FROM drivers WHERE id = ?", [(int)$_SESSION['driver_id']]);
}

// =====================================================
// دوال التنسيق والعرض
// =====================================================

/**
 * تنسيق السعر مع رمز العملة
 */
function format_price($price, bool $with_symbol = true): string {
    $formatted = number_format((float)$price, 2);
    if (!$with_symbol) return $formatted;

    $symbol   = get_setting('currency_symbol', 'ر.س');
    $position = get_setting('currency_position', 'after');

    return $position === 'before'
        ? $symbol . ' ' . $formatted
        : $formatted . ' ' . $symbol;
}

/**
 * تنسيق التاريخ بالعربية
 */
function format_date($date, string $format = 'd F Y - H:i'): string {
    if (!$date) return '';

    $timestamp = is_numeric($date) ? (int)$date : strtotime($date);
    if ($timestamp === false) return '';

    if (class_exists('IntlDateFormatter')) {
        $formatter = new IntlDateFormatter(
            'ar_EG',
            IntlDateFormatter::LONG,
            IntlDateFormatter::SHORT,
            date_default_timezone_get(),
            IntlDateFormatter::GREGORIAN
        );
        return $formatter->format($timestamp);
    }

    $months_ar = [
        1  => 'يناير',  2  => 'فبراير', 3  => 'مارس',
        4  => 'أبريل',  5  => 'مايو',   6  => 'يونيو',
        7  => 'يوليو',  8  => 'أغسطس',  9  => 'سبتمبر',
        10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر',
    ];

    $days_ar = [
        'Saturday'  => 'السبت',    'Sunday'    => 'الأحد',
        'Monday'    => 'الإثنين',  'Tuesday'   => 'الثلاثاء',
        'Wednesday' => 'الأربعاء', 'Thursday'  => 'الخميس',
        'Friday'    => 'الجمعة',
    ];

    $day_name  = date('l', $timestamp);
    $month_num = (int) date('n', $timestamp);
    $day_num   = date('j', $timestamp);
    $year      = date('Y', $timestamp);
    $time      = date('H:i', $timestamp);

    $day_ar   = $days_ar[$day_name]    ?? $day_name;
    $month_ar = $months_ar[$month_num] ?? date('F', $timestamp);

    return "{$day_ar}، {$day_num} {$month_ar} {$year} - {$time}";
}

/**
 * الوقت النسبي بالعربية
 */
function time_ago($datetime): string {
    $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    if ($timestamp === false) return '';

    $diff = max(0, time() - $timestamp);

    $intervals = [
        ['limit' => 60,          'singular' => 'لحظات', 'plural' => 'لحظات',  'divisor' => 1,        'prefix' => ''],
        ['limit' => 3600,        'singular' => 'دقيقة', 'plural' => 'دقائق',  'divisor' => 60,       'prefix' => 'منذ '],
        ['limit' => 86400,       'singular' => 'ساعة',  'plural' => 'ساعات',  'divisor' => 3600,     'prefix' => 'منذ '],
        ['limit' => 2592000,     'singular' => 'يوم',   'plural' => 'أيام',   'divisor' => 86400,    'prefix' => 'منذ '],
        ['limit' => 31536000,    'singular' => 'شهر',   'plural' => 'أشهر',   'divisor' => 2592000,  'prefix' => 'منذ '],
        ['limit' => PHP_INT_MAX, 'singular' => 'سنة',   'plural' => 'سنوات',  'divisor' => 31536000, 'prefix' => 'منذ '],
    ];

    if ($diff < 60) return 'منذ لحظات';

    foreach ($intervals as $interval) {
        if ($diff < $interval['limit']) {
            $count = (int) floor($diff / $interval['divisor']);
            $word  = $count === 1 ? $interval['singular'] : $interval['plural'];
            return $interval['prefix'] . $count . ' ' . $word;
        }
    }

    return format_date($datetime);
}

/**
 * عرض رقم الهاتف بشكل جميل
 */
function format_phone_display(string $phone): string {
    $phone = normalize_phone($phone);
    if (preg_match('/^(\+\d{2,3})(\d{3})(\d{3,4})(\d{3,4})$/', $phone, $m)) {
        return "{$m[1]} {$m[2]} {$m[3]} {$m[4]}";
    }
    return $phone;
}

/**
 * اختصار النص مع الحفاظ على ترميز UTF-8
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    $text = strip_tags($text);
    if (mb_strlen($text, 'UTF-8') <= $length) return $text;
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

// =====================================================
// دوال حالة الطلب
// =====================================================

function get_order_status_arabic(string $status): string {
    return [
        'new'       => 'جديد',
        'accepted'  => 'تم القبول',
        'preparing' => 'قيد التحضير',
        'on_way'    => 'خرج للتوصيل',
        'delivered' => 'تم التوصيل',
        'cancelled' => 'ملغي',
    ][$status] ?? $status;
}

function get_order_status_color(string $status): string {
    return [
        'new'       => '#FF9800',
        'accepted'  => '#2196F3',
        'preparing' => '#9C27B0',
        'on_way'    => '#FF6B35',
        'delivered' => '#4CAF50',
        'cancelled' => '#F44336',
    ][$status] ?? '#757575';
}

function get_order_status_icon(string $status): string {
    return [
        'new'       => '🆕',
        'accepted'  => '✅',
        'preparing' => '🍳',
        'on_way'    => '🛵',
        'delivered' => '🎉',
        'cancelled' => '❌',
    ][$status] ?? '📦';
}

// =====================================================
// دوال التوجيه
// =====================================================

function redirect(string $url): void {
    header('Location: ' . $url, true, 302);
    exit;
}

function redirect_with_message(string $url, string $message, string $type = 'success'): void {
    start_session();
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type']    = $type;
    redirect($url);
}

function show_flash_message(): string {
    start_session();
    if (empty($_SESSION['flash_message'])) return '';

    $message = escape($_SESSION['flash_message']);
    $type    = escape($_SESSION['flash_type'] ?? 'info');

    unset($_SESSION['flash_message'], $_SESSION['flash_type']);

    return "<div class='alert alert-{$type}' role='alert'>{$message}</div>";
}

// =====================================================
// دوال JSON والـ API
// =====================================================

function json_response(array $data, int $status_code = 200): void {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function json_success($data = null, string $message = 'تمت العملية بنجاح'): void {
    json_response(['status' => 'success', 'message' => $message, 'data' => $data]);
}

function json_error(string $message = 'حدث خطأ', int $status_code = 400): void {
    json_response(['status' => 'error', 'message' => $message], $status_code);
}

// =====================================================
// دوال رفع الملفات
// =====================================================

/**
 * رفع صورة بشكل آمن مع فحص MIME الحقيقي
 */
function upload_image(array $file, string $subfolder = 'general') {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > MAX_UPLOAD_SIZE || $file['size'] === 0) {
        return false;
    }

    $allowed_mimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!array_key_exists($mime, $allowed_mimes)) {
        return false;
    }

    if (@getimagesize($file['tmp_name']) === false) {
        return false;
    }

    $extension  = $allowed_mimes[$mime];
    $upload_dir = rtrim(UPLOAD_PATH, '/') . '/' . $subfolder . '/';

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
        return false;
    }

    $filename    = bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
    $destination = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return false;
    }

    return 'uploads/' . $subfolder . '/' . $filename;
}

/**
 * بناء رابط أصل (Asset) يدعم:
 * - رابط خارجي كامل
 * - مسار نسبي داخل المشروع
 * - fallback افتراضي
 */
function asset_url(?string $path, string $default = 'assets/images/default-restaurant.jpg'): string {
    if (empty($path)) {
        return BASE_URL . ltrim($default, '/');
    }

    if (filter_var($path, FILTER_VALIDATE_URL)) {
        return $path;
    }

    return BASE_URL . ltrim($path, '/');
}

// =====================================================
// دوال المسافات والتوصيل
// =====================================================

/**
 * حساب المسافة بين نقطتين (Haversine formula) - بالكيلومتر
 */
function calculate_distance(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $earth_radius = 6371.0;

    $dlat = deg2rad($lat2 - $lat1);
    $dlon = deg2rad($lon2 - $lon1);

    $a = sin($dlat / 2) ** 2
       + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon / 2) ** 2;

    return $earth_radius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * حساب رسوم التوصيل من DB أولاً ثم fallback من الإعدادات
 */
function calculate_delivery_fee(float $distance, ?int $zone_id = null): float {
    if ($zone_id !== null) {
        $fee = db_fetch(
            "SELECT fee FROM delivery_fees WHERE zone_id = ? ORDER BY id DESC LIMIT 1",
            [$zone_id]
        );
        if ($fee && isset($fee['fee'])) {
            return (float) $fee['fee'];
        }
    }

    $base_fee   = (float) get_setting('delivery_base_fee', 10);
    $per_km_fee = (float) get_setting('delivery_per_km_fee', 2);

    return $base_fee + ($distance * $per_km_fee);
}

// =====================================================
// دوال الإشعارات
// =====================================================

/**
 * إرسال إشعار داخل التطبيق
 */
function send_notification(
    $user_id,
    $user_type,
    $title,
    $body,
    $type           = 'general',
    $reference_id   = null,
    $reference_type = null
) {
    return db_insert('notifications', [
        'user_id'        => $user_id,
        'user_type'      => $user_type,
        'title_ar'       => $title,
        'body_ar'        => $body,
        'type'           => $type,
        'reference_id'   => $reference_id,
        'reference_type' => $reference_type,
        'is_read'        => 0,
        'created_at'     => date('Y-m-d H:i:s'),
    ]);
}

// =====================================================
// دوال الإعدادات
// =====================================================

// =====================================================
// دوال تسجيل النشاطات
// =====================================================

/**
 * تسجيل نشاط في النظام
 */
function log_activity(
    string  $user_type,
    int     $user_id,
    string  $action,
    $description = null,
    $data        = null
) {
    return db_insert('activity_logs', [
        'user_type'   => $user_type,
        'user_id'     => $user_id,
        'action'      => $action,
        'description' => $description,
        'ip_address'  => $_SERVER['REMOTE_ADDR']     ?? null,
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? null,
        'data'        => $data !== null
                            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                            : null,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}
