<?php
// Xử lý auth: Session start với JWT (signed HMAC-SHA256), logout.

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/User.php';

class Auth {
    private static $jwt_secret = 'your-jwt-secret-change-in-production';
    
    /**
     * Đăng nhập người dùng
     */
    public static function login($email, $password) {
        try {
            // Validate input
            if (!validate_email($email)) {
                return [
                    'success' => false,
                    'message' => 'Email không hợp lệ'
                ];
            }
            
            // Lấy thông tin user từ database
            $user = User::getUserByEmail($email);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không đúng'
                ];
            }
            
            // Kiểm tra mật khẩu - sử dụng pass_hash
            if (!verify_password($password, $user['pass_hash'])) {
                // Log failed login attempt
                error_log("Failed login attempt for email: $email from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                
                return [
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không đúng'
                ];
            }
            
            // Tạo JWT token
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'iat' => time(),
                'exp' => time() + 3600 // Hết hạn sau 1 giờ
            ];
            
            $jwt_token = create_jwt($payload, self::$jwt_secret);
            
            // Lưu JWT vào session
            start_secure_session();
            $_SESSION['jwt_token'] = $jwt_token;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            
            // Cập nhật last login
            User::updateLastLogin($user['id']);
            
            return [
                'success' => true,
                'message' => 'Đăng nhập thành công',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email']
                ]
            ];
            
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình đăng nhập'
            ];
        }
    }
    
    /**
     * Kiểm tra người dùng đã đăng nhập chưa
     */
    public static function isAuthenticated() {
        start_secure_session();
        
        if (!isset($_SESSION['jwt_token']) || !isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Xác minh JWT token
        $payload = verify_jwt($_SESSION['jwt_token'], self::$jwt_secret);
        
        if (!$payload) {
            // Token không hợp lệ, xóa session
            self::logout();
            return false;
        }
        
        // Kiểm tra user_id khớp
        if ($payload['user_id'] !== $_SESSION['user_id']) {
            self::logout();
            return false;
        }
        
        return [
            'user_id' => $payload['user_id'],
            'email' => $payload['email']
        ];
    }
    
    /**
     * Đăng xuất
     */
    public static function logout() {
        start_secure_session();
        
        // Xóa tất cả session data
        $_SESSION = [];
        
        // Xóa session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Hủy session
        session_destroy();
        
        return [
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ];
    }
    
    /**
     * Kiểm tra quyền truy cập trang
     */
    public static function requireAuth($redirect_url = '/pages/login.php') {
        if (!self::isAuthenticated()) {
            header("Location: $redirect_url");
            exit;
        }
    }
    
    /**
     * Lấy thông tin user hiện tại
     */
    public static function getCurrentUser() {
        $auth = self::isAuthenticated();
        
        if (!$auth) {
            return null;
        }
        
        return User::getUser($auth['user_id']);
    }
    
    /**
     * Refresh JWT token
     */
    public static function refreshToken() {
        $auth = self::isAuthenticated();
        
        if (!$auth) {
            return false;
        }
        
        // Tạo token mới
        $payload = [
            'user_id' => $auth['user_id'],
            'email' => $auth['email'],
            'iat' => time(),
            'exp' => time() + 3600
        ];
        
        $new_token = create_jwt($payload, self::$jwt_secret);
        $_SESSION['jwt_token'] = $new_token;
        
        return true;
    }
    
    /**
     * Kiểm tra session có hợp lệ không (không cần JWT)
     */
    public static function hasValidSession() {
        start_secure_session();
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}
?>