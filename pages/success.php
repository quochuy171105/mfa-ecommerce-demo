<?php
// PHP logic của bạn được giữ nguyên
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/User.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

start_secure_session();

if (!isset($_SESSION['mfa_verified']) || $_SESSION['mfa_verified'] !== true) {
    header('Location: login.php');
    exit;
}

$auth = Auth::isAuthenticated();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$user = User::getUser($auth['user_id']);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập Thành Công</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <div class="checkmark"></div>
        </div>
        
        <h1>Đăng Nhập Thành Công!</h1>
        <p class="subtitle">Chào mừng bạn đã quay trở lại hệ thống.</p>
        
        <div class="user-info">
            <p>Email: <strong><?php echo htmlspecialchars($user['email']); ?></strong></p>
            <p>ID Người dùng: <strong>#<?php echo htmlspecialchars($user['id']); ?></strong></p>
            <p>Thời gian đăng nhập: <strong><?php echo date('H:i:s, d/m/Y'); ?></strong></p>
        </div>
        
        <div class="btn-group">
            <a href="logout.php" class="btn btn-primary">Về Trang Chủ</a>
            <a href="logout.php" class="btn btn-primary">Đăng Xuất</a>
        </div>
    </div>
</body>
</html>