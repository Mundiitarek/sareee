<?php
/**
 * صفحة المحتوى الثابت
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

$slug = isset($_GET['slug']) ? clean_input($_GET['slug']) : 'about';

// الصفحات المتاحة
$allowed_pages = ['about', 'help', 'terms', 'privacy', 'contact', 'careers', 'faq'];

if (!in_array($slug, $allowed_pages)) {
    $slug = 'about';
}

// إعدادات كل صفحة
$pages = [
    'about' => [
        'title' => 'عن سريع',
        'icon' => 'fa-info-circle',
        'setting_key' => 'about_us_ar'
    ],
    'help' => [
        'title' => 'مركز المساعدة',
        'icon' => 'fa-headset',
        'setting_key' => 'help_center_ar'
    ],
    'terms' => [
        'title' => 'الشروط والأحكام',
        'icon' => 'fa-file-contract',
        'setting_key' => 'terms_ar'
    ],
    'privacy' => [
        'title' => 'سياسة الخصوصية',
        'icon' => 'fa-shield-alt',
        'setting_key' => 'privacy_ar'
    ],
    'contact' => [
        'title' => 'اتصل بنا',
        'icon' => 'fa-phone',
        'setting_key' => 'contact_us_ar'
    ],
    'careers' => [
        'title' => 'وظائف',
        'icon' => 'fa-briefcase',
        'setting_key' => 'careers_ar'
    ],
    'faq' => [
        'title' => 'الأسئلة الشائعة',
        'icon' => 'fa-question-circle',
        'setting_key' => 'faq_ar'
    ]
];

$page_config = $pages[$slug];
$page_title = $page_config['title'] . ' - ' . APP_NAME;

// جلب محتوى الصفحة من الإعدادات
$content = get_setting($page_config['setting_key'], '');

// لو المحتوى فاضي، نعرض محتوى افتراضي
if (empty($content)) {
    $content = getDefaultContent($slug);
}

function getDefaultContent($slug) {
    $defaults = [
        'about' => '
            <h2>من نحن</h2>
            <p>سريع هو تطبيق توصيل طعام ومقاضي رائد في المملكة العربية السعودية. نهدف إلى توفير تجربة طلب سلسة وسريعة لعملائنا الكرام.</p>
            <p>تأسس سريع في عام 2024 ليصبح الخيار الأول للتوصيل السريع في المملكة. نفتخر بشراكاتنا مع آلاف المطاعم والمتاجر لتلبية جميع احتياجاتكم.</p>
            
            <h3>رؤيتنا</h3>
            <p>أن نكون المنصة الأولى للتوصيل السريع في الشرق الأوسط، نقدم خدمة استثنائية تتجاوز توقعات عملائنا.</p>
            
            <h3>مهمتنا</h3>
            <p>توصيل كل ما تحتاجه إلى باب منزلك بسرعة وأمان، مع تجربة مستخدم سلسة وممتعة.</p>
            
            <h3>قيمنا</h3>
            <ul>
                <li><strong>السرعة:</strong> نلتزم بتوصيل طلباتك في أسرع وقت ممكن.</li>
                <li><strong>الجودة:</strong> نضمن جودة الخدمة والمنتجات التي نقدمها.</li>
                <li><strong>الثقة:</strong> نبني علاقة ثقة مع عملائنا وشركائنا.</li>
                <li><strong>الابتكار:</strong> نستمر في تطوير خدماتنا لتلبية احتياجاتكم المتغيرة.</li>
            </ul>
        ',
        'help' => '
            <h2>مركز المساعدة</h2>
            <p>نحن هنا لمساعدتك! إذا كان لديك أي استفسار أو مشكلة، يمكنك التواصل معنا عبر إحدى القنوات التالية:</p>
            
            <h3>التواصل المباشر</h3>
            <ul>
                <li><i class="fas fa-phone"></i> الهاتف: 920000000</li>
                <li><i class="fab fa-whatsapp"></i> واتساب: 0500000000</li>
                <li><i class="fas fa-envelope"></i> البريد الإلكتروني: support@saree3.com</li>
            </ul>
            
            <h3>ساعات العمل</h3>
            <p>نعمل على مدار 24 ساعة طوال أيام الأسبوع لخدمتكم.</p>
            
            <h3>الأسئلة الشائعة</h3>
            <h4>كيف يمكنني تتبع طلبي؟</h4>
            <p>يمكنك تتبع طلبك من خلال صفحة "طلباتي" في حسابك، أو من خلال الرابط المرسل إليك عبر الرسائل النصية.</p>
            
            <h4>كيف يمكنني إلغاء طلب؟</h4>
            <p>يمكنك إلغاء الطلب قبل قبوله من قبل المطعم. بعد القبول، يرجى التواصل مع خدمة العملاء.</p>
            
            <h4>ما هي طرق الدفع المتاحة؟</h4>
            <p>نقبل الدفع نقداً عند الاستلام، وبطاقات الائتمان (مدى، فيزا، ماستركارد)، والمحافظ الإلكترونية.</p>
        ',
        'terms' => '
            <h2>الشروط والأحكام</h2>
            <p>مرحباً بكم في تطبيق سريع. باستخدامك للتطبيق، فإنك توافق على الشروط والأحكام التالية:</p>
            
            <h3>1. قبول الشروط</h3>
            <p>باستخدامك لخدمات سريع، فإنك تقر بموافقتك على هذه الشروط والأحكام بشكل كامل.</p>
            
            <h3>2. التسجيل والحساب</h3>
            <p>يجب عليك تقديم معلومات دقيقة وكاملة عند التسجيل. أنت مسؤول عن الحفاظ على سرية حسابك وكلمة المرور.</p>
            
            <h3>3. الطلبات والإلغاء</h3>
            <p>يحق لك إلغاء الطلب قبل قبوله من قبل المطعم أو المتجر. بعد القبول، قد تطبق رسوم إلغاء.</p>
            
            <h3>4. الأسعار والدفع</h3>
            <p>جميع الأسعار بالريال السعودي وتشمل ضريبة القيمة المضافة. نقبل طرق الدفع المحددة في التطبيق.</p>
            
            <h3>5. التوصيل</h3>
            <p>نحن نسعى لتوصيل طلبك في الوقت المحدد، ولكن قد تحدث تأخيرات خارجة عن إرادتنا.</p>
            
            <h3>6. سياسة الاسترجاع</h3>
            <p>في حالة وجود مشكلة في طلبك، يرجى التواصل مع خدمة العملاء خلال 24 ساعة.</p>
        ',
        'privacy' => '
            <h2>سياسة الخصوصية</h2>
            <p>في سريع، نلتزم بحماية خصوصيتك وأمان معلوماتك الشخصية.</p>
            
            <h3>المعلومات التي نجمعها</h3>
            <ul>
                <li>معلومات الحساب: الاسم، رقم الجوال، العنوان.</li>
                <li>معلومات الطلبات: سجل الطلبات، المطاعم المفضلة.</li>
                <li>معلومات الجهاز: نوع الجهاز، نظام التشغيل، الموقع الجغرافي.</li>
            </ul>
            
            <h3>كيف نستخدم معلوماتك</h3>
            <ul>
                <li>تقديم خدماتنا وتحسينها.</li>
                <li>التواصل معك بخصوص طلباتك.</li>
                <li>إرسال العروض والتحديثات (بموافقتك).</li>
            </ul>
            
            <h3>مشاركة المعلومات</h3>
            <p>نحن لا نبيع أو نؤجر معلوماتك الشخصية لأطراف ثالثة. نشارك المعلومات فقط مع:</p>
            <ul>
                <li>المطاعم والمتاجر لتوصيل طلبك.</li>
                <li>المناديب لتوصيل طلبك.</li>
                <li>الجهات القانونية عند الحاجة.</li>
            </ul>
            
            <h3>أمان المعلومات</h3>
            <p>نستخدم تقنيات تشفير متقدمة لحماية معلوماتك. جميع البيانات تخزن على خوادم آمنة.</p>
        '
    ];
    
    return $defaults[$slug] ?? '<p>المحتوى قيد التجهيز...</p>';
}

include INCLUDES_PATH . '/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-bg"></div>
    <div class="page-container">
        <div class="page-header-content">
            <div class="page-icon">
                <i class="fas <?= $page_config['icon'] ?>"></i>
            </div>
            <h1 class="page-title"><?= $page_config['title'] ?></h1>
        </div>
    </div>
</div>

<!-- Page Content -->
<div class="page-container">
    <div class="page-content">
        <div class="content-card">
            <?= $content ?>
        </div>
        
        <!-- Contact Box (لصفحة المساعدة والتواصل) -->
        <?php if (in_array($slug, ['help', 'contact'])): ?>
        <div class="contact-box">
            <h3><i class="fas fa-headset"></i> هل تحتاج مساعدة إضافية؟</h3>
            <p>فريق الدعم لدينا جاهز لمساعدتك على مدار الساعة</p>
            <div class="contact-options">
                <a href="tel:920000000" class="contact-option">
                    <i class="fas fa-phone"></i>
                    <span>اتصل بنا</span>
                </a>
                <a href="https://wa.me/966500000000" class="contact-option">
                    <i class="fab fa-whatsapp"></i>
                    <span>واتساب</span>
                </a>
                <a href="mailto:support@saree3.com" class="contact-option">
                    <i class="fas fa-envelope"></i>
                    <span>بريد إلكتروني</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- FAQ Box -->
        <?php if ($slug == 'faq'): ?>
        <div class="faq-box">
            <h3>لم تجد إجابة لسؤالك؟</h3>
            <p>تواصل معنا وسنكون سعداء بمساعدتك</p>
            <a href="<?= BASE_URL ?>page.php?slug=contact" class="btn btn-primary">
                <i class="fas fa-phone"></i> اتصل بنا
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Download App Box -->
        <div class="download-app-box">
            <div class="download-content">
                <h3><i class="fas fa-mobile-alt"></i> حمل تطبيق سريع</h3>
                <p>استمتع بتجربة طلب أسرع وأسهل مع تطبيقنا</p>
                <div class="app-buttons">
                    <a href="#" class="app-btn">
                        <i class="fab fa-apple"></i>
                        <span>App Store</span>
                    </a>
                    <a href="#" class="app-btn">
                        <i class="fab fa-google-play"></i>
                        <span>Google Play</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Page Header */
    .page-header {
        background: var(--gradient-primary);
        padding: 40px 0;
        margin-bottom: 32px;
        position: relative;
        overflow: hidden;
    }
    
    .page-header-bg {
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M30 5v50M5 30h50' stroke='%23ffffff' stroke-width='1'/%3E%3C/g%3E%3C/svg%3E");
    }
    
    .page-container {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 16px;
    }
    
    .page-header-content {
        text-align: center;
        color: white;
        position: relative;
        z-index: 2;
    }
    
    .page-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 20px;
        background: rgba(255,255,255,0.2);
        border-radius: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36px;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.3);
    }
    
    .page-title {
        font-size: 32px;
        font-weight: 800;
        margin: 0;
        color: white;
    }
    
    /* Page Content */
    .page-content {
        padding-bottom: 48px;
    }
    
    .content-card {
        background: white;
        border-radius: 24px;
        padding: 32px;
        box-shadow: var(--shadow-card);
        margin-bottom: 24px;
    }
    
    .content-card h2 {
        color: var(--gray-900);
        margin-bottom: 20px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--primary-soft);
    }
    
    .content-card h3 {
        color: var(--gray-800);
        margin: 24px 0 16px;
    }
    
    .content-card h4 {
        color: var(--gray-700);
        margin: 20px 0 12px;
    }
    
    .content-card p {
        color: var(--gray-600);
        line-height: 1.8;
        margin-bottom: 16px;
    }
    
    .content-card ul, .content-card ol {
        margin: 16px 0;
        padding-right: 24px;
    }
    
    .content-card li {
        color: var(--gray-600);
        margin-bottom: 8px;
        line-height: 1.8;
    }
    
    .content-card i {
        color: var(--primary);
        margin-left: 8px;
    }
    
    /* Contact Box */
    .contact-box {
        background: linear-gradient(135deg, var(--primary-soft) 0%, #FFF8F5 100%);
        border-radius: 24px;
        padding: 32px;
        text-align: center;
        margin-bottom: 24px;
        border: 1px solid var(--primary-light);
    }
    
    .contact-box h3 {
        color: var(--gray-900);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .contact-box p {
        color: var(--gray-600);
        margin-bottom: 24px;
    }
    
    .contact-options {
        display: flex;
        gap: 16px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .contact-option {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: white;
        border-radius: 40px;
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        box-shadow: var(--shadow-sm);
        transition: all 0.2s;
    }
    
    .contact-option:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
        box-shadow: var(--shadow-primary);
    }
    
    /* FAQ Box */
    .faq-box {
        background: var(--gray-50);
        border-radius: 24px;
        padding: 32px;
        text-align: center;
        margin-bottom: 24px;
    }
    
    .faq-box h3 {
        margin-bottom: 8px;
    }
    
    .faq-box p {
        color: var(--gray-600);
        margin-bottom: 20px;
    }
    
    /* Download App Box */
    .download-app-box {
        background: var(--gradient-dark);
        border-radius: 24px;
        padding: 32px;
        color: white;
        margin-top: 32px;
    }
    
    .download-content {
        text-align: center;
    }
    
    .download-content h3 {
        color: white;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .download-content p {
        color: rgba(255,255,255,0.8);
        margin-bottom: 24px;
    }
    
    .app-buttons {
        display: flex;
        gap: 16px;
        justify-content: center;
        flex-wrap: wrap;
    }
    
    .app-btn {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 14px 28px;
        background: rgba(255,255,255,0.1);
        border-radius: 40px;
        color: white;
        text-decoration: none;
        font-weight: 600;
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255,255,255,0.2);
        transition: all 0.2s;
    }
    
    .app-btn i {
        font-size: 24px;
    }
    
    .app-btn:hover {
        background: white;
        color: var(--primary);
        transform: translateY(-2px);
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .page-header {
            padding: 30px 0;
        }
        
        .page-icon {
            width: 60px;
            height: 60px;
            font-size: 28px;
        }
        
        .page-title {
            font-size: 24px;
        }
        
        .content-card {
            padding: 20px;
        }
        
        .contact-box, .faq-box, .download-app-box {
            padding: 24px 16px;
        }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>