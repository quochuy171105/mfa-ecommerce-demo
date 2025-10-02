<?php
// KHÔNG có khoảng trắng trước <?php
session_start();
require_once __DIR__ . '/../classes/FaceAuth.php';
// Tắt mọi output buffer
ob_clean();
header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['face_descriptors'])) {
    echo "invalid_request";
    exit;
}
$face_descriptors_json = $_POST['face_descriptors'];
$register = isset($_POST['register']) ? filter_var($_POST['register'], FILTER_VALIDATE_BOOLEAN) : false;
$user_id = $_SESSION['user_id'] ?? 1;
error_log("=== verify.php START ===");
error_log("User ID: $user_id");
error_log("Register mode: " . ($register ? "YES" : "NO"));
error_log("Descriptor length: " . strlen($face_descriptors_json));
if ($register) {
    FaceAuth::storeFace($user_id, $face_descriptors_json);
    $_SESSION['face_register'] = true;
    error_log("Registration SUCCESS");
    echo "registered";
    exit;
}
// Verify mode
$result = FaceAuth::verifyFace($user_id, $face_descriptors_json);
error_log("Verify result: " . ($result ? "TRUE" : "FALSE"));

if ($result === true) {
    $_SESSION['authenticated'] = true;
    error_log("SENDING: success");
    echo "success";
    exit;
}
// Verify failed
if (!FaceAuth::hasFace($user_id)) {
    error_log("SENDING: register_first");
    echo "register_first";
    exit;
}
error_log("SENDING: no_match");
echo "no_match";
exit;
?>