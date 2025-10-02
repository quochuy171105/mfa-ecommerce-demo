document.addEventListener('DOMContentLoaded', async () => {
    console.log('BẮT ĐẦU');

    // Load models
    async function loadModels() {
        for (let i = 0; i < 3; i++) {
            try {
                console.log(`Đang tải models... (${i + 1})`);
                await faceapi.nets.ssdMobilenetv1.loadFromUri('../assets/js/weights');
                await faceapi.nets.faceLandmark68Net.loadFromUri('../assets/js/weights');
                await faceapi.nets.faceRecognitionNet.loadFromUri('../assets/js/weights');
                console.log('Models tải thành công');
                return true;
            } catch (err) {
                console.error(`Lỗi tải models:`, err);
                if (i === 2) {
                    alert('Không thể tải models. Kiểm tra kết nối.');
                    return false;
                }
                await new Promise(resolve => setTimeout(resolve, 2000));
            }
        }
    }

    if (!(await loadModels())) return;

    const video = document.getElementById('video');
    if (!video) {
        console.error('Không tìm thấy video');
        return;
    }

    let stream = null;
    let canvas = null;
    let displaySize = null;

    // Bật webcam
    try {
        console.log('Đang bật webcam...');
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { width: 640, height: 480 } 
        });
        video.srcObject = stream;
        await video.play();
        console.log('Webcam đã bật');
    } catch (err) {
        console.error('Lỗi webcam:', err);
        alert('Không thể truy cập webcam: ' + err.message);
        return;
    }

    // Đợi video sẵn sàng
    await new Promise((resolve) => {
        const check = setInterval(() => {
            if (video.readyState >= 2) {
                clearInterval(check);
                resolve();
            }
        }, 100);
    });

    // Tạo canvas
    console.log('Tạo canvas...');
    canvas = faceapi.createCanvasFromMedia(video);
    document.body.appendChild(canvas);
    displaySize = { 
        width: video.videoWidth || 640, 
        height: video.videoHeight || 480 
    };
    faceapi.matchDimensions(canvas, displaySize);
    console.log('Canvas sẵn sàng');

    // Hàm chụp nhiều góc (đăng ký)
    async function captureMultipleDescriptors() {
        console.log('=== ĐĂNG KÝ ===');
        const descriptors = [];
        const instructions = [
            "Nhìn thẳng vào camera",
            "Nghiêng mặt sang TRÁI 45°",
            "Nghiêng mặt sang PHẢI 45°"
        ];

        for (let i = 0; i < instructions.length; i++) {
            alert(instructions[i] + '\n\nNhấn OK và giữ yên 3 giây');
            await new Promise(resolve => setTimeout(resolve, 3000));

            console.log(`Chụp góc ${i + 1}...`);
            const detection = await faceapi
                .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
                .withFaceLandmarks()
                .withFaceDescriptor();

            if (!detection) {
                throw new Error('Không phát hiện khuôn mặt ở góc ' + (i + 1));
            }

            const descriptor = Array.from(detection.descriptor);
            descriptors.push(descriptor);
            console.log(`Góc ${i + 1} thành công`);

            // Vẽ
            const resized = faceapi.resizeResults(detection, displaySize);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            faceapi.draw.drawDetections(canvas, resized);
        }

        return JSON.stringify(descriptors);
    }

    // Hàm chụp 1 lần (quét)
    async function captureSingleDescriptor() {
        console.log('QUÉT');
        
        const detection = await faceapi
            .detectSingleFace(video, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
            .withFaceLandmarks()
            .withFaceDescriptor();

        if (!detection) {
            throw new Error('Không phát hiện khuôn mặt');
        }

        console.log(' Phát hiện khuôn mặt');
        const descriptor = Array.from(detection.descriptor);

        // Vẽ
        const resized = faceapi.resizeResults(detection, displaySize);
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        faceapi.draw.drawDetections(canvas, resized);

        return JSON.stringify(descriptor);
    }

    // Gửi lên server
    async function sendToServer(descriptorJson, isRegister) {
        console.log('Gửi lên server...');
        const response = await fetch('../pages/verify.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `face_descriptors=${encodeURIComponent(descriptorJson)}&register=${isRegister}`
        });

        const text = await response.text();
        console.log('Phản hồi:', text.trim());
        return text.trim();
    }

    // Xử lý đăng ký
    async function handleRegister() {
        const btn = document.getElementById('registerFace');
        if (btn) btn.disabled = true;
        
        try {
            const descriptorJson = await captureMultipleDescriptors();
            const result = await sendToServer(descriptorJson, true);

            if (result === 'registered') {
                alert(' Đăng ký thành công!');
                window.location.href = '../pages/face.php?message=registered';
            } else {
                throw new Error('Đăng ký thất bại: ' + result);
            }
        } catch (err) {
            console.error('❌ Lỗi:', err);
            alert('Lỗi: ' + err.message);
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // Xử lý quét
    async function handleScan() {
        const btn = document.getElementById('scanFace');
        if (btn) btn.disabled = true;
        
        try {
            const descriptorJson = await captureSingleDescriptor();
            const result = await sendToServer(descriptorJson, false);

            if (result === 'success') {
                alert(' Xác thực thành công!');
                window.location.href = '../pages/success.php';
            } else if (result === 'register_first') {
                alert(' Vui lòng đăng ký khuôn mặt trước');
            } else if (result === 'no_match') {
                alert(' Khuôn mặt không khớp!');
            } else {
                alert('Lỗi: ' + result);
            }
        } catch (err) {
            console.error(' Lỗi:', err);
            alert('Lỗi: ' + err.message);
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    // Gắn sự kiện
    const registerButton = document.getElementById('registerFace');
    const scanButton = document.getElementById('scanFace');

    if (registerButton) {
        registerButton.addEventListener('click', handleRegister);
        console.log(' Nút Đăng Ký đã gắn');
    }

    if (scanButton) {
        scanButton.addEventListener('click', handleScan);
        console.log(' Nút Quét đã gắn');
    }

    console.log('=== SẴN SÀNG ===');
});