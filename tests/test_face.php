// Unit test: Face hash comparison.
//F6
//Test FaceAuth::verifyFace (mock DB, kiểm tra hash_equals đúng/sai), test storeFace (mock insert).
<?php
require_once __DIR__ . '/../classes/FaceAuth.php';

class TestFaceAuth {
    private $test_user_id = 999;
    private $passed = 0;
    private $failed = 0;

    public function __construct() {
        echo "=== TEST FACE AUTH ===\n\n";
        $this->setupTestDB();
    }

    private function setupTestDB() {
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        
        $conn->query("DELETE FROM faces WHERE user_id = {$this->test_user_id}");
        
        echo "✓ Setup test database\n\n";
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

    // Test 1: Store Face - Lưu descriptor lần đầu
    public function testStoreFaceNew() {
        echo "--- Test 1: Store Face (New) ---\n";
        
        $descriptor = $this->mockDescriptor(1);
        FaceAuth::storeFace($this->test_user_id, $descriptor);
        
        // Kiểm tra đã lưu vào DB
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $stmt = $conn->prepare("SELECT face_descriptors FROM faces WHERE user_id = ?");
        $stmt->bind_param("i", $this->test_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $this->assert($row !== null, "Descriptor đã được lưu vào DB");
        $this->assert(!empty($row['face_descriptors']), "Descriptor không rỗng");
        $this->assert($row['face_descriptors'] === $descriptor, "Descriptor khớp với dữ liệu gốc");
        
        $stmt->close();
        $conn->close();
        echo "\n";
    }

    // Test 2: Store Face - Update descriptor đã tồn tại
    public function testStoreFaceUpdate() {
        echo "--- Test 2: Store Face (Update) ---\n";
        
        $descriptor1 = $this->mockDescriptor(1);
        $descriptor2 = $this->mockDescriptor(2); // Descriptor khác
        
        FaceAuth::storeFace($this->test_user_id, $descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptor2); // Overwrite
        
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $stmt = $conn->prepare("SELECT face_descriptors FROM faces WHERE user_id = ?");
        $stmt->bind_param("i", $this->test_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        $this->assert($row['face_descriptors'] === $descriptor2, "Descriptor đã được update");
        $this->assert($row['face_descriptors'] !== $descriptor1, "Descriptor cũ đã bị ghi đè");
        
        $stmt->close();
        $conn->close();
        echo "\n";
    }

    // Test 3: Verify Face - Khớp (cùng descriptor)
    public function testVerifyFaceMatch() {
        echo "--- Test 3: Verify Face (Match) ---\n";
        
        $descriptor = $this->mockDescriptor(1);
        
        FaceAuth::storeFace($this->test_user_id, $descriptor);
        
        $result = FaceAuth::verifyFace($this->test_user_id, $descriptor);
        
        $this->assert($result === true, "Verify thành công với descriptor giống hệt");
        echo "\n";
    }

    // Test 4: Verify Face - Khớp gần (descriptor hơi khác nhưng < threshold)
    public function testVerifyFaceNearMatch() {
        echo "--- Test 4: Verify Face (Near Match) ---\n";
        
        $descriptor1 = $this->mockDescriptor(1);
        
       
        $arr1 = json_decode($descriptor1, true);
        $arr2 = [];
        foreach ($arr1 as $val) {
            $arr2[] = $val + (rand(-100, 100) / 10000); // Noise rất nhỏ
        }
        $descriptor2 = json_encode($arr2);
        
     
        FaceAuth::storeFace($this->test_user_id, $descriptor1);
        
       
        $result = FaceAuth::verifyFace($this->test_user_id, $descriptor2);
        
        $this->assert($result === true, "Verify thành công với descriptor gần giống");
        echo "\n";
    }

    // Test 5: Verify Face - Không khớp (descriptor hoàn toàn khác)
    public function testVerifyFaceNoMatch() {
        echo "--- Test 5: Verify Face (No Match) ---\n";
        
        $descriptor1 = $this->mockDescriptor(1);
        $descriptor2 = $this->mockDescriptor(999); // Hoàn toàn khác
        
        
        FaceAuth::storeFace($this->test_user_id, $descriptor1);
        
        
        $result = FaceAuth::verifyFace($this->test_user_id, $descriptor2);
        
        $this->assert($result === false, "Verify thất bại với descriptor khác");
        echo "\n";
    }

    // Test 6: Verify Face - User chưa đăng ký
    public function testVerifyFaceNotRegistered() {
        echo "--- Test 6: Verify Face (Not Registered) ---\n";
        
        $unregistered_user = 888;
        
        // Xóa user nếu tồn tại
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM faces WHERE user_id = $unregistered_user");
        $conn->close();
        
        $descriptor = $this->mockDescriptor(1);
        $result = FaceAuth::verifyFace($unregistered_user, $descriptor);
        
        $this->assert($result === false, "Verify thất bại khi user chưa đăng ký");
        echo "\n";
    }

    // Test 7: Has Face - Kiểm tra user đã đăng ký chưa
    public function testHasFace() {
        echo "--- Test 7: Has Face ---\n";
        
        $new_user = 777;
        
        // Xóa user
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM faces WHERE user_id = $new_user");
        $conn->close();
        
        
        $hasFace1 = FaceAuth::hasFace($new_user);
        $this->assert($hasFace1 === false, "hasFace trả về false khi chưa đăng ký");
        
       
        $descriptor = $this->mockDescriptor(1);
        FaceAuth::storeFace($new_user, $descriptor);
        
       
        $hasFace2 = FaceAuth::hasFace($new_user);
        $this->assert($hasFace2 === true, "hasFace trả về true sau khi đăng ký");
        
        echo "\n";
    }

   
    public function testVerifyMultipleDescriptors() {
        echo "--- Test 8: Verify Multiple Descriptors ---\n";
        
        // Tạo 3 descriptors (giả lập 3 góc độ)
        $descriptors = [
            json_decode($this->mockDescriptor(1), true),
            json_decode($this->mockDescriptor(2), true),
            json_decode($this->mockDescriptor(3), true),
        ];
        $multiDescriptor = json_encode($descriptors);
        

        FaceAuth::storeFace($this->test_user_id, $multiDescriptor);
        
        $singleDescriptor = $this->mockDescriptor(2);
        $result = FaceAuth::verifyFace($this->test_user_id, $singleDescriptor);
        
        $this->assert($result === true, "Verify thành công với 1 trong 3 descriptors");
        echo "\n";
    }

    // Test 9: Invalid JSON descriptor
    public function testInvalidDescriptor() {
        echo "--- Test 9: Invalid Descriptor ---\n";
        
        $invalidDescriptor = "not a json";
        
        FaceAuth::storeFace($this->test_user_id, $invalidDescriptor);
        $result = FaceAuth::verifyFace($this->test_user_id, $invalidDescriptor);
        
        $this->assert($result === false, "Verify thất bại với descriptor không hợp lệ");
        echo "\n";
    }

    public function cleanup() {
        // Xóa dữ liệu test
        $conn = new mysqli('localhost', 'root', '', 'mfa_demo');
        $conn->query("DELETE FROM faces WHERE user_id IN ({$this->test_user_id}, 888, 777)");
        $conn->close();
        
        echo "\n=== KẾT QUẢ ===\n";
        echo "✓ Passed: {$this->passed}\n";
        echo "✗ Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        
        if ($this->failed === 0) {
            echo "\n TẤT CẢ TEST ĐỀU PASS!\n";
        } else {
            echo "\n CÓ {$this->failed} TEST THẤT BẠI\n";
        }
    }

    public function runAll() {
        $this->testStoreFaceNew();
        $this->testStoreFaceUpdate();
        $this->testVerifyFaceMatch();
        $this->testVerifyFaceNearMatch();
        $this->testVerifyFaceNoMatch();
        $this->testVerifyFaceNotRegistered();
        $this->testHasFace();
        $this->testVerifyMultipleDescriptors();
        $this->testInvalidDescriptor();
        $this->cleanup();
    }
}

// Chạy tests
$test = new TestFaceAuth();
$test->runAll();
?>