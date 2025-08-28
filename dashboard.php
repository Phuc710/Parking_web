<?php
// dashboard.php - User dashboard
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user = get_user($_SESSION['user_id']);

$tab = $_GET['tab'] ?? 'overview';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_profile':
            $email = $_POST['email'] ?? '';
            $full_name = $_POST['full_name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if (update_user_profile($_SESSION['user_id'], $email, $full_name, $phone)) {
                set_flash_message('success', 'Cập nhật thông tin thành công!');
            } else {
                set_flash_message('error', 'Có lỗi xảy ra khi cập nhật thông tin!');
            }
            redirect('dashboard.php?tab=profile');
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                set_flash_message('error', 'Mật khẩu xác nhận không khớp!');
                redirect('dashboard.php?tab=profile');
                break;
            }
            
            $result = change_user_password($_SESSION['user_id'], $current_password, $new_password);
            
            if ($result['success']) {
                set_flash_message('success', $result['message']);
            } else {
                set_flash_message('error', $result['message']);
            }
            redirect('dashboard.php?tab=profile');
            break;
            
        case 'create_booking':
            $slot_id = $_POST['slot_id'] ?? '';
            $license_plate = $_POST['license_plate'] ?? '';
            $start_date = $_POST['start_date'] ?? '';
            $start_time = $_POST['start_time'] ?? '';
            $duration = intval($_POST['duration'] ?? 1);
            
            // Validate required fields
            if (empty($slot_id) || empty($license_plate) || empty($start_date) || empty($start_time)) {
                set_flash_message('error', 'Vui lòng điền đầy đủ thông tin!');
                redirect('dashboard.php?tab=booking');
                break;
            }
            
            // Validate license plate format (basic)
            if (!preg_match('/^[0-9A-Z\-\.]{6,12}$/i', $license_plate)) {
                set_flash_message('error', 'Định dạng biển số xe không hợp lệ!');
                redirect('dashboard.php?tab=booking');
                break;
            }
            
            // Validate duration
            if ($duration < 1 || $duration > 24) {
                set_flash_message('error', 'Thời gian đỗ phải từ 1 đến 24 giờ!');
                redirect('dashboard.php?tab=booking');
                break;
            }
            
            try {
                // Create start and end datetime
                $start_datetime = new DateTime("$start_date $start_time");
                $end_datetime = clone $start_datetime;
                $end_datetime->add(new DateInterval("PT{$duration}H"));
                
                // Check if start time is not in the past
                $now = new DateTime();
                if ($start_datetime < $now) {
                    set_flash_message('error', 'Thời gian đặt chỗ không được trong quá khứ!');
                    redirect('dashboard.php?tab=booking');
                    break;
                }
                
                // Format for database
                $start_time_db = $start_datetime->format('Y-m-d H:i:s');
                $end_time_db = $end_datetime->format('Y-m-d H:i:s');
                
                // Create booking
                $result = create_booking($_SESSION['user_id'], $slot_id, $license_plate, $start_time_db, $end_time_db);
                
                if ($result['success']) {
                    set_flash_message('success', 'Đặt chỗ thành công! Vui lòng thanh toán để hoàn tất.');
                    redirect('dashboard.php?tab=payment&ref=' . $result['payment_ref']);
                } else {
                    set_flash_message('error', $result['message']);
                    redirect('dashboard.php?tab=booking');
                }
                
            } catch (Exception $e) {
                error_log("Booking form error: " . $e->getMessage());
                set_flash_message('error', 'Lỗi xử lý thời gian. Vui lòng kiểm tra lại!');
                redirect('dashboard.php?tab=booking');
            }
            break;
            
        case 'cancel_booking':
            $booking_id = $_POST['booking_id'] ?? '';
            
            if (empty($booking_id)) {
                set_flash_message('error', 'Booking ID không hợp lệ!');
                redirect('dashboard.php?tab=bookings');
                break;
            }
            
            $result = cancel_booking($booking_id, $_SESSION['user_id']);
            
            if ($result['success']) {
                set_flash_message('success', $result['message']);
            } else {
                set_flash_message('error', $result['message']);
            }
            redirect('dashboard.php?tab=bookings');
            break;
    }
}

