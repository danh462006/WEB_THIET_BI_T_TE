<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

// Kết nối database
$servername = "localhost";
$db_username = "reslan";
$db_password = "nguyendanh0399352950";
$dbname = "ducphuong";

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi kết nối database']);
    exit;
}

$username = $_SESSION['username'];

// Lấy thông tin user
$stmt = $conn->prepare("SELECT username, phone, gmail, Avatar, Address, pass FROM users WHERE username = ? OR phone = ?");
$stmt->bind_param("ss", $username, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    // Hiển thị 8 ký tự đầu của mật khẩu để user biết có mật khẩu
    $password_display = substr($user['pass'], 0, 8) . '...';
    
    echo json_encode([
        'status' => 'success',
        'username' => $user['username'],
        'phone' => $user['phone'],
        'email' => $user['gmail'],
        'avatar' => $user['Avatar'] ? $user['Avatar'] : 'hinh-anh/hinh-tk-macdinh.png',
        'address' => $user['Address'],
        'password' => $password_display
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy thông tin user']);
}

$stmt->close();
$conn->close();
?>
