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