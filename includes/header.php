<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = hash('sha256', random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' cdn.jsdelivr.net">
    <title><?php echo isset($title) ? $title : 'MFA E-Commerce Demo'; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>