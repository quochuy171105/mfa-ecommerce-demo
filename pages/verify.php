<?php
// --- KHÔNG có khoảng trắng trước <?php ---
ob_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/OTP.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/FaceAuth.php';

start_secure_session();

$auth = Auth::isAuthenticated();
if (!$auth) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'unauthorized']);
    } else {
        header('Location: login.php');
    }
    exit;
}

$user_id = $auth['user_id'];
// Lấy mfa_type từ session, nếu không có thì thử lấy từ POST (dùng cho form OTP)
$mfa_type = $_SESSION['mfa_type'] ?? ($_POST['method'] ?? 'face');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Chỉ khai báo hàm helper một lần
    if (!function_exists('send_json_response')) {
        function send_json_response($data)
        {
            echo json_encode($data);
            exit;
        }
    }

    switch ($mfa_type) {
        case 'otp':
            // --- LOGIC OTP ĐÃ ĐƯỢC KHÔI PHỤC ---
            $input_otp = trim($_POST['otp'] ?? '');
            $nonce = trim($_POST['nonce'] ?? '');

            if (empty($input_otp) || empty($nonce)) {
                $_SESSION['verify_error'] = 'Thông tin không đầy đủ';
                header('Location: otp.php');
                exit;
            }

            if (!check_rate_limit('otp_verify')) {
                $_SESSION['verify_error'] = 'Quá nhiều lần thử. Vui lòng thử lại sau 1 phút.';
                header('Location: otp.php');
                exit;
            }

            if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
                $_SESSION['verify_error'] = 'Token bảo mật không hợp lệ';
                header('Location: otp.php');
                exit;
            }

            $verify_result = OTP::verify($user_id, $input_otp, $nonce);

            if ($verify_result) {
                $_SESSION['mfa_verified'] = true;
                $_SESSION['authenticated'] = true;
                $_SESSION['login_time'] = time();
                User::updateLastLogin($user_id);
                unset($_SESSION['mfa_type'], $_SESSION['mfa_step'], $_SESSION['otp_sent'], $_SESSION['csrf']);
                header('Location: success.php');
                exit;
            } else {
                $_SESSION['verify_error'] = 'Mã OTP không đúng hoặc đã hết hạn';
                header('Location: otp.php');
                exit;
            }
            break;

        case 'face':
            // --- LOGIC FACE VẪN GIỮ NGUYÊN ---
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');

            $face_descriptors_json = $_POST['face_descriptors'] ?? null;
            $register = isset($_POST['register']) ? filter_var($_POST['register'], FILTER_VALIDATE_BOOLEAN) : false;
            $csrf_token_received = $_POST['csrf_token'] ?? '';
            $csrf_token_session = $_SESSION['csrf'] ?? '';

            if (!$face_descriptors_json) {
                send_json_response(['status' => 'error', 'message' => 'invalid_request']);
            }

            if (!hash_equals($csrf_token_session, $csrf_token_received)) {
                send_json_response([
                    'status' => 'error',
                    'message' => 'csrf_invalid'
                ]);
            }

            // Nhớ bật lại khi deploy

            if (!check_rate_limit('face_verify')) {
                send_json_response(['status' => 'error', 'message' => 'rate_limit_exceeded']);
            }


            if ($register) {
                if (FaceAuth::storeFace($user_id, $face_descriptors_json)) {
                    $_SESSION['face_register'] = true;
                    $_SESSION['authenticated'] = true;
                    $_SESSION['login_time'] = time();
                    unset($_SESSION['mfa_type'], $_SESSION['mfa_step']);
                    send_json_response(['status' => 'success', 'message' => 'registered']);
                } else {
                    send_json_response(['status' => 'error', 'message' => 'register_failed']);
                }
            } else { // Xử lý xác thực
                if (!FaceAuth::hasFace($user_id)) {
                    send_json_response(['status' => 'error', 'message' => 'register_first']);
                }

                $result = FaceAuth::verifyFace($user_id, $face_descriptors_json, $csrf_token_received);
                if ($result) {
                    $_SESSION['mfa_verified'] = true;
                    $_SESSION['authenticated'] = true;
                    unset($_SESSION['mfa_type'], $_SESSION['mfa_step']);
                    send_json_response(['status' => 'success', 'message' => 'verified']);
                } else {
                    send_json_response(['status' => 'error', 'message' => 'no_match']);
                }
            }
            break;

        default:
            http_response_code(400);
            echo "Unknown MFA method";
            exit;
    }
} else {
    // Xử lý GET request (chuyển hướng người dùng)
    if ($mfa_type === 'otp') {
        header('Location: otp.php');
    } else {
        header('Location: face.php');
    }
    exit;
}
ob_end_flush();
