<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No CSV file uploaded']);
    exit;
}

// Optional flags
$dryRun = isset($_POST['dry_run']) && ($_POST['dry_run'] === '1' || $_POST['dry_run'] === 1);

function sanitize_rich_text($str) {
    if ($str === null) return null;
    $s = (string)$str;
    $s = preg_replace('/<(script|style)[^>]*>[\s\S]*?<\/(script|style)>/i', ' ', $s);
    $s = preg_replace('/<!--[\s\S]*?-->/m', ' ', $s);
    $s = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $s);
    $s = preg_replace('/<\s*li\s*>/i', '- ', $s);
    $s = preg_replace('/<\s*\/\s*li\s*>/i', "\n", $s);
    $s = preg_replace('/<\s*p\s*>/i', '', $s);
    $s = preg_replace('/<\s*\/\s*p\s*>/i', "\n\n", $s);
    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/\s+/u', ' ', $s);
    return trim($s);
}
function sanitize_slug($str) {
    if ($str === null) return null;
    $s = strtolower(html_entity_decode(strip_tags((string)$str)));
    $s = preg_replace('/[^a-z0-9]+/u', '-', $s);
    $s = preg_replace('/-+/u', '-', $s);
    $s = trim($s, '-');
    return $s;
}

$allowedCols = [
    'id','name','sku','type_code','original_price','sale_price','stock_quantity','status',
    'description_short','description_long','technical_specs',
    'brand','weight','dimensions',
    'slug','meta_title','meta_description','meta_keywords'
];
$sanitizeCols = [
    'description_short','description_long','technical_specs','brand','weight','dimensions','meta_title','meta_description','meta_keywords'
];

$tmp = $_FILES['file']['tmp_name'];
$fin = fopen($tmp, 'r');
if (!$fin) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Open CSV failed']);
    exit;
}

// Read header
$header = fgetcsv($fin);
if (!$header) {
    echo json_encode(['success' => false, 'error' => 'Empty CSV']);
    exit;
}

// Handle semicolon-delimited CSV headers (common in some locales)
if (count($header) === 1 && strpos((string)$header[0], ';') !== false) {
    $header = str_getcsv($header[0], ';');
}

// Normalization helpers for header matching
function remove_bom($s) {
    return preg_replace('/^\xEF\xBB\xBF/', '', $s);
}
function normalize_col($s) {
    $s = remove_bom(trim((string)$s));
    $s = mb_strtolower($s, 'UTF-8');
    // best-effort accent removal
    $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($t !== false) { $s = $t; }
    // replace non-alnum with underscore
    $s = preg_replace('/[^a-z0-9]+/u', '_', $s);
    $s = preg_replace('/_+/u', '_', $s);
    $s = trim($s, '_');
    return $s;
}

// Build allowed normalized map
$allowedMap = [];
foreach ($allowedCols as $c) {
    $allowedMap[normalize_col($c)] = $c; // normalized -> canonical
}
// Backward-compatible headers
$allowedMap['short_description'] = 'description_short';
$allowedMap['long_description'] = 'description_long';

// Synonyms for id column (normalized forms)
$idSynonyms = ['id', 'product_id', 'ma', 'ma_san_pham', 'code'];
// Synonyms for sku column (normalized forms)
$skuSynonyms = ['sku', 'product_sku', 'ma_sp', 'ma_san_pham', 'code', 'product_code'];

// Map header indexes
$idx = [];
$headerRaw = $header;
foreach ($header as $i => $col) {
    $norm = normalize_col($col);
    // Map canonical if allowed
    if (isset($allowedMap[$norm])) {
        $canonical = $allowedMap[$norm];
        $idx[$canonical] = $i;
    }
    // Special: id synonyms
    if (in_array($norm, $idSynonyms, true)) {
        $idx['id'] = $i;
    }
    // Special: sku synonyms
    if (in_array($norm, $skuSynonyms, true)) {
        $idx['sku'] = $i;
    }
}

