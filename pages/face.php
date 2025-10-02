<?php
session_start();
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/FaceAuth.php';

// Kiểm tra đã login chưa
$auth = Auth::isAuthenticated();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$user_id = $auth['user_id'];
$hasFace = FaceAuth::hasFace($user_id);

// Xử lý message
$message = $_GET['message'] ?? '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực Khuôn Mặt</title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <h1>Xác Thực Khuôn Mặt</h1>
    
    <?php if ($message === 'registered'): ?>
        <div class="message success">Đăng ký thành công! Vui lòng quét khuôn mặt.</div>
    <?php endif; ?>
    
    <?php if (!$hasFace): ?>
        <!-- TH2: Chưa đăng ký -->
        <div class="message warning">Vui lòng đăng ký khuôn mặt của bạn.</div>
        <div class="button-container">
            <button id="registerFace">Đăng Ký Khuôn Mặt</button>
        </div>
    <?php else: ?>
        <!-- TH1: Đã đăng ký - chỉ hiện nút quét -->
        <div class="button-container">
            <button id="scanFace">Quét Khuôn Mặt</button>
        </div>
    <?php endif; ?>
    
    <div class="video-container">
        <video id="video" width="300" height="225" autoplay muted></video>
    </div>
    
    <script src="../assets/js/face-api.min.js"></script>
    <script src="../assets/js/face.js"></script>
</body>
</html>