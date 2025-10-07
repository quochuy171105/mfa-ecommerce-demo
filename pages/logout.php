<?php
require_once __DIR__ . '/../classes/Auth.php';

// Bắt đầu session để có thể xóa nó
start_secure_session();

// Gọi hàm logout để xóa toàn bộ session
Auth::logout();

// Chuyển hướng người dùng về trang đăng nhập
header('Location: login.php');
exit;
?>