if (!isset($idx['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'CSV must include id column (case-insensitive). Try header named id or product_id.',
        'headers_detected' => array_map(function($h){ return remove_bom($h); }, $headerRaw)
    ]);
    exit;
}

$processed = 0; $updated = 0; $failed = 0; $errors = [];
$preview = [];
$skuFallbackUsed = 0;
$idParsedNonNumeric = 0;

while (($row = fgetcsv($fin)) !== false) {
    // Handle semicolon-delimited CSV rows
    if (count($row) === 1 && strpos((string)$row[0], ';') !== false) {
        $row = str_getcsv($row[0], ';');
    }
    $processed++;
    // Parse product id robustly (accept values like "SP-00123" -> 123)
    $idRaw = isset($idx['id']) ? $row[$idx['id']] : null;
    $id = 0;
    if ($idRaw !== null) {
        $tmp = preg_match('/\d+/', (string)$idRaw, $m) ? intval($m[0]) : 0;
        $id = $tmp;
        // Count non-numeric raw ids we parsed
        if ($id > 0 && preg_match('/\D/', (string)$idRaw)) {
            $idParsedNonNumeric++;
        }
    }

    // Fallback: try resolve by SKU if id is invalid
    $sku = null;
    if ($id <= 0 && isset($idx['sku'])) {
        $sku = trim((string)$row[$idx['sku']]);
        if ($sku !== '') {
            $stmtSku = $conn->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
            if ($stmtSku) {
                $stmtSku->bind_param('s', $sku);
                $stmtSku->execute();
                $rsSku = $stmtSku->get_result();
                $found = $rsSku ? $rsSku->fetch_assoc() : null;
                if ($found && isset($found['id'])) {
                    $id = intval($found['id']);
                    $skuFallbackUsed++;
                }
            }
        }
    }

    if ($id <= 0) { 
        $failed++; 
        $errors[] = "Invalid id on row $processed" . ($idRaw!==null?" (raw:'".trim((string)$idRaw)."')":"") . ($sku?" sku:'$sku'":"");
        continue; 
    }

    // Build SET parts
    $setParts = [];
    $paramTypes = ''; $paramValues = [];

    foreach ($allowedCols as $col) {
        if (!isset($idx[$col])) continue;
        if ($col === 'id') continue;
        $val = $row[$idx[$col]];
        if (in_array($col, $sanitizeCols, true)) {
            $val = sanitize_rich_text($val);
        }
        if ($col === 'slug') {
            $val = sanitize_slug($val);
        }
        // Type handling: i, d, s
        $type = 's';
        if (in_array($col, ['stock_quantity'], true)) $type = 'i';
        if (in_array($col, ['original_price','sale_price'], true)) $type = 'd';
        if ($type === 'i') $val = (int)$val;
        if ($type === 'd') $val = (float)$val;
        $setParts[] = "`$col` = ?";
        $paramTypes .= $type;
        $paramValues[] = $val;
    }

    if (empty($setParts)) { $failed++; $errors[] = "No update columns on row $processed"; continue; }

    // bump cache_version
    $sql = "UPDATE products SET " . implode(', ', $setParts) . ", cache_version = cache_version + 1 WHERE id = ?";
    $paramTypes .= 'i';
    $paramValues[] = $id;

    if ($dryRun) {
        $preview[] = ['id'=>$id, 'changes'=> $setParts];
        $updated++; // count as potential update
        continue;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) { $failed++; $errors[] = "Prepare failed for id $id"; continue; }
    $stmt->bind_param($paramTypes, ...$paramValues);
    $ok = $stmt->execute();
    if (!$ok) { $failed++; $errors[] = "Execute failed for id $id"; continue; }
    $updated++;
}

fclose($fin);

echo json_encode([
    'success' => true,
    'processed' => $processed,
    'updated' => $updated,
    'failed' => $failed,
    'errors' => $errors,
    'sku_fallback_used' => $skuFallbackUsed,
    'id_parsed_non_numeric' => $idParsedNonNumeric,
    'dry_run' => $dryRun,
    'preview' => $dryRun ? $preview : null
]);
