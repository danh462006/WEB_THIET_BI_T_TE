<?php
header('Content-Type: application/json');

// Cho phép CORS nếu cần
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$response = array();

try {
    // Kiểm tra có file được upload không
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Không có file nào được upload hoặc có lỗi xảy ra.');
    }

    // Lấy thông tin từ POST
    $folderPath = isset($_POST['folderPath']) ? $_POST['folderPath'] : 'hinh-anh/';
    $fileName = isset($_POST['fileName']) ? $_POST['fileName'] : '';
    
    // Đảm bảo folderPath không chứa ký tự nguy hiểm
    $folderPath = str_replace(['..', '\\'], ['', '/'], $folderPath);
    
    // Tạo đường dẫn đầy đủ
    $uploadDir = __DIR__ . '/' . $folderPath;
    
    // Tạo thư mục nếu chưa tồn tại
    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Không thể tạo thư mục: ' . $folderPath);
        }
    }
    
    // Kiểm tra quyền ghi
    if (!is_writable($uploadDir)) {
        throw new Exception('Thư mục không có quyền ghi: ' . $folderPath);
    }
    
    // Lấy tên file từ POST hoặc dùng tên gốc
    if (empty($fileName)) {
        $fileName = basename($_FILES['image']['name']);
    }
    
    // Validate file type
    $allowedTypes = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/bmp');
    $fileType = $_FILES['image']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Loại file không được phép. Chỉ chấp nhận: JPG, PNG, GIF, WEBP, SVG, BMP');
    }
    
    // Validate file extension
    $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp');
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedExtensions)) {
        throw new Exception('Phần mở rộng file không hợp lệ.');
    }
    
    // Tạo đường dẫn file đầy đủ
    $targetFile = $uploadDir . $fileName;
    
    // Di chuyển file
    if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
        // Đường dẫn tương đối để lưu vào database/localStorage
        $relativePath = $folderPath . $fileName;
        
        $response['status'] = 'success';
        $response['message'] = 'Upload file thành công!';
        $response['path'] = $relativePath;
        $response['fullPath'] = $targetFile;
        $response['fileName'] = $fileName;
        $response['fileSize'] = $_FILES['image']['size'];
    } else {
        throw new Exception('Không thể di chuyển file đã upload.');
    }
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
