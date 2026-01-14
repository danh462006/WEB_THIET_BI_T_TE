<?php
require_once '../session-config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/data/news.json';

try {
    if (!file_exists($dataFile)) {
        echo json_encode(['success' => true, 'items' => []]);
        exit;
    }

    $raw = file_get_contents($dataFile);
    $items = json_decode($raw, true);
    if (!is_array($items)) {
        $items = [];
    }

    // Sort newest first
    usort($items, function ($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });

    echo json_encode([
        'success' => true,
        'items' => $items
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Không đọc được danh sách tin tức'
    ]);
}
