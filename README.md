# MFA E-Commerce Demo

Hệ thống xác thực đa yếu tố (Multi-Factor Authentication) cho ứng dụng web, hỗ trợ xác thực qua OTP email và nhận diện khuôn mặt.

---

## 📋 Mục lục

* [Tổng quan](#t%E1%BB%95ng-quan)
* [Tính năng](#t%C3%ADnh-n%C4%83ng)
* [Yêu cầu hệ thống](#y%C3%AAu-c%E1%BA%A7u-h%E1%BB%87-th%E1%BB%91ng)
* [Cài đặt](#c%C3%A0i-%C4%91%E1%BA%B7t)
* [Cấu hình](#c%E1%BA%A5u-h%C3%ACnh)
* [Chạy ứng dụng](#ch%E1%BA%A1y-%E1%BB%A9ng-d%E1%BB%A5ng)
* [Cấu trúc thư mục](#c%E1%BA%A5u-tr%C3%BAc-th%C6%B0-m%E1%BB%A5c)
* [Công nghệ sử dụng](#c%C3%B4ng-ngh%E1%BB%87-s%E1%BB%AD-d%E1%BB%A5ng)
* [Bảo mật](#b%E1%BA%A3o-m%E1%BA%ADt)
* [Xử lý sự cố](#x%E1%BB%AD-l%C3%BD-s%E1%BB%B1-c%E1%BB%91)
* [Tài liệu tham khảo](#t%C3%A0i-li%E1%BB%87u-tham-kh%E1%BA%A3o)
* [Giấy phép](#gi%E1%BA%A5y-ph%C3%A9p)

---

## 🎯 Tổng quan

Dự án này triển khai cơ chế xác thực đa yếu tố (MFA) với hai phương thức:

1. **OTP qua Email** : Mã xác thực 6 số được mã hóa AES-256-CBC, gửi qua SMTP
2. **Nhận diện khuôn mặt** : Sử dụng Face-api.js, so sánh descriptor bằng Euclidean distance

Hệ thống được xây dựng trên kiến trúc 3 tầng (Frontend, Backend, Database) với các lớp bảo mật: Bcrypt, JWT, CSRF Protection, Rate Limiting.

---

## ✨ Tính năng

* ✅ Đăng ký/Đăng nhập với mật khẩu được hash bằng Bcrypt (cost 12)
* ✅ Quản lý phiên làm việc bằng JWT (HMAC-SHA256, timeout 3600s)
* ✅ Xác thực OTP 6 số qua email (expiry 5 phút, mã hóa AES-256-CBC)
* ✅ Xác thực khuôn mặt với liveness detection (head movement)
* ✅ Rate limiting: 5 attempts/60 giây cho các endpoint nhạy cảm
* ✅ CSRF protection trên tất cả POST requests
* ✅ Ghi log các sự kiện bảo mật vào `logs/security.log`
* ✅ Responsive design với Neumorphic UI

---

## 💻 Yêu cầu hệ thống

### Phần mềm

* **XAMPP 8.0+** (Apache 2.4, MySQL 8.0, PHP 7.4+)
* **Composer** (PHP dependency manager)
* **Git** (khuyến nghị)

### Trình duyệt

* Chrome 90+, Firefox 88+, Edge 90+ (hỗ trợ WebRTC cho webcam)

### Phần cứng

* Webcam (cho tính năng nhận diện khuôn mặt)
* RAM tối thiểu: 4GB
* Dung lượng ổ cứng: ~500MB (bao gồm dependencies)

---

## 🚀 Cài đặt

### Bước 1: Clone repository

bash

```bash
git clone https://github.com/your-username/mfa-ecommerce-demo.git
cd mfa-ecommerce-demo
```

Hoặc download ZIP và giải nén vào thư mục `C:\xampp\htdocs\mfa-ecommerce-demo`

### Bước 2: Cài đặt XAMPP

1. Tải XAMPP từ [https://www.apachefriends.org](https://www.apachefriends.org)
2. Cài đặt với các component:  **Apache** ,  **MySQL** ,  **PHP** , **phpMyAdmin**
3. Khởi động **Apache** và **MySQL** từ XAMPP Control Panel

### Bước 3: Cài đặt Composer

1. Tải Composer từ [https://getcomposer.org](https://getcomposer.org)
2. Cài đặt global để sử dụng command `composer`

### Bước 4: Cài đặt dependencies

Mở terminal/command prompt tại thư mục dự án:

bash

```bash
cd C:\xampp\htdocs\mfa-ecommerce-demo
composerinstall
```

Composer sẽ tải các thư viện:

- PHPMailer 6.11
- PHPUnit 10.0
- PSR Log 3.0

### Bước 5: Tạo cơ sở dữ liệu

**Phương án 1: Qua trình duyệt**

```
http://localhost/mfa-ecommerce-demo/sql/install_init.php
```

**Phương án 2: Qua terminal**

bash

```bash
php sql/install_init.php
```

Script sẽ:

* Tạo database `mfa_demo`
* Import 3 bảng: `users`, `otps`, `faces`
* Thêm indexes cho optimization

Kiểm tra: Truy cập [http://localhost/phpmyadmin](http://localhost/phpmyadmin), database `mfa_demo` xuất hiện với 3 bảng.

### Bước 6: Set permissions (Linux/macOS)

bash

```bash
chmod600 logs/security.log
chmod755 sql/install_init.php
```

Windows: Right-click → Properties → Security → Edit permissions

---

## ⚙️ Cấu hình

### 1. Cấu hình Database

Mở `config/database.php`, kiểm tra credentials:

php

```php
$dbHost='localhost';
$dbName='mfa_demo';
$dbUser='root';
$dbPass='';// Mặc định XAMPP không có password
```

### 2. Cấu hình SMTP (Gmail)

#### Tạo App Password cho Gmail:

1. Đăng nhập Gmail
2. Truy cập [https://myaccount.google.com/security](https://myaccount.google.com/security)
3. Bật **2-Step Verification**
4. Truy cập [https://myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)
5. Chọn "Mail" và "Other (Custom name)" → Nhập "MFA Demo"
6. Copy mã 16 ký tự (ví dụ: `abcd efgh ijkl mnop`)

#### Cập nhật config/app.php:

php

```php
$smtp=[
'host'=>'smtp.gmail.com',
'port'=>587,
'username'=>'your-email@gmail.com',// Thay đổi
'password'=>'abcdefghijklmnop',// App Password (xóa dấu cách)
'from_email'=>'your-email@gmail.com',
];
```

### 3. Tạo AES Key mới

**Bước 1:** Tạo key ngẫu nhiên

bash

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

**Bước 2:** Copy output và paste vào `config/app.php`:

php

```php
define('AES_KEY','your-new-64-character-hex-string-here');
```

### 4. Cấu hình JWT Secret (Production)

php

```php
define('JWT_SECRET','your-strong-secret-key-256-bit');
```

⚠️ **Quan trọng**: KHÔNG commit file `config/app.php` lên Git nếu chứa credentials thật!

### 5. Download Face-api.js models

Models đã có sẵn trong `assets/js/weights/`. Nếu thiếu, download từ:
[https://github.com/justadudewhohacks/face-api.js-models](https://github.com/justadudewhohacks/face-api.js-models)

Files cần thiết:

```
assets/js/weights/
├── ssd_mobilenetv1_model-shard1
├── ssd_mobilenetv1_model-shard2
├── ssd_mobilenetv1_model-weights_manifest.json
├── face_landmark_68_model-shard1
├── face_landmark_68_model-weights_manifest.json
├── face_recognition_model-shard1
├── face_recognition_model-shard2
└── face_recognition_model-weights_manifest.json
```

---

## 🎮 Chạy ứng dụng

### 1. Khởi động XAMPP

- Mở XAMPP Control Panel
- Start **Apache** và **MySQL**

### 2. Truy cập ứng dụng

Mở trình duyệt, truy cập:

```
http://localhost/mfa-ecommerce-demo
```

### 3. Quy trình sử dụng

#### Đăng ký tài khoản

1. Click "Đăng ký ngay"
2. Nhập email và mật khẩu (≥8 ký tự, có chữ hoa/thường/số)
3. Xác nhận mật khẩu
4. Click "Đăng Ký"

#### Đăng nhập

1. Nhập email và mật khẩu
2. Click "Đăng Nhập"
3. Chuyển sang trang chọn phương thức MFA

#### Xác thực MFA - OTP

1. Chọn "Mã OTP qua Email"
2. Click "Tiếp Tục"
3. Kiểm tra email (inbox hoặc spam)
4. Nhập 6 số OTP (có hiệu lực 5 phút)
5. Click "Xác Nhận"

#### Xác thực MFA - Khuôn mặt

1. Chọn "Nhận diện khuôn mặt"
2. Click "Tiếp Tục"
3. **Cho phép trình duyệt truy cập webcam**
4. **Lần đầu**: Click "Đăng Ký Khuôn Mặt"
   - Nhìn thẳng camera
   - Quay mặt trái nhẹ
   - Quay mặt phải nhẹ
   - Hệ thống capture 3 descriptors
5. **Sau khi đăng ký**: Click "Quét Gương Mặt"
   - Thực hiện liveness detection (quay đầu trái/phải)
   - Hệ thống so sánh và xác thực

#### Hoàn tất

- Hiển thị trang "Đăng Nhập Thành Công"
- Xem thông tin: Email, User ID, thời gian đăng nhập
- Click "Đăng Xuất" để kết thúc phiên

---

## 📁 Cấu trúc thư mục

```
mfa-ecommerce-demo/
├── assets/
│   ├── css/
│   │   └── auth.css              # Neumorphic UI styling
│   └── js/
│       ├── face-api.min.js       # Face-api.js library
│       ├── face.js               # Face detection/recognition logic
│       ├── otp.js                # OTP timer & resend
│       └── weights/# ML models (SSD, Landmark, Recognition)
├── classes/
│   ├── Auth.php                  # Authentication & JWT
│   ├── FaceAuth.php              # Face verification (Euclidean distance)
│   ├── OTP.php                   # OTP generation/verification
│   └── User.php                  # User management
├── config/
│   ├── app.php                   # AES_KEY, JWT_SECRET, SMTP config
│   └── database.php              # PDO connection
├── emails/
│   └── otp_template.html         # Email template
├── includes/
│   ├── footer.php                # HTML footer
│   ├── functions.php             # Utilities (CSRF, rate limit, sanitize)
│   ├── header.php                # HTML header (CSP, nonce)
│   ├── security.php              # Bcrypt, AES, JWT functions
│   └── validation.php            # Input validation
├── logs/
│   └── security.log              # Security events log
├── pages/
│   ├── face.php                  # Face authentication UI
│   ├── login.php                 # Login form
│   ├── logout.php                # Logout handler
│   ├── mfa.php                   # MFA method selection
│   ├── otp.php                   # OTP input form
│   ├── register.php              # Registration form
│   ├── success.php               # Dashboard after authentication
│   └── verify.php                # OTP/Face verification endpoint
├── sql/
│   ├── init.sql                  # Database schema
│   └── install_init.php          # Auto DB setup script
├── vendor/# Composer dependencies
├── .gitignore                    # Git ignore rules
├── .htaccess                     # Apache config (security headers, rewrite)
├── composer.json                 # PHP dependencies
├── index.php                     # Entry point
└── README.md                     # This file
```

---

## 🛠️ Công nghệ sử dụng

### Backend

<pre class="font-ui border-border-100/50 overflow-x-scroll w-full rounded border-[0.5px] shadow-[0_2px_12px_hsl(var(--always-black)/5%)]"><table class="bg-bg-100 min-w-full border-separate border-spacing-0 text-sm leading-[1.88888] whitespace-normal"><thead class="border-b-border-100/50 border-b-[0.5px] text-left"><tr class="[tbody>&]:odd:bg-bg-500/10"><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Công nghệ</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Phiên bản</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Vai trò</th></tr></thead><tbody><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">PHP</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">7.4+</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Backend language</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">MySQL</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">8.0</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Database</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Apache</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">2.4</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Web server</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Composer</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Latest</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Dependency manager</td></tr></tbody></table></pre>

### Frontend

<pre class="font-ui border-border-100/50 overflow-x-scroll w-full rounded border-[0.5px] shadow-[0_2px_12px_hsl(var(--always-black)/5%)]"><table class="bg-bg-100 min-w-full border-separate border-spacing-0 text-sm leading-[1.88888] whitespace-normal"><thead class="border-b-border-100/50 border-b-[0.5px] text-left"><tr class="[tbody>&]:odd:bg-bg-500/10"><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Công nghệ</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Phiên bản</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Vai trò</th></tr></thead><tbody><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">HTML5/CSS3</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">-</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">UI structure & styling</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">JavaScript</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">ES6+</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Client-side logic</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Face-api.js</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">0.22</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Face recognition</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">TensorFlow.js</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">-</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">ML backend for Face-api</td></tr></tbody></table></pre>

### Thư viện PHP

<pre class="font-ui border-border-100/50 overflow-x-scroll w-full rounded border-[0.5px] shadow-[0_2px_12px_hsl(var(--always-black)/5%)]"><table class="bg-bg-100 min-w-full border-separate border-spacing-0 text-sm leading-[1.88888] whitespace-normal"><thead class="border-b-border-100/50 border-b-[0.5px] text-left"><tr class="[tbody>&]:odd:bg-bg-500/10"><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Thư viện</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Phiên bản</th><th class="text-text-000 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] px-2 [&:not(:first-child)]:border-l-[0.5px]">Vai trò</th></tr></thead><tbody><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">PHPMailer</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">6.11</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">SMTP email</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">PHPUnit</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">10.0</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Unit testing</td></tr><tr class="[tbody>&]:odd:bg-bg-500/10"><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">PSR Log</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">3.0</td><td class="border-t-border-100/50 [&:not(:first-child)]:-x-[hsla(var(--border-100) / 0.5)] border-t-[0.5px] px-2 [&:not(:first-child)]:border-l-[0.5px]">Logging interface</td></tr></tbody></table></pre>

### Bảo mật

* **Bcrypt** (cost 12): Password hashing
* **AES-256-CBC** : OTP encryption
* **JWT HMAC-SHA256** : Session management
* **CSRF Token** (64-char hex): CSRF protection
* **Rate Limiting** : 5 attempts/60s
* **CSP Level 2** : XSS protection
* **Euclidean Distance** (threshold 0.4): Face matching

---

## 🔒 Bảo mật

### Biện pháp đã triển khai

✅ **Password Security**

* Bcrypt hash với cost factor 12 (4,096 iterations)
* Không lưu plain password
* Password strength validation (≥8 chars, mixed case, digits)

✅ **OTP Security**

* AES-256-CBC encryption với random IV
* Expiry time: 5 phút
* Nonce chống replay attacks
* Rate limiting cho resend (5/60s)

✅ **Face Recognition Security**

* Không lưu hình ảnh khuôn mặt (chỉ descriptor 128-dim)
* Liveness detection (head movement ≥30px)
* Euclidean distance threshold 0.4 (nghiêm ngặt)
* Multiple descriptors (3 góc) giảm false negative

✅ **Session Security**

* JWT signed với HMAC-SHA256
* Session timeout: 3600 giây (1 giờ)
* HttpOnly cookie (JS không access)
* Secure cookie (HTTPS only - production)

✅ **Web Security**

* CSRF token trên tất cả POST requests
* Rate limiting: 5 attempts/60s per IP
* Content Security Policy (CSP) headers
* X-Frame-Options: DENY (chống clickjacking)
* X-Content-Type-Options: nosniff

✅ **Database Security**

* PDO Prepared Statements (chống SQL injection)
* Foreign Key constraints
* Indexed queries (optimization)

### Khuyến nghị Production

⚠️ **BẮT BUỘC khi deploy lên production:**

1. **HTTPS/SSL**

apache

```apache
   # Uncomment trong .htaccess:
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

* Cài Let's Encrypt hoặc Cloudflare SSL
* Set `session.cookie_secure = 1` trong `includes/functions.php`

2. **Environment Variables**
   * Sử dụng `.env` file (thư viện `vlucas/phpdotenv`)
   * Không commit `config/app.php` lên Git
   * Rotate JWT_SECRET định kỳ
3. **Database**
   * Tạo MySQL user riêng với quyền hạn chế
   * Enable MySQL SSL connection
   * Backup database định kỳ
4. **Logs & Monitoring**
   * Tích hợp ELK Stack hoặc Graylog
   * Setup email alerts cho suspicious activities
   * Log rotation (logrotate)
5. **Error Handling**
   * Set `display_errors = Off` trong php.ini
   * Chỉ log errors vào file, không hiển thị cho user
6. **File Permissions**

bash

```bash
chmod600 logs/security.log
chmod600 config/app.php
chmod755 pages/*.php
```

---

## 🐛 Xử lý sự cố

### Lỗi "Database connection failed"

**Nguyên nhân:** MySQL chưa khởi động hoặc credentials sai

**Giải pháp:**

1. Kiểm tra MySQL đang chạy trong XAMPP Control Panel
2. Xác nhận credentials trong `config/database.php`
3. Kiểm tra database `mfa_demo` đã tồn tại:

sql

```sql
SHOWDATABASES;
```

### Lỗi "Composer command not found"

**Nguyên nhân:** Composer chưa được cài đặt hoặc chưa add vào PATH

**Giải pháp:**

1. Download Composer từ [getcomposer.org](https://getcomposer.org)
2. Cài đặt global
3. Restart terminal/command prompt

### Lỗi "Cannot send email"

**Nguyên nhân:** SMTP credentials sai hoặc Gmail chặn

**Giải pháp:**

1. Kiểm tra App Password (16 ký tự, không có dấu cách)
2. Xác nhận 2-Step Verification đã bật
3. Check Gmail "Less secure app access" (nếu không dùng App Password)
4. Kiểm tra firewall không block port 587

### Lỗi "Webcam not found" hoặc "Permission denied"

**Nguyên nhân:** Browser không được phép access webcam

**Giải pháp:**

1. Click biểu tượng 🔒 trên address bar
2. Allow "Camera" permission
3. Reload trang
4. Chrome: `chrome://settings/content/camera`
5. Firefox: `about:preferences#privacy`

### Lỗi "Face-api.js models not loading"

**Nguyên nhân:** Model files thiếu hoặc đường dẫn sai

**Giải pháp:**

1. Kiểm tra thư mục `assets/js/weights/` có đủ 8 files
2. Check browser console (F12) xem lỗi cụ thể
3. Re-download models từ GitHub nếu cần

### Lỗi "CSRF token mismatch"

**Nguyên nhân:** Session timeout hoặc multiple tabs

**Giải pháp:**

1. Refresh trang (F5)
2. Clear browser cache
3. Logout và login lại

### Lỗi "Rate limit exceeded"

**Nguyên nhân:** Quá 5 attempts trong 60 giây

**Giải pháp:**

1. Đợi 1 phút
2. Thử lại
3. Check `logs/security.log` để verify

---

## 📚 Tài liệu tham khảo

### Security Standards

* [OWASP Top 10](https://owasp.org/www-project-top-ten/)
* [OWASP PHP Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html)
* [NIST Digital Identity Guidelines](https://pages.nist.gov/800-63-3/)

### Cryptography

* [RFC 6238 - TOTP](https://tools.ietf.org/html/rfc6238)
* [RFC 7519 - JWT](https://tools.ietf.org/html/rfc7519)
* [AES-256 Specification](https://csrc.nist.gov/publications/detail/fips/197/final)

### Face Recognition

* [Face-api.js Documentation](https://github.com/justadudewhohacks/face-api.js)
* [TensorFlow.js Guide](https://www.tensorflow.org/js/guide)
* [Euclidean Distance Explanation](https://en.wikipedia.org/wiki/Euclidean_distance)

### PHP Best Practices

* [PHP The Right Way](https://phptherightway.com/)
* [Composer Documentation](https://getcomposer.org/doc/)
* [PDO Tutorial](https://phpdelusions.net/pdo)

---

## 📄 Giấy phép

Dự án này được phát triển cho mục đích học tập và nghiên cứu.

**Lưu ý:**

* Không sử dụng cho mục đích thương mại mà chưa có sự cho phép
* Tác giả không chịu trách nhiệm về các vấn đề bảo mật phát sinh khi triển khai production
* Khuyến nghị thực hiện security audit trước khi deploy

---

## 👨‍💻 Tác giả

**[Nguyễn Quốc Huy, Nguyễn Ngọc Thảo Nguyên, Nguyễn Thị Tuyết Nhung, Lê Nguyên Mai Quỳnh, Trần Thị Mỹ Hòa]**

* GitHub: [github.com/quochuy171105](https://github.com/quochuy171105/mfa-ecommerce-demo)

---

## 🙏 Lời cảm ơn

* [Face-api.js](https://github.com/justadudewhohacks/face-api.js) by Vincent Mühler
* [PHPMailer](https://github.com/PHPMailer/PHPMailer)
* [OWASP Foundation](https://owasp.org/)
* [PHP Community](https://www.php.net/community)

---

## 📝 Changelog

### Version 1.0.0 (2025-01-XX)

* ✨ Initial release
* ✅ Bcrypt password hashing
* ✅ AES-256-CBC OTP encryption
* ✅ JWT session management
* ✅ Face recognition với liveness detection
* ✅ CSRF protection
* ✅ Rate limiting

---

**⭐ Nếu dự án này hữu ích, hãy cho một star trên GitHub!**
