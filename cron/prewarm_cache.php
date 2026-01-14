<?php
/**
 * Cron job để pre-warm cache cho sản phẩm hot
 * Chạy hàng ngày: php cron/prewarm_cache.php
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../cache_manager.php';

$cacheManager = new CacheManager($pdo);

echo "=== Pre-warming Product Cache ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Pre-warm basic cache cho 50 sản phẩm hot nhất
echo "Pre-warming BASIC cache for top 50 products...\n";
$basicResult = $cacheManager->prewarmCache(50, 'basic');
echo "✓ Warmed {$basicResult['warmed_count']}/{$basicResult['total']} basic caches\n\n";

// 2. Pre-warm full cache cho 20 sản phẩm hot nhất
echo "Pre-warming FULL cache for top 20 products...\n";
$fullResult = $cacheManager->prewarmCache(20, 'full');
echo "✓ Warmed {$fullResult['warmed_count']}/{$fullResult['total']} full caches\n\n";

// 3. Lấy cache performance stats
echo "=== Cache Performance Stats ===\n";
$stats = $cacheManager->getCachePerformance(24);

if ($stats) {
    echo "Total operations (24h): {$stats['total_operations']}\n";
    echo "Cache hits: {$stats['cache_hits']} ({$stats['hit_rate_percent']}%)\n";
    echo "Cache misses: {$stats['cache_misses']}\n";
    echo "Avg response time: {$stats['avg_response_time_ms']}ms\n";
    
    if ($stats['hit_rate_percent'] < 90) {
        echo "⚠️ WARNING: Cache hit rate below target (90%)\n";
    } else {
        echo "✓ Cache hit rate meets target\n";
    }
} else {
    echo "No stats available yet\n";
}

echo "\n=== Pre-warm Complete ===\n";
echo "Finished at: " . date('Y-m-d H:i:s') . "\n";
?>
