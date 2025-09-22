<?php
// Validate inputs: Email format, pass strength (min 8 chars, mixed case), face hash integrity.

/**
 * Kiểm tra định dạng email
 */
function validate_email($email) {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    
    // Kiểm tra độ dài
    if (strlen($email) > 254) {
        return false;
    }
    
    return true;
}

/**
 * Kiểm tra độ mạnh của mật khẩu
 * Yêu cầu: ≥8 ký tự, có chữ hoa, chữ thường, số
 */
function validate_password($password) {
    // Kiểm tra độ dài tối thiểu
    if (strlen($password) < 8) {
        return [
            'valid' => false,
            'errors' => ['Mật khẩu phải có ít nhất 8 ký tự']
        ];
    }
    
    $errors = [];
    
    // Kiểm tra có chữ hoa
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Mật khẩu phải có ít nhất 1 chữ cái viết hoa';
    }
    
    // Kiểm tra có chữ thường
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Mật khẩu phải có ít nhất 1 chữ cái viết thường';
    }
    
    // Kiểm tra có số
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Mật khẩu phải có ít nhất 1 chữ số';
    }
    
    // Kiểm tra ký tự đặc biệt (tùy chọn)
    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};:"\\|,.<>\?]/', $password)) {
        $errors[] = 'Mật khẩu nên có ít nhất 1 ký tự đặc biệt';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Kiểm tra tính toàn vẹn của face hash
 * Hash phải là 64 ký tự hex
 */
function validate_face_hash($hash) {
    if (!is_string($hash)) {
        return false;
    }
    
    // Kiểm tra độ dài chính xác 64 ký tự
    if (strlen($hash) !== 64) {
        return false;
    }
    
    // Kiểm tra chỉ chứa ký tự hex
    if (!ctype_xdigit($hash)) {
        return false;
    }
    
    return true;
}

/**
 * Kiểm tra độ mạnh của mật khẩu - phiên bản đơn giản
 */
function validate_password_simple($password) {
    // Kiểm tra độ dài và ký tự hỗn hợp
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/';
    return preg_match($pattern, $password) === 1;
}

/**
 * Làm sạch và kiểm tra tên người dùng
 */
function validate_username($username) {
    $username = trim($username);
    
    // Kiểm tra độ dài
    if (strlen($username) < 3 || strlen($username) > 20) {
        return [
            'valid' => false,
            'error' => 'Tên người dùng phải từ 3-20 ký tự'
        ];
    }
    
    // Kiểm tra chỉ chứa chữ cái, số và gạch dưới
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return [
            'valid' => false,
            'error' => 'Tên người dùng chỉ được chứa chữ cái, số và gạch dưới'
        ];
    }
    
    return [
        'valid' => true,
        'username' => $username
    ];
}

/**
 * Kiểm tra OTP (6 chữ số)
 */
function validate_otp($otp) {
    return preg_match('/^\d{6}$/', $otp) === 1;
}

/**
 * Sanitize và validate input chung
 */
function validate_input($input, $type = 'string', $max_length = 255) {
    $input = sanitize_input($input);
    
    switch ($type) {
        case 'email':
            return validate_email($input);
        case 'password':
            return validate_password_simple($input);
        case 'face_hash':
            return validate_face_hash($input);
        case 'otp':
            return validate_otp($input);
        default:
            return strlen($input) <= $max_length;
    }
}
?>