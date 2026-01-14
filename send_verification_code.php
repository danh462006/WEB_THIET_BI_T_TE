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
$type = $data['type'] ?? '';

// Kết nối database để lấy email user
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

// Tạo mã xác nhận 6 số
$verify_code = sprintf('%06d', mt_rand(0, 999999));

// Lưu mã vào session
$_SESSION['verify_code'] = $verify_code;
$_SESSION['verify_code_time'] = time();

// Xác định email/SĐT nhận mã
$recipient = '';
$subject = '';
$message_body = '';

switch ($type) {
    case 'password':
        // Lấy email user
        $stmt = $conn->prepare("SELECT Gmail FROM users WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $recipient = $user['Gmail'];
            $subject = 'Mã xác nhận thay đổi mật khẩu';
            $message_body = "Mã xác nhận của bạn là: <strong>$verify_code</strong><br>Mã có hiệu lực trong 5 phút.";
        }
        $stmt->close();
        break;

    case 'phone':
        // Gửi mã đến SĐT mới (tạm thời gửi email vì chưa có SMS service)
        $stmt = $conn->prepare("SELECT Gmail FROM users WHERE Username = ? OR Phone = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $recipient = $user['Gmail'];
            $subject = 'Mã xác nhận thay đổi số điện thoại';
            $message_body = "Mã xác nhận của bạn là: <strong>$verify_code</strong><br>Mã có hiệu lực trong 5 phút.";
        }
        $stmt->close();
        break;

    case 'email':
        // Gửi mã đến email mới
        $new_email = $data['email'] ?? '';
        if (!empty($new_email)) {
            $recipient = $new_email;
            $subject = 'Mã xác nhận thay đổi email';
            $message_body = "Mã xác nhận của bạn là: <strong>$verify_code</strong><br>Mã có hiệu lực trong 5 phút.";
        }
        break;

    default:
        echo json_encode(['status' => 'error', 'message' => 'Type không hợp lệ']);
        exit;
}

$conn->close();

if (empty($recipient)) {
    echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy email']);
    exit;
}

// Gửi email sử dụng PHPMailer
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // Cấu hình SMTP
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'nguyenthanhdanh4626@gmail.com';
    $mail->Password = 'mxkb emmn ebul dyuy';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->CharSet = 'UTF-8';

    // Người gửi và người nhận
    $mail->setFrom('nguyenthanhdanh4626@gmail.com', 'Đức Phương Medical');
    $mail->addAddress($recipient);

    // Nội dung email
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $message_body;

    $mail->send();
    echo json_encode(['status' => 'success', 'message' => 'Đã gửi mã xác nhận']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Không thể gửi email: ' . $mail->ErrorInfo]);
}
?>
