<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }
$db = get_db();

$reservations = $db->query("
    SELECT sl.*, s.first_name, s.last_name, s.student_id as sid
    FROM sitin_logs sl JOIN students s ON sl.student_id = s.id
    ORDER BY sl.date_in DESC
")->fetchAll();

$nav_admin_active = 'reservation';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Reservation</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .res-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
    }

    .res-table th {
      padding: 13px 20px;
      text-align: left;
      background: rgba(10,77,140,0.06);
      color: var(--blue-deep);
      font-weight: 600;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid rgba(10,77,140,0.1);
      white-space: nowrap;
    }

    .res-table td {
      padding: 14px 20px;
      color: var(--ink);
      border-bottom: 1px solid rgba(204,222,237,0.4);
      vertical-align: middle;
      white-space: nowrap;
    }

    .res-table tr:last-child td { border-bottom: none; }
    .res-table tr:hover td { background: rgba(10,77,140,0.02); }

    .res-toolbar {
      display: flex;
      align-items: center;
      justify-content: flex-end;
      padding: 14px 20px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      gap: 8px;
    }

    .res-toolbar label { font-size: 0.8rem; color: var(--ink-soft); }
    .res-toolbar input {
      width: 220px;
      padding: 7px 12px;
      border: 1.5px solid #ccdeed;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      outline: none;
      transition: border-color 0.2s;
    }
    .res-toolbar input:focus { border-color: #1877c9; }

    .res-table-wrap { overflow-x: auto; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Reservation List</h2>

  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      All Reservations
    </div>

    <div class="res-toolbar">
      <label>Search:</label>
      <input type="text" id="tableSearch" oninput="filterTable()" placeholder="Search by name, ID, lab..."/>
    </div>

    <div class="res-table-wrap">
      <table class="res-table" id="resTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Student ID</th>
            <th>Name</th>
            <th>Lab</th>
            <th>Purpose</th>
            <th>Date In</th>
            <th>Date Out</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($reservations as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['sid']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['lab_room']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['date_in']) ?></td>
            <td><?= $r['date_out'] ? htmlspecialchars($r['date_out']) : '<span style="color:#aaa;font-style:italic;">Still active</span>' ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($reservations)): ?>
            <tr><td colspan="8" class="empty-state">No reservations yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
function filterTable() {
  const q = document.getElementById('tableSearch').value.toLowerCase();
  document.querySelectorAll('#resTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>