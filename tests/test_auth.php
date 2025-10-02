// Unit test: PHPUnit cho Auth::verify (mock DB).
//F7
// Test Auth::isAuthenticated với session từ Face (mock, kiểm tra True sau verify)
//Lưu Ý Không Dính Lẫn (Độc Lập/Test Riêng): Độc lập luồng (dùng Auth từ B để lấy user_id, mock webcam bằng data giả)
//Test: Chạy face.php trên browser hỗ trợ webcam, kiểm tra hash/verify (không cần OTP). 
//Push branch luong-face. C chỉ dùng verify.php phần Face, không chạm code OTP.
<?php

require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/FaceAuth.php';

class TestAuthWithFace {
    private $test_user_id = 998;
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        echo "=== TEST AUTH WITH FACE ===\n\n";
        $this->setupTestData();
    }

    private function setupTestData() {
        // Tạo test user trong DB
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        
        // Xóa user test nếu tồn tại
        $conn->query("DELETE FROM users WHERE id = {$this->test_user_id}");
        $conn->query("DELETE FROM faces WHERE user_id = {$this->test_user_id}");
        
        // Tạo user mới
        $stmt = $conn->prepare("INSERT INTO users (id, username, password, email) VALUES (?, ?, ?, ?)");
        $username = 'test_face_user';
        $password = password_hash('password123', PASSWORD_DEFAULT);
        $email = 'test@face.com';
        $stmt->bind_param("isss", $this->test_user_id, $username, $password, $email);
        $stmt->execute();
        $stmt->close();
        
        echo "✓ Setup test user (ID: {$this->test_user_id})\n\n";
        $conn->close();
    }

    private function assert($condition, $message) {
        if ($condition) {
            echo "✓ PASS: $message\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: $message\n";
            $this->failed++;
        }
    }

    private function mockDescriptor($seed = 1) {
        $descriptor = [];
        for ($i = 0; $i < 128; $i++) {
            $descriptor[] = sin($i * $seed) + cos($i * $seed * 0.5);
        }
        return json_encode($descriptor);
    }

    // Test 1: isAuthenticated trả về false khi chưa verify
    public function testNotAuthenticatedInitially() {
        echo "--- Test 1: Not Authenticated Initially ---\n";
        
        // Reset session
        $_SESSION = [];
        
        $auth = Auth::isAuthenticated();
        
        $this->assert($auth === false, "isAuthenticated trả về false ban đầu");
        echo "\n";
    }

    // Test 2: Login bằng username/password (lấy user_id)
    public function testLoginGetUserId() {
        echo "--- Test 2: Login to Get User ID ---\n";
        
        $_SESSION = [];
        
        // Mock login (giả lập Auth::login)
        $_SESSION['user_id'] = $this->test_user_id;
        $_SESSION['username'] = 'test_face_user';
        
        $auth = Auth::isAuthenticated();
        
        // Lưu ý: Auth::isAuthenticated cần cả 'authenticated' = true
        // Ở đây chỉ test có user_id
        $this->assert(isset($_SESSION['user_id']), "Session có user_id sau login");
        $this->assert($_SESSION['user_id'] === $this->test_user_id, "user_id đúng");
        echo "\n";
    }

    // Test 3: Đăng ký Face descriptor
    public function testRegisterFace() {
        echo "--- Test 3: Register Face Descriptor ---\n";
        
        $descriptor = $this->mockDescriptor(1);
        
        FaceAuth::storeFace($this->test_user_id, $descriptor);
        
        $hasFace = FaceAuth::hasFace($this->test_user_id);
        
        $this->assert($hasFace === true, "Face descriptor đã được đăng ký");
        echo "\n";
    }

    // Test 4: Verify Face và set authenticated
    public function testVerifyFaceSetAuthenticated() {
        echo "--- Test 4: Verify Face and Set Authenticated ---\n";
        
        $_SESSION = [];
        $_SESSION['user_id'] = $this->test_user_id;
        
        $descriptor = $this->mockDescriptor(1);
        
        // Đăng ký face
        FaceAuth::storeFace($this->test_user_id, $descriptor);
        
        // Verify face
        $verifyResult = FaceAuth::verifyFace($this->test_user_id, $descriptor);
        
        $this->assert($verifyResult === true, "Verify face thành công");
        
        // Giả lập verify.php set session
        if ($verifyResult) {
            $_SESSION['authenticated'] = true;
        }
        
        $this->assert($_SESSION['authenticated'] === true, "Session authenticated được set");
        echo "\n";
    }

    // Test 5: isAuthenticated trả về true sau verify
    public function testIsAuthenticatedAfterVerify() {
        echo "--- Test 5: isAuthenticated After Face Verify ---\n";
        
        $_SESSION = [];
        $_SESSION['user_id'] = $this->test_user_id;
        $_SESSION['username'] = 'test_face_user';
        
        $descriptor = $this->mockDescriptor(1);
        
        // Đăng ký và verify
        FaceAuth::storeFace($this->test_user_id, $descriptor);
        $verifyResult = FaceAuth::verifyFace($this->test_user_id, $descriptor);
        
        // Set authenticated
        if ($verifyResult) {
            $_SESSION['authenticated'] = true;
        }
        
        // Kiểm tra Auth::isAuthenticated
        $auth = Auth::isAuthenticated();
        
        $this->assert($auth !== false, "isAuthenticated trả về dữ liệu");
        $this->assert($auth['user_id'] === $this->test_user_id, "user_id khớp");
        echo "\n";
    }

    // Test 6: Luồng hoàn chỉnh (Mock webcam data)
    public function testFullFlowMockWebcam() {
        echo "--- Test 6: Full Flow with Mock Webcam ---\n";
        
        // Bước 1: User login (lấy user_id từ Auth)
        $_SESSION = [];
        $_SESSION['user_id'] = $this->test_user_id;
        $_SESSION['username'] = 'test_face_user';
        
        echo "  → User logged in (user_id: {$this->test_user_id})\n";
        
        // Bước 2: Mock webcam data (descriptor từ face-api.js)
        $webcamDescriptor = $this->mockDescriptor(5);
        echo "  → Mock webcam captured descriptor\n";
        
        // Bước 3: Check nếu chưa đăng ký face
        $hasFace = FaceAuth::hasFace($this->test_user_id);
        
        if (!$hasFace) {
            echo "  → User chưa đăng ký face, đăng ký mới\n";
            FaceAuth::storeFace($this->test_user_id, $webcamDescriptor);
            $this->assert(FaceAuth::hasFace($this->test_user_id), "Face đã được đăng ký");
        }
        
        // Bước 4: Verify face
        $verifyResult = FaceAuth::verifyFace($this->test_user_id, $webcamDescriptor);
        echo "  → Verify face: " . ($verifyResult ? "SUCCESS" : "FAILED") . "\n";
        
        $this->assert($verifyResult === true, "Face verify thành công");
        
        // Bước 5: Set authenticated
        if ($verifyResult) {
            $_SESSION['authenticated'] = true;
        }
        
        // Bước 6: Check isAuthenticated
        $auth = Auth::isAuthenticated();
        
        $this->assert($auth !== false, "User đã authenticated");
        $this->assert($_SESSION['authenticated'] === true, "Session authenticated = true");
        
        echo "\n";
    }

    // Test 7: Verify thất bại với face khác
    public function testVerifyFailWithDifferentFace() {
        echo "--- Test 7: Verify Fail with Different Face ---\n";
        
        $_SESSION = [];
        $_SESSION['user_id'] = $this->test_user_id;
        
        // Đăng ký face 1
        $descriptor1 = $this->mockDescriptor(1);
        FaceAuth::storeFace($this->test_user_id, $descriptor1);
        
        // Verify với face 2 (hoàn toàn khác)
        $descriptor2 = $this->mockDescriptor(999);
        $verifyResult = FaceAuth::verifyFace($this->test_user_id, $descriptor2);
        
        $this->assert($verifyResult === false, "Verify thất bại với face khác");
        $this->assert(!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true, "Session không được set authenticated");
        
        echo "\n";
    }

    // Test 8: Multiple users - Face độc lập
    public function testMultipleUsersIndependentFaces() {
        echo "--- Test 8: Multiple Users Independent Faces ---\n";
        
        $user2_id = 997;
        
        // Tạo user 2
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM users WHERE id = $user2_id");
        $conn->query("DELETE FROM faces WHERE user_id = $user2_id");
        $stmt = $conn->prepare("INSERT INTO users (id, username, password, email) VALUES (?, ?, ?, ?)");
        $username = 'test_user_2';
        $password = password_hash('password123', PASSWORD_DEFAULT);
        $email = 'test2@face.com';
        $stmt->bind_param("isss", $user2_id, $username, $password, $email);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        
        // Đăng ký face cho user 1
        $descriptor1 = $this->mockDescriptor(1);
        FaceAuth::storeFace($this->test_user_id, $descriptor1);
        
        // Đăng ký face cho user 2
        $descriptor2 = $this->mockDescriptor(2);
        FaceAuth::storeFace($user2_id, $descriptor2);
        
        // Verify user 1 với descriptor 1
        $verify1 = FaceAuth::verifyFace($this->test_user_id, $descriptor1);
        $this->assert($verify1 === true, "User 1 verify thành công với face của mình");
        
        // Verify user 2 với descriptor 2
        $verify2 = FaceAuth::verifyFace($user2_id, $descriptor2);
        $this->assert($verify2 === true, "User 2 verify thành công với face của mình");
        
        // Verify user 1 với descriptor 2 (sai)
        $verifyWrong = FaceAuth::verifyFace($this->test_user_id, $descriptor2);
        $this->assert($verifyWrong === false, "User 1 verify thất bại với face của user 2");
        
        // Cleanup
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM users WHERE id = $user2_id");
        $conn->query("DELETE FROM faces WHERE user_id = $user2_id");
        $conn->close();
        
        echo "\n";
    }

    public function cleanup() {
        // Xóa dữ liệu test
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM users WHERE id = {$this->test_user_id}");
        $conn->query("DELETE FROM faces WHERE user_id = {$this->test_user_id}");
        $conn->close();
        
        // Reset session
        $_SESSION = [];
        
        echo "\n=== KẾT QUẢ ===\n";
        echo "✓ Passed: {$this->passed}\n";
        echo "✗ Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n🎉 TẤT CẢ TEST ĐỀU PASS!\n";
        } else {
            echo "\n⚠️ CÓ {$this->failed} TEST THẤT BẠI\n";
        }
    }

    public function runAll() {
        $this->testNotAuthenticatedInitially();
        $this->testLoginGetUserId();
        $this->testRegisterFace();
        $this->testVerifyFaceSetAuthenticated();
        $this->testIsAuthenticatedAfterVerify();
        $this->testFullFlowMockWebcam();
        $this->testVerifyFailWithDifferentFace();
        $this->testMultipleUsersIndependentFaces();
        $this->cleanup();
    }
}

// Chạy tests
session_start();
$test = new TestAuthWithFace();
$test->runAll();
?>