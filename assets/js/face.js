document.addEventListener('DOMContentLoaded', async () => {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const registerButton = document.getElementById('registerFace');
    const scanButton = document.getElementById('scanFace');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    if (!video || !canvas || !csrfToken) {
        console.error('Thiếu thành phần thiết yếu (video, canvas, csrf-token).');
        return;
    }

    const ctx = canvas.getContext('2d');

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
        showMessage('Không thể tải các model nhận dạng. Vui lòng kiểm tra lại kết nối mạng và thử lại.', 'error');
        return;
    }

    let stream = null;
    let lastScanTime = 0;
    let detectionInterval = null;
    let consecutiveNoFaceFrames = 0;
    const DEBOUNCE_MS = 2000;
    const MAX_NO_FACE_FRAMES = 5; // Cho phép 5 frame liên tiếp không có mặt trước khi cảnh báo

    // Hàm hiển thị thông báo
    function showMessage(text, type = 'info') {
        const messageContainer = document.getElementById('message-container');
        if (!messageContainer) return;

        messageContainer.innerHTML = `<div class="message ${type}">${text}</div>`;
    }

    async function setupWebcam() {
        if (stream) return true;
        try {
            console.log('Đang bật webcam...');
            stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                }
            });
            video.srcObject = stream;

            await new Promise((resolve) => {
                video.onloadedmetadata = () => {
                    // Thiết lập kích thước canvas khớp với video
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    resolve();
                };
            });

            await video.play();
            console.log('Webcam đã bật.');
            return true;
        } catch (err) {
            console.error('Lỗi webcam:', err);
            showMessage(`Không thể truy cập webcam: ${err.name}. Vui lòng cấp quyền và thử lại.`, 'error');
            return false;
        }
    }

    // Hàm vẽ khung hình theo khuôn mặt
    function drawFaceBox(detection) {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const box = detection.detection.box;
        const drawBox = detection.detection.box;

        // Vẽ khung hình chính
        ctx.strokeStyle = '#48bb78'; // Màu xanh lá
        ctx.lineWidth = 3;
        ctx.strokeRect(drawBox.x, drawBox.y, drawBox.width, drawBox.height);

        // Vẽ các góc trang trí
        const cornerLength = 20;
        ctx.strokeStyle = '#48bb78';
        ctx.lineWidth = 4;

        // Góc trên trái
        ctx.beginPath();
        ctx.moveTo(drawBox.x, drawBox.y + cornerLength);
        ctx.lineTo(drawBox.x, drawBox.y);
        ctx.lineTo(drawBox.x + cornerLength, drawBox.y);
        ctx.stroke();

        // Góc trên phải
        ctx.beginPath();
        ctx.moveTo(drawBox.x + drawBox.width - cornerLength, drawBox.y);
        ctx.lineTo(drawBox.x + drawBox.width, drawBox.y);
        ctx.lineTo(drawBox.x + drawBox.width, drawBox.y + cornerLength);
        ctx.stroke();

        // Góc dưới trái
        ctx.beginPath();
        ctx.moveTo(drawBox.x, drawBox.y + drawBox.height - cornerLength);
        ctx.lineTo(drawBox.x, drawBox.y + drawBox.height);
        ctx.lineTo(drawBox.x + cornerLength, drawBox.y + drawBox.height);
        ctx.stroke();

        // Góc dưới phải
        ctx.beginPath();
        ctx.moveTo(drawBox.x + drawBox.width - cornerLength, drawBox.y + drawBox.height);
        ctx.lineTo(drawBox.x + drawBox.width, drawBox.y + drawBox.height);
        ctx.lineTo(drawBox.x + drawBox.width, drawBox.y + drawBox.height - cornerLength);
        ctx.stroke();

        // Vẽ các landmark (điểm đặc trưng khuôn mặt) - tùy chọn
        if (detection.landmarks) {
            const landmarks = detection.landmarks.positions;
            ctx.fillStyle = '#2196F3';
            landmarks.forEach(point => {
                ctx.beginPath();
                ctx.arc(point.x, point.y, 2, 0, 2 * Math.PI);
                ctx.fill();
            });
        }

        // Hiển thị độ tin cậy
        const confidence = (detection.detection.score * 100).toFixed(1);
        ctx.fillStyle = '#48bb78';
        ctx.font = 'bold 16px Arial';
        ctx.fillText(`${confidence}%`, drawBox.x, drawBox.y - 10);
    }

    // Hàm vẽ cảnh báo không có mặt
    function drawNoFaceWarning() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        // Vẽ vòng tròn cảnh báo ở giữa
        const centerX = canvas.width / 2;
        const centerY = canvas.height / 2;
        const radius = 80;

        ctx.strokeStyle = '#FF9800';
        ctx.lineWidth = 4;
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        ctx.stroke();

        // Vẽ dấu chấm than
        ctx.fillStyle = '#FF9800';
        ctx.font = 'bold 60px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('!', centerX, centerY);

        // Vẽ text cảnh báo
        ctx.font = 'bold 20px Arial';
        ctx.fillText('Không phát hiện khuôn mặt', centerX, centerY + radius + 30);
    }

    // Bắt đầu phát hiện khuôn mặt liên tục
    async function startFaceDetection() {
        if (!await setupWebcam()) return;

        showMessage('Đang quét khuôn mặt... Vui lòng nhìn thẳng vào camera', 'info');

        // Dừng detection cũ nếu có
        if (detectionInterval) {
            clearInterval(detectionInterval);
        }

        // Bắt đầu detection mới
        detectionInterval = setInterval(async () => {
            try {
                const detection = await faceapi
                    .detectSingleFace(video)
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    drawFaceBox(detection);
                    consecutiveNoFaceFrames = 0;

                    // Kiểm tra chất lượng phát hiện
                    const confidence = detection.detection.score;
                    if (confidence > 0.9) {
                        showMessage('✓ Khuôn mặt rõ ràng - Sẵn sàng xác thực', 'success');
                    } else if (confidence > 0.7) {
                        showMessage('Khuôn mặt phát hiện - Vui lòng giữ nguyên tư thế', 'info');
                    } else {
                        showMessage('⚠ Chất lượng khuôn mặt thấp - Hãy di chuyển vào vùng sáng hơn', 'warning');
                    }
                } else {
                    consecutiveNoFaceFrames++;

                    if (consecutiveNoFaceFrames > MAX_NO_FACE_FRAMES) {
                        drawNoFaceWarning();
                        showMessage('⚠ Không phát hiện khuôn mặt - Vui lòng nhìn vào camera', 'warning');
                    }
                }
            } catch (error) {
                console.error('Lỗi trong quá trình phát hiện:', error);
            }
        }, 100); // Chạy mỗi 100ms (10 FPS)
    }

    // Dừng phát hiện khuôn mặt
    function stopFaceDetection() {
        if (detectionInterval) {
            clearInterval(detectionInterval);
            detectionInterval = null;
        }
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    }

    async function captureSingleDescriptor() {
        if (!await setupWebcam()) return null;

        console.log('Đang chụp khuôn mặt...');
        showMessage('Đang chụp... Vui lòng giữ nguyên', 'info');

        const detection = await faceapi
            .detectSingleFace(video)
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            throw new Error('Không nhận dạng được khuôn mặt. Vui lòng nhìn thẳng vào camera.');
        }

        // Kiểm tra chất lượng
        if (detection.detection.score < 0.6) {
            throw new Error('Chất lượng khuôn mặt quá thấp. Vui lòng di chuyển đến nơi sáng hơn.');
        }

        return Array.from(detection.descriptor);
    }

    async function captureMultipleDescriptors() {
        if (!await setupWebcam()) return null;

        const descriptors = [];
        const instructions = [
            "📸 Nhìn thẳng vào camera",
            "◀️ Từ từ quay mặt sang TRÁI một chút",
            "▶️ Từ từ quay mặt sang PHẢI một chút"
        ];

        for (let i = 0; i < instructions.length; i++) {
            showMessage(instructions[i], 'info');

            // Đợi người dùng điều chỉnh
            await new Promise(resolve => setTimeout(resolve, 2000));

            const detection = await faceapi
                .detectSingleFace(video)
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                throw new Error(`Lấy mẫu ${i + 1} thất bại. Không nhận dạng được khuôn mặt.`);
            }

            // Kiểm tra chất lượng
            if (detection.detection.score < 0.6) {
                throw new Error(`Lấy mẫu ${i + 1} thất bại. Chất lượng khuôn mặt quá thấp.`);
            }

            descriptors.push(Array.from(detection.descriptor));
            console.log(`Đã lấy mẫu ${i + 1} (confidence: ${(detection.detection.score * 100).toFixed(1)}%)`);

            // Flash hiệu ứng chụp
            canvas.style.opacity = '0.3';
            setTimeout(() => { canvas.style.opacity = '1'; }, 200);
        }

        return descriptors;
    }

    async function sendToServer(descriptorData, isRegister) {
        if (Date.now() - lastScanTime < DEBOUNCE_MS) {
            throw new Error('Thao tác quá nhanh. Vui lòng chờ 2 giây.');
        }
        lastScanTime = Date.now();

        const descriptorJson = JSON.stringify(descriptorData);

        const formData = new FormData();
        formData.append('face_descriptors', descriptorJson);
        formData.append('register', isRegister ? 'true' : 'false');
        formData.append('csrf_token', csrfToken);

        const response = await fetch('verify.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`Lỗi server: ${response.status}`);
        }

        return response.json();
    }

    async function handleRegister() {
        if (registerButton) registerButton.disabled = true;
        stopFaceDetection();

        try {
            showMessage('Bắt đầu đăng ký khuôn mặt...', 'info');
            const descriptors = await captureMultipleDescriptors();

            showMessage('Đang lưu dữ liệu...', 'info');
            const result = await sendToServer(descriptors, true);

            if (result.status === 'success' && result.message === 'registered') {
                showMessage('✓ Đăng ký thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'face.php?message=registered';
                }, 1500);
            } else {
                throw new Error(JSON.stringify(result, null, 2));
            }
        } catch (err) {
            console.error('Lỗi đăng ký:', err.message);
            showMessage('❌ Lỗi đăng ký: ' + err.message, 'error');
        } finally {
            if (registerButton) registerButton.disabled = false;
            startFaceDetection();
        }
    }

    async function handleScan() {
        if (scanButton) scanButton.disabled = true;
        stopFaceDetection();

        try {
            const descriptor = await captureSingleDescriptor();

            showMessage('Đang xác thực...', 'info');
            const result = await sendToServer(descriptor, false);

            if (result.status === 'success' && result.message === 'verified') {
                showMessage('✓ Xác thực thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'success.php';
                }, 1000);
            } else {
                if (result.message === 'register_first') {
                    showMessage('⚠ Bạn chưa đăng ký khuôn mặt. Vui lòng đăng ký trước.', 'warning');
                    setTimeout(() => window.location.reload(), 2000);
                } else if (result.message === 'no_match') {
                    showMessage('❌ Khuôn mặt không khớp. Vui lòng thử lại.', 'error');
                    setTimeout(() => startFaceDetection(), 2000);
                } else {
                    throw new Error(JSON.stringify(result, null, 2));
                }
            }
        } catch (err) {
            console.error('Lỗi quét:', err.message);
            showMessage('❌ Lỗi quét: ' + err.message, 'error');
            setTimeout(() => startFaceDetection(), 2000);
        } finally {
            if (scanButton) scanButton.disabled = false;
        }
    }

    // Hàm kiểm tra chuyển động đầu (Head Movement Detection)
    async function detectHeadMovement() {
        const samples = [];
        const requiredSamples = 10; // Lấy 10 mẫu trong 1 giây
        showMessage('Vui lòng từ từ quay đầu sang trái và phải...', 'info');
        for (let i = 0; i < requiredSamples; i++) {
            const detection = await faceapi
                .detectSingleFace(video)
                .withFaceLandmarks();
            if (!detection) {
                throw new Error('Mất dấu khuôn mặt. Vui lòng giữ mặt trong khung hình.');
            }
            // Lấy tọa độ mũi (nose tip) - điểm 30
            const nose = detection.landmarks.positions[30];
            samples.push({ x: nose.x, y: nose.y, time: Date.now() });
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        // Tính toán mức độ di chuyển
        const movementX = calculateMovementRange(samples.map(s => s.x));
        const movementY = calculateMovementRange(samples.map(s => s.y));
        console.log(`Movement detected - X: ${movementX.toFixed(2)}px, Y: ${movementY.toFixed(2)}px`);
        // Yêu cầu di chuyển tối thiểu 30px theo trục X (quay đầu)
        if (movementX < 30) {
            throw new Error('Không phát hiện chuyển động. Vui lòng từ từ quay đầu sang trái và phải.');
        }
        return true;
    }

    function calculateMovementRange(values) {
        const max = Math.max(...values);
        const min = Math.min(...values);
        return max - min;
    }

    // Hàm kiểm tra nhấp nháy mắt (Blink Detection)
    async function detectBlink() {
        showMessage('👁️ Vui lòng nhấp nháy mắt 2 lần...', 'info');

        let blinkCount = 0;
        let lastBlinkTime = 0;
        const requiredBlinks = 2;
        const maxTime = 5000; // 5 giây
        const startTime = Date.now();

        while (blinkCount < requiredBlinks && (Date.now() - startTime) < maxTime) {
            const detection = await faceapi
                .detectSingleFace(video)
                .withFaceLandmarks();

            if (!detection) {
                throw new Error('Mất dấu khuôn mặt.');
            }

            // Tính Eye Aspect Ratio (EAR)
            const leftEye = getEyeAspectRatio(detection.landmarks.getLeftEye());
            const rightEye = getEyeAspectRatio(detection.landmarks.getRightEye());
            const avgEAR = (leftEye + rightEye) / 2;

            // EAR < 0.2 = mắt đang nhắm
            if (avgEAR < 0.2 && (Date.now() - lastBlinkTime) > 300) {
                blinkCount++;
                lastBlinkTime = Date.now();
                console.log(`Blink detected! Count: ${blinkCount}`);

                // Visual feedback
                canvas.style.borderColor = '#48bb78';
                setTimeout(() => { canvas.style.borderColor = 'transparent'; }, 200);
            }

            await new Promise(resolve => setTimeout(resolve, 50));
        }

        if (blinkCount < requiredBlinks) {
            throw new Error('Không phát hiện đủ số lần nhấp nháy mắt. Vui lòng thử lại.');
        }

        return true;
    }

    function getEyeAspectRatio(eyePoints) {
        // EAR = (||p2-p6|| + ||p3-p5||) / (2 * ||p1-p4||)
        const p1 = eyePoints[0];
        const p2 = eyePoints[1];
        const p3 = eyePoints[2];
        const p4 = eyePoints[3];
        const p5 = eyePoints[4];
        const p6 = eyePoints[5];

        const vertical1 = euclideanDistance(p2, p6);
        const vertical2 = euclideanDistance(p3, p5);
        const horizontal = euclideanDistance(p1, p4);

        return (vertical1 + vertical2) / (2 * horizontal);
    }

    function euclideanDistance(p1, p2) {
        return Math.sqrt(Math.pow(p2.x - p1.x, 2) + Math.pow(p2.y - p1.y, 2));
    }

    // TÍCH HỢP VÀO HÀM handleScan và handleRegister
    async function handleScanWithLiveness() {
        if (scanButton) scanButton.disabled = true;
        stopFaceDetection();

        try {
            // BƯỚC 1: Kiểm tra liveness (chọn 1 trong 2 hoặc kết hợp)
            // await detectBlink();           // Nhấp nháy mắt
            await detectHeadMovement();      // Quay đầu

            showMessage('✓ Xác thực người thật thành công!', 'success');
            await new Promise(resolve => setTimeout(resolve, 1000));

            // BƯỚC 2: Chụp descriptor
            const descriptor = await captureSingleDescriptor();

            // BƯỚC 3: Gửi lên server
            showMessage('Đang xác thực...', 'info');
            const result = await sendToServer(descriptor, false);

            if (result.status === 'success' && result.message === 'verified') {
                showMessage('✓ Xác thực thành công!', 'success');
                setTimeout(() => {
                    window.location.href = 'success.php';
                }, 1000);
            } else {
                if (result.message === 'register_first') {
                    showMessage('⚠ Bạn chưa đăng ký khuôn mặt.', 'warning');
                    setTimeout(() => window.location.reload(), 2000);
                } else if (result.message === 'no_match') {
                    showMessage('❌ Khuôn mặt không khớp.', 'error');
                    setTimeout(() => startFaceDetection(), 2000);
                } else {
                    throw new Error(JSON.stringify(result, null, 2));
                }
            }
        } catch (err) {
            console.error('Lỗi quét:', err.message);
            showMessage('❌ ' + err.message, 'error');
            setTimeout(() => startFaceDetection(), 2000);
        } finally {
            if (scanButton) scanButton.disabled = false;
        }
    }

    if (registerButton) {
        registerButton.addEventListener('click', handleRegister);
    }

    if (scanButton) {
        scanButton.addEventListener('click', handleScanWithLiveness);
    }

    // Bắt đầu phát hiện khuôn mặt khi trang load
    await startFaceDetection();

    console.log('Hệ thống xác thực khuôn mặt đã sẵn sàng.');
});