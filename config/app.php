<?php
// AES_KEY from random_bytes if not defined
if (!defined('AES_KEY')) {
    define('AES_KEY', bin2hex(random_bytes(32)));
}

define('SESSION_TIMEOUT', 3600);
define('RATE_LIMIT', 5);

$smtp = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => $_ENV['SMTP_USER'] ?? '',
    'password' => $_ENV['SMTP_PASS'] ?? ''
];
?>