switch ($tab) {
    case 'bookings':
        $bookings = get_user_bookings($_SESSION['user_id']);
        break;
        
    case 'booking':
        $available_slots = get_available_slots();
        break;
        
    case 'payment':
        $payment_ref = $_GET['ref'] ?? ''; // Lấy mã tham chiếu từ URL
        $qr_data = null; // Khởi tạo biến để tránh lỗi

        if ($payment_ref) {
            // Tìm payment_id từ payment_ref để truyền vào hàm generate_payment_qr
            try {
                $stmt = $pdo->prepare("SELECT id FROM payments WHERE payment_ref = ?");
                $stmt->execute([$payment_ref]);
                $payment = $stmt->fetch();
                
                if ($payment) {
                    $payment_id = $payment['id'];
                    $qr_data = generate_payment_qr($payment_id); // Gọi hàm với payment_id đã tìm thấy
                } else {
                    set_flash_message('error', 'Không tìm thấy thanh toán với mã tham chiếu này!');
                    redirect('dashboard.php?tab=bookings');
                }
            } catch (PDOException $e) {
                set_flash_message('error', 'Lỗi truy vấn cơ sở dữ liệu!');
                redirect('dashboard.php?tab=bookings');
            }
        } else {
            set_flash_message('error', 'Thiếu mã tham chiếu thanh toán.');
            redirect('dashboard.php?tab=bookings');
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Bảng điều khiển</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🅿️</text></svg>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --light: #f9fafb;
            --dark: #111827;
            --gray: #6b7280;
            --white: #ffffff;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background-color: var(--light);
        }
        
        a {
            color: var(--primary);
            text-decoration: none;
        }
        
        a:hover {
            text-decoration: underline;
        }
        
        .container {
            width: 100%;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        .header {
            background-color: var(--white);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .logo i {
            margin-right: 0.5rem;
        }
        
        .nav-menu {
            display: flex;
            list-style: none;
        }
        
        .nav-item {
            margin-left: 1.5rem;
        }
        
        .nav-link {
            color: var(--dark);
            font-weight: 500;
            padding: 0.5rem;
            transition: color 0.3s;
        }
        
        .nav-link:hover {
            color: var(--primary);
            text-decoration: none;
        }
        
        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            text-decoration: none;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--white);
            text-decoration: none;
        }
        
        .btn-success {
            background-color: var(--success);
            color: var(--white);
        }
        
        .btn-success:hover {
            background-color: #0d9668;
            text-decoration: none;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
            text-decoration: none;
        }
        
        .alert {
            padding: 0.75rem 1.25rem;
            border-radius: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        
        .dashboard {
            display: flex;
            flex-wrap: wrap;
            margin: 2rem 0;
        }
        
        .sidebar {
            width: 250px;
            padding: 1rem;
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-right: 2rem;
        }
        
        .sidebar-menu {
            list-style: none;
        }
        
        .sidebar-item {
            margin-bottom: 0.5rem;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--dark);
            border-radius: 0.25rem;
            transition: all 0.3s;
        }
        
        .sidebar-link:hover {
            background-color: #f3f4f6;
            text-decoration: none;
        }
        
        .sidebar-link.active {
            background-color: #e0e7ff;
            color: var(--primary);
            font-weight: 500;
        }
        
        .sidebar-link i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
        }
        
        .content {
            flex: 1;
            min-width: 0;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .card-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.875rem;
        }
        
        .profile-section {
            margin-bottom: 2rem;
        }
        
        .profile-section h3 {
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 0.25rem;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        table th, table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        table th {
            font-weight: 600;
            background-color: #f9fafb;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        
        .badge-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .slot-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .slot-card:hover {
            transform: translateY(-5px);
        }
        
        .slot-card.selected {
            border-color: var(--primary);
        }
        
        .slot-card.occupied {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .slot-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .slot-id {
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .slot-status {
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .payment-qr {
            text-align: center;
            margin: 2rem 0;
        }
        
        .payment-qr img {
            max-width: 300px;
            margin-bottom: 1rem;
        }
        
        .payment-details {
            max-width: 400px;
            margin: 0 auto;
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
        }
        
        .payment-details p {
            margin-bottom: 0.5rem;
        }
        
        .payment-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
            margin: 1rem 0;
        }
        
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                margin-right: 0;
                margin-bottom: 1.5rem;
            }
            
            .content {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container header-container">
            <a href="index.php" class="logo">
                <i class="fas fa-parking"></i> XParking
            </a>
            
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="index.php" class="nav-link">Trang chủ</a>
                </li>
                <li class="nav-item">
                    <a href="dashboard.php?tab=booking" class="nav-link">Đặt chỗ</a>
                </li>
                <?php if (is_admin()): ?>
                <li class="nav-item">
                    <a href="admin.php" class="btn btn-outline">Quản trị</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a href="index.php?action=logout" class="btn btn-outline">Đăng xuất</a>
                </li>
            </ul>
        </div>
    </header>
    
    <main class="container dashboard">
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; background-color: #e0e7ff; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
                <h3><?php echo htmlspecialchars($_SESSION['user_fullname']); ?></h3>
                <p style="color: var(--gray); font-size: 0.875rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=overview" class="sidebar-link <?php echo $tab === 'overview' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Tổng quan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=booking" class="sidebar-link <?php echo $tab === 'booking' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-plus"></i> Đặt chỗ mới
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=bookings" class="sidebar-link <?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=vehicles" class="sidebar-link <?php echo $tab === 'vehicles' ? 'active' : ''; ?>">
                        <i class="fas fa-car"></i> Xe của tôi
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="dashboard.php?tab=profile" class="sidebar-link <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                        <i class="fas fa-user-cog"></i> Thông tin cá nhân
                    </a>
                </li>
            </ul>
        </aside>
        
        <section class="content">
            <?php
            $flash = get_flash_message();
            if ($flash): 
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <?php
            switch ($tab) {
                case 'overview':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-tachometer-alt"></i> Tổng quan</h2>
                        
                        <div class="stats">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Đặt chỗ hiện tại</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Lần đỗ xe</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-value">0</div>
                                <div class="stat-label">Giờ đỗ xe</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="stat-value">0₫</div>
                                <div class="stat-label">Tổng chi phí</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-parking"></i> Tình trạng bãi đỗ xe</h2>
                        
                        <div class="slot-grid">
                            <?php 
                            $slots = get_all_slots();
                            foreach ($slots as $slot): 
                                $statusClass = $slot['status'] === 'empty' ? 'success' : 'danger';
                                $statusText = $slot['status'] === 'empty' ? 'Trống' : 'Đã đặt';
                            ?>
                            <div class="slot-card <?php echo $slot['status'] !== 'empty' ? 'occupied' : ''; ?>">
                                <div class="slot-icon">
                                    <i class="fas fa-car" style="color: <?php echo $slot['status'] === 'empty' ? '#10b981' : '#ef4444'; ?>"></i>
                                </div>
                                <div class="slot-id"><?php echo htmlspecialchars($slot['id']); ?></div>
                                <div class="slot-status">
                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-bell"></i> Thông báo mới nhất</h2>
                        
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-bell-slash" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có thông báo mới</p>
                        </div>
                    </div>
                    <?php
                    break;
                    
                case 'booking':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-calendar-plus"></i> Đặt chỗ mới</h2>
                        
                        <form action="dashboard.php?tab=booking" method="post">
                            <input type="hidden" name="action" value="create_booking">
                            
                            <div class="form-group">
                                <label class="form-label">Chọn vị trí đỗ xe</label>
                                
                                <div class="slot-grid">
                                    <?php foreach ($available_slots as $slot): ?>
                                    <div class="slot-card" onclick="selectSlot(this, '<?php echo $slot['id']; ?>')">
                                        <div class="slot-icon">
                                            <i class="fas fa-car" style="color: #10b981;"></i>
                                        </div>
                                        <div class="slot-id"><?php echo htmlspecialchars($slot['id']); ?></div>
                                        <div class="slot-status">
                                            <span class="badge badge-success">Trống</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <input type="hidden" id="slot_id" name="slot_id" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="license_plate" class="form-label">Biển số xe</label>
                                <input type="text" id="license_plate" name="license_plate" class="form-control" placeholder="VD: 99F-99999" required>
                            </div>
                            
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="start_date" class="form-label">Ngày đặt</label>
                                    <input type="date" id="start_date" name="start_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="start_time" class="form-label">Giờ đặt</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control" value="<?php echo date('H:i'); ?>" required>
                                </div>
                                
                                <div class="form-group" style="flex: 1; min-width: 200px;">
                                    <label for="duration" class="form-label">Thời gian đỗ (giờ)</label>
                                    <input type="number" id="duration" name="duration" class="form-control" min="1" max="24" value="1" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Giá dự kiến</label>
                                <div id="estimated_price" class="payment-amount">5.000₫</div>
                                <p>Giá: 5.000₫/giờ</p>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Đặt chỗ</button>
                        </form>
                    </div>
                    <?php
                    break;
                    
                case 'bookings':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Lịch sử đặt chỗ</h2>
                        
                        <?php if (empty($bookings)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Bạn chưa có lịch sử đặt chỗ nào</p>
                            <a href="dashboard.php?tab=booking" class="btn btn-primary" style="margin-top: 1rem;">Đặt chỗ ngay</a>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Vị trí</th>
                                        <th>Biển số</th>
                                        <th>Thời gian bắt đầu</th>
                                        <th>Thời gian kết thúc</th>
                                        <th>Trạng thái đặt chỗ</th>
                                        <th>Trạng thái thanh toán</th>
                                        <th>Thành tiền</th>
                                        <th>Thao tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): 
                                        $bookingStatusClass = '';
                                        $paymentStatusClass = '';
                                        
                                        switch ($booking['status']) {
                                            case 'pending':
                                                $bookingStatusClass = 'warning';
                                                $bookingStatusText = 'Chờ xác nhận';
                                                break;
                                            case 'confirmed':
                                                $bookingStatusClass = 'success';
                                                $bookingStatusText = 'Đã xác nhận';
                                                break;
                                            case 'cancelled':
                                                $bookingStatusClass = 'danger';
                                                $bookingStatusText = 'Đã hủy';
                                                break;
                                            case 'completed':
                                                $bookingStatusClass = 'info';
                                                $bookingStatusText = 'Đã hoàn thành';
                                                break;
                                            default:
                                                $bookingStatusClass = 'warning';
                                                $bookingStatusText = 'Chờ xác nhận';
                                        }
                                        
                                        switch ($booking['payment_status']) {
                                            case 'pending':
                                                $paymentStatusClass = 'warning';
                                                $paymentStatusText = 'Chờ thanh toán';
                                                break;
                                            case 'completed':
                                                $paymentStatusClass = 'success';
                                                $paymentStatusText = 'Đã thanh toán';
                                                break;
                                            case 'failed':
                                                $paymentStatusClass = 'danger';
                                                $paymentStatusText = 'Thanh toán thất bại';
                                                break;
                                            case 'expired':
                                                $paymentStatusClass = 'danger';
                                                $paymentStatusText = 'Hết hạn';
                                                break;
                                            default:
                                                $paymentStatusClass = 'warning';
                                                $paymentStatusText = 'Chờ thanh toán';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $booking['id']; ?></td>
                                        <td><?php echo htmlspecialchars($booking['slot_id']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($booking['end_time'])); ?></td>
                                        <td><span class="badge badge-<?php echo $bookingStatusClass; ?>"><?php echo $bookingStatusText; ?></span></td>
                                        <td><span class="badge badge-<?php echo $paymentStatusClass; ?>"><?php echo $paymentStatusText; ?></span></td>
                                        <td><?php echo number_format($booking['amount'], 0, ',', '.'); ?>₫</td>
                                        <td>
                                            <?php if ($booking['status'] === 'pending'): ?>
                                            <form action="dashboard.php?tab=bookings" method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="cancel_booking">
                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;" onclick="return confirm('Bạn có chắc chắn muốn hủy đặt chỗ này?');">Hủy</button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($booking['payment_status'] === 'pending'): ?>
                                            <a href="dashboard.php?tab=payment&id=<?php echo $booking['id']; ?>" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Thanh toán</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                    
                case 'vehicles':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-car"></i> Xe của tôi</h2>
                        
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-car-alt" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Bạn chưa có xe nào trong hệ thống</p>
                            <p style="margin-top: 0.5rem;">Xe sẽ được tự động thêm khi bạn đặt chỗ hoặc sử dụng bãi đỗ xe</p>
                            <a href="dashboard.php?tab=booking" class="btn btn-primary" style="margin-top: 1rem;">Đặt chỗ ngay</a>
                        </div>
                    </div>
                    <?php
                    break;
                    
                case 'profile':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-user-cog"></i> Thông tin cá nhân</h2>
                        
                        <div class="profile-section">
                            <h3>Thông tin tài khoản</h3>
                            
                            <form action="dashboard.php?tab=profile" method="post">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label for="username" class="form-label">Tên đăng nhập</label>
                                    <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                </div>
                                
                                <div class="form-group">
                                    <label for="full_name" class="form-label">Họ và tên</label>
                                    <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone" class="form-label">Số điện thoại</label>
                                    <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Cập nhật thông tin</button>
                            </form>
                        </div>
                        
                        <div class="profile-section">
                            <h3>Đổi mật khẩu</h3>
                            
                            <form action="dashboard.php?tab=profile" method="post">
                                <input type="hidden" name="action" value="change_password">
                                
                                <div class="form-group">
                                    <label for="current_password" class="form-label">Mật khẩu hiện tại</label>
                                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password" class="form-label">Mật khẩu mới</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">Xác nhận mật khẩu mới</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Đổi mật khẩu</button>
                            </form>
                        </div>
                    </div>
                    <?php
                    break;
                    
                case 'payment':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Thanh toán</h2>
                        
                        <?php if (isset($qr_data) && $qr_data['success']): ?>
                        <div class="payment-qr">
                            <img src="<?php echo $qr_data['qr_code']; ?>" alt="QR Code">
                            <div class="payment-amount"><?php echo number_format($qr_data['amount'], 0, ',', '.'); ?>₫</div>
                            
                            <div class="payment-details">
                                <p><strong>Mã thanh toán:</strong> <?php echo $qr_data['reference']; ?></p>
                                <p><strong>Thời hạn:</strong> <?php echo QR_EXPIRE_MINUTES; ?> phút kể từ khi tạo</p>
                                <p>Quét mã QR bằng ứng dụng ngân hàng để thanh toán</p>
                            </div>
                            
                            <div style="margin-top: 2rem;">
                                <p>Trạng thái thanh toán: <span id="payment-status" class="badge badge-warning">Đang chờ thanh toán...</span></p>
                                <p id="payment-message"></p>
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <a href="dashboard.php?tab=bookings" class="btn btn-outline">Quay lại</a>
                                <button id="check-payment" class="btn btn-primary" style="margin-left: 1rem;">Kiểm tra thanh toán</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: #ef4444; display: block; margin-bottom: 1rem;"></i>
                            <p>Không tìm thấy thông tin thanh toán hoặc có lỗi xảy ra</p>
                            <p style="margin-top: 0.5rem;"><?php echo isset($qr_data) ? $qr_data['message'] : 'Vui lòng thử lại sau.'; ?></p>
                            <a href="dashboard.php?tab=bookings" class="btn btn-primary" style="margin-top: 1rem;">Quay lại</a>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
            }
            ?>
        </section>
    </main>
    <script>
        function selectSlot(element, slotId) {
            document.querySelectorAll('.slot-card').forEach(slot => {
                slot.classList.remove('selected');
            });
            element.classList.add('selected');
            document.getElementById('slot_id').value = slotId;
            console.log('Selected slot: ' + slotId);
        }

        function updateEstimatedPrice() {
            const durationInput = document.getElementById('duration');
            const estimatedPriceElement = document.getElementById('estimated_price');
            if (!durationInput || !estimatedPriceElement) {
                console.error('Missing duration input or price element.');
                return;
            }
            const duration = parseInt(durationInput.value) || 1;
            const hourlyRate = 5000;
            const totalPrice = duration * hourlyRate;
            estimatedPriceElement.textContent = totalPrice.toLocaleString('vi-VN') + '₫';
            console.log('Updated price: ' + totalPrice);
        }

        function checkPaymentStatus(reference, statusElement, messageElement) {
            if (!reference || !statusElement || !messageElement) {
                console.error('Missing required parameters for payment status check.');
                return;
            }
            statusElement.textContent = 'Đang kiểm tra...';
            statusElement.className = 'badge badge-info';
            console.log('Checking payment status for: ' + reference);
            const url = `api/check_payment.php?ref=${reference}&_=${Date.now()}`;
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Payment status:', data);
                    if (data.error) {
                        console.error('API Error:', data.error);
                        statusElement.textContent = 'Lỗi';
                        statusElement.className = 'badge badge-danger';
                        messageElement.textContent = `Lỗi từ server: ${data.error}`;
                        return;
                    }
                    switch (data.status) {
                        case 'completed':
                            statusElement.textContent = 'Đã thanh toán';
                            statusElement.className = 'badge badge-success';
                            messageElement.textContent = 'Thanh toán thành công! Đang chuyển hướng...';
                            if (window.checkInterval) {
                                clearInterval(window.checkInterval);
                            }
                            setTimeout(() => {
                                window.location.href = 'dashboard.php?tab=bookings';
                            }, 3000);
                            break;
                        case 'failed':
                            statusElement.textContent = 'Thanh toán thất bại';
                            statusElement.className = 'badge badge-danger';
                            messageElement.textContent = 'Thanh toán thất bại. Vui lòng thử lại.';
                            break;
                        case 'expired':
                            statusElement.textContent = 'Hết hạn';
                            statusElement.className = 'badge badge-danger';
                            messageElement.textContent = 'Mã QR đã hết hạn. Vui lòng tạo mã mới.';
                            break;
                        default:
                            statusElement.textContent = 'Đang chờ thanh toán';
                            statusElement.className = 'badge badge-warning';
                            messageElement.textContent = 'Vui lòng quét mã QR để thanh toán.';
                            break;
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    statusElement.textContent = 'Lỗi kết nối';
                    statusElement.className = 'badge badge-danger';
                    messageElement.textContent = 'Có lỗi xảy ra khi kiểm tra thanh toán. Vui lòng thử lại.';
                });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const durationInput = document.getElementById('duration');
            const bookingForm = document.querySelector('form[action="dashboard.php?tab=booking"]');
            
            if (durationInput) {
                durationInput.addEventListener('input', updateEstimatedPrice);
                updateEstimatedPrice();
            }

            if (bookingForm) {
                bookingForm.addEventListener('submit', function(e) {
                    const slotId = document.getElementById('slot_id').value;
                    if (!slotId) {
                        e.preventDefault();
                        alert('Vui lòng chọn một vị trí đỗ xe trước khi đặt chỗ!');
                        return false;
                    }
                });
            }

            const paymentPage = document.querySelector('.payment-qr');
            if (paymentPage) {
                const urlParams = new URLSearchParams(window.location.search);
                const reference = urlParams.get('ref');
                const statusElement = document.getElementById('payment-status');
                const messageElement = document.getElementById('payment-message');
                const checkButton = document.getElementById('check-payment');
                
                if (reference && statusElement && messageElement && checkButton) {
                    checkButton.addEventListener('click', () => {
                        checkPaymentStatus(reference, statusElement, messageElement);
                    });
                    
                    checkPaymentStatus(reference, statusElement, messageElement);
                    window.checkInterval = setInterval(() => {
                        checkPaymentStatus(reference, statusElement, messageElement);
                    }, 5000);
                    
                    window.addEventListener('beforeunload', () => {
                        clearInterval(window.checkInterval);
                    });
                    
                    const mockButton = document.createElement('button');
                    mockButton.textContent = 'Debug: Complete Payment';
                    mockButton.className = 'btn btn-outline';
                    mockButton.style.marginLeft = '10px';
                    mockButton.style.display = 'none';
                    checkButton.parentNode.appendChild(mockButton);
                    
                    mockButton.addEventListener('click', () => {
                        const formData = new FormData();
                        formData.append('transaction_id', `MOCK-${Date.now()}`);
                        formData.append('status', 'completed');
                        formData.append('reference', reference);
                        
                        fetch('api/webhook.php', { method: 'POST', body: formData })
                            .then(response => response.json())
                            .then(data => {
                                console.log('Mock webhook response:', data);
                            })
                            .catch(error => console.error('Mock webhook error:', error));
                    });
                    
                    document.addEventListener('keydown', (e) => {
                        if (e.shiftKey && e.key === 'D') {
                            mockButton.style.display = mockButton.style.display === 'none' ? 'inline-block' : 'none';
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
<?php
$api_dir = __DIR__ . '/api';
if (!is_dir($api_dir)) {
    mkdir($api_dir, 0755, true);
    
    file_put_contents($api_dir . '/check_payment.php', <<<PHP
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
\$reference = \$_GET['ref'] ?? '';
if (empty(\$reference)) {
    echo json_encode(['error' => 'Missing payment reference']);
    exit;
}
\$status = check_payment_status(\$reference);
echo json_encode(['status' => \$status]);
PHP);
    
    file_put_contents($api_dir . '/webhook.php', <<<PHP
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
file_put_contents('../webhook_log.txt', date('Y-m-d H:i:s') . ' - ' . file_get_contents('php://input') . PHP_EOL, FILE_APPEND);
\$transaction_id = \$_POST['transaction_id'] ?? '';
\$status = \$_POST['status'] ?? '';
\$reference = \$_POST['reference'] ?? '';
if (empty(\$transaction_id) || empty(\$status) || empty(\$reference)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
\$result = process_payment_webhook(\$transaction_id, \$status, \$reference);
if (\$result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to process payment']);
}
PHP);
    
    file_put_contents($api_dir . '/checkin.php', <<<PHP
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
if (\$_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
\$license_plate = \$_POST['license_plate'] ?? '';
\$image_path = \$_POST['image_path'] ?? null;
if (empty(\$license_plate)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
\$available_slots = get_available_slots();
if (empty(\$available_slots)) {
    http_response_code(409);
    echo json_encode(['error' => 'No available parking slots']);
    exit;
}
\$slot = \$available_slots[0];
\$rfid = get_available_rfid();
if (!\$rfid) {
    http_response_code(409);
    echo json_encode(['error' => 'No available RFID tags']);
    exit;
}
\$booking = check_vehicle_booking(\$license_plate);
\$vehicle_id = record_vehicle_entry(\$license_plate, \$slot['id'], \$rfid, \$image_path);
if (\$vehicle_id) {
    echo json_encode([
        'success' => true,
        'vehicle_id' => \$vehicle_id,
        'slot_id' => \$slot['id'],
        'rfid' => \$rfid,
        'has_booking' => !!\$booking
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to record vehicle entry']);
}
PHP);
    
    file_put_contents($api_dir . '/checkout.php', <<<PHP
<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
if (\$_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
\$rfid = \$_POST['rfid'] ?? '';
\$image_path = \$_POST['image_path'] ?? null;
if (empty(\$rfid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
\$vehicle = get_vehicle_by_rfid(\$rfid);
if (!\$vehicle) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehicle not found']);
    exit;
}
\$booking = check_vehicle_booking(\$vehicle['license_plate']);
\$now = date('Y-m-d H:i:s');
\$fee = calculate_parking_fee(\$vehicle['entry_time'], \$now);
if (\$booking) {
    \$booking_end = new DateTime(\$booking['end_time']);
    \$exit_time = new DateTime(\$now);
    if (\$exit_time <= \$booking_end) {
        \$fee = 0;
    } else {
        \$fee = calculate_parking_fee(\$booking['end_time'], \$now);
    }
}
if (\$fee > 0) {
    \$payment = create_exit_payment(\$vehicle['id'], \$fee);
    if (!\$payment['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create payment']);
        exit;
    }
    \$qr_data = generate_payment_qr(\$payment['payment_id']);
    if (!\$qr_data['success']) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate QR code']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'vehicle_id' => \$vehicle['id'],
        'license_plate' => \$vehicle['license_plate'],
        'entry_time' => \$vehicle['entry_time'],
        'exit_time' => \$now,
        'fee' => \$fee,
        'payment_required' => true,
        'payment_id' => \$payment['payment_id'],
        'payment_ref' => \$payment['payment_ref'],
        'qr_code' => \$qr_data['qr_code']
    ]);
} else {
    \$result = record_vehicle_exit(\$vehicle['id'], \$image_path);
    if (\$result) {
        echo json_encode([
            'success' => true,
            'vehicle_id' => \$vehicle['id'],
            'license_plate' => \$vehicle['license_plate'],
            'entry_time' => \$vehicle['entry_time'],
            'exit_time' => \$now,
            'fee' => 0,
            'payment_required' => false
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to record vehicle exit']);
    }
}
PHP);
}
?>