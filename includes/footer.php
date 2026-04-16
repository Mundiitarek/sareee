<?php
/**
 * فوتر واجهة العميل - تصميم عصري فاخر
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

$current_page = basename($_SERVER['PHP_SELF']);
?>
    </main>
    <!-- نهاية المحتوى الرئيسي -->

    <!-- شريط التنقل السفلي (للتطبيق موبايل) -->
    <?php if ($current_page == 'index.php' || $current_page == 'mart.php'): ?>
    <nav class="bottom-nav">
        <a href="<?= BASE_URL ?>index.php" class="bottom-nav-item <?= $current_page == 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-home"></i>
            <span>الرئيسية</span>
        </a>
        <a href="<?= BASE_URL ?>index.php#discover" class="bottom-nav-item">
            <i class="fas fa-compass"></i>
            <span>اكتشف</span>
        </a>
        <a href="<?= BASE_URL ?>mart.php" class="bottom-nav-item <?= $current_page == 'mart.php' ? 'active' : '' ?>">
            <i class="fas fa-shopping-basket"></i>
            <span>المارت</span>
        </a>
        <a href="<?= BASE_URL ?>checkout.php" class="bottom-nav-item cart-nav-item">
            <div class="cart-icon-wrapper">
                <i class="fas fa-shopping-cart"></i>
                <?php if ($cart_count > 0): ?>
                <span class="cart-badge-nav"><?= $cart_count ?></span>
                <?php endif; ?>
            </div>
            <span>السلة</span>
        </a>
        <a href="<?= BASE_URL ?>account.php" class="bottom-nav-item">
            <i class="fas fa-user"></i>
            <span>حسابي</span>
        </a>
    </nav>
    <?php endif; ?>

    <!-- الفوتر العادي (لغير صفحات التطبيق الرئيسية) -->
    <?php if ($current_page != 'index.php' && $current_page != 'mart.php'): ?>
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><?= APP_NAME ?></h4>
                    <p>التوصيل السريع لكل ما تحتاجه</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>روابط سريعة</h4>
                    <ul>
                        <li><a href="<?= BASE_URL ?>index.php">الرئيسية</a></li>
                        <li><a href="<?= BASE_URL ?>mart.php">المارت</a></li>
                        <li><a href="#">العروض</a></li>
                        <li><a href="#">مركز المساعدة</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>تواصل معنا</h4>
                    <ul>
                        <li><i class="fas fa-phone"></i> 920000000</li>
                        <li><i class="fas fa-envelope"></i> info@saree3.com</li>
                        <li><i class="fab fa-whatsapp"></i> 0500000000</li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. جميع الحقوق محفوظة.</p>
                <div class="footer-links">
                    <a href="#">الشروط والأحكام</a>
                    <a href="#">سياسة الخصوصية</a>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- نافذة اختيار الموقع Modal -->
    <div class="modal location-modal" id="locationModal">
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-map-pin"></i> اختر موقع التوصيل</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="location-options">
                    <button class="location-option" id="useCurrentLocation">
                        <i class="fas fa-location-dot"></i>
                        <span>استخدام موقعي الحالي</span>
                        <small>تحديد تلقائي</small>
                    </button>
                    <div class="separator">
                        <span>أو</span>
                    </div>
                    <div class="manual-location">
                        <label>اختر المدينة والمنطقة</label>
                        <select id="citySelect" class="form-control">
                            <option value="">اختر المدينة</option>
                        </select>
                        <select id="zoneSelect" class="form-control" disabled>
                            <option value="">اختر المنطقة</option>
                        </select>
                        <select id="areaSelect" class="form-control" disabled>
                            <option value="">اختر الحي</option>
                        </select>
                        <button class="btn btn-primary btn-block mt-3" id="confirmLocation">تأكيد الموقع</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- نافذة الإشعارات السريعة Toast -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Scripts -->
    <script>
        // تعريف الثوابت العامة
        const BASE_URL = '<?= BASE_URL ?>';
        const CSRF_TOKEN = '<?= get_csrf_token() ?>';
        const CURRENT_PAGE = '<?= $current_page ?>';
        const IS_LOGGED_IN = <?= is_logged_in() ? 'true' : 'false' ?>;
        const USER_ID = <?= is_logged_in() ? $_SESSION['user_id'] : 'null' ?>;
        const CURRENCY_SYMBOL = '<?= CURRENCY_SYMBOL ?>';
    </script>
    
    <!-- jQuery (للعمليات الأساسية) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <!-- Main App Script -->
    <script src="<?= BASE_URL ?>assets/js/app.js?v=<?= time() ?>"></script>
    
    <?php if ($current_page == 'index.php'): ?>
    <!-- Splash Screen Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const splash = document.getElementById('splash-screen');
            if (splash) {
                setTimeout(() => {
                    splash.classList.add('hidden-splash');
                }, 2500);
            }
        });
    </script>
    <?php endif; ?>
    
    <!-- Location Modal Logic -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // التحقق من وجود موقع محفوظ
            const savedLocation = localStorage.getItem('userLocation');
            
            if (!savedLocation && (CURRENT_PAGE === 'index.php' || CURRENT_PAGE === 'mart.php')) {
                setTimeout(() => {
                    document.getElementById('locationModal').classList.add('active');
                }, 3000);
            }
            
            // عرض الموقع المحدد
            if (savedLocation) {
                try {
                    const location = JSON.parse(savedLocation);
                    const displayEl = document.getElementById('selectedLocation');
                    if (displayEl) {
                        displayEl.textContent = location.display || location.area || 'تم تحديد الموقع';
                    }
                } catch(e) {}
            }
            
            // زر فتح النافذة
            const locationSelector = document.getElementById('locationSelector');
            if (locationSelector) {
                locationSelector.addEventListener('click', function() {
                    document.getElementById('locationModal').classList.add('active');
                });
            }
            
            // إغلاق النافذة
            document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
                el.addEventListener('click', function() {
                    this.closest('.modal').classList.remove('active');
                });
            });
            
            // استخدام الموقع الحالي
            document.getElementById('useCurrentLocation')?.addEventListener('click', function() {
                if (navigator.geolocation) {
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>جاري تحديد موقعك...</span>';
                    navigator.geolocation.getCurrentPosition(
                        function(position) {
                            const lat = position.coords.latitude;
                            const lng = position.coords.longitude;
                            
                            // حفظ الموقع
                            const location = {
                                type: 'gps',
                                lat: lat,
                                lng: lng,
                                display: 'موقعي الحالي'
                            };
                            localStorage.setItem('userLocation', JSON.stringify(location));
                            
                            // تحديث العرض
                            const displayEl = document.getElementById('selectedLocation');
                            if (displayEl) displayEl.textContent = 'موقعي الحالي';
                            
                            document.getElementById('locationModal').classList.remove('active');
                            
                            showToast('تم تحديد موقعك الحالي بنجاح', 'success');
                        },
                        function(error) {
                            showToast('تعذر الوصول إلى موقعك، يرجى الاختيار يدوياً', 'error');
                            document.querySelector('#useCurrentLocation').innerHTML = '<i class="fas fa-location-dot"></i><span>استخدام موقعي الحالي</span><small>تحديد تلقائي</small>';
                        }
                    );
                } else {
                    showToast('متصفحك لا يدعم تحديد الموقع', 'error');
                }
            });
            
            // تحميل المدن والمناطق
            loadCities();
        });
        
        function loadCities() {
            fetch(BASE_URL + 'api/handler.php?action=get_locations')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const citySelect = document.getElementById('citySelect');
                        citySelect.innerHTML = '<option value="">اختر المدينة</option>';
                        data.data.cities.forEach(city => {
                            citySelect.innerHTML += `<option value="${city.id}">${city.name}</option>`;
                        });
                    }
                });
        }
        
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            `;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // تسجيل الخروج
        document.getElementById('logoutBtn')?.addEventListener('click', function() {
            if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                fetch(BASE_URL + 'api/handler.php?action=logout', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-Token': CSRF_TOKEN
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        window.location.href = BASE_URL + 'index.php';
                    }
                });
            }
        });
        
        // تفعيل القائمة الجانبية
        const menuToggle = document.getElementById('menuToggle');
        const sideMenu = document.getElementById('sideMenu');
        const menuOverlay = document.getElementById('menuOverlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', () => sideMenu.classList.add('active'));
        }
        if (menuOverlay) {
            menuOverlay.addEventListener('click', () => sideMenu.classList.remove('active'));
        }
    </script>

    <?php if ($current_page == 'checkout.php'): ?>
    <!-- Google Maps API (لاختيار العنوان) -->
    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&libraries=places&language=ar&region=SA"></script>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_message'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?= addslashes($_SESSION['flash_message']) ?>', '<?= $_SESSION['flash_type'] ?? 'info' ?>');
        });
    </script>
    <?php 
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    endif; 
    ?>
</body>
</html>