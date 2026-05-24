<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();

// Default labs used only for initial seeding if the DB has no labs yet
$default_labs = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];

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
  CREATE TABLE IF NOT EXISTS pc_software (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    lab_room   TEXT NOT NULL,
    pc_number  INTEGER NOT NULL,
    software   TEXT NOT NULL,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    UNIQUE(lab_room, pc_number, software)
  );
");

// Migrate old is_enabled column to status if needed
try {
    $db->exec("ALTER TABLE lab_pcs ADD COLUMN status TEXT NOT NULL DEFAULT 'available'");
} catch (Exception $e) {}
// Sync old is_enabled -> status
$db->exec("UPDATE lab_pcs SET status='available' WHERE is_enabled=1 AND (status IS NULL OR status='')");
$db->exec("UPDATE lab_pcs SET status='disabled' WHERE is_enabled=0 AND (status IS NULL OR status='')");

// Ensure there's a lab_settings row for every known lab (so toggles are persistent and visible)
// If no lab_settings rows exist, seed with defaults
$cnt = (int)$db->query("SELECT COUNT(*) as c FROM lab_settings")->fetchColumn();
if ($cnt === 0) {
  $ins = $db->prepare("INSERT OR IGNORE INTO lab_settings (lab_room, is_enabled) VALUES (?, 1)");
  foreach ($default_labs as $r) { $ins->execute([$r]); }
}
// Load lab list from DB (this makes Add Lab persistent without editing code)
$lab_rooms = [];
$rows = $db->query("SELECT lab_room FROM lab_settings ORDER BY lab_room")->fetchAll();
foreach ($rows as $r) $lab_rooms[] = $r['lab_room'];

