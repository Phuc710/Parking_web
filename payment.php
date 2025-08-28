<?php
// payment.php - Trang thanh toán phí đỗ xe
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Kiểm tra tham số thanh toán
$payment_id = $_GET['id'] ?? 0;

if (!$payment_id) {
    set_flash_message('error', 'Thiếu thông tin thanh toán!');
    redirect('index.php');
    exit;
}

// Lấy thông tin thanh toán
try {
    $stmt = $pdo->prepare("SELECT p.*, v.license_plate, v.entry_time, v.rfid_tag, v.slot_id
                          FROM payments p 
                          LEFT JOIN vehicles v ON p.vehicle_id = v.id 
                          WHERE p.id = :id AND p.status = 'pending'");
    $stmt->bindParam(':id', $payment_id);
    $stmt->execute();
    
    $payment = $stmt->fetch();
    
    if (!$payment) {
        set_flash_message('error', 'Thanh toán không tồn tại hoặc đã hoàn thành!');
        redirect('index.php');
        exit;
    }
    
    // Tạo QR code thanh toán
    $qr_data = generate_payment_qr($payment_id);
    
    if (!$qr_data['success']) {
        set_flash_message('error', $qr_data['message']);
        redirect('index.php');
        exit;
    }
    
} catch (PDOException $e) {
    set_flash_message('error', 'Lỗi truy vấn cơ sở dữ liệu!');
    error_log("Payment error: " . $e->getMessage());
    redirect('index.php');
    exit;
}

// Tính thời gian đỗ xe
$entry_time = new DateTime($payment['entry_time']);
$now = new DateTime();
$interval = $now->diff($entry_time);

$hours = $interval->h + ($interval->days * 24);
$minutes = $interval->i;
$parking_duration = $hours . 'h ' . $minutes . 'm';

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Thanh toán</title>
    
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
        }
        
        .card-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary);
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
            text-align: center;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .vehicle-info {
            margin-bottom: 1.5rem;
        }
        
        .vehicle-info table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vehicle-info table th, 
        .vehicle-info table td {
            padding: 0.5rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .vehicle-info table th {
            font-weight: 600;
            width: 40%;
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
        
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: var(--primary);
            animation: spin 1s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 0.5rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h2 class="card-title"><i class="fas fa-money-bill-wave"></i> Thanh toán phí đỗ xe</h2>
            
            <div class="vehicle-info">
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
                        <th>Phí đỗ xe:</th>
                        <td><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</td>
                    </tr>
                </table>
            </div>
            
            <div class="payment-qr">
                <img src="<?php echo $qr_data['qr_code']; ?>" alt="QR Code">
                <div class="payment-amount"><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</div>
                
                <div class="payment-details">
                    <p><strong>Mã thanh toán:</strong> <?php echo $qr_data['reference']; ?></p>
                    <p><strong>Thời hạn:</strong> <?php echo QR_EXPIRE_MINUTES; ?> phút kể từ khi tạo</p>
                    <p>Quét mã QR bằng ứng dụng ngân hàng để thanh toán</p>
                </div>
                
                <div style="margin-top: 2rem;">
                    <p>Trạng thái thanh toán: <span id="payment-status" class="badge badge-warning">Đang chờ thanh toán...</span></p>
                    <p id="payment-message"></p>
                </div>
                
                <div style="margin-top: 1rem; text-align: center;">
                    <button id="check-payment" class="btn btn-primary">
                        <span id="spinner" class="spinner" style="display: none;"></span>
                        Kiểm tra thanh toán
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto check payment status every 5 seconds
        let checkInterval;
        const reference = '<?php echo $qr_data['reference']; ?>';
        const statusElement = document.getElementById('payment-status');
        const messageElement = document.getElementById('payment-message');
        const checkButton = document.getElementById('check-payment');
        const spinner = document.getElementById('spinner');
        
        // Function to check payment status
        function checkPaymentStatus() {
            statusElement.textContent = 'Đang kiểm tra...';
            statusElement.className = 'badge badge-warning';
            spinner.style.display = 'inline-block';
            
            fetch(`api/check_payment.php?ref=${reference}`)
                .then(response => response.json())
                .then(data => {
                    spinner.style.display = 'none';
                    
                    if (data.status === 'completed') {
                        statusElement.textContent = 'Đã thanh toán';
                        statusElement.className = 'badge badge-success';
                        messageElement.textContent = 'Thanh toán thành công! Đang chuyển hướng...';
                        
                        // Clear interval and redirect after 3 seconds
                        clearInterval(checkInterval);
                        setTimeout(() => {
                            window.location.href = 'payment_success.php?ref=' + reference;
                        }, 3000);
                    } else if (data.status === 'failed') {
                        statusElement.textContent = 'Thanh toán thất bại';
                        statusElement.className = 'badge badge-danger';
                        messageElement.textContent = 'Thanh toán thất bại. Vui lòng thử lại.';
                    } else if (data.status === 'expired') {
                        statusElement.textContent = 'Hết hạn';
                        statusElement.className = 'badge badge-danger';
                        messageElement.textContent = 'Mã QR đã hết hạn. Vui lòng tạo mã mới.';
                    } else {
                        statusElement.textContent = 'Đang chờ thanh toán';
                        statusElement.className = 'badge badge-warning';
                        messageElement.textContent = 'Vui lòng quét mã QR để thanh toán.';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    spinner.style.display = 'none';
                    statusElement.textContent = 'Lỗi';
                    statusElement.className = 'badge badge-danger';
                    messageElement.textContent = 'Có lỗi xảy ra khi kiểm tra thanh toán.';
                });
        }
        
        // Manual check
        checkButton.addEventListener('click', checkPaymentStatus);
        
        // Start automatic checking
        checkInterval = setInterval(checkPaymentStatus, 5000);
        
        // Initial check
        checkPaymentStatus();
        
        // Clear interval when leaving the page
        window.addEventListener('beforeunload', function() {
            clearInterval(checkInterval);
        });
    </script>
</body>
</html>