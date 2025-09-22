<?php
// Bảo mật core: Hash/verify pass (bcrypt), AES encrypt/decrypt OTP, nonce gen.

/**
 * Mã hóa AES-256-CBC
 */
function encrypt_aes($data, $key) {
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
function decrypt_aes($encrypted_data, $key) {
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
    // Trong thực tế, key này nên được lưu trong biến môi trường
    $key = hash('sha256', 'your-secret-key-here-change-in-production', true);
    return $key;
}

/**
 * Tạo JWT token đơn giản
 */
function create_jwt($payload, $secret) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode($payload);
    
    $header_encoded = base64url_encode($header);
    $payload_encoded = base64url_encode($payload);
    
    $signature = hash_hmac('sha256', $header_encoded . "." . $payload_encoded, $secret, true);
    $signature_encoded = base64url_encode($signature);
    
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
    
    $header = base64url_decode($parts[0]);
    $payload = base64url_decode($parts[1]);
    $signature = base64url_decode($parts[2]);
    
    $expected_signature = hash_hmac('sha256', $parts[0] . "." . $parts[1], $secret, true);
    
    if (!hash_equals($signature, $expected_signature)) {
        return false;
    }
    
    $payload_data = json_decode($payload, true);
    
    // Kiểm tra expiration
    if (isset($payload_data['exp']) && $payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

/**
 * Base64 URL safe encode
 */
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL safe decode
 */
function base64url_decode($data) {
    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
}
?>