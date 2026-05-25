<?php
session_start();
require_once __DIR__ . '/db.php';
if (empty($_SESSION['admin'])) { header('Location: ../Login.php'); exit; }

$db = get_db();
$nav_admin_active = 'testimonials';

// Handle approve / reject / delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id     = (int) $_POST['id'];
    $action = $_POST['action'];

    if ($action === 'delete') {
        $db->prepare("DELETE FROM testimonials WHERE id = ?")->execute([$id]);
    } else {
        $status = $action === 'approve' ? 'approved' : 'rejected';
        $db->prepare("UPDATE testimonials SET status = ? WHERE id = ?")->execute([$status, $id]);
        $testi = $db->prepare("SELECT t.*, s.first_name, s.last_name, s.student_id AS idno FROM testimonials t JOIN students s ON s.id = t.student_id WHERE t.id = ? LIMIT 1");
        $testi->execute([$id]);
        $testi = $testi->fetch();
        if ($testi) {
            log_notification('testimonial_decision', "{$testi['first_name']} {$testi['last_name']} ({$testi['idno']})'s testimonial has been {$status}.");
        }
    }
    header("Location: AdminTestimonials.php"); exit;
}

// Pending only — not yet reviewed
$pending = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno, s.profile_pic
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
")->fetchAll();