// ── AJAX handlers ─────────────────────────────────────────────────────────────
// Accept POSTed AJAX even if the X-Requested-With header is missing (some proxies/clients strip it).
// We require an 'action' field to avoid accidentally running handlers on unrelated POSTs.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || isset($_POST['action']))) {
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

  // Add a new lab
  if ($action === 'add_lab') {
    $lab = trim($_POST['lab_room'] ?? '');
    if ($lab !== '') {
      try {
        // create setting row
        $db->prepare("INSERT OR IGNORE INTO lab_settings (lab_room, is_enabled) VALUES (?,1)")->execute([$lab]);
        // seed pcs for the lab (1..50)
        $ins = $db->prepare("INSERT OR IGNORE INTO lab_pcs (lab_room, pc_number, status) VALUES (?,?, 'available')");
        for ($p = 1; $p <= 50; $p++) $ins->execute([$lab, $p]);
        echo json_encode(['ok' => true]);
      } catch (Exception $e) { echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
    } else echo json_encode(['ok'=>false,'msg'=>'Invalid name']);
    exit;
  }

  // Get a distinct global list of lab software (convenience endpoint)
  if ($action === 'get_global_software') {
    $gstmt = $db->prepare("SELECT DISTINCT software, description FROM lab_software ORDER BY software");
    $gstmt->execute();
    $grows = $gstmt->fetchAll();
    echo json_encode(['ok' => true, 'global_rows' => $grows]);
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
        // Auto-assign this software to all PCs in the lab (default enabled)
        $ins = $db->prepare("INSERT OR IGNORE INTO pc_software (lab_room, pc_number, software, is_enabled) VALUES (?,?,?,1)");
        for ($p = 1; $p <= 50; $p++) {
          $ins->execute([$lab, $p, $sw]);
        }
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
      // remove lab-level software and any per-pc assignments
      $stmt = $db->prepare("SELECT lab_room, software FROM lab_software WHERE id = ? LIMIT 1");
      $stmt->execute([$id]);
      $row = $stmt->fetch();
      if ($row) {
        $lab = $row['lab_room'];
        $sw  = $row['software'];
        $db->prepare("DELETE FROM lab_software WHERE id = ?")->execute([$id]);
        $db->prepare("DELETE FROM pc_software WHERE lab_room = ? AND software = ?")->execute([$lab, $sw]);
      }
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

  // Get software list for a PC (admin JS)
  if ($action === 'get_pc_software') {
    $lab = $_POST['lab_room'] ?? '';
    $pc  = (int)($_POST['pc_number'] ?? 0);
    if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50) {
      // pc-specific software
      $stmt = $db->prepare("SELECT id, software, is_enabled FROM pc_software WHERE lab_room=? AND pc_number=? ORDER BY software");
      $stmt->execute([$lab, $pc]);
      $pc_rows = $stmt->fetchAll();
      // lab-level software (available to assign)
      $lstmt = $db->prepare("SELECT id, software, description FROM lab_software WHERE lab_room=? ORDER BY software");
      $lstmt->execute([$lab]);
      $lab_rows = $lstmt->fetchAll();
      // If there are no explicit lab_software rows (data inconsistency or legacy),
      // fall back to deriving available software from any pc_software records for this lab.
      if (empty($lab_rows)) {
        $fallback = $db->prepare("SELECT DISTINCT software FROM pc_software WHERE lab_room=? ORDER BY software");
        $fallback->execute([$lab]);
        $frows = $fallback->fetchAll();
        $lab_rows = [];
        foreach ($frows as $fr) {
          $lab_rows[] = ['id' => null, 'software' => $fr['software'], 'description' => ''];
        }
        // Persist these as lab-level software entries so future calls return them directly
        try {
          $insLab = $db->prepare("INSERT OR IGNORE INTO lab_software (lab_room, software, description) VALUES (?,?,?)");
          foreach ($lab_rows as $lr) {
            $insLab->execute([$lab, $lr['software'], $lr['description'] ?? '']);
          }
          // re-query to get real ids if any were inserted
          $lstmt->execute([$lab]);
          $lab_rows = $lstmt->fetchAll();
        } catch (Exception $e) {
          // ignore migration errors and fall back to derived list
        }
      }
      // If still empty, as a convenience show globally-known lab software (distinct across all labs)
      if (empty($lab_rows)) {
        $gstmt = $db->prepare("SELECT DISTINCT software, description FROM lab_software ORDER BY software");
        $gstmt->execute();
        $grows = $gstmt->fetchAll();
        foreach ($grows as $gr) {
          $lab_rows[] = ['id' => null, 'software' => $gr['software'], 'description' => $gr['description'] ?? ''];
        }
      }
      echo json_encode(['ok' => true, 'pc_rows' => $pc_rows, 'lab_rows' => $lab_rows]);
    } else {
      echo json_encode(['ok' => false]);
    }
    exit;
  }

  // Toggle software on/off for a PC
  if ($action === 'toggle_pc_software') {
    $lab = $_POST['lab_room'] ?? '';
    $pc  = (int)($_POST['pc_number'] ?? 0);
    $sw  = trim($_POST['software'] ?? '');
    $val = (int)($_POST['is_enabled'] ?? 1);
    if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50 && $sw !== '') {
      try {
        $db->prepare("INSERT INTO pc_software (lab_room, pc_number, software, is_enabled) VALUES (?,?,?,?)
          ON CONFLICT(lab_room, pc_number, software) DO UPDATE SET is_enabled=excluded.is_enabled")
           ->execute([$lab, $pc, $sw, $val]);
        echo json_encode(['ok' => true]);
      } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
      }
    } else {
      echo json_encode(['ok' => false]);
    }
    exit;
  }

  // Assign or unassign a lab-level software to/from a PC
  if ($action === 'assign_pc_software') {
    $lab = $_POST['lab_room'] ?? '';
    $pc  = (int)($_POST['pc_number'] ?? 0);
    $sw  = trim($_POST['software'] ?? '');
    $assign = isset($_POST['assign']) ? (int)$_POST['assign'] : 1;
    if (in_array($lab, $lab_rooms) && $pc >= 1 && $pc <= 50 && $sw !== '') {
      try {
        if ($assign) {
          $db->prepare("INSERT OR IGNORE INTO pc_software (lab_room, pc_number, software, is_enabled) VALUES (?,?,?,1)")
             ->execute([$lab, $pc, $sw]);
        } else {
          $db->prepare("DELETE FROM pc_software WHERE lab_room=? AND pc_number=? AND software=?")
             ->execute([$lab, $pc, $sw]);
        }
        echo json_encode(['ok' => true]);
      } catch (Exception $e) {
        echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
      }
    } else {
      echo json_encode(['ok' => false]);
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
      background: white; min-width: 0; position: relative; overflow: hidden;
      /* extra left padding to make room for the accent bar */
      padding-left: 22px;
      transition: border-color 0.3s, background 0.3s, transform 0.12s;
    }
    /* accent bar on the left as a rounded pseudo-element (keeps rounded corners) */
    .lab-toggle-card::before {
      content: '';
      position: absolute; left: 8px; top: 6px; bottom: 6px; width: 8px;
      background: #86efac; border-top-left-radius: 10px; border-bottom-left-radius: 10px;
      box-shadow: 0 2px 8px rgba(134,239,172,0.06);
      transition: background 0.18s, transform 0.12s;
    }
    .lab-toggle-card.lab-disabled { background: rgba(220,38,38,0.03); }
    .lab-toggle-card.lab-disabled::before { background: #dc2626; box-shadow: 0 2px 8px rgba(220,38,38,0.06); }
    .lab-toggle-card:hover { transform: translateY(-3px); }
    .lab-toggle-info { min-width: 0; flex: 1; }
    .lab-toggle-name { font-weight: 700; font-size: 0.92rem; color: #1a2535; white-space: nowrap; }
    .lab-toggle-state { font-size: 0.75rem; font-weight: 600; margin-top: 3px; white-space: nowrap; }
    .lab-toggle-state.state-enabled  { color: #16a34a; }
    .lab-toggle-state.state-disabled { color: #dc2626; }
    .lab-btns { display: flex; gap: 6px; flex-shrink: 0; }
    .lab-btn {
      padding: 8px 18px; border-radius: 8px; border: 2px solid transparent;
      font-size: 0.8rem; font-weight: 700; cursor: pointer;
      transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease, background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
      white-space: nowrap; opacity: 1; -webkit-tap-highlight-color: transparent;
    }
    /* Default (not active) state - outlined */
    .lab-btn.enable  { background: white; color: #15803d; border-color: #86efac; }
    .lab-btn.enable:hover,
    .lab-btn.enable:focus { background: rgba(34,197,94,0.12); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(22,163,74,0.06); }
    .lab-btn.disable { background: white; color: #b91c1c; border-color: #fca5a5; }
    .lab-btn.disable:hover,
    .lab-btn.disable:focus { background: rgba(224,53,53,0.10); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.05); }
    .lab-btn:active { transform: translateY(0); box-shadow: 0 4px 10px rgba(0,0,0,0.08); }
    /* Active (currently selected) state - solid fill */
    .lab-btn.enable.active-state  { background: #16a34a; color: white; border-color: #16a34a; cursor: default; pointer-events: none; transform: none; box-shadow: 0 6px 18px rgba(22,163,74,0.08); }
    .lab-btn.disable.active-state { background: #dc2626; color: white; border-color: #dc2626; cursor: default; pointer-events: none; transform: none; box-shadow: 0 6px 18px rgba(220,38,38,0.08); }
    .lab-btn:focus { outline: 3px solid rgba(10,77,140,0.12); outline-offset: 2px; }

    /* Make modal action buttons visibly interactive too */
    #pcSwModal button { transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.12s ease; }
    #pcSwModal button:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(10,77,140,0.08); }
    #pcSwModal button:active { transform: translateY(0); box-shadow: 0 4px 8px rgba(0,0,0,0.06); }

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
      position: relative; /* allow absolute toolbar inside without affecting layout */
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
      /* Make the toolbar overlay inside the PC card so it does not reflow other content */
      position: absolute; left: 0; right: 0; top: 56px; z-index: 200;
      align-items: center; flex-wrap: wrap; gap: 8px; padding: 10px 20px;
      background: rgba(10,77,140,0.04);
      border-top: 1px solid rgba(204,222,237,0.5);
      border-bottom: 1px solid rgba(204,222,237,0.5);
      transform-origin: top center; transform: translateY(-6px) scaleY(0.98);
      opacity: 0; pointer-events: none; transition: opacity 0.18s ease, transform 0.14s ease;
    }
    .pc-sel-toolbar.active { opacity: 1; transform: translateY(0) scaleY(1); pointer-events: auto; }
    .pc-sel-count {
      font-size: 0.8rem; font-weight: 700; color: #0a4d8c; margin-right: 4px;
    }

    /* Dark mode adjustments for select-toolbar: make overlay solid, legible and above other elements */
    html.dark-theme .pc-sel-toolbar {
      background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.02)) !important;
      color: #ffffff !important;
      box-shadow: 0 12px 34px rgba(0,0,0,0.6) !important;
      border-top: 1px solid rgba(255,255,255,0.06) !important;
      border-bottom: 1px solid rgba(255,255,255,0.06) !important;
      z-index: 10050 !important;
      backdrop-filter: none !important;
      padding: 10px 18px !important;
    }
    html.dark-theme .pc-sel-toolbar .pc-sel-count { color: #ffffff !important; }
    /* default toolbar buttons: light translucent background with white text */
    html.dark-theme .pc-sel-toolbar .pc-sel-btn {
      background: rgba(255,255,255,0.06) !important;
      color: #0b1220 !important;
      border-radius: 7px !important;
      padding: 6px 12px !important;
      font-weight: 700 !important;
    }
    /* ensure colored action buttons remain colored and visible */
    html.dark-theme .pc-sel-toolbar .pc-sel-btn.avail { background: #16a34a !important; color: #ffffff !important; }
    html.dark-theme .pc-sel-toolbar .pc-sel-btn.dis   { background: #dc2626 !important; color: #ffffff !important; }
    html.dark-theme .pc-sel-toolbar .pc-sel-btn.maint { background: #d97706 !important; color: #ffffff !important; }
    /* make the cancel button a light pill with dark text so it's readable */
    html.dark-theme .pc-sel-toolbar .pc-sel-btn.cancel {
      background: #ffffff !important;
      color: #0b1220 !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.35) !important;
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
  <style>
    /* Page-specific dark theme overrides (applies when documentElement has .dark-theme) */
  :root { --ds-bg: #0b1220; --ds-surface: #0f1724; --ds-panel: #0c1622; --ds-muted: #9aa5b4; --ds-text: #ffffff; --ds-border: rgba(255,255,255,0.04); --accent-soft: rgba(74,163,255,0.06); }
    html.dark-theme, .dark-theme {
      background-color: var(--ds-bg) !important;
      color: var(--ds-text) !important;
    }

    /* Main containers and cards */
  .dark-theme .admin-main { background: transparent; color: var(--ds-text); }
    .dark-theme .admin-card,
    .dark-theme .pc-mgmt-card,
    .dark-theme .pc-modal,
    .dark-theme #bulkAssignModal > div > div,
    .dark-theme #pcSwModal > div > div {
      background: linear-gradient(180deg, var(--ds-panel), var(--ds-surface)) !important;
      color: var(--ds-text) !important;
      border-color: var(--ds-border) !important;
      box-shadow: 0 8px 30px rgba(2,6,23,0.6) !important;
    }

    /* Lab toggle card */
    .dark-theme .lab-toggle-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.03) !important;
    }
    .dark-theme .lab-toggle-card::before { box-shadow: none; }
    .dark-theme .lab-toggle-card.lab-disabled { background: rgba(220,38,38,0.06) !important; }
    .dark-theme .lab-toggle-name, .dark-theme .lab-toggle-state { color: var(--ds-text) !important; }

    /* Table and forms */
    .dark-theme .sw-add-form,
    .dark-theme .sw-filter-bar { border-color: rgba(255,255,255,0.03) !important; }
    .dark-theme .sw-add-form select, .dark-theme .sw-add-form input,
    .dark-theme .pc-lab-select {
      /* use a subtle dark panel background so dropdowns are readable (not fully transparent) */
      background: rgba(255,255,255,0.03) !important;
      color: var(--ds-text) !important;
      border-color: rgba(255,255,255,0.04) !important;
      -webkit-appearance: none; appearance: none;
    }
    .dark-theme .sw-table th { background: rgba(255,255,255,0.02) !important; color: var(--ds-text) !important; }
    .dark-theme .sw-table td { background: transparent !important; color: var(--ds-text) !important; border-bottom-color: rgba(255,255,255,0.03) !important; }

    /* PC grid and tiles */
    .dark-theme .pc-grid { background: transparent !important; }
    .dark-theme .pc-tile { box-shadow: none !important; border-color: rgba(255,255,255,0.06) !important; color: var(--ds-text) !important; }
    .dark-theme .pc-tile.available   { background: rgba(34,197,94,0.08) !important; color: #a7f3d0 !important; }
    .dark-theme .pc-tile.disabled    { background: rgba(224,53,53,0.06) !important; color: #fca5a5 !important; }
    .dark-theme .pc-tile.maintenance { background: rgba(251,191,36,0.06) !important; color: #ffd8a8 !important; }
    .dark-theme .pc-tile.occupied    { background: rgba(167,139,250,0.06) !important; color: #d6bcfa !important; }
    .dark-theme .pc-tile.selected    { outline-color: rgba(74,163,255,0.45) !important; }

    /* Context menu, legend, toast */
    .dark-theme .pc-ctx-menu { background: var(--ds-surface) !important; border-color: var(--ds-border) !important; color: var(--ds-text) !important; }
    .dark-theme .pc-ctx-item:hover { background: rgba(255,255,255,0.02) !important; }
    .dark-theme .pc-legend-item { color: var(--ds-muted) !important; }
    .dark-theme .pc-stat-box { background: rgba(255,255,255,0.02) !important; border-color: rgba(255,255,255,0.03) !important; }
    .dark-theme .toast-fixed { background: rgba(255,255,255,0.06) !important; color: var(--ds-text) !important; box-shadow: 0 6px 24px rgba(2,6,23,0.6) !important; }

    /* Modals backdrops are already dark; force modal surfaces darker */
    .dark-theme #pcSwModal .pc-modal, .dark-theme #bulkAssignModal > div > div { background: var(--ds-surface) !important; }
    .dark-theme .pc-modal button, .dark-theme #bulkAssignModal button { border-color: rgba(255,255,255,0.04) !important; }

    /* Badges and labels */
    .dark-theme .lab-badge { background: rgba(74,163,255,0.08) !important; color: var(--ds-text) !important; border: 1px solid rgba(255,255,255,0.02) !important; }

  /* make theme changes smooth */
  .dark-theme * { transition: background-color 180ms ease, color 180ms ease, border-color 180ms ease; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Lab Settings</h2>

  <!-- ── Enable / Disable Labs ───────────────────────────────────────────────── -->
  <div class="admin-card" style="margin-bottom:22px;">
    <div class="admin-card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div style="flex:1;">Enable / Disable Reservation per Lab</div>
      <div style="display:flex;gap:8px;align-items:center;">
        <input id="newLabName" type="text" placeholder="New lab name (e.g. Lab 532)" style="padding:6px 8px;border-radius:8px;border:1px solid #ccddee;min-width:180px;" />
        <button class="admin-btn blue" onclick="addLab()" style="padding:6px 10px;">+ Add Lab</button>
      </div>
    </div>
    <div class="lab-toggle-grid">
      <?php foreach ($lab_rooms as $room):
        $enabled = $lab_enabled[$room] ?? 1;
        $key = md5($room);
      ?>
      <div class="lab-toggle-card <?= $enabled ? '' : 'lab-disabled' ?>" id="ltcard-<?= $key ?>">
        <div class="lab-toggle-info">
          <div class="lab-toggle-name"><?= htmlspecialchars($room) ?></div>
          <div class="lab-toggle-state <?= $enabled ? 'state-enabled' : 'state-disabled' ?>" id="lts-<?= $key ?>"><?= $enabled ? '✓ Reservations Enabled' : '✕ Reservations Disabled' ?></div>
        </div>
        <div class="lab-btns">
          <button class="lab-btn enable <?= $enabled ? 'active-state' : '' ?>"
            id="lbe-<?= $key ?>"
            onclick='toggleLab(<?= json_encode($room) ?>, <?= json_encode($key) ?>, 1)'>Enable</button>
          <button class="lab-btn disable <?= !$enabled ? 'active-state' : '' ?>"
            id="lbd-<?= $key ?>"
            onclick='toggleLab(<?= json_encode($room) ?>, <?= json_encode($key) ?>, 0)'>Disable</button>
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
  <button class="pc-sel-btn neutral" onclick="openAssignToSelectedModal()">Assign Software</button>
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

<!-- PC Software Modal -->
<div id="pcSwModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:100000;align-items:center;justify-content:center;"> 
  <div style="width:600px;max-width:96%;margin:0 auto;display:flex;align-items:center;justify-content:center;height:100%;">
  <div class="pc-modal" style="width:100%;max-width:640px;background:#ffffff;padding:18px;border-radius:14px;box-shadow:0 12px 40px rgba(10,77,140,0.18);max-height:92vh;overflow:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <div style="font-weight:800;color:#0a4d8c;">PC Software — <span id="pcSwTitle"></span></div>
        <div style="display:flex;gap:8px;align-items:center;">
          <button class="primary-btn" onclick="assignAllLabSoftwareToPc()" style="background:#0a74ff;color:white;border:none;padding:6px 10px;border-radius:8px;">Assign all</button>
          <button class="primary-btn" onclick="unassignAllLabSoftwareFromPc()" style="background:#f97316;color:white;border:none;padding:6px 10px;border-radius:8px;">Unassign all</button>
          <button class="neutral-btn" onclick="closePcSwModal()" style="background:#f0f4f8;border:1px solid #e2e8f0;padding:6px 10px;border-radius:8px;">Close</button>
        </div>
      </div>
  <div style="display:flex;gap:12px;pointer-events:auto;">
        <div style="flex:1;">
          <div style="font-weight:700;margin-bottom:6px;color:#0a4d8c;">Assigned to this PC</div>
          <div id="pcSwList" style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow:auto;padding-right:6px;border-right:1px solid #f1f5f9;padding-right:12px;">
          </div>
        </div>
        <div style="width:220px;">
          <div style="font-weight:700;margin-bottom:6px;color:#0a4d8c;">Lab Software (assign)</div>
          <div id="labSwList" style="display:flex;flex-direction:column;gap:8px;max-height:360px;overflow:auto;padding-left:12px;"></div>
        </div>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;pointer-events:auto;">
        <button type="button" role="button" onclick="closePcSwModal()" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;">Done</button>
      </div>
    </div>
  </div>
</div>

<!-- Bulk Assign Modal (for multiple selected PCs) -->
<div id="bulkAssignModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:100001;align-items:center;justify-content:center;">
  <div style="width:720px;max-width:96%;margin:0 auto;display:flex;align-items:center;justify-content:center;height:100%;">
    <div style="width:100%;background:#fff;padding:18px;border-radius:14px;box-shadow:0 12px 40px rgba(10,77,140,0.18);max-height:92vh;overflow:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
        <div style="font-weight:800;color:#0a4d8c;">Assign Software to Selected PCs — <span id="bulkAssignTitle"></span></div>
        <div>
          <button class="neutral-btn" onclick="closeBulkAssignModal()" style="background:#f0f4f8;border:1px solid #e2e8f0;padding:6px 10px;border-radius:8px;">Close</button>
        </div>
      </div>
      <div style="display:flex;gap:12px;">
        <div style="flex:1;">
          <div style="font-weight:700;margin-bottom:8px;color:#0a4d8c;">Lab Software</div>
          <div id="bulkLabSwList" style="display:flex;flex-direction:column;gap:8px;max-height:420px;overflow:auto;padding-right:6px;border-right:1px solid #f1f5f9;padding-right:12px;"></div>
        </div>
        <div style="width:260px;padding-left:12px;">
          <div style="font-weight:700;margin-bottom:8px;color:#0a4d8c;">Selected PCs</div>
          <div id="bulkSelectedList" style="font-size:0.9rem;color:#1a2535;max-height:420px;overflow:auto;"></div>
        </div>
      </div>
      <div style="margin-top:12px;display:flex;gap:8px;justify-content:flex-end;">
  <button type="button" class="primary-btn" onclick="applyAssignToSelected()" style="padding:8px 12px;border-radius:8px;border:none;background:#16a34a;color:white;">Apply to Selected</button>
  <button type="button" class="neutral-btn" onclick="closeBulkAssignModal()" style="padding:8px 12px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;">Cancel</button>
      </div>
    </div>
  </div>
</div>

<style>
  /* Strong final dark-mode overrides (ensures inline-styled panels get themed) */
  html.dark-theme, .dark-theme {
    background: #0b1220 !important;
    color: #ffffff !important;
  }
  html.dark-theme body.admin-page, html.dark-theme .admin-main { background: #0b1220 !important; }
  html.dark-theme .admin-card, html.dark-theme .pc-mgmt-card, html.dark-theme .pc-modal,
  html.dark-theme #bulkAssignModal > div > div, html.dark-theme #pcSwModal .pc-modal,
  html.dark-theme .sw-add-form, html.dark-theme .sw-filter-bar,
  html.dark-theme .sw-table, html.dark-theme .pc-ctx-menu {
    background: #0f1724 !important;
    color: #ffffff !important; /* ensure pure white text for high contrast */
    border-color: rgba(255,255,255,0.04) !important;
  }

  /* Ensure selects and inputs on admin page are solid and readable */
  html.dark-theme select,
  html.dark-theme #swLab,
  html.dark-theme .sw-add-form select,
  html.dark-theme .pc-lab-select,
  html.dark-theme .sw-add-form input {
    background: rgba(255,255,255,0.03) !important;
    color: #ffffff !important;
    border-color: rgba(255,255,255,0.04) !important;
    -webkit-appearance: none !important;
    appearance: none !important;
    padding-right: 36px !important;
  }

  /* Option list colors (where browsers allow styling) */
  html.dark-theme select option {
    background: #0f1724 !important;
    color: #ffffff !important;
  }

  /* Add white arrow for select controls on admin page (SVG data URI) */
  html.dark-theme select,
  html.dark-theme #swLab,
  html.dark-theme .sw-add-form select,
  html.dark-theme .pc-lab-select {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23ffffff' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>") !important;
    background-repeat: no-repeat !important;
    background-position: right 10px center !important;
  }

  html.dark-theme .pc-grid, html.dark-theme .pc-legend, html.dark-theme .pc-stat-box { background: transparent !important; }
  html.dark-theme .pc-tile { background-clip: padding-box !important; }
  html.dark-theme .pc-tile.available { background: rgba(74,163,255,0.03) !important; }
  html.dark-theme .pc-tile.disabled { background: rgba(220,38,38,0.06) !important; color: #ffc7c7 !important; border-color: rgba(220,38,38,0.12) !important; }
  /* Ensure the Set Available action button is green and clear in dark mode */
  html.dark-theme .pc-act-btn.avail { background: #16a34a !important; color: #ffffff !important; box-shadow: 0 6px 14px rgba(22,163,74,0.14) !important; }
  /* Ensure the Set Disabled action button is red and clear in dark mode */
  html.dark-theme .pc-act-btn.dis { background: #dc2626 !important; color: #ffffff !important; box-shadow: 0 6px 14px rgba(220,38,38,0.12) !important; }
  html.dark-theme .lab-badge { background: rgba(255,255,255,0.02) !important; color: #ffffff !important; }
  html.dark-theme .del-btn { background: rgba(220,38,38,0.06) !important; color: #ffb4b4 !important; }
  html.dark-theme button { color: inherit !important; }
  /* Make disable lab button text explicitly red for readability */
  html.dark-theme .lab-btn.disable { background: rgba(220,38,38,0.06) !important; color: #ff6b6b !important; border-color: rgba(220,38,38,0.12) !important; }

  /* Select-mode cancel button: make background slightly light and text dark so it stands out */
  html.dark-theme .pc-sel-btn.cancel {
    background: rgba(255,255,255,0.08) !important;
    color: #0b1220 !important;
    border-color: rgba(255,255,255,0.06) !important;
  }

  /* Modal (pcSwModal / bulkAssignModal) — ensure neutral buttons (Close/Done/Cancel) are visible in dark mode */
  html.dark-theme #pcSwModal .pc-modal button,
  html.dark-theme #bulkAssignModal button {
    /* neutral default: slightly light background with dark text for contrast */
    background: rgba(255,255,255,0.06) !important;
    color: #0b1220 !important;
    border-color: rgba(255,255,255,0.06) !important;
  }
  /* Preserve colored action buttons (Assign all / Unassign all / Apply) with white text */
  html.dark-theme #pcSwModal .pc-modal button[onclick*="assignAllLabSoftwareToPc"],
  html.dark-theme #pcSwModal .pc-modal button[onclick*="unassignAllLabSoftwareFromPc"],
  html.dark-theme #bulkAssignModal button[onclick*="applyAssignToSelected"],
  html.dark-theme #pcSwModal .pc-modal button[onclick*="assignAllLabSoftwareToPc"] {
    color: #ffffff !important;
  }
</style>
<script>
console.log('AdminSoftware JS loaded');
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
  // safer post: return parsed JSON or an object with raw text on failure
  return fetch('AdminSoftware.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
    body: new URLSearchParams(data)
  }).then(r => {
    return r.text().then(txt => {
      try {
        const parsed = JSON.parse(txt);
        console.log('POST', data, '->', parsed);
        return parsed;
      } catch (e) {
        console.error('Failed to parse JSON response for POST', data, txt);
        return { ok: false, _raw: txt };
      }
    });
  });
}

// ── Lab toggle ────────────────────────────────────────────────────────────────
function toggleLab(lab, key, val) {
  console.log('toggleLab called', { lab, key, val });
  const eBtn = document.getElementById('lbe-' + key);
  const dBtn = document.getElementById('lbd-' + key);
  const lbl  = document.getElementById('lts-' + key);
  const card = eBtn ? eBtn.closest('.lab-toggle-card') : null;
  if (!eBtn || !dBtn) { showToast('✕ Could not find buttons.'); return; }
  // Normalize value (may be string when passed from HTML attributes)
  const isEnabled = Number(val) === 1;

  eBtn.disabled = true;
  dBtn.disabled = true;
  showToast('⏳ Saving...');

  post({ action: 'toggle_lab', lab_room: lab, is_enabled: isEnabled ? 1 : 0 }).then(r => {
    console.log('toggleLab response', r);
    eBtn.disabled = false;
    dBtn.disabled = false;
    if (!r || !r.ok) { showToast('✕ Error saving.'); return; }

    // Update label text and color
    if (lbl) {
      lbl.textContent = isEnabled ? '✓ Reservations Enabled' : '✕ Reservations Disabled';
      lbl.className = 'lab-toggle-state ' + (isEnabled ? 'state-enabled' : 'state-disabled');
    }

    // Update active-state on buttons (active-state = solid fill = current state)
    eBtn.classList.toggle('active-state', isEnabled);
    dBtn.classList.toggle('active-state', !isEnabled);

    // Update card left border color
    const cardEl = document.getElementById('ltcard-' + key);
    if (cardEl) {
      cardEl.classList.toggle('lab-disabled', !isEnabled);
    }

    showToast(isEnabled ? '✓ ' + lab + ' — Reservations Enabled' : '✕ ' + lab + ' — Reservations DISABLED');
  }).catch(() => {
    eBtn.disabled = false;
    dBtn.disabled = false;
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
  // Single click when not in select mode → open PC Software modal
  openPcSoftwareModal(currentLab, pc);
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

// ── PC Software modal ───────────────────────────────────────────────────────
let pcSwCurrent = { lab: null, pc: null };
function openPcSoftwareModal(lab, pc) {
  pcSwCurrent = { lab, pc };
  document.getElementById('pcSwTitle').textContent = lab + ' — PC ' + pc;
  document.getElementById('pcSwList').innerHTML = '<div style="color:#9aa5b4;padding:12px">Loading…</div>';
  document.getElementById('pcSwModal').style.display = 'flex';
  post({ action: 'get_pc_software', lab_room: lab, pc_number: pc }).then(r => {
    if (!r || !r.ok) {
      document.getElementById('pcSwList').innerHTML = '<div style="color:#dc2626;padding:12px">Error loading software.</div>';
      return;
    }
    // server returns { ok: true, pc_rows: [...], lab_rows: [...] }
    // If this PC is disabled, show notice and render but disable controls
    const status = pcStatuses[pc] || null;
    if (status === 'disabled') {
      r._pc_disabled = true;
    }
    renderPcSoftware(r);
  }).catch(() => {
    document.getElementById('pcSwList').innerHTML = '<div style="color:#dc2626;padding:12px">Network error.</div>';
  });
}

function closePcSwModal() {
  document.getElementById('pcSwModal').style.display = 'none';
  pcSwCurrent = { lab: null, pc: null };
}

function renderPcSoftware(data) {
  const pcRows = data.pc_rows || [];
  const labRows = data.lab_rows || [];
  const container = document.getElementById('pcSwList');
  const labContainer = document.getElementById('labSwList');

  // PC assigned software
  container.innerHTML = '';
  if (data._pc_disabled) {
    container.innerHTML = '<div style="color:#dc2626;padding:12px">This PC is disabled. Software cannot be changed.</div>';
  }
  if (pcRows.length === 0) {
    container.innerHTML = '<div style="color:#9aa5b4;padding:12px">No software assigned to this PC.</div>';
  } else {
    pcRows.forEach(r => {
      const item = document.createElement('div');
      item.style.display = 'flex'; item.style.alignItems = 'center'; item.style.justifyContent = 'space-between';
      item.style.padding = '8px'; item.style.border = '1px solid #eef6fb'; item.style.borderRadius = '8px';
      const left = document.createElement('div'); left.style.flex = '1'; left.textContent = r.software;
      const btn = document.createElement('button');
      btn.textContent = r.is_enabled == 1 ? 'Available' : 'Unavailable';
      btn.style.padding = '6px 10px'; btn.style.borderRadius = '8px'; btn.style.border = 'none';
      btn.style.cursor = 'pointer';
      if (r.is_enabled == 1) { btn.style.background = '#16a34a'; btn.style.color = 'white'; }
      else { btn.style.background = '#f8d7da'; btn.style.color = '#902026'; }
      btn.onclick = function() {
        // If PC is disabled, prevent toggling
        if (data._pc_disabled) { showToast('✕ PC disabled — cannot change software'); return; }
        const newVal = r.is_enabled == 1 ? 0 : 1;
        post({ action: 'toggle_pc_software', lab_room: pcSwCurrent.lab, pc_number: pcSwCurrent.pc, software: r.software, is_enabled: newVal }).then(resp => {
          if (resp && resp.ok) {
            r.is_enabled = newVal;
            btn.textContent = r.is_enabled == 1 ? 'Available' : 'Unavailable';
            if (r.is_enabled == 1) { btn.style.background = '#16a34a'; btn.style.color = 'white'; }
            else { btn.style.background = '#f8d7da'; btn.style.color = '#902026'; }
            showToast('✓ Updated');
          } else {
            showToast('✕ Could not update');
          }
        }).catch(() => showToast('✕ Network error'));
      };
      if (data._pc_disabled) btn.disabled = true;
      item.appendChild(left);
      item.appendChild(btn);
      container.appendChild(item);
    });
  }

  // Lab software (assign/unassign)
  labContainer.innerHTML = '';
  if (labRows.length === 0) {
    labContainer.innerHTML = '<div style="color:#9aa5b4;padding:12px">No software available for this lab. Loading global list…</div>';
    // fetch global fallback and render it
    post({ action: 'get_global_software' }).then(gresp => {
      if (!gresp || !gresp.ok || !gresp.global_rows || gresp.global_rows.length === 0) {
        labContainer.innerHTML = '<div style="color:#9aa5b4;padding:12px">No software available for this lab.</div>';
        return;
      }
      // treat global_rows like labRows
      labRows = gresp.global_rows.map(r => ({ id: null, software: r.software, description: r.description || '' }));
      // continue to render below
      labContainer.innerHTML = '';
      labRows.forEach(l => {
        const row = document.createElement('div');
        row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.justifyContent = 'space-between';
        row.style.padding = '6px 4px';
        const lbl = document.createElement('div'); lbl.textContent = l.software; lbl.style.flex = '1';
        const cb = document.createElement('input'); cb.type = 'checkbox'; cb.style.width = '18px'; cb.style.height = '18px';
        const assigned2 = pcRows.some(p => p.software === l.software);
        cb.checked = assigned2;
        cb.onchange = function() {
          post({ action: 'assign_pc_software', lab_room: pcSwCurrent.lab, pc_number: pcSwCurrent.pc, software: l.software, assign: cb.checked ? 1 : 0 }).then(resp => {
            if (resp && resp.ok) { showToast('✓ ' + (cb.checked ? 'Assigned' : 'Unassigned')); post({ action: 'get_pc_software', lab_room: pcSwCurrent.lab, pc_number: pcSwCurrent.pc }).then(rr => { if (rr && rr.ok) renderPcSoftware(rr); }); }
            else { showToast('✕ Could not change assignment'); cb.checked = !cb.checked; }
          }).catch(() => { showToast('✕ Network error'); cb.checked = !cb.checked; });
        };
        row.appendChild(lbl);
        row.appendChild(cb);
        labContainer.appendChild(row);
      });
    }).catch(()=>{ labContainer.innerHTML = '<div style="color:#dc2626;padding:12px">Network error loading global list.</div>'; });
  } else {
    labRows.forEach(l => {
      const row = document.createElement('div');
      row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.justifyContent = 'space-between';
      row.style.padding = '6px 4px';
      const lbl = document.createElement('div'); lbl.textContent = l.software; lbl.style.flex = '1';
      const cb = document.createElement('input'); cb.type = 'checkbox'; cb.style.width = '18px'; cb.style.height = '18px';
      // Check if this lab software is assigned to pc
      const assigned = pcRows.some(p => p.software === l.software);
      cb.checked = assigned;
      cb.onchange = function() {
        post({ action: 'assign_pc_software', lab_room: pcSwCurrent.lab, pc_number: pcSwCurrent.pc, software: l.software, assign: cb.checked ? 1 : 0 }).then(resp => {
          if (resp && resp.ok) {
            showToast('✓ ' + (cb.checked ? 'Assigned' : 'Unassigned'));
            // refresh modal lists
            post({ action: 'get_pc_software', lab_room: pcSwCurrent.lab, pc_number: pcSwCurrent.pc }).then(rr => { if (rr && rr.ok) renderPcSoftware(rr); });
          } else {
            showToast('✕ Could not change assignment');
            cb.checked = !cb.checked; // revert
          }
        }).catch(() => { showToast('✕ Network error'); cb.checked = !cb.checked; });
      };
      row.appendChild(lbl);
      row.appendChild(cb);
      labContainer.appendChild(row);
    });
  }
}

function assignAllLabSoftwareToPc() {
  const lab = pcSwCurrent.lab, pc = pcSwCurrent.pc;
  if (!lab || !pc) return;
  // fetch lab rows then assign each
  post({ action: 'get_pc_software', lab_room: lab, pc_number: pc }).then(r => {
    if (!r || !r.ok) return showToast('✕ Error');
    const labRows = r.lab_rows || [];
    if (labRows.length === 0) return showToast('⚠ No lab software to assign');
    let promises = labRows.map(l => post({ action: 'assign_pc_software', lab_room: lab, pc_number: pc, software: l.software, assign: 1 }));
    Promise.all(promises).then(results => {
      showToast('✓ Assigned');
      post({ action: 'get_pc_software', lab_room: lab, pc_number: pc }).then(rr => { if (rr && rr.ok) renderPcSoftware(rr); });
    }).catch(() => showToast('✕ Network error'));
  });
}

function unassignAllLabSoftwareFromPc() {
  const lab = pcSwCurrent.lab, pc = pcSwCurrent.pc;
  if (!lab || !pc) return;
  post({ action: 'get_pc_software', lab_room: lab, pc_number: pc }).then(r => {
    if (!r || !r.ok) return showToast('✕ Error');
    const pcRows = r.pc_rows || [];
    if (pcRows.length === 0) return showToast('⚠ No software assigned');
    let promises = pcRows.map(p => post({ action: 'assign_pc_software', lab_room: lab, pc_number: pc, software: p.software, assign: 0 }));
    Promise.all(promises).then(results => {
      showToast('✓ Unassigned');
      post({ action: 'get_pc_software', lab_room: lab, pc_number: pc }).then(rr => { if (rr && rr.ok) renderPcSoftware(rr); });
    }).catch(() => showToast('✕ Network error'));
  });
}

// ── Bulk assign (for multiple selected PCs) ─────────────────────────────────
function openAssignToSelectedModal() {
  if (selectedPcs.size === 0) { showToast('⚠ No PCs selected.'); return; }
  const pcs = Array.from(selectedPcs).sort((a,b)=>a-b);
  document.getElementById('bulkAssignTitle').textContent = currentLab + ' — ' + pcs.length + ' PC' + (pcs.length !== 1 ? 's' : '');
  // show selected list
  document.getElementById('bulkSelectedList').textContent = pcs.map(p => 'PC' + p).join(', ');
  document.getElementById('bulkLabSwList').innerHTML = '<div style="color:#9aa5b4;padding:12px">Loading…</div>';
  document.getElementById('bulkAssignModal').style.display = 'flex';

  // Fetch pc software for the first selected PC to get union data, and also get lab rows
  const firstPc = pcs[0];
  post({ action: 'get_pc_software', lab_room: currentLab, pc_number: firstPc }).then(r => {
    if (!r || !r.ok) return document.getElementById('bulkLabSwList').innerHTML = '<div style="color:#dc2626;padding:12px">Error loading software.</div>';
    // r.lab_rows contains lab-level software; r.pc_rows contains assignments for firstPc
    // We need to compute assignment counts across all selected PCs for each lab software
    const labRows = r.lab_rows || [];
    if (labRows.length === 0) return document.getElementById('bulkLabSwList').innerHTML = '<div style="color:#9aa5b4;padding:12px">No lab software to assign.</div>';
    // For each selected PC fetch its pc_rows to count assignments
    const fetches = pcs.map(p => post({ action: 'get_pc_software', lab_room: currentLab, pc_number: p }));
    Promise.all(fetches).then(all => {
      // all is array of responses
      const assignedMap = {}; // software -> count of selected PCs that have it assigned
      labRows.forEach(l => assignedMap[l.software] = 0);
      all.forEach(resp => {
        if (resp && resp.ok) {
          const pr = resp.pc_rows || [];
          pr.forEach(item => { if (assignedMap.hasOwnProperty(item.software)) assignedMap[item.software]++; });
        }
      });
      renderBulkAssignList(labRows, assignedMap, pcs.length);
    }).catch(() => document.getElementById('bulkLabSwList').innerHTML = '<div style="color:#dc2626;padding:12px">Network error.</div>');
  }).catch(() => document.getElementById('bulkLabSwList').innerHTML = '<div style="color:#dc2626;padding:12px">Network error.</div>');
}

function closeBulkAssignModal() { document.getElementById('bulkAssignModal').style.display = 'none'; }

function renderBulkAssignList(labRows, assignedMap, totalPcs) {
  const container = document.getElementById('bulkLabSwList');
  container.innerHTML = '';
  labRows.forEach(l => {
    const row = document.createElement('div');
    row.style.display = 'flex'; row.style.alignItems = 'center'; row.style.justifyContent = 'space-between';
    row.style.padding = '6px 4px';
    const lbl = document.createElement('div'); lbl.textContent = l.software; lbl.style.flex = '1';
    const cb = document.createElement('input'); cb.type = 'checkbox'; cb.style.width = '18px'; cb.style.height = '18px';
    const count = assignedMap[l.software] || 0;
    if (count === 0) { cb.checked = false; cb.indeterminate = false; }
    else if (count === totalPcs) { cb.checked = true; cb.indeterminate = false; }
    else { cb.checked = false; cb.indeterminate = true; }
    cb.dataset.software = l.software;
    row.appendChild(lbl);
    row.appendChild(cb);
    container.appendChild(row);
  });
}

function applyAssignToSelected() {
  const pcs = Array.from(selectedPcs);
  if (pcs.length === 0) { showToast('⚠ No PCs selected.'); return; }
  const checkboxes = Array.from(document.getElementById('bulkLabSwList').querySelectorAll('input[type=checkbox]'));
  const tasks = [];
  checkboxes.forEach(cb => {
    const sw = cb.dataset.software;
    const assign = cb.checked ? 1 : 0; // indeterminate treated as unchecked (no-op)
    // For each selected PC post assign/unassign
    pcs.forEach(pc => tasks.push(post({ action: 'assign_pc_software', lab_room: currentLab, pc_number: pc, software: sw, assign })));
  });
  showToast('⏳ Applying...');
  Promise.all(tasks).then(all => {
    // If any failed, warn; else success
    const anyFail = all.some(r => !r || !r.ok);
    if (anyFail) showToast('✕ Some changes failed.'); else showToast('✓ Assigned to selected PCs');
    // Refresh pc tiles or modal state
    closeBulkAssignModal();
  }).catch(() => { showToast('✕ Network error.'); });
}

// Add a new lab via AJAX
function addLab() {
  const name = document.getElementById('newLabName').value.trim();
  if (!name) return alert('Enter a lab name');
  post({ action: 'add_lab', lab_room: name }).then(r => {
    if (r && r.ok) { showToast('✓ Lab added'); setTimeout(() => location.reload(), 800); }
    else alert(r.msg || 'Could not add lab');
  }).catch(() => alert('Network error'));
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>