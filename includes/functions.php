<?php
// Hàm chung: Generate CSRF, rate limit check, input sanitization.

/**
 * Làm sạch input từ user
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Kiểm tra rate limit - tối đa 5 lần/phút
 */
function check_rate_limit($action = 'default') {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $current_time = time();
    $rate_key = $action . '_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    
    // Xóa các attempt cũ hơn 1 phút
    if (isset($_SESSION['rate_limit'][$rate_key])) {
        $_SESSION['rate_limit'][$rate_key] = array_filter(
            $_SESSION['rate_limit'][$rate_key], 
            function($timestamp) use ($current_time) {
                return ($current_time - $timestamp) < 60; // 60 giây
            }
        );
    } else {
        $_SESSION['rate_limit'][$rate_key] = [];
    }
    
    // Kiểm tra số lần thử
    if (count($_SESSION['rate_limit'][$rate_key]) >= 5) {
        return false; // Vượt quá limit
    }
    
    // Thêm attempt hiện tại
    $_SESSION['rate_limit'][$rate_key][] = $current_time;
    return true;
}

/**
 * Tạo CSRF token
 */
function gen_csrf() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Xác minh CSRF token
 */
function verify_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Tạo session an toàn
 */
function start_secure_session() {
    if (session_status() === PHP_SESSION_NONE) {
        // Cấu hình session bảo mật
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 1);
        
        session_start();
    }
}
?>