<?php
// admin.php - Admin dashboard
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Require admin login
require_login();
require_admin();

// Handle tab switching
$tab = $_GET['tab'] ?? 'dashboard';

// Handle admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_slot':
            $slot_id = $_POST['slot_id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if (empty($slot_id) || empty($status)) {
                set_flash_message('error', 'Thiếu thông tin cần thiết!');
                redirect('admin.php?tab=slots');
                break;
            }
            
            if (update_slot_status($slot_id, $status)) {
                set_flash_message('success', 'Cập nhật trạng thái slot thành công!');
            } else {
                set_flash_message('error', 'Có lỗi xảy ra khi cập nhật slot!');
            }
            redirect('admin.php?tab=slots');
            break;

        case 'send_notification':
            $message = $_POST['notification_message'] ?? '';
            $type = $_POST['notification_type'] ?? 'info';
            
            if (empty($message)) {
                set_flash_message('error', 'Vui lòng nhập nội dung thông báo!');
                redirect('admin.php?tab=settings');
                break;
            }
            
            if (send_notification($message, $type)) {
                set_flash_message('success', 'Gửi thông báo thành công!');
            } else {
                set_flash_message('error', 'Có lỗi xảy ra khi gửi thông báo!');
            }
            redirect('admin.php?tab=settings');
            break;
            
        case 'update_hourly_rate':
            $hourly_rate = $_POST['hourly_rate'] ?? '';
            
            if (empty($hourly_rate)) {
                set_flash_message('error', 'Vui lòng nhập giá tiền!');
                redirect('admin.php?tab=settings');
                break;
            }
        
            
            // In a real application, this would update a settings table
            // For this demo, we'll just show a success message
            set_flash_message('success', 'Cập nhật giá tiền thành công!');
            redirect('admin.php?tab=settings');
            break;
    }
}

// Function to get active vehicles
function get_active_vehicles() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT v.*, s.id as slot_id 
                            FROM vehicles v 
                            JOIN parking_slots s ON v.slot_id = s.id 
                            WHERE v.status = 'in_parking' 
                            ORDER BY v.entry_time DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get active vehicles error: " . $e->getMessage());
        return [];
    }
}
function send_notification($message, $type) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (message, type, created_at) VALUES (?, ?, NOW())");
        return $stmt->execute([$message, $type]);
    } catch (PDOException $e) {
        error_log("Send notification error: " . $e->getMessage());
        return false;
    }
}
// Function to get all users
function get_all_users() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT id, username, email, full_name, phone, role, created_at 
                            FROM users 
                            ORDER BY created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get users error: " . $e->getMessage());
        return [];
    }
}

// Function to get all bookings
function get_all_bookings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT b.*, u.username, u.full_name, p.status as payment_status, p.amount 
                            FROM bookings b 
                            JOIN users u ON b.user_id = u.id 
                            LEFT JOIN payments p ON b.id = p.booking_id 
                            ORDER BY b.created_at DESC");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get bookings error: " . $e->getMessage());
        return [];
    }
}

// Function to get all payments
function get_all_payments() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT p.*, u.username, u.full_name, b.id as booking_id, v.license_plate 
                            FROM payments p 
                            LEFT JOIN users u ON p.user_id = u.id 
                            LEFT JOIN bookings b ON p.booking_id = b.id 
                            LEFT JOIN vehicles v ON p.vehicle_id = v.id 
                            ORDER BY p.created_at DESC 
                            LIMIT 50");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get payments error: " . $e->getMessage());
        return [];
    }
}

// Function to get system logs
function get_system_logs() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT l.*, u.username 
                            FROM system_logs l 
                            LEFT JOIN users u ON l.user_id = u.id 
                            ORDER BY l.created_at DESC 
                            LIMIT 100");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Get logs error: " . $e->getMessage());
        return [];
    }
}

