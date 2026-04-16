<?php
/**
 * صفحة تسجيل الدخول - OTP
 * Saree3 - تطبيق توصيل ومارت
 * @version 2.0 - تصميم فاخر وثابت
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// إذا كان المستخدم مسجل دخول بالفعل
if (is_logged_in()) {
    redirect(BASE_URL . 'index.php');
}

$page_title = 'تسجيل الدخول - ' . APP_NAME;
$step = isset($_GET['step']) ? clean_input($_GET['step']) : 'phone';
$type = isset($_GET['type']) ? clean_input($_GET['type']) : 'user';

$valid_types = ['user', 'admin', 'vendor', 'driver'];
if (!in_array($type, $valid_types)) {
    $type = 'user';
}

$type_config = [
    'user' => [
        'title' => 'تسجيل الدخول للعملاء',
        'icon' => 'fa-user',
        'redirect' => BASE_URL . 'index.php'
    ],
    'admin' => [
        'title' => 'لوحة تحكم الأدمن',
        'icon' => 'fa-user-shield',
        'redirect' => BASE_URL . 'admin/index.php'
    ],
    'vendor' => [
        'title' => 'لوحة تحكم التاجر',
        'icon' => 'fa-store',
        'redirect' => BASE_URL . 'vendor/index.php'
    ],
    'driver' => [
        'title' => 'لوحة تحكم المندوب',
        'icon' => 'fa-motorcycle',
        'redirect' => BASE_URL . 'driver/index.php'
    ]
];

$config = $type_config[$type];

// هيدر بسيط للصفحة
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <title><?= $page_title ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Base Styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #FF6B35 0%, #FF8F65 50%, #FFA07A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            animation: bgMove 60s linear infinite;
        }
        
        @keyframes bgMove {
            from { transform: translate(0, 0); }
            to { transform: translate(-60px, -60px); }
        }
        
        .login-wrapper {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 450px;
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: #FFFFFF;
            border-radius: 32px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #FF6B35, #FF8F65, #FF6B35);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .login-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #FF6B35, #FF8F65);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 36px;
            box-shadow: 0 10px 25px rgba(255, 107, 53, 0.3);
            transform: rotate(0deg);
            transition: transform 0.3s ease;
        }
        
        .login-logo:hover {
            transform: rotate(10deg) scale(1.05);
        }
        
        .login-title {
            font-size: 26px;
            font-weight: 800;
            color: #1F2937;
            margin-bottom: 8px;
        }
        
        .login-subtitle {
            color: #6B7280;
            font-size: 15px;
        }
        
        .login-back {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            background: #F3F4F6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6B7280;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .login-back:hover {
            background: #FF6B35;
            color: white;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
            font-size: 14px;
        }
        
        .phone-input-wrapper {
            display: flex;
            align-items: center;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.2s;
            background: white;
        }
        
        .phone-input-wrapper:focus-within {
            border-color: #FF6B35;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }
        
        .country-code {
            padding: 0 16px;
            background: #F9FAFB;
            color: #374151;
            font-weight: 600;
            font-size: 16px;
            line-height: 52px;
            border-left: 2px solid #E5E7EB;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 16px;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.2s;
            background: white;
        }
        
        .phone-input-wrapper .form-control {
            border: none;
            border-radius: 0;
            padding: 14px 16px;
            font-size: 18px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
        }
        
        .form-hint {
            font-size: 12px;
            color: #6B7280;
            margin-top: 6px;
        }
        
        .btn {
            padding: 14px 24px;
            border-radius: 16px;
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
            width: 100%;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #FF6B35, #FF8F65);
            color: white;
            box-shadow: 0 8px 20px rgba(255, 107, 53, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255, 107, 53, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }
        
        .btn-link {
            background: none;
            border: none;
            color: #FF6B35;
            font-weight: 600;
            cursor: pointer;
            padding: 8px;
            font-family: 'Cairo', sans-serif;
        }
        
        .btn-link:hover {
            text-decoration: underline;
        }
        
        .otp-message {
            text-align: center;
            padding: 20px;
            background: #D1FAE5;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        
        .otp-message i {
            font-size: 28px;
            color: #10B981;
            margin-bottom: 12px;
        }
        
        .otp-message strong {
            display: block;
            font-size: 22px;
            margin-top: 8px;
            color: #1F2937;
        }
        
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 24px 0;
        }
        
        .otp-digit {
            width: 55px;
            height: 65px;
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            border: 2px solid #E5E7EB;
            border-radius: 14px;
            background: white;
            color: #1F2937;
            transition: all 0.2s;
            font-family: 'Cairo', sans-serif;
        }
        
        .otp-digit:focus {
            outline: none;
            border-color: #FF6B35;
            box-shadow: 0 0 0 4px rgba(255, 107, 53, 0.1);
            transform: scale(1.05);
        }
        
        .otp-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .timer {
            font-weight: 700;
            color: #FF6B35;
            font-size: 18px;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #E5E7EB;
        }
        
        .login-type-links {
            display: flex;
            gap: 8px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .type-link {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .type-link.admin {
            background: #FEE2E2;
            color: #DC2626;
        }
        
        .type-link.vendor {
            background: #D1FAE5;
            color: #059669;
        }
        
        .type-link.driver {
            background: #DBEAFE;
            color: #2563EB;
        }
        
        .type-link:hover {
            transform: translateY(-2px);
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .toggle-password {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #9CA3AF;
            cursor: pointer;
            font-size: 18px;
        }
        
        .toggle-password:hover {
            color: #FF6B35;
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 14px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #FEE2E2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        
        .alert-success {
            background: #D1FAE5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 9999;
            max-width: 350px;
        }
        
        .toast {
            background: white;
            border-radius: 14px;
            padding: 16px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            border-right: 4px solid;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-success { border-right-color: #10B981; }
        .toast-error { border-right-color: #EF4444; }
        .toast-info { border-right-color: #3B82F6; }
        
        .toast span {
            flex: 1;
            color: #1F2937;
            font-weight: 500;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #9CA3AF;
            font-size: 20px;
            cursor: pointer;
        }
        
        .security-notice {
            text-align: center;
            margin-top: 20px;
            color: rgba(255,255,255,0.9);
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .otp-inputs {
                gap: 6px;
            }
            
            .otp-digit {
                width: 45px;
                height: 55px;
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <?php if ($type != 'user'): ?>
        <a href="<?= BASE_URL ?>login.php" class="login-back">
            <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
        
        <div class="login-header">
            <div class="login-logo">
                <i class="fas <?= $config['icon'] ?>"></i>
            </div>
            <h1 class="login-title"><?= $config['title'] ?></h1>
            <p class="login-subtitle">
                <?= $type == 'user' ? 'أدخل رقم جوالك للمتابعة' : 'أدخل بيانات الدخول للوحة التحكم' ?>
            </p>
        </div>
        
        <?php if ($type == 'user'): ?>
        <!-- ============ User Login with OTP ============ -->
        <div class="step-content <?= $step == 'phone' ? 'active' : '' ?>" id="stepPhone">
            <form id="phoneForm">
                <?= csrf_field() ?>
                
                <div class="form-group">
                    <label>رقم الجوال</label>
                    <div class="phone-input-wrapper">
                        <span class="country-code">+966</span>
                        <input type="tel" name="phone" id="phoneInput" class="form-control" 
                               placeholder="5xxxxxxxx" maxlength="9" inputmode="numeric" autofocus required>
                    </div>
                    <small class="form-hint">سيتم إرسال رمز تحقق مكون من 6 أرقام</small>
                </div>
                
                <div class="form-group">
                    <label>الاسم (اختياري)</label>
                    <input type="text" name="name" id="nameInput" class="form-control" 
                           placeholder="اسمك الكريم">
                </div>
                
                <button type="submit" class="btn btn-primary" id="sendOtpBtn">
                    <i class="fas fa-paper-plane"></i>
                    إرسال رمز التحقق
                </button>
            </form>
        </div>
        
        <div class="step-content <?= $step == 'otp' ? 'active' : '' ?>" id="stepOtp">
            <form id="otpForm">
                <?= csrf_field() ?>
                <input type="hidden" name="phone" id="hiddenPhone">
                <input type="hidden" name="name" id="hiddenName">
                
                <div class="otp-message">
                    <i class="fas fa-check-circle"></i>
                    <span>تم إرسال رمز التحقق إلى</span>
                    <strong id="displayPhone">05xxxxxxxx</strong>
                </div>
                
                <div class="form-group">
                    <label>أدخل رمز التحقق</label>
                    <div class="otp-inputs" id="otpInputs">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="0">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="1">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="2">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="3">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="4">
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" data-index="5">
                    </div>
                    <input type="hidden" name="otp_code" id="otpCode">
                </div>
                
                <button type="submit" class="btn btn-primary" id="verifyOtpBtn">
                    <i class="fas fa-check-circle"></i>
                    تأكيد وتسجيل الدخول
                </button>
                
                <div class="otp-actions">
                    <button type="button" class="btn-link" id="resendOtpBtn">
                        <i class="fas fa-redo"></i> إعادة الإرسال
                    </button>
                    <span class="timer" id="otpTimer">02:00</span>
                </div>
                
                <button type="button" class="btn-link" id="changePhoneBtn" style="display: block; margin: 16px auto 0;">
                    <i class="fas fa-pen"></i> تغيير رقم الجوال
                </button>
            </form>
        </div>
        
        <div class="login-footer">
            <div class="login-type-links">
                <a href="?type=admin" class="type-link admin">
                    <i class="fas fa-user-shield"></i> أدمن
                </a>
                <a href="?type=vendor" class="type-link vendor">
                    <i class="fas fa-store"></i> تاجر
                </a>
                <a href="?type=driver" class="type-link driver">
                    <i class="fas fa-motorcycle"></i> مندوب
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- ============ Password Login ============ -->
        <form id="passwordLoginForm">
            <?= csrf_field() ?>
            <input type="hidden" name="login_type" value="<?= $type ?>">
            
            <div class="form-group">
                <label>رقم الجوال</label>
                <div class="phone-input-wrapper">
                    <span class="country-code">+966</span>
                    <input type="tel" name="phone" class="form-control" 
                           placeholder="5xxxxxxxx" maxlength="9" inputmode="numeric" autofocus required>
                </div>
            </div>
            
            <div class="form-group">
                <label>كلمة المرور</label>
                <div class="password-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-control" 
                           placeholder="••••••••" required>
                    <button type="button" class="toggle-password" data-target="passwordInput">
                        <i class="far fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt"></i>
                تسجيل الدخول
            </button>
        </form>
        
        <div class="login-footer">
            <a href="<?= BASE_URL ?>login.php" class="btn-link">
                <i class="fas fa-user"></i> العودة لتسجيل دخول العملاء
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="security-notice">
        <i class="fas fa-shield-alt"></i>
        <span>محمي بتشفير SSL - بياناتك آمنة تماماً</span>
    </div>
</div>

<div class="toast-container" id="toastContainer"></div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= get_csrf_token() ?>';

let timerInterval;
let resendCooldown = false;

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    else if (type === 'error') icon = 'times-circle';
    
    toast.innerHTML = `
        <i class="fas fa-${icon}"></i>
        <span>${message}</span>
        <button class="toast-close">&times;</button>
    `;
    
    container.appendChild(toast);
    
    toast.querySelector('.toast-close').addEventListener('click', () => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    });
    
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function formatPhone(phone) {
    if (phone.startsWith('+966')) return '0' + phone.substring(4);
    return phone;
}

function startTimer(seconds) {
    clearInterval(timerInterval);
    let remaining = seconds;
    resendCooldown = true;
    const resendBtn = document.getElementById('resendOtpBtn');
    const timerSpan = document.getElementById('otpTimer');
    
    timerInterval = setInterval(() => {
        remaining--;
        
        if (remaining <= 0) {
            clearInterval(timerInterval);
            timerSpan.textContent = '00:00';
            resendCooldown = false;
            if (resendBtn) {
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo"></i> إعادة الإرسال';
            }
        } else {
            const mins = Math.floor(remaining / 60);
            const secs = remaining % 60;
            timerSpan.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            if (resendBtn) resendBtn.disabled = true;
        }
    }, 1000);
}

// Phone Form
document.getElementById('phoneForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const phone = document.getElementById('phoneInput').value.trim();
    const name = document.getElementById('nameInput').value.trim();
    const btn = document.getElementById('sendOtpBtn');
    
    if (!phone || phone.length < 9) {
        showToast('الرجاء إدخال رقم جوال صحيح', 'error');
        return;
    }
    
    const fullPhone = '+966' + phone;
    const formData = new FormData();
    formData.append('phone', fullPhone);
    formData.append('name', name);
    formData.append('csrf_token', CSRF_TOKEN);
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=send_otp', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('hiddenPhone').value = fullPhone;
            document.getElementById('hiddenName').value = name;
            document.getElementById('displayPhone').textContent = formatPhone(fullPhone);
            
            document.getElementById('stepPhone').classList.remove('active');
            document.getElementById('stepOtp').classList.add('active');
            
            startTimer(120);
            
            <?php if (ENVIRONMENT === 'development'): ?>
            if (data.data && data.data.code) {
                console.log('OTP:', data.data.code);
                const digits = data.data.code.split('');
                document.querySelectorAll('.otp-digit').forEach((inp, i) => {
                    if (digits[i]) inp.value = digits[i];
                });
                updateOtpCode();
            }
            <?php endif; ?>
            
            showToast(data.message || 'تم إرسال رمز التحقق', 'success');
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
            btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال رمز التحقق';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('حدث خطأ في الاتصال', 'error');
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> إرسال رمز التحقق';
        btn.disabled = false;
    });
});

// OTP Inputs
const otpInputs = document.querySelectorAll('.otp-digit');
otpInputs.forEach((input, index) => {
    input.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        if (this.value && index < otpInputs.length - 1) {
            otpInputs[index + 1].focus();
        }
        updateOtpCode();
    });
    
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Backspace' && !this.value && index > 0) {
            otpInputs[index - 1].focus();
        }
    });
});

function updateOtpCode() {
    let code = '';
    otpInputs.forEach(input => code += input.value);
    document.getElementById('otpCode').value = code;
}

// OTP Form
document.getElementById('otpForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const code = document.getElementById('otpCode').value;
    const phone = document.getElementById('hiddenPhone').value;
    const name = document.getElementById('hiddenName').value;
    const btn = document.getElementById('verifyOtpBtn');
    
    if (code.length !== 6) {
        showToast('الرجاء إدخال رمز التحقق كاملاً', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('phone', phone);
    formData.append('code', code);
    formData.append('name', name);
    formData.append('csrf_token', CSRF_TOKEN);
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحقق...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=verify_otp', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تم تسجيل الدخول بنجاح', 'success');
            setTimeout(() => {
                window.location.href = data.data?.redirect || '<?= $config['redirect'] ?>';
            }, 1000);
        } else {
            showToast(data.message || 'رمز التحقق غير صحيح', 'error');
            btn.innerHTML = '<i class="fas fa-check-circle"></i> تأكيد وتسجيل الدخول';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('حدث خطأ في الاتصال', 'error');
        btn.innerHTML = '<i class="fas fa-check-circle"></i> تأكيد وتسجيل الدخول';
        btn.disabled = false;
    });
});

// Resend OTP
document.getElementById('resendOtpBtn')?.addEventListener('click', function() {
    if (resendCooldown) {
        showToast('يرجى الانتظار قبل إعادة الإرسال', 'warning');
        return;
    }
    
    const phone = document.getElementById('hiddenPhone').value;
    const btn = this;
    
    const formData = new FormData();
    formData.append('phone', phone);
    formData.append('csrf_token', CSRF_TOKEN);
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإرسال...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=resend_otp', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تم إعادة إرسال رمز التحقق', 'success');
            startTimer(120);
            
            <?php if (ENVIRONMENT === 'development'): ?>
            if (data.data && data.data.code) {
                const digits = data.data.code.split('');
                otpInputs.forEach((inp, i) => {
                    if (digits[i]) inp.value = digits[i];
                });
                updateOtpCode();
            }
            <?php endif; ?>
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
            btn.innerHTML = '<i class="fas fa-redo"></i> إعادة الإرسال';
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('حدث خطأ في الاتصال', 'error');
        btn.innerHTML = '<i class="fas fa-redo"></i> إعادة الإرسال';
        btn.disabled = false;
    });
});

// Change Phone
document.getElementById('changePhoneBtn')?.addEventListener('click', function() {
    clearInterval(timerInterval);
    document.getElementById('stepOtp').classList.remove('active');
    document.getElementById('stepPhone').classList.add('active');
});

// Password Login
document.getElementById('passwordLoginForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('csrf_token', CSRF_TOKEN);
    
    const btn = this.querySelector('button[type="submit"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الدخول...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=password_login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': CSRF_TOKEN
    },
    body: new URLSearchParams(new FormData(document.getElementById('passwordLoginForm')))
})
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تم تسجيل الدخول بنجاح', 'success');
            setTimeout(() => {
                window.location.href = data.data?.redirect || '<?= $config['redirect'] ?>';
            }, 1000);
        } else {
            showToast(data.message || 'بيانات الدخول غير صحيحة', 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(() => {
        showToast('حدث خطأ في الاتصال', 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
});

// Toggle Password
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const targetId = this.dataset.target;
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    });
});
</script>

</body>
</html>
