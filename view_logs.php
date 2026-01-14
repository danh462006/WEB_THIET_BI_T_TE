<?php
/**
 * Xem PHP error log trong trình duyệt
 * Truy cập: http://localhost/ducphuongmedical/view_logs.php
 */

// Tìm file log
$logFiles = [
    'C:\xampp\apache\logs\error.log',
    'C:\xampp\php\logs\php_error_log',
    __DIR__ . '/error_log',
    ini_get('error_log')
];

echo "<h2>PHP Error Logs</h2>";
echo "<p>Hiển thị 100 dòng cuối cùng của mỗi file log</p>";

foreach ($logFiles as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        echo "<h3>File: " . htmlspecialchars($logFile) . "</h3>";
        echo "<pre style='background:#f5f5f5; padding:10px; overflow:auto; max-height:500px; border:1px solid #ddd;'>";
        
        // Đọc 100 dòng cuối
        $lines = file($logFile);
        $last100 = array_slice($lines, -100);
        echo htmlspecialchars(implode('', $last100));
        
        echo "</pre><hr>";
    }
}

echo "<p><a href='view_logs.php'>Refresh</a></p>";
?>
