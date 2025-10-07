<?php
// THAY THẾ TOÀN BỘ CODE PHP CŨ BẰNG ĐOẠN NÀY
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
// Đây chính là phần logic bị lỗi trước đây.
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
            --primary-color: #646464ff;
            --secondary-color: #e05d0bff;
            --light-gray: #f8f9fa;
            --dark-gray: #333;
            --text-gray: #666;
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
        .mfa-container {
            background: white;
            padding: 2.5rem;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.5s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .mfa-header { text-align: center; margin-bottom: 2rem; }
        .mfa-header h1 { color: var(--dark-gray); margin-bottom: 0.5rem; }
        .mfa-header p { color: var(--text-gray); }
        .mfa-header p strong { color: var(--primary-color); }
        .mfa-options { display: flex; flex-direction: column; gap: 1rem; }
        .mfa-option {
            position: relative;
            border: 2px solid #eee;
            border-radius: 10px;
            transition: all 0.2s;
            cursor: pointer;
        }
        .mfa-option:hover { border-color: var(--primary-color); }
        .mfa-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 100%; height: 100%;
            cursor: pointer;
        }
        .mfa-option input[type="radio"]:checked + .option-content {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2);
        }
        .option-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            padding: 1.5rem;
            border: 2px solid transparent;
            border-radius: 8px;
        }
        .option-icon { font-size: 2.5rem; color: var(--primary-color); }
        .option-details h3 { color: var(--dark-gray); margin-bottom: 0.25rem; }
        .option-details p { color: var(--text-gray); font-size: 0.9rem; }
        .btn {
            width: 100%; padding: 15px; margin-top: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white; border: none; border-radius: 8px;
            font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .btn:disabled { background: #ccc; cursor: not-allowed; }
        .logout-link { text-align: center; margin-top: 1.5rem; }
        .logout-link a { color: var(--text-gray); font-size: 0.9rem; text-decoration: none; }
        .logout-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="mfa-container">
        <div class="mfa-header">
            <h1>Yêu Cầu Xác Thực Bổ Sung</h1>
            <p>Tài khoản <strong><?php echo htmlspecialchars($user_email); ?></strong> cần thêm một bước để đăng nhập.</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message" style="background: #ffebee; color: #c62828; padding: 12px; border-radius: 8px; margin-bottom: 1.5rem; border-left: 5px solid #f44336;"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="mfa.php" id="mfaForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
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