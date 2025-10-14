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
    <title>LE.GICARFT | Đăng nhập</title>
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
            <h1>Đăng Nhập</h1>

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

            <form method="POST" action="login.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="Nhập địa chỉ Email" 
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
                        autocomplete="current-password">
                </div>

                <button type="submit" class="auth-btn">Đăng Nhập</button>
            </form>

            <div class="register-link">
                <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
            </div>
        </section>
    </main>
</body>
</html>