<?php
/**
 * Cache Manager - MySQL-based caching system
 * Không dùng Redis, cache lưu trong MySQL tables
 */

class CacheManager {
    private $pdo;
    private $cacheSettings;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadCacheSettings();
    }
    
    /**
     * Load cache settings from database
     */
    private function loadCacheSettings() {
        try {
            $stmt = $this->pdo ? $this->pdo->query("SELECT * FROM cache_settings LIMIT 1") : false;
                $this->cacheSettings = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
        } catch (Exception $e) {
            $this->cacheSettings = null;
        }

        if (!$this->cacheSettings || !is_array($this->cacheSettings)) {
            // Default settings nếu chưa có hoặc lỗi query
            $this->cacheSettings = [
                'enable_cache' => false,
                'basic_cache_ttl' => 3600,
                'full_cache_ttl' => 300,
                'price_cache_ttl' => 300,
                'cache_hit_threshold' => 90
            ];
        }
    }
    
    /**
     * Kiểm tra cache có valid không
     * @param int $productId
     * @param string $cacheLevel 'basic' | 'full'
     * @return array ['valid' => bool, 'data' => mixed, 'cache_info' => array]
     */
    public function checkCache($productId, $cacheLevel = 'basic') {
        if (!$this->cacheSettings['enable_cache']) {
            return ['valid' => false, 'data' => null, 'reason' => 'cache_disabled'];
        }
        
        $cacheField = $cacheLevel === 'basic' ? 'basic_cached_at' : 'full_cached_at';
        $ttlField = $cacheLevel === 'basic' ? 'basic_ttl' : 'full_ttl';
        
        $stmt = $this->pdo->prepare("
            SELECT 
                pcm.*,
                p.form_cache_data,
                p.cache_version as product_cache_version,
                TIMESTAMPDIFF(SECOND, pcm.$cacheField, NOW()) as age_seconds
            FROM product_cache_metadata pcm
            JOIN products p ON p.id = pcm.product_id
            WHERE pcm.product_id = ?
        ");
        $stmt->execute([$productId]);
        $cache = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cache) {
            return ['valid' => false, 'data' => null, 'reason' => 'no_cache_metadata'];
        }
        
        // Kiểm tra cache expired
        $cachedAt = $cache[$cacheField];
        if (!$cachedAt) {
            return ['valid' => false, 'data' => null, 'reason' => 'never_cached'];
        }
        
        $ttl = $cache[$ttlField] ?: $this->cacheSettings[$ttlField];
        $ageSeconds = $cache['age_seconds'];
        
        if ($ageSeconds > $ttl) {
            return ['valid' => false, 'data' => null, 'reason' => 'cache_expired', 'age' => $ageSeconds, 'ttl' => $ttl];
        }
        
        // Cache version mismatch
        if ($cache['cache_version'] != $cache['product_cache_version']) {
            return ['valid' => false, 'data' => null, 'reason' => 'version_mismatch'];
        }
        
        // Cache HIT - parse JSON data
        $cacheData = json_decode($cache['form_cache_data'], true);
        
        // Log cache hit
        $this->logCacheOperation($productId, 'hit', $cacheLevel, null, $ageSeconds);
        
        return [
            'valid' => true,
            'data' => $cacheData,
            'cache_info' => [
                'age_seconds' => $ageSeconds,
                'ttl' => $ttl,
                'cached_at' => $cachedAt,
                'is_manually_cached' => $cache['is_manually_cached'],
                'cache_version' => $cache['cache_version']
            ]
        ];
    }
    
    /**
     * Tạo cache từ form data (khi admin submit form với checkbox checked)
     * @param int $productId
     * @param int $adminId
     * @param array $formData
     * @param string $cacheLevel 'basic' | 'full' | 'both'
     * @return bool
     */
    public function createCacheFromForm($productId, $adminId, $formData, $cacheLevel = 'both') {
        try {
            // Call stored procedure
            $stmt = $this->pdo->prepare("CALL create_cache_from_form(?, ?, ?, ?)");
            $stmt->execute([
                $productId,
                $adminId,
                json_encode($formData),
                $cacheLevel
            ]);
            
            // Log operation
            $this->logCacheOperation($productId, 'manual_create', $cacheLevel, $adminId);
            
            return true;
        } catch (PDOException $e) {
            error_log("Cache creation failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lấy dữ liệu sản phẩm với cache-first strategy
     * @param int $productId
     * @param string $cacheLevel
     * @return array
     */
    public function getProduct($productId, $cacheLevel = 'basic') {
        $startTime = microtime(true);
        
        // 1. Check cache trước
        $cacheResult = $this->checkCache($productId, $cacheLevel);
        
        if ($cacheResult['valid']) {
            $cacheData = $cacheResult['data'];

            // Đảm bảo luôn có technical_specs mới nhất khi lấy mức full
            if ($cacheLevel === 'full') {
                try {
                    $stmt = $this->pdo->prepare("SELECT technical_specs FROM products WHERE id = ?");
                    $stmt->execute([$productId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row && isset($row['technical_specs'])) {
                        $cacheData['technical_specs'] = $row['technical_specs'];
                    }
                } catch (Exception $e) {
                    // Nếu lỗi, vẫn dùng cacheData cũ
                }
            }

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'data' => $cacheData,
                'from_cache' => true,
                'cache_info' => $cacheResult['cache_info'],
                'response_time_ms' => $responseTime
            ];
        }
        
        // 2. Cache MISS - Query database
        $productData = $this->queryProductFromDB($productId, $cacheLevel);
        
        // Fallback to basic query if full view not available or failed
        if (!$productData && $cacheLevel === 'full') {
            $productData = $this->queryProductFromDB($productId, 'basic');
        }

        // Bổ sung technical_specs trực tiếp từ bảng products cho mức full
        if ($productData && $cacheLevel === 'full') {
            try {
                $stmt = $this->pdo->prepare("SELECT technical_specs FROM products WHERE id = ?");
                $stmt->execute([$productId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && isset($row['technical_specs'])) {
                    $productData['technical_specs'] = $row['technical_specs'];
                }
            } catch (Exception $e) {
                // ignore, dùng productData hiện tại
            }
        }

        if (!$productData) {
            return ['success' => false, 'error' => 'Product not found'];
        }
        
        // 3. Update cache với dữ liệu mới
        $this->updateCache($productId, $productData, $cacheLevel);
        
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Log cache miss
        $this->logCacheOperation($productId, 'miss', $cacheLevel, null, null, $responseTime);
        
        return [
            'success' => true,
            'data' => $productData,
            'from_cache' => false,
            'cache_info' => ['reason' => $cacheResult['reason']],
            'response_time_ms' => $responseTime
        ];
    }
    
    /**
     * Query sản phẩm từ database
     */
    private function queryProductFromDB($productId, $cacheLevel) {
        if ($cacheLevel === 'basic') {
            // Basic info: chỉ thông tin cơ bản
            $stmt = $this->pdo->prepare("
                SELECT 
                    p.id, p.name, p.sku, p.slug,
                    p.sale_price, p.original_price,
                    p.stock_quantity, p.status,
                    pt.name as type_name,
                    pg.name as group_name,
                    (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as thumbnail
                FROM products p
                LEFT JOIN product_types pt ON p.type_code = pt.type_code
                LEFT JOIN product_groups pg ON pt.group_id = pg.id
                WHERE p.id = ?
            ");
        } else {
            // Full info: thông tin đầy đủ
            $stmt = $this->pdo->prepare("
                SELECT * FROM vw_admin_products_full WHERE id = ?
            ");
        }
        
        $stmt->execute([$productId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Cập nhật cache
     */
    private function updateCache($productId, $productData, $cacheLevel) {
        try {
            // 1. Update products.form_cache_data
            $stmt = $this->pdo->prepare("
                UPDATE products 
                SET form_cache_data = ?,
                    last_cached_from_form = NOW(),
                    cache_version = cache_version + 1
                WHERE id = ?
            ");
            $stmt->execute([json_encode($productData), $productId]);
            
            // 2. Update product_cache_metadata
            if ($cacheLevel === 'basic' || $cacheLevel === 'both') {
                $stmt = $this->pdo->prepare("
                    UPDATE product_cache_metadata
                    SET basic_cached_at = NOW(),
                        cache_version = (SELECT cache_version FROM products WHERE id = ?),
                        is_manually_cached = 0
                    WHERE product_id = ?
                ");
                $stmt->execute([$productId, $productId]);
            }
            
            if ($cacheLevel === 'full' || $cacheLevel === 'both') {
                $stmt = $this->pdo->prepare("
                    UPDATE product_cache_metadata
                    SET full_cached_at = NOW(),
                        cache_version = (SELECT cache_version FROM products WHERE id = ?),
                        is_manually_cached = 0
                    WHERE product_id = ?
                ");
                $stmt->execute([$productId, $productId]);
            }
            
        } catch (PDOException $e) {
            error_log("Cache update failed: " . $e->getMessage());
        }
    }
    
    /**
     * Mark cache for refresh (gọi từ triggers hoặc manual)
     */
    public function markForRefresh($productId, $cacheLevel = 'both', $source = 'manual') {
        try {
            $stmt = $this->pdo->prepare("CALL mark_cache_for_refresh(?, ?, ?)");
            $stmt->execute([$productId, $cacheLevel, $source]);
            return true;
        } catch (PDOException $e) {
            error_log("Mark for refresh failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log cache operation
     */
    private function logCacheOperation($productId, $operation, $cacheLevel, $adminId = null, $cacheAge = null, $responseTime = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO cache_operations_log 
                (product_id, operation_type, cache_level, admin_id, cache_age_seconds, response_time_ms)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$productId, $operation, $cacheLevel, $adminId, $cacheAge, $responseTime]);
        } catch (PDOException $e) {
            error_log("Log cache operation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Lấy cache performance stats
     */
    public function getCachePerformance($hours = 24) {
        $stmt = $this->pdo->query("SELECT * FROM vw_cache_performance");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Pre-warm cache cho sản phẩm hot
     */
    public function prewarmCache($limit = 50, $cacheType = 'basic') {
        $stmt = $this->pdo->prepare("CALL get_products_for_prewarm(?, ?)");
        $stmt->execute([$limit, $cacheType]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $warmedCount = 0;
        foreach ($products as $product) {
            $productData = $this->queryProductFromDB($product['id'], $cacheType);
            if ($productData) {
                $this->updateCache($product['id'], $productData, $cacheType);
                $warmedCount++;
            }
        }
        
        return ['warmed_count' => $warmedCount, 'total' => count($products)];
    }
}
?>
