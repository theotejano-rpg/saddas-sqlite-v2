<?php
require_once __DIR__ . '/admin/db.php';
$db = get_db();
$lab = $argv[1] ?? 'Lab 524';
$items = [
    'C / C++',
    'Java',
    'Python',
    'PHP / Web Development',
    'Database (SQL)'
];
foreach ($items as $sw) {
    try {
        $db->prepare("INSERT INTO lab_software (lab_room, software, description) VALUES (?,?,?)")
           ->execute([$lab, $sw, 'Seeded software']);
        // ensure pc_software inserted
        $ins = $db->prepare("INSERT OR IGNORE INTO pc_software (lab_room, pc_number, software, is_enabled) VALUES (?,?,?,1)");
        for ($p = 1; $p <= 50; $p++) $ins->execute([$lab, $p, $sw]);
        echo "Added: $sw\n";
    } catch (Exception $e) {
        echo "Skipped (exists): $sw\n";
    }
}
echo "Done.\n";
