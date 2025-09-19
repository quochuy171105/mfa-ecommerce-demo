<?php
/**
 * install_init.php
 * Script PHP để reset database và import lại dữ liệu từ init.sql
 */

$host = "127.0.0.1";   // hoặc "localhost"
$user = "root";        // user mặc định của XAMPP
$pass = "";            // nếu bạn chưa đặt mật khẩu cho root thì để trống
$dbname = "mfa_demo";
$sqlFile = __DIR__ . "/init.sql";

try {
    // Kết nối MySQL (chưa chọn DB)
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true
    ]);

    // Tạo database nếu chưa có
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
    echo "✅ Database '$dbname' đã sẵn sàng.\n";

    // Chọn database
    $pdo->exec("USE `$dbname`;");

    // Xóa toàn bộ bảng trong DB
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`;");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "🗑️  Tất cả bảng trong '$dbname' đã được xóa.\n";

    // Import lại dữ liệu từ init.sql
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        $pdo->exec($sql);
        echo "📥 Import dữ liệu từ init.sql thành công!\n";
    } else {
        echo "⚠️ Không tìm thấy file init.sql\n";
    }

} catch (PDOException $e) {
    echo "❌ Lỗi: " . $e->getMessage() . "\n";
    exit(1);
}
