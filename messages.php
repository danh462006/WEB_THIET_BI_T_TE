<?php
session_start();

$servername = "localhost";
$username = "reslan";
$password = "nguyendanh0399352950";
$dbname = "ducphuong";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'DB connect error']);
    exit;
}

// Ensure table exists
// Create base table (legacy columns kept; new two-way columns added/migrated below)
$conn->query("CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `username` VARCHAR(100) NULL,
    `position` VARCHAR(50) NOT NULL,
    `from_page` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `is_read` TINYINT(1) DEFAULT 0,
    `archived` TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Auto-migrate to two-way chat schema
$columns = [];
$resCols = $conn->query("SHOW COLUMNS FROM messages");
if ($resCols) {
        while ($c = $resCols->fetch_assoc()) { $columns[] = $c['Field']; }
}
function addColIfMissing($conn, $columns, $name, $def) {
        if (!in_array($name, $columns)) {
                $conn->query("ALTER TABLE messages ADD COLUMN $name $def");
        }
}
addColIfMissing($conn, $columns, 'sender_id', 'INT NULL');
addColIfMissing($conn, $columns, 'receiver_id', 'INT NULL');
addColIfMissing($conn, $columns, 'sender_position', "VARCHAR(50) NULL");
addColIfMissing($conn, $columns, 'status', "ENUM('sent','delivered','read') DEFAULT 'sent'");
addColIfMissing($conn, $columns, 'is_recalled', 'TINYINT(1) DEFAULT 0');
addColIfMissing($conn, $columns, 'message_type', "ENUM('text','image','file','emoji','system','recall') DEFAULT 'text'");
addColIfMissing($conn, $columns, 'attachment_path', 'VARCHAR(255) NULL');

// Legacy data backfill: where new columns are NULL, infer from legacy columns
$conn->query("UPDATE messages SET sender_id = user_id WHERE sender_id IS NULL");
$conn->query("UPDATE messages SET sender_position = CASE WHEN position IS NOT NULL THEN position ELSE 'khach-hang' END WHERE sender_position IS NULL");
// Assume admin has ID 0 for receiver in legacy customer-to-admin messages
$conn->query("UPDATE messages SET receiver_id = 0 WHERE receiver_id IS NULL AND position = 'khach-hang'");

$method = $_SERVER['REQUEST_METHOD'];
$action = '';
$params = [];

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $params = json_decode($raw, true) ?: [];
    $action = $params['action'] ?? '';
} else {
    $action = $_GET['action'] ?? '';
}

