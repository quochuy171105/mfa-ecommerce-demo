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
    <title>LE.GICARFT | Đăng ký</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
</head>
<body>
    <header>
        <picture>
            <img src="../../assets/images/logo.png" alt="LE.GICARFT Logo" class="logo">
        </picture>
    </header>

    <main>
        <section class="auth-container">
            <h1>Đăng Ký</h1>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" autocomplete="on" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Nhập email của bạn" 
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        autocomplete="email">
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Nhập mật khẩu" 
                        required
                        autocomplete="new-password">
                    <div class="password-requirements">
                        Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường, số và ký tự đặc biệt
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Xác nhận mật khẩu:</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Xác nhận mật khẩu" 
                        required
                        autocomplete="new-password">
                </div>

                <button type="submit" class="auth-btn">Đăng Ký</button>
            </form>

            <p>Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a></p>
        </section>
    </main>

    <script>
        // Kiểm tra mật khẩu khớp nhau real-time
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#ccc';
            }
        });
        
        // Hiển thị yêu cầu mật khẩu real-time
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            let isValid = true;
            
            // Kiểm tra các yêu cầu
            if (password.length < 8 || 
                !/[A-Z]/.test(password) || 
                !/[a-z]/.test(password) || 
                !/[0-9]/.test(password)) {
                isValid = false;
            }
            
            this.style.borderColor = isValid && password ? '#28a745' : '#ccc';
        });
    </script>
</body>
</html>