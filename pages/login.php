<?php
// PHP logic của bạn được giữ nguyên
ob_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';

start_secure_session();

$error_message = '';
$success_message = '';

if (Auth::isAuthenticated()) {
    header('Location: mfa.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else {
        if (!check_rate_limit('login')) {
            $error_message = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 1 phút.';
            error_log('Rate limit exceeded for login from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        } else {
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error_message = 'Vui lòng nhập đầy đủ thông tin';
            } else {
                $result = Auth::login($email, $password);
                
                if ($result['success']) {
                    $success_message = $result['message'];
                    session_write_close();
                    header('Location: mfa.php');
                    exit;
                } else {
                    $error_message = $result['message'];
                    error_log("Failed login attempt for: $email from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                }
            }
        }
    }
}

$csrf_token = gen_csrf();
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập</title>
    <style>
        :root {
            --primary-color: #646464ff;
            --secondary-color: #e45b00ff;
            --dark-color: #333;
            --light-color: #f4f4f4;
            --error-color: #f44336;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 420px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header { text-align: center; margin-bottom: 2rem; }
        .login-header h1 { color: var(--dark-color); margin-bottom: 0.5rem; }
        .login-header p { color: #666; }
        .form-group { position: relative; margin-bottom: 1.5rem; }
        .form-input {
            width: 100%;
            padding: 12px 12px 12px 40px; /* Thêm padding trái cho icon */
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }
        .input-icon {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #aaa;
            transition: color 0.3s;
        }
        .form-input:focus + .input-icon { color: var(--primary-color); }
        .btn {
            width: 100%; padding: 15px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
            position: relative;
        }
        .btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .error-message {
            background: #ffebee; color: var(--error-color);
            padding: 12px; border-radius: 8px; margin-bottom: 1.5rem;
            border-left: 5px solid var(--error-color);
        }
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #666;
            font-size: 0.9rem;
        }
        .form-footer a { color: var(--primary-color); text-decoration: none; font-weight: 600; }
        .form-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Chào Mừng Trở Lại!</h1>
            <p>Đăng nhập để tiếp tục</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <input type="email" id="email" name="email" class="form-input" placeholder="Email" required>
                <span class="input-icon">📧</span>
            </div>
            <div class="form-group">
                <input type="password" id="password" name="password" class="form-input" placeholder="Mật khẩu" required>
                <span class="input-icon">🔒</span>
            </div>
            <button type="submit" class="btn" id="loginBtn">Đăng Nhập</button>
        </form>
        <div class="form-footer">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>
    </div>
</body>
</html>