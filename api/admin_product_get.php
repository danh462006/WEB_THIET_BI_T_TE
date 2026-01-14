<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid id']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT 
        p.id, p.name, p.sku, p.type_code,
        p.original_price, p.sale_price, p.stock_quantity, p.status,
        p.description_short AS short_description,
        p.description_long AS long_description,
        p.technical_specs,
        p.slug, p.meta_title, p.meta_description, p.meta_keywords,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_thumbnail = 1 ORDER BY display_order, id LIMIT 1) AS thumbnail_path,
        (SELECT GROUP_CONCAT(image_url ORDER BY display_order, id SEPARATOR '||') FROM product_images WHERE product_id = p.id) AS gallery_concat
        FROM products p WHERE p.id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Not found']);
        exit;
    }

    $gallery = array();
    if (!empty($row['gallery_concat'])) {
        $galleryParts = explode('||', $row['gallery_concat']);
        foreach ($galleryParts as $part) {
            if ($part !== '') {
                $gallery[] = $part;
            }
        }
    }

    if (empty($row['thumbnail_path']) && !empty($gallery)) {
        $row['thumbnail_path'] = $gallery[0];
    }

    $row['original_price'] = (float)$row['original_price'];
    $row['sale_price'] = (float)$row['sale_price'];
    $row['stock_quantity'] = (int)$row['stock_quantity'];
    $row['gallery_paths'] = $gallery;

    echo json_encode(['success' => true, 'data' => $row], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
