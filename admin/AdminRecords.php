<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }
$db = get_db();

$records = $db->query("
    SELECT sl.*, s.first_name, s.last_name, s.student_id as sid, s.course_code
    FROM sitin_logs sl JOIN students s ON sl.student_id = s.id
    ORDER BY sl.date_in DESC
")->fetchAll();

$nav_admin_active = 'records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Sit-In Records</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }
    .records-table { width:100%; border-collapse:collapse; font-size:0.85rem; }
    .records-table th {
      padding:13px 18px; text-align:left;
      background:rgba(10,77,140,0.06); color:var(--blue-deep);
      font-weight:600; font-size:0.72rem; text-transform:uppercase;
      letter-spacing:0.5px; border-bottom:2px solid rgba(10,77,140,0.1);
      white-space:nowrap;
    }
    .records-table td {
      padding:14px 18px; color:var(--ink);
      border-bottom:1px solid rgba(204,222,237,0.4);
      vertical-align:middle; white-space:nowrap;
    }
    .records-table tr:last-child td { border-bottom:none; }
    .records-table tr:hover td { background:rgba(10,77,140,0.02); }
    .records-toolbar {
      display:flex; align-items:center; justify-content:flex-end;
      padding:14px 20px; border-bottom:1px solid rgba(204,222,237,0.4); gap:8px;
    }
    .records-toolbar label { font-size:0.8rem; color:var(--ink-soft); }
    .records-toolbar input {
      width:220px; padding:7px 12px;
      border:1.5px solid #ccdeed; border-radius:8px;
      font-family:'DM Sans',sans-serif; font-size:0.82rem;
      outline:none; transition:border-color 0.2s;
    }
    .records-toolbar input:focus { border-color:#1877c9; }
    .records-table-wrap { padding:0 0 4px; overflow-x:auto; }

    .feedback-view-btn {
      display:inline-flex; align-items:center; gap:5px;
      padding:5px 12px;
      background:rgba(10,77,140,0.08); color:var(--blue-deep);
      border:1.5px solid rgba(10,77,140,0.18); border-radius:6px;
      font-family:'DM Sans',sans-serif; font-size:0.75rem; font-weight:600;
      cursor:pointer; transition:background 0.18s, transform 0.15s;
    }
    .feedback-view-btn:hover { background:rgba(10,77,140,0.15); transform:translateY(-1px); }
    .no-feedback { color:#ccc; font-size:0.75rem; font-style:italic; }

    .modal-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,0.45);
      display:none; align-items:center; justify-content:center;
      z-index:999; backdrop-filter:blur(4px);
    }
    .modal-overlay.open { display:flex; animation:fadeIn 0.2s ease; }
    @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    .modal-box {
      background:white; border-radius:18px; width:480px; max-width:95vw;
      box-shadow:0 20px 60px rgba(0,0,0,0.2); overflow:hidden;
      animation:slideUp 0.25s ease;
    }
    @keyframes slideUp { from{transform:translateY(20px);opacity:0} to{transform:translateY(0);opacity:1} }
    .modal-header {
      background:var(--blue-deep); padding:20px 24px;
      display:flex; align-items:center; justify-content:space-between;
    }
    .modal-title {
      font-family:'DM Serif Display',serif; font-size:1.1rem; color:white;
      display:flex; align-items:center; gap:8px;
    }
    .modal-close {
      background:rgba(255,255,255,0.2); border:none; color:white;
      width:28px; height:28px; border-radius:50%; font-size:1rem;
      cursor:pointer; display:flex; align-items:center; justify-content:center;
      transition:background 0.15s;
    }
    .modal-close:hover { background:rgba(255,255,255,0.35); }
    .modal-body { padding:24px; }
    .modal-info-box {
      background:rgba(10,77,140,0.05); border:1px solid rgba(10,77,140,0.12);
      border-radius:10px; padding:12px 16px; margin-bottom:16px;
      font-size:0.83rem; color:var(--ink); line-height:1.6;
    }
    .modal-info-box strong { color:var(--blue-deep); }
    .modal-feedback-text {
      background:#f8fafd; border:1.5px solid #ccdeed; border-radius:10px;
      padding:14px 16px; font-size:0.88rem; color:var(--ink);
      line-height:1.65; white-space:pre-wrap; min-height:80px;
    }
    .modal-footer { padding:0 24px 20px; display:flex; justify-content:flex-end; }
    .modal-close-btn {
      padding:9px 22px; background:transparent;
      border:1.5px solid #ccdeed; border-radius:10px;
      font-family:'DM Sans',sans-serif; font-size:0.88rem;
      color:var(--ink-soft); cursor:pointer; transition:background 0.18s;
    }
    .modal-close-btn:hover { background:#f0f5fb; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">View Sit-In Records</h2>

  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="12 8 12 12 14 14"/>
        <path d="M3.05 11a9 9 0 1 1 .5 4"/>
        <polyline points="3 21 3 16 8 16"/>
      </svg>
      Sit-In History
    </div>

    <div class="records-toolbar">
      <label>Search:</label>
      <input type="text" id="tableSearch" oninput="filterTable()" placeholder="Search by name, ID, lab..."/>
    </div>

    <div class="records-table-wrap">
      <table class="records-table" id="recordsTable">
        <thead>
          <tr>
            <th>Sit ID</th>
            <th>ID Number</th>
            <th>Name</th>
            <th>Purpose</th>
            <th>Lab</th>
            <th>Date In</th>
            <th>Date Out</th>
            <th>Status</th>
            <th>Feedback</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($records as $r): ?>
          <tr>
            <td><?= $r['id'] ?></td>
            <td><?= htmlspecialchars($r['sid']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= htmlspecialchars($r['lab_room']) ?></td>
            <td><?= htmlspecialchars($r['date_in']) ?></td>
            <td><?= $r['date_out'] ? htmlspecialchars($r['date_out']) : '<span style="color:#aaa;font-style:italic;">Still active</span>' ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
            <td>
              <?php if (!empty($r['feedback'])): ?>
                <button class="feedback-view-btn" onclick="viewFeedback(
                  <?= htmlspecialchars(json_encode($r['first_name'].' '.$r['last_name'])) ?>,
                  <?= htmlspecialchars(json_encode($r['sid'])) ?>,
                  <?= htmlspecialchars(json_encode($r['lab_room'])) ?>,
                  <?= htmlspecialchars(json_encode($r['purpose'])) ?>,
                  <?= htmlspecialchars(json_encode($r['date_in'])) ?>,
                  <?= htmlspecialchars(json_encode($r['feedback'])) ?>
                )">
                  &#128172; View Feedback
                </button>
              <?php else: ?>
                <span class="no-feedback">No feedback</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($records)): ?>
            <tr><td colspan="9" class="empty-state">No sit-in records yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>

<!-- Feedback View Modal -->
<div class="modal-overlay" id="feedbackModal">
  <div class="modal-box">
    <div class="modal-header">
      <div class="modal-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
        </svg>
        Student Feedback
      </div>
      <button class="modal-close" onclick="closeModal()">&#10005;</button>
    </div>
    <div class="modal-body">
      <div class="modal-info-box" id="modalInfo"></div>
      <div class="modal-feedback-text" id="modalFeedbackText"></div>
    </div>
    <div class="modal-footer">
      <button class="modal-close-btn" onclick="closeModal()">Close</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
<script>
function filterTable() {
  const q = document.getElementById('tableSearch').value.toLowerCase();
  document.querySelectorAll('#recordsTable tbody tr').forEach(r => {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

function viewFeedback(name, sid, lab, purpose, dateIn, feedback) {
  document.getElementById('modalInfo').innerHTML =
    '<strong>Student:</strong> ' + name + ' (' + sid + ')<br>' +
    '<strong>Lab:</strong> ' + lab + ' &bull; <strong>Purpose:</strong> ' + purpose + '<br>' +
    '<strong>Date In:</strong> ' + dateIn;
  document.getElementById('modalFeedbackText').textContent = feedback;
  document.getElementById('feedbackModal').classList.add('open');
}

function closeModal() {
  document.getElementById('feedbackModal').classList.remove('open');
}

document.getElementById('feedbackModal').addEventListener('click', function(e) {
  if (e.target === this) closeModal();
});
</script>
</body>
</html>