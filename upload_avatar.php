<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

// Kiểm tra file upload
if (!isset($_FILES['avatar'])) {
    echo json_encode(['status' => 'error', 'message' => 'Không có file được upload']);
    exit;
}

$file = $_FILES['avatar'];

// Validate file
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'Chỉ chấp nhận file ảnh (JPG, PNG, GIF, WEBP)']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'File không được vượt quá 5MB']);
    exit;
}

// Tạo tên file unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'avatar_' . $_SESSION['username'] . '_' . time() . '.' . $extension;
$upload_dir = 'hinh-anh/avatars/';

// Tạo thư mục nếu chưa tồn tại
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$upload_path = $upload_dir . $filename;

// Upload file
if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    // Cập nhật database
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
    $stmt = $conn->prepare("UPDATE users SET Avatar = ? WHERE Username = ? OR Phone = ?");
    $stmt->bind_param("sss", $upload_path, $username, $username);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Upload avatar thành công',
            'avatar_path' => $upload_path
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật database']);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Không thể upload file']);
}
?>
