<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();
$success = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $sid = (int)$_GET['delete'];
    try {
        // Delete related records first to satisfy foreign key constraints
        $db->prepare("DELETE FROM sitin_logs   WHERE student_id = ?")->execute([$sid]);
        $db->prepare("DELETE FROM testimonials  WHERE student_id = ?")->execute([$sid]);
        // Now safe to delete the student
        $db->prepare("DELETE FROM students WHERE id = ?")->execute([$sid]);
        header('Location: AdminStudents.php?msg=deleted'); exit;
    } catch (Exception $e) {
        header('Location: AdminStudents.php?msg=error'); exit;
    }
}

if (isset($_GET['reset_all'])) {
    $db->exec("UPDATE students SET used = 0");
    header('Location: AdminStudents.php?msg=reset'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $db->prepare("UPDATE students SET first_name=?,last_name=?,level=?,course=?,sessions=? WHERE id=?")
       ->execute([trim($_POST['first_name']),trim($_POST['last_name']),trim($_POST['level']),trim($_POST['course']),(int)$_POST['sessions'],(int)$_POST['edit_id']]);
    header('Location: AdminStudents.php?msg=updated'); exit;
}

$msg = $_GET['msg'] ?? '';
if ($msg === 'deleted') $success = 'Student deleted successfully.';
if ($msg === 'error')   $success = 'Could not delete student. Please try again.';
if ($msg === 'updated') $success = 'Student updated successfully.';
if ($msg === 'reset')   $success = 'All sessions have been reset.';

$students = $db->query("SELECT * FROM students ORDER BY last_name ASC")->fetchAll();
$nav_admin_active = 'students';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Students</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .student-list-header {
      display: grid;
      grid-template-columns: 1.2fr 2fr 1fr 1fr 1.2fr 1.2fr;
      padding: 8px 18px;
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      color: var(--ink-soft);
      gap: 12px;
    }

    .student-card {
      display: grid;
      grid-template-columns: 1.2fr 2fr 1fr 1fr 1.2fr 1.2fr;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,0.88);
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 12px;
      padding: 14px 18px;
      margin-bottom: 8px;
      box-shadow: 0 2px 8px rgba(10,77,140,0.06);
      font-size: 0.85rem;
      color: var(--ink);
      transition: box-shadow 0.18s, transform 0.18s;
    }

    .student-card:hover {
      box-shadow: 0 6px 20px rgba(10,77,140,0.12);
      transform: translateY(-1px);
    }

    .student-card-actions {
      display: flex;
      gap: 6px;
    }

    .student-list-wrap {
      padding: 16px 20px;
    }
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
  <h2 class="section-title">Students Information</h2>

  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Student List
    </div>

    <div class="student-list-wrap">
      <?php if ($success): ?>
        <div class="admin-alert success" style="margin-bottom:14px;">&#10003; <?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="admin-table-toolbar" style="margin-bottom:16px;">
        <div class="admin-table-toolbar-left">
          <a href="AdminStudents.php?reset_all=1" onclick="return confirm('Reset ALL student sessions to 30?')" class="admin-btn gold">&#8635; Reset All Sessions</a>
        </div>
        <div class="search-box-wrap">
          <label>Search:</label>
          <input type="text" id="tableSearch" oninput="filterCards()" placeholder="Name, ID, course..."/>
        </div>
      </div>

      <div class="student-list-header">
        <span>ID Number</span>
        <span>Name</span>
        <span>Year Level</span>
        <span>Course</span>
        <span>Remaining Sessions</span>
        <span>Actions</span>
      </div>

      <div id="studentCards">
        <?php if (empty($students)): ?>
          <div class="empty-state">No students registered yet.</div>
        <?php endif; ?>
        <?php foreach ($students as $s): ?>
        <div class="student-card">
          <span><?= htmlspecialchars($s['student_id']) ?></span>
          <span><?= htmlspecialchars($s['first_name'].' '.($s['middle_name']?$s['middle_name'].' ':'').$s['last_name']) ?></span>
          <span><?= htmlspecialchars($s['level']) ?></span>
          <span><?= htmlspecialchars($s['course_code']) ?></span>
          <span><?= $s['sessions'] - $s['used'] ?></span>
          <div class="student-card-actions">
            <button class="admin-btn blue sm" onclick='openEdit(<?= htmlspecialchars(json_encode($s)) ?>)'>Edit</button>
            <a href="AdminStudents.php?delete=<?= $s['id'] ?>" onclick="return confirm('Delete this student?')" class="admin-btn red sm">Delete</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</main>

<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-header">
      Edit Student
      <button class="modal-close" onclick="document.getElementById('editModal').classList.remove('open')">&#10005;</button>
    </div>
    <form method="POST" action="AdminStudents.php">
      <div class="modal-body">
        <input type="hidden" name="edit_id" id="edit_id"/>
        <div class="modal-row">
          <span class="modal-row-label">First Name</span>
          <input type="text" name="first_name" id="edit_fname" style="flex:1;padding:6px 10px;border:1.5px solid #ccdeed;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;"/>
        </div>
        <div class="modal-row">
          <span class="modal-row-label">Last Name</span>
          <input type="text" name="last_name" id="edit_lname" style="flex:1;padding:6px 10px;border:1.5px solid #ccdeed;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;"/>
        </div>
        <div class="modal-row">
          <span class="modal-row-label">Year Level</span>
          <select name="level" id="edit_level" style="flex:1;padding:6px 10px;border:1.5px solid #ccdeed;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;">
            <option>1st Year</option><option>2nd Year</option><option>3rd Year</option><option>4th Year</option>
          </select>
        </div>
        <div class="modal-row">
          <span class="modal-row-label">Course</span>
          <input type="text" name="course" id="edit_course" style="flex:1;padding:6px 10px;border:1.5px solid #ccdeed;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;"/>
        </div>
        <div class="modal-row">
          <span class="modal-row-label">Total Sessions</span>
          <input type="number" name="sessions" id="edit_sessions" min="0" max="100" style="flex:1;padding:6px 10px;border:1.5px solid #ccdeed;border-radius:6px;font-family:'DM Sans',sans-serif;font-size:0.85rem;outline:none;"/>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="admin-btn ghost" onclick="document.getElementById('editModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="admin-btn blue">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/footer.php'; ?>
<script>
function openEdit(s) {
  document.getElementById('edit_id').value       = s.id;
  document.getElementById('edit_fname').value    = s.first_name;
  document.getElementById('edit_lname').value    = s.last_name;
  document.getElementById('edit_level').value    = s.level;
  document.getElementById('edit_course').value   = s.course;
  document.getElementById('edit_sessions').value = s.sessions;
  document.getElementById('editModal').classList.add('open');
}
function filterCards() {
  const q = document.getElementById('tableSearch').value.toLowerCase();
  document.querySelectorAll('#studentCards .student-card').forEach(c => {
    c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</body>
</html>