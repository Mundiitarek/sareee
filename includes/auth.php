<?php
/**
 * منطق المصادقة والجلسات
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

// =====================================================
// بدء الجلسة
// =====================================================
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// =====================================================
// دوال OTP
// =====================================================

/**
 * إرسال رمز OTP للجوال
 */
function send_otp($phone) {
    global $db;
    
    // تنظيف رقم الجوال
    $phone = format_saudi_phone($phone);
    
    // التحقق من محاولات OTP السابقة (rate limiting)
    $recent_attempts = db_fetch(
        "SELECT COUNT(*) as count FROM otp_codes WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)",
        [$phone]
    );
    
    if ($recent_attempts['count'] >= 5) {
        return ['success' => false, 'message' => 'لقد تجاوزت الحد المسموح من المحاولات. يرجى الانتظار 15 دقيقة.'];
    }
    
    // التحقق من وجود رمز سابق لم ينته
    $existing = db_fetch(
        "SELECT * FROM otp_codes WHERE phone = ? AND verified = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1",
        [$phone]
    );
    
    if ($existing && (time() - strtotime($existing['created_at'])) < OTP_RESEND_COOLDOWN) {
        $remaining = OTP_RESEND_COOLDOWN - (time() - strtotime($existing['created_at']));
        return ['success' => false, 'message' => 'يرجى الانتظار ' . $remaining . ' ثانية قبل إعادة الإرسال.'];
    }
    
    // توليد رمز جديد
    $code = generate_otp(OTP_LENGTH);
    $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
    
    // حذف الرموز القديمة
    $db->prepare("DELETE FROM otp_codes WHERE phone = ?")->execute([$phone]);
    
    // حفظ الرمز في قاعدة البيانات
    $data = [
        'phone' => $phone,
        'code' => $code,
        'expires_at' => $expires_at,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    db_insert('otp_codes', $data);
    
    // إرسال SMS (حسب المزود المحدد)
    $message = "رمز التحقق الخاص بك هو: {$code}\nصالح لمدة " . OTP_EXPIRY_MINUTES . " دقائق\n" . APP_NAME;
    
    // تسجيل في سجل SMS
    $sms_data = [
        'phone' => $phone,
        'message' => $message,
        'type' => 'otp',
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    db_insert('sms_logs', $sms_data);
    
    // في بيئة التطوير، نعرض الرمز
    if (ENVIRONMENT === 'development') {
        return ['success' => true, 'message' => 'تم إرسال الرمز', 'code' => $code, 'phone' => $phone];
    }
    
    // في الإنتاج، نرسل SMS فعلياً
    $sms_result = send_sms_actual($phone, $message);
    
    if ($sms_result) {
        return ['success' => true, 'message' => 'تم إرسال رمز التحقق بنجاح'];
    } else {
        return ['success' => false, 'message' => 'فشل إرسال رمز التحقق. يرجى المحاولة لاحقاً.'];
    }
}

/**
 * التحقق من صحة رمز OTP
 */
function verify_otp($phone, $code) {
    $phone = format_saudi_phone($phone);
    
    $otp = db_fetch(
        "SELECT * FROM otp_codes WHERE phone = ? AND code = ? AND verified = 0 AND expires_at > NOW()",
        [$phone, $code]
    );
    
    if (!$otp) {
        return ['success' => false, 'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.'];
    }
    
    // التحقق من عدد المحاولات
    if ($otp['attempts'] >= OTP_MAX_ATTEMPTS) {
        return ['success' => false, 'message' => 'لقد تجاوزت الحد الأقصى للمحاولات. يرجى طلب رمز جديد.'];
    }
    
    // زيادة عدد المحاولات
    db_update(
        'otp_codes',
        ['attempts' => $otp['attempts'] + 1],
        'id = ?',
        [':id' => $otp['id']]
    );
    
    // إذا كان الرمز صحيحاً
    if ($otp['code'] === $code) {
        // تحديث حالة الرمز
        db_update(
            'otp_codes',
            ['verified' => 1],
            'id = ?',
            [':id' => $otp['id']]
        );
        
        return ['success' => true, 'message' => 'تم التحقق بنجاح'];
    }
    
    return ['success' => false, 'message' => 'رمز التحقق غير صحيح.'];
}

/**
 * إرسال SMS فعلي (يتم تعديله حسب المزود)
 */
function send_sms_actual($phone, $message) {
    $provider = SMS_PROVIDER;
    
    switch ($provider) {
        case 'twilio':
            // تكامل Twilio
            return true;
        case 'unifonic':
            // تكامل Unifonic
            return true;
        case 'mock':
        default:
            // تسجيل فقط
            return true;
    }
}

// =====================================================
// دوال تسجيل الدخول للعملاء
// =====================================================

/**
 * تسجيل دخول أو إنشاء حساب جديد للعميل
 */
function login_or_register_user($phone, $name = null, $remember_me = true) {
    global $db;
    
    $phone = format_saudi_phone($phone);
    
    // البحث عن المستخدم
    $user = db_fetch("SELECT * FROM users WHERE phone = ?", [$phone]);
    
    // إذا لم يكن موجوداً، ننشئ حساباً جديداً
    if (!$user) {
        if (!$name) {
            $name = 'مستخدم ' . substr($phone, -4);
        }
        
        $user_data = [
            'name' => $name,
            'phone' => $phone,
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $user_id = db_insert('users', $user_data);
        $user = db_fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
        
        // تسجيل النشاط
        log_activity('user', $user_id, 'register', 'تسجيل حساب جديد');
    }
    
    // التحقق من حظر المستخدم
    if ($user['is_blocked']) {
        return ['success' => false, 'message' => 'عذراً، تم حظر حسابك. يرجى التواصل مع الدعم.'];
    }
    
    // إنشاء الجلسة
    $session_token = generate_token();
    $device_id = $_POST['device_id'] ?? null;
    $device_name = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // حذف الجلسات القديمة لنفس الجهاز
    if ($device_id) {
        $db->prepare("DELETE FROM user_sessions WHERE user_id = ? AND device_id = ?")->execute([$user['id'], $device_id]);
    }
    
    // حفظ الجلسة الجديدة
    $session_data = [
        'user_id' => $user['id'],
        'session_token' => $session_token,
        'device_id' => $device_id,
        'device_name' => $device_name,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'created_at' => date('Y-m-d H:i:s'),
        'last_activity' => date('Y-m-d H:i:s')
    ];
    
    db_insert('user_sessions', $session_data);
    
    // تعيين متغيرات الجلسة
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['session_token'] = $session_token;
    $_SESSION['user_phone'] = $user['phone'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['login_time'] = time();
    
    // تجديد معرف الجلسة للحماية من Session Fixation
    session_regenerate_id(true);
    
    // تحديث آخر تسجيل دخول
    db_update(
        'users',
        ['last_login' => date('Y-m-d H:i:s')],
        'id = ?',
        [':id' => $user['id']]
    );
    
    // إذا كان "تذكرني" مفعلاً، نمدد عمر الجلسة
    if ($remember_me) {
        setcookie(
            session_name(),
            session_id(),
            time() + SESSION_LIFETIME,
            '/',
            '',
            SESSION_COOKIE_SECURE,
            SESSION_COOKIE_HTTPONLY
        );
    }
    
    // تسجيل النشاط
    log_activity('user', $user['id'], 'login', 'تسجيل دخول');
    
    return [
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'phone' => $user['phone']
        ]
    ];
}

/**
 * تسجيل خروج العميل
 */
function logout_user() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['session_token'])) {
        // حذف الجلسة من قاعدة البيانات
        db_delete(
            'user_sessions',
            'user_id = ? AND session_token = ?',
            [$_SESSION['user_id'], $_SESSION['session_token']]
        );
        
        // تسجيل النشاط
        log_activity('user', $_SESSION['user_id'], 'logout', 'تسجيل خروج');
    }
    
    // تدمير الجلسة
    session_destroy();
    
    return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
}

