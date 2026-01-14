<?php
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'None');
} else {
    ini_set('session.cookie_samesite', 'Lax');
}
session_start();
header('Content-Type: application/json; charset=utf-8');

if (isset($_SESSION['user_id'])) {
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
    $position = isset($_SESSION['position']) ? $_SESSION['position'] : '';

    echo json_encode([
        'logged_in' => true,
        'username' => $username,
        'position' => $position
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} else {
    echo json_encode(['logged_in' => false]);
}
?>
