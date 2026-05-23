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
    header("Location: AdminTestimonials.php");
    exit;
}

$pending = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno
    FROM testimonials t
    JOIN students s ON s.id = t.student_id
    WHERE t.status = 'pending'
    ORDER BY t.created_at DESC
")->fetchAll();

$all = $db->query("
    SELECT t.*, s.first_name, s.last_name, s.student_id AS idno
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
      <table class="at-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID No.</th>
            <th>Message</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
            <td><?= htmlspecialchars($t['idno']) ?></td>
            <td class="msg-cell"><?= htmlspecialchars($t['message']) ?></td>
            <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
            <td>
              <form method="POST" style="display:inline;">
                <input type="hidden" name="id" value="<?= $t['id'] ?>"/>
                <button type="submit" name="action" value="approve" class="at-action-btn approve">✓ Approve</button>
                <button type="submit" name="action" value="reject"  class="at-action-btn reject">✗ Reject</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <hr class="divider-line"/>

    <div class="at-section-title">📋 All Testimonials</div>
    <div class="at-section-sub">Full history of all submitted testimonials and their statuses.</div>

    <?php if (empty($all)): ?>
      <div class="at-empty">No testimonials submitted yet.</div>
    <?php else: ?>
      <table class="at-table">
        <thead>
          <tr>
            <th>Student</th>
            <th>ID No.</th>
            <th>Message</th>
            <th>Status</th>
            <th>Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all as $t): ?>
          <tr>
            <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
            <td><?= htmlspecialchars($t['idno']) ?></td>
            <td class="msg-cell"><?= htmlspecialchars($t['message']) ?></td>
            <td><span class="status-badge <?= $t['status'] ?>"><?= ucfirst($t['status']) ?></span></td>
            <td><?= date('M d, Y', strtotime($t['created_at'])) ?></td>
            <td>
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
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

  </div>
</main>
<?php include __DIR__ . '/../footer.php'; ?>
</body>
</html>