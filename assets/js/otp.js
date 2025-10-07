// JS cho OTP: Timer countdown, resend button với rate limit.
let timeLeft = 300; // 5 min
const timer = document.getElementById('timer');
const resend = document.getElementById('resend');

const countdown = setInterval(() => {
    timeLeft--;
    const min = Math.floor(timeLeft / 60);
    const sec = timeLeft % 60;
    timer.textContent = `${min}:${sec < 10 ? '0' + sec : sec}`;
    if (timeLeft <= 0) {
        clearInterval(countdown);
        resend.disabled = false;
        resend.textContent = 'Gửi Lại';
    }
}, 1000);
resend.disabled = true;
resend.textContent = 'Gửi Lại (sau 5 phút)';