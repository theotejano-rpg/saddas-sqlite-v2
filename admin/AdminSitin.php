<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();

if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $db->prepare("UPDATE sitin_logs SET status='active' WHERE id=? AND status='pending'")->execute([(int)$_GET['approve']]);
    header('Location: AdminSitin.php?msg=approved'); exit;
}

if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $db->prepare("UPDATE sitin_logs SET status='cancelled' WHERE id=? AND status='pending'")->execute([(int)$_GET['reject']]);
    header('Location: AdminSitin.php?msg=rejected'); exit;
}

if (isset($_GET['end']) && is_numeric($_GET['end'])) {
    $log = $db->prepare("SELECT * FROM sitin_logs WHERE id=? LIMIT 1");
    $log->execute([(int)$_GET['end']]);
    $log = $log->fetch();
    if ($log) {
        $db->prepare("UPDATE sitin_logs SET status='completed', date_out=datetime('now') WHERE id=?")->execute([(int)$_GET['end']]);
        $db->prepare("UPDATE students SET used = used + 1 WHERE id=?")->execute([$log['student_id']]);
    }
    header('Location: AdminSitin.php?msg=ended'); exit;
}

$initiate_error = $initiate_success = '';
$initiate_student = null;
$search_q = trim($_GET['sq'] ?? '');

