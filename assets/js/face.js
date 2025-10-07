document.addEventListener('DOMContentLoaded', async () => {
    const video = document.getElementById('video');
    const registerButton = document.getElementById('registerFace');
    const scanButton = document.getElementById('scanFace');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (!video || !csrfToken) {
        console.error('Thiếu thành phần thiết yếu (video, csrf-token).');
        return;
    }

    console.log('Đang tải models...');
    try {
        await Promise.all([
            faceapi.nets.ssdMobilenetv1.loadFromUri('../assets/js/weights'),
            faceapi.nets.faceLandmark68Net.loadFromUri('../assets/js/weights'),
            faceapi.nets.faceRecognitionNet.loadFromUri('../assets/js/weights')
        ]);
        console.log('Models đã tải xong.');
    } catch (error) {
        console.error('Lỗi tải models:', error);
        alert('Không thể tải các model nhận dạng. Vui lòng kiểm tra lại kết nối mạng và thử lại.');
        return;
    }

    let stream = null;
    let lastScanTime = 0;
    const DEBOUNCE_MS = 2000;

    // BỎ: Hàm hashDescriptor không còn cần thiết
    // async function hashDescriptor(descriptor) { ... }

    async function setupWebcam() {
        if (stream) return true;
        try {
            console.log('Đang bật webcam...');
            stream = await navigator.mediaDevices.getUserMedia({
                video: { width: 640, height: 480 }
            });
            video.srcObject = stream;
            await new Promise((resolve) => { video.onloadedmetadata = resolve; });
            video.play().catch(e => console.error("Lỗi play video:", e));
            console.log('Webcam đã bật.');
            return true;
        } catch (err) {
            console.error('Lỗi webcam:', err);
            alert(`Không thể truy cập webcam: ${err.name}. Vui lòng cấp quyền và thử lại.`);
            return false;
        }
    }

    async function captureSingleDescriptor() {
        if (!await setupWebcam()) return null;

        console.log('Đang quét 1 lần...');
        const detection = await faceapi.detectSingleFace(video).withFaceLandmarks().withFaceDescriptor();
        if (!detection) {
            throw new Error('Không nhận dạng được khuôn mặt. Vui lòng nhìn thẳng vào camera.');
        }
        return Array.from(detection.descriptor);
    }

    async function captureMultipleDescriptors() {
    if (!await setupWebcam()) return null;

    const descriptors = [];
    // THAY ĐỔI HƯỚNG DẪN TẠI ĐÂY
    const instructions = [
        "Vui lòng nhìn thẳng vào camera.",
        "Vui lòng từ từ quay mặt sang TRÁI một chút.",
        "Vui lòng từ từ quay mặt sang PHẢI một chút."
    ];

    for (let i = 0; i < instructions.length; i++) {
        alert(instructions[i]); // Hiển thị hướng dẫn

        // Chờ một chút để người dùng điều chỉnh
        await new Promise(resolve => setTimeout(resolve, 500)); 

        const detection = await faceapi.detectSingleFace(video).withFaceLandmarks().withFaceDescriptor();
        if (!detection) {
            throw new Error(`Lấy mẫu ${i + 1} thất bại. Không nhận dạng được khuôn mặt.`);
        }
        descriptors.push(Array.from(detection.descriptor));
        console.log(`Đã lấy mẫu ${i + 1}`);
    }
    return descriptors;
}

    async function sendToServer(descriptorData, isRegister) {
        if (Date.now() - lastScanTime < DEBOUNCE_MS) {
            throw new Error('Thao tác quá nhanh. Vui lòng chờ 2 giây.');
        }
        lastScanTime = Date.now();

        // SỬA LẠI: descriptorData giờ có thể là mảng 1 chiều (quét) hoặc 2 chiều (đăng ký)
        const descriptorJson = JSON.stringify(descriptorData);

        const formData = new FormData();
        formData.append('face_descriptors', descriptorJson);
        formData.append('register', isRegister ? 'true' : 'false');
        formData.append('csrf_token', csrfToken);
        // BỎ: Không gửi hashed_descriptor nữa
        // formData.append('hashed_descriptor', hashedDescriptor);
        console.log('CSRF Token đang gửi đi:', csrfToken);

        const response = await fetch('verify.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`Lỗi server: ${response.status}`);
        }

        // THAY ĐỔI: Chuyển đổi phản hồi thành JSON
        return response.json();
    }

    async function handleRegister() {
        if (registerButton) registerButton.disabled = true;
        try {
            const descriptors = await captureMultipleDescriptors();
            // THAY ĐỔI: Biến 'result' giờ là một đối tượng JSON
            const result = await sendToServer(descriptors, true);

            if (result.status === 'success' && result.message === 'registered') {
                alert('Đăng ký thành công!');
                window.location.href = 'face.php?message=registered';
            } else {
                // THAY ĐỔI: In ra toàn bộ đối tượng lỗi để gỡ lỗi
                throw new Error(JSON.stringify(result, null, 2));
            }
        } catch (err) {
            // THAY ĐỔI: Hiển thị lỗi rõ ràng hơn
            console.error('Lỗi đăng ký:', err.message);
            alert('Lỗi đăng ký: ' + err.message);
        } finally {
            if (registerButton) registerButton.disabled = false;
        }
    }

    async function handleScan() {
        if (scanButton) scanButton.disabled = true;
        try {
            const descriptor = await captureSingleDescriptor();
            const result = await sendToServer(descriptor, false); // result là một đối tượng JSON

            // SỬA LẠI HOÀN TOÀN LOGIC KIỂM TRA PHẢN HỒI JSON
            if (result.status === 'success' && result.message === 'verified') {
                alert('Xác thực thành công!');
                window.location.href = 'success.php';
            } else {
                // Xử lý các lỗi đã được định nghĩa từ server
                if (result.message === 'register_first') {
                    alert('Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước.');
                    window.location.reload();
                } else if (result.message === 'no_match') {
                    alert('Khuôn mặt không khớp. Vui lòng thử lại.');
                } else {
                    // Các lỗi không xác định khác (ví dụ: csrf_invalid)
                    throw new Error(JSON.stringify(result, null, 2));
                }
            }
        } catch (err) {
            console.error('Lỗi quét:', err.message);
            alert('Lỗi quét: ' + err.message);
        } finally {
            if (scanButton) scanButton.disabled = false;
        }
    }

    if (registerButton) registerButton.addEventListener('click', handleRegister);
    if (scanButton) scanButton.addEventListener('click', handleScan);

    console.log('Hệ thống xác thực khuôn mặt đã sẵn sàng.');
});