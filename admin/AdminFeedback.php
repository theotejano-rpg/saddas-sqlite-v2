<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();

// Filters
$filter_lab  = trim($_GET['lab']  ?? '');
$filter_date = trim($_GET['date'] ?? '');
$search      = trim($_GET['q']    ?? '');

$where  = ["sl.feedback IS NOT NULL AND sl.feedback != ''"];
$params = [];

if ($filter_lab)  { $where[] = 'sl.lab_room = ?';        $params[] = $filter_lab; }
if ($filter_date) { $where[] = 'DATE(sl.date_in) = ?';   $params[] = $filter_date; }
if ($search) {
    $where[]  = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR sl.feedback LIKE ?)';
    $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
}

$sql = "
    SELECT sl.id, sl.lab_room, sl.purpose, sl.date_in, sl.feedback,
           s.first_name, s.last_name, s.student_id as sid, s.course_code
    FROM sitin_logs sl
    JOIN students s ON sl.student_id = s.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY sl.date_in DESC
";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Summary stats
$total_feedback  = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE feedback IS NOT NULL AND feedback != ''")->fetchColumn();
$total_sessions  = $db->query("SELECT COUNT(*) FROM sitin_logs WHERE status='completed'")->fetchColumn();
$response_rate   = $total_sessions > 0 ? round(($total_feedback / $total_sessions) * 100) : 0;

