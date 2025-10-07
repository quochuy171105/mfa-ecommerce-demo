<?php
// PHPUnit test security: Test encrypt_aes, gen_nonce, bcrypt cost.

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/security.php';

class SecurityTest extends TestCase {
    
    /**
     * Test AES encryption and decryption
     */
    public function testEncryptDecryptAes() {
        $plaintext = 'This is a secret OTP: 123456';
        
        // Encrypt
        $encrypted = encrypt_aes($plaintext, AES_KEY);
        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($plaintext, $encrypted);
        
        // Decrypt
        $decrypted = decrypt_aes($encrypted, AES_KEY);
        $this->assertEquals($plaintext, $decrypted);
    }
    
    /**
     * Test AES với different keys
     */
    public function testAesWithDifferentKeys() {
        $plaintext = 'sensitive data 789';
        $key1 = bin2hex(random_bytes(32));
        $key2 = bin2hex(random_bytes(32));
        
        $encrypted_key1 = encrypt_aes($plaintext, $key1);
        $encrypted_key2 = encrypt_aes($plaintext, $key2);
        
        // Different keys → different ciphertext
        $this->assertNotEquals($encrypted_key1, $encrypted_key2);
        
        // Decrypt with correct key
        $this->assertEquals($plaintext, decrypt_aes($encrypted_key1, $key1));
        $this->assertEquals($plaintext, decrypt_aes($encrypted_key2, $key2));
    }
    
    /**
     * Test gen_nonce length
     */
    public function testGenNonceLength() {
        $nonce = gen_nonce(16);
        $this->assertEquals(16, strlen($nonce));
        
        $nonce32 = gen_nonce(32);
        $this->assertEquals(32, strlen($nonce32));
        
        // Convert to hex and check
        $nonce_hex = bin2hex($nonce);
        $this->assertEquals(32, strlen($nonce_hex)); // 16 bytes = 32 hex chars
    }
    
    /**
     * Test nonce randomness
     */
    public function testNonceRandomness() {
        $nonces = [];
        for ($i = 0; $i < 100; $i++) {
            $nonce = bin2hex(gen_nonce(16));
            $this->assertNotContains($nonce, $nonces, 'Nonce must be unique');
            $nonces[] = $nonce;
        }
    }
    
    /**
     * Test password hashing with bcrypt
     */
    public function testPasswordHashBcrypt() {
        $password = 'Test1234!@#';
        
        $hash = hash_password($password);
        
        // Check hash format (bcrypt starts with $2y$)
        $this->assertStringStartsWith('$2y$', $hash);
        
        // Check hash length (bcrypt is 60 chars)
        $this->assertEquals(60, strlen($hash));
        
        // Verify password
        $this->assertTrue(verify_password($password, $hash));
        
        // Wrong password
        $this->assertFalse(verify_password('WrongPass123', $hash));
    }
    
    /**
     * Test bcrypt cost factor
     */
    public function testBcryptCost() {
        $password = 'TestCost123';
        $hash = hash_password($password);
        
        // Extract cost from hash (format: $2y$12$...)
        preg_match('/^\$2y\$(\d+)\$/', $hash, $matches);
        $cost = (int)$matches[1];
        
        // Assert cost = 12 theo yêu cầu
        $this->assertEquals(12, $cost, 'Bcrypt cost must be 12');
    }
    
    /**
     * Test gen_otp format
     */
    public function testGenOtpFormat() {
        for ($i = 0; $i < 50; $i++) {
            $otp = gen_otp();
            
            // Must be 6 digits
            $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
            
            // Must be numeric
            $this->assertIsNumeric($otp);
            
            // Range check
            $this->assertGreaterThanOrEqual(0, (int)$otp);
            $this->assertLessThanOrEqual(999999, (int)$otp);
        }
    }
    
    /**
     * Test AES IV randomness
     */
    public function testAesIvRandomness() {
        $plaintext = 'same text';
        
        // Encrypt same text multiple times
        $encrypted1 = encrypt_aes($plaintext, AES_KEY);
        $encrypted2 = encrypt_aes($plaintext, AES_KEY);
        
        // Different IV → different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2, 'IV must be random');
        
        // Both should decrypt correctly
        $this->assertEquals($plaintext, decrypt_aes($encrypted1, AES_KEY));
        $this->assertEquals($plaintext, decrypt_aes($encrypted2, AES_KEY));
    }
    
    /**
     * Test decrypt với invalid data
     */
    public function testDecryptInvalidData() {
        $this->expectException(Exception::class);
        decrypt_aes('invalid_base64_data', AES_KEY);
    }
    
    /**
     * Test decrypt với data quá ngắn
     */
    public function testDecryptShortData() {
        $this->expectException(Exception::class);
        $short_data = base64_encode('short'); // < 16 bytes
        decrypt_aes($short_data, AES_KEY);
    }
    
    /**
     * Test password hashing is non-deterministic
     */
    public function testPasswordHashNonDeterministic() {
        $password = 'SamePassword123';
        
        $hash1 = hash_password($password);
        $hash2 = hash_password($password);
        
        // Same password → different hashes (bcrypt uses salt)
        $this->assertNotEquals($hash1, $hash2);
        
        // Both hashes verify correctly
        $this->assertTrue(verify_password($password, $hash1));
        $this->assertTrue(verify_password($password, $hash2));
    }
    
    /**
     * Test timing attack protection với hash_equals
     */
    public function testHashEqualsTimingProtection() {
        $string1 = 'secret123456';
        $string2 = 'secret123456';
        $string3 = 'public654321';
        
        // Same strings
        $this->assertTrue(hash_equals($string1, $string2));
        
        // Different strings
        $this->assertFalse(hash_equals($string1, $string3));
        
        // Empty strings
        $this->assertTrue(hash_equals('', ''));
    }
    
    /**
     * Test AES_KEY từ config
     */
    public function testAesKeyConfiguration() {
        $this->assertTrue(defined('AES_KEY'));
        $this->assertIsString(AES_KEY);
        $this->assertEquals(64, strlen(AES_KEY)); // 32 bytes hex
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', AES_KEY);
    }
}
?>