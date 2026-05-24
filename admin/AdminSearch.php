<?php
session_start();
require_once 'db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }
$db = get_db();

$search_query  = trim($_GET['q'] ?? '');
$search_result = null;

if ($search_query !== '') {
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? LIMIT 1");
    $stmt->execute(["%$search_query%","%$search_query%","%$search_query%","%$search_query%"]);
    $search_result = $stmt->fetch();
}

$nav_admin_active = 'search';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>UC CCS &mdash; Search Students</title>
  <link rel="stylesheet" href="../css/Style.css"/>
  <link rel="stylesheet" href="../css/Admin.css"/>
  <style>
    body.admin-page a { text-decoration: none !important; }

    .search-layout {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 24px;
      align-items: start;
    }

    .search-left { display: flex; flex-direction: column; gap: 16px; }

    .search-input-wrap {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .search-input-wrap input {
      flex: 1;
      padding: 12px 16px;
      border: 1.5px solid #ccdeed;
      border-radius: 10px;
      font-family: 'DM Sans', sans-serif;
      font-size: 0.9rem;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
      background: rgba(255,255,255,0.85);
    }

    .search-input-wrap input:focus {
      border-color: #1877c9;
      box-shadow: 0 0 0 3px rgba(24,119,201,0.1);
    }

    .search-hint {
      font-size: 0.8rem;
      color: var(--ink-soft);
      line-height: 1.6;
      padding: 14px 16px;
      background: rgba(10,77,140,0.04);
      border: 1px solid rgba(10,77,140,0.1);
      border-left: 3px solid var(--blue-mid);
      border-radius: 10px;
    }

    .search-hint strong { color: var(--blue-deep); display: block; margin-bottom: 6px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }

    .search-hint ul { padding-left: 16px; margin: 0; }
    .search-hint li { margin-bottom: 3px; }

    /* Student Profile Card */
    .profile-result-card {
      background: rgba(255,255,255,0.88);
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 18px;
      box-shadow: 0 8px 32px rgba(10,77,140,0.1);
      overflow: hidden;
      animation: fadeUp 0.3s ease;
    }

    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(12px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .profile-result-top {
      background: linear-gradient(135deg, var(--blue-deep), #1877c9);
      padding: 28px 24px;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 12px;
    }

    .profile-result-avatar {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: rgba(255,255,255,0.15);
      border: 3px solid rgba(255,255,255,0.4);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
    }

    .profile-result-name {
      font-family: 'DM Serif Display', serif;
      font-size: 1.2rem;
      color: white;
      text-align: center;
      line-height: 1.3;
    }

    .profile-result-id {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.7);
      letter-spacing: 1.5px;
      font-weight: 500;
    }

    .profile-result-body {
      padding: 20px 24px;
      display: flex;
      flex-direction: column;
      gap: 0;
    }

    .profile-result-row {
      display: flex;
      flex-direction: column;
      gap: 2px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(204,222,237,0.5);
    }

    .profile-result-row:last-child { border-bottom: none; }

    .profile-result-label {
      font-size: 0.65rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      color: var(--ink-soft);
    }

    .profile-result-val {
      font-size: 0.88rem;
      color: var(--ink);
    }

    .profile-result-footer {
      padding: 16px 24px;
      border-top: 1px solid rgba(204,222,237,0.5);
      display: flex;
      gap: 10px;
    }

    .profile-session-badge {
      flex: 1;
      background: linear-gradient(135deg, var(--blue-deep), #1877c9);
      border-radius: 10px;
      padding: 12px 16px;
      text-align: center;
    }

    .profile-session-num {
      font-family: 'DM Serif Display', serif;
      font-size: 1.8rem;
      color: white;
      line-height: 1;
    }

    .profile-session-lbl {
      font-size: 0.65rem;
      color: rgba(255,255,255,0.7);
      text-transform: uppercase;
      letter-spacing: 1px;
      margin-top: 2px;
    }

    .no-result-card {
      background: rgba(255,255,255,0.88);
      border: 1px solid rgba(204,222,237,0.7);
      border-radius: 18px;
      padding: 48px 24px;
      text-align: center;
      color: var(--ink-soft);
    }

    .no-result-card svg { margin-bottom: 12px; opacity: 0.3; }
    .no-result-card p { font-size: 0.9rem; margin: 0; }
    .no-result-card small { font-size: 0.78rem; color: var(--ink-soft); opacity: 0.7; }

    .empty-placeholder {
      background: rgba(255,255,255,0.4);
      border: 2px dashed rgba(10,77,140,0.15);
      border-radius: 18px;
      padding: 48px 24px;
      text-align: center;
      color: var(--ink-soft);
      opacity: 0.7;
    }

    .empty-placeholder svg { margin-bottom: 12px; }
    .empty-placeholder p { font-size: 0.88rem; margin: 0; }

    @media (max-width: 820px) {
      .search-layout { grid-template-columns: 1fr; }
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
  <h2 class="section-title">Search Student</h2>

  <div class="search-layout">

    <!-- LEFT: Search form -->
    <div class="search-left">
      <div class="admin-card">
        <div class="admin-card-header">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          Find a Student
        </div>
        <div style="padding:20px;">
          <form method="GET" action="AdminSearch.php">
            <div class="search-input-wrap">
              <input type="text" name="q" value="<?= htmlspecialchars($search_query) ?>"
                placeholder="Enter Student ID, name or email..."
                autofocus/>
              <button type="submit" class="admin-btn blue">Search</button>
            </div>
          </form>
        </div>
      </div>


    </div>

    <!-- RIGHT: Student profile card -->
    <div>
      <?php if ($search_query && $search_result): ?>
        <?php $remaining = $search_result['sessions'] - $search_result['used']; ?>
        <div class="profile-result-card">
          <div class="profile-result-top">
            <div class="profile-result-avatar">
              <?php if (!empty($search_result['profile_pic']) && file_exists(__DIR__ . '/../' . $search_result['profile_pic'])): ?>
                <img src="../<?= htmlspecialchars($search_result['profile_pic']) ?>" alt="Profile" style="width:80px;height:80px;object-fit:cover;border-radius:50%;"/>
              <?php else: ?>
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                  <circle cx="12" cy="7" r="4"/>
                </svg>
              <?php endif; ?>
            </div>
            <div class="profile-result-name">
              <?= htmlspecialchars($search_result['first_name'].' '.($search_result['middle_name']?$search_result['middle_name'].' ':'').$search_result['last_name']) ?>
            </div>
            <div class="profile-result-id"><?= htmlspecialchars($search_result['student_id']) ?></div>
          </div>

          <div class="profile-result-body">
            <div class="profile-result-row">
              <span class="profile-result-label">Course</span>
              <span class="profile-result-val"><?= htmlspecialchars($search_result['course']) ?></span>
            </div>
            <div class="profile-result-row">
              <span class="profile-result-label">Year Level</span>
              <span class="profile-result-val"><?= htmlspecialchars($search_result['level']) ?></span>
            </div>
            <div class="profile-result-row">
              <span class="profile-result-label">Email</span>
              <span class="profile-result-val"><?= htmlspecialchars($search_result['email']) ?></span>
            </div>
            <div class="profile-result-row">
              <span class="profile-result-label">Address</span>
              <span class="profile-result-val"><?= htmlspecialchars($search_result['address']) ?></span>
            </div>
          </div>

          <div class="profile-result-footer">
            <div class="profile-session-badge">
              <div class="profile-session-num"><?= $remaining ?></div>
              <div class="profile-session-lbl">Sessions Remaining</div>
            </div>
            <div class="profile-session-badge" style="background: linear-gradient(135deg, #6b21c8, #9b59e8);">
              <div class="profile-session-num"><?= $search_result['used'] ?></div>
              <div class="profile-session-lbl">Sessions Used</div>
            </div>
          </div>

          <div style="padding:0 24px 20px;display:flex;gap:10px;">
            <a href="AdminSitin.php?q=<?= urlencode($search_result['student_id']) ?>" class="admin-btn blue" style="flex:1;justify-content:center;">
              + Sit-In this Student
            </a>
          </div>
        </div>

      <?php elseif ($search_query && !$search_result): ?>
        <div class="no-result-card">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <p>No student found for "<strong><?= htmlspecialchars($search_query) ?></strong>"</p>
          <small>Try searching with a different name or ID.</small>
        </div>

      <?php else: ?>
        <div class="empty-placeholder">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round" color="var(--rule)">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <p>Student profile will appear here after searching.</p>
        </div>
      <?php endif; ?>
    </div>

  </div>
</main>
<?php include __DIR__ . '/footer.php'; ?>
</body>
</html>