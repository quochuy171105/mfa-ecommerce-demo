<?php
// Model User: Methods cho register (hash pass, insert DB), login verify.

require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';

class User {
    private static $pdo = null;
    
    /**
     * Khởi tạo kết nối database
     */
    private static function getConnection() {
        if (self::$pdo === null) {
            try {
                // Kết nối với database mfa_demo
                $host = 'localhost';
                $dbname = 'mfa_demo';
                $username = 'root';
                $password = '';
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
                self::$pdo = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$pdo;
    }
    
    /**
     * Đăng ký người dùng mới
     */
    public static function register($email, $password) {
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
            
            $pdo = self::getConnection();
            
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
        try {
            $pdo = self::getConnection();
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
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->prepare("SELECT id, email, pass_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            return $stmt->fetch();
            
        } catch (Exception $e) {
            error_log('Get user by email error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cập nhật last login (nếu cần thêm cột này vào database)
     */
    public static function updateLastLogin($user_id) {
        try {
            // Hiện tại bảng users chỉ có id, email, pass_hash
            // Nếu muốn track last login, cần thêm cột last_login vào bảng
            // ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL;
            
            $pdo = self::getConnection();
            // Tạm thời comment out vì chưa có cột last_login
            // $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            // $stmt->execute([$user_id]);
            
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
        try {
            $pdo = self::getConnection();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            error_log('Email exists check error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Tạo bảng users nếu chưa tồn tại (dùng cho setup)
     */
    public static function createUsersTable() {
        try {
            $pdo = self::getConnection();
            $sql = "
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password_hash VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_login TIMESTAMP NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $pdo->exec($sql);
            return true;
            
        } catch (Exception $e) {
            error_log('Create users table error: ' . $e->getMessage());
            return false;
        }
    }
}
?>