<?php
// Model OTP: Gen OTP (random 6-digit), encrypt AES, send email via PHPMailer, verify with expiry.
// Gen/store/verify OTP (AES encrypt, nonce, expiry 5p, insert otps table).

require_once __DIR__ . '/../config/database.php'; // Global $pdo
require_once __DIR__ . '/../config/app.php'; // AES_KEY
require_once __DIR__ . '/../includes/security.php'; // encrypt_aes, gen_otp, gen_nonce
require_once __DIR__ . '/../includes/validation.php'; // validate_otp

class OTP {
    /**
     * Generate and store OTP for user
     */
    public static function generateAndStore($user_id) {
        global $pdo;
        try {
            $otp = gen_otp(); // 6 digits
            $nonce = bin2hex(gen_nonce(16));
            $expiry = date('Y-m-d H:i:s', time() + 300); // 5 min
            
            $encrypted_otp = encrypt_aes($otp, AES_KEY);
            
            $stmt = $pdo->prepare("
                INSERT INTO otps (user_id, encrypted_otp, expiry, nonce) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $encrypted_otp, $expiry, $nonce]);
            
            return [
                'success' => true,
                'otp' => $otp, // Plain for email
                'nonce' => $nonce
            ];
        } catch (Exception $e) {
            error_log('OTP generate error: ' . $e->getMessage());
            return ['success' => false];
        }
    }
    
    /**
     * Verify OTP
     */
    public static function verify($user_id, $input_otp, $nonce) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("
                SELECT encrypted_otp, expiry, nonce FROM otps 
                WHERE user_id = ? AND nonce = ? ORDER BY expiry DESC LIMIT 1
            ");
            $stmt->execute([$user_id, $nonce]);
            $row = $stmt->fetch();
            
            if (!$row || strtotime($row['expiry']) < time()) {
                return false;
            }
            
            $decrypted_otp = decrypt_aes($row['encrypted_otp'], AES_KEY);
            
            return hash_equals($decrypted_otp, $input_otp);
        } catch (Exception $e) {
            error_log('OTP verify error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cleanup expired OTPs
     */
    public static function cleanup() {
        global $pdo;
        $pdo->prepare("DELETE FROM otps WHERE expiry < NOW()")->execute();
    }
}
?>