<?php
// payment_success.php - Trang thông báo thanh toán thành công
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Lấy tham số reference
$reference = $_GET['ref'] ?? '';

if (empty($reference)) {
    set_flash_message('error', 'Thiếu thông tin thanh toán!');
    redirect('index.php');
    exit;
}

// Kiểm tra trạng thái thanh toán
try {
    $stmt = $pdo->prepare("SELECT p.*, v.license_plate, v.entry_time, v.exit_time, v.slot_id 
                          FROM payments p 
                          LEFT JOIN vehicles v ON p.vehicle_id = v.id 
                          WHERE p.payment_ref = :reference");
    $stmt->bindParam(':reference', $reference);
    $stmt->execute();
    
    $payment = $stmt->fetch();
    
    if (!$payment || $payment['status'] !== 'completed') {
        set_flash_message('error', 'Thanh toán không tồn tại hoặc chưa hoàn thành!');
        redirect('index.php');
        exit;
    }
    
} catch (PDOException $e) {
    set_flash_message('error', 'Lỗi truy vấn cơ sở dữ liệu!');
    error_log("Payment success error: " . $e->getMessage());
    redirect('index.php');
    exit;
}

// Tính thời gian đỗ xe và phí
$entry_time = new DateTime($payment['entry_time']);
$exit_time = new DateTime($payment['exit_time'] ?? date('Y-m-d H:i:s'));
$interval = $exit_time->diff($entry_time);

$hours = $interval->h + ($interval->days * 24);
$minutes = $interval->i;
$parking_duration = $hours . 'h ' . $minutes . 'm';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Thanh toán thành công</title>
    
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
        
        .container {
            width: 100%;
            max-width: 500px;
            margin: 2rem auto;
            padding: 0 15px;
        }
        
        .card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .success-icon {
            font-size: 5rem;
            color: var(--success);
            margin-bottom: 1rem;
        }
        
        .card-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .payment-details {
            max-width: 400px;
            margin: 0 auto;
            background-color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: left;
            margin-bottom: 1.5rem;
        }
        
        .payment-details table {
            width: 100%;
        }
        
        .payment-details table th, 
        .payment-details table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .payment-details table th {
            font-weight: 600;
            width: 50%;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            text-decoration: none;
            margin: 0 0.5rem;
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
        
        .receipt {
            margin: 1rem 0 2rem;
            font-size: 1rem;
        }
        
        .receipt-icon {
            margin-right: 0.5rem;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="card-title">Thanh toán thành công!</h2>
            <p>Cảm ơn bạn đã sử dụng dịch vụ của XParking</p>
            
            <div class="payment-details">
                <table>
                    <tr>
                        <th>Biển số xe:</th>
                        <td><?php echo htmlspecialchars($payment['license_plate']); ?></td>
                    </tr>
                    <tr>
                        <th>Vị trí đỗ:</th>
                        <td><?php echo htmlspecialchars($payment['slot_id']); ?></td>
                    </tr>
                    <tr>
                        <th>Thời gian vào:</th>
                        <td><?php echo date('d/m/Y H:i', strtotime($payment['entry_time'])); ?></td>
                    </tr>
                    <tr>
                        <th>Thời gian đỗ:</th>
                        <td><?php echo $parking_duration; ?></td>
                    </tr>
                    <tr>
                        <th>Tổng tiền:</th>
                        <td><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</td>
                    </tr>
                    <tr>
                        <th>Mã giao dịch:</th>
                        <td><?php echo $payment['payment_ref']; ?></td>
                    </tr>
                    <tr>
                        <th>Thời gian thanh toán:</th>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($payment['payment_time'])); ?></td>
                    </tr>
                </table>
            </div>
            
            <div class="receipt">
                <a href="receipt.php?ref=<?php echo urlencode($reference); ?>" target="_blank">
                    <i class="fas fa-file-invoice receipt-icon"></i>Xem và tải hóa đơn
                </a>
            </div>
            
            <p>Bạn có thể ra khỏi bãi đỗ xe. Cổng sẽ tự động mở khi nhận diện xe của bạn.</p>
            
            <div style="margin-top: 2rem;">
                <a href="index.php" class="btn btn-primary">Về trang chủ</a>
                <a href="dashboard.php" class="btn btn-outline">Quản lý tài khoản</a>
            </div>
        </div>
    </div>
</body>
</html>