// Feedback count per lab
$lab_feedback = $db->query("
    SELECT lab_room, COUNT(*) as cnt
    FROM sitin_logs
    WHERE feedback IS NOT NULL AND feedback != ''
    GROUP BY lab_room ORDER BY cnt DESC
")->fetchAll();

// Feedback count per purpose
$purpose_feedback = $db->query("
    SELECT purpose, COUNT(*) as cnt
    FROM sitin_logs
    WHERE feedback IS NOT NULL AND feedback != ''
    GROUP BY purpose ORDER BY cnt DESC
")->fetchAll();

$lab_rooms = ['Lab 524', 'Lab 526', 'Lab 528', 'Lab 530'];

$nav_admin_active = 'feedback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Feedback Report</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .fb-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 14px;
      margin-bottom: 22px;
    }

    .fb-stat {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 14px;
      padding: 18px 22px;
      box-shadow: 0 4px 16px rgba(10,77,140,0.07);
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .fb-stat-icon {
      width: 46px; height: 46px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .fb-stat-icon.gold   { background: rgba(232,160,32,0.12); color: #a06010; }
    .fb-stat-icon.blue   { background: rgba(10,77,140,0.10);  color: #0a4d8c; }
    .fb-stat-icon.green  { background: rgba(26,140,78,0.10);  color: #1a7a42; }

    .fb-stat-info { flex: 1; }
    .fb-stat-num  {
      font-family: 'DM Serif Display', serif;
      font-size: 1.9rem; line-height: 1;
      color: var(--blue-deep);
    }
    .fb-stat-label { font-size: 0.75rem; color: var(--ink-soft); margin-top: 3px; }

    .fb-charts {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 22px;
    }

    .fb-filter-bar {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 18px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      flex-wrap: wrap;
    }

    .fb-filter-bar label { font-size: 0.78rem; color: var(--ink-soft); white-space: nowrap; }

    .fb-filter-bar input,
    .fb-filter-bar select {
      padding: 6px 12px;
      border: 1.5px solid #ccdeed;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      outline: none;
      transition: border-color 0.2s;
      background: #f8fafc;
    }

    .fb-filter-bar input:focus,
    .fb-filter-bar select:focus { border-color: #1877c9; }
    .fb-filter-bar input[type="text"] { width: 200px; }

    .fb-filter-submit {
      padding: 6px 16px;
      background: var(--blue-deep);
      color: white;
      border: none;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.18s;
    }
    .fb-filter-submit:hover { background: #1060aa; }

    .fb-filter-reset {
      padding: 6px 12px;
      background: transparent;
      color: var(--ink-soft);
      border: 1.5px solid #ccdeed;
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.82rem;
      cursor: pointer;
      text-decoration: none !important;
      transition: background 0.18s;
    }
    .fb-filter-reset:hover { background: rgba(10,77,140,0.05); }

    .fb-count-badge {
      margin-left: auto;
      font-size: 0.78rem;
      color: var(--ink-soft);
      white-space: nowrap;
    }

    .fb-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }

    .fb-table th {
      padding: 11px 16px;
      text-align: left;
      background: rgba(10,77,140,0.05);
      color: var(--blue-deep);
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border-bottom: 2px solid rgba(10,77,140,0.08);
      white-space: nowrap;
    }

    .fb-table td {
      padding: 13px 16px;
      color: var(--ink);
      border-bottom: 1px solid rgba(204,222,237,0.35);
      vertical-align: top;
    }

    .fb-table tr:last-child td { border-bottom: none; }
    .fb-table tr:hover td { background: rgba(10,77,140,0.02); cursor: pointer; }

    .fb-student-name { font-weight: 600; font-size: 0.87rem; }
    .fb-student-id   { font-size: 0.72rem; color: var(--ink-soft); margin-top: 1px; }

    .fb-lab-badge {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 700;
      background: rgba(10,77,140,0.08);
      color: #0a4d8c;
    }

    .fb-text-truncated {
      font-size: 0.82rem;
      color: var(--ink);
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      max-width: 340px;
    }

    .fb-view-btn {
      font-size: 0.72rem;
      color: #1877c9;
      background: rgba(24,119,201,0.08);
      border: none;
      border-radius: 6px;
      padding: 3px 9px;
      cursor: pointer;
      font-weight: 600;
      margin-top: 5px;
      display: inline-block;
      transition: background 0.15s;
    }
    .fb-view-btn:hover { background: rgba(24,119,201,0.16); }

    .fb-date { font-size: 0.75rem; color: var(--ink-soft); white-space: nowrap; }

    .fb-empty {
      padding: 60px 24px;
      text-align: center;
      color: var(--ink-soft);
    }
    .fb-empty svg { opacity: 0.25; margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }
    .fb-empty p { font-size: 0.88rem; margin: 0; }

    /* Modal */
    .fb-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.45);
      display: none;
      align-items: center;
      justify-content: center;
      z-index: 9999;
    }
    .fb-modal-overlay.open { display: flex; animation: fbFadeIn 0.2s ease; }
    @keyframes fbFadeIn { from{opacity:0} to{opacity:1} }

    .fb-modal {
      background: white;
      border-radius: 18px;
      width: 520px;
      max-width: 95vw;
      box-shadow: 0 20px 60px rgba(0,0,0,0.18);
      overflow: hidden;
    }

    .fb-modal-header {
      background: linear-gradient(135deg, #e8a020, #c88010);
      padding: 18px 22px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .fb-modal-title {
      font-family: 'DM Serif Display', serif;
      font-size: 1.05rem;
      color: white;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .fb-modal-close {
      background: rgba(255,255,255,0.2);
      border: none; color: white;
      width: 28px; height: 28px;
      border-radius: 50%;
      font-size: 1rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background 0.15s;
    }
    .fb-modal-close:hover { background: rgba(255,255,255,0.35); }

    .fb-modal-meta {
      background: rgba(232,160,32,0.05);
      border-bottom: 1px solid rgba(232,160,32,0.15);
      padding: 14px 22px;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .fb-modal-meta-item { font-size: 0.78rem; color: var(--ink-soft); }
    .fb-modal-meta-item strong { color: var(--ink); display: block; font-size: 0.85rem; margin-bottom: 1px; }

    .fb-modal-body { padding: 20px 22px 22px; }

    .fb-modal-feedback-label {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--blue-deep);
      margin-bottom: 10px;
    }

    .fb-modal-feedback-text {
      font-size: 0.9rem;
      color: var(--ink);
      line-height: 1.65;
      white-space: pre-wrap;
      background: rgba(10,77,140,0.03);
      border: 1px solid rgba(204,222,237,0.5);
      border-radius: 10px;
      padding: 14px 16px;
    }

    .fb-table-wrap { overflow-x: auto; }

    .fb-export-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      background: rgba(26,140,78,0.1);
      color: #1a7a42;
      border: 1.5px solid rgba(26,140,78,0.25);
      border-radius: 8px;
      font-size: 0.78rem;
      font-weight: 600;
      cursor: pointer;
      font-family: 'DM Sans', sans-serif;
      transition: background 0.15s;
    }
    .fb-export-btn:hover { background: rgba(26,140,78,0.18); }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>

<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Feedback Report</h2>

  <!-- Summary Stats -->
  <div class="fb-stats">
    <div class="fb-stat">
      <div class="fb-stat-icon gold">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <div class="fb-stat-info">
        <div class="fb-stat-num"><?= $total_feedback ?></div>
        <div class="fb-stat-label">Total Feedback Received</div>
      </div>
    </div>
    <div class="fb-stat">
      <div class="fb-stat-icon blue">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
      </div>
      <div class="fb-stat-info">
        <div class="fb-stat-num"><?= $total_sessions ?></div>
        <div class="fb-stat-label">Completed Sessions</div>
      </div>
    </div>
    <div class="fb-stat">
      <div class="fb-stat-icon green">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
      </div>
      <div class="fb-stat-info">
        <div class="fb-stat-num"><?= $response_rate ?>%</div>
        <div class="fb-stat-label">Response Rate</div>
      </div>
    </div>
  </div>

  <!-- Charts -->
  <?php if (!empty($lab_feedback) || !empty($purpose_feedback)): ?>
  <div class="fb-charts">
    <div class="admin-card">
      <div class="admin-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Feedback by Lab Room
      </div>
      <div class="chart-wrap" style="padding:16px;">
        <canvas id="labChart"></canvas>
      </div>
    </div>
    <div class="admin-card">
      <div class="admin-card-header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
        Feedback by Purpose
      </div>
      <div class="chart-wrap" style="padding:16px;">
        <canvas id="purposeChart"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Feedback Table -->
  <div class="admin-card">
    <div class="admin-card-header" style="justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:8px;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Student Feedback
      </span>
      <button class="fb-export-btn" onclick="exportCSV()">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Export CSV
      </button>
    </div>

    <form method="GET" action="AdminFeedback.php" class="fb-filter-bar">
      <label>Search:</label>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Name, ID, or keyword..."/>

      <label>Lab:</label>
      <select name="lab">
        <option value="">All Labs</option>
        <?php foreach ($lab_rooms as $lr): ?>
          <option value="<?= $lr ?>" <?= $filter_lab === $lr ? 'selected' : '' ?>><?= $lr ?></option>
        <?php endforeach; ?>
      </select>

      <label>Date:</label>
      <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>"/>

      <button type="submit" class="fb-filter-submit">Filter</button>
      <a href="AdminFeedback.php" class="fb-filter-reset">Reset</a>

      <span class="fb-count-badge"><?= count($feedbacks) ?> result<?= count($feedbacks) !== 1 ? 's' : '' ?></span>
    </form>

    <div class="fb-table-wrap">
      <?php if (empty($feedbacks)): ?>
        <div class="fb-empty">
          <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
          <p>No feedback found<?= ($search || $filter_lab || $filter_date) ? ' for these filters' : ' yet' ?>.</p>
        </div>
      <?php else: ?>
      <table class="fb-table" id="fbTable">
        <thead>
          <tr>
            <th>#</th>
            <th>Student</th>
            <th>Lab</th>
            <th>Purpose</th>
            <th>Date</th>
            <th>Feedback</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feedbacks as $i => $fb):
            $date_in = new DateTime($fb['date_in']);
            $modal_data = htmlspecialchars(json_encode([
              'name'    => $fb['first_name'] . ' ' . $fb['last_name'],
              'sid'     => $fb['sid'],
              'course'  => $fb['course_code'] ?? '',
              'lab'     => $fb['lab_room'],
              'purpose' => $fb['purpose'],
              'date'    => $date_in->format('M d, Y h:i A'),
              'text'    => $fb['feedback'],
            ]));
          ?>
          <tr onclick="openModal(<?= $modal_data ?>)">
            <td style="color:var(--ink-soft);font-size:0.78rem;"><?= $i + 1 ?></td>
            <td>
              <div class="fb-student-name"><?= htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']) ?></div>
              <div class="fb-student-id"><?= htmlspecialchars($fb['sid']) ?><?= $fb['course_code'] ? ' &bull; ' . htmlspecialchars($fb['course_code']) : '' ?></div>
            </td>
            <td><span class="fb-lab-badge"><?= htmlspecialchars($fb['lab_room']) ?></span></td>
            <td style="font-size:0.8rem;color:var(--ink-soft);"><?= htmlspecialchars($fb['purpose']) ?></td>
            <td class="fb-date"><?= $date_in->format('M d, Y') ?><br><?= $date_in->format('h:i A') ?></td>
            <td>
              <div class="fb-text-truncated"><?= htmlspecialchars($fb['feedback']) ?></div>
              <button class="fb-view-btn" onclick="event.stopPropagation();openModal(<?= $modal_data ?>)">View full</button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</main>

<?php include __DIR__ . '/footer.php'; ?>

<!-- Feedback Detail Modal -->
<div class="fb-modal-overlay" id="fbModal" onclick="if(event.target===this)closeModal()">
  <div class="fb-modal">
    <div class="fb-modal-header">
      <div class="fb-modal-title">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Student Feedback
      </div>
      <button class="fb-modal-close" onclick="closeModal()">&#10005;</button>
    </div>
    <div class="fb-modal-meta" id="fbModalMeta"></div>
    <div class="fb-modal-body">
      <div class="fb-modal-feedback-label">&#9998; Feedback Message</div>
      <div class="fb-modal-feedback-text" id="fbModalText"></div>
    </div>
  </div>
</div>

<script>
function openModal(data) {
  document.getElementById('fbModalMeta').innerHTML =
    `<div class="fb-modal-meta-item"><strong>${data.name}</strong>${data.sid}${data.course ? ' &bull; ' + data.course : ''}</div>` +
    `<div class="fb-modal-meta-item"><strong>${data.lab}</strong>${data.purpose}</div>` +
    `<div class="fb-modal-meta-item" style="grid-column:1/-1"><strong>Session Date</strong>${data.date}</div>`;
  document.getElementById('fbModalText').textContent = data.text;
  document.getElementById('fbModal').classList.add('open');
}
function closeModal() {
  document.getElementById('fbModal').classList.remove('open');
}

function exportCSV() {
  const rows = [['#','Student Name','Student ID','Course','Lab Room','Purpose','Date','Feedback']];
  let i = 1;
  document.querySelectorAll('#fbTable tbody tr').forEach(tr => {
    const raw = tr.getAttribute('onclick');
    const match = raw.match(/openModal\(([\s\S]*?)\)$/);
    if (!match) return;
    const data = JSON.parse(match[1]);
    rows.push([i++, data.name, data.sid, data.course, data.lab, data.purpose, data.date, data.text]);
  });
  const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
  const a = document.createElement('a');
  a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
  a.download = 'feedback_report_<?= date('Y-m-d') ?>.csv';
  a.click();
}

<?php if (!empty($lab_feedback)): ?>
new Chart(document.getElementById('labChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($lab_feedback, 'lab_room')) ?>,
    datasets: [{
      label: 'Feedback Count',
      data: <?= json_encode(array_column($lab_feedback, 'cnt')) ?>,
      backgroundColor: ['#1877c9','#6b21c8','#e8a020','#1a8c4e'],
      borderRadius: 6,
      borderSkipped: false,
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { display: false } },
    scales: {
      y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'DM Sans', size: 11 } }, grid: { color: 'rgba(10,77,140,0.06)' } },
      x: { ticks: { font: { family: 'DM Sans', size: 11 } }, grid: { display: false } }
    }
  }
});
<?php endif; ?>

<?php if (!empty($purpose_feedback)): ?>
new Chart(document.getElementById('purposeChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($purpose_feedback, 'purpose')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($purpose_feedback, 'cnt')) ?>,
      backgroundColor: ['#1877c9','#e74c3c','#6b21c8','#e8a020','#27ae60','#5aadea','#9b59e8','#f39c12'],
      borderWidth: 2,
      borderColor: '#fff'
    }]
  },
  options: {
    responsive: true,
    plugins: { legend: { position: 'bottom', labels: { font: { family: 'DM Sans', size: 11 }, padding: 10 } } }
  }
});
<?php endif; ?>
</script>
</body>
</html>