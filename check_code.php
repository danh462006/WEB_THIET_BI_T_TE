<?php
session_start();
header('Content-Type: application/json');

if (isset($_POST['code'])) {
    $code = trim($_POST['code']);
    if (isset($_SESSION['verification_code']) && $_SESSION['verification_code'] === $code) {
        echo json_encode(['status' => 'success', 'message' => 'Mã xác nhận chính xác']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sai mã xác nhận']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Vui lòng nhập mã xác nhận']);
}
?>