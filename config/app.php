<?php
// Load .env file
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0 || empty($line)) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

// Security settings
define('AES_KEY', $_ENV['AES_KEY'] ?? '');
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? '');
define('SESSION_TIMEOUT', (int)($_ENV['SESSION_TIMEOUT'] ?? 3600));
define('RATE_LIMIT', (int)($_ENV['RATE_LIMIT'] ?? 5));

// SMTP settings
$smtp = [
    'host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
    'port' => (int)($_ENV['SMTP_PORT'] ?? 587),
    'username' => $_ENV['SMTP_USER'] ?? '',
    'password' => $_ENV['SMTP_PASS'] ?? ''
];