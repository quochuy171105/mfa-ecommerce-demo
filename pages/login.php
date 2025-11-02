<?php
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
    <title>Nút Hiển Thị Mật Khẩu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e45b00;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
            --error-color: #e53e3e;
            --input-bg: #e0e5ec;
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

        .login-container {
            background: var(--bg-color);
            padding: 3rem 2.5rem;
            border-radius: 30px;
            box-shadow:
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            width: 100%;
            max-width: 440px;
            animation: fadeIn 0.6s ease-out;
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

        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .login-header h1 {
            color: var(--text-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow:
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }

        .login-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.8rem;
        }

        .form-input {
            width: 100%;
            padding: 15px 15px 15px 50px;
            background: var(--input-bg);
            border: none;
            border-radius: 15px;
            font-size: 1rem;
            color: var(--text-dark);
            box-shadow:
                inset 6px 6px 12px var(--shadow-dark),
                inset -6px -6px 12px var(--shadow-light);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light),
                0 0 0 3px rgba(100, 100, 100, 0.1);
        }

        .form-input::placeholder {
            color: var(--text-light);
            opacity: 0.7;
        }

        /* CSS cho icon ổ khóa và icon con mắt */
        .input-icon,
        .toggle-password {
            position: absolute;
            top: 45%;
            transform: translateY(-50%);
            color: #888;
        }

        /* Icon ổ khóa bên trái */
        .input-icon {
            left: 15px;
        }

        /* Nút con mắt bên phải */
        .toggle-password {
            right: 15px;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(145deg, #f0f0f0, #cacaca);
            color: var(--text-dark);
            border: none;
            border-radius: 15px;
            font-size: 1.05rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow:
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg,
                    transparent,
                    rgba(255, 255, 255, 0.3),
                    transparent);
            transition: left 0.5s;
        }

        .btn:hover:not(:disabled) {
            box-shadow:
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-2px);
        }

        .btn:hover:not(:disabled)::before {
            left: 100%;
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

        .error-message {
            background: var(--bg-color);
            color: var(--error-color);
            padding: 14px 18px;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            box-shadow:
                inset 3px 3px 6px rgba(229, 62, 62, 0.1),
                inset -3px -3px 6px var(--shadow-light),
                4px 4px 8px var(--shadow-dark);
            border-left: 4px solid var(--error-color);
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .form-footer a:hover {
            color: var(--secondary-color);
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Chào Mừng Trở Lại!</h1>
            <p>Đăng nhập để tiếp tục</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <span class="input-icon">📧</span>
                <input type="email"
                    id="email"
                    name="email"
                    class="form-input"
                    placeholder="Email"
                    required
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <span class="input-icon">🔒</span>
                <input type="password"
                    id="password"
                    name="password"
                    class="form-input"
                    placeholder="Mật khẩu"
                    required>
                <button type="button" class="toggle-password" id="togglePassword">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </div>
            <script>
                // Lấy các phần tử từ DOM
                const passwordInput = document.getElementById('password');
                const togglePasswordButton = document.getElementById('togglePassword');
                const icon = togglePasswordButton.querySelector('i');

                // Thêm sự kiện click cho nút
                togglePasswordButton.addEventListener('click', function() {
                    // Kiểm tra loại của ô input
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Thay đổi icon con mắt
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                });
            </script>

            <button type="submit" class="btn" id="loginBtn">Đăng Nhập</button>
        </form>

        <div class="form-footer">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>
    </div>
</body>

</html>