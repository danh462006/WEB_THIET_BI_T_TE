<?php
session_start();
header('Content-Type: application/json');

// Kiểm tra đăng nhập
if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Chưa đăng nhập']);
    exit;
}

// Nhận dữ liệu JSON
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

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

switch ($action) {
    case 'update_basic':
        // Cập nhật tên và địa chỉ
        $new_username = $data['username'] ?? '';
        $address = $data['address'] ?? '';
        
        if (empty($new_username)) {
            echo json_encode(['status' => 'error', 'message' => 'Tên không được để trống']);
            exit;
        }
        
        $stmt = $conn->prepare("UPDATE users SET Username = ?, Address = ? WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("ssss", $new_username, $address, $username, $username);
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $new_username;
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật']);
        }
        $stmt->close();
        break;

    case 'update_password':
        // Thay đổi mật khẩu với xác thực
        $old_password = $data['old_password'] ?? '';
        $new_password = $data['new_password'] ?? '';
        $verify_code = $data['verify_code'] ?? '';
        
        if (empty($old_password) || empty($new_password) || empty($verify_code)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
            exit;
        }
        
        // Kiểm tra mật khẩu cũ
        $stmt = $conn->prepare("SELECT Password FROM users WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy user']);
            exit;
        }
        
        $user = $result->fetch_assoc();
        if (!password_verify($old_password, $user['Password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Mật khẩu cũ không đúng']);
            exit;
        }
        $stmt->close();
        
        // Kiểm tra mã xác nhận
        if (!isset($_SESSION['verify_code']) || $_SESSION['verify_code'] !== $verify_code) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận không đúng']);
            exit;
        }
        
        // Kiểm tra thời hạn mã (5 phút)
        if (!isset($_SESSION['verify_code_time']) || time() - $_SESSION['verify_code_time'] > 300) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận đã hết hạn']);
            exit;
        }
        
        // Cập nhật mật khẩu mới
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET Password = ? WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("sss", $hashed_password, $username, $username);
        
        if ($stmt->execute()) {
            unset($_SESSION['verify_code']);
            unset($_SESSION['verify_code_time']);
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật mật khẩu thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật mật khẩu']);
        }
        $stmt->close();
        break;

    case 'update_phone':
        // Thay đổi số điện thoại với xác thực
        $new_phone = $data['phone'] ?? '';
        $verify_code = $data['verify_code'] ?? '';
        
        if (empty($new_phone) || empty($verify_code)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
            exit;
        }
        
        // Kiểm tra mã xác nhận
        if (!isset($_SESSION['verify_code']) || $_SESSION['verify_code'] !== $verify_code) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận không đúng']);
            exit;
        }
        
        if (!isset($_SESSION['verify_code_time']) || time() - $_SESSION['verify_code_time'] > 300) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận đã hết hạn']);
            exit;
        }
        
        // Kiểm tra SĐT đã tồn tại chưa
        $stmt = $conn->prepare("SELECT ID FROM users WHERE Phone = ?");
        $stmt->bind_param("s", $new_phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Số điện thoại đã được sử dụng']);
            exit;
        }
        $stmt->close();
        
        // Cập nhật SĐT
        $stmt = $conn->prepare("UPDATE users SET Phone = ? WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("sss", $new_phone, $username, $username);
        
        if ($stmt->execute()) {
            unset($_SESSION['verify_code']);
            unset($_SESSION['verify_code_time']);
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật SĐT thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật SĐT']);
        }
        $stmt->close();
        break;

    case 'update_email':
        // Thay đổi email với xác thực
        $new_email = $data['email'] ?? '';
        $verify_code = $data['verify_code'] ?? '';
        
        if (empty($new_email) || empty($verify_code)) {
            echo json_encode(['status' => 'error', 'message' => 'Thiếu thông tin']);
            exit;
        }
        
        // Kiểm tra mã xác nhận
        if (!isset($_SESSION['verify_code']) || $_SESSION['verify_code'] !== $verify_code) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận không đúng']);
            exit;
        }
        
        if (!isset($_SESSION['verify_code_time']) || time() - $_SESSION['verify_code_time'] > 300) {
            echo json_encode(['status' => 'error', 'message' => 'Mã xác nhận đã hết hạn']);
            exit;
        }
        
        // Kiểm tra email đã tồn tại chưa
        $stmt = $conn->prepare("SELECT ID FROM users WHERE Gmail = ?");
        $stmt->bind_param("s", $new_email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email đã được sử dụng']);
            exit;
        }
        $stmt->close();
        
        // Cập nhật email
        $stmt = $conn->prepare("UPDATE users SET Gmail = ? WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("sss", $new_email, $username, $username);
        
        if ($stmt->execute()) {
            unset($_SESSION['verify_code']);
            unset($_SESSION['verify_code_time']);
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật email thành công']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Không thể cập nhật email']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Action không hợp lệ']);
        break;
}

$conn->close();
?>
