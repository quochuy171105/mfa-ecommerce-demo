<?php
// Include header, form email/pass, POST Auth::login+rate limit, chuyển hướng mfa nếu OK, error+log nếu thất bại.

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';

start_secure_session();

$error_message = '';
$success_message = '';

// Kiểm tra nếu user đã đăng nhập
if (Auth::isAuthenticated()) {
    header('Location: mfa.php');
    exit;
}

// Xử lý form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Kiểm tra CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else {
        // Kiểm tra rate limit
        if (!check_rate_limit('login')) {
            $error_message = 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 1 phút.';
            error_log('Rate limit exceeded for login from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        } else {
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error_message = 'Vui lòng nhập đầy đủ thông tin';
            } else {
                // Thực hiện đăng nhập
                $result = Auth::login($email, $password);
                
                if ($result['success']) {
                    $success_message = $result['message'];
                    // Chuyển hướng đến trang MFA
                    header('Location: mfa.php');
                    exit;
                } else {
                    $error_message = $result['message'];
                    // Log failed attempt
                    error_log("Failed login attempt for: $email from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
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
    <title>Đăng Nhập - Auth System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #0c0811ff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #08030cff 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
        
        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }
        
        .forgot-password a {
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 1rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Đăng Nhập</h1>
            <p>Chào mừng bạn quay trở lại</p>
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
        
        <form method="POST" action="" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required 
                       autocomplete="off"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="current-password">
            </div>
            
            <button type="submit" class="btn" id="loginBtn">Đăng Nhập</button>
            
            <div class="loading" id="loading">
                Đang xử lý...
            </div>
        </form>
        
        <div class="forgot-password">
            <a href="#" onclick="alert('Tính năng này chưa được triển khai')">Quên mật khẩu?</a>
        </div>
        
        <div class="register-link">
            <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
        </div>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const loading = document.getElementById('loading');
            
            btn.disabled = true;
            btn.textContent = 'Đang đăng nhập...';
            loading.style.display = 'block';
            
            // Nếu có lỗi, reset button sau 3 giây
            setTimeout(() => {
                if (btn.disabled) {
                    btn.disabled = false;
                    btn.textContent = 'Đăng Nhập';
                    loading.style.display = 'none';
                }
            }, 3000);
        });
        
        // Auto focus on email field
        document.getElementById('email').focus();
        
        // Enter key navigation
        document.getElementById('email').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('password').focus();
            }
        });
    </script>
</body>
</html>