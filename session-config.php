<?php
// Session configuration - MUST be called BEFORE session_start()
ini_set('session.cookie_httponly', 1);

// Kiểm tra HTTPS để set cookie đúng cách
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    // HTTPS: Cần SameSite=None và Secure=true để cookie hoạt động
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'None');
} else {
    // HTTP (localhost): Dùng Lax
    ini_set('session.cookie_samesite', 'Lax');
}
?>
