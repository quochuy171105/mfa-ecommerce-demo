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
    <title>LE.GICARFT | Chọn Phương Thức Xác Thực</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
</head>
<body>
    <header>
        <picture>
            <source srcset="../../assets/images/logo.png" type="image/png">
            <img src="../../assets/images/logo.png" alt="LE.GICARFT Logo" class="logo">
        </picture>
    </header>

    <main>
        <section class="auth-container">
            <h1>Xác Thực Bổ Sung</h1>
            <p>Tài khoản <strong><?php echo htmlspecialchars($user_email); ?></strong> cần thêm một bước để đăng nhập.</p>

            <?php if ($error_message): ?>
                <div class="alert-danger">
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

                <button type="submit" class="auth-btn" id="continueBtn" disabled>Tiếp Tục</button>
            </form>

            <div class="logout-link">
                <a href="?action=logout">Đây không phải tôi? Đăng xuất</a>
            </div>
        </section>
    </main>

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