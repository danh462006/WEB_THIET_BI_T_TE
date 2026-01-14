# Hệ Thống Phân Quyền và Chuyển Hướng

## Tổng Quan
Hệ thống phân quyền tự động chuyển hướng user dựa trên `position` trong database.

## Cách Hoạt Động

### 1. Đăng Nhập
- User đăng nhập qua `login.php`
- System kiểm tra `Position` trong database
- Lưu position vào:
  - PHP Session (`$_SESSION['position']`)
  - LocalStorage (`userPosition`)

### 2. Phân Quyền Tự Động

#### Quản Trị Viên (position = "quản-trị-viên")
- Menu "Trang chủ" → `quan-tri-vien-index.html`
- Menu "Giới thiệu" → `quan-tri-vien-thongtin.html`
- Header màu tím (#667eea → #764ba2)
- Badge "ADMIN" hiển thị trên account button
- Có thể chỉnh sửa nội dung trang

#### Khách Hàng (position khác hoặc chưa đăng nhập)
- Menu "Trang chủ" → `index.html`
- Menu "Giới thiệu" → `thong-tin.html`
- Header màu xanh lá (#63e94b → #3fe009)
- Không có badge
- Chỉ xem, không chỉnh sửa

### 3. Bảo Mật Truy Cập

#### Auto-Redirect
- **Admin truy cập `index.html`** → Tự động chuyển đến `quan-tri-vien-index.html`
- **Admin truy cập `thong-tin.html`** → Tự động chuyển đến `quan-tri-vien-thongtin.html`
- **Khách truy cập trang admin** → Chuyển về trang thường + thông báo lỗi

#### Deep Linking Protection
- System kiểm tra quyền khi tải trang
- Chặn truy cập trực tiếp vào URL admin nếu không có quyền

### 4. Đăng Xuất
- Xóa position khỏi localStorage
- Xóa session trên server
- Menu reset về phiên bản khách hàng
- Redirect về:
  - `index.html` (từ quan-tri-vien-index.html)
  - `thong-tin.html` (từ quan-tri-vien-thongtin.html)

## Files Liên Quan

### Backend (PHP)
- `login.php` - Xử lý login, trả về position
- `check_session.php` - Kiểm tra session, trả về position
- `logout.php` - Xóa session

### Frontend (JavaScript)
- `auth-redirect.js` - Core logic phân quyền và chuyển hướng
  - `saveUserPosition()` - Lưu position
  - `clearUserPosition()` - Xóa position
  - `checkUserPosition()` - Cập nhật menu
  - `checkAdminAccess()` - Kiểm tra quyền trang admin
  - `autoRedirectAdmin()` - Auto-redirect admin

### HTML Files
- `quan-tri-vien-index.html` - Admin dashboard trang chủ
- `quan-tri-vien-thongtin.html` - Admin dashboard thông tin
- `index.html` - Trang chủ khách hàng
- `thong-tin.html` - Trang thông tin khách hàng

## Visual Indicators

### Admin Mode
```
- Header: Gradient tím (#667eea → #764ba2)
- Badge: "ADMIN" (đỏ #ff6b6b)
- Position: top-right của account button
```

### Customer Mode
```
- Header: Gradient xanh (#63e94b → #3fe009)
- No badge
```

## Flow Chart

```
┌─────────────┐
│   Login     │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│ Check Position      │
│ in Database         │
└──────┬──────────────┘
       │
       ├─────────────────────┐
       │                     │
       ▼                     ▼
┌──────────────┐      ┌─────────────┐
│ Admin        │      │ Customer    │
│ position =   │      │ position ≠  │
│ quản-trị-viên│      │ quản-trị-viên│
└──────┬───────┘      └──────┬──────┘
       │                     │
       ▼                     ▼
┌──────────────┐      ┌─────────────┐
│ Save to      │      │ Save to     │
│ localStorage │      │ localStorage│
└──────┬───────┘      └──────┬──────┘
       │                     │
       ▼                     ▼
┌──────────────┐      ┌─────────────┐
│ Redirect to  │      │ Redirect to │
│ quan-tri-vien│      │ index.html  │
│ -index.html  │      │             │
└──────────────┘      └─────────────┘
```

## Testing Scenarios

### Test Case 1: Admin Login
1. Login với position = "quản-trị-viên"
2. Kiểm tra redirect → `quan-tri-vien-index.html`
3. Kiểm tra header màu tím
4. Kiểm tra badge "ADMIN"
5. Click menu "Giới thiệu" → `quan-tri-vien-thongtin.html`

### Test Case 2: Customer Login
1. Login với position khác
2. Kiểm tra redirect → `index.html`
3. Kiểm tra header màu xanh
4. Không có badge
5. Click menu "Giới thiệu" → `thong-tin.html`

### Test Case 3: Deep Link Protection
1. Login as customer
2. Manually navigate to `quan-tri-vien-index.html`
3. Kiểm tra: Auto-redirect to `index.html`
4. Kiểm tra: Alert "Bạn không có quyền..."

### Test Case 4: Admin Deep Link
1. Login as admin
2. Manually navigate to `index.html`
3. Kiểm tra: Auto-redirect to `quan-tri-vien-index.html`

### Test Case 5: Logout
1. Login as admin
2. Logout
3. Kiểm tra: Redirect to `index.html`
4. Kiểm tra: localStorage cleared
5. Kiểm tra: Menu reset to customer links

## Troubleshooting

### Issue: Menu không đổi sau khi login
**Solution:** Check `auth-redirect.js` đã được load chưa

### Issue: Bị redirect loop
**Solution:** Xóa localStorage: `localStorage.clear()`

### Issue: Badge không hiện
**Solution:** Kiểm tra `saveUserPosition()` được gọi sau login

### Issue: Admin vẫn thấy trang customer
**Solution:** 
1. Check position in database = "quản-trị-viên" (chính xác)
2. Check localStorage có `userPosition` không
3. Check console.log trong `auth-redirect.js`
