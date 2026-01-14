<?php
// Simple upload endpoint for chat attachments
header('Content-Type: application/json');
$targetRoot = __DIR__ . DIRECTORY_SEPARATOR . 'hinh-chat';
if (!is_dir($targetRoot)) { @mkdir($targetRoot, 0777, true); }

if (!isset($_FILES['file'])) {
    echo json_encode(['status'=>'error','message'=>'No file']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status'=>'error','message'=>'Upload error']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed = ['png','jpg','jpeg','gif','webp','pdf','doc','docx'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['status'=>'error','message'=>'File type not allowed']);
    exit;
}

$basename = uniqid('chat_', true) . '.' . $ext;
$targetPath = $targetRoot . DIRECTORY_SEPARATOR . $basename;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['status'=>'error','message'=>'Save failed']);
    exit;
}

$publicPath = 'hinh-chat/' . $basename;
// Optionally insert a message record here, but we do it in messages.php send

echo json_encode(['status'=>'success','path'=>$publicPath]);
