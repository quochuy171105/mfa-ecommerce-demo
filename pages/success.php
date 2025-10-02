<?php
// Dashboard: Hiển thị "Thành công", user info (masked), logout button.
session_start();
require_once __DIR__ . '/../classes/Auth.php';

if (!Auth::isAuthenticated() || !isset($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Success</title>
</head>
<body>
    <h1>Xác thực thành công</h1>
    <button onclick="window.location.href='../pages/login.php?logout=true'">Logout</button>
</body>
</html>
