<?php
/**
 * Product Cache API
 * Endpoints để lấy sản phẩm với cache-first strategy
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../cache_manager.php';

$cacheManager = new CacheManager($pdo);

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';
$productId = $_GET['id'] ?? $_POST['id'] ?? null;

switch ($action) {
    case 'get':
        // Lấy sản phẩm với cache-first
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        $cacheLevel = $_GET['level'] ?? 'basic'; // basic | full
        $result = $cacheManager->getProduct($productId, $cacheLevel);
        echo json_encode($result);
        break;
        
    case 'check':
        // Chỉ kiểm tra cache status (không query DB)
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        $cacheLevel = $_GET['level'] ?? 'basic';
        $cacheCheck = $cacheManager->checkCache($productId, $cacheLevel);
        
        echo json_encode([
            'success' => true,
            'cache_valid' => $cacheCheck['valid'],
            'cache_info' => $cacheCheck['cache_info'] ?? ['reason' => $cacheCheck['reason']]
        ]);
        break;
        
    case 'refresh':
        // Mark cache for refresh
        if (!$productId) {
            echo json_encode(['success' => false, 'error' => 'Product ID required']);
            exit;
        }
        
        $cacheLevel = $_POST['level'] ?? 'both';
        $source = $_POST['source'] ?? 'manual_api';
        
        $success = $cacheManager->markForRefresh($productId, $cacheLevel, $source);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Cache marked for refresh' : 'Failed to mark cache'
        ]);
        break;
        
    case 'prewarm':
        // Pre-warm cache cho sản phẩm hot
        $limit = $_POST['limit'] ?? 50;
        $cacheType = $_POST['type'] ?? 'basic';
        
        $result = $cacheManager->prewarmCache($limit, $cacheType);
        
        echo json_encode([
            'success' => true,
            'warmed_count' => $result['warmed_count'],
            'total_products' => $result['total']
        ]);
        break;
        
    case 'stats':
        // Lấy cache performance stats
        $hours = $_GET['hours'] ?? 24;
        $stats = $cacheManager->getCachePerformance($hours);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
