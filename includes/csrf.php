<?php
/**
 * حماية CSRF - Cross-Site Request Forgery
 * Saree3 - تطبيق توصيل ومارت
 * @version 2.0 - إصلاح مشاكل التوكن
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// توليد توكن CSRF جديد
// =====================================================
function generate_csrf_token() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token']      = $token;
    $_SESSION['csrf_token_time'] = time();
    return $token;
}

// =====================================================
// الحصول على التوكن الحالي (أو إنشاء واحد جديد)
// =====================================================
function get_csrf_token() {
    // لو مفيش توكن أو انتهت صلاحيته، اعمل جديد
    if (
        !isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']) ||
        (time() - $_SESSION['csrf_token_time']) > CSRF_EXPIRY
    ) {
        return generate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}

// =====================================================
// التحقق من صحة توكن CSRF
// الإصلاح: إزالة unset بعد الاستخدام عشان يشتغل مع طلبات متعددة
// =====================================================
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if (empty($token)) {
        return false;
    }

    if (!isset($_SESSION['csrf_token'], $_SESSION['csrf_token_time'])) {
        return false;
    }

    // تحقق من الصلاحية الزمنية
    if ((time() - $_SESSION['csrf_token_time']) > CSRF_EXPIRY) {
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
        return false;
    }

    // مقارنة آمنة — بدون حذف التوكن عشان يشتغل مع طلبات متتالية
    return hash_equals($_SESSION['csrf_token'], $token);
}

// =====================================================
// حقل مخفي للفورم
// =====================================================
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

// =====================================================
// ميتا تاج للـ AJAX
// =====================================================
function csrf_meta() {
    $token = get_csrf_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES) . '">';
}

// =====================================================
// Middleware: التحقق من CSRF في الطلبات
// =====================================================
function require_csrf() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
        return; // GET و HEAD لا تحتاج CSRF
    }

    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    if (!verify_csrf_token($token)) {
        $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($is_ajax) {
            // أعد توكن جديد مع الخطأ عشان الـ JS يحدث نفسه
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode([
                'success'    => false,
                'message'    => 'رمز الحماية غير صالح أو منتهي الصلاحية، يرجى تحديث الصفحة',
                'csrf_token' => generate_csrf_token(), // توكن جديد للـ retry
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        die('خطأ: رمز الحماية غير صالح أو منتهي الصلاحية. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
    }
}

// =====================================================
// تجديد التوكن (endpoint: ?action=refresh_csrf)
// =====================================================
function refresh_csrf_token() {
    return generate_csrf_token();
}

// =====================================================
// JavaScript: حقن التوكن تلقائياً في كل طلب
// الإصلاح: قراءة التوكن من الـ meta tag بدل تخزينه
//          في closure عشان يتحدث بعد كل refresh
// =====================================================
function csrf_js() {
    // نولد التوكن مرة واحدة بس
    $token = get_csrf_token();
    return "
<script>
(function () {
    // =====================================================
    // مساعد: اقرأ التوكن الحالي من الـ meta أو الـ cookie
    // =====================================================
    function getCsrfToken() {
        var meta = document.querySelector('meta[name=\"csrf-token\"]');
        return meta ? meta.getAttribute('content') : null;
    }

    function setCsrfToken(newToken) {
        var meta = document.querySelector('meta[name=\"csrf-token\"]');
        if (meta) meta.setAttribute('content', newToken);
    }

    // ضع التوكن الأولي في الـ meta لو مش موجود
    if (!document.querySelector('meta[name=\"csrf-token\"]')) {
        var m = document.createElement('meta');
        m.name    = 'csrf-token';
        m.content = " . json_encode($token) . ";
        document.head.appendChild(m);
    }

    // =====================================================
    // Intercept: window.fetch
    // =====================================================
    var _fetch = window.fetch;
    window.fetch = function (input, init) {
        init = init || {};

        var method = (init.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
            init.headers = init.headers || {};

            // دعم Headers object و plain object
            if (init.headers instanceof Headers) {
                if (!init.headers.has('X-CSRF-Token') && !init.headers.has('X-CSRF-TOKEN')) {
                    init.headers.set('X-CSRF-Token', getCsrfToken());
                }
            } else {
                if (!init.headers['X-CSRF-Token'] && !init.headers['X-CSRF-TOKEN']) {
                    init.headers['X-CSRF-Token'] = getCsrfToken();
                }
            }
        }

        return _fetch.call(this, input, init).then(function (response) {
            // لو السيرفر رجع توكن جديد، حدثه تلقائياً
            var newToken = response.headers.get('X-New-CSRF-Token');
            if (newToken) setCsrfToken(newToken);
            return response;
        });
    };

    // =====================================================
    // Intercept: XMLHttpRequest
    // =====================================================
    var _open = XMLHttpRequest.prototype.open;
    XMLHttpRequest.prototype.open = function (method) {
        this._method = method;
        return _open.apply(this, arguments);
    };

    var _send = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.send = function () {
        var method = (this._method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1) {
            try { this.setRequestHeader('X-CSRF-Token', getCsrfToken()); } catch (e) {}
        }
        return _send.apply(this, arguments);
    };

    // =====================================================
    // تجديد التوكن كل 25 دقيقة (قبل انتهاء الساعة)
    // =====================================================
    setInterval(function () {
        _fetch('/api/handler.php?action=refresh_csrf', {
            method : 'POST',
            headers: { 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.data && d.data.token) setCsrfToken(d.data.token); })
        .catch(function () {});
    }, 25 * 60 * 1000);

})();
</script>
";
}

// =====================================================
// حقل CSRF مع زر إرسال
// =====================================================
function csrf_form_footer($submit_text = 'إرسال', $cancel_url = null) {
    $html  = csrf_field();
    $html .= '<div class="form-group">';
    $html .= '<button type="submit" class="btn btn-primary">' . htmlspecialchars($submit_text, ENT_QUOTES) . '</button>';
    if ($cancel_url) {
        $html .= ' <a href="' . htmlspecialchars($cancel_url, ENT_QUOTES) . '" class="btn btn-secondary">إلغاء</a>';
    }
    $html .= '</div>';
    return $html;
}

// =====================================================
// Cookie آمن
// =====================================================
function set_secure_cookie($name, $value, $expire = 0) {
    return setcookie($name, $value, [
        'expires'  => $expire,
        'path'     => '/',
        'domain'   => '',
        'secure'   => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
}