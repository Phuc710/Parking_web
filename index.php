<?php
// Main index file - Entry point for the application
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Handle login/logout actions
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                
                if (login_user($username, $password)) {
                    // Redirect to dashboard after login
                    redirect(is_admin() ? 'admin.php' : 'dashboard.php');
                } else {
                    set_flash_message('error', 'Tên đăng nhập hoặc mật khẩu không đúng!');
                    redirect('index.php?page=login');
                }
            }
            break;
            
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                $email = $_POST['email'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $phone = $_POST['phone'] ?? '';
                
                // Basic validation
                if (empty($username) || empty($password) || empty($email) || empty($full_name)) {
                    set_flash_message('error', 'Vui lòng điền đầy đủ thông tin!');
                    redirect('index.php?page=register');
                }
                
                if ($password !== $confirm_password) {
                    set_flash_message('error', 'Mật khẩu xác nhận không khớp!');
                    redirect('index.php?page=register');
                }
                
                $result = register_user($username, $password, $email, $full_name, $phone);
                
                if ($result['success']) {
                    set_flash_message('success', $result['message']);
                    redirect('index.php?page=login');
                } else {
                    set_flash_message('error', $result['message']);
                    redirect('index.php?page=register');
                }
            }
            break;
            
        case 'logout':
            logout_user();
            set_flash_message('success', 'Đăng xuất thành công!');
            redirect('index.php');
            break;
    }
}

// Determine which page to display
$page = $_GET['page'] ?? 'home';

