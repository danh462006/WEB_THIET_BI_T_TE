<?php
session_start();
header('Content-Type: application/json');

$servername = "localhost";
$username = "reslan";
$password = "nguyendanh0399352950";
$dbname = "ducphuong";

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Kết nối database thất bại']));
}

// Đảm bảo schema có các cột cần thiết (Avatar, Address)
try {
    $columns = [];
    if ($result = $conn->query("SHOW COLUMNS FROM users")) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $result->close();
    }

    if (!in_array('Avatar', $columns)) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `Avatar` VARCHAR(255) NULL DEFAULT 'hinh-anh/hinh-tk-macdinh.png' AFTER `Position`");
    }
    if (!in_array('Address', $columns)) {
        $conn->query("ALTER TABLE `users` ADD COLUMN `Address` TEXT NULL AFTER `Avatar`");
    }
} catch (Exception $e) {
    // Không chặn đăng ký nếu ALTER thất bại; sẽ fallback ở INSERT nếu cần
}

// Lấy dữ liệu JSON từ request
$input = json_decode(file_get_contents('php://input'), true);
$user = isset($input['username']) ? trim($input['username']) : '';
$phone = isset($input['phone']) ? trim($input['phone']) : '';
$gmail = isset($input['gmail']) ? trim($input['gmail']) : '';
$pass = isset($input['password']) ? trim($input['password']) : '';

if (empty($user) || empty($phone) || empty($gmail) || empty($pass)) {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập đầy đủ thông tin']);
    exit;
}

// Kiểm tra trùng lặp (username, phone, hoặc gmail)
$check = $conn->prepare("SELECT id FROM users WHERE username = ? OR phone = ? OR gmail = ?");
$check->bind_param("sss", $user, $phone, $gmail);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập, SĐT hoặc Email đã tồn tại']);
} else {
    $position = 'khach-hang'; // Mặc định là khách hàng
    $default_avatar = 'hinh-anh/hinh-tk-macdinh.png'; // Avatar mặc định
    // Tính ID tiếp theo = MAX(id) + 1; nếu lỗi, fallback để DB tự tăng
    $nextId = null;
    $manualIdPossible = in_array('id', $columns ?? []);
    if ($manualIdPossible) {
        if ($resNext = $conn->query("SELECT IFNULL(MAX(id),0)+1 AS next_id FROM users")) {
            $rowNext = $resNext->fetch_assoc();
            $nextId = (int)$rowNext['next_id'];
            $resNext->close();
        }
    }

    // Xây dựng câu lệnh INSERT linh hoạt theo schema hiện tại
    if ($manualIdPossible && $nextId !== null) {
        // Chèn kèm ID cụ thể
        $insertSql = "INSERT INTO users (id, username, phone, gmail, pass, Position";
        $placeholders = "?,?,?,?,?,?";
        $params = [$nextId, $user, $phone, $gmail, $pass, $position];
        $types = "isssss";
    } else {
        // Để DB tự đánh ID
        $insertSql = "INSERT INTO users (username, phone, gmail, pass, Position";
        $placeholders = "?,?,?,?,?";
        $params = [$user, $phone, $gmail, $pass, $position];
        $types = "sssss";
    }

    if (in_array('Avatar', $columns ?? [])) {
        $insertSql .= ", Avatar";
        $placeholders .= ",?";
        $params[] = $default_avatar;
        $types .= "s";
    }
    $insertSql .= ") VALUES (" . $placeholders . ")";

    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Tự động đăng nhập sau khi đăng ký thành công
        $_SESSION['user_id'] = ($manualIdPossible && $nextId !== null) ? $nextId : $conn->insert_id;
        $_SESSION['username'] = $user;
        $_SESSION['position'] = $position;
        echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi khi tạo tài khoản: ' . $conn->error]);
    }
    $stmt->close();
}

$check->close();
$conn->close();
?>