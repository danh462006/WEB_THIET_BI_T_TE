# Hệ thống Cache MySQL cho E-commerce Y Tế

## 📋 Tổng quan

Hệ thống cache sử dụng **MySQL làm storage** (không dùng Redis) để tăng tốc độ load trang và giảm query database.

## 🎯 Mục tiêu

- ✅ Cache hit rate > 90%
- ✅ Load time < 1 giây
- ✅ Tự động refresh cache khi data thay đổi
- ✅ Admin có thể tạo cache từ form

## 📂 Cấu trúc Files

```
ducphuongmedical/
├── cache_manager.php          # Core cache logic
├── san-pham.php               # Public product detail (cache-first)
├── api/
│   ├── product_cache.php      # Cache API endpoints
│   └── admin_product_save.php # Save product với cache
├── cron/
│   └── prewarm_cache.php      # Pre-warm cache job
└── .htaccess                  # SEO URL rewrites
```

## 🚀 Cách sử dụng

### 1. Admin thêm sản phẩm với cache

**Bước 1:** Vào trang quản trị sản phẩm
```
http://localhost/ducphuongmedical/quan-tri-vien-sanpham.html
```

**Bước 2:** Điền/chỉnh sửa thông tin sản phẩm và bật cache nếu cần

**Bước 3:** Lưu → Cache được tạo ngay từ form data

**Kết quả:**
- User thấy sản phẩm NGAY LẬP TỨC (không query DB)
- Cache TTL: Basic = 1h, Full = 5min
- Auto-refresh khi giá/stock thay đổi

### 2. Public user xem sản phẩm

**URL SEO-friendly:**
```
http://localhost/ducphuongmedical/san-pham/giuong-benh-dien-3-chuc-nang-123
```

**Flow:**
1. Check cache trong `product_cache_metadata`
2. Nếu valid → lấy từ `products.form_cache_data` (JSON)
3. Nếu expired → query DB → update cache
4. Response time: < 100ms (cache hit)

### 3. Pre-warm cache (Cron job)

**Chạy manual:**
```bash
cd c:\xampp\htdocs\ducphuongmedical
php cron/prewarm_cache.php
```

**Cron schedule (hàng ngày 2h sáng):**
```
0 2 * * * cd /path/to/ducphuongmedical && php cron/prewarm_cache.php
```

**Kết quả:**
- 50 sản phẩm hot nhất: basic cache
- 20 sản phẩm hot nhất: full cache

### 4. API Endpoints

#### 4.1 Lấy sản phẩm (cache-first)
```php
GET /api/product_cache.php?action=get&id=123&level=basic
```

**Response:**
```json
{
  "success": true,
  "data": { /* product data */ },
  "from_cache": true,
  "cache_info": {
    "age_seconds": 120,
    "ttl": 3600,
    "cached_at": "2025-12-26 10:30:00"
  },
  "response_time_ms": 45.2
}
```

#### 4.2 Kiểm tra cache status
```php
GET /api/product_cache.php?action=check&id=123&level=basic
```

#### 4.3 Mark cache for refresh
```php
POST /api/product_cache.php
action=refresh&id=123&level=both&source=manual
```

#### 4.4 Cache performance stats
```php
GET /api/product_cache.php?action=stats&hours=24
```

**Response:**
```json
{
  "success": true,
  "stats": {
    "total_operations": 1250,
    "cache_hits": 1150,
    "cache_misses": 100,
    "hit_rate_percent": 92,
    "avg_response_time_ms": 87.5
  }
}
```

#### 4.5 Pre-warm cache
```php
POST /api/product_cache.php
action=prewarm&limit=50&type=basic
```

## 🗄️ Database Schema

### Bảng chính

**1. products**
```sql
- form_cache_data (JSON)      -- Cache data từ form
- cache_version (INT)          -- Version để invalidate cache
- last_cached_from_form (DATETIME)
```

**2. product_cache_metadata**
```sql
- product_id
- cache_key_basic
- cache_key_full
- basic_cached_at
- full_cached_at
- basic_ttl (default: 3600)
- full_ttl (default: 300)
- cache_version
- is_manually_cached (BOOLEAN)
```

**3. cache_operations_log**
```sql
- product_id
- operation_type (hit, miss, manual_create, refresh)
- cache_level (basic, full, both)
- admin_id
- cache_age_seconds
- response_time_ms
- created_at
```

### Stored Procedures

**1. create_cache_from_form**
```sql
CALL create_cache_from_form(
    product_id INT,
    admin_id INT,
    form_data JSON,
    cache_level VARCHAR(10)  -- 'basic', 'full', 'both'
);
```

**2. mark_cache_for_refresh**
```sql
CALL mark_cache_for_refresh(
    product_id INT,
    cache_level VARCHAR(10),
    source VARCHAR(50)  -- 'price_change', 'stock_change', 'manual'
);
```

