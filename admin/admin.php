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
  <style>
    body.admin-page a { text-decoration: none !important; }

    /* ── Dark theme (matches AdminSoftware) ── */
    :root {
      --ds-bg: #0b1220; --ds-surface: #0f1724; --ds-panel: #0c1622;
      --ds-muted: #9aa5b4; --ds-text: #ffffff;
      --ds-border: rgba(255,255,255,0.04); --accent-soft: rgba(74,163,255,0.06);
    }
    html.dark-theme, .dark-theme { background-color: var(--ds-bg) !important; color: var(--ds-text) !important; }
    .dark-theme .admin-main { background: transparent; color: var(--ds-text); }

    /* Stat cards */
    .dark-theme .stat-card {
      background: rgba(255,255,255,0.02) !important;
      border-color: rgba(255,255,255,0.04) !important;
      box-shadow: 0 4px 20px rgba(2,6,23,0.5) !important;
      backdrop-filter: none !important;
    }
    .dark-theme .stat-label { color: var(--ds-muted) !important; }
    .dark-theme .stat-value { color: var(--ds-text) !important; }
    .dark-theme .stat-icon.blue   { background: rgba(10,77,140,0.18) !important;  color: #93c5fd !important; }
    .dark-theme .stat-icon.green  { background: rgba(34,197,94,0.14) !important;  color: #86efac !important; }
    .dark-theme .stat-icon.violet { background: rgba(139,92,246,0.14) !important; color: #c4b5fd !important; }
    .dark-theme .stat-icon.gold   { background: rgba(232,160,32,0.14) !important; color: #fbbf24 !important; }

    /* Admin cards */
    .dark-theme .admin-card {
      background: linear-gradient(180deg, var(--ds-panel), var(--ds-surface)) !important;
      border-color: var(--ds-border) !important;
      box-shadow: 0 8px 30px rgba(2,6,23,0.6) !important;
      backdrop-filter: none !important;
    }

    /* Announcement form */
    .dark-theme .ann-form input[type="text"],
    .dark-theme .ann-form textarea,
    .dark-theme .ann-form select {
      background: rgba(255,255,255,0.04) !important;
      border-color: rgba(255,255,255,0.07) !important;
      color: var(--ds-text) !important;
    }
    .dark-theme .ann-form input::placeholder,
    .dark-theme .ann-form textarea::placeholder { color: rgba(255,255,255,0.28) !important; }
    .dark-theme .ann-form input:focus,
    .dark-theme .ann-form textarea:focus,
    .dark-theme .ann-form select:focus { border-color: rgba(74,163,255,0.35) !important; box-shadow: 0 0 0 3px rgba(74,163,255,0.08) !important; }

    /* Announcement list */
    .dark-theme .ann-posted-title { color: var(--ds-text) !important; }
    .dark-theme .ann-item { border-bottom-color: rgba(255,255,255,0.04) !important; }
    .dark-theme .ann-item-meta { color: var(--ds-muted) !important; }
    .dark-theme .ann-item-body { color: var(--ds-text) !important; }

    /* Alert */
    .dark-theme .admin-alert.success { background: rgba(34,197,94,0.1) !important; border-color: rgba(34,197,94,0.2) !important; color: #86efac !important; }

    /* Chart legend */
    .dark-theme canvas { filter: none; }

    /* Smooth transitions */
    .dark-theme * { transition: background-color 180ms ease, color 180ms ease, border-color 180ms ease; }
  </style>
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
const isDark = document.documentElement.classList.contains('dark-theme');
const legendColor = isDark ? '#ffffff' : '#12243a';
new Chart(document.getElementById('purposeChart'), {
  type: 'pie',
  data: { labels: labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: isDark ? 1 : 2, borderColor: isDark ? 'rgba(255,255,255,0.08)' : '#fff' }] },
  options: {
    responsive: true,
    plugins: {
      legend: {
        position: 'bottom',
        labels: { font: { family:'DM Sans', size:11 }, padding:12, color: legendColor }
      }
    }
  }
});
<?php endif; ?>
</script>
</body>
</html>