<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Kin Kin BA Tool</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/style.css?v=4">
</head>
<body class="standalone-shell">
    <div class="login-box">
        <h2>Tạo Tài Khoản BA</h2>
        <div class="sub">Hệ thống quản lý yêu cầu — Kin Kin Logistics</div>

        <?php if(isset($error)): ?>
            <div style="background: var(--primary-soft); color: var(--danger-color); padding: 10px 14px; margin-bottom: 18px; border: 1px solid var(--primary-color); font-size: 0.88rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="?page=register" method="POST">
            <div class="form-group">
                <label>Họ và Tên</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Tên (nickname) <small style="color:var(--text-muted);">— sẽ ghi vào Google Sheet</small></label>
                <input type="text" name="nickname" class="form-control" placeholder="vd: Minh, Phương Anh">
            </div>
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:8px;">ĐĂNG KÝ</button>
        </form>

        <div style="text-align:center; margin-top:20px; font-size:0.88rem; color:var(--text-secondary);">
            Đã có tài khoản? <a href="?page=login" style="font-weight:600;">Đăng nhập</a>
        </div>
    </div>
</body>
</html>
