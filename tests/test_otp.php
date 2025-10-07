<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../classes/OTP.php';

use PHPUnit\Framework\TestCase;

class OTPTest extends TestCase {
    protected $pdo;

    protected function setUp(): void {
        $this->pdo = $this->createMock(PDO::class);
        $this->pdo->method('prepare')->willReturn($this->createMock(PDOStatement::class));
    }

    public function testGenerateAndStore() {
        $user_id = 1;
        $result = OTP::generateAndStore($user_id);
        $this->assertTrue($result['success']);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['otp']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $result['nonce']);
    }

    public function testVerify() {
        // Mock row
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('fetch')->willReturn([
            'encrypted_otp' => encrypt_aes('123456', AES_KEY),
            'expiry' => date('Y-m-d H:i:s', time() + 60),
            'nonce' => 'test_nonce'
        ]);
        $this->pdo->method('prepare')->willReturn($mockStmt);

        $result = OTP::verify(1, '123456', 'test_nonce');
        $this->assertTrue($result);
    }
}
?>