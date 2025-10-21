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

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực Khuôn Mặt</title>
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e57309;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
            --scan-color: #2196F3;
            --warning-color: #FF9800;
            --success-color: #48bb78;
            --error-color: #e53e3e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        /* Loader Overlay */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(224, 229, 236, 0.95);
            backdrop-filter: blur(8px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loader-content {
            text-align: center;
            background: var(--bg-color);
            padding: 3rem;
            border-radius: 30px;
            box-shadow: 
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
        }

        .loader {
            width: 70px;
            height: 70px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            background: var(--bg-color);
            box-shadow: 
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            position: relative;
            animation: loaderPulse 1.5s ease-in-out infinite;
        }

        .loader::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 50%;
            height: 50%;
            background: var(--primary-color);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            animation: spin 1s linear infinite;
        }

        @keyframes loaderPulse {
            0%, 100% {
                box-shadow: 
                    inset 4px 4px 8px var(--shadow-dark),
                    inset -4px -4px 8px var(--shadow-light);
            }
            50% {
                box-shadow: 
                    inset 6px 6px 12px var(--shadow-dark),
                    inset -6px -6px 12px var(--shadow-light);
            }
        }

        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .loader-content p {
            color: var(--text-dark);
            font-size: 1.1rem;
            font-weight: 600;
            text-shadow: 
                1px 1px 2px var(--shadow-dark),
                -1px -1px 2px var(--shadow-light);
        }

        /* Main Container */
        .face-container {
            background: var(--bg-color);
            border-radius: 30px;
            box-shadow: 
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            width: 100%;
            max-width: 950px;
            animation: fadeIn 0.6s ease-out;
            overflow: hidden;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .face-content {
            display: flex;
            flex-direction: row;
        }

        /* Video Section */
        .video-section {
            flex: 1.5;
            position: relative;
            background: var(--bg-color);
            padding: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .video-wrapper {
            position: relative;
            width: 100%;
            max-width: 500px;
            aspect-ratio: 4/3;
            background: var(--bg-color);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 
                inset 8px 8px 16px var(--shadow-dark),
                inset -8px -8px 16px var(--shadow-light);
        }

        #video, #canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 20px;
        }

        #canvas {
            z-index: 1;
            transition: opacity 0.2s ease;
        }
        
        /* Animation cho canvas khi chụp ảnh */
        @keyframes captureFlash {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }

        /* Controls Section */
        .controls-section {
            flex: 1;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .face-title {
            color: var(--text-dark);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-align: center;
            text-shadow: 
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }

        .face-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 1rem;
            filter: drop-shadow(2px 2px 4px var(--shadow-dark));
        }

        #message-container {
            min-height: 70px;
            margin-bottom: 2rem;
        }

        .message {
            padding: 14px 18px;
            border-radius: 15px;
            font-size: 0.9rem;
            line-height: 1.5;
            box-shadow: 
                4px 4px 8px var(--shadow-dark),
                -4px -4px 8px var(--shadow-light);
            border-left-width: 4px;
            border-left-style: solid;
        }

        .message.success {
            background: var(--bg-color);
            color: var(--success-color);
            border-color: var(--success-color);
        }

        .message.error {
            background: var(--bg-color);
            color: var(--error-color);
            border-color: var(--error-color);
        }

        .message.info {
            background: var(--bg-color);
            color: var(--scan-color);
            border-color: var(--scan-color);
        }

        .message.warning {
            background: var(--bg-color);
            color: var(--warning-color);
            border-color: var(--warning-color);
        }

        /* Buttons */
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .btn {
            width: 100%;
            padding: 16px;
            border: none;
            border-radius: 15px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: 
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
        }

        .btn:hover:not(:disabled) {
            box-shadow: 
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-2px);
        }

        .btn:active:not(:disabled) {
            box-shadow: 
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(145deg, #f0f0f0, #cacaca);
            color: var(--text-dark);
        }

        .btn-scan {
            background: linear-gradient(145deg, #f0f0f0, #cacaca);
            color: var(--scan-color);
        }

        .btn-secondary {
            background: var(--bg-color);
            color: var(--text-light);
            box-shadow: 
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
        }

        .btn .icon {
            font-size: 1.3rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .face-content {
                flex-direction: column;
            }

            .video-section {
                padding: 1.5rem;
            }

            .video-wrapper {
                max-width: 100%;
            }

            .controls-section {
                padding: 2rem 1.5rem;
            }

            .face-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="loader-overlay" id="loader-overlay">
        <div class="loader-content">
            <div class="loader"></div>
            <p id="loader-text">Đang xử lý...</p>
        </div>
    </div>

    <div class="face-container">
        <div class="face-content">
            <div class="video-section">
                <div class="video-wrapper">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="canvas"></canvas>
                </div>
            </div>

            <div class="controls-section">
                <div class="face-icon">👤</div>
                <h1 class="face-title">Xác Thực Gương Mặt</h1>

                <div id="message-container">
                    <?php if ($message === 'registered'): ?>
                        <div class="message success">✓ Đăng ký thành công! Giờ bạn có thể xác thực.</div>
                    <?php elseif (!$hasFace): ?>
                        <div class="message warning">⚠ Để bắt đầu, vui lòng đăng ký khuôn mặt của bạn.</div>
                    <?php else: ?>
                        <div class="message info">ℹ Vui lòng nhìn thẳng vào camera để xác thực.</div>
                    <?php endif; ?>
                </div>

                <div class="button-container">
                    <?php if (!$hasFace): ?>
                        <button id="registerFace" class="btn btn-primary">
                            <span class="icon">📝</span> Đăng Ký Khuôn Mặt
                        </button>
                    <?php else: ?>
                        <button id="scanFace" class="btn btn-scan">
                            <span class="icon">📷</span> Quét Gương Mặt
                        </button>
                        
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/face-api.min.js"></script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>