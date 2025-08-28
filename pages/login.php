<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        line-height: 1.6;
        color: var(--dark);
        background-color: #e9e9e9;
    }
</style>
<div class="container" style="margin: 3rem auto;">
    <div class="form-container">
        <h2 class="form-title">Đăng nhập</h2>
        
        <form action="index.php?action=login" method="post">
            <div class="form-group">
                <label for="username" class="form-label">Tên đăng nhập</label>
                <input type="text" id="username" name="username" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Mật khẩu</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary form-btn">Đăng nhập</button>
        </form>
        
        <div style="text-align: center; margin-top: 1rem;">
            <p>Chưa có tài khoản? <a href="index.php?page=register">Đăng ký ngay</a></p>
        </div>
    </div>
</div>