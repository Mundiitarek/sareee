<?php
/**
 * صفحة المتجر/المطعم - القائمة والمنتجات
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

// الحصول على ID المتجر
$vendor_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$vendor_id) {
    redirect(BASE_URL . 'index.php');
}

// جلب بيانات المتجر
$vendor = db_fetch(
    "SELECT v.*, z.name_ar as zone_name,
            (SELECT AVG(rating) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as avg_rating,
            (SELECT COUNT(*) FROM ratings WHERE vendor_id = v.id AND rated_for = 'vendor') as rating_count,
            (SELECT COUNT(*) FROM orders WHERE vendor_id = v.id AND status = 'delivered') as completed_orders
     FROM vendors v
     LEFT JOIN zones z ON v.zone_id = z.id
     WHERE v.id = ? AND v.status = 'approved'",
    [$vendor_id]
);

if (!$vendor) {
    redirect(BASE_URL . 'index.php');
}

$page_title = escape($vendor['business_name']) . ' - ' . APP_NAME;

// جلب أقسام المتجر
$categories = db_fetch_all(
    "SELECT * FROM categories 
     WHERE vendor_id = ? AND status = 1 
     ORDER BY sort_order ASC",
    [$vendor_id]
);

// جلب كل المنتجات
$products = db_fetch_all(
    "SELECT p.*, c.name_ar as category_name, c.id as category_id
     FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
     WHERE p.vendor_id = ? AND p.is_available = 1
     ORDER BY p.category_id, p.sort_order ASC",
    [$vendor_id]
);

// تجميع المنتجات حسب الأقسام
$products_by_category = [];
foreach ($products as $product) {
    $cat_id = $product['category_id'] ?? 0;
    if (!isset($products_by_category[$cat_id])) {
        $products_by_category[$cat_id] = [];
    }
    $products_by_category[$cat_id][] = $product;
}

// جلب تقييمات المتجر
$ratings = db_fetch_all(
    "SELECT r.*, u.name as user_name, u.avatar as user_avatar
     FROM ratings r
     JOIN users u ON r.user_id = u.id
     WHERE r.vendor_id = ? AND r.rated_for = 'vendor'
     ORDER BY r.created_at DESC
     LIMIT 10",
    [$vendor_id]
);

// جلب عروض المتجر
$offers = db_fetch_all(
    "SELECT * FROM offers 
     WHERE vendor_id = ? AND status = 1 
     AND start_date <= NOW() AND end_date >= NOW()
     ORDER BY created_at DESC",
    [$vendor_id]
);

// التحقق من حالة المتجر (مفتوح/مغلق)
$is_open = $vendor['is_open'] == 1;

// حساب رسوم التوصيل
$delivery_fee = db_fetch(
    "SELECT fee FROM delivery_fees WHERE zone_id = ?",
    [$vendor['zone_id']]
);
$delivery_fee_amount = $delivery_fee['fee'] ?? DELIVERY_FEE_DEFAULT;

// جلب بيانات السلة الحالية للمستخدم
$cart_items = [];
$cart_total = 0;
$cart_count = 0;

if (is_logged_in()) {
    $cart = db_fetch(
        "SELECT * FROM carts WHERE user_id = ? AND vendor_id = ?",
        [$_SESSION['user_id'], $vendor_id]
    );
    
    if ($cart) {
        $cart_items = db_fetch_all(
            "SELECT ci.*, p.name_ar, p.image, p.price, p.discount_price
             FROM cart_items ci
             JOIN products p ON ci.product_id = p.id
             WHERE ci.cart_id = ?",
            [$cart['id']]
        );
        
        foreach ($cart_items as $item) {
            $item_price = $item['unit_price'];
            $cart_total += $item_price * $item['quantity'];
            $cart_count += $item['quantity'];
        }
    }
}

include INCLUDES_PATH . '/header.php';
?>

<!-- Store Header -->
<div class="store-header">
    <div class="store-cover">
        <img src="<?= asset_url($vendor['cover'], 'assets/images/default-cover.jpg') ?>" 
             alt="<?= escape($vendor['business_name']) ?>"
             onerror="this.style.background='var(--gradient-primary)'">
        <div class="store-cover-overlay"></div>
    </div>
    
    <div class="store-info-container">
        <div class="store-logo">
            <img src="<?= asset_url($vendor['logo'], 'assets/images/default-logo.jpg') ?>" 
                 alt="<?= escape($vendor['business_name']) ?>"
                 onerror="this.style.background='var(--primary)'">
        </div>
        
        <div class="store-info">
            <div class="store-title">
                <h1><?= escape($vendor['business_name']) ?></h1>
                <div class="store-badges">
                    <?php if ($is_open): ?>
                    <span class="badge-open"><i class="fas fa-circle"></i> مفتوح</span>
                    <?php else: ?>
                    <span class="badge-closed"><i class="fas fa-circle"></i> مغلق</span>
                    <?php endif; ?>
                    
                    <?php if ($vendor['avg_rating']): ?>
                    <span class="badge-rating">
                        <i class="fas fa-star"></i>
                        <?= number_format($vendor['avg_rating'], 1) ?>
                        <small>(<?= $vendor['rating_count'] ?>)</small>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="store-meta">
                <span><i class="fas fa-utensils"></i> <?= escape($vendor['description'] ?: ($vendor['business_type'] == 'mart' ? 'متجر' : 'مطعم')) ?></span>
                <span><i class="fas fa-map-pin"></i> <?= escape($vendor['zone_name'] ?? 'غير محدد') ?></span>
                <span><i class="fas fa-clock"></i> <?= escape($vendor['delivery_time'] ?: '30-45 دقيقة') ?></span>
            </div>
            
            <div class="store-stats">
                <div class="stat-item">
                    <i class="fas fa-check-circle"></i>
                    <span><?= $vendor['completed_orders'] ?>+ طلب مكتمل</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-truck"></i>
                    <span><?= format_price($delivery_fee_amount) ?> توصيل</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-shopping-bag"></i>
                    <span>حد أدنى <?= format_price($vendor['min_order']) ?></span>
                </div>
            </div>
        </div>
        
        <button class="store-favorite" data-vendor-id="<?= $vendor['id'] ?>">
            <i class="far fa-heart"></i>
        </button>
    </div>
</div>

<!-- Offers Bar -->
<?php if (!empty($offers)): ?>
<div class="offers-bar swiper" id="offersSwiper">
    <div class="swiper-wrapper">
        <?php foreach ($offers as $offer): ?>
        <div class="swiper-slide">
            <div class="offer-card">
                <i class="fas fa-tag"></i>
                <span><?= escape($offer['title_ar']) ?></span>
                <?php if ($offer['code']): ?>
                <span class="offer-code"><?= escape($offer['code']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Category Navigation -->
<div class="category-nav-wrapper">
    <div class="category-nav" id="categoryNav">
        <a href="#all" class="category-nav-item active" data-category="all">
            <i class="fas fa-grid"></i>
            <span>الكل</span>
        </a>
        <?php foreach ($categories as $category): ?>
        <a href="#category-<?= $category['id'] ?>" class="category-nav-item" data-category="<?= $category['id'] ?>">
            <?php if ($category['icon']): ?>
            <i class="fas fa-<?= $category['icon'] ?>"></i>
            <?php else: ?>
            <i class="fas fa-tag"></i>
            <?php endif; ?>
            <span><?= escape($category['name_ar']) ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Menu Content -->
<div class="store-menu-container">
    <div class="store-menu">
        <!-- قسم الكل -->
        <section class="menu-section" id="category-all">
            <h2 class="section-title"><i class="fas fa-star"></i> الأكثر طلباً</h2>
            <div class="products-grid">
                <?php 
                $popular_products = array_slice($products, 0, 6);
                foreach ($popular_products as $product): 
                ?>
                <?= renderProductCard($product, $vendor_id) ?>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- الأقسام مع المنتجات -->
        <?php foreach ($categories as $category): ?>
        <section class="menu-section" id="category-<?= $category['id'] ?>">
            <h2 class="section-title">
                <?php if ($category['icon']): ?>
                <i class="fas fa-<?= $category['icon'] ?>"></i>
                <?php endif; ?>
                <?= escape($category['name_ar']) ?>
                <span class="item-count"><?= count($products_by_category[$category['id']] ?? []) ?> منتج</span>
            </h2>
            
            <div class="products-list">
                <?php if (isset($products_by_category[$category['id']])): ?>
                    <?php foreach ($products_by_category[$category['id']] as $product): ?>
                    <?= renderProductListItem($product, $vendor_id, $cart_items) ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-category">
                        <i class="fas fa-box-open"></i>
                        <p>لا توجد منتجات في هذا القسم حالياً</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endforeach; ?>
        
        <!-- قسم التقييمات -->
        <section class="menu-section" id="ratings-section">
            <h2 class="section-title"><i class="fas fa-star"></i> التقييمات</h2>
            
            <div class="ratings-summary">
                <div class="rating-overall">
                    <div class="rating-number"><?= number_format($vendor['avg_rating'] ?? 0, 1) ?></div>
                    <div class="rating-stars">
                        <?php 
                        $rating = $vendor['avg_rating'] ?? 0;
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                        <i class="fas fa-star <?= $i <= $rating ? 'active' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-count"><?= $vendor['rating_count'] ?? 0 ?> تقييم</div>
                </div>
                
                <div class="rating-bars">
                    <?php
                    $rating_distribution = db_fetch_all(
                        "SELECT rating, COUNT(*) as count FROM ratings 
                         WHERE vendor_id = ? AND rated_for = 'vendor' 
                         GROUP BY rating",
                        [$vendor_id]
                    );
                    $dist = [];
                    foreach ($rating_distribution as $d) {
                        $dist[$d['rating']] = $d['count'];
                    }
                    $total = $vendor['rating_count'] ?: 1;
                    
                    for ($i = 5; $i >= 1; $i--):
                        $count = $dist[$i] ?? 0;
                        $percent = ($count / $total) * 100;
                    ?>
                    <div class="rating-bar-item">
                        <span><?= $i ?> <i class="fas fa-star"></i></span>
                        <div class="rating-bar">
                            <div class="rating-bar-fill" style="width: <?= $percent ?>%"></div>
                        </div>
                        <span><?= $count ?></span>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="ratings-list">
                <?php if (empty($ratings)): ?>
                <div class="empty-ratings">
                    <i class="fas fa-star-half-alt"></i>
                    <p>لا توجد تقييمات بعد</p>
                </div>
                <?php else: ?>
                <?php foreach ($ratings as $rating_item): ?>
                <div class="rating-card">
                    <div class="rating-header">
                        <div class="user-info">
                            <div class="user-avatar">
                                <?php if ($rating_item['user_avatar']): ?>
                                <img src="<?= asset_url($rating_item['user_avatar'], 'assets/images/default-avatar.png') ?>" alt="<?= escape($rating_item['user_name']) ?>">
                                <?php else: ?>
                                <div class="avatar-placeholder"><?= mb_substr($rating_item['user_name'], 0, 1) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="user-details">
                                <h4><?= escape($rating_item['user_name']) ?></h4>
                                <span class="rating-date"><?= time_ago($rating_item['created_at']) ?></span>
                            </div>
                        </div>
                        <div class="rating-value">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $rating_item['rating'] ? 'active' : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($rating_item['review']): ?>
                    <p class="rating-review"><?= escape($rating_item['review']) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- Cart Summary Bar (ثابت في الأسفل) -->
<?php if ($cart_count > 0): ?>
<div class="cart-summary-bar" id="cartSummaryBar">
    <div class="cart-summary-content">
        <div class="cart-info">
            <div class="cart-icon">
                <i class="fas fa-shopping-cart"></i>
                <span class="cart-count"><?= $cart_count ?></span>
            </div>
            <div class="cart-details">
                <span class="cart-total"><?= format_price($cart_total) ?></span>
                <span class="cart-items-count"><?= $cart_count ?> منتجات</span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>checkout.php" class="btn btn-primary cart-checkout-btn">
            <i class="fas fa-arrow-left"></i>
            إتمام الطلب
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Quick View Modal -->
<div class="modal quick-view-modal" id="quickViewModal">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="qvProductName">اسم المنتج</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="qvProductBody">
            <!-- المحتوى يتم تحميله ديناميكياً -->
        </div>
    </div>
</div>

<?php
/**
 * دالة عرض بطاقة المنتج (نمط الشبكة)
 */
