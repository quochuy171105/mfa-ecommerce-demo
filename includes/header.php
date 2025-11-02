<?php
$nonce = bin2hex(random_bytes(16)); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' cdn.jsdelivr.net; script-src 'self' 'nonce-<?php echo $nonce; ?>' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline';">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf']; ?>">
    <title><?php echo isset($title) ? $title : 'MFA E-Commerce Demo'; ?></title>
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>