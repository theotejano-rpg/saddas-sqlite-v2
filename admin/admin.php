<?php
session_start();
require_once 'db.php';

if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db    = get_db();
$admin = $_SESSION['admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ann_title'])) {
    $title = trim($_POST['ann_title'] ?? '');
    $body  = trim($_POST['ann_body']  ?? '');
    $tag   = trim($_POST['ann_tag']   ?? 'General');
    if ($title && $body) {
        $db->prepare("INSERT INTO announcements (title, body, tag) VALUES (?,?,?)")->execute([$title, $body, $tag]);
        header('Location: admin.php?msg=posted'); exit;
    }
}

if (isset($_GET['delete_ann']) && is_numeric($_GET['delete_ann'])) {
    $db->prepare("DELETE FROM announcements WHERE id = ?")->execute([(int)$_GET['delete_ann']]);
    header('Location: admin.php?msg=deleted'); exit;
}

$total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$current_sitin  = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='active'")->fetchColumn();
$total_sitin    = $db->query("SELECT COUNT(*) FROM sitin_logs")->fetchColumn();
$purpose_data   = $db->query("SELECT purpose, COUNT(*) as cnt FROM sitin_logs GROUP BY purpose ORDER BY cnt DESC")->fetchAll();
$announcements  = $db->query("SELECT * FROM announcements ORDER BY posted_at DESC")->fetchAll();

$msg         = $_GET['msg'] ?? '';
$ann_success = $msg === 'posted' ? 'Announcement posted!' : ($msg === 'deleted' ? 'Announcement deleted.' : '');

$nav_admin_active = 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Admin Dashboard</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body class="admin-page">

<?php include __DIR__ . '/nav_admin.php'; ?>

<main class="admin-main">

  <div class="admin-stats-grid">
    <div class="stat-card">
      <div class="stat-icon blue">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-label">Students Registered</div>
        <div class="stat-value"><?= $total_students ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-label">Currently Sit-In</div>
        <div class="stat-value"><?= $current_sitin ?></div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon violet">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div class="stat-info">
        <div class="stat-label">Total Sit-Ins</div>
        <div class="stat-value"><?= $total_sitin ?></div>
      </div>
    </div>
  </div>

  <div class="admin-dashboard-grid">

    <div class="admin-card">
      <div class="admin-card-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Sit-In Statistics by Purpose
      </div>
      <div class="chart-wrap">
        <?php if (empty($purpose_data)): ?>
          <div class="empty-state">No sit-in data yet.</div>
        <?php else: ?>
          <canvas id="purposeChart"></canvas>
        <?php endif; ?>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-header">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3z"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Post Announcement
      </div>

      <?php if ($ann_success): ?>
        <div class="admin-alert success" style="margin:12px 20px 0">&#10003; <?= htmlspecialchars($ann_success) ?></div>
      <?php endif; ?>

      <form method="POST" action="admin.php" class="ann-form">
        <input type="text" name="ann_title" placeholder="Announcement title" required/>
        <textarea name="ann_body" placeholder="Write your announcement here..." required></textarea>
        <select name="ann_tag">
          <option value="General">General</option>
          <option value="Academics">Academics</option>
          <option value="Sit-In">Sit-In</option>
          <option value="Event">Event</option>
        </select>
        <button type="submit" class="ann-submit-btn">Submit</button>
      </form>

      <div class="ann-posted-title">Posted Announcements</div>
      <div class="ann-list">
        <?php if (empty($announcements)): ?>
          <div class="empty-state">No announcements yet.</div>
        <?php else: ?>
          <?php foreach ($announcements as $ann): ?>
          <div class="ann-item">
            <div class="ann-item-meta"><?= htmlspecialchars($ann['tag']) ?> &mdash; <?= htmlspecialchars($ann['posted_at']) ?></div>
            <div class="ann-item-body"><strong><?= htmlspecialchars($ann['title']) ?></strong><br><?= htmlspecialchars($ann['body']) ?></div>
            <div class="ann-item-actions">
              <a href="admin.php?delete_ann=<?= $ann['id'] ?>" onclick="return confirm('Delete this announcement?')" class="admin-btn red sm">Delete</a>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<script>
<?php if (!empty($purpose_data)): ?>
const labels = <?= json_encode(array_column($purpose_data,'purpose')) ?>;
const values = <?= json_encode(array_column($purpose_data,'cnt')) ?>;
const colors = ['#1877c9','#e74c3c','#6b21c8','#e8a020','#27ae60','#5aadea','#9b59e8','#f39c12'];
new Chart(document.getElementById('purposeChart'), {
  type: 'pie',
  data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
  options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { family:'DM Sans', size:11 }, padding:12 } } } }
});
<?php endif; ?>
</script>
</body>
</html>