function renderProductCard($product, $vendor_id) {
    ob_start();
?>
<div class="product-card menu-product-card" data-product-id="<?= $product['id'] ?>">
    <div class="product-image">
        <img src="<?= asset_url($product['image'], 'assets/images/default-product.jpg') ?>" 
             alt="<?= escape($product['name_ar']) ?>"
             onerror="this.src='<?= BASE_URL ?>assets/images/default-product.jpg'">
        <?php if ($product['discount_price']): ?>
        <span class="discount-badge">-<?= round((($product['price'] - $product['discount_price']) / $product['price']) * 100) ?>%</span>
        <?php endif; ?>
    </div>
    <h4 class="product-name"><?= escape($product['name_ar']) ?></h4>
    <p class="product-desc"><?= escape(truncate($product['description_ar'] ?? '', 30)) ?></p>
    <div class="product-price">
        <?php if ($product['discount_price']): ?>
            <span class="current-price"><?= format_price($product['discount_price']) ?></span>
            <span class="old-price"><?= format_price($product['price']) ?></span>
        <?php else: ?>
            <span class="current-price"><?= format_price($product['price']) ?></span>
        <?php endif; ?>
    </div>
    <button class="add-to-cart-btn" data-product-id="<?= $product['id'] ?>">
        <i class="fas fa-plus"></i>
        <span>أضف للسلة</span>
    </button>
</div>
<?php
    return ob_get_clean();
}

