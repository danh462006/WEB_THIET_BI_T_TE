<?php
/**
 * CLI: Clean HTML/CSS/JS from CSV columns and output sanitized CSV.
 * Usage:
 *   php tools/clean_csv.php input.csv output.csv
 */

if (php_sapi_name() !== 'cli') {
    echo "Run from CLI: php tools/clean_csv.php input.csv output.csv\n";
    exit(1);
}

$in = $argv[1] ?? null;
$out = $argv[2] ?? null;
if (!$in || !$out) {
    echo "Usage: php tools/clean_csv.php input.csv output.csv\n";
    exit(1);
}
if (!file_exists($in)) {
    echo "Input not found: $in\n";
    exit(1);
}

function sanitize_rich_text($str) {
    if ($str === null) return '';
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

$fin = fopen($in, 'r');
$fos = fopen($out, 'w');
if (!$fin || !$fos) {
    echo "Failed opening files\n";
    exit(1);
}

$header = fgetcsv($fin);
if (!$header) { echo "Empty CSV\n"; exit(1); }

// Write header unchanged
fputcsv($fos, $header);

// Columns to sanitize if present
$toSanitize = [
    'short_description','long_description','technical_specs','brand','weight','dimensions','meta_title','meta_description','meta_keywords'
];

// Map column indexes
$idx = [];
foreach ($toSanitize as $col) {
    $pos = array_search($col, $header, true);
    if ($pos !== false) $idx[$pos] = true;
}

while (($row = fgetcsv($fin)) !== false) {
    foreach ($row as $i => $val) {
        if (isset($idx[$i])) {
            $row[$i] = sanitize_rich_text($val);
        }
    }
    fputcsv($fos, $row);
}

fclose($fin);
fclose($fos);

echo "Sanitized CSV written to $out\n";
