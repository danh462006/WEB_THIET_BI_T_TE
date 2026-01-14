<?php
// Session configuration - MUST be called BEFORE session_start()
require_once 'session-config.php';

// Database configuration
$servername = "localhost";
$username = "reslan";
$password = "nguyendanh0399352950";
$dbname = "ducphuong";
$port = 3306;

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection

$conn = @new mysqli($servername, $username, $password, $dbname, $port);
// Đảm bảo kết nối dùng utf8mb4 để không lỗi tiếng Việt
$conn->set_charset("utf8mb4");

if ($conn->connect_errno) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Kết nối database thất bại']);
    exit;
}

// Suppress errors
error_reporting(0);
ini_set('display_errors', 0);
mysqli_report(MYSQLI_REPORT_OFF);

// Also expose a PDO connection for modules expecting PDO
if (!isset($pdo) || !$pdo) {
    try {
        $dsn = "mysql:host={$servername};port={$port};dbname={$dbname};charset=utf8mb4";
        $pdoOptions = array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true
        );
        $pdo = new PDO($dsn, $username, $password, $pdoOptions);
    } catch (Exception $e) {
        $pdo = null; // Fallback gracefully if PDO unavailable
    }
}
?>