// All reviewed (approved or rejected) — excludes pending to avoid duplication
$reviewed = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno, s.profile_pic
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status IN ('approved','rejected')
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
    .at-section-title { font-size: 1.1rem; font-weight: 800; color: #0a4d8c; margin-bottom: 4px; }
    .at-section-sub   { font-size: 0.83rem; color: #9aa5b4; margin-bottom: 20px; }

    .at-cards-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
      gap: 16px; margin-bottom: 36px;
    }
    .at-testi-card {
      background: white; border: 1px solid rgba(204,222,237,0.7);
      border-radius: 16px; padding: 20px 22px 16px;
      box-shadow: 0 3px 14px rgba(10,77,140,0.06);
      display: flex; flex-direction: column; gap: 12px;
      position: relative; overflow: hidden;
    }
    .at-testi-card::before {
      content: '\201C'; position: absolute; top: 10px; right: 16px;
      font-size: 3.5rem; color: rgba(10,77,140,0.06);
      font-family: Georgia,serif; line-height: 1; pointer-events: none;
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
    .at-testi-meta { display: flex; flex-direction: column; min-width: 0; }
    .at-testi-name { font-weight: 800; font-size: 0.88rem; color: #0a4d8c; line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .at-testi-idno { font-size: 0.72rem; color: #9aa5b4; margin-top: 2px; }
    .at-testi-msg  { font-size: 0.86rem; color: #1a2535; line-height: 1.65; font-style: italic; flex: 1; }
    .at-testi-footer {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 8px;
      border-top: 1px solid rgba(204,222,237,0.5); padding-top: 10px;
    }
    .at-testi-date { font-size: 0.72rem; color: #9aa5b4; }
    .at-testi-actions { display: flex; gap: 6px; align-items: center; }

    .at-action-btn {
      border: none; border-radius: 7px; padding: 6px 14px;
      font-size: 0.78rem; font-weight: 700; cursor: pointer;
      transition: opacity 0.15s; font-family: 'DM Sans', sans-serif;
    }
    .at-action-btn.approve { background: rgba(34,197,94,0.15);  color: #15803d; }
    .at-action-btn.approve:hover { background: rgba(34,197,94,0.3); }
    .at-action-btn.reject  { background: rgba(224,53,53,0.12);  color: #b91c1c; }
    .at-action-btn.reject:hover  { background: rgba(224,53,53,0.25); }
    .at-action-btn.delete  { background: rgba(100,100,100,0.1); color: #6b7280; }
    .at-action-btn.delete:hover  { background: rgba(100,100,100,0.2); }

    .status-badge {
      display: inline-block; padding: 3px 10px; border-radius: 20px;
      font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .status-badge.pending  { background: rgba(232,160,32,0.12); color: #a06010; }
    .status-badge.approved { background: rgba(34,197,94,0.12);  color: #15803d; }
    .status-badge.rejected { background: rgba(224,53,53,0.10);  color: #b91c1c; }

    .at-empty {
      text-align: center; color: #9aa5b4; font-size: 0.85rem;
      padding: 32px 0; background: white; border-radius: 12px;
      margin-bottom: 36px; box-shadow: 0 2px 12px rgba(10,77,140,0.07);
    }
    .divider-line { border: none; border-top: 1px solid rgba(204,222,237,0.6); margin: 32px 0; }

    /* ── Dark theme ── */
    html.dark-theme .at-section-title { color: #ffffff !important; }
    html.dark-theme .at-section-sub   { color: #9aa5b4 !important; }
    html.dark-theme .at-testi-card    { background: #0c1622 !important; border-color: rgba(255,255,255,0.05) !important; box-shadow: 0 8px 30px rgba(2,6,23,0.5) !important; }
    html.dark-theme .at-testi-name    { color: #93c5fd !important; }
    html.dark-theme .at-testi-idno    { color: #9aa5b4 !important; }
    html.dark-theme .at-testi-msg     { color: #ffffff !important; }
    html.dark-theme .at-testi-date    { color: #9aa5b4 !important; }
    html.dark-theme .at-testi-footer  { border-color: rgba(255,255,255,0.06) !important; }
    html.dark-theme .at-empty         { background: #0c1622 !important; color: #9aa5b4 !important; }
    html.dark-theme .divider-line     { border-color: rgba(255,255,255,0.04) !important; }
    html.dark-theme .status-badge.pending  { background: rgba(232,160,32,0.18) !important; color: #fbbf24 !important; }
    html.dark-theme .status-badge.approved { background: rgba(34,197,94,0.15)  !important; color: #86efac !important; }
    html.dark-theme .status-badge.rejected { background: rgba(220,38,38,0.15)  !important; color: #fca5a5 !important; }
    html.dark-theme .at-action-btn.approve { background: rgba(34,197,94,0.12)  !important; color: #86efac !important; }
    html.dark-theme .at-action-btn.reject  { background: rgba(220,38,38,0.10)  !important; color: #fca5a5 !important; }
    html.dark-theme .at-action-btn.delete  { background: rgba(255,255,255,0.05) !important; color: #9aa5b4 !important; }
  </style>
</head>
<body class="admin-page" style="display:flex;flex-direction:column;min-height:100vh;">
<?php include __DIR__ . '/nav_admin.php'; ?>
<main class="admin-main" style="flex:1;">
  <span class="section-eyebrow">Administration</span>
  <h2 class="section-title">Testimonials</h2>

  <!-- ── Pending ─────────────────────────────────────────────────────────── -->
  <div class="at-section-title">⏳ Pending Testimonials</div>
  <div class="at-section-sub">Review and approve or reject student testimonials before they go public.</div>

  <?php if (empty($pending)): ?>
    <div class="at-empty">No pending testimonials right now. ✓</div>
  <?php else: ?>
    <div class="at-cards-grid">
      <?php foreach ($pending as $t): ?>
        <?php
          $initials = strtoupper(substr($t['first_name'],0,1) . substr($t['last_name'],0,1));
          // profile_pic stores the full relative path e.g. "uploads/profiles/file.jpg"
          $picFile  = !empty($t['profile_pic']) ? $t['profile_pic'] : null;
          $picFull  = $picFile && file_exists(__DIR__ . '/../' . $picFile)
                      ? '../' . htmlspecialchars($picFile) : null;
        ?>
        <div class="at-testi-card">
          <div class="at-testi-card-header">
            <?php if ($picFull): ?>
              <img src="<?= $picFull ?>" alt="" class="at-testi-avatar"/>
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
              <form method="POST" style="display:contents;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
                <button type="submit" name="action" value="reject"  class="at-action-btn reject">✗ Reject</button>
                <button type="submit" name="action" value="delete"  class="at-action-btn delete" onclick="return confirm('Delete this testimonial permanently?')">🗑</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <hr class="divider-line"/>

  <!-- ── Reviewed ────────────────────────────────────────────────────────── -->
  <div class="at-section-title">📋 Reviewed Testimonials</div>
  <div class="at-section-sub">All approved and rejected testimonials.</div>

  <?php if (empty($reviewed)): ?>
    <div class="at-empty">No reviewed testimonials yet.</div>
  <?php else: ?>
    <div class="at-cards-grid">
      <?php foreach ($reviewed as $t): ?>
        <?php
          $initials = strtoupper(substr($t['first_name'],0,1) . substr($t['last_name'],0,1));
          // profile_pic stores the full relative path e.g. "uploads/profiles/file.jpg"
          $picFile  = !empty($t['profile_pic']) ? $t['profile_pic'] : null;
          $picFull  = $picFile && file_exists(__DIR__ . '/../' . $picFile)
                      ? '../' . htmlspecialchars($picFile) : null;
        ?>
        <div class="at-testi-card">
          <div class="at-testi-card-header">
            <?php if ($picFull): ?>
              <img src="<?= $picFull ?>" alt="" class="at-testi-avatar"/>
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
              <form method="POST" style="display:contents;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <?php if ($t['status'] === 'rejected'): ?>
                  <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
                <?php endif; ?>
                <?php if ($t['status'] === 'approved'): ?>
                  <button type="submit" name="action" value="reject" class="at-action-btn reject">✗ Reject</button>
                <?php endif; ?>
                <button type="submit" name="action" value="delete" class="at-action-btn delete" onclick="return confirm('Delete this testimonial permanently?')">🗑</button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</main>
<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>