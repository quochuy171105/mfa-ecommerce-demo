<?php
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/FaceAuth.php';

$title = 'Xác Thực Khuôn Mặt';
require_once __DIR__ . '/../includes/header.php';

$auth = Auth::isAuthenticated();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$user_id = $auth['user_id'];
$hasFace = FaceAuth::hasFace($user_id);
$message = $_GET['message'] ?? '';
?>
<div class="loader-overlay" id="loader-overlay">
    <div class="loader-content">
        <div class="loader"></div>
        <p id="loader-text">Đang xử lý...</p>
    </div>
</div>
<link rel="stylesheet" href="../../assets/css/main.css">
<div class="modern-face-container">
    <div class="video-wrapper">
        <video id="video" width="480" height="360" autoplay muted playsinline></video>
        <canvas id="canvas"></canvas>
    </div>
    
    <div class="controls-wrapper">
        <h1 class="face-title">Xác Thực Gương Mặt</h1>
        
        <div id="message-container">
        <?php if ($message === 'registered'): ?>
            <div class="message success">Đăng ký thành công! Giờ bạn có thể xác thực.</div>
        <?php elseif (!$hasFace): ?>
            <div class="message warning">Để bắt đầu, vui lòng đăng ký khuôn mặt của bạn.</div>
        <?php else: ?>
            <div class="message info">Vui lòng nhìn thẳng vào camera để xác thực.</div>
        <?php endif; ?>
        </div>
        
        <div class="button-container">
        <?php if (!$hasFace): ?>
            <button id="registerFace" class="btn btn-primary">Đăng Ký</button>
        <?php else: ?>
            <button id="scanFace" class="btn btn-scan">Quét Gương Mặt</button>
            <button id="registerAgain" class="btn btn-secondary">Đăng ký lại</button>
        <?php endif; ?>
        </div>
    </div>
</div>

<script src="../assets/js/face-api.min.js"></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>