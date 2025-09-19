<?php
require_once 'includes/header.php';

// Stub for Auth::isAuthenticated - independent test (no classes/Auth.php yet)
// In full project: require_once 'classes/Auth.php'; return Auth::isAuthenticated();
function isAuthenticated() {
    // Kiểm tra session JWT token validity (giả lập: check isset + not empty)
    if (isset($_SESSION['auth_token']) && !empty($_SESSION['auth_token'])) {
        return true; // Giả lập đã đăng nhập để test
    }
    return false;
}

// Log access IP vào logs/security.log
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$logEntry = date('Y-m-d H:i:s') . " Access from IP: " . $ip . "\n";
file_put_contents('logs/security.log', $logEntry, FILE_APPEND | LOCK_EX);

if (!isAuthenticated()) {
    header('Location: pages/login.php');
    exit;
} else {
    header('Location: pages/success.php');
    exit;
}
?>

<!-- Minimal content for test -->
<h1>Index Page</h1>

<?php require_once 'includes/footer.php'; ?>