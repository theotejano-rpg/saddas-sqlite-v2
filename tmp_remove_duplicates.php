<?php
require_once __DIR__ . '/admin/db.php';
$db = get_db();
echo "Removing duplicate pc_software rows...\n";
$db->exec("DELETE FROM pc_software WHERE id NOT IN (SELECT min(id) FROM pc_software GROUP BY lab_room, pc_number, software)");
echo "Done.\n";
// report duplicates now
$stmt=$db->query("SELECT lab_room, pc_number, software, COUNT(*) as c FROM pc_software WHERE lab_room='Lab 524' GROUP BY lab_room, pc_number, software HAVING c>1");
$dups=$stmt->fetchAll();
if (empty($dups)) echo "No duplicates\n"; else print_r($dups);
// show pc1
$stmt = $db->prepare("SELECT lab_room,pc_number,software,is_enabled FROM pc_software WHERE lab_room=? AND pc_number=? ORDER BY software");
$stmt->execute(['Lab 524', 1]);
print_r($stmt->fetchAll());
