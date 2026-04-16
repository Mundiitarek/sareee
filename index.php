<?php
/**
 * الصفحة الرئيسية - المطاعم والاكتشاف
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// جلب بيانات الصفحة
$page_title = APP_NAME . ' - التوصيل السريع';

// جلب البانرات النشطة
$banners = db_fetch_all(
    "SELECT * FROM banners 
     WHERE status = 1 
     AND target IN ('food', 'both') 
     AND (start_date IS NULL OR start_date <= NOW()) 
     AND (end_date IS NULL OR end_date >= NOW()) 
     ORDER BY sort_order ASC, created_at DESC 
     LIMIT 5"
);

// جلب الأقسام العامة
$categories = db_fetch_all(
    "SELECT * FROM categories 
     WHERE vendor_id IS NULL 
     AND status = 1 
     AND type IN ('food', 'both') 
     ORDER BY sort_order ASC 
     LIMIT 8"
);

// جلب المطاعم المميزة
$featured_vendors = db_fetch_all(
    "SELECT v.*, z.name_ar as zone_name,
            (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
            (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
     FROM vendors v
     LEFT JOIN zones z ON v.zone_id = z.id
     WHERE v.status = 'approved' 
     AND v.business_type IN ('restaurant', 'both')
     AND v.is_open = 1
     ORDER BY v.rating DESC, v.created_at DESC 
     LIMIT 6"
);

$all_vendors = db_fetch_all(
    "SELECT v.*, z.name_ar as zone_name,
            (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
            (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
     FROM vendors v
     LEFT JOIN zones z ON v.zone_id = z.id
     WHERE v.status = 'approved'
     AND v.business_type IN ('restaurant', 'both')
     AND v.is_open = 1
     ORDER BY v.sort_order ASC, v.created_at DESC
     LIMIT 20"
);

// جلب العروض النشطة
$active_offers = db_fetch_all(
    "SELECT * FROM offers 
     WHERE status = 1 
     AND start_date <= NOW() 
     AND end_date >= NOW()
     AND vendor_id IS NULL
     ORDER BY created_at DESC 
     LIMIT 3"
);

// جلب إعدادات التوصيل المجاني
$free_delivery_setting = db_fetch(
    "SELECT * FROM delivery_fees 
     WHERE min_order_for_free IS NOT NULL 
     LIMIT 1"
);

include INCLUDES_PATH . '/header.php';
?>

<!-- Splash Screen Animation (يظهر مرة واحدة) -->
<div id="splash-screen" style="display: none;">
    <div class="splash-logo">
        <img src="<?= BASE_URL ?>assets/images/logo-white.svg" alt="<?= APP_NAME ?>" onerror="this.style.display='none'">
        <h1><?= APP_NAME ?></h1>
        <p>التوصيل السريع</p>
        <div class="splash-spinner">
            <div class="spinner"></div>
        </div>
    </div>
</div>

<!-- Hero Section - البانر الرئيسي -->
<section class="hero-section">
    <div class="hero-banner swiper" id="heroBanner">
        <div class="swiper-wrapper">
            <?php if (empty($banners)): ?>
                <!-- بانر افتراضي -->
                <div class="swiper-slide">
                    <img src="<?= BASE_URL ?>assets/images/hero-placeholder.svg" alt="سريع" onerror="this.style.background='var(--gradient-primary)'">
                    <div class="banner-content">
                        <h2>اهلاً بك في سريع</h2>
                        <p>اطلب طعامك المفضل بسرعة وسهولة</p>
                    </div>
                </div>
                <div class="swiper-slide">
                    <img src="<?= BASE_URL ?>assets/images/hero-placeholder.svg" alt="عروض سريع" onerror="this.style.background='var(--gradient-dark)'">
                    <div class="banner-content">
                        <h2>عروض حصرية</h2>
                        <p>خصومات تصل إلى 50% على مطاعمك المفضلة</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($banners as $banner): ?>
                <div class="swiper-slide">
                    <img src="<?= asset_url($banner['image']) ?>" alt="<?= escape($banner['title_ar']) ?>">
                    <?php if ($banner['title_ar'] || $banner['description_ar']): ?>
                    <div class="banner-content">
                        <?php if ($banner['title_ar']): ?>
                        <h2><?= escape($banner['title_ar']) ?></h2>
                        <?php endif; ?>
                        <?php if ($banner['description_ar']): ?>
                        <p><?= escape($banner['description_ar']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

<!-- Categories Section - الأقسام -->
<section class="categories-section">
    <div class="section-header">
        <h2 class="section-title">ماذا تريد أن تطلب؟</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="categories-grid">
        <?php if (empty($categories)): ?>
            <!-- أقسام افتراضية -->
            <?php
            $default_categories = [
                ['icon' => '🍕', 'name' => 'بيتزا'],
                ['icon' => '🍔', 'name' => 'برجر'],
                ['icon' => '🍣', 'name' => 'سوشي'],
                ['icon' => '🥗', 'name' => 'صحي'],
                ['icon' => '🍝', 'name' => 'مكرونة'],
                ['icon' => '🥩', 'name' => 'ستيك'],
                ['icon' => '🍦', 'name' => 'حلويات'],
                ['icon' => '🥤', 'name' => 'مشروبات'],
            ];
            foreach ($default_categories as $cat):
            ?>
            <div class="category-card">
                <div class="category-icon">
                    <span><?= $cat['icon'] ?></span>
                </div>
                <span><?= $cat['name'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
            <div class="category-card" data-category-id="<?= $category['id'] ?>">
                <div class="category-icon">
                    <?php if ($category['icon']): ?>
                        <span><?= $category['icon'] ?></span>
                    <?php elseif ($category['image']): ?>
                        <img src="<?= asset_url($category['image']) ?>" alt="<?= escape($category['name_ar']) ?>">
                    <?php else: ?>
                        <i class="fas fa-utensils"></i>
                    <?php endif; ?>
                </div>
                <span><?= escape($category['name_ar']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Featured Section - "وفر مع سريع" -->
<?php if (!empty($active_offers)): ?>
<section class="featured-section">
    <div class="featured-content">
        <div class="featured-text">
            <h2>🎉 وفر مع سريع</h2>
            <p><?= escape($active_offers[0]['title_ar'] ?? 'خصومات حصرية على كل الطلبات') ?></p>
        </div>
        <div class="featured-offer">
            <?php if ($active_offers[0]['offer_type'] == 'percentage'): ?>
                <span>خصم <?= $active_offers[0]['discount_value'] ?>%</span>
            <?php else: ?>
                <span>خصم <?= format_price($active_offers[0]['discount_value']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Free Delivery Banner -->
<section class="free-delivery-banner" style="
    display: flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    border-radius: 16px;
    padding: 14px 16px;
    margin: 12px 16px;
    box-shadow: 0 4px 15px rgba(255, 107, 53, 0.3);
">
    <div class="banner-icon" style="
        width: 42px;
        height: 42px;
        background: rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    ">
        <i class="fas fa-truck-fast" style="color: #fff; font-size: 18px;"></i>
    </div>
    <div class="banner-text" style="flex: 1; min-width: 0;">
        <h3 style="color: #fff; font-size: 14px; font-weight: 700; margin: 0 0 2px;">توصيل مجاني</h3>
        <p style="color: rgba(255,255,255,0.85); font-size: 12px; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            عند طلبك بقيمة <?= format_price($free_delivery_setting['min_order_for_free'] ?? 100) ?> فأكثر
        </p>
    </div>
    <a href="#all-restaurants" style="
        background: #fff;
        color: #ff6b35;
        font-size: 12px;
        font-weight: 700;
        padding: 8px 14px;
        border-radius: 10px;
        text-decoration: none;
        white-space: nowrap;
        flex-shrink: 0;
    ">اطلب الآن</a>
</section>

<!-- Featured Restaurants - مطاعم مميزة -->
<section class="featured-restaurants">
    <div class="section-header">
        <h2 class="section-title">مطاعم مميزة</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="vendor-grid">
        <?php if (empty($featured_vendors)): ?>
            <!-- مطاعم افتراضية للعرض -->
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="vendor-card skeleton-loading">
                <div class="vendor-image">
                    <div class="loading-skeleton" style="width: 100%; height: 180px;"></div>
                </div>
                <div class="vendor-info">
                    <div class="loading-skeleton" style="height: 20px; margin-bottom: 8px;"></div>
                    <div class="loading-skeleton" style="height: 16px; width: 70%;"></div>
                </div>
            </div>
            <?php endfor; ?>
        <?php else: ?>
            <?php foreach ($featured_vendors as $index => $vendor): ?>
            <div class="vendor-card" data-vendor-id="<?= $vendor['id'] ?>" style="animation-delay: <?= $index * 0.1 ?>s;">
                <div class="vendor-image">
                    <img src="<?= asset_url($vendor['cover'], 'assets/images/default-restaurant.jpg') ?>" 
                         alt="<?= escape($vendor['business_name']) ?>"
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-restaurant.jpg'">
                    
                    <?php if ($vendor['rating'] > 4.5): ?>
                    <div class="vendor-badge">
                        <i class="fas fa-crown"></i> الأفضل مبيعاً
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    // عرض الخصم إذا وجد
                    $vendor_offer = db_fetch(
                        "SELECT * FROM offers WHERE vendor_id = ? AND status = 1 AND start_date <= NOW() AND end_date >= NOW() LIMIT 1",
                        [$vendor['id']]
                    );
                    if ($vendor_offer):
                    ?>
                    <div class="vendor-offer">
                        <?php if ($vendor_offer['offer_type'] == 'percentage'): ?>
                            <span>خصم <?= $vendor_offer['discount_value'] ?>%</span>
                        <?php else: ?>
                            <span>خصم <?= format_price($vendor_offer['discount_value']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="vendor-favorite" data-vendor-id="<?= $vendor['id'] ?>">
                        <i class="far fa-heart"></i>
                    </div>
                </div>
                
                <div class="vendor-info">
                    <div class="vendor-name">
                        <h3><?= escape($vendor['business_name']) ?></h3>
                        <?php if ($vendor['avg_rating']): ?>
                        <div class="vendor-rating">
                            <i class="fas fa-star"></i>
                            <span><?= number_format($vendor['avg_rating'], 1) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vendor-desc">
                        <span><i class="fas fa-utensils"></i> <?= escape($vendor['description'] ?: 'مأكولات متنوعة') ?></span>
                        <span><i class="fas fa-map-pin"></i> <?= escape($vendor['zone_name'] ?? 'غير محدد') ?></span>
                    </div>
                    
                    <div class="vendor-footer">
                        <div class="delivery-time">
                            <i class="fas fa-clock"></i>
                            <span><?= escape($vendor['delivery_time'] ?: '30-45 دقيقة') ?></span>
                        </div>
                        <div class="delivery-fee">
                            <?php
                            $delivery_fee = db_fetch(
                                "SELECT fee FROM delivery_fees WHERE zone_id = ?",
                                [$vendor['zone_id']]
                            );
                            $fee = $delivery_fee['fee'] ?? DELIVERY_FEE_DEFAULT;
                            ?>
                            <span><?= format_price($fee) ?> توصيل</span>
                        </div>
                    </div>
                    
                    <?php if ($vendor['min_order'] > 0): ?>
                    <div class="min-order">
                        <i class="fas fa-shopping-bag"></i>
                        الحد الأدنى: <?= format_price($vendor['min_order']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- All Restaurants - كل المطاعم -->
<section class="all-restaurants" id="all-restaurants">
    <div class="section-header">
        <h2 class="section-title">كل المطاعم</h2>
        <div class="filter-options">
            <select id="sortRestaurants" class="filter-select">
                <option value="rating">الأعلى تقييماً</option>
                <option value="newest">الأحدث</option>
                <option value="delivery_time">الأسرع توصيلاً</option>
                <option value="min_order">الأقل حد أدنى</option>
            </select>
        </div>
    </div>
    
    <div class="vendor-grid" id="allVendorsGrid">
        <?php foreach ($all_vendors as $index => $vendor): ?>
        <div class="vendor-card" data-vendor-id="<?= $vendor['id'] ?>" style="animation-delay: <?= $index * 0.05 ?>s;">
            <div class="vendor-image">
                <img src="<?= asset_url($vendor['cover'], 'assets/images/default-restaurant.jpg') ?>" 
                     alt="<?= escape($vendor['business_name']) ?>"
                     onerror="this.src='<?= BASE_URL ?>assets/images/default-restaurant.jpg'">
                
                <?php if ($vendor['is_open']): ?>
                <div class="vendor-badge" style="background: var(--success);">
                    <i class="fas fa-circle"></i> مفتوح
                </div>
                <?php else: ?>
                <div class="vendor-badge" style="background: var(--danger);">
                    <i class="fas fa-circle"></i> مغلق
                </div>
                <?php endif; ?>
                
                <div class="vendor-favorite" data-vendor-id="<?= $vendor['id'] ?>">
                    <i class="far fa-heart"></i>
                </div>
            </div>
            
            <div class="vendor-info">
                <div class="vendor-name">
                    <h3><?= escape($vendor['business_name']) ?></h3>
                    <?php if ($vendor['avg_rating']): ?>
                    <div class="vendor-rating">
                        <i class="fas fa-star"></i>
                        <span><?= number_format($vendor['avg_rating'], 1) ?></span>
                        <small>(<?= $vendor['rating_count'] ?>)</small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="vendor-desc">
                    <span><i class="fas fa-tag"></i> <?= escape($vendor['description'] ?: 'مطعم') ?></span>
                </div>
                
                <div class="vendor-footer">
                    <div class="delivery-time">
                        <i class="fas fa-clock"></i>
                        <span><?= escape($vendor['delivery_time'] ?: '30-45 دقيقة') ?></span>
                    </div>
                    <div class="delivery-fee">
                        <span><?= format_price(DELIVERY_FEE_DEFAULT) ?> توصيل</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($all_vendors) >= 20): ?>
    <div class="load-more-container">
        <button class="btn btn-outline-primary btn-block" id="loadMoreVendors">
            <i class="fas fa-plus"></i>
            عرض المزيد من المطاعم
        </button>
    </div>
    <?php endif; ?>
</section>

<!-- Discover Section - اكتشف -->
<section class="discover-section" id="discover">
    <div class="section-header">
        <h2 class="section-title">اكتشف</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="discover-grid">
        <!-- منتجات مميزة -->
        <?php
        $featured_products = db_fetch_all(
            "SELECT p.*, v.business_name as vendor_name, v.id as vendor_id
             FROM products p
             JOIN vendors v ON p.vendor_id = v.id
             WHERE p.is_featured = 1 
             AND p.is_available = 1
             AND v.status = 'approved'
             ORDER BY RAND()
             LIMIT 6"
        );
        ?>
        
        <?php if (!empty($featured_products)): ?>
            <?php foreach ($featured_products as $product): ?>
            <div class="product-card" data-product-id="<?= $product['id'] ?>" data-vendor-id="<?= $product['vendor_id'] ?>">
                <div class="product-image">
                    <img src="<?= asset_url($product['image'], 'assets/images/default-product.jpg') ?>" 
     alt="<?= escape($product['name_ar']) ?>"
     onerror="this.onerror=null;this.src='https://placehold.co/400x400?text=صورة'">
                </div>
                <h4 class="product-name"><?= escape($product['name_ar']) ?></h4>
                <p class="product-desc"><?= escape($product['vendor_name']) ?></p>
                <div class="product-price">
                    <?php if ($product['discount_price']): ?>
                        <span class="current-price"><?= format_price($product['discount_price']) ?></span>
                        <span class="old-price"><?= format_price($product['price']) ?></span>
                    <?php else: ?>
                        <span class="current-price"><?= format_price($product['price']) ?></span>
                    <?php endif; ?>
                </div>
                <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- منتجات افتراضية -->
            <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="product-card">
                <div class="product-image">
                    <div class="loading-skeleton" style="width: 100%; aspect-ratio: 1;"></div>
                </div>
                <div class="loading-skeleton" style="height: 20px; margin: 10px 0;"></div>
                <div class="loading-skeleton" style="height: 16px; width: 60%;"></div>
            </div>
            <?php endfor; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Scripts خاصة بالصفحة الرئيسية -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة Swiper للبانر
    if (typeof Swiper !== 'undefined') {
        new Swiper('#heroBanner', {
            slidesPerView: 1,
            spaceBetween: 0,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            }
        });
    }
    
    // تفعيل Splash Screen
    const splashShown = getCookie('splash_shown');
    if (!splashShown) {
        const splash = document.getElementById('splash-screen');
        if (splash) {
            splash.style.display = 'flex';
            setTimeout(() => {
                splash.style.opacity = '0';
                splash.style.visibility = 'hidden';
                setCookie('splash_shown', '1', 1);
            }, 2500);
        }
    }
    
    // تصفية المطاعم
    const sortSelect = document.getElementById('sortRestaurants');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortVendors(this.value);
        });
    }
    
    // أزرار الإضافة للمفضلة
    document.querySelectorAll('.vendor-favorite').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const vendorId = this.dataset.vendorId;
            toggleFavorite(vendorId, 'vendor', this);
        });
    });
    
    // النقر على بطاقة المطعم
    document.querySelectorAll('.vendor-card').forEach(card => {
        card.addEventListener('click', function() {
            const vendorId = this.dataset.vendorId;
            if (vendorId) {
                window.location.href = BASE_URL + 'store.php?id=' + vendorId;
            }
        });
    });
    
    // النقر على بطاقة المنتج
    document.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', function(e) {
            if (!e.target.closest('.add-to-cart')) {
                const vendorId = this.dataset.vendorId;
                const productId = this.dataset.productId;
                if (vendorId) {
                    window.location.href = BASE_URL + 'store.php?id=' + vendorId + '#product-' + productId;
                }
            }
        });
    });
    
    // أزرار الإضافة للسلة
    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const productId = this.dataset.productId;
            addToCart(productId, 1);
        });
    });
    
    // النقر على الأقسام
    document.querySelectorAll('.category-card').forEach(card => {
        card.addEventListener('click', function() {
            const categoryId = this.dataset.categoryId;
            if (categoryId) {
                window.location.href = BASE_URL + 'index.php?category=' + categoryId;
            }
        });
    });
    
    // تحميل المزيد من المطاعم
    const loadMoreBtn = document.getElementById('loadMoreVendors');
    if (loadMoreBtn) {
        let page = 2;
        loadMoreBtn.addEventListener('click', function() {
            loadMoreVendors(page);
            page++;
        });
    }
});

// دوال مساعدة
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/`;
}

function resolveAsset(path, fallback = 'assets/images/default-restaurant.jpg') {
    if (!path) return BASE_URL + fallback;
    if (/^https?:\/\//i.test(path)) return path;
    return BASE_URL + String(path).replace(/^\/+/, '');
}

function sortVendors(sortBy) {
    const grid = document.getElementById('allVendorsGrid');
    const cards = Array.from(grid.children);
    
    cards.sort((a, b) => {
        const aData = getVendorData(a);
        const bData = getVendorData(b);
        
        switch(sortBy) {
            case 'rating':
                return bData.rating - aData.rating;
            case 'newest':
                return bData.id - aData.id;
            case 'delivery_time':
                return aData.deliveryTime - bData.deliveryTime;
            case 'min_order':
                return aData.minOrder - bData.minOrder;
            default:
                return 0;
        }
    });
    
    grid.innerHTML = '';
    cards.forEach(card => grid.appendChild(card));
}

function getVendorData(card) {
    // استخراج البيانات من البطاقة
    return {
        id: parseInt(card.dataset.vendorId) || 0,
        rating: parseFloat(card.querySelector('.vendor-rating span')?.textContent) || 0,
        deliveryTime: 30, // افتراضي
        minOrder: 0
    };
}

function toggleFavorite(vendorId, type, element) {
    if (!IS_LOGGED_IN) {
        showToast('يرجى تسجيل الدخول لإضافة المفضلة', 'info');
        window.location.href = BASE_URL + 'login.php';
        return;
    }
    
    const icon = element.querySelector('i');
    const isFavorite = icon.classList.contains('fas');
    
    fetch(BASE_URL + 'api/handler.php?action=toggle_favorite', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
            type: type,
            id: vendorId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            if (isFavorite) {
                icon.classList.remove('fas');
                icon.classList.add('far');
                showToast('تم الإزالة من المفضلة', 'info');
            } else {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.animation = 'heartBeat 0.3s ease-in-out';
                showToast('تمت الإضافة إلى المفضلة', 'success');
                setTimeout(() => icon.style.animation = '', 300);
            }
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

function addToCart(productId, quantity) {
    if (!IS_LOGGED_IN) {
        showToast('يرجى تسجيل الدخول لإضافة المنتجات', 'info');
        window.location.href = BASE_URL + 'login.php';
        return;
    }
    
    fetch(BASE_URL + 'api/handler.php?action=add_to_cart', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({
            product_id: productId,
            quantity: quantity
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تمت الإضافة إلى السلة', 'success');
            updateCartCount(data.data.cart_count);
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

function loadMoreVendors(page) {
    const btn = document.getElementById('loadMoreVendors');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحميل...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=get_vendors&page=' + page)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                const grid = document.getElementById('allVendorsGrid');
                data.data.forEach((vendor, index) => {
                    const card = createVendorCard(vendor, index);
                    grid.appendChild(card);
                });
                
                if (data.data.length < 20) {
                    btn.style.display = 'none';
                }
            } else {
                btn.style.display = 'none';
            }
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
}

function createVendorCard(vendor, index) {
    const div = document.createElement('div');
    div.className = 'vendor-card';
    div.dataset.vendorId = vendor.id;
    div.style.animationDelay = (index * 0.05) + 's';
    
    div.innerHTML = `
        <div class="vendor-image">
            <img src="${resolveAsset(vendor.cover, 'assets/images/default-restaurant.jpg')}" 
                 alt="${escapeHtml(vendor.business_name)}"
                 onerror="this.src='${BASE_URL}assets/images/default-restaurant.jpg'">
            ${vendor.is_open ? 
                '<div class="vendor-badge" style="background: var(--success);"><i class="fas fa-circle"></i> مفتوح</div>' : 
                '<div class="vendor-badge" style="background: var(--danger);"><i class="fas fa-circle"></i> مغلق</div>'}
            <div class="vendor-favorite" data-vendor-id="${vendor.id}">
                <i class="far fa-heart"></i>
            </div>
        </div>
        <div class="vendor-info">
            <div class="vendor-name">
                <h3>${escapeHtml(vendor.business_name)}</h3>
                ${vendor.avg_rating ? `
                <div class="vendor-rating">
                    <i class="fas fa-star"></i>
                    <span>${vendor.avg_rating.toFixed(1)}</span>
                    <small>(${vendor.rating_count})</small>
                </div>` : ''}
            </div>
            <div class="vendor-desc">
                <span><i class="fas fa-tag"></i> ${escapeHtml(vendor.description || 'مطعم')}</span>
            </div>
            <div class="vendor-footer">
                <div class="delivery-time">
                    <i class="fas fa-clock"></i>
                    <span>${escapeHtml(vendor.delivery_time || '30-45 دقيقة')}</span>
                </div>
                <div class="delivery-fee">
                    <span>${formatPrice(vendor.delivery_fee || 15)} توصيل</span>
                </div>
            </div>
        </div>
    `;
    
    div.addEventListener('click', () => {
        window.location.href = BASE_URL + 'store.php?id=' + vendor.id;
    });
    
    const favBtn = div.querySelector('.vendor-favorite');
    favBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFavorite(vendor.id, 'vendor', favBtn);
    });
    
    return div;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    return price.toFixed(2) + ' ' + CURRENCY_SYMBOL;
}

function updateCartCount(count) {
    const badges = document.querySelectorAll('.cart-badge, .cart-badge-nav');
    badges.forEach(badge => {
        badge.textContent = count;
        if (count > 0) {
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    });
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

<!-- إضافة Swiper JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<!-- إضافة أنيميشن إضافية -->
<style>
    .discover-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: var(--spacing-md);
    }
    
    .filter-select {
        padding: var(--spacing-sm) var(--spacing-lg);
        border: 1px solid var(--gray-300);
        border-radius: var(--radius-full);
        background: var(--white);
        color: var(--gray-700);
        font-family: var(--font-primary);
        font-size: 14px;
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-soft);
    }
    
    .load-more-container {
        text-align: center;
        margin-top: var(--spacing-xl);
    }
    
    @keyframes heartBeat {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.3); }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
