<?php
// api/check_payment.php - Kiểm tra trạng thái thanh toán
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Get payment reference
$reference = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $reference = $_GET['ref'];
}

if (empty($reference)) {
    echo json_encode(['error' => 'Thiếu mã tham chiếu thanh toán']);
    exit;
}

// Check payment status
try {
    // FIX: Lấy created_at thay vì payment_time để tính expired
    $stmt = $pdo->prepare("SELECT status, payment_time, amount, created_at FROM payments WHERE payment_ref = :reference");
    $stmt->bindParam(':reference', $reference);
    $stmt->execute();
    
    $payment = $stmt->fetch();
    
    if (!$payment) {
        echo json_encode(['error' => 'Không tìm thấy thanh toán với mã tham chiếu này']);
        exit;
    }
    
    // FIX: Check expired dựa trên created_at, không phải payment_time
    $expired = false;
    $time_remaining = 0;
    
    if ($payment['status'] === 'pending') {
        $created = new DateTime($payment['created_at']);
        $now = new DateTime();
        $diff = $now->getTimestamp() - $created->getTimestamp();
        
        // If more than QR_EXPIRE_MINUTES minutes passed
        if ($diff > (QR_EXPIRE_MINUTES * 60)) {
            // Update status to expired
            $stmt = $pdo->prepare("UPDATE payments SET status = 'expired' WHERE payment_ref = :reference");
            $stmt->bindParam(':reference', $reference);
            $stmt->execute();
            
            $expired = true;
            $time_remaining = 0;
        } else {
            // Calculate remaining time in seconds
            $time_remaining = (QR_EXPIRE_MINUTES * 60) - $diff;
        }
    }
    
    $status = $expired ? 'expired' : $payment['status'];
    
    // FIX: Trả về thêm thông tin thời gian
    $response = [
        'status' => $status,
        'payment_time' => $payment['payment_time'],
        'amount' => $payment['amount'],
        'created_at' => $payment['created_at']
    ];
    
    // Thêm thời gian còn lại nếu đang pending
    if ($status === 'pending') {
        $response['time_remaining'] = $time_remaining;
        $response['expires_in'] = gmdate('i:s', $time_remaining); // Format MM:SS
    }
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Check payment error: " . $e->getMessage());
    echo json_encode(['error' => 'Lỗi kiểm tra trạng thái thanh toán']);
    exit;
}
?>