// =====================================================
// دوال تسجيل الدخول للأدمن
// =====================================================

/**
 * تسجيل دخول الأدمن
 */
function login_admin($phone, $password) {
    $phone = format_saudi_phone($phone);
    
    $admin = db_fetch("SELECT * FROM admins WHERE phone = ? AND status = 1", [$phone]);
    
    if (!$admin) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    if (!password_verify($password, $admin['password_hash'])) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    // إنشاء الجلسة
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_name'] = $admin['name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['admin_login_time'] = time();
    
    session_regenerate_id(true);
    
    // تحديث آخر تسجيل دخول
    db_update(
        'admins',
        ['last_login' => date('Y-m-d H:i:s')],
        'id = ?',
        [':id' => $admin['id']]
    );
    
    // تسجيل النشاط
    log_activity('admin', $admin['id'], 'login', 'تسجيل دخول لوحة التحكم');
    
    return [
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'admin' => [
            'id' => $admin['id'],
            'name' => $admin['name'],
            'role' => $admin['role']
        ]
    ];
}

/**
 * تسجيل خروج الأدمن
 */
function logout_admin() {
    if (isset($_SESSION['admin_id'])) {
        log_activity('admin', $_SESSION['admin_id'], 'logout', 'تسجيل خروج من لوحة التحكم');
    }
    
    session_destroy();
    return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
}

