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
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #646464ff 0%, #e57309 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
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
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
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
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
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
        
        .login-link {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
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
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       required 
                       autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Mật khẩu:</label>
                <input type="password" 
                       id="password" 
                       name="password" 
                       required 
                       autocomplete="new-password">
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
                       autocomplete="new-password">
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
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#c33';
            } else {
                this.style.borderColor = '#ddd';
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
            
            this.style.borderColor = isValid && password ? '#363' : '#ddd';
        });
    </script>
</body>
</html>