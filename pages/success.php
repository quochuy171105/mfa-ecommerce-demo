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
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e57309;
            --success-color: #48bb78;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .success-container {
            background: var(--bg-color);
            padding: 3rem 2.5rem;
            border-radius: 30px;
            box-shadow:
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            text-align: center;
            max-width: 580px;
            width: 100%;
            transform: scale(0.9);
            opacity: 0;
            animation: fadeInScale 0.7s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        }

        @keyframes fadeInScale {
            to {
                transform: scale(1);
                opacity: 1;
            }
        }

        .success-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            background: var(--bg-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                10px 10px 20px var(--shadow-dark),
                -10px -10px 20px var(--shadow-light);
            position: relative;
            animation: iconPulse 2s ease-in-out infinite;
        }

        @keyframes iconPulse {

            0%,
            100% {
                box-shadow:
                    10px 10px 20px var(--shadow-dark),
                    -10px -10px 20px var(--shadow-light);
            }

            50% {
                box-shadow:
                    12px 12px 24px var(--shadow-dark),
                    -12px -12px 24px var(--shadow-light);
            }
        }

        .checkmark {
            width: 30px;
            height: 55px;
            border: solid var(--success-color);
            border-width: 0 8px 8px 0;
            transform: rotate(45deg);
            animation: drawCheck 0.5s 0.3s cubic-bezier(0.65, 0, 0.45, 1) forwards;
            opacity: 0;
            filter: drop-shadow(2px 2px 4px var(--shadow-dark));
        }

        @keyframes drawCheck {
            from {
                height: 0;
                width: 0;
                opacity: 0;
            }

            to {
                height: 55px;
                width: 30px;
                opacity: 1;
            }
        }

        h1 {
            color: var(--text-dark);
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            text-shadow:
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }

        .subtitle {
            color: var(--text-light);
            font-size: 1.1rem;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .user-info {
            background: var(--bg-color);
            padding: 2rem 1.8rem;
            border-radius: 20px;
            margin-bottom: 2.5rem;
            text-align: left;
            box-shadow:
                inset 6px 6px 12px var(--shadow-dark),
                inset -6px -6px 12px var(--shadow-light);
        }

        .user-info p {
            margin-bottom: 1rem;
            color: var(--text-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.95rem;
        }

        .user-info p:last-child {
            margin-bottom: 0;
        }

        .user-info strong {
            color: var(--text-dark);
            font-weight: 600;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: var(--bg-color);
            color: var(--text-dark);
            box-shadow:
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
        }

        .btn-primary:hover {
            box-shadow:
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-3px);
        }

        .btn-primary:active {
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            transform: translateY(0);
        }

        .btn-secondary {
            background: var(--bg-color);
            color: var(--primary-color);
            box-shadow:
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
        }

        .btn-secondary:hover {
            box-shadow:
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-3px);
            color: var(--secondary-color);
        }

        .btn-secondary:active {
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            transform: translateY(0);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>

<body>
    <div class="success-container">
        <div class="success-icon">
            <div class="checkmark"></div>
        </div>

        <h1>Đăng Nhập Thành Công!</h1>
        <p class="subtitle">Chào mừng bạn đã quay trở lại hệ thống.</p>

        <div class="user-info">
            <p>
                <span>Email:</span>
                <strong><?php echo htmlspecialchars($user['email']); ?></strong>
            </p>
            <p>
                <span>ID Người dùng:</span>
                <strong>#<?php echo htmlspecialchars($user['id']); ?></strong>
            </p>
            <p>
                <span>Thời gian đăng nhập:</span>
                <strong><?php echo date('H:i:s, d/m/Y'); ?></strong>
            </p>
        </div>

        <div class="btn-group">
            <a href="logout.php" class="btn btn-primary">Về Trang Chủ</a>
            <a href="logout.php" class="btn btn-secondary">Đăng Xuất</a>
        </div>
    </div>
</body>

</html>