<?php

// Debug branch: gọi /api/admin_product_list.php?debug=1 để xem rõ lỗi trên hosting
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    header('Content-Type: text/plain; charset=utf-8');

    echo "DEBUG admin_product_list.php\n";
    echo "PHP_VERSION=" . PHP_VERSION . "\n";

    require_once __DIR__ . '/../config.php';

    if (!isset($conn) || !$conn) {
        echo "No mysqli connection (\\$conn not set)\n";
        exit;
    }

    echo "MySQL host_info=" . $conn->host_info . "\n";

    $res = $conn->query('SELECT COUNT(*) AS c FROM products');
    if (!$res) {
        echo "Query error: " . $conn->error . "\n";
    } else {
        $row = $res->fetch_assoc();
        echo "products_count=" . $row['c'] . "\n";
    }

    exit;
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$missingOnly = isset($_GET['missing_only']) && $_GET['missing_only'] == '1';
$noImageOnly = isset($_GET['no_image_only']) && $_GET['no_image_only'] == '1';
// Optional: giới hạn số sản phẩm trả về, mặc định 1000
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 1000;
if ($limit <= 0 || $limit > 1000) {
    $limit = 1000;
}

try {
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
        $kw = '%' . $conn->real_escape_string($search) . '%';
        $params[] = $kw; $params[] = $kw;
    }

    // Build base query
    $sql = "
        SELECT 
            p.id, p.name, p.sku,
            p.original_price, p.sale_price, p.status,
            COALESCE(p.description_short, '') AS description_short,
            COALESCE(p.description_long, '') AS description_long,
            COALESCE(p.technical_specs, '') AS technical_specs,
            COALESCE(p.slug, '') AS slug,
            pt.name AS type_name,
            pt.type_code AS type_code,
            pg.name AS group_name,
            MAX(CASE WHEN pi.is_thumbnail = 1 THEN pi.image_url END) AS thumbnail_path,
            GROUP_CONCAT(pi.image_url ORDER BY pi.display_order, pi.id SEPARATOR '||') AS gallery_concat
        FROM products p
        LEFT JOIN product_types pt ON pt.type_code = p.type_code
        LEFT JOIN product_groups pg ON pg.id = pt.group_id
        LEFT JOIN product_images pi ON pi.product_id = p.id
    ";

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' GROUP BY p.id ORDER BY p.id DESC LIMIT ' . (int)$limit;

    // Prepare & bind
    if (!empty($params)) {
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        if (!$res) throw new Exception('Execute failed: ' . $conn->error);
    } else {
        $res = $conn->query($sql);
        if (!$res) throw new Exception('Query failed: ' . $conn->error);
    }

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $gallery = array();
        if (!empty($row['gallery_concat'])) {
            $galleryParts = explode('||', $row['gallery_concat']);
            foreach ($galleryParts as $part) {
                if ($part !== '') {
                    $gallery[] = $part;
                }
            }
        }
        $thumbnail = $row['thumbnail_path'];
        if (empty($thumbnail) && !empty($gallery)) {
            $thumbnail = $gallery[0];
        }

        $hasThumb = !empty($thumbnail);
        $hasImages = $hasThumb || count($gallery) > 0;
        $hasDesc = !empty($row['description_short']) || !empty($row['description_long']);
        $hasSpecs = !empty($row['technical_specs']);

        $isComplete = $hasThumb && $hasDesc && $hasSpecs; // core completeness rule
        $isMissing = !$isComplete;

        if ($missingOnly && !$isMissing) continue;
        if ($noImageOnly && $hasImages) continue;

        $rows[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'sku' => $row['sku'],
            'original_price' => (float)$row['original_price'],
            'sale_price' => (float)$row['sale_price'],
            'status' => $row['status'],
            'thumbnail_path' => $thumbnail,
            'gallery_paths' => $gallery,
            'short_description' => $row['description_short'],
            'long_description' => $row['description_long'],
            'technical_specs' => $row['technical_specs'],
            'slug' => $row['slug'],
            'type' => $row['type_name'],
            'group' => $row['group_name'],
            'has_thumbnail' => $hasThumb,
            'has_desc' => $hasDesc,
            'has_specs' => $hasSpecs,
            'is_complete' => $isComplete
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $rows,
        'total' => count($rows)
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'detail' => $e->getMessage()
    ]);
}
