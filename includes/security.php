<?php
// Bảo mật core: Hash/verify pass (bcrypt), AES encrypt/decrypt OTP, nonce gen.

require_once __DIR__ . '/../config/app.php'; // Sử dụng AES_KEY từ config

/**
 * Mã hóa AES-256-CBC
 */
function encrypt_aes($data, $key = AES_KEY) {
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    
    if ($encrypted === false) {
        throw new Exception('Encryption failed');
    }
    
    // Kết hợp IV và data đã mã hóa
    return base64_encode($iv . $encrypted);
}

/**
 * Giải mã AES-256-CBC
 */
function decrypt_aes($encrypted_data, $key = AES_KEY) {
    $data = base64_decode($encrypted_data);
    
    if ($data === false || strlen($data) < 16) {
        throw new Exception('Invalid encrypted data');
    }
    
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
    
    if ($decrypted === false) {
        throw new Exception('Decryption failed');
    }
    
    return $decrypted;
}

/**
 * Hash mật khẩu với bcrypt
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Xác minh mật khẩu
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Tạo nonce ngẫu nhiên
 */
function gen_nonce($length = 16) {
    return random_bytes($length);
}

/**
 * Tạo OTP ngẫu nhiên 6 số
 */
function gen_otp() {
    return sprintf('%06d', mt_rand(0, 999999));
}

/**
 * Tạo secret key cho AES từ config
 */
function get_encryption_key() {
    return AES_KEY; // Từ config/app.php
}

/**
 * Tạo JWT token đơn giản
 */
function create_jwt($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload_json = json_encode($payload);
    
    $header_encoded = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
    $payload_encoded = rtrim(strtr(base64_encode($payload_json), '+/', '-_'), '=');
    
    $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, $secret, true);
    $signature_encoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    
    return $header_encoded . "." . $payload_encoded . "." . $signature_encoded;
}

/**
 * Xác minh JWT token
 */
function verify_jwt($token, $secret) {
    $parts = explode('.', $token);
    
    if (count($parts) !== 3) {
        return false;
    }
    
    // Decode header và payload
    $header = json_decode(base64_decode(str_pad(strtr($parts[0], '-_', '+/'), strlen($parts[0]) % 4, '=', STR_PAD_RIGHT)), true);
    $payload = json_decode(base64_decode(str_pad(strtr($parts[1], '-_', '+/'), strlen($parts[1]) % 4, '=', STR_PAD_RIGHT)), true);
    
    if (!$header || !$payload) {
        return false;
    }
    
    // Decode signature as binary
    $signature = base64_decode(str_pad(strtr($parts[2], '-_', '+/'), strlen($parts[2]) % 4, '=', STR_PAD_RIGHT));
    
    $expected_signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $secret, true);
    
    if (!hash_equals($signature, $expected_signature)) {
        return false;
    }
    
    // Kiểm tra expiration
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        return false;
    }
    
    return $payload;
}
?>