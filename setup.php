<?php
// setup.php - Database setup script
require_once 'includes/config.php';

// Create tables if they don't exist
$tables = [
    // Users table
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20),
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    // Parking slots table
    "CREATE TABLE IF NOT EXISTS parking_slots (
        id VARCHAR(10) PRIMARY KEY,
        rfid_assigned VARCHAR(50) DEFAULT 'empty',
        status ENUM('empty', 'occupied', 'reserved', 'maintenance') DEFAULT 'empty'
    )",
    
    // Vehicles table
    "CREATE TABLE IF NOT EXISTS vehicles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        license_plate VARCHAR(20) NOT NULL,
        user_id INT,
        entry_time DATETIME DEFAULT NULL,
        exit_time DATETIME DEFAULT NULL,
        slot_id VARCHAR(10),
        rfid_tag VARCHAR(50),
        entry_image VARCHAR(255),
        exit_image VARCHAR(255),
        status ENUM('in_parking', 'exited', 'pending') DEFAULT 'pending',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE SET NULL
    )",
    
    // Bookings table
    "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        slot_id VARCHAR(10) NOT NULL,
        license_plate VARCHAR(20) NOT NULL,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE
    )",
    
    // Payments table
    "CREATE TABLE IF NOT EXISTS payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        vehicle_id INT,
        booking_id INT,
        amount DECIMAL(10, 2) NOT NULL,
        payment_ref VARCHAR(100) UNIQUE,
        sepay_ref VARCHAR(100),
        qr_code VARCHAR(255),
        payment_time DATETIME,
        status ENUM('pending', 'completed', 'failed', 'expired') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
    )",
    
    // Log table
    "CREATE TABLE IF NOT EXISTS system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        event_type VARCHAR(50) NOT NULL,
        description TEXT NOT NULL,
        user_id INT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    // RFID pool table
    "CREATE TABLE IF NOT EXISTS rfid_pool (
        id INT AUTO_INCREMENT PRIMARY KEY,
        uid VARCHAR(50) UNIQUE NOT NULL,
        status ENUM('available', 'assigned') DEFAULT 'available'
    )"
];

// Execute each table creation query
$success = true;
$message = "";

foreach ($tables as $sql) {
    try {
        $pdo->exec($sql);
        $message .= "<div class='success'>Table created successfully</div>";
    } catch (PDOException $e) {
        $success = false;
        $message .= "<div class='error'>Error creating table: " . $e->getMessage() . "</div>";
    }
}

// Insert default admin user if not exists
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, full_name, role) 
                              VALUES ('admin', :password, 'admin@xparking.x10.mx', 'System Administrator', 'admin')");
        $stmt->bindParam(':password', $password_hash);
        $stmt->execute();
        $message .= "<div class='success'>Admin user created successfully</div>";
    }
} catch (PDOException $e) {
    $success = false;
    $message .= "<div class='error'>Error creating admin user: " . $e->getMessage() . "</div>";
}

// Insert default parking slots if not exists
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM parking_slots");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $slots = [
            ['A01', 'empty'],
            ['A02', 'empty'],
            ['A03', 'empty'],
            ['A04', 'empty'],
            ['A05', 'empty']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO parking_slots (id, status) VALUES (?, ?)");
        foreach ($slots as $slot) {
            $stmt->execute($slot);
        }
        $message .= "<div class='success'>Default parking slots created successfully</div>";
    }
} catch (PDOException $e) {
    $success = false;
    $message .= "<div class='error'>Error creating parking slots: " . $e->getMessage() . "</div>";
}

// Insert RFID tags if not exists
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rfid_pool");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $rfids = [
            ['CD290C73'],
            ['AB123456'],
            ['EF789012'],
            ['GH345678'],
            ['IJ901234']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO rfid_pool (uid) VALUES (?)");
        foreach ($rfids as $rfid) {
            $stmt->execute($rfid);
        }
        $message .= "<div class='success'>Default RFID tags created successfully</div>";
    }
} catch (PDOException $e) {
    $success = false;
    $message .= "<div class='error'>Error creating RFID tags: " . $e->getMessage() . "</div>";
}

// Output result
echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking - Cài đặt hệ thống</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f7f9fc;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2563eb;
            margin-top: 0;
        }
        .success {
            background: #d1fae5;
            color: #047857;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .button {
            display: inline-block;
            background: #2563eb;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 20px;
        }
        .button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>XParking - Cài đặt hệ thống</h1>
        
        <?php echo $message; ?>
        
        <?php if ($success): ?>
            <p>Cài đặt hệ thống thành công! Bạn có thể bắt đầu sử dụng XParking.</p>
            <p>Tài khoản admin mặc định:</p>
            <ul>
                <li><strong>Tên đăng nhập:</strong> admin</li>
                <li><strong>Mật khẩu:</strong> admin123</li>
            </ul>
            <p><strong>Lưu ý:</strong> Hãy đổi mật khẩu admin ngay sau khi đăng nhập!</p>
            
            <a href="index.php" class="button">Đi đến trang chủ</a>
        <?php else: ?>
            <p>Có lỗi xảy ra trong quá trình cài đặt. Vui lòng kiểm tra lại thông tin.</p>
            <a href="setup.php" class="button">Thử lại</a>
        <?php endif; ?>
    </div>
</body>
</html>';
?>