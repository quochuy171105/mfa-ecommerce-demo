<?php
// Form input OTP, resend OTP, POST to verify.php for verify.

ob_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/OTP.php';
require_once __DIR__ . '/../config/app.php'; // $smtp
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

start_secure_session();

$auth = Auth::isAuthenticated();
if (!$auth || ($_SESSION['mfa_type'] ?? '') != 'otp') {
    header('Location: mfa.php');
    exit;
}

$user_id = $auth['user_id'];
$user_email = $_SESSION['email'] ?? ($auth['email'] ?? '');
$error_message = $_SESSION['verify_error'] ?? '';
$success_message = '';
unset($_SESSION['verify_error']); // Xóa lỗi sau khi hiển thị

// Hàm gửi email OTP (giữ nguyên logic của bạn)
if (!function_exists('sendOtpMail')) {
    function sendOtpMail($toEmail, $otp) {
        global $smtp;
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $smtp['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp['username'];
            $mail->Password = $smtp['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtp['port'];
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($smtp['from_email'] ?? $smtp['username'], 'Hệ Thống Bảo Mật');
            $mail->addAddress($toEmail);
            $mail->isHTML(true);
            $mail->Subject = 'Mã xác thực OTP của bạn';
            $mail->Body = str_replace('{otp}', $otp, file_get_contents(__DIR__ . '/../emails/otp_template.html'));
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("OTP email error: " . $mail->ErrorInfo);
            return false;
        }
    }
}


// Xử lý gửi lại OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'resend') {
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        $error_message = 'Token bảo mật không hợp lệ';
    } else if (!check_rate_limit('otp_resend')) {
        $error_message = 'Bạn thao tác quá nhanh. Vui lòng thử lại sau 1 phút.';
    } else {
        $result = OTP::generateAndStore($user_id);
        if ($result['success']) {
            if (sendOtpMail($user_email, $result['otp'])) {
                $success_message = 'Đã gửi lại mã OTP thành công!';
            } else {
                $error_message = 'Không thể gửi email. Vui lòng thử lại.';
            }
        } else {
            $error_message = 'Không thể tạo mã OTP mới.';
        }
    }
}

// Gen OTP lần đầu nếu chưa có
if (!isset($_SESSION['otp_sent'])) {
    $result = OTP::generateAndStore($user_id);
    if ($result['success']) {
        if (sendOtpMail($user_email, $result['otp'])) {
            $_SESSION['otp_sent'] = true;
        } else {
            // Nếu gửi mail lần đầu thất bại
            $error_message = "Không thể gửi mã OTP đến email của bạn. Vui lòng thử lại.";
        }
    }
}

$csrf_token = gen_csrf();
global $pdo;
$stmt = $pdo->prepare("SELECT nonce FROM otps WHERE user_id = ? ORDER BY expiry DESC LIMIT 1");
$stmt->execute([$user_id]);
$nonce = $stmt->fetchColumn() ?? '';
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Thực OTP</title>
    <link rel="stylesheet" href="../../assets/css/main.css">
</head>
<body class="otp-page">
    <div class="otp-container">
        <div class="icon">✉️</div>
        <h1>Xác thực OTP</h1>
        <p class="instructions">Một mã gồm 6 chữ số đã được gửi đến <strong><?php echo htmlspecialchars($user_email); ?></strong></p>

        <?php if ($error_message): ?><div class="message error-message"><?php echo htmlspecialchars($error_message); ?></div><?php endif; ?>
        <?php if ($success_message): ?><div class="message success-message"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>

        <form method="POST" action="verify.php" id="otp-form">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="nonce" value="<?php echo $nonce; ?>">
            <input type="hidden" name="otp" id="otp-full">
            <div class="otp-inputs" id="otp-inputs">
                <?php for ($i = 0; $i < 6; $i++): ?>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <?php endfor; ?>
            </div>
            <button type="submit" class="btn" id="verify-btn">Xác Nhận</button>
        </form>

        <div class="resend-container">
            <span>Không nhận được mã?</span>
            <form method="POST" action="otp.php" id="resend-form" style="display: inline;">
                 <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                 <input type="hidden" name="action" value="resend">
                 <button type="submit" id="resend-btn" disabled>Gửi lại</button>
            </form>
            <span id="timer">(1:00)</span>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const inputs = document.getElementById('otp-inputs');
            const otpFullInput = document.getElementById('otp-full');
            const otpForm = document.getElementById('otp-form');
            const inputFields = [...inputs.children];

            // Logic tự động nhảy ô
            inputs.addEventListener('input', (e) => {
                const target = e.target;
                const index = inputFields.indexOf(target);
                if (target.value !== '' && index < inputFields.length - 1) {
                    inputFields[index + 1].focus();
                }
            });

            // Logic xử lý nút Backspace
            inputs.addEventListener('keydown', (e) => {
                const target = e.target;
                const index = inputFields.indexOf(target);
                if (e.key === "Backspace" && target.value === '' && index > 0) {
                    inputFields[index - 1].focus();
                }
            });
            
            // Logic xử lý dán (paste)
            inputFields[0].addEventListener('paste', (e) => {
                e.preventDefault();
                const text = e.clipboardData.getData('text').slice(0, 6);
                text.split('').forEach((char, index) => {
                    if(inputFields[index] && !isNaN(char)) {
                         inputFields[index].value = char;
                    }
                });
                inputFields[Math.min(5, text.length-1)].focus();
            });

            // Gộp 6 ô thành 1 input ẩn trước khi submit
            otpForm.addEventListener('submit', (e) => {
                let otp = '';
                inputFields.forEach(input => otp += input.value);
                if (otp.length === 6) {
                    otpFullInput.value = otp;
                } else {
                    e.preventDefault();
                    alert('Vui lòng nhập đủ 6 chữ số OTP.');
                }
            });

            // Logic đếm ngược và nút gửi lại
            const resendBtn = document.getElementById('resend-btn');
            const timerEl = document.getElementById('timer');
            let timeLeft = 60;

            const timer = setInterval(() => {
                timeLeft--;
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerEl.textContent = `(${minutes}:${seconds.toString().padStart(2, '0')})`;

                if (timeLeft <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    timerEl.textContent = '';
                }
            }, 1000);
        });
    </script>
</body>
</html>