if ($search_q !== '') {
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = ? OR first_name LIKE ? OR last_name LIKE ? LIMIT 1");
    $stmt->execute([$search_q, "%$search_q%", "%$search_q%"]);
    $initiate_student = $stmt->fetch();
    if (!$initiate_student) $initiate_error = "No student found for \"".htmlspecialchars($search_q)."\".";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['initiate_student_id'])) {
    $sid = (int)$_POST['initiate_student_id'];
    $lab = trim($_POST['lab_room'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $stu = $db->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
    $stu->execute([$sid]); $stu = $stu->fetch();
    if (!$stu) { $initiate_error = 'Student not found.'; }
    elseif (($stu['sessions'] - $stu['used']) <= 0) { $initiate_error = 'Student has no remaining sessions.'; }
    else {
        $active = $db->prepare("SELECT id FROM sitin_logs WHERE student_id=? AND status='active' LIMIT 1");
        $active->execute([$sid]);
        if ($active->fetch()) { $initiate_error = 'Student already has an active sit-in.'; }
        else {
            $db->prepare("INSERT INTO sitin_logs (student_id, lab_room, purpose, date_in, status) VALUES (?,?,?,datetime('now'),'active')")->execute([$sid, $lab, $purpose]);
            $initiate_success = "Sit-in started for {$stu['first_name']} {$stu['last_name']}! Session deducted on End.";
            $s2 = $db->prepare("SELECT * FROM students WHERE id=? LIMIT 1"); $s2->execute([$sid]);
            $initiate_student = $s2->fetch();
        }
    }
}

$msg = $_GET['msg'] ?? '';
$pending_sitins = $db->query("SELECT sl.*, s.first_name, s.last_name, s.student_id as sid, (s.sessions-s.used) as remaining FROM sitin_logs sl JOIN students s ON sl.student_id=s.id WHERE sl.status='pending' ORDER BY sl.date_in ASC")->fetchAll();
$current_sitins = $db->query("SELECT sl.*, s.first_name, s.last_name, s.student_id as sid, (s.sessions-s.used) as remaining FROM sitin_logs sl JOIN students s ON sl.student_id=s.id WHERE sl.status='active' ORDER BY sl.date_in DESC")->fetchAll();
$purposes  = ['C / C++','Java','Python','PHP / Web Development','Database (SQL)','Networking','Research / Thesis','Other'];
$lab_rooms = ['Lab 524','Lab 526','Lab 528','Lab 530'];
$nav_admin_active = 'sitin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Sit-In</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .top-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }

    .mini-card {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 14px;
      box-shadow: 0 4px 16px rgba(10,77,140,0.08);
      overflow: hidden;
    }

    .mini-card-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 16px;
      cursor: pointer;
      user-select: none;
    }

    .mini-card-header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      font-weight: 600;
      color: white;
    }

    .mini-card-toggle {
      transition: transform 0.25s;
      opacity: 0.8;
    }
    .mini-card-toggle.open { transform: rotate(180deg); }

    .mini-card-body { display: none; padding: 12px 16px; }
    .mini-card-body.open { display: block; }

    .initiate-search-row { display: flex; gap: 8px; margin-bottom: 10px; }
    .initiate-search-row input {
      flex: 1; padding: 8px 12px; border: 1.5px solid #ccdeed;
      border-radius: 8px; font-family: 'DM Sans',sans-serif; font-size: 0.85rem; outline: none;
    }

    .initiate-student-info {
      background: rgba(10,77,140,0.04); border: 1px solid rgba(10,77,140,0.1);
      border-radius: 8px; padding: 10px 14px; margin-bottom: 10px;
    }
    .initiate-student-name { font-weight: 600; font-size: 0.88rem; color: var(--ink); }
    .initiate-student-meta { font-size: 0.75rem; color: var(--ink-soft); margin-top: 2px; }

    .initiate-form-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
    .initiate-form-row > div { display: flex; flex-direction: column; gap: 4px; }
    .initiate-form-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--blue-deep); }
    .initiate-form-row select {
      padding: 7px 10px; border: 1.5px solid #ccdeed; border-radius: 8px;
      font-family: 'DM Sans',sans-serif; font-size: 0.82rem; outline: none; background: #f5f8fb; min-width: 140px;
    }

    .pending-mini-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
    .pending-mini-table th {
      padding: 8px 10px; text-align: left; background: rgba(10,77,140,0.05);
      color: var(--blue-deep); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.5px;
      border-bottom: 1.5px solid rgba(10,77,140,0.08);
    }
    .pending-mini-table td {
      padding: 9px 10px; color: var(--ink); border-bottom: 1px solid rgba(204,222,237,0.35);
      vertical-align: middle;
    }
    .pending-mini-table tr:last-child td { border-bottom: none; }

    .sitin-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
    .sitin-table th {
      padding: 11px 14px; text-align: left; background: rgba(10,77,140,0.06);
      color: var(--blue-deep); font-weight: 600; font-size: 0.68rem;
      text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid rgba(10,77,140,0.1);
    }
    .sitin-table td {
      padding: 12px 14px; color: var(--ink); border-bottom: 1px solid rgba(204,222,237,0.4);
      vertical-align: middle; white-space: nowrap;
    }
    .sitin-table tr:last-child td { border-bottom: none; }
    .sitin-table tr:hover td { background: rgba(10,77,140,0.02); }

    .inline-search-wrap {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 16px; border-bottom: 1px solid rgba(204,222,237,0.4);
    }
    .inline-search-wrap label { font-size: 0.8rem; color: var(--ink-soft); }
    .inline-search-wrap input {
      width: 200px; padding: 6px 12px; border: 1.5px solid #ccdeed;
      border-radius: 8px; font-family: 'DM Sans',sans-serif; font-size: 0.82rem; outline: none;
    }
    .sitin-table-wrap { padding: 0 16px 16px; overflow-x: auto; }

    .badge-pending   { background: rgba(232,160,32,0.12); color: #a06010; }
    .badge-active    { background: rgba(26,140,78,0.12);  color: #1a7a42; }
    .badge-completed { background: rgba(10,77,140,0.1);   color: #0a4d8c; }
    .badge-cancelled { background: rgba(208,49,45,0.1);   color: #c0392b; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Sit-In Management</h2>

  <?php if ($msg === 'approved'): ?><div class="admin-alert success" style="margin-bottom:14px;">&#10003; Sit-in approved!</div>
  <?php elseif ($msg === 'rejected'): ?><div class="admin-alert error" style="margin-bottom:14px;">&#10005; Sit-in rejected.</div>
  <?php elseif ($msg === 'ended'): ?><div class="admin-alert success" style="margin-bottom:14px;">&#10003; Session ended. 1 session deducted.</div>
  <?php endif; ?>

  <div class="top-row">

    <!-- INITIATE SIT-IN -->
    <div class="mini-card">
      <div class="mini-card-header" style="background:linear-gradient(135deg,#1a8c4e,#27ae60);" onclick="toggleCard('initiate')">
        <div class="mini-card-header-left">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
          Initiate Sit-In <span style="font-weight:400;font-size:0.72rem;opacity:0.85;">— walk-in</span>
        </div>
        <svg id="toggle-initiate" class="mini-card-toggle <?= ($search_q||$initiate_success) ? 'open':'' ?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="mini-card-body <?= ($search_q||$initiate_success) ? 'open':'' ?>" id="body-initiate">
        <?php if ($initiate_error): ?><div class="admin-alert error" style="margin-bottom:10px;font-size:0.8rem;">&#10005; <?= htmlspecialchars($initiate_error) ?></div><?php endif; ?>
        <?php if ($initiate_success): ?><div class="admin-alert success" style="margin-bottom:10px;font-size:0.8rem;">&#10003; <?= htmlspecialchars($initiate_success) ?></div><?php endif; ?>
        <form method="GET" action="AdminSitin.php" class="initiate-search-row">
          <input type="text" name="sq" value="<?= htmlspecialchars($search_q) ?>" placeholder="Student ID or name..."/>
          <button type="submit" class="admin-btn blue sm">Search</button>
        </form>
        <?php if ($initiate_student): ?>
          <div class="initiate-student-info">
            <div class="initiate-student-name"><?= htmlspecialchars($initiate_student['first_name'].' '.$initiate_student['last_name']) ?></div>
            <div class="initiate-student-meta"><?= htmlspecialchars($initiate_student['student_id']) ?> &bull; <?= htmlspecialchars($initiate_student['course']) ?> &bull; <strong><?= $initiate_student['sessions'] - $initiate_student['used'] ?> sessions left</strong></div>
          </div>
          <form method="POST" action="AdminSitin.php?sq=<?= urlencode($search_q) ?>">
            <input type="hidden" name="initiate_student_id" value="<?= $initiate_student['id'] ?>"/>
            <div class="initiate-form-row">
              <div>
                <div class="initiate-form-label">Lab Room</div>
                <select name="lab_room"><?php foreach ($lab_rooms as $r): ?><option><?= $r ?></option><?php endforeach; ?></select>
              </div>
              <div>
                <div class="initiate-form-label">Purpose</div>
                <select name="purpose"><?php foreach ($purposes as $p): ?><option><?= $p ?></option><?php endforeach; ?></select>
              </div>
              <button type="submit" class="admin-btn green sm" style="height:34px;">&#9654; Start</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- PENDING RESERVATIONS -->
    <div class="mini-card">
      <div class="mini-card-header" style="background:linear-gradient(135deg,#a06010,#e8a020);" onclick="toggleCard('pending')">
        <div class="mini-card-header-left">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Pending Reservations
          <?php if (!empty($pending_sitins)): ?><span style="background:white;color:#a06010;border-radius:20px;padding:1px 8px;font-size:0.7rem;font-weight:700;"><?= count($pending_sitins) ?></span><?php endif; ?>
        </div>
        <svg id="toggle-pending" class="mini-card-toggle <?= !empty($pending_sitins) ? 'open':'' ?>" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
      </div>
      <div class="mini-card-body <?= !empty($pending_sitins) ? 'open':'' ?>" id="body-pending">
        <table class="pending-mini-table">
          <thead><tr><th>ID</th><th>Name</th><th>Lab</th><th>Purpose</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($pending_sitins as $log): ?>
            <tr>
              <td><?= htmlspecialchars($log['sid']) ?></td>
              <td><?= htmlspecialchars($log['first_name'].' '.$log['last_name']) ?></td>
              <td><?= htmlspecialchars($log['lab_room']) ?></td>
              <td><?= htmlspecialchars($log['purpose']) ?></td>
              <td style="display:flex;gap:5px;">
                <a href="AdminSitin.php?approve=<?= $log['id'] ?>" onclick="return confirm('Approve?')" class="admin-btn green sm">&#10003;</a>
                <a href="AdminSitin.php?reject=<?= $log['id'] ?>"  onclick="return confirm('Reject?')"  class="admin-btn red sm">&#10005;</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($pending_sitins)): ?><tr><td colspan="5" class="empty-state" style="font-size:0.8rem;">No pending reservations.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- CURRENT SIT-IN -->
  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      Current Sit-In
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:20px;font-size:0.72rem;"><?= count($current_sitins) ?> active</span>
    </div>
    <div class="inline-search-wrap">
      <label>Search:</label>
      <input type="text" id="tableSearch" oninput="filterTable()" placeholder="Search..."/>
    </div>
    <div class="sitin-table-wrap">
      <table class="sitin-table" id="sitinTable">
        <thead>
          <tr><th>ID Number</th><th>Name</th><th>Purpose</th><th>Lab</th><th>Date In</th><th>Time In</th><th>Sessions Left</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($current_sitins as $log):
            $date_in = new DateTime($log['date_in']);
          ?>
          <tr>
            <td><?= htmlspecialchars($log['sid']) ?></td>
            <td><?= htmlspecialchars($log['first_name'].' '.$log['last_name']) ?></td>
            <td><?= htmlspecialchars($log['purpose']) ?></td>
            <td><?= htmlspecialchars($log['lab_room']) ?></td>
            <td><?= $date_in->format('M d, Y') ?></td>
            <td><?= $date_in->format('h:i A') ?></td>
            <td><?= $log['remaining'] ?></td>
            <td><span class="badge badge-active">Active</span></td>
            <td><a href="AdminSitin.php?end=<?= $log['id'] ?>" onclick="return confirm('End this sit-in session?')" class="admin-btn red sm">End Session</a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($current_sitins)): ?>
            <tr><td colspan="9" class="empty-state">No active sit-in sessionggs.</td></tr>
          <?php endif; ?>
        </tbody>

      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
function toggleCard(id) {
  const body   = document.getElementById('body-' + id);
  const toggle = document.getElementById('toggle-' + id);
  body.classList.toggle('open');
  toggle.classList.toggle('open');
}
function filterTable() {
  const q = document.getElementById('tableSearch').value.toLowerCase();
  document.querySelectorAll('#sitinTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>