<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();
$success = '';

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $db->prepare("DELETE FROM students WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: AdminStudents.php?msg=deleted'); exit;
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