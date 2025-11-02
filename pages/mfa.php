<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';

start_secure_session();

// Xử lý logout trước tiên
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header('Location: login.php');
    exit;
}

// BƯỚC 1: Kiểm tra xem người dùng đã đăng nhập chưa. Nếu chưa, về trang login.
$auth = Auth::isAuthenticated();
if (!$auth) {
    header('Location: login.php');
    exit;
}

// BƯỚC 2: Kiểm tra xem người dùng đã xác thực MFA chưa. Nếu rồi, vào trang success.
if (isset($_SESSION['mfa_verified']) && $_SESSION['mfa_verified'] === true) {
    header('Location: success.php');
    exit;
}

// Nếu code chạy đến đây, nghĩa là người dùng đã đăng nhập nhưng chưa xác thực MFA.
// Chúng ta sẽ hiển thị các lựa chọn.
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else {
        $mfa_type = sanitize_input($_POST['mfa_type'] ?? '');
        if (in_array($mfa_type, ['otp', 'face'])) {
            $_SESSION['mfa_type'] = $mfa_type;
            // Chuyển hướng đến đúng trang của phương thức đã chọn
            header("Location: {$mfa_type}.php");
            exit;
        } else {
            $error_message = 'Vui lòng chọn một phương thức xác thực hợp lệ.';
        }
    }
}

$csrf_token = gen_csrf();
$user_email = $_SESSION['email'] ?? ($auth['email'] ?? '');
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chọn Phương Thức Xác Thực</title>
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e05d0b;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
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

        .mfa-container {
            background: var(--bg-color);
            padding: 3rem 2.5rem;
            border-radius: 30px;
            box-shadow:
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            width: 100%;
            max-width: 520px;
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

        .mfa-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .mfa-header h1 {
            color: var(--text-dark);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            text-shadow:
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }

        .mfa-header p {
            color: var(--text-light);
            line-height: 1.6;
        }

        .mfa-header p strong {
            color: var(--primary-color);
            font-weight: 600;
        }

        .mfa-options {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .mfa-option {
            position: relative;
            cursor: pointer;
        }

        .mfa-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }

        .option-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.8rem;
            background: var(--bg-color);
            border-radius: 20px;
            box-shadow:
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
            transition: all 0.3s ease;
        }

        .mfa-option:hover .option-content {
            box-shadow:
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-2px);
        }

        .mfa-option input[type="radio"]:checked+.option-content {
            box-shadow:
                inset 6px 6px 12px var(--shadow-dark),
                inset -6px -6px 12px var(--shadow-light);
            transform: translateY(0);
        }

        .option-icon {
            font-size: 2.5rem;
            min-width: 60px;
            text-align: center;
            filter: drop-shadow(2px 2px 4px var(--shadow-dark));
        }

        .option-details h3 {
            color: var(--text-dark);
            font-size: 1.15rem;
            margin-bottom: 0.4rem;
            font-weight: 600;
        }

        .option-details p {
            color: var(--text-light);
            font-size: 0.9rem;
            line-height: 1.5;
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

        .logout-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .logout-link a {
            color: var(--text-light);
            font-size: 0.9rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .logout-link a:hover {
            color: var(--primary-color);
        }
    </style>
</head>

<body>
    <div class="mfa-container">
        <div class="mfa-header">
            <h1>Yêu Cầu Xác Thực Bổ Sung</h1>
            <p>Tài khoản <strong><?php echo htmlspecialchars($user_email); ?></strong> cần thêm một bước để đăng nhập.</p>
        </div>

        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="mfa.php" id="mfaForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="mfa-options">
                <label class="mfa-option">
                    <input type="radio" name="mfa_type" value="otp" required>
                    <div class="option-content">
                        <span class="option-icon">✉️</span>
                        <div class="option-details">
                            <h3>Mã OTP qua Email</h3>
                            <p>Nhận mã xác thực 6 số dùng một lần.</p>
                        </div>
                    </div>
                </label>

                <label class="mfa-option">
                    <input type="radio" name="mfa_type" value="face" required>
                    <div class="option-content">
                        <span class="option-icon">👤</span>
                        <div class="option-details">
                            <h3>Nhận diện khuôn mặt</h3>
                            <p>Sử dụng camera để xác thực nhanh chóng.</p>
                        </div>
                    </div>
                </label>
            </div>

            <button type="submit" class="btn" id="continueBtn" disabled>Tiếp Tục</button>
        </form>

        <div class="logout-link">
            <a href="?action=logout">Đây không phải tôi? Đăng xuất</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const radioButtons = document.querySelectorAll('input[name="mfa_type"]');
            const continueBtn = document.getElementById('continueBtn');

            radioButtons.forEach(radio => {
                radio.addEventListener('change', () => {
                    continueBtn.disabled = false;
                });
            });

            document.getElementById('mfaForm').addEventListener('submit', () => {
                continueBtn.disabled = true;
                continueBtn.textContent = 'Đang chuyển hướng...';
            });
        });
    </script>
</body>

</html>