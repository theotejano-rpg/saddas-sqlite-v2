<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['student'])) { header('Location: Login.php'); exit; }

$db      = get_db();
$student = $_SESSION['student'];

// Refresh student row
$stmt = $db->prepare('SELECT * FROM students WHERE id = ? LIMIT 1');
$stmt->execute([$student['id']]);
$student = $stmt->fetch();
if (!$student) { session_destroy(); header('Location: Login.php'); exit; }

// All logs for this student
$all_logs = $db->prepare("SELECT * FROM sitin_logs WHERE student_id = ? ORDER BY date_in DESC");
$all_logs->execute([$student['id']]);
$all_logs = $all_logs->fetchAll();

$completed_logs = array_filter($all_logs, fn($l) => $l['status'] === 'completed' && $l['date_out']);
$total_logs     = count($all_logs);
$completed_count = count($completed_logs);
$cancelled_count = count(array_filter($all_logs, fn($l) => $l['status'] === 'cancelled'));
$pending_count   = count(array_filter($all_logs, fn($l) => $l['status'] === 'pending'));

// Duration stats
$total_minutes = 0;
$longest_min   = 0;
$shortest_min  = PHP_INT_MAX;
$lab_counts    = [];
$purpose_counts = [];
$sessions_by_month = [];
$durations     = [];

foreach ($completed_logs as $log) {
    $in  = strtotime($log['date_in']);
    $out = strtotime($log['date_out']);
    if ($in && $out && $out > $in) {
        $mins = ($out - $in) / 60;
        $total_minutes  += $mins;
        $durations[]     = $mins;
        if ($mins > $longest_min)  $longest_min  = $mins;
        if ($mins < $shortest_min) $shortest_min = $mins;
    }
    if ($log['lab_room']) $lab_counts[$log['lab_room']]   = ($lab_counts[$log['lab_room']] ?? 0) + 1;
    if ($log['purpose'])  $purpose_counts[$log['purpose']] = ($purpose_counts[$log['purpose']] ?? 0) + 1;
    $month_key = date('M Y', strtotime($log['date_in']));
    $sessions_by_month[$month_key] = ($sessions_by_month[$month_key] ?? 0) + 1;
}

$avg_minutes     = $completed_count > 0 ? $total_minutes / $completed_count : 0;
$shortest_min    = $durations ? $shortest_min : 0;
$completion_rate = $total_logs > 0 ? round(($completed_count / $total_logs) * 100) : 0;
$points          = $student['used'] * 20;
$remaining       = $student['sessions'] - $student['used'];

arsort($lab_counts);     $fav_lab      = $lab_counts     ? array_key_first($lab_counts)     : '—';
arsort($purpose_counts); $fav_purpose  = $purpose_counts ? array_key_first($purpose_counts) : '—';

function fmt_dur(float $m): string {
    if ($m <= 0) return '—';
    $h = floor($m / 60); $s = round(fmod($m, 60));
    if ($h > 0 && $s > 0) return "{$h}h {$s}m";
    if ($h > 0) return "{$h}h";
    return "{$s}m";
}

