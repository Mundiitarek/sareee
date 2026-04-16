/**
 * الجافاسكريبت الرئيسي للعميل
 * Saree3 - تطبيق توصيل ومارت
 * @version 1.0
 */

(function() {
    'use strict';

    // =====================================================
    // Global Variables & Configuration
    // =====================================================
    window.APP = {
        baseUrl: BASE_URL || '/',
        csrfToken: CSRF_TOKEN || '',
        isLoggedIn: IS_LOGGED_IN || false,
        userId: USER_ID || null,
        currencySymbol: CURRENCY_SYMBOL || 'ر.س',
        currentPage: CURRENT_PAGE || 'index'
    };

    // =====================================================
    // Utility Functions
    // =====================================================

    /**
     * عرض رسالة Toast
     */
    window.showToast = function(message, type = 'info', duration = 3000) {
        let container = document.getElementById('toastContainer');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let icon = 'info-circle';
        if (type === 'success') icon = 'check-circle';
        else if (type === 'error') icon = 'times-circle';
        else if (type === 'warning') icon = 'exclamation-triangle';
        
        toast.innerHTML = `
            <i class="fas fa-${icon}"></i>
            <span>${escapeHtml(message)}</span>
            <button class="toast-close">&times;</button>
        `;
        
        container.appendChild(toast);
        
        // إضافة زر الإغلاق
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        });
        
        // إظهار التوست
        setTimeout(() => toast.classList.add('show'), 10);
        
        // إخفاء تلقائي
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, duration);
        
        return toast;
    };

    /**
     * تنظيف النص من HTML
     */
    window.escapeHtml = function(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };

    /**
     * تنسيق السعر
     */
    window.formatPrice = function(price, withSymbol = true) {
        const formatted = parseFloat(price).toFixed(2);
        return withSymbol ? `${formatted} ${APP.currencySymbol}` : formatted;
    };

    /**
     * عمل طلب AJAX
     */
    window.apiRequest = async function(endpoint, options = {}) {
        const defaultOptions = {
            method: 'GET',
            headers: {
                'X-CSRF-Token': APP.csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };
        
        const mergedOptions = { ...defaultOptions, ...options };
        
        if (mergedOptions.body && !(mergedOptions.body instanceof FormData)) {
            mergedOptions.headers['Content-Type'] = 'application/json';
            mergedOptions.body = JSON.stringify(mergedOptions.body);
        }
        
        try {
            const response = await fetch(APP.baseUrl + 'api/handler.php?action=' + endpoint, mergedOptions);
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.message || 'حدث خطأ في الطلب');
            }
            
            return data;
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    };

    /**
     * الحصول على كوكي
     */
    window.getCookie = function(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    };

    /**
     * تعيين كوكي
     */
    window.setCookie = function(name, value, days = 7) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value}; expires=${date.toUTCString()}; path=/`;
    };

    /**
     * حذف كوكي
     */
    window.deleteCookie = function(name) {
        document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/`;
    };

    // =====================================================
    // Cart Functions
    // =====================================================

    window.cart = {
        count: 0,
        
        /**
         * تحديث عداد السلة
         */
        updateCount: function(count) {
            this.count = count;
            const badges = document.querySelectorAll('.cart-badge, .cart-badge-nav');
            badges.forEach(badge => {
                if (badge) {
                    badge.textContent = count;
                    badge.style.display = count > 0 ? 'flex' : 'none';
                }
            });
        },
        
        /**
         * إضافة منتج للسلة
         */
        addItem: async function(productId, quantity = 1, notes = '', options = null) {
            if (!APP.isLoggedIn) {
                showToast('يرجى تسجيل الدخول لإضافة المنتجات', 'info');
                setTimeout(() => {
                    window.location.href = APP.baseUrl + 'login.php';
                }, 1000);
                return;
            }
            
            try {
                const data = await apiRequest('add_to_cart', {
                    method: 'POST',
                    body: {
                        product_id: productId,
                        quantity: quantity,
                        notes: notes,
                        options: options
                    }
                });
                
                if (data.status === 'success') {
                    showToast('تمت الإضافة إلى السلة', 'success');
                    this.updateCount(data.data.cart_count);
                    
                    // أنيميشن إضافة للسلة
                    this.animateAddToCart();
                }
                
                return data;
            } catch (error) {
                showToast(error.message || 'حدث خطأ', 'error');
            }
        },
        
        /**
         * أنيميشن إضافة للسلة
         */
        animateAddToCart: function() {
            const cartIcon = document.querySelector('.cart-btn i, .bottom-nav-item .fa-shopping-cart');
            if (cartIcon) {
                cartIcon.style.animation = 'none';
                setTimeout(() => {
                    cartIcon.style.animation = 'cartBounce 0.5s ease';
                }, 10);
            }
        },
        
        /**
         * تحديث كمية منتج
         */
        updateItem: async function(productId, change) {
            try {
                const data = await apiRequest('update_cart_item', {
                    method: 'POST',
                    body: { product_id: productId, change: change }
                });
                
                if (data.status === 'success') {
                    return data;
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        },
        
        /**
         * حذف منتج من السلة
         */
        removeItem: async function(productId) {
            try {
                const data = await apiRequest('remove_from_cart', {
                    method: 'POST',
                    body: { product_id: productId }
                });
                
                if (data.status === 'success') {
                    showToast('تم حذف المنتج من السلة', 'info');
                }
                
                return data;
            } catch (error) {
                showToast(error.message, 'error');
            }
        }
    };

    // =====================================================
    // Location Functions
    // =====================================================

    window.locationManager = {
        currentLocation: null,
        
        /**
         * تحميل بيانات المدن والمناطق
         */
        loadLocations: async function() {
            try {
                const data = await apiRequest('get_locations');
                if (data.status === 'success') {
                    return data.data;
                }
            } catch (error) {
                console.error('Error loading locations:', error);
            }
            return null;
        },
        
        /**
         * الحصول على الموقع الحالي
         */
        getCurrentPosition: function() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('متصفحك لا يدعم تحديد الموقع'));
                    return;
                }
                
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        });
                    },
                    (error) => {
                        reject(new Error('تعذر الوصول إلى موقعك'));
                    },
                    { timeout: 10000 }
                );
            });
        },
        
        /**
         * حفظ الموقع المختار
         */
        saveLocation: function(location) {
            this.currentLocation = location;
            localStorage.setItem('userLocation', JSON.stringify(location));
            
            const displayEl = document.getElementById('selectedLocation');
            if (displayEl) {
                displayEl.textContent = location.display || location.area || 'تم تحديد الموقع';
            }
        },
        
        /**
         * استرجاع الموقع المحفوظ
         */
        getSavedLocation: function() {
            const saved = localStorage.getItem('userLocation');
            if (saved) {
                try {
                    this.currentLocation = JSON.parse(saved);
                    return this.currentLocation;
                } catch (e) {
                    return null;
                }
            }
            return null;
        }
    };

    // =====================================================
    // Notification Functions
    // =====================================================

    window.notifications = {
        unreadCount: 0,
        
        /**
         * جلب الإشعارات
         */
        fetch: async function(page = 1) {
            try {
                const data = await apiRequest(`get_notifications&page=${page}`);
                if (data.status === 'success') {
                    this.unreadCount = data.data.unread_count;
                    this.updateBadge();
                    return data.data.notifications;
                }
            } catch (error) {
                console.error('Error fetching notifications:', error);
            }
            return [];
        },
        
        /**
         * تحديث شارة الإشعارات
         */
        updateBadge: function() {
            const badges = document.querySelectorAll('.notification-badge, .tab-badge');
            badges.forEach(badge => {
                if (badge) {
                    badge.textContent = this.unreadCount;
                    badge.style.display = this.unreadCount > 0 ? 'flex' : 'none';
                }
            });
        },
        
        /**
         * تحديد إشعار كمقروء
         */
        markAsRead: async function(notificationId) {
            try {
                await apiRequest('mark_notification_read', {
                    method: 'POST',
                    body: { notification_id: notificationId }
                });
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
            } catch (error) {
                console.error('Error marking notification as read:', error);
            }
        },
        
        /**
         * تحديد الكل كمقروء
         */
        markAllAsRead: async function() {
            try {
                await apiRequest('mark_all_notifications_read', {
                    method: 'POST'
                });
                this.unreadCount = 0;
                this.updateBadge();
            } catch (error) {
                console.error('Error marking all as read:', error);
            }
        }
    };

    // =====================================================
    // Favorites Functions
    // =====================================================

    window.favorites = {
        /**
         * تبديل حالة المفضلة
         */
        toggle: async function(type, id, element) {
            if (!APP.isLoggedIn) {
                showToast('يرجى تسجيل الدخول لإضافة المفضلة', 'info');
                setTimeout(() => {
                    window.location.href = APP.baseUrl + 'login.php';
                }, 1000);
                return;
            }
            
            try {
                const data = await apiRequest('toggle_favorite', {
                    method: 'POST',
                    body: { type: type, id: id }
                });
                
                if (data.status === 'success') {
                    const icon = element.querySelector('i') || element;
                    
                    if (data.data.is_favorite) {
                        icon.classList.remove('far');
                        icon.classList.add('fas');
                        icon.style.animation = 'heartBeat 0.3s ease-in-out';
                        showToast('تمت الإضافة إلى المفضلة', 'success');
                    } else {
                        icon.classList.remove('fas');
                        icon.classList.add('far');
                        showToast('تم الإزالة من المفضلة', 'info');
                    }
                    
                    setTimeout(() => icon.style.animation = '', 300);
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        }
    };

    // =====================================================
    // Order Functions
    // =====================================================

    window.orders = {
        /**
         * إلغاء طلب
         */
        cancel: async function(orderId) {
            if (!confirm('هل أنت متأكد من إلغاء الطلب؟')) {
                return;
            }
            
            try {
                const data = await apiRequest('cancel_order', {
                    method: 'POST',
                    body: { order_id: orderId }
                });
                
                if (data.status === 'success') {
                    showToast('تم إلغاء الطلب بنجاح', 'success');
                    setTimeout(() => location.reload(), 1000);
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        },
        
        /**
         * إعادة طلب
         */
        reorder: async function(orderId) {
            try {
                const data = await apiRequest(`reorder&order_id=${orderId}`);
                
                if (data.status === 'success') {
                    showToast('تم إضافة الطلب إلى السلة', 'success');
                    setTimeout(() => {
                        window.location.href = APP.baseUrl + 'checkout.php';
                    }, 1000);
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        },
        
        /**
         * تتبع الطلب
         */
        track: async function(orderId) {
            try {
                const data = await apiRequest(`track_order&order_id=${orderId}`);
                
                if (data.status === 'success') {
                    // فتح نافذة التتبع
                    this.showTrackingModal(data.data);
                }
            } catch (error) {
                showToast(error.message, 'error');
            }
        },
        
        /**
         * عرض نافذة التتبع
         */
        showTrackingModal: function(trackingData) {
            // يتم تنفيذها حسب الحاجة
            console.log('Tracking data:', trackingData);
        }
    };

    // =====================================================
    // Rating Functions
    // =====================================================

    window.ratings = {
        /**
         * تهيئة نجوم التقييم
         */
        initStars: function() {
            document.querySelectorAll('.star-rating').forEach(ratingDiv => {
                const stars = ratingDiv.querySelectorAll('i');
                const forType = ratingDiv.dataset.for;
                const hiddenInput = document.getElementById(forType + 'Rating');
                
                stars.forEach((star, index) => {
                    star.addEventListener('click', function() {
                        const rating = this.dataset.rating;
                        if (hiddenInput) hiddenInput.value = rating;
                        
                        stars.forEach((s, i) => {
                            if (i < rating) {
                                s.classList.remove('far');
                                s.classList.add('fas', 'active');
                            } else {
                                s.classList.remove('fas', 'active');
                                s.classList.add('far');
                            }
                        });
                    });
                    
                    star.addEventListener('mouseenter', function() {
                        const rating = this.dataset.rating;
                        stars.forEach((s, i) => {
                            if (i < rating) {
                                s.classList.add('hover');
                            } else {
                                s.classList.remove('hover');
                            }
                        });
                    });
                    
                    star.addEventListener('mouseleave', function() {
                        stars.forEach(s => s.classList.remove('hover'));
                    });
                });
            });
        }
    };

    // =====================================================
    // Modal Functions
    // =====================================================

    window.modal = {
        /**
         * فتح مودال
         */
        open: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },
        
        /**
         * إغلاق مودال
         */
        close: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        },
        
        /**
         * إغلاق جميع المودالات
         */
        closeAll: function() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.classList.remove('active');
            });
            document.body.style.overflow = '';
        }
    };

    // =====================================================
    // Form Validation
    // =====================================================

    window.formValidator = {
        /**
         * التحقق من صحة رقم الجوال
         */
        validatePhone: function(phone) {
            const cleaned = phone.replace(/[\s\-\(\)]/g, '');
            return /^\+?[0-9]{7,15}$/.test(cleaned);
        },
        
        /**
         * التحقق من صحة البريد الإلكتروني
         */
        validateEmail: function(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        },
        
        /**
         * عرض خطأ في الحقل
         */
        showError: function(input, message) {
            const formGroup = input.closest('.form-group');
            if (formGroup) {
                const errorEl = formGroup.querySelector('.error-message') || document.createElement('span');
                errorEl.className = 'error-message';
                errorEl.textContent = message;
                if (!formGroup.querySelector('.error-message')) {
                    formGroup.appendChild(errorEl);
                }
                input.classList.add('error');
            }
        },
        
        /**
         * إخفاء خطأ الحقل
         */
        clearError: function(input) {
            const formGroup = input.closest('.form-group');
            if (formGroup) {
                const errorEl = formGroup.querySelector('.error-message');
                if (errorEl) errorEl.remove();
                input.classList.remove('error');
            }
        }
    };

    // =====================================================
    // Loading States
    // =====================================================

    window.loading = {
        /**
         * إظهار تحميل على زر
         */
        showButton: function(btn, text = 'جاري التحميل...') {
            if (!btn) return;
            
            btn.dataset.originalText = btn.innerHTML;
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${text}`;
            btn.disabled = true;
        },
        
        /**
         * إخفاء تحميل الزر
         */
        hideButton: function(btn) {
            if (!btn) return;
            
            btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
            btn.disabled = false;
            delete btn.dataset.originalText;
        },
        
        /**
         * إظهار تحميل عام
         */
        show: function(container = 'body') {
            const loader = document.createElement('div');
            loader.className = 'global-loader';
            loader.innerHTML = '<div class="loading-spinner"></div>';
            loader.id = 'globalLoader';
            
            const target = typeof container === 'string' ? document.querySelector(container) : container;
            if (target) {
                target.style.position = 'relative';
                target.appendChild(loader);
            }
        },
        
        /**
         * إخفاء التحميل العام
         */
        hide: function() {
            const loader = document.getElementById('globalLoader');
            if (loader) loader.remove();
        }
    };

    // =====================================================
    // Side Menu
    // =====================================================

    window.sideMenu = {
        init: function() {
            const menuToggle = document.getElementById('menuToggle');
            const sideMenu = document.getElementById('sideMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    sideMenu?.classList.add('active');
                    document.body.style.overflow = 'hidden';
                });
            }
            
            if (menuOverlay) {
                menuOverlay.addEventListener('click', () => {
                    sideMenu?.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
            
            // إغلاق القائمة عند النقر على رابط
            sideMenu?.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    sideMenu.classList.remove('active');
                    document.body.style.overflow = '';
                });
            });
        }
    };

    // =====================================================
    // Logout
    // =====================================================

    window.logout = async function() {
        if (!confirm('هل أنت متأكد من تسجيل الخروج؟')) {
            return;
        }
        
        try {
            const data = await apiRequest('logout', { method: 'POST' });
            
            if (data.status === 'success') {
                showToast('تم تسجيل الخروج بنجاح', 'success');
                setTimeout(() => {
                    window.location.href = APP.baseUrl + 'index.php';
                }, 1000);
            }
        } catch (error) {
            showToast(error.message, 'error');
        }
    };

    // =====================================================
    // Initialize on DOM Ready
    // =====================================================
// تعريف sideMenu لو مش موجود
// تعريف sideMenu لو مش موجود
if (typeof window.sideMenu === 'undefined') {
    window.sideMenu = {
        init: function() {
            const menuToggle = document.getElementById('menuToggle');
            const sideMenu = document.getElementById('sideMenu');
            const menuOverlay = document.getElementById('menuOverlay');
            
            console.log('SideMenu Debug:', { menuToggle, sideMenu, menuOverlay });
            
            if (menuToggle) {
                menuToggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Menu toggle clicked!');
                    if (sideMenu) {
                        sideMenu.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                });
            }
            
            if (menuOverlay) {
                menuOverlay.addEventListener('click', () => {
                    console.log('Overlay clicked!');
                    if (sideMenu) {
                        sideMenu.classList.remove('active');
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // إغلاق القائمة عند النقر على أي رابط
            if (sideMenu) {
                sideMenu.querySelectorAll('a').forEach(link => {
                    link.addEventListener('click', () => {
                        sideMenu.classList.remove('active');
                        document.body.style.overflow = '';
                    });
                });
            }
        }
    };
}
    document.addEventListener('DOMContentLoaded', function() {
        
        // تهيئة القائمة الجانبية
        if (typeof sideMenu !== 'undefined' && sideMenu.init) {
    sideMenu.init();
}
        
        // تهيئة نجوم التقييم
        ratings.initStars();
        
        // إغلاق المودالات
        document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
            el.addEventListener('click', function() {
                this.closest('.modal')?.classList.remove('active');
                document.body.style.overflow = '';
            });
        });
        
        // زر تسجيل الخروج
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', logout);
        }
        
        // أزرار إضافة للمفضلة
        document.querySelectorAll('[data-favorite]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const type = this.dataset.favoriteType || 'vendor';
                const id = this.dataset.favoriteId || this.dataset.vendorId || this.dataset.productId;
                
                if (id) {
                    favorites.toggle(type, id, this);
                }
            });
        });
        
        // أزرار الإضافة للسلة
        document.querySelectorAll('[data-add-to-cart]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const productId = this.dataset.productId || this.dataset.addToCart;
                const quantity = parseInt(this.dataset.quantity || 1);
                
                if (productId) {
                    cart.addItem(productId, quantity);
                }
            });
        });
        
        // استرجاع الموقع المحفوظ
        const savedLocation = locationManager.getSavedLocation();
        if (savedLocation) {
            const displayEl = document.getElementById('selectedLocation');
            if (displayEl) {
                displayEl.textContent = savedLocation.display || savedLocation.area || 'تم تحديد الموقع';
            }
        }
        
        // جلب عدد الإشعارات غير المقروءة
        if (APP.isLoggedIn) {
            notifications.fetch().then(() => {
                notifications.updateBadge();
            });
        }
        
        // تأثيرات التمرير
        initScrollEffects();
    });

    // =====================================================
    // Scroll Effects
    // =====================================================

    function initScrollEffects() {
        const header = document.querySelector('.main-header');
        
        if (header) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    header.classList.add('scrolled');
                } else {
                    header.classList.remove('scrolled');
                }
            });
        }
        
        // أزرار الرجوع للأعلى
        const scrollTopBtn = document.getElementById('scrollTopBtn');
        if (scrollTopBtn) {
            window.addEventListener('scroll', () => {
                if (window.scrollY > 300) {
                    scrollTopBtn.classList.add('visible');
                } else {
                    scrollTopBtn.classList.remove('visible');
                }
            });
            
            scrollTopBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }
    }

    // =====================================================
    // CSS Animations
    // =====================================================

    const style = document.createElement('style');
    style.textContent = `
        @keyframes cartBounce {
            0%, 100% { transform: scale(1); }
            25% { transform: scale(1.3); }
            50% { transform: scale(0.9); }
            75% { transform: scale(1.1); }
        }
        
        @keyframes heartBeat {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }
        
        .toast-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }
        
        .toast {
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border-right: 4px solid;
            position: relative;
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast-success { border-right-color: #10B981; }
        .toast-success i { color: #10B981; }
        
        .toast-error { border-right-color: #EF4444; }
        .toast-error i { color: #EF4444; }
        
        .toast-warning { border-right-color: #F59E0B; }
        .toast-warning i { color: #F59E0B; }
        
        .toast-info { border-right-color: #3B82F6; }
        .toast-info i { color: #3B82F6; }
        
        .toast span {
            flex: 1;
            color: #1F2937;
            font-size: 14px;
            font-weight: 500;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: #9CA3AF;
            font-size: 20px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.2s;
        }
        
        .toast-close:hover {
            background: #F3F4F6;
            color: #1F2937;
        }
        
        .global-loader {
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: inherit;
        }
        
        .main-header.scrolled {
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .error-message {
            color: #EF4444;
            font-size: 12px;
            margin-top: 4px;
            display: block;
        }
        
        input.error, select.error, textarea.error {
            border-color: #EF4444 !important;
        }
        
        input.error:focus, select.error:focus, textarea.error:focus {
            box-shadow: 0 0 0 3px rgba(239,68,68,0.1) !important;
        }
    `;
    document.head.appendChild(style);

})();