<?php
require_once __DIR__ . '/db.php';
$db = get_db();
// Known labs in the application
$lab_rooms = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];

// Ensure there's a row for each lab (default enabled = 1)
$ins = $db->prepare("INSERT OR IGNORE INTO lab_settings (lab_room, is_enabled) VALUES (?, 1)");
foreach ($lab_rooms as $r) { $ins->execute([$r]); }

$stmt = $db->query("SELECT lab_room, is_enabled FROM lab_settings ORDER BY lab_room");
$rows = $stmt->fetchAll();
if (empty($rows)) {
    echo "(no rows)\n";
    exit(0);
}
foreach ($rows as $r) {
    $room = $r['lab_room'];
    echo $r['lab_room'] . "|" . $r['is_enabled'] . "|len=" . strlen($room) . "|hex=" . bin2hex($room) . "\n";
}
echo "-- total: " . count($rows) . " rows\n";
