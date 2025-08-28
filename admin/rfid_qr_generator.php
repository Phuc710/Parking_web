<?php
// https://www.xparking.x10.mx/rfid_handler.php?rfid=CD290C73 
// đổi id của rfid ở đầy này ku
// admin/rfid_qr_generator.php - Tạo mã QR cho thẻ RFID
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Yêu cầu đăng nhập và quyền admin
require_login();
require_admin();

// Lấy danh sách RFID từ cơ sở dữ liệu
$rfids = [];
try {
    $stmt = $pdo->query("SELECT * FROM rfid_pool ORDER BY id ASC");
    $rfids = $stmt->fetchAll();
} catch (PDOException $e) {
    set_flash_message('error', 'Lỗi truy vấn cơ sở dữ liệu!');
    error_log("RFID query error: " . $e->getMessage());
}

// Xử lý thêm mới RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_rfid') {
        $uid = $_POST['uid'] ?? '';
        
        if (empty($uid)) {
            set_flash_message('error', 'Vui lòng nhập UID của thẻ RFID!');
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO rfid_pool (uid, status) VALUES (:uid, 'available')");
                $stmt->bindParam(':uid', $uid);
                $stmt->execute();
                
                set_flash_message('success', 'Thêm thẻ RFID thành công!');
                redirect('rfid_qr_generator.php');
            } catch (PDOException $e) {
                set_flash_message('error', 'Lỗi thêm thẻ RFID: ' . $e->getMessage());
                error_log("RFID add error: " . $e->getMessage());
            }
        }
    } elseif ($_POST['action'] === 'delete_rfid') {
        $id = $_POST['id'] ?? 0;
        
        if (!$id) {
            set_flash_message('error', 'ID không hợp lệ!');
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM rfid_pool WHERE id = :id");
                $stmt->bindParam(':id', $id);
                $stmt->execute();
                
                set_flash_message('success', 'Xóa thẻ RFID thành công!');
                redirect('rfid_qr_generator.php');
            } catch (PDOException $e) {
                set_flash_message('error', 'Lỗi xóa thẻ RFID: ' . $e->getMessage());
                error_log("RFID delete error: " . $e->getMessage());
            }
        }
    }
}

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XParking Admin - Tạo mã QR cho thẻ RFID</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        .qr-card {
            margin-bottom: 30px;
        }
        .badge-available {
            background-color: #10b981;
            color: white;
        }
        .badge-assigned {
            background-color: #f59e0b;
            color: white;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-parking"></i> XParking Admin
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../admin.php">Quay lại Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php?action=logout">Đăng xuất</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <?php
        // Display flash messages
        $flash = get_flash_message();
        if ($flash): 
        ?>
        <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show no-print">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="card mb-4 no-print">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-qrcode me-2"></i> Tạo mã QR cho thẻ RFID
            </div>
            <div class="card-body">
                <form action="rfid_qr_generator.php" method="post">
                    <input type="hidden" name="action" value="add_rfid">
                    
                    <div class="mb-3">
                        <label for="uid" class="form-label">UID thẻ RFID</label>
                        <input type="text" id="uid" name="uid" class="form-control" placeholder="Nhập UID của thẻ RFID" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Thêm thẻ RFID</button>
                </form>
            </div>
        </div>
        
        <?php if (!empty($rfids)): ?>
        <div class="text-end mb-3 no-print">
            <button onclick="window.print();" class="btn btn-primary">
                <i class="fas fa-print me-2"></i> In mã QR
            </button>
        </div>
        
        <div class="row">
            <?php foreach ($rfids as $rfid): 
                $rfid_url = SITE_URL . '/rfid_handler.php?rfid=' . urlencode($rfid['uid']);
                $status_class = $rfid['status'] === 'available' ? 'available' : 'assigned';
                $status_text = $rfid['status'] === 'available' ? 'Sẵn sàng' : 'Đã sử dụng';
                
                // Tạo QR code URL
                $qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($rfid_url);
            ?>
            <div class="col-md-4 qr-card">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <img src="<?php echo $qr_code_url; ?>" alt="QR Code" class="img-fluid mb-3" style="max-width: 200px;">
                        <h5 class="card-title">Thẻ RFID #<?php echo $rfid['id']; ?></h5>
                        <p class="card-text"><strong>UID:</strong> <?php echo $rfid['uid']; ?></p>
                        <p class="card-text">
                            <span class="badge badge-<?php echo $status_class; ?> rounded-pill"><?php echo $status_text; ?></span>
                        </p>
                        <div class="no-print mt-3">
                            <a href="<?php echo $rfid_url; ?>" target="_blank" class="btn btn-sm btn-primary">Kiểm tra</a>
                            <form action="rfid_qr_generator.php" method="post" class="d-inline" onsubmit="return confirm('Bạn có chắc chắn muốn xóa thẻ RFID này?');">
                                <input type="hidden" name="action" value="delete_rfid">
                                <input type="hidden" name="id" value="<?php echo $rfid['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Xóa</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <p>Chưa có thẻ RFID nào. Vui lòng thêm thẻ mới.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>