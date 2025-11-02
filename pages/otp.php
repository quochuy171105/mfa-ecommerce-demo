<?php
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

// Hàm gửi email OTP 
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
    <style>
        :root {
            --primary-color: #646464;
            --secondary-color: #e57309;
            --bg-color: #e0e5ec;
            --text-dark: #4a5568;
            --text-light: #718096;
            --shadow-light: #ffffff;
            --shadow-dark: #a3b1c6;
            --error-color: #e53e3e;
            --success-color: #48bb78;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .otp-container {
            background: var(--bg-color);
            padding: 3rem 2.5rem;
            border-radius: 30px;
            box-shadow: 
                12px 12px 24px var(--shadow-dark),
                -12px -12px 24px var(--shadow-light);
            width: 100%;
            max-width: 480px;
            text-align: center;
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .icon { 
            font-size: 4rem;
            margin-bottom: 1.5rem;
            filter: drop-shadow(3px 3px 6px var(--shadow-dark));
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        h1 { 
            color: var(--text-dark);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            text-shadow: 
                2px 2px 4px var(--shadow-dark),
                -2px -2px 4px var(--shadow-light);
        }
        
        .instructions { 
            color: var(--text-light);
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }
        
        .instructions strong { 
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .otp-inputs { 
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 2.5rem;
        }
        
        .otp-input {
            width: 55px;
            height: 65px;
            font-size: 1.8rem;
            text-align: center;
            background: var(--bg-color);
            border: none;
            border-radius: 15px;
            color: var(--text-dark);
            font-weight: 600;
            box-shadow: 
                inset 6px 6px 12px var(--shadow-dark),
                inset -6px -6px 12px var(--shadow-light);
            transition: all 0.3s ease;
        }
        
        .otp-input:focus {
            outline: none;
            box-shadow: 
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light),
                0 0 0 3px rgba(100, 100, 100, 0.15);
            transform: scale(1.05);
        }
        
        .btn {
            width: 100%; 
            padding: 16px;
            background: linear-gradient(145deg, #f0f0f0, #cacaca);
            color: var(--text-dark);
            border: none; 
            border-radius: 15px;
            font-size: 1.05rem; 
            font-weight: 700;
            cursor: pointer;
            box-shadow: 
                8px 8px 16px var(--shadow-dark),
                -8px -8px 16px var(--shadow-light);
            transition: all 0.3s ease;
        }
        
        .btn:hover:not(:disabled) { 
            box-shadow: 
                6px 6px 12px var(--shadow-dark),
                -6px -6px 12px var(--shadow-light);
            transform: translateY(-2px);
        }
        
        .btn:active:not(:disabled) {
            box-shadow: 
                inset 4px 4px 8px var(--shadow-dark),
                inset -4px -4px 8px var(--shadow-light);
            transform: translateY(0);
        }
        
        .btn:disabled { 
            opacity: 0.5;
            cursor: not-allowed; 
        }
        
        .resend-container { 
            margin-top: 2rem;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        #resend-form { display: inline; }
        
        #resend-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            font-weight: 600;
            cursor: pointer;
            padding: 0;
            font-size: 0.95rem;
            transition: color 0.3s;
        }
        
        #resend-btn:hover:not(:disabled) {
            color: var(--secondary-color);
        }
        
        #resend-btn:disabled { 
            color: var(--text-light);
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        #timer {
            color: var(--text-light);
            font-weight: 600;
        }
        
        .message { 
            padding: 14px 18px;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            box-shadow: 
                4px 4px 8px var(--shadow-dark),
                -4px -4px 8px var(--shadow-light);
        }
        
        .error-message { 
            background: var(--bg-color);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }
        
        .success-message { 
            background: var(--bg-color);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="icon">✉️</div>
        <h1>Xác thực OTP</h1>
        <p class="instructions">Một mã gồm 6 chữ số đã được gửi đến <strong><?php echo htmlspecialchars($user_email); ?></strong></p>

        <?php if ($error_message): ?>
        <div class="message error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
        <div class="message success-message">
            <?php echo htmlspecialchars($success_message); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="verify.php" id="otp-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="nonce" value="<?php echo htmlspecialchars($nonce); ?>">
            <input type="hidden" name="otp" id="otp-full">
            
            <div class="otp-inputs" id="otp-inputs">
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="tel" class="otp-input" maxlength="1" pattern="[0-9]" required>
            </div>
            
            <button type="submit" class="btn" id="verify-btn">Xác Nhận</button>
        </form>

        <div class="resend-container">
            <span>Không nhận được mã? </span>
            <form method="POST" action="otp.php" id="resend-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
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