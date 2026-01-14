# HƯỚNG DẪN UPLOAD LÊN HOSTING

## ✅ ĐÃ SỬA XONG

Đã sửa **TẤT CẢ** các đường dẫn API từ tương đối sang tuyệt đối:
- ❌ Cũ: `fetch('api/admin_product_list.php')`
- ✅ Mới: `fetch('/api/admin_product_list.php')`

Điều này giúp code hoạt động tốt trên cả Windows (local) và Linux (hosting).

---

## 📋 CHECKLIST KHI UPLOAD LÊN HOSTING

### 1️⃣ **Cập nhật file `config.php`**

Mở file `config.php` và thay đổi thông tin database theo hosting của bạn:

```php
<?php
// Thông tin database từ hosting
$servername = "localhost";        // Có thể là "localhost" hoặc host MySQL của bạn
$username   = "reslan";            // Tên user database
$password   = "nguyendanh0399352950"; // Mật khẩu database
$dbname     = "ducphuong";         // Tên database (thay theo hosting)
$port       = 3306;                 // Thường là 3306
```

### 2️⃣ **Import database**

1. Export database từ phpMyAdmin local (database `ducphuong`)
2. Login vào phpMyAdmin trên hosting
3. Tạo database mới (hoặc dùng database có sẵn)
4. Import file SQL vừa export

### 3️⃣ **Upload files lên hosting**

Upload TẤT CẢ các file và folder lên hosting qua:
- FTP (FileZilla)
- hoặc File Manager của hosting

**Cấu trúc cần giữ nguyên:**
```
/
├── api/
├── cron/
├── hinh-anh/
├── hinh-du-an/
├── tools/
├── index.html
├── config.php
├── san-pham.html
├── quan-tri-vien-sanpham.html
└── ... (tất cả các file khác)
```

### 4️⃣ **Kiểm tra quyền folder**

Các folder chứa ảnh cần quyền ghi (chmod 755 hoặc 777):
- `hinh-anh/`
- `hinh-du-an/`
- `hinh-chung-nhan/`
- `hinh-mau/`
- `hinh-thanhtoan/`
- `hinh-thongtin/`
- `hinh-trien-lam/`
- `hinh-xuong/`
- `icon-hinhdanhmuc/`

### 5️⃣ **Kiểm tra .htaccess (nếu cần)**

Nếu muốn URL đẹp kiểu `/san-pham/ten-san-pham-123`, tạo file `.htaccess`:

```apache
RewriteEngine On

# Rewrite product detail URLs
RewriteRule ^san-pham/([a-zA-Z0-9-]+)-(\d+)$ san-pham.php?slug=$1&id=$2 [L,QSA]

# Disable directory listing
Options -Indexes
```

---

## 🔍 CÁCH KIỂM TRA LỖI

### Nếu không hiện sản phẩm:

1. **Mở Developer Tools** (F12)
2. Vào tab **Console** - xem có lỗi JavaScript không
3. Vào tab **Network**:
   - Reload trang
  - Tìm request tới `./api/admin_product_list.php` (hoặc `/api/admin_product_list.php` nếu site ở domain root)
   - Xem status code:
     - ✅ **200**: OK
     - ❌ **404**: File không tìm thấy (kiểm tra đường dẫn)
     - ❌ **500**: Lỗi server (kiểm tra config.php)
   - Click vào request để xem Response

### Các lỗi thường gặp:

#### ❌ Lỗi: "Kết nối database thất bại"
→ Sửa file `config.php` với thông tin database đúng

#### ❌ Lỗi: API trả về 0 sản phẩm
→ Kiểm tra đã import database chưa

#### ❌ Lỗi: 404 Not Found
→ Kiểm tra tên file và folder có đúng không (phân biệt hoa thường)

#### ❌ Lỗi: CORS / Permission denied
→ Kiểm tra domain có đúng không

---

## 🧪 TEST SAU KHI UPLOAD

1. Truy cập `https://domain-cua-ban.com/san-pham.html`
2. Kiểm tra có hiện danh sách sản phẩm không
3. Click vào 1 sản phẩm xem chi tiết
4. Truy cập trang admin `https://domain-cua-ban.com/quan-tri-vien-sanpham.html`
5. Thử tạo/sửa/xóa sản phẩm

---

## 📞 HỖ TRỢ

Nếu vẫn còn lỗi:
1. Mở Console (F12)
2. Chụp màn hình lỗi
3. Kiểm tra file `config.php` đã đúng chưa
4. Kiểm tra database đã import đầy đủ chưa
