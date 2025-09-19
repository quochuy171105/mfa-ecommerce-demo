# MFA E-Commerce Demo

## Cài đặt

1. Cài **XAMPP** và khởi động Apache/MySQL.
2. Đặt thư mục dự án vào `htdocs` (ví dụ: `C:\xampp\htdocs\mfa-ecommerce-demo`).
3. Chạy lệnh `composer install` trong thư mục gốc của dự án để cài các thư viện phụ thuộc.

## Cấu hình

* **Cơ sở dữ liệu (Database):** Chạy file `sql/install_init.php` để tạo database. (chạy xong mở phpmyadmin lên là thấy đã có db)
* **SMTP (Email):** Chỉnh trong `config/app.php` để dùng Gmail SMTP (host: `smtp.gmail.com`, port: `587`, điền username/password từ biến môi trường).
* **Bảo mật:** Thay đổi giá trị `AES_KEY` trong `config/app.php` (tạo bằng lệnh `bin2hex(random_bytes(32))`).

## Chạy Demo

* Truy cập `http://localhost/mfa-ecommerce-demo` (sẽ tự động chuyển hướng sang HTTPS).
* Đăng ký tài khoản → Đăng nhập → Chọn phương thức xác thực đa yếu tố (OTP hoặc Nhận diện khuôn mặt) → Xác thực → Vào trang Dashboard.

## Lưu ý bảo mật

* **Thay đổi ngay AES_KEY** sau khi cài đặt.
* **Luôn dùng HTTPS** khi triển khai thực tế.
* Đặt quyền file `logs/security.log` thành **chmod 600** (chỉ cho phép user đọc/ghi).
* Tham khảo **OWASP Cheat Sheet** để củng cố bảo mật PHP.
* Xem chuẩn **RFC 6238** để hiểu thêm về TOTP (mã OTP theo thời gian).
