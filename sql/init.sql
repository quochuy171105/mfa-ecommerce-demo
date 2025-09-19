-- Script tạo DB/table: users (id, email, pass_hash), otps (user_id, encrypted_otp, expiry, nonce), faces (user_id, face_hash).

-- Tạo bảng users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    pass_hash VARCHAR(255) NOT NULL
);

-- Tạo bảng otps
CREATE TABLE IF NOT EXISTS otps (
    user_id INT NOT NULL,
    encrypted_otp TEXT NOT NULL,
    expiry DATETIME NOT NULL,
    nonce VARCHAR(32) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tạo bảng faces
CREATE TABLE IF NOT EXISTS faces (
    user_id INT PRIMARY KEY,
    face_hash VARCHAR(64) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Thêm index cho performance
ALTER TABLE users ADD INDEX idx_email (email);
ALTER TABLE otps ADD INDEX idx_user_id (user_id);
ALTER TABLE faces ADD INDEX idx_user_id (user_id);