// HTML Header
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>X Parking</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="/Logo.png" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            max-width: 1200px;
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
            align-items: center;
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
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        /* Hero button styles - improved visibility */
        .hero .btn-primary {
            background-color: var(--white);
            color: var(--primary);
            border: 2px solid var(--white);
        }
        
        .hero .btn-primary:hover {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .hero .btn-outline {
            background-color: transparent;
            border: 2px solid var(--white);
            color: var(--white);
        }
        
        .hero .btn-outline:hover {
            background-color: var(--white);
            color: var(--primary);
        }
        
        .hero {
            padding: 4rem 0;
            background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);
            color: var(--white);
            text-align: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .features {
            padding: 4rem 0;
            background-color: var(--white);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .form-container {
            max-width: 500px;
            margin: 2rem auto;
            background-color: var(--white);
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        
        .form-title {
            text-align: center;
            margin-bottom: 1.5rem;
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
        
        .form-text {
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--gray);
        }
        
        .form-btn {
            width: 100%;
            padding: 0.75rem;
            font-size: 1rem;
            margin-top: 1rem;
        }     
        
        /* Modern popup styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        
        .popup-content {
            background-color: var(--white);
            border-radius: 0.75rem;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: popupSlideIn 0.3s ease-out;
        }
        
        @keyframes popupSlideIn {
            from {
                transform: scale(0.8) translateY(-20px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }
        
        .popup-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .popup-icon.success {
            color: var(--success);
        }
        
        .popup-icon.error {
            color: var(--danger);
        }
        
        .popup-title {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .popup-message {
            color: var(--gray);
            margin-bottom: 1.5rem;
        }
        
        .popup-btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .popup-btn.primary {
            background-color: var(--primary);
            color: var(--white);
        }
        
        .popup-btn.primary:hover {
            background-color: var(--primary-dark);
        }
        
        .footer {
            background-color: var(--dark);
            color: var(--light);
            padding: 3rem 0 1rem;
            margin-top: 2rem;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-section {
            margin-bottom: 1rem;
        }
        
        .footer-title {
            font-size: 1.25rem;
            margin-bottom: 1rem;
            color: var(--white);
            font-weight: 600;
        }
        
        .footer-section p {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-link {
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }
        
        .footer-link i {
            margin-right: 0.5rem;
            width: 20px;
            color: var(--primary);
        }
        
        .footer-link a {
            color: rgba(255, 255, 255, 0.8);
            transition: color 0.3s;
        }
        
        .footer-link a:hover {
            color: var(--white);
            text-decoration: none;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .social-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s;
            
        }
        
        .social-link:hover {
            background-color: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
            text-decoration: none;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
        }
        
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-menu {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .nav-item {
                margin-left: 0.5rem;
                margin-right: 0.5rem;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .footer-container {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .footer-link {
                justify-content: center;
            }
            
            .social-links {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Modern Popup -->
    

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
                    <a href="index.php?page=about" class="nav-link">Giới thiệu</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=services" class="nav-link">Dịch vụ</a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=contact" class="nav-link">Liên hệ</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a href="dashboard.php" class="btn btn-primary">Bảng điều khiển</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?action=logout" class="btn btn-outline">Đăng xuất</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a href="index.php?page=login" class="btn btn-primary">Đăng nhập</a>
                    </li>
                    <li class="nav-item">
                        <a href="index.php?page=register" class="btn btn-outline">Đăng ký</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </header>
    
    <main>    
        <?php
        // Hiển thị thông báo flash bằng SweetAlert2
        $flash = get_flash_message();
        if ($flash):
            $title = $flash['type'] === 'success' ? "Thành công!" : "Thất bại!";
            $icon = $flash['type'] === 'success' ? "success" : "error";
            $message = addslashes($flash['message']);
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const flashType = '<?php echo $flash['type']; ?>';
                const title = flashType === 'success' ? "Thành công!" : "Thất bại!";
                const icon = flashType === 'success' ? "success" : "error";
                const message = '<?php echo addslashes($flash['message']); ?>';

                let options = {
                    title: title,
                    text: message,
                    icon: icon,
                    confirmButtonText: "OK",
                    confirmButtonColor: '#2563eb',
                    draggable: false,
                    allowOutsideClick: false
                };
                
                // Tùy chỉnh riêng cho thông báo thành công
                if (flashType === 'success') {
                    options.showConfirmButton = false;
                    options.timer = 2500;
                    options.timerProgressBar = true;
                }

                Swal.fire(options);
            });
        </script>
        <?php endif; ?>
        <?php
        // Load the appropriate page content
        switch ($page) {
            case 'login':
                include 'pages/login.php';
                break;
                
            case 'register':
                include 'pages/register.php';
                break;
                
            case 'about':
                include 'pages/about.php';
                break;
                
            case 'services':
                include 'pages/services.php';
                break;
                
            case 'contact':
                include 'pages/contact.php';
                break;
                
            default:
                // Home page
                ?>
                <!-- Hero Section -->
                <section class="hero">
                    <div class="container">
                        <h1>Hệ thống đỗ xe thông minh XParking</h1>
                        <p>Giải pháp quản lý bãi đỗ xe hiện đại, tiện lợi và an toàn với công nghệ nhận diện biển số tự động.</p>
                        <div>
                            <a href="index.php?page=register" class="btn btn-primary">Đăng ký ngay</a>
                            <a href="index.php?page=services" class="btn btn-outline" style="margin-left: 1rem;">Tìm hiểu thêm</a>
                        </div>
                    </div>
                </section>
                
                <!-- Features Section -->
                <section class="features">
                    <div class="container">
                        <h2 class="section-title">Tính năng nổi bật</h2>
                        
                        <div class="feature-grid">
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <h3>Nhận diện biển số tự động</h3>
                                <p>Hệ thống camera thông minh nhận diện biển số xe tự động, giúp quá trình vào/ra bãi đỗ xe nhanh chóng và thuận tiện.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <h3>Thanh toán QR Code</h3>
                                <p>Thanh toán nhanh chóng và an toàn bằng mã QR qua SePay, hỗ trợ đa dạng ngân hàng và ví điện tử.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <h3>Đặt chỗ trước</h3>
                                <p>Tiết kiệm thời gian bằng cách đặt chỗ đỗ xe trước, đảm bảo luôn có chỗ khi bạn cần.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-bell"></i>
                                </div>
                                <h3>Thông báo thời gian thực</h3>
                                <p>Nhận thông báo kịp thời về tình trạng xe, thời gian đỗ và thanh toán qua email.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <h3>An ninh tối đa</h3>
                                <p>Hệ thống giám sát 24/7 với cảm biến thông minh, đảm bảo an toàn cho phương tiện của bạn.</p>
                            </div>
                            
                            <div class="feature-card">
                                <div class="feature-icon">
                                    <i class="fas fa-tachometer-alt"></i>
                                </div>
                                <h3>Bảng điều khiển trực quan</h3>
                                <p>Theo dõi lịch sử đỗ xe, thanh toán và quản lý tài khoản dễ dàng qua bảng điều khiển người dùng.</p>
                            </div>
                        </div>
                    </div>
                </section>
                <?php
                break;
        }
        ?>
    </main>
    <?php
    if (in_array($page, ['home', 'about', 'services'])): ?>
        <!-- Footer -->
        <footer class="footer">
            <div class="container footer-container">
                <div class="footer-section">
                    <h3 class="footer-title">Mô tả</h3>
                    <p>Hệ thống đỗ xe thông minh với công nghệ hiện đại, thanh toán tự động và quản lý hiệu quả. Chúng tôi mang đến trải nghiệm đỗ xe thuận tiện và an toàn nhất cho bạn.</p>
                </div>

                <div class="footer-section">
                    <h3 class="footer-title">Liên hệ</h3>
                    <ul class="footer-links">
                        <li class="footer-link">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Đ. Nguyễn Kiệm/371 Đ. Hạnh Thông, Gò Vấp, HCM</span>
                        </li>
                        <li class="footer-link">
                            <i class="fas fa-phone"></i>
                            <a href="tel:02812345678">(028) 1234 5678</a>
                        </li>
                        <li class="footer-link">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:tp710@gmail.com">tp710@gmail.com</a>
                        </li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3 class="footer-title">Liên kết nhanh</h3>
                    <ul class="footer-links">
                        <li class="footer-link"><a href="index.php">Trang chủ</a></li>
                        <li class="footer-link"><a href="index.php?page=about">Giới thiệu</a></li>
                        <li class="footer-link"><a href="index.php?page=services">Dịch vụ</a></li>
                        <li class="footer-link"><a href="index.php?page=contact">Liên hệ</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3 class="footer-title">Kết nối với chúng tôi</h3>
                    <p>Theo dõi chúng tôi trên mạng xã hội để cập nhật thông tin mới nhất</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fa-brands fa-x-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="container">
                    <p>&copy; 2025 XParking. Được phát triển bởi <a href="https://github.com/Phuc710" target="_blank" style="text-decoration: none">Phucx</a> ❤️.</p>
                </div>
            </div>
        </footer>
    <?php endif; ?>

    <!-- JavaScript -->
    <script>
        // Modern popup functions
        
        // Form validation with modern popup
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            
            forms.forEach(form => {
                form.addEventListener('submit', function(event) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    let emptyFields = [];
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            field.style.borderColor = '#ef4444';
                            isValid = false;
                            const label = form.querySelector(`label[for="${field.id}"]`)?.textContent || field.placeholder || 'Trường này';
                            emptyFields.push(label);
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!isValid) {
                        event.preventDefault();
                        showPopup('error', 'Thông tin chưa đầy đủ!', 'Vui lòng điền đầy đủ các trường bắt buộc.');
                        return;
                    }
                    
                    // Password match validation for registration
                    if (form.id === 'registerForm') {
                        const password = form.querySelector('#password');
                        const confirmPassword = form.querySelector('#confirm_password');
                        
                        if (password && confirmPassword && password.value !== confirmPassword.value) {
                            confirmPassword.style.borderColor = '#ef4444';
                            event.preventDefault();
                            showPopup('error', 'Mật khẩu không khớp!', 'Mật khẩu xác nhận phải giống với mật khẩu đã nhập.');
                            return;
                        }
                        
                        // Email validation
                        const email = form.querySelector('#email');
                        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (email && !emailPattern.test(email.value)) {
                            email.style.borderColor = '#ef4444';
                            event.preventDefault();
                            showPopup('error', 'Email không hợp lệ!', 'Vui lòng nhập địa chỉ email đúng định dạng.');
                            return;
                        }
                        
                        // Password strength validation
                        if (password && password.value.length < 6) {
                            password.style.borderColor = '#ef4444';
                            event.preventDefault();
                            showPopup('error', 'Mật khẩu quá yếu!', 'Mật khẩu phải có ít nhất 6 ký tự.');
                            return;
                        }
                    }
                });
            });
            
            // Show popup for flash messages
            <?php if ($flash): ?>
                showPopup('<?php echo $flash['type']; ?>', 
                         <?php echo $flash['type'] === 'success' ? "'Thành công!'" : "'Thất bại!'"; ?>, 
                         '<?php echo addslashes($flash['message']); ?>');
            <?php endif; ?>
        });
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Mobile menu toggle (if needed for future mobile improvements)
        const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
        const navMenu = document.querySelector('.nav-menu');
        
        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', function() {
                navMenu.classList.toggle('show');
            });
        }
        
        // Form field focus effects
        document.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = '#ef4444';
                } else {
                    this.style.borderColor = '';
                }
            });
        });
    
               
    </script>
</body>
</html>