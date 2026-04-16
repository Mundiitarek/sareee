/**
 * جافاسكريبت لوحات التحكم
 * Saree3 - Admin, Vendor, Driver Panels
 * @version 1.0
 */

(function() {
    'use strict';

    // =====================================================
    // Global Variables
    // =====================================================
    window.PANEL = {
        baseUrl: BASE_URL || '/',
        csrfToken: CSRF_TOKEN || '',
        currentSection: new URLSearchParams(window.location.search).get('section') || 'dashboard',
        sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true'
    };

    // =====================================================
    // Panel Initialization
    // =====================================================
    
    const Panel = {
        init: function() {
            this.initSidebar();
            this.initMobileMenu();
            this.initNotifications();
            this.initModals();
            this.initTabs();
            this.initForms();
            this.initDataTables();
            this.initCharts();
            this.initFilters();
            this.initBulkActions();
        },

        // =====================================================
        // Sidebar Management
        // =====================================================
        
        initSidebar: function() {
            const wrapper = document.querySelector('.panel-wrapper');
            const toggleBtn = document.getElementById('sidebarToggle');
            
            if (PANEL.sidebarCollapsed) {
                wrapper?.classList.add('sidebar-collapsed');
            }
            
            if (toggleBtn) {
                toggleBtn.addEventListener('click', () => {
                    wrapper?.classList.toggle('sidebar-collapsed');
                    const isCollapsed = wrapper?.classList.contains('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', isCollapsed);
                });
            }
        },

        initMobileMenu: function() {
            const menuToggle = document.getElementById('mobileMenuToggle');
            const wrapper = document.querySelector('.panel-wrapper');
            const overlay = document.getElementById('sidebarOverlay');
            
            if (menuToggle) {
                menuToggle.addEventListener('click', () => {
                    wrapper?.classList.add('sidebar-mobile-open');
                });
            }
            
            if (overlay) {
                overlay.addEventListener('click', () => {
                    wrapper?.classList.remove('sidebar-mobile-open');
                });
            }
            
            // إغلاق القائمة عند النقر على رابط
            document.querySelectorAll('.panel-sidebar .nav-item').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        wrapper?.classList.remove('sidebar-mobile-open');
                    }
                });
            });
        },

        // =====================================================
        // Notifications
        // =====================================================
        
        initNotifications: function() {
            const notifBtn = document.getElementById('notificationBtn');
            const notifDropdown = document.getElementById('notificationDropdown');
            
            if (notifBtn && notifDropdown) {
                notifBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    notifDropdown.classList.toggle('active');
                    this.fetchNotifications();
                });
                
                document.addEventListener('click', () => {
                    notifDropdown.classList.remove('active');
                });
            }
        },

        fetchNotifications: function() {
            const container = document.getElementById('notificationList');
            if (!container) return;
            
            fetch(PANEL.baseUrl + 'api/handler.php?action=get_notifications')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        this.renderNotifications(data.data.notifications, container);
                        this.updateNotificationBadge(data.data.unread_count);
                    }
                });
        },

        renderNotifications: function(notifications, container) {
            if (!notifications || notifications.length === 0) {
                container.innerHTML = '<div class="notification-empty">لا توجد إشعارات</div>';
                return;
            }
            
            container.innerHTML = notifications.slice(0, 10).map(n => `
                <div class="notification-item ${n.is_read ? '' : 'unread'}" data-id="${n.id}">
                    <div class="notification-icon">
                        <i class="fas fa-${this.getNotificationIcon(n.type)}"></i>
                    </div>
                    <div class="notification-content">
                        <p class="notification-title">${this.escapeHtml(n.title_ar)}</p>
                        <p class="notification-body">${this.escapeHtml(n.body_ar)}</p>
                        <span class="notification-time">${this.timeAgo(n.created_at)}</span>
                    </div>
                </div>
            `).join('');
            
            container.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', () => {
                    const id = item.dataset.id;
                    this.markNotificationRead(id);
                    item.classList.remove('unread');
                });
            });
        },

        markNotificationRead: function(id) {
            fetch(PANEL.baseUrl + 'api/handler.php?action=mark_notification_read', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ notification_id: id })
            });
        },

        updateNotificationBadge: function(count) {
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                badge.textContent = count;
                badge.style.display = count > 0 ? 'flex' : 'none';
            }
        },

        // =====================================================
        // Modals
        // =====================================================
        
        initModals: function() {
            // فتح المودال
            document.querySelectorAll('[data-modal]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modalId = btn.dataset.modal;
                    this.openModal(modalId);
                    
                    // تحميل بيانات إضافية إذا وجدت
                    const loadUrl = btn.dataset.load;
                    if (loadUrl) {
                        this.loadModalContent(modalId, loadUrl);
                    }
                    
                    // تعيين عنوان المودال
                    const title = btn.dataset.title;
                    if (title) {
                        const modalTitle = document.querySelector(`#${modalId} .panel-modal-header h3`);
                        if (modalTitle) modalTitle.textContent = title;
                    }
                });
            });
            
            // إغلاق المودال
            document.querySelectorAll('.panel-modal-close, .panel-modal-overlay').forEach(el => {
                el.addEventListener('click', (e) => {
                    const modal = el.closest('.panel-modal');
                    if (modal) this.closeModal(modal.id);
                });
            });
            
            // إغلاق بـ ESC
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    document.querySelectorAll('.panel-modal.active').forEach(modal => {
                        this.closeModal(modal.id);
                    });
                }
            });
        },

        openModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        },

        closeModal: function(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        },

        loadModalContent: function(modalId, url) {
            const modalBody = document.querySelector(`#${modalId} .panel-modal-body`);
            if (!modalBody) return;
            
            modalBody.innerHTML = '<div class="loading-spinner"></div>';
            
            fetch(url)
                .then(res => res.text())
                .then(html => {
                    modalBody.innerHTML = html;
                    Panel.initForms();
                })
                .catch(() => {
                    modalBody.innerHTML = '<p class="error-message">فشل تحميل المحتوى</p>';
                });
        },

        // =====================================================
        // Tabs
        // =====================================================
        
        initTabs: function() {
            document.querySelectorAll('.panel-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabGroup = tab.closest('.panel-tabs');
                    const targetId = tab.dataset.tab;
                    
                    // إزالة التفعيل من جميع التبويبات
                    tabGroup?.querySelectorAll('.panel-tab').forEach(t => {
                        t.classList.remove('active');
                    });
                    
                    // إخفاء جميع المحتويات
                    const contentContainer = tab.closest('.panel-card') || document;
                    contentContainer.querySelectorAll('.panel-tab-content').forEach(c => {
                        c.classList.remove('active');
                    });
                    
                    // تفعيل التبويب والمحتوى المحدد
                    tab.classList.add('active');
                    const targetContent = document.getElementById(targetId);
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                });
            });
        },

        // =====================================================
        // Forms
        // =====================================================
        
        initForms: function() {
            // تقديم النماذج عبر AJAX
            document.querySelectorAll('[data-ajax-form]').forEach(form => {
                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.submitAjaxForm(form);
                });
            });
            
            // معاينة الصور قبل الرفع
            document.querySelectorAll('.upload-preview input[type="file"]').forEach(input => {
                input.addEventListener('change', (e) => {
                    this.previewImage(e.target);
                });
            });
            
            // البحث في الجداول
            document.querySelectorAll('[data-table-search]').forEach(input => {
                input.addEventListener('keyup', (e) => {
                    this.searchTable(e.target);
                });
            });
            
            // تأكيد الحذف
            document.querySelectorAll('[data-confirm]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const message = btn.dataset.confirm || 'هل أنت متأكد؟';
                    if (!confirm(message)) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });
            });
        },

        submitAjaxForm: function(form) {
            const formData = new FormData(form);
            const submitBtn = form.querySelector('[type="submit"]');
            const action = form.dataset.action || form.getAttribute('action');
            const method = form.method || 'POST';
            
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري...';
            }
            
            fetch(action, {
                method: method,
                body: formData,
                headers: {
                    'X-CSRF-Token': PANEL.csrfToken
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showToast(data.message || 'تمت العملية بنجاح', 'success');
                    
                    if (form.dataset.reload === 'true') {
                        setTimeout(() => location.reload(), 1000);
                    }
                    
                    if (form.dataset.closeModal) {
                        const modal = form.closest('.panel-modal');
                        if (modal) this.closeModal(modal.id);
                    }
                    
                    if (form.dataset.reset === 'true') {
                        form.reset();
                    }
                } else {
                    this.showToast(data.message || 'حدث خطأ', 'error');
                }
            })
            .catch(() => {
                this.showToast('حدث خطأ في الاتصال', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = submitBtn.dataset.originalText || 'حفظ';
                }
            });
        },

        previewImage: function(input) {
            const preview = input.closest('.upload-preview');
            const img = preview?.querySelector('img');
            const icon = preview?.querySelector('i');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (img) {
                        img.src = e.target.result;
                        img.style.display = 'block';
                    }
                    if (icon) icon.style.display = 'none';
                };
                reader.readAsDataURL(input.files[0]);
            }
        },

        // =====================================================
        // Data Tables
        // =====================================================
        
        initDataTables: function() {
            // تحديد الكل
            document.querySelectorAll('[data-select-all]').forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    const table = checkbox.closest('table');
                    const targetName = checkbox.dataset.selectAll;
                    table?.querySelectorAll(`input[name="${targetName}"]`).forEach(cb => {
                        cb.checked = checkbox.checked;
                    });
                    this.updateBulkActions();
                });
            });
            
            // تحديث أزرار bulk actions
            document.querySelectorAll('input[data-bulk-item]').forEach(cb => {
                cb.addEventListener('change', () => this.updateBulkActions());
            });
        },

        searchTable: function(input) {
            const tableId = input.dataset.tableSearch;
            const table = document.getElementById(tableId);
            const searchTerm = input.value.toLowerCase();
            
            table?.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        },

        updateBulkActions: function() {
            const checked = document.querySelectorAll('input[data-bulk-item]:checked');
            const bulkBar = document.getElementById('bulkActionsBar');
            const countSpan = document.getElementById('selectedCount');
            
            if (bulkBar) {
                if (checked.length > 0) {
                    bulkBar.style.display = 'flex';
                    if (countSpan) countSpan.textContent = checked.length;
                } else {
                    bulkBar.style.display = 'none';
                }
            }
        },

        // =====================================================
        // Charts
        // =====================================================
        
        initCharts: function() {
            this.renderStatsChart();
            this.renderOrdersChart();
        },

        renderStatsChart: function() {
            const canvas = document.getElementById('statsChart');
            if (!canvas) return;
            
            // سيتم استخدام Chart.js إذا تم تضمينه
            if (typeof Chart !== 'undefined') {
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: canvas.dataset.labels?.split(',') || [],
                        datasets: [{
                            label: 'الطلبات',
                            data: canvas.dataset.orders?.split(',').map(Number) || [],
                            borderColor: '#FF6B35',
                            backgroundColor: 'rgba(255, 107, 53, 0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        }
                    }
                });
            }
        },

        renderOrdersChart: function() {
            const canvas = document.getElementById('ordersChart');
            if (!canvas || typeof Chart === 'undefined') return;
            
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: ['مكتمل', 'قيد التوصيل', 'جديد', 'ملغي'],
                    datasets: [{
                        data: canvas.dataset.values?.split(',').map(Number) || [0, 0, 0, 0],
                        backgroundColor: ['#10B981', '#FF6B35', '#3B82F6', '#EF4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        },

        // =====================================================
        // Filters
        // =====================================================
        
        initFilters: function() {
            document.querySelectorAll('[data-filter]').forEach(filter => {
                filter.addEventListener('change', () => {
                    this.applyFilters();
                });
            });
        },

        applyFilters: function() {
            const filters = {};
            document.querySelectorAll('[data-filter]').forEach(filter => {
                filters[filter.name] = filter.value;
            });
            
            const url = new URL(window.location);
            Object.keys(filters).forEach(key => {
                if (filters[key]) {
                    url.searchParams.set(key, filters[key]);
                } else {
                    url.searchParams.delete(key);
                }
            });
            
            window.location.href = url.toString();
        },

        // =====================================================
        // Bulk Actions
        // =====================================================
        
        initBulkActions: function() {
            document.querySelectorAll('[data-bulk-action]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const action = btn.dataset.bulkAction;
                    const items = document.querySelectorAll('input[data-bulk-item]:checked');
                    
                    if (items.length === 0) {
                        this.showToast('الرجاء تحديد عناصر أولاً', 'warning');
                        return;
                    }
                    
                    this.executeBulkAction(action, items);
                });
            });
        },

        executeBulkAction: function(action, items) {
            const ids = Array.from(items).map(cb => cb.value);
            const confirmMsg = `هل أنت متأكد من تنفيذ هذا الإجراء على ${ids.length} عناصر؟`;
            
            if (!confirm(confirmMsg)) return;
            
            fetch(PANEL.baseUrl + 'api/handler.php?action=bulk_' + action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ ids: ids })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    this.showToast(data.message, 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    this.showToast(data.message || 'حدث خطأ', 'error');
                }
            });
        },

        // =====================================================
        // Utility Functions
        // =====================================================
        
        showToast: function(message, type = 'info') {
            let container = document.getElementById('panelToastContainer');
            
            if (!container) {
                container = document.createElement('div');
                container.id = 'panelToastContainer';
                container.className = 'panel-toast-container';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = `panel-toast panel-toast-${type}`;
            
            let icon = 'info-circle';
            if (type === 'success') icon = 'check-circle';
            else if (type === 'error') icon = 'times-circle';
            else if (type === 'warning') icon = 'exclamation-triangle';
            
            toast.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${this.escapeHtml(message)}</span>
                <button class="panel-toast-close">&times;</button>
            `;
            
            container.appendChild(toast);
            
            const closeBtn = toast.querySelector('.panel-toast-close');
            closeBtn.addEventListener('click', () => {
                toast.remove();
            });
            
            setTimeout(() => toast.classList.add('show'), 10);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        timeAgo: function(datetime) {
            const timestamp = new Date(datetime).getTime();
            const now = Date.now();
            const diff = Math.floor((now - timestamp) / 1000);
            
            if (diff < 60) return 'منذ لحظات';
            if (diff < 3600) return `منذ ${Math.floor(diff / 60)} دقيقة`;
            if (diff < 86400) return `منذ ${Math.floor(diff / 3600)} ساعة`;
            if (diff < 2592000) return `منذ ${Math.floor(diff / 86400)} يوم`;
            if (diff < 31536000) return `منذ ${Math.floor(diff / 2592000)} شهر`;
            return `منذ ${Math.floor(diff / 31536000)} سنة`;
        },

        getNotificationIcon: function(type) {
            const icons = {
                'order': 'shopping-bag',
                'promo': 'tag',
                'system': 'cog',
                'rating': 'star',
                'report': 'flag'
            };
            return icons[type] || 'bell';
        },

        formatPrice: function(price) {
            return parseFloat(price).toFixed(2) + ' ر.س';
        },

        formatDate: function(date) {
            return new Date(date).toLocaleDateString('ar-SA', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
    };

    // =====================================================
    // Order Management (Specific to Vendor/Driver)
    // =====================================================
    
    window.OrderManager = {
        updateStatus: function(orderId, status) {
            if (!confirm(`هل أنت متأكد من تغيير حالة الطلب إلى "${this.getStatusText(status)}"؟`)) {
                return;
            }
            
            fetch(PANEL.baseUrl + 'api/handler.php?action=update_order_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ order_id: orderId, status: status })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Panel.showToast('تم تحديث حالة الطلب', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    Panel.showToast(data.message || 'حدث خطأ', 'error');
                }
            });
        },
        
        acceptOrder: function(orderId) {
            fetch(PANEL.baseUrl + 'api/handler.php?action=accept_order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ order_id: orderId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Panel.showToast('تم قبول الطلب بنجاح', 'success');
                    setTimeout(() => location.reload(), 500);
                } else {
                    Panel.showToast(data.message || 'حدث خطأ', 'error');
                }
            });
        },
        
        getStatusText: function(status) {
            const statuses = {
                'new': 'جديد',
                'accepted': 'تم القبول',
                'preparing': 'قيد التحضير',
                'on_way': 'خرج للتوصيل',
                'delivered': 'تم التوصيل',
                'cancelled': 'ملغي'
            };
            return statuses[status] || status;
        }
    };

    // =====================================================
    // Driver Location Tracking
    // =====================================================
    
    window.DriverTracker = {
        watchId: null,
        
        startTracking: function() {
            if (!navigator.geolocation) {
                Panel.showToast('متصفحك لا يدعم تحديد الموقع', 'error');
                return;
            }
            
            this.watchId = navigator.geolocation.watchPosition(
                (position) => {
                    this.updateLocation(position.coords.latitude, position.coords.longitude);
                },
                (error) => {
                    console.error('Location error:', error);
                },
                { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 }
            );
            
            Panel.showToast('تم بدء تتبع الموقع', 'success');
        },
        
        stopTracking: function() {
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
                Panel.showToast('تم إيقاف تتبع الموقع', 'info');
            }
        },
        
        updateLocation: function(lat, lng) {
            fetch(PANEL.baseUrl + 'api/handler.php?action=update_driver_location', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ latitude: lat, longitude: lng })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    document.getElementById('locationStatus')?.classList.add('online');
                }
            });
        },
        
        toggleOnline: function(isOnline) {
            fetch(PANEL.baseUrl + 'api/handler.php?action=toggle_driver_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': PANEL.csrfToken
                },
                body: JSON.stringify({ is_online: isOnline })
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Panel.showToast(isOnline ? 'أنت متصل الآن' : 'أنت غير متصل', 'success');
                    
                    if (isOnline) {
                        DriverTracker.startTracking();
                    } else {
                        DriverTracker.stopTracking();
                    }
                }
            });
        }
    };

    // =====================================================
    // Initialize on DOM Ready
    // =====================================================
    
    document.addEventListener('DOMContentLoaded', () => {
        Panel.init();
        
        // تصدير الدوال للاستخدام العام
        window.Panel = Panel;
    });

    // =====================================================
    // Panel Toast Styles
    // =====================================================
    
    const style = document.createElement('style');
    style.textContent = `
        .panel-toast-container {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-width: 350px;
        }
        
        .panel-toast {
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
        }
        
        .panel-toast.show {
            transform: translateX(0);
        }
        
        .panel-toast-success { border-right-color: #10B981; }
        .panel-toast-success i { color: #10B981; }
        
        .panel-toast-error { border-right-color: #EF4444; }
        .panel-toast-error i { color: #EF4444; }
        
        .panel-toast-warning { border-right-color: #F59E0B; }
        .panel-toast-warning i { color: #F59E0B; }
        
        .panel-toast-info { border-right-color: #3B82F6; }
        .panel-toast-info i { color: #3B82F6; }
        
        .panel-toast span {
            flex: 1;
            color: #1F2937;
            font-size: 14px;
            font-weight: 500;
        }
        
        .panel-toast-close {
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
        
        .panel-toast-close:hover {
            background: #F3F4F6;
            color: #1F2937;
        }
        
        .notification-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 320px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            margin-top: 10px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 100;
        }
        
        .notification-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .notification-item {
            display: flex;
            gap: 12px;
            padding: 16px;
            border-bottom: 1px solid #F3F4F6;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .notification-item:hover {
            background: #F9FAFB;
        }
        
        .notification-item.unread {
            background: #FFF1EB;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            background: #FFF1EB;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FF6B35;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .notification-body {
            font-size: 13px;
            color: #6B7280;
            margin-bottom: 4px;
        }
        
        .notification-time {
            font-size: 11px;
            color: #9CA3AF;
        }
        
        .notification-empty {
            padding: 24px;
            text-align: center;
            color: #9CA3AF;
        }
        
        #bulkActionsBar {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: white;
            border-radius: 50px;
            padding: 12px 24px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: none;
            align-items: center;
            gap: 20px;
            z-index: 500;
        }
    `;
    document.head.appendChild(style);

})();