$nav_student_active = 'summary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; My Summary</title>
  <link rel="stylesheet" href="css/Style.css"/>
  <link rel="stylesheet" href="css/Students.css"/>
  <style>
    body.student-page a { text-decoration: none !important; }

    .summary-wrap {
      max-width: 1050px;
      margin: 32px auto 0;
      padding: 0 28px 64px;
      flex: 1;
    }

    /* ── Stat cards ── */
    .sum-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 14px;
      margin: 24px 0 28px;
    }
    .sum-card {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 16px;
      padding: 20px 18px 16px;
      box-shadow: 0 4px 16px rgba(10,77,140,0.07);
      display: flex; flex-direction: column; gap: 3px;
      transition: transform 0.18s, box-shadow 0.18s;
    }
    .sum-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(10,77,140,0.12); }
    .sum-card--blue {
      background: linear-gradient(135deg, #0a4d8c 0%, #1877c9 100%) !important;
      border-color: transparent !important;
      box-shadow: 0 6px 24px rgba(10,77,140,0.28) !important;
    }
    .sum-card--blue .sum-icon,
    .sum-card--blue .sum-val,
    .sum-card--blue .sum-lbl,
    .sum-card--blue .sum-sub { color: #fff !important; }
    .sum-card--blue .sum-sub { opacity: 0.72; }

    .sum-icon { font-size: 1.5rem; line-height: 1; margin-bottom: 6px; }
    .sum-val  { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--blue-deep); line-height: 1.1; }
    .sum-val--sm { font-size: 1.1rem; font-family: inherit; font-weight: 800; word-break: break-word; }
    .sum-lbl  { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.9px; color: var(--ink-soft); margin-top: 2px; }
    .sum-sub  { font-size: 0.74rem; color: var(--ink-soft); margin-top: 2px; line-height: 1.3; }

    /* ── Two-column panels ── */
    .sum-panels {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 18px;
      margin-bottom: 18px;
    }
    .sum-panel {
      background: rgba(255,255,255,0.82);
      border: 1px solid rgba(255,255,255,0.9);
      border-radius: 16px;
      box-shadow: 0 4px 16px rgba(10,77,140,0.07);
      overflow: hidden;
    }
    .sum-panel-header {
      background: var(--blue-deep);
      color: white;
      padding: 13px 20px;
      font-family: 'DM Serif Display', serif;
      font-size: 0.95rem;
      display: flex; align-items: center; gap: 8px;
    }
    .sum-panel-body { padding: 16px 20px; }

    /* Bar chart rows */
    .bar-row { margin-bottom: 14px; }
    .bar-row:last-child { margin-bottom: 0; }
    .bar-label { display: flex; justify-content: space-between; font-size: 0.78rem; margin-bottom: 5px; }
    .bar-label span:first-child { color: var(--ink); font-weight: 600; }
    .bar-label span:last-child  { color: var(--ink-soft); }
    .bar-track { height: 8px; background: rgba(10,77,140,0.07); border-radius: 99px; overflow: hidden; }
    .bar-fill  { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #0a4d8c, #1877c9); transition: width 0.6s ease; }
    .bar-fill--purple { background: linear-gradient(90deg, #7c3aed, #a855f7); }

    /* Month activity list */
    .month-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(204,222,237,0.3); font-size: 0.83rem; }
    .month-row:last-child { border-bottom: none; }
    .month-name { width: 70px; color: var(--ink); font-weight: 600; flex-shrink: 0; }
    .month-track { flex: 1; height: 8px; background: rgba(10,77,140,0.07); border-radius: 99px; overflow: hidden; }
    .month-fill  { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #0a4d8c, #1877c9); }
    .month-count { width: 30px; text-align: right; color: var(--ink-soft); font-size: 0.78rem; flex-shrink: 0; }

    /* Status breakdown */
    .status-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(204,222,237,0.3); font-size: 0.84rem; }
    .status-row:last-child { border-bottom: none; }
    .status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .status-name { flex: 1; margin-left: 10px; color: var(--ink); }
    .status-count { font-weight: 700; color: var(--blue-deep); }
    .status-pct { font-size: 0.72rem; color: var(--ink-soft); margin-left: 6px; }

    /* Empty state */
    .sum-empty { text-align: center; padding: 40px 20px; color: var(--ink-soft); font-size: 0.88rem; }

    /* ── Dark mode ── */
    html.dark-theme .sum-card { background: var(--dt-panel) !important; border-color: rgba(255,255,255,0.06) !important; box-shadow: 0 4px 20px rgba(2,6,23,0.4) !important; }
    html.dark-theme .sum-card:hover { box-shadow: 0 8px 32px rgba(2,6,23,0.55) !important; }
    html.dark-theme .sum-card--blue { background: linear-gradient(135deg, #0d3d72, #1460a8) !important; border-color: rgba(74,163,255,0.15) !important; }
    html.dark-theme .sum-val  { color: #93c5fd !important; }
    html.dark-theme .sum-lbl  { color: #6b7a8d !important; }
    html.dark-theme .sum-sub  { color: #6b7a8d !important; }
    html.dark-theme .sum-panel { background: var(--dt-panel) !important; border-color: rgba(255,255,255,0.06) !important; }
    html.dark-theme .bar-track  { background: rgba(255,255,255,0.06) !important; }
    html.dark-theme .month-track { background: rgba(255,255,255,0.06) !important; }
    html.dark-theme .bar-label span:first-child { color: var(--dt-text) !important; }
    html.dark-theme .bar-label span:last-child  { color: var(--dt-muted) !important; }
    html.dark-theme .month-name  { color: var(--dt-text) !important; }
    html.dark-theme .month-count { color: var(--dt-muted) !important; }
    html.dark-theme .month-row   { border-bottom-color: rgba(255,255,255,0.05) !important; }
    html.dark-theme .status-row  { border-bottom-color: rgba(255,255,255,0.05) !important; }
    html.dark-theme .status-name { color: var(--dt-text) !important; }
    html.dark-theme .status-count { color: #93c5fd !important; }
    html.dark-theme .status-pct  { color: var(--dt-muted) !important; }
    html.dark-theme .sum-empty   { color: var(--dt-muted) !important; }

    @media (max-width: 860px) {
      .sum-grid   { grid-template-columns: repeat(2, 1fr); }
      .sum-panels { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
      .sum-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
      .sum-val  { font-size: 1.6rem; }
    }
  </style>
</head>
<body class="student-page" style="display:flex;flex-direction:column;min-height:100vh;">

<?php include __DIR__ . '/nav_student.php'; ?>

<div class="summary-wrap">
  <span class="section-eyebrow">My Account</span>
  <h2 class="section-title">Sit-In Summary</h2>

  <!-- ── Top stat cards ── -->
  <div class="sum-grid">

    <div class="sum-card sum-card--blue">
      <div class="sum-icon">⏱️</div>
      <div class="sum-val"><?= $total_minutes > 0 ? number_format($total_minutes / 60, 1) : '0' ?></div>
      <div class="sum-lbl">Total Hours</div>
      <div class="sum-sub"><?= fmt_dur($total_minutes) ?> in the lab</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">📊</div>
      <div class="sum-val"><?= fmt_dur($avg_minutes) ?></div>
      <div class="sum-lbl">Avg. Session</div>
      <div class="sum-sub">Per completed sit-in</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">🏅</div>
      <div class="sum-val"><?= fmt_dur($longest_min) ?></div>
      <div class="sum-lbl">Longest Session</div>
      <div class="sum-sub">Personal best</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">⚡</div>
      <div class="sum-val"><?= fmt_dur($shortest_min) ?></div>
      <div class="sum-lbl">Shortest Session</div>
      <div class="sum-sub">Quickest completed</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">✅</div>
      <div class="sum-val"><?= $completed_count ?></div>
      <div class="sum-lbl">Sessions Completed</div>
      <div class="sum-sub"><?= $completion_rate ?>% completion rate</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">⭐</div>
      <div class="sum-val"><?= number_format($points) ?></div>
      <div class="sum-lbl">Points Earned</div>
      <div class="sum-sub"><?= $student['used'] ?> sessions × 20 pts</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">🖥️</div>
      <div class="sum-val sum-val--sm"><?= htmlspecialchars($fav_lab) ?></div>
      <div class="sum-lbl">Favourite Lab</div>
      <div class="sum-sub">Most visited room</div>
    </div>

    <div class="sum-card">
      <div class="sum-icon">🔋</div>
      <div class="sum-val"><?= $remaining ?></div>
      <div class="sum-lbl">Sessions Left</div>
      <div class="sum-sub">of <?= $student['sessions'] ?> allotted</div>
    </div>

  </div>

  <?php if ($completed_count === 0): ?>
    <div class="sum-empty">📭 No completed sessions yet — reserve one to start building your stats!</div>
  <?php else: ?>

  <!-- ── Detail panels ── -->
  <div class="sum-panels">

    <!-- Lab breakdown -->
    <div class="sum-panel">
      <div class="sum-panel-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Lab Room Usage
      </div>
      <div class="sum-panel-body">
        <?php if ($lab_counts):
          $max_lab = max($lab_counts);
          foreach ($lab_counts as $lab => $cnt):
            $pct = round(($cnt / $max_lab) * 100);
        ?>
        <div class="bar-row">
          <div class="bar-label">
            <span><?= htmlspecialchars($lab) ?></span>
            <span><?= $cnt ?> session<?= $cnt > 1 ? 's' : '' ?></span>
          </div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach; else: ?>
        <div class="sum-empty">No lab data yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Purpose breakdown -->
    <div class="sum-panel">
      <div class="sum-panel-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Top Purposes
      </div>
      <div class="sum-panel-body">
        <?php if ($purpose_counts):
          $max_p = max($purpose_counts);
          foreach ($purpose_counts as $pur => $cnt):
            $pct = round(($cnt / $max_p) * 100);
        ?>
        <div class="bar-row">
          <div class="bar-label">
            <span><?= htmlspecialchars($pur) ?></span>
            <span><?= $cnt ?>×</span>
          </div>
          <div class="bar-track"><div class="bar-fill bar-fill--purple" style="width:<?= $pct ?>%"></div></div>
        </div>
        <?php endforeach; else: ?>
        <div class="sum-empty">No purpose data yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Monthly activity -->
    <div class="sum-panel">
      <div class="sum-panel-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Sessions by Month
      </div>
      <div class="sum-panel-body">
        <?php if ($sessions_by_month):
          $max_m = max($sessions_by_month);
          foreach ($sessions_by_month as $month => $cnt):
            $pct = round(($cnt / $max_m) * 100);
        ?>
        <div class="month-row">
          <div class="month-name"><?= $month ?></div>
          <div class="month-track"><div class="month-fill" style="width:<?= $pct ?>%"></div></div>
          <div class="month-count"><?= $cnt ?></div>
        </div>
        <?php endforeach; else: ?>
        <div class="sum-empty">No monthly data yet.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Status breakdown -->
    <div class="sum-panel">
      <div class="sum-panel-header">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
        Session Breakdown
      </div>
      <div class="sum-panel-body">
        <?php
          $statuses = [
            ['label'=>'Completed', 'count'=>$completed_count, 'color'=>'#1877c9'],
            ['label'=>'Pending',   'count'=>$pending_count,   'color'=>'#e8a020'],
            ['label'=>'Cancelled', 'count'=>$cancelled_count, 'color'=>'#c0392b'],
          ];
          foreach ($statuses as $s):
            $pct = $total_logs > 0 ? round(($s['count'] / $total_logs) * 100) : 0;
        ?>
        <div class="status-row">
          <div class="status-dot" style="background:<?= $s['color'] ?>"></div>
          <div class="status-name"><?= $s['label'] ?></div>
          <div class="status-count"><?= $s['count'] ?></div>
          <div class="status-pct"><?= $pct ?>%</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>