/**
 * دالة عرض المنتج في القائمة
 */
function renderProductListItem($product, $vendor_id, $cart_items) {
    $in_cart = false;
    $cart_quantity = 0;
    
    foreach ($cart_items as $item) {
        if ($item['product_id'] == $product['id']) {
            $in_cart = true;
            $cart_quantity = $item['quantity'];
            break;
        }
    }
    
    ob_start();
?>
<div class="product-list-item" data-product-id="<?= $product['id'] ?>">
    <div class="product-list-info">
        <h4 class="product-list-name"><?= escape($product['name_ar']) ?></h4>
        <?php if ($product['description_ar']): ?>
        <p class="product-list-desc"><?= escape(truncate($product['description_ar'], 60)) ?></p>
        <?php endif; ?>
        <div class="product-list-price">
            <?php if ($product['discount_price']): ?>
                <span class="current-price"><?= format_price($product['discount_price']) ?></span>
                <span class="old-price"><?= format_price($product['price']) ?></span>
            <?php else: ?>
                <span class="current-price"><?= format_price($product['price']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="product-list-image">
        <img src="<?= asset_url($product['image'], 'assets/images/default-product.jpg') ?>" 
             alt="<?= escape($product['name_ar']) ?>"
             onerror="this.style.display='none'">
        
        <?php if ($in_cart): ?>
        <div class="quantity-control cart-quantity-control">
            <button class="quantity-btn decrease-btn" data-product-id="<?= $product['id'] ?>">
                <i class="fas fa-minus"></i>
            </button>
            <span class="quantity-value"><?= $cart_quantity ?></span>
            <button class="quantity-btn increase-btn" data-product-id="<?= $product['id'] ?>">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <?php else: ?>
        <button class="add-btn" data-product-id="<?= $product['id'] ?>">
            <i class="fas fa-plus"></i>
        </button>
        <?php endif; ?>
    </div>
</div>
<?php
    return ob_get_clean();
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Category Navigation Scroll
    const categoryNav = document.getElementById('categoryNav');
    const navItems = document.querySelectorAll('.category-nav-item');
    const sections = document.querySelectorAll('.menu-section');
    
    // تفعيل القسم النشط عند التمرير
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const id = entry.target.id.replace('category-', '');
                navItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.dataset.category === id || (id === 'all' && item.dataset.category === 'all')) {
                        item.classList.add('active');
                    }
                });
            }
        });
    }, { threshold: 0.3, rootMargin: '-100px 0px 0px 0px' });
    
    sections.forEach(section => observer.observe(section));
    
    // النقر على أزرار التنقل
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const category = this.dataset.category;
            const target = category === 'all' ? 'category-all' : 'category-' + category;
            const element = document.getElementById(target);
            
            if (element) {
                const offset = 120;
                const elementPosition = element.getBoundingClientRect().top;
                const offsetPosition = elementPosition + window.pageYOffset - offset;
                
                window.scrollTo({
                    top: offsetPosition,
                    behavior: 'smooth'
                });
            }
            
            navItems.forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Offers Swiper
    if (typeof Swiper !== 'undefined') {
        new Swiper('#offersSwiper', {
            slidesPerView: 'auto',
            spaceBetween: 10,
            freeMode: true,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false
            }
        });
    }
    
    // إضافة للمفضلة
    const favoriteBtn = document.querySelector('.store-favorite');
    if (favoriteBtn) {
        favoriteBtn.addEventListener('click', function() {
            const vendorId = this.dataset.vendorId;
            toggleFavorite(vendorId, 'vendor', this);
        });
    }
    
    // أزرار الإضافة للسلة
    document.querySelectorAll('.add-btn, .add-to-cart-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            addToCart(productId, 1);
        });
    });
    
    // أزرار التحكم بالكمية
    document.querySelectorAll('.increase-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            updateCartItem(productId, 1);
        });
    });
    
    document.querySelectorAll('.decrease-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const productId = this.dataset.productId;
            const quantitySpan = this.parentElement.querySelector('.quantity-value');
            const currentQty = parseInt(quantitySpan.textContent);
            
            if (currentQty > 1) {
                updateCartItem(productId, -1);
            } else {
                removeFromCart(productId);
            }
        });
    });
    
    // Quick View
    document.querySelectorAll('.product-list-item, .menu-product-card').forEach(item => {
        item.addEventListener('click', function(e) {
            if (e.target.closest('button')) return;
            
            const productId = this.dataset.productId;
            openQuickView(productId);
        });
    });
    
    // إغلاق المودال
    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', function() {
            this.closest('.modal').classList.remove('active');
        });
    });
});

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
                showToast('تمت الإضافة إلى المفضلة', 'success');
            }
        }
    });
}

