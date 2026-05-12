<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Kin Kin BA Tool</title>
    <link rel="stylesheet" href="/BA.Tool/assets/css/style.css?v=13">
</head>
<body class="standalone-shell">
    <div class="login-box">
        <div style="text-align:center;margin-bottom:18px;">
            <img src="https://kinkinlogistics.com/uploads/up/root/sitecontact/2023/06/13/20/58/kinkin-logistic-logo-01.png"
                 alt="KinKin Logistics" class="logo logo-login">
        </div>
        <h2>Đăng Nhập BA</h2>
        <div class="sub">Hệ thống quản lý yêu cầu — Kin Kin Logistics</div>

        <?php if(isset($error)): ?>
            <div style="background: var(--primary-soft); color: var(--danger-color); padding: 10px 14px; margin-bottom: 18px; border: 1px solid var(--primary-color); font-size: 0.88rem;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form action="?page=login" method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; margin-top:8px;">ĐĂNG NHẬP</button>
        </form>

        <div style="text-align:center; margin-top:20px; font-size:0.88rem; color:var(--text-secondary);">
            Chưa có tài khoản?
            <a href="?page=register" style="font-weight:600;">Đăng ký</a>
            <br><br>
            <a href="?page=public_form" style="color:var(--text-muted); font-size:0.85rem;">← Trở về trang gửi yêu cầu</a>
        </div>
    </div>
</body>
</html>
