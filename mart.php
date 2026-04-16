<?php
/**
 * صفحة المارت - المتاجر والمنتجات
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

$page_title = 'المارت - ' . APP_NAME;

// جلب البانرات الخاصة بالمارت
$banners = db_fetch_all(
    "SELECT * FROM banners 
     WHERE status = 1 
     AND target IN ('mart', 'both') 
     AND (start_date IS NULL OR start_date <= NOW()) 
     AND (end_date IS NULL OR end_date >= NOW()) 
     ORDER BY sort_order ASC, created_at DESC 
     LIMIT 5"
);

// جلب أقسام المارت
$categories = db_fetch_all(
    "SELECT * FROM categories 
     WHERE vendor_id IS NULL 
     AND status = 1 
     AND type IN ('mart', 'both') 
     ORDER BY sort_order ASC 
     LIMIT 8"
);

// جلب متاجر المارت المميزة
$featured_marts = db_fetch_all(
    "SELECT v.*, z.name_ar as zone_name,
            (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
            (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
     FROM vendors v
     LEFT JOIN zones z ON v.zone_id = z.id
     WHERE v.status = 'approved' 
     AND v.business_type IN ('mart', 'both')
     AND v.is_open = 1
     ORDER BY v.rating DESC, v.created_at DESC 
     LIMIT 6"
);

// جلب كل المتاجر
$all_marts = db_fetch_all(
    "SELECT v.*, z.name_ar as zone_name,
            (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
            (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count
     FROM vendors v
     LEFT JOIN zones z ON v.zone_id = z.id
     WHERE v.status = 'approved'
     AND v.business_type IN ('mart', 'both')
     AND v.is_open = 1
     ORDER BY v.sort_order ASC, v.created_at DESC
     LIMIT 20"
);

// جلب المنتجات المخفضة
$discounted_products = db_fetch_all(
    "SELECT p.*, v.business_name as vendor_name, v.id as vendor_id
     FROM products p
     JOIN vendors v ON p.vendor_id = v.id
     WHERE p.discount_price IS NOT NULL 
     AND p.discount_price > 0
     AND p.is_available = 1
     AND v.status = 'approved'
     AND v.business_type IN ('mart', 'both')
     ORDER BY (p.price - p.discount_price) DESC
     LIMIT 8"
);

// جلب المنتجات الأكثر مبيعاً
$best_selling = db_fetch_all(
    "SELECT p.*, v.business_name as vendor_name, v.id as vendor_id,
            COUNT(oi.id) as order_count
     FROM products p
     JOIN vendors v ON p.vendor_id = v.id
     LEFT JOIN order_items oi ON p.id = oi.product_id
     WHERE p.is_available = 1
     AND v.status = 'approved'
     AND v.business_type IN ('mart', 'both')
     GROUP BY p.id
     ORDER BY order_count DESC
     LIMIT 8"
);

// جلب العروض النشطة
$active_offers = db_fetch_all(
    "SELECT * FROM offers 
     WHERE status = 1 
     AND start_date <= NOW() 
     AND end_date >= NOW()
     ORDER BY created_at DESC 
     LIMIT 3"
);

include INCLUDES_PATH . '/header.php';
?>

<!-- Hero Banner للمارت -->
<section class="hero-section mart-hero">
    <div class="hero-banner swiper" id="martHeroBanner">
        <div class="swiper-wrapper">
            <?php if (empty($banners)): ?>
                <div class="swiper-slide">
                    <img src="<?= BASE_URL ?>assets/images/mart-banner-1.jpg" alt="المارت" onerror="this.style.background='var(--gradient-success)'">
                    <div class="banner-content">
                        <h2><i class="fas fa-basket-shopping"></i> المارت</h2>
                        <p>كل احتياجاتك اليومية في مكان واحد</p>
                    </div>
                </div>
                <div class="swiper-slide">
                    <img src="<?= BASE_URL ?>assets/images/mart-banner-2.jpg" alt="عروض المارت" onerror="this.style.background='var(--gradient-primary)'">
                    <div class="banner-content">
                        <h2><i class="fas fa-tags"></i> عروض حصرية</h2>
                        <p>خصومات تصل إلى 40% على منتجات مختارة</p>
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

<!-- أقسام المارت -->
<section class="categories-section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-grid-2"></i> أقسام المارت</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="categories-grid">
        <?php if (empty($categories)): ?>
            <?php
            $default_categories = [
                ['icon' => 'fa-basket-shopping', 'name' => 'مقاضي'],
                ['icon' => 'fa-apple-whole', 'name' => 'فواكه وخضار'],
                ['icon' => 'fa-cow', 'name' => 'ألبان وأجبان'],
                ['icon' => 'fa-bread-slice', 'name' => 'مخبوزات'],
                ['icon' => 'fa-drumstick-bite', 'name' => 'لحوم ودواجن'],
                ['icon' => 'fa-candy-cane', 'name' => 'حلويات'],
                ['icon' => 'fa-flask', 'name' => 'منظفات'],
                ['icon' => 'fa-baby', 'name' => 'عناية شخصية'],
            ];
            foreach ($default_categories as $cat):
            ?>
            <div class="category-card">
                <div class="category-icon">
                    <i class="fas <?= $cat['icon'] ?>"></i>
                </div>
                <span><?= $cat['name'] ?></span>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
            <div class="category-card" data-category-id="<?= $category['id'] ?>">
                <div class="category-icon">
                    <?php if ($category['image']): ?>
                        <img src="<?= asset_url($category['image']) ?>" alt="<?= escape($category['name_ar']) ?>">
                    <?php else: ?>
                        <i class="fas fa-shopping-bag"></i>
                    <?php endif; ?>
                </div>
                <span><?= escape($category['name_ar']) ?></span>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- عروض المارت -->
<?php if (!empty($active_offers)): ?>
<section class="featured-section mart-featured">
    <div class="featured-content">
        <div class="featured-text">
            <h2><i class="fas fa-gem"></i> عروض المارت الحصرية</h2>
            <p><?= escape($active_offers[0]['title_ar'] ?? 'خصومات مميزة على آلاف المنتجات') ?></p>
        </div>
        <div class="featured-offer">
            <?php if ($active_offers[0]['offer_type'] == 'percentage'): ?>
                <span><i class="fas fa-percent"></i> خصم <?= $active_offers[0]['discount_value'] ?>%</span>
            <?php else: ?>
                <span><i class="fas fa-tag"></i> خصم <?= format_price($active_offers[0]['discount_value']) ?></span>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- توصيل سريع للمارت -->
<section class="free-delivery-banner mart-delivery">
    <div class="banner-icon">
        <i class="fas fa-truck"></i>
    </div>
    <div class="banner-text">
        <h3><i class="fas fa-bolt"></i> توصيل سريع للمارت</h3>
        <p>توصيل طلبات المارت خلال 60 دقيقة أو أقل</p>
    </div>
    <a href="#all-marts" class="banner-btn"><i class="fas fa-store"></i> تسوق الآن</a>
</section>

<!-- متاجر مميزة -->
<section class="featured-restaurants">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-star"></i> متاجر مميزة</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="vendor-grid">
        <?php if (empty($featured_marts)): ?>
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
            <?php foreach ($featured_marts as $index => $mart): ?>
            <div class="vendor-card" data-vendor-id="<?= $mart['id'] ?>" style="animation-delay: <?= $index * 0.1 ?>s;">
                <div class="vendor-image">
                    <img src="<?= asset_url($mart['cover'], 'assets/images/default-mart.jpg') ?>" 
                         alt="<?= escape($mart['business_name']) ?>"
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-mart.jpg'">
                    
                    <?php if ($mart['rating'] > 4.5): ?>
                    <div class="vendor-badge">
                        <i class="fas fa-crown"></i> متجر مميز
                    </div>
                    <?php endif; ?>
                    
                    <?php
                    $mart_offer = db_fetch(
                        "SELECT * FROM offers WHERE vendor_id = ? AND status = 1 AND start_date <= NOW() AND end_date >= NOW() LIMIT 1",
                        [$mart['id']]
                    );
                    if ($mart_offer):
                    ?>
                    <div class="vendor-offer">
                        <?php if ($mart_offer['offer_type'] == 'percentage'): ?>
                            <span><i class="fas fa-percent"></i> خصم <?= $mart_offer['discount_value'] ?>%</span>
                        <?php else: ?>
                            <span><i class="fas fa-tag"></i> خصم <?= format_price($mart_offer['discount_value']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="vendor-favorite" data-vendor-id="<?= $mart['id'] ?>">
                        <i class="far fa-heart"></i>
                    </div>
                </div>
                
                <div class="vendor-info">
                    <div class="vendor-name">
                        <h3><?= escape($mart['business_name']) ?></h3>
                        <?php if ($mart['avg_rating']): ?>
                        <div class="vendor-rating">
                            <i class="fas fa-star"></i>
                            <span><?= number_format($mart['avg_rating'], 1) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vendor-desc">
                        <span><i class="fas fa-basket-shopping"></i> <?= escape($mart['description'] ?: 'متجر') ?></span>
                        <span><i class="fas fa-map-pin"></i> <?= escape($mart['zone_name'] ?? 'غير محدد') ?></span>
                    </div>
                    
                    <div class="vendor-footer">
                        <div class="delivery-time">
                            <i class="fas fa-clock"></i>
                            <span><?= escape($mart['delivery_time'] ?: '45-60 دقيقة') ?></span>
                        </div>
                        <div class="delivery-fee">
                            <?php
                            $delivery_fee = db_fetch(
                                "SELECT fee FROM delivery_fees WHERE zone_id = ?",
                                [$mart['zone_id']]
                            );
                            $fee = $delivery_fee['fee'] ?? DELIVERY_FEE_DEFAULT;
                            ?>
                            <span><?= format_price($fee) ?> توصيل</span>
                        </div>
                    </div>
                    
                    <?php if ($mart['min_order'] > 0): ?>
                    <div class="min-order">
                        <i class="fas fa-shopping-cart"></i>
                        الحد الأدنى: <?= format_price($mart['min_order']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- منتجات مخفضة -->
<section class="discounted-products">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-fire"></i> عروض وتخفيضات</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="products-grid">
        <?php if (!empty($discounted_products)): ?>
            <?php foreach ($discounted_products as $product): ?>
            <div class="product-card discount-card" data-product-id="<?= $product['id'] ?>" data-vendor-id="<?= $product['vendor_id'] ?>">
                <div class="product-image">
                    <img src="<?= asset_url($product['image'], 'assets/images/default-product.jpg') ?>" 
                         alt="<?= escape($product['name_ar']) ?>"
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-product.jpg'">
                    <?php
                    $discount_percent = round((($product['price'] - $product['discount_price']) / $product['price']) * 100);
                    ?>
                    <span class="discount-badge">-<?= $discount_percent ?>%</span>
                </div>
                <h4 class="product-name"><?= escape($product['name_ar']) ?></h4>
                <p class="product-desc"><?= escape($product['vendor_name']) ?></p>
                <div class="product-price">
                    <span class="current-price"><?= format_price($product['discount_price']) ?></span>
                    <span class="old-price"><?= format_price($product['price']) ?></span>
                </div>
                <button class="add-to-cart" data-product-id="<?= $product['id'] ?>">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- كل المتاجر -->
<section class="all-restaurants" id="all-marts">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-store"></i> كل المتاجر</h2>
        <div class="filter-options">
            <select id="sortMarts" class="filter-select">
                <option value="rating">الأعلى تقييماً</option>
                <option value="newest">الأحدث</option>
                <option value="delivery_time">الأسرع توصيلاً</option>
                <option value="min_order">الأقل حد أدنى</option>
            </select>
        </div>
    </div>
    
    <div class="vendor-grid" id="allMartsGrid">
        <?php foreach ($all_marts as $index => $mart): ?>
        <div class="vendor-card" data-vendor-id="<?= $mart['id'] ?>" style="animation-delay: <?= $index * 0.05 ?>s;">
            <div class="vendor-image">
                <img src="<?= asset_url($mart['cover'], 'assets/images/default-mart.jpg') ?>" 
                     alt="<?= escape($mart['business_name']) ?>"
                     onerror="this.src='<?= BASE_URL ?>assets/images/default-mart.jpg'">
                
                <?php if ($mart['is_open']): ?>
                <div class="vendor-badge" style="background: var(--success);">
                    <i class="fas fa-circle"></i> مفتوح
                </div>
                <?php else: ?>
                <div class="vendor-badge" style="background: var(--danger);">
                    <i class="fas fa-circle"></i> مغلق
                </div>
                <?php endif; ?>
                
                <div class="vendor-favorite" data-vendor-id="<?= $mart['id'] ?>">
                    <i class="far fa-heart"></i>
                </div>
            </div>
            
            <div class="vendor-info">
                <div class="vendor-name">
                    <h3><?= escape($mart['business_name']) ?></h3>
                    <?php if ($mart['avg_rating']): ?>
                    <div class="vendor-rating">
                        <i class="fas fa-star"></i>
                        <span><?= number_format($mart['avg_rating'], 1) ?></span>
                        <small>(<?= $mart['rating_count'] ?>)</small>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="vendor-desc">
                    <span><i class="fas fa-tag"></i> <?= escape($mart['description'] ?: 'متجر') ?></span>
                </div>
                
                <div class="vendor-footer">
                    <div class="delivery-time">
                        <i class="fas fa-clock"></i>
                        <span><?= escape($mart['delivery_time'] ?: '45-60 دقيقة') ?></span>
                    </div>
                    <div class="delivery-fee">
                        <span><?= format_price(DELIVERY_FEE_DEFAULT) ?> توصيل</span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php if (count($all_marts) >= 20): ?>
    <div class="load-more-container">
        <button class="btn btn-outline-primary btn-block" id="loadMoreMarts">
            <i class="fas fa-plus"></i>
            عرض المزيد من المتاجر
        </button>
    </div>
    <?php endif; ?>
</section>

<!-- المنتجات الأكثر مبيعاً -->
<section class="best-selling-section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-chart-line"></i> الأكثر مبيعاً</h2>
        <a href="#" class="see-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
    <div class="products-grid">
        <?php if (!empty($best_selling)): ?>
            <?php foreach ($best_selling as $product): ?>
            <div class="product-card best-seller-card" data-product-id="<?= $product['id'] ?>" data-vendor-id="<?= $product['vendor_id'] ?>">
                <div class="product-image">
                    <img src="<?= asset_url($product['image'], 'assets/images/default-product.jpg') ?>" 
                         alt="<?= escape($product['name_ar']) ?>"
                         onerror="this.src='<?= BASE_URL ?>assets/images/default-product.jpg'">
                    <span class="best-seller-badge"><i class="fas fa-crown"></i> الأكثر مبيعاً</span>
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
        <?php endif; ?>
    </div>
</section>

<!-- Scripts خاصة بصفحة المارت -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // تهيئة Swiper للبانر
    if (typeof Swiper !== 'undefined') {
        new Swiper('#martHeroBanner', {
            slidesPerView: 1,
            spaceBetween: 0,
            loop: true,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            effect: 'slide'
        });
    }
    
    // تصفية المتاجر
    const sortSelect = document.getElementById('sortMarts');
    if (sortSelect) {
        sortSelect.addEventListener('change', function() {
            sortMarts(this.value);
        });
    }
    
    // أزرار المفضلة
    document.querySelectorAll('.vendor-favorite').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const vendorId = this.dataset.vendorId;
            toggleFavorite(vendorId, 'vendor', this);
        });
    });
    
    // النقر على بطاقة المتجر
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
                window.location.href = BASE_URL + 'mart.php?category=' + categoryId;
            }
        });
    });
    
    // تحميل المزيد من المتاجر
    const loadMoreBtn = document.getElementById('loadMoreMarts');
    if (loadMoreBtn) {
        let page = 2;
        loadMoreBtn.addEventListener('click', function() {
            loadMoreMarts(page);
            page++;
        });
    }
});

function sortMarts(sortBy) {
    const grid = document.getElementById('allMartsGrid');
    const cards = Array.from(grid.children);
    
    cards.sort((a, b) => {
        const aData = getMartData(a);
        const bData = getMartData(b);
        
        switch(sortBy) {
            case 'rating': return bData.rating - aData.rating;
            case 'newest': return bData.id - aData.id;
            case 'delivery_time': return aData.deliveryTime - bData.deliveryTime;
            case 'min_order': return aData.minOrder - bData.minOrder;
            default: return 0;
        }
    });
    
    grid.innerHTML = '';
    cards.forEach(card => grid.appendChild(card));
}

function getMartData(card) {
    return {
        id: parseInt(card.dataset.vendorId) || 0,
        rating: parseFloat(card.querySelector('.vendor-rating span')?.textContent) || 0,
        deliveryTime: 45,
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
        body: JSON.stringify({ type: type, id: vendorId })
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
        body: JSON.stringify({ product_id: productId, quantity: quantity })
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

function loadMoreMarts(page) {
    const btn = document.getElementById('loadMoreMarts');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحميل...';
    btn.disabled = true;
    
    fetch(BASE_URL + 'api/handler.php?action=get_marts&page=' + page)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success' && data.data.length > 0) {
                const grid = document.getElementById('allMartsGrid');
                data.data.forEach((mart, index) => {
                    const card = createMartCard(mart, index);
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

function createMartCard(mart, index) {
    const div = document.createElement('div');
    div.className = 'vendor-card';
    div.dataset.vendorId = mart.id;
    div.style.animationDelay = (index * 0.05) + 's';
    
    div.innerHTML = `
        <div class="vendor-image">
            <img src="${resolveAsset(mart.cover, 'assets/images/default-mart.jpg')}" 
                 alt="${escapeHtml(mart.business_name)}"
                 onerror="this.src='${BASE_URL}assets/images/default-mart.jpg'">
            ${mart.is_open ? 
                '<div class="vendor-badge" style="background: var(--success);"><i class="fas fa-circle"></i> مفتوح</div>' : 
                '<div class="vendor-badge" style="background: var(--danger);"><i class="fas fa-circle"></i> مغلق</div>'}
            <div class="vendor-favorite" data-vendor-id="${mart.id}">
                <i class="far fa-heart"></i>
            </div>
        </div>
        <div class="vendor-info">
            <div class="vendor-name">
                <h3>${escapeHtml(mart.business_name)}</h3>
                ${mart.avg_rating ? `
                <div class="vendor-rating">
                    <i class="fas fa-star"></i>
                    <span>${mart.avg_rating.toFixed(1)}</span>
                    <small>(${mart.rating_count})</small>
                </div>` : ''}
            </div>
            <div class="vendor-desc">
                <span><i class="fas fa-tag"></i> ${escapeHtml(mart.description || 'متجر')}</span>
            </div>
            <div class="vendor-footer">
                <div class="delivery-time">
                    <i class="fas fa-clock"></i>
                    <span>${escapeHtml(mart.delivery_time || '45-60 دقيقة')}</span>
                </div>
                <div class="delivery-fee">
                    <span>${formatPrice(mart.delivery_fee || 15)} توصيل</span>
                </div>
            </div>
        </div>
    `;
    
    div.addEventListener('click', () => {
        window.location.href = BASE_URL + 'store.php?id=' + mart.id;
    });
    
    const favBtn = div.querySelector('.vendor-favorite');
    favBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFavorite(mart.id, 'vendor', favBtn);
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

function resolveAsset(path, fallback = 'assets/images/default-mart.jpg') {
    if (!path) return BASE_URL + fallback;
    if (/^https?:\/\//i.test(path)) return path;
    return BASE_URL + String(path).replace(/^\/+/, '');
}

function updateCartCount(count) {
    const badges = document.querySelectorAll('.cart-badge, .cart-badge-nav');
    badges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'flex' : 'none';
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

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

<style>
    .mart-hero .hero-banner {
        background: linear-gradient(135deg, var(--success) 0%, var(--primary) 100%);
    }
    
    .mart-featured {
        background: var(--gradient-success);
    }
    
    .mart-delivery {
        border-right-color: var(--success);
    }
    
    .products-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: var(--spacing-md);
    }
    
    .discount-badge {
        position: absolute;
        top: var(--spacing-sm);
        left: var(--spacing-sm);
        padding: 4px 10px;
        background: var(--gradient-danger);
        color: var(--white);
        font-size: 12px;
        font-weight: 700;
        border-radius: var(--radius-full);
        z-index: 2;
        box-shadow: var(--shadow-md);
    }
    
    .best-seller-badge {
        position: absolute;
        bottom: var(--spacing-sm);
        left: var(--spacing-sm);
        padding: 4px 10px;
        background: var(--gradient-warning);
        color: var(--white);
        font-size: 11px;
        font-weight: 700;
        border-radius: var(--radius-full);
        z-index: 2;
    }
    
    .discount-card .product-image::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(45deg, rgba(239,68,68,0.1), transparent);
        z-index: 1;
    }
    
    .best-seller-card {
        border: 1px solid var(--warning-light);
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
