<?php
session_start();
require_once __DIR__ . '/db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();
$nav_admin_active = 'testimonials';

// Ensure testimonials table exists
$db->exec("
    CREATE TABLE IF NOT EXISTS testimonials (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL REFERENCES students(id),
        message    TEXT    NOT NULL,
        status     TEXT    NOT NULL DEFAULT 'pending',
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )
");

// Handle approve / reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int) $_POST['id'];
    $action = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
    $db->prepare("UPDATE testimonials SET status = ? WHERE id = ?")->execute([$action, $id]);
    // Notify about testimonial decision
    $testi = $db->prepare("SELECT t.*, s.first_name, s.last_name, s.student_id AS idno FROM testimonials t JOIN students s ON s.id = t.student_id WHERE t.id = ? LIMIT 1");
    $testi->execute([$id]); $testi = $testi->fetch();
    if ($testi) {
        log_notification('testimonial_decision', "{$testi['first_name']} {$testi['last_name']} ({$testi['idno']})'s testimonial has been {$action}.");
    }
    header("Location: AdminTestimonials.php");
    exit;
}

$pending = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno, s.profile_pic
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
")->fetchAll();

$all = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno, s.profile_pic
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    ORDER BY t.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Testimonials &mdash; Admin</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    .at-wrap { padding: 0 0 60px; }

    .at-section-title {
      font-size: 1.1rem; font-weight: 800;
      color: #0a4d8c; margin-bottom: 4px;
    }
    .at-section-sub {
      font-size: 0.83rem; color: #9aa5b4; margin-bottom: 20px;
    }

    .at-table {
      width: 100%; border-collapse: collapse;
      font-size: 0.85rem; margin-bottom: 36px;
      background: white; border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 12px rgba(10,77,140,0.07);
    }
    .at-table th {
      text-align: left; padding: 11px 16px;
      background: rgba(10,77,140,0.06);
      color: #0a4d8c; font-weight: 700;
      font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.6px;
    }
    .at-table td {
      padding: 12px 16px;
      border-bottom: 1px solid rgba(204,222,237,0.4);
      color: #1a2535; vertical-align: top;
    }
    .at-table tr:last-child td { border-bottom: none; }
    .at-table tr:hover td { background: rgba(10,77,140,0.02); }

    .status-badge {
      display: inline-block; padding: 3px 10px;
      border-radius: 20px; font-size: 0.7rem; font-weight: 700;
      text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-badge.pending  { background: rgba(232,160,32,0.12); color: #a06010; }
    .status-badge.approved { background: rgba(34,197,94,0.12);  color: #15803d; }
    .status-badge.rejected { background: rgba(224,53,53,0.10);  color: #b91c1c; }

    .at-action-btn {
      border: none; border-radius: 7px;
      padding: 6px 14px; font-size: 0.78rem;
      font-weight: 700; cursor: pointer;
      transition: opacity 0.15s; margin-right: 5px;
    }
    .at-action-btn.approve { background: rgba(34,197,94,0.15); color: #15803d; }
    .at-action-btn.approve:hover { background: rgba(34,197,94,0.3); }
    .at-action-btn.reject  { background: rgba(224,53,53,0.12);  color: #b91c1c; }
    .at-action-btn.reject:hover  { background: rgba(224,53,53,0.25); }

    .at-empty {
      text-align: center; color: #9aa5b4;
      font-size: 0.85rem; padding: 32px 0;
      background: white; border-radius: 12px;
      margin-bottom: 36px;
      box-shadow: 0 2px 12px rgba(10,77,140,0.07);
    }
    .divider-line { border: none; border-top: 1px solid rgba(204,222,237,0.6); margin: 32px 0; }
    .msg-cell { max-width: 340px; word-break: break-word; }

    /* Student identity cell with avatar */
    .student-id-cell { display: flex; align-items: center; gap: 10px; }
    .student-avatar {
      width: 38px; height: 38px; border-radius: 50%;
      object-fit: cover; flex-shrink: 0;
      border: 2px solid rgba(10,77,140,0.15);
      background: rgba(10,77,140,0.06);
    }
    .student-avatar-placeholder {
      width: 38px; height: 38px; border-radius: 50%; flex-shrink: 0;
      background: linear-gradient(135deg,#0a4d8c,#1877c9);
      display: flex; align-items: center; justify-content: center;
      font-size: 0.85rem; font-weight: 800; color: white;
      border: 2px solid rgba(10,77,140,0.15);
    }
    .student-name-info { display: flex; flex-direction: column; }
    .student-name-text { font-weight: 700; font-size: 0.85rem; color: #1a2535; line-height: 1.2; }
    .student-id-text  { font-size: 0.72rem; color: #9aa5b4; margin-top: 1px; }

    /* Testimony cards section */
    .at-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 16px;
      margin-bottom: 36px;
    }
    .at-testi-card {
      background: white;
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 16px;
      padding: 20px 22px 18px;
      box-shadow: 0 3px 14px rgba(10,77,140,0.06);
      display: flex; flex-direction: column; gap: 12px;
      position: relative; overflow: hidden;
    }
    .at-testi-card::before {
      content: '\201C';
      position: absolute; top: 10px; right: 16px;
      font-size: 3.5rem; color: rgba(10,77,140,0.06);
      font-family: Georgia, serif; line-height: 1;
      pointer-events: none;
    }
    .at-testi-card-header { display: flex; align-items: center; gap: 12px; }
    .at-testi-avatar {
      width: 46px; height: 46px; border-radius: 50%;
      object-fit: cover; flex-shrink: 0;
      border: 2px solid rgba(10,77,140,0.18);
    }
    .at-testi-avatar-placeholder {
      width: 46px; height: 46px; border-radius: 50%; flex-shrink: 0;
      background: linear-gradient(135deg,#0a4d8c,#1877c9);
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; font-weight: 800; color: white;
      border: 2px solid rgba(10,77,140,0.18);
    }
    .at-testi-meta { display: flex; flex-direction: column; }
    .at-testi-name { font-weight: 800; font-size: 0.88rem; color: #0a4d8c; line-height: 1.2; }
    .at-testi-idno { font-size: 0.72rem; color: #9aa5b4; margin-top: 2px; }
    .at-testi-msg {
      font-size: 0.86rem; color: #1a2535; line-height: 1.65;
      font-style: italic; flex: 1;
    }
    .at-testi-footer {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 8px;
      border-top: 1px solid rgba(204,222,237,0.5); padding-top: 10px;
    }
    .at-testi-date { font-size: 0.72rem; color: #9aa5b4; }
    .at-testi-actions { display: flex; gap: 6px; }
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

    /* Testimony cards dark */
    .dark-theme .at-testi-card {
      background: linear-gradient(180deg, var(--ds-panel), var(--ds-surface)) !important;
      border-color: var(--ds-border) !important;
      box-shadow: 0 8px 30px rgba(2,6,23,0.6) !important;
    }
    .dark-theme .at-testi-name { color: #93c5fd !important; }
    .dark-theme .at-testi-idno { color: var(--ds-muted) !important; }
    .dark-theme .at-testi-msg  { color: var(--ds-text) !important; }
    .dark-theme .at-testi-date { color: var(--ds-muted) !important; }
    .dark-theme .at-testi-footer { border-color: rgba(255,255,255,0.05) !important; }
    .dark-theme .student-name-text { color: var(--ds-text) !important; }
    .dark-theme .student-id-text  { color: var(--ds-muted) !important; }

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
  <h2 class="section-title">Testimonials</h2>

  <div class="at-wrap">

    <div class="at-section-title">⏳ Pending Testimonials</div>
    <div class="at-section-sub">Review and approve or reject student testimonials before they go public.</div>

    <?php if (empty($pending)): ?>
      <div class="at-empty">No pending testimonials right now.</div>
    <?php else: ?>
      <!-- Testimony Cards for pending -->
      <div class="at-cards-grid">
        <?php foreach ($pending as $t): ?>
        <?php
          $initials = strtoupper(substr($t['first_name'],0,1) . substr($t['last_name'],0,1));
          $pic = !empty($t['profile_pic']) ? '../uploads/profiles/' . htmlspecialchars($t['profile_pic']) : null;
        ?>
        <div class="at-testi-card">
          <div class="at-testi-card-header">
            <?php if ($pic): ?>
              <img src="<?= $pic ?>" alt="" class="at-testi-avatar"/>
            <?php else: ?>
              <div class="at-testi-avatar-placeholder"><?= $initials ?></div>
            <?php endif; ?>
            <div class="at-testi-meta">
              <span class="at-testi-name"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></span>
              <span class="at-testi-idno"><?= htmlspecialchars($t['idno']) ?></span>
            </div>
          </div>
          <div class="at-testi-msg"><?= htmlspecialchars($t['message']) ?></div>
          <div class="at-testi-footer">
            <span class="at-testi-date"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
            <div class="at-testi-actions">
              <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
                <button type="submit" name="action" value="reject"  class="at-action-btn reject">✗ Reject</button>
              </form>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <hr class="divider-line"/>

    <div class="at-section-title">📋 All Testimonials</div>
    <div class="at-section-sub">Full history of all submitted testimonials and their statuses.</div>

    <?php if (empty($all)): ?>
      <div class="at-empty">No testimonials submitted yet.</div>
    <?php else: ?>
      <!-- Testimony Cards for all testimonials -->
      <div class="at-cards-grid">
        <?php foreach ($all as $t): ?>
        <?php
          $initials = strtoupper(substr($t['first_name'],0,1) . substr($t['last_name'],0,1));
          $pic = !empty($t['profile_pic']) ? '../uploads/profiles/' . htmlspecialchars($t['profile_pic']) : null;
        ?>
        <div class="at-testi-card">
          <div class="at-testi-card-header">
            <?php if ($pic): ?>
              <img src="<?= $pic ?>" alt="" class="at-testi-avatar"/>
            <?php else: ?>
              <div class="at-testi-avatar-placeholder"><?= $initials ?></div>
            <?php endif; ?>
            <div class="at-testi-meta">
              <span class="at-testi-name"><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></span>
              <span class="at-testi-idno"><?= htmlspecialchars($t['idno']) ?></span>
            </div>
          </div>
          <div class="at-testi-msg"><?= htmlspecialchars($t['message']) ?></div>
          <div class="at-testi-footer">
            <div style="display:flex;align-items:center;gap:8px;">
              <span class="status-badge <?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span>
              <span class="at-testi-date"><?= date('M d, Y', strtotime($t['created_at'])) ?></span>
            </div>
            <div class="at-testi-actions">
              <?php if ($t['status'] !== 'approved'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
              </form>
              <?php endif; ?>
              <?php if ($t['status'] !== 'rejected'): ?>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <button type="submit" name="action" value="reject" class="at-action-btn reject">✗ Reject</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</main>
<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>