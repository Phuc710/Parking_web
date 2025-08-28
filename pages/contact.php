<?php
?>
<div class="container" style="margin: 3rem auto;">
    <!-- Page Title -->
    <div style="text-align: center; margin-bottom: 3rem;">
        <h1 style="color: var(--primary); margin-bottom: 0.5rem; font-size: 2.5rem; font-weight: 600;">Liên hệ với chúng tôi</h1>
        <p style="color: var(--gray); font-size: 1.1rem; max-width: 600px; margin: 0 auto;">
            Chúng tôi luôn sẵn sàng hỗ trợ bạn. Hãy liên hệ với chúng tôi qua thông tin bên dưới hoặc gửi tin nhắn trực tiếp.
        </p>
    </div>

    <div style="display: flex; flex-wrap: wrap; max-width: 970px; margin: 0 auto; gap: 2rem; align-items: flex-start;">
        <!-- Contact Form -->
        <div style="flex: 1; min-width: 300px; order: 1;">
            <div class="form-container" style="margin: 0;">
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-map-marker-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Địa chỉ
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        <a href="https://maps.app.goo.gl/SRkoVjBhkZTnbuEc9" 
                           target="_blank" 
                           style="color: var(--gray); text-decoration: none;"
                           onmouseover="this.style.color='var(--primary)'" 
                           onmouseout="this.style.color='var(--gray)'">
                           Đ. Nguyễn Kiệm/371 Đ. Hạnh Thông, Phường, Gò Vấp, Hồ Chí Minh 700000, Việt Nam
                        </a>
                        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d3918.8848295951193!2d106.6755987!3d10.8201249!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31752918602fcba5%3A0x2599dd3bc2b48244!2zR0RVIC0gVFLGr-G7nE5HIMSQ4bqgSSBI4buMQyBHSUEgxJDhu4pOSCBUUEhDTQ!5e0!3m2!1svi!2s!4v1756265989840!5m2!1svi!2s" width="380" height="220" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>                     
                    </p>                   
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-phone" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Điện thoại
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        <a href="tel:02812345678" style="color: var(--gray);">(028) 1234 5678</a>
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-envelope" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Email
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        support@xparking.x10.mx
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-clock" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Giờ làm việc
                    </h3>
                    <p style="color: var(--gray); margin-left: 1.5rem;">
                        Thứ Hai - Thứ Sáu: 8:00 - 17:30<br>
                        Thứ Bảy - Chủ Nhật: 8:00 - 12:00
                    </p>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <h3 style="margin-bottom: 0.5rem; display: flex; align-items: center;">
                        <i class="fas fa-share-alt" style="color: var(--primary); margin-right: 0.5rem;"></i> 
                        Kết nối với chúng tôi
                    </h3>
                    <div style="font-size: 1.5rem; margin-left: 1.5rem;">
                        <a href="#" style="color: var(--primary); margin-right: 1rem;"><i class="fab fa-facebook"></i></a>                        
                        <a href="#" style="color: var(--primary); margin-right: 1rem;"><i class="fa-brands fa-x-twitter"></i></a>                        
                        <a href="#" style="color: var(--primary); margin-right: 1rem;"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Contact Form -->
        <div style="flex: 1; min-width: 300px;">
            <div class="form-container">
                <h2 class="form-title">Gửi tin nhắn cho chúng tôi</h2>
                
                <form id="contactForm" action="#" method="post">
                    <div class="form-group">
                        <label for="userName" class="form-label">Họ và tên</label>
                        <input type="text" id="userName" name="userName" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userEmail" class="form-label">Email</label>
                        <input type="email" id="userEmail" name="userEmail" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="userPhone" class="form-label">Số điện thoại</label>
                        <input type="tel" id="userPhone" name="userPhone" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label for="subject" class="form-label">Chủ đề</label>
                        <select id="subject" name="subject" class="form-control">
                            <option value="">-- Chọn chủ đề --</option>
                            <option value="Thông tin chung">Thông tin chung</option>
                            <option value="Đặt chỗ">Đặt chỗ</option>
                            <option value="Hỗ trợ kỹ thuật">Hỗ trợ kỹ thuật</option>
                            <option value="Thanh toán">Thanh toán</option>
                            <option value="Khác">Khác</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="message" class="form-label">Nội dung tin nhắn</label>
                        <textarea rows="5" id="message" name="message" class="form-control" required></textarea>
                    </div>
                    
                    <button type="submit" id="submitBtn" class="btn btn-primary form-btn">Gửi tin nhắn</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission
    document.getElementById('contactForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;
        
        // Show loading state
        submitBtn.innerHTML = '<span class="spinner"></span>Đang gửi...';
        submitBtn.disabled = true;
        
        // Get form data
        const formData = new FormData();
        formData.append('from_name', document.getElementById('userName').value);
        formData.append('from_email', document.getElementById('userEmail').value);
        formData.append('phone', document.getElementById('userPhone').value);
        formData.append('subject', document.getElementById('subject').value || 'Liên hệ từ website');
        formData.append('message', document.getElementById('message').value);
        
        // Send to PHP backend
        fetch('/pages/send_email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                // Handle HTTP errors
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                new Noty({
                    text: `Cảm ơn ${document.getElementById('userName').value}! Tin nhắn của bạn đã được gửi.`,
                    theme: 'mint',
                    type: 'success',
                    layout: 'topRight',
                    progressBar: true,
                    animation: {
                        open: 'animated fadeInRight', 
                        close: 'animated fadeOutRight'
                    },
                    timeout: 3000
                }).show();
                document.getElementById('contactForm').reset();
            } else {
                new Noty({
                    text: data.message || 'Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại sau.',
                    theme: 'mint',
                    type: 'error',
                    layout: 'topRight',
                    animation: {
                        open: 'animated fadeInRight',
                        close: 'animated fadeOutRight'
                    },
                    timeout: 5000
                }).show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Gửi thất bại!',
                text: 'Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại sau hoặc liên hệ trực tiếp qua email support@xparking.x10.mx.',
                icon: 'error',
                confirmButtonText: 'OK',
                confirmButtonColor: '#ef4444'
            });
        })
        .finally(() => {
            // Reset button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        });
    });
});
</script>