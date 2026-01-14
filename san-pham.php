<?php
/**
 * Trang chi tiết sản phẩm public
 * Sử dụng cache-first strategy
 * URL: /san-pham/{slug}-{id}
 */

require_once 'config.php';
require_once 'cache_manager.php';

$cacheManager = new CacheManager($pdo);

// Parse URL để lấy product ID
// Ưu tiên query string ?id=..., nếu không có thì thử dạng /san-pham/ten-123
$uri = $_SERVER['REQUEST_URI'];
if (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $slug = isset($_GET['slug']) ? $_GET['slug'] : '';
} else {
    preg_match('/\/san-pham\/(.+)-(\d+)/', $uri, $matches);
    if (!isset($matches[2])) {
        header("HTTP/1.0 404 Not Found");
        include '404.php';
        exit;
    }
    $productId = (int)$matches[2];
    $slug = $matches[1];
}

// Cache-first: Lấy dữ liệu sản phẩm
$result = $cacheManager->getProduct($productId, 'full');

if (!$result['success']) {
    header("HTTP/1.0 404 Not Found");
    include '404.php';
    exit;
}

$product = $result['data'];
$fromCache = $result['from_cache'];
$cacheInfo = $result['cache_info'];

// Helper: tìm technical_specs ở bất kỳ vị trí nào trong JSON đã decode (định nghĩa trước khi dùng)
if (!function_exists('dp_find_technical_specs_in_array')) {
    function dp_find_technical_specs_in_array($arr) {
        foreach ($arr as $k => $v) {
            if ($k === 'technical_specs' && trim((string)$v) !== '') {
                return (string)$v;
            }
            if (is_array($v)) {
                $found = dp_find_technical_specs_in_array($v);
                if ($found !== null) return $found;
            }
        }
        return null;
    }
}

// Luôn ép lại technical_specs mới nhất từ bảng products (phòng khi cache/view thiếu trường)
try {
    $stmtTech = $pdo->prepare("SELECT technical_specs, form_cache_data FROM products WHERE id = ? LIMIT 1");
    $stmtTech->execute([$productId]);
    $rowTech = $stmtTech->fetch(PDO::FETCH_ASSOC);
    if ($rowTech) {
        if (array_key_exists('technical_specs', $rowTech) && trim((string)$rowTech['technical_specs']) !== '') {
            $product['technical_specs'] = $rowTech['technical_specs'];
        } elseif (!empty($rowTech['form_cache_data'])) {
            $fc = json_decode($rowTech['form_cache_data'], true);
            if (is_array($fc)) {
                $foundFc = dp_find_technical_specs_in_array($fc);
                if ($foundFc !== null) {
                    $product['technical_specs'] = $foundFc;
                }
            }
        }
    }
} catch (Exception $e) {
    // Nếu lỗi, bỏ qua và dùng dữ liệu hiện có
}

// Đảm bảo luôn có technical_specs nếu đã được lưu ở bất kỳ đâu (products hoặc cache metadata)
try {
    if (!isset($product['technical_specs']) || trim((string)$product['technical_specs']) === '') {
        // 1) Ưu tiên đọc trực tiếp từ bảng products
        $stmtSpecs = $pdo->prepare("SELECT technical_specs, form_cache_data FROM products WHERE id = ?");
        $stmtSpecs->execute([$productId]);
        $rowSpecs = $stmtSpecs->fetch(PDO::FETCH_ASSOC);
        if ($rowSpecs) {
            if (isset($rowSpecs['technical_specs']) && trim((string)$rowSpecs['technical_specs']) !== '') {
                $product['technical_specs'] = $rowSpecs['technical_specs'];
            } elseif (!empty($rowSpecs['form_cache_data'])) {
                $fc = json_decode($rowSpecs['form_cache_data'], true);
                if (is_array($fc)) {
                    $found = dp_find_technical_specs_in_array($fc);
                    if ($found !== null) {
                        $product['technical_specs'] = $found;
                    }
                }
            }
        }

        // 2) Nếu vẫn chưa có, thử đọc từ bảng product_cache_metadata
        if (!isset($product['technical_specs']) || trim((string)$product['technical_specs']) === '') {
            $stmtMeta = $pdo->prepare("SELECT form_cache_data FROM product_cache_metadata WHERE product_id = ? LIMIT 1");
            $stmtMeta->execute([$productId]);
            $rowMeta = $stmtMeta->fetch(PDO::FETCH_ASSOC);
            if ($rowMeta && !empty($rowMeta['form_cache_data'])) {
                $fc2 = json_decode($rowMeta['form_cache_data'], true);
                if (is_array($fc2)) {
                    $found2 = dp_find_technical_specs_in_array($fc2);
                    if ($found2 !== null) {
                        $product['technical_specs'] = $found2;
                    }
                }
            }
        }
    }
} catch (Exception $e) {
    // Nếu lỗi, vẫn tiếp tục hiển thị phần còn lại
}

