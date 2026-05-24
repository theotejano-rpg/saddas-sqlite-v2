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
  <style>
    /* ── Shared dark theme (matches AdminSoftware design) ── */
    :root {
      --ds-bg: #0b1220; --ds-surface: #0f1724; --ds-panel: #0c1622;
      --ds-muted: #9aa5b4; --ds-text: #ffffff;
      --ds-border: rgba(255,255,255,0.04); --accent-soft: rgba(74,163,255,0.06);
    }
    html.dark-theme, .dark-theme {
      background-color: var(--ds-bg) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .admin-main { background: transparent; color: var(--ds-text); }

    /* Cards */
    .dark-theme .admin-card,
    .dark-theme .mini-card,
    .dark-theme .fb-stat,
    .dark-theme .report-stat,
    .dark-theme .profile-result-card,
    .dark-theme .modal-box,
    .dark-theme .pc-modal,
    .dark-theme .at-table,
    .dark-theme .at-empty {
      background: linear-gradient(180deg, var(--ds-panel), var(--ds-surface)) !important;
      color: var(--ds-text) !important;
      border-color: var(--ds-border) !important;
      box-shadow: 0 8px 30px rgba(2,6,23,0.6) !important;
    }

    /* All table headers */
    .dark-theme .records-table th,
    .dark-theme .res-table th,
    .dark-theme .sitin-table th,
    .dark-theme .pending-mini-table th,
    .dark-theme .history-table th,
    .dark-theme .fb-table th,
    .dark-theme .at-table th {
      background: rgba(255,255,255,0.03) !important;
      color: var(--ds-text) !important;
      border-bottom-color: rgba(255,255,255,0.04) !important;
    }

    /* All table cells */
    .dark-theme .records-table td,
    .dark-theme .res-table td,
    .dark-theme .sitin-table td,
    .dark-theme .pending-mini-table td,
    .dark-theme .history-table td,
    .dark-theme .fb-table td,
    .dark-theme .at-table td {
      background: transparent !important;
      color: var(--ds-text) !important;
      border-bottom-color: rgba(255,255,255,0.03) !important;
    }

    /* Table row hover */
    .dark-theme .records-table tr:hover td,
    .dark-theme .res-table tr:hover td,
    .dark-theme .sitin-table tr:hover td,
    .dark-theme .pending-mini-table tr:hover td,
    .dark-theme .history-table tr:hover td,
    .dark-theme .fb-table tr:hover td,
    .dark-theme .at-table tr:hover td {
      background: rgba(255,255,255,0.02) !important;
    }

    /* Toolbars / filter bars */
    .dark-theme .records-toolbar,
    .dark-theme .res-toolbar,
    .dark-theme .inline-search-wrap,
    .dark-theme .history-toolbar,
    .dark-theme .fb-filter-bar,
    .dark-theme .sw-filter-bar,
    .dark-theme .sw-add-form {
      border-color: rgba(255,255,255,0.03) !important;
    }

    /* All inputs / selects */
    .dark-theme input,
    .dark-theme select,
    .dark-theme textarea {
      background: rgba(255,255,255,0.04) !important;
      color: var(--ds-text) !important;
      border-color: rgba(255,255,255,0.07) !important;
    }
    .dark-theme input::placeholder { color: rgba(255,255,255,0.3) !important; }
    .dark-theme input:focus,
    .dark-theme select:focus {
      border-color: rgba(74,163,255,0.35) !important;
      outline: none;
    }

    /* Stat numbers/labels */
    .dark-theme .report-stat-num,
    .dark-theme .fb-stat-num { color: var(--ds-text) !important; }
    .dark-theme .report-stat-label,
    .dark-theme .fb-stat-label,
    .dark-theme .records-toolbar label,
    .dark-theme .res-toolbar label,
    .dark-theme .inline-search-wrap label { color: var(--ds-muted) !important; }

    /* Student cards */
    .dark-theme .student-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.04) !important;
      color: var(--ds-text) !important;
      box-shadow: 0 2px 10px rgba(2,6,23,0.4) !important;
    }
    .dark-theme .student-card:hover {
      background: rgba(255,255,255,0.04) !important;
      box-shadow: 0 6px 20px rgba(2,6,23,0.5) !important;
    }

    /* Mini cards (Sitin) */
    .dark-theme .mini-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.04) !important;
    }

    /* Modal */
    .dark-theme .modal-box {
      background: var(--ds-surface) !important;
      border: 1px solid rgba(255,255,255,0.04) !important;
    }
    .dark-theme .modal-body { background: var(--ds-surface) !important; color: var(--ds-text) !important; }
    .dark-theme .modal-info-box {
      background: rgba(74,163,255,0.05) !important;
      border-color: rgba(74,163,255,0.1) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .modal-feedback-text {
      background: rgba(255,255,255,0.03) !important;
      border-color: rgba(255,255,255,0.06) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .modal-footer { background: var(--ds-surface) !important; }
    .dark-theme .modal-close-btn {
      background: transparent !important;
      border-color: rgba(255,255,255,0.08) !important;
      color: var(--ds-muted) !important;
    }
    .dark-theme .modal-close-btn:hover { background: rgba(255,255,255,0.04) !important; }

    /* Search page */
    .dark-theme .search-hint {
      background: rgba(74,163,255,0.04) !important;
      border-color: rgba(74,163,255,0.1) !important;
      color: var(--ds-muted) !important;
    }
    .dark-theme .profile-result-card { overflow: hidden; }
    .dark-theme .profile-result-details { background: var(--ds-surface) !important; color: var(--ds-text) !important; }
    .dark-theme .profile-detail-row { border-color: rgba(255,255,255,0.03) !important; color: var(--ds-text) !important; }

    /* Initiate student info (Sitin) */
    .dark-theme .initiate-student-info {
      background: rgba(74,163,255,0.04) !important;
      border-color: rgba(74,163,255,0.08) !important;
    }
    .dark-theme .initiate-student-name { color: var(--ds-text) !important; }
    .dark-theme .initiate-student-meta { color: var(--ds-muted) !important; }
    .dark-theme .initiate-form-row select { background: rgba(255,255,255,0.03) !important; color: var(--ds-text) !important; border-color: rgba(255,255,255,0.06) !important; }

    /* Badges (keep color but adjust bg opacity for dark) */
    .dark-theme .badge-pending,  .dark-theme .status-badge.pending  { background: rgba(232,160,32,0.18) !important; color: #fbbf24 !important; }
    .dark-theme .badge-active,   .dark-theme .status-badge.approved { background: rgba(34,197,94,0.15) !important; color: #86efac !important; }
    .dark-theme .badge-completed { background: rgba(74,163,255,0.12) !important; color: #93c5fd !important; }
    .dark-theme .badge-cancelled, .dark-theme .status-badge.rejected { background: rgba(220,38,38,0.15) !important; color: #fca5a5 !important; }

    /* Action buttons */
    .dark-theme .at-action-btn.approve { background: rgba(34,197,94,0.12) !important; color: #86efac !important; }
    .dark-theme .at-action-btn.reject  { background: rgba(220,38,38,0.1) !important; color: #fca5a5 !important; }
    .dark-theme .feedback-view-btn { background: rgba(74,163,255,0.08) !important; color: #93c5fd !important; border-color: rgba(74,163,255,0.12) !important; }

    /* Fb filter submit */
    .dark-theme .fb-filter-submit { background: #1877c9 !important; }
    .dark-theme .fb-filter-reset { border-color: rgba(255,255,255,0.08) !important; color: var(--ds-muted) !important; }

    /* Section headings / eyebrows */
    .dark-theme .at-section-title { color: var(--ds-text) !important; }
    .dark-theme .at-section-sub { color: var(--ds-muted) !important; }

    /* Divider */
    .dark-theme .divider-line { border-color: rgba(255,255,255,0.04) !important; }

    /* Toast */
    .dark-theme .toast-fixed { background: rgba(255,255,255,0.06) !important; color: var(--ds-text) !important; box-shadow: 0 6px 24px rgba(2,6,23,0.6) !important; }

    /* Smooth transitions */
    .dark-theme * { transition: background-color 180ms ease, color 180ms ease, border-color 180ms ease; }
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