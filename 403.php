<?php header('HTTP/1.0 403 Forbidden'); ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - ممنوع الوصول</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #EF4444, #DC2626); min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 20px; }
        .error-card { background: white; border-radius: 32px; padding: 48px 32px; text-align: center; max-width: 400px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); }
        .error-code { font-size: 80px; font-weight: 800; color: #DC2626; line-height: 1; }
        .error-title { font-size: 24px; margin: 16px 0; color: #1F2937; }
        .btn { display: inline-block; padding: 14px 32px; background: #DC2626; color: white; text-decoration: none; border-radius: 16px; font-weight: 700; }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-code">403</div>
        <h1 class="error-title">ممنوع الوصول</h1>
        <p>عذراً، ليس لديك صلاحية للوصول إلى هذه الصفحة</p>
        <a href="/" class="btn">العودة للرئيسية</a>
    </div>
</body>
</html>