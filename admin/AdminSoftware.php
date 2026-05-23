<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();

// Ensure tables exist
$db->exec("
    CREATE TABLE IF NOT EXISTS lab_settings (
        lab_room   TEXT PRIMARY KEY,
        is_enabled INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS lab_software (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        lab_room    TEXT NOT NULL,
        software    TEXT NOT NULL,
        description TEXT DEFAULT '',
        added_at    TEXT NOT NULL DEFAULT (datetime('now')),
        UNIQUE(lab_room, software)
    );
    CREATE TABLE IF NOT EXISTS lab_pcs (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        lab_room   TEXT NOT NULL,
        pc_number  INTEGER NOT NULL,
        status     TEXT NOT NULL DEFAULT 'available',
        UNIQUE(lab_room, pc_number)
    );
");

// Migrate old is_enabled column to status if needed
try {
    $db->exec("ALTER TABLE lab_pcs ADD COLUMN status TEXT NOT NULL DEFAULT 'available'");
} catch (Exception $e) {}
// Sync old is_enabled -> status
$db->exec("UPDATE lab_pcs SET status='available' WHERE is_enabled=1 AND (status IS NULL OR status='')");
$db->exec("UPDATE lab_pcs SET status='disabled' WHERE is_enabled=0 AND (status IS NULL OR status='')");

$lab_rooms = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];

// ── AJAX handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';

    // Toggle lab enabled/disabled
    if ($action === 'toggle_lab') {
        $lab = $_POST['lab_room'] ?? '';
        $val = (int)($_POST['is_enabled'] ?? 1);
        if (in_array($lab, $lab_rooms)) {
            $db->prepare("INSERT INTO lab_settings (lab_room, is_enabled) VALUES (?,?)
                ON CONFLICT(lab_room) DO UPDATE SET is_enabled=excluded.is_enabled")
               ->execute([$lab, $val]);
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    // Add software to lab
    if ($action === 'add_software') {
        $lab  = $_POST['lab_room'] ?? '';
        $sw   = trim($_POST['software'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (in_array($lab, $lab_rooms) && $sw !== '') {
            try {
                $db->prepare("INSERT INTO lab_software (lab_room, software, description) VALUES (?,?,?)")
                   ->execute([$lab, $sw, $desc]);
                echo json_encode(['ok' => true]);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'msg' => 'Software already exists in this lab.']);
            }
        }
        exit;
    }

    // Delete software from lab
    if ($action === 'delete_software') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM lab_software WHERE id = ?")->execute([$id]);
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    // Set PC status (available / disabled / maintenance)
    if ($action === 'set_pc_status') {
        $lab    = $_POST['lab_room'] ?? '';
        $pc     = (int)($_POST['pc_number'] ?? 0);
        $status = $_POST['status'] ?? 'available';
        if (!in_array($status, ['available','disabled','maintenance'])) $status = 'available';
        if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50) {
            $db->prepare("INSERT INTO lab_pcs (lab_room, pc_number, status, is_enabled) VALUES (?,?,?,?)
                ON CONFLICT(lab_room, pc_number) DO UPDATE SET status=excluded.status, is_enabled=excluded.is_enabled")
               ->execute([$lab, $pc, $status, $status === 'available' ? 1 : 0]);
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    // Bulk set PC status
    if ($action === 'bulk_pc_status') {
        $lab     = $_POST['lab_room'] ?? '';
        $pcs     = json_decode($_POST['pc_numbers'] ?? '[]', true);
        $status  = $_POST['status'] ?? 'available';
        if (!in_array($status, ['available','disabled','maintenance'])) $status = 'available';
        if (in_array($lab, $lab_rooms) && is_array($pcs)) {
            $enabled = $status === 'available' ? 1 : 0;
            foreach ($pcs as $pc) {
                $pc = (int)$pc;
                if ($pc >= 1 && $pc <= 50) {
                    $db->prepare("INSERT INTO lab_pcs (lab_room, pc_number, status, is_enabled) VALUES (?,?,?,?)
                        ON CONFLICT(lab_room, pc_number) DO UPDATE SET status=excluded.status, is_enabled=excluded.is_enabled")
                       ->execute([$lab, $pc, $status, $enabled]);
                }
            }
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    // Set ALL PCs in lab to a status
    if ($action === 'set_all_pc_status') {
        $lab    = $_POST['lab_room'] ?? '';
        $status = $_POST['status'] ?? 'available';
        if (!in_array($status, ['available','disabled','maintenance'])) $status = 'available';
        if (in_array($lab, $lab_rooms)) {
            $enabled = $status === 'available' ? 1 : 0;
            for ($pc = 1; $pc <= 50; $pc++) {
                $db->prepare("INSERT INTO lab_pcs (lab_room, pc_number, status, is_enabled) VALUES (?,?,?,?)
                    ON CONFLICT(lab_room, pc_number) DO UPDATE SET status=excluded.status, is_enabled=excluded.is_enabled")
                   ->execute([$lab, $pc, $status, $enabled]);
            }
            echo json_encode(['ok' => true]);
        }
        exit;
    }

    exit;
}

// ── Fetch data ─────────────────────────────────────────────────────────────────
$lab_enabled = [];
$rows = $db->query("SELECT lab_room, is_enabled FROM lab_settings")->fetchAll();
foreach ($rows as $r) $lab_enabled[$r['lab_room']] = (int)$r['is_enabled'];

// Software filtered by lab
$filter_lab = $_GET['filter_lab'] ?? '';
if ($filter_lab && !in_array($filter_lab, $lab_rooms)) $filter_lab = '';
if ($filter_lab) {
    $sw_rows = $db->prepare("SELECT * FROM lab_software WHERE lab_room=? ORDER BY added_at DESC")->execute([$filter_lab]) ? [] : [];
    $stmt = $db->prepare("SELECT * FROM lab_software WHERE lab_room=? ORDER BY added_at DESC");
    $stmt->execute([$filter_lab]);
    $sw_table = $stmt->fetchAll();
} else {
    $sw_table = $db->query("SELECT * FROM lab_software ORDER BY lab_room, added_at DESC")->fetchAll();
}

// PC states for selected lab (PC management)
$pc_lab = $_GET['pc_lab'] ?? $lab_rooms[0];
if (!in_array($pc_lab, $lab_rooms)) $pc_lab = $lab_rooms[0];

$pc_states = [];
$pcs = $db->prepare("SELECT pc_number, status FROM lab_pcs WHERE lab_room=?");
$pcs->execute([$pc_lab]);
foreach ($pcs->fetchAll() as $r) $pc_states[$r['pc_number']] = $r['status'];

$cnt_avail = 0; $cnt_disabled = 0; $cnt_maintenance = 0;
for ($i = 1; $i <= 50; $i++) {
    $s = $pc_states[$i] ?? 'available';
    if ($s === 'available')    $cnt_avail++;
    elseif ($s === 'disabled') $cnt_disabled++;
    else                       $cnt_maintenance++;
}

$nav_admin_active = 'software';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS — Lab Settings</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    /* Lab enable/disable grid */
    .lab-toggle-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 14px; padding: 20px;
    }
    .lab-toggle-card {
      display: flex; align-items: center; justify-content: space-between;
      gap: 10px;
      padding: 14px 16px; border-radius: 12px;
      border: 1.5px solid rgba(204,222,237,0.6);
      background: white; min-width: 0;
    }
    .lab-toggle-info { min-width: 0; flex: 1; }
    .lab-toggle-name { font-weight: 700; font-size: 0.92rem; color: #1a2535; white-space: nowrap; }
    .lab-toggle-state { font-size: 0.72rem; color: #9aa5b4; margin-top: 2px; white-space: nowrap; }
    .lab-btns { display: flex; gap: 6px; flex-shrink: 0; }
    .lab-btn {
      padding: 6px 12px; border-radius: 7px; border: none;
      font-size: 0.76rem; font-weight: 700; cursor: pointer;
      transition: opacity 0.15s; white-space: nowrap;
    }
    .lab-btn.enable  { background: rgba(34,197,94,0.15); color: #15803d; }
    .lab-btn.enable:hover  { background: rgba(34,197,94,0.3); }
    .lab-btn.disable { background: rgba(224,53,53,0.12); color: #b91c1c; }
    .lab-btn.disable:hover { background: rgba(224,53,53,0.25); }
    .lab-btn.active-state { opacity: 0.35; cursor: default; pointer-events: none; }

    /* Add software form */
    .sw-add-form {
      display: flex; gap: 10px; flex-wrap: wrap;
      padding: 16px 20px; align-items: flex-end;
      border-bottom: 1px solid rgba(204,222,237,0.4);
    }
    .sw-add-form select, .sw-add-form input {
      padding: 8px 12px; border: 1.5px solid #ccdeed;
      border-radius: 8px; font-size: 0.85rem; font-family: inherit;
    }
    .sw-add-form select { min-width: 160px; }
    .sw-add-form input { flex: 1; min-width: 180px; }

    /* Software table */
    .sw-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
    .sw-table th {
      padding: 10px 16px; text-align: left;
      background: rgba(10,77,140,0.06); color: #0a4d8c;
      font-size: 0.72rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.5px;
    }
    .sw-table td {
      padding: 11px 16px;
      border-bottom: 1px solid rgba(204,222,237,0.35);
      color: #1a2535; vertical-align: middle;
    }
    .sw-table tr:last-child td { border-bottom: none; }
    .sw-table tr:hover td { background: rgba(10,77,140,0.02); }

    /* Lab filter bar */
    .sw-filter-bar {
      display: flex; gap: 8px; align-items: center;
      padding: 12px 20px; border-bottom: 1px solid rgba(204,222,237,0.4);
      flex-wrap: wrap;
    }
    .sw-filter-bar label { font-size: 0.8rem; font-weight: 600; color: #4a5568; }
    .sw-filter-bar select { padding: 7px 12px; border: 1.5px solid #ccdeed; border-radius: 8px; font-size: 0.83rem; }
    .sw-filter-bar a.clear { font-size: 0.78rem; color: #9aa5b4; text-decoration: none; }

    /* ── PC Management card (matches admin-card style) ── */
    .pc-mgmt-card {
      background: rgba(255,255,255,0.82);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 16px;
      box-shadow: 0 8px 32px rgba(10,77,140,0.08);
      overflow: hidden;
      margin-bottom: 22px;
    }
    .pc-mgmt-header {
      display: flex; align-items: center; gap: 8px;
      padding: 14px 20px;
      background: #0a4d8c;
      color: white; font-size: 0.88rem; font-weight: 700;
    }

    /* Lab selector */
    .pc-lab-row { padding: 16px 20px 0; }
    .pc-lab-label {
      font-size: 0.68rem; font-weight: 700; color: #4a6278;
      text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px;
    }
    .pc-lab-select {
      padding: 8px 14px; border-radius: 8px;
      border: 1.5px solid #ccdeed;
      background: white; color: #12243a;
      font-size: 0.85rem; font-family: inherit; cursor: pointer;
      min-width: 160px;
    }

    /* Stats row */
    .pc-stats-row {
      display: grid; grid-template-columns: repeat(4, 1fr);
      gap: 12px; padding: 16px 20px;
    }
    .pc-stat-box {
      text-align: center; padding: 14px 8px;
      border-radius: 10px;
      background: white;
      border: 1.5px solid rgba(204,222,237,0.6);
      box-shadow: 0 2px 8px rgba(10,77,140,0.05);
      min-width: 0; /* prevent overflow */
    }
    .pc-stat-num {
      font-size: 1.7rem; font-weight: 900; line-height: 1;
    }
    .pc-stat-num.avail { color: #16a34a; }
    .pc-stat-num.dis   { color: #dc2626; }
    .pc-stat-num.maint { color: #d97706; }
    .pc-stat-num.total { color: #0a4d8c; }
    .pc-stat-lbl {
      font-size: 0.62rem; text-transform: uppercase;
      letter-spacing: 0.6px; color: #9aa5b4; margin-top: 4px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* Action buttons — centered */
    .pc-action-bar {
      display: flex; gap: 8px;
      padding: 0 20px 14px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .pc-act-btn {
      padding: 7px 16px; border-radius: 8px; border: none;
      font-size: 0.78rem; font-weight: 700; cursor: pointer;
      transition: opacity 0.15s, transform 0.1s;
      white-space: nowrap;
    }
    .pc-act-btn:hover  { opacity: 0.85; transform: translateY(-1px); }
    .pc-act-btn:active { transform: translateY(0); }
    .pc-act-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
    .pc-act-btn.avail   { background: rgba(22,163,74,0.15);  color: #15803d; }
    .pc-act-btn.dis     { background: rgba(220,38,38,0.12);  color: #b91c1c; }
    .pc-act-btn.maint   { background: rgba(217,119,6,0.13);  color: #92400e; }
    .pc-act-btn.selmode { background: rgba(10,77,140,0.1);   color: #0a4d8c; }
    .pc-act-btn.selmode.active {
      background: rgba(10,77,140,0.2); color: #0a4d8c;
      outline: 2px solid rgba(10,77,140,0.35); outline-offset: 1px;
    }

    /* Select mode sub-toolbar */
    .pc-sel-toolbar {
      display: none; align-items: center; flex-wrap: wrap; gap: 8px;
      padding: 10px 20px;
      background: rgba(10,77,140,0.04);
      border-top: 1px solid rgba(204,222,237,0.5);
      border-bottom: 1px solid rgba(204,222,237,0.5);
    }
    .pc-sel-toolbar.active { display: flex; }
    .pc-sel-count {
      font-size: 0.8rem; font-weight: 700; color: #0a4d8c; margin-right: 4px;
    }
    .pc-sel-btn {
      padding: 6px 13px; border-radius: 7px; border: none;
      font-size: 0.75rem; font-weight: 700; cursor: pointer;
      white-space: nowrap;
    }
    .pc-sel-btn.avail   { background: rgba(22,163,74,0.15);  color: #15803d; }
    .pc-sel-btn.dis     { background: rgba(220,38,38,0.12);  color: #b91c1c; }
    .pc-sel-btn.maint   { background: rgba(217,119,6,0.13);  color: #92400e; }
    .pc-sel-btn.neutral { background: rgba(10,77,140,0.1);   color: #0a4d8c; }
    .pc-sel-btn.cancel  { background: #f0f4f8; color: #4a5568; margin-left: auto; }

    /* PC Grid — original light style */
    .pc-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(56px, 1fr));
      gap: 7px; padding: 12px 20px 20px;
    }
    .pc-tile {
      padding: 8px 2px; border-radius: 8px; border: 2px solid;
      display: flex; align-items: center; justify-content: center;
      font-size: 0.7rem; font-weight: 700; cursor: pointer;
      transition: transform 0.13s, box-shadow 0.13s;
      user-select: none; white-space: nowrap;
    }
    .pc-tile:hover { transform: scale(1.09); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
    .pc-tile.available   { background: rgba(34,197,94,0.12);   border-color: #86efac; color: #15803d; }
    .pc-tile.disabled    { background: rgba(224,53,53,0.1);    border-color: #fca5a5; color: #b91c1c; }
    .pc-tile.maintenance { background: rgba(232,160,32,0.12);  border-color: #fcd34d; color: #a06010; }
    .pc-tile.occupied    { background: rgba(107,33,200,0.12);  border-color: #c4b5fd; color: #6b21c8; }
    .pc-tile.selected    { outline: 3px solid #0a4d8c; outline-offset: 2px; }

    /* Context menu */
    .pc-ctx-menu {
      display: none; position: fixed;
      background: white; border: 1px solid #ccdeed;
      border-radius: 10px; padding: 5px;
      box-shadow: 0 8px 28px rgba(10,77,140,0.15);
      z-index: 5000; min-width: 155px;
    }
    .pc-ctx-menu.show { display: block; }
    .pc-ctx-title {
      font-size: 0.66rem; font-weight: 700; color: #9aa5b4;
      text-transform: uppercase; letter-spacing: 0.8px;
      padding: 6px 12px 4px;
    }
    .pc-ctx-item {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 12px; border-radius: 7px; font-size: 0.82rem;
      font-weight: 600; cursor: pointer; transition: background 0.12s; color: #1a2535;
    }
    .pc-ctx-item:hover { background: rgba(10,77,140,0.06); }
    .pc-ctx-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    .pc-ctx-dot.avail { background: #16a34a; }
    .pc-ctx-dot.dis   { background: #dc2626; }
    .pc-ctx-dot.maint { background: #d97706; }

    /* Legend */
    .pc-legend {
      display: flex; gap: 16px; padding: 4px 20px 14px; flex-wrap: wrap;
    }
    .pc-legend-item {
      display: flex; align-items: center; gap: 6px;
      font-size: 0.73rem; color: #4a6278; font-weight: 600;
    }
    .pc-legend-dot { width: 11px; height: 11px; border-radius: 3px; border: 1.5px solid; }

    .toast-fixed {
      position: fixed; bottom: 28px; right: 28px;
      background: #0a4d8c; color: white;
      padding: 11px 20px; border-radius: 10px;
      font-size: 0.83rem; font-weight: 700;
      opacity: 0; transition: opacity 0.25s;
      pointer-events: none; z-index: 9999;
      box-shadow: 0 6px 24px rgba(0,0,0,0.18);
      max-width: 280px;
    }
    .toast-fixed.show { opacity: 1; }
    .toast-fixed.success { background: #16a34a; }
    .toast-fixed.error   { background: #dc2626; }
    .toast-fixed.warning { background: #d97706; }

    .del-btn { background: rgba(224,53,53,0.12); color: #b91c1c; border: none;
      padding: 5px 12px; border-radius: 6px; font-size: 0.76rem; font-weight: 700;
      cursor: pointer; }
    .del-btn:hover { background: rgba(224,53,53,0.25); }
    .empty-sw { text-align: center; color: #9aa5b4; padding: 28px; font-size: 0.85rem; }
    .lab-badge {
      display: inline-block; padding: 2px 10px; border-radius: 20px;
      font-size: 0.72rem; font-weight: 700;
      background: rgba(10,77,140,0.1); color: #0a4d8c;
    }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Lab Settings</h2>

  <!-- ── Enable / Disable Labs ───────────────────────────────────────────────── -->
  <div class="admin-card" style="margin-bottom:22px;">
    <div class="admin-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Enable / Disable Reservation per Lab
    </div>
    <div class="lab-toggle-grid">
      <?php foreach ($lab_rooms as $room):
        $enabled = $lab_enabled[$room] ?? 1;
        $key = md5($room);
      ?>
      <div class="lab-toggle-card" data-lab="<?= htmlspecialchars($room) ?>">
        <div class="lab-toggle-info">
          <div class="lab-toggle-name"><?= htmlspecialchars($room) ?></div>
          <div class="lab-toggle-state" id="lts-<?= $key ?>"><?= $enabled ? 'Reservations Enabled' : 'Reservations Disabled' ?></div>
        </div>
        <div class="lab-btns">
          <button class="lab-btn enable <?= $enabled ? 'active-state' : '' ?>"
            id="lbe-<?= $key ?>"
            onclick="toggleLab(<?= json_encode($room) ?>, 1)">Enabled</button>
          <button class="lab-btn disable <?= !$enabled ? 'active-state' : '' ?>"
            id="lbd-<?= $key ?>"
            onclick="toggleLab(<?= json_encode($room) ?>, 0)">Disable</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Add Software ─────────────────────────────────────────────────────────── -->
  <div class="admin-card" style="margin-bottom:22px;">
    <div class="admin-card-header">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      Add Software to Lab
    </div>
    <div class="sw-add-form">
      <select id="swLab">
        <?php foreach ($lab_rooms as $r): ?>
        <option><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="text" id="swName" placeholder="Software name (e.g. NetBeans)" style="min-width:220px;"/>
      <input type="text" id="swDesc" placeholder="Description (optional)" style="min-width:200px;"/>
      <button class="admin-btn blue" onclick="addSoftware()">+ Add Software</button>
    </div>

    <!-- Filter bar -->
    <div class="sw-filter-bar">
      <label>Filter by Lab:</label>
      <select onchange="location='AdminSoftware.php?filter_lab='+this.value+'&pc_lab=<?= urlencode($pc_lab) ?>'">
        <option value="">All Labs</option>
        <?php foreach ($lab_rooms as $r): ?>
        <option value="<?= htmlspecialchars($r) ?>" <?= $filter_lab === $r ? 'selected':'' ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($filter_lab): ?>
      <a class="clear" href="AdminSoftware.php?pc_lab=<?= urlencode($pc_lab) ?>">✕ Clear filter</a>
      <?php endif; ?>
    </div>

    <!-- Software Table -->
    <div style="overflow-x:auto;">
      <?php if (empty($sw_table)): ?>
        <div class="empty-sw">No software added yet. Use the form above to add some.</div>
      <?php else: ?>
      <table class="sw-table">
        <thead>
          <tr>
            <th>Lab</th>
            <th>Software</th>
            <th>Description</th>
            <th>Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="swTableBody">
          <?php foreach ($sw_table as $sw): ?>
          <tr id="sw-row-<?= $sw['id'] ?>">
            <td><span class="lab-badge"><?= htmlspecialchars($sw['lab_room']) ?></span></td>
            <td style="font-weight:600;"><?= htmlspecialchars($sw['software']) ?></td>
            <td><?= htmlspecialchars($sw['description'] ?: '—') ?></td>
            <td><?= date('Y-m-d', strtotime($sw['added_at'])) ?></td>
            <td><button class="del-btn" onclick="deleteSoftware(<?= $sw['id'] ?>)">Delete</button></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── PC Management (dark card) ──────────────────────────────────────────── -->
  <div class="pc-mgmt-card">
    <div class="pc-mgmt-header">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      PC Management
    </div>

    <!-- Lab selector -->
    <div class="pc-lab-row">
      <div class="pc-lab-label">Select Lab</div>
      <select class="pc-lab-select" id="pcLabSelect" onchange="changePcLab(this.value)">
        <?php foreach ($lab_rooms as $r): ?>
        <option value="<?= htmlspecialchars($r) ?>" <?= $pc_lab === $r ? 'selected':'' ?>><?= htmlspecialchars($r) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Stat boxes -->
    <div class="pc-stats-row">
      <div class="pc-stat-box">
        <div class="pc-stat-num avail" id="cntAvail"><?= $cnt_avail ?></div>
        <div class="pc-stat-lbl">Available</div>
      </div>
      <div class="pc-stat-box">
        <div class="pc-stat-num dis" id="cntDis"><?= $cnt_disabled ?></div>
        <div class="pc-stat-lbl">Disabled</div>
      </div>
      <div class="pc-stat-box">
        <div class="pc-stat-num maint" id="cntMaint"><?= $cnt_maintenance ?></div>
        <div class="pc-stat-lbl">Maintenance</div>
      </div>
      <div class="pc-stat-box">
        <div class="pc-stat-num total">50</div>
        <div class="pc-stat-lbl">Total PCs</div>
      </div>
    </div>

    <!-- Primary action buttons -->
    <div class="pc-action-bar">
      <button class="pc-act-btn avail" onclick="setAllPcStatus('available')">✓ Set All Available</button>
      <button class="pc-act-btn dis"   onclick="setAllPcStatus('disabled')">✕ Set All Disabled</button>
      <button class="pc-act-btn maint" onclick="setAllPcStatus('maintenance')">⚙ Set All Maintenance</button>
      <button class="pc-act-btn selmode" id="selectModeBtn" onclick="toggleSelectMode()">☑ Select Multiple PCs</button>
    </div>

    <!-- Select mode sub-toolbar (hidden until activated) -->
    <div class="pc-sel-toolbar" id="selectToolbar">
      <span class="pc-sel-count" id="selectCount">0 PCs selected</span>
      <button class="pc-sel-btn avail"   onclick="bulkSetStatus('available')">✓ Set Available</button>
      <button class="pc-sel-btn dis"     onclick="bulkSetStatus('disabled')">✕ Set Disabled</button>
      <button class="pc-sel-btn maint"   onclick="bulkSetStatus('maintenance')">⚙ Set Maintenance</button>
      <button class="pc-sel-btn neutral" onclick="selectAll()">Select All</button>
      <button class="pc-sel-btn neutral" onclick="deselectAll()">Deselect All</button>
      <button class="pc-sel-btn cancel"  onclick="toggleSelectMode()">✕ Cancel</button>
    </div>

    <!-- Legend -->
    <div class="pc-legend">
      <div class="pc-legend-item">
        <div class="pc-legend-dot" style="background:rgba(74,222,128,0.18);border-color:#4ade80;"></div> Available
      </div>
      <div class="pc-legend-item">
        <div class="pc-legend-dot" style="background:rgba(248,113,113,0.18);border-color:#f87171;"></div> Disabled
      </div>
      <div class="pc-legend-item">
        <div class="pc-legend-dot" style="background:rgba(251,191,36,0.18);border-color:#fbbf24;"></div> Maintenance
      </div>
      <div class="pc-legend-item">
        <div class="pc-legend-dot" style="background:rgba(167,139,250,0.18);border-color:#a78bfa;"></div> Occupied
      </div>
    </div>

    <!-- PC Grid -->
    <div class="pc-grid" id="pcGrid">
      <?php for ($pc = 1; $pc <= 50; $pc++):
        $s = $pc_states[$pc] ?? 'available';
      ?>
      <div class="pc-tile <?= $s ?>"
           id="pc-<?= $pc ?>"
           data-pc="<?= $pc ?>"
           data-status="<?= $s ?>"
           onclick="pcClick(<?= $pc ?>)"
           oncontextmenu="pcRightClick(event, <?= $pc ?>)"
           title="PC <?= $pc ?> — <?= ucfirst($s) ?>">
        PC<?= $pc ?>
      </div>
      <?php endfor; ?>
    </div>
  </div>

</main>

<!-- Context menu (dark themed) -->
<div class="pc-ctx-menu" id="pcCtxMenu">
  <div class="pc-ctx-title" id="ctxTitle">PC — Options</div>
  <div class="pc-ctx-item" onclick="ctxSetStatus('available')">
    <span class="pc-ctx-dot avail"></span> Available
  </div>
  <div class="pc-ctx-item" onclick="ctxSetStatus('disabled')">
    <span class="pc-ctx-dot dis"></span> Disabled
  </div>
  <div class="pc-ctx-item" onclick="ctxSetStatus('maintenance')">
    <span class="pc-ctx-dot maint"></span> Maintenance
  </div>
</div>

<div class="toast-fixed" id="toast">✓ Saved!</div>

<script>
const LAB_ROOMS = <?= json_encode($lab_rooms) ?>;
let currentLab   = <?= json_encode($pc_lab) ?>;
let pcStatuses   = <?= json_encode($pc_states) ?>;
let selectMode   = false;
let selectedPcs  = new Set();
let ctxTargetPc  = null;

function showToast(msg = '✓ Saved!') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.remove('show', 'success', 'error', 'warning');
  if (msg.startsWith('✓'))      t.classList.add('success');
  else if (msg.startsWith('✕')) t.classList.add('error');
  else if (msg.startsWith('⚠')) t.classList.add('warning');
  // Force reflow so transition fires even if already showing
  void t.offsetWidth;
  t.classList.add('show');
  clearTimeout(window._tt);
  window._tt = setTimeout(() => t.classList.remove('show'), 2200);
}

function post(data) {
  return fetch('AdminSoftware.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(data)
  }).then(r => r.json());
}

// ── Lab toggle ────────────────────────────────────────────────────────────────
function toggleLab(lab, val) {
  // Find the card for this lab using data-lab attribute
  const card = document.querySelector(`.lab-toggle-card[data-lab="${CSS.escape(lab)}"]`);
  if (!card) return;
  const eBtn = card.querySelector('.lab-btn.enable');
  const dBtn = card.querySelector('.lab-btn.disable');
  const lbl  = card.querySelector('.lab-toggle-state');

  // Disable both buttons while saving
  eBtn.disabled = true; dBtn.disabled = true;
  showToast('⏳ Saving...');

  post({ action: 'toggle_lab', lab_room: lab, is_enabled: val }).then(r => {
    eBtn.disabled = false; dBtn.disabled = false;
    if (!r.ok) { showToast('✕ Error saving.'); return; }
    lbl.textContent = val ? 'Reservations Enabled' : 'Reservations Disabled';
    eBtn.classList.toggle('active-state',  !!val);
    dBtn.classList.toggle('active-state', !val);
    showToast(val ? '✓ ' + lab + ' enabled' : '✓ ' + lab + ' disabled');
  }).catch(() => {
    eBtn.disabled = false; dBtn.disabled = false;
    showToast('✕ Network error.');
  });
}

// ── Software ──────────────────────────────────────────────────────────────────
function addSoftware() {
  const lab  = document.getElementById('swLab').value;
  const sw   = document.getElementById('swName').value.trim();
  const desc = document.getElementById('swDesc').value.trim();
  if (!sw) { alert('Please enter a software name.'); return; }
  post({ action: 'add_software', lab_room: lab, software: sw, description: desc })
    .then(r => {
      if (!r.ok) { alert(r.msg || 'Error adding software.'); return; }
      document.getElementById('swName').value = '';
      document.getElementById('swDesc').value = '';
      showToast('✓ Software added! Reload to see table.');
      setTimeout(() => location.reload(), 1200);
    });
}

function deleteSoftware(id) {
  if (!confirm('Delete this software entry?')) return;
  post({ action: 'delete_software', id }).then(r => {
    if (r.ok) {
      const row = document.getElementById('sw-row-' + id);
      if (row) row.remove();
      showToast('✓ Deleted');
    }
  });
}

// ── PC Lab select ─────────────────────────────────────────────────────────────
function changePcLab(lab) {
  const url = new URL(location.href);
  url.searchParams.set('pc_lab', lab);
  location.href = url.toString();
}

// ── PC status helpers ─────────────────────────────────────────────────────────
function setPcTileStatus(pc, status) {
  const tile = document.getElementById('pc-' + pc);
  if (!tile) return;
  tile.classList.remove('available','disabled','maintenance','occupied');
  tile.classList.add(status);
  tile.dataset.status = status;
  tile.title = 'PC ' + pc + ' — ' + status.charAt(0).toUpperCase() + status.slice(1);
  pcStatuses[pc] = status;
  updateStats();
}

function updateStats() {
  let a = 0, d = 0, m = 0;
  for (let i = 1; i <= 50; i++) {
    const s = pcStatuses[i] || 'available';
    if (s === 'available')   a++;
    else if (s === 'disabled') d++;
    else m++;
  }
  document.getElementById('cntAvail').textContent = a;
  document.getElementById('cntDis').textContent   = d;
  document.getElementById('cntMaint').textContent  = m;
}

function setAllPcStatus(status) {
  const labels = { available: 'Available', disabled: 'Disabled', maintenance: 'Maintenance' };
  // Disable all action buttons while saving
  document.querySelectorAll('.pc-act-btn').forEach(b => b.disabled = true);
  showToast('⏳ Saving...');
  post({ action: 'set_all_pc_status', lab_room: currentLab, status }).then(r => {
    document.querySelectorAll('.pc-act-btn').forEach(b => b.disabled = false);
    if (!r.ok) { showToast('✕ Error saving. Try again.'); return; }
    for (let i = 1; i <= 50; i++) setPcTileStatus(i, status);
    showToast('✓ All 50 PCs set to ' + labels[status]);
  }).catch(() => {
    document.querySelectorAll('.pc-act-btn').forEach(b => b.disabled = false);
    showToast('✕ Network error. Try again.');
  });
}

// ── PC click ──────────────────────────────────────────────────────────────────
function pcClick(pc) {
  if (selectMode) {
    if (selectedPcs.has(pc)) {
      selectedPcs.delete(pc);
      document.getElementById('pc-'+pc).classList.remove('selected');
    } else {
      selectedPcs.add(pc);
      document.getElementById('pc-'+pc).classList.add('selected');
    }
    const n = selectedPcs.size;
    document.getElementById('selectCount').textContent = n + ' PC' + (n !== 1 ? 's' : '') + ' selected';
    return;
  }
  // Single click → context menu below tile
  ctxTargetPc = pc;
  const tile = document.getElementById('pc-' + pc);
  const rect = tile.getBoundingClientRect();
  const menu = document.getElementById('pcCtxMenu');
  document.getElementById('ctxTitle').textContent = 'PC' + pc + ' — Set Status';
  // Smart positioning: flip left if overflowing
  let left = rect.left + window.scrollX;
  if (left + 170 > window.innerWidth) left = rect.right + window.scrollX - 170;
  menu.style.top  = (rect.bottom + window.scrollY + 6) + 'px';
  menu.style.left = left + 'px';
  menu.classList.add('show');
}

function pcRightClick(e, pc) {
  e.preventDefault();
  ctxTargetPc = pc;
  const menu = document.getElementById('pcCtxMenu');
  menu.style.top  = (e.clientY + window.scrollY) + 'px';
  menu.style.left = (e.clientX + window.scrollX) + 'px';
  menu.classList.add('show');
}

function ctxSetStatus(status) {
  if (!ctxTargetPc) return;
  post({ action: 'set_pc_status', lab_room: currentLab, pc_number: ctxTargetPc, status }).then(r => {
    if (r.ok) { setPcTileStatus(ctxTargetPc, status); showToast(); }
  });
  document.getElementById('pcCtxMenu').classList.remove('show');
  ctxTargetPc = null;
}

document.addEventListener('click', () => document.getElementById('pcCtxMenu').classList.remove('show'));
document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.getElementById('pcCtxMenu').classList.remove('show'); if (selectMode) toggleSelectMode(); } });

// ── Select mode ───────────────────────────────────────────────────────────────
function toggleSelectMode() {
  selectMode = !selectMode;
  const toolbar = document.getElementById('selectToolbar');
  const btn     = document.getElementById('selectModeBtn');
  toolbar.classList.toggle('active', selectMode);
  btn.classList.toggle('active', selectMode);
  btn.textContent = selectMode ? '☑ Selecting...' : '☑ Select Multiple PCs';
  if (!selectMode) deselectAll();
}

function selectAll() {
  for (let i = 1; i <= 50; i++) {
    selectedPcs.add(i);
    document.getElementById('pc-' + i)?.classList.add('selected');
  }
  document.getElementById('selectCount').textContent = '50 PCs selected';
}

function deselectAll() {
  selectedPcs.forEach(p => document.getElementById('pc-'+p)?.classList.remove('selected'));
  selectedPcs.clear();
  document.getElementById('selectCount').textContent = '0 PCs selected';
}

function bulkSetStatus(status) {
  if (selectedPcs.size === 0) { showToast('⚠ No PCs selected.'); return; }
  const labels = { available: 'Available', disabled: 'Disabled', maintenance: 'Maintenance' };
  const pcs = Array.from(selectedPcs);
  document.querySelectorAll('.pc-sel-btn').forEach(b => b.disabled = true);
  showToast('⏳ Saving...');
  post({ action: 'bulk_pc_status', lab_room: currentLab, pc_numbers: JSON.stringify(pcs), status }).then(r => {
    document.querySelectorAll('.pc-sel-btn').forEach(b => b.disabled = false);
    if (!r.ok) { showToast('✕ Error saving.'); return; }
    pcs.forEach(pc => {
      setPcTileStatus(pc, status);
      document.getElementById('pc-'+pc)?.classList.remove('selected');
    });
    selectedPcs.clear();
    document.getElementById('selectCount').textContent = '0 PCs selected';
    showToast('✓ ' + pcs.length + ' PC' + (pcs.length !== 1 ? 's' : '') + ' set to ' + labels[status]);
  }).catch(() => {
    document.querySelectorAll('.pc-sel-btn').forEach(b => b.disabled = false);
    showToast('✕ Network error.');
  });
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>