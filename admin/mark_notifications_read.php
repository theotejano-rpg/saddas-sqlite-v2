<?php
// mark_notifications_read.php
// Usage:
// - mark_notifications_read.php?mark_all=1 -> mark all notifications read
// - mark_notifications_read.php?mark_notif=ID -> mark single notification read
// Optional: return URL in 'return' param
session_start();
require_once 'db.php';
$db = get_db();
// Resolve the return URL — this file lives inside admin/, so bare filenames
// like "AdminSitin.php" are already correct relative paths. No prefix needed.
$raw = isset($_GET['return']) ? $_GET['return'] : ($_SERVER['HTTP_REFERER'] ?? 'AdminSitin.php');
$redirect = $raw;

if (isset($_GET['mark_all'])) {
    $db->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    header('Location: ' . $redirect);
    exit;
}

if (isset($_GET['mark_notif'])) {
    $id = (int)$_GET['mark_notif'];
    if ($id > 0) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([$id]);
    }
    header('Location: ' . $redirect);
    exit;
}

// Fallback: if called with POST from JS to mark all
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all'])) {
        $db->exec("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
        echo json_encode(['ok'=>true]);
        exit;
    }
}

// nothing to do
header('Location: ' . $redirect);
exit;