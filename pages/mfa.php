<?php
// Chọn MFA: Form radio (email OTP hoặc face), POST to respective handler.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';

start_secure_session();

// Kiểm tra user đã đăng nhập chưa
$auth = Auth::isAuthenticated();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$error_message = '';
$success_message = '';

// Xử lý form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else {
        $mfa_type = sanitize_input($_POST['mfa_type'] ?? '');
        
        if (empty($mfa_type) || !in_array($mfa_type, ['otp', 'face'])) {
            $error_message = 'Vui lòng chọn phương thức xác thực';
        } else {
            // Lưu loại MFA vào session
            $_SESSION['mfa_type'] = $mfa_type;
            $_SESSION['mfa_step'] = 'verify';
            
            // Chuyển hướng đến trang xử lý tương ứng
            if ($mfa_type === 'otp') {
                $success_message = 'Đã chọn xác thực qua Email OTP';
                // Trong thực tế sẽ chuyển đến otp.php
                header('refresh:1;url=#otp-handler');
            } else {
                $success_message = 'Đã chọn xác thực qua Face Recognition';
                // Trong thực tế sẽ chuyển đến face.php  
                header('refresh:1;url=#face-handler');
            }
        }
    }
}

// Tạo CSRF token mới
$csrf_token = gen_csrf();

// Lấy thông tin user hiện tại
$current_user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực Đa Yếu Tố - Auth System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mfa-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .mfa-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .mfa-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .mfa-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .user-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .user-info .email {
            color: #667eea;
            font-weight: 500;
        }
        
        .mfa-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .mfa-option {
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .mfa-option:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .mfa-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .mfa-option input[type="radio"]:checked + .option-content {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .mfa-option input[type="radio"]:checked ~ .option-icon {
            color: white;
        }
        
        .option-content {
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s;
            border-radius: 6px;
            padding: 0.5rem;
        }
        
        .option-icon {
            font-size: 2rem;
            color: #667eea;
            transition: color 0.3s;
        }
        
        .option-details h3 {
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }
        
        .option-details p {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 4px solid #c33;
        }
        
        .success-message {
            background: #efe;
            color: #363;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
            border-left: 4px solid #363;
        }
        
        .logout-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .logout-link a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .logout-link a:hover {
            text-decoration: underline;
        }
        
        .security-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="mfa-container">
        <div class="mfa-header">
            <h1>Xác Thực Bảo Mật</h1>
            <p>Chọn phương thức xác thực để hoàn tất đăng nhập</p>
        </div>
        
        <?php if ($current_user): ?>
        <div class="user-info">
            <p>Đăng nhập với tài khoản: <span class="email"><?php echo htmlspecialchars($current_user['email']); ?></span></p>
        </div>
        <?php endif; ?>
        
        <div class="security-note">
            <strong></strong>Lưu ý bảo mật:</strong> Xác thực đa yếu tố giúp bảo vệ tài khoản của bạn khỏi truy cập trái phép.
        </div>
        
        <?php if ($error_message): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success_message); ?>
                <br><small>Đang chuyển hướng...</small>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="mfaForm">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mfa-options">
                <label class="mfa-option">
                    <input type="radio" name="mfa_type" value="otp" required>
                    <div class="option-content">
                        <div class="option-icon">📧</div>
                        <div class="option-details">
                            <h3>Email OTP</h3>
                            <p>Nhận mã xác thực 6 số qua email</p>
                        </div>
                    </div>
                </label>
                
                <label class="mfa-option">
                    <input type="radio" name="mfa_type" value="face" required>
                    <div class="option-content">
                        <div class="option-icon">👤</div>
                        <div class="option-details">
                            <h3>Face Recognition</h3>
                            <p>Xác thực bằng nhận diện khuôn mặt</p>
                        </div>
                    </div>
                </label>
            </div>
            
            <button type="submit" class="btn" id="continueBtn" disabled>
                Tiếp Tục Xác Thực
            </button>
        </form>
        
        <div class="logout-link">
            <a href="?action=logout" onclick="return confirm('Bạn có chắc muốn đăng xuất?')">
                Đăng xuất và quay lại trang đăng nhập
            </a>
        </div>
    </div>
    
    <script>
        // Enable/disable continue button based on selection
        const radioButtons = document.querySelectorAll('input[name="mfa_type"]');
        const continueBtn = document.getElementById('continueBtn');
        
        radioButtons.forEach(radio => {
            radio.addEventListener('change', function() {
                continueBtn.disabled = false;
                continueBtn.textContent = `Tiếp tục với ${this.value === 'otp' ? 'Email OTP' : 'Face Recognition'}`;
            });
        });
        
        // Form submission handling
        document.getElementById('mfaForm').addEventListener('submit', function(e) {
            continueBtn.disabled = true;
            continueBtn.textContent = 'Đang xử lý...';
        });
        
        // Auto-select first option if only one is available
        if (radioButtons.length === 1) {
            radioButtons[0].checked = true;
            radioButtons[0].dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>

<?php
// Xử lý logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    Auth::logout();
    header('Location: login.php');
    exit;
}
?>