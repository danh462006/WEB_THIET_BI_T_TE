<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

$input = [];
if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

$id = isset($input['id']) ? intval($input['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product id']);
    exit;
}

// Sanitizers
function sanitize_rich_text($str) {
    if ($str === null) return null;
    $s = (string)$str;
    // Remove style/script blocks and HTML comments
    $s = preg_replace('/<(script|style)[^>]*>[\s\S]*?<\/(script|style)>/i', ' ', $s);
    $s = preg_replace('/<!--[\s\S]*?-->/m', ' ', $s);
    // Normalize common inline breaks and list items
    $s = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $s);
    $s = preg_replace('/<\s*li\s*>/i', '- ', $s);
    $s = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $s);
    $s = preg_replace('/<\s*p\s*>/i', '', $s);
    $s = preg_replace('/<\s*\/\s*p\s*>/i', "\n\n", $s);
    // Strip remaining tags
    $s = strip_tags($s);
    // Decode HTML entities
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Collapse whitespace
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}

function sanitize_slug($str) {
    if ($str === null) return null;
    $s = strtolower(html_entity_decode(strip_tags((string)$str)));
    // Replace non alnum with dashes
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    // Collapse and trim dashes
    $s = preg_replace('/-+/u', '-', $s);
    $s = trim($s, '-');
    return $s;
}

function sync_product_images(mysqli $conn, int $productId, ?string $thumbnailPath, ?array $galleryPaths, bool $shouldProcess): void {
    if (!$shouldProcess) return;

    $images = [];
    if (is_array($galleryPaths)) {
        foreach ($galleryPaths as $url) {
            $u = trim((string)$url);
            if ($u !== '') $images[] = $u;
        }
    }

    $thumb = $thumbnailPath !== null ? trim($thumbnailPath) : '';
    if ($thumb !== '' && !in_array($thumb, $images, true)) {
        array_unshift($images, $thumb);
    }

    $conn->begin_transaction();
    try {
        $del = $conn->prepare('DELETE FROM product_images WHERE product_id = ?');
        if ($del) {
            $del->bind_param('i', $productId);
            $del->execute();
            $del->close();
        }

        if (!empty($images)) {
            $ins = $conn->prepare('INSERT INTO product_images (product_id, image_url, is_thumbnail, is_main, display_order) VALUES (?, ?, ?, ?, ?)');
            if ($ins) {
                foreach ($images as $idx => $url) {
                    $isThumb = ($thumb !== '') ? (int)($url === $thumb) : ($idx === 0 ? 1 : 0);
                    $isMain = $isThumb;
                    $order = $idx + 1;
                    $ins->bind_param('isiii', $productId, $url, $isThumb, $isMain, $order);
                    $ins->execute();
                }
                $ins->close();
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
    }
}

// Collect fields
$fieldMap = [
    'name' => ['column' => 'name', 'type' => 's'],
    'sku' => ['column' => 'sku', 'type' => 's'],
    'type_code' => ['column' => 'type_code', 'type' => 's'],
    'original_price' => ['column' => 'original_price', 'type' => 'd'],
    'sale_price' => ['column' => 'sale_price', 'type' => 'd'],
    'stock_quantity' => ['column' => 'stock_quantity', 'type' => 'i'],
    'status' => ['column' => 'status', 'type' => 's'],
    'short_description' => ['column' => 'description_short', 'type' => 's'],
    'long_description' => ['column' => 'description_long', 'type' => 's'],
    'technical_specs' => ['column' => 'technical_specs', 'type' => 's'],
    'brand' => ['column' => 'brand', 'type' => 's'],
    'weight' => ['column' => 'weight', 'type' => 's'],
    'dimensions' => ['column' => 'dimensions', 'type' => 's'],
    'slug' => ['column' => 'slug', 'type' => 's'],
    'meta_title' => ['column' => 'meta_title', 'type' => 's'],
    'meta_description' => ['column' => 'meta_description', 'type' => 's'],
    'meta_keywords' => ['column' => 'meta_keywords', 'type' => 's']
];

$sanitizeKeys = ['short_description','long_description','technical_specs','brand','dimensions','meta_title','meta_description','meta_keywords'];

$galleryPaths = isset($input['gallery_paths']) ? $input['gallery_paths'] : null;
if (is_string($galleryPaths)) {
    $decoded = json_decode($galleryPaths, true);
    if (is_array($decoded)) $galleryPaths = $decoded;
}
if ($galleryPaths !== null && !is_array($galleryPaths)) {
    $galleryPaths = null;
}
$thumbnailPath = array_key_exists('thumbnail_path', $input) ? trim((string)$input['thumbnail_path']) : null;
$imagesProvided = array_key_exists('gallery_paths', $input) || array_key_exists('thumbnail_path', $input);

$setParts = [];
$paramTypes = '';
$paramValues = [];

foreach ($fieldMap as $inputKey => $meta) {
    if (array_key_exists($inputKey, $input)) {
        $setParts[] = "`{$meta['column']}` = ?";
        $paramTypes .= $meta['type'];
        $val = $input[$inputKey];
        if (in_array($inputKey, $sanitizeKeys, true)) {
            $val = sanitize_rich_text($val);
        }
        if ($inputKey === 'slug') {
            $val = sanitize_slug($val);
        }
        if ($meta['type'] === 'd') $val = (float)$val;
        if ($meta['type'] === 'i') $val = (int)$val;
        $paramValues[] = $val;
    }
}

if (empty($setParts) && !$imagesProvided) {
    echo json_encode(['success' => false, 'error' => 'No fields to update']);
    exit;
}

// bump cache_version so consumers know to refresh
$setParts[] = "cache_version = cache_version + 1";

if ($paramTypes === '' && $imagesProvided) {
    $sql = "UPDATE products SET " . implode(', ', $setParts) . " WHERE id = ?";
    $paramTypes = 'i';
    $paramValues = [$id];
} else {
    $sql = "UPDATE products SET " . implode(', ', $setParts) . " WHERE id = ?";
    $paramTypes .= 'i';
    $paramValues[] = $id;
}

try {
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed');
    $stmt->bind_param($paramTypes, ...$paramValues);
    $ok = $stmt->execute();
    if (!$ok) throw new Exception('Execute failed');

    sync_product_images($conn, $id, $thumbnailPath, $galleryPaths, $imagesProvided);

    $createCache = isset($input['create_cache']) && ($input['create_cache'] === true || $input['create_cache'] === '1' || $input['create_cache'] === 1);

    $cacheCreated = false;
    if ($createCache) {
        // Build PDO + CacheManager
        try {
            $dsn = "mysql:host={$servername};dbname={$dbname};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            require_once __DIR__ . '/../cache_manager.php';

            // Fetch product row for cache data
            $rs = $conn->query("SELECT * FROM products WHERE id = " . intval($id) . " LIMIT 1");
            $productRow = $rs ? $rs->fetch_assoc() : null;

            $adminId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $cm = new CacheManager($pdo);
            $cacheCreated = $cm->createCacheFromForm($id, $adminId, $productRow, 'both');
        } catch (Throwable $e2) {
            } catch (Exception $e2) {
            // ignore cache errors but report flag
            $cacheCreated = false;
        }
    }

    echo json_encode(['success' => true, 'cache_created' => $cacheCreated]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
