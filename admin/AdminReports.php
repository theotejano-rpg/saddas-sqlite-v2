<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }
$db = get_db();

$filter_status = $_GET['status'] ?? '';
$filter_date   = $_GET['date']   ?? '';
$search        = trim($_GET['q'] ?? '');

$where  = ['1=1'];
$params = [];
if ($filter_status) { $where[] = 'sl.status = ?'; $params[] = $filter_status; }
if ($filter_date)   { $where[] = 'DATE(sl.date_in) = ?'; $params[] = $filter_date; }
if ($search) {
    $where[]  = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$sql = "
    SELECT sl.*, s.first_name, s.last_name, s.student_id as sid, s.course_code
    FROM sitin_logs sl JOIN students s ON sl.student_id = s.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY sl.date_in DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$purpose_stats = $db->query("SELECT purpose, COUNT(*) as cnt FROM sitin_logs GROUP BY purpose ORDER BY cnt DESC")->fetchAll();
$lab_stats     = $db->query("SELECT lab_room, COUNT(*) as cnt FROM sitin_logs GROUP BY lab_room ORDER BY cnt DESC")->fetchAll();
$daily_stats   = $db->query("SELECT DATE(date_in) as day, COUNT(*) as cnt FROM sitin_logs GROUP BY DATE(date_in) ORDER BY day DESC LIMIT 14")->fetchAll();

$total_all       = $db->query("SELECT COUNT(*) FROM sitin_logs")->fetchColumn();
$total_completed = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='completed'")->fetchColumn();
$total_active    = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='active'")->fetchColumn();
$total_pending   = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='pending'")->fetchColumn();
$total_cancelled = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='cancelled'")->fetchColumn();

$nav_admin_active = 'reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Sit-In Reports</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .report-stats {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 14px;
      margin-bottom: 24px;
    }

    .report-stat {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 14px;
      padding: 16px 18px;
      box-shadow: 0 4px 16px rgba(10,77,140,0.07);
      text-align: center;
      cursor: pointer;
      transition: transform 0.18s, box-shadow 0.18s;
      text-decoration: none;
    }

    .report-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(10,77,140,0.12); }

    .report-stat-num {
      font-family: 'DM Serif Display', serif;
      font-size: 1.8rem;
      color: var(--blue-deep);
      line-height: 1;
    }

    .report-stat-label {
      font-size: 0.68rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--ink-soft);
      margin-top: 4px;
    }

    .history-toolbar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 14px 20px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      flex-wrap: wrap;
    }

    .history-toolbar input,
    .history-toolbar select {
      padding: 7px 12px;
      border: 1.5px solid #ccdeed;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      outline: none;
    }

    .history-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.84rem;
    }

    .history-table th {
      padding: 12px 16px;
      text-align: left;
      background: rgba(10,77,140,0.06);
      color: var(--blue-deep);
      font-weight: 600;
      font-size: 0.68rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid rgba(10,77,140,0.1);
      white-space: nowrap;
    }

    .history-table td {
      padding: 13px 16px;
      color: var(--ink);
      border-bottom: 1px solid rgba(204,222,237,0.4);
      vertical-align: middle;
      white-space: nowrap;
    }

    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: rgba(10,77,140,0.02); }
    .history-table-wrap { overflow-x: auto; }

    .badge-pending   { background: rgba(232,160,32,0.12);  color: #a06010; }
    .badge-active    { background: rgba(26,140,78,0.12);   color: #1a7a42; }
    .badge-completed { background: rgba(10,77,140,0.1);    color: #0a4d8c; }
    .badge-cancelled { background: rgba(208,49,45,0.1);    color: #c0392b; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Sit-In Reports</h2>

  <!-- Stats -->
  <div class="report-stats">
    <a href="AdminReports.php" class="report-stat">
      <div class="report-stat-num"><?= $total_all ?></div>
      <div class="report-stat-label">All Sessions</div>
    </a>
    <a href="AdminReports.php?status=completed" class="report-stat">
      <div class="report-stat-num" style="color:#1a7a42;"><?= $total_completed ?></div>
      <div class="report-stat-label">Completed</div>
    </a>
    <a href="AdminReports.php?status=active" class="report-stat">
      <div class="report-stat-num" style="color:#0a4d8c;"><?= $total_active ?></div>
      <div class="report-stat-label">Active</div>
    </a>
    <a href="AdminReports.php?status=pending" class="report-stat">
      <div class="report-stat-num" style="color:#a06010;"><?= $total_pending ?></div>
      <div class="report-stat-label">Pending</div>
    </a>
    <a href="AdminReports.php?status=cancelled" class="report-stat">
      <div class="report-stat-num" style="color:#c0392b;"><?= $total_cancelled ?></div>
      <div class="report-stat-label">Cancelled</div>
    </a>
  </div>

  <!-- Charts -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px;">
    <div class="admin-card">
      <div class="admin-card-header">Sit-Ins by Purpose</div>
      <div class="chart-wrap">
        <?php if (empty($purpose_stats)): ?><div class="empty-state">No data yet.</div>
        <?php else: ?><canvas id="purposeChart"></canvas><?php endif; ?>
      </div>
    </div>
    <div class="admin-card">
      <div class="admin-card-header">Sit-Ins by Lab Room</div>
      <div class="chart-wrap">
        <?php if (empty($lab_stats)): ?><div class="empty-state">No data yet.</div>
        <?php else: ?><canvas id="labChart"></canvas><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="admin-card" style="margin-bottom:22px;">
    <div class="admin-card-header">Daily Sit-In Activity (Last 14 days)</div>
    <div class="chart-wrap">
      <?php if (empty($daily_stats)): ?><div class="empty-state">No data yet.</div>
      <?php else: ?><canvas id="dailyChart" style="max-height:200px;"></canvas><?php endif; ?>
    </div>
  </div>

  <!-- History Table -->
  <div class="admin-card">
    <div class="admin-card-header">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="12 8 12 12 14 14"/><path d="M3.05 11a9 9 0 1 1 .5 4"/><polyline points="3 21 3 16 8 16"/></svg>
      Sit-In History Log
      <span style="margin-left:8px;background:rgba(255,255,255,0.2);padding:2px 10px;border-radius:20px;font-size:0.72rem;"><?= count($records) ?> records</span>
    </div>
    <form method="GET" action="AdminReports.php" class="history-toolbar">
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or ID..."/>
      <select name="status">
        <option value="">All Status</option>
        <option value="pending"   <?= $filter_status==='pending'   ? 'selected':'' ?>>Pending</option>
        <option value="active"    <?= $filter_status==='active'    ? 'selected':'' ?>>Active</option>
        <option value="completed" <?= $filter_status==='completed' ? 'selected':'' ?>>Completed</option>
        <option value="cancelled" <?= $filter_status==='cancelled' ? 'selected':'' ?>>Cancelled</option>
      </select>
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"/>
      <button type="submit" class="admin-btn blue">Filter</button>
      <a href="AdminReports.php" class="admin-btn ghost">Clear</a>
    </form>
    <div class="history-table-wrap">
      <table class="history-table">
        <thead>
          <tr>
            <th>#</th><th>Student ID</th><th>Name</th><th>Course</th>
            <th>Lab</th><th>Purpose</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($records)): ?>
            <tr><td colspan="10" class="empty-state">No records found.</td></tr>
          <?php endif; ?>
          <?php foreach ($records as $i => $r):
            $date_in  = new DateTime($r['date_in']);
            $date_out = $r['date_out'] ? new DateTime($r['date_out']) : null;
          ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><?= htmlspecialchars($r['sid']) ?></td>
            <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
            <td><?= htmlspecialchars($r['course_code']) ?></td>
            <td><?= htmlspecialchars($r['lab_room']) ?></td>
            <td><?= htmlspecialchars($r['purpose']) ?></td>
            <td><?= $date_in->format('M d, Y') ?></td>
            <td><?= $date_in->format('h:i A') ?></td>
            <td><?= $date_out ? $date_out->format('h:i A') : '<span style="color:#aaa;font-style:italic;">—</span>' ?></td>
            <td><span class="badge badge-<?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>
<?php include __DIR__ . '/footer.php'; ?>
<script>
const colors = ['#1877c9','#e74c3c','#6b21c8','#e8a020','#27ae60','#5aadea','#9b59e8','#f39c12'];
<?php if (!empty($purpose_stats)): ?>
new Chart(document.getElementById('purposeChart'), { type:'pie', data:{ labels:<?= json_encode(array_column($purpose_stats,'purpose')) ?>, datasets:[{ data:<?= json_encode(array_column($purpose_stats,'cnt')) ?>, backgroundColor:colors, borderWidth:2, borderColor:'#fff' }] }, options:{ responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{family:'DM Sans',size:11}, padding:10 } } } } });
<?php endif; ?>
<?php if (!empty($lab_stats)): ?>
new Chart(document.getElementById('labChart'), { type:'bar', data:{ labels:<?= json_encode(array_column($lab_stats,'lab_room')) ?>, datasets:[{ label:'Sit-Ins', data:<?= json_encode(array_column($lab_stats,'cnt')) ?>, backgroundColor:'#1877c9', borderRadius:6 }] }, options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{stepSize:1} } } } });
<?php endif; ?>
<?php if (!empty($daily_stats)): ?>
const daily = <?= json_encode(array_reverse($daily_stats)) ?>;
new Chart(document.getElementById('dailyChart'), { type:'line', data:{ labels:daily.map(d=>d.day), datasets:[{ label:'Sit-Ins', data:daily.map(d=>d.cnt), borderColor:'#6b21c8', backgroundColor:'rgba(107,33,200,0.08)', tension:0.4, fill:true, pointBackgroundColor:'#6b21c8' }] }, options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true, ticks:{stepSize:1} } } } });
<?php endif; ?>
</script>
</body>
</html>