<?php
require_once '../session-config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

// Only admin/quản trị viên can add
$position = strtolower((string)($_SESSION['position'] ?? ''));
if (!in_array($position, ['quan-tri-vien', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Bạn không có quyền thêm tin tức']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$youtubeUrl = trim($_POST['youtube_url'] ?? '');
$note = trim($_POST['note'] ?? '');

if ($youtubeUrl === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Vui lòng nhập link YouTube']);
    exit;
}

function extractYouTubeId(string $url): ?string {
    $url = trim($url);
    // Direct ID
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $url)) {
        return $url;
    }

    $parts = parse_url($url);
    if (!$parts || !isset($parts['host'])) {
        return null;
    }

    // youtu.be short link
    if (strpos($parts['host'], 'youtu.be') !== false && isset($parts['path'])) {
        $id = ltrim($parts['path'], '/');
        return preg_match('/^[a-zA-Z0-9_-]{11}$/', $id) ? $id : null;
    }

    // youtube.com watch?v=
    if (isset($parts['query'])) {
        parse_str($parts['query'], $query);
        if (!empty($query['v']) && preg_match('/^[a-zA-Z0-9_-]{11}$/', $query['v'])) {
            return $query['v'];
        }
    }

    // /embed/ID or /v/ID
    if (isset($parts['path'])) {
        if (preg_match('#/(embed|v)/([a-zA-Z0-9_-]{11})#', $parts['path'], $m)) {
            return $m[2];
        }
    }

    return null;
}

$videoId = extractYouTubeId($youtubeUrl);
if (!$videoId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Link YouTube không hợp lệ']);
    exit;
}

if ($title === '') {
    $title = 'Video kiến thức';
}

$dataDir = __DIR__ . '/data';
$dataFile = $dataDir . '/news.json';
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0777, true);
}

$items = [];
if (file_exists($dataFile)) {
    $raw = file_get_contents($dataFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $items = $decoded;
    }
}

$entry = [
    'id' => uniqid('news_'),
    'title' => $title,
    'youtube_url' => $youtubeUrl,
    'video_id' => $videoId,
    'thumbnail' => 'https://img.youtube.com/vi/' . $videoId . '/hqdefault.jpg',
    'note' => $note,
    'created_at' => date('c'),
    'created_by' => $_SESSION['username'] ?? ($_SESSION['user_id'] ?? 'admin')
];

array_unshift($items, $entry);

$saved = file_put_contents($dataFile, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
if ($saved === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Không ghi được file tin tức']);
    exit;
}

echo json_encode([
    'success' => true,
    'item' => $entry
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