// =====================================================
// دوال تسجيل الدخول للتجار
// =====================================================

/**
 * تسجيل دخول التاجر
 */
function login_vendor($phone, $password) {
    $phone = format_saudi_phone($phone);
    
    $vendor = db_fetch("SELECT * FROM vendors WHERE phone = ?", [$phone]);
    
    if (!$vendor) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    if (!password_verify($password, $vendor['password_hash'])) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    if ($vendor['status'] !== 'approved') {
        return ['success' => false, 'message' => 'حسابك قيد المراجعة أو تم رفضه.'];
    }
    
    // إنشاء الجلسة
    $_SESSION['vendor_id'] = $vendor['id'];
    $_SESSION['vendor_name'] = $vendor['business_name'];
    $_SESSION['vendor_login_time'] = time();
    
    session_regenerate_id(true);
    
    // تسجيل النشاط
    log_activity('vendor', $vendor['id'], 'login', 'تسجيل دخول لوحة التاجر');
    
    return [
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'vendor' => [
            'id' => $vendor['id'],
            'name' => $vendor['business_name'],
            'type' => $vendor['business_type']
        ]
    ];
}

/**
 * تسجيل خروج التاجر
 */
function logout_vendor() {
    if (isset($_SESSION['vendor_id'])) {
        log_activity('vendor', $_SESSION['vendor_id'], 'logout', 'تسجيل خروج من لوحة التاجر');
    }
    
    session_destroy();
    return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
}

// =====================================================
// دوال تسجيل الدخول للمناديب
// =====================================================

/**
 * تسجيل دخول المندوب
 */
function login_driver($phone, $password) {
    $phone = format_saudi_phone($phone);
    
    $driver = db_fetch("SELECT * FROM drivers WHERE phone = ?", [$phone]);
    
    if (!$driver) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    if (!password_verify($password, $driver['password_hash'])) {
        return ['success' => false, 'message' => 'رقم الجوال أو كلمة المرور غير صحيحة.'];
    }
    
    if ($driver['status'] !== 'approved') {
        return ['success' => false, 'message' => 'حسابك قيد المراجعة أو تم رفضه.'];
    }
    
    // إنشاء الجلسة
    $_SESSION['driver_id'] = $driver['id'];
    $_SESSION['driver_name'] = $driver['name'];
    $_SESSION['driver_login_time'] = time();
    
    session_regenerate_id(true);
    
    // تسجيل النشاط
    log_activity('driver', $driver['id'], 'login', 'تسجيل دخول لوحة المندوب');
    
    return [
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'driver' => [
            'id' => $driver['id'],
            'name' => $driver['name'],
            'zone_id' => $driver['zone_id']
        ]
    ];
}

/**
 * تسجيل خروج المندوب
 */
function logout_driver() {
    if (isset($_SESSION['driver_id'])) {
        // تحديث حالة المندوب إلى غير متصل
        db_update(
            'drivers',
            ['is_online' => 0, 'is_busy' => 0],
            'id = ?',
            [':id' => $_SESSION['driver_id']]
        );
        
        log_activity('driver', $_SESSION['driver_id'], 'logout', 'تسجيل خروج من لوحة المندوب');
    }
    
    session_destroy();
    return ['success' => true, 'message' => 'تم تسجيل الخروج بنجاح'];
}

// =====================================================
// دوال التحقق من الصلاحيات
// =====================================================

/**
 * التحقق من صلاحية الوصول لصفحة الأدمن
 */
function require_admin() {
    if (!is_admin()) {
        redirect(BASE_URL . 'login.php?type=admin');
    }
}

/**
 * التحقق من صلاحية الوصول لصفحة التاجر
 */
function require_vendor() {
    if (!is_vendor()) {
        redirect(BASE_URL . 'login.php?type=vendor');
    }
}

/**
 * التحقق من صلاحية الوصول لصفحة المندوب
 */
function require_driver() {
    if (!is_driver()) {
        redirect(BASE_URL . 'login.php?type=driver');
    }
}

/**
 * التحقق من صلاحية الوصول لصفحة العميل
 */
function require_user() {
    if (!is_logged_in()) {
        // حفظ الصفحة المطلوبة للعودة إليها بعد الدخول
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(BASE_URL . 'login.php');
    }
}

/**
 * التحقق من صلاحية الأدمن حسب الدور
 */
function require_admin_role($allowed_roles = ['super']) {
    require_admin();
    
    $admin = get_current_admin();
    
    if (!in_array($admin['role'], $allowed_roles)) {
        die('غير مصرح لك بالوصول إلى هذه الصفحة.');
    }
}