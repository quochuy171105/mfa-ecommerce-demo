<?php
// Form đăng ký: Collect email/pass, call User::register, redirect to login.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/User.php';

start_secure_session();

$error_message = '';
$success_message = '';

// Xử lý form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else {
        // Kiểm tra rate limit
        if (!check_rate_limit('register')) {
            $error_message = 'Bạn đã thực hiện quá nhiều lần đăng ký. Vui lòng thử lại sau 1 phút.';
        } else {
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Kiểm tra mật khẩu xác nhận
            if ($password !== $confirm_password) {
                $error_message = 'Mật khẩu xác nhận không khớp';
            } else {
                // Thực hiện đăng ký
                $result = User::register($email, $password);

                if ($result['success']) {
                    $success_message = $result['message'];
                    // Chờ 2 giây rồi chuyển hướng
                    header("refresh:2;url=login.php");
                } else {
                    $error_message = $result['message'];
                }
            }
        }
    }
}

// Tạo CSRF token mới
$csrf_token = gen_csrf();
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Ký - Auth System</title>
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e57309;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
            --error-color: #e53e3e;
            --success-color: #48bb78;
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

        .register-container {
            background: var(--bg-color);
            padding: 3rem 2.5rem;
            border-radius: 30px;
            box-shadow:
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            width: 100%;
            max-width: 460px;
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

        .register-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .register-header h1 {
            color: var(--text-dark);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow:
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }

        .register-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .form-group {
            margin-bottom: 1.8rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.6rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 15px;
            background: var(--bg-color);
            border: none;
            border-radius: 15px;
            font-size: 1rem;
            color: var(--text-dark);
            box-shadow:
                inset 6px 6px 12px var(--shadow-dark),
                inset -6px -6px 12px var(--shadow-light);
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light),
                0 0 0 3px rgba(100, 100, 100, 0.1);
        }

        .password-requirements {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: 0.6rem;
            line-height: 1.5;
            padding-left: 0.5rem;
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
            margin-top: 0.5rem;
        }

        .btn:hover {
            box-shadow:
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-2px);
        }

        .btn:active {
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            transform: translateY(0);
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

        .success-message {
            background: var(--bg-color);
            color: var(--success-color);
            padding: 14px 18px;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            box-shadow:
                inset 3px 3px 6px rgba(72, 187, 120, 0.1),
                inset -3px -3px 6px var(--shadow-light),
                4px 4px 8px var(--shadow-dark);
            border-left: 4px solid var(--success-color);
        }

        .login-link {
            text-align: center;
            margin-top: 2rem;
        }

        .login-link p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }

        .login-link a:hover {
            color: var(--secondary-color);
        }

        /* Validation styles */
        .form-group input.valid {
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light),
                0 0 0 2px rgba(72, 187, 120, 0.3);
        }

        .form-group input.invalid {
            box-shadow:
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light),
                0 0 0 2px rgba(229, 62, 62, 0.3);
        }
    </style>
</head>

<body>
    <div class="register-container">
        <div class="register-header">
            <h1>Đăng Ký</h1>
            <p>Tạo tài khoản mới</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email"
                    id="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="example@email.com"
                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Nhập mật khẩu">
                <div class="password-requirements">
                    Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu:</label>
                <input type="password"
                    id="confirm_password"
                    name="confirm_password"
                    required
                    autocomplete="new-password"
                    placeholder="Nhập lại mật khẩu">
            </div>

            <button type="submit" class="btn">Đăng Ký</button>
        </form>

        <div class="login-link">
            <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
        </div>
    </div>

    <script>
        // Kiểm tra mật khẩu khớp nhau real-time
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (confirmPassword) {
                if (password === confirmPassword) {
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                } else {
                    this.classList.remove('valid');
                    this.classList.add('invalid');
                }
            } else {
                this.classList.remove('valid', 'invalid');
            }
        });

        // Hiển thị yêu cầu mật khẩu real-time
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;

            if (password) {
                // Kiểm tra các yêu cầu
                const isValid = password.length >= 8 &&
                    /[A-Z]/.test(password) &&
                    /[a-z]/.test(password) &&
                    /[0-9]/.test(password);

                if (isValid) {
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                } else {
                    this.classList.remove('valid');
                    this.classList.add('invalid');
                }
            } else {
                this.classList.remove('valid', 'invalid');
            }

            // Cập nhật trạng thái xác nhận mật khẩu
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>

</html>