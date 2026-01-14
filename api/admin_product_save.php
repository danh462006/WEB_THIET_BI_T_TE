<?php
/**
 * Admin Product Save API
 * Lưu sản phẩm với tùy chọn tạo cache từ form
 */

require_once '../session-config.php';
require_once '../config.php';
require_once '../cache_manager.php';

header('Content-Type: application/json');

// Debug: Log session info
error_log('=== admin_product_save.php ===');
error_log('Session ID: ' . session_id());
error_log('Session status: ' . session_status());
error_log('$_SESSION: ' . json_encode($_SESSION));
error_log('$_COOKIE: ' . json_encode($_COOKIE));

// Kiểm tra quyền admin dựa trên session hiện có
$sessionUserId = $_SESSION['user_id'] ?? null;
$sessionPosition = $_SESSION['position'] ?? '';

if (!$sessionUserId) {
    error_log('Unauthorized: user_id not set in session');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$posLower = mb_strtolower((string)$sessionPosition, 'UTF-8');
if ($posLower !== 'quan-tri-vien' && $posLower !== 'admin') {
    error_log('Unauthorized: position is not admin, got: ' . $sessionPosition);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$cacheManager = new CacheManager($pdo);

try {
    $pdo->beginTransaction();
    
    // 1. Lấy dữ liệu từ POST
    // Debug: log toàn bộ $_POST để kiểm tra
    error_log('=== $_POST data ===');
    error_log('All POST keys: ' . implode(', ', array_keys($_POST)));
    error_log('technical_specs isset: ' . (isset($_POST['technical_specs']) ? 'YES' : 'NO'));
    error_log('technical_specs value: ' . ($_POST['technical_specs'] ?? 'NULL'));
    
    $productId = $_POST['product_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $sku = $_POST['sku'] ?? '';
    $typeCode = $_POST['type_code'] ?? null;
    $originalPrice = $_POST['original_price'] ?? 0;
    $salePrice = $_POST['sale_price'] ?? 0;
    $stockQuantity = $_POST['stock_quantity'] ?? 0;
    $descriptionShort = $_POST['description_short'] ?? '';
    $descriptionLong = $_POST['description_long'] ?? '';
    $technicalSpecs = $_POST['technical_specs'] ?? '';
    $createCacheFromForm = isset($_POST['create_cache_from_form']) && $_POST['create_cache_from_form'] == '1';
    
    // Validate required fields
    if (empty($name) || empty($sku) || !$typeCode) {
        throw new Exception('Vui lòng điền đầy đủ thông tin bắt buộc');
    }
    
    // Debug: Log technical_specs being saved
    error_log("Saving product - technical_specs length: " . strlen($technicalSpecs));
    error_log("technical_specs value: " . substr($technicalSpecs, 0, 100));

    // 2. Insert hoặc Update sản phẩm - Lưu technical_specs dưới dạng text thuần
    if ($productId) {
        // Update existing product - LUÔN update cột technical_specs vào bảng products
        $stmt = $pdo->prepare("
            UPDATE products SET
                name = ?,
                sku = ?,
                type_code = ?,
                original_price = ?,
                sale_price = ?,
                stock_quantity = ?,
                technical_specs = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $result = $stmt->execute([
            $name, $sku, $typeCode, $originalPrice, $salePrice, 
            $stockQuantity, $technicalSpecs, $productId
        ]);
        error_log("UPDATE executed: " . ($result ? 'SUCCESS' : 'FAILED'));
        error_log("Rows affected: " . $stmt->rowCount());
        if (!$result) {
            error_log("UPDATE error: " . print_r($stmt->errorInfo(), true));
        }
    } else {
        // Insert new product
        $stmt = $pdo->prepare("
            INSERT INTO products (name, sku, type_code, original_price, sale_price, stock_quantity, technical_specs)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $sku, $typeCode, $originalPrice, $salePrice, $stockQuantity, $technicalSpecs
        ]);
        $productId = $pdo->lastInsertId();
    }
    
    // Verify: Đọc lại từ DB để confirm technical_specs đã được lưu
    $verifyStmt = $pdo->prepare("SELECT technical_specs FROM products WHERE id = ?");
    $verifyStmt->execute([$productId]);
    $verifyRow = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    error_log("After save - technical_specs in DB: " . substr($verifyRow['technical_specs'] ?? 'NULL', 0, 100));
    
    // 3. Nếu checkbox "Tạo cache từ form" được check
    if ($createCacheFromForm) {
        $adminId = $_SESSION['user_id'] ?? null;
        
        // Gọi stored procedure để tạo cache từ form data
        $formData = [
            'id' => $productId,
            'name' => $name,
            'sku' => $sku,
            'type_code' => $typeCode,
            'original_price' => $originalPrice,
            'sale_price' => $salePrice,
            'stock_quantity' => $stockQuantity,
            'technical_specs' => $technicalSpecs,
            'cached_from_form' => true,
            'cached_at' => date('Y-m-d H:i:s')
        ];
        
        $cacheSuccess = $cacheManager->createCacheFromForm(
            $productId, 
            $adminId, 
            $formData, 
            'both' // Cache cả basic và full
        );
        
        if (!$cacheSuccess) {
            error_log("Warning: Cache creation failed for product $productId");
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'product_id' => $productId,
        'message' => $productId ? 'Cập nhật sản phẩm thành công' : 'Thêm sản phẩm thành công',
        'cache_created' => $createCacheFromForm
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