function json_out($arr) {
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

// Route legacy 'add' to new 'send' toward admin (receiver_id = 0)
if ($action === 'add') { $params['receiver_id'] = 0; $action = 'send'; }

// Two-way send message
if ($action === 'send') {
    $content = trim($params['content'] ?? '');
    $receiver_id = isset($params['receiver_id']) ? (int)$params['receiver_id'] : null;
    $message_type = $params['message_type'] ?? 'text';
    $attachment_path = $params['attachment_path'] ?? null;
    $from_page = $params['from_page'] ?? '';
    if (($content === '' && !$attachment_path) || $receiver_id === null) {
        json_out(['status' => 'error', 'message' => 'Thiếu nội dung/tệp hoặc người nhận']);
    }

    // Determine sender
    $sender_position = 'khach-hang';
    $usernameSession = $_SESSION['username'] ?? null;
    // If sending to admin (receiver_id = 0) → sender is customer/guest; else sender is admin
    if ($receiver_id !== 0) {
        // Admin sending to customer
        $sender_id = 0;
        $sender_position = 'quan-tri-vien';
        if (!$usernameSession) { $usernameSession = 'Admin'; }
    } else {
        // Customer/guest sending to admin
        $sender_id = $_SESSION['user_id'] ?? null;
        if (!$sender_id) {
            if (!isset($_SESSION['guest_id'])) { $_SESSION['guest_id'] = rand(100000, 999999); }
            $usernameSession = $usernameSession ?: ('Khach-' . $_SESSION['guest_id']);
            $sender_position = 'khach-hang';
            $sender_id = -1 * (int)$_SESSION['guest_id'];
        } else {
            $stmt = $conn->prepare("SELECT username, Position FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $sender_id);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $usernameSession = $row['username'] ?: $usernameSession;
                    $sender_position = ($row['Position'] ?? $sender_position) ?: $sender_position;
                }
                $stmt->close();
            }
        }
    }

    $stmt = $conn->prepare("INSERT INTO messages (sender_id, receiver_id, sender_position, user_id, username, position, from_page, content, message_type, attachment_path)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    // user_id/username/position retained for legacy compatibility (mirror sender); use 0 for admin
    $legacy_user_id = ($sender_position === 'khach-hang') ? (int)$sender_id : 0;
    $legacy_position = ($sender_position === 'khach-hang') ? 'khach-hang' : 'quan-tri-vien';
    // Correct bind types: i i s i s s s s s s
    $stmt->bind_param('iisissssss', $sender_id, $receiver_id, $sender_position, $legacy_user_id, $usernameSession, $legacy_position, $from_page, $content, $message_type, $attachment_path);
    $ok = $stmt->execute();
    if ($ok) {
        json_out(['status' => 'success', 'id' => $stmt->insert_id]);
    } else {
        json_out(['status' => 'error', 'message' => 'Không thể lưu tin nhắn']);
    }
}

if ($action === 'list') {
    // Filters
    $positionFilter = $params['position'] ?? ($_GET['position'] ?? 'khach-hang');
    $fromPageFilter = $params['from_page'] ?? ($_GET['from_page'] ?? 'index.html');
    $archived = isset($params['archived']) ? (int)$params['archived'] : (isset($_GET['archived']) ? (int)$_GET['archived'] : 0);
    $limit = isset($params['limit']) ? (int)$params['limit'] : (isset($_GET['limit']) ? (int)$_GET['limit'] : 200);

    $stmt = $conn->prepare("SELECT id, user_id, username, position, from_page, content, created_at, is_read, archived
                             FROM messages
                             WHERE position = ? AND from_page = ? AND archived = ?
                             ORDER BY created_at DESC
                             LIMIT ?");
    $stmt->bind_param('ssii', $positionFilter, $fromPageFilter, $archived, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    json_out(['status' => 'success', 'messages' => $rows]);
}

if ($action === 'unread_count') {
    // Admin perspective: unread messages sent to admin (receiver_id = 0)
    $res = $conn->query("SELECT COUNT(*) AS c FROM messages WHERE receiver_id = 0 AND archived = 0 AND is_read = 0");
    $row = $res ? $res->fetch_assoc() : ['c' => 0];
    json_out(['status' => 'success', 'count' => (int)$row['c']]);
}

if ($action === 'mark_read' || $action === 'archive' || $action === 'delete') {
    $ids = $params['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) {
        json_out(['status' => 'error', 'message' => 'Không có ID']);
    }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    if ($action === 'mark_read') {
        $sql = "UPDATE messages SET is_read = 1 WHERE id IN ($placeholders)";
    } elseif ($action === 'archive') {
        $sql = "UPDATE messages SET archived = 1 WHERE id IN ($placeholders)";
    } else {
        $sql = "DELETE FROM messages WHERE id IN ($placeholders)";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $ok = $stmt->execute();
    json_out(['status' => $ok ? 'success' : 'error']);
}

if ($action === 'export') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cskh_messages.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Thời gian', 'ID khách hàng', 'Tên', 'Nội dung']);

    $res = $conn->query("SELECT created_at, user_id, username, content FROM messages WHERE sender_position='khach-hang' ORDER BY created_at DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($out, [$row['created_at'], $row['user_id'] ?: '', $row['username'] ?: '', $row['content']]);
        }
    }
    fclose($out);
    exit;
}

// New endpoints for two-way chat
if ($action === 'list_conversations') {
    // Admin perspective: group by customer (sender or receiver as customer)
    // Latest message and unread count per customer
    $sql = "SELECT c.peer_id, c.peer_username, c.peer_avatar, c.last_time, c.last_content, c.last_type,
                   COALESCE(uc.unread_count, 0) AS unread_count
            FROM (
                SELECT 
                    CASE 
                        WHEN m.sender_position = 'khach-hang' THEN m.sender_id 
                        ELSE m.receiver_id 
                    END AS peer_id,
                    MAX(m.created_at) AS last_time
                FROM messages m
                WHERE (m.sender_position = 'khach-hang' OR m.receiver_id IS NOT NULL)
                GROUP BY peer_id
            ) t
            JOIN (
                SELECT m2.*
                FROM messages m2
                JOIN (
                    SELECT 
                        CASE 
                            WHEN m3.sender_position = 'khach-hang' THEN m3.sender_id 
                            ELSE m3.receiver_id 
                        END AS peer_id,
                        MAX(m3.created_at) AS last_time
                    FROM messages m3
                    GROUP BY peer_id
                ) lm ON (
                    ((m2.sender_position = 'khach-hang' AND m2.sender_id = lm.peer_id) OR (m2.receiver_id = lm.peer_id))
                    AND m2.created_at = lm.last_time
                )
            ) last ON 1=1
            JOIN (
                SELECT 
                    CASE WHEN u.id IS NULL THEN NULL ELSE u.id END AS uid,
                    CASE WHEN u.username IS NULL THEN NULL ELSE u.username END AS uname,
                    CASE WHEN u.Avatar IS NULL THEN NULL ELSE u.Avatar END AS avatar
                FROM users u
            ) u ON 1=1
            LEFT JOIN (
                SELECT sender_id AS peer_id, COUNT(*) AS unread_count
                FROM messages
                WHERE receiver_id = 0 AND is_read = 0 AND archived = 0
                GROUP BY sender_id
            ) uc ON uc.peer_id = (CASE WHEN last.sender_position = 'khach-hang' THEN last.sender_id ELSE last.receiver_id END)
            CROSS JOIN (
                SELECT 
                    CASE 
                        WHEN last.sender_position = 'khach-hang' THEN last.sender_id
                        ELSE last.receiver_id
                    END AS peer_id,
                    last.username AS peer_username,
                    last.content AS last_content,
                    last.message_type AS last_type,
                    last.created_at AS last_time
                FROM messages last
                WHERE 1=0 -- will be overridden by above joins
            ) dummy -- trick to shape select
            ";
    // The above complex SQL may not run on all MySQL versions; use simpler approach in PHP
    $rows = [];
    $res = $conn->query("SELECT id, sender_id, receiver_id, sender_position, username, content, message_type, created_at FROM messages ORDER BY created_at DESC");
    $map = [];
    if ($res) {
        while ($m = $res->fetch_assoc()) {
            $peer_id = ($m['sender_position'] === 'khach-hang') ? (int)$m['sender_id'] : (int)$m['receiver_id'];
            if (!$peer_id) continue;
            if (!isset($map[$peer_id])) {
                $map[$peer_id] = [
                    'peer_id' => $peer_id,
                    'peer_username' => $m['username'] ?: ('ID:' . $peer_id),
                    'peer_avatar' => null,
                    'last_content' => $m['content'],
                    'last_type' => $m['message_type'],
                    'last_time' => $m['created_at'],
                    'unread_count' => 0
                ];
            }
        }
        // Fill unread_count
        $res2 = $conn->query("SELECT sender_id, COUNT(*) AS c FROM messages WHERE receiver_id = 0 AND is_read = 0 AND archived = 0 GROUP BY sender_id");
        if ($res2) {
            while ($r = $res2->fetch_assoc()) {
                $sid = (int)$r['sender_id'];
                if (isset($map[$sid])) { $map[$sid]['unread_count'] = (int)$r['c']; }
            }
        }
        // Fill avatar from users table if exists
        $ids = array_keys($map);
        if (!empty($ids)) {
            $place = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $stmt = $conn->prepare("SELECT id, username, Avatar FROM users WHERE id IN ($place)");
            if ($stmt) {
                $stmt->bind_param($types, ...$ids);
                $stmt->execute();
                $resU = $stmt->get_result();
                while ($u = $resU->fetch_assoc()) {
                    $pid = (int)$u['id'];
                    if (isset($map[$pid])) {
                        $map[$pid]['peer_username'] = $u['username'] ?: $map[$pid]['peer_username'];
                        $map[$pid]['peer_avatar'] = $u['Avatar'] ?: null;
                    }
                }
                $stmt->close();
            }
        }
    }
    $rows = array_values($map);
    json_out(['status' => 'success', 'conversations' => $rows]);
}

if ($action === 'list_messages') {
    $peer_id = isset($params['peer_id']) ? (int)$params['peer_id'] : (isset($_GET['peer_id']) ? (int)$_GET['peer_id'] : null);
    $limit = isset($params['limit']) ? (int)$params['limit'] : 200;
    if ($peer_id === null) { json_out(['status' => 'error', 'message' => 'Thiếu peer_id']); }
    $stmt = $conn->prepare("SELECT id, sender_id, receiver_id, sender_position, content, message_type, attachment_path, created_at, status, is_recalled
                             FROM messages
                             WHERE (sender_id = ? AND receiver_id = 0) OR (sender_id = 0 AND receiver_id = ?)
                             ORDER BY created_at ASC
                             LIMIT ?");
    $stmt->bind_param('iii', $peer_id, $peer_id, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    json_out(['status' => 'success', 'messages' => $rows]);
}

if ($action === 'mark_read_conversation') {
    $peer_id = isset($params['peer_id']) ? (int)$params['peer_id'] : null;
    if ($peer_id === null) { json_out(['status' => 'error', 'message' => 'Thiếu peer_id']); }
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1, status = 'read' WHERE receiver_id = 0 AND sender_id = ? AND is_read = 0");
    $stmt->bind_param('i', $peer_id);
    $ok = $stmt->execute();
    json_out(['status' => $ok ? 'success' : 'error']);
}

// Customer side: mark admin messages as read
if ($action === 'mark_read_user') {
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        if (!isset($_SESSION['guest_id'])) { $_SESSION['guest_id'] = rand(100000, 999999); }
        $uid = -1 * (int)$_SESSION['guest_id'];
    }
    $stmt = $conn->prepare("UPDATE messages SET is_read = 1, status = 'read' WHERE receiver_id = ? AND sender_id = 0 AND is_read = 0");
    $stmt->bind_param('i', $uid);
    $ok = $stmt->execute();
    json_out(['status' => $ok ? 'success' : 'error']);
}

if ($action === 'recall_message') {
    $msg_id = isset($params['id']) ? (int)$params['id'] : null;
    if ($msg_id === null) { json_out(['status' => 'error', 'message' => 'Thiếu id']); }
    // Only sender can recall within 180 seconds and before read
    $sender_id = $_SESSION['user_id'] ?? 0;
    $stmt = $conn->prepare("SELECT sender_id, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS age, is_read FROM messages WHERE id = ?");
    $stmt->bind_param('i', $msg_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) { json_out(['status' => 'error', 'message' => 'Không tìm thấy tin nhắn']); }
    $row = $res->fetch_assoc();
    if ((int)$row['sender_id'] !== (int)$sender_id) { json_out(['status' => 'error', 'message' => 'Không có quyền thu hồi']); }
    if ((int)$row['is_read'] === 1) { json_out(['status' => 'error', 'message' => 'Tin nhắn đã được đọc']); }
    if ((int)$row['age'] > 180) { json_out(['status' => 'error', 'message' => 'Quá thời gian thu hồi']); }
    $ok = $conn->query("UPDATE messages SET is_recalled = 1, message_type = 'recall', content = '' WHERE id = " . $msg_id);
    json_out(['status' => $ok ? 'success' : 'error']);
}

// Identify current chat identity (user or guest) for frontend
if ($action === 'whoami') {
    $uid = $_SESSION['user_id'] ?? null;
    $pos = $_SESSION['position'] ?? null;
    $uname = $_SESSION['username'] ?? null;
    if ($uid) {
        $stmt = $conn->prepare("SELECT username, Avatar, gmail, phone FROM users WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $uname = $row['username'] ?: $uname;
            json_out(['status'=>'success','sender_id'=>$uid,'username'=>$uname,'avatar'=>$row['Avatar']??null,'gmail'=>$row['gmail']??null,'phone'=>$row['phone']??null,'position'=>$pos]);
        }
        json_out(['status'=>'success','sender_id'=>$uid,'username'=>$uname,'position'=>$pos]);
    } else {
        if (!isset($_SESSION['guest_id'])) { $_SESSION['guest_id'] = rand(100000, 999999); }
        $gid = -1 * (int)$_SESSION['guest_id'];
        $uname = $uname ?: ('Khach-' . $_SESSION['guest_id']);
        json_out(['status'=>'success','sender_id'=>$gid,'username'=>$uname,'position'=>'khach-hang']);
    }
}

// Get user info by ID (for admin to view customer details)
if ($action === 'get_user_info') {
    $user_id = isset($params['user_id']) ? (int)$params['user_id'] : (isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0);
    if ($user_id <= 0) {
        json_out(['status' => 'error', 'message' => 'ID người dùng không hợp lệ']);
    }
    
    $stmt = $conn->prepare("SELECT id, username, gmail, phone, Avatar FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $res->num_rows > 0) {
        $user = $res->fetch_assoc();
        json_out([
            'status' => 'success',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'gmail' => $user['gmail'],
                'phone' => $user['phone'],
                'avatar_path' => $user['Avatar'],
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        json_out(['status' => 'error', 'message' => 'Không tìm thấy người dùng']);
    }
}

// Fallback if no action matched above
json_out(['status' => 'error', 'message' => 'Hành động không hợp lệ']);