**3. get_products_for_prewarm**
```sql
CALL get_products_for_prewarm(
    limit_count INT,
    cache_type VARCHAR(10)
);
```

### Views

**vw_cache_performance**
```sql
SELECT 
    total_operations,
    cache_hits,
    cache_misses,
    ROUND((cache_hits / total_operations) * 100, 2) as hit_rate_percent,
    AVG(response_time_ms) as avg_response_time_ms
FROM cache_operations_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
```

## ⚙️ Cache Settings

**Bảng cache_settings:**
```sql
INSERT INTO cache_settings VALUES (
    enable_cache = TRUE,
    basic_cache_ttl = 3600,    -- 1 giờ
    full_cache_ttl = 300,       -- 5 phút
    price_cache_ttl = 300,      -- 5 phút (auto-refresh khi giá thay đổi)
    cache_hit_threshold = 90    -- Mục tiêu hit rate
);
```

## 🔄 Auto Refresh (Triggers)

**Triggers đã có sẵn trong database:**

1. **after_product_price_update**
   - Khi giá thay đổi → Mark cache for refresh (both)
   - Cache sẽ tự động expired sau 5 phút

2. **after_product_stock_update**
   - Khi stock thay đổi → Mark cache for refresh (full)

3. **after_product_status_update**
   - Khi status thay đổi → Mark cache for refresh (both)

4. **after_product_insert**
   - Tạo cache metadata entry mới

## 📊 Monitoring

### Dashboard query
```sql
-- Cache performance 24h
SELECT * FROM vw_cache_performance;

-- Top 10 sản phẩm cache hit nhiều nhất
SELECT 
    product_id,
    COUNT(*) as total_hits,
    AVG(response_time_ms) as avg_response_ms
FROM cache_operations_log
WHERE operation_type = 'hit'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY product_id
ORDER BY total_hits DESC
LIMIT 10;

-- Sản phẩm cache expired nhiều nhất (cần pre-warm)
SELECT 
    product_id,
    COUNT(*) as miss_count
FROM cache_operations_log
WHERE operation_type = 'miss'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY product_id
ORDER BY miss_count DESC
LIMIT 10;
```

### Thông báo khi hit rate < 90%
```sql
SELECT 
    CASE 
        WHEN hit_rate_percent < 90 THEN '⚠️ WARNING: Hit rate below target!'
        ELSE '✅ Cache performance OK'
    END as status,
    hit_rate_percent,
    total_operations
FROM vw_cache_performance;
```

## 🧪 Testing

### Test cache hit
```bash
# Lần 1: Cache miss (query DB)
curl "http://localhost/ducphuongmedical/san-pham/giuong-benh-123"
# Response time: ~500ms

# Lần 2: Cache hit
curl "http://localhost/ducphuongmedical/san-pham/giuong-benh-123"
# Response time: ~50ms
```

### Test admin form cache
1. Vào admin form
2. Check ✅ "Tạo cache từ form"
3. Submit
4. Kiểm tra database:
```sql
SELECT 
    id, 
    last_cached_from_form, 
    cache_version,
    JSON_EXTRACT(form_cache_data, '$.name') as cached_name
FROM products 
WHERE id = <product_id>;
```

## 🔒 Security

- Admin form: Kiểm tra session `logged_in` và quyền admin
- API: Validate input parameters
- SQL Injection: Dùng prepared statements
- XSS: htmlspecialchars() cho output

## 📈 Performance Targets

| Metric | Target | Current |
|--------|--------|---------|
| Cache hit rate | > 90% | Check `vw_cache_performance` |
| Avg response time | < 1000ms | Check logs |
| Basic cache TTL | 1 hour | 3600s |
| Full cache TTL | 5 minutes | 300s |

## 🐛 Troubleshooting

### Cache không hoạt động?
```sql
-- Kiểm tra enable_cache
SELECT enable_cache FROM cache_settings;

-- Kiểm tra cache metadata
SELECT * FROM product_cache_metadata WHERE product_id = 123;
```

### Cache hit rate thấp?
1. Tăng TTL trong `cache_settings`
2. Chạy pre-warm: `php cron/prewarm_cache.php`
3. Kiểm tra triggers có hoạt động không

### Response time vẫn chậm?
1. Kiểm tra index database
2. Optimize JSON trong `form_cache_data`
3. Giảm dữ liệu trong full cache

## 📞 Support

Liên hệ admin nếu:
- Cache hit rate < 80%
- Response time > 2 giây
- Triggers không hoạt động

---

**Tạo bởi:** Đức Phương Medical Equipment
**Ngày:** 26/12/2025
**Version:** 1.0