function addToCart(productId, quantity) {
    if (!IS_LOGGED_IN) {
        showToast('يرجى تسجيل الدخول لإضافة المنتجات', 'info');
        window.location.href = BASE_URL + 'login.php';
        return;
    }
    
    const vendorId = <?= $vendor_id ?>;
    
    fetch(BASE_URL + 'api/handler.php?action=add_to_cart', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ 
            product_id: productId, 
            quantity: quantity,
            vendor_id: vendorId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            showToast('تمت الإضافة إلى السلة', 'success');
            location.reload();
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

function updateCartItem(productId, change) {
    fetch(BASE_URL + 'api/handler.php?action=update_cart_item', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ product_id: productId, change: change })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

function removeFromCart(productId) {
    if (!confirm('هل أنت متأكد من إزالة هذا المنتج من السلة؟')) return;
    
    fetch(BASE_URL + 'api/handler.php?action=remove_from_cart', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': CSRF_TOKEN
        },
        body: JSON.stringify({ product_id: productId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            location.reload();
        } else {
            showToast(data.message || 'حدث خطأ', 'error');
        }
    });
}

function openQuickView(productId) {
    fetch(BASE_URL + 'api/handler.php?action=get_product_details&id=' + productId)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const product = data.data;
                const modal = document.getElementById('quickViewModal');
                const title = document.getElementById('qvProductName');
                const body = document.getElementById('qvProductBody');
                
                title.textContent = product.name_ar;
                body.innerHTML = `
                    <div class="qv-product">
                        <div class="qv-image">
                            <img src="${BASE_URL + (product.image || 'assets/images/default-product.jpg')}" 
                                 alt="${escapeHtml(product.name_ar)}">
                        </div>
                        <div class="qv-details">
                            <p class="qv-description">${escapeHtml(product.description_ar || 'لا يوجد وصف')}</p>
                            <div class="qv-price">
                                ${product.discount_price ? 
                                    `<span class="current-price">${formatPrice(product.discount_price)}</span>
                                     <span class="old-price">${formatPrice(product.price)}</span>` :
                                    `<span class="current-price">${formatPrice(product.price)}</span>`
                                }
                            </div>
                            <button class="btn btn-primary btn-block qv-add-btn" data-product-id="${product.id}">
                                <i class="fas fa-cart-plus"></i> أضف للسلة
                            </button>
                        </div>
                    </div>
                `;
                
                modal.classList.add('active');
                
                body.querySelector('.qv-add-btn')?.addEventListener('click', function() {
                    addToCart(this.dataset.productId, 1);
                    modal.classList.remove('active');
                });
            }
        });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    return price.toFixed(2) + ' ' + CURRENCY_SYMBOL;
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
    /* Store Header */
    .store-header {
        position: relative;
        margin-bottom: var(--spacing-xl);
    }
    
    .store-cover {
        position: relative;
        height: 180px;
        overflow: hidden;
    }
    
    .store-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .store-cover-overlay {
        position: absolute;
        inset: 0;
        background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.6));
    }
    
    .store-info-container {
        display: flex;
        align-items: flex-end;
        gap: var(--spacing-md);
        padding: 0 var(--spacing-md);
        margin-top: -40px;
        position: relative;
        z-index: 2;
    }
    
    .store-logo {
        width: 80px;
        height: 80px;
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-lg);
        overflow: hidden;
        border: 3px solid var(--white);
    }
    
    .store-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .store-info {
        flex: 1;
        padding-bottom: var(--spacing-sm);
    }
    
    .store-title {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-xs);
    }
    
    .store-title h1 {
        font-size: 24px;
        margin: 0;
        color: var(--white);
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }
    
    .store-badges {
        display: flex;
        gap: var(--spacing-xs);
    }
    
    .badge-open, .badge-closed, .badge-rating {
        padding: 4px 10px;
        border-radius: var(--radius-full);
        font-size: 12px;
        font-weight: 600;
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(4px);
    }
    
    .badge-open { color: var(--success); }
    .badge-closed { color: var(--danger); }
    .badge-rating { color: var(--warning); }
    
    .store-meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--spacing-md);
        color: rgba(255,255,255,0.9);
        font-size: 13px;
        margin-bottom: var(--spacing-sm);
    }
    
    .store-meta span {
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .store-stats {
        display: flex;
        gap: var(--spacing-lg);
    }
    
    .stat-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: rgba(255,255,255,0.9);
        font-size: 13px;
    }
    
    .store-favorite {
        width: 44px;
        height: 44px;
        background: var(--white);
        border: none;
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: var(--danger);
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-bounce);
        margin-bottom: var(--spacing-sm);
    }
    
    .store-favorite:hover {
        transform: scale(1.1);
        background: var(--danger);
        color: var(--white);
    }
    
    /* Offers Bar */
    .offers-bar {
        padding: var(--spacing-md);
        background: var(--white);
        margin-bottom: var(--spacing-md);
    }
    
    .offer-card {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        padding: var(--spacing-sm) var(--spacing-md);
        background: var(--primary-soft);
        border-radius: var(--radius-full);
        color: var(--primary);
        font-weight: 500;
        white-space: nowrap;
    }
    
    .offer-code {
        padding: 2px 8px;
        background: var(--primary);
        color: var(--white);
        border-radius: var(--radius-full);
        font-size: 12px;
        margin-right: auto;
    }
    
    /* Category Navigation */
    .category-nav-wrapper {
        background: var(--white);
        border-bottom: 1px solid var(--gray-200);
        position: sticky;
        top: 56px;
        z-index: var(--z-sticky);
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .category-nav {
        display: flex;
        padding: var(--spacing-sm) var(--spacing-md);
        gap: var(--spacing-sm);
    }
    
    .category-nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 4px;
        padding: var(--spacing-sm) var(--spacing-md);
        color: var(--gray-600);
        font-size: 13px;
        font-weight: 500;
        white-space: nowrap;
        border-bottom: 2px solid transparent;
        transition: all var(--transition-base);
    }
    
    .category-nav-item i {
        font-size: 18px;
    }
    
    .category-nav-item.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    /* Menu Sections */
    .store-menu-container {
        padding: var(--spacing-md);
    }
    
    .menu-section {
        margin-bottom: var(--spacing-xl);
    }
    
    .menu-section .section-title {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-lg);
    }
    
    .item-count {
        font-size: 14px;
        font-weight: 400;
        color: var(--gray-500);
        margin-right: auto;
    }
    
    /* Products Grid */
    .products-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: var(--spacing-md);
    }
    
    .menu-product-card {
        position: relative;
    }
    
    .add-to-cart-btn {
        width: 100%;
        padding: var(--spacing-sm);
        background: var(--primary-soft);
        border: 1px solid var(--primary-light);
        border-radius: var(--radius-md);
        color: var(--primary);
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--spacing-xs);
        cursor: pointer;
        transition: all var(--transition-base);
        margin-top: var(--spacing-sm);
    }
    
    .add-to-cart-btn:hover {
        background: var(--primary);
        color: var(--white);
    }
    
    /* Products List */
    .products-list {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    
    .product-list-item {
        display: flex;
        gap: var(--spacing-md);
        padding: var(--spacing-md);
        background: var(--white);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        cursor: pointer;
        transition: all var(--transition-base);
    }
    
    .product-list-item:hover {
        box-shadow: var(--shadow-md);
    }
    
    .product-list-info {
        flex: 1;
    }
    
    .product-list-name {
        font-weight: 700;
        margin-bottom: 4px;
    }
    
    .product-list-desc {
        font-size: 13px;
        color: var(--gray-500);
        margin-bottom: var(--spacing-sm);
    }
    
    .product-list-image {
        position: relative;
        width: 80px;
        height: 80px;
        border-radius: var(--radius-md);
        overflow: hidden;
        flex-shrink: 0;
    }
    
    .product-list-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .add-btn {
        position: absolute;
        bottom: 4px;
        left: 4px;
        width: 28px;
        height: 28px;
        background: var(--primary);
        border: none;
        border-radius: var(--radius-full);
        color: var(--white);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: var(--shadow-md);
        transition: all var(--transition-bounce);
    }
    
    .cart-quantity-control {
        position: absolute;
        bottom: 4px;
        left: 4px;
        right: 4px;
        background: var(--white);
        border-radius: var(--radius-full);
        box-shadow: var(--shadow-md);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 2px;
    }
    
    .cart-quantity-control .quantity-btn {
        width: 24px;
        height: 24px;
        background: var(--primary-soft);
        border: none;
        border-radius: var(--radius-full);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
    }
    
    .cart-quantity-control .quantity-value {
        font-weight: 700;
        color: var(--primary);
    }
    
    /* Ratings */
    .ratings-summary {
        display: flex;
        gap: var(--spacing-xl);
        padding: var(--spacing-lg);
        background: var(--white);
        border-radius: var(--radius-lg);
        margin-bottom: var(--spacing-lg);
    }
    
    .rating-overall {
        text-align: center;
    }
    
    .rating-number {
        font-size: 48px;
        font-weight: 800;
        color: var(--primary);
    }
    
    .rating-stars {
        margin: var(--spacing-xs) 0;
    }
    
    .rating-stars i {
        color: var(--gray-300);
    }
    
    .rating-stars i.active {
        color: #FBBF24;
    }
    
    .rating-bars {
        flex: 1;
    }
    
    .rating-bar-item {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
        margin-bottom: var(--spacing-xs);
    }
    
    .rating-bar {
        flex: 1;
        height: 8px;
        background: var(--gray-200);
        border-radius: var(--radius-full);
        overflow: hidden;
    }
    
    .rating-bar-fill {
        height: 100%;
        background: #FBBF24;
        border-radius: var(--radius-full);
    }
    
    .rating-card {
        padding: var(--spacing-md);
        background: var(--white);
        border-radius: var(--radius-lg);
        margin-bottom: var(--spacing-sm);
    }
    
    .rating-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: var(--spacing-sm);
    }
    
    .rating-header .user-info {
        display: flex;
        align-items: center;
        gap: var(--spacing-sm);
    }
    
    .rating-header .user-avatar {
        width: 40px;
        height: 40px;
    }
    
    .rating-value i {
        color: var(--gray-300);
        font-size: 14px;
    }
    
    .rating-value i.active {
        color: #FBBF24;
    }
    
    .rating-review {
        color: var(--gray-600);
        font-size: 14px;
    }
    
    /* Cart Summary Bar */
    .cart-summary-bar {
        position: fixed;
        bottom: 70px;
        left: 0;
        right: 0;
        background: var(--white);
        box-shadow: 0 -4px 20px rgba(0,0,0,0.1);
        padding: var(--spacing-md);
        z-index: var(--z-fixed);
        max-width: 600px;
        margin: 0 auto;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        animation: slideUp 0.3s ease;
    }
    
    .cart-summary-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .cart-info {
        display: flex;
        align-items: center;
        gap: var(--spacing-md);
    }
    
    .cart-icon {
        position: relative;
        width: 50px;
        height: 50px;
        background: var(--primary-soft);
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--primary);
    }
    
    .cart-count {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 22px;
        height: 22px;
        background: var(--danger);
        color: var(--white);
        font-size: 12px;
        font-weight: 700;
        border-radius: var(--radius-full);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .cart-details {
        display: flex;
        flex-direction: column;
    }
    
    .cart-total {
        font-size: 18px;
        font-weight: 800;
        color: var(--primary);
    }
    
    .cart-items-count {
        font-size: 13px;
        color: var(--gray-500);
    }
    
    .cart-checkout-btn {
        padding: var(--spacing-sm) var(--spacing-xl);
        font-size: 16px;
    }
    
    /* Empty States */
    .empty-category, .empty-ratings {
        text-align: center;
        padding: var(--spacing-xl);
        color: var(--gray-400);
    }
    
    .empty-category i, .empty-ratings i {
        font-size: 48px;
        margin-bottom: var(--spacing-md);
    }
    
    /* Quick View Modal */
    .qv-product {
        display: flex;
        flex-direction: column;
        gap: var(--spacing-md);
    }
    
    .qv-image {
        width: 100%;
        aspect-ratio: 1;
        border-radius: var(--radius-lg);
        overflow: hidden;
    }
    
    .qv-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .qv-details {
        text-align: center;
    }
    
    .qv-description {
        color: var(--gray-600);
        margin-bottom: var(--spacing-md);
    }
    
    .qv-price {
        margin-bottom: var(--spacing-lg);
    }
    
    .qv-add-btn {
        padding: var(--spacing-md);
    }
    
    /* Responsive */
    @media (min-width: 768px) {
        .products-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        
        .store-cover {
            height: 220px;
        }
    }
</style>

<?php include INCLUDES_PATH . '/footer.php'; ?>
