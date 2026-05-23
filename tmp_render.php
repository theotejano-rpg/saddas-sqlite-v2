<?php
// Render admin/AdminSoftware.php with a fake admin session so we can inspect the generated HTML/JS
session_start();
$_SESSION['admin'] = ['id' => 1, 'username' => 'dev'];
ob_start();
include __DIR__ . '/admin/AdminSoftware.php';
$html = ob_get_clean();
// Output with line numbers around the script block for debugging
$lines = explode("\n", $html);
foreach ($lines as $n => $line) {
    $ln = $n + 1;
    if ($ln >= 430 && $ln <= 520) printf("%4d: %s\n", $ln, $line);
}
// Also print full script block start..end (find <script> and </script>)
$start = strpos($html, '<script>');
$end = strpos($html, '</script>', $start);
if ($start !== false && $end !== false) {
    $script = substr($html, $start, $end - $start + 9);
    echo "\n--- SCRIPT BLOCK (excerpt) ---\n";
    $slines = explode("\n", $script);
    foreach ($slines as $n => $line) printf("%4d: %s\n", $n+1, $line);
}
