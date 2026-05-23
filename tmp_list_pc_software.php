<?php
require_once __DIR__ . '/admin/db.php';
$db = get_db();
$lab = $argv[1] ?? 'Lab 524';
echo "Lab: $lab\n\n";
// lab_software
$stmt = $db->prepare("SELECT id, software, description FROM lab_software WHERE lab_room=? ORDER BY software");
$stmt->execute([$lab]);
$lrows = $stmt->fetchAll();
echo "lab_software (" . count($lrows) . "):\n";
foreach ($lrows as $r) echo " - {$r['software']} (id={$r['id']})\n";

// pc_software count
$stmt = $db->prepare("SELECT pc_number, software, is_enabled FROM pc_software WHERE lab_room=? ORDER BY pc_number, software");
$stmt->execute([$lab]);
$prows = $stmt->fetchAll();
$map = [];
foreach ($prows as $r) $map[$r['pc_number']][] = $r;

echo "\npc_software total rows: " . count($prows) . "\n";
for ($pc = 1; $pc <= 50; $pc++) {
    $arr = $map[$pc] ?? [];
    echo "PC $pc: " . (count($arr) ? count($arr) . ' items' : 'no software') . "\n";
    if (count($arr) && $pc <= 10) {
        foreach ($arr as $a) echo "    - {$a['software']} (enabled={$a['is_enabled']})\n";
    }
}
