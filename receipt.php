<?php
// receipt.php - Trang hóa đơn thanh toán để in/tải
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Lấy tham số reference
$reference = $_GET['ref'] ?? '';

if (empty($reference)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Thiếu thông tin thanh toán!';
    exit;
}

// Kiểm tra trạng thái thanh toán
try {
    $stmt = $pdo->prepare("SELECT p.*, v.license_plate, v.entry_time, v.exit_time, v.slot_id, 
                          u.full_name, u.email, u.username 
                          FROM payments p 
                          LEFT JOIN vehicles v ON p.vehicle_id = v.id 
                          LEFT JOIN users u ON p.user_id = u.id 
                          WHERE p.payment_ref = :reference");
    $stmt->bindParam(':reference', $reference);
    $stmt->execute();
    
    $payment = $stmt->fetch();
    
    if (!$payment || $payment['status'] !== 'completed') {
        header('HTTP/1.1 404 Not Found');
        echo 'Thanh toán không tồn tại hoặc chưa hoàn thành!';
        exit;
    }
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log("Receipt error: " . $e->getMessage());
    echo 'Lỗi truy vấn cơ sở dữ liệu!';
    exit;
}

// Tính thời gian đỗ xe và phí
$entry_time = new DateTime($payment['entry_time']);
$exit_time = new DateTime($payment['exit_time'] ?? date('Y-m-d H:i:s'));
$interval = $exit_time->diff($entry_time);

$hours = $interval->h + ($interval->days * 24);
$minutes = $interval->i;
$parking_duration = $hours . 'h ' . $minutes . 'm';

// Kiểm tra xem người dùng đã đăng nhập hay không
$user_info = '';
if (isset($payment['username']) && !empty($payment['username'])) {
    $user_info = $payment['full_name'] . ' (' . $payment['username'] . ')';
} else {
    $user_info = 'Khách vãng lai';
}

// Tạo ID hóa đơn
$invoice_id = date('Ymd', strtotime($payment['payment_time'])) . '-' . $payment['id'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Hóa đơn #<?php echo $invoice_id; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🅿️</text></svg>">
    
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f9fafb;
            margin: 0;
            padding: 20px;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .receipt-logo {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 10px;
        }
        
        .receipt-title {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 5px;
        }
        
        .receipt-id {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .receipt-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .receipt-section {
            flex: 1;
        }
        
        .receipt-section h3 {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #4b5563;
        }
        
        .receipt-info {
            margin-bottom: 5px;
        }
        
        .receipt-info strong {
            font-weight: 600;
            margin-right: 5px;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .receipt-table th, 
        .receipt-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .receipt-table th {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        
        .receipt-table td.amount {
            text-align: right;
        }
        
        .receipt-total {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 30px;
        }
        
        .receipt-total-inner {
            width: 300px;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
        }
        
        .receipt-total-row.final {
            font-weight: bold;
            font-size: 1.1rem;
            border-top: 2px solid #e5e7eb;
            padding-top: 12px;
        }
        
        .receipt-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
        }
        
        .receipt-barcode {
            text-align: center;
            margin: 20px 0;
            font-family: monospace;
            font-size: 0.9rem;
            color: #6b7280;
        }
        
        .print-button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            margin-bottom: 20px;
            cursor: pointer;
            border: none;
            font-size: 1rem;
        }
        
        .print-button:hover {
            background-color: #1d4ed8;
        }
        
        @media print {
            body {
                background-color: #fff;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                padding: 0;
            }
            
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="no-print" style="text-align: center; margin-bottom: 20px;">
            <button class="print-button" onclick="window.print();">In hóa đơn</button>
        </div>
        
        <div class="receipt-header">
            <div class="receipt-logo">XParking</div>
            <div class="receipt-title">HÓA ĐƠN THANH TOÁN</div>
            <div class="receipt-id">#<?php echo $invoice_id; ?></div>
        </div>
        
        <div class="receipt-details">
            <div class="receipt-section">
                <h3>Thông tin dịch vụ</h3>
                <div class="receipt-info"><strong>Dịch vụ:</strong> Đỗ xe</div>
                <div class="receipt-info"><strong>Vị trí đỗ:</strong> <?php echo htmlspecialchars($payment['slot_id']); ?></div>
                <div class="receipt-info"><strong>Biển số xe:</strong> <?php echo htmlspecialchars($payment['license_plate']); ?></div>
                <div class="receipt-info"><strong>Thời gian vào:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['entry_time'])); ?></div>
                <div class="receipt-info"><strong>Thời gian ra:</strong> <?php echo date('d/m/Y H:i', strtotime($payment['exit_time'] ?? date('Y-m-d H:i:s'))); ?></div>
                <div class="receipt-info"><strong>Thời gian đỗ:</strong> <?php echo $parking_duration; ?></div>
            </div>
            
            <div class="receipt-section">
                <h3>Thông tin thanh toán</h3>
                <div class="receipt-info"><strong>Mã thanh toán:</strong> <?php echo $payment['payment_ref']; ?></div>
                <div class="receipt-info"><strong>Thời gian thanh toán:</strong> <?php echo date('d/m/Y H:i:s', strtotime($payment['payment_time'])); ?></div>
                <div class="receipt-info"><strong>Phương thức:</strong> QR Code (SePay)</div>
                <div class="receipt-info"><strong>Trạng thái:</strong> Đã thanh toán</div>
                <div class="receipt-info"><strong>Khách hàng:</strong> <?php echo htmlspecialchars($user_info); ?></div>
            </div>
        </div>
        
        <table class="receipt-table">
            <thead>
                <tr>
                    <th>Dịch vụ</th>
                    <th>Mô tả</th>
                    <th>Đơn giá</th>
                    <th>Số lượng</th>
                    <th>Thành tiền</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Phí đỗ xe</td>
                    <td>Đỗ xe tại vị trí <?php echo htmlspecialchars($payment['slot_id']); ?></td>
                    <td><?php echo number_format(HOURLY_RATE, 0, ',', '.'); ?>₫/giờ</td>
                    <td><?php echo $hours + ($minutes > 0 ? 1 : 0); ?> giờ</td>
                    <td class="amount"><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</td>
                </tr>
            </tbody>
        </table>
        
        <div class="receipt-total">
            <div class="receipt-total-inner">
                <div class="receipt-total-row">
                    <span>Tổng phụ:</span>
                    <span><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</span>
                </div>
                <div class="receipt-total-row">
                    <span>Thuế (0%):</span>
                    <span>0₫</span>
                </div>
                <div class="receipt-total-row final">
                    <span>Tổng cộng:</span>
                    <span><?php echo number_format($payment['amount'], 0, ',', '.'); ?>₫</span>
                </div>
            </div>
        </div>
        
        <div class="receipt-barcode">
            *<?php echo $payment['payment_ref']; ?>*
        </div>
        
        <div class="receipt-footer">
            <p>Cảm ơn bạn đã sử dụng dịch vụ của XParking!</p>
            <p>Mọi thắc mắc xin liên hệ: info@xparking.x10.mx</p>
            <p>Hóa đơn được tạo tự động, không cần đóng dấu.</p>
        </div>
    </div>
</body>
</html>