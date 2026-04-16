<?php
/**
 * صفحة دولاب الحظ
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = BASE_URL . 'wheel.php';
    redirect(BASE_URL . 'login.php');
}

$page_title = 'دولاب الحظ - ' . APP_NAME;
$user_id = $_SESSION['user_id'];

// جلب بيانات المستخدم
$user = get_current_user_data();

// جلب إعدادات الدولاب
$wheel_settings = db_fetch_all(
    "SELECT * FROM settings WHERE setting_group = 'wheel'",
    []
);
$settings = [];
foreach ($wheel_settings as $s) {
    $settings[$s['setting_key']] = $s['setting_value'];
}

// الحد الأدنى للطلبات للدوران
$min_orders = $settings['wheel_min_orders'] ?? WHEEL_MIN_ORDERS_FOR_SPIN;
$max_spins_per_day = $settings['wheel_max_spins_per_day'] ?? WHEEL_MAX_SPINS_PER_DAY;

// حساب عدد الطلبات المكتملة للمستخدم
$completed_orders = db_fetch(
    "SELECT COUNT(*) as count FROM orders 
     WHERE user_id = ? AND status = 'delivered'",
    [$user_id]
)['count'] ?? 0;

// حساب عدد مرات الدوران اليوم
$today_spins = db_fetch(
    "SELECT COUNT(*) as count FROM wheel_spins 
     WHERE user_id = ? AND DATE(created_at) = CURDATE()",
    [$user_id]
)['count'] ?? 0;

$can_spin = ($completed_orders >= $min_orders) && ($today_spins < $max_spins_per_day);
$remaining_spins = $max_spins_per_day - $today_spins;

// جلب الجوائز النشطة
$rewards = db_fetch_all(
    "SELECT * FROM lucky_wheel_rewards 
     WHERE status = 1 
     AND (max_usage IS NULL OR used_count < max_usage)
     ORDER BY probability DESC",
    []
);

// حساب مجموع الاحتمالات
$total_probability = array_sum(array_column($rewards, 'probability'));

// جلب سجل جوائز المستخدم
$user_rewards = db_fetch_all(
    "SELECT ws.*, lwr.name_ar, lwr.reward_type, lwr.reward_value, lwr.icon, lwr.color
     FROM wheel_spins ws
     JOIN lucky_wheel_rewards lwr ON ws.reward_id = lwr.id
     WHERE ws.user_id = ?
     ORDER BY ws.created_at DESC
     LIMIT 10",
    [$user_id]
);

// جلب آخر الفائزين
$recent_winners = db_fetch_all(
    "SELECT ws.*, u.name as user_name, lwr.name_ar, lwr.icon, lwr.color
     FROM wheel_spins ws
     JOIN users u ON ws.user_id = u.id
     JOIN lucky_wheel_rewards lwr ON ws.reward_id = lwr.id
     WHERE ws.is_claimed = 1
     ORDER BY ws.created_at DESC
     LIMIT 5",
    []
);

// تحويل الجوائز لصيغة JavaScript
$wheel_segments = [];
foreach ($rewards as $reward) {
    $wheel_segments[] = [
        'id' => $reward['id'],
        'name' => $reward['name_ar'],
        'probability' => (float)$reward['probability'],
        'color' => $reward['color'],
        'icon' => $reward['icon']
    ];
}

include INCLUDES_PATH . '/header.php';
?>

<!-- Wheel Header -->
<div class="wheel-header">
    <div class="wheel-container">
        <div class="wheel-header-content">
            <h1 class="wheel-title">
                <i class="fas fa-ticket-alt"></i>
                دولاب الحظ
            </h1>
            <p class="wheel-subtitle">جرب حظك واربح جوائز رائعة!</p>
        </div>
    </div>
</div>

<!-- Wheel Stats -->
<div class="wheel-stats-wrapper">
    <div class="wheel-container">
        <div class="wheel-stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--primary-soft); color: var(--primary);">
                    <i class="fas fa-shopping-bag"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= $completed_orders ?></span>
                    <span class="stat-label">طلب مكتمل</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--success-light); color: var(--success);">
                    <i class="fas fa-sync"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= $today_spins ?> / <?= $max_spins_per_day ?></span>
                    <span class="stat-label">دوران اليوم</span>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: var(--warning-light); color: var(--warning);">
                    <i class="fas fa-gift"></i>
                </div>
                <div class="stat-content">
                    <span class="stat-value"><?= count($user_rewards) ?></span>
                    <span class="stat-label">جائزة ربحتها</span>
                </div>
            </div>
        </div>
        
        <?php if (!$can_spin): ?>
        <div class="wheel-limit-message">
            <i class="fas fa-info-circle"></i>
            <?php if ($completed_orders < $min_orders): ?>
            <span>يجب إكمال <?= $min_orders ?> طلبات على الأقل للدوران (لديك <?= $completed_orders ?>)</span>
            <?php else: ?>
            <span>لقد استنفذت محاولاتك اليومية (<?= $max_spins_per_day ?> محاولات)</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Wheel Section -->
<div class="wheel-section">
    <div class="wheel-container">
        <div class="wheel-wrapper">
            <!-- Canvas للدولاب -->
            <div class="wheel-canvas-container">
                <canvas id="wheelCanvas" width="500" height="500"></canvas>
                
                <!-- المؤشر -->
                <div class="wheel-pointer">
                    <i class="fas fa-caret-down"></i>
                </div>
                
                <!-- زر الدوران -->
                <button class="spin-btn" id="spinWheelBtn" <?= !$can_spin ? 'disabled' : '' ?>>
                    <?php if ($can_spin): ?>
                    <i class="fas fa-play"></i>
                    <span>دور</span>
                    <?php else: ?>
                    <i class="fas fa-lock"></i>
                    <span>مغلق</span>
                    <?php endif; ?>
                </button>
            </div>
            
            <!-- معلومات إضافية -->
            <div class="wheel-info">
                <div class="remaining-spins">
                    <i class="fas fa-hourglass-half"></i>
                    <span>متبقي <?= $remaining_spins ?> محاولات اليوم</span>
                </div>
                
                <?php if ($completed_orders < $min_orders): ?>
                <div class="progress-to-spin">
                    <div class="progress-text">
                        <span>تقدمك للدوران</span>
                        <span><?= $completed_orders ?> / <?= $min_orders ?></span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= ($completed_orders / $min_orders) * 100 ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Rewards & History Section -->
<div class="wheel-rewards-section">
    <div class="wheel-container">
        <!-- Tabs -->
        <div class="wheel-tabs">
            <button class="wheel-tab active" data-tab="rewards">
                <i class="fas fa-gift"></i>
                <span>الجوائز المتاحة</span>
            </button>
            <button class="wheel-tab" data-tab="history">
                <i class="fas fa-history"></i>
                <span>سجل جوائزي</span>
            </button>
            <button class="wheel-tab" data-tab="winners">
                <i class="fas fa-trophy"></i>
                <span>آخر الفائزين</span>
            </button>
        </div>
        
        <!-- Tab Content: Rewards -->
        <div class="wheel-tab-content active" id="rewardsTab">
            <div class="rewards-grid">
                <?php foreach ($rewards as $reward): ?>
                <div class="reward-card" style="border-color: <?= $reward['color'] ?>">
                    <div class="reward-icon" style="background: <?= $reward['color'] ?>20; color: <?= $reward['color'] ?>">
                        <?php if ($reward['icon'] && strpos($reward['icon'], 'fa-') === 0): ?>
                        <i class="fas <?= $reward['icon'] ?>"></i>
                        <?php else: ?>
                        <span><?= $reward['icon'] ?: '🎁' ?></span>
                        <?php endif; ?>
                    </div>
                    <h4 class="reward-name"><?= escape($reward['name_ar']) ?></h4>
                    <p class="reward-value">
                        <?php
                        switch ($reward['reward_type']) {
                            case 'points':
                                echo $reward['reward_value'] . ' نقطة';
                                break;
                            case 'discount':
                                echo 'خصم ' . $reward['reward_value'] . (is_numeric($reward['reward_value']) ? '%' : '');
                                break;
                            case 'free_delivery':
                                echo 'توصيل مجاني';
                                break;
                            case 'cash':
                                echo format_price($reward['reward_value']);
                                break;
                            default:
                                echo $reward['reward_value'];
                        }
                        ?>
                    </p>
                    <div class="reward-probability">
                        <i class="fas fa-chart-pie"></i>
                        <span><?= round(($reward['probability'] / $total_probability) * 100, 1) ?>%</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Tab Content: History -->
        <div class="wheel-tab-content" id="historyTab">
            <?php if (empty($user_rewards)): ?>
            <div class="empty-state">
                <i class="fas fa-ticket-alt"></i>
                <h3>لا توجد جوائز حتى الآن</h3>
                <p>قم بالدوران وجرب حظك للفوز بجوائز رائعة!</p>
            </div>
            <?php else: ?>
            <div class="history-list">
                <?php foreach ($user_rewards as $reward): ?>
                <div class="history-item">
                    <div class="history-icon" style="background: <?= $reward['color'] ?>20; color: <?= $reward['color'] ?>">
                        <?php if ($reward['icon'] && strpos($reward['icon'], 'fa-') === 0): ?>
                        <i class="fas <?= $reward['icon'] ?>"></i>
                        <?php else: ?>
                        <span><?= $reward['icon'] ?: '🎁' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="history-details">
                        <span class="history-title"><?= escape($reward['name_ar']) ?></span>
                        <span class="history-date"><?= time_ago($reward['created_at']) ?></span>
                    </div>
                    <div class="history-status">
                        <?php if ($reward['is_claimed']): ?>
                        <span class="badge-success"><i class="fas fa-check-circle"></i> تم الاستلام</span>
                        <?php else: ?>
                        <button class="btn btn-sm btn-primary claim-reward-btn" data-spin-id="<?= $reward['id'] ?>">
                            <i class="fas fa-gift"></i> استلم
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tab Content: Recent Winners -->
        <div class="wheel-tab-content" id="winnersTab">
            <?php if (empty($recent_winners)): ?>
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <h3>لا يوجد فائزون بعد</h3>
                <p>كن أول الفائزين!</p>
            </div>
            <?php else: ?>
            <div class="winners-list">
                <?php foreach ($recent_winners as $winner): ?>
                <div class="winner-item">
                    <div class="winner-avatar">
                        <?= mb_substr($winner['user_name'], 0, 1) ?>
                    </div>
                    <div class="winner-info">
                        <span class="winner-name"><?= escape($winner['user_name']) ?></span>
                        <span class="winner-prize" style="color: <?= $winner['color'] ?>">
                            <i class="fas fa-gift"></i>
                            <?= escape($winner['name_ar']) ?>
                        </span>
                    </div>
                    <div class="winner-time">
                        <?= time_ago($winner['created_at']) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Win Modal -->
<div class="modal" id="winModal">
    <div class="modal-overlay"></div>
    <div class="modal-content modal-sm">
        <div class="modal-body win-modal-body">
            <div class="win-animation">
                <div class="confetti-container" id="confettiContainer"></div>
                <div class="win-icon" id="winIcon">
                    <i class="fas fa-gift"></i>
                </div>
                <h2 class="win-title">🎉 مبروك! 🎉</h2>
                <p class="win-message">لقد ربحت</p>
                <div class="win-prize" id="winPrize">جائزة رائعة</div>
                <p class="win-note">تمت إضافة الجائزة إلى حسابك</p>
            </div>
            <button class="btn btn-primary btn-block modal-close">رائع!</button>
        </div>
    </div>
</div>

<script>
// بيانات الدولاب
const wheelSegments = <?= json_encode($wheel_segments, JSON_UNESCAPED_UNICODE) ?>;
const totalProbability = <?= $total_probability ?>;
const canSpin = <?= $can_spin ? 'true' : 'false' ?>;

let canvas = document.getElementById('wheelCanvas');
let ctx = canvas.getContext('2d');
let spinning = false;
let currentRotation = 0;

// رسم الدولاب
function drawWheel(rotation = 0) {
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 10;
    
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    
    if (wheelSegments.length === 0) return;
    
    const anglePerSegment = (2 * Math.PI) / wheelSegments.length;
    
    wheelSegments.forEach((segment, index) => {
        const startAngle = index * anglePerSegment + rotation;
        const endAngle = startAngle + anglePerSegment;
        
        // رسم القطاع
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startAngle, endAngle);
        ctx.closePath();
        
        ctx.fillStyle = segment.color;
        ctx.fill();
        
        // رسم الحدود
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 2;
        ctx.stroke();
        
        // رسم النص
        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.rotate(startAngle + anglePerSegment / 2);
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        
        // الأيقونة
        ctx.font = '24px "Font Awesome 6 Free"';
        ctx.fillStyle = '#FFFFFF';
        ctx.shadowColor = 'rgba(0,0,0,0.3)';
        ctx.shadowBlur = 4;
        
        let iconChar = '\uf06b'; // gift icon default
        if (segment.icon && segment.icon.startsWith('fa-')) {
            const iconMap = {
                'fa-star': '\uf005',
                'fa-gift': '\uf06b',
                'fa-tag': '\uf02b',
                'fa-truck': '\uf0d1',
                'fa-coins': '\uf51e',
                'fa-percent': '\uf295',
                'fa-crown': '\uf521',
                'fa-gem': '\uf3a5'
            };
            iconChar = iconMap[segment.icon] || '\uf06b';
        }
        
        ctx.fillText(iconChar, 0, -radius * 0.5);
        
        // النص
        ctx.font = 'bold 12px Cairo, sans-serif';
        ctx.fillStyle = '#FFFFFF';
        
        const name = segment.name.length > 10 ? segment.name.substring(0, 8) + '..' : segment.name;
        ctx.fillText(name, 0, -radius * 0.3);
        
        ctx.restore();
    });
    
    // رسم الدائرة الداخلية
    ctx.beginPath();
    ctx.arc(centerX, centerY, radius * 0.15, 0, 2 * Math.PI);
    ctx.fillStyle = '#FFFFFF';
    ctx.shadowColor = 'rgba(0,0,0,0.2)';
    ctx.shadowBlur = 10;
    ctx.fill();
    ctx.shadowBlur = 0;
    ctx.strokeStyle = '#FF6B35';
    ctx.lineWidth = 3;
    ctx.stroke();
}

// تحديد الجائزة الفائزة
function getWinningSegment(rotation) {
    const anglePerSegment = (2 * Math.PI) / wheelSegments.length;
    
    // زاوية المؤشر (في الأعلى)
    const pointerAngle = -Math.PI / 2;
    
    // حساب الزاوية النسبية
    let rawAngle = (pointerAngle - rotation) % (2 * Math.PI);
    if (rawAngle < 0) rawAngle += 2 * Math.PI;
    
    // تحديد القطاع
    let segmentIndex = Math.floor(rawAngle / anglePerSegment);
    segmentIndex = wheelSegments.length - 1 - segmentIndex;
    if (segmentIndex < 0) segmentIndex += wheelSegments.length;
    
    return wheelSegments[segmentIndex];
}

// الدوران
function spinWheel() {
    if (spinning || !canSpin) return;
    
    spinning = true;
    const spinBtn = document.getElementById('spinWheelBtn');
    spinBtn.disabled = true;
    spinBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>دوران...</span>';
    
    // عدد الدورات العشوائية
    const spinDuration = 4000;
    const minSpins = 8;
    const maxSpins = 15;
    const spins = minSpins + Math.random() * (maxSpins - minSpins);
    const targetRotation = currentRotation + (spins * 2 * Math.PI);
    
    const startTime = Date.now();
    
    function animate() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / spinDuration, 1);
        
        // دالة التباطؤ
        const easeOut = 1 - Math.pow(1 - progress, 3);
        
        currentRotation = targetRotation * easeOut;
        drawWheel(currentRotation);
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            spinning = false;
            spinBtn.disabled = false;
            spinBtn.innerHTML = '<i class="fas fa-play"></i><span>دور</span>';
            
            // تحديد الجائزة
            const winningSegment = getWinningSegment(currentRotation);
            showWinModal(winningSegment);
            
            // إرسال النتيجة للخادم
            saveSpinResult(winningSegment.id);
        }
    }
    
    requestAnimationFrame(animate);
}

// حفظ نتيجة الدوران
function saveSpinResult(rewardId) {
    fetch(BASE_URL + 'api/handler.php?action=spin_wheel', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ reward_id: rewardId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            // تحديث العداد
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

// عرض مودال الفوز
function showWinModal(segment) {
    const modal = document.getElementById('winModal');
    const winIcon = document.getElementById('winIcon');
    const winPrize = document.getElementById('winPrize');
    
    winIcon.style.background = segment.color + '20';
    winIcon.style.color = segment.color;
    winPrize.style.color = segment.color;
    winPrize.textContent = segment.name;
    
    modal.classList.add('active');
    
    // إطلاق المؤثرات
    createConfetti();
}

// مؤثرات الكونفيتي
function createConfetti() {
    const container = document.getElementById('confettiContainer');
    const colors = ['#FF6B35', '#FF8F65', '#FF4757', '#10B981', '#3B82F6', '#F59E0B', '#9C27B0'];
    
    for (let i = 0; i < 50; i++) {
        const confetti = document.createElement('div');
        confetti.className = 'confetti';
        confetti.style.left = Math.random() * 100 + '%';
        confetti.style.animationDelay = Math.random() * 2 + 's';
        confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
        container.appendChild(confetti);
        
        setTimeout(() => confetti.remove(), 4000);
    }
}

// استلام الجائزة
document.querySelectorAll('.claim-reward-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const spinId = this.dataset.spinId;
        
        fetch(BASE_URL + 'api/handler.php?action=claim_reward', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': CSRF_TOKEN
            },
            body: JSON.stringify({ spin_id: spinId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                showToast('تم استلام الجائزة بنجاح', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    });
});

// التبويبات
document.querySelectorAll('.wheel-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const tabId = this.dataset.tab;
        
        document.querySelectorAll('.wheel-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.wheel-tab-content').forEach(c => c.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById(tabId + 'Tab').classList.add('active');
    });
});

// زر الدوران
document.getElementById('spinWheelBtn')?.addEventListener('click', spinWheel);

// إغلاق المودال
document.querySelectorAll('.modal-close').forEach(btn => {
    btn.addEventListener('click', function() {
        this.closest('.modal').classList.remove('active');
    });
});

// الرسم الأولي
drawWheel(0);

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
    /* Wheel Header */
    .wheel-header {
        background: var(--gradient-primary);
        padding: var(--spacing-2xl) 0;
        margin-bottom: var(--spacing-xl);
        position: relative;
        overflow: hidden;
    }
    
    .wheel-header::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M30 5v50M5 30h50' stroke='%23ffffff' stroke-width='1'/%3E%3C/g%3E%3C/svg%3E");
    }
    
    .wheel-container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 0 var(--spacing-md);
    }
    
    .wheel-header-content {
        text-align: center;
        color: var(--white);
        position: relative;
        z-index: 2;
    }
    
    .wheel-title {
        font-size: 36px;
        font-weight: 800;
        margin-bottom: var(--spacing-sm);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--spacing-md);
    }
    
    .wheel-subtitle {
        font-size: 18px;
        opacity: 0.95;
    }
    
    /* Stats */
    .wheel-stats-wrapper {
        margin-bottom: var(--spacing-xl);
    }
    
    .wheel-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-lg);
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
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    
    .stat-content {
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
    
    .wheel-limit-message {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md);
        background: var(--warning-light);
        border-radius: var(--radius-full);
        color: var(--warning);
        font-weight: 500;
    }
    
    /* Wheel Section */
    .wheel-section {
        padding: var(--spacing-xl) 0;
    }
    
    .wheel-wrapper {
        display: flex;
        align-items: center;
        gap: var(--spacing-2xl);
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .wheel-canvas-container {
        position: relative;
        width: 400px;
        height: 400px;
    }
    
    #wheelCanvas {
        width: 100%;
        height: 100%;
        display: block;
    }
    
    .wheel-pointer {
        position: absolute;
        top: -10px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 40px;
        color: var(--primary);
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.2));
        z-index: 10;
    }
    
    .spin-btn {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 80px;
        height: 80px;
        background: var(--white);
        border: none;
        border-radius: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 16px;
        font-weight: 800;
        color: var(--primary);
        cursor: pointer;
        box-shadow: var(--shadow-xl);
        z-index: 20;
        transition: all var(--transition-bounce);
    }
    
    .spin-btn:hover:not(:disabled) {
        transform: translate(-50%, -50%) scale(1.1);
        background: var(--primary);
        color: var(--white);
    }
    
    .spin-btn:disabled {
        opacity: 0.6;
        cursor: not-allowed;
        background: var(--gray-200);
        color: var(--gray-500);
    }
    
    .spin-btn i {
        font-size: 24px;
    }
    
    .wheel-info {
        flex: 1;
        min-width: 250px;
    }
    
    .remaining-spins {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md);
        background: var(--primary-soft);
        border-radius: var(--radius-full);
        color: var(--primary);
        font-weight: 600;
        margin-bottom: var(--spacing-lg);
    }
    
    .progress-to-spin {
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
    }
    
    .progress-text {
        display: flex;
        justify-content: space-between;
        margin-bottom: var(--spacing-sm);
        font-weight: 600;
    }
    
    /* Rewards Section */
    .wheel-rewards-section {
        padding: var(--spacing-2xl) 0;
        background: var(--gray-50);
    }
    
    .wheel-tabs {
        display: flex;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-xl);
        border-bottom: 1px solid var(--gray-300);
    }
    
    .wheel-tab {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-md) var(--spacing-xl);
        background: none;
        border: none;
        color: var(--gray-600);
        font-weight: 600;
        cursor: pointer;
        position: relative;
        transition: all var(--transition-base);
    }
    
    .wheel-tab::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 3px;
        background: var(--primary);
        transform: scaleX(0);
        transition: transform var(--transition-base);
    }
    
    .wheel-tab.active {
        color: var(--primary);
    }
    
    .wheel-tab.active::after {
        transform: scaleX(1);
    }
    
    .wheel-tab-content {
        display: none;
    }
    
    .wheel-tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }
    
    .rewards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: var(--spacing-md);
    }
    
    .reward-card {
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-card);
        text-align: center;
        border-top: 4px solid;
        transition: all var(--transition-bounce);
    }
    
    .reward-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-lg);
    }
    
    .reward-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto var(--spacing-md);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .reward-name {
        font-weight: 700;
        margin-bottom: var(--spacing-xs);
    }
    
    .reward-value {
        color: var(--gray-500);
        font-size: 14px;
        margin-bottom: var(--spacing-sm);
    }
    
    .reward-probability {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        font-size: 12px;
        color: var(--gray-400);
    }
    
    /* Winners List */
    .winners-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-sm);
    }
    
    .winner-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
        padding: var(--spacing-md);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
    }
    
    .winner-avatar {
        width: 48px;
        height: 48px;
        background: var(--gradient-primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--white);
        font-weight: 700;
        font-size: 20px;
    }
    
    .winner-info {
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    .winner-name {
        font-weight: 700;
    }
    
    .winner-prize {
        font-size: 13px;
        font-weight: 600;
    }
    
    .winner-time {
        font-size: 12px;
        color: var(--gray-400);
    }
    
    /* Win Modal */
    .modal-sm {
        max-width: 350px;
    }
    
    .win-modal-body {
        text-align: center;
        padding: var(--spacing-2xl) var(--spacing-lg);
        position: relative;
        overflow: hidden;
    }
    
    .confetti-container {
        position: absolute;
        inset: 0;
        pointer-events: none;
    }
    
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        top: -20px;
        animation: confettiFall 3s linear forwards;
    }
    
    @keyframes confettiFall {
        0% { transform: translateY(0) rotate(0deg); opacity: 1; }
        100% { transform: translateY(400px) rotate(720deg); opacity: 0; }
    }
    
    .win-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto var(--spacing-lg);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 40px;
        animation: winBounce 0.6s ease;
    }
    
    @keyframes winBounce {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.2); }
    }
    
    .win-title {
        font-size: 28px;
        font-weight: 800;
        margin-bottom: var(--spacing-sm);
        color: var(--gray-900);
    }
    
    .win-message {
        color: var(--gray-500);
        margin-bottom: var(--spacing-sm);
    }
    
    .win-prize {
        font-size: 24px;
        font-weight: 800;
        margin-bottom: var(--spacing-lg);
    }
    
    .win-note {
        font-size: 13px;
        color: var(--gray-400);
        margin-bottom: var(--spacing-xl);
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
    
    /* Responsive */
    @media (max-width: 768px) {
        .wheel-stats-grid {
            grid-template-columns: 1fr;
        }
        
        .wheel-canvas-container {
            width: 320px;
            height: 320px;
        }
        
        .wheel-wrapper {
            flex-direction: column;
        }
        
        .wheel-tabs {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .wheel-tab {
            white-space: nowrap;
        }
        
        .rewards-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>