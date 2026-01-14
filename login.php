<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$user_input = isset($input['username']) ? $input['username'] : '';
$pass = isset($input['password']) ? $input['password'] : '';

if (empty($user_input) || empty($pass)) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin'], JSON_UNESCAPED_UNICODE);
    exit;
}

// Sử dụng Prepared Statement để tránh SQL Injection
// Kiểm tra input khớp với username HOẶC phone
$stmt = $conn->prepare("SELECT id, username, pass, Position FROM users WHERE username = ? OR phone = ?");
if ($stmt) {
    $stmt->bind_param("ss", $user_input, $user_input);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // Kiểm tra mật khẩu (so sánh trực tiếp vì yêu cầu là chưa mã hóa)
        if ($pass === $row['pass']) {
            // Xác định quyền dựa trên username và/hoặc Position
            $role = 'khach-hang';

            // 1) Ưu tiên username nằm trong danh sách admin (tránh lỗi do Position bị lỗi font / sai encoding trên hosting)
            $adminUsernames = ['reslan']; // thêm username admin khác nếu cần
            $usernameLower = mb_strtolower($row['username'] ?? '', 'UTF-8');
            if (in_array($usernameLower, $adminUsernames, true)) {
                $role = 'quan-tri-vien';
            } else {
                // 2) Thử đọc Position nếu còn dùng được
                $rawPos = $row['Position'] ?? '';
                $posLower = mb_strtolower($rawPos, 'UTF-8');
                if ($posLower === 'quan-tri-vien' || $posLower === 'admin') {
                    $role = 'quan-tri-vien';
                }
            }

            // Lưu session với giá trị đã chuẩn hoá
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['position'] = $role;
            
            // Xác định trang điều hướng
            $redirect = 'index.html';
            if ($role === 'quan-tri-vien') {
                $redirect = 'quan-tri-vien-index.html';
            }

            echo json_encode([
                'status' => 'success', 
                'message' => 'Đăng nhập thành công!',
                'username' => $row['username'],
                'position' => $role,
                'redirect' => $redirect
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Mật khẩu không đúng'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tài khoản hoặc số điện thoại không tồn tại'], JSON_UNESCAPED_UNICODE);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi truy vấn'], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>