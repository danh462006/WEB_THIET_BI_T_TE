<?php
ini_set('session.cookie_httponly', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
    ini_set('session.cookie_samesite', 'None');
} else {
    ini_set('session.cookie_samesite', 'Lax');
}
session_start();
session_destroy();
header('Content-Type: application/json');
echo json_encode(['status' => 'success']);
?>