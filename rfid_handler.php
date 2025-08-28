<?php
// rfid_handler.php - Xử lý khi người dùng quét mã QR trên thẻ RFID
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Lấy RFID từ tham số URL
$rfid = $_GET['rfid'] ?? '';

if (empty($rfid)) {
    set_flash_message('error', 'Thiếu thông tin RFID!');
    redirect('index.php');
    exit;
}

// Tìm xe theo RFID
$vehicle = get_vehicle_by_rfid($rfid);

if (!$vehicle) {
    set_flash_message('error', 'Không tìm thấy xe với RFID này!');
    redirect('index.php');
    exit;
}

// Tính phí đỗ xe
$now = date('Y-m-d H:i:s');
$fee = calculate_parking_fee($vehicle['entry_time'], $now);

// Kiểm tra xem xe có booking không
$booking = check_vehicle_booking($vehicle['license_plate']);

// Nếu có booking, kiểm tra xem booking có bao gồm thời gian đỗ không
if ($booking) {
    $booking_end = new DateTime($booking['end_time']);
    $exit_time = new DateTime($now);
    
    if ($exit_time <= $booking_end) {
        // Booking bao gồm thời gian đỗ, không tính phí
        $fee = 0;
    } else {
        // Booking không bao gồm toàn bộ thời gian đỗ, tính phí cho thời gian thêm
        $fee = calculate_parking_fee($booking['end_time'], $now);
    }
}

// Nếu có phí, tạo thanh toán
if ($fee > 0) {
    // Tạo thanh toán
    $payment = create_exit_payment($vehicle['id'], $fee);
    
    if (!$payment['success']) {
        set_flash_message('error', 'Lỗi tạo thanh toán!');
        redirect('index.php');
        exit;
    }
    
    // Chuyển hướng đến trang thanh toán
    redirect('payment.php?id=' . $payment['payment_id']);
} else {
    // Không có phí, hoàn tất quá trình
    $result = record_vehicle_exit($vehicle['id']);
    
    if ($result) {
        set_flash_message('success', 'Xe ra thành công! Không cần thanh toán.');
        redirect('index.php');
    } else {
        set_flash_message('error', 'Lỗi xử lý xe ra!');
        redirect('index.php');
    }
}
?>