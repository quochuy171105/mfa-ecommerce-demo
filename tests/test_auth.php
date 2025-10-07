<?php
// Unit test: PHPUnit cho Auth::verify (mock DB).
// Test Auth::isAuthenticated với session từ Face (mock, kiểm tra True sau verify)
// Lưu Ý Không Dính Lẫn (Độc Lập/Test Riêng): Độc lập luồng (dùng Auth từ B để lấy user_id, mock webcam bằng data giả)
// Test: Chạy face.php trên browser hỗ trợ webcam, kiểm tra hash/verify (không cần OTP). 
// Push branch luong-face. C chỉ dùng verify.php phần Face, không chạm code OTP.

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/FaceAuth.php';
require_once __DIR__ . '/../config/database.php'; // Global $pdo for mock

use PHPUnit\Framework\TestCase;

class AuthWithFaceTest extends TestCase {
    protected $pdo;
    protected $test_user_id = 998;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        global $pdo;
        $pdo = $this->pdo;
    }

    public function testNotAuthenticatedInitially() {
        $_SESSION = [];
        $auth = Auth::isAuthenticated();
        $this->assertFalse($auth, "Ban đầu không authenticated");
    }

    public function testLoginGetUserId() {
        // Mock User::getUserByEmail
        $mockUser = ['id' => $this->test_user_id, 'email' => 'test@face.com', 'pass_hash' => password_hash('testpass', PASSWORD_DEFAULT)];
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockUser);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = Auth::login('test@face.com', 'testpass');
        $this->assertTrue($result['success'], "Login thành công");
        $this->assertEquals($this->test_user_id, $_SESSION['user_id'], "User ID lưu đúng");
    }

    public function testRegisterFace() {
        // Mock storeFace
        $descriptor = [0.1, 0.2, 0.3]; // Mock descriptor
        $descriptorJson = json_encode($descriptor);
        $hashedDescriptor = hash('sha256', $descriptorJson);

        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        FaceAuth::storeFace($this->test_user_id, $descriptorJson);

        // Mock query to check stored
        $mockRow = ['face_hash' => $hashedDescriptor, 'face_descriptors' => $descriptorJson];
        $mockStmt2 = $this->createMock(PDOStatement::class);
        $mockStmt2->method('execute')->willReturn(true);
        $mockStmt2->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturnOnConsecutiveCalls($mockStmt, $mockStmt2); // For store and check

        $hasFace = FaceAuth::hasFace($this->test_user_id);
        $this->assertTrue($hasFace, "Face đã đăng ký");
    }

    public function testVerifyFaceSetAuthenticated() {
        // Mock verifyFace true
        $descriptor = [0.1, 0.2, 0.3];
        $descriptorJson = json_encode($descriptor);
        $hashedDescriptor = hash('sha256', $descriptorJson);

        $mockRow = ['face_hash' => $hashedDescriptor, 'face_descriptors' => $descriptorJson];
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson, 'mock_csrf');

        $this->assertTrue($result, "Verify face thành công");

        // Check session authenticated
        $this->assertTrue(isset($_SESSION['authenticated']), "Authenticated session set");
    }

    public function testIsAuthenticatedAfterVerify() {
        // Mock session with jwt_token
        $_SESSION['jwt_token'] = 'mock_jwt';
        $_SESSION['user_id'] = $this->test_user_id;

        // Mock verify_jwt true
        $mockPayload = ['user_id' => $this->test_user_id, 'email' => 'test@face.com', 'exp' => time() + 3600];
        $mockVerify = function($token, $secret) use ($mockPayload) {
            return $mockPayload;
        };
        // Override verify_jwt for test
        $this->assertTrue(Auth::isAuthenticated(), "isAuthenticated true sau verify");
    }

    public function testFullFlowMockWebcam() {
        // Mock webcam descriptor
        $descriptor = [0.1, 0.2, 0.3];
        $descriptorJson = json_encode($descriptor);

        // Mock storeFace
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        FaceAuth::storeFace($this->test_user_id, $descriptorJson);

        // Mock verifyFace true
        $mockRow = ['face_hash' => hash('sha256', $descriptorJson), 'face_descriptors' => $descriptorJson];
        $mockStmt2 = $this->createMock(PDOStatement::class);
        $mockStmt2->method('execute')->willReturn(true);
        $mockStmt2->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturnOnConsecutiveCalls($mockStmt, $mockStmt2);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson, 'mock_csrf');

        $this->assertTrue($result, "Full flow verify thành công");
    }

    public function testVerifyFailWithDifferentFace() {
        $descriptor1 = [0.1, 0.2, 0.3];
        $descriptorJson1 = json_encode($descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson1);

        $descriptor2 = [1.1, 1.2, 1.3]; // Khác biệt
        $descriptorJson2 = json_encode($descriptor2);

        $mockRow = ['face_hash' => hash('sha256', $descriptorJson1), 'face_descriptors' => $descriptorJson1];
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson2, 'mock_csrf');

        $this->assertFalse($result, "Verify thất bại với face khác");
    }

    public function testMultipleUsersIndependentFaces() {
        $user2_id = 888;
        $descriptor1 = [0.1, 0.2, 0.3];
        $descriptorJson1 = json_encode($descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson1);

        $descriptor2 = [1.1, 1.2, 1.3];
        $descriptorJson2 = json_encode($descriptor2);
        FaceAuth::storeFace($user2_id, $descriptorJson2);

        // Mock for user1
        $mockRow1 = ['face_hash' => hash('sha256', $descriptorJson1), 'face_descriptors' => $descriptorJson1];
        $mockStmt1 = $this->createMock(PDOStatement::class);
        $mockStmt1->method('execute')->willReturn(true);
        $mockStmt1->method('fetch')->willReturn($mockRow1);
        $this->pdo->method('prepare')->willReturnOnConsecutiveCalls($mockStmt1, $mockStmt1);

        $verify1 = FaceAuth::verifyFace($this->test_user_id, $descriptorJson1, 'mock_csrf');
        $this->assertTrue($verify1, "User 1 verify thành công với face của mình");

        // Mock for user2
        $mockRow2 = ['face_hash' => hash('sha256', $descriptorJson2), 'face_descriptors' => $descriptorJson2];
        $mockStmt2 = $this->createMock(PDOStatement::class);
        $mockStmt2->method('execute')->willReturn(true);
        $mockStmt2->method('fetch')->willReturn($mockRow2);
        $this->pdo->method('prepare')->willReturn($mockStmt2);

        $verify2 = FaceAuth::verifyFace($user2_id, $descriptorJson2, 'mock_csrf');
        $this->assertTrue($verify2, "User 2 verify thành công với face của mình");

        // Verify user 1 với descriptor 2 (sai)
        $verifyWrong = FaceAuth::verifyFace($this->test_user_id, $descriptorJson2, 'mock_csrf');
        $this->assertFalse($verifyWrong, "User 1 verify thất bại với face của user 2");

        // Cleanup
        global $pdo;
        $pdo->prepare("DELETE FROM faces WHERE user_id IN (?, ?)")
            ->execute([$this->test_user_id, $user2_id]);

        echo "\n";
    }

    protected function tearDown(): void {
        // Cleanup test data
        global $pdo;
        $pdo->prepare("DELETE FROM users WHERE id = ?")
            ->execute([$this->test_user_id]);
        $pdo->prepare("DELETE FROM faces WHERE user_id = ?")
            ->execute([$this->test_user_id]);
    }
}

$test = new AuthWithFaceTest();
$test->testNotAuthenticatedInitially();
$test->testLoginGetUserId();
$test->testRegisterFace();
$test->testVerifyFaceSetAuthenticated();
$test->testIsAuthenticatedAfterVerify();
$test->testFullFlowMockWebcam();
$test->testVerifyFailWithDifferentFace();
$test->testMultipleUsersIndependentFaces();
?>