<?php
/**
 * لوحة تحكم المندوب - Live Edition
 * Saree3 - تطبيق توصيل ومارت
 * @version 2.0
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once INCLUDES_PATH . '/auth.php';
require_once INCLUDES_PATH . '/helpers.php';

if (!is_driver()) {
    redirect(BASE_URL . 'login.php?type=driver');
}

$driver = get_current_driver();
$section = isset($_GET['section']) ? clean_input($_GET['section']) : 'dashboard';
$allowed_sections = ['dashboard', 'orders', 'profile'];
if (!in_array($section, $allowed_sections)) $section = 'dashboard';

// =====================================================
// AJAX Polling Endpoint
// =====================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'poll') {
    header('Content-Type: application/json');
    
    $available_orders = db_fetch_all(
        "SELECT o.id, o.order_number, o.total, o.created_at,
                v.business_name as vendor_name, v.address as vendor_address,
                u.name as user_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count,
                a.address as delivery_address, a.latitude, a.longitude
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.id
         JOIN users u ON o.user_id = u.id
         JOIN addresses a ON o.address_id = a.id
         WHERE o.driver_id IS NULL 
         AND o.status IN ('new', 'ready')
         AND v.zone_id = ?
         ORDER BY o.created_at ASC
         LIMIT 20",
        [$driver['zone_id']]
    );

    $current_order = db_fetch(
        "SELECT o.id, o.order_number, o.total, o.status,
                v.business_name as vendor_name, v.address as vendor_address,
                u.name as user_name, u.phone as user_phone,
                a.address as delivery_address,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.id
         JOIN users u ON o.user_id = u.id
         JOIN addresses a ON o.address_id = a.id
         WHERE o.driver_id = ? AND o.status IN ('accepted','preparing','ready','on_way')
         ORDER BY o.created_at ASC LIMIT 1",
        [$driver['id']]
    );

    $today_stats = db_fetch(
        "SELECT COUNT(*) as total_deliveries, SUM(total) as total_earnings
         FROM orders WHERE driver_id = ? AND status = 'delivered' AND DATE(delivered_at) = CURDATE()",
        [$driver['id']]
    );

    // Distance calculation for each order
    if ($available_orders) {
        foreach ($available_orders as &$order) {
            $order['distance'] = round(calculate_distance(
                $driver['latitude'] ?? 0, $driver['longitude'] ?? 0,
                $order['latitude'] ?? 0, $order['longitude'] ?? 0
            ), 1);
        }
        unset($order);
    }

    echo json_encode([
        'available_orders' => $available_orders ?: [],
        'current_order' => $current_order ?: null,
        'today_stats' => $today_stats ?: ['total_deliveries' => 0, 'total_earnings' => 0],
        'driver_online' => (bool)$driver['is_online'],
        'driver_busy' => (bool)$driver['is_busy'],
        'timestamp' => time()
    ]);
    exit;
}

// =====================================================
// POST Handler
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleDriverPost();
}

$page_title = 'لوحة المندوب - ' . APP_NAME;

function renderDriverStatusBadge($status) {
    $badges = [
        'new'       => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'جديد',          'icon' => 'fa-circle-dot'],
        'accepted'  => ['bg' => '#EDE9FE', 'color' => '#7C3AED', 'text' => 'تم القبول',     'icon' => 'fa-circle-check'],
        'preparing' => ['bg' => '#FEF3C7', 'color' => '#D97706', 'text' => 'قيد التحضير',  'icon' => 'fa-fire-burner'],
        'ready'     => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'جاهز',          'icon' => 'fa-bag-shopping'],
        'on_way'    => ['bg' => '#DBEAFE', 'color' => '#2563EB', 'text' => 'في الطريق',     'icon' => 'fa-motorcycle'],
        'delivered' => ['bg' => '#D1FAE5', 'color' => '#059669', 'text' => 'تم التوصيل',   'icon' => 'fa-circle-check'],
        'cancelled' => ['bg' => '#FEE2E2', 'color' => '#DC2626', 'text' => 'ملغي',          'icon' => 'fa-circle-xmark'],
    ];
    $b = $badges[$status] ?? ['bg' => '#F3F4F6', 'color' => '#6B7280', 'text' => $status, 'icon' => 'fa-circle'];
    return "<span class='status-badge' style='background:{$b['bg']};color:{$b['color']};'><i class='fas {$b['icon']}'></i> {$b['text']}</span>";
}

function handleDriverPost() {
    global $driver;
    $action = $_POST['action'] ?? '';
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        redirect_with_message(BASE_URL . 'driver/index.php', 'رمز الحماية غير صالح، حدّث الصفحة وحاول مرة أخرى', 'error');
    }

    switch ($action) {
        case 'accept_order':
            $order_id = (int)($_POST['order_id'] ?? 0);
            // Transaction-safe accept
            $pdo = get_pdo(); // assumes helper exists
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND driver_id IS NULL AND status IN ('new','ready') FOR UPDATE");
                $stmt->execute([$order_id]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$order) {
                    $pdo->rollBack();
                    redirect_with_message(BASE_URL . 'driver/index.php', 'الطلب لم يعد متاحاً', 'error');
                }
                db_update('orders', ['driver_id' => $driver['id'], 'status' => 'accepted'], 'id = ?', [':id' => $order_id]);
                db_update('drivers', ['is_busy' => 1], 'id = ?', [':id' => $driver['id']]);
                db_insert('order_status_logs', [
                    'order_id' => $order_id, 'status' => 'accepted',
                    'changed_by' => 'driver', 'changed_by_id' => $driver['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                redirect_with_message(BASE_URL . 'driver/index.php', 'حدث خطأ، حاول مجدداً', 'error');
            }
            send_notification($order['user_id'], 'user', 'تم قبول طلبك', "المندوب {$driver['name']} في طريقه لاستلام طلبك", 'order', $order_id);
            redirect_with_message(BASE_URL . 'driver/index.php', 'تم قبول الطلب بنجاح', 'success');
            break;

        case 'update_order_status':
            $order_id = (int)($_POST['order_id'] ?? 0);
            $status   = $_POST['status'] ?? '';
            $allowed_statuses = ['on_way', 'delivered'];
            if (!in_array($status, $allowed_statuses)) break;

            $order = db_fetch("SELECT * FROM orders WHERE id = ? AND driver_id = ?", [$order_id, $driver['id']]);
            if (!$order) redirect_with_message(BASE_URL . 'driver/index.php?section=orders', 'الطلب غير موجود', 'error');

            $update_data = ['status' => $status];
            if ($status === 'delivered') {
                $update_data['delivered_at'] = date('Y-m-d H:i:s');
                db_update('drivers', ['is_busy' => 0], 'id = ?', [':id' => $driver['id']]);
            }
            db_update('orders', $update_data, 'id = ?', [':id' => $order_id]);
            db_insert('order_status_logs', [
                'order_id' => $order_id, 'status' => $status,
                'changed_by' => 'driver', 'changed_by_id' => $driver['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            if ($status === 'on_way') {
                send_notification($order['user_id'], 'user', 'طلبك في الطريق', "المندوب {$driver['name']} في طريقه إليك", 'order', $order_id);
            }
            redirect_with_message(BASE_URL . 'driver/index.php?section=orders', 'تم تحديث حالة الطلب', 'success');
            break;

        case 'toggle_online':
            $is_online = (int)($_POST['is_online'] ?? 0);
            db_update('drivers', ['is_online' => $is_online], 'id = ?', [':id' => $driver['id']]);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'is_online' => $is_online]);
                exit;
            }
            redirect_with_message(BASE_URL . 'driver/index.php', $is_online ? 'أنت متصل الآن' : 'أنت غير متصل', 'success');
            break;

        case 'update_location':
            $lat = (float)($_POST['latitude'] ?? 0);
            $lng = (float)($_POST['longitude'] ?? 0);
            db_update('drivers', [
                'latitude' => $lat, 'longitude' => $lng,
                'last_location_update' => date('Y-m-d H:i:s')
            ], 'id = ?', [':id' => $driver['id']]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;

        case 'update_profile':
            $name           = trim($_POST['name'] ?? '');
            $vehicle_type   = $_POST['vehicle_type'] ?? 'motorcycle';
            $vehicle_number = trim($_POST['vehicle_number'] ?? '');
            $data = ['name' => $name, 'vehicle_type' => $vehicle_type, 'vehicle_number' => $vehicle_number];
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $avatar_path = upload_image($_FILES['avatar'], 'drivers');
                if ($avatar_path) $data['avatar'] = $avatar_path;
            }
            db_update('drivers', $data, 'id = ?', [':id' => $driver['id']]);
            $_SESSION['driver_name'] = $name;
            redirect_with_message(BASE_URL . 'driver/index.php?section=profile', 'تم تحديث الملف الشخصي', 'success');
            break;
    }
}

// Fetch data for non-dashboard sections
if ($section === 'orders') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $total_orders = db_fetch("SELECT COUNT(*) as count FROM orders WHERE driver_id = ? AND status = 'delivered'", [$driver['id']])['count'] ?? 0;
    $orders = db_fetch_all(
        "SELECT o.*, v.business_name as vendor_name, u.name as user_name,
                (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
         FROM orders o JOIN vendors v ON o.vendor_id = v.id JOIN users u ON o.user_id = u.id
         WHERE o.driver_id = ? AND o.status = 'delivered'
         ORDER BY o.delivered_at DESC LIMIT ? OFFSET ?",
        [$driver['id'], $limit, $offset]
    );
    $total_pages = ceil($total_orders / $limit);
}

if ($section === 'profile') {
    $total_stats = db_fetch(
        "SELECT COUNT(*) as total_deliveries, SUM(o.total) as total_earnings,
                AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
         FROM orders o LEFT JOIN ratings r ON o.driver_id = r.driver_id AND r.rated_for = 'driver'
         WHERE o.driver_id = ? AND o.status = 'delivered'",
        [$driver['id']]
    );
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($page_title) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --blue:       #1D4ED8;
            --blue-light: #3B82F6;
            --blue-ghost: #EFF6FF;
            --blue-dim:   #DBEAFE;
            --green:      #059669;
            --green-dim:  #D1FAE5;
            --amber:      #D97706;
            --amber-dim:  #FEF3C7;
            --red:        #DC2626;
            --red-dim:    #FEE2E2;
            --surface:    #FFFFFF;
            --surface-2:  #F8FAFC;
            --border:     #E2E8F0;
            --text:       #0F172A;
            --text-2:     #475569;
            --text-3:     #94A3B8;
            --shadow-sm:  0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md:  0 4px 16px rgba(0,0,0,.08);
            --shadow-lg:  0 12px 40px rgba(0,0,0,.12);
            --r-sm:       10px;
            --r-md:       16px;
            --r-lg:       24px;
            --r-xl:       32px;
        }

        * { margin:0; padding:0; box-sizing:border-box; -webkit-tap-highlight-color:transparent; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            background: var(--surface-2);
            color: var(--text);
            direction: rtl;
            padding-bottom: 90px;
            min-height: 100vh;
        }

        /* ── HEADER ── */
        .header {
            background: linear-gradient(160deg, #1e40af 0%, #1D4ED8 55%, #2563EB 100%);
            padding: 52px 20px 28px;
            position: relative;
            overflow: hidden;
        }
        .header::before {
            content: '';
            position: absolute;
            top: -60px; left: -60px;
            width: 240px; height: 240px;
            border-radius: 50%;
            background: rgba(255,255,255,.06);
        }
        .header::after {
            content: '';
            position: absolute;
            bottom: -80px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: rgba(255,255,255,.04);
        }

        .header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative; z-index: 1;
        }

        .driver-identity { display: flex; align-items: center; gap: 14px; }

        .avatar-wrap {
            position: relative;
            width: 52px; height: 52px;
        }
        .avatar-img {
            width: 52px; height: 52px;
            border-radius: 16px;
            object-fit: cover;
            border: 2.5px solid rgba(255,255,255,.35);
            background: rgba(255,255,255,.15);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 22px;
            overflow: hidden;
        }
        .avatar-status-ring {
            position: absolute;
            bottom: -3px; left: -3px;
            width: 16px; height: 16px;
            border-radius: 50%;
            border: 2.5px solid white;
            background: #94A3B8;
            transition: background .3s;
        }
        .avatar-status-ring.online { background: #10B981; }

        .driver-name {
            color: white;
            font-size: 17px;
            font-weight: 700;
            letter-spacing: -.01em;
        }
        .driver-sub {
            color: rgba(255,255,255,.7);
            font-size: 12.5px;
            margin-top: 2px;
        }

        .header-actions { display: flex; gap: 8px; position: relative; z-index: 1; }

        .icon-btn {
            width: 42px; height: 42px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 13px;
            color: white;
            font-size: 17px;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .2s;
            backdrop-filter: blur(8px);
        }
        .icon-btn:hover { background: rgba(255,255,255,.25); }

        /* Online Toggle pill */
        .online-pill {
            position: relative; z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 50px;
            padding: 8px 16px;
            backdrop-filter: blur(8px);
            cursor: pointer;
            transition: all .25s;
            user-select: none;
        }
        .online-pill:hover { background: rgba(255,255,255,.2); }

        .pill-label { color: rgba(255,255,255,.9); font-size: 13.5px; font-weight: 600; }

        .toggle {
            width: 44px; height: 24px;
            background: rgba(255,255,255,.25);
            border-radius: 12px;
            position: relative;
            transition: background .3s;
        }
        .toggle.on { background: #10B981; }
        .toggle::after {
            content: '';
            position: absolute;
            width: 18px; height: 18px;
            background: white;
            border-radius: 50%;
            top: 3px; right: 3px;
            transition: right .3s;
            box-shadow: 0 1px 4px rgba(0,0,0,.2);
        }
        .toggle.on::after { right: 23px; }

        .pill-status { color: rgba(255,255,255,.7); font-size: 12px; }

        /* ── STATS STRIP ── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 0 16px;
            margin-top: -1px;
            transform: translateY(-24px);
        }

        .stat-tile {
            background: var(--surface);
            border-radius: var(--r-md);
            padding: 14px 12px;
            box-shadow: var(--shadow-md);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .stat-icon-wrap {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
        }
        .stat-val { font-size: 20px; font-weight: 800; color: var(--text); line-height: 1; }
        .stat-lbl { font-size: 11px; color: var(--text-3); font-weight: 500; }

        /* ── BOTTOM NAV ── */
        .bottom-nav {
            position: fixed;
            bottom: 0; left: 0; right: 0;
            background: var(--surface);
            display: flex;
            padding: 10px 20px 16px;
            box-shadow: 0 -1px 0 var(--border), 0 -8px 24px rgba(0,0,0,.06);
            border-radius: 24px 24px 0 0;
            z-index: 100;
        }
        .nav-item {
            flex: 1;
            display: flex; flex-direction: column;
            align-items: center; gap: 4px;
            padding: 8px 6px;
            color: var(--text-3);
            text-decoration: none;
            font-size: 11px; font-weight: 600;
            border-radius: 12px;
            transition: all .2s;
            position: relative;
        }
        .nav-item i { font-size: 20px; transition: transform .2s; }
        .nav-item.active { color: var(--blue); }
        .nav-item.active i { transform: scale(1.1); }
        .nav-item.active::before {
            content: '';
            position: absolute;
            top: 0; left: 50%; transform: translateX(-50%);
            width: 28px; height: 3px;
            background: var(--blue);
            border-radius: 0 0 4px 4px;
        }

        /* ── CONTENT ── */
        .content { padding: 0 16px 16px; }

        .sec-header {
            display: flex; align-items: center;
            margin-bottom: 14px;
            margin-top: 8px;
        }
        .sec-header h3 {
            font-size: 16px; font-weight: 700; color: var(--text);
            display: flex; align-items: center; gap: 8px;
        }
        .sec-header h3 i { color: var(--blue); font-size: 15px; }
        .count-badge {
            margin-right: auto;
            background: var(--blue-dim);
            color: var(--blue);
            font-size: 11px; font-weight: 700;
            padding: 3px 10px;
            border-radius: 20px;
        }

        /* ── ORDER CARD ── */
        .order-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            padding: 18px;
            margin-bottom: 12px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid var(--border);
            transition: transform .15s, box-shadow .15s;
            animation: slideUp .3s ease both;
        }
        .order-card:active { transform: scale(.99); }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .order-card.current-order {
            border-color: var(--blue);
            background: linear-gradient(135deg, #EFF6FF 0%, #F8FAFF 100%);
            box-shadow: 0 4px 24px rgba(29,78,216,.12);
        }

        /* New order pulse animation */
        .order-card.new-order {
            animation: newOrderPulse .6s ease both;
        }
        @keyframes newOrderPulse {
            0%   { opacity:0; transform: scale(.96) translateY(8px); box-shadow: 0 0 0 0 rgba(29,78,216,.3); }
            60%  { box-shadow: 0 0 0 8px rgba(29,78,216,0); }
            100% { opacity:1; transform: scale(1) translateY(0); }
        }

        .card-top {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 14px;
        }
        .order-num {
            font-size: 15px; font-weight: 800; color: var(--blue);
            display: flex; align-items: center; gap: 6px;
        }
        .order-num::before {
            content: '#';
            font-size: 12px;
            opacity: .6;
        }
        .dist-tag {
            display: flex; align-items: center; gap: 5px;
            font-size: 12.5px; color: var(--text-3); font-weight: 500;
            background: var(--surface-2);
            padding: 4px 10px; border-radius: 20px;
        }

        .route-line {
            position: relative;
            padding-right: 28px;
            margin-bottom: 14px;
        }
        .route-line::before {
            content: '';
            position: absolute;
            right: 11px; top: 22px;
            width: 1.5px;
            height: calc(100% - 32px);
            background: var(--border);
        }
        .route-stop {
            display: flex; align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
        }
        .route-stop:last-child { margin-bottom: 0; }

        .stop-dot {
            width: 22px; height: 22px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
            margin-right: -28px;
        }
        .stop-dot.pickup  { background: var(--green);   color: white; }
        .stop-dot.dropoff { background: var(--red);     color: white; }

        .stop-info { font-size: 13.5px; color: var(--text); line-height: 1.4; }
        .stop-info strong { font-weight: 700; display: block; }
        .stop-info small  { color: var(--text-3); font-size: 12px; }

        .card-meta {
            display: flex; gap: 12px;
            padding: 12px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .meta-item {
            display: flex; align-items: center; gap: 6px;
            font-size: 13px; color: var(--text-2);
        }
        .meta-item i { color: var(--text-3); font-size: 13px; }
        .price-tag { font-weight: 800; font-size: 15px; color: var(--text); }

        .card-actions { display: flex; gap: 8px; }

        /* ── BUTTONS ── */
        .btn {
            padding: 13px 18px;
            border-radius: var(--r-sm);
            font-family: inherit;
            font-weight: 700; font-size: 14px;
            border: none; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 7px;
            transition: all .18s;
            text-decoration: none;
        }
        .btn:active { transform: scale(.97); }

        .btn-primary { background: var(--blue); color: white; flex: 1; }
        .btn-primary:hover { background: #1e3fa8; }

        .btn-success { background: var(--green); color: white; flex: 1; }
        .btn-success:hover { background: #047857; }

        .btn-ghost {
            background: var(--surface-2);
            color: var(--text-2);
            border: 1.5px solid var(--border);
        }
        .btn-ghost:hover { background: var(--border); }

        .btn-block { width: 100%; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }

        .btn-call {
            width: 44px; height: 44px; padding: 0;
            background: var(--green-dim);
            color: var(--green);
            border-radius: 12px;
            flex-shrink: 0;
        }

        /* ── STATUS BADGE ── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px; font-weight: 700;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 56px 20px;
        }
        .empty-icon {
            width: 80px; height: 80px;
            background: var(--blue-dim);
            border-radius: 24px;
            display: flex; align-items: center; justify-content: center;
            font-size: 36px; color: var(--blue);
            margin: 0 auto 20px;
        }
        .empty-state h3 { font-size: 16px; color: var(--text-2); margin-bottom: 6px; }
        .empty-state p  { font-size: 13.5px; color: var(--text-3); }

        /* ── PROFILE ── */
        .profile-card {
            background: var(--surface);
            border-radius: var(--r-lg);
            padding: 20px;
            margin-bottom: 14px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid var(--border);
        }
        .profile-card h4 {
            font-size: 14px; font-weight: 700; color: var(--text-2);
            margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px;
            text-transform: uppercase; letter-spacing: .03em;
        }
        .profile-card h4 i { color: var(--blue); }

        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-2); margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 13px 15px;
            border: 1.5px solid var(--border);
            border-radius: var(--r-sm);
            font-family: inherit; font-size: 14px;
            background: var(--surface-2);
            color: var(--text);
            transition: border-color .2s, box-shadow .2s;
        }
        .form-control:focus { outline: none; border-color: var(--blue); box-shadow: 0 0 0 3px rgba(29,78,216,.1); background: white; }
        .form-control:disabled { opacity: .55; cursor: not-allowed; }

        .avatar-row {
            display: flex; align-items: center; gap: 16px;
        }
        .avatar-box {
            width: 72px; height: 72px;
            border: 2px dashed var(--border);
            border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; background: var(--surface-2);
            flex-shrink: 0;
        }
        .avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-box i { font-size: 28px; color: var(--text-3); }
        .avatar-upload-btn {
            padding: 10px 16px;
            background: var(--blue-ghost);
            color: var(--blue);
            border: 1.5px solid var(--blue-dim);
            border-radius: 10px;
            font-size: 13px; font-weight: 600; cursor: pointer;
            font-family: inherit;
        }

        .stats-grid {
            display: grid; grid-template-columns: repeat(3,1fr); gap: 12px;
            text-align: center;
        }
        .stats-grid-item .val {
            font-size: 26px; font-weight: 800; line-height: 1;
            margin-bottom: 4px;
        }
        .stats-grid-item .lbl { font-size: 11.5px; color: var(--text-3); }

        /* ── HISTORY CARD ── */
        .history-card {
            background: var(--surface);
            border-radius: var(--r-md);
            padding: 16px;
            margin-bottom: 10px;
            box-shadow: var(--shadow-sm);
            border: 1.5px solid var(--border);
            display: flex; align-items: center; gap: 14px;
        }
        .history-icon {
            width: 44px; height: 44px;
            border-radius: 13px;
            background: var(--green-dim);
            color: var(--green);
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; flex-shrink: 0;
        }
        .history-info { flex: 1; }
        .history-num  { font-weight: 700; font-size: 15px; margin-bottom: 3px; }
        .history-meta { font-size: 12.5px; color: var(--text-3); }
        .history-right { text-align: left; }
        .history-price { font-weight: 800; font-size: 15px; color: var(--text); }
        .history-time  { font-size: 11.5px; color: var(--text-3); margin-top: 2px; }
        .tip-tag {
            display: inline-flex; align-items: center; gap: 4px;
            background: var(--green-dim); color: var(--green);
            font-size: 11px; font-weight: 700;
            padding: 2px 8px; border-radius: 20px; margin-top: 4px;
        }

        /* ── PAGINATION ── */
        .pagination {
            display: flex; justify-content: center; gap: 6px; margin-top: 20px;
        }
        .page-btn {
            width: 38px; height: 38px;
            display: flex; align-items: center; justify-content: center;
            background: var(--surface); border: 1.5px solid var(--border);
            border-radius: 10px; color: var(--text-2); text-decoration: none;
            font-size: 14px; font-weight: 600;
            transition: all .15s;
        }
        .page-btn.active { background: var(--blue); border-color: var(--blue); color: white; }
        .page-btn:hover:not(.active) { background: var(--surface-2); }

        /* ── LIVE INDICATOR ── */
        .live-dot {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 11.5px; color: var(--green); font-weight: 700;
            margin-right: auto;
        }
        .live-dot::before {
            content: '';
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: livePulse 1.4s infinite;
        }
        @keyframes livePulse {
            0%,100% { opacity:1; transform: scale(1); }
            50%      { opacity:.4; transform: scale(.8); }
        }

        /* ── TOAST ── */
        .toasts {
            position: fixed; top: 16px; left: 16px; right: 16px;
            z-index: 9999;
            display: flex; flex-direction: column; gap: 8px;
            pointer-events: none;
        }
        .toast {
            background: var(--surface);
            border-radius: var(--r-md);
            padding: 14px 18px;
            box-shadow: var(--shadow-lg);
            display: flex; align-items: center; gap: 12px;
            transform: translateY(-110%); opacity: 0;
            transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .3s;
            pointer-events: auto;
            border-right: 4px solid var(--blue);
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast-success { border-right-color: var(--green); }
        .toast-success .toast-icon { color: var(--green); }
        .toast-error   { border-right-color: var(--red); }
        .toast-error   .toast-icon { color: var(--red); }
        .toast-info    { border-right-color: var(--amber); }
        .toast-info    .toast-icon { color: var(--amber); }
        .toast-icon { font-size: 20px; }
        .toast span { flex:1; font-weight:600; font-size: 14px; }

        /* ── NEW ORDER NOTIFICATION ── */
        .new-order-banner {
            position: fixed;
            top: 0; left: 0; right: 0;
            background: linear-gradient(135deg, var(--blue) 0%, #1e40af 100%);
            color: white;
            padding: 16px 20px;
            z-index: 200;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 4px 24px rgba(29,78,216,.4);
            transform: translateY(-100%);
            transition: transform .4s cubic-bezier(.34,1.56,.64,1);
        }
        .new-order-banner.show { transform: translateY(0); }
        .banner-icon {
            width: 40px; height: 40px;
            background: rgba(255,255,255,.2);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            animation: shake .5s .2s both;
        }
        @keyframes shake {
            0%,100% { transform: rotate(0); }
            25%      { transform: rotate(-8deg); }
            75%      { transform: rotate(8deg); }
        }
        .banner-text { flex:1; }
        .banner-text strong { display: block; font-size: 15px; margin-bottom: 2px; }
        .banner-text span   { font-size: 12.5px; opacity: .85; }
        .banner-dismiss {
            width: 32px; height: 32px;
            background: rgba(255,255,255,.15);
            border: none; border-radius: 8px;
            color: white; font-size: 14px; cursor: pointer;
        }

        /* Skeleton loader */
        .skeleton {
            background: linear-gradient(90deg, var(--border) 25%, #e8edf2 50%, var(--border) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.3s infinite;
            border-radius: 8px;
        }
        @keyframes shimmer {
            0%   { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Offline overlay */
        .offline-overlay {
            position: absolute;
            inset: 0;
            background: rgba(248,250,252,.85);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            gap: 12px;
            border-radius: inherit;
            backdrop-filter: blur(2px);
            z-index: 5;
        }
        .offline-overlay i { font-size: 32px; color: var(--text-3); }
        .offline-overlay span { font-size: 14px; color: var(--text-2); font-weight: 600; }
    </style>
</head>
<body>

<!-- Header -->
<div class="header">
    <div class="header-row">
        <div class="driver-identity">
            <div class="avatar-wrap">
                <div class="avatar-img">
                    <?php if (!empty($driver['avatar'])): ?>
                    <img src="<?= BASE_URL . htmlspecialchars($driver['avatar']) ?>" alt="">
                    <?php else: ?>
                    <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <div class="avatar-status-ring <?= $driver['is_online'] ? 'online' : '' ?>" id="statusRing"></div>
            </div>
            <div>
                <div class="driver-name"><?= htmlspecialchars($driver['name']) ?></div>
                <div class="driver-sub">
                    <?php if (!empty($driver['vehicle_type'])): ?>
                    <i class="fas fa-motorcycle"></i>
                    <?= htmlspecialchars($driver['vehicle_number'] ?? '') ?>
                    <?php else: ?>
                    مندوب التوصيل
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="header-actions">
            <button class="icon-btn" onclick="doLogout()" title="تسجيل الخروج">
                <i class="fas fa-sign-out-alt"></i>
            </button>
        </div>
    </div>

    <div class="online-pill" id="onlinePill" onclick="toggleOnline()">
        <span class="pill-label">الحالة</span>
        <div class="toggle <?= $driver['is_online'] ? 'on' : '' ?>" id="toggleEl"></div>
        <span class="pill-status" id="pillStatus"><?= $driver['is_online'] ? 'متصل' : 'غير متصل' ?></span>
    </div>
</div>

<!-- Stats (dashboard only) -->
<?php if ($section === 'dashboard'): ?>
<div class="stats-strip" id="statsStrip">
    <?php
    $today_stats = db_fetch("SELECT COUNT(*) as total_deliveries, SUM(total) as total_earnings FROM orders WHERE driver_id = ? AND status='delivered' AND DATE(delivered_at)=CURDATE()", [$driver['id']]);
    $total_stats = db_fetch("SELECT AVG(r.rating) as avg_rating FROM orders o LEFT JOIN ratings r ON o.driver_id=r.driver_id AND r.rated_for='driver' WHERE o.driver_id=? AND o.status='delivered'", [$driver['id']]);
    ?>
    <div class="stat-tile">
        <div class="stat-icon-wrap" style="background:var(--blue-dim);color:var(--blue);">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-val" id="statDeliveries"><?= (int)($today_stats['total_deliveries'] ?? 0) ?></div>
        <div class="stat-lbl">توصيلات اليوم</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon-wrap" style="background:var(--green-dim);color:var(--green);">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-val" id="statEarnings"><?= number_format($today_stats['total_earnings'] ?? 0, 0) ?></div>
        <div class="stat-lbl">أرباح اليوم</div>
    </div>
    <div class="stat-tile">
        <div class="stat-icon-wrap" style="background:var(--amber-dim);color:var(--amber);">
            <i class="fas fa-star"></i>
        </div>
        <div class="stat-val"><?= number_format($total_stats['avg_rating'] ?? 0, 1) ?></div>
        <div class="stat-lbl">التقييم</div>
    </div>
</div>
<?php else: ?>
<div style="height:14px;"></div>
<?php endif; ?>

<!-- Content -->
<div class="content">

<?php if ($section === 'dashboard'): ?>

    <!-- Current Order -->
    <div id="currentOrderSection"></div>

    <!-- Available Orders -->
    <div class="sec-header">
        <h3><i class="fas fa-bell"></i> طلبات متاحة</h3>
        <div class="live-dot" id="liveDot">مباشر</div>
        <span class="count-badge" id="availableCount" style="display:none"></span>
    </div>
    <div id="availableOrdersContainer">
        <!-- Skeleton placeholders -->
        <?php for($i=0;$i<2;$i++): ?>
        <div class="order-card" style="padding:20px;">
            <div class="skeleton" style="height:18px;width:120px;margin-bottom:14px;"></div>
            <div class="skeleton" style="height:14px;width:80%;margin-bottom:8px;"></div>
            <div class="skeleton" style="height:14px;width:60%;margin-bottom:14px;"></div>
            <div class="skeleton" style="height:44px;border-radius:10px;"></div>
        </div>
        <?php endfor; ?>
    </div>

<?php elseif ($section === 'orders'): ?>

    <div class="sec-header">
        <h3><i class="fas fa-history"></i> سجل التوصيلات</h3>
        <span style="margin-right:auto;font-size:13px;color:var(--text-3);"><?= (int)$total_orders ?> طلب</span>
    </div>

    <?php if (empty($orders)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-receipt"></i></div>
        <h3>لا توجد توصيلات سابقة</h3>
        <p>ستظهر هنا سجلات طلباتك المكتملة</p>
    </div>
    <?php else: ?>
    <?php foreach ($orders as $ord): ?>
    <div class="history-card">
        <div class="history-icon"><i class="fas fa-check-circle"></i></div>
        <div class="history-info">
            <div class="history-num">#<?= htmlspecialchars($ord['order_number']) ?></div>
            <div class="history-meta"><?= htmlspecialchars($ord['vendor_name']) ?> · <?= (int)$ord['items_count'] ?> منتجات</div>
            <?php if (!empty($ord['tip']) && $ord['tip'] > 0): ?>
            <span class="tip-tag"><i class="fas fa-heart"></i> إكرامية +<?= number_format($ord['tip'],0) ?></span>
            <?php endif; ?>
        </div>
        <div class="history-right">
            <div class="history-price"><?= number_format($ord['total'],0) ?></div>
            <div class="history-time"><?= time_ago($ord['delivered_at'] ?? $ord['created_at']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?section=orders&page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

<?php elseif ($section === 'profile'): ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">

        <div class="profile-card">
            <h4><i class="fas fa-user-circle"></i> المعلومات الشخصية</h4>
            <div class="form-group">
                <label>الصورة الشخصية</label>
                <div class="avatar-row">
                    <div class="avatar-box">
                        <?php if (!empty($driver['avatar'])): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($driver['avatar']) ?>" alt="">
                        <?php else: ?>
                        <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <label class="avatar-upload-btn">
                        <i class="fas fa-camera"></i> تغيير الصورة
                        <input type="file" name="avatar" accept="image/*" style="display:none;">
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label>الاسم الكامل</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($driver['name']) ?>" required>
            </div>
            <div class="form-group">
                <label>رقم الجوال</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(format_phone_display($driver['phone'])) ?>" disabled>
            </div>
        </div>

        <div class="profile-card">
            <h4><i class="fas fa-motorcycle"></i> معلومات المركبة</h4>
            <div class="form-group">
                <label>نوع المركبة</label>
                <select name="vehicle_type" class="form-control">
                    <option value="motorcycle" <?= ($driver['vehicle_type']??'')=='motorcycle'?'selected':'' ?>>🏍️ دراجة نارية</option>
                    <option value="car"        <?= ($driver['vehicle_type']??'')=='car'       ?'selected':'' ?>>🚗 سيارة</option>
                    <option value="bicycle"    <?= ($driver['vehicle_type']??'')=='bicycle'   ?'selected':'' ?>>🚲 دراجة هوائية</option>
                </select>
            </div>
            <div class="form-group">
                <label>رقم المركبة / اللوحة</label>
                <input type="text" name="vehicle_number" class="form-control" value="<?= htmlspecialchars($driver['vehicle_number'] ?? '') ?>">
            </div>
        </div>

        <div class="profile-card">
            <h4><i class="fas fa-chart-bar"></i> إحصائياتك الكاملة</h4>
            <div class="stats-grid">
                <div class="stats-grid-item">
                    <div class="val" style="color:var(--blue);"><?= (int)($total_stats['total_deliveries']??0) ?></div>
                    <div class="lbl">توصيلة</div>
                </div>
                <div class="stats-grid-item">
                    <div class="val" style="color:var(--green);"><?= number_format($total_stats['total_earnings']??0,0) ?></div>
                    <div class="lbl">إجمالي الأرباح</div>
                </div>
                <div class="stats-grid-item">
                    <div class="val" style="color:var(--amber);"><?= number_format($total_stats['avg_rating']??0,1) ?></div>
                    <div class="lbl">تقييم</div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:4px;">
            <i class="fas fa-save"></i> حفظ التغييرات
        </button>
    </form>

<?php endif; ?>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
    <a href="?section=dashboard" class="nav-item <?= $section==='dashboard'?'active':'' ?>">
        <i class="fas fa-home"></i><span>الرئيسية</span>
    </a>
    <a href="?section=orders" class="nav-item <?= $section==='orders'?'active':'' ?>">
        <i class="fas fa-history"></i><span>الطلبات</span>
    </a>
    <a href="?section=profile" class="nav-item <?= $section==='profile'?'active':'' ?>">
        <i class="fas fa-user"></i><span>حسابي</span>
    </a>
</nav>

<!-- Toasts -->
<div class="toasts" id="toastContainer"></div>

<!-- New Order Banner -->
<div class="new-order-banner" id="newOrderBanner">
    <div class="banner-icon"><i class="fas fa-bell"></i></div>
    <div class="banner-text">
        <strong>طلب جديد!</strong>
        <span id="bannerText">وصل طلب جديد في منطقتك</span>
    </div>
    <button class="banner-dismiss" onclick="dismissBanner()"><i class="fas fa-times"></i></button>
</div>

<script>
const BASE_URL   = '<?= BASE_URL ?>';
const CSRF_TOKEN = '<?= get_csrf_token() ?>';
const SECTION    = '<?= $section ?>';
const IS_ONLINE  = <?= $driver['is_online'] ? 'true' : 'false' ?>;
const IS_BUSY    = <?= $driver['is_busy']   ? 'true' : 'false' ?>;
const DRIVER_ID  = <?= (int)$driver['id'] ?>;
const FORMAT_CURRENCY = (v) => Math.round(v).toLocaleString('ar-SA');

let locationWatchId  = null;
let pollInterval     = null;
let knownOrderIds    = new Set();
let bannerTimeout    = null;
let isOnline         = IS_ONLINE;
let isBusy           = IS_BUSY;

// ── INIT ──────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    if (SECTION === 'dashboard') {
        fetchDashboard(true); // initial load
        startPolling();
    }
    if (isOnline) startLocationTracking();
});

// ── POLLING ───────────────────────────────────────────
function startPolling() {
    clearInterval(pollInterval);
    pollInterval = setInterval(() => fetchDashboard(false), 8000); // every 8s
}

async function fetchDashboard(isFirstLoad) {
    try {
        const res  = await fetch(BASE_URL + 'driver/index.php?ajax=poll&_=' + Date.now());
        const data = await res.json();

        renderCurrentOrder(data.current_order);
        renderAvailableOrders(data.available_orders, data.driver_busy, isFirstLoad);
        updateStats(data.today_stats);
        syncOnlineState(data.driver_online);

    } catch(e) {
        if (!isFirstLoad) return; // silent fail on background polls
    }
}

// ── RENDER CURRENT ORDER ──────────────────────────────
function renderCurrentOrder(order) {
    const wrap = document.getElementById('currentOrderSection');
    if (!order) { wrap.innerHTML = ''; return; }

    const statusMap = {
        accepted:  { bg:'#EDE9FE', c:'#7C3AED', t:'تم القبول',    i:'fa-circle-check' },
        preparing: { bg:'#FEF3C7', c:'#D97706', t:'قيد التحضير',  i:'fa-fire-burner'  },
        ready:     { bg:'#D1FAE5', c:'#059669', t:'جاهز',          i:'fa-bag-shopping' },
        on_way:    { bg:'#DBEAFE', c:'#2563EB', t:'في الطريق',     i:'fa-motorcycle'   },
    };
    const s = statusMap[order.status] || { bg:'#F3F4F6', c:'#6B7280', t:order.status, i:'fa-circle' };

    let actionBtn = '';
    if (order.status === 'accepted' || order.status === 'ready') {
        actionBtn = `
        <form method="POST" style="flex:1">
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" value="${order.id}">
            <button type="submit" name="status" value="on_way" class="btn btn-primary btn-block">
                <i class="fas fa-check"></i> استلمت الطلب
            </button>
        </form>`;
    } else if (order.status === 'on_way') {
        actionBtn = `
        <form method="POST" style="flex:1">
            <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
            <input type="hidden" name="action" value="update_order_status">
            <input type="hidden" name="order_id" value="${order.id}">
            <button type="submit" name="status" value="delivered" class="btn btn-success btn-block">
                <i class="fas fa-check-circle"></i> تم التوصيل
            </button>
        </form>`;
    }

    wrap.innerHTML = `
    <div class="sec-header"><h3><i class="fas fa-clock"></i> طلبك الحالي</h3></div>
    <div class="order-card current-order">
        <div class="card-top">
            <span class="order-num">${escHtml(order.order_number)}</span>
            <span class="status-badge" style="background:${s.bg};color:${s.c};">
                <i class="fas ${s.i}"></i> ${s.t}
            </span>
        </div>
        <div class="route-line">
            <div class="route-stop">
                <div class="stop-dot pickup"><i class="fas fa-store"></i></div>
                <div class="stop-info">
                    <strong>${escHtml(order.vendor_name)}</strong>
                    <small>${escHtml(order.vendor_address)}</small>
                </div>
            </div>
            <div class="route-stop">
                <div class="stop-dot dropoff"><i class="fas fa-user"></i></div>
                <div class="stop-info">
                    <strong>${escHtml(order.user_name)}</strong>
                    <small>${escHtml(order.delivery_address)}</small>
                </div>
            </div>
        </div>
        <div class="card-meta">
            <div class="meta-item"><i class="fas fa-shopping-cart"></i> ${order.items_count} منتجات</div>
            <div class="meta-item"><i class="fas fa-tag"></i> <span class="price-tag">${FORMAT_CURRENCY(order.total)}</span></div>
        </div>
        <div class="card-actions">
            <a href="tel:${escHtml(order.user_phone)}" class="btn btn-call"><i class="fas fa-phone"></i></a>
            ${actionBtn}
        </div>
    </div>`;
}

// ── RENDER AVAILABLE ORDERS ───────────────────────────
function renderAvailableOrders(orders, driverBusy, isFirstLoad) {
    const container = document.getElementById('availableOrdersContainer');
    const countEl   = document.getElementById('availableCount');

    if (!orders || orders.length === 0) {
        container.innerHTML = `
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-inbox"></i></div>
            <h3>لا توجد طلبات متاحة</h3>
            <p>ستظهر هنا الطلبات الجديدة في منطقتك</p>
        </div>`;
        countEl.style.display = 'none';
        return;
    }

    countEl.textContent    = orders.length;
    countEl.style.display  = 'inline-flex';

    // detect new orders
    const newIds = new Set(orders.map(o => o.id));
    if (!isFirstLoad) {
        orders.forEach(o => {
            if (!knownOrderIds.has(o.id)) {
                notifyNewOrder(o);
            }
        });
    }
    knownOrderIds = newIds;

    const canAccept = isOnline && !driverBusy;

    container.innerHTML = orders.map((o, idx) => `
    <div class="order-card ${!isFirstLoad && !knownOrderIds.has(o.id) ? 'new-order' : ''}"
         style="animation-delay:${isFirstLoad ? idx * 0.06 : 0}s">
        <div class="card-top">
            <span class="order-num">${escHtml(o.order_number)}</span>
            <span class="dist-tag"><i class="fas fa-location-dot"></i> ${o.distance} كم</span>
        </div>
        <div class="route-line">
            <div class="route-stop">
                <div class="stop-dot pickup"><i class="fas fa-store"></i></div>
                <div class="stop-info">
                    <strong>${escHtml(o.vendor_name)}</strong>
                    <small>${escHtml(o.vendor_address ?? '').substring(0,45)}</small>
                </div>
            </div>
            <div class="route-stop">
                <div class="stop-dot dropoff"><i class="fas fa-user"></i></div>
                <div class="stop-info">
                    <strong>${escHtml(o.user_name)}</strong>
                    <small>${escHtml(o.delivery_address ?? '').substring(0,45)}</small>
                </div>
            </div>
        </div>
        <div class="card-meta">
            <div class="meta-item"><i class="fas fa-shopping-cart"></i> ${o.items_count} منتجات</div>
            <div class="meta-item"><i class="fas fa-tag"></i> <span class="price-tag">${FORMAT_CURRENCY(o.total)}</span></div>
        </div>
        <div class="card-actions">
            ${canAccept
                ? `<form method="POST" style="flex:1">
                    <input type="hidden" name="csrf_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="action" value="accept_order">
                    <input type="hidden" name="order_id" value="${o.id}">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-check"></i> قبول الطلب
                    </button>
                   </form>`
                : `<button class="btn btn-primary btn-block" disabled>
                    <i class="fas fa-lock"></i> غير متاح
                   </button>`
            }
        </div>
    </div>`).join('');
}

// ── UPDATE STATS ──────────────────────────────────────
function updateStats(stats) {
    if (!stats) return;
    const dEl = document.getElementById('statDeliveries');
    const eEl = document.getElementById('statEarnings');
    if (dEl) dEl.textContent = stats.total_deliveries ?? 0;
    if (eEl) eEl.textContent = FORMAT_CURRENCY(stats.total_earnings ?? 0);
}

// ── NEW ORDER NOTIFICATION ────────────────────────────
function notifyNewOrder(order) {
    // Banner
    const banner = document.getElementById('newOrderBanner');
    document.getElementById('bannerText').textContent =
        `طلب #${order.order_number} · ${order.distance} كم · ${FORMAT_CURRENCY(order.total)}`;
    banner.classList.add('show');
    clearTimeout(bannerTimeout);
    bannerTimeout = setTimeout(() => banner.classList.remove('show'), 6000);

    // Vibrate
    if (navigator.vibrate) navigator.vibrate([150, 80, 150]);

    // Sound (simple beep)
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1100, ctx.currentTime + 0.1);
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.4);
    } catch(e) {}
}

function dismissBanner() {
    document.getElementById('newOrderBanner').classList.remove('show');
    clearTimeout(bannerTimeout);
}

// ── SYNC ONLINE STATE ──────────────────────────────────
function syncOnlineState(online) {
    isOnline = online;
    const ring   = document.getElementById('statusRing');
    const toggle = document.getElementById('toggleEl');
    const status = document.getElementById('pillStatus');
    if (ring)   ring.className   = 'avatar-status-ring' + (online ? ' online' : '');
    if (toggle) toggle.className = 'toggle' + (online ? ' on' : '');
    if (status) status.textContent = online ? 'متصل' : 'غير متصل';
}

// ── TOGGLE ONLINE ──────────────────────────────────────
async function toggleOnline() {
    const newStatus = isOnline ? 0 : 1;
    const fd = new FormData();
    fd.append('action', 'toggle_online');
    fd.append('is_online', newStatus);
    fd.append('csrf_token', CSRF_TOKEN);

    try {
        const res  = await fetch(BASE_URL + 'driver/index.php', {
            method: 'POST', body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        syncOnlineState(!!data.is_online);
        isOnline = !!data.is_online;
        showToast(isOnline ? 'أنت متصل الآن ✓' : 'أنت غير متصل', isOnline ? 'success' : 'info');
        if (isOnline)  startLocationTracking();
        else           stopLocationTracking();
    } catch(e) {
        showToast('حدث خطأ، أعد المحاولة', 'error');
    }
}

// ── LOCATION TRACKING ─────────────────────────────────
function startLocationTracking() {
    if (!navigator.geolocation || locationWatchId !== null) return;
    locationWatchId = navigator.geolocation.watchPosition(
        pos => sendLocation(pos.coords.latitude, pos.coords.longitude),
        err => console.warn('GPS:', err.message),
        { enableHighAccuracy: true, maximumAge: 20000, timeout: 10000 }
    );
}

function stopLocationTracking() {
    if (locationWatchId !== null) {
        navigator.geolocation.clearWatch(locationWatchId);
        locationWatchId = null;
    }
}

function sendLocation(lat, lng) {
    const fd = new FormData();
    fd.append('action', 'update_location');
    fd.append('latitude', lat);
    fd.append('longitude', lng);
    fd.append('csrf_token', CSRF_TOKEN);
    fetch(BASE_URL + 'driver/index.php', { method: 'POST', body: fd });
}

// ── LOGOUT ────────────────────────────────────────────
async function doLogout() {
    if (!confirm('هل أنت متأكد من تسجيل الخروج؟')) return;
    stopLocationTracking();
    clearInterval(pollInterval);
    // Set offline
    const fd = new FormData();
    fd.append('action','toggle_online'); fd.append('is_online','0'); fd.append('csrf_token',CSRF_TOKEN);
    try { await fetch(BASE_URL+'driver/index.php',{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest'}}); } catch(e){}
    // Logout
    try {
        await fetch(BASE_URL+'api/handler.php?action=logout',{method:'POST',headers:{'X-CSRF-Token':CSRF_TOKEN}});
    } catch(e){}
    window.location.href = BASE_URL + 'login.php';
}

// ── UTILS ─────────────────────────────────────────────
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showToast(msg, type = 'success') {
    const icons = { success:'fa-check-circle', error:'fa-times-circle', info:'fa-info-circle' };
    const wrap  = document.createElement('div');
    wrap.className = `toast toast-${type}`;
    wrap.innerHTML = `<i class="fas ${icons[type]||icons.success} toast-icon"></i><span>${escHtml(msg)}</span>`;
    document.getElementById('toastContainer').appendChild(wrap);
    requestAnimationFrame(() => { requestAnimationFrame(() => wrap.classList.add('show')); });
    setTimeout(() => { wrap.classList.remove('show'); setTimeout(() => wrap.remove(), 350); }, 3200);
}

// Flash from PHP
<?php $flash = show_flash_message(); if ($flash): ?>
showToast(<?= json_encode(strip_tags($flash)) ?>, 'success');
<?php endif; ?>
</script>
</body>
</html>
