<?php
// includes/config.php - Updated cho SePay
session_start();

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'aywfzpkg_pay_xparking');
define('DB_USER', 'aywfzpkg_phuc');
define('DB_PASS', 'phucthanh710');

// SePay Configuration - SỬ DỤNG API THẬT
define('SEPAY_TOKEN', 'WPWUUB36IZ3FFSMX5IBTPGZEKK0MSXUTYEN9Y7IT8N2YJBHQLOS2CVKKRVRLANZ5');
define('SEPAY_QR_API', 'https://qr.sepay.vn/img');
//define('SEPAY_API_URL', 'https://my.sepay.vn/userapi/transactions/create');
define('WEBHOOK_URL', 'https://xparking.x10.mx/api/webhook.php');
// Bank Info for QR Code
define('VIETQR_BANK_ID', 'MBBank');              // MB Bank code  
define('VIETQR_ACCOUNT_NO', '09696969690');      // Số tài khoản
define('VIETQR_ACCOUNT_NAME', 'NGUYEN THANH PHUC'); // Tên chủ tài khoản
define('VIETQR_TEMPLATE', 'compact');            // Template QR

// Site Configuration
define('SITE_URL', 'https://xparking.x10.mx');
define('HOURLY_RATE', 5000); // 5000đ/giờ
define('QR_EXPIRE_MINUTES', 10); 

// Email Configuration
define('ADMIN_EMAIL', 'Supprt@xparking.x10.mx');

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
                   DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Common Functions
function redirect($url) {
    header("Location: $url");
    exit;
}

function send_email($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: XParking <noreply@xparking.x10.mx>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Flash Messages
function set_flash_message($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash_message() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
?>