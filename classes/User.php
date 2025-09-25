<?php
// Model User: Methods cho register (hash pass, insert DB), login verify.

require_once __DIR__ . '/../config/database.php'; // Sử dụng global $pdo
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';

class User {
    /**
     * Đăng ký người dùng mới
     */
    public static function register($email, $password) {
        global $pdo;
        try {
            // Validate input
            if (!validate_email($email)) {
                return [
                    'success' => false,
                    'message' => 'Email không hợp lệ'
                ];
            }
            
            if (!validate_password_simple($password)) {
                return [
                    'success' => false,
                    'message' => 'Mật khẩu phải có ít nhất 8 ký tự, bao gồm chữ hoa, chữ thường và số'
                ];
            }
            
            // Kiểm tra email đã tồn tại
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn()) {
                return [
                    'success' => false,
                    'message' => 'Email đã được sử dụng'
                ];
            }
            
            // Hash mật khẩu
            $password_hash = hash_password($password);
            
            // Thêm user mới vào bảng users với cấu trúc hiện có
            $stmt = $pdo->prepare("
                INSERT INTO users (email, pass_hash) 
                VALUES (?, ?)
            ");
            
            $stmt->execute([$email, $password_hash]);
            
            return [
                'success' => true,
                'message' => 'Đăng ký thành công',
                'user_id' => $pdo->lastInsertId()
            ];
            
        } catch (Exception $e) {
            error_log('User registration error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra trong quá trình đăng ký'
            ];
        }
    }
    
    /**
     * Lấy thông tin user theo ID
     */
    public static function getUser($id) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log('Get user error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy thông tin user theo email (dùng cho login)
     */
    public static function getUserByEmail($email) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT id, email, pass_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log('Get user by email error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cập nhật last login 
     */
    public static function updateLastLogin($user_id) {
        global $pdo;
        try {
             $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
             $stmt->execute([$user_id]);
            
            return true;
            
        } catch (Exception $e) {
            error_log('Update last login error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Kiểm tra email có tồn tại không
     */
    public static function emailExists($email) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            error_log('Email exists check error: ' . $e->getMessage());
            return false;
        }
    }
}
?>