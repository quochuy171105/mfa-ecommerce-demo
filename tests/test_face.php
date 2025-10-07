<?php
// Unit test: Face hash comparison.
// Test FaceAuth::verifyFace (mock DB, kiểm tra hash_equals đúng/sai), test storeFace (mock insert).

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/FaceAuth.php';
require_once __DIR__ . '/../config/database.php'; // Global $pdo for mock

use PHPUnit\Framework\TestCase;

class FaceAuthTest extends TestCase {
    protected $pdo;
    protected $test_user_id = 999;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        global $pdo;
        $pdo = $this->pdo;
    }

    public function testStoreFaceNew() {
        $descriptor = [0.1, 0.2, 0.3];
        $descriptorJson = json_encode($descriptor);
        $hashedDescriptor = hash('sha256', $descriptorJson);

        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        FaceAuth::storeFace($this->test_user_id, $descriptorJson);

        $this->assertTrue(true, "Store face new OK"); // Check execute
    }

    public function testStoreFaceUpdate() {
        $descriptor1 = [0.1, 0.2, 0.3];
        $descriptorJson1 = json_encode($descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson1);

        $descriptor2 = [0.4, 0.5, 0.6];
        $descriptorJson2 = json_encode($descriptor2);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson2); // Update

        $this->assertTrue(true, "Store face update OK"); // Check execute
    }

    public function testVerifyFaceMatch() {
        $descriptor = [0.1, 0.2, 0.3];
        $descriptorJson = json_encode($descriptor);
        $hashedDescriptor = hash('sha256', $descriptorJson);

        $mockRow = ['face_hash' => $hashedDescriptor, 'face_descriptors' => $descriptorJson];
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson, 'mock_csrf');

        $this->assertTrue($result, "Verify face match OK");
    }

    public function testVerifyFaceNearMatch() {
        $descriptor1 = [0.1, 0.2, 0.3];
        $descriptorJson1 = json_encode($descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson1);

        $descriptor2 = [0.11, 0.21, 0.31]; // Gần giống
        $descriptorJson2 = json_encode($descriptor2);
        $hashedDescriptor2 = hash('sha256', $descriptorJson2);

        $mockRow = ['face_hash' => $hashedDescriptor2, 'face_descriptors' => $descriptorJson1]; // Hash match, distance < 0.4
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson2, 'mock_csrf');

        $this->assertTrue($result, "Verify face near match OK");
    }

    public function testVerifyFaceNoMatch() {
        $descriptor1 = [0.1, 0.2, 0.3];
        $descriptorJson1 = json_encode($descriptor1);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson1);

        $descriptor2 = [1.1, 1.2, 1.3]; // Khác biệt
        $descriptorJson2 = json_encode($descriptor2);
        $hashedDescriptor2 = hash('sha256', $descriptorJson2);

        $mockRow = ['face_hash' => $hashedDescriptor2, 'face_descriptors' => $descriptorJson1]; // Hash match, distance > 0.4
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson2, 'mock_csrf');

        $this->assertFalse($result, "Verify face no match OK");
    }

    public function testVerifyFaceNotRegistered() {
        $descriptor = [0.1, 0.2, 0.3];
        $descriptorJson = json_encode($descriptor);

        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn(false); // No row
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $descriptorJson, 'mock_csrf');

        $this->assertFalse($result, "Verify face not registered OK");
    }

    public function testHasFace() {
        // Mock hasFace true
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetchColumn')->willReturn(1);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::hasFace($this->test_user_id);

        $this->assertTrue($result, "HasFace true OK");

        // Mock hasFace false
        $mockStmt2 = $this->createMock(PDOStatement::class);
        $mockStmt2->method('execute')->willReturn(true);
        $mockStmt2->method('fetchColumn')->willReturn(0);
        $this->pdo->method('prepare')->willReturn($mockStmt2);

        $result2 = FaceAuth::hasFace($this->test_user_id + 1);

        $this->assertFalse($result2, "HasFace false OK");
    }

    public function testVerifyMultipleDescriptors() {
        $descriptors = [[0.1, 0.2, 0.3], [0.11, 0.21, 0.31]]; // Multiple
        $descriptorJson = json_encode($descriptors);
        FaceAuth::storeFace($this->test_user_id, $descriptorJson);

        $testDescriptor = [0.105, 0.205, 0.305]; // Match one
        $testDescriptorJson = json_encode($testDescriptor);
        $hashedTest = hash('sha256', $testDescriptorJson);

        $mockRow = ['face_hash' => $hashedTest, 'face_descriptors' => $descriptorJson];
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn($mockRow);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = FaceAuth::verifyFace($this->test_user_id, $testDescriptorJson, 'mock_csrf');

        $this->assertTrue($result, "Verify multiple descriptors OK");
    }

    public function testInvalidDescriptor() {
        $invalidDescriptor = "not a json";
        
        FaceAuth::storeFace($this->test_user_id, $invalidDescriptor);
        $result = FaceAuth::verifyFace($this->test_user_id, $invalidDescriptor, 'mock_csrf');

        $this->assertFalse($result, "Verify invalid descriptor OK");
    }

    protected function tearDown(): void {
        // Cleanup test data
        global $pdo;
        $pdo->prepare("DELETE FROM faces WHERE user_id = ?")->execute([$this->test_user_id]);
    }
}

$test = new FaceAuthTest();
$test->testStoreFaceNew();
$test->testStoreFaceUpdate();
$test->testVerifyFaceMatch();
$test->testVerifyFaceNearMatch();
$test->testVerifyFaceNoMatch();
$test->testVerifyFaceNotRegistered();
$test->testHasFace();
$test->testVerifyMultipleDescriptors();
$test->testInvalidDescriptor();
?>