// Verify slug matches (SEO) chỉ khi slug tồn tại trong URL dạng đẹp
if ($slug) {
    $expectedSlug = generateSlug($product['name']);
    if ($slug !== $expectedSlug) {
        header("Location: /san-pham/$expectedSlug-$productId", true, 301);
        exit;
    }
}

// Helper function
function generateSlug($str) {
    $str = mb_strtolower($str, 'UTF-8');
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['meta_title'] ?? ($product['name'] ?? 'Sản phẩm')); ?> - TBYT Đức Phương</title>
    <meta name="description" content="<?php echo htmlspecialchars($product['meta_description'] ?? ($product['description'] ?? '')); ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?php echo htmlspecialchars($product['name'] ?? 'Sản phẩm'); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($product['description'] ?? ''); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars(($product['thumbnail'] ?? ($product['thumbnail_path'] ?? ''))); ?>">
    <meta property="og:url" content="https://<?php echo $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="profile-modal.css">
    <link rel="stylesheet" href="product-detail.css">
    <script src="auth-redirect.js"></script>
    <style>
        /* Đánh giá & bình luận */
        .comment-section { max-width: 1100px; margin: 40px auto; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; box-shadow: 0 6px 18px rgba(0,0,0,0.05); }
        .comment-section h2 { margin: 0 0 16px; font-size: 22px; }
        .comment-form { border-bottom: 1px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 20px; }
        .comment-form h3 { margin: 0 0 12px; }
        .rating-input { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .star-rating { display: flex; flex-direction: row-reverse; gap: 6px; }
        .star-rating input { display: none; }
        .star-rating label { font-size: 22px; color: #d1d5db; cursor: pointer; transition: color 0.2s; }
        .star-rating input:checked ~ label, .star-rating label:hover, .star-rating label:hover ~ label { color: #f59e0b; }
        #commentText { width: 100%; border: 1px solid #d1d5db; border-radius: 8px; padding: 10px; resize: vertical; font-size: 14px; min-height: 90px; }
        .comment-form button { margin-top: 10px; }
        .comment-list h3 { margin: 0 0 12px; }
        .comment-item { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; margin-bottom: 12px; background: #f9fafb; }
        .comment-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-weight: 600; }
        .comment-rating { color: #f59e0b; font-size: 14px; }
        @media (max-width: 768px) { .comment-section { margin: 24px 12px; padding: 16px; } }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <div class="logo-section">
                <img src="hinh-anh/logo.png" alt="TBYT Đức Phương" class="logo">
            </div>
            <div class="search-section">
                <div class="search-box">
                    <input type="text" placeholder="Tìm Kiếm ..." class="search-input">
                    <button class="search-btn">🔍</button>
                </div>
            </div>
            <div class="user-section">
                <div class="account-dropdown">
                    <button class="account-btn" onclick="handleAccountClick(event)">
                        <img src="hinh-anh/icon_tk.png" alt="Tài khoản" class="account-icon">
                    </button>
                    <div id="usernameDisplay" class="username-display" style="display: none;"></div>
                    <div class="account-menu" id="accountMenu" style="min-width: 150px; right: 0; left: auto;">
                        <a href="#" onclick="event.stopPropagation(); openProfileModal(); return false;">Thông tin tài khoản</a>
                        <a href="#">Đơn hàng của tôi</a>
                        <a href="#" onclick="handleLogout && handleLogout(); return false;">Đăng xuất</a>
                    </div>
                </div>
                <button class="cart-btn">🛒 Giỏ hàng</button>
                <div class="dropdown">
                    <button class="dropdown-btn">📋 Danh mục ▼</button>
                    <div class="dropdown-content">
                        <a href="index.html">Trang chủ</a>
                        <div class="dropdown-item-with-submenu">
                            <a href="#" class="has-submenu">Sản phẩm</a>
                            <div class="submenu">
                                <div class="submenu-content">
                                    <div class="category-header">
                                        <h3>🔥 Gợi ý cho bạn</h3>
                                    </div>
                                    <div class="category-brands">
                                        <div class="brand-item" data-category="Giường"><img src="icon-hinhdanhmuc/nhom-giuong.png" alt="Giường" class="category-icon">Giường</div>
                                        <div class="brand-item" data-category="Xe lăn"><img src="icon-hinhdanhmuc/nhom-xelan.png" alt="Xe lăn" class="category-icon">Xe lăn</div>
                                        <div class="brand-item" data-category="Xe Scooter điện"><img src="icon-hinhdanhmuc/nhom-xedien.png" alt="Xe Scooter điện" class="category-icon">Xe Scooter điện</div>
                                        <div class="brand-item" data-category="Băng ca"><img src="icon-hinhdanhmuc/nhom-bangca.png" alt="Băng ca" class="category-icon">Băng ca</div>
                                        <div class="brand-item" data-category="Tủ"><img src="icon-hinhdanhmuc/nhom-tu.png" alt="Tủ" class="category-icon">Tủ</div>
                                        <div class="brand-item" data-category="Máy tạo oxy"><img src="icon-hinhdanhmuc/nhom-maytaooxy.png" alt="Máy tạo oxy" class="category-icon">Máy tạo oxy</div>
                                        <div class="brand-item" data-category="Máy đo"><img src="icon-hinhdanhmuc/nhom-maydo.png" alt="Máy đo" class="category-icon">Máy đo</div>
                                        <div class="brand-item" data-category="Máy xông"><img src="icon-hinhdanhmuc/nhom-xong.png" alt="Máy xông" class="category-icon">Máy xông</div>
                                        <div class="brand-item" data-category="Máy hút dịch"><img src="icon-hinhdanhmuc/nhom-mayhutdich.png" alt="Máy hút dịch" class="category-icon">Máy hút dịch</div>
                                        <div class="brand-item" data-category="Máy massage"><img src="icon-hinhdanhmuc/nhom-massage.png" alt="Máy massage" class="category-icon">Máy massage</div>
                                        <div class="brand-item" data-category="Thiết bị tập"><img src="icon-hinhdanhmuc/nhom-maytap.png" alt="Thiết bị tập" class="category-icon">Thiết bị tập</div>
                                        <div class="brand-item" data-category="Nệm"><img src="icon-hinhdanhmuc/nhom-nem.png" alt="Nệm" class="category-icon">Nệm</div>
                                        <div class="brand-item" data-category="Dụng cụ hỗ trợ"><img src="icon-hinhdanhmuc/nhom-dungcuhotro.png" alt="Dụng cụ hỗ trợ" class="category-icon">Dụng cụ hỗ trợ</div>
                                        <div class="brand-item" data-category="Thiết bị y tế khác"><img src="icon-hinhdanhmuc/nhom-thietbiyte.png" alt="Thiết bị y tế khác" class="category-icon">Thiết bị y tế khác</div>
                                    </div>
                                    <div class="category-main">
                                        <div class="category-detail" id="categoryDetail">
                                            <p>Di chuột vào các nhóm sản phẩm ở trên để xem chi tiết</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <a href="thong-tin.html">Giới thiệu</a>
                        <a href="#">Tin tức - Kiến thức</a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <?php if ($fromCache): ?>
    <!-- Cache Hit Indicator (chỉ hiển thị cho admin) -->
    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
    <div style="background: #d4edda; color: #155724; padding: 8px; text-align: center; font-size: 12px; position: fixed; top: 0; left: 0; right: 0; z-index: 9999;">
        ✅ Loaded from cache (age: <?php echo $cacheInfo['age_seconds']; ?>s / TTL: <?php echo $cacheInfo['ttl']; ?>s) | Response: <?php echo $result['response_time_ms']; ?>ms
    </div>
    <?php endif; ?>
    <?php endif; ?>
    
    <div class="product-detail-container">
        <div class="product-images">
              <img src="<?php echo htmlspecialchars($product['thumbnail'] ?? ($product['thumbnail_path'] ?? 'placeholder.jpg')); ?>" 
                  alt="<?php echo htmlspecialchars($product['name'] ?? 'Sản phẩm'); ?>" 
                 class="main-image">
        </div>
        
        <div class="product-info">
            <h1><?php echo htmlspecialchars($product['name'] ?? 'Sản phẩm'); ?></h1>
            
            <div class="product-meta">
                <span class="sku">SKU: <?php echo htmlspecialchars($product['sku'] ?? 'Đang cập nhật'); ?></span>
                <?php $stockQty = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0; ?>
                <span class="stock <?php echo $stockQty > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                    <?php echo $stockQty > 0 ? 'Còn hàng' : 'Hết hàng'; ?>
                </span>
            </div>
            
            <?php
                $price = isset($product['sale_price']) && is_numeric($product['sale_price']) ? (float)$product['sale_price'] : null;
                $orig  = isset($product['original_price']) && is_numeric($product['original_price']) ? (float)$product['original_price'] : null;
            ?>
            <div class="product-price">
                <?php if ($price === null && $orig === null): ?>
                    <span class="sale-price">Giá: Liên hệ</span>
                <?php elseif ($orig !== null && $price !== null && $orig > $price): ?>
                    <span class="original-price"><?php echo number_format($orig); ?>đ</span>
                    <span class="sale-price"><?php echo number_format($price); ?>đ</span>
                    <span class="discount">-<?php echo max(0, round((1 - ($price / max($orig, 1))) * 100)); ?>%</span>
                <?php else: ?>
                    <span class="sale-price"><?php echo number_format($price ?? $orig ?? 0); ?>đ</span>
                <?php endif; ?>
            </div>
            
            <div class="product-description">
                <?php echo nl2br(htmlspecialchars($product['description'] ?? 'Đang cập nhật thông tin sản phẩm.')); ?>
            </div>
            
            <div class="product-actions">
                <button class="btn-add-to-cart" onclick="addToCart(<?php echo $productId; ?>)">
                    🛒 Thêm vào giỏ hàng
                </button>
                <button class="btn-buy-now" onclick="buyNow(<?php echo $productId; ?>)">
                    Mua ngay
                </button>
            </div>
        </div>
    </div>

    <?php 
        $technicalSpecs = isset($product['technical_specs']) ? trim((string)$product['technical_specs']) : '';
    ?>
    <?php if ($technicalSpecs !== ''): ?>
    <section class="comment-section">
        <h2>📋 Thông số kỹ thuật</h2>
        <div style="background: #f9fafb; border-radius: 8px; padding: 16px; line-height: 1.8; white-space: pre-wrap; font-size: 15px; color: #374151;">
            <?php echo nl2br(htmlspecialchars($technicalSpecs)); ?>
        </div>
    </section>
    <?php endif; ?>

    <section class="comment-section">
        <h2>Đánh giá &amp; Bình luận</h2>
        <div class="comment-form">
            <h3>Viết đánh giá của bạn</h3>
            <div class="rating-input">
                <label>Đánh giá:</label>
                <div class="star-rating">
                    <input type="radio" id="star5" name="rating" value="5">
                    <label for="star5">★</label>
                    <input type="radio" id="star4" name="rating" value="4">
                    <label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3" checked>
                    <label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2">
                    <label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1">
                    <label for="star1">★</label>
                </div>
            </div>
            <textarea id="commentText" rows="4" placeholder="Nhập đánh giá của bạn về sản phẩm..." required></textarea>
            <button type="button" class="btn btn-primary" id="submitComment">Gửi đánh giá</button>
        </div>
        <div class="comment-list">
            <h3>Các đánh giá từ khách hàng</h3>
            <div id="commentList">
                <div class="comment-item">
                    <div class="comment-header">
                        <span>Nguyễn Văn A</span>
                        <span class="comment-rating">★★★★★</span>
                    </div>
                    <p>Giường chắc chắn, giao hàng nhanh.</p>
                    <small>2 ngày trước</small>
                </div>
                <div class="comment-item">
                    <div class="comment-header">
                        <span>Trần Thị B</span>
                        <span class="comment-rating">★★★★☆</span>
                    </div>
                    <p>Sản phẩm đúng mô tả, nhân viên tư vấn tốt.</p>
                    <small>1 tuần trước</small>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>Hỗ trợ khách hàng</h3>
                    <ul>
                        <li>Hotline: 0937 043 808</li>
                        <li>(8-21h kể cả T7, CN)</li>
                        <li>Điều khoản dịch vụ</li>
                        <li>Chính sách phục vụ</li>
                        <li>Chính sách đổi trả</li>
                        <li>Chính sách bảo mật</li>
                        <li>Chính sách vận chuyển</li>
                        <li>Chính sách thanh toán</li>
                        <li>Chính sách bảo hành</li>
                        <li>Hỗ trợ khách hàng: ducphuongmedical@gmail.com</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Bản tin sức khỏe</h3>
                    <ul>
                        <li>Tin tức về giường bệnh nhân</li>
                        <li>Hướng dẫn sử dụng giường bệnh</li>
                        <li>Hướng dẫn sử dụng xe lăn điện</li>
                        <li>Tin tức về máy tạo Oxy</li>
                        <li>Tin tức về Xe Lăn</li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Phương thức thanh toán</h3>
                    <div class="payment-methods">
                        <img src="hinh-thanhtoan/visa.png" alt="Visa">
                        <img src="hinh-thanhtoan/mastercard.png" alt="Mastercard">
                        <img src="hinh-thanhtoan/jcb.png" alt="JCB">
                        <img src="hinh-thanhtoan/momo.png" alt="MoMo">
                    </div>
                    <h4>Dịch vụ giao hàng</h4>
                    <div class="delivery-services">
                        <img src="hinh-anh/giao-hang.png" alt="Giao hàng">
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Kết nối với chúng tôi</h3>
                    <div class="social-links">
                        <a href="https://www.facebook.com/ducphuongnguyenphuoctay" target="_blank">
                            <img src="hinh-anh/fb-logo.png" alt="Facebook">
                        </a>
                        <a href="https://youtube.com/@ducphuongmedical" target="_blank">
                            <img src="hinh-anh/youtube-icon.png" alt="YouTube">
                        </a>
                        <a href="https://zalo.me/0938.062.808" target="_blank">
                            <img src="hinh-anh/icon-zalo.png" alt="Zalo">
                        </a>
                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=ducphuongmedical@gmail.com" target="_blank">
                            <img src="hinh-anh/icon-gmail.png" alt="Gmail">
                        </a>
                    </div>
                    <div class="certificates">
                        <h4>Chứng nhận bởi</h4>
                        <div class="cert-logos">
                            <img src="hinh-anh/icon-ddk.png" alt="Đã đăng ký">
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <div class="company-info">
                        <p>Địa chỉ: 12 Đông Hồ, Phường 08, Quận Tân Bình, TP.HCM</p>
                        <p>TaJerMy nhận đặt hàng trực tuyến và giao hàng tận nơi hoặc mua hàng trực tiếp tại cửa hàng</p>
                        <p>Giấy chứng nhận Đăng ký Kinh doanh số 0313717853 do Sở Kế hoạch và Đầu tư Thành phố Hồ Chí Minh cấp ngày 25/03/2016</p>
                        <p>&copy; 2022 - Bản quyền của TaJerMy - www.ducphuongmedical.com. Cấm sao chép dưới mọi hình thức nếu không có sự chấp thuận bằng văn bản.</p>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
    // Performance tracking
    if (window.performance) {
        const loadTime = performance.now();
        console.log('Page load time:', loadTime.toFixed(2), 'ms');
        console.log('From cache:', <?php echo $fromCache ? 'true' : 'false'; ?>);
    }
    
    function addToCart(productId) {
        // TODO: Implement add to cart
        alert('Thêm vào giỏ hàng: ' + productId);
    }
    
    function buyNow(productId) {
        // TODO: Implement buy now
        alert('Mua ngay: ' + productId);
    }

    // Danh mục + loại sản phẩm (dùng cho dropdown chi tiết)
    const categoryData = {
        "Giường": ["Giường bệnh cơ bản", "Giường chuyên dụng (ICU, sản khoa)", "Giường điều chỉnh độ cao/ tư thế (giường điện)", "Giường chăm sóc tại nhà"],
        "Xe lăn": ["Xe lăn tay (người dùng tự đẩy)", "Xe lăn đẩy sau (người chăm sóc đẩy)", "Xe lăn điện", "Xe lăn thể thao", "Xe lăn gấp gọn/ di chuyển", "Xe lăn lên xuống cầu thang"],
        "Xe Scooter điện": ["Loại di chuyển trong nhà/ đường bằng phẳng", "Loại địa hình (bánh lớn, đi đường dài)", "Loại gấp gọn/ tháo rời"],
        "Băng ca": ["Băng ca cấp cứu (cố định)", "Băng ca đẩy (có bánh xe)", "Băng ca gấp (vận chuyển)", "Băng ca chuyên dụng (chống sốc, chụp X-Quang)", "Đệm vận chuyển"],
        "Tủ": ["Tủ đựng thuốc", "Tủ đựng hồ sơ bệnh án", "Tủ trưng bày/ lưu trữ dụng cụ", "Tủ đầu giường bệnh"],
        "Máy tạo oxy": ["Máy tạo oxy tại nhà (loại lớn)", "Máy tạo oxy xách tay/ di động", "Máy tạo oxy dòng cao (cho trị liệu đặc biệt)"],
        "Máy đo": ["Máy đo huyết áp", "Máy đo đường huyết", "Máy đo nồng độ oxy trong máu (SpO2)", "Máy đo thân nhiệt", "Máy đo tim (ECG di động)", "Cân sức khỏe (cơ/ điện tử)"],
        "Máy xông": ["Máy xông khí dung kiểu nén", "Máy xông siêu âm", "Máy xông màng (mesh)"],
        "Máy hút dịch": ["Máy hút dịch để bàn/ cố định", "Máy hút dịch xách tay/ di động"],
        "Máy massage": ["Máy massage cầm tay (giảm đau cơ)", "Đai/ ghế massage toàn thân", "Máy massage chân (xoa bóp, lưu thông máu)", "Thiết bị massage trị liệu"],
        "Thiết bị tập": ["Thiết bị tập vận động thụ động (CPM)", "Thiết bị tập đi/ thăng bằng", "Thiết bị tập phục hồi chức năng tay/ chân", "Máy tập thể dục chuyên biệt", "Dụng cụ tập trị liệu"],
        "Nệm": ["Nệm chống loét (hơi, xốp, gel)", "Nệm bệnh viện tiêu chuẩn", "Nệm nâng đỡ cơ thể"],
        "Dụng cụ hỗ trợ": ["Hỗ trợ di chuyển", "Hỗ trợ vệ sinh", "Hỗ trợ tắm", "Hỗ trợ mặc quần áo", "Hỗ trợ ăn uống"],
        "Thiết bị y tế khác": ["Đèn khám bệnh", "Máy khử rung tim (AED)", "Bơm tiêm điện", "Hộp đựng dụng cụ vô trùng"]
    };
    window.categoryData = window.categoryData || categoryData;

    // Bình luận đơn giản (client-side)
    document.addEventListener('DOMContentLoaded', () => {
        const submitBtn = document.getElementById('submitComment');
        const textEl = document.getElementById('commentText');
        const listEl = document.getElementById('commentList');
        if (!submitBtn || !textEl || !listEl) return;
        submitBtn.addEventListener('click', () => {
            const content = (textEl.value || '').trim();
            const rating = document.querySelector('input[name="rating"]:checked')?.value || '3';
            if (!content) { alert('Vui lòng nhập nội dung đánh giá.'); return; }
            const stars = '★★★★★'.slice(0, Number(rating)).padEnd(5, '☆');
            const item = document.createElement('div');
            item.className = 'comment-item';
            item.innerHTML = `
                <div class="comment-header">
                    <span>Ẩn danh</span>
                    <span class="comment-rating">${stars}</span>
                </div>
                <p>${content}</p>
                <small>Vừa xong</small>
            `;
            listEl.prepend(item);
            textEl.value = '';
            alert('Cảm ơn bạn đã đánh giá sản phẩm!');
        });
    });
    
    // Header account menu logic (lightweight)
    let isLoggedIn = false;
    function handleAccountClick(event) {
        event.stopPropagation();
        const accountMenu = document.getElementById('accountMenu');
        if (!isLoggedIn) {
            if (typeof toggleLoginForm === 'function') { toggleLoginForm(); }
            else { window.location.href = 'index.html'; }
            return;
        }
        if (accountMenu) accountMenu.style.display = accountMenu.style.display === 'block' ? 'none' : 'block';
    }
    document.addEventListener('click', function(event) {
        const dd = document.querySelector('.account-dropdown');
        const menu = document.getElementById('accountMenu');
        if (dd && menu && !dd.contains(event.target)) { menu.style.display = 'none'; }
    });
    // Check session display name
    (function checkLoginStatus(){
        fetch('/check_session.php').then(r=>r.json()).then(data=>{
            const usernameDisplay = document.getElementById('usernameDisplay');
            isLoggedIn = !!data.logged_in;
            if (isLoggedIn && usernameDisplay) { usernameDisplay.innerText = data.username||''; usernameDisplay.style.display='block'; }
        }).catch(()=>{});
    })();

    // Category detail via click (no hover required)
    document.querySelectorAll('.submenu-content .brand-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const categoryName = this.getAttribute('data-category') || this.textContent.trim();
            const categoryDetail = document.getElementById('categoryDetail');
            if (!window.categoryData || !window.categoryData[categoryName] || !categoryDetail) return;
            const items = window.categoryData[categoryName];
            const itemsPerColumn = Math.ceil(items.length / 3) || 1;
            let columns = ['', '', ''];
            items.forEach((item, index) => {
                const columnIndex = Math.min(2, Math.floor(index / itemsPerColumn));
                columns[columnIndex] += `<li class="detail-item" data-group="${categoryName}" data-type="${item}">${item}</li>`;
            });
            categoryDetail.innerHTML = `
                <div class="detail-column">
                    <h4>${categoryName}</h4>
                    <ul>${columns[0]}</ul>
                </div>
                <div class="detail-column">
                    <ul>${columns[1]}</ul>
                </div>
                <div class="detail-column">
                    <ul>${columns[2]}</ul>
                </div>
            `;
        });
    });
    const detailEl = document.getElementById('categoryDetail');
    if (detailEl) {
        detailEl.addEventListener('click', function(e) {
            const li = e.target.closest('.detail-item');
            if (!li) return;
            const group = li.getAttribute('data-group') || '';
            const type = li.getAttribute('data-type') || '';
            const url = `san-pham.php?groups=${encodeURIComponent(group)}&types=${encodeURIComponent(type)}`;
            window.location.href = url;
        });
    }
    // Mobile dropdown minimal
    document.addEventListener('DOMContentLoaded', function(){
        const dropdown = document.querySelector('.dropdown');
        if (!dropdown) return;
        const btn = dropdown.querySelector('.dropdown-btn');
        const content = dropdown.querySelector('.dropdown-content');
        const originalHTML = content.innerHTML;
        const icons = { 'Giường':'icon-hinhdanhmuc/nhom-giuong.png','Xe lăn':'icon-hinhdanhmuc/nhom-xelan.png','Xe Scooter điện':'icon-hinhdanhmuc/nhom-xedien.png','Băng ca':'icon-hinhdanhmuc/nhom-bangca.png','Tủ':'icon-hinhdanhmuc/nhom-tu.png','Máy tạo oxy':'icon-hinhdanhmuc/nhom-maytaooxy.png','Máy đo':'icon-hinhdanhmuc/nhom-maydo.png','Máy xông':'icon-hinhdanhmuc/nhom-xong.png','Máy hút dịch':'icon-hinhdanhmuc/nhom-mayhutdich.png','Máy massage':'icon-hinhdanhmuc/nhom-massage.png','Thiết bị tập':'icon-hinhdanhmuc/nhom-maytap.png','Nệm':'icon-hinhdanhmuc/nhom-nem.png','Dụng cụ hỗ trợ':'icon-hinhdanhmuc/nhom-dungcuhotro.png','Thiết bị y tế khác':'icon-hinhdanhmuc/nhom-thietbiyte.png' };
        function buildCats(){
            let grid = '<div class="mobile-category-grid">';
            Object.keys(window.categoryData||{}).forEach(name=>{
                grid += `<div class="mobile-category-item" data-category="${name}"><img src="${icons[name]||''}" class="mobile-category-icon"><span>${name}</span></div>`;
            });
            grid += '</div>';
            return `<div class="mobile-menu-header"><button class="mobile-menu-back-btn">‹ Quay lại</button><h3>Danh mục sản phẩm</h3></div>${grid}`;
        }
        btn.addEventListener('click', function(e){ if (window.innerWidth<=768){ e.preventDefault(); e.stopPropagation(); dropdown.classList.toggle('mobile-menu-active'); }});
        content.addEventListener('click', function(e){ if (window.innerWidth>768) return; const t=e.target; if (t.matches('.has-submenu')){ e.preventDefault(); content.innerHTML = buildCats(); } if (t.matches('.mobile-menu-back-btn')){ content.innerHTML = originalHTML; } const item=t.closest('.mobile-category-item'); if (item){ const name=item.getAttribute('data-category'); dropdown.classList.remove('mobile-menu-active'); window.location.href = `san-pham.php?groups=${encodeURIComponent(name)}`; }});
        document.addEventListener('click', function(e){ if (window.innerWidth<=768 && !dropdown.contains(e.target)){ dropdown.classList.remove('mobile-menu-active'); }});
        window.addEventListener('resize', function(){ dropdown.classList.remove('mobile-menu-active'); content.innerHTML = originalHTML; if (window.innerWidth>768){ content.style.display=''; }});
    });
    </script>

    <!-- Profile Modal System -->
    <div id="profileModal" class="profile-modal" style="display: none;">
        <div class="profile-modal-content">
            <span class="profile-close" onclick="closeProfileModal()">&times;</span>
            <h2>Thông tin tài khoản</h2>
            <div class="profile-avatar-section">
                <img id="profileAvatar" src="hinh-anh/hinh-tk-macdinh.png" alt="Avatar" class="profile-avatar-img">
                <input type="file" id="avatarInput" accept="image/*" style="display: none;" onchange="handleAvatarUpload(event)">
                <button class="profile-btn-secondary" onclick="document.getElementById('avatarInput').click()">Chỉnh sửa avatar</button>
            </div>
            <div class="profile-info-section">
                <div class="profile-field"><label>Tên người dùng:</label><input type="text" id="profileUsername" class="profile-input" readonly><button class="profile-btn-edit" onclick="enableEdit('profileUsername')">✏️</button></div>
                <div class="profile-field"><label>Số điện thoại:</label><input type="text" id="profilePhone" class="profile-input" readonly><button class="profile-btn-change" onclick="openChangePhoneModal()">Thay đổi</button></div>
                <div class="profile-field"><label>Email:</label><input type="email" id="profileEmail" class="profile-input" readonly><button class="profile-btn-change" onclick="openChangeEmailModal()">Thay đổi</button></div>
                <div class="profile-field"><label>Mật khẩu:</label><div style="position: relative; flex: 1;"><input type="password" id="profilePassword" class="profile-input" value="••••••••" readonly style="width: 100%;"><span onclick="togglePasswordVisibility()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; user-select: none; font-size: 18px;">👁️</span></div><a href="#" class="profile-link-change" onclick="openChangePasswordModal(); return false;">Thay đổi mật khẩu</a></div>
                <div class="profile-field"><label>Địa chỉ:</label><textarea id="profileAddress" class="profile-textarea" rows="3"></textarea></div>
                <button class="profile-btn-primary" onclick="saveProfileChanges()">Lưu thay đổi</button>
            </div>
        </div>
    </div>
    <div id="changePasswordModal" class="profile-modal" style="display: none;">
        <div class="profile-modal-content profile-modal-small">
            <span class="profile-close" onclick="closeChangePasswordModal()">&times;</span>
            <h3>Thay đổi mật khẩu</h3>
            <div class="profile-field"><label>Mật khẩu cũ:</label><input type="password" id="oldPassword" class="profile-input"></div>
            <div class="profile-field"><label>Mật khẩu mới:</label><input type="password" id="newPassword" class="profile-input"></div>
            <div class="profile-field"><label>Mã xác nhận:</label><div style="display: flex; gap: 10px;"><input type="text" id="passwordVerifyCode" class="profile-input" style="flex: 1;"><button class="profile-btn-secondary" onclick="sendPasswordVerificationCode()">Gửi mã</button></div><small style="color: #666;">Mã xác nhận sẽ được gửi đến email của bạn</small></div>
            <button class="profile-btn-primary" onclick="updatePassword()">Cập nhật mật khẩu</button>
        </div>
    </div>
    <div id="changePhoneModal" class="profile-modal" style="display: none;">
        <div class="profile-modal-content profile-modal-small">
            <span class="profile-close" onclick="closeChangePhoneModal()">&times;</span>
            <h3>Thay đổi số điện thoại</h3>
            <div class="profile-field"><label>Số điện thoại mới:</label><input type="text" id="newPhone" class="profile-input"></div>
            <div class="profile-field"><label>Mã xác nhận:</label><div style="display: flex; gap: 10px;"><input type="text" id="phoneVerifyCode" class="profile-input" style="flex: 1;"><button class="profile-btn-secondary" onclick="sendPhoneVerificationCode()">Gửi mã</button></div><small style="color: #666;">Mã xác nhận sẽ được gửi đến SĐT mới</small></div>
            <button class="profile-btn-primary" onclick="updatePhone()">Cập nhật SĐT</button>
        </div>
    </div>
    <div id="changeEmailModal" class="profile-modal" style="display: none;">
        <div class="profile-modal-content profile-modal-small">
            <span class="profile-close" onclick="closeChangeEmailModal()">&times;</span>
            <h3>Thay đổi Email</h3>
            <div class="profile-field"><label>Email mới:</label><input type="email" id="newEmail" class="profile-input"></div>
            <div class="profile-field"><label>Mã xác nhận:</label><div style="display: flex; gap: 10px;"><input type="text" id="emailVerifyCode" class="profile-input" style="flex: 1;"><button class="profile-btn-secondary" onclick="sendEmailVerificationCode()">Gửi mã</button></div><small style="color: #666;">Mã xác nhận sẽ được gửi đến email mới</small></div>
            <button class="profile-btn-primary" onclick="updateEmail()">Cập nhật Email</button>
        </div>
    </div>
    <script src="profile-modal.js"></script>
</body>
</html>