// Get data for current tab
switch ($tab) {
    case 'dashboard':
        $active_vehicles = get_active_vehicles();
        $slots = get_all_slots();
        break;
        
    case 'slots':
        $slots = get_all_slots();
        break;
        
    case 'users':
        $users = get_all_users();
        break;
        
    case 'bookings':
        $bookings = get_all_bookings();
        break;
        
    case 'payments':
        $payments = get_all_payments();
        break;
        
    case 'logs':
        $logs = get_system_logs();
        break;
}

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Quản trị hệ thống</title>
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🅿️</text></svg>">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Custom CSS -->
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
            text-decoration: none;
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
            align-items: center;
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
            text-decoration: none;
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
            color: var(---white);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: var(--primary);
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
        
        .badge-secondary {
            background-color: #e5e7eb;
            color: #4b5563;
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
            transition: all 0.3s;
        }
        
        .slot-card:hover {
            transform: translateY(-5px);
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
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 2rem;
            border-radius: 0.5rem;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .modal-title {
            margin-bottom: 1.5rem;
            font-size: 1.25rem;
            font-weight: bold;
        }
        
        .modal-close {
            float: right;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-close:hover {
            color: var(--danger);
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
    <!-- Header -->
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
                    <a href="dashboard.php" class="btn btn-outline">Trang người dùng</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?action=logout" class="btn btn-outline">Đăng xuất</a>
                </li>
            </ul>
        </div>
    </header>
    
    <main class="container dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <div style="width: 80px; height: 80px; background-color: #e0e7ff; border-radius: 50%; display: flex; justify-content: center; align-items: center; margin: 0 auto 1rem;">
                    <i class="fas fa-user-shield" style="font-size: 2rem; color: var(--primary);"></i>
                </div>
                <h3>Quản trị viên</h3>
                <p style="color: var(--gray); font-size: 0.875rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>
            
            <ul class="sidebar-menu">
                <li class="sidebar-item">
                    <a href="admin.php?tab=dashboard" class="sidebar-link <?php echo $tab === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Tổng quan
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=slots" class="sidebar-link <?php echo $tab === 'slots' ? 'active' : ''; ?>">
                        <i class="fas fa-parking"></i> Quản lý slots
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=users" class="sidebar-link <?php echo $tab === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Quản lý người dùng
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=bookings" class="sidebar-link <?php echo $tab === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Quản lý đặt chỗ
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=payments" class="sidebar-link <?php echo $tab === 'payments' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i> Quản lý thanh toán
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=logs" class="sidebar-link <?php echo $tab === 'logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> Nhật ký hệ thống
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="admin.php?tab=settings" class="sidebar-link <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> Cài đặt hệ thống
                    </a>
                </li>
            </ul>
        </aside>
        
        <!-- Main Content -->
        <section class="content">
            <?php
            // Display flash messages
            $flash = get_flash_message();
            if ($flash): 
            ?>
            <div class="alert alert-<?php echo $flash['type']; ?>">
                <?php echo $flash['message']; ?>
            </div>
            <?php endif; ?>
            
            <?php
            // Load content based on current tab
            switch ($tab) {
                case 'dashboard':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-tachometer-alt"></i> Tổng quan hệ thống</h2>
                        
                        <div class="stats">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="stat-value"><?php echo count($active_vehicles); ?></div>
                                <div class="stat-label">Xe đang đỗ</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-parking"></i>
                                </div>
                                <?php
                                $available_count = 0;
                                foreach ($slots as $slot) {
                                    if ($slot['status'] === 'empty') {
                                        $available_count++;
                                    }
                                }
                                ?>
                                <div class="stat-value"><?php echo $available_count; ?>/<?php echo count($slots); ?></div>
                                <div class="stat-label">Slot trống</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <?php
                                $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
                                $booking_count = $stmt->fetchColumn();
                                ?>
                                <div class="stat-value"><?php echo $booking_count; ?></div>
                                <div class="stat-label">Đặt chỗ hiện tại</div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <?php
                                $stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'completed'");
                                $total_revenue = $stmt->fetchColumn() ?: 0;
                                ?>
                                <div class="stat-value"><?php echo number_format($total_revenue, 0, ',', '.'); ?>₫</div>
                                <div class="stat-label">Tổng doanh thu</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-car"></i> Xe đang đỗ</h2>
                        
                        <?php if (empty($active_vehicles)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-car" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có xe nào đang đỗ</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Biển số</th>
                                        <th>Slot</th>
                                        <th>RFID</th>
                                        <th>Thời gian vào</th>
                                        <th>Thời gian đỗ</th>
                                        <th>Phí dự kiến</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($active_vehicles as $vehicle): 
                                        $entry_time = new DateTime($vehicle['entry_time']);
                                        $now = new DateTime();
                                        $diff = $now->diff($entry_time);
                                        $hours = $diff->h + ($diff->days * 24);
                                        $minutes = $diff->i;
                                        
                                        // Calculate estimated fee
                                        $fee = ($hours + ($minutes > 0 ? 1 : 0)) * HOURLY_RATE;
                                    ?>
                                    <tr>
                                        <td><?php echo $vehicle['id']; ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['license_plate']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['slot_id']); ?></td>
                                        <td><?php echo htmlspecialchars($vehicle['rfid_tag']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($vehicle['entry_time'])); ?></td>
                                        <td><?php echo $hours . 'h ' . $minutes . 'm'; ?></td>
                                        <td><?php echo number_format($fee, 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-parking"></i> Tình trạng bãi đỗ xe</h2>
                        
                        <div class="slot-grid">
                            <?php foreach ($slots as $slot): 
                                $statusClass = '';
                                $statusText = '';
                                $statusColor = '';
                                
                                switch ($slot['status']) {
                                    case 'empty':
                                        $statusClass = 'success';
                                        $statusText = 'Trống';
                                        $statusColor = '#10b981';
                                        break;
                                    case 'occupied':
                                        $statusClass = 'danger';
                                        $statusText = 'Đang sử dụng';
                                        $statusColor = '#ef4444';
                                        break;
                                    case 'reserved':
                                        $statusClass = 'warning';
                                        $statusText = 'Đã đặt trước';
                                        $statusColor = '#f59e0b';
                                        break;
                                    case 'maintenance':
                                        $statusClass = 'secondary';
                                        $statusText = 'Bảo trì';
                                        $statusColor = '#6b7280';
                                        break;
                                }
                            ?>
                            <div class="slot-card">
                                <div class="slot-icon">
                                    <i class="fas fa-car" style="color: <?php echo $statusColor; ?>"></i>
                                </div>
                                <div class="slot-id"><?php echo htmlspecialchars($slot['id']); ?></div>
                                <div class="slot-status">
                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                                <?php if ($slot['rfid_assigned'] !== 'empty'): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.875rem;">
                                    <strong>RFID:</strong> <?php echo htmlspecialchars($slot['rfid_assigned']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php
                    break;
                    
                case 'slots':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-parking"></i> Quản lý slots</h2>
                        
                        <div class="slot-grid">
                            <?php foreach ($slots as $slot): 
                                $statusClass = '';
                                $statusText = '';
                                $statusColor = '';
                                
                                switch ($slot['status']) {
                                    case 'empty':
                                        $statusClass = 'success';
                                        $statusText = 'Trống';
                                        $statusColor = '#10b981';
                                        break;
                                    case 'occupied':
                                        $statusClass = 'danger';
                                        $statusText = 'Đang sử dụng';
                                        $statusColor = '#ef4444';
                                        break;
                                    case 'reserved':
                                        $statusClass = 'warning';
                                        $statusText = 'Đã đặt trước';
                                        $statusColor = '#f59e0b';
                                        break;
                                    case 'maintenance':
                                        $statusClass = 'secondary';
                                        $statusText = 'Bảo trì';
                                        $statusColor = '#6b7280';
                                        break;
                                }
                            ?>
                            <div class="slot-card">
                                <div class="slot-icon">
                                    <i class="fas fa-car" style="color: <?php echo $statusColor; ?>"></i>
                                </div>
                                <div class="slot-id"><?php echo htmlspecialchars($slot['id']); ?></div>
                                <div class="slot-status">
                                    <span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </div>
                                <?php if ($slot['rfid_assigned'] !== 'empty'): ?>
                                <div style="margin-top: 0.5rem; font-size: 0.875rem;">
                                    <strong>RFID:</strong> <?php echo htmlspecialchars($slot['rfid_assigned']); ?>
                                </div>
                                <?php endif; ?>
                                <div style="margin-top: 1rem;">
                                    <button class="btn btn-primary" onclick="openEditModal('<?php echo $slot['id']; ?>', '<?php echo $slot['status']; ?>')">Cập nhật</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Edit Slot Modal -->
                    <div id="editSlotModal" class="modal">
                        <div class="modal-content">
                            <span class="modal-close" onclick="closeModal()">&times;</span>
                            <h3 class="modal-title">Cập nhật trạng thái slot</h3>
                            
                            <form action="admin.php?tab=slots" method="post">
                                <input type="hidden" name="action" value="update_slot">
                                <input type="hidden" id="slot_id" name="slot_id" value="">
                                
                                <div class="form-group">
                                    <label for="status" class="form-label">Trạng thái</label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="empty">Trống</option>
                                        <option value="maintenance">Bảo trì</option>
                                    </select>
                                    <small>Lưu ý: Chỉ có thể chuyển slot đang trống sang bảo trì hoặc ngược lại.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary" style="width: 100%;">Cập nhật</button>
                            </form>
                        </div>
                    </div>               
                    <?php
                    break;
                    
                case 'users':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-users"></i> Quản lý người dùng</h2>
                        
                        <?php if (empty($users)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-users" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có người dùng nào</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tên đăng nhập</th>
                                        <th>Họ và tên</th>
                                        <th>Email</th>
                                        <th>Số điện thoại</th>
                                        <th>Vai trò</th>
                                        <th>Ngày đăng ký</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): 
                                        $roleClass = $user['role'] === 'admin' ? 'danger' : 'info';
                                        $roleText = $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng';
                                    ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td><span class="badge badge-<?php echo $roleClass; ?>"><?php echo $roleText; ?></span></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                    
                case 'bookings':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-calendar-alt"></i> Quản lý đặt chỗ</h2>
                        
                        <?php if (empty($bookings)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có đặt chỗ nào</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người dùng</th>
                                        <th>Slot</th>
                                        <th>Biển số</th>
                                        <th>Thời gian bắt đầu</th>
                                        <th>Thời gian kết thúc</th>
                                        <th>Trạng thái đặt chỗ</th>
                                        <th>Trạng thái thanh toán</th>
                                        <th>Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): 
                                        // Set status classes
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
                                        <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['slot_id']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['license_plate']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($booking['start_time'])); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($booking['end_time'])); ?></td>
                                        <td><span class="badge badge-<?php echo $bookingStatusClass; ?>"><?php echo $bookingStatusText; ?></span></td>
                                        <td><span class="badge badge-<?php echo $paymentStatusClass; ?>"><?php echo $paymentStatusText; ?></span></td>
                                        <td><?php echo number_format($booking['amount'], 0, ',', '.'); ?>₫</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                    
                case 'payments':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Quản lý thanh toán</h2>
                        
                        <?php if (empty($payments)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-money-bill-wave" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có thanh toán nào</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Người dùng</th>
                                        <th>Loại</th>
                                        <th>Mã tham chiếu</th>
                                        <th>Số tiền</th>
                                        <th>Thời gian thanh toán</th>
                                        <th>Trạng thái</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): 
                                        // Set status class
                                        $statusClass = '';
                                        $statusText = '';
                                        
                                        switch ($payment['status']) {
                                            case 'pending':
                                                $statusClass = 'warning';
                                                $statusText = 'Chờ thanh toán';
                                                break;
                                            case 'completed':
                                                $statusClass = 'success';
                                                $statusText = 'Đã thanh toán';
                                                break;
                                            case 'failed':
                                                $statusClass = 'danger';
                                                $statusText = 'Thanh toán thất bại';
                                                break;
                                            case 'expired':
                                                $statusClass = 'danger';
                                                $statusText = 'Hết hạn';
                                                break;
                                            default:
                                                $statusClass = 'warning';
                                                $statusText = 'Chờ thanh toán';
                                        }
                                        
                                        // Determine payment type
                                        $paymentType = '';
                                        if ($payment['booking_id']) {
                                            $paymentType = 'Đặt chỗ #' . $payment['booking_id'];
                                        } elseif ($payment['vehicle_id']) {
                                            $paymentType = 'Xe ra #' . $payment['vehicle_id'];
                                            if ($payment['license_plate']) {
                                                $paymentType .= ' (' . $payment['license_plate'] . ')';
                                            }
                                        } else {
                                            $paymentType = 'Khác';
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $payment['id']; ?></td>
                                        <td><?php echo $payment['full_name'] ? htmlspecialchars($payment['full_name']) : 'N/A'; ?></td>
                                        <td><?php echo $paymentType; ?></td>
                                        <td><?php echo htmlspecialchars($payment['payment_ref']); ?></td>
                                        <td><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</td>
                                        <td><?php echo $payment['payment_time'] ? date('d/m/Y H:i', strtotime($payment['payment_time'])) : 'N/A'; ?></td>
                                        <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                    
                case 'logs':
                    ?>
                    <div class="card">
                        <h2 class="card-title"><i class="fas fa-history"></i> Nhật ký hệ thống</h2>
                        
                        <?php if (empty($logs)): ?>
                        <div style="text-align: center; padding: 2rem 0;">
                            <i class="fas fa-history" style="font-size: 3rem; color: #d1d5db; display: block; margin-bottom: 1rem;"></i>
                            <p>Không có nhật ký nào</p>
                        </div>
                        <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Sự kiện</th>
                                        <th>Mô tả</th>
                                        <th>Người dùng</th>
                                        <th>IP</th>
                                        <th>Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?php echo $log['id']; ?></td>
                                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                                        <td><?php echo $log['username'] ? htmlspecialchars($log['username']) : 'N/A'; ?></td>
                                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    break;
                    
                case 'settings':
                    ?>
                    <div class="card">
                         <h3 style="margin-bottom: 1rem;">Hệ thống</h3>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <strong>Thời gian:</strong>
                                <p id="clock"><?php echo date('d/m/Y H:i:s'); ?></p>
                            </div>
                            <div>   
                                <strong>Own:</strong>                           
                                <p>PHUCX</p>
                            </div>                            
                        </div>
                        <hr style="margin: 2rem 0;">

                        <h2 class="card-title"><i class="fas fa-cogs"></i> Setup giá</h2>
                        
                        <form action="admin.php?tab=settings" method="post">
                            <input type="hidden" name="action" value="update_hourly_rate">
                            
                            <div class="form-group">
                                <label for="hourly_rate" class="form-label">Giá giờ đỗ xe (VNĐ)</label>
                                <input type="number" id="hourly_rate" name="hourly_rate" class="form-control" value="<?php echo HOURLY_RATE; ?>" min="1000" step="1000" required>
                                <small>Giá hiện tại: <?php echo number_format(HOURLY_RATE, 0, ',', '.'); ?>₫/giờ</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Cập nhật</button>
                        </form>
                        
                        <hr style="margin: 2rem 0;">
                        
                        <h3 style="margin-bottom: 1rem;">Gửi thông báo</h3>
                        
                        <form action="admin.php?tab=settings" method="post">
                            <input type="hidden" name="action" value="send_notification">
                            
                            <div class="form-group">
                                <label for="notification_message" class="form-label">Nội dung</label>
                                <textarea id="notification_message" name="notification_message" class="form-control" rows="4" required placeholder="Nhập nội dung thông báo..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="notification_type" class="form-label">Loại thông báo</label>
                                <select id="notification_type" name="notification_type" class="form-control" required>
                                    <option value="info">Update</option>
                                    <option value="warning">Cảnh báo</option>                                
                                    <option value="error">Lỗi</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Gửi thông báo</button>
                        </form>                 
                        <hr style="margin: 2rem 0;">                       
                       
                    </div>
                    <?php
                    break;
            }
            ?>
        </section>
    </main>
    
    <!-- Footer -->
    <footer style="background-color: var(--dark); color: var(--light); padding: 1rem 0; text-align: center; margin-top: 2rem;">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> XParking. All rights reserved.</p>
        </div>
    </footer>
    <script>
                        function updateClock() {
                            const now = new Date();
                            const formatted = now.toLocaleString("vi-VN", { hour12: false });
                            document.getElementById("clock").innerText = formatted;
                        }
                        setInterval(updateClock, 1000);
                        // Modal functions
                        function openEditModal(slotId, status) {
                            document.getElementById('slot_id').value = slotId;
                            document.getElementById('status').value = status;
                            
                            // Only allow changing status if slot is empty or in maintenance
                            if (status !== 'empty' && status !== 'maintenance') {
                                alert('Không thể thay đổi trạng thái slot đang sử dụng!');
                                return;
                            }
                            
                            document.getElementById('editSlotModal').style.display = 'block';
                        }
                        
                        function closeModal() {
                            document.getElementById('editSlotModal').style.display = 'none';
                        }
                        
                        // Close modal when clicking outside
                        window.onclick = function(event) {
                            if (event.target === document.getElementById('editSlotModal')) {
                                closeModal();
                            }
                        }
                    </script>
</body>
</html>
<?php
?>