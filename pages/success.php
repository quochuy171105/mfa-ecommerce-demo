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
            --primary-color: #646464ff;
            --secondary-color: #e57309;
            --success-color: #4CAF50;
            --light-bg: #f8f9fa;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .success-container {
            background: white;
            padding: 2.5rem 3rem;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            text-align: center;
            max-width: 550px;
            width: 100%;
            transform: scale(0.95);
            opacity: 0;
            animation: fadeInScale 0.6s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
        }
        @keyframes fadeInScale {
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        .success-icon {
            width: 80px; height: 80px;
            margin: 0 auto 1.5rem;
            background: var(--success-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 20px rgba(76, 175, 80, 0.3);
            position: relative;
        }
        .checkmark {
            width: 25px; height: 50px;
            border: solid white;
            border-width: 0 8px 8px 0;
            transform: rotate(45deg);
            animation: drawCheck 0.4s 0.3s ease-out forwards;
            opacity: 0;
        }
        @keyframes drawCheck {
            from { height: 0; width: 0; opacity: 0; }
            to { height: 50px; width: 25px; opacity: 1; }
        }
        h1 {
            color: #222;
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 2rem;
        }
        .user-info {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2.5rem;
            text-align: left;
            border: 1px solid #e9ecef;
        }
        .user-info p {
            margin-bottom: 0.75rem; color: #555;
            display: flex; justify-content: space-between;
        }
        .user-info p:last-child { margin-bottom: 0; }
        .user-info strong { color: #333; }
        .btn-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .btn {
            padding: 14px 35px; border: none; border-radius: 50px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.3s ease; text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:hover { transform: translateY(-3px); box-shadow: 0 7px 20px rgba(102, 126, 234, 0.6); }
        .btn-secondary {
            background: white; color: var(--primary-color);
            border: 2px solid #f17407ff;
        }
        .btn-secondary:hover { background-color: var(--light-bg); border-color: #ccc; }
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